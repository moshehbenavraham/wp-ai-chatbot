# Integrations Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S04-003` |
| `session_id` | `phase01-session04-security-tools-and-integrations-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `32000` |
| `domain_scope` | `["DOM-INTEGRATIONS"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-INTEGRATIONS` - `integrations`

**Slice Count**: 4 slices (including 1 N/A entry)
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA` (N/A)

---

### Slice 1 - AxiaChat MCP Client Manager

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-003-INT-01` |
| `anchor_id` | `ANC-INT-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` |
| `symbol_or_scope` | `AIChatMCPClientManager` singleton, `connect_server`, `discover_tools`, `execute_tool`, `perform_initialization` |
| `excerpt_summary` | Singleton MCP client manager (626 lines). Multi-server session management with lazy connection (avoids 2-5s startup penalty). JSON-RPC communication over HTTP transport. MCP initialization handshake with client capabilities (`elicitation`, `sampling`). On-demand tool discovery via `tools/list`. Tool execution via `tools/call` with automatic server lookup: memory cache first, then DB table `wp_aichat_tools` with sanitized name pattern. Response conversion from MCP content format (text, resource types) to internal format. In-memory tool cache with 300s TTL. Session cleanup on WordPress shutdown hook. Configuration from `aichat_mcp_servers` option (handles JSON string and array formats). Execution timing logged in milliseconds. Tool name format: `mcp_servername_suffix_toolname` (underscored). Per-server isolation: bad servers do not break other connections. |
| `excerpt_lines` | `L1-L626` |
| `token_estimate` | `7500` |
| `secondary_domains` | `["DOM-TOOLS"]` |
| `notes` | Lazy connection is a performance optimization learned from production deployment. Tool discovery is read-only; DB sync only happens via admin endpoints. Singleton ensures single connection pool per request. |

---

### Slice 2 - AxiaChat Webhook Executor

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-003-INT-02` |
| `anchor_id` | `ANC-INT-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` |
| `symbol_or_scope` | `AIChatWebhookExecutor::execute`, placeholder replacement, SSRF protection |
| `excerpt_summary` | Webhook execution engine (247 lines). Static `execute()` method for HTTP request dispatch. URL validation with `FILTER_VALIDATE_URL`. SSRF protection: blocks localhost, 127.0.0.1, 0.0.0.0, ::1, and private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16). Parameter merging: static params + AI-provided params + conversation history (optional, limited by `history_limit`). Metadata object (`_meta`) with source, bot_slug, session_id, timestamp, webhook_slug. Placeholder substitution: `{{NOW}}`, `{{DATE}}`, `{{TIME}}`, `{{TIMESTAMP}}`, `{{SESSION_ID}}`, `{{BOT_SLUG}}`, `{{PAGE_URL}}`, `{{USER_IP}}` (privacy-aware: 'hidden' if tracking disabled). Header sanitization via `sanitize_text_field()`. HTTP methods: GET (query params), POST/PUT/PATCH (JSON body). 15-second timeout with SSL verification. Response parsing: JSON preferred, raw text fallback. Execution logging to `wp_aichat_webhook_logs` table (optional, graceful if table missing). Duration tracking in milliseconds. |
| `excerpt_lines` | `L1-L247` |
| `token_estimate` | `4000` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | SSRF protection is the strongest security control in the integrations domain. Privacy-aware IP handling respects user tracking preferences. Logging table is optional feature (graceful degradation). |

---

### Slice 3 - MxChat Addons Framework

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-003-INT-03` |
| `anchor_id` | `ANC-INT-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-addons.php` |
| `symbol_or_scope` | `MxChat_Addons` class, addon configuration registry, status detection, marketplace rendering |
| `excerpt_summary` | Centralized addon/extension management system (407 lines). Registry of 15+ addons with configuration: title, description, key benefits, license requirement, accent color, documentation URL, plugin file path, config page, hero features, deprecation status. Status detection via `get_addon_status()`: active, inactive, or not-installed with appropriate action URLs (configure, activate, install). Marketplace UI with hero section, addon card grid, and CTA section. Addon cards display: status badge (Pro/Deprecated), description, benefits list, action button. Deprecated addons redirect to built-in features. Nonce protection on activation links. Internationalization throughout. CSS variable-based theming per addon card. |
| `excerpt_lines` | `L1-L407` |
| `token_estimate` | `4500` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | This is an addon marketplace/management UI, not a runtime integration protocol. Addons include moderation, forms, multi-bot, WooCommerce, Perplexity search, image analysis, video generation, etc. The framework manages addon discovery and activation but does not define a formal extension API or protocol. |

---

### Slice 4 - Baachal Integrations Domain (N/A)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S04-003-INT-04` |
| `anchor_id` | `ANC-INT-BAA-NA` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php` |
| `symbol_or_scope` | N/A -- no dedicated integration subsystem beyond provider connections |
| `excerpt_summary` | N/A (no matching implementation). Baachal connects to AI providers (Gemini, OpenAI, Claude, Grok) and has WooCommerce product search integration, but has no MCP client, webhook execution, external channel adapters, addon framework, or formal extension system. Provider connections are handled inline in the main plugin file as direct API calls. No external system bridges beyond core provider APIs. |
| `token_estimate` | `0` |
| `notes` | Explicit N/A per domain taxonomy policy. Baachal's provider connections fall under DOM-PROVIDER, not DOM-INTEGRATIONS. WooCommerce integration falls under DOM-WOOCOMMERCE. |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `4` |
| `total_token_estimate` | `16000` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-INTEGRATIONS` | `CP-P01-S04-003-INT-01`, `INT-02` | `CP-P01-S04-003-INT-03` | `N/A (CP-P01-S04-003-INT-04)` |

### Gaps and Risks

- **Architecture asymmetry**: SRC-AXI has protocol-level integration (MCP JSON-RPC, HTTP webhooks with execution engine); SRC-MXC has an addon management framework (marketplace UI, status detection) but no formal integration protocol or execution engine.
- **Runtime vs management**: SRC-AXI integrations execute at runtime (MCP tool calls, webhook HTTP requests during chat); SRC-MXC addons are installed plugins activated at admin time, not runtime integration points.
- **External protocol support**: SRC-AXI supports MCP (Model Context Protocol) for standardized AI tool interop; SRC-MXC has no external protocol support (addons are WordPress plugins, not protocol clients).
- **Baachal gap**: No integrations beyond provider API calls -- cannot serve as baseline for this domain.
- **Webhook security**: SRC-AXI's SSRF protection (private host blocking) is a strong security control absent from other sources.
