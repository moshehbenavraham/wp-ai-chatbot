# Security Feature Entries

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Domain**: `DOM-SECURITY`
**Context Pack**: `CP-P01-S04-001`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-SEC-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-SEC-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-SEC-AXI-001` |
| `source_path` | `axiachat-ai/includes/sanitize-helpers.php`, `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Input validation and sanitization pipeline` |
| `capability_summary` | Multi-layer input validation with custom domain sanitizers. Core WordPress sanitizers (`sanitize_textarea_field`, `sanitize_title`, `sanitize_key`, `absint`) applied per field type in AJAX handler. Custom helpers: `aichat_sanitize_session_id()` enforces UUID pattern with 72-char max, `aichat_bool()` for strict boolean parsing, `aichat_bounded_int()` with min/max constraints, `aichat_validate_patch_payload()` with 20KB JSON size limit and safe `wp_unslash()` handling. Tool call structure validation filters unexpected fields and type-checks parameters before provider consumption. Output escaping via `wp_kses_post()` for instructions and custom `sanitize_answer_html()` for AI responses. Returns `WP_Error` on validation failures. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-RUNTIME"]` |
| `evidence_refs` | `["docs/axiachat-ai-feature-map.md"]` |

---

### FEAT-AXI-SEC-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-SEC-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-SEC-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Authentication and capability enforcement` |
| `capability_summary` | Nonce verification via `wp_verify_nonce($nonce, 'aichat_ajax')` at AJAX entry point. Encrypted API key management via `aichat_get_setting()` -- keys stored encrypted at rest, decrypted only at call time. Per-bot capability settings control tool access and domain restrictions. Session-based authentication via validated session IDs. No explicit `current_user_can()` check on public chat endpoint (by design -- public chat is guest-accessible), but admin endpoints enforce capability checks. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-AXI-SEC-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-SEC-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-SEC-AXI-001` |
| `source_path` | `axiachat-ai/includes/moderation.php`, `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Content moderation and abuse prevention` |
| `capability_summary` | Multi-layer moderation pipeline integrated in request flow. Layer 1: honeypot anti-bot field detection. Layer 2: rate limiting via `aichat_rate_limit_check()` per session and bot. Layer 3: spam signature checking. Layer 4: banned words checking via regex patterns with word boundary matching. Layer 5: external moderation via OpenAI Moderation API with encrypted key retrieval and graceful fallback (permits on network error). IP detection with proxy awareness (Cloudflare `HTTP_CF_CONNECTING_IP`, X-Forwarded-For headers) and `FILTER_VALIDATE_IP`. CIDR matching for IPv4 and IPv6 ban lists. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-RUNTIME"]` |

---

### FEAT-AXI-SEC-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-SEC-004` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-SEC-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Privacy controls and usage limits` |
| `capability_summary` | Daily conversation usage limits with configurable behavior (hard block or soft warning). Session-based rate limiting namespaced per bot. IP detection is privacy-aware: client IP only exposed when usage limits are enabled (webhook executor returns 'hidden' if tracking disabled). API keys encrypted at rest. Debug headers optional and not exposed by default. No sensitive data logged in production error messages. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |

---

### FEAT-AXI-SEC-005

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-SEC-005` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-SEC-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Audit logging and diagnostics` |
| `capability_summary` | Conditional debug logging when `AICHAT_DEBUG` is enabled. Context retrieval logged with top-3 preview. Provider call parameters logged with response metadata. Tool execution timing tracked. Moderation decisions logged. Rate limit checks logged. Error captures include context but not secrets. Debug headers available for developer troubleshooting but suppressed in production. Webhook execution logged to optional `wp_aichat_webhook_logs` table with payload excerpts (1000 chars), response excerpts (500 chars), and duration. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-SEC-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-SEC-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-SEC-MXC-001` |
| `source_path` | `mxchat-basic/admin/class-ajax-handler.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Input validation and sanitization pipeline` |
| `capability_summary` | Field-specific sanitization on all admin AJAX endpoints. WordPress core sanitizers applied per type: `sanitize_text_field()` for general text, `sanitize_email()` for emails, `sanitize_key()` for provider selection, `sanitize_textarea_field()` for multi-line, `wp_kses_post()` for HTML content. Input validation: whitelist validation for model selection, threshold bounds checking (min 20, max 85), RAG limit bounds (min 3, max 10), API key format regex (OpenAI `sk-*`, Claude `sk-ant-*`). Error responses via `esc_html__()`. Output escaping in admin UI via `esc_html()`, `esc_attr()`, `esc_url()`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-MXC-SEC-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-SEC-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-SEC-MXC-001` |
| `source_path` | `mxchat-basic/admin/class-ajax-handler.php`, `mxchat-basic/mxchat-basic.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Authentication and capability enforcement` |
| `capability_summary` | Consistent dual-check pattern on all admin AJAX endpoints: `check_ajax_referer('mxchat_save_setting_nonce')` for nonce verification + `current_user_can('manage_options')` for capability enforcement. Admin-only access enforced before any data processing. Separate nonces per operation type in Actions UI (6 distinct nonces: save, add, edit, toggle, delete, bulk delete). Bootstrap enforces `current_user_can('administrator')` for admin features. API keys stored as plaintext WP options (no encryption at rest). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-MXC-SEC-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-SEC-003` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-SEC-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-addons.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Content moderation and abuse prevention` |
| `capability_summary` | Moderation is a paid add-on (`mxchat-moderation`), not built into the core plugin. The addon provides IP/email blocking and content filtering. No built-in rate limiting, honeypot, spam detection, or external moderation API integration in the free/core plugin. Nonce verification on all AJAX actions provides CSRF protection. Asset exclusion filters prevent caching plugins from stripping security-critical scripts. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `paid_addon_or_license` |
| `risk_level` | `medium` |

---

### FEAT-MXC-SEC-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-SEC-004` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-SEC-MXC-001` |
| `source_path` | `mxchat-basic/admin/class-ajax-handler.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Privacy controls and usage limits` |
| `capability_summary` | No dedicated privacy controls or usage limits in core plugin. API key format validation (regex checks) provides partial credential protection. No encrypted API key storage. No conversation usage limits. No IP tracking controls. Role-based content access available in knowledge base (SRC-MXC RAG feature, cross-domain). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `medium` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-SEC-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-SEC-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-SEC-BAA-001` |
| `source_path` | `baachal/admin/settings-page.php`, `baachal/baachal.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Input validation and sanitization pipeline` |
| `capability_summary` | Comprehensive settings-page sanitization with per-field type mapping. Text: `sanitize_text_field(wp_unslash(...))`. Multi-line: `sanitize_textarea_field()`. Colors: `sanitize_hex_color()`. Numbers: `intval()` with bounds checking. Arrays: `array_map('sanitize_text_field', ...)`. API key format validation with provider-specific regex (Gemini, OpenAI `sk-*`, Claude `sk-ant-*`, Grok). Conservative bounds: title max 100 chars, welcome max 500 chars, max terms 1-50, min term length 1-10, cache 60s-24h, memory 1-50, results 1-20. Provider whitelist. Model name regex. Error notices on validation failures. Database safety via `$wpdb->prepare()` and `esc_sql()`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-BAA-SEC-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-SEC-002` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-SEC-BAA-001` |
| `source_path` | `baachal/baachal.php`, `baachal/admin/settings-page.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Authentication and capability enforcement` |
| `capability_summary` | Nonce verification via `check_admin_referer('baachal_settings')` on settings save. Capability check `current_user_can('activate_plugins')` for uninstall hook. Option namespacing with `baachal_` prefix prevents collisions. Hook-based extensibility with filtered settings: `baachal_before_save_settings` and `baachal_save_additional_settings` hooks receive only non-core settings to prevent accidental key leakage to third-party extensions. API keys stored as plaintext WP options. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-BAA-SEC-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-SEC-003` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-SEC-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Content moderation and abuse prevention` |
| `capability_summary` | [Minimal] No dedicated moderation module. No banned words, no external moderation API, no honeypot, no spam detection. Input length constraints via bounds checking provide basic DoS prevention. No rate limiting. No IP-based controls. Security relies on WordPress core nonce/capability infrastructure and the settings-page sanitization layer. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `high` |

---

### FEAT-BAA-SEC-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-SEC-004` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-SEC-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Privacy controls and usage limits` |
| `capability_summary` | [Minimal] Memory limit configurable (1-50 messages per conversation). No dedicated privacy controls, no IP tracking controls, no usage limits, no encrypted API key storage. Conversation data stored in custom post type (`baachal_conversation`) with transient caching. No audit logging. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `medium` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 5 | 5 | 0 |
| `SRC-MXC` | 4 | 4 | 0 |
| `SRC-BAA` | 4 | 4 | 0 |
| **Total** | **13** | **13** | **0** |
