<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * General tab markup for AI Chat settings.
 *
 * Assumes variables from parent scope: $openai_key, $claude_key, $gemini_key,
 * $bots, $global_on, $global_slug.
 */
?>
<div class="tab-pane active" id="aichat-tab-general" role="tabpanel" aria-labelledby="aichat-tab-link-general" aria-hidden="false">
    <div class="row g-4">
<?php
/**
 * Permite que un add-on (ej: Agency Connector) oculte el card de API keys.
 *
 * @since 1.3.0
 * @param bool $show True para mostrar, false para ocultar.
 */
$aichat_show_api_keys_card = apply_filters( 'aichat_settings_show_api_keys', true );
if ( $aichat_show_api_keys_card ) :
?>
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-key-fill me-2"></i><strong><?php echo esc_html__( 'API Keys', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="aichat_openai_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'OpenAI API Key', 'axiachat-ai' ); ?></label>
                        <div class="input-group">
                            <input type="password" autocomplete="off" class="form-control" id="aichat_openai_api_key" name="aichat_openai_api_key" value="<?php echo esc_attr( $openai_key ); ?>" />
                            <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_openai_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="form-text"><?php echo esc_html__( 'API key to use OpenAI models.', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="aichat_claude_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'Claude (Anthropic) API Key', 'axiachat-ai' ); ?></label>
                        <div class="input-group">
                            <input type="password" autocomplete="off" class="form-control" id="aichat_claude_api_key" name="aichat_claude_api_key" value="<?php echo esc_attr( $claude_key ); ?>" />
                            <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_claude_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="form-text"><?php echo esc_html__( 'API key to use Anthropic (Claude) models.', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="mb-0">
                        <label for="aichat_gemini_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'Google Gemini API Key', 'axiachat-ai' ); ?></label>
                        <div class="input-group">
                            <input type="password" autocomplete="off" class="form-control" id="aichat_gemini_api_key" name="aichat_gemini_api_key" value="<?php echo esc_attr( $gemini_key ); ?>" />
                            <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_gemini_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="form-text"><?php echo esc_html__( 'API key to use Google Gemini models. Get your key at aistudio.google.com/apikey', 'axiachat-ai' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
<?php endif; // aichat_settings_show_api_keys ?>
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-secondary text-white d-flex align-items-center">
                    <i class="bi bi-robot me-2"></i><strong><?php echo esc_html__( 'Global Bot & Logging', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_global_bot_enabled" value="0" />
                        <label for="aichat_global_bot_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_global_bot_enabled" name="aichat_global_bot_enabled" value="1" <?php checked( $global_on ); ?> />
                            <span><?php echo esc_html__( 'Enable global floating bot', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0"><?php echo esc_html__( 'Shortcode [aichat bot="..."] on a page suppresses the global bot there.', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="aichat_global_bot_slug" class="form-label fw-semibold"><?php echo esc_html__( 'Global Bot', 'axiachat-ai' ); ?></label>
                        <?php if ( empty( $bots ) ) : ?>
                            <select id="aichat_global_bot_slug" class="form-select" disabled name="aichat_global_bot_slug"><option><?php echo esc_html__( 'No bots defined yet', 'axiachat-ai' ); ?></option></select>
                            <div class="form-text">
                                <?php
                                /* translators: %s: URL to AI Chat Bots settings page. */
                                printf( wp_kses_post( __( 'Create one in <a href="%s">AI Chat → Bots</a>.', 'axiachat-ai' ) ), esc_url( admin_url( 'admin.php?page=aichat-bots-settings' ) ) );
                                ?>
                            </div>
                        <?php else : ?>
                            <select id="aichat_global_bot_slug" class="form-select" name="aichat_global_bot_slug">
                                <?php foreach ( $bots as $aichat_bot ) : ?>
                                    <option value="<?php echo esc_attr( $aichat_bot['slug'] ); ?>" <?php selected( $global_slug, $aichat_bot['slug'] ); ?>><?php echo esc_html( $aichat_bot['name'] . ' (' . $aichat_bot['slug'] . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?php echo esc_html__( 'Bot used when global floating bot is active.', 'axiachat-ai' ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_logging_enabled" value="0" />
                        <label for="aichat_logging_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_logging_enabled" name="aichat_logging_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_logging_enabled' ), 1 ); ?> />
                            <span><?php echo esc_html__( 'Conversation logging', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0"><?php echo esc_html__( 'Disable to stop saving new messages (existing records remain).', 'axiachat-ai' ); ?></div>
                    </div>
                    <?php
                    $aichat_test_url = function_exists( 'aichat_get_test_page_url' ) ? aichat_get_test_page_url() : false;
                    if ( $aichat_test_url ) :
                    ?>
                    <div class="mb-0">
                        <label class="form-label fw-semibold"><i class="bi bi-eye me-1"></i><?php echo esc_html__( 'Test Page', 'axiachat-ai' ); ?></label>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?php echo esc_url( $aichat_test_url ); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i><?php echo esc_html__( 'Open test page', 'axiachat-ai' ); ?>
                            </a>
                        </div>
                        <div class="form-text"><?php echo esc_html__( 'Preview your bot on a real page with your theme styles — without exposing it to visitors.', 'axiachat-ai' ); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white d-flex align-items-center">
                    <i class="bi bi-globe me-2"></i><strong><?php echo esc_html__( 'Embed (External Sites)', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <?php
                    // Check if JS Embed feature is available (Starter+ plan required)
                    $aichat_can_use_js_embed = function_exists( 'aichat_can_use_js_embed' ) ? aichat_can_use_js_embed() : true;
                    
                    if ( ! $aichat_can_use_js_embed ) :
                        // Show upgrade message for free users
                        $aichat_upgrade_url = function_exists( 'aichat_fs' ) ? aichat_fs()->get_upgrade_url() : admin_url( 'admin.php?page=axiachat-ai-pricing' );
                    ?>
                        <div class="alert alert-warning d-flex align-items-center mb-0" role="alert">
                            <i class="bi bi-lock-fill me-2" style="font-size:1.25rem;"></i>
                            <div>
                                <strong><?php echo esc_html__( 'Starter Plan Required', 'axiachat-ai' ); ?></strong><br />
                                <?php echo esc_html__( 'JavaScript Embed allows you to embed the chat widget on external websites using a simple script tag. This feature is available on Starter plan and above.', 'axiachat-ai' ); ?>
                                <br /><br />
                                <a href="<?php echo esc_url( $aichat_upgrade_url ); ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-arrow-up-circle me-1"></i><?php echo esc_html__( 'Upgrade to Starter', 'axiachat-ai' ); ?>
                                </a>
                            </div>
                        </div>
                    <?php else : ?>
                    <?php $aichat_embed_origins_raw = (string) get_option( 'aichat_embed_allowed_origins', '' ); ?>
                    <p class="mb-3 text-muted" style="font-size:13px;">
                        <?php echo esc_html__( 'List the allowed external site origins (protocol + domain) that can embed the chat via the script loader. One per line. Example: https://example.com', 'axiachat-ai' ); ?>
                    </p>
                    <div class="mb-3">
                        <label for="aichat_embed_allowed_origins" class="form-label fw-semibold"><?php echo esc_html__( 'Allowed Origins', 'axiachat-ai' ); ?></label>
                        <textarea id="aichat_embed_allowed_origins" name="aichat_embed_allowed_origins" class="form-control" rows="5" placeholder="https://site1.com
https://sub.site2.net"><?php echo esc_textarea( $aichat_embed_origins_raw ); ?></textarea>
                        <div class="form-text"><?php echo esc_html__( 'Leave empty to disallow all external script embeds (iframe method still works).', 'axiachat-ai' ); ?></div>
                    </div>
                    <div class="mb-3 small text-secondary">
                        <?php echo esc_html__( 'Security: Each external request is validated against this list. Use full origin, no trailing slash.', 'axiachat-ai' ); ?>
                    </div>
                    <div class="mb-0" style="background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:12.5px;line-height:1.5;">
<?php $aichat_example_bot = $global_slug ? $global_slug : ( ! empty( $bots ) ? $bots[0]['slug'] : 'default' ); ?>
<div style="font-weight:600;margin-bottom:6px;color:#93c5fd;">
    <?php echo esc_html__( 'Example snippet to paste on an external page', 'axiachat-ai' ); ?>:
</div>
&lt;!-- AI Chat Widget --&gt;<br />
&lt;div id=&quot;aichat-embed&quot; data-bot=&quot;<?php echo esc_html( $aichat_example_bot ); ?>&quot;&gt;&lt;/div&gt;<br />
&lt;script async src=&quot;<?php echo esc_url( AICHAT_PLUGIN_URL . 'assets/js/aichat-embed-loader.js' ); ?>&quot; data-ajax-url=&quot;<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>&quot; data-nonce-endpoint=&quot;<?php echo esc_url( add_query_arg( 'aichat_embed_nonce', '1', home_url( '/' ) ) ); ?>&quot;&gt;&lt;/script&gt;<br />
&lt;!-- /AI Chat Widget --&gt;
<div style="margin-top:6px;font-size:11px;color:#cbd5e1;">
    <?php echo wp_kses_post( __( 'Make sure the external site origin (e.g. <code>https://example.com</code>) is present in the list above, otherwise the embed will be blocked.', 'axiachat-ai' ) ); ?>
</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
