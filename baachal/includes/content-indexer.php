<?php
/**
 * Baachal Content Indexer
 * Indexes website content for chatbot searches
 */

if (!defined('ABSPATH')) {
    exit;
}

class Baachal_Content_Indexer {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'baachal_content_index';
        
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('save_post', array($this, 'index_post'), 10, 1);
        add_action('delete_post', array($this, 'delete_post_index'), 10, 1);
    }
    
    public function init() {
        $this->create_index_table();
    }
    
    /**
     * Create the content index table
     */
    public function create_index_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            post_type varchar(20) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            excerpt text,
            url varchar(500) NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY post_type (post_type),
            FULLTEXT KEY search_content (title, content, excerpt)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Index all content based on settings
     */
    public function index_all_content() {
        global $wpdb;
        
        // Define flag for manual indexing
        define('BAACHAL_MANUAL_INDEX', true);
        
        // Clear existing index
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        // Get indexable post types from settings
        $indexable_types = get_option('baachal_indexable_post_types', array('post', 'page'));
        
        // Default to common post types if none set
        if (empty($indexable_types)) {
            $indexable_types = array('post', 'page');
        }
        
        $indexed_count = 0;
        
        foreach ($indexable_types as $post_type) {
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_baachal_exclude_from_index',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ));
            
            foreach ($posts as $post) {
                if ($this->index_post($post->ID)) {
                    $indexed_count++;
                }
            }
        }
        
        return $indexed_count;
    }
    
    /**
     * Index a single post
     */
    public function index_post($post_id) {
        global $wpdb;
        
        // Skip if auto indexing is disabled (unless this is a manual reindex)
        if (!defined('BAACHAL_MANUAL_INDEX') && get_option('baachal_auto_index', '1') !== '1') {
            return false;
        }
        
        // Skip if not a valid post
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return false;
        }
        
        // Skip if excluded from index
        if (get_post_meta($post_id, '_baachal_exclude_from_index', true)) {
            return false;
        }
        
        // Get indexable post types
        $indexable_types = get_option('baachal_indexable_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $indexable_types)) {
            return false;
        }
        
        // Prepare content
        $title = $post->post_title;
        $content = $this->clean_content($post->post_content);
        $excerpt = $post->post_excerpt ? $post->post_excerpt : wp_trim_words($content, 30);
        $url = get_permalink($post_id);
        
        // Remove existing index for this post
        $wpdb->delete($this->table_name, array('post_id' => $post_id), array('%d'));
        
        // Insert new index
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'title' => $title,
                'content' => $content,
                'excerpt' => $excerpt,
                'url' => $url
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete post from index
     */
    public function delete_post_index($post_id) {
        global $wpdb;
        $wpdb->delete($this->table_name, array('post_id' => $post_id), array('%d'));
    }
    
    /**
     * Search indexed content
     */
    public function search_content($query, $limit = 5) {
        global $wpdb;
        
        $search_terms = $this->prepare_search_terms($query);
        
        if (empty($search_terms)) {
            return array();
        }
        
        // Build search query using FULLTEXT search
        $search_query = "
            SELECT *, 
                   MATCH(title, content, excerpt) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance
            FROM {$this->table_name}
            WHERE MATCH(title, content, excerpt) AGAINST(%s IN NATURAL LANGUAGE MODE)
            ORDER BY relevance DESC, title ASC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($search_query, $query, $query, $limit)
        );
        
        // If no results with fulltext, try LIKE search
        if (empty($results)) {
            $like_query = "
                SELECT *
                FROM {$this->table_name}
                WHERE title LIKE %s 
                   OR content LIKE %s 
                   OR excerpt LIKE %s
                ORDER BY 
                    CASE 
                        WHEN title LIKE %s THEN 1
                        WHEN excerpt LIKE %s THEN 2
                        ELSE 3
                    END,
                    title ASC
                LIMIT %d
            ";
            
            $like_term = '%' . $wpdb->esc_like($query) . '%';
            $title_term = '%' . $wpdb->esc_like($query) . '%';
            $excerpt_term = '%' . $wpdb->esc_like($query) . '%';
            
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    $like_query, 
                    $like_term, $like_term, $like_term, 
                    $title_term, $excerpt_term, 
                    $limit
                )
            );
        }
        
        return $this->format_search_results($results);
    }
    
    /**
     * Clean content for indexing
     */
    private function clean_content($content) {
        // Remove shortcodes
        $content = strip_shortcodes($content);
        
        // Remove HTML tags
        $content = wp_strip_all_tags($content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Prepare search terms
     */
    private function prepare_search_terms($query) {
        // Remove special characters
        $query = preg_replace('/[^\w\s]/', '', $query);
        
        // Split into words
        $words = array_filter(explode(' ', strtolower($query)));
        
        // Remove common stop words
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must');
        $words = array_diff($words, $stop_words);
        
        return $words;
    }
    
    /**
     * Format search results for chatbot
     */
    private function format_search_results($results) {
        $formatted = array();
        
        foreach ($results as $result) {
            $formatted[] = array(
                'title' => $result->title,
                'excerpt' => $result->excerpt,
                'url' => $result->url,
                'post_type' => $result->post_type,
                'relevance' => isset($result->relevance) ? $result->relevance : 0
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get index statistics
     */
    public function get_index_stats() {
        global $wpdb;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        $by_type = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count 
            FROM {$this->table_name} 
            GROUP BY post_type 
            ORDER BY count DESC
        ");
        
        $last_updated = $wpdb->get_var("
            SELECT MAX(last_updated) 
            FROM {$this->table_name}
        ");
        
        return array(
            'total' => (int) $total,
            'by_type' => $by_type,
            'last_updated' => $last_updated
        );
    }
}

// Initialize the content indexer
new Baachal_Content_Indexer();