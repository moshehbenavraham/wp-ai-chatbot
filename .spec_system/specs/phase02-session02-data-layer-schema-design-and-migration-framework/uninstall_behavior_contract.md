# Uninstall Behavior Contract

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

The uninstall behavior contract defines the complete data cleanup sequence when the plugin is deleted from WordPress. It follows the Baachal winner pattern (`baachal/uninstall.php`) with admin-configurable data preservation and extension domain cleanup hooks.

The uninstall handler runs from `uninstall.php` at the plugin root (WordPress convention). It delegates to `Migration\Lifecycle_Manager::uninstall()` if the autoloader is available, with a fallback standalone implementation for safety.

**Implementation files**:
- `uninstall.php` -- WordPress entry point (standalone fallback)
- `includes/migration/class-lifecycle-manager.php` -- Primary implementation

---

## 2. Data Preservation Flag

### 2.1 Option Behavior

| Option | Key | Default | Effect |
|--------|-----|---------|--------|
| Preserve data | `wpaic_admin_preserve_data` | `true` (checked) | When `true`: uninstall deletes only the plugin files, not user data |

**Default is `true`** (preserve data). This is a safety-first design -- users must explicitly opt out of data preservation before uninstalling. This prevents accidental data loss when users deactivate and delete the plugin for troubleshooting.

### 2.2 Admin UI

The data preservation toggle appears on the admin settings page under "Advanced":

```
[ ] Delete all plugin data when uninstalling
    Warning: This will permanently delete all chat history, knowledge bases,
    bot configurations, and plugin settings. This action cannot be undone.
```

Note: The checkbox is **inverted** in the UI (checked = delete data) while the option stores the preservation flag (true = preserve). This follows UX convention where destructive actions require affirmative opt-in.

### 2.3 Preservation Mode Behavior

When `wpaic_admin_preserve_data` is `true`:

```
1. Log that uninstall was called with preservation enabled
2. Delete ONLY the preservation flag option itself
3. Exit without deleting any other data
```

All tables, options, transients, and uploaded files are preserved. The user can reinstall the plugin and recover their data.

---

## 3. Cleanup Sequence

When `wpaic_admin_preserve_data` is `false` (user opted to delete all data), the following staged cleanup runs:

### Stage 1: Extension Notification

```php
do_action('wpaic_uninstalling');
```

This action fires BEFORE any data is deleted. Extension domains (tools, integrations, woocommerce) register cleanup callbacks:

```php
add_action('wpaic_uninstalling', function () {
    // Clean up extension-specific tables and data
});
```

### Stage 2: Extension Cleanup Collection

```php
$cleanup_tables = apply_filters('wpaic_uninstall_cleanup', [
    // MVP tables already listed
]);
```

Extensions append their table names to the cleanup list:

```php
add_filter('wpaic_uninstall_cleanup', function (array $tables): array {
    $tables[] = 'wpaic_tools';
    $tables[] = 'wpaic_macros';
    $tables[] = 'wpaic_tool_calls';
    $tables[] = 'wpaic_tool_states';
    $tables[] = 'wpaic_webhooks';
    return $tables;
});
```

### Stage 3: Drop Custom Tables

```php
$tables = [
    'wpaic_messages',      // Child records first (referential order)
    'wpaic_sessions',
    'wpaic_chunks',
    'wpaic_queue',
    'wpaic_knowledge',
    'wpaic_usage_daily',
    'wpaic_bots',
    'wpaic_migrations',
    // + extension tables from Stage 2
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}
```

**Order matters**: Child tables (messages, chunks) are dropped before parent tables (sessions, knowledge) to avoid FK constraint issues on databases that enforce them.

### Stage 4: Delete Options

```php
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpaic_%'"
);
```

This deletes all plugin options in a single query. The `wpaic_` prefix ensures no WordPress core options are affected.

### Stage 5: Delete Transients

```php
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wpaic_%'
        OR option_name LIKE '_transient_timeout_wpaic_%'"
);
```

### Stage 6: Remove Uploaded Files

```php
$upload_dir = wp_upload_dir();
$plugin_upload_dir = $upload_dir['basedir'] . '/wp-ai-chatbot/';
if (is_dir($plugin_upload_dir)) {
    // Recursively delete upload directory
    // Uses wp_delete_file() for individual files
}
```

This removes any knowledge base files (PDFs, documents) uploaded through the plugin.

### Stage 7: Remove Cron Events

```php
wp_clear_scheduled_hook('wpaic_cleanup_retention');
wp_clear_scheduled_hook('wpaic_index_content');
// Extension cron events
$cron_hooks = apply_filters('wpaic_uninstall_cron_hooks', [
    'wpaic_cleanup_retention',
    'wpaic_index_content',
]);
foreach ($cron_hooks as $hook) {
    wp_clear_scheduled_hook($hook);
}
```

### Stage 8: Remove User Meta

```php
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpaic_%'"
);
```

This removes any per-user preferences or dismissal flags stored in user meta.

---

## 4. Extension Domain Hooks

| Hook | Type | When | Purpose |
|------|------|------|---------|
| `wpaic_uninstalling` | action | Before any cleanup | Extensions perform their own cleanup |
| `wpaic_uninstall_cleanup` | filter | During table collection | Extensions add table names to drop list |
| `wpaic_uninstall_cron_hooks` | filter | During cron cleanup | Extensions add cron hook names to clear |

### 4.1 Extension Registration Example

```php
// In includes/tools/class-tool-registry.php (deferred domain)
add_action('wpaic_uninstalling', function () {
    global $wpdb;
    // Clean up tool-specific transients or cache
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_wpaic_tool_%'"
    );
});

add_filter('wpaic_uninstall_cleanup', function (array $tables): array {
    $tables[] = 'wpaic_tools';
    $tables[] = 'wpaic_macros';
    $tables[] = 'wpaic_tool_calls';
    $tables[] = 'wpaic_tool_states';
    return $tables;
});
```

---

## 5. Safety Guarantees

### 5.1 No Orphaned Data

The staged cleanup sequence ensures all data artifacts are removed:

| Artifact Type | Cleanup Stage | Mechanism |
|---------------|--------------|-----------|
| Custom tables | Stage 3 | `DROP TABLE IF EXISTS` |
| WordPress options | Stage 4 | `DELETE WHERE option_name LIKE 'wpaic_%'` |
| Transients | Stage 5 | `DELETE WHERE option_name LIKE '_transient_wpaic_%'` |
| Uploaded files | Stage 6 | Recursive directory deletion |
| Cron events | Stage 7 | `wp_clear_scheduled_hook()` |
| User meta | Stage 8 | `DELETE WHERE meta_key LIKE 'wpaic_%'` |

### 5.2 No Accidental Deletion

- Default is **preserve data** (`wpaic_admin_preserve_data = true`)
- Destructive action requires explicit admin opt-in
- UI uses inverted checkbox with warning text
- Uninstall checks the flag BEFORE performing any cleanup

### 5.3 Multisite Safety

On multisite WordPress installations:
- Uninstall runs per-site (each site has its own `$wpdb->prefix`)
- Network deactivation unscheduled cron events for the current site only
- `DROP TABLE` uses `{$wpdb->prefix}` which includes the site-specific prefix (e.g., `wp_2_wpaic_sessions`)

### 5.4 Fallback Implementation

`uninstall.php` contains a standalone fallback that does not depend on the autoloader:

```php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Try to use the class-based implementation
if (file_exists(__DIR__ . '/includes/autoloader.php')) {
    require_once __DIR__ . '/includes/autoloader.php';
    WPAIC\Migration\Lifecycle_Manager::uninstall();
    return;
}

// Fallback: inline cleanup (same logic, no class dependencies)
$preserve = get_option('wpaic_admin_preserve_data', true);
if ($preserve) {
    delete_option('wpaic_admin_preserve_data');
    return;
}
// ... inline cleanup stages ...
```

---

## 6. Deactivation vs Uninstall

| Action | Deactivation | Uninstall |
|--------|-------------|-----------|
| Tables | Preserved | Dropped (if preservation disabled) |
| Options | Preserved | Deleted (if preservation disabled) |
| Transients | Deleted (wildcard) | Deleted (wildcard) |
| Cron events | Unscheduled | Unscheduled |
| Uploaded files | Preserved | Deleted (if preservation disabled) |
| User meta | Preserved | Deleted (if preservation disabled) |
| Rewrite rules | Flushed | N/A (plugin no longer active) |

**Key distinction**: Deactivation is reversible (reactivate to restore). Uninstall with preservation disabled is permanent.

---

## 7. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Data preservation flag + full cleanup | `baachal/uninstall.php` | Adopted: `baachal_preserve_data_on_uninstall` pattern |
| Three-hook lifecycle coverage | `baachal/baachal.php` | Adopted: activate/deactivate/uninstall |
| Minimal uninstall (anti-pattern) | `axiachat-ai/axiachat-ai.php` | Replaced: deletes only 3 options, keeps tables |
| No uninstall handler (anti-pattern) | `mxchat-basic/mxchat-basic.php` | Replaced: full cleanup implemented |
| Fallback uninstall.php | `baachal/uninstall.php` | Adopted: standalone fallback for safety |
| Wildcard transient deletion | `baachal/baachal.php` | Adopted: `DELETE WHERE LIKE` pattern |
