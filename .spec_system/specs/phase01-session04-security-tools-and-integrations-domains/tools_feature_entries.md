# Tools Feature Entries

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Domain**: `DOM-TOOLS`
**Context Pack**: `CP-P01-S04-002`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-TOL-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-TOL-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-TOL-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/api.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Tool registration API with JSON Schema validation` |
| `capability_summary` | Central tool registration API using global registry initialized on `init`. Registration validates type (`function` or `custom`), callable callback, JSON Schema (with defensive normalization: empty arrays to objects for OpenAI compatibility, `additionalProperties` coercion to boolean, required array normalization), name sanitization (alphanumeric + underscore/hyphen only), and description max 600 chars. Configurable per-tool: timeout (default 5s), max calls (default 1), auth support, parallel execution flag. Safe registration wrapper fires `aichat_tool_registered` action hook. Global registry accessible via `aichat_get_registered_tools()`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `evidence_refs` | `["docs/axiachat-ai-feature-map.md"]` |

---

### FEAT-AXI-TOL-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-TOL-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-TOL-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/api.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Provider-specific tool injection and execution` |
| `capability_summary` | Tool injection into provider API calls via WordPress filters. OpenAI Responses (`aichat_openai_responses_tools`): injects `web_search` with domain filtering via capability settings. Gemini (`aichat_gemini_tools`): injects `google_search` with multi-tool limitation notice (REST API limit). Claude Messages (`aichat_claude_messages_tools`): injects `web_search_20250305` (versioned per Anthropic spec) with `allowed_domains` and configurable `max_uses`. Pre-processing filter injects web search policy hint into system message. Reads bot tool configuration from `wp_aichat_bots.tools_json`. Per-bot capability settings with domain restrictions stored in options. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-PROVIDER"]` |

---

### FEAT-AXI-TOL-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-TOL-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-TOL-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Macro registry and tool grouping` |
| `capability_summary` | Macro layer managing named tool collections. Groups multiple atomic tools into selectable bundles persisted in `wp_aichat_macros` DB table. Per-macro: name, label, description, tools array, source tracking (`local`, `mcp`, `webhook`) with `source_ref` for cleanup cascade. Upsert semantics (insert new, update existing). In-memory cache via global with DB as primary source. Expansion resolves both macro names and atomic tool IDs with deduplication. Table existence verified via `information_schema` for fresh installation handling. Cascade delete by source when MCP servers or webhooks removed. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-INTEGRATIONS"]` |

---

### FEAT-AXI-TOL-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-TOL-004` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-TOL-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/api.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Per-bot capability settings and access control` |
| `capability_summary` | Per-bot tool access control via `aichat_tools_capability_settings` option. Capability settings map: per-bot configuration of allowed tools, domain restrictions (`allowed_domains` array for web search), and tool enablement. `aichat_get_capability_settings_for_bot($bot_slug)` retrieves settings; `aichat_save_capability_settings_for_bot($bot_slug, $settings_map)` persists. Settings applied at tool injection time (filter priority) to control which tools reach the provider API per bot. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN", "DOM-SECURITY"]` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-TOL-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-TOL-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-TOL-MXC-001` |
| `source_path` | `mxchat-basic/js/mxchat_actions.js`, `mxchat-basic/includes/admin-actions-page.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Intent-based action registration and matching` |
| `capability_summary` | Action/intent framework where custom bot behaviors are triggered by meaning-based matching against user messages. Actions defined with: label, trigger phrases, similarity threshold (10-95%), callback function type (from grouped categories), and multi-bot assignment. CRUD operations via AJAX with per-operation nonces. Two-step editor: type selection with category filtering, then configuration form. Bulk operations (select-all, bulk delete). Pagination with debounced search. Pro/addon-gated action types. Action status toggles with optimistic UI. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-MXC-TOL-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-TOL-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-TOL-MXC-001` |
| `source_path` | `mxchat-basic/includes/admin-actions-page.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Action admin interface with type extensibility` |
| `capability_summary` | Split-panel admin UI with mobile-responsive design. Action type selector with grouped callback categories, pro/addon badges, and installation status detection via `get_addon_status()`. Type cards display icon, label, description, and gating badges. Configuration form with nonce field, label input, phrases textarea, threshold range slider, and multi-bot checkbox selector (conditional on `MxChat_Multi_Bot_Core_Manager`). Dashboard section with quick action cards and feature documentation. Output escaping throughout (`esc_html`, `esc_attr`, `esc_url`, `sanitize_title`, `wp_json_encode`). XSS prevention in JS via text node creation. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-TOL-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-TOL-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-TOL-BAA-NA` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-TOOLS` |
| `capability_name` | `Tool registration and execution parity` |
| `capability_summary` | [N/A] Baachal has no tool registry, callable tool schema, macro grouping, action/intent framework, or tool execution callbacks. The chat flow processes user messages and returns AI responses without any intermediate tool execution layer. Provider interactions are direct API calls without tool-calling protocol support (no function calling, no tool use). |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `na_reason` | `No tools subsystem. Baachal implements direct provider chat (request-response) without any tool registry, tool-calling protocol, action matching, or callback execution. Provider calls are inline API calls in the main plugin file.` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 4 | 4 | 0 |
| `SRC-MXC` | 2 | 2 | 0 |
| `SRC-BAA` | 1 | 0 | 1 |
| **Total** | **7** | **6** | **1** |
