<?php
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Página de settings para Configuración y Mantenimiento de Contextos
function aichat_contexto_settings_page()
{
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin screen listing internal context tables.
	$contexts = $wpdb->get_results(
		"SELECT c.id, c.name, c.embedding_provider, c.processing_progress, c.processing_status, c.created_at,
                (SELECT COUNT(*) FROM {$wpdb->prefix}aichat_chunks ch WHERE ch.id_context=c.id) AS chunk_count,
                (SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aichat_chunks ch2 WHERE ch2.id_context=c.id) AS post_count
         FROM {$wpdb->prefix}aichat_contexts c",
		ARRAY_A
	);
	?>
    <div class="wrap aichat-admin">
    <h1 class="mb-3"><?php echo esc_html(__('Context Settings', 'axiachat-ai')); ?></h1>

        <!-- Pestañas -->
        <ul class="nav nav-tabs mb-4" id="context-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="context-tab" data-bs-toggle="tab" data-bs-target="#context"
                        type="button" role="tab" aria-controls="context" aria-selected="true">
                    <i class="bi bi-gear"></i> <?php esc_html_e('Context', 'axiachat-ai'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url(admin_url('admin.php?page=aichat-contexto-create')); ?>" role="tab">
                    <i class="bi bi-plus-circle"></i> <?php esc_html_e('Add New', 'axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url(admin_url('admin.php?page=aichat-contexto-pdf')); ?>" role="tab">
                    <i class="bi bi-file-earmark-arrow-up"></i> <?php esc_html_e('Import PDF/Data', 'axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url(admin_url('admin.php?page=aichat-contexto-modify')); ?>" role="tab">
                    <i class="bi bi-pencil-square"></i> <?php esc_html_e('Modify Context', 'axiachat-ai'); ?>
                </a>
            </li>
        </ul>

        <!-- Contenido de las pestañas -->
        <div class="tab-content" id="context-tab-content">
            <div class="tab-pane fade show active" id="context" role="tabpanel" aria-labelledby="context-tab">

                <!-- Card: Existing Contexts -->
                <div class="card card100 shadow-sm border-0">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="bi bi-layers"></i> <?php esc_html_e('Existing Contexts', 'axiachat-ai'); ?>
                        </h5>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-contexto-create')); ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> <?php esc_html_e('Create New', 'axiachat-ai'); ?>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="aichat-contexts-table" class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?php esc_html_e('ID', 'axiachat-ai'); ?></th>
                                        <th><?php esc_html_e('Name', 'axiachat-ai'); ?></th>
                                        <th><?php esc_html_e('AI Provider', 'axiachat-ai'); ?></th>
                                        <th><?php esc_html_e('Chunks', 'axiachat-ai'); ?></th>
                                        <th><?php esc_html_e('Posts', 'axiachat-ai'); ?></th>
                                        <th><?php esc_html_e('Created / Status', 'axiachat-ai'); ?></th>
                                        <th class="w-15"><?php esc_html_e('Progress', 'axiachat-ai'); ?></th>
                                        <th class="text-end"><?php esc_html_e('Actions', 'axiachat-ai'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aichat-contexts-body">
                            <?php if (! empty($contexts)) : foreach ($contexts as $context):
                            	$provider_label = '';
                            	$provider_icon = '';
                            	if (! empty($context['embedding_provider'])) {
                            		$ep = strtolower($context['embedding_provider']);
                            		if ($ep === 'openai') {
                            			$provider_label = 'OpenAI';
                            			$provider_icon = '🟢';
                            		} elseif ($ep === 'claude' || $ep === 'anthropic') {
                            			$provider_label = 'Claude';
                            			$provider_icon = '🟠';
                            		} elseif ($ep === 'gemini') {
                            			$provider_label = 'Gemini';
                            			$provider_icon = '🔵';
                            		} else {
                            			$provider_label = ucfirst($ep);
                            			$provider_icon = '⚪';
                            		}
                            	}
                            	?>
                                <tr>
                                    <td class="text-muted small"><?php echo esc_html($context['id']); ?></td>
                                    <td>
                                        <span class="context-name fw-semibold" data-id="<?php echo esc_attr($context['id']); ?>">
                                            <i class="bi bi-folder2"></i> <?php echo esc_html($context['name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo esc_html($provider_icon . ' ' . $provider_label); ?></td>
                                    <td class="text-muted small"><?php echo (int) $context['chunk_count']; ?></td>
                                    <td class="text-muted small"><?php echo (int) $context['post_count']; ?></td>
                                    <td class="text-muted small"><?php echo esc_html($context['created_at']); ?> / <?php echo esc_html($context['processing_status']); ?></td>
                                    <td>
                                        <div class="progress" style="height:14px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo esc_attr($context['processing_progress']); ?>%;" aria-valuenow="<?php echo esc_attr($context['processing_progress']); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo (int) $context['processing_progress']; ?>%</div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="button btn btn-sm btn-outline-secondary edit-context-settings" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-gear"></i> <?php esc_html_e('Settings', 'axiachat-ai'); ?>
                                            </button>
                                            <button type="button" class="button btn btn-sm btn-outline-secondary edit-context-simtest" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-search"></i> <?php esc_html_e('Similarity', 'axiachat-ai'); ?>
                                            </button>
                                            <?php if (isset($context['context_type']) ? $context['context_type'] === 'local' : true): ?>
                                            <button type="button" class="button btn btn-sm btn-outline-dark browse-context" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-list-ul"></i> <?php esc_html_e('Browse', 'axiachat-ai'); ?>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="button btn btn-sm btn-outline-info run-autosync-now" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-arrow-repeat"></i> <?php esc_html_e('Run AutoSync', 'axiachat-ai'); ?>
                                            </button>
                                            <button type="button" class="button btn btn-sm btn-outline-danger delete-context" data-id="<?php echo esc_attr($context['id']); ?>">
                                                <i class="bi bi-trash"></i> <?php esc_html_e('Delete', 'axiachat-ai'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach;
	else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inboxes"></i> <?php esc_html_e('No contexts yet. Create one in the “Add New” tab.', 'axiachat-ai'); ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        <i class="bi bi-info-circle"></i> <?php esc_html_e('Progress updates automatically when indexing runs from Add New or via cron.', 'axiachat-ai'); ?>
                        <br><i class="bi bi-exclamation-triangle"></i> <?php esc_html_e('If you change the AI provider in the future, existing contexts must be reindexed or recreated for that provider.', 'axiachat-ai'); ?>
                    </div>
                </div>

            </div><!-- /.tab-pane -->
            
                        <!-- Redesigned Context Edit / Test Panel -->
                        <div id="aichat-context-test-wrapper" class="mt-4" style="display:none;">
                            <div class="card card100 shadow-sm border-0">
                                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                                    <h5 class="mb-0" id="aichat-context-panel-title"><i class="bi bi-folder2"></i> <?php esc_html_e('Context', 'axiachat-ai'); ?> <span class="text-muted" id="aichat-context-panel-name"></span></h5>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="aichat-close-test" aria-label="Close">&times;</button>
                                </div>
                                <div class="card-body">
                                    <!-- Inner tabs for Settings / Similarity / Browse -->
                                    <ul class="nav nav-pills mb-3" id="aichat-inner-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="aichat-tab-settings" data-bs-toggle="tab" data-bs-target="#aichat-pane-settings" type="button" role="tab"><?php esc_html_e('Settings', 'axiachat-ai'); ?></button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="aichat-tab-simtest" data-bs-toggle="tab" data-bs-target="#aichat-pane-simtest" type="button" role="tab"><?php esc_html_e('Similarity Test', 'axiachat-ai'); ?></button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="aichat-tab-browse" data-bs-toggle="tab" data-bs-target="#aichat-pane-browse" type="button" role="tab"><?php esc_html_e('Browse Chunks', 'axiachat-ai'); ?></button>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="aichat-inner-tabcontent">
                                        <div class="tab-pane fade show active" id="aichat-pane-settings" role="tabpanel" aria-labelledby="aichat-tab-settings">
                                    <!-- Settings Section -->
                                    <div id="aichat-context-meta" class="mb-3" style="display:none;">
                                        <form id="aichat-context-rename-form" onsubmit="return false;">
                                            <!-- Name -->
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold mb-1" for="aichat-edit-context-name"><?php esc_html_e('Name', 'axiachat-ai'); ?></label>
                                                <input type="text" id="aichat-edit-context-name" class="form-control form-control-sm" />
                                            </div>
                                            <!-- AutoSync Toggle -->
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold mb-1 d-flex align-items-center gap-1" for="aichat-autosync-toggle">AutoSync <span class="text-muted small" data-bs-toggle="tooltip" title="<?php esc_attr_e('Keeps the context updated with site content changes', 'axiachat-ai'); ?>"><i class="bi bi-question-circle"></i></span></label>
                                                <div class="mb-1">
                                                    <label class="d-flex align-items-center gap-2 m-0">
                                                        <input type="checkbox" id="aichat-autosync-toggle" />
                                                        <span class="small"><?php esc_html_e('Enable', 'axiachat-ai'); ?></span>
                                                    </label>
                                                </div>
                                                <div class="small text-muted" style="max-width:520px;">
                                                    <?php esc_html_e('If enabled, modified items are periodically re-embedded to keep answers fresh.', 'axiachat-ai'); ?>
                                                </div>
                                            </div>
                                            <!-- AutoSync Mode -->
                                            <div class="mb-3" id="aichat-autosync-mode-wrapper" style="display:none;">
                                                <label class="form-label fw-semibold mb-1" for="aichat-autosync-mode"><?php esc_html_e('AutoSync Mode', 'axiachat-ai'); ?></label>
                                                <select id="aichat-autosync-mode" class="form-select form-select-sm">
                                                    <option value="updates"><?php esc_html_e('Only update existing', 'axiachat-ai'); ?></option>
                                                    <option value="updates_and_new"><?php esc_html_e('Update + add new', 'axiachat-ai'); ?></option>
                                                </select>
                                                <div class="form-text small" id="aichat-autosync-mode-help">
                                                    <?php esc_html_e('"Only update existing" re-embeds items already present. "Update + add new" also discovers and indexes newly published content from ALL sources.', 'axiachat-ai'); ?>
                                                </div>
                                                <div class="form-text small text-warning" id="aichat-autosync-mode-restricted" style="display:none;">
                                                    <?php esc_html_e('This context is limited; only updating existing content is allowed.', 'axiachat-ai'); ?>
                                                </div>
                                            </div>

                                            <!-- Advanced Indexing Options (collapsible) -->
                                            <div class="mb-3">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <label class="form-label fw-semibold mb-0"><?php esc_html_e('Advanced Indexing Options', 'axiachat-ai'); ?></label>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="collapse" data-bs-target="#aichat-settings-idx-body" aria-expanded="false">
                                                        <i class="bi bi-chevron-down"></i>
                                                    </button>
                                                </div>
                                                <div class="collapse" id="aichat-settings-idx-body">
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input aichat-settings-idx" type="checkbox" id="aichat-settings-idx-excerpt" />
                                                        <label class="form-check-label small" for="aichat-settings-idx-excerpt"><?php esc_html_e('Include excerpt', 'axiachat-ai'); ?></label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input aichat-settings-idx" type="checkbox" id="aichat-settings-idx-url" />
                                                        <label class="form-check-label small" for="aichat-settings-idx-url"><?php esc_html_e('Include post URL', 'axiachat-ai'); ?></label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input aichat-settings-idx" type="checkbox" id="aichat-settings-idx-featured-image" />
                                                        <label class="form-check-label small" for="aichat-settings-idx-featured-image"><?php esc_html_e('Featured image URL', 'axiachat-ai'); ?></label>
                                                    </div>
                                                    <div class="mb-2">
                                                        <input type="text" class="form-control form-control-sm aichat-settings-idx" id="aichat-settings-idx-taxonomies" placeholder="<?php esc_attr_e('Taxonomies: category, post_tag, product_cat', 'axiachat-ai'); ?>" />
                                                    </div>
                                                    <div class="mb-2">
                                                        <input type="text" class="form-control form-control-sm aichat-settings-idx" id="aichat-settings-idx-custom-meta" placeholder="<?php esc_attr_e('Custom meta keys: my_field, other_field', 'axiachat-ai'); ?>" />
                                                    </div>
                                                    <?php if (class_exists('WooCommerce')) : ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input aichat-settings-idx" type="checkbox" id="aichat-settings-idx-wc-short-desc" />
                                                        <label class="form-check-label small" for="aichat-settings-idx-wc-short-desc"><?php esc_html_e('WC: Short description', 'axiachat-ai'); ?></label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input aichat-settings-idx" type="checkbox" id="aichat-settings-idx-wc-attributes" />
                                                        <label class="form-check-label small" for="aichat-settings-idx-wc-attributes"><?php esc_html_e('WC: Attributes', 'axiachat-ai'); ?></label>
                                                    </div>
                                                    <div class="small text-info mb-2"><i class="bi bi-info-circle me-1"></i><?php esc_html_e('Price, stock & SKU are injected live at query time.', 'axiachat-ai'); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Save Button -->
                                            <div class="mb-2 d-flex justify-content-end">
                                                <button class="btn btn-sm btn-primary" id="aichat-save-context-name"><i class="bi bi-save"></i> <?php esc_html_e('Save Changes', 'axiachat-ai'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                    </div><!-- /settings pane -->
                                    <div class="tab-pane fade" id="aichat-pane-simtest" role="tabpanel" aria-labelledby="aichat-tab-simtest">
                                        <hr class="my-4" />
                                        <!-- Semantic Test Section -->
                                        <div class="d-flex align-items-center mb-2">
                                        <h6 class="mb-0 me-2"><i class="bi bi-search"></i> <?php esc_html_e('Similarity Test', 'axiachat-ai'); ?></h6>
                                        <span class="small text-muted"><?php esc_html_e('Run a query to inspect top matching chunks.', 'axiachat-ai'); ?></span>
                                        </div>
                                    <form id="aichat-context-test-form" class="row g-2 align-items-center mb-3" onsubmit="return false;">
                                        <div class="col-md-7">
                                            <input type="text" class="form-control" id="aichat-test-query" placeholder="<?php echo esc_attr(__('Example: shipping costs for returns', 'axiachat-ai')); ?>" />
                                        </div>
                                        <div class="col-md-2">
                                            <select id="aichat-test-limit" class="form-select form-select-sm">
                                                <option value="5">Top 5</option>
                                                <option value="10" selected>Top 10</option>
                                                <option value="15">Top 15</option>
                                                <option value="20">Top 20</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-grid">
                                            <button id="aichat-run-test" class="btn btn-outline-primary"><i class="bi bi-search"></i> <?php esc_html_e('Run Search', 'axiachat-ai'); ?></button>
                                        </div>
                                    </form>
                                    <div id="aichat-test-status" class="small text-muted mb-2" style="display:none;"></div>
                                        <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0" id="aichat-test-results" style="display:none;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:70px;">Score</th>
                                                    <th><?php esc_html_e('Title', 'axiachat-ai'); ?></th>
                                                    <th><?php esc_html_e('Excerpt', 'axiachat-ai'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                        </div>
                                    </div><!-- /simtest pane -->
                                    <div class="tab-pane fade" id="aichat-pane-browse" role="tabpanel" aria-labelledby="aichat-tab-browse">
                                        <hr class="my-4" />
                                        <div class="d-flex align-items-center mb-2">
                                            <h6 class="mb-0 me-2"><i class="bi bi-list-ul"></i> <?php esc_html_e('Browse Chunks', 'axiachat-ai'); ?></h6>
                                            <span class="small text-muted"><?php esc_html_e('Inspect stored chunks with filters.', 'axiachat-ai'); ?></span>
                                        </div>
                                        <form id="aichat-browse-form" class="row g-2 align-items-center mb-3" onsubmit="return false;">
                                            <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="aichat-browse-q" placeholder="<?php echo esc_attr(__('Search text', 'axiachat-ai')); ?>" /></div>
                                            <div class="col-md-2">
                                                <select id="aichat-browse-type" class="form-select form-select-sm">
                                                    <option value=""><?php esc_html_e('All types', 'axiachat-ai'); ?></option>
                                                    <option value="post">Post</option>
                                                    <option value="page">Page</option>
                                                    <option value="product">Product</option>
                                                    <option value="upload">Upload</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <select id="aichat-browse-perpage" class="form-select form-select-sm">
                                                    <option value="10">10</option>
                                                    <option value="25" selected>25</option>
                                                    <option value="50">50</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-grid">
                                                <button id="aichat-browse-run" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> <?php esc_html_e('Run', 'axiachat-ai'); ?></button>
                                            </div>
                                            <div class="col-md-2 small text-muted" id="aichat-browse-status" style="display:none;"></div>
                                        </form>
                                        <div class="table-responsive mb-2">
                                            <table class="table table-sm table-striped align-middle mb-0" id="aichat-browse-results" style="display:none;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th><?php esc_html_e('Chunk', 'axiachat-ai'); ?></th>
                                                        <th><?php esc_html_e('Post ID', 'axiachat-ai'); ?></th>
                                                        <th><?php esc_html_e('Type', 'axiachat-ai'); ?></th>
                                                        <th><?php esc_html_e('Title', 'axiachat-ai'); ?></th>
                                                        <th><?php esc_html_e('Updated', 'axiachat-ai'); ?></th>
                                                        <th><?php esc_html_e('Size', 'axiachat-ai'); ?></th>
                                                        <th><?php esc_html_e('Excerpt', 'axiachat-ai'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center" id="aichat-browse-pager" style="display:none;">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-secondary" id="aichat-browse-prev" disabled>&laquo; <?php esc_html_e('Prev', 'axiachat-ai'); ?></button>
                                                <button type="button" class="btn btn-outline-secondary" id="aichat-browse-next" disabled><?php esc_html_e('Next', 'axiachat-ai'); ?> &raquo;</button>
                                            </div>
                                            <div class="small text-muted" id="aichat-browse-pageinfo"></div>
                                        </div>
                                    </div><!-- /browse pane -->
                                    </div><!-- /tab-content -->
                                </div>
                            </div>
                        </div>
        </div><!-- /.tab-content -->
    </div>

        <!-- Modal Run AutoSync Now -->
        <div class="modal fade" id="aichat-autosync-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> <?php esc_html_e('Run AutoSync Now', 'axiachat-ai'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2"><?php esc_html_e('Choose what to sync. "Update + add new" only available if context scope allows discovering new items.', 'axiachat-ai'); ?></p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="aichat-autosync-mode-radio" id="aichat-autosync-radio-modified" value="modified" checked>
                            <label class="form-check-label" for="aichat-autosync-radio-modified"><?php esc_html_e('Modified only (fast)', 'axiachat-ai'); ?></label>
                        </div>
                        <div class="form-check mb-2" id="aichat-autosync-radio-new-wrapper">
                            <input class="form-check-input" type="radio" name="aichat-autosync-mode-radio" id="aichat-autosync-radio-modified-new" value="modified_and_new">
                            <label class="form-check-label" for="aichat-autosync-radio-modified-new"><?php esc_html_e('Modified + discover new', 'axiachat-ai'); ?></label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="aichat-autosync-mode-radio" id="aichat-autosync-radio-full" value="full">
                            <label class="form-check-label" for="aichat-autosync-radio-full"><?php esc_html_e('Full rebuild (slow)', 'axiachat-ai'); ?></label>
                        </div>
                        <div class="alert alert-warning py-2 small d-none" id="aichat-autosync-limited-note"><i class="bi bi-exclamation-triangle me-1"></i><?php esc_html_e('This is a LIMITED scope context; discovering new items is disabled.', 'axiachat-ai'); ?></div>
                        <input type="hidden" id="aichat-autosync-modal-context-id" value="" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php esc_html_e('Cancel', 'axiachat-ai'); ?></button>
                        <button type="button" class="btn btn-primary btn-sm" id="aichat-autosync-run-confirm"><i class="bi bi-play-circle"></i> <?php esc_html_e('Run', 'axiachat-ai'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    <?php
}
