# Tools Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S04-002` |
| `session_id` | `phase01-session04-security-tools-and-integrations-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `32000` |
| `domain_scope` | `["DOM-TOOLS"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-TOOLS` - `tools`

**Slice Count**: 5 slices (including 1 N/A entry)
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA` (N/A)

---

### Slice 1 - AxiaChat Tool Registration and Capability API

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-002-TOL-01` |
| `anchor_id` | `ANC-TOL-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/api.php` |
| `symbol_or_scope` | `aichat_register_tool`, `aichat_register_tool_safe`, `aichat_get_registered_tools`, `aichat_get_capability_settings_map`, provider tool injection filters |
| `excerpt_summary` | Central tool registration API (519 lines). Global tools registry initialized on `init`. Tool registration with validation: type (`function` or `custom`), callable callback, JSON Schema normalization (empty arrays to objects for OpenAI compatibility), `additionalProperties` coercion to boolean, required array normalization, name sanitization (alphanumeric + underscore/hyphen), description max 600 chars. Configurable timeout (default 5s), max calls (default 1), auth support, parallel execution flag. Per-bot capability settings stored in options with domain restrictions (`allowed_domains`). Provider-specific tool injection via filters: OpenAI Responses (`aichat_openai_responses_tools`) injects `web_search` with domain filtering, Gemini (`aichat_gemini_tools`) injects `google_search` with multi-tool limitation notice, Claude Messages (`aichat_claude_messages_tools`) injects `web_search_20250305` with `allowed_domains` and `max_uses`. Messages pre-processing filter injects web search policy hint into system message. Action hook `aichat_tool_registered` fires on registration. |
| `excerpt_lines` | `L1-L519` |
| `token_estimate` | `7000` |
| `secondary_domains` | `["DOM-PROVIDER", "DOM-SECURITY"]` |
| `notes` | Provider-specific schema normalization is critical for cross-provider tool compatibility. Capability settings enable per-bot tool access control. Tool injection filters run at provider call time, not registration time. |

---

### Slice 2 - AxiaChat Macro Registry and Tool Grouping

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-002-TOL-02` |
| `anchor_id` | `ANC-TOL-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` |
| `symbol_or_scope` | `aichat_register_macro`, `aichat_persist_macro`, `aichat_delete_macro`, `aichat_delete_macros_by_source`, `aichat_get_registered_macros`, `aichat_expand_macros_to_atomic` |
| `excerpt_summary` | Macro tools layer (207 lines) managing named tool collections. Macros group multiple atomic tools into selectable bundles persisted in `wp_aichat_macros` DB table. Source tracking (`local`, `mcp`, `webhook`) with `source_ref` for cleanup when parent is deleted. In-memory cache via `$AICHAT_MACRO_TOOLS` global with DB as primary source of truth. Upsert semantics (insert if new, update if exists). Macro expansion resolves both macro names and atomic tool IDs in a single array with deduplication. Table existence verified via `information_schema` before querying (handles fresh installations). |
| `excerpt_lines` | `L1-L207` |
| `token_estimate` | `3000` |
| `secondary_domains` | `["DOM-INTEGRATIONS"]` |
| `notes` | Macro source tracking enables cleanup cascade when MCP servers or webhooks are deleted. DB is primary source of truth; memory cache is convenience. |

---

### Slice 3 - MxChat Actions Admin UI (Client-Side)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-002-TOL-03` |
| `anchor_id` | `ANC-TOL-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/js/mxchat_actions.js` |
| `symbol_or_scope` | Action CRUD, type selection, bulk operations, mobile-responsive split panel |
| `excerpt_summary` | Client-side admin interface for MxChat Actions (953 lines). Intent-triggered custom bot actions with meaning-based matching. Split-panel layout: action list (left) with editor (right). CRUD operations via AJAX with nonce verification on all endpoints (6 distinct nonces). Two-step editor: type selection with search/category filtering, then configuration form (label, trigger phrases, similarity threshold 10-95%, multi-bot assignment). Bulk selection with select-all/indeterminate state and bulk delete. Pagination with debounced search (300ms). Optimistic UI updates for toggle status with revert on error. XSS prevention via `escapeHtml()` text node creation. Mobile responsive with panel switching. Loading overlay and delete confirmation modal. Localization via `mxchActionsData.i18n`. |
| `excerpt_lines` | `L1-L953` |
| `token_estimate` | `5000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Actions are the MxChat equivalent of tools -- intent-triggered callbacks. Not a formal tool-calling API but an action/intent framework. Client-side escaping via text nodes rather than regex. |

---

### Slice 4 - MxChat Actions Admin UI (Server-Side)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-002-TOL-04` |
| `anchor_id` | `ANC-TOL-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/admin-actions-page.php` |
| `symbol_or_scope` | `mxchat_render_actions_page`, type card rendering, form layout |
| `excerpt_summary` | Server-side admin page rendering (509 lines). Split-panel HTML structure with mobile-responsive navigation. Dashboard with quick action cards and feature documentation. Action type selector with grouped callback categories, pro/addon badges, and installation status detection. Configuration form with nonce field (`wp_nonce_field`), label input, phrases textarea, threshold range slider (10-95%), multi-bot checkbox selector (conditional on `MxChat_Multi_Bot_Core_Manager`). Output escaping throughout: `esc_html()`, `esc_attr()`, `esc_url()`, `sanitize_title()` for categories, `wp_json_encode()` for data attributes. Delete confirmation modal. Loading overlay with spinner. |
| `excerpt_lines` | `L1-L509` |
| `token_estimate` | `4000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Server-side rendering with proper WordPress escaping. Type card system enables extensible action types. Pro/addon gating prevents unauthorized feature access. |

---

### Slice 5 - Baachal Tools Domain (N/A)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-002-TOL-05` |
| `anchor_id` | `ANC-TOL-BAA-NA` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php` |
| `symbol_or_scope` | N/A -- no dedicated tools or macro subsystem |
| `excerpt_summary` | N/A (no matching implementation). Baachal provides core chat functionality with provider API calls but has no standalone tool registry, callable tool schema, macro grouping, action/intent framework, or tool execution callbacks. Provider interactions are direct API calls without tool-calling protocol support. The chat flow processes user messages and returns AI responses without any intermediate tool execution layer. |
| `token_estimate` | `0` |
| `notes` | Explicit N/A per domain taxonomy policy. Baachal focuses on core provider chat flow without tools abstraction. |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `5` |
| `total_token_estimate` | `19000` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-TOOLS` | `CP-P01-S04-002-TOL-01`, `TOL-02` | `CP-P01-S04-002-TOL-03`, `TOL-04` | `N/A (CP-P01-S04-002-TOL-05)` |

### Gaps and Risks

- **Architecture asymmetry**: SRC-AXI has a formal tool registration API with JSON Schema, provider-specific injection, and macro grouping; SRC-MXC has an intent/action framework with meaning-based matching, not a formal tool-calling API. These are fundamentally different approaches to extensible bot behavior.
- **Tool-calling protocol**: SRC-AXI supports provider-native tool-calling (OpenAI function calling, Claude tool use, Gemini function declarations); SRC-MXC actions are intent-matched against user messages, not invoked by the AI model directly.
- **Schema validation**: SRC-AXI enforces JSON Schema on tool definitions with provider-specific normalization; SRC-MXC validates form fields (label, phrases, threshold) but has no formal action schema.
- **Baachal gap**: No tools implementation at all -- cannot serve as baseline or fallback for this domain.
