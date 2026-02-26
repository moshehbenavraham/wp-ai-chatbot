<?php
/**
 * WPForms Adapter for Leads
 * 
 * Submits leads through WPForms.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_Adapter_WPForms {
    
    /**
     * Save lead via WPForms
     * 
     * Uses dynamic form fields directly from the lead data.
     * 
     * @param array $lead Lead data with 'fields' array containing form fields
     * @param array $settings Plugin settings
     * @return array Result
     */
    public function save( $lead, $settings = [] ) {
        
        // Debug logging
        if ( function_exists( 'aichat_log_debug' ) ) {
            aichat_log_debug( '[Leads WPForms] Adapter save() called', [
                'lead_keys'    => array_keys( $lead ),
                'has_fields'   => isset( $lead['fields'] ),
                'fields_data'  => $lead['fields'] ?? 'NOT SET',
                'settings_keys' => array_keys( $settings ),
            ] );
        }
        
        // Check if WPForms is active
        if ( ! function_exists( 'wpforms' ) ) {
            return [
                'ok'    => false,
                'error' => 'wpforms_not_active',
                'dest'  => 'wpforms',
            ];
        }
        
        $form_id = $settings['wpforms_form_id'] ?? 0;
        if ( ! $form_id ) {
            return [
                'ok'    => false,
                'error' => 'wpforms_form_not_configured',
                'dest'  => 'wpforms',
            ];
        }
        
        // Get dynamic fields from lead data
        $lead_fields = $lead['fields'] ?? [];
        if ( empty( $lead_fields ) ) {
            if ( function_exists( 'aichat_log_debug' ) ) {
                aichat_log_debug( '[Leads WPForms] No fields in lead data', [ 'lead' => $lead ] );
            }
            return [
                'ok'    => false,
                'error' => 'no_fields_provided',
                'dest'  => 'wpforms',
            ];
        }
        
        // Get form field definitions to map names to IDs
        $form = wpforms()->form->get( $form_id );
        if ( ! $form ) {
            return [
                'ok'    => false,
                'error' => 'wpforms_form_not_found',
                'dest'  => 'wpforms',
            ];
        }
        
        $form_data = wpforms_decode( $form->post_content );
        $field_name_to_id = [];
        
        if ( ! empty( $form_data['fields'] ) ) {
            foreach ( $form_data['fields'] as $field ) {
                // Create a sanitized key from the label
                $key = sanitize_key( str_replace( ' ', '_', strtolower( $field['label'] ) ) );
                $field_name_to_id[ $key ] = $field['id'];
            }
        }
        
        // Build entry fields array
        $fields = [];
        foreach ( $lead_fields as $field_name => $value ) {
            // Try to find the field ID
            $field_id = $field_name_to_id[ $field_name ] ?? null;
            
            if ( $field_id !== null && ! empty( $value ) ) {
                $fields[ $field_id ] = [
                    'id'    => $field_id,
                    'value' => $value,
                ];
            }
        }
        
        if ( empty( $fields ) ) {
            return [
                'ok'    => false,
                'error' => 'wpforms_no_fields_matched',
                'dest'  => 'wpforms',
            ];
        }
        
        // Create entry
        try {
            $entry_data = [
                'form_id' => $form_id,
                'fields'  => $fields,
                'date'    => current_time( 'mysql' ),
                'meta'    => [
                    'aichat_lead' => true,
                    'bot_slug'    => $lead['bot_slug'] ?? '',
                ],
            ];
            
            // WPForms Pro has entries, Lite does not
            if ( function_exists( 'wpforms_get_entries_table_name' ) ) {
                // Use WPForms entry system
                $entry_id = wpforms()->entry->add( $entry_data );
                
                if ( $entry_id ) {
                    return [
                        'ok'       => true,
                        'entry_id' => $entry_id,
                        'dest'     => 'wpforms',
                    ];
                }
            } else {
                // Lite version - just send notifications
                // Trigger form processing for notifications
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPForms core hook.
                do_action( 'wpforms_process_complete', $fields, [], [], $form_id );
                
                return [
                    'ok'   => true,
                    'note' => 'wpforms_lite_processed',
                    'dest' => 'wpforms',
                ];
            }
            
        } catch ( \Exception $e ) {
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
                'dest'  => 'wpforms',
            ];
        }
        
        return [
            'ok'    => false,
            'error' => 'wpforms_save_failed',
            'dest'  => 'wpforms',
        ];
    }
    
    /**
     * Get available WPForms forms
     */
    public static function get_forms() {
        if ( ! function_exists( 'wpforms' ) ) {
            return [];
        }
        
        $forms = wpforms()->form->get( '', [ 'posts_per_page' => -1 ] );
        
        if ( empty( $forms ) ) {
            return [];
        }
        
        $result = [];
        foreach ( $forms as $form ) {
            $result[] = [
                'id'    => $form->ID,
                'title' => $form->post_title,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get form fields for mapping
     */
    public static function get_form_fields( $form_id ) {
        if ( ! function_exists( 'wpforms' ) ) {
            return [];
        }
        
        $form = wpforms()->form->get( $form_id );
        if ( ! $form ) {
            return [];
        }
        
        $form_data = wpforms_decode( $form->post_content );
        $fields = [];
        
        if ( ! empty( $form_data['fields'] ) ) {
            foreach ( $form_data['fields'] as $field ) {
                $fields[] = [
                    'id'    => $field['id'],
                    'label' => $field['label'],
                    'type'  => $field['type'],
                ];
            }
        }
        
        return $fields;
    }
}

// Register adapter if WPForms is available - use plugins_loaded to ensure WPForms is loaded
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'wpforms' ) || class_exists( 'WPForms' ) ) {
        AIChat_Leads_Manager::register_adapter( 'wpforms', new AIChat_Leads_Adapter_WPForms() );
        
        if ( function_exists( 'aichat_log_debug' ) ) {
            aichat_log_debug( '[Leads] WPForms adapter registered' );
        }
    }
}, 20 );
