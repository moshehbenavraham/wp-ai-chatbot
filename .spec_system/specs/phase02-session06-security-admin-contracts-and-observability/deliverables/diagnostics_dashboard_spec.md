# Diagnostics Dashboard Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-ADM-003 (diagnostics dashboard), CFI-ADM-005 (admin UX vanilla JS)

---

## 1. Overview

The diagnostics dashboard (`admin.php?page=uwpaic-diagnostics`) provides four panels: health
overview, streaming test console, usage analytics, and log viewer. The panel architecture
uses a hosting pattern (RES-XD-010): other modules register panels via the
`wpaic_diagnostics_panels` filter, allowing decoupled diagnostic surfaces for future domains.

Admin UX is vanilla JS (no jQuery) per the CFI-ADM-005 / CFI-RUN-004 circular dependency
resolution. Auto-save uses a 200ms debounce + 800ms success state indicator (MxChat pattern).

**Owner module**: `includes/admin/class-diagnostics.php`
**Template**: `admin/views/diagnostics.php`
**Namespace**: `WPAIC\Admin\Diagnostics`

---

## 2. Panel Registry

### 2.1 Panel Registration Hook

```php
// Panels register on the wpaic_diagnostics_panels filter.
// Called in class-diagnostics.php render() before loading template.

add_filter( 'wpaic_diagnostics_panels', function ( array $panels ): array {
    $panels['my_domain'] = [
        'label'    => __( 'My Domain', 'wp-ai-chatbot' ),
        'priority' => 50,
        'callback' => [ My_Domain_Diagnostics::class, 'render_panel' ],
    ];
    return $panels;
} );
```

### 2.2 Built-In Panels

| Panel ID | Label | Priority | Provided By |
|----------|-------|----------|------------|
| `health` | System Health | 10 | `class-diagnostics.php` |
| `streaming_test` | Streaming Test | 20 | `class-diagnostics.php` |
| `usage` | Usage and Costs | 30 | `class-diagnostics.php` |
| `logs` | Recent Logs | 40 | `class-diagnostics.php` |

### 2.3 Panel Callback Contract

```php
// Panel callback signature:
function render_panel(): void;
// Outputs panel HTML directly (called inside template via ob_start/ob_get_clean for safety).
```

---

## 3. Health Overview Panel

Aggregates health data from multiple subsystems into a single status grid.

### 3.1 Health Data Sources

| Check | Source | Status Values |
|-------|--------|--------------|
| PHP version | `phpversion()` | ok / warn (< 8.0) / error (< 7.4) |
| WordPress version | `get_bloginfo('version')` | ok / warn (< 6.0) / error (< 5.0) |
| OpenSSL extension | `extension_loaded('openssl')` | ok / error |
| DB connectivity | `$wpdb->check_connection()` | ok / error |
| Plugin DB schema | compare installed schema version | ok / needs_migration |
| Encryption key | `Encryption::get_key_status()` | ok / warn (using fallback) / error |
| Active provider | test-call to active provider | ok / warn (slow) / error |
| Rate limiter | `lib/rate-limiter.php` status | ok / warn (high hit rate) |
| Queue backlog | `wp_wpaic_queue` pending count | ok (0) / warn (>10) / error (>100) |
| Log file writable | `is_writable(WPAIC_PLUGIN_DIR . 'logs/')` | ok / warn |

### 3.2 Health Endpoint Integration

The health panel reuses data from `lib/health-endpoint.php` (GET `/wp-json/wp-ai-chatbot/v1/health`).
The diagnostics page JS fetches this endpoint and renders the result. This avoids duplicating
health check logic.

---

## 4. Streaming Test Console

Allows administrators to send a test message to the active provider and observe the SSE stream in
real-time in the admin panel.

### 4.1 UI Flow

1. Admin selects provider and model from dropdowns (populated from model registry)
2. Enters test message in textarea
3. Clicks "Run Test" button
4. JS opens SSE connection to `wp-ajax.php?action=wpaic_diagnostics_panel` with `stream=1`
5. Streamed chunks appear in the console output box in real-time
6. Test completes with total tokens, latency, and cost estimate displayed

### 4.2 Backend Contract

```
POST wp-ajax.php?action=wpaic_diagnostics_panel
Auth: nonce wpaic_diagnostics_stream + manage_options
Params: {provider: string, model: string, message: string, stream: '1'}
Response: SSE stream (text/event-stream)
  data: {"chunk": "...", "type": "delta"}
  data: {"usage": {"prompt_tokens": N, "completion_tokens": N}, "type": "done"}
  data: {"error": "...", "type": "error"}
```

### 4.3 JS Contract

```js
// In admin/js/admin.js:
function wpaic_run_streaming_test(provider, model, message) {
    const evtSource = new EventSource(ajaxurl + '?action=wpaic_diagnostics_panel&stream=1&nonce=...');
    evtSource.onmessage = function(e) {
        const data = JSON.parse(e.data);
        if (data.type === 'delta') appendToConsole(data.chunk);
        if (data.type === 'done') showUsage(data.usage);
        if (data.type === 'error') showError(data.error);
        if (data.type === 'done' || data.type === 'error') evtSource.close();
    };
}
```

---

## 5. Usage Analytics Panel

Displays aggregated token usage and estimated cost metrics.

### 5.1 Data Sources

| Metric | Source |
|--------|--------|
| Total tokens this month | Aggregate of `wp_wpaic_messages.token_count` |
| Total cost estimate this month | Aggregate of `wp_wpaic_messages.cost_usd` |
| Tokens by provider | Grouped by `wp_wpaic_sessions.provider` |
| Daily usage trend (30 days) | `wp_wpaic_messages` grouped by date |
| Active sessions last 24h | COUNT from `wp_wpaic_sessions` |
| Average response latency | Average of `wp_wpaic_messages.latency_ms` |

### 5.2 Data Endpoint

```
GET /wp-json/wp-ai-chatbot/v1/diagnostics/usage
Auth: manage_options
Query: ?period=30d (default) | 7d | 90d
Response: {total_tokens, total_cost_usd, by_provider: [...], daily: [...], avg_latency_ms}
```

### 5.3 Display

Simple HTML table with optional sparkline chart (rendered via pure CSS or minimal canvas).
No external chart library dependency. Chart library deferred to Phase 09.

---

## 6. Log Viewer Panel

Displays recent structured log entries from `logs/` directory.

### 6.1 Log File Reading

```php
// Reads most recent log file from logs/ directory:
$log_files = glob( WPAIC_PLUGIN_DIR . 'logs/app-*.log' );
rsort( $log_files ); // Most recent first
$lines = file( $log_files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
$recent = array_slice( array_reverse($lines), 0, 200 ); // Last 200 lines
```

### 6.2 Log Viewer REST Endpoint

```
GET /wp-json/wp-ai-chatbot/v1/diagnostics/logs
Auth: manage_options
Query: ?limit=50&level=error|warn|info (default: all, limit 200)
Response: {entries: [{timestamp, level, subsystem, message, context}], total}
```

### 6.3 UI Controls

- Level filter dropdown (all / error / warn / info / debug)
- Subsystem filter (all / runtime / provider / rag / security / admin)
- Auto-refresh toggle (polls every 10 seconds when enabled)
- Download logs link (triggers `lib/backup-manager.php` log export)

---

## 7. Admin UX Vanilla JS Contract (CFI-ADM-005)

All admin JavaScript is loaded from `admin/js/admin.js` as a single IIFE module. No jQuery
dependency. The script is enqueued conditionally: only on plugin admin pages.

### 7.1 Auto-Save Pattern

```js
// 200ms debounce on input; 800ms success state (MxChat pattern)
function wpaic_autosave(input) {
    clearTimeout(input._timer);
    input._timer = setTimeout(function() {
        const data = new FormData();
        data.append('action', 'wp_ajax_wpaic_autosave');
        data.append('nonce', wpaic_admin.nonce);
        data.append('key', input.name);
        data.append('value', input.value);
        // Show inline spinner
        fetch(wpaic_admin.ajaxurl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(function(res) {
                if (res.success) showSuccessIndicator(input, 800);
            });
    }, 200);
}
```

### 7.2 Toast Notification System

```js
// wpaic_toast(message, type='success'|'error'|'warn', duration=3000)
function wpaic_toast(message, type, duration) {
    const el = document.createElement('div');
    el.className = 'wpaic-toast wpaic-toast--' + type;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, duration);
}
```

### 7.3 Admin Script Localization

```php
wp_localize_script( 'wpaic-admin', 'wpaic_admin', [
    'ajaxurl'   => admin_url( 'admin-ajax.php' ),
    'nonce'     => wp_create_nonce( Auth_Enforcer::nonce_action( 'admin_settings' ) ),
    'rest_url'  => rest_url( 'wp-ai-chatbot/v1/' ),
    'rest_nonce'=> wp_create_nonce( 'wp_rest' ),
    'i18n'      => [
        'saved'       => __( 'Saved', 'wp-ai-chatbot' ),
        'save_failed' => __( 'Save failed. Please try again.', 'wp-ai-chatbot' ),
        'confirm_del' => __( 'Are you sure? This cannot be undone.', 'wp-ai-chatbot' ),
    ],
] );
```

### 7.4 WordPress i18n Integration

Admin JS uses `wp.i18n` functions (`__()`, `_n()`) for strings that require translation:

```js
// After enqueueing wp-i18n as dependency:
const { __ } = wp.i18n;
const label = __( 'Save settings', 'wp-ai-chatbot' );
```

---

## 8. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Diagnostics panel hosting pattern | `axiachat-ai/includes/class-aichat-admin.php` panel section | Adopted; converted to filter-based registry |
| SSE streaming test in admin | `axiachat-ai/includes/class-aichat-ajax.php` stream test | Adopted; moved to dedicated diagnostics endpoint |
| Usage metrics aggregation | `axiachat-ai/includes/usage.php` | Adopted; queries adapted to WPAIC tables |
| Auto-save with 800ms success state | `mxchat-basic/js/mxchat-admin.js` auto-save pattern | Adopted verbatim |
| Vanilla JS admin (no jQuery) | `baachal/assets/admin.js` | Adopted; base for wpaic admin.js |
| wp.i18n for admin JS strings | `mxchat-basic/js/mxchat-admin.js` (wp.i18n calls) | Adopted |
| Conditional field reveal | `baachal/assets/admin.js` | Adopted; generalized for all providers |
