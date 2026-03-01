# Naming Conventions

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Plugin Prefix

All globally scoped identifiers use the prefix `wpaic` (WP AI Chatbot).

| Scope | Prefix Form | Example |
|-------|------------|---------|
| PHP constants | `WPAIC_` | `WPAIC_VERSION`, `WPAIC_PLUGIN_DIR` |
| PHP functions (global) | `wpaic_` | `wpaic_get_setting()`, `wpaic_register_providers()` |
| PHP classes | `WPAIC\` namespace | `WPAIC\Provider\Provider_Registry` |
| WordPress hooks/filters | `wpaic_` | `wpaic_before_message_send`, `wpaic_register_hooks` |
| WordPress options | `wpaic_` | `wpaic_api_key_openai`, `wpaic_chat_model` |
| WordPress transients | `wpaic_` | `wpaic_rate_limit_{ip}`, `wpaic_model_cache` |
| REST API namespace | `wp-ai-chatbot/v1` | `/wp-json/wp-ai-chatbot/v1/health` |
| Custom DB tables | `{$wpdb->prefix}wpaic_` | `wp_wpaic_sessions`, `wp_wpaic_migrations` |
| CSS classes | `wpaic-` | `wpaic-chatbot-widget`, `wpaic-admin-page` |
| JS globals | `wpaic` | `window.wpaicConfig`, `wpaic.chatbot` |
| Cron events | `wpaic_` | `wpaic_cleanup_sessions`, `wpaic_index_content` |
| Shortcodes | `wpaic_` | `[wpaic_chatbot]`, `[wpaic_chatbot id="2"]` |
| Text domain | `wp-ai-chatbot` | `__('Settings', 'wp-ai-chatbot')` |

### Reference Prefix Comparison

| Element | AxiaChat | MxChat | Baachal | Ours |
|---------|----------|--------|---------|------|
| Constant prefix | `AICHAT_` | `MXCHAT_` | `BAACHAL_` | `WPAIC_` |
| Function prefix | `aichat_` | `mxchat_` | `baachal_` | `wpaic_` |
| Hook prefix | `aichat_` | `mxchat_` | `baachal_` | `wpaic_` |
| Option prefix | `aichat_` | `mxchat_` | `baachal_` | `wpaic_` |

---

## 2. PHP Naming

### 2.1 Files

| Type | Pattern | Example |
|------|---------|---------|
| Class file | `class-{name}.php` | `class-provider-registry.php` |
| Interface file | `interface-{name}.php` | `interface-provider.php` |
| Trait file | `trait-{name}.php` | `trait-has-hooks.php` |
| Procedural file | `{name}.php` (no prefix) | `autoloader.php` |
| Test file | `{ClassUnderTest}Test.php` | `ProviderRegistryTest.php` |

File names use lowercase `kebab-case` with a type prefix to match WordPress conventions
(as seen in AxiaChat `class-aichat-provider-registry.php` and MxChat `class-mxchat-admin.php`).

### 2.2 Classes

| Element | Convention | Example |
|---------|-----------|---------|
| Class name | `PascalCase` with underscores between words | `Provider_Registry` |
| Namespace | `WPAIC\{Domain}` | `WPAIC\Provider`, `WPAIC\Admin\Pages` |
| Full qualified | `WPAIC\{Domain}\{Class}` | `WPAIC\Provider\Provider_Registry` |

**Note on WordPress naming**: WordPress core uses `PascalCase_With_Underscores` for classes
(e.g., `WP_REST_Request`). We follow this convention within our namespace to be consistent with
the WordPress ecosystem. This differs from strict PSR-1 which uses `PascalCase` without underscores.

### 2.3 Methods and Functions

| Element | Convention | Example |
|---------|-----------|---------|
| Public method | `snake_case` | `$registry->get_provider('openai')` |
| Private method | `snake_case` | `$this->validate_config($config)` |
| Global function | `wpaic_` prefix + `snake_case` | `wpaic_get_setting('api_key')` |
| Static factory | `snake_case` | `Provider_Registry::get_instance()` |
| Boolean method | Question form | `$session->is_valid()`, `$user->has_access()` |

### 2.4 Variables

| Element | Convention | Example |
|---------|-----------|---------|
| Local variable | `$snake_case` | `$provider_slug`, `$message_content` |
| Class property | `$snake_case` | `$this->providers`, `$this->migration_version` |
| Boolean variable | Question form | `$is_admin`, `$has_woocommerce`, `$should_stream` |
| Constant (class) | `UPPER_SNAKE` | `self::MAX_RETRIES` |
| Constant (global) | `WPAIC_UPPER_SNAKE` | `WPAIC_VERSION` |

### 2.5 Interfaces and Traits

| Element | Convention | Example |
|---------|-----------|---------|
| Interface name | `{Noun}` (no `I` prefix) | `Provider`, `Embedding` |
| Interface file | `interface-{name}.php` | `interface-provider.php` |
| Trait name | `Has_{Capability}` or `{Behavior}_Trait` | `Has_Hooks`, `Retry_Trait` |
| Trait file | `trait-{name}.php` | `trait-has-hooks.php` |

---

## 3. WordPress Hook Naming

### 3.1 Actions

Pattern: `wpaic_{timing}_{noun}_{verb}` or `wpaic_{noun}_{event}`

| Timing | Pattern | Example |
|--------|---------|---------|
| Before | `wpaic_before_{action}` | `wpaic_before_message_send` |
| After | `wpaic_after_{action}` | `wpaic_after_message_send` |
| On event | `wpaic_{noun}_{event}` | `wpaic_session_created` |
| Registration | `wpaic_register_{type}` | `wpaic_register_providers` |
| Lifecycle | `wpaic_{lifecycle}` | `wpaic_activated`, `wpaic_deactivated` |

### 3.2 Filters

Pattern: `wpaic_{noun}_{aspect}` or `wpaic_filter_{target}`

| Purpose | Pattern | Example |
|---------|---------|---------|
| Modify data | `wpaic_{noun}_{aspect}` | `wpaic_message_content` |
| Hook array | `wpaic_register_hooks` | `wpaic_register_hooks` (BAA pattern) |
| Capability | `wpaic_{domain}_capabilities` | `wpaic_tools_capabilities` |
| Config | `wpaic_{module}_config` | `wpaic_provider_config` |

### 3.3 Hook Priority Guidelines

| Priority | Purpose | Example |
|----------|---------|---------|
| 5 | Security checks (early interception) | Moderation, rate limiting |
| 10 | Default (standard WordPress priority) | Most hooks |
| 15 | Post-processing, enrichment | WooCommerce product enrichment |
| 20 | Logging, audit (after all processing) | Audit logger |
| 99 | Cleanup, teardown | Session cleanup |

---

## 4. WordPress Option Naming

### 4.1 Options

Pattern: `wpaic_{domain}_{setting}`

| Domain | Example Options |
|--------|----------------|
| Provider | `wpaic_provider_default`, `wpaic_provider_openai_key` |
| Runtime | `wpaic_runtime_stream_enabled`, `wpaic_runtime_max_tokens` |
| RAG | `wpaic_rag_chunk_size`, `wpaic_rag_retrieval_mode` |
| Admin | `wpaic_admin_sidebar_position` |
| Security | `wpaic_security_rate_limit`, `wpaic_security_moderation_enabled` |
| Migration | `wpaic_migration_version`, `wpaic_migration_log` |

### 4.2 Transients

Pattern: `wpaic_{purpose}_{identifier}`

| Purpose | Example |
|---------|---------|
| Rate limiting | `wpaic_rate_{endpoint}_{ip_hash}` |
| Caching | `wpaic_cache_models`, `wpaic_cache_products_{query_hash}` |
| Temporary state | `wpaic_temp_migration_lock` |

### 4.3 Custom Table Names

Pattern: `{$wpdb->prefix}wpaic_{entity}`

| Table | Purpose |
|-------|---------|
| `wp_wpaic_sessions` | Chat session storage (CFI-RUN-002) |
| `wp_wpaic_messages` | Message history |
| `wp_wpaic_migrations` | Migration execution log (CFI-MIG-002) |
| `wp_wpaic_knowledge` | Knowledge base content index |
| `wp_wpaic_chunks` | Content chunks with embeddings |

---

## 5. JavaScript Naming

### 5.1 Variables and Functions

| Element | Convention | Example |
|---------|-----------|---------|
| Variable | `camelCase` | `chatContainer`, `messageContent` |
| Function | `camelCase` | `sendMessage()`, `initChatbot()` |
| Constant | `UPPER_SNAKE` | `MAX_RETRIES`, `API_ENDPOINT` |
| DOM element ref | `camelCase` + `El` suffix | `chatBoxEl`, `inputFieldEl` |
| Event handler | `handle{Event}` or `on{Event}` | `handleSubmit()`, `onMessageReceived()` |
| Boolean | Question form | `isStreaming`, `hasSession`, `shouldRetry` |

### 5.2 Script Handles (wp_enqueue_script)

Pattern: `wpaic-{purpose}`

| Handle | File | Context |
|--------|------|---------|
| `wpaic-chatbot` | `assets/js/chatbot.js` | Frontend |
| `wpaic-admin` | `admin/js/admin.js` | Admin |

### 5.3 CSS Class Naming

Pattern: `wpaic-{block}__{element}--{modifier}` (simplified BEM)

| Type | Pattern | Example |
|------|---------|---------|
| Block | `wpaic-{block}` | `wpaic-chatbot`, `wpaic-admin` |
| Element | `wpaic-{block}__{element}` | `wpaic-chatbot__input`, `wpaic-chatbot__message` |
| Modifier | `wpaic-{block}--{modifier}` | `wpaic-chatbot--minimized`, `wpaic-message--bot` |
| State | `is-{state}` | `is-loading`, `is-open`, `is-error` |

### 5.4 Localized Data Objects

Pattern: `wpaicData` or `wpaic{Context}Data`

| Object | Context | Example Properties |
|--------|---------|-------------------|
| `wpaicChatData` | Frontend chatbot | `ajaxUrl`, `nonce`, `botId`, `streamEnabled` |
| `wpaicAdminData` | Admin pages | `ajaxUrl`, `nonce`, `restUrl`, `currentPage` |

---

## 6. REST API Endpoint Naming

Namespace: `wp-ai-chatbot/v1`

Pattern: `/{resource}` (nouns, plural) or `/{resource}/{action}` (for RPC-style)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/health` | GET | System health check |
| `/chat` | POST | Send chat message |
| `/chat/stream` | POST | Send chat message with SSE streaming |
| `/sessions` | GET | List user sessions |
| `/sessions/{id}` | DELETE | Clear specific session |
| `/settings` | GET | Read plugin settings (admin) |
| `/settings` | POST | Update plugin settings (admin) |
| `/backup/export` | GET | Export plugin data (admin) |
| `/backup/import` | POST | Import plugin data (admin) |
| `/knowledge` | POST | Trigger knowledge ingestion (admin) |
| `/diagnostics` | GET | Run diagnostic checks (admin) |

---

## 7. Domain Vocabulary Alignment

All module, file, and identifier names use the canonical domain terms from Phase 00 taxonomy:

| Canonical Domain ID | Module Directory | Class Prefix | Hook Prefix |
|---------------------|-----------------|-------------|-------------|
| `provider` | `includes/provider/` | `WPAIC\Provider\` | `wpaic_provider_` |
| `runtime` | `includes/runtime/` | `WPAIC\Runtime\` | `wpaic_runtime_` |
| `migration` | `includes/migration/` | `WPAIC\Migration\` | `wpaic_migration_` |
| `rag` | `includes/rag/` | `WPAIC\Rag\` | `wpaic_rag_` |
| `admin` | `includes/admin/` | `WPAIC\Admin\` | `wpaic_admin_` |
| `security` | `includes/security/` | `WPAIC\Security\` | `wpaic_security_` |
| `tools` | `includes/tools/` | `WPAIC\Tools\` | `wpaic_tools_` |
| `integrations` | `includes/integrations/` | `WPAIC\Integrations\` | `wpaic_integrations_` |
| `woocommerce` | `includes/woocommerce/` | `WPAIC\WooCommerce\` | `wpaic_wc_` |

**Note**: WooCommerce hooks use `wpaic_wc_` (shortened) rather than `wpaic_woocommerce_` for
brevity, following WordPress convention where WooCommerce itself uses `wc_` internally.
