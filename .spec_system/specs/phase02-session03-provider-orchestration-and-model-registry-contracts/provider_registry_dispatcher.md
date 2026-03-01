# Provider Registry Dispatcher

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

The Provider Registry is the singleton+factory that manages the lifecycle of all AI provider adapters. It handles registration, instantiation, caching, alias resolution, and enable/disable toggling. All provider access in the plugin flows through this registry -- no direct adapter instantiation.

Design adopted from AxiaChat's `AIChat_Provider_Registry` with refinements for the unified plugin architecture.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/class-aichat-provider-registry.php` | Primary: Singleton+factory pattern, register/get/is_available API, instance caching by ID+config hash, get_all/get_stats |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | Anti-pattern: Inline switch-case dispatch with duplicated provider logic (N/A -- not adopted) |
| Baachal | `baachal/baachal.php` | Anti-pattern: Single function with if/elseif provider routing (N/A -- not adopted) |

---

## 2. Class Design

### Namespace and Location

```
Namespace: WPAIC\Provider
File:      includes/provider/class-provider-registry.php
Class:     Provider_Registry
```

### Singleton Pattern

```php
<?php
namespace WPAIC\Provider;

/**
 * Provider_Registry
 *
 * Singleton factory for AI provider adapters. Manages registration,
 * instantiation, caching, and lifecycle of all provider adapters.
 *
 * Usage:
 *   $registry = Provider_Registry::instance();
 *   $adapter  = $registry->get( 'openai' );
 *   $response = $adapter->chat( $messages, $params );
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
class Provider_Registry {

    /**
     * @var Provider_Registry|null Singleton instance.
     */
    private static $instance = null;

    /**
     * @var array Registered provider definitions.
     *            Key: provider ID (string).
     *            Value: array { class_name: string, enabled: bool }
     */
    private $providers = array();

    /**
     * @var array Cached adapter instances.
     *            Key: cache key (provider_id + config hash).
     *            Value: Provider_Interface instance.
     */
    private $cache = array();

    /**
     * @var array Alias normalization map.
     *            Key: alias string.
     *            Value: canonical provider ID.
     */
    private $aliases = array(
        'anthropic' => 'claude',
        'google'    => 'gemini',
        'xai'       => 'grok',
    );

    /**
     * @var bool Whether the registration hook has fired.
     */
    private $initialized = false;

    /**
     * Get the singleton instance.
     *
     * @return Provider_Registry
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor -- use instance().
     */
    private function __construct() {}
}
```

---

## 3. Registration API

### Hook-Driven Registration

Providers register during the `wpaic_register_providers` action, fired once during plugin bootstrap:

```php
// In wp-ai-chatbot.php bootstrap sequence:
do_action( 'wpaic_register_providers', Provider_Registry::instance() );
```

Each provider adapter registers itself:

```php
// In includes/provider/adapters/class-openai-adapter.php (or bootstrap):
add_action( 'wpaic_register_providers', function( $registry ) {
    $registry->register( 'openai', 'WPAIC\Provider\Adapters\OpenAI_Adapter' );
} );
```

### register() Method

```php
/**
 * Register a provider adapter class.
 *
 * @param string $id         Canonical provider ID (lowercase, [a-z0-9-]).
 * @param string $class_name Fully qualified class name implementing Provider_Interface.
 * @param bool   $enabled    Whether the provider is initially enabled (default: true).
 * @param bool   $force      Force re-registration if ID exists (default: false).
 *
 * @return bool True if registered, false if rejected.
 */
public function register( $id, $class_name, $enabled = true, $force = false );
```

**Validation rules** (from `provider_interface_contract.md` CR-01 through CR-05):

1. `$id` must be non-empty string matching `[a-z0-9-]+`
2. `$class_name` must be a valid class that implements `Provider_Interface`
3. No duplicate IDs unless `$force = true`
4. On validation failure: `_doing_it_wrong()` notice, return `false`

**Registration flow**:

```
register($id, $class_name, $enabled)
  |
  +-> Validate $id format (CR-04)
  +-> Check class_exists($class_name) -- autoload if needed
  +-> Verify implements Provider_Interface (CR-01)
  +-> Check for duplicate $id (CR-05, skip if $force)
  +-> Store: $this->providers[$id] = array(
  |       'class_name' => $class_name,
  |       'enabled'    => $enabled,
  |   )
  +-> do_action('wpaic_provider_registered', $id, $class_name)
  +-> return true
```

### Filter: wpaic_provider_aliases

The alias map is filterable for extensibility:

```php
$this->aliases = apply_filters( 'wpaic_provider_aliases', $this->aliases );
```

---

## 4. Retrieval API

### get() Method

```php
/**
 * Get a provider adapter instance.
 *
 * Returns a cached instance if available and $cached is true.
 * Otherwise creates a new instance via factory pattern.
 *
 * Config is resolved from wp_options via Settings_Manager if not
 * provided explicitly.
 *
 * @param string $id     Provider ID (aliases resolved automatically).
 * @param array  $config Optional config override. If empty, resolved from Settings_Manager.
 * @param bool   $cached Whether to use instance cache (default: true).
 *
 * @return Provider_Interface|null Adapter instance, or null if unavailable.
 */
public function get( $id, $config = array(), $cached = true );
```

**Resolution flow**:

```
get($id, $config, $cached)
  |
  +-> Ensure initialized (fire wpaic_register_providers if not yet)
  +-> Normalize $id via alias map
  +-> Check provider exists and is enabled
  |     -> Return null if not found or disabled
  +-> Resolve $config from Settings_Manager if empty:
  |     $config = array(
  |         'api_key'     => Settings_Manager::get("wpaic_provider_{$id}_key"),
  |         'timeout'     => Settings_Manager::get('wpaic_provider_timeout', 30),
  |         'max_retries' => Settings_Manager::get('wpaic_provider_max_retries', 3),
  |     )
  |     $config = apply_filters("wpaic_provider_{$id}_config", $config)
  +-> Compute cache key: "{$id}_" . md5(wp_json_encode($config))
  +-> If $cached and cache hit: return cached instance
  +-> Instantiate: $adapter = new $class_name($config)
  +-> Validate get_id() matches $id (CR-03)
  +-> Store in cache
  +-> return $adapter
```

### is_available() Method

```php
/**
 * Check if a provider is registered, enabled, and has a valid API key.
 *
 * @param string $id Provider ID (aliases resolved).
 *
 * @return bool True if the provider can be used for requests.
 */
public function is_available( $id );
```

**Availability requires**:
1. Provider is registered
2. Provider is enabled
3. API key is non-empty (checked via `Settings_Manager::get("wpaic_provider_{$id}_key")`)

### list_providers() Method

```php
/**
 * List all registered providers.
 *
 * @param bool $enabled_only Only return enabled providers (default: false).
 *
 * @return array {
 *     Provider information indexed by ID.
 *
 *     @type string $id         Canonical provider ID.
 *     @type string $class_name Fully qualified class name.
 *     @type bool   $enabled    Whether the provider is enabled.
 *     @type bool   $available  Whether the provider has a valid API key.
 *     @type bool   $streamable Whether the provider supports streaming.
 * }
 */
public function list_providers( $enabled_only = false );
```

The `streamable` flag is determined by checking if the registered class implements `Streamable_Provider_Interface`.

---

## 5. Instance Caching

### Cache Key Strategy

Cache keys combine provider ID and a hash of the config array:

```php
$cache_key = $id . '_' . md5( wp_json_encode( $config ) );
```

This allows multiple instances of the same provider with different configurations (e.g., different API keys for testing vs. production).

### Cache Lifecycle

| Event | Cache Behavior |
|-------|---------------|
| `get($id)` with cache hit | Return cached instance |
| `get($id)` with cache miss | Create, cache, return |
| `get($id, $config, false)` | Skip cache, create new instance |
| `clear_cache($id)` | Remove all cached instances for provider |
| `clear_cache()` | Clear entire cache |
| Provider disabled | Cache NOT cleared (lazy invalidation on next `get()`) |
| Settings change | Consumer should call `clear_cache($id)` |

### clear_cache() Method

```php
/**
 * Clear cached provider instances.
 *
 * @param string|null $id Provider ID to clear, or null to clear all.
 */
public function clear_cache( $id = null );
```

When `$id` is provided, removes all cache entries whose key starts with `{$id}_`.

---

## 6. Enable/Disable Toggling

### set_enabled() Method

```php
/**
 * Enable or disable a registered provider.
 *
 * Disabled providers return null from get() and false from is_available().
 * Enable/disable state is runtime-only (not persisted to wp_options).
 * Admin UI controls persist via the provider's API key presence.
 *
 * @param string $id      Provider ID.
 * @param bool   $enabled Whether to enable or disable.
 *
 * @return bool True if state changed, false if provider not found.
 */
public function set_enabled( $id, $enabled );
```

### Provider Availability Logic

A provider is "available" when ALL conditions are met:

```
is_available($id):
  1. $id is registered (after alias resolution)
  2. $providers[$id]['enabled'] === true
  3. Settings_Manager::get("wpaic_provider_{$id}_key") is non-empty
```

The admin UI can also check `is_available()` to show configuration status indicators.

---

## 7. Lazy Initialization

The registry fires the registration hook on first access to avoid unnecessary work on requests that don't use AI providers:

```php
private function ensure_initialized() {
    if ( $this->initialized ) {
        return;
    }
    $this->initialized = true;
    $this->aliases = apply_filters( 'wpaic_provider_aliases', $this->aliases );
    do_action( 'wpaic_register_providers', $this );
}
```

This method is called at the start of `get()`, `is_available()`, and `list_providers()`.

---

## 8. Registry Statistics

### get_stats() Method

```php
/**
 * Return registry statistics for diagnostics.
 *
 * Used by the health endpoint and admin diagnostics page.
 *
 * @return array {
 *     @type int   $total_registered Total registered providers.
 *     @type int   $total_enabled    Enabled providers count.
 *     @type int   $total_available  Available providers (enabled + API key).
 *     @type int   $cache_size       Number of cached instances.
 *     @type array $providers        Per-provider status array.
 * }
 */
public function get_stats();
```

---

## 9. Hook Reference

| Hook | Type | Fired When | Parameters |
|------|------|-----------|------------|
| `wpaic_register_providers` | Action | First registry access (lazy init) | `Provider_Registry $registry` |
| `wpaic_provider_registered` | Action | After successful registration | `string $id`, `string $class_name` |
| `wpaic_provider_aliases` | Filter | During lazy init | `array $aliases` |
| `wpaic_provider_{$id}_config` | Filter | During adapter instantiation | `array $config` |

---

## 10. Error Handling

| Scenario | Behavior |
|----------|----------|
| Unknown provider ID | `get()` returns `null`, logs debug notice |
| Disabled provider | `get()` returns `null` |
| Class not found | `register()` returns `false`, `_doing_it_wrong()` notice |
| Interface not implemented | `register()` returns `false`, `_doing_it_wrong()` notice |
| ID mismatch (get_id() != $id) | `get()` logs error, returns `null`, clears bad cache entry |
| Empty API key | `is_available()` returns `false` |

---

## 11. Testability Notes

### Unit Testable

- Registration with valid/invalid classes
- Alias resolution (anthropic -> claude, google -> gemini, xai -> grok)
- Instance caching (same config = same instance, different config = different instance)
- Enable/disable toggling
- Compliance validation rules (CR-01 through CR-05)
- `get_stats()` output shape

### Integration Testable

- Hook-driven registration via `wpaic_register_providers`
- Config resolution from Settings_Manager
- Provider filter `wpaic_provider_{$id}_config`
- Full lifecycle: register -> get -> chat -> cost

---
