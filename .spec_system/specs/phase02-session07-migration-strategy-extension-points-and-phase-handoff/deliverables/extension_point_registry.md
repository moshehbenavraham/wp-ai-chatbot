# Extension Point Registry for Deferred Capabilities

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

The extension point registry defines the minimal contracts that allow deferred domain modules
(tools, integrations, WooCommerce) to plug into the MVP codebase in Phase 07+ without
requiring changes to MVP code. The pattern is: stub directories exist, registration hooks
are pre-defined, execution entry points are pre-defined. MVP modules treat these as no-ops when
no extension is registered.

---

## 2. Tools Extension Points

### 2.1 Registration (Phase 07)

```php
// Fires on wpaic_loaded (after plugin init)
do_action( 'wpaic_register_tools', WPAIC\Tools\Registry::instance() );

// Tool definition registered by extension:
$registry->register([
    'slug'         => 'search_products',
    'label'        => 'Search Products',
    'description'  => 'Search WooCommerce products by keyword',
    'parameters'   => [
        'query' => ['type' => 'string', 'required' => true],
    ],
    'capability'   => '',  // '' = no WP capability required to invoke
    'phase'        => 7,
]);
```

### 2.2 Execution (Phase 07)

```php
// Message pipeline Stage 5 (tool execution):
$result = apply_filters( 'wpaic_tool_execute_' . $slug, null, $params, $session_id );
if ( $result !== null ) {
    do_action( 'wpaic_tool_post_execute', $slug, $result, $session_id );
}
```

### 2.3 Tool Pending State (Phase 05)

The message pipeline already handles the two-phase tool model (tool_pending -> continuation)
as defined in session 04. When tools are registered in Phase 07, the pipeline's Stage 5
simply starts firing the `wpaic_tool_execute_{slug}` filter instead of returning no-op.

---

## 3. Integrations Extension Points

### 3.1 MCP Client (Phase 07 -- CFI-INT-001)

```php
// Extension registers an MCP server connection:
do_action( 'wpaic_register_integrations', WPAIC\Integrations\Registry::instance() );

$registry->register_mcp([
    'slug'      => 'my_mcp_server',
    'transport' => 'sse',         // 'sse' | 'stdio' | 'http'
    'endpoint'  => 'https://...',
    'tools'     => [],            // auto-discovered via MCP tool listing
]);
```

### 3.2 Webhook Executor (Phase 07 -- CFI-INT-002)

```php
// Extension registers an event -> webhook mapping:
$registry->register_webhook([
    'event'    => 'wpaic_message_sent',  // any WP action hook
    'url'      => 'https://...',
    'method'   => 'POST',
    'payload'  => ['message_id', 'session_id', 'provider'],  // whitelisted fields
    'async'    => true,                  // queue via WP-Cron
]);
```

---

## 4. WooCommerce Extension Points

### 4.1 Product Context Injection (Phase 08 -- CFI-WOO-001)

```php
// WooCommerce module registers context enrichment via rag_context filter:
add_filter( 'wpaic_rag_context', function( $context, $session_id, $query ) {
    if ( ! function_exists( 'WC' ) ) return $context;
    $products = wpaic_woo_retrieve_products( $query );
    return $context . wpaic_woo_format_product_context( $products );
}, 20, 3 );
```

### 4.2 Cart Operation Tools (Phase 08 -- CFI-WOO-003)

```php
// WooCommerce module registers tools via wpaic_register_tools:
add_action( 'wpaic_register_tools', function( $registry ) {
    if ( ! function_exists( 'WC' ) ) return;
    $registry->register([
        'slug'       => 'add_to_cart',
        'label'      => 'Add to Cart',
        'parameters' => ['product_id' => ['type' => 'integer', 'required' => true]],
    ]);
} );
```

### 4.3 WooCommerce Detection Guard

All WooCommerce extension code MUST wrap logic in `function_exists('WC')` checks. The admin
page `class-tools-page.php` renders a WooCommerce-detected/missing notice using the Baachal
pattern (detect active plugin, show contextual message).

---

## 5. Stub Files

These files exist in the MVP codebase as empty placeholders with a docblock:

```
includes/tools/.gitkeep
includes/integrations/.gitkeep
includes/woocommerce/.gitkeep
```

When Phase 07 populates these directories, the autoloader resolves classes automatically.
No bootstrap changes required (class-plugin.php conditionally loads domain modules only if
their directory contains PHP files beyond .gitkeep).

---

## 6. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Tool registration action hook | `axiachat-ai/includes/add-ons/ai-tools/api.php` | Adopted; generalized to Registry |
| Macro expansion as tool sub-type | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` | Deferred -- macros are tools in Phase 07 |
| MCP client manager pattern | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` | Adopted; transport expanded in Phase 07 |
| Webhook executor pattern | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` | Adopted; async queue added |
| WooCommerce detection guard | `baachal/admin/tabs/woocommerce.php` | Adopted; generalized |
| WooCommerce noticed in admin | `baachal/baachal.php` WC detection notice | Adopted |
