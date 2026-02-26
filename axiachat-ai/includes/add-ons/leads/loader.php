<?php
/**
 * Leads Capture Add-on Loader
 * 
 * Provides lead capture functionality through AI chat conversations.
 * Allows bots to collect customer data (name, email, phone, etc.) and
 * save it to multiple destinations: internal table, CPT, CF7, or WPForms.
 * 
 * @package AIChat
 * @subpackage Leads
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Add-on metadata
if ( ! function_exists( 'aichat_leads_addon_info' ) ) {
    function aichat_leads_addon_info() {
        return [
            'id'          => 'leads',
            'name'        => __( 'Lead Capture', 'axiachat-ai' ),
            'description' => __( 'Capture customer contact information through chat conversations and save to multiple destinations.', 'axiachat-ai' ),
            'version'     => '1.0.0',
            'author'      => 'AxiaChat AI',
            'requires'    => '2.0.0',
            'default'     => true, // Activado por defecto
        ];
    }
}

// Check if add-on is enabled (default: true)
$aichat_leads_enabled = get_option( 'aichat_addon_leads_enabled', 1 );
if ( ! $aichat_leads_enabled ) {
    return;
}

// Define constants
if ( ! defined( 'AICHAT_LEADS_VERSION' ) ) {
    define( 'AICHAT_LEADS_VERSION', '1.0.0' );
}
if ( ! defined( 'AICHAT_LEADS_DIR' ) ) {
    define( 'AICHAT_LEADS_DIR', __DIR__ . '/' );
}

// Load core components
require_once AICHAT_LEADS_DIR . 'class-leads-manager.php';
require_once AICHAT_LEADS_DIR . 'integration.php';

// Load adapters
require_once AICHAT_LEADS_DIR . 'adapters/class-adapter-internal.php';
require_once AICHAT_LEADS_DIR . 'adapters/class-adapter-cpt.php';
if ( file_exists( AICHAT_LEADS_DIR . 'adapters/class-adapter-cf7.php' ) ) {
    require_once AICHAT_LEADS_DIR . 'adapters/class-adapter-cf7.php';
}
if ( file_exists( AICHAT_LEADS_DIR . 'adapters/class-adapter-wpforms.php' ) ) {
    require_once AICHAT_LEADS_DIR . 'adapters/class-adapter-wpforms.php';
}

// Load Google Sheets integration
require_once AICHAT_LEADS_DIR . 'google-sheets/class-gsheets-oauth.php';
require_once AICHAT_LEADS_DIR . 'google-sheets/class-gsheets-client.php';
require_once AICHAT_LEADS_DIR . 'adapters/class-adapter-gsheets.php';

// Admin UI (solo en admin)
if ( is_admin() ) {
    require_once AICHAT_LEADS_DIR . 'admin-settings.php';
    require_once AICHAT_LEADS_DIR . 'admin-ajax.php';
    
    // Register Leads submenu
    add_action( 'admin_menu', 'aichat_leads_register_menu', 16 );
    
    // Enqueue admin assets
    add_action( 'admin_enqueue_scripts', 'aichat_leads_admin_enqueue_scripts' );
}

/**
 * Register Leads admin menu
 */
function aichat_leads_register_menu() {
    add_submenu_page(
        'aichat-settings',
        __( 'Leads', 'axiachat-ai' ),
        __( 'Leads', 'axiachat-ai' ),
        'manage_options',
        'aichat-leads',
        'aichat_leads_render_page'
    );
}

/**
 * Enqueue Leads admin scripts
 */
function aichat_leads_admin_enqueue_scripts( $hook_suffix ) {
    if ( $hook_suffix !== 'axiachat-ai_page_aichat-leads' ) {
        return;
    }
    
    $base_url = plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) );
    
    // CSS - depends on Bootstrap which is loaded by main plugin for this page
    wp_enqueue_style(
        'aichat-leads-admin',
        $base_url . 'assets/css/leads-admin.css',
        [ 'aichat-bootstrap', 'aichat-bootstrap-icons' ],
        AICHAT_LEADS_VERSION
    );
    
    // JS depends on jQuery and Bootstrap - add wp-i18n for translation support
    wp_enqueue_script(
        'aichat-leads-admin',
        $base_url . 'assets/js/leads-admin.js',
        [ 'jquery', 'aichat-bootstrap', 'wp-i18n' ],
        AICHAT_LEADS_VERSION,
        true
    );
    wp_set_script_translations( 'aichat-leads-admin', 'axiachat-ai', dirname( dirname( dirname( __DIR__ ) ) ) . '/languages' );
    
    wp_localize_script( 'aichat-leads-admin', 'aichatLeadsAdmin', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'aichat_leads_admin' ),
        'i18n'     => [
            'confirm_delete'          => __( 'Are you sure you want to delete this lead?', 'axiachat-ai' ),
            'confirm_bulk'            => __( 'Are you sure you want to delete the selected leads?', 'axiachat-ai' ),
            'confirm_disconnect'      => __( 'Are you sure you want to disconnect Google Sheets?', 'axiachat-ai' ),
            'export_success'          => __( 'Export completed successfully.', 'axiachat-ai' ),
            'save_success'            => __( 'Settings saved successfully.', 'axiachat-ai' ),
            'spreadsheet_id_required' => __( 'Please enter a Spreadsheet ID first.', 'axiachat-ai' ),
            'error'                   => __( 'An error occurred. Please try again.', 'axiachat-ai' ),
        ],
    ] );
}

/**
 * Create leads table on activation
 */
function aichat_leads_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_leads';
    $charset = $wpdb->get_charset_collate();
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(64) NULL,
        bot_slug VARCHAR(100) NOT NULL DEFAULT '',
        conversation_id BIGINT(20) UNSIGNED NULL,
        
        nombre VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        telefono VARCHAR(50) NULL,
        empresa VARCHAR(255) NULL,
        interes TEXT NULL,
        notas TEXT NULL,
        campos_extra LONGTEXT NULL,
        
        destino VARCHAR(32) NOT NULL DEFAULT 'internal',
        destino_ref VARCHAR(100) NULL,
        estado ENUM('nuevo','contactado','convertido','descartado') NOT NULL DEFAULT 'nuevo',
        ip_hash VARCHAR(64) NULL,
        
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        
        PRIMARY KEY (id),
        KEY bot_slug (bot_slug),
        KEY email (email),
        KEY estado (estado),
        KEY created_at (created_at),
        KEY conversation_id (conversation_id)
    ) $charset;";
    
    dbDelta( $sql );
}

// Hook table creation to plugin activation
add_action( 'aichat_after_tables_created', 'aichat_leads_create_table' );

// Also create on init if table doesn't exist (for existing installations)
add_action( 'admin_init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_leads';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        aichat_leads_create_table();
    }
}, 5 );

// Google Sheets OAuth callback handler
add_action( 'admin_init', 'aichat_leads_gsheets_oauth_callback', 10 );
function aichat_leads_gsheets_oauth_callback() {
    // Check if this is a Google Sheets OAuth callback
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google cannot include nonce.
    if ( ! isset( $_GET['gsheets_callback'] ) || ! isset( $_GET['page'] ) || $_GET['page'] !== 'aichat-leads' ) {
        return;
    }
    
    // Verify we have a code
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google.
    if ( ! isset( $_GET['code'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth error from Google.
        if ( isset( $_GET['error'] ) ) {
            add_action( 'admin_notices', function() {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth error from Google.
                $error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Google Sheets connection failed: ', 'axiachat-ai' ) . esc_html( $error ) . '</p></div>';
            } );
        }
        return;
    }
    
    // Verify state for CSRF protection
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback; state verified below as CSRF protection.
    $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
    $saved_state = get_transient( 'aichat_gsheets_oauth_state' );
    
    // Try to decode state as JSON (new format from central redirect)
    $state_data = json_decode( base64_decode( $state ), true );
    $state_nonce = is_array( $state_data ) && isset( $state_data['nonce'] ) ? $state_data['nonce'] : $state;
    
    if ( ! $state_nonce || ! $saved_state || ! hash_equals( $saved_state, $state_nonce ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid OAuth state. Please try again.', 'axiachat-ai' ) . '</p></div>';
        } );
        return;
    }
    
    // Exchange code for tokens
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google.
    $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
    $result = AIChat_Leads_GSheets_OAuth::exchange_code( $code );
    
    if ( is_wp_error( $result ) ) {
        add_action( 'admin_notices', function() use ( $result ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to connect: ', 'axiachat-ai' ) . esc_html( $result->get_error_message() ) . '</p></div>';
        } );
        return;
    }
    
    // Success - redirect to clean URL
    delete_transient( 'aichat_gsheets_oauth_state' );
    wp_safe_redirect( admin_url( 'admin.php?page=aichat-leads&tab=settings&gsheets_connected=1' ) );
    exit;
}
