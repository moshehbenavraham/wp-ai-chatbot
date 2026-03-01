# Security Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S04-SEC` |
| `session_id` | `phase01-session04-security-tools-and-integrations-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-SECURITY"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S04-001` |

---

## Entry: `DEC-SEC-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-SEC-001` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Input validation and sanitization` |
| `capability_summary` | Input validation pipeline with field-specific sanitization, custom domain validators, output escaping, and bounds checking for all trust boundaries in the chat and admin flows. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-SEC-AXI-001` | `axiachat-ai/includes/sanitize-helpers.php`, `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-SEC-001` | `implemented` |
| `SRC-MXC` | `ANC-SEC-MXC-001` | `mxchat-basic/admin/class-ajax-handler.php` | `FEAT-MXC-SEC-001` | `implemented` |
| `SRC-BAA` | `ANC-SEC-BAA-001` | `baachal/admin/settings-page.php`, `baachal/baachal.php` | `FEAT-BAA-SEC-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 5 | 4 | 4 | 4.50 |
| `SRC-MXC` | 4 | 4 | 4 | 4 | 3 | 3.85 |
| `SRC-BAA` | 4 | 4 | 4 | 3 | 3 | 3.70 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat has the strongest input validation with custom domain sanitizers (`aichat_sanitize_session_id`, `aichat_bounded_int`, `aichat_validate_patch_payload`) that address domain-specific risks no other source covers. The encrypted API key pattern and tool call structure validation provide best-in-class security. MxChat adds strong per-field type mapping and whitelist model validation. Baachal contributes the most granular bounds checking pattern (per-field min/max ranges). Modification: adopt SRC-AXI's custom sanitizer library and tool call validation, integrate SRC-MXC's whitelist model validation pattern and per-endpoint type mapping, and adopt SRC-BAA's granular bounds checking approach for all configurable fields. |
| `modification_notes` | Merge SRC-BAA's bounds checking granularity into SRC-AXI sanitizer library. Add SRC-MXC's model whitelist validation. Ensure all three sanitization patterns (custom domain sanitizers, per-field type mapping, granular bounds) are available as reusable helpers. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RUNTIME", "DOM-ADMIN", "DOM-TOOLS"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Custom sanitizer functions can be adopted directly. Bounds checking patterns need to be formalized in a validation registry. Tool call validation is SRC-AXI exclusive and ports cleanly. |
| `carry_forward_ids` | `["CFI-SEC-001"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-SEC-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-SEC-002` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Authentication and capability enforcement` |
| `capability_summary` | Nonce verification, capability checks, API key management, per-bot access control, and session-based authentication for admin and public-facing endpoints. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-SEC-AXI-001` | `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-SEC-002` | `implemented` |
| `SRC-MXC` | `ANC-SEC-MXC-001` | `mxchat-basic/admin/class-ajax-handler.php`, `mxchat-basic/mxchat-basic.php` | `FEAT-MXC-SEC-002` | `implemented` |
| `SRC-BAA` | `ANC-SEC-BAA-001` | `baachal/baachal.php`, `baachal/admin/settings-page.php` | `FEAT-BAA-SEC-002` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 5 | 4 | 4 | 4.20 |
| `SRC-MXC` | 4 | 4 | 4 | 4 | 3 | 3.85 |
| `SRC-BAA` | 3 | 3 | 3 | 3 | 4 | 3.15 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat has the strongest auth pattern with API key encryption at rest (unique among sources), per-bot capability settings, and session-based authentication. MxChat's dual-check pattern (nonce + capability on every endpoint) is the most consistent admin access control. Baachal's hook parameter filtering (excluding core settings from extension hooks) is a unique extensibility-aware security pattern. Modification: adopt SRC-AXI's encrypted key storage and per-bot capability model, enforce SRC-MXC's dual-check pattern on all admin endpoints, and implement SRC-BAA's hook parameter filtering to prevent key leakage to extensions. |
| `modification_notes` | Mandate nonce + capability dual-check on every admin endpoint (SRC-MXC pattern). Implement API key encryption at rest (SRC-AXI pattern). Add per-bot capability settings (SRC-AXI). Filter sensitive settings from extension hooks (SRC-BAA pattern). |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-PROVIDER", "DOM-TOOLS", "DOM-INTEGRATIONS"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | API key encryption requires migration routine for existing plaintext keys. Per-bot capability settings need admin UI. Hook parameter filtering is a new pattern to implement. |
| `carry_forward_ids` | `["CFI-SEC-002"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-SEC-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-SEC-003` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Content moderation and abuse prevention` |
| `capability_summary` | Multi-layer moderation pipeline including rate limiting, IP controls, banned words, external content moderation, honeypot anti-bot measures, and spam detection for public-facing chat endpoints. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-SEC-AXI-001` | `axiachat-ai/includes/moderation.php`, `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-SEC-003` | `implemented` |
| `SRC-MXC` | `ANC-SEC-MXC-001` | `mxchat-basic/includes/class-mxchat-addons.php` | `FEAT-MXC-SEC-003` | `implemented` |
| `SRC-BAA` | `ANC-SEC-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-SEC-003` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 5 | 4 | 4 | 4.50 |
| `SRC-MXC` | 2 | 3 | 2 | 2 | 3 | 2.35 |
| `SRC-BAA` | 1 | 2 | 1 | 1 | 1 | 1.20 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_as_is` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's moderation is the clear winner with a 5-layer defense-in-depth approach: honeypot, rate limiting, spam signatures, banned words regex, and OpenAI Moderation API integration. The 2.15-point gap over MxChat makes this unambiguous. MxChat's moderation is behind a paywall (paid addon), and Baachal has no moderation at all. For a public-facing chat plugin, built-in moderation is a non-negotiable security requirement. Adopt SRC-AXI's moderation pipeline as-is -- it is production-stable and covers all major abuse vectors. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-RUNTIME", "DOM-ADMIN"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `migration_notes` | Moderation module is self-contained (moderation.php). OpenAI Moderation API is optional (graceful fallback). IP detection and banned words work without external dependencies. Rate limiter is already implemented in lib/rate-limiter.php. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-SEC-004`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-SEC-004` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Privacy controls and usage limits` |
| `capability_summary` | User privacy protections including data minimization, IP privacy controls, conversation usage limits, and API credential security at rest. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-SEC-AXI-001` | `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-SEC-004` | `implemented` |
| `SRC-MXC` | `ANC-SEC-MXC-001` | `mxchat-basic/admin/class-ajax-handler.php` | `FEAT-MXC-SEC-004` | `implemented` |
| `SRC-BAA` | `ANC-SEC-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-SEC-004` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 5 | 4 | 3 | 4.05 |
| `SRC-MXC` | 2 | 3 | 2 | 2 | 2 | 2.20 |
| `SRC-BAA` | 2 | 3 | 2 | 2 | 2 | 2.20 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat is the only source with meaningful privacy controls: encrypted API keys, privacy-aware IP handling (hidden when tracking disabled), daily usage limits with configurable behavior, and debug suppression in production. Both MxChat and Baachal store API keys as plaintext and lack privacy controls. Modification: adopt SRC-AXI's privacy and usage limit architecture, add Baachal's memory limit concept (conversation message cap) as an additional privacy layer, and plan API key encryption migration for existing plaintext keys. |
| `modification_notes` | Adopt SRC-AXI's encrypted key storage. Add configurable conversation memory limit (SRC-BAA concept). Implement IP privacy toggle. Plan encryption migration routine for existing plaintext keys (Phase 02). |
| `carry_forward_ids` | `["CFI-SEC-003"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-PROVIDER", "DOM-RUNTIME"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | API key encryption migration requires careful handling of existing plaintext keys. Privacy toggle needs admin UI. Usage limits need per-bot configuration. |
| `carry_forward_ids` | `["CFI-SEC-003"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-SEC-005`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-SEC-005` |
| `domain_id` | `DOM-SECURITY` |
| `capability_name` | `Audit logging and diagnostics` |
| `capability_summary` | Security event logging, execution audit trails, diagnostic capabilities, and debug tooling for monitoring security-sensitive operations in the chatbot runtime. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-SEC-AXI-001` | `axiachat-ai/includes/class-aichat-ajax.php` | `FEAT-AXI-SEC-005` | `implemented` |
| `SRC-MXC` | `ANC-SEC-MXC-001` | `mxchat-basic/admin/class-ajax-handler.php` | `FEAT-MXC-SEC-001` | `implemented` |
| `SRC-BAA` | `ANC-SEC-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-SEC-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 4 | 3 | 3.85 |
| `SRC-MXC` | 2 | 3 | 3 | 2 | 2 | 2.40 |
| `SRC-BAA` | 1 | 2 | 2 | 1 | 1 | 1.40 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat is the only source with meaningful audit logging: conditional debug logging, moderation decision tracking, tool execution timing, provider call logging, webhook execution logging with DB persistence, and error captures with context. MxChat and Baachal have no dedicated audit logging. Modification: adopt SRC-AXI's logging patterns and integrate with the project's structured logger (`lib/structured-logger.php`) for consistent JSON output. Add log level hierarchy and retention policy. |
| `modification_notes` | Integrate SRC-AXI logging patterns with `lib/structured-logger.php`. Add structured log levels (debug, info, warn, error). Add log retention/rotation policy. Ensure webhook execution logs use structured format. Plan security event dashboard for admin UI (Phase 02+). |
| `carry_forward_ids` | `["CFI-SEC-004"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-RUNTIME", "DOM-TOOLS", "DOM-INTEGRATIONS"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Structured logger already exists in `lib/structured-logger.php`. SRC-AXI logging patterns need to be adapted to use structured output. Webhook log table schema should be evaluated for structured logger compatibility. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-SEC-001

| Field | Value |
|---|---|
| `item_id` | `CFI-SEC-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-SECURITY` |
| `description` | Design unified sanitization library: merge SRC-AXI custom domain sanitizers (session ID, bounded int, JSON payload, tool call validation) with SRC-BAA granular bounds checking and SRC-MXC whitelist model validation into a reusable sanitization module. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-SEC-001` |
| `related_anchor_ids` | `["ANC-SEC-AXI-001", "ANC-SEC-MXC-001", "ANC-SEC-BAA-001"]` |
| `resolution_criteria` | Unified sanitization module provides domain-specific validators, configurable bounds checking, and model whitelist validation as reusable functions. All AJAX and REST endpoints use the unified module. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-SEC-002

| Field | Value |
|---|---|
| `item_id` | `CFI-SEC-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-SECURITY` |
| `secondary_domains` | `["DOM-PROVIDER", "DOM-ADMIN"]` |
| `description` | Implement API key encryption at rest with migration routine for existing plaintext keys. Design key rotation strategy. Integrate with provider registry for transparent encrypted key retrieval. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-SEC-002` |
| `related_anchor_ids` | `["ANC-SEC-AXI-001"]` |
| `resolution_criteria` | API keys encrypted at rest in wp_options. Migration routine safely converts existing plaintext keys. Provider registry retrieves and decrypts keys transparently. Key rotation documented. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-SEC-003

| Field | Value |
|---|---|
| `item_id` | `CFI-SEC-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-SECURITY` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `description` | Design privacy and usage limit configuration: daily conversation limits with configurable behavior (hard/soft), IP privacy toggle, conversation memory cap, and admin UI for all privacy controls. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-SEC-004` |
| `related_anchor_ids` | `["ANC-SEC-AXI-001", "ANC-SEC-BAA-001"]` |
| `resolution_criteria` | Admin UI exposes usage limits, IP privacy toggle, and memory cap. Limits enforced at runtime per session/bot. Privacy-aware IP handling defaults to hidden. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-SEC-004

| Field | Value |
|---|---|
| `item_id` | `CFI-SEC-004` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session04-security-tools-and-integrations-domains` |
| `domain_id` | `DOM-SECURITY` |
| `secondary_domains` | `["DOM-ADMIN", "DOM-RUNTIME"]` |
| `description` | Integrate audit logging with structured logger: adapt SRC-AXI logging patterns to use `lib/structured-logger.php` for consistent JSON output. Define log levels, retention policy, and security event categories. Plan admin security dashboard. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-SEC-005` |
| `related_anchor_ids` | `["ANC-SEC-AXI-001"]` |
| `resolution_criteria` | Security events logged via structured logger with level hierarchy. Log retention policy defined. Webhook execution logs use structured format. Admin can view recent security events. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-SECURITY x DOM-PROVIDER

- API key encryption (DEC-SEC-002) directly affects provider registry key retrieval. Encrypted storage must integrate with provider adapter interface from DEC-PRO-001.
- Provider-specific tool injection filters (SRC-AXI) pass through security validation before provider calls.

### DOM-SECURITY x DOM-RUNTIME

- Moderation pipeline (DEC-SEC-003) is the primary security gate in the runtime request path. Every chat message must pass moderation before provider dispatch.
- Rate limiting operates at the session/bot level within the runtime pipeline.
- Input sanitization (DEC-SEC-001) is the first security layer in the runtime request handler.

### DOM-SECURITY x DOM-RAG

- Context assembly security policy injection (DEC-RAG-004 from session 03) depends on security domain's policy configuration.
- Template tokens in RAG context use allowlist registry (no eval) -- a security enforcement from DOM-SECURITY.
- Role-based content access (SRC-MXC RAG feature) is a security control applied at the RAG layer.

### DOM-SECURITY x DOM-ADMIN

- Capability enforcement (DEC-SEC-002) applies to all admin endpoints. Admin page routing must enforce nonce + capability dual-check.
- Privacy and usage limit configuration (CFI-SEC-003) needs admin UI surface.
- Audit logging dashboard (CFI-SEC-004) is an admin feature.
- Hook parameter filtering (SRC-BAA pattern) prevents settings hooks from leaking API keys.

### DOM-SECURITY x DOM-TOOLS

- Tool call structure validation (SRC-AXI, part of DEC-SEC-001) prevents malformed tool calls from reaching provider APIs.
- Per-bot capability settings (DEC-SEC-002) control which tools a bot can access.
- Tool execution must pass through moderation and rate limiting gates.

### DOM-SECURITY x DOM-INTEGRATIONS

- Webhook execution requires SSRF protection (SRC-AXI private host blocking from integrations domain).
- MCP server connections need authentication validation.
- External endpoint payloads must be sanitized before processing.
- Webhook execution logging is an audit trail concern.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-SEC-001` | Input validation and sanitization | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.50 |
| `DEC-SEC-002` | Authentication and capability enforcement | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.20 |
| `DEC-SEC-003` | Content moderation and abuse prevention | `SRC-AXI` | `adopt_as_is` | `adopt` | 4.50 |
| `DEC-SEC-004` | Privacy controls and usage limits | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.05 |
| `DEC-SEC-005` | Audit logging and diagnostics | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 3.85 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 5 |
| Adopt-As-Is | 1 |
| Adopt-With-Modifications | 4 |
| Reject/Defer | 0 |
| Domain Winner | `SRC-AXI` (5 of 5 capabilities) |
| Dominant Maturity | `production_stable` |
| Key Risks | API key encryption migration; moderation external dependency (OpenAI); audit logging integration with structured logger |
| Cross-Domain Dependencies | `DOM-RUNTIME`, `DOM-ADMIN`, `DOM-PROVIDER`, `DOM-TOOLS`, `DOM-INTEGRATIONS`, `DOM-RAG` |
| Carry-Forward Items | `CFI-SEC-001`, `CFI-SEC-002`, `CFI-SEC-003`, `CFI-SEC-004` |
