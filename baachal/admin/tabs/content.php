<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get content indexer instance
global $wpdb;
$indexer = new Baachal_Content_Indexer();
$stats = $indexer->get_index_stats();

// Get all available post types
$post_types = get_post_types(array('public' => true), 'objects');
$indexable_types = get_option('baachal_indexable_post_types', array('post', 'page'));
?>

<div class="baachal-tab-content">
    <h3><?php esc_html_e('Content Indexing', 'baachal'); ?></h3>
    <p><?php esc_html_e('Configure which content should be indexed for the chatbot to search through. This allows your chatbot to answer questions about your website content, not just products.', 'baachal'); ?></p>
    
    <!-- Index Statistics -->
    <div class="baachal-stats-box" style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h4><?php esc_html_e('Index Statistics', 'baachal'); ?></h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <div>
                <strong><?php esc_html_e('Total Indexed Items:', 'baachal'); ?></strong><br>
                <span style="font-size: 24px; color: #0073aa;"><?php echo number_format($stats['total']); ?></span>
            </div>
            
            <?php if (!empty($stats['by_type'])): ?>
            <div>
                <strong><?php esc_html_e('By Content Type:', 'baachal'); ?></strong><br>
                <?php foreach ($stats['by_type'] as $type_stat): ?>
                    <div><?php echo esc_html(ucfirst($type_stat->post_type)); ?>: <?php echo number_format($type_stat->count); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['last_updated']): ?>
            <div>
                <strong><?php esc_html_e('Last Updated:', 'baachal'); ?></strong><br>
                <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($stats['last_updated']))); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="baachal_content_indexing_enabled"><?php esc_html_e('Enable Content Indexing', 'baachal'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" id="baachal_content_indexing_enabled" name="baachal_content_indexing_enabled" value="1" <?php checked(get_option('baachal_content_indexing_enabled', '1'), '1'); ?> />
                    <?php esc_html_e('Allow chatbot to search website content (pages, posts, etc.)', 'baachal'); ?>
                </label>
                <p class="description"><?php esc_html_e('When enabled, the chatbot can answer questions about your website content, not just products.', 'baachal'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php esc_html_e('Content Types to Index', 'baachal'); ?></label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><?php esc_html_e('Select content types to index', 'baachal'); ?></legend>
                    <?php foreach ($post_types as $post_type): ?>
                        <?php 
                        // Skip attachment and some system post types
                        if (in_array($post_type->name, array('attachment', 'revision', 'nav_menu_item'))) continue;
                        ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="baachal_indexable_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $indexable_types)); ?> />
                            <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                        </label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e('Select which types of content should be indexed for chatbot searches.', 'baachal'); ?></p>
                </fieldset>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="baachal_content_max_results"><?php esc_html_e('Max Search Results', 'baachal'); ?></label>
            </th>
            <td>
                <input type="number" id="baachal_content_max_results" name="baachal_content_max_results" value="<?php echo esc_attr(get_option('baachal_content_max_results', '5')); ?>" min="1" max="20" class="small-text" />
                <p class="description"><?php esc_html_e('Maximum number of content results to consider when answering questions (1-20).', 'baachal'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="baachal_auto_index"><?php esc_html_e('Auto Index', 'baachal'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" id="baachal_auto_index" name="baachal_auto_index" value="1" <?php checked(get_option('baachal_auto_index', '1'), '1'); ?> />
                    <?php esc_html_e('Automatically index content when it\'s created or updated', 'baachal'); ?>
                </label>
                <p class="description"><?php esc_html_e('When enabled, new and updated content will be automatically indexed for chatbot searches.', 'baachal'); ?></p>
            </td>
        </tr>
    </table>
    
    <h4><?php esc_html_e('Manual Actions', 'baachal'); ?></h4>
    <p style="margin-bottom: 15px;">
        <button type="button" id="reindex-content-btn" class="button button-secondary">
            <?php esc_html_e('Reindex All Content', 'baachal'); ?>
        </button>
        <span class="description" style="margin-left: 10px;"><?php esc_html_e('This will clear the current index and rebuild it from scratch.', 'baachal'); ?></span>
    </p>
    
    <!-- Content Search Test -->
    <div class="baachal-test-section" style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h4><?php esc_html_e('Test Content Search', 'baachal'); ?></h4>
        <p><?php esc_html_e('Test the content search functionality to see what results would be returned for a query.', 'baachal'); ?></p>
        
        <div id="content-search-test">
            <input type="text" id="test-query" placeholder="<?php esc_attr_e('Enter a search query...', 'baachal'); ?>" style="width: 300px; margin-right: 10px;" />
            <button type="button" id="test-search-btn" class="button"><?php esc_html_e('Test Search', 'baachal'); ?></button>
            <div id="search-results" style="margin-top: 15px;"></div>
        </div>
        
    </div>
</div>
