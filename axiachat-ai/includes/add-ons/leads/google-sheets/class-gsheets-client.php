<?php
/**
 * Google Sheets API Client for Leads
 * 
 * Handles all Google Sheets API operations.
 * Only writes data - no read or delete permissions needed.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_GSheets_Client {
    
    /** API Base URL */
    const API_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';
    
    /**
     * Extract spreadsheet ID from URL or return as-is if already an ID
     * 
     * @param string $url_or_id URL or spreadsheet ID
     * @return string Spreadsheet ID
     */
    public static function extract_spreadsheet_id( $url_or_id ) {
        // If it looks like a URL, extract the ID
        if ( strpos( $url_or_id, 'docs.google.com' ) !== false || strpos( $url_or_id, 'sheets.google.com' ) !== false ) {
            // Pattern: /spreadsheets/d/SPREADSHEET_ID/
            if ( preg_match( '/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $url_or_id, $matches ) ) {
                return $matches[1];
            }
        }
        
        // Already an ID or unrecognized format - return as-is
        return trim( $url_or_id );
    }
    
    /**
     * Make authenticated API request
     */
    private static function request( $endpoint, $method = 'GET', $body = null ) {
        $access_token = AIChat_Leads_GSheets_OAuth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
        ];
        
        if ( $body !== null ) {
            $args['body'] = wp_json_encode( $body );
        }
        
        $response = wp_remote_request( $endpoint, $args );
        
        if ( is_wp_error( $response ) ) {
            aichat_log_debug( '[GSheets Client] Request error', [ 'error' => $response->get_error_message() ] );
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $code >= 400 ) {
            $error_message = $body['error']['message'] ?? 'Unknown error';
            aichat_log_debug( '[GSheets Client] API error', [
                'code'    => $code,
                'message' => $error_message,
            ] );
            return new WP_Error( 'api_error', $error_message );
        }
        
        return $body;
    }
    
    /**
     * Get spreadsheet info (to verify access)
     */
    public static function get_spreadsheet( $spreadsheet_id ) {
        // Extract ID from URL if needed
        $spreadsheet_id = self::extract_spreadsheet_id( $spreadsheet_id );
        $endpoint = self::API_BASE . '/' . rawurlencode( $spreadsheet_id );
        return self::request( $endpoint );
    }
    
    /**
     * Get sheet names from a spreadsheet
     */
    public static function get_sheets( $spreadsheet_id ) {
        // Extract ID from URL if needed
        $spreadsheet_id = self::extract_spreadsheet_id( $spreadsheet_id );
        $result = self::get_spreadsheet( $spreadsheet_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $sheets = [];
        if ( isset( $result['sheets'] ) ) {
            foreach ( $result['sheets'] as $sheet ) {
                $sheets[] = [
                    'id'    => $sheet['properties']['sheetId'],
                    'title' => $sheet['properties']['title'],
                ];
            }
        }
        
        return $sheets;
    }
    
    /**
     * Get the name of the first sheet in a spreadsheet
     * 
     * @param string $spreadsheet_id The spreadsheet ID or URL
     * @return string|WP_Error Sheet name or error
     */
    public static function get_first_sheet_name( $spreadsheet_id ) {
        $sheets = self::get_sheets( $spreadsheet_id );
        
        if ( is_wp_error( $sheets ) ) {
            return $sheets;
        }
        
        if ( empty( $sheets ) ) {
            return new WP_Error( 'no_sheets', __( 'No sheets found in spreadsheet', 'axiachat-ai' ) );
        }
        
        return $sheets[0]['title'];
    }
    
    /**
     * Append a row to a sheet
     * 
     * @param string $spreadsheet_id The spreadsheet ID or URL
     * @param string $sheet_name The sheet/tab name (default: Sheet1)
     * @param array $values Array of values for the row
     * @return array|WP_Error
     */
    public static function append_row( $spreadsheet_id, $sheet_name, $values ) {
        // Extract ID from URL if needed
        $spreadsheet_id = self::extract_spreadsheet_id( $spreadsheet_id );
        
        // Build range with proper encoding for sheet names with spaces
        // Sheet names with spaces need single quotes: 'Sheet Name'!A:Z
        $quoted_sheet = self::quote_sheet_name( $sheet_name );
        $range = rawurlencode( $quoted_sheet . '!A:Z' );
        
        $endpoint = self::API_BASE . '/' . rawurlencode( $spreadsheet_id ) 
                  . '/values/' . $range . ':append'
                  . '?valueInputOption=USER_ENTERED'
                  . '&insertDataOption=INSERT_ROWS';
        
        $body = [
            'values' => [ $values ],
        ];
        
        aichat_log_debug( '[GSheets Client] Appending row', [
            'spreadsheet_id' => $spreadsheet_id,
            'sheet_name'     => $sheet_name,
            'range'          => $quoted_sheet . '!A:Z',
            'values_count'   => count( $values ),
        ] );
        
        $result = self::request( $endpoint, 'POST', $body );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        aichat_log_debug( '[GSheets Client] Row appended successfully', [
            'updates' => $result['updates'] ?? [],
        ] );
        
        return $result;
    }
    
    /**
     * Write header row if sheet is empty
     */
    public static function ensure_headers( $spreadsheet_id, $sheet_name, $headers ) {
        // Extract ID from URL if needed
        $spreadsheet_id = self::extract_spreadsheet_id( $spreadsheet_id );
        
        // Check if first row has data
        $quoted_sheet = self::quote_sheet_name( $sheet_name );
        $range = rawurlencode( $quoted_sheet . '!A1:A1' );
        $endpoint = self::API_BASE . '/' . rawurlencode( $spreadsheet_id ) 
                  . '/values/' . $range;
        
        $result = self::request( $endpoint );
        
        // If no values or error, write headers
        if ( is_wp_error( $result ) || empty( $result['values'] ) ) {
            return self::write_headers( $spreadsheet_id, $sheet_name, $headers );
        }
        
        return true;
    }
    
    /**
     * Write header row
     */
    public static function write_headers( $spreadsheet_id, $sheet_name, $headers ) {
        // Extract ID from URL if needed
        $spreadsheet_id = self::extract_spreadsheet_id( $spreadsheet_id );
        
        $quoted_sheet = self::quote_sheet_name( $sheet_name );
        $range = rawurlencode( $quoted_sheet . '!A1' );
        
        $endpoint = self::API_BASE . '/' . rawurlencode( $spreadsheet_id ) 
                  . '/values/' . $range
                  . '?valueInputOption=RAW';
        
        $body = [
            'values' => [ $headers ],
        ];
        
        return self::request( $endpoint, 'PUT', $body );
    }
    
    /**
     * Quote sheet name if it contains spaces or special characters
     * 
     * @param string $sheet_name Sheet name
     * @return string Quoted sheet name if needed
     */
    private static function quote_sheet_name( $sheet_name ) {
        // If sheet name contains spaces or special chars, wrap in single quotes
        // Also escape any existing single quotes by doubling them
        if ( preg_match( '/[\s\'\!]/', $sheet_name ) ) {
            $escaped = str_replace( "'", "''", $sheet_name );
            return "'" . $escaped . "'";
        }
        return $sheet_name;
    }
    
    /**
     * Test connection to a specific spreadsheet
     */
    public static function test_connection( $spreadsheet_id ) {
        // Extract ID from URL if needed
        $spreadsheet_id = self::extract_spreadsheet_id( $spreadsheet_id );
        
        $result = self::get_spreadsheet( $spreadsheet_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return [
            'success' => true,
            'title'   => $result['properties']['title'] ?? 'Unknown',
            'sheets'  => self::get_sheets( $spreadsheet_id ),
        ];
    }
}
