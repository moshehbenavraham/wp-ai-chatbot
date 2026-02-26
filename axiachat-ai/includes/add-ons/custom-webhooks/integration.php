<?php
/**
 * Custom Webhooks Integration with AI Tools System
 * 
 * Registers each custom webhook as a tool and macro so they appear
 * in the Capabilities list and can be enabled per bot.
 * 
 * @package AIChat
 * @subpackage CustomWebhooks
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Register custom webhooks as tools and macros
 */
add_action( 'init', 'aichat_custom_webhooks_register_tools', 26 );
function aichat_custom_webhooks_register_tools() {
    // Only if tool registration is available
    if ( ! function_exists( 'aichat_register_tool_safe' ) ) {
        return;
    }
    
    $webhooks = aichat_get_custom_webhooks( true ); // Active only
    
    foreach ( $webhooks as $wh ) {
        $tool_name = 'webhook_' . $wh['slug'];
        
        // Build schema from params_schema
        $schema = aichat_custom_webhooks_build_schema( $wh );
        
        // Register atomic tool
        aichat_register_tool_safe( $tool_name, [
            'type' => 'function',
            'name' => $tool_name,
            'description' => $wh['description_ai'],
            'activity_label' => $wh['activity_label'] ?: __( 'Processing action...', 'axiachat-ai' ),
            'schema' => $schema,
            'callback' => function( $args, $context ) use ( $wh ) {
                return aichat_custom_webhook_callback( $wh, $args, $context );
            },
            'timeout' => 15,
            'parallel' => false,
            'max_calls' => 1,
        ] );
        
        // Register macro for Capabilities UI
        if ( function_exists( 'aichat_register_macro' ) ) {
            aichat_register_macro( [
                'name' => 'custom_webhook_' . $wh['slug'],
                'label' => $wh['label'],
                'description' => $wh['description_ai'],
                'tools' => [ $tool_name ],
                'source' => 'custom_webhook',
                'source_ref' => $wh['slug'],
            ] );
        }
    }
}

/**
 * Build OpenAI-compatible schema from webhook params
 */
function aichat_custom_webhooks_build_schema( $wh ) {
    $params_schema = $wh['params_schema'] ?? [];
    
    if ( empty( $params_schema ) || ! is_array( $params_schema ) ) {
        // No parameters defined - empty schema
        return [
            'type' => 'object',
            'properties' => new stdClass(), // Empty object for JSON
            'required' => [],
            'additionalProperties' => false,
        ];
    }
    
    $properties = [];
    $required = [];
    
    foreach ( $params_schema as $param ) {
        $name = $param['name'] ?? '';
        if ( empty( $name ) ) continue;
        
        $type = $param['type'] ?? 'string';
        $desc = $param['description'] ?? '';
        $is_required = ! empty( $param['required'] );
        
        $prop = [
            'type' => $type,
            'description' => $desc,
        ];
        
        // Handle enum type
        if ( $type === 'enum' && ! empty( $param['values'] ) ) {
            $prop['type'] = 'string';
            $prop['enum'] = array_map( 'trim', explode( ',', $param['values'] ) );
        }
        
        $properties[ $name ] = $prop;
        
        if ( $is_required ) {
            $required[] = $name;
        }
    }
    
    return [
        'type' => 'object',
        'properties' => $properties ?: new stdClass(),
        'required' => $required,
        'additionalProperties' => false,
    ];
}

/**
 * Callback for custom webhook tool execution
 */
function aichat_custom_webhook_callback( $wh, $args, $context ) {
    // Get conversation history if needed
    if ( ! empty( $wh['include_history'] ) ) {
        $history = aichat_custom_webhooks_get_history( $context );
        $context['history'] = $history;
    }
    
    // Execute webhook
    $result = AIChat_Webhook_Executor::execute( $wh, $args, $context );
    
    return $result;
}

/**
 * Get conversation history for webhook payload
 */
function aichat_custom_webhooks_get_history( $context ) {
    $session_id = $context['session_id'] ?? '';
    
    if ( empty( $session_id ) ) {
        return [];
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_conversations';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $rows = $wpdb->get_results( $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
        "SELECT role, user_message, assistant_message FROM $table WHERE session_id = %s ORDER BY id ASC LIMIT 50",
        $session_id
    ), ARRAY_A );
    
    if ( ! $rows ) {
        return [];
    }
    
    $history = [];
    foreach ( $rows as $row ) {
        if ( ! empty( $row['user_message'] ) ) {
            $history[] = [
                'role' => 'user',
                'content' => $row['user_message'],
            ];
        }
        if ( ! empty( $row['assistant_message'] ) ) {
            $history[] = [
                'role' => 'assistant',
                'content' => $row['assistant_message'],
            ];
        }
    }
    
    return $history;
}

/**
 * Clean up macros when webhook is deleted
 */
function aichat_custom_webhooks_cleanup_macro( $slug ) {
    if ( function_exists( 'aichat_delete_macro' ) ) {
        aichat_delete_macro( 'custom_webhook_' . $slug );
    }
}
