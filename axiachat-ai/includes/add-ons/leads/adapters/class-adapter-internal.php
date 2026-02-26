<?php
/**
 * Internal Database Adapter for Leads
 * 
 * Saves leads to the plugin's internal wp_aichat_leads table.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_Adapter_Internal {
    
    /**
     * Save lead to internal table
     * 
     * @param array $lead Lead data
     * @param array $settings Plugin settings
     * @return array Result
     */
    public function save( $lead, $settings = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        $data = [
            'session_id'      => $lead['session_id'] ?? '',
            'bot_slug'        => $lead['bot_slug'] ?? '',
            'conversation_id' => null, // Will be linked later if available
            'nombre'          => $lead['nombre'],
            'email'           => $lead['email'] ?? '',
            'telefono'        => $lead['telefono'] ?? '',
            'empresa'         => $lead['empresa'] ?? '',
            'interes'         => $lead['interes'] ?? '',
            'notas'           => $lead['notas'] ?? '',
            'campos_extra'    => ! empty( $lead['campos_extra'] ) ? wp_json_encode( $lead['campos_extra'] ) : null,
            'destino'         => 'internal',
            'destino_ref'     => null,
            'estado'          => 'nuevo',
            'ip_hash'         => $lead['ip_hash'] ?? null,
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ];
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table, $data, [
            '%s', // session_id
            '%s', // bot_slug
            '%d', // conversation_id
            '%s', // nombre
            '%s', // email
            '%s', // telefono
            '%s', // empresa
            '%s', // interes
            '%s', // notas
            '%s', // campos_extra
            '%s', // destino
            '%s', // destino_ref
            '%s', // estado
            '%s', // ip_hash
            '%s', // created_at
            '%s', // updated_at
        ] );
        
        if ( $result ) {
            return [
                'ok'      => true,
                'lead_id' => $wpdb->insert_id,
                'dest'    => 'internal',
            ];
        }
        
        return [
            'ok'    => false,
            'error' => 'db_insert_failed',
            'dest'  => 'internal',
        ];
    }
}

// Register adapter
AIChat_Leads_Manager::register_adapter( 'internal', new AIChat_Leads_Adapter_Internal() );
