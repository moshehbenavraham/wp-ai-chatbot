# Security Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S04-001` |
| `session_id` | `phase01-session04-security-tools-and-integrations-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-SECURITY"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-SECURITY` - `security`

**Slice Count**: 7 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

---

### Slice 1 - AxiaChat Moderation and Abuse Controls

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-01` |
| `anchor_id` | `ANC-SEC-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/moderation.php` |
| `symbol_or_scope` | `aichat_detect_client_ip`, `aichat_ip_in_cidr`, `aichat_check_banned_words`, `aichat_openai_moderation_check` |
| `excerpt_summary` | Centralized moderation and anti-abuse module (202 lines). IP detection with proxy awareness (Cloudflare `HTTP_CF_CONNECTING_IP`, `X-Forwarded-For`) and `FILTER_VALIDATE_IP`. CIDR matching for IPv4 and IPv6. Banned words checking via regex patterns with word boundary matching. External moderation via OpenAI Moderation API with encrypted API key retrieval. Graceful fallback on network error (permits message rather than blocking). Input sanitization via `sanitize_text_field(wp_unslash(...))` on all IP sources. |
| `excerpt_lines` | `L1-L202` |
| `token_estimate` | `3000` |
| `secondary_domains` | `["DOM-RUNTIME"]` |
| `notes` | IP header spoofing partially mitigated by first-valid-IP selection. Moderation is pre-processing gate in runtime pipeline. |

---

### Slice 2 - AxiaChat Custom Sanitization Helpers

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-02` |
| `anchor_id` | `ANC-SEC-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/sanitize-helpers.php` |
| `symbol_or_scope` | `aichat_sanitize_session_id`, `aichat_bool`, `aichat_bounded_int`, `aichat_validate_patch_payload` |
| `excerpt_summary` | Domain-specific sanitization library (89 lines). Session ID validator enforces UUID pattern with 72-char max length. Strict boolean parsing. Bounded integer with min/max constraints prevents overflow. JSON patch payload validation with configurable size limit (20KB default), safe `wp_unslash()` handling, and `WP_Error` returns. Purpose-built helpers encapsulate repetitive sanitization patterns used across AJAX and REST endpoints. |
| `excerpt_lines` | `L1-L89` |
| `token_estimate` | `1500` |
| `notes` | Returns `WP_Error` on validation failures. Complements WordPress core sanitizers with domain-specific constraints. |

---

### Slice 3 - AxiaChat AJAX Request Security Pipeline

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-03` |
| `anchor_id` | `ANC-SEC-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `symbol_or_scope` | `AIChat_AJAX::handle_chat_message` security layers, tool call validation, usage limits |
| `excerpt_summary` | Multi-layer security pipeline in primary AJAX handler (3766 lines total, security-relevant sections). Layer 1: nonce verification via `wp_verify_nonce($nonce, 'aichat_ajax')`. Layer 2: comprehensive input sanitization (`sanitize_textarea_field` for messages, `sanitize_title` for bot slugs, `sanitize_key` for providers, `absint` for IDs, custom `aichat_sanitize_session_id` for session tokens). Layer 3: honeypot anti-bot field detection. Layer 4: rate limiting via `aichat_rate_limit_check()` per session and bot. Layer 5: spam signature checking. Layer 6: content moderation via `aichat_check_banned_words` and OpenAI moderation. Layer 7: output escaping via `wp_kses_post()` for instructions, `sanitize_answer_html()` for AI responses. Layer 8: tool call structure validation (filters unexpected fields, type-checks parameters). Layer 9: daily conversation usage limits with configurable behavior. Layer 10: API key management via encrypted `aichat_get_setting()`. |
| `excerpt_lines` | `L45-L200` (validation), `L537-L583` (usage limits), `L860-L870` (output sanitization) |
| `token_estimate` | `6000` |
| `secondary_domains` | `["DOM-RUNTIME", "DOM-TOOLS"]` |
| `notes` | 10 distinct security layers in a single request path. Debug headers optional, not exposed by default. Tool call validation enforces structure before provider consumption. |

---

### Slice 4 - MxChat AJAX Handler Security Controls

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-04` |
| `anchor_id` | `ANC-SEC-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/admin/class-ajax-handler.php` |
| `symbol_or_scope` | `MxChat_Ajax_Handler` nonce and capability enforcement, field-specific validation |
| `excerpt_summary` | Admin AJAX handler with consistent security pattern (1338 lines). Every endpoint starts with `check_ajax_referer('mxchat_save_setting_nonce')` + `current_user_can('manage_options')` capability check. Field-specific sanitization: `sanitize_text_field()` for general text, `sanitize_email()` for emails, `sanitize_key()` for provider selection, `sanitize_textarea_field()` for multi-line, `wp_kses_post()` for HTML content. Input validation: whitelist validation for model selection, threshold bounds checking (min 20, max 85), RAG limit bounds (min 3, max 10), API key format regex (OpenAI `sk-*`, Claude `sk-ant-*`). Error responses via `esc_html__()`. |
| `excerpt_lines` | `L60-L200` (security patterns), `L100-L130` (whitelist validation) |
| `token_estimate` | `5000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | All admin AJAX endpoints enforce capability check before processing. `stripslashes()` on POST values could re-introduce escaping issues. |

---

### Slice 5 - MxChat Main Plugin Security Patterns

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-05` |
| `anchor_id` | `ANC-SEC-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/mxchat-basic.php` |
| `symbol_or_scope` | Admin capability checks, output escaping in bootstrap |
| `excerpt_summary` | Main plugin file (1378 lines) with security patterns in bootstrap and init. Admin capability check via `current_user_can('administrator')`. Output escaping with `esc_html_e()` and `esc_html()` in admin UI. Asset exclusion filters prevent caching plugins from breaking chatbot scripts (security-adjacent: prevents script stripping that could disable nonce validation). Hook-based architecture supports extensibility without direct code modification. |
| `excerpt_lines` | `L760-L770` (escaping), `L1080-L1090` (capability) |
| `token_estimate` | `2000` |
| `notes` | Bootstrap file focuses on setup/init. Security-critical logic delegated to dedicated handlers. |

---

### Slice 6 - Baachal Main Plugin Security Guards

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-06` |
| `anchor_id` | `ANC-SEC-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php` |
| `symbol_or_scope` | Nonce verification, capability checks, `$wpdb->prepare()`, input limits |
| `excerpt_summary` | Main plugin file (2569 lines) with security guards distributed across chat/admin handlers. Access control via `current_user_can('activate_plugins')` for uninstall. Database safety via `$wpdb->prepare()` for parameterized queries and `esc_sql()` for table names. Transient management for caching with proper invalidation. Option namespacing with `baachal_` prefix. Custom post type registration (`baachal_conversation`) with appropriate visibility. Hooks architecture with `apply_filters()` and `do_action()` for extensibility. |
| `excerpt_lines` | `L120-L125` (prepare), `L170-L190` (capability/uninstall) |
| `token_estimate` | `4000` |
| `secondary_domains` | `["DOM-RUNTIME"]` |
| `notes` | Security guards are inline throughout the main plugin file rather than separated into dedicated modules. Message handling security patterns embedded in chat flow. |

---

### Slice 7 - Baachal Settings Page Security Controls

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-001-SEC-07` |
| `anchor_id` | `ANC-SEC-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/admin/settings-page.php` |
| `symbol_or_scope` | `check_admin_referer`, input sanitization, bounds checking, API key validation |
| `excerpt_summary` | Admin settings save handler (341 lines) with comprehensive input security. Nonce verification via `check_admin_referer('baachal_settings')`. Input sanitization: `sanitize_text_field(wp_unslash(...))` for text, `sanitize_textarea_field()` for multi-line, `sanitize_hex_color()` for colors, `intval()` for numbers, `array_map('sanitize_text_field', ...)` for arrays. API key format validation with provider-specific regex: Gemini (`[A-Za-z0-9_-]+`), OpenAI (`sk-[A-Za-z0-9_-]+`), Claude (`sk-ant-[A-Za-z0-9_-]+`), Grok (`[A-Za-z0-9_-]+`). Provider whitelist (`gemini`, `openai`, `claude`, `grok`). Conservative bounds checking: title max 100 chars, welcome max 500 chars, max terms 1-50, min term length 1-10, cache duration 60s-24h, memory limit 1-50, content max results 1-20. Error notices on validation failures. Hook-based extensibility with filtered settings passed to hooks. |
| `excerpt_lines` | `L1-L200` (security patterns) |
| `token_estimate` | `4000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Strongest Baachal security artifact. Per-field bounds checking is more granular than other sources. Hook parameters filtered to exclude core settings (prevents accidental key leakage to third-party extensions). |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `7` |
| `total_token_estimate` | `25500` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-SECURITY` | `CP-P01-S04-001-SEC-01` through `SEC-03` | `CP-P01-S04-001-SEC-04`, `SEC-05` | `CP-P01-S04-001-SEC-06`, `SEC-07` |

### Gaps and Risks

- **Security architecture asymmetry**: SRC-AXI separates security into dedicated modules (moderation.php, sanitize-helpers.php) with multi-layer request pipeline; SRC-MXC embeds security inline in AJAX handlers; SRC-BAA distributes guards throughout main plugin file. Scoring must assess security posture holistically, not just file count.
- **Moderation depth variance**: SRC-AXI has external moderation (OpenAI API), banned words regex, and IP-based controls; SRC-MXC offers moderation as a paid add-on; SRC-BAA has no dedicated moderation.
- **API key security**: SRC-AXI encrypts API keys at rest via `aichat_get_setting()`; SRC-MXC and SRC-BAA store keys as plaintext WP options.
- **Rate limiting**: SRC-AXI has integrated rate limiting in request pipeline; SRC-MXC and SRC-BAA have no dedicated rate limiting.
- **Custom sanitization**: SRC-AXI has purpose-built domain sanitizers (session ID, bounded int, JSON payload); SRC-MXC and SRC-BAA use only WP core sanitizers.
