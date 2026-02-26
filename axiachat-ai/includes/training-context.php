<?php
/**
 * Training – Context Sub-page
 *
 * Unified context management: 3 source boxes (WordPress, WooCommerce, PDF/TXT),
 * inline content selection, advanced settings accordion, and action buttons.
 *
 * @package AxiaChat
 * @since   3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aichat_training_context_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $bot_slug = isset( $_GET['bot'] ) ? sanitize_title( wp_unslash( $_GET['bot'] ) ) : '';

    $bots_table = $wpdb->prefix . 'aichat_bots';
    $ctx_table  = $wpdb->prefix . 'aichat_contexts';

    // Resolve current bot
    if ( $bot_slug ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bots_table} WHERE slug = %s LIMIT 1", $bot_slug ), ARRAY_A );
    }
    if ( empty( $bot ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bot = $wpdb->get_row( "SELECT * FROM {$bots_table} ORDER BY id ASC LIMIT 1", ARRAY_A );
    }
    if ( ! $bot ) {
        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'No bots found. Create one first.', 'axiachat-ai' ) . '</p></div></div>';
        return;
    }

    $bot_slug = $bot['slug'];

    // All bots for selector
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_bots = $wpdb->get_results( "SELECT id, name, slug FROM {$bots_table} WHERE is_active = 1 ORDER BY id ASC", ARRAY_A );
    if ( ! $all_bots ) { $all_bots = []; }

    // All contexts
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $contexts = $wpdb->get_results(
        "SELECT c.id, c.name, c.context_type, c.embedding_provider, c.processing_status, c.processing_progress,
                c.autosync, c.autosync_mode, c.indexing_options,
                c.remote_type, c.remote_api_key, c.remote_endpoint,
                (SELECT COUNT(*) FROM {$wpdb->prefix}aichat_chunks ch WHERE ch.id_context = c.id) AS chunk_count,
                (SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aichat_chunks ch2 WHERE ch2.id_context = c.id) AS post_count
         FROM {$ctx_table} c ORDER BY c.id ASC",
        ARRAY_A
    );
    if ( ! $contexts ) { $contexts = []; }

    // Current context for this bot
    $current_ctx_id = (int) ( $bot['context_id'] ?? 0 );
    $has_woocommerce = class_exists( 'WooCommerce' ) ? 1 : 0;

    // Embedding provider selector
    $embedding_provider_html = '';
    if ( function_exists( 'aichat_render_embedding_provider_select' ) ) {
        $embedding_provider_html = aichat_render_embedding_provider_select( '', 'aichat-ctx-embedding-provider', 'embedding_provider' );
    }

    $nonce       = wp_create_nonce( 'aichat_training' );
    $modify_nonce = wp_create_nonce( 'aichat_modify_nonce' );
    $create_nonce = wp_create_nonce( 'aichat_nonce' );
    $back_url    = admin_url( 'admin.php?page=aichat-training&bot=' . $bot_slug );

    // Context-related thresholds from bot
    $context_max_length = (int) ( $bot['context_max_length'] ?? 4096 );
    $context_limit      = (int) ( $bot['context_limit'] ?? 5 );
    $context_mode       = $bot['context_mode'] ?? 'embeddings';
    ?>
    <div class="wrap aichat-admin">
        <div class="aichat-training-context-wrap">

            <a href="<?php echo esc_url( $back_url ); ?>" class="aichat-training-back">
                <i class="bi bi-arrow-left"></i> <?php esc_html_e( 'Back to Training', 'axiachat-ai' ); ?>
            </a>

            <h1><i class="bi bi-database me-2"></i><?php esc_html_e( 'Context (Knowledge Base)', 'axiachat-ai' ); ?>
                <small class="text-muted" style="font-size:14px;font-weight:400;">&mdash; <?php echo esc_html( $bot['name'] ); ?></small>
            </h1>
            <p class="aichat-training-subtitle">
                <?php esc_html_e( 'Choose what your bot knows. Select the content sources that form its knowledge base.', 'axiachat-ai' ); ?>
            </p>

            <?php if ( count( $all_bots ) > 1 ) : ?>
            <div class="aichat-training-bot-selector mb-3">
                <label for="aichat-ctx-bot-select">
                    <i class="bi bi-robot me-1"></i><?php esc_html_e( 'Bot:', 'axiachat-ai' ); ?>
                </label>
                <select id="aichat-ctx-bot-select" class="form-select d-inline-block w-auto">
                    <?php foreach ( $all_bots as $b ) : ?>
                        <option value="<?php echo esc_attr( $b['slug'] ); ?>" <?php selected( $b['slug'], $bot_slug ); ?>>
                            <?php echo esc_html( $b['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Context selector (if >1 context exists) -->
            <?php if ( count( $contexts ) > 1 ) : ?>
            <div class="aichat-training-ctx-selector mb-4">
                <label for="aichat-ctx-select">
                    <i class="bi bi-layers me-1"></i><?php esc_html_e( 'Active Context:', 'axiachat-ai' ); ?>
                </label>
                <select id="aichat-ctx-select" class="form-select d-inline-block w-auto">
                    <?php foreach ( $contexts as $ctx ) : ?>
                        <option value="<?php echo esc_attr( $ctx['id'] ); ?>" <?php selected( (int) $ctx['id'], $current_ctx_id ); ?>
                                data-status="<?php echo esc_attr( $ctx['processing_status'] ); ?>"
                                data-progress="<?php echo esc_attr( $ctx['processing_progress'] ); ?>">
                            <?php echo esc_html( $ctx['name'] . ' (#' . $ctx['id'] . ') — ' . $ctx['post_count'] . ' docs, ' . $ctx['chunk_count'] . ' chunks' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Stats bar -->
            <div class="aichat-ctx-stats" id="aichat-ctx-stats">
                <?php
                $curr_ctx = null;
                foreach ( $contexts as $c ) {
                    if ( (int) $c['id'] === $current_ctx_id ) { $curr_ctx = $c; break; }
                }
                if ( $curr_ctx ) :
                ?>
                    <div class="aichat-ctx-stat-badge"><i class="bi bi-file-earmark-text me-1"></i> <strong><?php echo (int) $curr_ctx['post_count']; ?></strong> <?php esc_html_e( 'documents', 'axiachat-ai' ); ?></div>
                    <div class="aichat-ctx-stat-badge"><i class="bi bi-puzzle me-1"></i> <strong><?php echo (int) $curr_ctx['chunk_count']; ?></strong> <?php esc_html_e( 'chunks', 'axiachat-ai' ); ?></div>
                    <div class="aichat-ctx-stat-badge"><i class="bi bi-activity me-1"></i> <?php echo esc_html( ucfirst( $curr_ctx['processing_status'] ) ); ?> <?php echo (int) $curr_ctx['processing_progress']; ?>%</div>
                <?php elseif ( empty( $contexts ) ) : ?>
                    <div class="aichat-ctx-stat-badge"><i class="bi bi-info-circle me-1"></i> <?php esc_html_e( 'No context created yet. Configure sources below and save to create one.', 'axiachat-ai' ); ?></div>
                <?php endif; ?>
            </div>

            <!-- ==================== 3 SOURCE BOXES ==================== -->
            <div class="aichat-ctx-source-grid">

                <!-- WordPress -->
                <div class="aichat-ctx-source-card" id="aichat-ctx-card-wp">
                    <div class="aichat-ctx-source-header">
                        <div class="aichat-ctx-source-icon">📄</div>
                        <div>
                            <div class="aichat-ctx-source-title"><?php esc_html_e( 'WordPress', 'axiachat-ai' ); ?></div>
                            <div class="aichat-ctx-source-desc"><?php esc_html_e( 'Posts, pages, and custom post types.', 'axiachat-ai' ); ?></div>
                        </div>
                        <i class="bi bi-chevron-down aichat-ctx-source-toggle ms-auto"></i>
                    </div>
                    <div class="aichat-ctx-source-body">

                        <!-- Posts -->
                        <div class="mb-3">
                            <div class="fw-semibold small mb-1"><i class="bi bi-file-text me-1"></i><?php esc_html_e( 'Posts', 'axiachat-ai' ); ?></div>
                            <div class="aichat-ctx-wp-tabs">
                                <input type="radio" name="aichat_ctx_posts" value="none" id="ctx-posts-none" checked>
                                <label for="ctx-posts-none"><?php esc_html_e( 'None', 'axiachat-ai' ); ?></label>

                                <input type="radio" name="aichat_ctx_posts" value="all" id="ctx-posts-all">
                                <label for="ctx-posts-all"><?php esc_html_e( 'All Posts', 'axiachat-ai' ); ?></label>

                                <input type="radio" name="aichat_ctx_posts" value="recent" id="ctx-posts-recent">
                                <label for="ctx-posts-recent"><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?></label>

                                <input type="radio" name="aichat_ctx_posts" value="search" id="ctx-posts-search">
                                <label for="ctx-posts-search"><?php esc_html_e( 'Search', 'axiachat-ai' ); ?></label>
                            </div>
                            <div class="aichat-ctx-custom-panel" id="aichat-ctx-posts-panel">
                                <div class="aichat-ctx-search" id="aichat-ctx-posts-search-box" style="display:none;">
                                    <input type="text" placeholder="<?php esc_attr_e( 'Search posts...', 'axiachat-ai' ); ?>" data-type="post">
                                </div>
                                <div class="aichat-ctx-items" id="aichat-ctx-posts-items"></div>
                            </div>
                        </div>

                        <!-- Pages -->
                        <div class="mb-3">
                            <div class="fw-semibold small mb-1"><i class="bi bi-file-earmark-text me-1"></i><?php esc_html_e( 'Pages', 'axiachat-ai' ); ?></div>
                            <div class="aichat-ctx-wp-tabs">
                                <input type="radio" name="aichat_ctx_pages" value="none" id="ctx-pages-none" checked>
                                <label for="ctx-pages-none"><?php esc_html_e( 'None', 'axiachat-ai' ); ?></label>

                                <input type="radio" name="aichat_ctx_pages" value="all" id="ctx-pages-all">
                                <label for="ctx-pages-all"><?php esc_html_e( 'All Pages', 'axiachat-ai' ); ?></label>

                                <input type="radio" name="aichat_ctx_pages" value="recent" id="ctx-pages-recent">
                                <label for="ctx-pages-recent"><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?></label>

                                <input type="radio" name="aichat_ctx_pages" value="search" id="ctx-pages-search">
                                <label for="ctx-pages-search"><?php esc_html_e( 'Search', 'axiachat-ai' ); ?></label>
                            </div>
                            <div class="aichat-ctx-custom-panel" id="aichat-ctx-pages-panel">
                                <div class="aichat-ctx-search" id="aichat-ctx-pages-search-box" style="display:none;">
                                    <input type="text" placeholder="<?php esc_attr_e( 'Search pages...', 'axiachat-ai' ); ?>" data-type="page">
                                </div>
                                <div class="aichat-ctx-items" id="aichat-ctx-pages-items"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- WooCommerce -->
                <div class="aichat-ctx-source-card <?php echo $has_woocommerce ? '' : 'disabled'; ?>" id="aichat-ctx-card-woo">
                    <div class="aichat-ctx-source-header">
                        <div class="aichat-ctx-source-icon">🛒</div>
                        <div>
                            <div class="aichat-ctx-source-title"><?php esc_html_e( 'WooCommerce', 'axiachat-ai' ); ?></div>
                            <div class="aichat-ctx-source-desc">
                                <?php
                                if ( $has_woocommerce ) {
                                    esc_html_e( 'Products from your store.', 'axiachat-ai' );
                                } else {
                                    esc_html_e( 'WooCommerce not detected.', 'axiachat-ai' );
                                }
                                ?>
                            </div>
                        </div>
                        <i class="bi bi-chevron-down aichat-ctx-source-toggle ms-auto"></i>
                    </div>
                    <?php if ( $has_woocommerce ) : ?>
                    <div class="aichat-ctx-source-body">
                        <div class="aichat-ctx-wp-tabs">
                            <input type="radio" name="aichat_ctx_products" value="none" id="ctx-prods-none" checked>
                            <label for="ctx-prods-none"><?php esc_html_e( 'None', 'axiachat-ai' ); ?></label>

                            <input type="radio" name="aichat_ctx_products" value="all" id="ctx-prods-all">
                            <label for="ctx-prods-all"><?php esc_html_e( 'All Products', 'axiachat-ai' ); ?></label>

                            <input type="radio" name="aichat_ctx_products" value="recent" id="ctx-prods-recent">
                            <label for="ctx-prods-recent"><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?></label>

                            <input type="radio" name="aichat_ctx_products" value="search" id="ctx-prods-search">
                            <label for="ctx-prods-search"><?php esc_html_e( 'Search', 'axiachat-ai' ); ?></label>
                        </div>
                        <div class="aichat-ctx-custom-panel" id="aichat-ctx-products-panel">
                            <div class="aichat-ctx-search" id="aichat-ctx-products-search-box" style="display:none;">
                                <input type="text" placeholder="<?php esc_attr_e( 'Search products...', 'axiachat-ai' ); ?>" data-type="product">
                            </div>
                            <div class="aichat-ctx-items" id="aichat-ctx-products-items"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- PDF / TXT -->
                <div class="aichat-ctx-source-card" id="aichat-ctx-card-pdf">
                    <div class="aichat-ctx-source-header">
                        <div class="aichat-ctx-source-icon">📎</div>
                        <div>
                            <div class="aichat-ctx-source-title"><?php esc_html_e( 'PDF / TXT', 'axiachat-ai' ); ?></div>
                            <div class="aichat-ctx-source-desc"><?php esc_html_e( 'Upload documents to add to the knowledge base.', 'axiachat-ai' ); ?></div>
                        </div>
                        <i class="bi bi-chevron-down aichat-ctx-source-toggle ms-auto"></i>
                    </div>
                    <div class="aichat-ctx-source-body">
                        <div class="aichat-ctx-upload-zone" id="aichat-ctx-upload-zone">
                            <i class="bi bi-cloud-arrow-up d-block"></i>
                            <p><?php esc_html_e( 'Drop files here or click to upload (PDF, TXT)', 'axiachat-ai' ); ?></p>
                            <input type="file" id="aichat-ctx-file-input" accept=".pdf,.txt,application/pdf,text/plain" multiple style="display:none;">
                        </div>
                        <div class="aichat-ctx-file-list" id="aichat-ctx-file-list">
                            <!-- Uploaded files listed here by JS -->
                        </div>
                    </div>
                </div>

                <!-- Web Pages (External) -->
                <div class="aichat-ctx-source-card" id="aichat-ctx-card-web">
                    <div class="aichat-ctx-source-header">
                        <div class="aichat-ctx-source-icon">🌐</div>
                        <div>
                            <div class="aichat-ctx-source-title"><?php esc_html_e( 'Web Pages', 'axiachat-ai' ); ?></div>
                            <div class="aichat-ctx-source-desc"><?php esc_html_e( 'Import content from external websites.', 'axiachat-ai' ); ?></div>
                        </div>
                        <i class="bi bi-chevron-down aichat-ctx-source-toggle ms-auto"></i>
                    </div>
                    <div class="aichat-ctx-source-body">

                        <!-- Method tabs -->
                        <div class="aichat-ctx-web-tabs mb-3">
                            <input type="radio" name="aichat_ctx_web_method" value="urls" id="ctx-web-urls" checked>
                            <label for="ctx-web-urls"><i class="bi bi-link-45deg me-1"></i><?php esc_html_e( 'Import URLs', 'axiachat-ai' ); ?></label>

                            <input type="radio" name="aichat_ctx_web_method" value="crawl" id="ctx-web-crawl">
                            <label for="ctx-web-crawl"><i class="bi bi-diagram-3 me-1"></i><?php esc_html_e( 'Discover from Website', 'axiachat-ai' ); ?></label>
                        </div>

                        <!-- Method A: Paste URLs -->
                        <div id="aichat-ctx-web-urls-panel">
                            <div class="mb-2">
                                <textarea id="aichat-ctx-web-urls-input" class="form-control form-control-sm" rows="5"
                                    placeholder="<?php esc_attr_e( "Paste one URL per line:\nhttps://example.com/page-1\nhttps://example.com/page-2", 'axiachat-ai' ); ?>"></textarea>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="aichat-ctx-web-fetch-urls">
                                    <i class="bi bi-cloud-download me-1"></i><?php esc_html_e( 'Fetch Pages', 'axiachat-ai' ); ?>
                                </button>
                                <span class="small text-muted" id="aichat-ctx-web-urls-count"></span>
                            </div>
                        </div>

                        <!-- Method B: Crawl / Discover -->
                        <div id="aichat-ctx-web-crawl-panel" style="display:none;">
                            <div class="mb-2">
                                <input type="url" id="aichat-ctx-web-crawl-root" class="form-control form-control-sm"
                                    placeholder="<?php esc_attr_e( 'https://example.com', 'axiachat-ai' ); ?>">
                                <div class="form-text small"><?php esc_html_e( 'Enter the homepage or section URL. The tool will follow links within the same site.', 'axiachat-ai' ); ?></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label for="aichat-ctx-web-crawl-depth" class="form-label small fw-semibold"><?php esc_html_e( 'Link depth', 'axiachat-ai' ); ?></label>
                                    <select id="aichat-ctx-web-crawl-depth" class="form-select form-select-sm">
                                        <option value="1"><?php esc_html_e( '1 — Only links on that page', 'axiachat-ai' ); ?></option>
                                        <option value="2" selected><?php esc_html_e( '2 — Links + one level deeper', 'axiachat-ai' ); ?></option>
                                        <option value="3"><?php esc_html_e( '3 — Three levels deep', 'axiachat-ai' ); ?></option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="aichat-ctx-web-crawl-limit" class="form-label small fw-semibold"><?php esc_html_e( 'Max pages', 'axiachat-ai' ); ?></label>
                                    <select id="aichat-ctx-web-crawl-limit" class="form-select form-select-sm">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50" selected>50</option>
                                        <option value="100">100</option>
                                        <option value="200">200</option>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="aichat-ctx-web-crawl-start">
                                <i class="bi bi-search me-1"></i><?php esc_html_e( 'Discover Pages', 'axiachat-ai' ); ?>
                            </button>
                        </div>

                        <!-- Options -->
                        <div class="mt-3 pt-3 border-top">
                            <label style="cursor:pointer;" class="small">
                                <input type="checkbox" id="aichat-ctx-web-include-url" checked style="margin-right:6px;">
                                <?php esc_html_e( 'Include source URL in the knowledge base (so the bot can reference it in answers)', 'axiachat-ai' ); ?>
                            </label>
                        </div>

                        <!-- Discovered/fetched pages list -->
                        <div id="aichat-ctx-web-results" class="mt-3" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold small"><i class="bi bi-list-check me-1"></i><span id="aichat-ctx-web-results-title"><?php esc_html_e( 'Discovered Pages', 'axiachat-ai' ); ?></span></span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="aichat-ctx-web-select-all"><?php esc_html_e( 'Select All', 'axiachat-ai' ); ?></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="aichat-ctx-web-deselect-all"><?php esc_html_e( 'Deselect All', 'axiachat-ai' ); ?></button>
                                </div>
                            </div>
                            <div id="aichat-ctx-web-results-list" class="aichat-ctx-web-results-list">
                                <!-- URLs listed here by JS -->
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary" id="aichat-ctx-web-import-selected">
                                    <i class="bi bi-cloud-download me-1"></i><?php esc_html_e( 'Import Selected Pages', 'axiachat-ai' ); ?>
                                </button>
                                <span class="small text-muted ms-2" id="aichat-ctx-web-import-status"></span>
                            </div>
                        </div>

                        <!-- Import progress -->
                        <div id="aichat-ctx-web-progress" class="mt-3" style="display:none;">
                            <div class="progress" style="height: 18px;">
                                <div class="progress-bar" id="aichat-ctx-web-progress-bar" style="width:0%;">0%</div>
                            </div>
                            <div id="aichat-ctx-web-log" class="mt-2 p-2 border rounded bg-light"
                                 style="max-height:200px; overflow-y:auto; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:12px;">
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Duplicate Save & Index (visible only when documents exist) -->
            <div class="aichat-ctx-actions" id="aichat-ctx-save-top-wrapper" style="display:none;">
                <button type="button" class="button button-primary aichat-ctx-save-sources">
                    <i class="bi bi-check-lg me-1"></i><?php esc_html_e( 'Save & Index', 'axiachat-ai' ); ?>
                </button>
            </div>

            <!-- Current documents summary -->
            <div class="card shadow-sm mb-4 card100" id="aichat-ctx-documents-card" style="display:none;">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-1"></i><?php esc_html_e( 'Documents in Context', 'axiachat-ai' ); ?></h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="aichat-ctx-refresh-docs"><i class="bi bi-arrow-clockwise me-1"></i><?php esc_html_e( 'Refresh', 'axiachat-ai' ); ?></button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="aichat-ctx-remove-selected" disabled><i class="bi bi-trash me-1"></i><?php esc_html_e( 'Remove Selected', 'axiachat-ai' ); ?></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 border-bottom bg-light">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" id="aichat-ctx-docs-search" placeholder="<?php esc_attr_e( 'Search by title...', 'axiachat-ai' ); ?>">
                            </div>
                            <div class="col-md-2">
                                <select id="aichat-ctx-docs-type" class="form-select form-select-sm">
                                    <option value=""><?php esc_html_e( 'All types', 'axiachat-ai' ); ?></option>
                                    <option value="post">Post</option>
                                    <option value="page">Page</option>
                                    <option value="product">Product</option>
                                    <option value="file">File PDF/TXT</option>
                                    <option value="web"><?php esc_html_e( 'Web Page', 'axiachat-ai' ); ?></option>
                                </select>
                            </div>
                            <div class="col-md-2"><span class="small text-muted" id="aichat-ctx-docs-status"></span></div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="aichat-ctx-docs-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="aichat-ctx-select-all-docs"></th>
                                    <th><?php esc_html_e( 'Title', 'axiachat-ai' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'axiachat-ai' ); ?></th>
                                    <th><?php esc_html_e( 'Chunks', 'axiachat-ai' ); ?></th>
                                    <th class="text-end"><?php esc_html_e( 'Actions', 'axiachat-ai' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="aichat-ctx-docs-body">
                                <tr><td colspan="5" class="text-center text-muted py-4"><?php esc_html_e( 'Loading...', 'axiachat-ai' ); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center" id="aichat-ctx-docs-pager" style="display:none;">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="aichat-ctx-docs-prev" disabled>&laquo; <?php esc_html_e( 'Prev', 'axiachat-ai' ); ?></button>
                        <button type="button" class="btn btn-outline-secondary" id="aichat-ctx-docs-next" disabled><?php esc_html_e( 'Next', 'axiachat-ai' ); ?> &raquo;</button>
                    </div>
                    <span class="small text-muted" id="aichat-ctx-docs-pageinfo"></span>
                </div>
            </div>

            <!-- ==================== Advanced Settings (collapsed) ==================== -->
            <button type="button" class="aichat-ctx-advanced-toggle" id="aichat-ctx-advanced-toggle">
                <i class="bi bi-chevron-down"></i> <?php esc_html_e( 'Advanced Settings', 'axiachat-ai' ); ?>
            </button>

            <div class="aichat-ctx-advanced-body" id="aichat-ctx-advanced-body">
                <div class="row g-3">
                    <!-- Context Name -->
                    <div class="col-md-4">
                        <label for="aichat-ctx-name" class="form-label fw-semibold small"><?php esc_html_e( 'Context Name', 'axiachat-ai' ); ?></label>
                        <input type="text" id="aichat-ctx-name" class="form-control form-control-sm aichat-ctx-adv-field" value="<?php echo esc_attr( $curr_ctx ? $curr_ctx['name'] : 'My context' ); ?>" placeholder="<?php esc_attr_e( 'My Context', 'axiachat-ai' ); ?>">
                    </div>

                    <!-- Context Type -->
                    <div class="col-md-4">
                        <label for="aichat-ctx-type" class="form-label fw-semibold small"><?php esc_html_e( 'Storage Type', 'axiachat-ai' ); ?></label>
                        <select id="aichat-ctx-type" class="form-select form-select-sm aichat-ctx-adv-field">
                            <option value="local" <?php selected( $curr_ctx ? $curr_ctx['context_type'] : 'local', 'local' ); ?>><?php esc_html_e( 'Local (your server)', 'axiachat-ai' ); ?></option>
                            <option value="remoto" <?php selected( $curr_ctx ? $curr_ctx['context_type'] : '', 'remoto' ); ?>><?php esc_html_e( 'Remote (Pinecone)', 'axiachat-ai' ); ?></option>
                        </select>
                    </div>

                    <!-- Embedding Provider -->
                    <div class="col-md-4">
                        <?php
                        if ( $embedding_provider_html ) {
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo $embedding_provider_html;
                        }
                        ?>
                    </div>
                </div>

                <!-- Remote (Pinecone) config fields — hidden by default -->
                <?php
                $remote_display = ( $curr_ctx && $curr_ctx['context_type'] === 'remoto' ) ? '' : 'display:none;';
                ?>
                <div id="aichat-ctx-remote-fields" class="border rounded p-3 mt-3" style="<?php echo esc_attr( $remote_display ); ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="aichat-ctx-remote-type" class="form-label fw-semibold small">
                                <i class="bi bi-cloud-arrow-up me-1"></i><?php esc_html_e( 'Remote Type', 'axiachat-ai' ); ?>
                            </label>
                            <select id="aichat-ctx-remote-type" class="form-select form-select-sm aichat-ctx-adv-field">
                                <option value="pinecone"><?php esc_html_e( 'Pinecone', 'axiachat-ai' ); ?></option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="aichat-ctx-remote-api-key" class="form-label fw-semibold small">
                                <i class="bi bi-key me-1"></i><?php esc_html_e( 'API Key', 'axiachat-ai' ); ?>
                            </label>
                            <input type="text" id="aichat-ctx-remote-api-key" class="form-control form-control-sm aichat-ctx-adv-field"
                                   value="<?php echo esc_attr( $curr_ctx ? ( $curr_ctx['remote_api_key'] ?? '' ) : '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Enter Pinecone API Key', 'axiachat-ai' ); ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="aichat-ctx-remote-endpoint" class="form-label fw-semibold small">
                                <i class="bi bi-link-45deg me-1"></i><?php esc_html_e( 'Endpoint URL', 'axiachat-ai' ); ?>
                            </label>
                            <input type="text" id="aichat-ctx-remote-endpoint" class="form-control form-control-sm aichat-ctx-adv-field"
                                   value="<?php echo esc_attr( $curr_ctx ? ( $curr_ctx['remote_endpoint'] ?? '' ) : 'https://controller.pinecone.io' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Enter Pinecone Endpoint', 'axiachat-ai' ); ?>">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-0">

                    <!-- Include page content checkbox -->
                    <div class="col-12">
                        <div class="mb-1">
                            <label style="cursor:pointer;"><input type="checkbox" id="aichat-ctx-include-page" class="aichat-ctx-adv-field" <?php checked( in_array( $context_mode, [ 'page', 'auto' ], true ) ); ?> style="margin-right:6px;"><?php esc_html_e( 'Also include the content of the page where the chat is displayed', 'axiachat-ai' ); ?></label>
                            <div class="form-text small"><?php esc_html_e( 'When enabled, the bot also receives the current page content alongside the knowledge base.', 'axiachat-ai' ); ?></div>
                        </div>
                    </div>

                    <!-- Thresholds -->
                    <div class="col-md-4">
                        <label for="aichat-ctx-max-length" class="form-label fw-semibold small"><?php esc_html_e( 'Max context length (characters)', 'axiachat-ai' ); ?></label>
                        <input type="number" id="aichat-ctx-max-length" class="form-control form-control-sm aichat-ctx-adv-field" value="<?php echo esc_attr( $context_max_length ); ?>" min="128">
                        <div class="form-text small"><?php esc_html_e( 'Limits how much knowledge is sent per question. Higher = more detail but more cost.', 'axiachat-ai' ); ?></div>
                    </div>

                    <div class="col-md-4">
                        <label for="aichat-ctx-limit" class="form-label fw-semibold small"><?php esc_html_e( 'Knowledge chunks per answer', 'axiachat-ai' ); ?>: <strong id="aichat-ctx-limit-val"><?php echo esc_html( $context_limit ); ?></strong></label>
                        <input type="range" id="aichat-ctx-limit" class="aichat-ctx-adv-field" min="3" max="10" step="1" value="<?php echo esc_attr( $context_limit ); ?>">
                        <div class="form-text small"><?php esc_html_e( 'How many pieces of your content (3-10) the bot reads before answering each question.', 'axiachat-ai' ); ?></div>
                    </div>

                    <!-- AutoSync -->
                    <div class="col-md-4">
                        <div class="mt-3">
                            <label style="cursor:pointer;font-weight:600;"><input type="checkbox" id="aichat-ctx-autosync" class="aichat-ctx-adv-field" <?php checked( $curr_ctx && ! empty( $curr_ctx['autosync'] ) ); ?> style="margin-right:6px;"><?php esc_html_e( 'Keep knowledge up to date automatically', 'axiachat-ai' ); ?></label>
                            <div class="form-text small"><?php esc_html_e( 'When you edit a page or product, the bot\'s knowledge updates automatically — no manual re-indexing needed.', 'axiachat-ai' ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- AutoSync Mode (shown when autosync checked) -->
                <?php
                $autosync_mode_val = ( $curr_ctx && ! empty( $curr_ctx['autosync_mode'] ) ) ? $curr_ctx['autosync_mode'] : 'updates';
                $autosync_display  = ( $curr_ctx && ! empty( $curr_ctx['autosync'] ) ) ? '' : 'display:none;';
                ?>
                <div id="aichat-ctx-autosync-mode-wrapper" class="mt-2" style="<?php echo esc_attr( $autosync_display ); ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="aichat-ctx-autosync-mode" class="form-label fw-semibold small"><?php esc_html_e( 'Sync mode', 'axiachat-ai' ); ?></label>
                            <select id="aichat-ctx-autosync-mode" class="form-select form-select-sm aichat-ctx-adv-field">
                                <option value="updates" <?php selected( $autosync_mode_val, 'updates' ); ?>><?php esc_html_e( 'Only update modified content', 'axiachat-ai' ); ?></option>
                                <option value="updates_and_new" <?php selected( $autosync_mode_val, 'updates_and_new' ); ?>><?php esc_html_e( 'Update modified + add new content', 'axiachat-ai' ); ?></option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div class="form-text small mt-md-4" id="aichat-ctx-autosync-help-general">
                                <strong><?php esc_html_e( 'Update modified:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'when you edit existing content, the bot learns the changes automatically.', 'axiachat-ai' ); ?><br>
                                <strong><?php esc_html_e( 'Update + add new:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'also picks up brand new pages/products you publish. Requires at least one source set to "All".', 'axiachat-ai' ); ?>
                            </div>
                            <div class="form-text small text-warning mt-md-4" id="aichat-ctx-autosync-help-limited" style="display:none;">
                                <i class="bi bi-exclamation-triangle me-1"></i><?php esc_html_e( 'To detect new content automatically, set at least one source (Posts, Pages, or Products) to "All" above.', 'axiachat-ai' ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Indexing Options separator -->
                <div class="aichat-ctx-adv-separator"><?php esc_html_e( 'What to include when indexing', 'axiachat-ai' ); ?></div>

                <div class="form-text small mb-2" style="line-height:1.6;">
                    <?php esc_html_e( 'The title and full content of each page, post or product are always indexed automatically.', 'axiachat-ai' ); ?>
                    <?php if ( $has_woocommerce ) : ?>
                        <?php esc_html_e( 'Product prices, stock and SKU are fetched in real time when the bot answers — you don\'t need to include them here.', 'axiachat-ai' ); ?>
                    <?php endif; ?>
                    <?php esc_html_e( 'Use the options below to enrich the indexed data with additional fields:', 'axiachat-ai' ); ?>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label style="cursor:pointer;"><input type="checkbox" class="aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-excerpt" style="margin-right:6px;"><?php esc_html_e( 'Include post summary (excerpt)', 'axiachat-ai' ); ?></label>
                        </div>
                        <div class="mb-2">
                            <label style="cursor:pointer;"><input type="checkbox" class="aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-url" checked style="margin-right:6px;"><?php esc_html_e( 'Include page/post URL', 'axiachat-ai' ); ?></label>
                        </div>
                        <div class="mb-2">
                            <label style="cursor:pointer;"><input type="checkbox" class="aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-featured-image" style="margin-right:6px;"><?php esc_html_e( 'Include featured image', 'axiachat-ai' ); ?></label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-taxonomies" placeholder="<?php esc_attr_e( 'Categories & tags (comma-separated): category, post_tag, product_cat', 'axiachat-ai' ); ?>">
                        </div>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-custom-meta" placeholder="<?php esc_attr_e( 'Custom fields (comma-separated): my_field, other_field', 'axiachat-ai' ); ?>">
                        </div>
                        <?php if ( $has_woocommerce ) : ?>
                        <div class="mb-2">
                            <label style="cursor:pointer;"><input type="checkbox" class="aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-wc-short-desc" style="margin-right:6px;"><?php esc_html_e( 'Product short description', 'axiachat-ai' ); ?></label>
                        </div>
                        <div class="mb-2">
                            <label style="cursor:pointer;"><input type="checkbox" class="aichat-ctx-idx aichat-ctx-adv-field" id="aichat-ctx-idx-wc-attributes" style="margin-right:6px;"><?php esc_html_e( 'Product attributes (size, color…)', 'axiachat-ai' ); ?></label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tip -->
                <div class="mt-2 p-3 border rounded" style="background:#f8f9fa;font-size:13px;line-height:1.6;">
                    <i class="bi bi-lightbulb me-1 text-warning"></i> <strong>Tips:</strong>
                    <ul class="mb-0 mt-1" style="padding-left:18px;">
                        <?php if ( $has_woocommerce ) : ?>
                        <li><?php esc_html_e( 'Product prices, stock and SKU are always fresh — no re-indexing needed when they change.', 'axiachat-ai' ); ?></li>
                        <?php endif; ?>
                        <li><?php esc_html_e( 'Changing these options only affects new/re-indexed content. Click "Save & Index" above to apply.', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'To show images in responses, enable "Include featured image" and tell your bot to use images in its instructions.', 'axiachat-ai' ); ?></li>
                    </ul>
                </div>

                <!-- AutoSync run button + tip -->
                <div class="mt-3 p-3 border rounded" style="background:#f0f7ff;">
                    <div class="d-flex align-items-start gap-3">
                        <div style="flex:1;">
                            <strong><i class="bi bi-arrow-repeat me-1"></i><?php esc_html_e( 'Sync Now', 'axiachat-ai' ); ?></strong>
                            <p class="small text-muted mb-0 mt-1">
                                <?php esc_html_e( 'If you recently edited pages or products, click this button to update the bot\'s knowledge immediately instead of waiting for the automatic check.', 'axiachat-ai' ); ?>
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="aichat-ctx-run-autosync" style="white-space:nowrap;">
                            <i class="bi bi-arrow-repeat me-1"></i><?php esc_html_e( 'Run Sync Now', 'axiachat-ai' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Auto-save feedback -->
                <div class="mt-2 small text-success" id="aichat-ctx-advanced-saved" style="display:none;">
                    <i class="bi bi-check-circle"></i> <?php esc_html_e( 'Settings saved', 'axiachat-ai' ); ?>
                </div>
            </div>

            <!-- ==================== Action Buttons ==================== -->
            <div class="aichat-ctx-actions">
                <button type="button" class="button button-primary aichat-ctx-save-sources" id="aichat-ctx-save-sources">
                    <i class="bi bi-check-lg me-1"></i><?php esc_html_e( 'Save & Index', 'axiachat-ai' ); ?>
                </button>
                <span class="ms-2 text-success" id="aichat-ctx-save-msg" style="display:none;"><i class="bi bi-check-circle"></i> <?php esc_html_e( 'Saved', 'axiachat-ai' ); ?></span>
            </div>

            <!-- ==================== Indexing Progress + Log ==================== -->
            <div id="aichat-ctx-process-panel" style="display:none;" class="mt-3">
                <div class="progress" style="height: 22px;">
                    <div class="progress-bar" id="aichat-ctx-progress-bar" role="progressbar"
                         style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        0%
                    </div>
                </div>
                <div id="aichat-ctx-index-log" class="mt-2 p-2 border rounded bg-light"
                     style="height: 220px; overflow-y: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px;">
                </div>
            </div>

            <!-- ==================== Advanced Contexts Link ==================== -->
            <div class="aichat-ctx-advanced-link">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-settings' ) ); ?>">
                    <i class="bi bi-gear me-1"></i><?php esc_html_e( 'Advanced Context Management (create, browse, similarity test, delete)', 'axiachat-ai' ); ?>
                </a>
            </div>

            <!-- Hidden data for JS -->
            <div id="aichat-ctx-data"
                 data-bot-id="<?php echo esc_attr( $bot['id'] ); ?>"
                 data-bot-slug="<?php echo esc_attr( $bot_slug ); ?>"
                 data-nonce="<?php echo esc_attr( $nonce ); ?>"
                 data-modify-nonce="<?php echo esc_attr( $modify_nonce ); ?>"
                 data-create-nonce="<?php echo esc_attr( $create_nonce ); ?>"
                 data-pdf-nonce="<?php echo esc_attr( wp_create_nonce( 'aichat_pdf_nonce' ) ); ?>"
                 data-context-id="<?php echo esc_attr( $current_ctx_id ); ?>"
                 data-contexts="<?php echo esc_attr( wp_json_encode( $contexts ) ); ?>"
                 data-has-woo="<?php echo esc_attr( $has_woocommerce ); ?>"
                 data-context-mode="<?php echo esc_attr( $context_mode ); ?>"
                 data-max-upload="<?php echo esc_attr( wp_max_upload_size() ); ?>"
                 style="display:none;"></div>

        </div>
    </div>
    <?php
}
