# Module Boundaries

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Module Ownership Rules

Each domain module owns one directory under `includes/`. Ownership means:

1. **All code for a domain lives in its directory** -- no domain logic scattered across other modules.
2. **One module, one responsibility** -- a module addresses capabilities within a single domain.
3. **Public interface at module root** -- other modules interact through the public classes at the
   top of the module directory, never reaching into subdirectories.
4. **Internal implementation is private** -- subdirectories (e.g., `adapters/`, `pages/`) are
   implementation details of that module.

---

## 2. Dependency Direction Rules

### 2.1 Allowed Dependencies

Dependencies flow **downward** from higher-level modules to lower-level modules. The dependency
direction is:

```
    Presentation Layer (admin/, assets/, templates/)
            |
            v
    Domain Modules (includes/{domain}/)
            |
            v
    Infrastructure (lib/, WordPress API)
```

Within domain modules, the allowed dependency directions are:

| Source Module | May Depend On | Relationship |
|--------------|--------------|-------------|
| `runtime` | `provider`, `security`, `rag` | Dispatches to provider, enforces security, injects context |
| `rag` | `provider`, `security` | Uses embedding providers, enforces context policies |
| `admin` | `provider`, `runtime`, `rag`, `security`, `migration` | Reads/writes settings for all domains |
| `security` | (none -- leaf module) | Provides policies consumed by others |
| `provider` | `security` | Reads encrypted API keys via security module |
| `migration` | (none -- leaf module) | Provides lifecycle and versioning infrastructure |
| `tools` (deferred) | `provider`, `security` | Registers tools, dispatches via provider |
| `integrations` (deferred) | `tools`, `security` | MCP/webhooks register as tool sources |
| `woocommerce` (deferred) | `rag`, `tools`, `security` | Enriches RAG results, registers cart tools |

### 2.2 Forbidden Dependencies

| Rule | Rationale |
|------|-----------|
| `security` must NOT depend on any domain module | Security is a leaf policy module (Policy-vs-Execution pattern) |
| `migration` must NOT depend on any domain module | Migration runs at plugin lifecycle boundaries before domains are fully loaded |
| `provider` must NOT depend on `runtime` | Prevents circular: runtime dispatches to provider, not the reverse |
| `rag` must NOT depend on `runtime` | RAG provides context to runtime, not the reverse |
| No module may depend on `tools`, `integrations`, or `woocommerce` | Deferred domains register via hooks; MVP modules must not import them |
| No module may reach into another module's subdirectories | Access only through top-level public classes |
| `lib/` must NOT depend on any `includes/` module | Infrastructure is domain-agnostic |

### 2.3 Cross-Module Communication Patterns

All cross-module communication follows one of the 6 resolution patterns from Phase 01:

| Pattern | How It Works | Example |
|---------|-------------|---------|
| Policy-vs-Execution | Module A calls `security->get_policy()`, applies result | Runtime calls `Moderator::check()` |
| Registry Centralization | Single registry, multiple contributors/consumers | `Provider_Registry` serves runtime + rag + admin |
| Natural Interface Boundary | Read/write getter/setter pair | Admin writes settings, provider reads them |
| Layered Ownership | Generic pipeline + domain-specific enrichment callback | RAG retrieves, WooCommerce enriches |
| Hosting Pattern | Host provides `register_panel()`, contributor calls it | Admin hosts, security registers audit panel |
| Dual Exposure | Same implementation exposed through multiple surfaces | WooCommerce cart via AJAX + tool calling |

---

## 3. Module Definitions

### 3.1 DOM-PROVIDER (`includes/provider/`)

**Responsibility**: AI provider registration, model catalog, adapter dispatch, error resilience.

**Public Interface**:
- `Provider_Registry` -- Singleton registry, `register()`, `get_provider()`, `get_providers()`
- `Model_Registry` -- Model catalog, resolution pipeline (exact -> alias -> prefix -> default)
- `Provider` interface -- Contract for all provider adapters
- `Embedding` interface -- Contract for embedding-capable providers (RES-XD-008)

**Internal**:
- `adapters/` -- Per-provider implementation classes (OpenAI, Claude, Gemini, DeepSeek, Grok)

**Hooks Provided**:
- `wpaic_register_providers` (action) -- Extension point for registering custom providers
- `wpaic_model_registry` (filter) -- Modify model catalog
- `wpaic_provider_config` (filter) -- Modify provider configuration before dispatch

**Source Anchors**: `axiachat-ai/includes/class-aichat-provider-registry.php`,
`axiachat-ai/includes/providers/class-openai-provider.php`,
`axiachat-ai/includes/model-registry.php`

### 3.2 DOM-RUNTIME (`includes/runtime/`)

**Responsibility**: Chat message lifecycle, session handling, streaming delivery, context injection.

**Public Interface**:
- `Message_Pipeline` -- Pre-process -> dispatch -> post-process chain
- `Session_Manager` -- Session CRUD, ownership validation, token tracking
- `Stream_Handler` -- SSE streaming transport layer
- `Context_Injector` -- Page context injection, handler replacement hook

**Hooks Provided**:
- `wpaic_before_message_send` / `wpaic_after_message_send` (action)
- `wpaic_session_created` / `wpaic_session_destroyed` (action)
- `wpaic_message_content` (filter) -- Modify message before provider dispatch
- `wpaic_context_handler` (filter) -- Replace default page context handler (CFI-RUN-001)

**Source Anchors**: `axiachat-ai/includes/class-aichat-ajax.php`,
`mxchat-basic/includes/class-mxchat-integrator.php`

### 3.3 DOM-MIGRATION (`includes/migration/`)

**Responsibility**: Plugin activate/deactivate/uninstall, schema versioning, settings migration.

**Public Interface**:
- `Lifecycle_Manager` -- Activation, deactivation, uninstall with extension hooks (CFI-MIG-001)
- `Migration_Registry` -- Versioned callback registration, execution log (CFI-MIG-002)
- `Option_Migrator` -- Key mapping, value transforms for settings migration (CFI-MIG-003)

**Hooks Provided**:
- `wpaic_activated` / `wpaic_deactivated` / `wpaic_uninstalling` (action)
- `wpaic_before_migration` / `wpaic_after_migration` (action)
- `wpaic_register_migrations` (action) -- Extension point for domain-specific migrations
- `wpaic_uninstall_cleanup` (filter) -- Modules register cleanup callbacks

**Source Anchors**: `baachal/baachal.php` (lifecycle), `baachal/uninstall.php`,
`mxchat-basic/mxchat-basic.php` (version-conditional migrations)

### 3.4 DOM-RAG (`includes/rag/`)

**Responsibility**: Content ingestion, chunking, indexing, retrieval, context assembly, embedding.

**Public Interface**:
- `Ingestion_Pipeline` -- Queue-based content ingestion with retry/stall detection
- `Chunker` -- Overlap-based splitting with configurable chunk size
- `Retriever` -- Multi-mode retrieval (local/Pinecone/page/auto)
- `Context_Assembler` -- Security policy injection, template token substitution
- `Embedding_Client` -- Dispatch embedding requests via provider registry

**Hooks Provided**:
- `wpaic_before_ingest` / `wpaic_after_ingest` (action)
- `wpaic_rag_context` (filter) -- Modify assembled context before prompt injection
- `wpaic_rag_results` (filter) -- Modify retrieval results (WooCommerce enrichment point)
- `wpaic_rag_sources` (filter) -- Modify available content sources

**Source Anchors**: `axiachat-ai/includes/contexto-functions.php`,
`mxchat-basic/admin/class-knowledge-manager.php`

### 3.5 DOM-ADMIN (`includes/admin/`)

**Responsibility**: Settings architecture, menu/page routing, diagnostics, operational controls.

**Public Interface**:
- `Admin_Router` -- WP menu registration, page dispatch, asset enqueue
- `Settings_Manager` -- WP Settings API integration with auto-encrypt/decrypt
- `Diagnostics` -- Panel registration hook, health aggregation (RES-XD-010)

**Internal**:
- `pages/` -- Per-page controllers (<600 LOC each) per CFI-ADM-001

**Hooks Provided**:
- `wpaic_admin_register_page` (action) -- Register additional admin pages
- `wpaic_admin_register_diagnostic_panel` (action) -- Register diagnostic panels (Hosting pattern)
- `wpaic_admin_settings_saved` (action) -- Triggered after settings save
- `wpaic_admin_tabs` (filter) -- Modify tab list for a page

**Source Anchors**: `mxchat-basic/includes/class-mxchat-admin.php` (anti-pattern to decompose),
`baachal/admin/tabs/` (tab-based settings pattern),
`axiachat-ai/includes/settings/` (settings sub-modules)

### 3.6 DOM-SECURITY (`includes/security/`)

**Responsibility**: Input sanitization, auth enforcement, content moderation, encryption, privacy, audit.

**Public Interface**:
- `Sanitizer` -- Unified sanitization library with domain-specific validators (CFI-SEC-001)
- `Auth_Enforcer` -- Nonce + capability dual-check, per-bot capability settings
- `Moderator` -- 5-layer moderation pipeline (honeypot, rate, spam, banned, API)
- `Privacy_Manager` -- IP privacy toggle, daily usage limits, GDPR controls
- `Encryption` -- AES-256-GCM encrypt/decrypt, key rotation (CFI-SEC-002)
- `Audit_Logger` -- Structured security event logging with retention policy

**Hooks Provided**:
- `wpaic_security_moderation_check` (filter) -- Modify moderation result
- `wpaic_security_audit_event` (action) -- Log security-relevant event
- `wpaic_security_capabilities` (filter) -- Modify required capabilities for an operation

**Source Anchors**: `axiachat-ai/includes/sanitize-helpers.php`,
`axiachat-ai/includes/moderation.php`,
`axiachat-ai/includes/settings.php` (AES-256-GCM)

### 3.7 DOM-TOOLS (`includes/tools/`) -- Deferred

**Responsibility**: Tool registration, execution engine, macro registry, admin controls.

**Extension Registration**: Via `wpaic_register_tools` action hook. Tools domain code registers
tools with `Provider_Registry` for injection into AI provider calls. MVP modules never import
from this directory.

**Source Anchors**: `axiachat-ai/includes/add-ons/ai-tools/api.php`,
`axiachat-ai/includes/add-ons/ai-tools/macro-api.php`

### 3.8 DOM-INTEGRATIONS (`includes/integrations/`) -- Deferred

**Responsibility**: MCP client, webhook executor, extension framework, marketplace UI.

**Extension Registration**: Via `wpaic_register_integrations` action hook. Integrations register
their tools with the tool registry. MVP modules never import from this directory.

**Source Anchors**: `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php`,
`axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php`

### 3.9 DOM-WOOCOMMERCE (`includes/woocommerce/`) -- Deferred

**Responsibility**: Product context injection, catalog operations, cart handling, HPOS compat.

**Extension Registration**: Via `wpaic_wc_init` action hook (fires only when WooCommerce is
active). WooCommerce module hooks into `wpaic_rag_results` filter for product enrichment
(Layered Ownership pattern) and registers cart tools via tool registry (Dual Exposure pattern).

**Source Anchors**: `baachal/baachal.php` (product context, HPOS),
`mxchat-basic/includes/class-mxchat-woocommerce.php` (cart operations)

---

## 4. Cross-Domain Integration Points

These are the critical interfaces between modules, mapped to resolution patterns:

| # | Integration Point | Source -> Target | Interface Contract | Pattern |
|---|------------------|-----------------|-------------------|---------|
| 1 | Provider dispatch | Runtime -> Provider | `Provider_Registry::get_provider($slug)->send($messages, $params)` | Natural Interface |
| 2 | Tool injection | Tools -> Provider | `$adapter->normalize_tools($canonical_tools)` | Registry Centralization |
| 3 | Moderation gate | Runtime -> Security | `Moderator::check($message, $context)` returns pass/fail | Policy-vs-Execution |
| 4 | Session validation | Runtime -> Security | `Auth_Enforcer::validate_session($session)` | Policy-vs-Execution |
| 5 | Context policies | RAG -> Security | `Privacy_Manager::get_context_policies($user)` | Policy-vs-Execution |
| 6 | Key encryption | Admin -> Security | `Encryption::encrypt($value)` / `Encryption::decrypt($value)` | Policy-vs-Execution |
| 7 | Embedding lookup | RAG -> Provider | `Provider_Registry::get_providers('embedding')` | Registry Centralization |
| 8 | Product enrichment | WooCommerce -> RAG | Hook into `wpaic_rag_results` filter | Layered Ownership |
| 9 | Cart as tool | WooCommerce -> Tools | `Tool_Registry::register($wc_cart_tools)` | Dual Exposure |
| 10 | Tool source tracking | Integrations -> Tools | `Tool_Registry::remove_by_source($type, $id)` | Registry Centralization |
| 11 | Diagnostics panels | Security -> Admin | `do_action('wpaic_admin_register_diagnostic_panel', ...)` | Hosting Pattern |
| 12 | Extension lifecycle | Integrations -> Tools | Cascade delete on source removal | Registry Centralization |

---

## 5. Boundary Enforcement Rules

### 5.1 Import Rules (enforced by code review and static analysis)

```
ALLOWED:
  includes/{domain}/ -> includes/{allowed-dependency}/  (top-level classes only)
  includes/{domain}/ -> lib/                            (infrastructure utilities)
  includes/{domain}/ -> WordPress API functions          (global WP functions)
  admin/views/       -> includes/admin/                  (template gets data from controller)
  assets/            -> (no PHP imports)
  templates/         -> includes/runtime/                (template gets data from controller)

FORBIDDEN:
  includes/{domain}/ -> includes/{domain}/subdirectory/  (from another module)
  includes/{domain}/ -> admin/                           (domain must not know about presentation)
  includes/{domain}/ -> assets/                          (domain must not know about presentation)
  lib/               -> includes/                        (infrastructure is domain-agnostic)
  includes/security/ -> includes/{any-domain}/           (security is a leaf module)
  includes/migration/-> includes/{any-domain}/           (migration is a leaf module)
  includes/{mvp}/    -> includes/tools|integrations|woocommerce/  (MVP never imports deferred)
```

### 5.2 Testing Boundary Verification

To verify no circular dependencies exist at review time:
1. Map each `use` statement and `require` to source/target modules
2. Build directed graph of module -> module dependencies
3. Run topological sort -- if sort fails, a cycle exists
4. See `module_dependency_graph.md` for the verified graph

---

## 6. MVP Capability-to-Module Mapping (26 capabilities)

Every MVP capability from the v1 scope boundary has a specific module home and file target.

### 6.1 DOM-PROVIDER (4/4 MVP)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 1 | DEC-PRO-001 | Provider Registry and Adapter Dispatch | `includes/provider/` | `class-provider-registry.php` | `axiachat-ai/includes/class-aichat-provider-registry.php` |
| 2 | DEC-PRO-002 | Provider-Specific API Adapters | `includes/provider/adapters/` | `class-openai-adapter.php` et al. | `axiachat-ai/includes/providers/class-openai-provider.php` |
| 3 | DEC-PRO-003 | Centralized Model Registry | `includes/provider/` | `class-model-registry.php` | `axiachat-ai/includes/model-registry.php` |
| 4 | DEC-PRO-004 | Provider Error Handling | `includes/provider/adapters/` | Per-adapter retry/fallback logic | `axiachat-ai/includes/providers/class-claude-provider.php` |

### 6.2 DOM-RUNTIME (4/4 MVP)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 5 | DEC-RUN-001 | Message Lifecycle and Request Pipeline | `includes/runtime/` | `class-message-pipeline.php` | `axiachat-ai/includes/class-aichat-ajax.php` |
| 6 | DEC-RUN-002 | Session Handling | `includes/runtime/` | `class-session-manager.php` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| 7 | DEC-RUN-003 | Streaming and Real-Time Delivery | `includes/runtime/` | `class-stream-handler.php` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| 8 | DEC-RUN-004 | Frontend State Management | `assets/js/` | `chatbot.js` | `mxchat-basic/js/chat-script.js` |

### 6.3 DOM-MIGRATION (3/3 MVP)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 9 | DEC-MIG-001 | Plugin Lifecycle Management | `includes/migration/` | `class-lifecycle-manager.php` | `baachal/baachal.php`, `baachal/uninstall.php` |
| 10 | DEC-MIG-002 | Schema Versioning and Upgrade | `includes/migration/` | `class-migration-registry.php` | `mxchat-basic/mxchat-basic.php` |
| 11 | DEC-MIG-003 | Settings Migration and Cleanup | `includes/migration/` | `class-option-migrator.php` | `mxchat-basic/mxchat-basic.php` |

### 6.4 DOM-RAG (5/5 MVP)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 12 | DEC-RAG-001 | Ingestion Pipeline | `includes/rag/` | `class-ingestion-pipeline.php` | `mxchat-basic/admin/class-knowledge-manager.php` |
| 13 | DEC-RAG-002 | Chunking and Indexing | `includes/rag/` | `class-chunker.php` | `axiachat-ai/includes/contexto-functions.php` |
| 14 | DEC-RAG-003 | Retrieval and Ranking | `includes/rag/` | `class-retriever.php` | `axiachat-ai/includes/contexto-functions.php` |
| 15 | DEC-RAG-004 | Context Assembly | `includes/rag/` | `class-context-assembler.php` | `axiachat-ai/includes/contexto-functions.php` |
| 16 | DEC-RAG-005 | Embedding Integration | `includes/rag/` | `class-embedding-client.php` | `axiachat-ai/includes/contexto-functions.php` |

### 6.5 DOM-ADMIN (5/5 MVP)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 17 | DEC-ADM-001 | Settings Architecture | `includes/admin/` | `class-settings-manager.php` | `mxchat-basic/includes/class-mxchat-admin.php` |
| 18 | DEC-ADM-002 | Option Management | `includes/admin/` | `class-settings-manager.php` (encrypt/decrypt via Security) | `axiachat-ai/includes/settings.php` |
| 19 | DEC-ADM-003 | Diagnostics and Health | `includes/admin/` | `class-diagnostics.php` | `mxchat-basic/includes/class-mxchat-admin.php` |
| 20 | DEC-ADM-004 | Operational Controls | `includes/admin/pages/` | `class-security-page.php` (moderation/GDPR/limits UI) | `axiachat-ai/includes/settings.php` |
| 21 | DEC-ADM-005 | Admin UX | `admin/js/` | `admin.js` (vanilla JS) | `mxchat-basic/js/mxchat-admin.js` |

### 6.6 DOM-SECURITY (5/5 MVP)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 22 | DEC-SEC-001 | Input Validation and Sanitization | `includes/security/` | `class-sanitizer.php` | `axiachat-ai/includes/sanitize-helpers.php` |
| 23 | DEC-SEC-002 | Authentication and Capability Enforcement | `includes/security/` | `class-auth-enforcer.php` | `axiachat-ai/includes/class-aichat-ajax.php` |
| 24 | DEC-SEC-003 | Content Moderation and Abuse Prevention | `includes/security/` | `class-moderator.php` | `axiachat-ai/includes/moderation.php` |
| 25 | DEC-SEC-004 | Privacy Controls and Usage Limits | `includes/security/` | `class-privacy-manager.php` | `axiachat-ai/includes/class-aichat-ajax.php` |
| 26 | DEC-SEC-005 | Audit Logging | `includes/security/` | `class-audit-logger.php` | `axiachat-ai/includes/class-aichat-ajax.php` |

---

## 7. Deferred Capability-to-Module Mapping (12 capabilities)

### 7.1 DOM-TOOLS (4/4 deferred)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 27 | DEC-TOL-001 | Tool/Action Registration | `includes/tools/` | `class-tool-registry.php` | `axiachat-ai/includes/add-ons/ai-tools/api.php` |
| 28 | DEC-TOL-002 | Execution Engine | `includes/tools/` | `class-tool-executor.php` | `axiachat-ai/includes/add-ons/ai-tools/api.php` |
| 29 | DEC-TOL-003 | Schema Validation and Lifecycle | `includes/tools/` | `class-macro-registry.php` | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` |
| 30 | DEC-TOL-004 | Admin Controls | `includes/tools/` + `admin/views/` | `class-tools-admin.php` + `tools.php` | `mxchat-basic/includes/admin-actions-page.php` |

### 7.2 DOM-INTEGRATIONS (3/3 deferred)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 31 | DEC-INT-001 | External Protocol Support | `includes/integrations/` | `class-mcp-client.php` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` |
| 32 | DEC-INT-002 | Webhook and Event System | `includes/integrations/` | `class-webhook-executor.php` | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` |
| 33 | DEC-INT-003 | Add-on and Extension Framework | `includes/integrations/` | `class-extension-manager.php` | `mxchat-basic/includes/class-mxchat-addons.php` |

### 7.3 DOM-WOOCOMMERCE (5/5 deferred)

| # | Decision ID | Capability | Module Home | Target File | Source Anchor |
|---|-------------|-----------|-------------|------------|--------------|
| 34 | DEC-WOO-001 | Product Context Injection | `includes/woocommerce/` | `class-product-context.php` | `baachal/baachal.php` |
| 35 | DEC-WOO-002 | Catalog Operations | `includes/woocommerce/` | `class-catalog-search.php` | `baachal/baachal.php`, `baachal/admin/tabs/search.php` |
| 36 | DEC-WOO-003 | Cart and Order Handling | `includes/woocommerce/` | `class-cart-handler.php` | `mxchat-basic/includes/class-mxchat-woocommerce.php` |
| 37 | DEC-WOO-004 | Product Presentation | `includes/woocommerce/` | `class-product-cards.php` | `baachal/baachal.php` |
| 38 | DEC-WOO-005 | WC Platform Compatibility | `includes/woocommerce/` | HPOS declaration in bootstrap | `baachal/baachal.php` |

---

## 8. Admin Decomposition Architecture (CFI-ADM-001)

### 8.1 Problem

MxChat's `class-mxchat-admin.php` is a 9036-line monolithic class handling all admin pages,
settings, AJAX, and diagnostics. This violates single-responsibility and makes the file impossible
to navigate, test, or extend.

### 8.2 Solution: Per-Page Controller Pattern

The admin module is decomposed into:

1. **Admin_Router** (`class-admin-router.php`) -- Menu registration, page dispatch, shared asset loading
2. **Settings_Manager** (`class-settings-manager.php`) -- WP Settings API, encrypt/decrypt
3. **Diagnostics** (`class-diagnostics.php`) -- Panel registration, health aggregation
4. **Per-page controllers** (`pages/class-{page}-page.php`) -- One controller per admin page

### 8.3 Controller Constraints

| Constraint | Value | Rationale |
|-----------|-------|-----------|
| Max LOC per controller | 600 | Prevents monolith regrowth (CFI-ADM-001) |
| Max methods per controller | 15 | Keeps interface discoverable |
| Template rendering | Via `admin/views/{page}.php` | Separates logic from presentation |
| Settings registration | Via `Settings_Manager::register()` | Centralized settings API |
| AJAX handling | Via dedicated handler methods | Not mixed with page rendering |

### 8.4 Page Controller Interface

Each page controller follows the same interface:

```php
namespace WPAIC\Admin\Pages;

class General_Page {
    public function register_settings(): void;    // Register WP settings fields
    public function render(): void;               // Render page via template
    public function enqueue_assets(): void;       // Page-specific assets
    public function handle_ajax(): void;          // Page-specific AJAX (if any)
}
```

### 8.5 Tab Registration (Hosting Pattern)

Pages support tabbed layouts via the `wpaic_admin_tabs` filter:

```php
// Admin_Router dispatches to the correct page controller
// Page controller fires tab filter
$tabs = apply_filters('wpaic_admin_tabs', $default_tabs, $page_slug);

// External modules (WooCommerce, Security) register tabs:
add_filter('wpaic_admin_tabs', function ($tabs, $page) {
    if ($page === 'chat') {
        $tabs['woocommerce'] = [
            'label'    => __('WooCommerce', 'wp-ai-chatbot'),
            'renderer' => [WooCommerce\Admin_Tab::class, 'render'],
            'priority' => 50,
        ];
    }
    return $tabs;
}, 10, 2);
```

### 8.6 Diagnostics Panel Registration (Hosting Pattern, RES-XD-010)

```php
// Admin_Router fires panel registration action on diagnostics page load
do_action('wpaic_admin_register_diagnostic_panel', $diagnostics);

// Security module registers its audit panel:
add_action('wpaic_admin_register_diagnostic_panel', function ($diagnostics) {
    $diagnostics->register_panel([
        'id'       => 'security-audit',
        'title'    => __('Security Audit', 'wp-ai-chatbot'),
        'renderer' => [Security\Audit_Logger::class, 'render_panel'],
        'priority' => 10,
    ]);
});
```

### 8.7 Admin Page List

| Page Slug | Controller | Template | Purpose |
|-----------|-----------|----------|---------|
| `wpaic-general` | `General_Page` | `admin/views/general.php` | Provider selection, model config, basic behavior |
| `wpaic-providers` | `Providers_Page` | `admin/views/providers.php` | API key management, provider config |
| `wpaic-knowledge` | `Knowledge_Page` | `admin/views/knowledge.php` | Knowledge base, ingestion, training |
| `wpaic-chat` | `Chat_Page` | `admin/views/chat.php` | Chat behavior, prompts, appearance, multi-bot |
| `wpaic-security` | `Security_Page` | `admin/views/security.php` | Moderation, privacy, rate limits, audit |
| `wpaic-tools` | `Tools_Page` | `admin/views/tools.php` | Tools, integrations, extensions |
