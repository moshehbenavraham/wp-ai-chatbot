<?php
/**
 * Training – Instructions Sub-page
 *
 * Two modes: Manual (textarea + templates) and Guided (wizard-style type/tone/length).
 *
 * @package AxiaChat
 * @since   3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load easy-config data for the guided mode.
if ( ! function_exists( 'aichat_easycfg_get_chatbot_types' ) ) {
    require_once __DIR__ . '/easy-config-data.php';
}

function aichat_training_instructions_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $bot_slug = isset( $_GET['bot'] ) ? sanitize_title( wp_unslash( $_GET['bot'] ) ) : '';

    $bots_table = $wpdb->prefix . 'aichat_bots';

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

    // Instruction templates (for manual mode)
    $instruction_templates = function_exists( 'aichat_get_chatbot_templates' ) ? aichat_get_chatbot_templates() : [];

    // Guided mode data
    $chatbot_types    = aichat_easycfg_get_chatbot_types();
    $voice_tones      = aichat_easycfg_get_voice_tones();
    $response_lengths = aichat_easycfg_get_response_lengths();

    $nonce = wp_create_nonce( 'aichat_training' );

    $back_url = admin_url( 'admin.php?page=aichat-training&bot=' . $bot_slug );
    ?>
    <div class="wrap aichat-admin">
        <div class="aichat-training-instructions-wrap">

            <a href="<?php echo esc_url( $back_url ); ?>" class="aichat-training-back">
                <i class="bi bi-arrow-left"></i> <?php esc_html_e( 'Back to Training', 'axiachat-ai' ); ?>
            </a>

            <h1><i class="bi bi-pencil-square me-2"></i><?php esc_html_e( 'Instructions', 'axiachat-ai' ); ?>
                <small class="text-muted" style="font-size: 14px; font-weight: 400;">&mdash; <?php echo esc_html( $bot['name'] ); ?></small>
            </h1>
            <p class="aichat-training-subtitle">
                <?php esc_html_e( 'Define how your bot behaves, its personality, and the rules it follows. These instructions are sent as the system prompt.', 'axiachat-ai' ); ?>
            </p>

            <?php if ( count( $all_bots ) > 1 ) : ?>
            <div class="aichat-training-bot-selector mb-3">
                <label for="aichat-instr-bot-select">
                    <i class="bi bi-robot me-1"></i><?php esc_html_e( 'Bot:', 'axiachat-ai' ); ?>
                </label>
                <select id="aichat-instr-bot-select" class="form-select d-inline-block w-auto">
                    <?php foreach ( $all_bots as $b ) : ?>
                        <option value="<?php echo esc_attr( $b['slug'] ); ?>" <?php selected( $b['slug'], $bot_slug ); ?>>
                            <?php echo esc_html( $b['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Mode Toggle -->
            <div class="aichat-instr-mode-toggle">
                <button type="button" class="aichat-instr-mode-btn active" data-mode="manual">
                    <i class="bi bi-pencil"></i> <?php esc_html_e( 'Manual', 'axiachat-ai' ); ?>
                </button>
                <button type="button" class="aichat-instr-mode-btn" data-mode="guided">
                    <i class="bi bi-magic"></i> <?php esc_html_e( 'Guided', 'axiachat-ai' ); ?>
                </button>
            </div>

            <!-- ==================== MANUAL MODE ==================== -->
            <div class="aichat-instr-manual" id="aichat-instr-manual">

                <div class="mb-3">
                    <label class="form-label fw-semibold mb-2">
                        <i class="bi bi-file-earmark-text me-1"></i><?php esc_html_e( 'Load a predefined template (optional)', 'axiachat-ai' ); ?>
                    </label>
                    <div class="aichat-tpl-simple" id="aichat-instr-tpl-panel">
                        <div class="aichat-tpl-box">
                            <button type="button" class="aichat-tpl-arrow up" aria-label="<?php esc_attr_e( 'Scroll up', 'axiachat-ai' ); ?>">▲</button>
                            <div class="aichat-tpl-list" id="aichat-instr-tpl-list" role="listbox" aria-label="<?php esc_attr_e( 'Instruction templates', 'axiachat-ai' ); ?>">
                                <?php if ( ! empty( $instruction_templates ) ) : ?>
                                    <?php foreach ( $instruction_templates as $tpl_id => $tpl ) : ?>
                                        <div class="aichat-tpl-item" data-id="<?php echo esc_attr( $tpl_id ); ?>" title="<?php echo esc_attr( $tpl['description'] ?? '' ); ?>">
                                            <?php echo esc_html( $tpl['name'] ?? $tpl_id ); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="aichat-tpl-empty"><?php esc_html_e( 'No templates available', 'axiachat-ai' ); ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="aichat-tpl-arrow down" aria-label="<?php esc_attr_e( 'Scroll down', 'axiachat-ai' ); ?>">▼</button>
                        </div>
                        <div class="aichat-tpl-side">
                            <div class="aichat-tpl-desc" id="aichat-instr-tpl-desc" aria-live="polite"></div>
                            <button type="button" class="button button-secondary aichat-tpl-load mt-2" id="aichat-instr-tpl-load" disabled>
                                <i class="bi bi-download"></i> <?php esc_html_e( 'Load', 'axiachat-ai' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <label class="form-label fw-semibold" for="aichat-instr-textarea">
                    <?php esc_html_e( 'Instructions / System Prompt', 'axiachat-ai' ); ?>
                </label>
                <textarea id="aichat-instr-textarea" class="aichat-instr-textarea"><?php echo esc_textarea( $bot['instructions'] ?? '' ); ?></textarea>

                <div class="mt-3">
                    <button type="button" class="button button-primary" id="aichat-instr-save-manual">
                        <i class="bi bi-check-lg me-1"></i><?php esc_html_e( 'Save Instructions', 'axiachat-ai' ); ?>
                    </button>
                    <span class="ms-2 text-success" id="aichat-instr-manual-saved" style="display:none;">
                        <i class="bi bi-check-circle"></i> <?php esc_html_e( 'Saved', 'axiachat-ai' ); ?>
                    </span>
                </div>
            </div>

            <!-- ==================== GUIDED MODE ==================== -->
            <div class="aichat-instr-guided" id="aichat-instr-guided">

                <div class="aichat-instr-guided-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php esc_html_e( 'Applying guided mode will overwrite your current manual instructions entirely.', 'axiachat-ai' ); ?>
                </div>

                <!-- Step 1: Chatbot Type -->
                <h3 class="mb-3" style="font-size:16px; font-weight:700;">
                    <span style="color:#667eea;">1.</span> <?php esc_html_e( 'What type of assistant is this?', 'axiachat-ai' ); ?>
                </h3>
                <div class="aichat-ec-type-grid" id="aichat-guided-type-grid">
                    <!-- Populated by JS -->
                </div>

                <!-- Step 2: Voice Tone -->
                <h3 class="mb-3" style="font-size:16px; font-weight:700;">
                    <span style="color:#667eea;">2.</span> <?php esc_html_e( 'What tone should it use?', 'axiachat-ai' ); ?>
                </h3>
                <div class="aichat-instr-tone-options" id="aichat-guided-tone-grid">
                    <!-- Populated by JS -->
                </div>

                <!-- Step 3: Response Length -->
                <h3 class="mb-3" style="font-size:16px; font-weight:700;">
                    <span style="color:#667eea;">3.</span> <?php esc_html_e( 'How long should responses be?', 'axiachat-ai' ); ?>
                </h3>
                <div class="aichat-instr-length-wrap">
                    <input type="range" id="aichat-guided-length" min="1" max="4" step="1" value="3">
                    <div class="aichat-instr-length-labels">
                        <span><?php esc_html_e( 'Minimal', 'axiachat-ai' ); ?></span>
                        <span><?php esc_html_e( 'Short', 'axiachat-ai' ); ?></span>
                        <span><?php esc_html_e( 'Medium', 'axiachat-ai' ); ?></span>
                        <span><?php esc_html_e( 'Detailed', 'axiachat-ai' ); ?></span>
                    </div>
                </div>

                <!-- Step 4: Chat Guidelines -->
                <h3 class="mb-3" style="font-size:16px; font-weight:700;">
                    <span style="color:#667eea;">4.</span> 📋 <?php esc_html_e( 'Chat Guidelines', 'axiachat-ai' ); ?>
                </h3>
                <p class="small text-muted mb-2"><?php esc_html_e( 'Specific rules or behaviours your bot should follow. They will be appended to the instructions.', 'axiachat-ai' ); ?></p>
                <div class="aichat-guidelines-section">
                    <div class="aichat-guidelines-list" id="aichat-guided-guidelines-list">
                        <!-- Populated by JS from selected chatbot type -->
                    </div>
                    <button type="button" class="aichat-add-guideline" id="aichat-guided-add-guideline">
                        <i class="bi bi-plus-circle"></i> <?php esc_html_e( 'Add Guideline', 'axiachat-ai' ); ?>
                    </button>
                </div>

                <!-- Preview -->
                <h3 class="mb-2" style="font-size:14px; font-weight:600; color:#64748b;">
                    <i class="bi bi-eye me-1"></i><?php esc_html_e( 'Preview of generated instructions:', 'axiachat-ai' ); ?>
                </h3>
                <div class="aichat-instr-preview" id="aichat-guided-preview">
                    <?php esc_html_e( 'Select a chatbot type above to see the preview.', 'axiachat-ai' ); ?>
                </div>

                <div class="mt-3">
                    <button type="button" class="button button-primary" id="aichat-instr-save-guided">
                        <i class="bi bi-check-lg me-1"></i><?php esc_html_e( 'Apply & Save Instructions', 'axiachat-ai' ); ?>
                    </button>
                    <span class="ms-2 text-success" id="aichat-instr-guided-saved" style="display:none;">
                        <i class="bi bi-check-circle"></i> <?php esc_html_e( 'Saved', 'axiachat-ai' ); ?>
                    </span>
                </div>
            </div>

            <!-- Hidden data for JS -->
            <div id="aichat-instr-data"
                 data-bot-id="<?php echo esc_attr( $bot['id'] ); ?>"
                 data-bot-slug="<?php echo esc_attr( $bot_slug ); ?>"
                 data-nonce="<?php echo esc_attr( $nonce ); ?>"
                 data-chatbot-types="<?php echo esc_attr( wp_json_encode( $chatbot_types ) ); ?>"
                 data-voice-tones="<?php echo esc_attr( wp_json_encode( $voice_tones ) ); ?>"
                 data-response-lengths="<?php echo esc_attr( wp_json_encode( $response_lengths ) ); ?>"
                 data-templates="<?php echo esc_attr( wp_json_encode( $instruction_templates ) ); ?>"
                 style="display:none;"></div>

        </div>
    </div>
    <?php
}
