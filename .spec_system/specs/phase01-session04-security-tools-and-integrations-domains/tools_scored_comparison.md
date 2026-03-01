# Tools Scored Comparison

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Domain**: `DOM-TOOLS`
**Context Pack**: `CP-P01-S04-002`
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

## Capability 1: Tool/Action Registration

Comparison of tool/action definition, registration APIs, and schema validation.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-TOL-001` | `SRC-AXI` | 5 | 4 | 5 | 4 | 5 | 4.65 | `adopt` |
| `FEAT-MXC-TOL-001` | `SRC-MXC` | 3 | 4 | 4 | 4 | 3 | 3.55 | `investigate` |
| `FEAT-BAA-TOL-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-TOL-001 - SRC-AXI

- Coverage: 5 - Full tool registration API with JSON Schema, type validation (function/custom), callable validation, name sanitization, description limits, timeout/max-calls/auth/parallel config. Global registry with `init` lifecycle. Best-in-class registration breadth.
- Reliability: 4 - Schema normalization handles provider-specific quirks (empty arrays to objects, additionalProperties coercion). Table existence checks for fresh installs. Registration failures handled gracefully.
- Security: 5 - Name sanitization prevents injection. Schema validation prevents malformed tool definitions. Callable validation ensures callbacks exist. Description length limits prevent abuse. Tool call structure validation in AJAX handler.
- Operability: 4 - Global registry accessible from anywhere. Action hook on registration. Capability settings configurable per bot.
- Extensibility: 5 - `aichat_tool_registered` action hook. Filter-based provider injection. Global registry pattern allows external tool registration. Macro layer for tool grouping. Best extensibility.

- Weighted Summary: 4.65 (adopt - strongest registration)
- Recommendation: adopt
- Residual Risks: None significant. Well-designed registration API.

#### FEAT-MXC-TOL-001 - SRC-MXC

- Coverage: 3 - Action/intent framework with label, trigger phrases, threshold, callback type, multi-bot assignment. CRUD operations. But not a formal tool-calling API -- intent matching instead of provider-invoked tool calls.
- Reliability: 4 - AJAX-based CRUD with per-operation nonces. Debounced search. Optimistic UI with revert on error. Bulk operations.
- Security: 4 - Per-operation nonces. Pro/addon gating prevents unauthorized feature access. Output escaping throughout. XSS prevention.
- Operability: 4 - Split-panel admin UI with mobile responsive design. Two-step editor with category filtering. Dashboard with documentation. Strong admin UX.
- Extensibility: 3 - Type card system enables extensible action types. But no external registration API. Adding action types requires code changes. No lifecycle hooks.

- Weighted Summary: 3.55 (investigate)
- Recommendation: investigate
- Residual Risks: Not a formal tool-calling API. Actions are intent-matched, not AI-model-invoked. Limited to predefined callback types.

#### FEAT-BAA-TOL-001 - SRC-BAA

- Coverage: 0 - No tool or action registration capability.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Capability 2: Execution Engine

Comparison of tool execution mechanisms, provider integration, and runtime behavior.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-TOL-002` | `SRC-AXI` | 5 | 4 | 4 | 4 | 5 | 4.45 | `adopt` |
| `FEAT-MXC-TOL-001` | `SRC-MXC` | 3 | 3 | 3 | 3 | 3 | 3.00 | `investigate` |
| `FEAT-BAA-TOL-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-TOL-002 - SRC-AXI

- Coverage: 5 - Provider-native tool injection for 3 providers (OpenAI function calling, Gemini function declarations, Claude tool use). Per-provider schema normalization. Web search tool injection with domain filtering. Provider-specific versioning (Claude `web_search_20250305`). Pre-processing filter for system message policy. Best multi-provider coverage.
- Reliability: 4 - Filter-based injection prevents duplicate tools. Provider limitation notices (Gemini multi-tool). Bot tool configuration from DB. Capability settings applied at injection time.
- Security: 4 - Tool call validation in AJAX handler. Capability settings restrict tool access per bot. Domain filtering for web search. Schema normalization prevents provider-specific exploits.
- Operability: 4 - Bot-specific tool configuration via admin. Capability settings for domain restrictions. Tool injection transparent to end users.
- Extensibility: 5 - Filter hooks per provider. Capability settings extensible. Macro expansion bridges tools from external sources (MCP, webhooks). Most extensible execution pattern.

- Weighted Summary: 4.45 (adopt)
- Recommendation: adopt
- Residual Risks: Provider-specific normalization must keep pace with API changes. Gemini REST API multi-tool limitation.

#### FEAT-MXC-TOL-001 - SRC-MXC (execution aspects)

- Coverage: 3 - Intent matching against user messages via similarity threshold. Callback execution based on predefined types. Multi-bot assignment. But no AI-model-invoked tool calling protocol.
- Reliability: 3 - Toggle status with optimistic UI. AJAX callbacks with nonce verification. But no timeout handling, no retry, no parallel execution.
- Security: 3 - Nonce verification. Pro/addon gating. But no tool call validation, no schema enforcement.
- Operability: 3 - Admin UI for action management. Threshold configuration. But no runtime execution visibility.
- Extensibility: 3 - Category-based type system. But no filter hooks for extending execution behavior.

- Weighted Summary: 3.00 (investigate)
- Recommendation: investigate
- Residual Risks: Intent matching is a different paradigm from tool calling. No provider-level integration.

#### FEAT-BAA-TOL-001 - SRC-BAA

- Coverage: 0 - No execution engine.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Capability 3: Schema Validation and Tool Lifecycle

Comparison of tool definition validation, lifecycle management, and schema enforcement.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-TOL-003`, `FEAT-AXI-TOL-004` | `SRC-AXI` | 5 | 4 | 4 | 4 | 4 | 4.30 | `adopt` |
| `FEAT-MXC-TOL-002` | `SRC-MXC` | 3 | 3 | 4 | 4 | 3 | 3.35 | `investigate` |
| `FEAT-BAA-TOL-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-TOL-003, FEAT-AXI-TOL-004 - SRC-AXI

- Coverage: 5 - Macro registry for tool grouping with DB persistence. Source tracking (local/MCP/webhook) with cascade delete. Per-bot capability settings for access control. Macro expansion with deduplication. Table existence verification. Best tool lifecycle management.
- Reliability: 4 - Upsert semantics prevent duplicates. DB primary source of truth with memory cache. Cascade delete ensures cleanup when sources removed. Fresh installation handling.
- Security: 4 - Per-bot capability settings control tool access. Source tracking prevents orphaned tools. Macro expansion validates tool references.
- Operability: 4 - Admin-configurable capability settings per bot. Macro management via admin. Source-aware tool organization.
- Extensibility: 4 - Macro system bridges external sources (MCP, webhooks). Per-bot settings extensible. But macro schema is not formally documented for external consumers.

- Weighted Summary: 4.30 (adopt)
- Recommendation: adopt
- Residual Risks: Macro schema informally defined. Tool lifecycle hooks (pre-execute, post-execute) not yet implemented.

#### FEAT-MXC-TOL-002 - SRC-MXC

- Coverage: 3 - Type card system with categories. Pro/addon gating. Multi-bot assignment. But no formal schema validation, no tool lifecycle hooks, no grouping.
- Reliability: 3 - Form validation (required fields). But no schema enforcement beyond basic field presence.
- Security: 4 - Nonce fields on forms. Output escaping throughout. Pro/addon gating prevents unauthorized usage. Sanitized data attributes.
- Operability: 4 - Split-panel UI. Type categories with search/filter. Dashboard with documentation. Strong admin experience.
- Extensibility: 3 - Category system allows new types. But adding types requires code changes. No external registration.

- Weighted Summary: 3.35 (investigate)
- Recommendation: investigate
- Residual Risks: No formal schema validation. No tool lifecycle management.

#### FEAT-BAA-TOL-001 - SRC-BAA

- Coverage: 0 - N/A.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Capability 4: Admin Controls

Comparison of tool/action administration interfaces and configuration management.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-TOL-004` | `SRC-AXI` | 4 | 4 | 4 | 3 | 4 | 3.85 | `adopt` |
| `FEAT-MXC-TOL-002` | `SRC-MXC` | 4 | 4 | 4 | 5 | 3 | 4.00 | `adopt` |
| `FEAT-BAA-TOL-001` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-TOL-004 - SRC-AXI

- Coverage: 4 - Per-bot capability settings with domain restrictions. Tool enablement configuration. Macro management. But admin UI for tools is more configuration-oriented than visual management.
- Reliability: 4 - Settings persistence via WP options. Per-bot isolation. Macro CRUD via DB.
- Security: 4 - Per-bot access control. Capability settings restrict tool visibility. Domain restrictions for web search.
- Operability: 3 - Configuration is options-based. Less visual than MxChat's split-panel UI.
- Extensibility: 4 - Per-bot settings extensible. Capability settings map allows new tool types. Macro system bridges external sources.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: Admin UI for tools could be more visual. Tool management is primarily configuration-driven.

#### FEAT-MXC-TOL-002 - SRC-MXC

- Coverage: 4 - Full CRUD admin UI for actions. Split-panel layout. Dashboard with documentation. Type selector with categories. Multi-bot assignment.
- Reliability: 4 - Bulk operations. Pagination. Search with debounce. Status toggles. Confirmation modals.
- Security: 4 - Per-operation nonces. Output escaping. Pro/addon gating. XSS prevention.
- Operability: 5 - Best-in-class admin UI: mobile-responsive split-panel, two-step editor, category filtering, bulk operations, dashboard with feature docs. Strongest operability score.
- Extensibility: 3 - Category system. But no external admin extension points.

- Weighted Summary: 4.00 (adopt)
- Recommendation: adopt
- Residual Risks: Admin UI is MxChat-specific. Needs adaptation for unified plugin's tool management.

#### FEAT-BAA-TOL-001 - SRC-BAA

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

### Per-Source Weighted Averages (across all 4 scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 4.75 | 4.0 | 4.25 | 3.75 | 4.50 | 4.31 | `adopt` |
| `SRC-MXC` | 3.25 | 3.50 | 3.75 | 4.00 | 3.00 | 3.48 | `investigate` |
| `SRC-BAA` | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.00 | `reject` |

**Notes on aggregation**: Averages computed across 4 capabilities, giving equal weight to each. SRC-AXI dominates with formal tool-calling API, provider-native integration, and macro registry. SRC-MXC has the strongest admin UI (operability 5 on admin controls) but uses intent matching rather than tool-calling protocol. SRC-BAA has no tools implementation. The two approaches (formal tool calling vs intent matching) are complementary, not competing.
