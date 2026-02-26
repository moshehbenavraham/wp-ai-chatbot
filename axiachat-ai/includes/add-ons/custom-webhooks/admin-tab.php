<?php
/**
 * Custom Webhooks Admin Tab Content
 * 
 * Renders the UI for managing custom webhook actions.
 * 
 * @package AIChat
 * @subpackage CustomWebhooks
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render the Custom Webhooks tab content
 */
function aichat_custom_webhooks_tab_content() {
    $is_pro = aichat_custom_webhooks_has_pro();
    $max = aichat_custom_webhooks_max_allowed();
    $count = aichat_custom_webhooks_count();
    ?>
    <div id="aichat-custom-webhooks-container">
        
        <!-- Hero Banner -->
        <div class="card card100 mb-4 border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="text-white mb-2">
                            <i class="bi bi-broadcast me-2"></i><?php esc_html_e( 'Connect Your Bot to 1,000+ Apps', 'axiachat-ai' ); ?>
                        </h4>
                        <p class="text-white-50 mb-2">
                            <?php esc_html_e( 'Create custom webhook actions that your AI can trigger during conversations. Integrate with n8n, Zapier, Make, or any HTTP endpoint.', 'axiachat-ai' ); ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge bg-white bg-opacity-25 text-white"><i class="bi bi-check2 me-1"></i><?php esc_html_e( 'Escalate to humans', 'axiachat-ai' ); ?></span>
                            <span class="badge bg-white bg-opacity-25 text-white"><i class="bi bi-check2 me-1"></i><?php esc_html_e( 'Create tickets', 'axiachat-ai' ); ?></span>
                            <span class="badge bg-white bg-opacity-25 text-white"><i class="bi bi-check2 me-1"></i><?php esc_html_e( 'Notify via Slack/SMS', 'axiachat-ai' ); ?></span>
                            <span class="badge bg-white bg-opacity-25 text-white"><i class="bi bi-check2 me-1"></i><?php esc_html_e( 'Sync with CRM', 'axiachat-ai' ); ?></span>
                        </div>
                        <a href="https://n8n.io/integrations/" target="_blank" rel="noopener" class="btn btn-light btn-sm mt-3">
                            <i class="bi bi-box-arrow-up-right me-1"></i><?php esc_html_e( 'Explore 1,000+ integrations', 'axiachat-ai' ); ?>
                        </a>
                    </div>
                    <div class="col-md-4 text-center d-none d-md-block">
                        <i class="bi bi-diagram-3 display-1 text-white opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ( ! $is_pro ) : ?>
        <!-- Free Plan Banner -->
        <div class="alert alert-info mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="bi bi-broadcast me-2 fs-5"></i>
                <div>
                    <strong><?php esc_html_e( 'Free Plan', 'axiachat-ai' ); ?>:</strong>
                    <span id="aichat-webhooks-count"><?php echo esc_html( $count ); ?></span>/<?php echo esc_html( $max ); ?> <?php esc_html_e( 'webhooks used', 'axiachat-ai' ); ?>
                    <?php if ( $count >= $max ) : ?>
                        <span class="badge bg-danger ms-2"><?php esc_html_e( 'Limit reached', 'axiachat-ai' ); ?></span>
                    <?php endif; ?>
                    <span class="text-muted ms-2">—</span>
                    <span class="text-muted ms-1"><?php esc_html_e( 'Pro has unlimited webhooks', 'axiachat-ai' ); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-settings-pricing' ) ); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-rocket me-1"></i><?php esc_html_e( 'Upgrade to Pro', 'axiachat-ai' ); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="text-muted mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php esc_html_e( 'Each webhook becomes a capability you can enable per bot in the Capabilities tab.', 'axiachat-ai' ); ?>
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" id="aichat-webhook-add-btn" <?php echo ( ! $is_pro && $count >= $max ) ? 'disabled' : ''; ?>>
                    <i class="bi bi-plus-lg me-1"></i><?php esc_html_e( 'New Webhook Action', 'axiachat-ai' ); ?>
                </button>
            </div>
        </div>
        
        <!-- Webhooks List -->
        <div id="aichat-webhooks-list" class="row g-3">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden"><?php esc_html_e( 'Loading...', 'axiachat-ai' ); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Empty State -->
        <div id="aichat-webhooks-empty" class="text-center py-5" style="display:none;">
            <i class="bi bi-broadcast-pin display-1 text-muted"></i>
            <h4 class="mt-3"><?php esc_html_e( 'No custom webhooks yet', 'axiachat-ai' ); ?></h4>
            <p class="text-muted"><?php esc_html_e( 'Create your first webhook action to extend your bot\'s capabilities.', 'axiachat-ai' ); ?></p>
            <button type="button" class="btn btn-outline-primary" id="aichat-webhook-add-first">
                <i class="bi bi-plus-lg me-1"></i><?php esc_html_e( 'Create First Webhook', 'axiachat-ai' ); ?>
            </button>
        </div>
    </div>
    
    <!-- Modal: Create/Edit Webhook -->
    <div class="modal fade" id="aichat-webhook-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="aichat-webhook-modal-title">
                        <i class="bi bi-broadcast me-2"></i><?php esc_html_e( 'New Webhook Action', 'axiachat-ai' ); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="aichat-webhook-form">
                        <input type="hidden" name="id" id="wh-id" value="0">
                        
                        <!-- Basic Info -->
                        <div class="card card100 mb-3">
                            <div class="card-header bg-light">
                                <i class="bi bi-tag me-1"></i><?php esc_html_e( 'Basic Information', 'axiachat-ai' ); ?>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="wh-slug"><?php esc_html_e( 'Internal Name (slug)', 'axiachat-ai' ); ?> <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><code>webhook_</code></span>
                                            <input type="text" class="form-control" id="wh-slug" name="slug" pattern="[a-z0-9_]+" required placeholder="escalar_a_humano">
                                        </div>
                                        <small class="text-muted"><?php esc_html_e( 'Lowercase letters, numbers, underscores only.', 'axiachat-ai' ); ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="wh-label"><?php esc_html_e( 'Display Label', 'axiachat-ai' ); ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="wh-label" name="label" required placeholder="<?php esc_attr_e( 'Escalate to Human', 'axiachat-ai' ); ?>">
                                        <small class="text-muted"><?php esc_html_e( 'Shown in Capabilities list.', 'axiachat-ai' ); ?></small>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="wh-description-ai"><?php esc_html_e( 'AI Description', 'axiachat-ai' ); ?> <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="wh-description-ai" name="description_ai" rows="3" required placeholder="<?php esc_attr_e( 'Call this function when the user asks to speak with a real person, is frustrated, or the problem is too complex to solve via chat.', 'axiachat-ai' ); ?>"></textarea>
                                        <small class="text-muted"><?php esc_html_e( 'This tells the AI model when to use this action. Be specific!', 'axiachat-ai' ); ?></small>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="wh-activity-label"><?php esc_html_e( 'Activity Label (shown to user)', 'axiachat-ai' ); ?></label>
                                        <input type="text" class="form-control" id="wh-activity-label" name="activity_label" placeholder="<?php esc_attr_e( 'Escalating to a human agent...', 'axiachat-ai' ); ?>">
                                        <small class="text-muted"><?php esc_html_e( 'Displayed while the action is executing.', 'axiachat-ai' ); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Webhook Config -->
                        <div class="card card100 mb-3">
                            <div class="card-header bg-light">
                                <i class="bi bi-link-45deg me-1"></i><?php esc_html_e( 'Webhook Configuration', 'axiachat-ai' ); ?>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-9">
                                        <label class="form-label" for="wh-url"><?php esc_html_e( 'Webhook URL', 'axiachat-ai' ); ?> <span class="text-danger">*</span></label>
                                        <input type="url" class="form-control" id="wh-url" name="webhook_url" required placeholder="https://example.com/webhook">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="wh-method"><?php esc_html_e( 'Method', 'axiachat-ai' ); ?></label>
                                        <select class="form-select" id="wh-method" name="http_method">
                                            <option value="POST">POST</option>
                                            <option value="GET">GET</option>
                                            <option value="PUT">PUT</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="wh-headers"><?php esc_html_e( 'Custom Headers (JSON)', 'axiachat-ai' ); ?></label>
                                        <textarea class="form-control font-monospace" id="wh-headers" name="headers_json" rows="2" placeholder='{"Authorization": "Bearer your-api-key"}'></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Parameters -->
                        <div class="card card100 mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-sliders me-1"></i><?php esc_html_e( 'Parameters (AI fills these dynamically)', 'axiachat-ai' ); ?></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="wh-add-param">
                                    <i class="bi bi-plus"></i> <?php esc_html_e( 'Add Parameter', 'axiachat-ai' ); ?>
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="wh-params-list">
                                    <!-- Parameters added dynamically -->
                                </div>
                                <p class="text-muted small mb-0" id="wh-params-empty">
                                    <i class="bi bi-info-circle me-1"></i><?php esc_html_e( 'No parameters defined. The AI will call this webhook without arguments.', 'axiachat-ai' ); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Static Parameters -->
                        <div class="card card100 mb-3">
                            <div class="card-header bg-light">
                                <i class="bi bi-lock me-1"></i><?php esc_html_e( 'Static Parameters (always sent)', 'axiachat-ai' ); ?>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control font-monospace" id="wh-static-params" name="static_params" rows="2" placeholder='{"source": "aichat", "api_version": "1.0"}'></textarea>
                                <small class="text-muted">
                                    <?php esc_html_e( 'Placeholders:', 'axiachat-ai' ); ?> 
                                    <code>{{NOW}}</code>, <code>{{DATE}}</code>, <code>{{TIME}}</code>, <code>{{SESSION_ID}}</code>, <code>{{BOT_SLUG}}</code>, <code>{{PAGE_URL}}</code>
                                </small>
                            </div>
                        </div>
                        
                        <!-- History Option -->
                        <div class="card card100 mb-3">
                            <div class="card-header bg-light">
                                <i class="bi bi-chat-left-text me-1"></i><?php esc_html_e( 'Conversation History', 'axiachat-ai' ); ?>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="wh-include-history" name="include_history" value="1">
                                    <label class="form-check-label" for="wh-include-history">
                                        <?php esc_html_e( 'Include conversation history in webhook payload', 'axiachat-ai' ); ?>
                                    </label>
                                </div>
                                <div id="wh-history-options" style="display:none;">
                                    <label class="form-label" for="wh-history-limit"><?php esc_html_e( 'Max messages to include', 'axiachat-ai' ); ?></label>
                                    <input type="number" class="form-control" id="wh-history-limit" name="history_limit" value="10" min="1" max="50" style="width:100px;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Response Messages -->
                        <div class="card card100 mb-3">
                            <div class="card-header bg-light">
                                <i class="bi bi-chat-dots me-1"></i><?php esc_html_e( 'Response Messages', 'axiachat-ai' ); ?>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="wh-success-msg"><?php esc_html_e( 'Success Message', 'axiachat-ai' ); ?></label>
                                        <textarea class="form-control" id="wh-success-msg" name="success_message" rows="2" placeholder="<?php esc_attr_e( 'I\'ve notified our team. Someone will contact you shortly.', 'axiachat-ai' ); ?>"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="wh-error-msg"><?php esc_html_e( 'Error Message', 'axiachat-ai' ); ?></label>
                                        <textarea class="form-control" id="wh-error-msg" name="error_message" rows="2" placeholder="<?php esc_attr_e( 'Sorry, I couldn\'t complete that action. Please try again.', 'axiachat-ai' ); ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Status -->
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="wh-active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="wh-active">
                                <?php esc_html_e( 'Active (available to enable in bots)', 'axiachat-ai' ); ?>
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?php esc_html_e( 'Cancel', 'axiachat-ai' ); ?>
                    </button>
                    <button type="button" class="btn btn-primary" id="aichat-webhook-save">
                        <i class="bi bi-check-lg me-1"></i><?php esc_html_e( 'Save Webhook', 'axiachat-ai' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Parameter Template (hidden) -->
    <template id="wh-param-template">
        <div class="wh-param-row border rounded p-2 mb-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small"><?php esc_html_e( 'Name', 'axiachat-ai' ); ?></label>
                    <input type="text" class="form-control form-control-sm wh-param-name" placeholder="motivo" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><?php esc_html_e( 'Type', 'axiachat-ai' ); ?></label>
                    <select class="form-select form-select-sm wh-param-type">
                        <option value="string">string</option>
                        <option value="integer">integer</option>
                        <option value="number">number</option>
                        <option value="boolean">boolean</option>
                        <option value="enum">enum</option>
                    </select>
                </div>
                <div class="col-md-2 wh-param-enum-col" style="display:none;">
                    <label class="form-label small"><?php esc_html_e( 'Values', 'axiachat-ai' ); ?></label>
                    <input type="text" class="form-control form-control-sm wh-param-values" placeholder="alta, media, baja">
                </div>
                <div class="col">
                    <label class="form-label small"><?php esc_html_e( 'AI Description', 'axiachat-ai' ); ?></label>
                    <input type="text" class="form-control form-control-sm wh-param-desc" placeholder="<?php esc_attr_e( 'Reason for escalation', 'axiachat-ai' ); ?>">
                </div>
                <div class="col-auto">
                    <div class="form-check">
                        <input class="form-check-input wh-param-required" type="checkbox" checked>
                        <label class="form-check-label small"><?php esc_html_e( 'Req', 'axiachat-ai' ); ?></label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger wh-param-remove" title="<?php esc_attr_e( 'Remove', 'axiachat-ai' ); ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
    <?php
}
