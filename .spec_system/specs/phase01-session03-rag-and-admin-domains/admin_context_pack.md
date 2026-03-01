# Admin Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S03-002` |
| `session_id` | `phase01-session03-rag-and-admin-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-ADMIN"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-ADMIN` - `admin`

**Slice Count**: 7 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

---

### Slice 1 - AxiaChat Settings Registration and Encryption

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-01` |
| `anchor_id` | `ANC-ADM-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/settings.php` |
| `symbol_or_scope` | `aichat_register_simple_settings`, `aichat_get_setting`, `aichat_encrypt_value`, `aichat_decrypt_value`, `aichat_settings_page` |
| `excerpt_summary` | Settings infrastructure using full WP Settings API. Registers ~35 options under single `aichat_settings` group with explicit type, sanitize callback, and default per option. Custom `aichat_get_setting()` honors registered defaults and auto-decrypts encrypted values. API key encryption at rest via AES-256-GCM with per-value random IVs and authentication tags; key derived from `AICHAT_MASTER_KEY` constant or WP salts. Tabbed settings page (General, Usage & Moderation, GDPR Consent, Add-ons, Advanced) with tab partials. Sanitizers: API keys get `wp_kses()` + encryption; text fields use `sanitize_text_field()`; checkboxes normalize to 0/1; embed origins URL-parsed and normalized; behaviors whitelist-validated. 576 lines. |
| `excerpt_lines` | `L1-L576` |
| `token_estimate` | `5500` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | OpenSSL graceful degradation: stores plaintext if extension unavailable. Pre-update filters for global bot validation. Debug toggle and log viewer via AJAX. |

---

### Slice 2 - AxiaChat Admin JS

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-02` |
| `anchor_id` | `ANC-ADM-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/assets/js/settings.js` |
| `symbol_or_scope` | Settings UI interactions |
| `excerpt_summary` | Vanilla JS (no jQuery) IIFE with modular init functions: tab switching, API key visibility toggle (password/text), security policy reset to defaults, log viewer refresh/clear via AJAX with spinner feedback, Andromeda Connect plugin installation flow with confirmation dialog. Uses `wp.i18n.__` for JS-side i18n and `fetch()` for AJAX calls. 255 lines. |
| `excerpt_lines` | `L1-L255` |
| `token_estimate` | `1500` |
| `notes` | Lightweight and modern -- no jQuery dependency. Clean separation of concerns via modular init functions. |

---

### Slice 3 - MxChat Admin Class (Settings and Configuration)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-03` |
| `anchor_id` | `ANC-ADM-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `symbol_or_scope` | `MxChat_Admin` class: settings, options, diagnostics, operational controls |
| `excerpt_summary` | Monolithic admin controller class (9036 lines). 6 admin pages via `add_menu_page` + `add_submenu_page` (Settings, Knowledge, Transcripts, Actions, Content, Pro & Extensions). Single `mxchat_options` array with 80+ fields plus separate groups for Pinecone, OpenAI Vector Store, and Transcripts. Full WP Settings API usage. Comprehensive `mxchat_sanitize()` handler for field-by-field validation. Diagnostics: streaming test (actual SSE test against provider APIs), Vector Store connection test, Slack connection test with scope verification. Operational controls: license activation (remote API), multi-bot support, per-role rate limits, post type visibility, email gating, chat persistence, WooCommerce integration, privacy/Complianz, auto-delete transcripts (cron), auto-email transcripts, transcript translation (AI-powered), content generation. 70+ configurable options. |
| `excerpt_lines` | `L1-L4500` (settings/options), `L4500-L9036` (diagnostics/AJAX handlers) |
| `token_estimate` | `8000` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | API keys stored as plaintext (no encryption). Constructor registers 40+ action hooks. License verification via remote mxchat.ai API. AJAX auto-save with per-field debouncing. |

---

### Slice 4 - MxChat Admin JS

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-04` |
| `anchor_id` | `ANC-ADM-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/js/mxchat-admin.js` |
| `symbol_or_scope` | Admin UI interactions and auto-save |
| `excerpt_summary` | jQuery-based admin JS (3824 lines). Key features: AJAX auto-save with per-field debouncing and visual feedback (spinners, checkmarks), AI model selector modal with category filtering and search, OpenRouter model browser with live API fetching, embedding model selector, API key visibility toggles, post type visibility controls, color picker integration, action/intent modal management, notice dismissal, Pinecone/Vector Store UI state management. Uses `wp_localize_script` for nonce/URL passing. Page-specific JS loading. |
| `excerpt_lines` | `L1-L3824` |
| `token_estimate` | `5500` |
| `notes` | jQuery dependency. Complex UI with modals, auto-save, real-time validation. Much larger than other admin JS files. |

---

### Slice 5 - Baachal Settings Page and Form Handler

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-05` |
| `anchor_id` | `ANC-ADM-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/admin/settings-page.php` |
| `symbol_or_scope` | Form submission handler, option retrieval, tab rendering |
| `excerpt_summary` | Single settings page with 6 tabs (General, WooCommerce, Product Search, Content Indexing, UI Styling, Advanced). Does NOT use WP Settings API -- manual `update_option()` per field in form handler. ~30 settings. Inline validation: API key format regex (Gemini, OpenAI, Claude, Grok patterns), provider whitelist, numeric range clamping, string length limits. Settings saved via form POST to self. ~25 individual `wp_options` rows (prefix `baachal_`). Tab partials loaded from `admin/tabs/` directory. Setting filters: `apply_filters('baachal_setting_*', ...)` per setting. Strong extensibility hooks: `baachal_before_save_settings`, `baachal_save_additional_settings`, `baachal_settings_tabs`, `baachal_settings_tab_content`, plus 6 more action hooks around form structure. 341 lines. |
| `excerpt_lines` | `L1-L341` |
| `token_estimate` | `3500` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | No WP Settings API. No API key encryption. Missing `current_user_can()` in settings page file (relies on menu registration). Most extensible via hooks despite simplest architecture. |

---

### Slice 6 - Baachal Advanced Tab

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-06` |
| `anchor_id` | `ANC-ADM-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/admin/tabs/advanced.php` |
| `symbol_or_scope` | Advanced settings tab |
| `excerpt_summary` | Minimal tab with 4 fields: Debug Mode, Preserve Data on Uninstall, Conversational Memory toggle, Memory Limit (messages). Static HTML table with `checked()` helpers and `esc_attr()`. 47 lines. |
| `excerpt_lines` | `L1-L47` |
| `token_estimate` | `500` |
| `notes` | Smallest admin file. Functional but minimal. |

---

### Slice 7 - Baachal Admin JS

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-002-ADM-07` |
| `anchor_id` | `ANC-ADM-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/assets/admin.js` |
| `symbol_or_scope` | Admin UI interactions |
| `excerpt_summary` | jQuery-based admin JS (292 lines). Features: content reindexing via AJAX, test search via AJAX, cache clearing via AJAX, tab navigation with localStorage persistence and URL hash support, color picker with preset colors and hex input, AI provider switching (shows/hides relevant API key rows and model groups, sets default model on provider change). Nonce-protected AJAX calls. |
| `excerpt_lines` | `L1-L292` |
| `token_estimate` | `2000` |
| `notes` | jQuery dependency. Clean and focused. Provider-switching UI auto-updates visible fields. |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `7` |
| `total_token_estimate` | `26500` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-ADMIN` | `CP-P01-S03-002-ADM-01`, `ADM-02` | `CP-P01-S03-002-ADM-03`, `ADM-04` | `CP-P01-S03-002-ADM-05` through `ADM-07` |

### Gaps and Risks

- **Architecture asymmetry**: SRC-AXI uses WP Settings API with encryption; SRC-MXC uses WP Settings API with monolithic class; SRC-BAA bypasses Settings API entirely with manual `update_option()` calls. Scoring must assess operational outcomes, not just API usage.
- **API key security gap**: Only SRC-AXI encrypts API keys at rest (AES-256-GCM). SRC-MXC and SRC-BAA store keys as plaintext in `wp_options`. This is a significant security differentiator.
- **Diagnostics breadth**: SRC-MXC has the richest diagnostics (streaming test, Vector Store test, Slack test, analytics charts). SRC-AXI has log viewer + debug toggle. SRC-BAA has debug toggle only.
- **Admin JS quality**: SRC-AXI uses modern vanilla JS (255 lines). SRC-MXC uses jQuery with complex auto-save and modals (3824 lines). SRC-BAA uses jQuery with focused functionality (292 lines). Code size does not correlate with quality.
- **Extensibility hooks**: SRC-BAA has the most explicit extension hooks (10+ actions/filters for settings). SRC-AXI has 2 pre-update filters. SRC-MXC has minimal custom hooks despite being the largest codebase.
- **Capability checks**: SRC-AXI checks in page render. SRC-MXC checks in every AJAX handler. SRC-BAA is missing checks in settings page file (relies on menu registration). Security scoring should flag this.
