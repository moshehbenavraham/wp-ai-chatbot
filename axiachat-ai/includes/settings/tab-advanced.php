<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Advanced tab markup for AI Chat settings.
 */
?>
<div class="tab-pane" id="aichat-tab-advanced" role="tabpanel" aria-labelledby="aichat-tab-link-advanced" aria-hidden="true">
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
        <div>
            <strong><?php echo esc_html__( 'Advanced Settings — Handle with Care', 'axiachat-ai' ); ?></strong>
            <p class="mb-0 mt-2"><?php echo esc_html__( 'These settings control critical bot behavior and security policies. Only modify these values if you understand their impact. Incorrect configuration may compromise bot security or functionality.', 'axiachat-ai' ); ?></p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12">
            <div class="card card100 shadow-sm h-100">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="bi bi-shield-lock-fill me-2"></i><strong><?php echo esc_html__( 'Security & Privacy Policy', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-4">
                        <input type="hidden" name="aichat_datetime_injection_enabled" value="0" />
                        <label for="aichat_datetime_injection_enabled" class="aichat-checkbox-label">
                            <input
                                type="checkbox"
                                id="aichat_datetime_injection_enabled"
                                name="aichat_datetime_injection_enabled"
                                value="1"
                                <?php checked( (int) get_option( 'aichat_datetime_injection_enabled', 1 ), 1 ); ?>
                            />
                            <span><?php echo esc_html__( 'Let the chatbot know the current site date/time', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0 mt-1">
                            <i class="bi bi-clock-history me-1"></i>
                            <?php echo esc_html__( 'When enabled, the system prompt includes the WordPress timezone date/time before every conversation. Disable this to keep the policy static.', 'axiachat-ai' ); ?>
                        </div>
                    </div>
                    <div class="aichat-checkbox-row mb-4">
                        <input type="hidden" name="aichat_inject_user_context_enabled" value="0" />
                        <label for="aichat_inject_user_context_enabled" class="aichat-checkbox-label">
                            <input
                                type="checkbox"
                                id="aichat_inject_user_context_enabled"
                                name="aichat_inject_user_context_enabled"
                                value="1"
                                <?php checked( (int) get_option( 'aichat_inject_user_context_enabled', 0 ), 1 ); ?>
                            />
                            <span><?php echo esc_html__( 'Let the chatbot know if the visitor is logged in', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0 mt-1">
                            <i class="bi bi-person-badge me-1"></i>
                            <?php echo esc_html__( 'When enabled, the prompt includes whether the visitor is a logged-in WordPress user and, if so, their user ID. Default is disabled.', 'axiachat-ai' ); ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="aichat_security_policy" class="form-label fw-semibold">
                            <?php echo esc_html__( 'Bot Security Policy (System Prompt Prefix)', 'axiachat-ai' ); ?>
                        </label>
                        <textarea
                            name="aichat_security_policy"
                            id="aichat_security_policy"
                            class="form-control font-monospace"
                            rows="5"
                            style="font-size: 0.9em; line-height: 1.6;"
                        ><?php echo esc_textarea( aichat_get_setting( 'aichat_security_policy' ) ); ?></textarea>
                        <p class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php echo esc_html__( 'This policy is automatically prepended to all bot conversations as part of the system prompt. It enforces security rules like preventing API key disclosure, blocking prompt injection attacks, and defining how the bot should respond to sensitive queries.', 'axiachat-ai' ); ?>
                        </p>
                        <p class="form-text text-danger mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong><?php echo esc_html__( 'Warning:', 'axiachat-ai' ); ?></strong>
                            <?php echo esc_html__( 'Removing or weakening this policy may expose internal system details, API credentials, or allow malicious prompt injection. Only modify if you have a specific security requirement and understand the implications.', 'axiachat-ai' ); ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        id="aichat-reset-security-policy"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        <?php echo esc_html__( 'Reset to Default Policy', 'axiachat-ai' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card card100 shadow-sm h-100 mt-4">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="bi bi-bug-fill me-2"></i><strong><?php echo esc_html__( 'Debug & System Logs', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_debug_enabled" value="0" />
                        <label for="aichat_debug_enabled" class="aichat-checkbox-label">
                            <input
                                type="checkbox"
                                id="aichat_debug_enabled"
                                name="aichat_debug_enabled"
                                value="1"
                                <?php checked( (int) get_option( 'aichat_debug_enabled', 0 ), 1 ); ?>
                            />
                            <span><?php echo esc_html__( 'Enable debug logging from settings (OR with AICHAT_DEBUG)', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0 mt-1">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php echo esc_html__( 'When enabled, the plugin writes diagnostic messages even if the AICHAT_DEBUG constant is false. If the constant is true, logging is always active regardless of this option.', 'axiachat-ai' ); ?>
                        </div>
                    </div>
                    <p class="form-text mb-2">
                        <i class="bi bi-file-earmark-text me-1"></i>
                        <?php
                        printf(
                            /* translators: %s: log directory path */
                            esc_html__( 'Below you can inspect the last 500 lines of the general log (axiachat-ai.log) and the AI-specific log (debug_ia.log) stored under %s.', 'axiachat-ai' ),
                            '<code>' . esc_html( str_replace( ABSPATH, '', aichat_get_log_dir() ) ) . '</code>'
                        );
                        ?>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-2" for="aichat_debug_log_preview">
                            <?php echo esc_html__( 'axiachat-ai.log (tail 500 lines)', 'axiachat-ai' ); ?>
                        </label>
                        <div class="mb-2 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="aichat-debug-log-refresh" data-log-type="general">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                <?php echo esc_html__( 'View / Refresh', 'axiachat-ai' ); ?>
                            </button>
                            <a
                                href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aichat_download_log&log_type=general' ), 'aichat_settings', 'nonce' ) ); ?>"
                                class="btn btn-sm btn-outline-primary"
                                download
                            >
                                <i class="bi bi-download me-1"></i>
                                <?php echo esc_html__( 'Download', 'axiachat-ai' ); ?>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger aichat-clear-log-btn" data-log-type="general">
                                <i class="bi bi-trash me-1"></i>
                                <?php echo esc_html__( 'Clear log', 'axiachat-ai' ); ?>
                            </button>
                        </div>
                        <textarea
                            id="aichat_debug_log_preview"
                            class="form-control font-monospace d-none"
                            rows="6"
                            readonly
                            style="font-size: 0.85em; white-space: pre;"
                        ></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold mb-2" for="aichat_debug_ai_log_preview">
                            <?php echo esc_html__( 'AI debug_ia.log (tail 500 lines)', 'axiachat-ai' ); ?>
                        </label>
                        <div class="mb-2 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="aichat-debug-ai-log-refresh" data-log-type="ai">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                <?php echo esc_html__( 'View / Refresh', 'axiachat-ai' ); ?>
                            </button>
                            <a
                                href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aichat_download_log&log_type=ai' ), 'aichat_settings', 'nonce' ) ); ?>"
                                class="btn btn-sm btn-outline-primary"
                                download
                            >
                                <i class="bi bi-download me-1"></i>
                                <?php echo esc_html__( 'Download', 'axiachat-ai' ); ?>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger aichat-clear-log-btn" data-log-type="ai">
                                <i class="bi bi-trash me-1"></i>
                                <?php echo esc_html__( 'Clear log', 'axiachat-ai' ); ?>
                            </button>
                        </div>
                        <textarea
                            id="aichat_debug_ai_log_preview"
                            class="form-control font-monospace d-none"
                            rows="6"
                            readonly
                            style="font-size: 0.85em; white-space: pre;"
                        ></textarea>
                        <p class="form-text mt-2 mb-0">
                            <i class="bi bi-shield-check me-1"></i>
                            <?php echo esc_html__( 'Log files are stored in a protected directory under wp-content/uploads/axiachat-ai/. Make sure debug logging is enabled above to start writing logs.', 'axiachat-ai' ); ?>
                        </p>
                    </div>
                    <hr class="my-4">
                    <div class="mb-0">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-file-earmark-zip me-2"></i><?php echo esc_html__( 'Export Diagnostics (Remote Support)', 'axiachat-ai' ); ?>
                        </h6>
                        <p class="form-text mb-3">
                            <?php echo esc_html__( 'Download a ZIP file containing both log files and a full environment report (PHP, WordPress, active plugins, bot configuration, etc.). No API keys or passwords are included. Send this file to the support team to speed up troubleshooting.', 'axiachat-ai' ); ?>
                        </p>
                        <a
                            href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=aichat_export_diagnostics' ), 'aichat_settings', 'nonce' ) ); ?>"
                            class="btn btn-sm btn-outline-success"
                            download
                        >
                            <i class="bi bi-file-earmark-zip me-1"></i>
                            <?php echo esc_html__( 'Export Diagnostics ZIP', 'axiachat-ai' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
