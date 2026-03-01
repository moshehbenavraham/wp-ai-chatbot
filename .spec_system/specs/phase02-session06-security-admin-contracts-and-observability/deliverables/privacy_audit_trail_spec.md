# Privacy Controls and Audit Trail Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-SEC-003 (privacy controls), CFI-SEC-004 (audit trail)

---

## 1. Overview

This document covers two related concerns: (1) privacy controls for the plugin's data collection
and processing behavior, and (2) the audit trail system for logging security-relevant events.
Both are owned by `includes/security/class-privacy-manager.php` and `includes/security/class-audit-logger.php`.

---

## PART A: Privacy Controls (CFI-SEC-003)

---

## 2. Privacy Manager Contract

```php
namespace WPAIC\Security;

class Privacy_Manager {

    /**
     * Check if the current visitor has exceeded the daily message limit.
     *
     * @param string $identifier  Hashed IP or user ID.
     * @return bool               True if limit exceeded.
     */
    public static function is_over_daily_limit( string $identifier ): bool;

    /**
     * Increment the message count for an identifier.
     * Called after successful message processing.
     *
     * @param string $identifier
     */
    public static function increment_message_count( string $identifier ): void;

    /**
     * Get the client IP address in privacy-compliant form.
     * Returns hashed IP if IP privacy mode is enabled.
     *
     * @return string  IP address (raw or md5-hashed based on wpaic_privacy_ip_mode).
     */
    public static function get_client_identifier(): string;

    /**
     * Export all data associated with an identifier (GDPR data subject access).
     *
     * @param string $identifier  Hashed IP or user ID.
     * @return array              Structured export payload.
     */
    public static function export_data( string $identifier ): array;

    /**
     * Erase all data for an identifier (GDPR right to erasure).
     *
     * @param string $identifier
     * @return int  Number of records erased.
     */
    public static function erase_data( string $identifier ): int;

    /**
     * Run data retention cleanup (called by WP-Cron: wpaic_retention_cleanup).
     * Deletes sessions and messages older than retention TTL.
     */
    public static function run_retention_cleanup(): void;
}
```

---

## 3. Privacy Configuration Options

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `wpaic_privacy_ip_mode` | `string` | `'hash'` | `'raw'` \| `'hash'` \| `'none'` -- IP storage mode |
| `wpaic_privacy_daily_limit` | `int` | `0` | Max messages per visitor per day (0 = unlimited) |
| `wpaic_privacy_lifetime_limit` | `int` | `0` | Max total messages per visitor lifetime (0 = unlimited) |
| `wpaic_privacy_memory_cap_messages` | `int` | `100` | Max messages kept in session history per session |
| `wpaic_privacy_retention_days` | `int` | `90` | Days to retain session/message data (0 = retain forever) |
| `wpaic_privacy_gdpr_consent_enabled` | `bool` | `false` | Require GDPR consent before first message |
| `wpaic_privacy_gdpr_consent_text` | `string` | `''` | Consent bubble text (HTML allowed) |
| `wpaic_privacy_log_retention_days` | `int` | `30` | Days to retain structured log files |

---

## 4. IP Privacy Modes

| Mode | Stored Value | Use Case |
|------|-------------|----------|
| `raw` | Actual IP address | Full logging enabled; may require consent |
| `hash` | `md5($ip)` (default) | Pseudonymized; rate limiting works; not GDPR-identifiable |
| `none` | Empty string | Maximum privacy; no IP-based rate limiting; no usage attribution |

When `mode = 'hash'`, the raw IP is never written to the database or logs. Rate limiting
uses the hash as the key (still functional). Daily limits use the hash as the identifier.

---

## 5. Daily Message Limit Implementation

Usage counts are stored in WP transients (not database) for performance:

```
Transient key: wpaic_usage_{identifier}_{YYYY-MM-DD}
Transient expiry: midnight UTC (set to seconds until next day)
Value: int (message count)
```

Reset schedule: Automatic via transient expiry. No cron needed.

When `wpaic_privacy_daily_limit > 0` and count >= limit:
- Return error response to frontend: `{"error": "quota_exceeded", "message": "..."}`
- Frontend renders quota banner (from session 04 frontend spec)
- No moderation pipeline stages are run (short-circuit before Stage 2)

---

## 6. Data Export Endpoint

```
POST /wp-json/wp-ai-chatbot/v1/privacy/export
Auth: manage_options + nonce wpaic_data_export
Body: {"identifier": "<md5 hash or user_id>", "identifier_type": "ip_hash|user_id"}
Response:
{
  "export": {
    "generated_at": "ISO8601",
    "identifier_type": "ip_hash",
    "sessions": [
      {
        "session_token": "...",
        "created_at": "...",
        "messages": [
          {"role": "user", "content": "...", "created_at": "..."},
          {"role": "assistant", "content": "...", "created_at": "..."}
        ]
      }
    ]
  }
}
```

The export excludes: internal metadata, cost data, provider-specific fields, IP addresses in raw
form. Sessions are returned with message content (for GDPR data subject access requests).

---

## 7. Data Erasure Endpoint

```
POST /wp-json/wp-ai-chatbot/v1/privacy/erase
Auth: manage_options + nonce wpaic_data_erasure
Body: {"identifier": "<md5 hash or user_id>", "identifier_type": "ip_hash|user_id", "confirm": true}
Response: {"erased_sessions": N, "erased_messages": N}
```

**Erasure behavior**:
1. DELETE from `wp_wpaic_messages` WHERE session_id IN (sessions for identifier)
2. DELETE from `wp_wpaic_sessions` WHERE ip_hash = identifier OR user_id = identifier
3. Log erasure event to audit trail (identifier hash + count only, not content)
4. Purge usage transients for identifier
5. Return counts

**Idempotent**: Re-running erasure for an already-erased identifier returns counts of 0.

---

## 8. Retention Cleanup

WP-Cron event `wpaic_retention_cleanup` runs daily. When `wpaic_privacy_retention_days > 0`:

```sql
-- Delete old messages (cascade from sessions):
DELETE m FROM wp_wpaic_messages m
INNER JOIN wp_wpaic_sessions s ON m.session_id = s.id
WHERE s.created_at < DATE_SUB(NOW(), INTERVAL {retention_days} DAY);

-- Delete old sessions:
DELETE FROM wp_wpaic_sessions
WHERE created_at < DATE_SUB(NOW(), INTERVAL {retention_days} DAY);
```

Log file cleanup: Files older than `wpaic_privacy_log_retention_days` are deleted from `logs/`.

---

## PART B: Audit Trail (CFI-SEC-004)

---

## 9. Audit Logger Contract

```php
namespace WPAIC\Security;

class Audit_Logger {

    /**
     * Log a security audit event.
     *
     * @param string $event_type  One of the EVENT_* constants.
     * @param array  $context     Event-specific structured data.
     * @param string $severity    'info'|'warn'|'error'|'critical'
     */
    public static function log(
        string $event_type,
        array $context = [],
        string $severity = 'info'
    ): void;

    /**
     * Query recent audit log entries (from log files).
     *
     * @param array $filters  ['event_type' => string, 'severity' => string, 'limit' => int]
     * @return array          Array of log entry arrays.
     */
    public static function query( array $filters = [] ): array;
}
```

---

## 10. Audit Event Taxonomy

| Event Type Constant | Trigger | Severity | Context Fields |
|--------------------|---------|----------|---------------|
| `EVENT_AUTH_FAIL` | Nonce or capability check failed | warn | endpoint, capability, user_id, ip_hash |
| `EVENT_RATE_LIMIT` | Rate limit exceeded | warn | endpoint, ip_hash, limit, window |
| `EVENT_MODERATION_BLOCK` | Message blocked by moderation layer | info | layer, reason_code, ip_hash |
| `EVENT_ENCRYPTION_DEGRADED` | OpenSSL unavailable | error | php_version, extensions |
| `EVENT_KEY_ROTATION` | Encryption key changed or re-encrypted | info | fields_re_encrypted, timestamp |
| `EVENT_KEY_CHANGE_DETECTED` | Stored key hash mismatch detected | warn | old_hash_prefix, timestamp |
| `EVENT_DATA_EXPORT` | GDPR data export performed | info | identifier_type, performed_by_user_id |
| `EVENT_DATA_ERASURE` | GDPR data erasure performed | info | identifier_type, erased_count, performed_by_user_id |
| `EVENT_SETTINGS_CHANGE` | Admin settings updated | info | changed_keys (list only, no values), user_id |
| `EVENT_PROVIDER_KEY_SET` | API key saved or cleared | info | provider_slug, action (set|cleared), user_id |
| `EVENT_MIGRATION_EXECUTED` | DB migration callback ran | info | from_version, to_version, tables_affected |
| `EVENT_PLAINTEXT_KEY_MIGRATED` | Plaintext key encrypted on upgrade | info | option_key (name only), timestamp |
| `EVENT_SPAM_BLOCKED` | Spam signature matched | info | ip_hash (no message content) |
| `EVENT_AI_MODERATION_BLOCKED` | AI moderation flagged content | info | provider, ip_hash (no message content) |
| `EVENT_QUEUE_STALL` | Ingestion queue item stalled | warn | item_id, knowledge_id, attempts |

---

## 11. Log Entry Schema

Audit events are written to the structured logger (`lib/structured-logger.php`) with subsystem
tag `audit`. The JSON log entry format:

```json
{
  "timestamp": "2026-03-01T12:00:00Z",
  "level": "WARN",
  "subsystem": "audit",
  "event_type": "auth_fail",
  "context": {
    "endpoint": "wp_ajax_wpaic_send_message",
    "capability": "manage_options",
    "user_id": 0,
    "ip_hash": "a1b2c3d4..."
  },
  "plugin_version": "1.0.0",
  "wp_version": "6.7"
}
```

**Privacy rule**: Audit log entries MUST NOT contain:
- Raw IP addresses (use ip_hash only)
- Message content (user messages or assistant responses)
- API key values (event_type `EVENT_PROVIDER_KEY_SET` logs key name only)
- Nonce values

---

## 12. Audit Log Retention

- Log files are rotated daily by `lib/structured-logger.php` (`logs/app-YYYY-MM-DD.log`)
- Log cleanup runs on `wpaic_retention_cleanup` cron event
- Files older than `wpaic_privacy_log_retention_days` (default 30) are deleted
- Admin can trigger manual log download from Diagnostics > Logs panel

---

## 13. CFI-SEC-003 and CFI-SEC-004 Resolution

### CFI-SEC-003 Resolution
| Requirement | Resolution | Section |
|-------------|-----------|---------|
| Daily message limits | Transient-based counter, configurable | Section 5 |
| IP privacy mode | hash/raw/none configuration | Section 4 |
| Memory cap (max history messages) | wpaic_privacy_memory_cap_messages option | Section 3 |
| GDPR data export | POST /privacy/export endpoint | Section 6 |
| GDPR data erasure | POST /privacy/erase endpoint | Section 7 |
| Data retention configuration | wpaic_privacy_retention_days + cron cleanup | Section 8 |

**Status**: CFI-SEC-003 fully resolved.

### CFI-SEC-004 Resolution
| Requirement | Resolution | Section |
|-------------|-----------|---------|
| Structured audit logging | Audit_Logger::log() routes to lib/structured-logger.php | Section 9 |
| Event taxonomy | 15 EVENT_* constants covering all security-relevant actions | Section 10 |
| Log schema | JSON entry format with subsystem=audit tag | Section 11 |
| Retention policy | Cron-based cleanup aligned with log_retention_days option | Section 12 |
| Privacy in logs | Explicit rule: no raw IPs, no message content, no key values | Section 11 |
| Admin log viewer | Diagnostics > Logs panel via /diagnostics/logs REST endpoint | Section 12 |

**Status**: CFI-SEC-004 fully resolved.

---

## 14. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Daily message limit via transient | `axiachat-ai/includes/usage.php` | Adopted; keyed by ip_hash |
| IP extraction and privacy mode | `axiachat-ai/includes/usage.php` IP handling | Adopted; added hash mode |
| Memory cap (max history truncation) | `axiachat-ai/includes/class-aichat-ajax.php` | Adopted |
| Structured audit log format | `axiachat-ai/includes/logs.php` | Adopted; routed via lib/structured-logger.php |
| Data erasure pattern | `baachal/uninstall.php` (clean uninstall reference) | Adapted for per-user erasure |
| GDPR consent gating | `axiachat-ai/assets/js/aichat-frontend.js` consent bubble | Adapted for admin config |
| Usage limiting with daily reset | `mxchat-basic/includes/class-mxchat-admin.php` (daily limits) | Adopted logic; transient storage |
