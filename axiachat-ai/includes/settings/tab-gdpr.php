<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GDPR tab markup for AI Chat settings.
 */
?>
<div class="tab-pane" id="aichat-tab-gdpr" role="tabpanel" aria-labelledby="aichat-tab-link-gdpr" aria-hidden="true">
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="bi bi-shield-lock-fill me-2"></i><strong><?php echo esc_html__( 'GDPR Consent', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_gdpr_consent_enabled" value="0" />
                        <label for="aichat_gdpr_consent_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_gdpr_consent_enabled" name="aichat_gdpr_consent_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_gdpr_consent_enabled' ), 1 ); ?> />
                            <span><?php echo esc_html__( 'Enable consent gate', 'axiachat-ai' ); ?></span>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label for="aichat_gdpr_text" class="form-label fw-semibold"><?php echo esc_html__( 'Consent text', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="aichat_gdpr_text" name="aichat_gdpr_text" value="<?php echo esc_attr( aichat_get_setting( 'aichat_gdpr_text' ) ); ?>" />
                        <div class="form-text"><?php echo esc_html__( 'Shown above the accept button. Basic HTML allowed.', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="mb-0">
                        <label for="aichat_gdpr_button" class="form-label fw-semibold"><?php echo esc_html__( 'Button label', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="aichat_gdpr_button" name="aichat_gdpr_button" value="<?php echo esc_attr( aichat_get_setting( 'aichat_gdpr_button' ) ); ?>" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
