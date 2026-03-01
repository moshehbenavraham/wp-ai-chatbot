# Unified Sanitization Library Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-SEC-001

---

## 1. Overview

The unified sanitization library (`includes/security/class-sanitizer.php`) provides domain-specific
input sanitizers as a centralized, dependency-injected registry. It merges AxiaChat's domain
sanitizers (the Phase 01 winner) with Baachal's bounds-checking and MxChat's field-level
validation patterns.

Every domain module calls `Sanitizer::sanitize($value, $domain)` or a typed method alias.
No domain module implements its own sanitization inline. This prevents the fragmented
sanitization pattern found in reference plugins (each file doing ad hoc `sanitize_text_field`
calls without domain context).

**Owner module**: `includes/security/class-sanitizer.php`
**Namespace**: `WPAIC\Security\Sanitizer`

---

## 2. Sanitizer Registry

The library provides a static registry of sanitizer functions indexed by domain type. The registry
can be extended via the `wpaic_sanitizer_types` filter (compliance rule SCR-04 below).

### 2.1 Built-In Sanitizer Types

| Type Constant | Method Alias | Input | Validation Rules | Output | Source Anchor |
|---------------|-------------|-------|-----------------|--------|--------------|
| `TYPE_MESSAGE` | `sanitize_message()` | User chat message | Max 10,000 chars; strip null bytes; no PHP tags; no `<script>` | `string` | `axiachat-ai/includes/sanitize-helpers.php:sanitize_user_message()` |
| `TYPE_SYSTEM_PROMPT` | `sanitize_system_prompt()` | Bot instruction text | Max 50,000 chars; strip null bytes; allow template tokens `{date}`, `{url}`; no `<script>` | `string` | `axiachat-ai/includes/sanitize-helpers.php:sanitize_system_prompt()` |
| `TYPE_API_KEY` | `sanitize_api_key()` | Provider or Pinecone API key | Max 200 chars; alphanumeric + `-_.:` only; strip whitespace | `string` | `axiachat-ai/includes/settings.php` |
| `TYPE_SLUG` | `sanitize_slug()` | Model slug, provider slug, source type | Max 64 chars; `[a-z0-9_-]` only; lowercase | `string` | `axiachat-ai/includes/sanitize-helpers.php:sanitize_model_slug()` |
| `TYPE_INT` | `sanitize_int()` | Integer settings (limits, counts, timeouts) | `absint()` + min/max bounds passed as params; default 0 on invalid | `int` | `axiachat-ai/includes/sanitize-helpers.php:sanitize_positive_int()` |
| `TYPE_FLOAT` | `sanitize_float()` | Temperature, similarity threshold | `floatval()` + min/max bounds; default `null` on invalid | `float\|null` | (derived from AXI bounds pattern) |
| `TYPE_BOOL` | `sanitize_bool()` | Toggle settings | `'1'`, `'true'`, `true`, `1` -> `true`; all else -> `false` | `bool` | MxChat `absint()` pattern extended |
| `TYPE_URL` | `sanitize_url()` | Pinecone endpoint, webhook URL | `esc_url_raw()`; HTTPS-only; domain allowlist via `wpaic_allowed_domains` filter | `string` | `axiachat-ai/includes/sanitize-helpers.php:sanitize_endpoint_url()` |
| `TYPE_HTML_RESTRICTED` | `sanitize_html()` | Bot description, tooltip text | `wp_kses()` with restricted allowlist (no script/style/iframe); max 2,000 chars | `string` | `axiachat-ai/includes/sanitize-helpers.php:sanitize_rich_text()` |
| `TYPE_FILENAME` | `sanitize_filename()` | Log file names, export file names | `sanitize_file_name()`; strip path traversal `../`; max 128 chars | `string` | (WP core pattern) |
| `TYPE_IP` | `sanitize_ip()` | Client IP from headers | IPv4/IPv6 format validation; reject private ranges if IP privacy mode active | `string\|null` | `axiachat-ai/includes/usage.php` IP extraction |
| `TYPE_JSON` | `sanitize_json()` | Stored serialized config | `json_decode()` + re-encode; reject if non-array/object; max 64KB | `string` | (defensive deserialization, BAA pattern) |

### 2.2 HTML Restricted Allowlist

The `TYPE_HTML_RESTRICTED` allowlist used with `wp_kses()`:

```php
const HTML_ALLOWLIST = [
    'a'      => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
    'b'      => [],
    'em'     => [],
    'i'      => [],
    'strong' => [],
    'ul'     => [],
    'ol'     => [],
    'li'     => [],
    'p'      => [],
    'br'     => [],
    'code'   => [],
    'pre'    => [],
    'blockquote' => [],
];
```

---

## 3. Primary Interface

```php
namespace WPAIC\Security;

class Sanitizer {

    /**
     * Sanitize a value using a registered type.
     *
     * @param mixed       $value   Raw input value.
     * @param string      $type    One of the TYPE_* constants.
     * @param array       $params  Type-specific params (e.g., ['min' => 0, 'max' => 100] for TYPE_INT).
     * @return mixed               Sanitized value; type guaranteed per type constant.
     */
    public static function sanitize( $value, string $type, array $params = [] );

    /**
     * Validate and sanitize; return WP_Error on validation failure.
     *
     * @param mixed       $value
     * @param string      $type
     * @param array       $params
     * @return mixed|\WP_Error    Sanitized value or WP_Error with code 'wpaic_invalid_input'.
     */
    public static function validate( $value, string $type, array $params = [] );

    /**
     * Sanitize an associative array of values using a type map.
     *
     * @param array<string, mixed>  $data     Associative array of raw inputs.
     * @param array<string, array>  $type_map Array of ['field' => ['type' => TYPE_*, 'params' => [...]]]
     * @return array<string, mixed>           Sanitized values for all fields in type_map (extras stripped).
     */
    public static function sanitize_map( array $data, array $type_map ): array;

    /**
     * Register a custom sanitizer type.
     * Called via wpaic_sanitizer_types filter.
     *
     * @param string   $type      Type constant string.
     * @param callable $callback  function($value, array $params): mixed
     */
    public static function register_type( string $type, callable $callback ): void;

    // --- Typed method aliases (preferred for readability) ---

    public static function sanitize_message( string $value ): string;
    public static function sanitize_system_prompt( string $value ): string;
    public static function sanitize_api_key( string $value ): string;
    public static function sanitize_slug( string $value ): string;
    public static function sanitize_int( $value, int $min = 0, int $max = PHP_INT_MAX ): int;
    public static function sanitize_float( $value, float $min = 0.0, float $max = PHP_FLOAT_MAX ): ?float;
    public static function sanitize_bool( $value ): bool;
    public static function sanitize_url( string $value ): string;
    public static function sanitize_html( string $value ): string;
    public static function sanitize_ip( string $value ): ?string;
    public static function sanitize_json( string $value ): string;
}
```

---

## 4. Usage Patterns

### 4.1 Admin Settings Registration (register_setting callback)

```php
register_setting( 'wpaic_runtime_settings', 'wpaic_runtime_max_tokens', [
    'type'              => 'integer',
    'sanitize_callback' => function ( $value ) {
        return Sanitizer::sanitize_int( $value, 1, 128000 );
    },
    'default'           => 2048,
] );
```

### 4.2 AJAX Handler Input Sanitization

```php
// In message pipeline pre-processing stage:
$sanitized = Sanitizer::sanitize_map( $_POST, [
    'message'    => [ 'type' => Sanitizer::TYPE_MESSAGE ],
    'session_id' => [ 'type' => Sanitizer::TYPE_SLUG ],
    'nonce'      => [ 'type' => Sanitizer::TYPE_SLUG ],
] );
```

### 4.3 REST Endpoint Param Sanitization

```php
register_rest_route( 'wp-ai-chatbot/v1', '/chat', [
    'args' => [
        'message' => [
            'sanitize_callback' => [ Sanitizer::class, 'sanitize_message' ],
            'validate_callback' => fn($v) => ! empty( $v ),
        ],
    ],
] );
```

---

## 5. Compliance Rules

| Rule | Requirement |
|------|------------|
| SCR-01 | Every AJAX `$_POST`/`$_GET`/`$_REQUEST` read MUST pass through `Sanitizer::sanitize()` or a typed alias before use |
| SCR-02 | Every REST endpoint `args` array MUST specify `sanitize_callback` using Sanitizer methods |
| SCR-03 | No domain module implements inline sanitization with raw WordPress functions (all routing via Sanitizer) |
| SCR-04 | Extensions may add custom sanitizer types via `wpaic_sanitizer_types` filter ONLY; no monkey-patching |
| SCR-05 | `TYPE_URL` MUST enforce HTTPS-only on all external service endpoints (Pinecone, webhooks, embedding APIs) |
| SCR-06 | `TYPE_HTML_RESTRICTED` MUST use the defined `HTML_ALLOWLIST` constant; allowlist is not configurable at runtime |
| SCR-07 | Raw `sanitize_text_field()` is permitted ONLY inside `class-sanitizer.php` as an implementation detail |

---

## 6. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Domain sanitizer functions (`sanitize_user_message`, `sanitize_model_slug`) | `axiachat-ai/includes/sanitize-helpers.php` | Adopted; converted to static class methods |
| `wp_kses()` with restricted allowlist for rich text | `axiachat-ai/includes/sanitize-helpers.php` | Adopted; allowlist defined as constant |
| Bounds checking on integer fields (`min`, `max`) | `baachal/admin/tabs/general.php` (absint + min check) | Adopted; generalized into TYPE_INT params |
| Field-level sanitize_callback in register_setting | `mxchat-basic/includes/class-mxchat-admin.php` | Adopted; routed through Sanitizer |
| HTTPS-only URL enforcement | `axiachat-ai/includes/sanitize-helpers.php:sanitize_endpoint_url()` | Adopted verbatim |
| IP extraction and validation | `axiachat-ai/includes/usage.php` | Adopted; added IPv6 support |

---

## 7. CFI-SEC-001 Resolution

CFI-SEC-001 required: "Unified sanitization library (AXI domain sanitizers + BAA bounds + MXC validation)."

| Requirement | Resolution | Section |
|-------------|-----------|---------|
| Domain-specific sanitizers | 12 type constants with typed method aliases | Section 2 |
| AXI domain sanitizer pattern | All AXI helper functions mapped to TYPE_* constants | Section 6 |
| BAA bounds checking | TYPE_INT and TYPE_FLOAT accept min/max params | Section 2.1 |
| MXC field-level validation | sanitize_map() for bulk field sanitization | Section 3 |
| Registry extensibility | register_type() + wpaic_sanitizer_types filter | Sections 3, 5 |
| Compliance enforcement rules | SCR-01 through SCR-07 | Section 5 |

**Status**: CFI-SEC-001 fully resolved.
