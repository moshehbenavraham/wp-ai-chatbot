<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Devuelve true|WP_Error según pase o no la moderación.
 */
function aichat_run_moderation_checks( $message ) {
    $enabled = (bool) get_option('aichat_moderation_enabled', false);
    if (!$enabled) return true;

    $rejection = trim( get_option('aichat_moderation_rejection_message', __( 'Request not authorized.', 'axiachat-ai' ) ) );
    if ($rejection === '') $rejection = __( 'Request not authorized.', 'axiachat-ai' );

    // 1) Bloqueo IP local
    $client_ip = aichat_detect_client_ip();
    $banned_ips_raw = (string) get_option('aichat_moderation_banned_ips', '');
    if ($banned_ips_raw !== '') {
        $list = preg_split('/[\r\n,]+/', $banned_ips_raw);
        foreach ($list as $ipMask) {
            $ipMask = trim($ipMask);
            if ($ipMask === '') continue;
            if (aichat_ip_matches($client_ip, $ipMask)) {
                return new WP_Error('aichat_blocked_ip', $rejection);
            }
        }
    }

    // 2) Palabras prohibidas (local)
    $user_words_raw = (string) get_option('aichat_moderation_banned_words', '');
    $use_default    = (bool) get_option('aichat_moderation_use_default_words', false);
    $words = [];

    if ($use_default) {
        $defaults_file = __DIR__ . '/banned-words-en.php';
        if (file_exists($defaults_file)) {
            $def = include $defaults_file;
            if (is_array($def)) $words = array_merge($words, $def);
        }
    }
    if ($user_words_raw !== '') {
        $extra = preg_split('/\r\n|\n+/', $user_words_raw);
        $words = array_merge($words, array_map('trim', $extra));
    }
    $words = array_filter(array_unique($words));

    if ($words) {
        $lower = mb_strtolower($message);
        foreach ($words as $w) {
            if ($w === '') continue;
            if ($w[0] === '/' && substr($w,-1) === '/') {
                // Regex
                if (@preg_match($w.'u', '') === false) continue;
                if (preg_match($w.'u', $message)) {
                    return new WP_Error('aichat_blocked_word', $rejection);
                }
                continue;
            }
            // Palabra simple (boundary aproximada)
            $esc = preg_quote(mb_strtolower($w), '/');
            if (preg_match('/\b'.$esc.'\b/u', $lower)) {
                return new WP_Error('aichat_blocked_word', $rejection);
            }
        }
    }

    // 3) Moderación externa (OpenAI)
    $external = (bool) get_option('aichat_moderation_external_enabled', false);
    if ($external) {
        // Use aichat_get_setting() for consistent decryption (same as settings.php)
        $openai_key = function_exists('aichat_get_setting') ? aichat_get_setting('aichat_openai_api_key') : get_option('aichat_openai_api_key', '');
        if ($openai_key) {
            $flagged = aichat_openai_moderation_flagged($message, $openai_key);
            if (is_wp_error($flagged)) {
                // Si error de red → permitir (o podrías bloquear opcionalmente)
                return true;
            }
            if ($flagged === true) {
                return new WP_Error('aichat_blocked_external', $rejection);
            }
        }
    }

    return true;
}

function aichat_detect_client_ip() {
    $candidates = [];
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
    }
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $xff_raw = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        $parts   = explode( ',', (string) $xff_raw );
        foreach ( $parts as $p ) {
            $candidates[] = sanitize_text_field( trim( (string) $p ) );
        }
    }
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}

function aichat_ip_matches($ip, $rule) {
    // Single IP
    if (strpos($rule, '/') === false) {
        return $ip === $rule;
    }
    // CIDR
    list($subnet, $mask) = explode('/', $rule, 2);
    if (!filter_var($subnet, FILTER_VALIDATE_IP)) return false;
    $mask = (int)$mask;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ||
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6 simplificada: comparar prefix textual
        $packed_ip = inet_pton($ip);
        $packed_sub = inet_pton($subnet);
        if (!$packed_ip || !$packed_sub) return false;
        $bytes = intdiv($mask,8);
        $rem   = $mask % 8;
        if ($bytes && substr($packed_ip,0,$bytes)!==substr($packed_sub,0,$bytes)) return false;
        if ($rem) {
            $ibit = ord($packed_ip[$bytes]) >> (8-$rem);
            $sbit = ord($packed_sub[$bytes]) >> (8-$rem);
            if ($ibit !== $sbit) return false;
        }
        return true;
    } else {
        // IPv4
        $ip_long = ip2long($ip);
        $sub_long= ip2long($subnet);
        if ($ip_long === false || $sub_long === false) return false;
        $mask_bin = -1 << (32 - $mask);
        return ($ip_long & $mask_bin) === ($sub_long & $mask_bin);
    }
}

function aichat_openai_moderation_flagged($text, $api_key) {
    $body = [
        'model' => 'omni-moderation-latest',
        'input' => (string)$text,
    ];
    $res = wp_remote_post('https://api.openai.com/v1/moderations', [
        'headers' => [
            'Authorization' => 'Bearer '.$api_key,
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 15,
        'body'    => wp_json_encode($body),
    ]);
    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    $raw  = wp_remote_retrieve_body($res);
    if ($code >= 400) return new WP_Error('moderation_http', 'HTTP '.$code);
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['results'][0])) return new WP_Error('moderation_parse','Moderation parse error');
    return (bool)($data['results'][0]['flagged'] ?? false);
}
