# Integrations Scored Comparison

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Domain**: `DOM-INTEGRATIONS`
**Context Pack**: `CP-P01-S04-003`
**Scoring Rubric**: `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
**Created**: 2026-03-01

---

## Scoring Scale Reference

| Score | Meaning |
|---|---|
| 0 | Not present or unusable |
| 1 | Bare-minimum with major limits |
| 2 | Partial with clear constraints |
| 3 | Solid for common use cases |
| 4 | Strong with low friction |
| 5 | Best-in-class among reference plugins |

**Weighted Total**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

---

## Capability 1: External Protocol Support

Comparison of MCP client, webhook execution, and standardized external system communication protocols.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-INT-001`, `FEAT-AXI-INT-002` | `SRC-AXI` | 5 | 4 | 5 | 4 | 4 | 4.50 | `adopt` |
| `FEAT-MXC-INT-002` | `SRC-MXC` | 2 | 3 | 3 | 3 | 3 | 2.70 | `reject` |
| `FEAT-BAA-INT-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-INT-001, FEAT-AXI-INT-002 - SRC-AXI

- Coverage: 5 - MCP client with multi-server sessions and JSON-RPC. Webhook executor with HTTP methods (GET/POST/PUT/PATCH). Two distinct integration protocols. Placeholder substitution, conversation history inclusion, metadata tracking. Best external protocol coverage.
- Reliability: 4 - Lazy MCP connection avoids startup penalty. Per-server isolation (failures contained). In-memory cache with TTL. Session cleanup on shutdown. Webhook timeout (15s). SSL verification. Graceful fallback on missing log table.
- Security: 5 - SSRF protection blocks private IPs and localhost. URL validation. Header sanitization. Privacy-aware IP handling. SSL verification. No secrets in webhook payloads. Tool name sanitization. Best integration security.
- Operability: 4 - MCP server configuration via admin. Webhook execution with timing. Execution logging with DB persistence. Tool discovery on demand.
- Extensibility: 4 - MCP protocol is inherently extensible (standardized tool discovery). Webhook placeholders are extensible. But transport currently HTTP-only (no stdio/SSE).

- Weighted Summary: 4.50 (adopt - dominant protocol support)
- Recommendation: adopt
- Residual Risks: MCP transport is HTTP-only (no stdio or SSE). Webhook executor is synchronous (no async/queue).

#### FEAT-MXC-INT-002 - SRC-MXC

- Coverage: 2 - Addon-based channel extension. Individual addons provide Perplexity, Assistants API, WooCommerce, etc. But no standardized external protocol -- each addon is a separate WordPress plugin.
- Reliability: 3 - WordPress plugin activation/deactivation is reliable. Status detection works.
- Security: 3 - Nonce on activation links. License gating. But no SSRF protection, no URL validation for external calls.
- Operability: 3 - Marketplace UI for addon discovery. Per-addon configuration pages.
- Extensibility: 3 - Addon model is extensible (add new WordPress plugins). But no formal extension API.

- Weighted Summary: 2.70 (reject - no standardized protocol)
- Recommendation: reject
- Residual Risks: Addon model is not a protocol-level integration. No standardized tool discovery. Paid dependency.

#### FEAT-BAA-INT-001 - SRC-BAA

- Coverage: 0 - No external protocol support.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Capability 2: Webhook and Event System

Comparison of webhook registration, execution, logging, and event-driven integration patterns.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-INT-002` | `SRC-AXI` | 5 | 4 | 5 | 4 | 3 | 4.35 | `adopt` |
| `FEAT-MXC-INT-001` | `SRC-MXC` | 2 | 3 | 3 | 3 | 3 | 2.70 | `reject` |
| `FEAT-BAA-INT-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-INT-002 - SRC-AXI

- Coverage: 5 - Full webhook execution engine with HTTP method support, parameter merging, placeholder substitution, conversation history, metadata tracking. Execution logging to DB. Duration tracking. Privacy-aware IP handling.
- Reliability: 4 - 15-second timeout. SSL verification. JSON parsing with raw text fallback. Graceful log table handling. HTTP status code validation.
- Security: 5 - SSRF protection (private IP blocking). URL validation. Header sanitization. Payload size limits (conversation history capped). Privacy-aware IP handling. No secret leakage in payloads.
- Operability: 4 - Webhook configuration with static params, headers, and execution options. Execution logging queryable. Duration metrics for performance monitoring.
- Extensibility: 3 - Placeholder system is extensible. But webhook executor is static class (no hooks for extending execution pipeline). No event-driven webhook triggers.

- Weighted Summary: 4.35 (adopt)
- Recommendation: adopt
- Residual Risks: Synchronous execution (no queue/async). No event-driven webhook triggers. Static class limits extension.

#### FEAT-MXC-INT-001 - SRC-MXC

- Coverage: 2 - Addon registry with status detection. No webhook execution engine. No event system. Addons are WordPress plugins, not webhook consumers.
- Reliability: 3 - Addon status detection is reliable. Plugin activation mechanism is WordPress-standard.
- Security: 3 - Nonce on activation links. License gating.
- Operability: 3 - Marketplace UI. Per-addon configuration.
- Extensibility: 3 - Addon model allows new extensions via WordPress plugins.

- Weighted Summary: 2.70 (reject)
- Recommendation: reject
- Residual Risks: No webhook/event capabilities.

#### FEAT-BAA-INT-001 - SRC-BAA

- Coverage: 0 - N/A.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Capability 3: Add-on and Extension Framework

Comparison of extension management, addon marketplace, and third-party plugin integration patterns.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-INT-003` | `SRC-AXI` | 4 | 4 | 4 | 3 | 4 | 3.85 | `adopt` |
| `FEAT-MXC-INT-001` | `SRC-MXC` | 4 | 4 | 3 | 5 | 4 | 3.95 | `adopt` |
| `FEAT-BAA-INT-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-INT-003 - SRC-AXI

- Coverage: 4 - Integration source tracking bridges MCP and webhook tools into tool registry. Cascade delete on source removal. Lifecycle management: connect, discover, register, manage, disconnect. Source-aware macro organization.
- Reliability: 4 - DB-backed source tracking. Table existence checks. Cascade delete prevents orphaned tools.
- Security: 4 - Source tracking enables audit trail. Cascade delete prevents stale tool references. Tool names sanitized.
- Operability: 3 - Source management is configuration-driven (admin UI for MCP servers). Less visual than MxChat marketplace.
- Extensibility: 4 - Source types extensible (local, mcp, webhook, potentially more). Macro system is the extension bridge.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: Extension model is tool-centric. No general-purpose addon framework beyond tool sources.

#### FEAT-MXC-INT-001 - SRC-MXC

- Coverage: 4 - 15+ addon registry. Status detection (active/inactive/not-installed). Marketplace UI with hero features. Deprecation management. License gating.
- Reliability: 4 - WordPress plugin activation is standard and reliable. Status detection via plugin list.
- Security: 3 - Nonce on activation. But no security controls on addon behavior. License gating is payment security, not runtime security.
- Operability: 5 - Best-in-class addon marketplace UI: hero section, card grid, status badges, accent colors, CTA section. Visual discoverability. Documentation links. Best operability.
- Extensibility: 4 - Addon model inherently extensible. Any WordPress plugin can be an addon. Category system. Deprecation flow.

- Weighted Summary: 3.95 (adopt)
- Recommendation: adopt
- Residual Risks: Addon model is WordPress-plugin-based (heavy). No lightweight extension API. Paid license dependency.

#### FEAT-BAA-INT-001 - SRC-BAA

- Coverage: 0 - N/A.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all 3 scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 4.67 | 4.0 | 4.67 | 3.67 | 3.67 | 4.23 | `adopt` |
| `SRC-MXC` | 2.67 | 3.33 | 3.00 | 3.67 | 3.33 | 3.12 | `investigate` |
| `SRC-BAA` | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.00 | `reject` |

**Notes on aggregation**: Averages computed across 3 capabilities, giving equal weight to each. SRC-AXI dominates with protocol-level integration (MCP, webhooks) and strong security (SSRF protection). SRC-MXC has the strongest extension framework operability (marketplace UI) but lacks runtime integration protocols. SRC-BAA has no integration capabilities.
