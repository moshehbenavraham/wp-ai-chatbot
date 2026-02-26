<?php
/**
 * Training Hub – Main page
 *
 * Shows bot selector (if >1 bot) and two clickable cards:
 * Instructions & Context.
 *
 * @package AxiaChat
 * @since   3.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aichat_training_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;

    // Fetch all bots
    $bots_table = $wpdb->prefix . 'aichat_bots';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $bots = $wpdb->get_results( "SELECT id, name, slug FROM {$bots_table} WHERE is_active = 1 ORDER BY id ASC", ARRAY_A );
    if ( ! $bots ) {
        $bots = [];
    }

    $show_bot_selector = count( $bots ) > 1;

    // Current bot (from query string or first available)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_slug = isset( $_GET['bot'] ) ? sanitize_title( wp_unslash( $_GET['bot'] ) ) : '';
    if ( ! $current_slug && ! empty( $bots ) ) {
        $current_slug = $bots[0]['slug'];
    }

    $instructions_url = admin_url( 'admin.php?page=aichat-training-instructions&bot=' . $current_slug );
    $context_url      = admin_url( 'admin.php?page=aichat-training-context&bot=' . $current_slug );
    ?>
    <div class="wrap aichat-admin">
        <div class="aichat-training-wrap">

            <h1><i class="bi bi-mortarboard me-2"></i><?php esc_html_e( 'Bot Training', 'axiachat-ai' ); ?></h1>
            <p class="aichat-training-subtitle">
                <?php esc_html_e( 'Train your bot by defining its behavior (Instructions) and providing it with knowledge (Context).', 'axiachat-ai' ); ?>
            </p>

            <?php if ( $show_bot_selector ) : ?>
            <div class="aichat-training-bot-selector" id="aichat-training-bot-selector">
                <label for="aichat-training-bot-select">
                    <i class="bi bi-robot me-1"></i><?php esc_html_e( 'Select Bot:', 'axiachat-ai' ); ?>
                </label>
                <select id="aichat-training-bot-select" class="form-select d-inline-block w-auto">
                    <?php foreach ( $bots as $bot ) : ?>
                        <option value="<?php echo esc_attr( $bot['slug'] ); ?>" <?php selected( $bot['slug'], $current_slug ); ?>>
                            <?php echo esc_html( $bot['name'] ); ?> (<?php echo esc_html( $bot['slug'] ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="aichat-training-grid">

                <!-- Instructions card -->
                <a href="<?php echo esc_url( $instructions_url ); ?>" class="aichat-training-card" id="aichat-training-card-instructions">
                    <div class="aichat-training-card-icon">📝</div>
                    <div class="aichat-training-card-title"><?php esc_html_e( 'Instructions', 'axiachat-ai' ); ?></div>
                    <div class="aichat-training-card-desc">
                        <?php esc_html_e( 'Define how your bot behaves: personality, tone, rules, and guidelines. This is the system prompt that shapes every response.', 'axiachat-ai' ); ?>
                    </div>
                </a>

                <!-- Context card -->
                <a href="<?php echo esc_url( $context_url ); ?>" class="aichat-training-card" id="aichat-training-card-context">
                    <div class="aichat-training-card-icon">🧠</div>
                    <div class="aichat-training-card-title"><?php esc_html_e( 'Context (Knowledge Base)', 'axiachat-ai' ); ?></div>
                    <div class="aichat-training-card-desc">
                        <?php esc_html_e( 'Choose what your bot knows: WordPress posts, WooCommerce products, PDF/TXT documents. This is the data your bot uses to answer questions.', 'axiachat-ai' ); ?>
                    </div>
                </a>

            </div>

        </div>
    </div>
    <?php
}
