# Hook and Filter Architecture

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

The plugin exposes a documented set of action hooks and filters that follow WordPress conventions.
All hooks use the `wpaic_` prefix. This document defines naming conventions, priority rules,
the hook registration pattern, and the complete hook registry for Phase 03+ implementation.

---

## 2. Naming Conventions

### 2.1 Action Hooks (do_action)

Pattern: `wpaic_{noun}_{verb}` or `wpaic_{noun}_{lifecycle_event}`

Examples:
- `wpaic_pipeline_pre_process` -- fires before pipeline processing
- `wpaic_pipeline_post_process` -- fires after pipeline processing
- `wpaic_session_created` -- fires after session creation
- `wpaic_message_sent` -- fires after message sent and response saved

### 2.2 Filter Hooks (apply_filters)

Pattern: `wpaic_{noun}` or `wpaic_{noun}_{qualifier}`

Examples:
- `wpaic_system_prompt` -- filters the system prompt string
- `wpaic_admin_capability` -- filters the required capability for admin pages
- `wpaic_sanitizer_types` -- filters the sanitizer type registry
- `wpaic_sensitive_fields` -- filters the list of encrypted option keys

### 2.3 Registration Hooks (registries)

Pattern: `wpaic_register_{noun}` (action hooks where objects register themselves)

Examples:
- `wpaic_register_providers` -- action to register provider adapters
- `wpaic_register_rag_sources` -- action to register RAG source adapters
- `wpaic_register_tools` -- action to register tools (Phase 07+)
- `wpaic_diagnostics_panels` -- filter to register diagnostics panels

---

## 3. Priority Rules

| Priority Range | Usage |
|---------------|-------|
| 1-9 | Reserved for plugin core internal hooks (never used by extensions) |
| 10 | Default core registration (MVP modules use this) |
| 20-50 | Extension modules (add-ons, integrations) |
| 100+ | Last-resort overrides (use sparingly, document clearly) |

The plugin itself uses priority 10 for all its own hook registrations. Extensions should use
priority 20 or higher to run after core.

---

## 4. Action vs Filter Guidelines

**Action hooks** (`do_action`): Used when the hook fires an event with no expected return value.
Extensions react to the event but don't modify the triggering data.
- `wpaic_session_created($session_id, $user_id, $ip_hash)`
- `wpaic_message_sent($message_id, $session_id, $provider, $tokens)`
- `wpaic_queue_item_failed($item_id, $knowledge_id, $error_message)`

**Filter hooks** (`apply_filters`): Used when the hook expects a return value that may be
modified by extensions. The plugin uses the returned value.
- `wpaic_system_prompt($prompt, $bot_id, $context)` -- extensions can customize system prompt
- `wpaic_rag_context($context_string, $session_id, $query)` -- extensions can modify RAG output
- `wpaic_response_html($html, $session_id)` -- extensions can post-process rendered response

---

## 5. Complete Hook Registry (Phase 02 Defined Hooks)

### 5.1 Bootstrap Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_register_providers` | action | `$registry` (Provider_Registry instance) | Register provider adapters |
| `wpaic_register_rag_sources` | action | `$registry` (Source_Adapter_Registry) | Register RAG source adapters |
| `wpaic_loaded` | action | none | Plugin fully loaded, all modules initialized |

### 5.2 Provider Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_provider_request` | filter | `$payload, $provider_slug, $model` | Modify payload before provider call |
| `wpaic_provider_response` | filter | `$response, $provider_slug, $model` | Modify raw provider response |
| `wpaic_provider_cost` | filter | `$cost_usd, $usage, $provider_slug` | Modify cost calculation |

### 5.3 Runtime / Pipeline Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_pipeline_pre_process` | action | `$message, $session_id` | Before pre-processing stage |
| `wpaic_message_handler` | filter | `$result, $message, $session_id` | Replace pipeline (BAA pattern) |
| `wpaic_pipeline_post_process` | action | `$response, $session_id, $usage` | After post-processing stage |
| `wpaic_system_prompt` | filter | `$prompt, $bot_id, $context` | Customize system prompt |
| `wpaic_instruction_tokens_map` | filter | `$tokens_map` | Add template tokens |
| `wpaic_security_policy` | filter | `$policy_text` | Customize security policy block |
| `wpaic_message_sent` | action | `$message_id, $session_id, $provider, $tokens` | Message saved to DB |

### 5.4 Session Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_session_created` | action | `$session_id, $user_id, $ip_hash` | New session created |
| `wpaic_session_expired` | action | `$session_id` | Session expired |

### 5.5 RAG / Knowledge Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_rag_pre_chunk` | filter | `$text, $source_type, $item_id` | Pre-process text before chunking |
| `wpaic_rag_context` | filter | `$context, $session_id, $query` | Modify assembled context |
| `wpaic_rag_suggestions` | filter | `$suggestions, $context, $session_id` | Modify suggested questions |
| `wpaic_queue_item_failed` | action | `$item_id, $knowledge_id, $error` | Queue item failure notification |

### 5.6 Security Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_admin_capability` | filter | `$capability` | Override required admin capability |
| `wpaic_sanitizer_types` | filter | `$types_map` | Register custom sanitizer types |
| `wpaic_sensitive_fields` | filter | `$fields_array` | Register additional encrypted fields |
| `wpaic_moderation_spam_check` | filter | `$blocked, $message` | Custom spam detection |
| `wpaic_moderation_ai_result` | filter | `$flagged, $message, $provider` | Override AI moderation result |
| `wpaic_rate_limit_bypass_check` | filter | `$bypass, $ip, $endpoint` | Custom rate limit bypass logic |

### 5.7 Admin Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_diagnostics_panels` | filter | `$panels_array` | Register diagnostics dashboard panels |
| `wpaic_admin_pages` | filter | `$pages_array` | Register additional admin pages |
| `wpaic_settings_saved` | action | `$page_slug, $changed_keys` | Admin settings save notification |

### 5.8 Migration Hooks

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_before_migration` | action | `$from_version, $to_version` | Before migration run |
| `wpaic_after_migration` | action | `$from_version, $to_version, $report` | After successful migration |
| `wpaic_lifecycle_activate` | action | `$is_network_wide` | Plugin activated |
| `wpaic_lifecycle_deactivate` | action | `$is_network_wide` | Plugin deactivated |
| `wpaic_lifecycle_uninstall` | action | none | Plugin uninstalled (before data deletion) |

### 5.9 Extension Point Stubs (Phase 07+)

| Hook | Type | Args | Description |
|------|------|------|-------------|
| `wpaic_register_tools` | action | `$registry` | Register tool definitions |
| `wpaic_tool_pre_execute` | action | `$tool_slug, $params, $session_id` | Before tool execution |
| `wpaic_tool_execute_{slug}` | filter | `$result, $params, $session_id` | Execute a specific tool |
| `wpaic_tool_post_execute` | action | `$tool_slug, $result, $session_id` | After tool execution |
| `wpaic_register_integrations` | action | `$registry` | Register integration adapters |

---

## 6. Hook Documentation Standard

Every registered hook MUST be documented in `includes/class-plugin.php` as a docblock:

```php
/**
 * Fires after a chat message is sent and the response saved.
 *
 * @since 1.0.0
 * @param string $message_id  UUID of the saved assistant message row.
 * @param string $session_id  UUID of the session.
 * @param string $provider    Provider slug (e.g., 'openai').
 * @param int    $tokens      Total tokens used (prompt + completion).
 */
do_action( 'wpaic_message_sent', $message_id, $session_id, $provider, $tokens );
```

---

## 7. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Hook-driven extensibility with wpaic_ prefix | `baachal/baachal.php` hook registration | Adopted; extended with all domains |
| `wpaic_message_handler` filter (handler replacement) | `baachal/baachal.php` handler hook | Adopted; renamed to WPAIC prefix |
| Registration action pattern (`do_action('register_...')`) | `axiachat-ai/includes/add-ons/ai-tools/api.php` | Adopted; generalized |
| Filter chain for system prompt | `axiachat-ai/includes/contexto-functions.php` | Adopted |
| Admin page registration filter | `mxchat-basic/includes/class-mxchat-addons.php` | Adapted |
