<?php
// Moved sample tools from includes/tools-sample.php
//
// Convenciones de registro de tools:
// - 'description' (MODELO): guía de uso para el LLM (cuándo/cómo usar la tool, formatos, restricciones).
// - 'activity_label' (UI): texto visible en UI cuando la tool está "ejecutándose" (spinner/progreso).
// - 'schema': parámetros con tipos + descriptions que el MODELO lee para construir llamadas correctas.
// - 'name' y 'type': identificadores de la tool que ve el MODELO; mantener estables.
// - Las descriptions en propiedades del schema deben ser explícitas (formato de fechas, unidades, rangos).
if ( ! defined('ABSPATH') ) { exit; }

// Sample tools/macros; uses internal DB checks and writes.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Helper wrapper for safe tool registration
if ( ! function_exists('aichat_register_tool_safe') ) {
    function aichat_register_tool_safe($id, $args) {
        if ( function_exists('aichat_register_tool') ) {
            return aichat_register_tool($id, $args);
        }
        return false;
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Defer registration until tables are created (after plugin activation)
add_action( 'init', 'aichat_register_sample_tools_and_macros', 20 );

function aichat_register_sample_tools_and_macros() {
    // Only register if tables exist
    global $wpdb;
    $tools_table = $wpdb->prefix . 'aichat_tools';
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching not applicable.
  if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tools_table ) ) !== $tools_table ) {
        return; // Tables not created yet, skip registration
    }

    // Skip if tools registration not available
    if ( ! function_exists('aichat_register_tool') ) {
        return;
    }

    // (Removed) Demo utilities: util_get_datetime, util_mortgage_payment, util_list_categories, content_categories

// === Web Search (Multi-Provider) ===
// Universal web search capability compatible with multiple providers:
// - OpenAI (GPT-5* models via Responses API)
// - Claude (4.x models via Messages API with web_search_20250305)
// - Gemini (2.0/2.5 models with native googleSearch tool + grounding)
//
// IMPORTANT LIMITATION FOR GEMINI:
// Multi-tool use (combining google_search + functionDeclarations) is ONLY supported
// in Live API (WebSocket), NOT in REST API generateContent endpoint.
// In REST API, user must choose: web_search OR custom functions, but not both.
// See: https://ai.google.dev/gemini-api/docs/function-calling#multi-tool-use
//
// We register a dummy atomic tool so the macro can reference it, but it's type 'custom' so it's not included in CC function tools.
aichat_register_tool_safe('__builtin_web_search', [
  'type' => 'custom',
  'name' => 'web_search_builtin',
  'description' => 'Enables web search on compatible models (OpenAI GPT-5*, Claude 4.x, Gemini 2.0/2.5). Allows real-time internet lookups with optional domain restrictions. GEMINI LIMITATION: Cannot combine with custom functions in REST API (Live API only).', // MODELO
  'callback' => '__return_null'
]);

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro([
    'name' => 'web_search',
    'label' => 'Web Search',
    'description' => 'Allows the assistant to search the internet for real-time information. Compatible with OpenAI (GPT-5* models), Claude (4.x models), and Gemini (2.0/2.5 models). GEMINI LIMITATION: In REST API, web_search cannot be combined with custom functions (multi-tool use requires Live API/WebSocket). Configure allowed domains to restrict sources.', // UI/Admin
    'tools' => ['__builtin_web_search'],
    'source' => 'local',
    'source_ref' => 'axiachat_core'
  ]);
}

// ============ Email (Admin Only) ============
// Safe default: always sends to site admin email, ignoring custom recipient to avoid misuse from visitor prompts.
// Recommend enabling only on internal/ops bots and guarding via system instructions.
aichat_register_tool_safe( 'aichat_send_email_admin', [
  'type' => 'function',
  'name' => 'aichat_send_email_admin',
  'description' => 'Send an email notification to the site admin. For internal assistant-initiated notifications only—never on direct user request.',
  'activity_label' => 'Sending email notification to admin...',
  'schema' => [
    'type' => 'object',
    'properties' => [
      'subject' => [ 'type'=>'string', 'description'=>'Email subject (short, plain text).' ],
      'message' => [ 'type'=>'string', 'description'=>'Email message body.' ],
      'html'    => [ 'type'=>'boolean', 'description'=>'If true, body will be sent as HTML.', 'default'=>false ],
      'from_name'  => [ 'type'=>'string', 'description'=>'Optional From name (restricted to site domain).'],
      'from_email' => [ 'type'=>'string', 'description'=>'Optional From email (must match site domain).'],
      // Note: No freeform "to"—we intentionally send to admin_email to prevent abuse.
    ],
    'required' => ['subject','message'],
    'additionalProperties' => false
  ],
  'callback' => function( $args, $ctx = [] ){
    // Resolve admin recipient only
    $to = get_option('admin_email');
    if ( ! is_email( $to ) ) {
      return [ 'ok'=>false, 'error'=>'no_admin_email' ];
    }
    $subject = isset($args['subject']) ? sanitize_text_field( (string)$args['subject'] ) : '';
    $message = isset($args['message']) ? (string)$args['message'] : '';
    $html = ! empty($args['html']);
    if ( $subject === '' || $message === '' ) {
      return [ 'ok'=>false, 'error'=>'missing_subject_or_message' ];
    }
    $headers = [];
    if ( $html ) {
      $headers[] = 'Content-Type: text/html; charset=UTF-8';
      // allow basic HTML only
      $message = wp_kses_post( $message );
    } else {
      $message = wp_strip_all_tags( $message );
    }
    // Restrict optional From to same domain as site to avoid spoofing
  $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $from_name = isset($args['from_name']) ? sanitize_text_field( (string)$args['from_name'] ) : '';
    $from_email = isset($args['from_email']) ? sanitize_email( (string)$args['from_email'] ) : '';
    if ( $from_email && is_email($from_email) ) {
      $from_host = substr(strrchr($from_email, '@'), 1);
      if ( is_string($home_host) && is_string($from_host) && strcasecmp($home_host, $from_host) === 0 ) {
        $from = $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;
        $headers[] = 'From: ' . $from;
      }
    }
    $sent = wp_mail( $to, $subject, $message, $headers );
    if ( ! $sent ) {
      return [ 'ok'=>false, 'error'=>'send_failed' ];
    }
    // Do not echo back full message; return a compact status
    return [ 'ok'=>true, 'to'=>'admin', 'subject'=>mb_substr($subject,0,140) ];
  },
  'timeout' => 8,
  'parallel' => false,
  'max_calls' => 1
] );

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro([
    'name' => 'notifications_email_admin',
    'label' => 'Notifications: Email Admin',
    'description' => 'Allows the assistant to send an email notification to the site admin (internal use only).',
    'tools' => ['aichat_send_email_admin'],
    'source' => 'local',
    'source_ref' => 'axiachat_core'
  ]);
}

// ============ Email (Client) ============
// Safer default: requires explicit server-side authorization via filter 'aichat_can_send_client_email'.
// By default this tool is DENIED (returns not_authorized) to prevent user-triggered misuse.
// You can authorize on specific conditions (e.g., when appointment is confirmed) using the filter.
aichat_register_tool_safe( 'aichat_send_email_client', [
  'type' => 'function',
  'name' => 'aichat_send_email_client',
  'description' => 'Send an email to a customer. Disabled by default; requires server-side authorization filter. Use for appointment confirmations, etc.',
  'activity_label' => 'Sending email to client...',
  'schema' => [
    'type' => 'object',
    'properties' => [
      'to'      => [ 'type'=>'string', 'description'=>'Recipient email address (customer).' ],
      'subject' => [ 'type'=>'string', 'description'=>'Email subject (short, plain text).' ],
      'message' => [ 'type'=>'string', 'description'=>'Email message body.' ],
      'html'    => [ 'type'=>'boolean', 'description'=>'If true, body will be sent as HTML.', 'default'=>false ],
      'from_name'  => [ 'type'=>'string', 'description'=>'Optional From name (restricted to site domain).'],
      'from_email' => [ 'type'=>'string', 'description'=>'Optional From email (must match site domain).'],
    ],
    'required' => ['to','subject','message'],
    'additionalProperties' => false
  ],
  'callback' => function( $args, $ctx = [] ){
    $to = isset($args['to']) ? sanitize_email( (string)$args['to'] ) : '';
    if ( ! is_email( $to ) ) { return [ 'ok'=>false, 'error'=>'invalid_email' ]; }
    // Authorization gate (default: false)
    $allowed = apply_filters( 'aichat_can_send_client_email', false, $to, $ctx, $args );
    if ( ! $allowed ) { return [ 'ok'=>false, 'error'=>'not_authorized' ]; }
    // Simple rate limit per session+recipient (10 minutes)
    $session = isset($ctx['session_id']) ? (string)$ctx['session_id'] : '';
    $rl_key = 'aichat_emcli_'.md5( strtolower($session.'|'.$to) );
    if ( get_transient($rl_key) ) { return [ 'ok'=>false, 'error'=>'rate_limited' ]; }
    $subject = isset($args['subject']) ? sanitize_text_field( (string)$args['subject'] ) : '';
    $message = isset($args['message']) ? (string)$args['message'] : '';
    $html = ! empty($args['html']);
    if ( $subject === '' || $message === '' ) { return [ 'ok'=>false, 'error'=>'missing_subject_or_message' ]; }
    $headers = [];
    if ( $html ) { $headers[] = 'Content-Type: text/html; charset=UTF-8'; $message = wp_kses_post( $message ); }
    else { $message = wp_strip_all_tags( $message ); }
    // Restrict From to site domain to avoid spoofing
  $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $from_name = isset($args['from_name']) ? sanitize_text_field( (string)$args['from_name'] ) : '';
    $from_email = isset($args['from_email']) ? sanitize_email( (string)$args['from_email'] ) : '';
    if ( $from_email && is_email($from_email) ) {
      $from_host = substr(strrchr($from_email, '@'), 1);
      if ( is_string($home_host) && is_string($from_host) && strcasecmp($home_host, $from_host) === 0 ) {
        $from = $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;
        $headers[] = 'From: ' . $from;
      }
    }
    $sent = wp_mail( $to, $subject, $message, $headers );
    if ( ! $sent ) { return [ 'ok'=>false, 'error'=>'send_failed' ]; }
    set_transient( $rl_key, 1, 10 * MINUTE_IN_SECONDS );
    // Return minimal info
    $mask = function($email){ $parts = explode('@',$email); if(count($parts)!==2) return $email; $name=$parts[0]; $dom=$parts[1]; $name_mask = strlen($name)>2 ? substr($name,0,1).'***'.substr($name,-1) : '*'; return $name_mask.'@'.$dom; };
    return [ 'ok'=>true, 'to'=>$mask($to), 'subject'=>mb_substr($subject,0,140) ];
  },
  'timeout' => 8,
  'parallel' => false,
  'max_calls' => 1
] );

if ( function_exists('aichat_register_macro') ) {
  aichat_register_macro([
    'name' => 'notifications_email_client',
    'label' => 'Notifications: Email Client',
    'description' => 'Allows the assistant to send an email to a customer (requires server-side authorization).',
    'tools' => ['aichat_send_email_client'],
    'source' => 'local',
    'source_ref' => 'axiachat_core'
  ]);
}

} // End aichat_register_sample_tools_and_macros()

/**
 * Sync local tools to unified aichat_tools table (one-time setup)
 * This function is called ONLY during plugin activation, not on every init.
 */
function aichat_sync_local_tools_to_db() {
    // Only run once
    if ( get_option( 'aichat_local_tools_synced' ) ) {
        return;
    }
    
    // Get all registered tools from the global registry
    if ( ! function_exists( 'aichat_get_registered_tools' ) ) {
        // Tools not loaded yet, will be synced on activation
        return;
    }
    
    $all_tools = aichat_get_registered_tools();
    if ( empty( $all_tools ) ) {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_tools';
    $now = current_time( 'mysql' );
    
    foreach ( $all_tools as $tool_name => $tool_def ) {
        // Skip MCP tools (they're synced separately)
        if ( strpos( $tool_name, 'mcp_' ) === 0 ) {
            continue;
        }
        
        // Check if tool already exists in DB
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable.
        $existing = $wpdb->get_var(
          $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            "SELECT id FROM {$table} WHERE type = 'local' AND name = %s",
            $tool_name
          )
        );
        
        if ( $existing ) {
            continue; // Already synced
        }
        
        // Insert local tool (only once)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable.
        $wpdb->insert(
            $table,
            [
                'name'            => $tool_name,
                'type'            => 'local',
                'source_id'       => null,
                'label'           => $tool_def['activity_label'] ?? $tool_name,
                'description'     => $tool_def['description'] ?? '',
                'definition_json' => wp_json_encode( $tool_def ),
                'enabled'         => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );
    }
    
    // Mark as synced (only once in lifetime)
    update_option( 'aichat_local_tools_synced', 1 );
    
    if ( function_exists( 'aichat_log_debug' ) ) {
        aichat_log_debug( '[AI Tools] Local tools synced to database (one-time activation)', [
            'tool_count' => count( $all_tools ),
        ] );
    }
}
