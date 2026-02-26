<?php
/**
 * Uninstall Baachal AI Chatbot
 *
 * @package Baachal
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Call the main plugin uninstall method
if (class_exists('Baachal')) {
    Baachal::uninstall_plugin();
} else {
    // Fallback cleanup if class is not available
    
    // Check if we should preserve data
    $preserve_data = get_option('baachal_preserve_data_on_uninstall', false);
    
    if (!$preserve_data) {
        // Delete all plugin options using WordPress API
        $plugin_options = array(
            'baachal_ai_provider',
            'baachal_gemini_api_key',
            'baachal_openai_api_key',
            'baachal_claude_api_key',
            'baachal_grok_api_key',
            'baachal_enabled',
            'baachal_title',
            'baachal_welcome_message',
            'baachal_ai_model',
            'baachal_debug_mode',
            'baachal_woocommerce_integration',
            'baachal_message_persistence',
            'baachal_show_clear_history',
            'baachal_max_terms',
            'baachal_min_term_length',
            'baachal_cache_duration',
            'baachal_exclude_terms',
            'baachal_primary_color',
            'baachal_secondary_color',
            'baachal_position',
            'baachal_size',
            'baachal_border_radius',
            'baachal_chat_height',
            'baachal_font_size',
            'baachal_animation_enabled',
            'baachal_content_indexing_enabled',
            'baachal_auto_index',
            'baachal_preserve_data_on_uninstall',
            'baachal_indexable_post_types',
            'baachal_content_max_results'
        );
        
        // Delete each option using WordPress API
        foreach ($plugin_options as $option_name) {
            delete_option($option_name);
        }
        
        // Delete content index table (direct DB call is acceptable for table dropping in uninstall)
        global $wpdb;
        $table_name = 'baachal_content_index';
        $full_table_name = $wpdb->prefix . $table_name;
        $escaped_table = '`' . esc_sql($full_table_name) . '`';
        // Use direct query for table dropping - no prepare() needed for DDL statements
        // Schema changes are acceptable in uninstall files
        $wpdb->query("DROP TABLE IF EXISTS " . $escaped_table); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
        
        // Delete all conversations using WordPress API
        $conversations = get_posts(array(
            'post_type' => 'baachal_conversation',
            'numberposts' => -1,
            'post_status' => 'any',
            'fields' => 'ids' // Only get IDs for better performance
        ));
        
        foreach ($conversations as $conversation_id) {
            wp_delete_post($conversation_id, true);
        }
        
        // Clear all plugin transients using WordPress API
        $transient_keys = array(
            'baachal_dynamic_terms',
            'baachal_content_index_stats',
            'baachal_woocommerce_products'
        );
        
        foreach ($transient_keys as $transient_key) {
            delete_transient($transient_key);
        }
        
        // Clean up any remaining plugin transients with direct query (with proper comments)
        // Use prepared statement with proper escaping for LIKE patterns
        $like_pattern_1 = $wpdb->esc_like('_transient_baachal_') . '%';
        $like_pattern_2 = $wpdb->esc_like('_transient_timeout_baachal_') . '%';
        
        // Direct database call is necessary for cleanup in uninstall context
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like_pattern_1,
                $like_pattern_2
            )
        );
    }
}
