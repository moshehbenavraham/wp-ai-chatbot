# Data Retention Policy

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

Data retention policies define how long each type of data is kept and how cleanup is scheduled. The design follows GDPR data minimization principles: data is retained only as long as it serves a stated purpose, with configurable retention periods where appropriate.

**Cleanup mechanism**: WP-Cron scheduled event (`wpaic_cleanup_retention`) running daily.

**Owner module**: `includes/admin/` (cron scheduling and configuration) with cleanup logic distributed to domain modules via `wpaic_retention_cleanup` action.

---

## 2. Per-Data-Type Retention Rules

| Data Type | Table/Storage | Default Retention | Configurable | Min | Max | Rationale |
|-----------|--------------|-------------------|-------------|-----|-----|-----------|
| Chat sessions | `wp_wpaic_sessions` | 90 days | Yes | 7 days | 365 days | Balance between user experience (session recall) and privacy |
| Chat messages | `wp_wpaic_messages` | Follows session | No | - | - | Messages are deleted when their parent session is deleted |
| Knowledge contexts | `wp_wpaic_knowledge` | Indefinite | No | - | - | Admin-created content; deleted only manually or on uninstall |
| Content chunks | `wp_wpaic_chunks` | Indefinite | No | - | - | Tied to knowledge context lifecycle |
| Ingestion queue | `wp_wpaic_queue` | 7 days after completion | No | - | - | Completed/failed items are historical noise after 7 days |
| Usage daily | `wp_wpaic_usage_daily` | 365 days | Yes | 30 days | Indefinite | Annual cost reporting; configurable for compliance needs |
| Bot configuration | `wp_wpaic_bots` | Indefinite | No | - | - | Admin-created configuration; manual lifecycle |
| Migration log | `wp_wpaic_migrations` | Indefinite | No | - | - | Small table (~50 rows); audit trail for debugging |
| Transients | WordPress transients | Per-TTL (see transient guide) | No | - | - | Self-expiring via WordPress TTL mechanism |
| Options | WordPress options | Indefinite | No | - | - | Configuration data; removed only on uninstall |

---

## 3. Cleanup Schedule

### 3.1 Cron Event Registration

On activation, the plugin schedules a daily cron event:

```php
if (!wp_next_scheduled('wpaic_cleanup_retention')) {
    wp_schedule_event(time(), 'daily', 'wpaic_cleanup_retention');
}
```

On deactivation, the cron event is unscheduled:

```php
wp_clear_scheduled_hook('wpaic_cleanup_retention');
```

### 3.2 Cleanup Execution

The cron callback acquires a lock (`wpaic_temp_cleanup_lock` transient, 5-minute TTL) and performs cleanup in this order:

```
1. Acquire cleanup lock (skip if already held)
2. Clean expired sessions:
   a. Find sessions with expires_at < NOW() OR
      (status='active' AND updated_at < NOW() - retention_period)
   b. Delete associated messages first (referential integrity)
   c. Delete sessions
   d. Log count of deleted sessions
3. Clean completed queue items:
   a. DELETE FROM wp_wpaic_queue
      WHERE status IN ('completed', 'failed')
      AND completed_at < NOW() - INTERVAL 7 DAY
4. Clean stalled queue items:
   a. UPDATE wp_wpaic_queue SET status='stalled'
      WHERE status='processing'
      AND started_at < NOW() - INTERVAL 10 MINUTE
5. Clean old usage data (if configured):
   a. DELETE FROM wp_wpaic_usage_daily
      WHERE date < NOW() - INTERVAL {retention_days} DAY
6. Fire wpaic_retention_cleanup action (extension domains register custom cleanup)
7. Release cleanup lock
```

### 3.3 Session Expiration

When a session is created, `expires_at` is set based on the configured retention period:

```php
$retention_days = (int) get_option('wpaic_admin_session_retention', 90);
$expires_at = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));
```

Active sessions that receive new messages have their `updated_at` and `expires_at` refreshed, preventing cleanup of ongoing conversations.

---

## 4. Admin Configuration

### 4.1 Configurable Retention Options

| Option Key | Default | Type | Description |
|------------|---------|------|-------------|
| `wpaic_admin_session_retention` | `90` | `int` (days) | Chat session retention period |
| `wpaic_admin_usage_retention` | `365` | `int` (days) | Usage data retention period (0 = indefinite) |

These options appear in the admin security/privacy settings page.

### 4.2 Immediate Cleanup

Admin can trigger an immediate retention cleanup via the diagnostics page:

```
POST /wp-json/wp-ai-chatbot/v1/diagnostics/cleanup
Authorization: manage_options capability
```

This fires the same `wpaic_cleanup_retention` cron callback immediately.

---

## 5. GDPR Compliance

### 5.1 Data Minimization

- Chat messages store only the content needed for conversation history
- IP addresses are stored as binary (VARBINARY(16)) and can be anonymized via `wpaic_security_ip_privacy` option
- User agent is hashed (SHA-256), not stored in plaintext
- Token counts and costs are aggregated in `usage_daily`, not linked to individual users

### 5.2 Right to Deletion

Users can request deletion of their data. The plugin supports:

1. **Self-service**: Logged-in users can clear their own sessions via the chat widget "Clear history" button
2. **Admin-initiated**: Admin can delete all data for a specific user via:
   ```sql
   DELETE FROM wp_wpaic_messages WHERE session_id IN
     (SELECT id FROM wp_wpaic_sessions WHERE user_id = ?)
   DELETE FROM wp_wpaic_sessions WHERE user_id = ?
   ```
3. **Bulk export/delete**: Admin REST endpoints for data export (existing `lib/backup-manager.php`) and user-specific deletion

### 5.3 Data Processing Inventory

Per GDPR Article 30, the following personal data is processed:

| Data | Purpose | Legal Basis | Retention |
|------|---------|-------------|-----------|
| IP address (binary) | Session ownership, rate limiting, abuse prevention | Legitimate interest | Session retention period |
| User agent hash | Session ownership validation | Legitimate interest | Session retention period |
| WordPress user ID | Session ownership for logged-in users | Legitimate interest | Session retention period |
| Chat messages | Conversation history, AI response generation | Consent (GDPR overlay) or legitimate interest | Configurable (default 90 days) |
| Usage statistics | Cost tracking, capacity planning | Legitimate interest | Configurable (default 365 days) |

### 5.4 IP Anonymization

When `wpaic_security_ip_privacy` is enabled:
- New sessions store `NULL` for `ip_address`
- Rate limiting uses session-scoped hashes instead of IP addresses
- Existing IP data is anonymized on next cleanup run

---

## 6. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Cron-based cleanup | `axiachat-ai/includes/aichat-cron.php` | Adopted: daily scheduled cleanup |
| No retention (anti-pattern) | `baachal/baachal.php`, `mxchat-basic/mxchat-basic.php` | Replaced: configurable retention periods |
| Data preservation flag | `baachal/uninstall.php` | Adopted: `wpaic_admin_preserve_data` option |
