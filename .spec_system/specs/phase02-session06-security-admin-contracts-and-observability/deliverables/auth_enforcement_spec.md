# Auth Enforcement Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

Every endpoint in the plugin -- AJAX and REST alike -- MUST pass a dual-check: (1) nonce
verification and (2) capability verification. Neither check alone is sufficient. This pattern
is adopted from AxiaChat (`class-aichat-ajax.php` lines 55-80) and enforced as Compliance
Rule ACR-01 (below).

The auth enforcer (`includes/security/class-auth-enforcer.php`) provides a single entry point
that all endpoints call at the top of their handler. If either check fails, the handler
immediately returns an error response and exits. No handler reads `$_POST` data before calling
the auth enforcer.

**Owner module**: `includes/security/class-auth-enforcer.php`
**Namespace**: `WPAIC\Security\Auth_Enforcer`

---

## 2. Auth Enforcer Interface

```php
namespace WPAIC\Security;

class Auth_Enforcer {

    /**
     * Verify nonce + capability for an AJAX request.
     * Calls wp_die() on failure (AJAX convention).
     *
     * @param string $action      Nonce action string.
     * @param string $nonce_field POST field name containing the nonce value.
     * @param string $capability  WP capability required (e.g., 'manage_options').
     * @param bool   $nopriv      True if endpoint serves unauthenticated users.
     */
    public static function check_ajax(
        string $action,
        string $nonce_field = 'nonce',
        string $capability = '',
        bool $nopriv = false
    ): void;

    /**
     * Verify nonce + capability for a REST request.
     * Returns WP_Error on failure (REST convention).
     *
     * @param WP_REST_Request $request
     * @param string          $action      Nonce action string.
     * @param string          $capability  WP capability required; empty string for public endpoints.
     * @return true|\WP_Error
     */
    public static function check_rest(
        \WP_REST_Request $request,
        string $action,
        string $capability = ''
    );

    /**
     * Check ownership of a session (UUID match + user/IP match).
     * Used by session resume and history endpoints.
     *
     * @param string $session_token  UUID from request.
     * @param array  $session_row    Row from wp_wpaic_sessions.
     * @return bool                  True if ownership verified.
     */
    public static function verify_session_ownership(
        string $session_token,
        array $session_row
    ): bool;

    /**
     * Get the nonce action string for a given endpoint slug.
     * Centralizes nonce action naming to prevent typos.
     *
     * @param string $endpoint_slug  One of the ENDPOINT_* constants.
     * @return string                Nonce action string.
     */
    public static function nonce_action( string $endpoint_slug ): string;
}
```

---

## 3. Nonce Strategy

All nonces use `wp_create_nonce()` with action string `wpaic_{endpoint_slug}`. The action
string is centralized in `Auth_Enforcer::nonce_action()` to prevent drift between where
nonces are created (template/JS localization) and verified (AJAX/REST handler).

### 3.1 Nonce Lifetime

WordPress nonces expire after 24 hours (two 12-hour ticks). This is acceptable for admin
endpoints. For frontend chat endpoints, the nonce is refreshed with each page load (localized
in the shortcode output via `wp_localize_script`).

### 3.2 Nonce Actions by Endpoint

| Endpoint Slug | Nonce Action | Created In | Verified In |
|--------------|-------------|-----------|------------|
| `chat_send` | `wpaic_chat_send` | `shortcode.php` localize | AJAX handler |
| `session_create` | `wpaic_session_create` | REST args inline | REST permission callback |
| `session_history` | `wpaic_session_history` | JS localize | REST permission callback |
| `knowledge_crud` | `wpaic_knowledge_crud` | Admin page localize | REST permission callback |
| `knowledge_reindex` | `wpaic_knowledge_reindex` | Admin page localize | REST permission callback |
| `admin_settings` | `wpaic_admin_settings` | WP Settings API (built-in) | WP Settings API (built-in) |
| `diagnostics_stream` | `wpaic_diagnostics_stream` | Admin page localize | SSE endpoint |
| `data_export` | `wpaic_data_export` | Security page localize | REST permission callback |
| `data_erasure` | `wpaic_data_erasure` | Security page localize | REST permission callback |
| `provider_test` | `wpaic_provider_test` | Providers page localize | AJAX handler |
| `backup_export` | `wpaic_backup_export` | lib/backup-manager.php | lib/backup-manager.php |
| `backup_import` | `wpaic_backup_import` | lib/backup-manager.php | lib/backup-manager.php |

---

## 4. Complete Endpoint Auth Matrix

### 4.1 Frontend AJAX Endpoints (nopriv -- serve unauthenticated visitors)

| Endpoint | WP Hook | Capability | Nonce | Notes |
|---------|---------|-----------|-------|-------|
| `wp_ajax_nopriv_wpaic_send_message` | runtime/class-message-pipeline.php | None (nopriv) | `wpaic_chat_send` | Rate limiter runs BEFORE nonce check |
| `wp_ajax_nopriv_wpaic_session_create` | runtime/class-session-manager.php | None (nopriv) | `wpaic_session_create` | Returns session UUID + nonce for chat_send |
| `wp_ajax_nopriv_wpaic_stream_response` | runtime/class-stream-handler.php | None (nopriv) | `wpaic_chat_send` | SSE endpoint; same nonce as send_message |

**Note on rate limiting**: For nopriv endpoints, the rate limiter (`lib/rate-limiter.php`) runs
before nonce verification to prevent nonce-farming attacks. If rate limit exceeded, return
429 before checking nonce.

### 4.2 Admin AJAX Endpoints (require logged-in + manage_options)

| Endpoint | WP Hook | Capability | Nonce |
|---------|---------|-----------|-------|
| `wp_ajax_wpaic_provider_test` | admin/pages/class-providers-page.php | `manage_options` | `wpaic_provider_test` |
| `wp_ajax_wpaic_diagnostics_panel` | admin/class-diagnostics.php | `manage_options` | `wpaic_diagnostics_stream` |

### 4.3 REST API Endpoints (wp-ai-chatbot/v1)

| Route | Method | Auth | Capability | Nonce Header |
|-------|--------|------|-----------|-------------|
| `/session` | POST | Cookie/logged-in OR nopriv | None | `X-WP-Nonce: wpaic_session_create` |
| `/session/{token}` | GET | Session ownership | None | `X-WP-Nonce: wpaic_session_history` |
| `/session/{token}/messages` | GET | Session ownership | None | `X-WP-Nonce: wpaic_session_history` |
| `/session/{token}` | DELETE | Session ownership | None | `X-WP-Nonce: wpaic_session_history` |
| `/knowledge` | GET | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/knowledge` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/knowledge/{id}` | GET | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/knowledge/{id}` | PUT | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/knowledge/{id}` | DELETE | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/knowledge/{id}/reindex` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_reindex` |
| `/knowledge/{id}/queue` | GET | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/knowledge/{id}/search` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_knowledge_crud` |
| `/privacy/export` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_data_export` |
| `/privacy/erase` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_data_erasure` |
| `/health` | GET | None (public) | None | None |
| `/backup/export` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_backup_export` |
| `/backup/import` | POST | Logged-in | `manage_options` | `X-WP-Nonce: wpaic_backup_import` |

---

## 5. Capability Model

The plugin uses a single admin capability: `manage_options`. This follows MxChat and Baachal
patterns and is the WP standard for plugin settings management.

**Deferred**: Per-user roles or custom capabilities (e.g., `wpaic_manage_knowledge`) are
deferred to Phase 07 extension point design. The capability is accessed via the filter
`wpaic_admin_capability` so custom roles can be injected without code changes:

```php
$capability = apply_filters( 'wpaic_admin_capability', 'manage_options' );
```

---

## 6. Session Ownership Verification

Session endpoints do not require WordPress authentication -- sessions belong to visitors.
Ownership is verified by matching:
1. The `session_token` UUID in the request path
2. The IP address OR the WordPress `user_id` (if logged in) against `wp_wpaic_sessions`

```
verify_session_ownership($token, $row):
  if current_user_id() > 0 and $row.user_id == current_user_id(): return true
  if md5(get_client_ip()) == $row.ip_hash: return true
  return false
```

`ip_hash` is stored as `md5(client_ip)` in the sessions table (one-way, IP privacy compliant).

---

## 7. Compliance Rules

| Rule | Requirement |
|------|------------|
| ACR-01 | Every AJAX/REST handler MUST call `Auth_Enforcer::check_ajax()` or `check_rest()` before reading any request data |
| ACR-02 | Nonce-only (without capability check) is forbidden for admin endpoints |
| ACR-03 | Capability-only (without nonce) is forbidden for all endpoints |
| ACR-04 | Frontend nopriv endpoints MUST run rate limiter before nonce check |
| ACR-05 | Session ownership verification MUST precede any session data retrieval |
| ACR-06 | Every nonce action string MUST be retrieved via `Auth_Enforcer::nonce_action()` |
| ACR-07 | Nonce values MUST NOT be logged in structured logger or audit trail |

---

## 8. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Dual-check (nonce + capability) on all handlers | `axiachat-ai/includes/class-aichat-ajax.php` lines 55-80 | Adopted; centralized in Auth_Enforcer |
| `manage_options` capability for all admin | `mxchat-basic/admin/class-ajax-handler.php` | Adopted |
| Nonce in shortcode localize for frontend | `axiachat-ai/includes/shortcode.php` | Adopted |
| Rate limiter before nonce on nopriv | `axiachat-ai/includes/class-aichat-ajax.php` lines 131-145 | Adopted |
| Session UUID ownership check | `axiachat-ai/includes/class-aichat-ajax.php` lines 55-80 | Adopted; generalized |
| `wp_ajax_nopriv_*` hook registration | `baachal/baachal.php` AJAX registration | Adopted |
