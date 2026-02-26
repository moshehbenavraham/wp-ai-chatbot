<?php
/**
 * MCP Integration with AIChat Tools System
 * 
 * Bridges MCP tools into the existing AIChat tools registry.
 * Registers MCP tools as proxy tools that delegate to MCP servers.
 * 
 * @package AIChat
 * @subpackage MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// MCP integration uses internal plugin tables directly.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Check if a specific MCP tool is enabled
 * 
 * @param string $server_id Server identifier (source_id)
 * @param string $tool_name Tool local name (without server prefix)
 * @return bool True if enabled (default if no record exists)
 */
function aichat_mcp_is_tool_enabled( $server_id, $tool_name ) {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_tools';
    
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled via $wpdb->prefix.
            "SELECT enabled FROM {$table} WHERE type = 'mcp' AND source_id = %s AND name = %s",
            $server_id,
            $tool_name
        )
    );
    
    // Si no hay registro, por defecto está enabled
    return $row ? (bool) $row->enabled : true;
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Sync MCP tools to unified aichat_tools table
 * 
 * Called ONLY when:
 * - Adding a new MCP server (first connection)
 * - Testing connection to server
 * - NOT called on every discover_tools (read-only after initial sync)
 * 
 * @param string $server_id Server identifier
 * @param array $tools Array of tool definitions from tools/list
 */
function aichat_mcp_sync_tools_to_db( $server_id, $tools ) {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_tools';
    $now = current_time( 'mysql' );
    
    $inserted = 0;
    
    foreach ( $tools as $tool ) {
        if ( ! isset( $tool['name'] ) ) {
            continue;
        }
        
        $tool_name = $tool['name'];
        $label = $tool['name']; // Use name as label by default
        $description = $tool['description'] ?? '';
        $definition_json = wp_json_encode( $tool );
        
        // Check if tool already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled via $wpdb->prefix.
                "SELECT id FROM {$table} WHERE type = 'mcp' AND source_id = %s AND name = %s",
                $server_id,
                $tool_name
            )
        );
        
        if ( $existing ) {
            // Tool already in DB - skip (table is read-only except on add/test)
            continue;
        }
        
        // Insert new tool (enabled by default)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable.
        $result = $wpdb->insert(
            $table,
            [
                'name'            => $tool_name,
                'type'            => 'mcp',
                'source_id'       => $server_id,
                'label'           => $label,
                'description'     => $description,
                'definition_json' => $definition_json,
                'enabled'         => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );
        
        if ( $result ) {
            $inserted++;
        }
    }
    
    if ( function_exists( 'aichat_log_debug' ) && $inserted > 0 ) {
        aichat_log_debug( '[MCP] Tools synced to database', [
            'server_id' => $server_id,
            'inserted'  => $inserted,
            'total'     => count( $tools ),
        ] );
    }
}

/**
 * Hook: Register MCP tools into the unified tool registry
 * 
 * OPTIMIZED: Reads tools from wp_aichat_tools table (no server connection needed)
 * Only connects to server when tool is actually executed
 */
add_action( 'aichat_mcp_server_connected', 'aichat_mcp_register_server_tools', 10 );
function aichat_mcp_register_server_tools( $server_id = null ) {
    
    // Only proceed if AI Tools API is available
    if ( ! function_exists( 'aichat_register_tool_safe' ) ) {
        if ( function_exists( 'aichat_log_debug' ) ) {
            // aichat_log_debug( '[MCP Integration] AI Tools API not available - skipping' );
        }
        return;
    }
    
    // OPTIMIZED: Read tools from database instead of connecting to MCP servers
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_tools';
    
    // Build prepared SQL with required placeholders
    $sql     = "SELECT source_id, name, description, definition_json FROM {$table} WHERE type = %s AND enabled = %d";
    $params  = [ 'mcp', 1 ];

    if ( $server_id !== null ) {
        $sql     .= ' AND source_id = %s';
        $params[] = $server_id;
    }

    $sql .= ' ORDER BY source_id, name';

    // Use variadics so PHPCS can verify placeholders are bound.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built from static fragments with placeholders and prepared immediately.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable.
    $mcp_tools = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
    
    if ( empty( $mcp_tools ) ) {
        return;
    }
    
    if ( function_exists( 'aichat_log_debug' ) ) {
        // aichat_log_debug( '[MCP Integration] Registering MCP tools from database', [
        //     'count'     => count( $mcp_tools ),
        //     'server_id' => $server_id ?? 'all',
        // ] );
    }
    
    // Get MCP Manager (for lazy execution, not for reading tools)
    if ( ! class_exists( 'AIChat_MCP_Client_Manager' ) ) {
        return;
    }
    $mcp_manager = AIChat_MCP_Client_Manager::instance();
    
    // Register each MCP tool as a proxy tool
    foreach ( $mcp_tools as $row ) {
        $tool_server_id = $row['source_id'];
        $local_name     = $row['name'];
        $description    = $row['description'];
        
        // Parse definition
        $definition = json_decode( $row['definition_json'], true );
        if ( ! is_array( $definition ) ) {
            $definition = [];
        }
        
        $input_schema = $definition['inputSchema'] ?? [];
        
        // Ensure schema is valid and properly formatted for OpenAI
        if ( empty( $input_schema ) || ! isset( $input_schema['type'] ) ) {
            $input_schema = [
                'type'       => 'object',
                'properties' => new stdClass(),
            ];
        } else {
            // Ensure properties is an object (not array) when empty
            if ( isset( $input_schema['properties'] ) ) {
                if ( is_array( $input_schema['properties'] ) && empty( $input_schema['properties'] ) ) {
                    $input_schema['properties'] = new stdClass();
                }
            } else {
                $input_schema['properties'] = new stdClass();
            }
        }
        
        // Build global tool name (server_id + local_name, sanitized)
        $global_name = $tool_server_id . '_' . $local_name;
        $safe_global_name = str_replace( '-', '_', strtolower( $global_name ) );
        
        // Create friendly activity label for UI
    $friendly_name = ucwords( str_replace( '_', ' ', $local_name ) );
    $activity_label = sprintf(
        /* translators: %s: Tool name being executed (e.g., 'Get Weather') */
        __( 'Running %s...', 'axiachat-ai' ),
        $friendly_name
    );
        
        // Register as proxy tool
        $registered = aichat_register_tool_safe( $safe_global_name, [
            'type'        => 'function',
            'name'        => $safe_global_name,
            'description' => $description,
            'schema'      => $input_schema,
            'activity_label' => $activity_label,
            
            // Proxy callback - connects to server ONLY when tool is executed (lazy)
            'callback' => function( $args, $context ) use ( $global_name, $mcp_manager, $tool_server_id ) {
                // LAZY CONNECTION: Connect to server if not already connected
                // Get server config
                $servers = get_option( 'aichat_mcp_servers', [] );
                if ( isset( $servers[ $tool_server_id ] ) ) {
                    // connect_server() will reuse existing session if already connected
                    $mcp_manager->connect_server( $tool_server_id, $servers[ $tool_server_id ] );
                }
                
                // Execute tool (connection is now guaranteed)
                return $mcp_manager->execute_tool( $global_name, $args, $context );
            },
            
            'timeout'  => 30,
            'parallel' => true,
            'max_calls' => 1,
            
            // Metadata to identify as MCP tool
            'meta' => [
                'source'     => 'mcp',
                'server_id'  => $tool_server_id,
                'local_name' => $local_name,
                'original_global_name' => $global_name,
            ],
        ] );
        
        if ( $registered && function_exists( 'aichat_log_debug' ) ) {
            // aichat_log_debug( '[MCP Integration] Registered MCP tool', [
            //     'safe_name'   => $safe_global_name,
            //     'server_id'   => $tool_server_id,
            //     'local_name'  => $local_name,
            // ] );
        }
    }
}

// Register all enabled MCP tools on init (reads from DB, no server connection)
add_action( 'init', function() {
    aichat_mcp_register_server_tools(); // Register all from database
}, 25 );

/**
 * Filter: Identify MCP tools in tool execution flow
 * 
 * This allows special handling if needed, but generally the proxy callback
 * handles everything transparently.
 */
add_filter( 'aichat_tool_execute', function( $result, $tool_name, $args, $context ) {
    
    // Check if this is an MCP tool
    if ( ! function_exists( 'aichat_get_registered_tools' ) ) {
        return $result;
    }
    
    $registered = aichat_get_registered_tools();
    
    if ( ! isset( $registered[ $tool_name ] ) ) {
        return $result;
    }
    
    $tool_def = $registered[ $tool_name ];
    
    // Is it an MCP tool?
    if ( ! isset( $tool_def['meta']['source'] ) || $tool_def['meta']['source'] !== 'mcp' ) {
        return $result; // Not MCP, pass through
    }
    
    // MCP tool - execution happens via the proxy callback
    // We can add additional logging or monitoring here if needed
    
    if ( function_exists( 'aichat_log_debug' ) && defined( 'AICHAT_DEBUG' ) && AICHAT_DEBUG ) {
        // aichat_log_debug( '[MCP Integration] Executing MCP tool', [
        //     'tool'      => $tool_name,
        //     'server_id' => $tool_def['meta']['server_id'] ?? 'unknown',
        // ] );
    }
    
    return $result;
    
}, 10, 4 );

/**
 * Register MCP macros (grouping MCP tools by server)
 * 
 * This creates macros like "mcp_sentry" that enable all tools from a server.
 * Called when a server connects or on init for already-connected servers.
 * 
 * OPTIMIZED: Reads tools from wp_aichat_tools database (no connection needed)
 */
function aichat_mcp_register_macros( $server_id = null ) {
    global $wpdb;
    
    if ( ! function_exists( 'aichat_register_macro' ) ) {
        return;
    }
    
    // Read tools from database (no server connection needed)
    $table = $wpdb->prefix . 'aichat_tools';
    
    if ( $server_id !== null ) {
        // Get tools for specific server
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable.
        $tools = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled via $wpdb->prefix.
                "SELECT source_id, name, enabled FROM {$table} WHERE type = 'mcp' AND source_id = %s",
                $server_id
            ),
            ARRAY_A
        );
    } else {
        // Get all MCP tools
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled via $wpdb->prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable.
        $tools = $wpdb->get_results( "SELECT source_id, name, enabled FROM {$table} WHERE type = 'mcp'", ARRAY_A );
    }
    
    if ( empty( $tools ) ) {
        return;
    }
    
    // Group tools by server
    $servers = [];
    
    foreach ( $tools as $tool ) {
        $tool_server_id = $tool['source_id'];
        $local_name = $tool['name'];
        
        // Skip disabled tools
        if ( ! $tool['enabled'] ) {
            continue;
        }
        
        // Construct global name (server_id + tool name)
        $global_name = $tool_server_id . '_' . $local_name;
        
        // Sanitize to match registered tool names
        $safe_global_name = str_replace( '-', '_', strtolower( $global_name ) );
        
        if ( ! isset( $servers[ $tool_server_id ] ) ) {
            $servers[ $tool_server_id ] = [
                'global_names' => [],
                'local_names'  => [],
            ];
        }
        
        $servers[ $tool_server_id ]['global_names'][] = $safe_global_name;
        $servers[ $tool_server_id ]['local_names'][] = $local_name;
    }
    
    // Register a macro for each server
    foreach ( $servers as $tool_server_id => $server_data ) {
        $macro_name = 'mcp_' . $tool_server_id;
        
        // Get server config for friendly name
        $servers_config = get_option( 'aichat_mcp_servers', [] );
        $server_config = $servers_config[ $tool_server_id ] ?? [];
        $server_friendly_name = $server_config['name'] ?? ucfirst( str_replace( '_', ' ', $tool_server_id ) );
        
        aichat_register_macro( [
            'name'        => $macro_name,
            'label'       => sprintf( 
                /* translators: %s: Server name */
                __( 'MCP: %s', 'axiachat-ai' ), 
                $server_friendly_name
            ),
            'description' => sprintf( 
                /* translators: %1$s: server name, %2$d: tool count */
                __( 'Enable all tools from MCP server "%1$s" (%2$d tools)', 'axiachat-ai' ),
                $server_friendly_name,
                count( $server_data['global_names'] )
            ),
            'tools'       => $server_data['global_names'], // Still use global names for registration
            'source'      => 'mcp',
            'source_ref'  => $tool_server_id,
        ] );
        
        if ( function_exists( 'aichat_log_debug' ) ) {
            // aichat_log_debug( '[MCP Integration] Registered MCP macro', [
            //     'macro'      => $macro_name,
            //     'server_id'  => $tool_server_id,
            //     'tool_count' => count( $server_data['global_names'] ),
            // ] );
        }
    }
}

// Register macros when a server connects
add_action( 'aichat_mcp_server_connected', 'aichat_mcp_register_macros', 11 ); // After tools (10)

// Also register on init for already-connected servers
add_action( 'init', function() {
    aichat_mcp_register_macros(); // Register all
}, 26 ); // After tool registration (25)

/**
 * Check if bot has active MCP tools and return server info
 * 
 * @param string $bot_slug Bot identifier (optional)
 * @return array Array of active MCP servers with name/version
 */
function aichat_has_active_mcp_tools( $bot_slug = '' ) {
    $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
    
    $mcp_servers = [];
    foreach ( $macros as $m ) {
        // Solo macros MCP habilitadas
        if ( ($m['source'] ?? '') !== 'mcp' || empty($m['enabled']) ) {
            continue;
        }
        
        $server_id = $m['source_ref'] ?? '';
        if ( !$server_id || isset($mcp_servers[$server_id]) ) {
            continue;
        }
        
        // Cargar config del servidor
        $config = get_option( 'aichat_mcp_server_' . $server_id );
        if ( !$config || empty($config['enabled']) ) {
            continue;
        }
        
        // Extraer info del servidor
        $server_info = $config['server_info'] ?? [];
        $mcp_servers[$server_id] = [
            'id'      => $server_id,
            'name'    => $config['name'] ?? $server_id,
            'version' => $server_info['version'] ?? 'unknown',
        ];
    }
    
    return array_values( $mcp_servers );
}
