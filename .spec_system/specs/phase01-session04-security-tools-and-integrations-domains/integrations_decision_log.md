# Integrations Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S04-INT` |
| `session_id` | `phase01-session04-security-tools-and-integrations-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-INTEGRATIONS"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S04-003` |

---

## Entry: `DEC-INT-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-INT-001` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `External protocol support` |
| `capability_summary` | Standardized communication with external systems via protocol clients (MCP JSON-RPC) and HTTP execution engines (webhooks) for bridging AI chatbot capabilities with third-party services. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-INT-AXI-001` | `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php`, `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` | `FEAT-AXI-INT-001`, `FEAT-AXI-INT-002` | `implemented` |
| `SRC-MXC` | `ANC-INT-MXC-001` | `mxchat-basic/includes/class-mxchat-addons.php` | `FEAT-MXC-INT-002` | `implemented` |
| `SRC-BAA` | `ANC-INT-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-INT-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 5 | 4 | 4 | 4.50 |
| `SRC-MXC` | 2 | 3 | 3 | 3 | 3 | 2.70 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat is the only source with protocol-level external integration: MCP client supporting multi-server JSON-RPC sessions and webhook executor with HTTP method support, SSRF protection, and execution logging. This is a 1.80-point gap over MxChat, which only provides WordPress-plugin-based addons (not a protocol). For a modern AI chatbot, MCP protocol support is strategically important for interoperability. Modification: adopt SRC-AXI's MCP client and webhook executor as-is, and plan transport expansion (SSE, stdio) and async webhook execution for Phase 02. |
| `modification_notes` | Adopt SRC-AXI MCP client and webhook executor. Plan SSE/stdio transport support for broader MCP compatibility. Add async webhook execution via queue. Keep SSRF protection as mandatory. |
| `carry_forward_ids` | `["CFI-INT-001"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-TOOLS", "DOM-SECURITY", "DOM-PROVIDER"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `migration_notes` | MCP client manager is a self-contained singleton class. Webhook executor is a static class. Both port cleanly. MCP server configuration needs admin UI. Transport expansion is net-new work. |
| `carry_forward_ids` | `["CFI-INT-001"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-INT-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-INT-002` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `Webhook and event system` |
| `capability_summary` | Webhook registration, execution, logging, and event-driven integration patterns for connecting chatbot actions to external HTTP endpoints. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-INT-AXI-001` | `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` | `FEAT-AXI-INT-002` | `implemented` |
| `SRC-MXC` | `ANC-INT-MXC-001` | `mxchat-basic/includes/class-mxchat-addons.php` | `FEAT-MXC-INT-001` | `implemented` |
| `SRC-BAA` | `ANC-INT-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-INT-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 5 | 4 | 3 | 4.35 |
| `SRC-MXC` | 2 | 3 | 3 | 3 | 3 | 2.70 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's webhook executor is the only implementation with HTTP execution capabilities, SSRF protection, placeholder substitution, and execution logging. MxChat has no webhook execution engine. Modification: adopt SRC-AXI's webhook executor and add event-driven triggers (webhook fired on specific chatbot events) and async execution via queue for non-blocking webhook calls during chat. |
| `modification_notes` | Adopt SRC-AXI webhook executor. Add event-driven webhook triggers (on_message, on_tool_call, on_moderation, etc.). Add async execution via WP cron or queue table. Extend placeholder system for custom variables. |
| `carry_forward_ids` | `["CFI-INT-002"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-SECURITY", "DOM-RUNTIME"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `migration_notes` | Webhook executor is a self-contained static class. Event-driven triggers are net-new architecture. Async execution needs queue infrastructure. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-INT-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-INT-003` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `capability_name` | `Add-on and extension framework` |
| `capability_summary` | Extension management system for discovering, installing, and configuring third-party capabilities that expand the chatbot's integration surface. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-INT-AXI-001` | `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` | `FEAT-AXI-INT-003` | `implemented` |
| `SRC-MXC` | `ANC-INT-MXC-001` | `mxchat-basic/includes/class-mxchat-addons.php` | `FEAT-MXC-INT-001` | `implemented` |
| `SRC-BAA` | `ANC-INT-BAA-NA` | `baachal/baachal.php` | `FEAT-BAA-INT-001` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 3 | 4 | 3.85 |
| `SRC-MXC` | 4 | 4 | 3 | 5 | 4 | 3.95 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat's addon framework has the strongest operability (marketplace UI with visual cards, status detection, deprecation management). AxiaChat's source tracking provides stronger lifecycle management (cascade delete, source-aware organization). Scores are close (0.10 difference). Modification: adopt SRC-MXC's marketplace UI pattern as the visual framework for extension discovery, backed by SRC-AXI's source tracking for lifecycle management. Design a lightweight extension API that supports both WordPress plugin addons and MCP/webhook tool sources in a unified interface. |
| `modification_notes` | Use SRC-MXC marketplace UI pattern for extension discovery page. Integrate SRC-AXI source tracking for tool-providing extensions. Design unified extension model: WordPress plugin addons + MCP servers + webhook sources all visible in one admin surface. Add extension health status and configuration validation. |
| `carry_forward_ids` | `["CFI-INT-003"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-TOOLS"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | MxChat marketplace UI is vanilla CSS/PHP with WordPress escaping. AxiaChat source tracking is DB-backed. Unified extension model is Phase 02 architecture work. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-INT-001

| Field | Value |
|---|---|
| `item_id` | `CFI-INT-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `secondary_domains` | `["DOM-TOOLS"]` |
| `description` | Expand MCP client transport support beyond HTTP: add SSE (Server-Sent Events) and stdio transports for broader MCP server compatibility. Design connection pooling and reconnection strategy. Keep lazy connection pattern. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-INT-001` |
| `related_anchor_ids` | `["ANC-INT-AXI-001"]` |
| `resolution_criteria` | MCP client supports HTTP, SSE, and stdio transports. Connection pooling manages multiple concurrent sessions. Reconnection on transport failure with backoff. Lazy connection pattern preserved. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-INT-002

| Field | Value |
|---|---|
| `item_id` | `CFI-INT-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `secondary_domains` | `["DOM-RUNTIME", "DOM-SECURITY"]` |
| `description` | Design event-driven webhook system: define chatbot events that can trigger webhooks (on_message, on_tool_call, on_moderation_block, on_session_end), add async execution via queue for non-blocking webhook calls during chat flow, and extend placeholder system for custom variables. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-INT-002` |
| `related_anchor_ids` | `["ANC-INT-AXI-001"]` |
| `resolution_criteria` | Webhook events defined with trigger conditions. Async execution queue prevents blocking chat flow. Custom placeholders registerable via filter. SSRF protection applied to all webhook URLs. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-INT-003

| Field | Value |
|---|---|
| `item_id` | `CFI-INT-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-INTEGRATIONS` |
| `secondary_domains` | `["DOM-ADMIN", "DOM-TOOLS"]` |
| `description` | Design unified extension model: single admin surface showing WordPress plugin addons, MCP server connections, and webhook integrations. Merge MxChat's marketplace visual patterns with AxiaChat's source tracking lifecycle. Define extension health monitoring and configuration validation. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-INT-003` |
| `related_anchor_ids` | `["ANC-INT-AXI-001", "ANC-INT-MXC-001"]` |
| `resolution_criteria` | Admin page shows all extension types (addons, MCP servers, webhooks) in unified interface. Source lifecycle (connect, discover, manage, disconnect) works for all types. Visual marketplace pattern with status badges. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-INTEGRATIONS x DOM-SECURITY

- Webhook execution requires SSRF protection (DEC-INT-002 inherits from SRC-AXI). Every external URL must be validated against private IP blocking.
- MCP server authentication needs validation at connection time.
- External payload sanitization required before processing responses.
- Webhook execution logging is an audit trail (cross-references DEC-SEC-005).
- Extension lifecycle events should be logged for security audit.

### DOM-INTEGRATIONS x DOM-PROVIDER

- MCP servers provide tools that are consumed via provider tool-calling protocol (MCP tool -> macro -> tool registry -> provider injection).
- External AI services (Perplexity, Assistants API) are integration-level providers distinct from core provider domain.
- Webhook metadata includes provider-relevant context (bot_slug, session_id).

### DOM-INTEGRATIONS x DOM-TOOLS

- MCP-discovered tools are registered via macro system with `source=mcp` (DEC-TOL-003).
- Webhook-associated tools use `source=webhook` in macro registry.
- Cascade delete when integration sources removed (DEC-INT-001) affects tool registry.
- Integration source tracking is the bridge between integration and tools domains.

### DOM-INTEGRATIONS x DOM-ADMIN

- Extension marketplace UI (DEC-INT-003) is an admin page.
- MCP server configuration needs admin surface.
- Webhook management needs admin CRUD.
- Extension health status displayed in admin.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-INT-001` | External protocol support | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.50 |
| `DEC-INT-002` | Webhook and event system | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.35 |
| `DEC-INT-003` | Add-on and extension framework | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 3.95 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 3 |
| Adopt-As-Is | 0 |
| Adopt-With-Modifications | 3 |
| Reject/Defer | 0 |
| Domain Winner | `SRC-AXI` (2 of 3 capabilities) |
| Dominant Maturity | `production_stable` |
| Key Risks | MCP transport expansion (HTTP-only); async webhook execution; unified extension model design |
| Cross-Domain Dependencies | `DOM-TOOLS`, `DOM-SECURITY`, `DOM-PROVIDER`, `DOM-ADMIN` |
| Carry-Forward Items | `CFI-INT-001`, `CFI-INT-002`, `CFI-INT-003` |
