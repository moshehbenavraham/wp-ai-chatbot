<?php
/**
 * Google Sheets Adapter for Leads
 * 
 * Saves leads to a Google Spreadsheet.
 * Also saves a copy to the internal database for backup.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_Adapter_GSheets {
    
    /** Default headers for the spreadsheet */
    const DEFAULT_HEADERS = [
        'Date',
        'Name',
        'Email',
        'Phone',
        'Company',
        'Interest',
        'Notes',
        'Bot',
        'Status',
    ];
    
    /**
     * Save lead to Google Sheets
     * 
     * @param array $lead Lead data
     * @param array $settings Plugin settings
     * @return array Result
     */
    public function save( $lead, $settings = [] ) {
        // Get spreadsheet settings
        $spreadsheet_url = get_option( 'aichat_leads_gsheets_spreadsheet_id', '' );
        $sheet_name      = get_option( 'aichat_leads_gsheets_sheet_name', '' );
        
        if ( empty( $spreadsheet_url ) ) {
            aichat_log_debug( '[GSheets Adapter] No spreadsheet configured' );
            return [
                'ok'    => false,
                'error' => 'no_spreadsheet_configured',
                'dest'  => 'google_sheets',
            ];
        }
        
        // Extract ID from URL if needed
        $spreadsheet_id = AIChat_Leads_GSheets_Client::extract_spreadsheet_id( $spreadsheet_url );
        
        // Check if connected
        if ( ! AIChat_Leads_GSheets_OAuth::is_connected() ) {
            aichat_log_debug( '[GSheets Adapter] Not connected to Google Sheets' );
            return [
                'ok'    => false,
                'error' => 'not_connected',
                'dest'  => 'google_sheets',
            ];
        }
        
        // If no sheet name specified, get the first sheet
        if ( empty( $sheet_name ) ) {
            $sheet_name = AIChat_Leads_GSheets_Client::get_first_sheet_name( $spreadsheet_id );
            if ( is_wp_error( $sheet_name ) ) {
                aichat_log_debug( '[GSheets Adapter] Could not get first sheet name', [ 'error' => $sheet_name->get_error_message() ] );
                $sheet_name = 'Sheet1'; // Fallback
            }
        }
        
        // Ensure headers exist
        AIChat_Leads_GSheets_Client::ensure_headers( $spreadsheet_id, $sheet_name, self::DEFAULT_HEADERS );
        
        // Prepare row data
        $row = [
            current_time( 'Y-m-d H:i:s' ),           // Date
            $lead['nombre'] ?? '',                    // Name
            $lead['email'] ?? '',                     // Email
            $lead['telefono'] ?? '',                  // Phone
            $lead['empresa'] ?? '',                   // Company
            $lead['interes'] ?? '',                   // Interest
            $lead['notas'] ?? '',                     // Notes
            $lead['bot_slug'] ?? '',                  // Bot
            'nuevo',                                  // Status
        ];
        
        aichat_log_debug( '[GSheets Adapter] Saving lead', [
            'spreadsheet_id' => $spreadsheet_id,
            'sheet_name'     => $sheet_name,
            'lead_name'      => $lead['nombre'] ?? 'unknown',
        ] );
        
        // Append to Google Sheets
        $result = AIChat_Leads_GSheets_Client::append_row( $spreadsheet_id, $sheet_name, $row );
        
        if ( is_wp_error( $result ) ) {
            aichat_log_debug( '[GSheets Adapter] Failed to save to Sheets', [ 'error' => $result->get_error_message() ] );
            return [
                'ok'    => false,
                'error' => $result->get_error_message(),
                'dest'  => 'google_sheets',
            ];
        }
        
        // Also save to internal database as backup
        $internal_id = $this->save_internal_backup( $lead, $spreadsheet_id );
        
        aichat_log_debug( '[GSheets Adapter] Lead saved successfully', [
            'internal_id' => $internal_id,
        ] );
        
        return [
            'ok'      => true,
            'lead_id' => $internal_id,
            'dest'    => 'google_sheets',
        ];
    }
    
    /**
     * Save a backup copy to internal database
     */
    private function save_internal_backup( $lead, $spreadsheet_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        $data = [
            'session_id'      => $lead['session_id'] ?? '',
            'bot_slug'        => $lead['bot_slug'] ?? '',
            'conversation_id' => null,
            'nombre'          => $lead['nombre'] ?? '',
            'email'           => $lead['email'] ?? '',
            'telefono'        => $lead['telefono'] ?? '',
            'empresa'         => $lead['empresa'] ?? '',
            'interes'         => $lead['interes'] ?? '',
            'notas'           => $lead['notas'] ?? '',
            'campos_extra'    => ! empty( $lead['campos_extra'] ) ? wp_json_encode( $lead['campos_extra'] ) : null,
            'destino'         => 'google_sheets',
            'destino_ref'     => $spreadsheet_id,
            'estado'          => 'nuevo',
            'ip_hash'         => $lead['ip_hash'] ?? null,
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ];
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert( $table, $data );
        
        return $wpdb->insert_id;
    }
}

// Register adapter
AIChat_Leads_Manager::register_adapter( 'google_sheets', new AIChat_Leads_Adapter_GSheets() );
