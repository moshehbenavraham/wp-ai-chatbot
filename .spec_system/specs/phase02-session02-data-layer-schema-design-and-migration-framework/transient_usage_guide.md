# Transient Usage Guide

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

Transients provide short-lived cached data using the WordPress Transients API. The plugin uses transients for four purposes: rate limiting, caching, processing locks, and temporary UI state. All transient keys use the `wpaic_` prefix per naming conventions.

**Key design principle**: Transients are ephemeral. The plugin MUST function correctly if any transient is missing or expired. Critical state (sessions, tool execution state) uses database tables, not transients -- this follows the AxiaChat pattern where `aichat_tool_states` was moved from transients to a DB table to avoid object cache race conditions.

---

## 2. Rate Limiting Transients

Rate limiting uses the existing `lib/rate-limiter.php` implementation with per-endpoint namespacing.

| Key Pattern | Example | TTL | Purpose |
|-------------|---------|-----|---------|
| `wpaic_rate_{endpoint}_{ip_hash}` | `wpaic_rate_chat_a1b2c3d4` | 60s (configurable) | Per-IP request count for an endpoint |
| `wpaic_rate_block_{ip_hash}` | `wpaic_rate_block_a1b2c3d4` | 300s | Temporary IP block after limit exceeded |

### 2.1 IP Hash Generation

IP addresses are hashed for privacy: `substr(md5($ip_address), 0, 8)`

This provides sufficient uniqueness for rate limiting while not storing raw IP addresses in transient keys (which would be visible in the options table on sites without persistent object cache).

### 2.2 Source Anchors

| Source | Pattern | Adopted |
|--------|---------|---------|
| `lib/rate-limiter.php` | Existing rate limit implementation | Retained as-is |
| `axiachat-ai/axiachat-ai.php` | `aichat_rl_{ip}` rate limit transients | Pattern adopted |
| `axiachat-ai/axiachat-ai.php` | `aichat_block_{ip}` block transients | Pattern adopted |
| `mxchat-basic/mxchat-basic.php` | Per-role rate limits in options | Adopted: role limits stored in options, enforcement via transients |

---

## 3. Caching Transients

Caching transients store computed or fetched data that is expensive to regenerate.

| Key Pattern | Example | TTL | Purpose | Domain |
|-------------|---------|-----|---------|--------|
| `wpaic_cache_models` | `wpaic_cache_models` | 3600s (1h) | Cached model registry from provider API | provider |
| `wpaic_cache_health` | `wpaic_cache_health` | 300s (5m) | Cached health check results | infrastructure |
| `wpaic_cache_products_{hash}` | `wpaic_cache_products_f7a3b1` | 3600s (1h) | WooCommerce product search results (deferred) | woocommerce |
| `wpaic_cache_terms` | `wpaic_cache_terms` | 86400s (24h) | WooCommerce dynamic terms cache (deferred) | woocommerce |
| `wpaic_cache_index_stats` | `wpaic_cache_index_stats` | 3600s (1h) | Knowledge index statistics | rag |

### 3.1 Cache Invalidation

Caching transients are invalidated explicitly when the underlying data changes:

| Trigger | Transients Invalidated |
|---------|----------------------|
| Model registry update | `wpaic_cache_models` |
| Health check request | `wpaic_cache_health` |
| Knowledge re-index | `wpaic_cache_index_stats` |
| Product save/delete (WC) | `wpaic_cache_products_*` (wildcard delete) |
| Term/attribute change (WC) | `wpaic_cache_terms` |

Wildcard deletion uses a direct database query:
```sql
DELETE FROM {$wpdb->options}
WHERE option_name LIKE '_transient_wpaic_cache_products_%'
   OR option_name LIKE '_transient_timeout_wpaic_cache_products_%'
```

### 3.2 Source Anchors

| Source | Pattern | Adopted |
|--------|---------|---------|
| `baachal/baachal.php` | `baachal_dynamic_terms`, `baachal_products_*` caches | Pattern adopted with `wpaic_cache_` prefix |
| `baachal/baachal.php` | Hook-triggered invalidation on product/term changes | Pattern adopted |

---

## 4. Processing Lock Transients

Lock transients prevent concurrent execution of exclusive operations.

| Key Pattern | Example | TTL | Purpose | Domain |
|-------------|---------|-----|---------|--------|
| `wpaic_temp_migration_lock` | `wpaic_temp_migration_lock` | 300s (5m) | Prevent concurrent migration runs | migration |
| `wpaic_temp_ingest_lock_{id}` | `wpaic_temp_ingest_lock_42` | 600s (10m) | Prevent concurrent ingestion for same knowledge context | rag |
| `wpaic_temp_cleanup_lock` | `wpaic_temp_cleanup_lock` | 300s (5m) | Prevent concurrent retention cleanup runs | admin |

### 4.1 Lock Value Format

Lock transient values encode the owner for debugging: `{hostname}:{pid}:{timestamp}`

### 4.2 Stale Lock Recovery

All lock transients have TTLs. If a process crashes while holding a lock, the transient expires and the next process can acquire it. The structured logger records lock acquisition failures.

### 4.3 Object Cache Warning

On sites with persistent object caches, lock transients may not provide cross-server mutual exclusion. Operations protected by transient locks MUST be idempotent to handle this safely.

---

## 5. Temporary State Transients

Short-lived state that supports UI flows or admin notices.

| Key Pattern | Example | TTL | Purpose | Domain |
|-------------|---------|-----|---------|--------|
| `wpaic_temp_notice_{type}` | `wpaic_temp_notice_success` | 60s | Admin flash notice (success/error/info) | admin |
| `wpaic_temp_export_{hash}` | `wpaic_temp_export_a1b2c3` | 600s (10m) | Temporary export file reference | admin |
| `wpaic_temp_prechat_{hash}` | `wpaic_temp_prechat_f7a3b1` | 86400s (24h) | Track pre-chat message shown to visitor | runtime |

### 5.1 Source Anchors

| Source | Pattern | Adopted |
|--------|---------|---------|
| `mxchat-basic/mxchat-basic.php` | `mxchat_admin_notice_*` transients (60s TTL) | Pattern adopted |
| `mxchat-basic/includes/class-mxchat-public.php` | `mxchat_prechat_message_shown_*` transients | Pattern adopted |

---

## 6. TTL Policies

### 6.1 TTL by Category

| Category | Default TTL | Rationale |
|----------|-------------|-----------|
| Rate limiting | 60s (window) / 300s (block) | Matches rate limit window; block is 5x window |
| Caching (API data) | 3600s (1h) | Balance between freshness and API call reduction |
| Caching (computed data) | 300-3600s | Depends on computation cost |
| Caching (WC products) | 3600s (1h) | Product data changes infrequently; hook-triggered invalidation |
| Processing locks | 300-600s | Must exceed maximum expected operation duration |
| Admin notices | 60s | Shown once then cleared |
| Temporary state | 600-86400s | Depends on user flow duration |

### 6.2 Configurable TTLs

The following TTLs are configurable via options:

| Option Key | Default | Controls |
|------------|---------|----------|
| `wpaic_security_rate_window` | 60 | Rate limit window (seconds) |
| `wpaic_security_block_duration` | 300 | Rate limit block duration (seconds) |
| `wpaic_rag_cache_ttl` | 3600 | Knowledge index stats cache |

All other TTLs are hardcoded constants. They can be overridden via filter hooks for extensibility:

```php
$ttl = apply_filters('wpaic_transient_ttl', $default_ttl, $transient_key);
```

---

## 7. Cleanup Triggers

### 7.1 Deactivation Cleanup

On plugin deactivation (`Lifecycle_Manager::deactivate()`), all transients with the `wpaic_` prefix are deleted:

```sql
DELETE FROM {$wpdb->options}
WHERE option_name LIKE '_transient_wpaic_%'
   OR option_name LIKE '_transient_timeout_wpaic_%'
```

This follows the Baachal pattern (`baachal/baachal.php` deactivation cleanup).

### 7.2 Uninstall Cleanup

On plugin uninstall (if data preservation is disabled), transients are deleted as part of the staged cleanup sequence defined in `uninstall_behavior_contract.md`.

### 7.3 Object Cache Compatibility

| Scenario | Behavior |
|----------|----------|
| No persistent object cache | Transients stored in `wp_options` table; wildcard DELETE works |
| Persistent object cache (Redis/Memcached) | Transients stored in object cache; wildcard DELETE has no effect |
| Persistent cache + deactivation | Object cache entries expire via TTL; `wp_cache_flush()` is NOT called (too destructive) |

**Important**: The plugin does not call `wp_cache_flush()` during deactivation or uninstall because it would clear the entire site's object cache, affecting other plugins and WordPress core.

For sites with persistent object caches, transient cleanup relies on TTL expiration. This is acceptable because:
- All transients have bounded TTLs (max 24 hours)
- Transient data is non-critical (plugin functions without it)
- Object cache memory is managed by the cache service's eviction policy

### 7.4 Avoiding Transient Abuse

Based on lessons from the reference plugins:

| Anti-Pattern | Source | Our Approach |
|-------------|--------|-------------|
| Session state in transients | MxChat `mxchat_history_{session_id}` | Session state in `wp_wpaic_sessions` table |
| Tool execution state in transients | (not in sources, but a risk) | Tool state in DB (following AXI `aichat_tool_states` pattern) |
| Unbounded transient creation | MxChat per-session options/transients | Bounded by session count + TTL expiration |
| No cleanup on deactivation | MxChat (no deactivation handler) | Wildcard DELETE on deactivation |

---

## 8. Transient Key Summary

| Key Pattern | Category | TTL | Domain |
|-------------|----------|-----|--------|
| `wpaic_rate_{endpoint}_{ip_hash}` | Rate limiting | 60s | security |
| `wpaic_rate_block_{ip_hash}` | Rate limiting | 300s | security |
| `wpaic_cache_models` | Caching | 3600s | provider |
| `wpaic_cache_health` | Caching | 300s | infrastructure |
| `wpaic_cache_index_stats` | Caching | 3600s | rag |
| `wpaic_cache_products_{hash}` | Caching | 3600s | woocommerce (deferred) |
| `wpaic_cache_terms` | Caching | 86400s | woocommerce (deferred) |
| `wpaic_temp_migration_lock` | Lock | 300s | migration |
| `wpaic_temp_ingest_lock_{id}` | Lock | 600s | rag |
| `wpaic_temp_cleanup_lock` | Lock | 300s | admin |
| `wpaic_temp_notice_{type}` | Temporary state | 60s | admin |
| `wpaic_temp_export_{hash}` | Temporary state | 600s | admin |
| `wpaic_temp_prechat_{hash}` | Temporary state | 86400s | runtime |
