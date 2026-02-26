<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_User {

    // Function to get user identifier (username, email, or session ID)
    public static function mxchat_get_user_identifier() {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            //error_log('Current User: ' . print_r($current_user, true));
            return $current_user->user_login; // Use username as identifier
        } else {
            //error_log('User is not logged in. Using IP as identifier.');
            return sanitize_text_field($_SERVER['REMOTE_ADDR']); // Use IP address as fallback
        }
    }

    // Function to get user email
    public static function mxchat_get_user_email() {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return $current_user->user_email;
        }
        return null; // No email if not logged in
    }
}

