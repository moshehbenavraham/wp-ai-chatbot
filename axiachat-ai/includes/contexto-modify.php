<?php
/**
 * Admin UI: Modify Context
 * Allows adding/removing documents from an existing context.
 *
 * @package AxiaChat
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aichat_contexto_modify_page() {
    global $wpdb;

    // Fetch all contexts for the selector
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin screen listing internal context tables.
    $contexts = $wpdb->get_results(
        "SELECT c.id, c.name, c.context_type, c.embedding_provider, c.processing_status, c.processing_progress,
                (SELECT COUNT(*) FROM {$wpdb->prefix}aichat_chunks ch WHERE ch.id_context=c.id) AS chunk_count,
                (SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aichat_chunks ch2 WHERE ch2.id_context=c.id) AS post_count
         FROM {$wpdb->prefix}aichat_contexts c
         ORDER BY c.id ASC",
        ARRAY_A
    );
    if ( ! $contexts ) { $contexts = []; }

    $has_woocommerce = class_exists('WooCommerce') ? 1 : 0;
    ?>
    <div class="wrap aichat-admin">

        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-pencil-square fs-3 me-2 text-primary"></i>
            <h1 class="m-0"><?php echo esc_html__( 'Modify Context', 'axiachat-ai' ); ?></h1>
        </div>

        <!-- Tabs (consistent with other context pages) -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-settings' ) ); ?>" role="tab">
                    <i class="bi bi-gear me-1"></i><?php esc_html_e('Context', 'axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-create' ) ); ?>" role="tab">
                    <i class="bi bi-plus-circle me-1"></i><?php esc_html_e('Add New', 'axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>" role="tab">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i><?php esc_html_e('Import PDF/Data', 'axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button">
                    <i class="bi bi-pencil-square me-1"></i><?php esc_html_e('Modify Context', 'axiachat-ai'); ?>
                </button>
            </li>
        </ul>

        <!-- Context Selector -->
        <div class="card card100 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="bi bi-folder2-open me-2 text-secondary"></i><?php esc_html_e('Select Context to Modify', 'axiachat-ai'); ?>
                </h5>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <label for="aichat-modify-context-select" class="form-label fw-semibold">
                            <?php esc_html_e('Context', 'axiachat-ai'); ?>
                        </label>
                        <select id="aichat-modify-context-select" class="form-select">
                            <option value=""><?php esc_html_e('— Select a context —', 'axiachat-ai'); ?></option>
                            <?php foreach ( $contexts as $ctx ) : ?>
                                <option value="<?php echo esc_attr( $ctx['id'] ); ?>"
                                        data-type="<?php echo esc_attr( $ctx['context_type'] ); ?>"
                                        data-provider="<?php echo esc_attr( $ctx['embedding_provider'] ); ?>"
                                        data-status="<?php echo esc_attr( $ctx['processing_status'] ); ?>">
                                    <?php
                                    echo esc_html( $ctx['name'] . ' (#' . $ctx['id'] . ') — ' . $ctx['post_count'] . ' docs, ' . $ctx['chunk_count'] . ' chunks' );
                                    if ( $ctx['processing_status'] !== 'completed' ) {
                                        echo ' [' . esc_html( $ctx['processing_status'] ) . ' ' . (int) $ctx['processing_progress'] . '%]';
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6" id="aichat-modify-context-info" style="display:none;">
                        <div class="alert alert-info py-2 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            <span id="aichat-modify-info-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content (hidden until context selected) -->
        <div id="aichat-modify-main" style="display:none;">

            <!-- Advanced Indexing Options (editable per context) -->
            <div class="card card100 shadow-sm mb-4" id="aichat-modify-indexing-options-card">
                <div class="card-body">
                    <h5 class="card-title mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-sliders me-2 text-secondary"></i><?php esc_html_e('Advanced Indexing Options','axiachat-ai'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#aichat-modify-indexing-body" aria-expanded="false">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </h5>
                    <p class="text-muted small mb-2"><?php esc_html_e('Configure which fields are included when indexing. Changes apply on next re-index.','axiachat-ai'); ?></p>
                    <div class="collapse" id="aichat-modify-indexing-body">

                        <!-- General fields -->
                        <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-file-text me-1"></i> <?php esc_html_e('General Fields','axiachat-ai'); ?></h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input aichat-modify-idx" type="checkbox" id="aichat-modify-idx-excerpt" />
                            <label class="form-check-label" for="aichat-modify-idx-excerpt"><?php esc_html_e('Include excerpt / summary','axiachat-ai'); ?></label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input aichat-modify-idx" type="checkbox" id="aichat-modify-idx-url" />
                            <label class="form-check-label" for="aichat-modify-idx-url"><?php esc_html_e('Include post URL','axiachat-ai'); ?></label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input aichat-modify-idx" type="checkbox" id="aichat-modify-idx-featured-image" />
                            <label class="form-check-label" for="aichat-modify-idx-featured-image"><?php esc_html_e('Include featured image URL','axiachat-ai'); ?></label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="aichat-modify-idx-taxonomies"><?php esc_html_e('Include taxonomies (comma-separated slugs)','axiachat-ai'); ?></label>
                            <input type="text" class="form-control form-control-sm aichat-modify-idx" id="aichat-modify-idx-taxonomies" placeholder="category, post_tag, product_cat, product_tag" />
                            <div class="form-text small"><?php esc_html_e('Leave empty to skip. Examples: category, post_tag, product_cat','axiachat-ai'); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="aichat-modify-idx-custom-meta"><?php esc_html_e('Custom meta keys (comma-separated)','axiachat-ai'); ?></label>
                            <input type="text" class="form-control form-control-sm aichat-modify-idx" id="aichat-modify-idx-custom-meta" placeholder="my_acf_field, another_field" />
                            <div class="form-text small"><?php esc_html_e('Post meta keys to include in indexing (ACF, custom fields, etc.)','axiachat-ai'); ?></div>
                        </div>

                        <!-- WooCommerce fields -->
                        <?php if ( class_exists('WooCommerce') ) : ?>
                        <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-cart me-1"></i> <?php esc_html_e('WooCommerce Fields (static, embedded)','axiachat-ai'); ?></h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input aichat-modify-idx" type="checkbox" id="aichat-modify-idx-wc-short-desc" />
                            <label class="form-check-label" for="aichat-modify-idx-wc-short-desc"><?php esc_html_e('Include short description','axiachat-ai'); ?></label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input aichat-modify-idx" type="checkbox" id="aichat-modify-idx-wc-attributes" />
                            <label class="form-check-label" for="aichat-modify-idx-wc-attributes"><?php esc_html_e('Include attributes (size, color, etc.)','axiachat-ai'); ?></label>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-light border py-2 small mt-3 mb-2">
                            <i class="bi bi-lightbulb me-1 text-warning"></i>
                            <strong><?php esc_html_e('Tips:','axiachat-ai'); ?></strong>
                            <ul class="mb-0 mt-1 ps-3">
                                <li><?php esc_html_e('Dynamic WooCommerce data (price, stock, SKU) is automatically injected at query time with fresh values — no need to re-index when prices change.','axiachat-ai'); ?></li>
                                <li><?php esc_html_e('Changes to these options require re-indexing the context to take effect on existing content.','axiachat-ai'); ?></li>
                                <li><?php esc_html_e('To display images in the chat, enable "Featured image URL" and instruct your bot to use Markdown image syntax in its responses.','axiachat-ai'); ?></li>
                            </ul>
                        </div>

                        <button type="button" class="btn btn-primary btn-sm mt-2" id="aichat-modify-save-indexing-options">
                            <i class="bi bi-check-lg me-1"></i><?php esc_html_e('Save Options','axiachat-ai'); ?>
                        </button>
                        <span class="ms-2 small text-success" id="aichat-modify-idx-saved-msg" style="display:none;"><i class="bi bi-check-circle"></i> <?php esc_html_e('Saved','axiachat-ai'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Inner tabs: Documents / Add Documents -->
            <ul class="nav nav-pills mb-3" id="aichat-modify-inner-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="aichat-modify-tab-docs" data-bs-toggle="tab" data-bs-target="#aichat-modify-pane-docs" type="button" role="tab">
                        <i class="bi bi-list-ul me-1"></i><?php esc_html_e('Current Documents', 'axiachat-ai'); ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="aichat-modify-tab-add" data-bs-toggle="tab" data-bs-target="#aichat-modify-pane-add" type="button" role="tab">
                        <i class="bi bi-plus-lg me-1"></i><?php esc_html_e('Add Documents', 'axiachat-ai'); ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="aichat-modify-inner-tabcontent">

                <!-- ==================== CURRENT DOCUMENTS PANE ==================== -->
                <div class="tab-pane fade show active" id="aichat-modify-pane-docs" role="tabpanel">
                    <div class="card card100 shadow-sm mb-4">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">
                                <i class="bi bi-file-earmark-text me-1"></i><?php esc_html_e('Documents in Context', 'axiachat-ai'); ?>
                            </h5>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="aichat-modify-refresh-docs">
                                    <i class="bi bi-arrow-clockwise me-1"></i><?php esc_html_e('Refresh', 'axiachat-ai'); ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="aichat-modify-remove-selected" disabled>
                                    <i class="bi bi-trash me-1"></i><?php esc_html_e('Remove Selected', 'axiachat-ai'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <!-- Filters -->
                            <div class="p-3 border-bottom bg-light">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" id="aichat-modify-docs-search" placeholder="<?php esc_attr_e('Search by title...', 'axiachat-ai'); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <select id="aichat-modify-docs-type" class="form-select form-select-sm">
                                            <option value=""><?php esc_html_e('All types', 'axiachat-ai'); ?></option>
                                            <option value="post">Post</option>
                                            <option value="page">Page</option>
                                            <option value="product">Product</option>
                                            <option value="aichat_upload_chunk">Upload</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select id="aichat-modify-docs-perpage" class="form-select form-select-sm">
                                            <option value="10">10</option>
                                            <option value="25" selected>25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <span class="small text-muted" id="aichat-modify-docs-status"></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Table -->
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="aichat-modify-docs-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;"><input type="checkbox" id="aichat-modify-select-all-docs"></th>
                                            <th><?php esc_html_e('Post ID', 'axiachat-ai'); ?></th>
                                            <th><?php esc_html_e('Title', 'axiachat-ai'); ?></th>
                                            <th><?php esc_html_e('Type', 'axiachat-ai'); ?></th>
                                            <th><?php esc_html_e('Chunks', 'axiachat-ai'); ?></th>
                                            <th><?php esc_html_e('Last Updated', 'axiachat-ai'); ?></th>
                                            <th class="text-end"><?php esc_html_e('Actions', 'axiachat-ai'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="aichat-modify-docs-body">
                                        <tr><td colspan="7" class="text-center text-muted py-4"><?php esc_html_e('Select a context above.', 'axiachat-ai'); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center" id="aichat-modify-docs-pager" style="display:none;">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="aichat-modify-docs-prev" disabled>&laquo; <?php esc_html_e('Prev', 'axiachat-ai'); ?></button>
                                <button type="button" class="btn btn-outline-secondary" id="aichat-modify-docs-next" disabled><?php esc_html_e('Next', 'axiachat-ai'); ?> &raquo;</button>
                            </div>
                            <span class="small text-muted" id="aichat-modify-docs-pageinfo"></span>
                        </div>
                    </div>
                </div>

                <!-- ==================== ADD DOCUMENTS PANE ==================== -->
                <div class="tab-pane fade" id="aichat-modify-pane-add" role="tabpanel">
                    <div class="card card100 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-plus-circle me-1"></i><?php esc_html_e('Add Documents to Context', 'axiachat-ai'); ?>
                            </h5>
                        </div>
                        <div class="card-body">

                            <div class="alert alert-warning py-2 small" id="aichat-modify-processing-warn" style="display:none;">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <?php esc_html_e('This context is currently being processed. Wait for it to complete before adding new documents.', 'axiachat-ai'); ?>
                            </div>

                            <!-- POSTS -->
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-file-text me-2 text-primary"></i>
                                    <span class="fw-semibold"><?php esc_html_e('Posts', 'axiachat-ai'); ?></span>
                                    <label class="d-flex align-items-center gap-2 ms-3 m-0">
                                        <input type="checkbox" name="aichat_modify_posts_mode" value="all">
                                        <span class="small"><?php esc_html_e('All Posts', 'axiachat-ai'); ?></span>
                                    </label>
                                    <label class="d-flex align-items-center gap-2 ms-2 m-0">
                                        <input type="checkbox" name="aichat_modify_posts_mode" value="custom">
                                        <span class="small"><?php esc_html_e('Custom', 'axiachat-ai'); ?></span>
                                    </label>
                                </div>
                                <div class="aichat-modify-source-panel" data-post-type="post" style="display:none;">
                                    <div class="border rounded p-3">
                                        <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aichat-modify-recent-post" type="button"><i class="bi bi-clock-history me-1"></i><?php esc_html_e('Recent', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-all-post" type="button"><i class="bi bi-card-list me-1"></i><?php esc_html_e('All', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-search-post" type="button"><i class="bi bi-search me-1"></i><?php esc_html_e('Search', 'axiachat-ai'); ?></button></li>
                                        </ul>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="aichat-modify-recent-post">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-recent-items-post"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-recent-items-post" class="aichat-items"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-all-post">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-all-items-post"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-all-items-post" class="aichat-items"></div>
                                                <div id="aichat-modify-all-pagination-post" class="aichat-pagination mt-2"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-search-post">
                                                <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" class="form-control aichat-modify-search-input" data-post-type="post" placeholder="<?php esc_attr_e('Search...', 'axiachat-ai'); ?>"></div>
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-search-items-post"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-search-items-post" class="aichat-items"></div>
                                                <div id="aichat-modify-search-pagination-post" class="aichat-pagination mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PAGES -->
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                    <span class="fw-semibold"><?php esc_html_e('Pages', 'axiachat-ai'); ?></span>
                                    <label class="d-flex align-items-center gap-2 ms-3 m-0">
                                        <input type="checkbox" name="aichat_modify_pages_mode" value="all">
                                        <span class="small"><?php esc_html_e('All Pages', 'axiachat-ai'); ?></span>
                                    </label>
                                    <label class="d-flex align-items-center gap-2 ms-2 m-0">
                                        <input type="checkbox" name="aichat_modify_pages_mode" value="custom">
                                        <span class="small"><?php esc_html_e('Custom', 'axiachat-ai'); ?></span>
                                    </label>
                                </div>
                                <div class="aichat-modify-source-panel" data-post-type="page" style="display:none;">
                                    <div class="border rounded p-3">
                                        <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aichat-modify-recent-page" type="button"><i class="bi bi-clock-history me-1"></i><?php esc_html_e('Recent', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-all-page" type="button"><i class="bi bi-card-list me-1"></i><?php esc_html_e('All', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-search-page" type="button"><i class="bi bi-search me-1"></i><?php esc_html_e('Search', 'axiachat-ai'); ?></button></li>
                                        </ul>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="aichat-modify-recent-page">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-recent-items-page"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-recent-items-page" class="aichat-items"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-all-page">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-all-items-page"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-all-items-page" class="aichat-items"></div>
                                                <div id="aichat-modify-all-pagination-page" class="aichat-pagination mt-2"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-search-page">
                                                <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" class="form-control aichat-modify-search-input" data-post-type="page" placeholder="<?php esc_attr_e('Search...', 'axiachat-ai'); ?>"></div>
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-search-items-page"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-search-items-page" class="aichat-items"></div>
                                                <div id="aichat-modify-search-pagination-page" class="aichat-pagination mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PRODUCTS (WooCommerce) -->
                            <?php if ( $has_woocommerce ) : ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-bag-check me-2 text-primary"></i>
                                    <span class="fw-semibold"><?php esc_html_e('Products', 'axiachat-ai'); ?></span>
                                    <label class="d-flex align-items-center gap-2 ms-3 m-0">
                                        <input type="checkbox" name="aichat_modify_products_mode" value="all">
                                        <span class="small"><?php esc_html_e('All Products', 'axiachat-ai'); ?></span>
                                    </label>
                                    <label class="d-flex align-items-center gap-2 ms-2 m-0">
                                        <input type="checkbox" name="aichat_modify_products_mode" value="custom">
                                        <span class="small"><?php esc_html_e('Custom', 'axiachat-ai'); ?></span>
                                    </label>
                                </div>
                                <div class="aichat-modify-source-panel" data-post-type="product" style="display:none;">
                                    <div class="border rounded p-3">
                                        <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aichat-modify-recent-product" type="button"><i class="bi bi-clock-history me-1"></i><?php esc_html_e('Recent', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-all-product" type="button"><i class="bi bi-card-list me-1"></i><?php esc_html_e('All', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-search-product" type="button"><i class="bi bi-search me-1"></i><?php esc_html_e('Search', 'axiachat-ai'); ?></button></li>
                                        </ul>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="aichat-modify-recent-product">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-recent-items-product"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-recent-items-product" class="aichat-items"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-all-product">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-all-items-product"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-all-items-product" class="aichat-items"></div>
                                                <div id="aichat-modify-all-pagination-product" class="aichat-pagination mt-2"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-search-product">
                                                <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" class="form-control aichat-modify-search-input" data-post-type="product" placeholder="<?php esc_attr_e('Search...', 'axiachat-ai'); ?>"></div>
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-search-items-product"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-search-items-product" class="aichat-items"></div>
                                                <div id="aichat-modify-search-pagination-product" class="aichat-pagination mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- UPLOADED FILES -->
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-cloud-upload me-2 text-primary"></i>
                                    <span class="fw-semibold"><?php esc_html_e('Uploaded Files', 'axiachat-ai'); ?></span>
                                    <label class="d-flex align-items-center gap-2 ms-3 m-0">
                                        <input type="checkbox" name="aichat_modify_uploaded_mode" value="all">
                                        <span class="small"><?php esc_html_e('All Uploaded', 'axiachat-ai'); ?></span>
                                    </label>
                                    <label class="d-flex align-items-center gap-2 ms-2 m-0">
                                        <input type="checkbox" name="aichat_modify_uploaded_mode" value="custom">
                                        <span class="small"><?php esc_html_e('Custom', 'axiachat-ai'); ?></span>
                                    </label>
                                </div>
                                <div class="aichat-modify-source-panel" data-post-type="aichat_upload" style="display:none;">
                                    <div class="border rounded p-3">
                                        <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aichat-modify-recent-aichat_upload" type="button"><i class="bi bi-clock-history me-1"></i><?php esc_html_e('Recent', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-all-aichat_upload" type="button"><i class="bi bi-card-list me-1"></i><?php esc_html_e('All', 'axiachat-ai'); ?></button></li>
                                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aichat-modify-search-aichat_upload" type="button"><i class="bi bi-search me-1"></i><?php esc_html_e('Search', 'axiachat-ai'); ?></button></li>
                                        </ul>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="aichat-modify-recent-aichat_upload">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-recent-items-aichat_upload"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-recent-items-aichat_upload" class="aichat-items"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-all-aichat_upload">
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-all-items-aichat_upload"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-all-items-aichat_upload" class="aichat-items"></div>
                                                <div id="aichat-modify-all-pagination-aichat_upload" class="aichat-pagination mt-2"></div>
                                            </div>
                                            <div class="tab-pane fade" id="aichat-modify-search-aichat_upload">
                                                <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" class="form-control aichat-modify-search-input" data-post-type="aichat_upload" placeholder="<?php esc_attr_e('Search...', 'axiachat-ai'); ?>"></div>
                                                <label class="d-block mb-2 small"><input type="checkbox" class="aichat-modify-select-all me-1" data-target="#aichat-modify-search-items-aichat_upload"> <?php esc_html_e('Select All', 'axiachat-ai'); ?></label>
                                                <div id="aichat-modify-search-items-aichat_upload" class="aichat-items"></div>
                                                <div id="aichat-modify-search-pagination-aichat_upload" class="aichat-pagination mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Selection summary + Add button -->
                            <div class="alert alert-info d-flex align-items-center py-2 mt-3" role="alert">
                                <i class="bi bi-info-circle me-2"></i>
                                <div id="aichat-modify-add-summary"><?php esc_html_e('No selections yet.', 'axiachat-ai'); ?></div>
                            </div>

                            <button type="button" id="aichat-modify-add-process" class="btn btn-primary" disabled>
                                <i class="bi bi-plus-circle me-1"></i><?php esc_html_e('Add & Process Selected', 'axiachat-ai'); ?>
                            </button>

                            <!-- Add progress -->
                            <div class="progress mt-3" style="height:22px;" id="aichat-modify-add-progress-wrap" style="display:none;">
                                <div class="progress-bar" id="aichat-modify-add-progress" role="progressbar" style="width:0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                            <div id="aichat-modify-add-log" class="mt-3 p-2 border rounded bg-light" style="height:160px; overflow-y:auto; font-family:monospace; font-size:12px; display:none;"></div>

                        </div>
                    </div>
                </div>

            </div><!-- /tab-content -->

        </div><!-- /#aichat-modify-main -->

    </div><!-- /.wrap -->
    <?php
}
