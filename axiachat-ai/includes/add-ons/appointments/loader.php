<?php
/**
 * Appointments Add-on Loader
 * 
 * Handles AI-powered appointment booking through chat conversations.
 * Supports internal calendar and integration with Bookly/Amelia.
 * 
 * @package AIChat
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Check if add-on is enabled
$aichat_appointments_enabled = (int) get_option( 'aichat_addon_appointments_enabled', 1 );
if ( ! $aichat_appointments_enabled ) {
    return;
}

// Define add-on constants
if ( ! defined( 'AICHAT_APPOINTMENTS_DIR' ) ) {
    define( 'AICHAT_APPOINTMENTS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'AICHAT_APPOINTMENTS_URL' ) ) {
    define( 'AICHAT_APPOINTMENTS_URL', plugin_dir_url( __FILE__ ) );
}

// Load interface first
require_once AICHAT_APPOINTMENTS_DIR . 'interfaces/interface-appointments-adapter.php';

// Load Google Calendar classes
require_once AICHAT_APPOINTMENTS_DIR . 'google-calendar/class-gcal-oauth.php';
require_once AICHAT_APPOINTMENTS_DIR . 'google-calendar/class-gcal-client.php';

// Load adapters
require_once AICHAT_APPOINTMENTS_DIR . 'adapters/class-adapter-internal.php';
require_once AICHAT_APPOINTMENTS_DIR . 'adapters/class-adapter-bookly.php';
require_once AICHAT_APPOINTMENTS_DIR . 'adapters/class-adapter-amelia.php';
require_once AICHAT_APPOINTMENTS_DIR . 'adapters/class-adapter-ssa.php';
require_once AICHAT_APPOINTMENTS_DIR . 'adapters/class-adapter-google-calendar.php';

// Load core components
require_once AICHAT_APPOINTMENTS_DIR . 'class-appointments-manager.php';

// Register adapters
add_action( 'init', function() {
    AIChat_Appointments_Manager::register_adapter( 'internal', new AIChat_Appointments_Adapter_Internal() );
    AIChat_Appointments_Manager::register_adapter( 'bookly', new AIChat_Appointments_Adapter_Bookly() );
    AIChat_Appointments_Manager::register_adapter( 'amelia', new AIChat_Appointments_Adapter_Amelia() );
    AIChat_Appointments_Manager::register_adapter( 'ssa', new AIChat_Appointments_Adapter_SSA() );
    AIChat_Appointments_Manager::register_adapter( 'google_calendar', new AIChat_Appointments_Adapter_Google_Calendar() );
}, 5 );

// Load admin components
if ( is_admin() ) {
    require_once AICHAT_APPOINTMENTS_DIR . 'admin-settings.php';
    require_once AICHAT_APPOINTMENTS_DIR . 'admin-ajax.php';
}

// Load tools registration (legacy filter-based)
require_once AICHAT_APPOINTMENTS_DIR . 'tools.php';

// Load AI Tools integration (proper registration via aichat_register_tool_safe)
require_once AICHAT_APPOINTMENTS_DIR . 'integration.php';

/**
 * Initialize appointments table on plugin activation
 */
function aichat_appointments_activate() {
    AIChat_Appointments_Manager::create_table();
}
register_activation_hook( AICHAT_PLUGIN_DIR . 'axiachat-ai.php', 'aichat_appointments_activate' );

/**
 * Ensure table exists and run migrations
 * This handles cases where the add-on is enabled after plugin activation
 */
add_action( 'admin_init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_appointments';
    
    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $table_exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table
    ) );
    
    if ( ! $table_exists ) {
        AIChat_Appointments_Manager::create_table();
    } else {
        // Run migrations for existing tables (once per version)
        $db_version = get_option( 'aichat_appointments_db_version', '1.0' );
        if ( version_compare( $db_version, '1.1', '<' ) ) {
            AIChat_Appointments_Manager::create_table(); // This will run migrations
            update_option( 'aichat_appointments_db_version', '1.1' );
        }
    }
}, 5 );

/**
 * Add admin menu for Appointments
 */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'aichat-settings',
        __( 'Appointments', 'axiachat-ai' ),
        __( 'Appointments', 'axiachat-ai' ),
        'manage_options',
        'aichat-appointments',
        'aichat_appointments_render_page'
    );
}, 25 );

/**
 * Log helper for debugging
 */
function aichat_appointments_log( $message, $context = [] ) {
    if ( function_exists( 'aichat_log_debug' ) ) {
        aichat_log_debug( '[Appointments] ' . $message, $context );
    }
}

// =========================================================================
// CRON JOB FOR REMINDERS
// =========================================================================

/**
 * Schedule reminder cron event on plugin activation
 */
function aichat_appointments_schedule_reminder_cron() {
    if ( ! wp_next_scheduled( 'aichat_appointments_send_reminders' ) ) {
        wp_schedule_event( time(), 'hourly', 'aichat_appointments_send_reminders' );
    }
}
add_action( 'admin_init', 'aichat_appointments_schedule_reminder_cron' );

/**
 * Unschedule cron on plugin deactivation
 */
function aichat_appointments_unschedule_reminder_cron() {
    $timestamp = wp_next_scheduled( 'aichat_appointments_send_reminders' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'aichat_appointments_send_reminders' );
    }
}
register_deactivation_hook( AICHAT_PLUGIN_DIR . 'axiachat-ai.php', 'aichat_appointments_unschedule_reminder_cron' );

/**
 * Process reminder emails cron callback
 */
add_action( 'aichat_appointments_send_reminders', function() {
    if ( class_exists( 'AIChat_Appointments_Manager' ) ) {
        $sent = AIChat_Appointments_Manager::process_pending_reminders();
        if ( $sent > 0 ) {
            aichat_appointments_log( 'Reminder cron completed', [ 'sent_count' => $sent ] );
        }
    }
} );
