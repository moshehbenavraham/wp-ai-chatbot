<?php

/**
 * Rate Limiter — WordPress transient-based rate limiting for REST API endpoints.
 *
 * Provides IP-based rate limiting using WP transients. Apply to any REST
 * endpoint via the wpaic_rate_limited_permission() wrapper.
 *
 * Usage (in endpoint registration):
 *   'permission_callback' => wpaic_rate_limited_permission('chat', 30, 60)
 */

if (! defined('ABSPATH')) {
	define('WPAIC_RATE_LIMITER_STANDALONE', true);
}

/**
 * Check rate limit for the current request.
 *
 * @param string $namespace      Endpoint namespace for separate limits (e.g., 'chat', 'health').
 * @param int    $max_requests   Maximum requests allowed in the window.
 * @param int    $window_seconds Duration of the rate limit window in seconds.
 * @return array{allowed: bool, remaining: int, retry_after: int}
 */
function wpaic_check_rate_limit(string $namespace = 'default', int $max_requests = 60, int $window_seconds = 60): array
{
	$ip = wpaic_get_client_ip();
	$key = 'wpaic_rl_' . md5($namespace . '_' . $ip);

	$data = get_transient($key);
	$now = time();

	if ($data === false || ! is_array($data)) {
		$data = [
			'count' => 1,
			'window_start' => $now,
		];
		set_transient($key, $data, $window_seconds);

		return [
			'allowed' => true,
			'remaining' => $max_requests - 1,
			'retry_after' => 0,
		];
	}

	$elapsed = $now - $data['window_start'];

	if ($elapsed >= $window_seconds) {
		$data = [
			'count' => 1,
			'window_start' => $now,
		];
		set_transient($key, $data, $window_seconds);

		return [
			'allowed' => true,
			'remaining' => $max_requests - 1,
			'retry_after' => 0,
		];
	}

	$data['count']++;
	set_transient($key, $data, $window_seconds - $elapsed);

	if ($data['count'] > $max_requests) {
		return [
			'allowed' => false,
			'remaining' => 0,
			'retry_after' => $window_seconds - $elapsed,
		];
	}

	return [
		'allowed' => true,
		'remaining' => $max_requests - $data['count'],
		'retry_after' => 0,
	];
}

/**
 * REST API permission callback that enforces rate limiting.
 *
 * Returns a closure suitable for use as a WP REST 'permission_callback'.
 * Optionally wraps an inner permission check (e.g., nonce or capability check).
 *
 * @param string        $namespace      Rate limit namespace.
 * @param int           $max_requests   Max requests per window.
 * @param int           $window_seconds Window duration in seconds.
 * @param callable|null $inner_check    Optional inner permission callback.
 * @return callable
 */
function wpaic_rate_limited_permission(
	string $namespace = 'default',
	int $max_requests = 60,
	int $window_seconds = 60,
	?callable $inner_check = null
): callable {
	return function ($request) use ($namespace, $max_requests, $window_seconds, $inner_check) {
		if ($inner_check !== null) {
			$inner_result = call_user_func($inner_check, $request);
			if ($inner_result !== true) {
				return $inner_result;
			}
		}

		$result = wpaic_check_rate_limit($namespace, $max_requests, $window_seconds);

		if (! $result['allowed']) {
			return new WP_Error(
				'rate_limit_exceeded',
				'Too many requests. Please try again later.',
				[
					'status' => 429,
					'retry_after' => $result['retry_after'],
				]
			);
		}

		return true;
	};
}

/**
 * Add rate-limit headers to a WP_REST_Response.
 *
 * Call after wpaic_check_rate_limit() to inform clients of their quota.
 *
 * @param WP_REST_Response $response      The outgoing response.
 * @param int              $max_requests  Window limit.
 * @param int              $remaining     Requests remaining.
 * @param int              $retry_after   Seconds until window resets (0 if not rate-limited).
 * @return WP_REST_Response
 */
function wpaic_add_rate_limit_headers($response, int $max_requests, int $remaining, int $retry_after = 0)
{
	$response->header('X-RateLimit-Limit', (string) $max_requests);
	$response->header('X-RateLimit-Remaining', (string) max(0, $remaining));
	if ($retry_after > 0) {
		$response->header('Retry-After', (string) $retry_after);
	}

	return $response;
}

/**
 * Get client IP address with proxy awareness.
 *
 * Checks common proxy headers before falling back to REMOTE_ADDR.
 *
 * @return string
 */
function wpaic_get_client_ip(): string
{
	$headers = [
		'HTTP_CF_CONNECTING_IP',   // Cloudflare
		'HTTP_X_REAL_IP',          // nginx proxy
		'HTTP_X_FORWARDED_FOR',    // Generic proxy (may contain chain)
	];

	foreach ($headers as $header) {
		if (! empty($_SERVER[$header])) {
			$ips = explode(',', $_SERVER[$header]);
			$ip = trim($ips[0]);
			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}
	}

	return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
