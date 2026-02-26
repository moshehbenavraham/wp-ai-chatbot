<?php
// Loader for Simply Schedule Appointments (SSA) AI Tools add-on
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined('AICHAT_SSA_LOADER_ATTACHED') ) {
    define('AICHAT_SSA_LOADER_ATTACHED', true);

    // Always load helpers and register tools/macros so capabilities are visible even if SSA isn't installed yet.
    require_once __DIR__ . '/helpers.php';
    require_once __DIR__ . '/tools.php';
    // Helpers and tools are loaded regardless of SSA presence to show capabilities in UI

    // Defer detection/agent bootstrapping until init to ensure SSA finished setting up.
    add_action('init', function(){
        $detected = function_exists('ssa')
            || class_exists('Simply_Schedule_Appointments')
            || class_exists('SSA_Appointment_Model')
            || class_exists('SSA_Service_Model')
            || class_exists('SSA_Appointment');

        if ( ! $detected ) { return; }

        // Load agent classes and attach to SSA instance if available
        require_once __DIR__ . '/class-availability-agent.php';
        require_once __DIR__ . '/class-booking-agent.php';

        if ( function_exists( 'ssa' ) ) {
            $plugin = call_user_func( 'ssa' );
            if ( $plugin ) {
                if ( ! isset( $plugin->availability_agent ) ) {
                    try {
                        $plugin->availability_agent = new SSA_Availability_Agent( $plugin );
                        // availability_agent attached
                    } catch ( \Throwable $e ) {
                        // silent fail
                    }
                }
                if ( ! isset( $plugin->booking_agent ) ) {
                    try {
                        $plugin->booking_agent = new SSA_Booking_Agent( $plugin );
                        // booking_agent attached
                    } catch ( \Throwable $e ) {
                        // silent fail
                    }
                }
            }
        } else {
            if ( function_exists('aichat_log_debug') ) { aichat_log_debug('SSA loader: ssa() function missing; agents not attached'); }
        }
    }, 20);
}
