<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Usage & Moderation tab markup for AI Chat settings.
 * Depends on helper functions like aichat_get_setting() already loaded.
 */
?>
<div class="tab-pane" id="aichat-tab-usage" role="tabpanel" aria-labelledby="aichat-tab-link-usage" aria-hidden="true">
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white d-flex align-items-center">
                    <i class="bi bi-speedometer2 me-2"></i><strong><?php echo esc_html__( 'Usage (Limits)', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <?php $aichat_logging_on = (bool) aichat_get_setting( 'aichat_logging_enabled' ); ?>
                    <?php if ( ! $aichat_logging_on ) : ?>
                        <div class="alert alert-warning p-2 py-2 mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo esc_html__( 'Conversation logging must be enabled for limits to work.', 'axiachat-ai' ); ?></div>
                    <?php endif; ?>
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_usage_limits_enabled" value="0" />
                        <label for="aichat_usage_limits_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_usage_limits_enabled" name="aichat_usage_limits_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_usage_limits_enabled' ), 1 ); ?> />
                            <span><?php echo esc_html__( 'Enable usage limits', 'axiachat-ai' ); ?></span>
                        </label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="aichat_usage_max_daily_total" class="form-label fw-semibold"><?php echo esc_html__( 'Max messages per day', 'axiachat-ai' ); ?></label>
                            <input type="number" min="0" class="form-control" id="aichat_usage_max_daily_total" name="aichat_usage_max_daily_total" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_max_daily_total' ) ); ?>" />
                            <div class="form-text"><?php echo esc_html__( '0 = Unlimited', 'axiachat-ai' ); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="aichat_usage_max_daily_per_user" class="form-label fw-semibold"><?php echo esc_html__( 'Max messages per user/day', 'axiachat-ai' ); ?></label>
                            <input type="number" min="0" class="form-control" id="aichat_usage_max_daily_per_user" name="aichat_usage_max_daily_per_user" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_max_daily_per_user' ) ); ?>" />
                            <div class="form-text"><?php echo esc_html__( 'Guests tracked by IP. 0 = Unlimited', 'axiachat-ai' ); ?></div>
                        </div>
                    </div>
                    <hr />
                    <div class="mb-3">
                        <label for="aichat_usage_per_user_message" class="form-label fw-semibold"><?php echo esc_html__( 'Message when user limit reached', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="aichat_usage_per_user_message" name="aichat_usage_per_user_message" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_per_user_message' ) ); ?>" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="aichat_usage_daily_total_behavior"><?php echo esc_html__( 'Daily total limit behavior', 'axiachat-ai' ); ?></label>
                        <?php $aichat_beh = get_option( 'aichat_usage_daily_total_behavior', 'disabled' ); ?>
                        <select class="form-select" id="aichat_usage_daily_total_behavior" name="aichat_usage_daily_total_behavior">
                            <option value="disabled" <?php selected( $aichat_beh, 'disabled' ); ?>><?php echo esc_html__( 'Show widget disabled with message', 'axiachat-ai' ); ?></option>
                            <option value="hide" <?php selected( $aichat_beh, 'hide' ); ?>><?php echo esc_html__( 'Hide widget completely', 'axiachat-ai' ); ?></option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label for="aichat_usage_daily_total_message" class="form-label fw-semibold"><?php echo esc_html__( 'Daily total limit message', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="aichat_usage_daily_total_message" name="aichat_usage_daily_total_message" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_daily_total_message' ) ); ?>" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-warning d-flex align-items-center">
                    <i class="bi bi-shield-exclamation me-2"></i><strong><?php echo esc_html__( 'Moderation', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-3">
                        <label for="aichat_moderation_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_moderation_enabled" name="aichat_moderation_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_moderation_enabled' ), 1 ); ?> />
                            <span><?php echo esc_html__( 'Enable moderation layer', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0"><?php echo esc_html__( 'Checks IP/words and optionally external API before sending to AI.', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="aichat-checkbox-row mb-3">
                        <label for="aichat_moderation_external_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_moderation_external_enabled" name="aichat_moderation_external_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_moderation_external_enabled' ), 1 ); ?> />
                            <span><?php echo esc_html__( 'External moderation (OpenAI)', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0"><?php echo esc_html__( 'Requires OpenAI API key (omni-moderation-latest).', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="aichat_moderation_rejection_message" class="form-label fw-semibold"><?php echo esc_html__( 'Rejection message', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="aichat_moderation_rejection_message" name="aichat_moderation_rejection_message" value="<?php echo esc_attr( aichat_get_setting( 'aichat_moderation_rejection_message' ) ); ?>" />
                    </div>
                    <div class="mb-3">
                        <label for="aichat_moderation_banned_ips" class="form-label fw-semibold"><?php echo esc_html__( 'Blocked IPs', 'axiachat-ai' ); ?></label>
                        <textarea class="form-control" id="aichat_moderation_banned_ips" name="aichat_moderation_banned_ips" rows="4"><?php echo esc_textarea( get_option( 'aichat_moderation_banned_ips', '' ) ); ?></textarea>
                        <div class="form-text"><?php echo esc_html__( 'One per line. Supports CIDR.', 'axiachat-ai' ); ?></div>
                    </div>
                    <div>
                        <label for="aichat_moderation_banned_words" class="form-label fw-semibold d-block"><?php echo esc_html__( 'Banned words', 'axiachat-ai' ); ?></label>
                        <div class="form-check mb-2">
                            <label class="aichat-checkbox-label" for="aichat_moderation_use_default_words">
                                <input type="checkbox" id="aichat_moderation_use_default_words" name="aichat_moderation_use_default_words" value="1" <?php checked( (int) aichat_get_setting( 'aichat_moderation_use_default_words' ), 1 ); ?> />
                                <span><?php echo esc_html__( 'Include base list in English', 'axiachat-ai' ); ?></span>
                            </label>
                        </div>
                        <textarea class="form-control" id="aichat_moderation_banned_words" name="aichat_moderation_banned_words" rows="5"><?php echo esc_textarea( get_option( 'aichat_moderation_banned_words', '' ) ); ?></textarea>
                        <div class="form-text"><?php echo esc_html__( 'One per line. Regex allowed if wrapped in /.', 'axiachat-ai' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
