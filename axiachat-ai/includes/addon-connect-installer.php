<?php
/**
 * Andromeda Connect (WhatsApp & Telegram) installation guide.
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render installation guide page for Andromeda Connect companion plugin.
 */
function aichat_addon_connect_installer_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'axiachat-ai' ) );
    }

    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    $page_title        = esc_html__( 'Andromeda Connect – WhatsApp & Telegram Add-on', 'axiachat-ai' );
    $plugin_file       = 'andromeda-connect/andromeda-connect.php';
    $plugin_main_path  = WP_PLUGIN_DIR . '/andromeda-connect/andromeda-connect.php';
    $plugin_is_active  = function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );
    $plugin_is_present = file_exists( $plugin_main_path );
    $plugin_version    = '';

    if ( $plugin_is_present && function_exists( 'get_plugin_data' ) ) {
        $data = get_plugin_data( $plugin_main_path, false, false );
        if ( ! empty( $data['Version'] ) ) {
            $plugin_version = $data['Version'];
        }
    }

    $return_url     = admin_url( 'admin.php?page=aichat-settings#aichat-tab-addons' );
    $plugins_url    = admin_url( 'plugins.php' );
    $download_url   = 'https://github.com/estebanstifli/aichat-connect/releases/latest/download/andromeda-connect.zip';
    $github_url     = 'https://github.com/estebanstifli/aichat-connect';
    $banner_url     = plugins_url( 'assets/images/andromeda.png', dirname( __FILE__ ) );

    echo '<div class="wrap aichat-connect-guide">';
    echo '<h1 class="wp-heading-inline">' . esc_html( $page_title ) . '</h1>';

    if ( $plugin_is_active ) {
        echo '<div class="notice notice-success" style="margin-top:20px;"><p><strong>' . esc_html__( 'Andromeda Connect is already active!', 'axiachat-ai' ) . '</strong>';
        if ( $plugin_version ) {
            /* translators: %s: Detected Andromeda Connect plugin version. */
            echo ' ' . sprintf( esc_html__( '(Version %s detected)', 'axiachat-ai' ), esc_html( $plugin_version ) );
        }
        echo '</p></div>';
        echo '<p><a class="button button-primary" href="' . esc_url( $return_url ) . '">' . esc_html__( 'Return to AxiaChat Settings', 'axiachat-ai' ) . '</a></p>';
        echo '</div>';
        return;
    }

    echo '<style>
    .aichat-connect-guide .card { max-width: 900px; margin-top: 20px; }
    .aichat-connect-guide .card-body { padding: 24px; }
    .aichat-connect-banner { width: 100%; height: auto; border-radius: 8px; margin-bottom: 24px; }
    .aichat-install-steps { margin-top: 20px; }
    .aichat-install-steps ol { margin-left: 20px; }
    .aichat-install-steps li { margin-bottom: 14px; line-height: 1.6; }
    .aichat-install-steps code { background: #f0f0f1; padding: 3px 8px; border-radius: 4px; font-size: 13px; }
    .aichat-download-box { background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 6px; padding: 18px 20px; margin: 20px 0; }
    .aichat-download-box h3 { margin-top: 0; font-size: 16px; }
    .aichat-download-box .button { margin-right: 10px; margin-top: 8px; }
    </style>';

    if ( file_exists( str_replace( plugins_url( '', dirname( __FILE__ ) ), WP_PLUGIN_DIR, $banner_url ) ) ) {
        echo '<img src="' . esc_url( $banner_url ) . '" alt="' . esc_attr__( 'Andromeda Connect Banner', 'axiachat-ai' ) . '" class="aichat-connect-banner" />';
    }

    echo '<div class="card">';
    echo '<div class="card-header" style="font-weight:600; background:#f6f7f7;">' . esc_html__( 'Installation Instructions', 'axiachat-ai' ) . '</div>';
    echo '<div class="card-body">';

    echo '<p>' . esc_html__( 'Andromeda Connect is a companion plugin that bridges your AxiaChat bots with WhatsApp and Telegram. Follow the steps below to install it manually.', 'axiachat-ai' ) . '</p>';

    echo '<div class="aichat-download-box">';
    echo '<h3>' . esc_html__( 'Download the Plugin', 'axiachat-ai' ) . '</h3>';
    echo '<p>' . esc_html__( 'Get the latest release directly from GitHub:', 'axiachat-ai' ) . '</p>';
    echo '<p><code>' . esc_html( $download_url ) . '</code></p>';
    echo '<a class="button button-primary" href="' . esc_url( $download_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Download Latest Release', 'axiachat-ai' ) . '</a>';
    echo ' <a class="button" href="' . esc_url( $github_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on GitHub', 'axiachat-ai' ) . '</a>';
    echo '</div>';

    echo '<div class="aichat-install-steps">';
    echo '<h3>' . esc_html__( 'Installation Steps', 'axiachat-ai' ) . '</h3>';
    echo '<ol>';
    echo '<li>' . sprintf(
        /* translators: %s: The filename of the plugin zip file. */
        esc_html__( 'Download the %s file from the link above.', 'axiachat-ai' ),
        '<code>andromeda-connect.zip</code>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %s: The WordPress admin menu path. */
        esc_html__( 'In your WordPress admin, navigate to %s.', 'axiachat-ai' ),
        '<strong>' . esc_html__( 'Plugins → Add New Plugin', 'axiachat-ai' ) . '</strong>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %s: The button label in the WordPress Plugins screen. */
        esc_html__( 'Click %s at the top of the page.', 'axiachat-ai' ),
        '<strong>' . esc_html__( 'Upload Plugin', 'axiachat-ai' ) . '</strong>'
    ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: 1: The filename of the plugin zip file. 2: The install button label. */
        esc_html__( 'Choose the %1$s file you downloaded and click %2$s.', 'axiachat-ai' ),
        '<code>andromeda-connect.zip</code>',
        '<strong>' . esc_html__( 'Install Now', 'axiachat-ai' ) . '</strong>'
    ) . '</li>';
    echo '<li>' . esc_html__( 'Once the upload completes, click <strong>Activate Plugin</strong>.', 'axiachat-ai' ) . '</li>';
    echo '<li>' . sprintf(
        /* translators: %s: The settings menu path where the Add-ons tab lives. */
        esc_html__( 'Return to %s and enable the WhatsApp & Telegram toggle.', 'axiachat-ai' ),
        '<strong>' . esc_html__( 'AxiaChat Settings → Add-ons', 'axiachat-ai' ) . '</strong>'
    ) . '</li>';
    echo '</ol>';
    echo '</div>';

    if ( $plugin_is_present && ! $plugin_is_active ) {
        echo '<div class="notice notice-warning inline" style="margin-top:24px;"><p>' . esc_html__( 'Plugin files already exist on this site. You can activate Andromeda Connect from the Plugins screen.', 'axiachat-ai' );
        if ( $plugin_version ) {
            /* translators: %s: Detected Andromeda Connect plugin version. */
            echo ' ' . sprintf( esc_html__( '(Version %s detected)', 'axiachat-ai' ), esc_html( $plugin_version ) );
        }
        echo '</p></div>';
    }

    echo '<div style="margin-top:32px;">';
    echo '<a class="button button-primary" href="' . esc_url( $return_url ) . '">' . esc_html__( 'Return to AxiaChat Settings', 'axiachat-ai' ) . '</a>';
    echo ' <a class="button" href="' . esc_url( $plugins_url ) . '">' . esc_html__( 'Go to Plugins', 'axiachat-ai' ) . '</a>';
    echo '</div>';

    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // wrap
}
