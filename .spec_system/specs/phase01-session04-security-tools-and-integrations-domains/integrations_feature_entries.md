# Integrations Feature Entries

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Domain**: `DOM-INTEGRATIONS`
**Context Pack**: `CP-P01-S04-003`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-INT-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-INT-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-INT-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `MCP client management with multi-server sessions` |
| `capability_summary` | Singleton MCP client manager (626 lines) implementing Model Context Protocol over HTTP transport. Multi-server session management with lazy connection (avoids 2-5s startup penalty). JSON-RPC communication with MCP initialization handshake (client capabilities: `elicitation`, `sampling`). On-demand tool discovery via `tools/list`. Tool execution via `tools/call` with automatic server lookup (memory cache first, then DB table `wp_aichat_tools`). In-memory tool cache with 300s TTL. Session cleanup on WordPress shutdown. Tool name format: `mcp_servername_suffix_toolname`. Per-server isolation (bad servers do not affect others). Response conversion from MCP content format (text, resource types) to internal format. Configuration from `aichat_mcp_servers` option. Execution timing logged. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-TOOLS"]` |
| `evidence_refs` | `["docs/axiachat-ai-feature-map.md"]` |

---

### FEAT-AXI-INT-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-INT-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-INT-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `Webhook execution with SSRF protection` |
| `capability_summary` | Static webhook executor (247 lines) for HTTP request dispatch. URL validation with `FILTER_VALIDATE_URL`. SSRF protection: blocks localhost, 127.0.0.1, 0.0.0.0, ::1, and private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16). Parameter merging (static + AI-provided + optional conversation history). Placeholder substitution (NOW, DATE, SESSION_ID, BOT_SLUG, PAGE_URL, USER_IP with privacy awareness). Metadata object with source tracking. Header sanitization. HTTP methods: GET (query params), POST/PUT/PATCH (JSON body). 15-second timeout with SSL verification. Response parsing (JSON preferred, raw text fallback). Execution logging to optional `wp_aichat_webhook_logs` DB table. Duration tracking. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-INT-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-INT-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-INT-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `Integration source tracking and cleanup` |
| `capability_summary` | Macro system source tracking bridges tools from external integration sources (MCP servers, webhooks) into the tool registry. Per-macro `source` field (local, mcp, webhook) and `source_ref` (server ID, webhook ID) enable cascade delete when integration sources are removed. `aichat_delete_macros_by_source($source, $source_ref)` removes all associated macros. MCP-discovered tools are registered as macros with `source=mcp`. Webhook-associated tools use `source=webhook`. This creates an integration lifecycle: connect source, discover/register tools, manage, disconnect and cascade cleanup. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-TOOLS"]` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-INT-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-INT-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-INT-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-addons.php` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `Addon management framework with marketplace` |
| `capability_summary` | Centralized addon/extension management system (407 lines). Registry of 15+ addons with configuration: title, description, key benefits, license requirement, accent color, documentation URL, plugin file path, config page, hero features, deprecation status. Status detection: active, inactive, or not-installed with appropriate action URLs (configure/activate/install). Marketplace UI with hero section, addon card grid, and CTA section. Addon cards display status badge, description, benefits, and action button. Deprecated addons redirect to built-in features. Nonce protection on activation links. CSS variable-based theming per card. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-MXC-INT-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-INT-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-INT-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-addons.php` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `Channel extensibility via addon architecture` |
| `capability_summary` | Channel and capability extension via WordPress plugin addons. Available addons provide integration channels: Perplexity (real-time web search), OpenAI Assistants (24h persistence), WooCommerce (product recommendations), image analysis (vision models), video generation (Veo 2/3), forms (lead capture), admin assistant, and more. Extension model: each addon is a separate WordPress plugin installed and activated independently. Status detection via `get_addon_status()` checks WordPress plugin list. Configuration pages per addon. License-based gating via MxChat PRO. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `paid_addon_or_license` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-INT-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-INT-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-INT-BAA-NA` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `External system integration parity` |
| `capability_summary` | [N/A] Baachal has no integration subsystem beyond core provider API connections and WooCommerce product search. No MCP client, no webhook execution, no external channel adapters, no addon framework, no extension system. Provider connections are inline API calls in the main plugin file. WooCommerce integration falls under DOM-WOOCOMMERCE, not DOM-INTEGRATIONS. |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `na_reason` | `No integration subsystem. Baachal connects to AI providers via direct API calls (DOM-PROVIDER) and has WooCommerce product search (DOM-WOOCOMMERCE) but no external protocol clients, webhook engines, channel adapters, or extension frameworks.` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 3 | 3 | 0 |
| `SRC-MXC` | 2 | 2 | 0 |
| `SRC-BAA` | 1 | 0 | 1 |
| **Total** | **6** | **5** | **1** |
