<?php
/**
 * AI Tools Registry API (moved from includes/tools.php)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Tool registry + helpers may use direct DB reads/writes for internal plugin tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

if ( ! function_exists( 'aichat_register_tool' ) ) {
    function aichat_register_tool( $id, $args ) {
        static $tools = [];

        $raw_id = (string)$id;
        if ( ! preg_match( '/^[a-z0-9_]{2,64}$/', $raw_id ) ) {
            return false;
        }

        $defaults = [
            'type'        => 'function',
            'name'        => $raw_id,
            'description' => '',
            'schema'      => [],
            'strict'      => true,
            'callback'    => null,
            'auth'        => null,
            'timeout'     => 5,
            'parallel'    => true,
            'max_calls'   => 1,
            'custom_input_format' => null,
        ];
        $tool = array_merge( $defaults, (array)$args );

        if ( $tool['type'] !== 'function' && $tool['type'] !== 'custom' ) {
            return false;
        }
        if ( ! is_callable( $tool['callback'] ) ) {
            return false;
        }
        if ( $tool['type'] === 'function' ) {
            // Defensive normalization of JSON Schema
            if ( is_array($tool['schema']) ) {
                // Default to object if type omitted
                if ( empty($tool['schema']['type']) ) {
                    $tool['schema']['type'] = 'object';
                }
                // Ensure properties is an object when empty
                if ( isset($tool['schema']['properties']) ) {
                    if ( is_array($tool['schema']['properties']) && empty($tool['schema']['properties']) ) {
                        // Convert [] → {} to satisfy OpenAI validator
                        $tool['schema']['properties'] = (object) [];
                    }
                } else {
                    // Provide empty object properties by default
                    $tool['schema']['properties'] = (object) [];
                }
                // Coerce additionalProperties to boolean if present
                if ( isset($tool['schema']['additionalProperties']) ) {
                    $tool['schema']['additionalProperties'] = (bool) $tool['schema']['additionalProperties'];
                }
                // Ensure required is array if present
                if ( isset($tool['schema']['required']) && ! is_array($tool['schema']['required']) ) {
                    $tool['schema']['required'] = [];
                }
            }
            // Validation after normalization
            if ( ! is_array( $tool['schema'] ) || empty( $tool['schema'] ) ) {
                return false;
            }
            if ( empty( $tool['schema']['type'] ) || $tool['schema']['type'] !== 'object' ) {
                return false;
            }
        }
        if ( ! is_int( $tool['timeout'] ) ) { $tool['timeout'] = (int)$tool['timeout']; }
        if ( $tool['timeout'] <= 0 ) { $tool['timeout'] = 5; }
        if ( ! is_int( $tool['max_calls'] ) || $tool['max_calls'] <= 0 ) { $tool['max_calls'] = 1; }

        $tool['name'] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string)$tool['name'] );
        if ( $tool['name'] === '' ) { $tool['name'] = $raw_id; }
        $tool['description'] = mb_substr( (string)$tool['description'], 0, 600 );

        $tools[ $raw_id ] = $tool;
        return true;
    }
}

if ( ! function_exists( 'aichat_get_registered_tools' ) ) {
    function aichat_get_registered_tools() {
        if ( isset( $GLOBALS['aichat_registered_tools'] ) && is_array( $GLOBALS['aichat_registered_tools'] ) ) {
            return $GLOBALS['aichat_registered_tools'];
        }
        return [];
    }
}

add_action( 'init', function(){
    if ( ! isset( $GLOBALS['aichat_registered_tools'] ) ) {
        $GLOBALS['aichat_registered_tools'] = [];
    }
});

add_action( 'plugins_loaded', function(){
    if ( ! function_exists('aichat_register_tool') ) return;
    if ( ! has_action('aichat_tool_registered') ) {
        add_action('aichat_tool_registered', function($id, $def){
            if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
            $GLOBALS['aichat_registered_tools'][$id] = $def;
        }, 10, 2);
    }
});

if ( ! function_exists('aichat_register_tool_decorator_applied') ) {
    function aichat_register_tool_decorator_applied() { return true; }
    if ( function_exists('aichat_register_tool') ) {
        if ( ! function_exists('aichat_register_tool_safe') ) {
            function aichat_register_tool_safe( $id, $args ) {
                $ok = \aichat_register_tool( $id, $args );
                if ( $ok && isset($GLOBALS['aichat_registered_tools'][$id]) === false ) {
                    if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
                    $def = $args; $def['id'] = $id;
                    $GLOBALS['aichat_registered_tools'][$id] = $def;
                    do_action('aichat_tool_registered', $id, $def );
                }
                return $ok;
            }
        }
    }
}

// ================= Capability Settings (per bot, per capability) =================
// Option shape: { "bot-slug": { "capability_id": { "system_policy": "...", ... } } }
if ( ! function_exists('aichat_get_capability_settings_map') ) {
    function aichat_get_capability_settings_map() {
        $raw = get_option('aichat_tools_capability_settings', '{}');
        $map = json_decode( (string) $raw, true );
        return is_array($map) ? $map : [];
    }
}
if ( ! function_exists('aichat_get_capability_settings_for_bot') ) {
    function aichat_get_capability_settings_for_bot( $bot_slug ) {
        $all = aichat_get_capability_settings_map();
        $slug = sanitize_title( (string)$bot_slug );
        return isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : [];
    }
}
if ( ! function_exists('aichat_save_capability_settings_for_bot') ) {
    function aichat_save_capability_settings_for_bot( $bot_slug, array $settings_map ) {
        $all = aichat_get_capability_settings_map();
        $slug = sanitize_title( (string)$bot_slug );
        $all[$slug] = $settings_map;
        update_option('aichat_tools_capability_settings', wp_json_encode($all));
        return true;
    }
}

// Inject provider-native tools (e.g., web_search) for OpenAI Responses when selected via macro, with optional domain constraints
add_filter('aichat_openai_responses_tools', function( $tools, $ctx ){
    // $ctx: ['model'=>..., 'bot'=>bot_slug]
    // Detect if macro 'web_search' is selected for this bot
    $bot_slug = isset($ctx['bot']) ? sanitize_title($ctx['bot']) : '';
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[AI Tools] aichat_openai_responses_tools filter called', [
            'bot_slug' => $bot_slug,
            'tools_count_in' => count($tools),
            'context' => $ctx
        ], true);
    }
    
    if ($bot_slug === '') return $tools;
    // Load bot row to inspect selected capabilities (tools_json)
    global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table read during tool injection.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name safe; slug uses placeholder
    $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
    $selected = [];
    if ($row && !empty($row['tools_json'])){ $tmp = json_decode((string)$row['tools_json'], true); if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); }
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[AI Tools] Bot tools_json loaded', [
            'selected_count' => count($selected),
            'selected_items' => implode(', ', $selected),
            'raw_tools_json' => $row['tools_json'] ?? 'null'
        ], true);
    }
    
    if ( empty($selected) ) return $tools;
    $has_web_search_macro = in_array('web_search', $selected, true);
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[AI Tools] Checking web_search macro', [
            'has_web_search' => $has_web_search_macro,
            'looking_for' => 'web_search',
            'selected_items' => implode(', ', $selected)
        ], true);
    }
    
    if ( ! $has_web_search_macro ) return $tools;
    // Optional: read capability settings to restrict domains
    $domains = [];
    if ( function_exists('aichat_get_capability_settings_for_bot') ) {
        $cap_settings = aichat_get_capability_settings_for_bot($bot_slug);
        if ( isset($cap_settings['web_search']['domains']) && is_array($cap_settings['web_search']['domains']) ) {
            $domains = array_values(array_filter(array_map('sanitize_text_field', $cap_settings['web_search']['domains'])));
        }
    }
    // Build OpenAI native web_search tool entry for Responses
    // Supports filters.allowed_domains as per OpenAI Responses API (2025)
    $ws = [ 'type' => 'web_search' ];
    if ($domains) {
        $ws['filters'] = [ 'allowed_domains' => $domains ];
    }
    // Ensure it's not duplicated
    $found = false; foreach($tools as $t){ if( isset($t['type']) && $t['type']==='web_search' ){ $found=true; break; } }
    if ( ! $found ) { 
        $tools[] = $ws;
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[AI Tools] INJECTED web_search into OpenAI tools', [
                'domains' => $domains,
                'tools_count_out' => count($tools)
            ], true);
        }
    }
    return $tools;
}, 10, 2);

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Inject google_search tool for Gemini when selected via macro
add_filter('aichat_gemini_tools', function( $tools, $ctx ){
    // $ctx: ['model'=>..., 'bot'=>bot_slug]
    // Detect if macro 'web_search' is selected for this bot
    $bot_slug = isset($ctx['bot']) ? sanitize_title($ctx['bot']) : '';
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[AI Tools] aichat_gemini_tools filter called', [
            'bot_slug' => $bot_slug,
            'tools_count_in' => count($tools),
            'context' => $ctx
        ], true);
    }
    
    if ($bot_slug === '') return $tools;
    
    // Load bot row to inspect selected capabilities (tools_json)
    global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table read during tool injection.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name safe; slug uses placeholder
    $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
    $selected = [];
    if ($row && !empty($row['tools_json'])){ 
        $tmp = json_decode((string)$row['tools_json'], true); 
        if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); 
    }
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[AI Tools] Bot tools_json loaded for Gemini', [
            'selected_count' => count($selected),
            'selected_items' => implode(', ', $selected),
            'raw_tools_json' => $row['tools_json'] ?? 'null'
        ], true);
    }
    
    if ( empty($selected) ) return $tools;
    $has_web_search_macro = in_array('web_search', $selected, true);
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[AI Tools] Checking web_search macro for Gemini', [
            'has_web_search' => $has_web_search_macro,
            'looking_for' => 'web_search',
            'selected_items' => implode(', ', $selected)
        ], true);
    }
    
    if ( ! $has_web_search_macro ) return $tools;
    
    // CRITICAL LIMITATION: Multi-tool use (google_search + functionDeclarations) is ONLY
    // supported in Live API (WebSocket), NOT in REST API generateContent endpoint.
    // See: https://ai.google.dev/gemini-api/docs/function-calling#multi-tool-use
    // 
    // "Multi-tool use is a Live API only feature at the moment."
    //
    // Solution: Do NOT inject google_search if there are already functionDeclarations.
    // The user must choose: web_search OR custom functions, but not both (in REST API).
    
    // Check if there are already function declarations
    $has_function_declarations = false;
    foreach($tools as $t){ 
        if( isset($t['type']) && $t['type']==='function' ){ 
            $has_function_declarations = true; 
            break; 
        } 
    }
    
    if ( $has_function_declarations ) {
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[AI Tools] SKIPPED google_search injection - functionDeclarations present (Live API only limitation)', [
                'reason' => 'Multi-tool use (google_search + functionDeclarations) only supported in Live API',
                'tools_count' => count($tools)
            ], true);
        }
        return $tools; // Don't inject google_search
    }
    
    // Build Gemini native google_search tool entry
    // Note: Gemini's google_search doesn't support domain filtering in the same way as OpenAI/Claude
    // Domain restrictions would need to be enforced via system instructions
    $gs = [ 'type' => 'google_search' ];
    
    // Check if already added to avoid duplicates
    $found = false; 
    foreach($tools as $t){ 
        if( isset($t['type']) && $t['type']==='google_search' ){ 
            $found=true; 
            break; 
        } 
    }
    
    if ( ! $found ) { 
        $tools[] = $gs;
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[AI Tools] INJECTED google_search into Gemini tools (no function declarations present)', [
                'tools_count_out' => count($tools)
            ], true);
        }
    }
    
    return $tools;
}, 10, 2);

// Early injection of allowed domains policy into system message (runs before provider call)
add_filter('aichat_messages_before_provider', function($messages, $meta){
    if ( empty($meta['bot']) || !is_array($meta['bot']) ) return $messages;
    $bot = $meta['bot']; $slug = isset($bot['slug']) ? sanitize_title($bot['slug']) : '';
    if ($slug==='') return $messages;
    // Check web search macro selected
    $selected = [];
    if (!empty($bot['tools_json'])){ $tmp = json_decode((string)$bot['tools_json'], true); if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); }
    if ( empty($selected) || !in_array('web_search',$selected,true) ) return $messages;
    // Load domains from capability settings
    $domains = [];
    if ( function_exists('aichat_get_capability_settings_for_bot') ) {
        $cap_settings = aichat_get_capability_settings_for_bot($slug);
        if ( isset($cap_settings['web_search']['domains']) && is_array($cap_settings['web_search']['domains']) ) {
            $domains = array_values(array_filter(array_map('sanitize_text_field', $cap_settings['web_search']['domains'])));
        }
    }
    if (!$domains) return $messages;
    if ( isset($messages[0]['role']) && $messages[0]['role']==='system' ) {
        $hint = "\n\nWEB SEARCH POLICY: Prefer retrieving and citing information from these domains: ".implode(', ', array_map('wp_strip_all_tags',$domains)).". If relevant info isn't available there, you may extend to trusted sources.";
        $messages[0]['content'] .= $hint;
    }
    return $messages;
}, 18, 2);

// Inject web_search_20250305 server-side tool for Claude when selected via macro
add_filter('aichat_claude_messages_tools', function( $tools, $ctx ){
    // $ctx: ['model'=>..., 'bot'=>bot_slug]
    // Detect if macro 'web_search' is selected for this bot
    $bot_slug = isset($ctx['bot']) ? sanitize_title($ctx['bot']) : '';
    if ($bot_slug === '') return $tools;
    
    // Load bot row to inspect selected capabilities (tools_json)
    global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching not applicable.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name safe; slug uses placeholder
    $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
    $selected = [];
    if ($row && !empty($row['tools_json'])) {
        $tmp = json_decode((string)$row['tools_json'], true);
        if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string'));
    }
    if ( empty($selected) ) return $tools;
    
    // Check if web_search macro is selected
    $has_web_search_macro = in_array('web_search', $selected, true);
    if ( ! $has_web_search_macro ) return $tools;
    
    // Load capability settings (allowed_domains)
    $domains = [];
    if ( function_exists('aichat_get_capability_settings_for_bot') ) {
        $cap_settings = aichat_get_capability_settings_for_bot($bot_slug);
        if ( isset($cap_settings['web_search']['domains']) && is_array($cap_settings['web_search']['domains']) ) {
            $domains = array_values(array_filter(array_map('sanitize_text_field', $cap_settings['web_search']['domains'])));
        }
    }
    
    // Build Claude web_search tool (versioned type per Anthropic spec)
    // https://docs.anthropic.com/en/docs/build-with-claude/tool-use/web-search-tool
    $ws = [
        'type' => 'web_search_20250305',
        'name' => 'web_search'
    ];
    
    // Optional: max uses per request (default 5 to avoid runaway costs)
    $max_uses = apply_filters('aichat_claude_web_search_max_uses', 5, $bot_slug);
    if ($max_uses > 0) {
        $ws['max_uses'] = (int) $max_uses;
    }
    
    // Optional: allowed domains (same as OpenAI for consistency)
    if ($domains) {
        $ws['allowed_domains'] = $domains;
    }
    
    // Optional: user location for localized results (could detect from WP user meta)
    // $ws['user_location'] = [
    //     'type' => 'approximate',
    //     'city' => 'San Francisco',
    //     'region' => 'California',
    //     'country' => 'US',
    //     'timezone' => 'America/Los_Angeles'
    // ];
    
    // Ensure it's not duplicated
    $found = false;
    foreach($tools as $t) {
        if( isset($t['type']) && $t['type']==='web_search_20250305' ) {
            $found=true;
            break;
        }
    }
    if ( ! $found ) {
        $tools[] = $ws;
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug("[AI Tools] Injected Claude web_search_20250305 for bot={$bot_slug}, domains=" . count($domains) . ", max_uses={$max_uses}");
        }
    }
    
    return $tools;
}, 10, 2);
