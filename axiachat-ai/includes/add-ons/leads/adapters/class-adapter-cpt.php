<?php
/**
 * Custom Post Type Adapter for Leads
 * 
 * Saves leads as a WordPress Custom Post Type for native integration.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_Adapter_CPT {
    
    /**
     * CPT name
     */
    const POST_TYPE = 'aichat_lead';
    
    /**
     * Constructor - register CPT
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ], 5 );
    }
    
    /**
     * Register the custom post type
     */
    public function register_post_type() {
        $settings = AIChat_Leads_Manager::get_settings();
        $destinations = $settings['destinations'] ?? [];
        
        // Only register if CPT destination is enabled
        if ( ! in_array( 'cpt', $destinations, true ) ) {
            return;
        }
        
        $labels = [
            'name'               => __( 'Leads', 'axiachat-ai' ),
            'singular_name'      => __( 'Lead', 'axiachat-ai' ),
            'menu_name'          => __( 'Leads (CPT)', 'axiachat-ai' ),
            'add_new'            => __( 'Add New', 'axiachat-ai' ),
            'add_new_item'       => __( 'Add New Lead', 'axiachat-ai' ),
            'edit_item'          => __( 'Edit Lead', 'axiachat-ai' ),
            'new_item'           => __( 'New Lead', 'axiachat-ai' ),
            'view_item'          => __( 'View Lead', 'axiachat-ai' ),
            'search_items'       => __( 'Search Leads', 'axiachat-ai' ),
            'not_found'          => __( 'No leads found', 'axiachat-ai' ),
            'not_found_in_trash' => __( 'No leads found in trash', 'axiachat-ai' ),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false, // We'll add our own menu
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => [ 'title', 'custom-fields' ],
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'show_in_rest'        => false,
        ];
        
        register_post_type( self::POST_TYPE, $args );
    }
    
    /**
     * Save lead as CPT
     * 
     * @param array $lead Lead data
     * @param array $settings Plugin settings
     * @return array Result
     */
    public function save( $lead, $settings = [] ) {
        
        // Create post
        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => $lead['nombre'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
        ];
        
        $post_id = wp_insert_post( $post_data, true );
        
        if ( is_wp_error( $post_id ) ) {
            return [
                'ok'    => false,
                'error' => $post_id->get_error_message(),
                'dest'  => 'cpt',
            ];
        }
        
        // Save meta fields
        update_post_meta( $post_id, '_lead_email', $lead['email'] ?? '' );
        update_post_meta( $post_id, '_lead_telefono', $lead['telefono'] ?? '' );
        update_post_meta( $post_id, '_lead_empresa', $lead['empresa'] ?? '' );
        update_post_meta( $post_id, '_lead_interes', $lead['interes'] ?? '' );
        update_post_meta( $post_id, '_lead_notas', $lead['notas'] ?? '' );
        update_post_meta( $post_id, '_lead_bot_slug', $lead['bot_slug'] ?? '' );
        update_post_meta( $post_id, '_lead_session_id', $lead['session_id'] ?? '' );
        update_post_meta( $post_id, '_lead_estado', 'nuevo' );
        
        if ( ! empty( $lead['campos_extra'] ) ) {
            update_post_meta( $post_id, '_lead_campos_extra', $lead['campos_extra'] );
        }
        
        return [
            'ok'      => true,
            'post_id' => $post_id,
            'dest'    => 'cpt',
        ];
    }
}

// Register adapter
AIChat_Leads_Manager::register_adapter( 'cpt', new AIChat_Leads_Adapter_CPT() );
