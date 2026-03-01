# Plugin Directory Structure

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Design Rationale

The directory tree follows a **module-per-domain** layout where each of the 9 canonical domains
(provider, runtime, migration, rag, admin, security, tools, integrations, woocommerce) maps to one
directory under `includes/`. This merges the best patterns from all three reference plugins:

- **AxiaChat**: Interface-driven provider abstraction with subdirectories
  (`includes/interfaces/`, `includes/providers/`, `includes/add-ons/`)
- **MxChat**: Class-per-concern organization (`class-mxchat-admin.php`, `class-mxchat-integrator.php`)
- **Baachal**: Centralized filterable hook array, tab-based admin, clean `templates/` separation

The unified plugin avoids:
- Monolithic god-classes (MxChat's 9036-line `class-mxchat-admin.php`)
- Deep nesting beyond 2 subdirectory levels
- Mixing domain logic with presentation templates

---

## 2. Complete Directory Tree

```
wp-ai-chatbot/
|
|-- wp-ai-chatbot.php                    # Plugin bootstrap: constants, autoloader, init
|-- uninstall.php                        # Clean uninstall handler (data preservation flag)
|-- composer.json                        # Dev dependencies (PHP-CS-Fixer, PHPStan, stubs)
|-- package.json                         # Dev dependencies (Biome, Vitest, husky)
|
|-- includes/                            # All domain module code (PHP)
|   |-- autoloader.php                   # PSR-4-compatible custom autoloader
|   |-- class-plugin.php                 # Main orchestrator: wires modules, registers hooks
|   |
|   |-- provider/                        # DOM-PROVIDER: AI provider registry and adapters
|   |   |-- class-provider-registry.php  #   Singleton+Factory registry (SRC-AXI pattern)
|   |   |-- class-model-registry.php     #   Model catalog with resolution pipeline
|   |   |-- interface-provider.php       #   Provider adapter contract
|   |   |-- interface-embedding.php      #   Embedding provider contract (RES-XD-008)
|   |   `-- adapters/                    #   Per-provider adapter implementations
|   |       |-- class-openai-adapter.php
|   |       |-- class-claude-adapter.php
|   |       |-- class-gemini-adapter.php
|   |       |-- class-deepseek-adapter.php
|   |       `-- class-grok-adapter.php
|   |
|   |-- runtime/                         # DOM-RUNTIME: Chat pipeline, sessions, streaming
|   |   |-- class-message-pipeline.php   #   Pre-process -> dispatch -> post-process chain
|   |   |-- class-session-manager.php    #   Session ownership, persistence, token tracking
|   |   |-- class-stream-handler.php     #   SSE streaming transport layer (CFI-RUN-003)
|   |   `-- class-context-injector.php   #   Page context + handler replacement hook
|   |
|   |-- migration/                       # DOM-MIGRATION: Lifecycle, versioning, settings
|   |   |-- class-lifecycle-manager.php  #   Activate/deactivate/uninstall with extension hooks
|   |   |-- class-migration-registry.php #   Versioned migration callbacks (CFI-MIG-002)
|   |   `-- class-option-migrator.php    #   Key mapping + value transforms (CFI-MIG-003)
|   |
|   |-- rag/                             # DOM-RAG: Ingestion, chunking, retrieval, assembly
|   |   |-- class-ingestion-pipeline.php #   Queue-based ingestion (MXC queue + AXI cleaning)
|   |   |-- class-chunker.php            #   Overlap-based splitting, configurable size
|   |   |-- class-retriever.php          #   Multi-mode retrieval (local/Pinecone/page/auto)
|   |   |-- class-context-assembler.php  #   Security policy injection, template tokens
|   |   `-- class-embedding-client.php   #   Embedding provider dispatch via provider registry
|   |
|   |-- admin/                           # DOM-ADMIN: Settings, diagnostics, per-page controllers
|   |   |-- class-admin-router.php       #   Menu registration, page routing, asset loading
|   |   |-- class-settings-manager.php   #   WP Settings API integration, auto-decrypt
|   |   |-- class-diagnostics.php        #   Panel registration hook (RES-XD-010)
|   |   `-- pages/                       #   Per-page controllers (<600 LOC each, CFI-ADM-001)
|   |       |-- class-general-page.php   #     General settings (provider selection, model)
|   |       |-- class-providers-page.php #     API key management, provider config
|   |       |-- class-knowledge-page.php #     Knowledge base, ingestion, training
|   |       |-- class-chat-page.php      #     Chat behavior, prompts, appearance
|   |       |-- class-security-page.php  #     Security, moderation, privacy settings
|   |       `-- class-tools-page.php     #     Tools, integrations, extensions management
|   |
|   |-- security/                        # DOM-SECURITY: Validation, auth, moderation, audit
|   |   |-- class-sanitizer.php          #   Unified sanitization library (CFI-SEC-001)
|   |   |-- class-auth-enforcer.php      #   Nonce + capability dual-check
|   |   |-- class-moderator.php          #   5-layer moderation pipeline
|   |   |-- class-privacy-manager.php    #   IP privacy, usage limits, GDPR
|   |   |-- class-encryption.php         #   AES-256-GCM encryption module (CFI-SEC-002)
|   |   `-- class-audit-logger.php       #   Structured security event logging
|   |
|   |-- tools/                           # DOM-TOOLS (deferred): Tool registration, execution
|   |   `-- .gitkeep                     #   Extension point stub -- Phase 03+
|   |
|   |-- integrations/                    # DOM-INTEGRATIONS (deferred): MCP, webhooks, extensions
|   |   `-- .gitkeep                     #   Extension point stub -- Phase 03+
|   |
|   `-- woocommerce/                     # DOM-WOOCOMMERCE (deferred): Product context, cart ops
|       `-- .gitkeep                     #   Extension point stub -- Phase 03+
|
|-- admin/                               # Admin presentation layer (templates + assets)
|   |-- views/                           #   Admin page PHP templates
|   |   |-- general.php
|   |   |-- providers.php
|   |   |-- knowledge.php
|   |   |-- chat.php
|   |   |-- security.php
|   |   `-- tools.php
|   |-- css/
|   |   `-- admin.css                    #   Admin-only stylesheet
|   `-- js/
|       `-- admin.js                     #   Admin-only vanilla JS (CFI-ADM-005)
|
|-- assets/                              # Frontend presentation layer
|   |-- css/
|   |   `-- chatbot.css                  #   Chat widget stylesheet
|   |-- js/
|   |   `-- chatbot.js                   #   Chat widget vanilla JS (streaming, multi-bot)
|   `-- images/
|       `-- .gitkeep
|
|-- templates/                           # Frontend PHP templates
|   `-- chatbot-widget.php               #   Chat widget HTML shell (shortcode output)
|
|-- languages/                           # Internationalization
|   `-- wp-ai-chatbot.pot                #   Translation template
|
|-- lib/                                 # Shared infrastructure utilities (existing)
|   |-- structured-logger.php            #   PHP structured logger with JSON output
|   |-- structured-logger.js             #   JS structured logger (Node.js, CJS)
|   |-- health-endpoint.php              #   WP REST /health endpoint
|   |-- rate-limiter.php                 #   IP-based rate limiting via WP transients
|   `-- backup-manager.php               #   Plugin data export/import REST endpoints
|
`-- logs/                                # Runtime log output (.gitignored contents)
    `-- .gitignore                       #   Keeps dir, ignores log files
```

### Directory Responsibility Summary

| Directory | Responsibility | Owner Domain |
|-----------|---------------|-------------|
| `includes/provider/` | AI provider registration, model catalog, adapter dispatch | DOM-PROVIDER |
| `includes/runtime/` | Message pipeline, session handling, streaming transport | DOM-RUNTIME |
| `includes/migration/` | Plugin lifecycle, schema versioning, settings migration | DOM-MIGRATION |
| `includes/rag/` | Content ingestion, chunking, retrieval, context assembly | DOM-RAG |
| `includes/admin/` | Settings architecture, page routing, diagnostics | DOM-ADMIN |
| `includes/security/` | Sanitization, auth, moderation, encryption, audit | DOM-SECURITY |
| `includes/tools/` | Tool registration, execution engine, macro registry | DOM-TOOLS (deferred) |
| `includes/integrations/` | MCP client, webhooks, extension framework | DOM-INTEGRATIONS (deferred) |
| `includes/woocommerce/` | Product context, cart operations, HPOS compat | DOM-WOOCOMMERCE (deferred) |
| `admin/` | Admin UI templates and static assets | Presentation (DOM-ADMIN) |
| `assets/` | Frontend UI static assets (CSS/JS/images) | Presentation (DOM-RUNTIME) |
| `templates/` | Frontend PHP templates (shortcode output) | Presentation (DOM-RUNTIME) |
| `lib/` | Cross-domain infrastructure utilities | Infrastructure |
| `languages/` | Translation files | i18n |
| `logs/` | Runtime log output | Observability |

---

## 3. Constants and Global Configuration Architecture

All constants are defined in `wp-ai-chatbot.php` before any module loads. No constant is defined
anywhere else in the codebase.

### 3.1 Plugin Identity Constants

```php
// Plugin version (matches plugin header)
define('WPAIC_VERSION', '1.0.0');

// Minimum requirements
define('WPAIC_MIN_PHP', '7.4');
define('WPAIC_MIN_WP', '5.0');

// Text domain (matches Plugin Name slug)
define('WPAIC_TEXT_DOMAIN', 'wp-ai-chatbot');
```

### 3.2 Path and URL Constants

```php
// Filesystem paths (trailing slash via plugin_dir_path)
define('WPAIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAIC_INCLUDES_DIR', WPAIC_PLUGIN_DIR . 'includes/');

// URL paths (trailing slash via plugin_dir_url)
define('WPAIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAIC_ASSETS_URL', WPAIC_PLUGIN_URL . 'assets/');
define('WPAIC_ADMIN_URL', WPAIC_PLUGIN_URL . 'admin/');
```

### 3.3 Feature Flag Constants

```php
// Debug mode (default off; override in wp-config.php)
define('WPAIC_DEBUG', false);

// Conditional domain loading flags (auto-detected at runtime)
// These are NOT constants -- they are determined by the bootstrap sequence.
// See bootstrap_sequence.md for WooCommerce detection logic.
```

### 3.4 Prefix and Slug Constants

```php
// Database prefix for options, transients, and custom tables
define('WPAIC_PREFIX', 'wpaic_');

// REST API namespace
define('WPAIC_REST_NAMESPACE', 'wp-ai-chatbot/v1');

// Plugin basename (for action links, activation hooks)
define('WPAIC_BASENAME', plugin_basename(__FILE__));
```

### 3.5 Capacity Limits

```php
// Maximum log file age in days (used by structured logger)
define('WPAIC_LOG_RETENTION_DAYS', 30);

// Default rate limit (requests per window, per endpoint)
define('WPAIC_DEFAULT_RATE_LIMIT', 60);
define('WPAIC_DEFAULT_RATE_WINDOW', 60); // seconds
```

### 3.6 Reference Constant Comparison

| Our Constant | AxiaChat Equivalent | MxChat Equivalent | Baachal Equivalent |
|-------------|--------------------|--------------------|-------------------|
| `WPAIC_VERSION` | `AICHAT_VERSION` | `MXCHAT_VERSION` | `BAACHAL_VERSION` |
| `WPAIC_PLUGIN_DIR` | `AICHAT_PLUGIN_DIR` | (implicit) | `BAACHAL_PLUGIN_PATH` |
| `WPAIC_PLUGIN_URL` | `AICHAT_PLUGIN_URL` | (implicit) | `BAACHAL_PLUGIN_URL` |
| `WPAIC_DEBUG` | `AICHAT_DEBUG` | (none) | (none) |
| `WPAIC_PREFIX` | `aichat_` (convention) | `mxchat_` (convention) | `baachal_` (convention) |

---

## 4. Separation Principles

### 4.1 Domain Logic vs Presentation

Domain logic lives under `includes/{domain}/`. Presentation (HTML templates, CSS, JS) lives under
`admin/` (admin-facing) and `assets/` + `templates/` (frontend-facing). Domain modules never emit
HTML directly -- they return data that templates render.

### 4.2 Infrastructure vs Domain

Shared infrastructure (logging, health checks, rate limiting, backup) lives under `lib/`.
Infrastructure modules have no dependency on domain modules. Domain modules may call `lib/`
utilities but `lib/` never calls `includes/`.

### 4.3 MVP vs Deferred

Deferred domain directories (`tools/`, `integrations/`, `woocommerce/`) exist as empty stubs with
`.gitkeep` files. This establishes the directory structure without coupling MVP code to deferred
implementations. When Phase 03+ populates these directories, the autoloader will resolve them
automatically.

---

## 5. File Count Estimates

| Directory | MVP Files | Deferred Files | Total |
|-----------|----------|---------------|-------|
| `includes/provider/` | 8 | 0 | 8 |
| `includes/runtime/` | 4 | 0 | 4 |
| `includes/migration/` | 3 | 0 | 3 |
| `includes/rag/` | 5 | 0 | 5 |
| `includes/admin/` | 3 + 6 pages | 0 | 9 |
| `includes/security/` | 6 | 0 | 6 |
| `includes/tools/` | 0 | ~5 | 0 (stub) |
| `includes/integrations/` | 0 | ~5 | 0 (stub) |
| `includes/woocommerce/` | 0 | ~5 | 0 (stub) |
| Root includes | 2 | 0 | 2 |
| **Total includes/** | **37** | **~15** | **37 MVP** |
| `admin/views/` | 6 | 0 | 6 |
| `assets/` | 2 | 0 | 2 |
| `templates/` | 1 | 0 | 1 |
| `lib/` | 5 | 0 | 5 |
| Root files | 2 | 0 | 2 |
| **Grand Total** | **53** | **~15** | **53 MVP** |
