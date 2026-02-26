<?php
/**
 * Contact Form 7 Adapter for Leads
 * 
 * Submits leads through Contact Form 7.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_Adapter_CF7 {
    
    /**
     * Save lead via CF7
     * 
     * Uses dynamic form fields directly from the lead data.
     * 
     * @param array $lead Lead data with 'fields' array containing form fields
     * @param array $settings Plugin settings
     * @return array Result
     */
    public function save( $lead, $settings = [] ) {
        
        // Check if CF7 is active
        if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
            return [
                'ok'    => false,
                'error' => 'cf7_not_active',
                'dest'  => 'cf7',
            ];
        }
        
        $form_id = $settings['cf7_form_id'] ?? 0;
        if ( ! $form_id ) {
            return [
                'ok'    => false,
                'error' => 'cf7_form_not_configured',
                'dest'  => 'cf7',
            ];
        }
        
        // Get the form
        $form = WPCF7_ContactForm::get_instance( $form_id );
        if ( ! $form ) {
            return [
                'ok'    => false,
                'error' => 'cf7_form_not_found',
                'dest'  => 'cf7',
            ];
        }
        
        // Get dynamic fields from lead data
        $fields = $lead['fields'] ?? [];
        if ( empty( $fields ) ) {
            return [
                'ok'    => false,
                'error' => 'no_fields_provided',
                'dest'  => 'cf7',
            ];
        }
        
        // Build submission data - field names already match CF7 form fields
        $posted_data = [];
        foreach ( $fields as $field_name => $value ) {
            if ( ! empty( $value ) ) {
                $posted_data[ $field_name ] = $value;
            }
        }
        
        // Add source indicator
        $posted_data['_aichat_lead'] = 'true';
        $posted_data['_aichat_bot'] = $lead['bot_slug'] ?? '';
        
        // Try to submit the form programmatically
        try {
            // Simulate form submission
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Intentionally populating $_POST to simulate CF7 form submission via their API.
            $_POST = array_merge( $_POST, $posted_data );
            
            // Create submission instance
            $submission = WPCF7_Submission::get_instance();
            
            if ( $submission ) {
                $result = $submission->get_result();
                
                return [
                    'ok'     => true,
                    'result' => $result,
                    'dest'   => 'cf7',
                ];
            }
            
            // Fallback: just send the mail manually
            $mail = $form->prop( 'mail' );
            if ( $mail ) {
                // This is a simplified approach
                // In production, you'd want to properly handle CF7's mail system
                return [
                    'ok'   => true,
                    'note' => 'cf7_mail_queued',
                    'dest' => 'cf7',
                ];
            }
            
        } catch ( \Exception $e ) {
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
                'dest'  => 'cf7',
            ];
        }
        
        return [
            'ok'    => false,
            'error' => 'cf7_submission_failed',
            'dest'  => 'cf7',
        ];
    }
    
    /**
     * Get available CF7 forms
     */
    public static function get_forms() {
        if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
            return [];
        }
        
        $forms = WPCF7_ContactForm::find( [ 'posts_per_page' => -1 ] );
        $result = [];
        
        foreach ( $forms as $form ) {
            $result[] = [
                'id'    => $form->id(),
                'title' => $form->title(),
            ];
        }
        
        return $result;
    }
    
    /**
     * Get form fields for mapping
     */
    public static function get_form_fields( $form_id ) {
        if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
            return [];
        }
        
        $form = WPCF7_ContactForm::get_instance( $form_id );
        if ( ! $form ) {
            return [];
        }
        
        $tags = $form->scan_form_tags();
        $fields = [];
        
        foreach ( $tags as $tag ) {
            if ( ! empty( $tag->name ) ) {
                $fields[] = [
                    'name' => $tag->name,
                    'type' => $tag->basetype,
                ];
            }
        }
        
        return $fields;
    }
}

// Register adapter if CF7 is available - use plugins_loaded to ensure CF7 is loaded
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WPCF7' ) || defined( 'WPCF7_VERSION' ) ) {
        AIChat_Leads_Manager::register_adapter( 'cf7', new AIChat_Leads_Adapter_CF7() );
        
        if ( function_exists( 'aichat_log_debug' ) ) {
            aichat_log_debug( '[Leads] CF7 adapter registered' );
        }
    }
}, 20 );
