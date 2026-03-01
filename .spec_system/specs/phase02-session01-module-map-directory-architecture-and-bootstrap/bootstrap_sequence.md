# Bootstrap Sequence

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

The bootstrap sequence defines the deterministic order in which the plugin initializes. It
follows a phased approach:

1. **Phase A**: Constants and autoloader (immediate, on file load)
2. **Phase B**: Core infrastructure loading (immediate, after autoloader)
3. **Phase C**: Hook registration (deferred to WordPress action hooks)
4. **Phase D**: Conditional module loading (deferred, context-dependent)

This design is inspired by:
- **Baachal**: Filterable hook array with `foreach` registration (centralized, discoverable)
- **AxiaChat**: Sequential includes with constants-first ordering
- **MxChat**: Text domain loading, cache exclusions, conditional loading

---

## 2. Bootstrap Phases

### Phase A: Constants and Autoloader (wp-ai-chatbot.php, line 1-50)

**Trigger**: WordPress loads the plugin file.
**Timing**: Before any WordPress hook fires.

```
1. ABSPATH guard (exit if accessed directly)
2. Define WPAIC_VERSION (hardcoded string, not get_file_data)
3. Define WPAIC_MIN_PHP, WPAIC_MIN_WP
4. Define WPAIC_TEXT_DOMAIN
5. Define WPAIC_PLUGIN_DIR, WPAIC_INCLUDES_DIR
6. Define WPAIC_PLUGIN_URL, WPAIC_ASSETS_URL, WPAIC_ADMIN_URL
7. Define WPAIC_PREFIX, WPAIC_REST_NAMESPACE, WPAIC_BASENAME
8. Define WPAIC_DEBUG, WPAIC_LOG_RETENTION_DAYS
9. Define WPAIC_DEFAULT_RATE_LIMIT, WPAIC_DEFAULT_RATE_WINDOW
10. Require includes/autoloader.php (registers spl_autoload)
```

**Design decision**: Version is hardcoded (like AxiaChat) rather than parsed from plugin header
(like MxChat) because `get_file_data()` performs file I/O on every request. The version constant
must match the plugin header -- enforced by release workflow.

### Phase B: Core Infrastructure (wp-ai-chatbot.php, line 51-80)

**Trigger**: Immediately after Phase A.
**Timing**: Before any WordPress hook fires.

```
11. Require lib/structured-logger.php (error capture, always available)
12. PHP version check: if PHP < WPAIC_MIN_PHP, show admin notice and return
13. WP version check: if WP < WPAIC_MIN_WP, show admin notice and return
14. Instantiate WPAIC\Plugin (main orchestrator)
15. Call Plugin::register_hooks() -- queues all WordPress hooks
```

**Design decision**: The structured logger loads before version checks so that errors during
bootstrap are captured. Version check failures produce a visible admin notice and halt
initialization -- the plugin does not silently fail.

### Phase C: Hook Registration (Plugin::register_hooks())

**Trigger**: Called from Phase B.
**Timing**: Registers hooks; callbacks fire when WordPress reaches each action.

The hook registration follows Baachal's centralized filterable array pattern:

```php
$hooks = apply_filters('wpaic_register_hooks', [
    // Lifecycle
    'register_activation_hook'   => [Lifecycle_Manager, 'activate'],
    'register_deactivation_hook' => [Lifecycle_Manager, 'deactivate'],

    // Init phase (priority 10)
    'init'                       => [$this, 'on_init'],
    'plugins_loaded'             => [$this, 'on_plugins_loaded'],

    // Admin hooks (conditional)
    'admin_init'                 => [$this, 'on_admin_init'],
    'admin_menu'                 => [Admin_Router, 'register_menus'],
    'admin_enqueue_scripts'      => [Admin_Router, 'enqueue_assets'],

    // REST API
    'rest_api_init'              => [$this, 'on_rest_api_init'],

    // Frontend hooks (conditional)
    'wp_enqueue_scripts'         => [$this, 'on_frontend_enqueue'],
    'wp_footer'                  => [$this, 'on_frontend_footer'],

    // AJAX handlers
    'wp_ajax_wpaic_chat'         => [Message_Pipeline, 'handle_ajax'],
    'wp_ajax_nopriv_wpaic_chat'  => [Message_Pipeline, 'handle_ajax'],
]);

foreach ($hooks as $hook => $callback) {
    add_action($hook, $callback);
}
```

### Phase D: Conditional Loading (triggered by action hooks)

**Trigger**: WordPress action hooks from Phase C.
**Timing**: Varies per context.

#### D1. `plugins_loaded` (priority 10)

```
1. Load text domain (i18n)
2. Run migration check: Migration_Registry::check_and_run()
   - Compares stored version with WPAIC_VERSION
   - Runs pending migrations in order
   - Updates stored version
3. Fire wpaic_loaded action (signals plugin is ready)
```

#### D2. `init` (priority 10)

```
1. Register shortcodes ([wpaic_chatbot])
2. Detect WooCommerce: class_exists('WooCommerce')
   - If WC active: fire wpaic_wc_init action (deferred domain registration point)
   - If WC active: declare HPOS compatibility
3. Fire wpaic_init action (extension registration point)
```

#### D3. `admin_init` (priority 10)

```
1. Register settings via Settings_Manager
2. Initialize per-page controllers for current admin page
3. Fire wpaic_admin_ready action
```

**Conditional**: Only runs in admin context (`is_admin()`).

#### D4. `rest_api_init` (priority 10)

```
1. Load lib/health-endpoint.php (register /health route)
2. Load lib/rate-limiter.php
3. Load lib/backup-manager.php (register /backup routes)
4. Register domain REST routes (chat, sessions, settings, knowledge, diagnostics)
```

#### D5. `wp_enqueue_scripts` (priority 10)

```
1. Check if chatbot should display on current page
2. If yes: enqueue assets/css/chatbot.css
3. If yes: enqueue assets/js/chatbot.js with wp_localize_script
```

**Conditional**: Only enqueues if the chatbot is configured to display on the current page
(global, specific pages, or shortcode-only mode).

---

## 3. Hook Priority Map

Complete priority map for all registered hooks:

| Hook | Callback | Priority | Module |
|------|----------|----------|--------|
| `plugins_loaded` | `Plugin::on_plugins_loaded` | 10 | Core |
| `init` | `Plugin::on_init` | 10 | Core |
| `admin_init` | `Plugin::on_admin_init` | 10 | Core |
| `admin_menu` | `Admin_Router::register_menus` | 10 | Admin |
| `admin_enqueue_scripts` | `Admin_Router::enqueue_assets` | 10 | Admin |
| `rest_api_init` | `Plugin::on_rest_api_init` | 10 | Core |
| `wp_enqueue_scripts` | `Plugin::on_frontend_enqueue` | 10 | Core |
| `wp_footer` | `Plugin::on_frontend_footer` | 10 | Core |
| `wp_ajax_wpaic_chat` | `Message_Pipeline::handle_ajax` | 10 | Runtime |
| `wp_ajax_nopriv_wpaic_chat` | `Message_Pipeline::handle_ajax` | 10 | Runtime |
| `before_woocommerce_init` | HPOS compatibility declaration | 10 | Core |

### Internal hook priorities (within plugin callbacks):

| Hook | Priority | Purpose |
|------|----------|---------|
| `wpaic_before_message_send` | 5 | Security moderation check |
| `wpaic_before_message_send` | 10 | Context injection |
| `wpaic_after_message_send` | 20 | Audit logging |
| `wpaic_rag_results` | 15 | WooCommerce product enrichment |
| `wpaic_register_providers` | 10 | Core provider registration |
| `wpaic_register_providers` | 15 | Extension provider registration |

---

## 4. Initialization State Diagram

```
  wp-ai-chatbot.php loaded
          |
          v
  [Phase A] Constants + Autoloader
          |
          v
  [Phase B] Logger + Version Checks
          |
     PHP/WP OK?
    /         \
  NO           YES
   |            |
   v            v
  Admin      Instantiate Plugin
  Notice     Register Hooks [Phase C]
  + return         |
                   v
            WordPress event loop
           /    |     |      \
          v     v     v       v
       plugins  init  admin   rest_api
       _loaded        _init   _init
          |      |      |       |
          v      v      v       v
       i18n    short   settings REST
       migrate codes   pages    routes
       wpaic   WC det  assets   health
       _loaded          |       rate
                        v       backup
                     wpaic
                     _admin
                     _ready
```

---

## 5. Conditional Loading Paths

### 5.1 Admin Context (`is_admin() === true`)

Loaded: admin/, includes/admin/, all includes/{domain}/ (via autoloader on-demand)
Not loaded: assets/js/chatbot.js, assets/css/chatbot.css, templates/

### 5.2 Frontend Context (`is_admin() === false`)

Loaded: assets/, templates/, includes/runtime/, includes/provider/ (on-demand)
Not loaded: admin/, includes/admin/pages/ (not triggered)

### 5.3 REST API Context

Loaded: lib/ (health, rate limiter, backup), includes/ domains as needed
Not loaded: admin/ assets, frontend assets

### 5.4 AJAX Context (`wp_doing_ajax() === true`)

Loaded: includes/runtime/, includes/provider/, includes/security/ (on-demand)
Not loaded: admin/ views, frontend assets

### 5.5 WooCommerce Detection

```php
// In Plugin::on_init(), priority 10
if (class_exists('WooCommerce')) {
    // Declare HPOS compatibility
    add_action('before_woocommerce_init', function () {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables', WPAIC_BASENAME, true
            );
        }
    });

    // Fire deferred domain registration hook
    do_action('wpaic_wc_init');
}
```

**Source**: Baachal's `before_woocommerce_init` HPOS pattern.

---

## 6. Plugin Lifecycle Hooks (CFI-MIG-001)

### 6.1 Activation

**Trigger**: `register_activation_hook(__FILE__, ...)`
**Handler**: `Migration\Lifecycle_Manager::activate()`

```
1. Check PHP version (bail with wp_die if incompatible)
2. Check WP version (bail with wp_die if incompatible)
3. Run Migration_Registry::run_all_pending()
4. Create custom database tables (if needed)
5. Set default options (only if not already set)
6. Schedule cron events
7. Fire wpaic_activated action (extension hook)
8. Flush rewrite rules
```

### 6.2 Deactivation

**Trigger**: `register_deactivation_hook(__FILE__, ...)`
**Handler**: `Migration\Lifecycle_Manager::deactivate()`

```
1. Unschedule all cron events
2. Fire wpaic_deactivated action (extension hook)
3. Flush rewrite rules
```

**Note**: Data is preserved on deactivation. Tables and options are NOT deleted.

### 6.3 Uninstall

**Trigger**: `register_uninstall_hook(__FILE__, ...)` or `uninstall.php` fallback
**Handler**: `Migration\Lifecycle_Manager::uninstall()` (static)

```
1. Check data preservation flag: get_option('wpaic_preserve_data')
   - If true: skip data deletion, only remove the flag
   - If false or not set: proceed with full cleanup

2. Fire wpaic_uninstalling action (extension cleanup hook)
   - Each module registers cleanup via wpaic_uninstall_cleanup filter

3. Delete custom database tables
4. Delete all options with wpaic_ prefix
5. Delete all transients with wpaic_ prefix
6. Remove uploaded files (knowledge base content)
7. Remove scheduled cron events (safety net)
8. Remove any user meta with wpaic_ prefix
```

**Source**: Baachal's three-hook coverage with data preservation flag (`baachal/uninstall.php`).

### 6.4 Extension Lifecycle Hooks

| Hook | When | Use Case |
|------|------|---------|
| `wpaic_activated` | After activation completes | Extensions create their own tables/options |
| `wpaic_deactivated` | After deactivation | Extensions clean up scheduled tasks |
| `wpaic_uninstalling` | Before uninstall cleanup | Extensions register cleanup callbacks |
| `wpaic_uninstall_cleanup` (filter) | During uninstall | Extensions add table/option names to cleanup list |
| `wpaic_register_migrations` | During migration check | Extensions register versioned migrations |

---

## 7. Migration Runner Entry Points (CFI-MIG-001)

### 7.1 Automatic Migration Check

Runs on every `plugins_loaded` hook:

```
1. Read stored version: get_option('wpaic_migration_version', '0.0.0')
2. Compare with WPAIC_VERSION
3. If versions differ:
   a. Acquire migration lock (transient-based, 5-minute TTL)
   b. Get pending migrations from Migration_Registry
   c. Run each migration in version order
   d. Log execution results
   e. Update stored version
   f. Release lock
4. If versions match: skip (no-op)
```

### 7.2 Migration Registration

Domains register migrations via the `wpaic_register_migrations` action:

```php
// Example: Runtime module registers session table migration
add_action('wpaic_register_migrations', function ($registry) {
    $registry->register('1.0.0', 'create_sessions_table', function () {
        // Create wp_wpaic_sessions table
    });
    $registry->register('1.1.0', 'add_token_tracking', function () {
        // ALTER TABLE wp_wpaic_sessions ADD COLUMN tokens_used INT
    });
});
```

### 7.3 Migration Safety

| Safety Feature | Implementation |
|---------------|---------------|
| Idempotent | Each migration checks state before modifying (e.g., `IF NOT EXISTS`) |
| Ordered | Migrations run in semver order, never out of sequence |
| Logged | Each execution recorded in `wp_wpaic_migrations` table |
| Locked | Transient-based lock prevents concurrent migration runs |
| Resumable | Failed migrations are logged; re-run attempts the failed migration first |

---

## 8. Cache Exclusion Strategy

Following MxChat's pattern, the plugin registers cache exclusions for popular caching plugins
during `init`:

```
1. WP Rocket: Exclude from RUCSS, CSS/JS minification
2. LiteSpeed Cache: Exclude from optimization
3. Autoptimize: Exclude from aggregation
4. WP Super Cache: Exclude dynamic pages
5. W3 Total Cache: Exclude from minification
```

This prevents caching plugins from breaking the chatbot's real-time streaming and AJAX
functionality.
