<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aichat_contexto_create_page() {
    $select_posts_mode    = get_option( 'aichat_select_posts_mode', '' );
    $select_pages_mode    = get_option( 'aichat_select_pages_mode', '' );
    $select_products_mode = get_option( 'aichat_select_products_mode', '' );
    $select_uploaded_mode = get_option( 'aichat_select_uploaded_mode', '' );
    ?>
    <div class="wrap">
        
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-diagram-3 fs-3 me-2 text-primary"></i>
            <h1 class="m-0"><?php echo esc_html( __( 'Create Context', 'axiachat-ai' ) ); ?></h1>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="create-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-settings' ) ); ?>" role="tab">
                    <i class="bi bi-gear me-1"></i> Context
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="add-new-tab" data-bs-toggle="tab" data-bs-target="#add-new" type="button" role="tab" aria-controls="add-new" aria-selected="true">
                    <i class="bi bi-plus-circle me-1"></i> Add New
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>" role="tab">
                    <i class="bi bi-filetype-pdf me-1"></i> Import PDF/Data
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-modify' ) ); ?>" role="tab">
                    <i class="bi bi-pencil-square me-1"></i> <?php esc_html_e('Modify Context', 'axiachat-ai'); ?>
                </a>
            </li>
        </ul>

        <div class="tab-content" id="create-tab-content">
            <div class="tab-pane fade show active" id="add-new" role="tabpanel" aria-labelledby="add-new-tab">

                <!-- Card: Basic Settings -->
                <div class="card card100 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-sliders me-2 text-secondary"></i><?php esc_html_e( 'Basic Settings', 'axiachat-ai' ); ?>
                        </h5>

                        <div class="row g-3">
                            <!-- Context Name -->
                            <div class="col-12 col-lg-6">
                                <label for="context-name" class="form-label fw-semibold">
                                    <i class="bi bi-tag me-1"></i><?php esc_html_e( 'Context Name', 'axiachat-ai' ); ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-pencil-square"></i></span>
                                    <input type="text" id="context-name" name="context_name" class="form-control" placeholder="<?php esc_attr_e( 'Enter context name', 'axiachat-ai' ); ?>" required>
                                </div>
                                <div class="form-text"><?php esc_html_e( 'If left empty, a default name (e.g., Default1) will be assigned.', 'axiachat-ai' ); ?></div>
                            </div>

                            <!-- Context Type -->
                            <div class="col-12 col-lg-6">
                                <label for="context-type" class="form-label fw-semibold">
                                    <i class="bi bi-hdd-network me-1"></i><?php esc_html_e( 'Context Type', 'axiachat-ai' ); ?>
                                </label>
                                <select id="context-type" name="context_type" class="form-select">
                                    <option value="local"><?php esc_html_e( 'Local', 'axiachat-ai' ); ?></option>
                                    <option value="remoto"><?php esc_html_e( 'Remote', 'axiachat-ai' ); ?></option>
                                </select>
                                <div class="form-text"><?php esc_html_e( 'Choose where to store the context data.', 'axiachat-ai' ); ?></div>
                            </div>
                            
                            <?php 
                            // Render embedding provider selector if multiple providers available
                            if ( function_exists( 'aichat_render_embedding_provider_select' ) ) {
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Function returns escaped HTML
                                echo aichat_render_embedding_provider_select( '', 'embedding-provider', 'embedding_provider' );
                            }
                            ?>
                        </div>

                        <!-- Remote config (la muestra tu JS según selección) -->
                        <div id="remote-config-fields" class="border rounded p-3 mt-3" style="display:none;">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="remote-type" class="form-label fw-semibold">
                                        <i class="bi bi-cloud-arrow-up me-1"></i><?php esc_html_e( 'Remote Type', 'axiachat-ai' ); ?>
                                    </label>
                                    <select id="remote-type" name="remote_type" class="form-select">
                                        <option value="pinecone"><?php esc_html_e( 'Pinecone', 'axiachat-ai' ); ?></option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="remote-api-key" class="form-label fw-semibold">
                                        <i class="bi bi-key me-1"></i><?php esc_html_e( 'API Key', 'axiachat-ai' ); ?>
                                    </label>
                                    <input type="text" id="remote-api-key" name="remote_api_key" class="form-control" placeholder="<?php esc_attr_e( 'Enter Pinecone API Key', 'axiachat-ai' ); ?>">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="remote-endpoint" class="form-label fw-semibold">
                                        <i class="bi bi-link-45deg me-1"></i><?php esc_html_e( 'Endpoint', 'axiachat-ai' ); ?>
                                    </label>
                                    <input type="text" id="remote-endpoint" name="remote_endpoint" class="form-control" placeholder="<?php esc_attr_e( 'Enter Pinecone Endpoint', 'axiachat-ai' ); ?>" value="https://controller.pinecone.io">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Sources -->
                <div class="card card100 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-check2-square me-2 text-secondary"></i><?php esc_html_e( 'Select Sources', 'axiachat-ai' ); ?>
                        </h5>

                        <!-- POSTS -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-file-text me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Posts', 'axiachat-ai' ); ?></span>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_posts_mode" value="all" <?php checked( $select_posts_mode, 'all' ); ?>>
                                    <span>All Posts</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_posts_mode" value="custom" <?php checked( $select_posts_mode, 'custom' ); ?>>
                                    <span>Custom Posts</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-post-accordion" style="<?php echo $select_posts_mode === 'custom' ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="post">
                                    <h2 class="accordion-header" id="heading-post">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-post" aria-expanded="false" aria-controls="collapse-post">
                                            <?php esc_html_e( 'Posts', 'axiachat-ai' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-post" class="accordion-collapse collapse" aria-labelledby="heading-post" data-bs-parent="#aichat-post-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-post" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-post" data-bs-toggle="tab" data-bs-target="#recent-post" type="button" role="tab" aria-controls="recent-post" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-post" data-bs-toggle="tab" data-bs-target="#all-post" type="button" role="tab" aria-controls="all-post" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-post" data-bs-toggle="tab" data-bs-target="#search-post" type="button" role="tab" aria-controls="search-post" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="tabs-content-post">
                                                <div class="tab-pane fade show active" id="recent-post" role="tabpanel" aria-labelledby="recent-tab-post">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-post"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="recent-items-post" class="aichat-items"></div>
                                                </div>
                                                <div class="tab-pane fade" id="all-post" role="tabpanel" aria-labelledby="all-tab-post">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-post"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="all-items-post" class="aichat-items"></div>
                                                    <div id="all-pagination-post" class="aichat-pagination mt-2"></div>
                                                </div>
                                                <div class="tab-pane fade" id="search-post" role="tabpanel" aria-labelledby="search-tab-post">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-post" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'axiachat-ai'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-post"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="search-items-post" class="aichat-items"></div>
                                                    <div id="search-pagination-post" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PAGES -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Pages', 'axiachat-ai' ); ?></span>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_pages_mode" value="all" <?php checked( $select_pages_mode, 'all' ); ?>>
                                    <span>All Pages</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_pages_mode" value="custom" <?php checked( $select_pages_mode, 'custom' ); ?>>
                                    <span>Custom Pages</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-page-accordion" style="<?php echo $select_pages_mode === 'custom' ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="page">
                                    <h2 class="accordion-header" id="heading-page">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-page" aria-expanded="false" aria-controls="collapse-page">
                                            <?php esc_html_e( 'Pages', 'axiachat-ai' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-page" class="accordion-collapse collapse" aria-labelledby="heading-page" data-bs-parent="#aichat-page-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-page" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-page" data-bs-toggle="tab" data-bs-target="#recent-page" type="button" role="tab" aria-controls="recent-page" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-page" data-bs-toggle="tab" data-bs-target="#all-page" type="button" role="tab" aria-controls="all-page" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-page" data-bs-toggle="tab" data-bs-target="#search-page" type="button" role="tab" aria-controls="search-page" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="tabs-content-page">
                                                <div class="tab-pane fade show active" id="recent-page" role="tabpanel" aria-labelledby="recent-tab-page">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-page"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="recent-items-page" class="aichat-items"></div>
                                                </div>
                                                <div class="tab-pane fade" id="all-page" role="tabpanel" aria-labelledby="all-tab-page">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-page"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="all-items-page" class="aichat-items"></div>
                                                    <div id="all-pagination-page" class="aichat-pagination mt-2"></div>
                                                </div>
                                                <div class="tab-pane fade" id="search-page" role="tabpanel" aria-labelledby="search-tab-page">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-page" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'axiachat-ai'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-page"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="search-items-page" class="aichat-items"></div>
                                                    <div id="search-pagination-page" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PRODUCTS -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-bag-check me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Products', 'axiachat-ai' ); ?></span>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_products_mode" value="all" <?php checked( $select_products_mode, 'all' ); ?>>
                                    <span>All Products</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_products_mode" value="custom" <?php checked( $select_products_mode, 'custom' ); ?>>
                                    <span>Custom Products</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-product-accordion" style="<?php echo ($select_products_mode === 'custom' && class_exists('WooCommerce')) ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="product">
                                    <h2 class="accordion-header" id="heading-product">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-product" aria-expanded="false" aria-controls="collapse-product">
                                            <?php esc_html_e( 'Products', 'axiachat-ai' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-product" class="accordion-collapse collapse" aria-labelledby="heading-product" data-bs-parent="#aichat-product-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-product" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-product" data-bs-toggle="tab" data-bs-target="#recent-product" type="button" role="tab" aria-controls="recent-product" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-product" data-bs-toggle="tab" data-bs-target="#all-product" type="button" role="tab" aria-controls="all-product" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-product" data-bs-toggle="tab" data-bs-target="#search-product" type="button" role="tab" aria-controls="search-product" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="tabs-content-product">
                                                <div class="tab-pane fade show active" id="recent-product" role="tabpanel" aria-labelledby="recent-tab-product">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-product"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="recent-items-product" class="aichat-items"></div>
                                                </div>
                                                <div class="tab-pane fade" id="all-product" role="tabpanel" aria-labelledby="all-tab-product">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-product"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="all-items-product" class="aichat-items"></div>
                                                    <div id="all-pagination-product" class="aichat-pagination mt-2"></div>
                                                </div>
                                                <div class="tab-pane fade" id="search-product" role="tabpanel" aria-labelledby="search-tab-product">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-product" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'axiachat-ai'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-product"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="search-items-product" class="aichat-items"></div>
                                                    <div id="search-pagination-product" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- UPLOADED FILES (aichat_upload) -->
                        <div class="mb-2">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-cloud-upload me-2 text-primary"></i>
                                <span class="fw-semibold"><?php esc_html_e( 'Uploaded Files', 'axiachat-ai' ); ?></span>
                                <a class="btn btn-sm btn-outline-secondary ms-2" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-pdf' ) ); ?>">
                                    <i class="bi bi-upload me-1"></i><?php esc_html_e('Open Import PDF/Data', 'axiachat-ai'); ?>
                                </a>
                            </div>
                            <div class="d-flex gap-3 mb-2">
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_uploaded_mode" value="all" <?php checked( $select_uploaded_mode, 'all' ); ?>>
                                    <span>All Uploaded Files</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 m-0">
                                    <input type="checkbox" name="aichat_select_uploaded_mode" value="custom" <?php checked( $select_uploaded_mode, 'custom' ); ?>>
                                    <span>Custom Uploaded Files</span>
                                </label>
                            </div>

                            <div class="accordion" id="aichat-uploaded-accordion" style="<?php echo $select_uploaded_mode === 'custom' ? '' : 'display:none;'; ?>">
                                <div class="accordion-item" data-post-type="aichat_upload">
                                    <h2 class="accordion-header" id="heading-uploaded">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-aichat_upload" aria-expanded="false" aria-controls="collapse-aichat_upload">
                                            <?php esc_html_e( 'Uploaded Files', 'axiachat-ai' ); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-aichat_upload" class="accordion-collapse collapse" aria-labelledby="heading-uploaded" data-bs-parent="#aichat-uploaded-accordion">
                                        <div class="accordion-body">
                                            <ul class="nav nav-tabs mb-3" id="tabs-aichat_upload" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="recent-tab-aichat_upload" data-bs-toggle="tab" data-bs-target="#recent-aichat_upload" type="button" role="tab" aria-controls="recent-aichat_upload" aria-selected="true">
                                                        <i class="bi bi-clock-history me-1"></i><?php esc_html_e( 'Most Recent', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="all-tab-aichat_upload" data-bs-toggle="tab" data-bs-target="#all-aichat_upload" type="button" role="tab" aria-controls="all-aichat_upload" aria-selected="false">
                                                        <i class="bi bi-card-list me-1"></i><?php esc_html_e( 'View All', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" id="search-tab-aichat_upload" data-bs-toggle="tab" data-bs-target="#search-aichat_upload" type="button" role="tab" aria-controls="search-aichat_upload" aria-selected="false">
                                                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Search', 'axiachat-ai' ); ?>
                                                    </button>
                                                </li>
                                            </ul>

                                            <div class="tab-content" id="tabs-content-aichat_upload">
                                                <div class="tab-pane fade show active" id="recent-aichat_upload" role="tabpanel" aria-labelledby="recent-tab-aichat_upload">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#recent-items-aichat_upload"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="recent-items-aichat_upload" class="aichat-items"></div>
                                                </div>

                                                <div class="tab-pane fade" id="all-aichat_upload" role="tabpanel" aria-labelledby="all-tab-aichat_upload">
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#all-items-aichat_upload"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="all-items-aichat_upload" class="aichat-items"></div>
                                                    <div id="all-pagination-aichat_upload" class="aichat-pagination mt-2"></div>
                                                </div>

                                                <div class="tab-pane fade" id="search-aichat_upload" role="tabpanel" aria-labelledby="search-tab-aichat_upload">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                        <input type="text" id="search-input-aichat_upload" class="form-control aichat-search-input" placeholder="<?php esc_attr_e('Search…', 'axiachat-ai'); ?>">
                                                    </div>
                                                    <label class="d-block mb-2">
                                                        <input type="checkbox" class="aichat-select-all me-1" data-target="#search-items-aichat_upload"> <?php esc_html_e('Select All These', 'axiachat-ai'); ?>
                                                    </label>
                                                    <div id="search-items-aichat_upload" class="aichat-items"></div>
                                                    <div id="search-pagination-aichat_upload" class="aichat-pagination mt-2"></div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text"><?php esc_html_e('Select the parent items (each uploaded file). Internal logic will process their chunks automatically.', 'axiachat-ai'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Card: Advanced Indexing Options -->
                <div class="card card100 shadow-sm mb-4" id="aichat-indexing-options-card">
                    <div class="card-body">
                        <h5 class="card-title mb-3 d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-sliders me-2 text-secondary"></i><?php esc_html_e('Advanced Indexing Options','axiachat-ai'); ?></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#aichat-indexing-options-body" aria-expanded="false">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </h5>
                        <p class="text-muted small mb-2"><?php esc_html_e('By default, only title and content are indexed. Expand to include additional fields.','axiachat-ai'); ?></p>
                        <div class="collapse" id="aichat-indexing-options-body">

                            <!-- General fields -->
                            <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-file-text me-1"></i> <?php esc_html_e('General Fields','axiachat-ai'); ?></h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="aichat-idx-excerpt" />
                                <label class="form-check-label" for="aichat-idx-excerpt"><?php esc_html_e('Include excerpt / summary','axiachat-ai'); ?></label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="aichat-idx-url" checked />
                                <label class="form-check-label" for="aichat-idx-url"><?php esc_html_e('Include post URL','axiachat-ai'); ?></label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="aichat-idx-featured-image" />
                                <label class="form-check-label" for="aichat-idx-featured-image"><?php esc_html_e('Include featured image URL','axiachat-ai'); ?></label>
                                <div class="form-text small"><?php esc_html_e('The AI will be able to show the featured image in its responses (requires Markdown image syntax in bot instructions).','axiachat-ai'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small" for="aichat-idx-taxonomies"><?php esc_html_e('Include taxonomies (comma-separated slugs)','axiachat-ai'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="aichat-idx-taxonomies" placeholder="category, post_tag, product_cat, product_tag" />
                                <div class="form-text small"><?php esc_html_e('Leave empty to skip. Examples: category, post_tag, product_cat','axiachat-ai'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small" for="aichat-idx-custom-meta"><?php esc_html_e('Custom meta keys (comma-separated)','axiachat-ai'); ?></label>
                                <input type="text" class="form-control form-control-sm" id="aichat-idx-custom-meta" placeholder="my_acf_field, another_field" />
                                <div class="form-text small"><?php esc_html_e('Post meta keys to include in indexing (ACF, custom fields, etc.)','axiachat-ai'); ?></div>
                            </div>

                            <!-- WooCommerce fields -->
                            <?php if ( class_exists('WooCommerce') ) : ?>
                            <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-cart me-1"></i> <?php esc_html_e('WooCommerce Fields (static, embedded)','axiachat-ai'); ?></h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="aichat-idx-wc-short-desc" />
                                <label class="form-check-label" for="aichat-idx-wc-short-desc"><?php esc_html_e('Include short description','axiachat-ai'); ?></label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="aichat-idx-wc-attributes" />
                                <label class="form-check-label" for="aichat-idx-wc-attributes"><?php esc_html_e('Include attributes (size, color, etc.)','axiachat-ai'); ?></label>
                            </div>
                            <?php endif; ?>

                            <div class="alert alert-light border py-2 small mt-3 mb-0">
                                <i class="bi bi-lightbulb me-1 text-warning"></i>
                                <strong><?php esc_html_e('Tips:','axiachat-ai'); ?></strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    <li><?php esc_html_e('Dynamic WooCommerce data (price, stock, SKU) is automatically injected at query time with fresh values — no need to re-index when prices change.','axiachat-ai'); ?></li>
                                    <li><?php esc_html_e('Changes to these options require re-indexing the context to take effect on existing content.','axiachat-ai'); ?></li>
                                    <li><?php esc_html_e('To display images in the chat, enable "Featured image URL" and instruct your bot to use Markdown image syntax in its responses.','axiachat-ai'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Autosync Content (shown only if any ALL_* source selected) -->
                <div class="card card100 shadow-sm mb-4" id="aichat-autosync-card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-arrow-repeat me-2 text-secondary"></i><?php esc_html_e('Autosync Content','axiachat-ai'); ?>
                        </h5>
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block" for="aichat-create-autosync">
                                <?php esc_html_e('Enable Autosync','axiachat-ai'); ?>
                            </label>
                            <input type="checkbox" id="aichat-create-autosync" />
                            <div class="form-text">
                                <?php esc_html_e('When enabled, the system will periodically detect modified content and re-embed it so your context stays fresh.','axiachat-ai'); ?>
                            </div>
                        </div>
                        <div class="mb-3" id="aichat-create-autosync-mode-wrapper" style="display:none;">
                            <label class="form-label fw-semibold" for="aichat-create-autosync-mode"><?php esc_html_e('Autosync Mode','axiachat-ai'); ?></label>
                            <select id="aichat-create-autosync-mode" class="form-select form-select-sm">
                                <option value="updates"><?php esc_html_e('Only update existing','axiachat-ai'); ?></option>
                                <option value="updates_and_new"><?php esc_html_e('Update + add new','axiachat-ai'); ?></option>
                            </select>
                            <div class="form-text" id="aichat-create-autosync-help-general">
                                <?php esc_html_e('Only update existing: re-embeds items already in the context. Update + add new: also discovers and indexes newly published items of the selected ALL sources.','axiachat-ai'); ?>
                            </div>
                            <div class="form-text text-warning" id="aichat-create-autosync-help-limited" style="display:none;">
                                <?php esc_html_e('This selection is limited (no ALL source), so only updating existing items is available.','axiachat-ai'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Process -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-cpu me-2 text-secondary"></i><?php esc_html_e( 'CREATE CONTEXT', 'axiachat-ai' ); ?>
                        </h5>

                        <div class="alert alert-info d-flex align-items-center py-2" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <div id="aichat-selection-summary">No selections yet.</div>
                        </div>

                        <button type="button" id="aichat-process-context" class="button button-primary btn btn-primary">
                            <i class="bi bi-play-circle me-1"></i><?php esc_html_e( 'PROCESS', 'axiachat-ai' ); ?>
                        </button>

                        <div class="progress mt-3" style="height: 22px;">
                            <div class="progress-bar" id="aichat-progress-bar" role="progressbar"
                                 style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                0%
                            </div>
                        </div>

                        <div id="aichat-index-log" class="mt-3 p-2 border rounded bg-light" style="height: 240px; overflow-y: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px;"></div>
                    </div>
                </div>

            </div><!-- /tab-pane -->
        </div><!-- /tab-content -->
    </div><!-- /wrap -->
    <?php
}
