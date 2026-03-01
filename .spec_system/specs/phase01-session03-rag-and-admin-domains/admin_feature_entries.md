# Admin Feature Entries

**Session ID**: `phase01-session03-rag-and-admin-domains`
**Domain**: `DOM-ADMIN`
**Context Pack**: `CP-P01-S03-002`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-ADM-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-ADM-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-ADM-AXI-001` |
| `source_path` | `axiachat-ai/includes/settings.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `WP Settings API registration with tab-based architecture` |
| `capability_summary` | Full WP Settings API usage with single `aichat_settings` option group. ~35 options registered with explicit type, sanitize callback, and default per option. Tabbed settings page (General, Usage & Moderation, GDPR Consent, Add-ons, Advanced) rendered via PHP tab partials. Standard form submission to `options.php`. Individual `wp_options` rows per setting. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-AXI-ADM-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-ADM-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-ADM-AXI-001` |
| `source_path` | `axiachat-ai/includes/settings.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Encrypted API key storage with per-field sanitization` |
| `capability_summary` | API key encryption at rest via AES-256-GCM with per-value random IVs and authentication tags. Key derived from `AICHAT_MASTER_KEY` constant or WP salts. Custom `aichat_get_setting()` auto-decrypts on retrieval. Graceful degradation: stores plaintext if OpenSSL unavailable. Per-field sanitizers: API keys get `wp_kses()` + encryption, text fields use `sanitize_text_field()`, checkboxes normalize to 0/1, embed origins URL-parsed and normalized, behaviors whitelist-validated. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-ADM-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-ADM-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-ADM-AXI-001` |
| `source_path` | `axiachat-ai/includes/settings.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Debug logging and log management` |
| `capability_summary` | Debug toggle (`aichat_debug_enabled`). Log viewer via AJAX (`aichat_get_log_tail`) with tail display in admin. Log clearing via AJAX (`aichat_clear_log`). Supports both general and AI-specific log streams. JS log viewer with spinner feedback. Capability check (`manage_options`) on page render. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-AXI-ADM-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-ADM-004` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-ADM-AXI-001` |
| `source_path` | `axiachat-ai/includes/settings.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Operational controls for usage, moderation, and compliance` |
| `capability_summary` | Global bot enable/disable with auto-slug-assignment. Usage limits: daily total and per-user message caps with configurable behavior (disabled/hide). Content moderation: banned IPs, banned words, rejection message, external moderation toggle. GDPR consent overlay with configurable text and button labels. Add-on toggles for AI Tools, Lead Capture, Appointments, MCP, Live Chat, WhatsApp/Telegram connector. Security policy with customizable text and reset-to-defaults. Embed origins whitelist. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-ADM-005

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-ADM-005` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-ADM-AXI-001` |
| `source_path` | `axiachat-ai/assets/js/settings.js` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Modern vanilla JS admin interface` |
| `capability_summary` | No jQuery dependency -- uses vanilla JS with `document.querySelectorAll` and `fetch()`. IIFE with modular init functions: tab switching, API key visibility toggle, security policy reset, log viewer refresh/clear via AJAX, Andromeda Connect plugin installation flow. Uses `wp.i18n.__` for JS-side i18n. 255 lines. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-ADM-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-ADM-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-ADM-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Multi-page admin with WP Settings API and 80+ options` |
| `capability_summary` | 6 admin pages via `add_menu_page` + `add_submenu_page` (Settings, Knowledge, Transcripts, Actions, Content, Pro & Extensions). Single monolithic `mxchat_options` array with 80+ fields plus 3 separate option groups (Pinecone, Vector Store, Transcripts). Full WP Settings API with `register_setting`, `add_settings_section`, `add_settings_field`. Comprehensive `mxchat_sanitize()` for field-by-field validation. Template-based page rendering. Sidebar navigation on main settings page. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

### FEAT-MXC-ADM-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-ADM-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-ADM-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Plaintext option storage with field-by-field sanitization` |
| `capability_summary` | All options including API keys stored as plaintext in `mxchat_options` array in `wp_options`. Comprehensive `mxchat_sanitize()` handler with per-field validation: `sanitize_text_field`, `absint`, `sanitize_email`, `wp_kses_post`, `sanitize_textarea_field`, whitelist validation for enums. Range enforcement (similarity threshold 10-95%, RAG sources 3-10). Callback function whitelist validation against `mxchat_get_available_callbacks()`. Nonce and capability checks on all AJAX handlers. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-MXC-ADM-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-ADM-003` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-ADM-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Comprehensive diagnostics with provider connectivity testing` |
| `capability_summary` | Streaming test: actual SSE test against provider APIs with detailed error events. Vector Store connection test: validates OpenAI Vector Store accessibility. Slack connection test: validates bot token and checks scope permissions. Frontend debugger toggle for client-side debugging. Debug mode toggle. Transcript analytics with 7-day charts (Chart.js). Transient-based admin notices for success/error feedback. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `low` |

---

### FEAT-MXC-ADM-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-ADM-004` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-ADM-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Rich operational controls with licensing and multi-bot` |
| `capability_summary` | License activation/deactivation via remote mxchat.ai API with domain verification. Multi-bot support with per-bot knowledge and provider settings. Per-role rate limits. Post type visibility (include/exclude). Email gating. Chat persistence. WooCommerce integration toggles. Privacy/Complianz integration. Auto-delete transcripts (cron-based). Auto-email transcripts. Transcript translation (AI-powered). Content generation page. CSV export. 70+ configurable options. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

### FEAT-MXC-ADM-005

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-ADM-005` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-ADM-MXC-001` |
| `source_path` | `mxchat-basic/js/mxchat-admin.js` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Feature-rich jQuery admin interface with auto-save` |
| `capability_summary` | jQuery-based admin JS (3824 lines). AJAX auto-save with per-field debouncing and visual feedback (spinners, checkmarks). AI model selector modal with category filtering and search. OpenRouter model browser with live API fetching. Embedding model selector. API key visibility toggles. Post type visibility controls. Color picker integration. Action/intent modal management. Notice dismissal. Pinecone/Vector Store UI state management. Page-specific JS loading via `wp_localize_script`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-ADM-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-ADM-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-ADM-BAA-001` |
| `source_path` | `baachal/admin/settings-page.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Manual settings page with tab partials and extension hooks` |
| `capability_summary` | Single settings page with 6 tabs (General, WooCommerce, Product Search, Content Indexing, UI Styling, Advanced). Does NOT use WP Settings API -- manual `update_option()` per field in form handler. Tab partials loaded from `admin/tabs/` directory. ~30 settings as individual `wp_options` rows (prefix `baachal_`). Form POST to self. Strong extensibility: 10+ action/filter hooks including `baachal_settings_tabs`, `baachal_settings_tab_content`, `baachal_before_save_settings`, per-setting filters. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `medium` |

---

### FEAT-BAA-ADM-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-ADM-002` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-ADM-BAA-001` |
| `source_path` | `baachal/admin/settings-page.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Inline validation with regex API key format checking` |
| `capability_summary` | Manual per-field sanitization: `sanitize_text_field()`, `sanitize_textarea_field()`, `sanitize_hex_color()`, `intval()`, `wp_unslash()`. API key format validation via regex: Gemini, OpenAI (`sk-*`), Claude (`sk-ant-*`), Grok patterns. Provider whitelist. Numeric range clamping: max_terms (1-50), min_term_length (1-10), cache_duration (60-86400), memory_limit (1-50), max_results (1-20). String length limits. No API key encryption at rest. Missing `current_user_can()` in settings page file. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-BAA-ADM-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-ADM-003` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-ADM-BAA-001` |
| `source_path` | `baachal/admin/tabs/advanced.php` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Minimal diagnostics and operational controls` |
| `capability_summary` | Debug mode checkbox (console-level only). No built-in connection testing or health checks. Operational controls: chatbot enable/disable, conversation memory with configurable limit, preserve data on uninstall, content indexing toggle, auto-index toggle, provider switching. No usage limits, no content moderation, no GDPR/compliance features, no transcript management, no multi-bot support. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-BAA-ADM-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-ADM-004` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-ADM-BAA-001` |
| `source_path` | `baachal/assets/admin.js` |
| `domain_id` | `DOM-ADMIN` |
| `capability_name` | `Focused jQuery admin interface with provider switching` |
| `capability_summary` | jQuery-based admin JS (292 lines). Features: content reindexing via AJAX, test search via AJAX, cache clearing via AJAX, tab navigation with localStorage persistence and URL hash support, color picker with preset colors and hex input, AI provider switching (shows/hides relevant API key rows and model groups, sets default model on provider change). Nonce-protected AJAX calls. Clean and focused. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 5 | 5 | 0 |
| `SRC-MXC` | 5 | 5 | 0 |
| `SRC-BAA` | 4 | 4 | 0 |
| **Total** | **14** | **14** | **0** |
