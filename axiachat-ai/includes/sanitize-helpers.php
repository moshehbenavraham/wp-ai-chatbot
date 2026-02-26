<?php
/**
 * Centralized sanitization / validation helpers for AxiaChat AI.
 *
 * Follows WP Security Guidelines:
 * - Sanitize early (user / external input)
 * - Validate (enforce expected domain / ranges)
 * - Escape late (templates / output)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aichat_sanitize_session_id' ) ) {
    function aichat_sanitize_session_id( $raw ) {
        $raw = (string) $raw;
        // keep only a-z0-9 - (UUID pattern acceptable)
        $san = preg_replace( '/[^a-z0-9\-]/i', '', $raw );
        if ( $san === '' ) { return ''; }
        // bound length to avoid abuse (UUID v4 length 36). Accept up to 72 just in case.
        if ( strlen( $san ) > 72 ) { $san = substr( $san, 0, 72 ); }
        return $san;
    }
}

if ( ! function_exists( 'aichat_bool' ) ) {
    function aichat_bool( $v ) {
        return ( isset( $v ) && ( $v === '1' || $v === 1 || $v === true || $v === 'true' ) );
    }
}

if ( ! function_exists( 'aichat_bounded_int' ) ) {
    function aichat_bounded_int( $v, $min, $max, $default ) {
        $v = is_numeric( $v ) ? (int)$v : $default;
        if ( $v < $min ) $v = $min;
        if ( $v > $max ) $v = $max;
        return $v;
    }
}

if ( ! function_exists( 'aichat_validate_patch_payload' ) ) {
    /**
     * Validate raw JSON patch string size & decode safely.
     * Returns array (possibly empty) or WP_Error.
     */
    function aichat_validate_patch_payload( $raw, $max_bytes = 20480 ) { // 20KB default cap.
        if ( is_string( $raw ) ) {
            if ( strlen( $raw ) > $max_bytes ) {
                return new WP_Error( 'aichat_patch_too_large', __( 'Patch payload too large.', 'axiachat-ai' ) );
            }
            // Do NOT call stripslashes() here: the caller already uses
            // wp_unslash() which removes WordPress magic-quotes.  A second
            // stripslashes() destroys valid JSON escape sequences (\n → n,
            // \t → t, \" → ", etc.), corrupting multi-line text fields.
            $decoded = json_decode( $raw, true );
            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
                // Fallback: try once with stripslashes in case the input was
                // not previously wp_unslash()-ed (legacy/external callers).
                $decoded = json_decode( stripslashes( $raw ), true );
                if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
                    return new WP_Error( 'aichat_patch_invalid_json', __( 'Invalid patch JSON.', 'axiachat-ai' ) );
                }
            }
            return $decoded;
        }
        if ( is_array( $raw ) ) { return $raw; }
        return [];
    }
}
