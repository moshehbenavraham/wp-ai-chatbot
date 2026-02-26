<?php
/**
 * Easy Config Wizard - Main PHP Handler
 *
 * Multi-step wizard for quick bot and context setup.
 *
 * @package AxiaChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load data definitions.
require_once __DIR__ . '/easy-config-data.php';

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Render the Easy Config page.
 */
function aichat_easy_config_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $nonce = wp_create_nonce( 'aichat_easycfg' );

    // Get data for JS.
    $chatbot_types    = aichat_easycfg_get_chatbot_types();
    $voice_tones      = aichat_easycfg_get_voice_tones();
    $response_lengths = aichat_easycfg_get_response_lengths();
    $providers        = aichat_easycfg_get_providers();
    $existing_bots    = aichat_easycfg_get_existing_bots();

    $agency_configured = function_exists( 'aichat_agency_is_configured' ) ? aichat_agency_is_configured() : false;
    $agency_enabled    = $agency_configured || (bool) get_option( 'aichat_agency_enabled', false );

    // Check which providers have keys configured.
    $provider_status = [];
    foreach ( $providers as $key => $provider ) {
        $opt_key = isset( $provider['option_key'] ) ? $provider['option_key'] : '';
        $has_key = false;
        if ( $opt_key && function_exists( 'aichat_get_setting' ) ) {
            $val = aichat_get_setting( $opt_key );
            $has_key = ! empty( $val );
        }
        $provider_status[ $key ] = $has_key;
    }

    // Check if bots exist (determines whether to show "Create New" / "Update Existing" options)
    $has_bots = ! empty( $existing_bots );

    // Get bot limits for free plan
    $max_bots    = function_exists( 'aichat_max_bots_allowed' ) ? aichat_max_bots_allowed() : 1;
    $bot_count   = count( $existing_bots );
    $can_create  = $bot_count < $max_bots;
    $upgrade_url = function_exists( 'aichat_get_upgrade_url' ) ? aichat_get_upgrade_url() : 'https://axiachat.com/pricing/';

    // Detect reusable empty context: when there's exactly 1 bot with exactly 1 context
    // that has 0 chunks (first-time wizard run, default context created on activation).
    global $wpdb;
    $reusable_context_id = 0;
    if ( $bot_count === 1 ) {
        $single_bot = $existing_bots[0];
        $ctx_table  = $wpdb->prefix . 'aichat_contexts';
        $chk_table  = $wpdb->prefix . 'aichat_chunks';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_contexts = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM {$ctx_table}"
        );
        if ( 1 === $total_contexts ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $ctx_row = $wpdb->get_row(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id FROM {$ctx_table} LIMIT 1",
                ARRAY_A
            );
            if ( $ctx_row ) {
                $ctx_id = (int) $ctx_row['id'];
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $chunk_count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        "SELECT COUNT(*) FROM {$chk_table} WHERE id_context = %d",
                        $ctx_id
                    )
                );
                if ( 0 === $chunk_count ) {
                    $reusable_context_id = $ctx_id;
                }
            }
        }
    }

    ?>
    <div class="wrap aichat-easy-config-wrapper">
        <h1 class="aichat-ec-header">
            <span class="aichat-ec-logo">🚀</span>
            <?php esc_html_e( 'AI Chat – Easy Setup Wizard', 'axiachat-ai' ); ?>
        </h1>
        <p class="aichat-ec-subtitle">
            <?php esc_html_e( 'Set up your AI chatbot in just a few minutes. Follow the steps below to configure your assistant.', 'axiachat-ai' ); ?>
        </p>

        <div id="aichat-easy-config-root" 
             data-nonce="<?php echo esc_attr( $nonce ); ?>"
             data-chatbot-types="<?php echo esc_attr( wp_json_encode( $chatbot_types ) ); ?>"
             data-voice-tones="<?php echo esc_attr( wp_json_encode( $voice_tones ) ); ?>"
             data-response-lengths="<?php echo esc_attr( wp_json_encode( $response_lengths ) ); ?>"
             data-providers="<?php echo esc_attr( wp_json_encode( $providers ) ); ?>"
             data-provider-status="<?php echo esc_attr( wp_json_encode( $provider_status ) ); ?>"
             data-agency-enabled="<?php echo $agency_enabled ? '1' : '0'; ?>"
             data-agency-configured="<?php echo $agency_configured ? '1' : '0'; ?>"
             data-existing-bots="<?php echo esc_attr( wp_json_encode( $existing_bots ) ); ?>"
             data-max-upload="<?php echo esc_attr( wp_max_upload_size() ); ?>"
             data-has-bots="<?php echo $has_bots ? '1' : '0'; ?>"
             data-max-bots="<?php echo esc_attr( $max_bots === PHP_INT_MAX ? 'unlimited' : $max_bots ); ?>"
             data-bot-count="<?php echo esc_attr( $bot_count ); ?>"
             data-can-create="<?php echo $can_create ? '1' : '0'; ?>"
             data-upgrade-url="<?php echo esc_attr( esc_url( $upgrade_url ) ); ?>"
             data-reusable-context-id="<?php echo esc_attr( $reusable_context_id ); ?>">
        </div>

        <noscript>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'This wizard requires JavaScript to be enabled.', 'axiachat-ai' ); ?></p>
            </div>
        </noscript>
    </div>
    <?php
}

/**
 * Check capability for AJAX requests.
 */
function aichat_easycfg_require_cap() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
    }
}

// =============================================================================
// AJAX: Get wizard data (reload data if needed)
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_get_data', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    wp_send_json_success( [
        'chatbot_types'    => aichat_easycfg_get_chatbot_types(),
        'voice_tones'      => aichat_easycfg_get_voice_tones(),
        'response_lengths' => aichat_easycfg_get_response_lengths(),
        'providers'        => aichat_easycfg_get_providers(),
        'existing_bots'    => aichat_easycfg_get_existing_bots(),
    ] );
} );

// =============================================================================
// AJAX: Build system prompt preview
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_preview_prompt', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $config = [
        'chatbot_type'    => isset( $_POST['chatbot_type'] ) ? sanitize_key( wp_unslash( $_POST['chatbot_type'] ) ) : 'customer_service',
        'voice_tone'      => isset( $_POST['voice_tone'] ) ? sanitize_key( wp_unslash( $_POST['voice_tone'] ) ) : 'friendly',
        'response_length' => isset( $_POST['response_length'] ) ? sanitize_key( wp_unslash( $_POST['response_length'] ) ) : 'short',
        'guidelines'      => [],
    ];

    if ( isset( $_POST['guidelines'] ) && is_array( $_POST['guidelines'] ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_guidelines = wp_unslash( $_POST['guidelines'] );
        foreach ( $raw_guidelines as $g ) {
            $config['guidelines'][] = sanitize_text_field( $g );
        }
    }

    $prompt = aichat_easycfg_build_system_prompt( $config );

    wp_send_json_success( [ 'prompt' => $prompt ] );
} );

// =============================================================================
// AJAX: Discover site content (smart or legacy mode)
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_discover', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'smart';

    if ( 'smart' === $mode ) {
        $data = aichat_easycfg_discover_smart();
        // Append WooCommerce products as a separate list.
        $woo               = aichat_easycfg_get_woo_products();
        $data['woo_items'] = $woo;
        $data['has_woo']   = ! empty( $woo );
        $data['woo_total'] = count( $woo );
        wp_send_json_success( $data );
    }

    // Legacy fallback — pages & posts only (products go in woo_items).
    $limit      = 200;
    $post_types = [ 'post', 'page' ];

    $args = [
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    $ids   = get_posts( $args );
    $ids   = aichat_easycfg_exclude_test_page_ids( $ids );
    $items = [];

    foreach ( $ids as $pid ) {
        $p = get_post( $pid );
        if ( ! $p ) {
            continue;
        }
        $items[] = [
            'id'    => (int) $pid,
            'title' => get_the_title( $p ),
            'type'  => $p->post_type,
        ];
    }

    // Append WooCommerce products as a separate list.
    $woo = aichat_easycfg_get_woo_products();

    wp_send_json_success( [
        'total'     => count( $ids ),
        'ids'       => $ids,
        'items'     => $items,
        'mode'      => 'legacy',
        'woo_items' => $woo,
        'has_woo'   => ! empty( $woo ),
        'woo_total' => count( $woo ),
    ] );
} );

/**
 * Exclude AxiaChat's internal bot test page from discovery lists.
 *
 * The page is created by the plugin and stored in option `aichat_test_page_id`.
 * It should not be suggested for indexing/context ingestion.
 *
 * @param array $ids List of post IDs.
 * @return array Filtered post IDs.
 */
function aichat_easycfg_exclude_test_page_ids( $ids ) {
    if ( ! is_array( $ids ) || ! $ids ) {
        return $ids;
    }

    $test_page_id = (int) get_option( 'aichat_test_page_id', 0 );
    if ( $test_page_id < 1 ) {
        return $ids;
    }

    $out = [];
    foreach ( $ids as $pid ) {
        $pid = (int) $pid;
        if ( $pid && $pid !== $test_page_id ) {
            $out[] = $pid;
        }
    }

    return $out;
}

/**
 * Fetch ALL published WooCommerce products efficiently.
 *
 * Uses a direct DB query to handle large catalogues (10 000+ products)
 * without excessive memory or slow post‑object hydration.
 *
 * @return array [ { id, title, type:'product' }, … ]
 */
function aichat_easycfg_get_woo_products() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return [];
    }

    global $wpdb;

    $test_page_id   = (int) get_option( 'aichat_test_page_id', 0 );
    $exclude_clause = '';
    if ( $test_page_id > 0 ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $exclude_clause = $wpdb->prepare( ' AND ID != %d', $test_page_id );
    }

    // Single lightweight query — no post‑cache overhead.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $results = $wpdb->get_results(
        "SELECT ID, post_title
         FROM {$wpdb->posts}
         WHERE post_type = 'product'
           AND post_status = 'publish'{$exclude_clause}
         ORDER BY post_title ASC"
    );

    $items = [];
    if ( $results ) {
        foreach ( $results as $row ) {
            $items[] = [
                'id'    => (int) $row->ID,
                'title' => $row->post_title,
                'type'  => 'product',
            ];
        }
    }

    return $items;
}

/**
 * Smart discovery: prioritizes homepage -> internal links -> legal pages -> products.
 *
 * @return array
 */
function aichat_easycfg_discover_smart() {
    $home_url  = home_url( '/' );
    $max_total = 200;
    $ids       = [];
    $seen      = [];

    $test_page_id = (int) get_option( 'aichat_test_page_id', 0 );

    // 1. Homepage (if static front page is set).
    $front_id = (int) get_option( 'page_on_front' );
    if ( $front_id ) {
        if ( $test_page_id > 0 && $front_id === $test_page_id ) {
            $front_id = 0;
        }
    }

    if ( $front_id ) {
        $ids[]            = $front_id;
        $seen[ $front_id ] = true;

        $content  = get_post_field( 'post_content', $front_id );
        $link_ids = aichat_easycfg_extract_linked_post_ids( $content, $home_url, 80 );

        foreach ( $link_ids as $pid ) {
            if ( $test_page_id > 0 && (int) $pid === $test_page_id ) {
                continue;
            }
            if ( ! isset( $seen[ $pid ] ) ) {
                $ids[]        = $pid;
                $seen[ $pid ] = true;
            }
        }
    }

    // 2. Legal/FAQ/About pages by slug/title heuristics.
    $legal_needles = [
        'aviso-legal', 'legal', 'termin', 'condicion', 'terminos',
        'faq', 'preguntas', 'privacy', 'privacidad', 'cookies',
        'about', 'quienes', 'envio', 'devol', 'contact',
    ];

    $legal_pages = get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'fields'         => 'ids',
    ] );

    foreach ( $legal_pages as $pid ) {
        if ( $test_page_id > 0 && (int) $pid === $test_page_id ) {
            continue;
        }
        if ( isset( $seen[ $pid ] ) ) {
            continue;
        }

        $p = get_post( $pid );
        if ( ! $p ) {
            continue;
        }

        $slug  = mb_strtolower( $p->post_name );
        $title = mb_strtolower( $p->post_title );

        foreach ( $legal_needles as $needle ) {
            if ( false !== strpos( $slug, $needle ) || false !== strpos( $title, $needle ) ) {
                $ids[]        = $pid;
                $seen[ $pid ] = true;
                break;
            }
        }
    }

    // 3. WooCommerce shop page (products are handled separately via woo_items).
    if ( class_exists( 'WooCommerce' ) ) {
        // Shop page stays in the main web-content list (it's a regular page).
        if ( function_exists( 'wc_get_page_id' ) ) {
            $shop_id = (int) wc_get_page_id( 'shop' );
            if ( $shop_id > 0 && ( $test_page_id < 1 || $shop_id !== $test_page_id ) && ! isset( $seen[ $shop_id ] ) ) {
                $ids[]            = $shop_id;
                $seen[ $shop_id ] = true;
            }
        }
    }

    // 4. Fallback if too few items.
    if ( count( $ids ) < 5 ) {
        $extra = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => ( 5 - count( $ids ) ) * 2,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        foreach ( $extra as $pid ) {
            if ( $test_page_id > 0 && (int) $pid === $test_page_id ) {
                continue;
            }
            if ( ! isset( $seen[ $pid ] ) ) {
                $ids[]        = $pid;
                $seen[ $pid ] = true;
            }
        }
    }

    // Ensure the internal test page never shows up.
    $ids = aichat_easycfg_exclude_test_page_ids( $ids );
    $ids = array_slice( $ids, 0, $max_total );

    // Build items metadata — exclude products (they go in woo_items).
    $items = [];
    foreach ( $ids as $pid ) {
        $p = get_post( $pid );
        if ( $p && 'product' !== $p->post_type ) {
            $items[] = [
                'id'    => (int) $pid,
                'title' => get_the_title( $p ),
                'type'  => $p->post_type,
            ];
        }
    }

    return [
        'total' => count( $items ),
        'ids'   => wp_list_pluck( $items, 'id' ),
        'items' => $items,
        'mode'  => 'smart',
    ];
}

/**
 * Extract linked post IDs from HTML content.
 *
 * @param string $html     HTML content.
 * @param string $home_url Home URL for filtering.
 * @param int    $max      Maximum links to extract.
 * @return array
 */
function aichat_easycfg_extract_linked_post_ids( $html, $home_url, $max = 80 ) {
    $out = [];

    if ( ! is_string( $html ) || '' === $html ) {
        return $out;
    }

    libxml_use_internal_errors( true );
    $dom = new DOMDocument();

    if ( ! $dom->loadHTML( '<?xml encoding="utf-8"?>' . $html ) ) {
        return $out;
    }

    $links     = $dom->getElementsByTagName( 'a' );
    $seen_urls = [];

    foreach ( $links as $a ) {
        $href = $a->getAttribute( 'href' );

        if ( ! $href ) {
            continue;
        }

        // Skip anchors, mailto, tel.
        if ( 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) {
            continue;
        }

        // Normalize relative URLs.
        if ( ! preg_match( '~^https?://~', $href ) ) {
            $href = rtrim( $home_url, '/' ) . '/' . ltrim( $href, '/' );
        }

        // Same domain only.
        if ( 0 !== strpos( $href, $home_url ) ) {
            continue;
        }

        $href = strtok( $href, '#' );
        $href = preg_replace( '~[?].*$~', '', $href );

        if ( isset( $seen_urls[ $href ] ) ) {
            continue;
        }

        $seen_urls[ $href ] = true;

        $pid = url_to_postid( $href );
        if ( $pid && 'publish' === get_post_status( $pid ) ) {
            $out[] = $pid;
        }

        if ( count( $out ) >= $max ) {
            break;
        }
    }

    return $out;
}

// =============================================================================
// AJAX: Create context
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_create_context', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    global $wpdb;
    $table = $wpdb->prefix . 'aichat_contexts';

    // Provider para embeddings (normalizar gpt -> openai).
    $provider_raw = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'openai';
    $provider     = ( 'gpt' === $provider_raw ) ? 'openai' : $provider_raw;

    // Check if we should reuse an existing empty context (first wizard run, default context with 0 docs).
    $reuse_id = isset( $_POST['reusable_context_id'] ) ? absint( wp_unslash( $_POST['reusable_context_id'] ) ) : 0;
    if ( $reuse_id > 0 ) {
        $chk_table = $wpdb->prefix . 'aichat_chunks';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $chunk_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$chk_table} WHERE id_context = %d",
                $reuse_id
            )
        );
        if ( 0 === $chunk_count ) {
            // Reuse: update provider and status, keep existing name.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update(
                $table,
                [
                    'embedding_provider'  => $provider,
                    'processing_status'   => 'wizard',
                    'processing_progress' => 0,
                ],
                [ 'id' => $reuse_id ],
                [ '%s', '%s', '%d' ],
                [ '%d' ]
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $reuse_name = (string) $wpdb->get_var(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT name FROM {$table} WHERE id = %d",
                    $reuse_id
                )
            );
            wp_send_json_success( [
                'context_id' => $reuse_id,
                'name'       => $reuse_name,
                'provider'   => $provider,
                'reused'     => true,
            ] );
        }
    }

    // Create a new context with incremental name: "Wizard Context 1", "Wizard Context 2", etc.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $max_num = (int) $wpdb->get_var(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
        "SELECT MAX(CAST(SUBSTRING_INDEX(name, ' ', -1) AS UNSIGNED)) 
         FROM {$table} 
         WHERE name LIKE 'Wizard Context %'"
    );
    $next_num = max( 1, $max_num + 1 );
    $name     = sprintf( 'Wizard Context %d', $next_num );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $ok = $wpdb->insert(
        $table,
        [
            'name'                => $name,
            'context_type'        => 'local',
            'embedding_provider'  => $provider,
            'processing_status'   => 'wizard',  // Use 'wizard' status so cron ignores it
            'processing_progress' => 0,
        ],
        [ '%s', '%s', '%s', '%s', '%d' ]
    );

    if ( ! $ok ) {
        wp_send_json_error( [ 'message' => 'db_insert_failed' ] );
    }

    wp_send_json_success( [
        'context_id' => (int) $wpdb->insert_id,
        'name'       => $name,
        'provider'   => $provider,
    ] );
} );

// =============================================================================
// AJAX: Index batch of posts
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_index_batch', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $context_id = isset( $_POST['context_id'] ) ? absint( wp_unslash( $_POST['context_id'] ) ) : 0;
    $batch      = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : [];
    $processed  = [];

    foreach ( $batch as $pid ) {
        $pid = (int) $pid;
        if ( $pid <= 0 ) {
            continue;
        }

        $ok          = function_exists( 'aichat_index_post' ) ? aichat_index_post( $pid, $context_id ) : false;
        $processed[] = [ 'id' => $pid, 'ok' => $ok ? 1 : 0 ];
    }

    wp_send_json_success( [ 'processed' => $processed ] );
} );

// =============================================================================
// AJAX: Index manual text content
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_index_text', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $context_id = isset( $_POST['context_id'] ) ? absint( wp_unslash( $_POST['context_id'] ) ) : 0;
    $text       = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
    $title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Manual Information', 'axiachat-ai' );

    if ( ! $context_id || empty( $text ) ) {
        wp_send_json_error( [ 'message' => 'missing_data' ] );
    }

    // Index the text as a custom chunk.
    $ok = false;
    if ( function_exists( 'aichat_index_custom_text' ) ) {
        $ok = aichat_index_custom_text( $text, $context_id, $title );
    } elseif ( function_exists( 'aichat_index_text_chunk' ) ) {
        $ok = aichat_index_text_chunk( $text, $context_id, 0, $title );
    }

    wp_send_json_success( [ 'indexed' => $ok ? 1 : 0 ] );
} );

// =============================================================================
// AJAX: Upload and index file (txt/pdf) - Full processing like contexto-pdf
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_upload_file', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $context_id    = isset( $_POST['context_id'] ) ? absint( wp_unslash( $_POST['context_id'] ) ) : 0;
    $use_ai_vision = isset( $_POST['use_ai_vision'] ) && '1' === $_POST['use_ai_vision'];

    aichat_log_debug( '[AIChat] Wizard upload start', [
        'context_id'    => $context_id,
        'use_ai_vision' => $use_ai_vision,
        'has_file'      => ! empty( $_FILES['file'] ),
    ] );

    if ( ! $context_id ) {
        wp_send_json_error( [ 'message' => 'missing_context_id' ] );
    }

    if ( empty( $_FILES['file'] ) ) {
        wp_send_json_error( [ 'message' => 'no_file_uploaded' ] );
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES handled via server-side validation
    $file = $_FILES['file'];

    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => 'upload_error_code_' . $file['error'] ] );
    }

    $name = sanitize_file_name( $file['name'] );
    $tmp  = $file['tmp_name'];
    $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

    if ( ! in_array( $ext, [ 'txt', 'pdf' ], true ) ) {
        wp_send_json_error( [ 'message' => 'invalid_file_type' ] );
    }

    // Validate MIME.
    $filetype = wp_check_filetype_and_ext( $tmp, $name );
    $mime     = $filetype['type'] ?: ( $file['type'] ?: mime_content_type( $tmp ) );

    if ( 'pdf' === $ext && $mime !== 'application/pdf' ) {
        wp_send_json_error( [ 'message' => 'invalid_pdf_mime' ] );
    }

    // Size cap (10MB).
    $max_bytes = apply_filters( 'aichat_easycfg_max_bytes', 10 * 1024 * 1024 );
    if ( $file['size'] > $max_bytes ) {
        wp_send_json_error( [ 'message' => 'file_too_large' ] );
    }

    // Store file persistently using aichat_upload_dir if available.
    $dest_path = '';
    if ( function_exists( 'aichat_upload_dir' ) ) {
        $dir  = aichat_upload_dir();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = file_get_contents( $tmp );
        if ( false === $data ) {
            wp_send_json_error( [ 'message' => 'could_not_read_file' ] );
        }
        $sha       = hash( 'sha256', $data );
        $safe_base = $sha . '.' . $ext;
        $dest_path = trailingslashit( $dir ) . $safe_base;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( ! file_put_contents( $dest_path, $data ) ) {
            wp_send_json_error( [ 'message' => 'could_not_store_file' ] );
        }
    } else {
        // Fallback: use tmp path directly.
        $dest_path = $tmp;
    }

    // Create aichat_upload post (parent).
    $upload_post_id = wp_insert_post( [
        'post_type'   => 'aichat_upload',
        'post_status' => 'private',
        'post_title'  => $name,
    ] );

    if ( ! $upload_post_id || is_wp_error( $upload_post_id ) ) {
        wp_send_json_error( [ 'message' => 'could_not_create_upload_post' ] );
    }

    add_post_meta( $upload_post_id, '_aichat_filename', $name, true );
    add_post_meta( $upload_post_id, '_aichat_mime', $mime, true );
    add_post_meta( $upload_post_id, '_aichat_size', (int) $file['size'], true );
    add_post_meta( $upload_post_id, '_aichat_path', $dest_path, true );
    add_post_meta( $upload_post_id, '_aichat_status', 'uploaded', true );
    add_post_meta( $upload_post_id, '_aichat_chunk_count', 0, true );
    add_post_meta( $upload_post_id, '_aichat_context_id', $context_id, true );

    aichat_log_debug( '[AIChat] Wizard upload post created', [
        'upload_post_id' => $upload_post_id,
        'filename'       => $name,
        'ext'            => $ext,
        'mime'           => $mime,
        'dest_path'      => $dest_path,
    ] );

    // Extract text content.
    $content          = '';
    $extraction_method = 'none';

    if ( function_exists( 'aichat_extract_text' ) ) {
        aichat_log_debug( '[AIChat] Wizard: trying aichat_extract_text' );
        $result = aichat_extract_text( $dest_path, $mime, $name );
        if ( is_wp_error( $result ) ) {
            aichat_log_debug( '[AIChat] Wizard: aichat_extract_text failed', [ 'error' => $result->get_error_message() ] );
        } else {
            $content           = $result;
            $extraction_method = 'aichat_extract_text';
        }
    }

    // Fallback extraction for PDFs/TXTs.
    if ( empty( trim( $content ) ) ) {
        if ( 'txt' === $ext ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content           = file_get_contents( $dest_path );
            $content           = wp_check_invalid_utf8( $content, true );
            $extraction_method = 'file_get_contents';
            aichat_log_debug( '[AIChat] Wizard: TXT read via file_get_contents', [ 'len' => strlen( $content ) ] );
        } elseif ( 'pdf' === $ext ) {
            // Try pdftotext first.
            if ( function_exists( 'aichat_pdftotext_available' ) && aichat_pdftotext_available() ) {
                $cmd     = 'pdftotext -enc UTF-8 -q ' . escapeshellarg( $dest_path ) . ' -';
                $content = @shell_exec( $cmd );
                if ( ! empty( trim( $content ) ) ) {
                    $extraction_method = 'pdftotext';
                    aichat_log_debug( '[AIChat] Wizard: pdftotext success', [ 'len' => strlen( $content ) ] );
                }
            }
            // Fallback to Smalot.
            if ( empty( trim( $content ) ) && class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
                try {
                    aichat_log_debug( '[AIChat] Wizard: trying Smalot PDF parser' );
                    $parser  = new \Smalot\PdfParser\Parser();
                    $pdf     = $parser->parseFile( $dest_path );
                    $content = $pdf->getText();
                    if ( ! empty( trim( $content ) ) ) {
                        $extraction_method = 'smalot';
                        aichat_log_debug( '[AIChat] Wizard: Smalot success', [ 'len' => strlen( $content ) ] );
                    }
                } catch ( \Exception $e ) {
                    aichat_log_debug( '[AIChat] Wizard: Smalot failed', [ 'error' => $e->getMessage() ] );
                }
            }
        }
    }

    aichat_log_debug( '[AIChat] Wizard: after standard extraction', [
        'method'      => $extraction_method,
        'content_len' => strlen( $content ),
        'is_empty'    => empty( trim( $content ) ),
    ] );

    // AI Vision fallback for PDFs when content is empty and enabled.
    if ( 'pdf' === $ext && empty( trim( $content ) ) && $use_ai_vision ) {
        aichat_log_debug( '[AIChat] Wizard: trying AI Vision fallback for PDF' );

        if ( function_exists( 'aichat_pdf_to_text_via_ai' ) ) {
            // Determine which provider to use for vision.
            $vision_provider = 'openai';
            if ( ! empty( aichat_get_setting( 'aichat_gemini_api_key' ) ) ) {
                $vision_provider = 'gemini';
            }
            if ( ! empty( aichat_get_setting( 'aichat_openai_api_key' ) ) ) {
                $vision_provider = 'openai';
            }

            // Max pages for AI Vision (each page ~15-20 seconds, so 5 pages ~1.5 min)
            $max_vision_pages = apply_filters( 'aichat_vision_max_pages', 5 );
            
            aichat_log_debug( '[AIChat] Wizard: AI Vision using provider', [ 
                'provider'  => $vision_provider,
                'max_pages' => $max_vision_pages,
            ] );

            $vision_result = aichat_pdf_to_text_via_ai( $dest_path, $vision_provider, [ 'max_pages' => $max_vision_pages ] );

            if ( is_wp_error( $vision_result ) ) {
                aichat_log_debug( '[AIChat] Wizard: AI Vision failed', [ 'error' => $vision_result->get_error_message() ] );
            } else {
                $content           = $vision_result['text'];
                $extraction_method = 'ai_vision_' . $vision_provider;
                aichat_log_debug( '[AIChat] Wizard: AI Vision success', [
                    'provider'   => $vision_provider,
                    'pages'      => $vision_result['processed'],
                    'total'      => $vision_result['total_pages'],
                    'len'        => strlen( $content ),
                ] );
            }
        } else {
            aichat_log_debug( '[AIChat] Wizard: aichat_pdf_to_text_via_ai function not available' );
        }
    }

    if ( empty( trim( $content ) ) ) {
        aichat_log_debug( '[AIChat] Wizard: final content is empty, returning error' );
        update_post_meta( $upload_post_id, '_aichat_status', 'empty' );
        wp_send_json_error( [ 'message' => 'empty_file_content' ] );
    }

    aichat_log_debug( '[AIChat] Wizard: extraction complete', [
        'method'      => $extraction_method,
        'content_len' => strlen( $content ),
    ] );

    // Validate content before chunking
    if ( ! is_string( $content ) || strlen( trim( $content ) ) === 0 ) {
        aichat_log_debug( '[AIChat] Wizard: content invalid for chunking', [
            'is_string' => is_string( $content ),
            'type'      => gettype( $content ),
        ] );
        update_post_meta( $upload_post_id, '_aichat_status', 'empty' );
        wp_send_json_error( [ 'message' => 'empty_file_content' ] );
    }

    // Clean content - remove null bytes and invalid UTF-8
    $content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content );
    if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
        $content = mb_convert_encoding( $content, 'UTF-8', 'auto' );
    }
    
    aichat_log_debug( '[AIChat] Wizard: content cleaned', [ 'content_len' => strlen( $content ) ] );

    // Create chunk posts using aichat_create_chunks_posts if available.
    $chunk_ids = [];
    if ( function_exists( 'aichat_create_chunks_posts' ) ) {
        aichat_log_debug( '[AIChat] Wizard: calling aichat_create_chunks_posts', [
            'upload_post_id' => $upload_post_id,
            'filename'       => $name,
            'content_len'    => strlen( $content ),
            'content_preview'=> substr( $content, 0, 200 ),
        ] );
        $chunk_ids = aichat_create_chunks_posts( $upload_post_id, $name, $content );
        aichat_log_debug( '[AIChat] Wizard: chunks created', [ 'count' => count( $chunk_ids ) ] );
    } else {
        // Fallback: basic chunking.
        $chunk_ids = aichat_easycfg_create_chunk_posts( $upload_post_id, $name, $content );
    }

    update_post_meta( $upload_post_id, '_aichat_status', 'chunked' );
    update_post_meta( $upload_post_id, '_aichat_chunk_count', count( $chunk_ids ) );

    // Index each chunk post into the context.
    $indexed_count = 0;
    if ( function_exists( 'aichat_index_post' ) ) {
        foreach ( $chunk_ids as $chunk_post_id ) {
            $ok = aichat_index_post( $chunk_post_id, $context_id );
            if ( $ok ) {
                $indexed_count++;
            }
        }
    }

    update_post_meta( $upload_post_id, '_aichat_status', 'indexed' );

    wp_send_json_success( [
        'indexed'       => $indexed_count,
        'chunks'        => count( $chunk_ids ),
        'chunk_ids'     => $chunk_ids,
        'upload_id'     => $upload_post_id,
        'filename'      => $name,
        'content_size'  => strlen( $content ),
    ] );
} );

/**
 * Fallback chunking function for when aichat_create_chunks_posts is not available.
 *
 * @param int    $upload_post_id Parent upload post ID.
 * @param string $filename       Original filename.
 * @param string $text           Full text content.
 * @return array Array of created chunk post IDs.
 */
function aichat_easycfg_create_chunk_posts( $upload_post_id, $filename, $text ) {
    $target_words = apply_filters( 'aichat_chunk_words', 1000 );
    $overlap      = apply_filters( 'aichat_chunk_overlap', 180 );

    $text  = trim( preg_replace( "/\r\n|\r/", "\n", $text ) );
    $words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
    $n     = count( $words );

    if ( 0 === $n ) {
        return [];
    }

    $chunks = [];
    $i      = 0;
    $idx    = 0;

    while ( $i < $n ) {
        $end   = min( $n, $i + $target_words );
        $slice = array_slice( $words, $i, $end - $i );
        $chunk = trim( implode( ' ', $slice ) );

        if ( '' !== $chunk ) {
            $chunks[] = [
                'index' => $idx++,
                'text'  => $chunk,
            ];
        }

        if ( $end >= $n ) {
            break;
        }

        $i = max( $end - $overlap, $i + 1 );
    }

    $ids   = [];
    $total = count( $chunks );

    foreach ( $chunks as $c ) {
        $title   = sprintf( '%s (chunk %d/%d)', $filename, $c['index'] + 1, $total );
        $post_id = wp_insert_post( [
            'post_type'    => 'aichat_upload_chunk',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $c['text'],
        ] );

        if ( $post_id && ! is_wp_error( $post_id ) ) {
            add_post_meta( $post_id, '_aichat_upload_id', (int) $upload_post_id, true );
            add_post_meta( $post_id, '_aichat_chunk_index', (int) $c['index'], true );
            add_post_meta( $post_id, '_aichat_tokens', str_word_count( $c['text'] ), true );
            $ids[] = (int) $post_id;
        }
    }

    return $ids;
}

// =============================================================================
// AJAX: Save API key
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_save_api_key', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'openai';
    $key      = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

    $providers = aichat_easycfg_get_providers();

    if ( ! isset( $providers[ $provider ] ) ) {
        wp_send_json_error( [ 'message' => 'invalid_provider' ] );
    }

    $option_key = $providers[ $provider ]['option_key'];

    if ( $key ) {
        update_option( $option_key, $key );

        // Fix double encryption if needed.
        if ( function_exists( 'aichat_is_encrypted_value' ) && function_exists( 'aichat_decrypt_value' ) ) {
            $raw_from_db = get_option( $option_key, '' );
            if ( aichat_is_encrypted_value( $raw_from_db ) ) {
                $first_pass = aichat_decrypt_value( $raw_from_db );
                if ( is_string( $first_pass ) && aichat_is_encrypted_value( $first_pass ) ) {
                    update_option( $option_key, $first_pass );
                }
            }
        }
    }

    wp_send_json_success( [ 'saved' => $key ? 1 : 0, 'provider' => $provider ] );
} );

// =============================================================================
// AJAX: Get provider status
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_status', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $providers       = aichat_easycfg_get_providers();
    $provider_status = [];

    foreach ( $providers as $key => $provider ) {
        $opt_key = isset( $provider['option_key'] ) ? $provider['option_key'] : '';
        $has_key = false;

        if ( $opt_key && function_exists( 'aichat_get_setting' ) ) {
            $val     = aichat_get_setting( $opt_key );
            $has_key = ! empty( $val );
        }

        $provider_status[ $key ] = $has_key;
    }

    wp_send_json_success( [ 'provider_status' => $provider_status ] );
} );

// =============================================================================
// AJAX: Create or update bot
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_save_bot', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    global $wpdb;
    $table = aichat_bots_table();

    // Mode: 'new' or 'overwrite'.
    $mode       = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'new';
    $bot_id     = isset( $_POST['bot_id'] ) ? absint( wp_unslash( $_POST['bot_id'] ) ) : 0;
    $context_id = isset( $_POST['context_id'] ) ? absint( wp_unslash( $_POST['context_id'] ) ) : 0;

    aichat_log_debug( '[EasyCfg] save_bot START', [
        'mode'       => $mode,
        'bot_id'     => $bot_id,
        'context_id' => $context_id,
    ] );

    // Provider selected in step 3.
    $provider_raw = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'openai';
    // Normalize provider: gpt -> openai.
    $provider = ( 'gpt' === $provider_raw ) ? 'openai' : $provider_raw;

    // Config for building prompt.
    $config = [
        'chatbot_type'    => isset( $_POST['chatbot_type'] ) ? sanitize_key( wp_unslash( $_POST['chatbot_type'] ) ) : 'customer_service',
        'voice_tone'      => isset( $_POST['voice_tone'] ) ? sanitize_key( wp_unslash( $_POST['voice_tone'] ) ) : 'friendly',
        'response_length' => isset( $_POST['response_length'] ) ? sanitize_key( wp_unslash( $_POST['response_length'] ) ) : 'short',
        'guidelines'      => [],
    ];

    if ( isset( $_POST['guidelines'] ) && is_array( $_POST['guidelines'] ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_guidelines = wp_unslash( $_POST['guidelines'] );
        foreach ( $raw_guidelines as $g ) {
            $g = sanitize_text_field( $g );
            if ( ! empty( $g ) ) {
                $config['guidelines'][] = $g;
            }
        }
    }

    // Build the system prompt.
    $instructions = aichat_easycfg_build_system_prompt( $config );

    // Bot name.
    $types    = aichat_easycfg_get_chatbot_types();
    $type_key = $config['chatbot_type'];
    $bot_name = isset( $types[ $type_key ]['name'] ) ? $types[ $type_key ]['name'] : __( 'AI Assistant', 'axiachat-ai' );

    $result_slug = '';

    if ( 'overwrite' === $mode && $bot_id > 0 ) {
        // Update existing bot - also update provider and model.
        $model = aichat_easycfg_default_model( $provider );

        aichat_log_debug( '[EasyCfg] OVERWRITE existing bot', [
            'bot_id'   => $bot_id,
            'provider' => $provider,
            'model'    => $model,
        ] );

        $update_result = $wpdb->update(
            $table,
            [
                'provider'      => $provider,
                'model'         => $model,
                'instructions'  => $instructions,
                'context_mode'  => $context_id ? 'embeddings' : 'none',
                'context_id'    => $context_id,
                'updated_at'    => current_time( 'mysql' ),
            ],
            [ 'id' => $bot_id ],
            [ '%s', '%s', '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        aichat_log_debug( '[EasyCfg] OVERWRITE result', [
            'rows_affected' => $update_result,
            'last_error'    => $wpdb->last_error,
        ] );

        $result_id = $bot_id;

        // Get existing slug for global bot setting.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result_slug = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "SELECT slug FROM {$table} WHERE id = %d", $bot_id )
        );
    } else {
        // Create new bot.
        $slug = sanitize_title( $bot_name );
        $slug = aichat_easycfg_unique_slug( $slug, $table );

        // Bot defaults: random avatar (1-5), model based on provider, chips enabled.
        $avatar_key = (string) wp_rand( 1, 5 );
        $model      = aichat_easycfg_default_model( $provider );

        aichat_log_debug( '[EasyCfg] CREATE new bot', [
            'slug'     => $slug,
            'bot_name' => $bot_name,
            'provider' => $provider,
            'model'    => $model,
        ] );

        $insert_result = $wpdb->insert(
            $table,
            [
                'slug'                   => $slug,
                'name'                   => $bot_name,
                'provider'               => $provider,
                'model'                  => $model,
                'instructions'           => $instructions,
                'context_mode'           => $context_id ? 'embeddings' : 'none',
                'context_id'             => $context_id,
                'ui_avatar_enabled'      => 1,
                'ui_avatar_key'          => $avatar_key,
                'ui_suggestions_enabled' => 1,
                'created_at'             => current_time( 'mysql' ),
                'updated_at'             => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' ]
        );

        aichat_log_debug( '[EasyCfg] CREATE result', [
            'insert_result' => $insert_result,
            'insert_id'     => $wpdb->insert_id,
            'last_error'    => $wpdb->last_error,
        ] );

        $result_id   = (int) $wpdb->insert_id;
        $result_slug = $slug;
    }

    aichat_log_debug( '[EasyCfg] save_bot END', [
        'result_id'   => $result_id,
        'result_slug' => $result_slug,
        'mode'        => $mode,
    ] );

    // Mark context as completed (always mark, even if empty, to exit 'wizard' status).
    if ( $context_id ) {
        $ctx_table    = $wpdb->prefix . 'aichat_contexts';
        $chunks_table = $wpdb->prefix . 'aichat_chunks';

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $chunks_table is a trusted plugin table name via $wpdb->prefix.
        $chunk_count = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $chunks_table is a trusted plugin table name.
            $wpdb->prepare( "SELECT COUNT(*) FROM {$chunks_table} WHERE id_context=%d", $context_id )
        );

        // Always mark as completed with appropriate progress
        $progress = ( $chunk_count > 0 ) ? 100 : 0;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $ctx_table,
            [ 'processing_status' => 'completed', 'processing_progress' => $progress ],
            [ 'id' => $context_id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );
    }

    // Mark wizard as completed.
    update_option( 'aichat_easy_config_completed', 1 );

    wp_send_json_success( [
        'bot_id'     => $result_id,
        'bot_slug'   => $result_slug,
        'context_id' => $context_id,
        'mode'       => $mode,
    ] );
} );

/**
 * Generate unique slug for bot.
 *
 * @param string $slug  Base slug.
 * @param string $table Table name.
 * @return string
 */
function aichat_easycfg_unique_slug( $slug, $table ) {
    global $wpdb;

    $original = $slug;
    $counter  = 1;

    while ( true ) {
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
        $exists = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", $slug )
        );

        if ( ! $exists ) {
            break;
        }

        $slug = $original . '-' . $counter;
        $counter++;
    }

    return $slug;
}

// =============================================================================
// AJAX: Save global bot settings
// =============================================================================
add_action( 'wp_ajax_aichat_easycfg_save_global_bot', function() {
    aichat_easycfg_require_cap();
    check_ajax_referer( 'aichat_easycfg', 'nonce' );

    $slug = isset( $_POST['bot_slug'] ) ? sanitize_title( wp_unslash( $_POST['bot_slug'] ) ) : '';

    if ( '' === $slug ) {
        wp_send_json_error( [ 'message' => 'missing_slug' ], 400 );
    }

    update_option( 'aichat_global_bot_enabled', 1 );
    update_option( 'aichat_global_bot_slug', $slug );

    // Ensure logging is enabled by default.
    if ( null === get_option( 'aichat_logging_enabled', null ) ) {
        update_option( 'aichat_logging_enabled', 1 );
    }

    wp_send_json_success( [ 'saved' => 1, 'slug' => $slug ] );
} );

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
