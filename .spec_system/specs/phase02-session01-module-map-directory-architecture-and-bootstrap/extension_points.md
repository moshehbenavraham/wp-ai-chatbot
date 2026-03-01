# Extension Points

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Extension Architecture Overview

Deferred domains (Tools, Integrations, WooCommerce) integrate with the plugin through
**hook-based registration** -- they never create import dependencies from MVP modules. This
ensures MVP code compiles and runs without any deferred domain code present.

The extension lifecycle follows this pattern:

```
1. Plugin bootstrap fires wpaic_init action
2. Extension modules (in includes/tools/, integrations/, woocommerce/) listen
3. Extensions register their capabilities via domain-specific hooks
4. MVP modules consume registered capabilities through existing hook/filter interfaces
5. If no extensions are registered, MVP modules operate normally (graceful absence)
```

---

## 2. Tools Domain Extension Architecture

### 2.1 Overview

The Tools domain enables AI-initiated actions (tool calling / function calling). It provides a
registry where tools are registered with JSON Schema definitions, an execution engine that
dispatches tool calls from AI providers, and lifecycle hooks for pre/post execution.

**Source Patterns**:
- `axiachat-ai/includes/add-ons/ai-tools/api.php` -- Tool registration with JSON Schema
- `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` -- Macro registry with source tracking
- `mxchat-basic/includes/admin-actions-page.php` -- Action admin UI with categories

### 2.2 Registration Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_register_tools` | action | Register tool definitions with the tool registry |
| `wpaic_tools_capabilities` | filter | Modify per-bot tool capability settings |
| `wpaic_tool_schema` | filter | Modify a tool's JSON Schema before provider injection |

### 2.3 Tool Registration Interface

```php
// Extension registers tools via action hook
add_action('wpaic_register_tools', function ($registry) {
    $registry->register([
        'name'        => 'get_weather',
        'description' => 'Get current weather for a location',
        'source'      => 'local',        // local | mcp | webhook
        'source_id'   => null,           // null for local tools
        'schema'      => [               // JSON Schema for parameters
            'type'       => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City name'],
            ],
            'required'   => ['location'],
        ],
        'handler'     => 'my_weather_handler', // callable
        'capabilities' => ['manage_options'],  // required WP capabilities
    ]);
});
```

### 2.4 Execution Lifecycle Hooks

```
wpaic_before_tool_execute ($tool_name, $args, $context)
    |
    v
Tool handler executes
    |
    v
wpaic_after_tool_execute ($tool_name, $args, $result, $duration)
    |
    v
wpaic_tool_result ($result, $tool_name, $context) -- filter to modify result
```

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_before_tool_execute` | action | Pre-execution validation, logging, timeout setup |
| `wpaic_after_tool_execute` | action | Post-execution logging, metrics, cleanup |
| `wpaic_tool_result` | filter | Modify tool result before returning to AI provider |
| `wpaic_tool_timeout` | filter | Modify per-tool timeout (default: 30 seconds) |

### 2.5 Provider Integration

Tools are injected into AI provider calls via the provider adapter's `normalize_tools()` method.
The flow is:

```
1. Runtime builds message payload
2. Runtime queries Tool_Registry for tools available to current bot
3. Runtime passes canonical tool definitions to provider adapter
4. Provider adapter normalizes to provider-specific format (OpenAI, Claude, Gemini)
5. Provider sends request with tools
6. Provider receives tool_call response
7. Runtime dispatches to Tool_Registry for execution
8. Tool result fed back to provider for final response
```

**Key design**: Tool schemas are stored in **canonical (provider-agnostic)** format in the
registry. Per-provider normalization happens in the provider adapter, not the tools module
(per RES-XD-002 Registry Centralization).

### 2.6 Macro Registry Stub

Macros are composable tool sequences. The macro registry extends the tool registry:

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_register_macros` | action | Register macro definitions |
| `wpaic_macro_steps` | filter | Modify macro step sequence |

Macros support source tracking (local, MCP, webhook) with cascade delete when a source is
removed (per `axiachat-ai/includes/add-ons/ai-tools/macro-api.php`).

---

## 3. Integrations Domain Extension Architecture

### 3.1 Overview

The Integrations domain provides external protocol support (MCP), webhook execution, and an
extension framework. It bridges external services to the plugin's tool and event systems.

**Source Patterns**:
- `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` -- MCP JSON-RPC sessions
- `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` -- Webhook execution
- `mxchat-basic/includes/class-mxchat-addons.php` -- Marketplace UI

### 3.2 MCP Client Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_mcp_register_server` | action | Register an MCP server connection |
| `wpaic_mcp_tools_discovered` | action | Fired when tools are discovered from an MCP server |
| `wpaic_mcp_transport_config` | filter | Modify MCP transport configuration |
| `wpaic_mcp_before_request` | action | Pre-request logging, auth injection |
| `wpaic_mcp_after_response` | action | Post-response logging, error tracking |

### 3.3 MCP-to-Tool Bridge

MCP servers expose tools via the Model Context Protocol. The integration flow is:

```
1. MCP_Client connects to configured servers (lazy, on first use)
2. MCP_Client discovers available tools via tools/list JSON-RPC call
3. Discovered tools are registered with Tool_Registry:
   registry->register([
       'source'    => 'mcp',
       'source_id' => $server_id,
       ...tool definition from MCP...
   ]);
4. When tool is called by AI, Tool_Registry delegates to MCP_Client
5. MCP_Client sends tools/call JSON-RPC request to the source server
6. Result returned through normal tool execution lifecycle
```

**Cascade delete**: When an MCP server is disconnected or removed, all tools with
`source=mcp, source_id=$server_id` are removed from the registry.

### 3.4 Webhook Event Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_webhook_register` | action | Register a webhook endpoint configuration |
| `wpaic_webhook_before_execute` | action | Pre-execution SSRF validation, auth |
| `wpaic_webhook_after_execute` | action | Post-execution logging, DB recording |
| `wpaic_webhook_response` | filter | Transform webhook response before use |
| `wpaic_webhook_events` | filter | Register chatbot events that trigger webhooks |

### 3.5 Webhook Event System

Webhooks can be triggered by chatbot events:

```
1. Chatbot event fires (e.g., wpaic_after_message_send)
2. Webhook event system checks for registered webhooks matching this event
3. For each matching webhook:
   a. Build payload with placeholder substitution
   b. Validate target URL (SSRF protection)
   c. Execute HTTP request with timeout and retry
   d. Log result to database
```

**Event types** (extensible via `wpaic_webhook_events` filter):
- `message.sent` -- After AI responds
- `session.created` -- New chat session started
- `session.ended` -- Chat session cleared/expired
- `tool.executed` -- After tool execution
- `moderation.blocked` -- Message blocked by moderation

### 3.6 Extension Model Registration

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_register_integrations` | action | Register an integration (MCP server, webhook, addon) |
| `wpaic_integration_status` | filter | Report integration health status |
| `wpaic_integration_settings` | filter | Register integration-specific settings fields |

The extension model provides a unified interface for all integration types:

```php
add_action('wpaic_register_integrations', function ($manager) {
    $manager->register([
        'id'          => 'my-mcp-server',
        'type'        => 'mcp',           // mcp | webhook | addon
        'name'        => 'My MCP Server',
        'description' => 'Custom MCP server for data access',
        'config'      => [...],
        'health_check' => 'my_health_callback',
    ]);
});
```

---

## 4. WooCommerce Domain Extension Architecture

### 4.1 Overview

The WooCommerce domain provides product context injection, catalog search, cart operations, and
HPOS compatibility. It activates only when WooCommerce is detected and integrates through the
Layered Ownership (RAG enrichment) and Dual Exposure (cart as AJAX + tool) patterns.

**Source Patterns**:
- `baachal/baachal.php` -- Product context injection, HPOS, dynamic term extraction
- `mxchat-basic/includes/class-mxchat-woocommerce.php` -- AJAX cart operations
- `baachal/admin/tabs/woocommerce.php` -- WC admin settings

### 4.2 Activation Hook

```php
// WooCommerce module only loads when WC is active
add_action('wpaic_wc_init', function () {
    // This action only fires when class_exists('WooCommerce') is true
    // Register WooCommerce capabilities
});
```

**Conditional loading**: The `wpaic_wc_init` action fires from `Plugin::on_init()` only after
confirming WooCommerce is active. If WooCommerce is deactivated, none of the WC extension code
loads.

### 4.3 Product Context Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_wc_product_context` | filter | Modify product context data before injection |
| `wpaic_wc_product_search` | filter | Modify product search query/results |
| `wpaic_wc_product_fields` | filter | Configure which product fields are indexed |
| `wpaic_wc_cache_ttl` | filter | Modify product cache TTL (default: 1 hour) |

### 4.4 RAG Integration (Layered Ownership)

WooCommerce enriches RAG retrieval results with live product data:

```
1. RAG retrieves content matches (generic pipeline)
2. wpaic_rag_results filter fires
3. WooCommerce module hooks at priority 15:
   a. Identifies product-type results
   b. Fetches live price, stock status, images
   c. Enriches result with product card HTML
   d. Returns modified results
4. Context assembler builds final prompt with enriched data
```

**Standalone fallback**: If RAG is not configured (no embeddings, no vector DB), WooCommerce
provides a standalone keyword search fallback using WP_Query:

```php
// Standalone product search (no RAG dependency)
add_action('wpaic_wc_init', function () {
    // Only register standalone if RAG is not configured
    if (!wpaic_is_rag_configured()) {
        add_filter('wpaic_message_content', 'wpaic_wc_standalone_product_search', 15);
    }
});
```

### 4.5 Cart Operation Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `wpaic_wc_before_cart_action` | action | Pre-cart-action validation (existence, visibility, stock) |
| `wpaic_wc_after_cart_action` | action | Post-cart-action logging |
| `wpaic_wc_cart_operations` | filter | Register available cart operations |
| `wpaic_wc_cart_validation` | filter | Add custom validation rules for cart operations |

### 4.6 Dual Exposure: AJAX + Tool Calling (RES-XD-011)

Cart operations are exposed through two interfaces:

**AJAX Interface** (user-initiated via chat widget):
```
wp_ajax_wpaic_wc_add_to_cart     -> WooCommerce\Cart_Handler::add_to_cart()
wp_ajax_nopriv_wpaic_wc_add_to_cart
wp_ajax_wpaic_wc_cart_status     -> WooCommerce\Cart_Handler::get_status()
wp_ajax_nopriv_wpaic_wc_cart_status
```

**Tool Interface** (AI-initiated via tool calling):
```php
add_action('wpaic_register_tools', function ($registry) {
    $registry->register([
        'name'        => 'wc_add_to_cart',
        'description' => 'Add a product to the shopping cart',
        'source'      => 'local',
        'schema'      => [...],
        'handler'     => [WooCommerce\Cart_Handler::class, 'add_to_cart'],
    ]);
    // ... wc_get_cart_status, wc_remove_from_cart, etc.
});
```

Both interfaces call the **same** `Cart_Handler` implementation. The handler includes:
- Product existence and visibility checks
- Stock validation
- Variable product attribute validation
- Nonce verification (AJAX path) or capability check (tool path)
- Rate limiting to prevent cart abuse

### 4.7 HPOS Compatibility Declaration

```php
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            WPAIC_BASENAME,
            true
        );
    }
});
```

This declaration fires from the bootstrap sequence (Phase D2 in `bootstrap_sequence.md`),
ensuring it runs even if the WooCommerce module is not yet loaded.

### 4.8 Admin Settings Integration (Hosting Pattern)

WooCommerce settings are hosted within the Admin domain's settings pages:

```php
add_action('wpaic_admin_register_page', function ($router) {
    // WC settings appear as a tab within existing settings page
});

add_filter('wpaic_admin_tabs', function ($tabs, $page) {
    if ($page === 'chat') {
        $tabs['woocommerce'] = [
            'label'    => __('WooCommerce', 'wp-ai-chatbot'),
            'renderer' => [WooCommerce\Admin_Tab::class, 'render'],
        ];
    }
    return $tabs;
}, 10, 2);
```

---

## 5. Extension Point Safety Rules

### 5.1 Graceful Absence

Every extension point MUST work when no extensions are registered:

| Scenario | Behavior |
|----------|---------|
| No tools registered | AI provider calls proceed without tool definitions |
| No MCP servers connected | Tool registry has no MCP-sourced tools |
| WooCommerce not active | `wpaic_wc_init` never fires; no WC code loads |
| All integrations disabled | Plugin operates as standalone chatbot |

### 5.2 Registration Validation

Extensions MUST validate their registrations:

| Rule | Enforcement |
|------|------------|
| Tool names must be unique | `Tool_Registry::register()` rejects duplicates |
| Tool schemas must be valid JSON Schema | Validated on registration |
| MCP servers must have valid transport config | Validated on `mcp_register_server` |
| Webhook URLs must pass SSRF validation | Validated on registration and execution |
| WooCommerce features require WC active | `wpaic_wc_init` only fires when WC detected |

### 5.3 Cleanup on Removal

When an extension source is removed, all registered items are cleaned up:

```
MCP server disconnected:
  -> Tool_Registry::remove_by_source('mcp', $server_id)
  -> Macro_Registry::remove_by_source('mcp', $server_id) (cascade)

Webhook removed:
  -> Tool_Registry::remove_by_source('webhook', $webhook_id)

WooCommerce deactivated:
  -> wpaic_wc_init never fires on next load
  -> WC tools are not re-registered
  -> RAG continues without product enrichment (graceful degradation)
```
