# Admin Settings Architecture

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-ADM-001 (decomposition), CFI-ADM-004 (operational controls)

---

## 1. Overview

The admin settings layer decomposes MxChat's monolithic 9036-line `class-mxchat-admin.php` into
a structured multi-page architecture following AxiaChat's per-section pattern with Baachal's
tab-based layout for complex pages. Each per-page controller is capped at 600 LOC (CFI-ADM-001).

The architecture has three layers:
1. **Router** (`class-admin-router.php`): registers WP admin menu, dispatches page load to controller
2. **Settings Manager** (`class-settings-manager.php`, session 02): WP Settings API, encrypt/decrypt
3. **Page Controllers** (`admin/pages/class-*-page.php`): per-page render + save logic

---

## 2. Admin Menu Structure

All plugin admin pages register under the top-level menu slug `uwpaic`. The menu icon is a
dashicon or custom SVG loaded in the admin enqueue hook.

```
wp-admin/admin.php?page=uwpaic                 # General Settings (default)
wp-admin/admin.php?page=uwpaic-providers       # Providers and Models
wp-admin/admin.php?page=uwpaic-chat            # Chat Behavior and Appearance
wp-admin/admin.php?page=uwpaic-knowledge       # Knowledge Base and RAG
wp-admin/admin.php?page=uwpaic-security        # Security, Moderation, and Privacy
wp-admin/admin.php?page=uwpaic-tools           # Tools and Integrations (stub)
wp-admin/admin.php?page=uwpaic-diagnostics     # Diagnostics and Usage
```

**Capability**: All pages require `manage_options` (or filtered via `wpaic_admin_capability`).

---

## 3. Admin Router Contract

```php
namespace WPAIC\Admin;

class Admin_Router {

    /**
     * Register the admin menu tree. Called on admin_menu action.
     */
    public function register_menus(): void;

    /**
     * Enqueue admin assets (CSS + JS) for the current plugin page.
     * Called on admin_enqueue_scripts action.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_assets( string $hook ): void;

    /**
     * Route the current page request to the appropriate page controller.
     * Called via page callback in add_submenu_page().
     *
     * @param string $page_slug  Current page slug (e.g., 'uwpaic-providers').
     */
    public function dispatch( string $page_slug ): void;

    /**
     * Register a page controller for a slug.
     * Called by page controllers during their static register() method.
     *
     * @param string          $page_slug   Menu page slug.
     * @param Admin_Page      $controller  Page controller instance.
     */
    public function register_page( string $page_slug, Admin_Page $controller ): void;
}
```

---

## 4. Admin Page Controller Base Contract

All per-page controllers extend the abstract `Admin_Page` base class:

```php
namespace WPAIC\Admin;

abstract class Admin_Page {

    /**
     * Return the page slug this controller handles.
     */
    abstract public function get_slug(): string;

    /**
     * Return the page title (used in <title> and <h1>).
     */
    abstract public function get_title(): string;

    /**
     * Register WP Settings API settings for this page.
     * Called on admin_init.
     */
    abstract public function register_settings(): void;

    /**
     * Render the full page HTML.
     * Loads template from admin/views/{slug}.php.
     */
    abstract public function render(): void;

    /**
     * Return ordered array of tabs for tabbed pages.
     * Empty array = no tabs.
     *
     * @return array<string, string>  ['tab_slug' => 'Tab Label']
     */
    public function get_tabs(): array { return []; }

    /**
     * Enqueue page-specific assets (called by router on enqueue_assets).
     */
    public function enqueue_page_assets(): void {}
}
```

---

## 5. Per-Page Controller Specifications

### 5.1 General Settings (`class-general-page.php`)

**Slug**: `uwpaic`
**LOC target**: < 300
**Tabs**: None (single-page)
**Settings groups**: `wpaic_general_settings`
**Options covered**: All `wpaic_provider_*` options (10 keys from options schema)
**Key interactions**: Provider selection conditionally reveals matching credential fields (BAA
pattern from `baachal/assets/admin.js`). Model list is populated via JS after provider selection.

---

### 5.2 Providers and Models (`class-providers-page.php`)

**Slug**: `uwpaic-providers`
**LOC target**: < 600
**Tabs**: One tab per provider: `openai`, `claude`, `gemini`, `deepseek`, `grok`, `voyage`
**Settings groups**: `wpaic_provider_{slug}_settings` (one per provider)
**Options covered**: Provider API keys [E], model selection, per-provider timeout overrides
**Key interactions**:
- "Test connection" button per provider (AJAX: `wp_ajax_wpaic_provider_test`)
- API key field: masked display (`****...last4`), clear button, re-enter to update
- Model list: fetched from model registry (populated from `class-model-registry.php`)

---

### 5.3 Chat Behavior (`class-chat-page.php`)

**Slug**: `uwpaic-chat`
**LOC target**: < 500
**Tabs**: `behavior`, `appearance`, `widget`, `shortcode`
**Settings groups**: `wpaic_runtime_settings`, `wpaic_widget_settings`
**Options covered**: All `wpaic_runtime_*` options (10 keys), widget/shortcode config options
**Key interactions**: Live preview pane (renders chat widget in iframe with current settings)

---

### 5.4 Knowledge Base (`class-knowledge-page.php`)

**Slug**: `uwpaic-knowledge`
**LOC target**: < 600
**Tabs**: `sources`, `queue`, `settings`, `test`
**Settings groups**: `wpaic_rag_settings`
**Options covered**: All `wpaic_rag_*` options; delegated to session 05 knowledge admin contract
**Key interactions**: Source list (CRUD), queue dashboard (REST polling), test-search console

---

### 5.5 Security, Moderation, and Privacy (`class-security-page.php`)

**Slug**: `uwpaic-security`
**LOC target**: < 600
**Tabs**: `moderation`, `privacy`, `audit`, `encryption`
**Settings groups**: `wpaic_security_settings`, `wpaic_privacy_settings`
**Options covered**: All moderation options (11 keys), all `wpaic_security_*` options
**Key interactions**:
- Moderation layer enable/disable toggles (per-layer)
- Banned word list textarea (auto-save on blur)
- Privacy export button (AJAX -> REST -> ZIP download)
- Erasure form (token-based, with confirmation dialog)
- Audit log viewer (paginated table, filter by event type)
- Encryption status indicator (OpenSSL version, key status, last rotation date)

---

### 5.6 Tools and Integrations (`class-tools-page.php`)

**Slug**: `uwpaic-tools`
**LOC target**: < 200 (stub)
**Status**: Deferred to Phase 07. Renders a "Coming soon" placeholder with documentation link.

---

### 5.7 Diagnostics (`class-diagnostics-page.php`)

**Slug**: `uwpaic-diagnostics`
**LOC target**: < 400
**Tabs**: `overview`, `streaming_test`, `usage`, `logs`
**Key interactions**: SSE streaming test, usage charts, log viewer
**See**: `diagnostics_dashboard_spec.md` for full specification

---

## 6. WP Settings API Integration

Each page controller calls `register_setting()` and `add_settings_section()` / `add_settings_field()`
in its `register_settings()` method (called on `admin_init`). The settings manager transparently
handles encryption for [E]-marked fields.

### 6.1 Settings Group Naming Convention

```
wpaic_{domain}_settings    # e.g., wpaic_provider_openai_settings
```

### 6.2 Sanitize Callback Pattern

All `register_setting()` calls specify a `sanitize_callback` that routes through `Sanitizer`:

```php
register_setting( 'wpaic_runtime_settings', 'wpaic_runtime_max_tokens', [
    'type'              => 'integer',
    'sanitize_callback' => fn($v) => Sanitizer::sanitize_int( $v, 1, 128000 ),
    'default'           => 2048,
] );
```

### 6.3 Conditional Field Reveal

Provider-specific fields are shown/hidden based on the selected provider using vanilla JS
(no jQuery). The pattern follows Baachal's `admin.js` approach:

```js
// In admin/js/admin.js:
document.getElementById('wpaic_provider_default').addEventListener('change', function() {
    const selected = this.value;
    document.querySelectorAll('[data-provider]').forEach(function(el) {
        el.style.display = el.dataset.provider === selected ? '' : 'none';
    });
});
```

HTML template structure:
```html
<div class="wpaic-provider-fields" data-provider="openai">...</div>
<div class="wpaic-provider-fields" data-provider="claude" style="display:none">...</div>
```

---

## 7. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Per-section admin decomposition (< 600 LOC per file) | `axiachat-ai/includes/class-aichat-admin.php` section pattern | Adopted; formalized as page controller base class |
| Tab-based admin layout | `baachal/admin/settings-page.php` | Adopted |
| Conditional field reveal on provider change | `baachal/assets/admin.js` | Adopted; converted to vanilla JS |
| WP Settings API register_setting pattern | `mxchat-basic/includes/class-mxchat-admin.php` | Adopted; decomposed from monolith |
| Admin menu registration with capability | `mxchat-basic/mxchat-basic.php` | Adopted; filtered via wpaic_admin_capability |
| Masked API key display (last4 chars) | `axiachat-ai/includes/class-aichat-admin.php` | Adopted |
| Monolith admin (9036-line god-class) | `mxchat-basic/includes/class-mxchat-admin.php` | Replaced by decomposed per-page controllers |
