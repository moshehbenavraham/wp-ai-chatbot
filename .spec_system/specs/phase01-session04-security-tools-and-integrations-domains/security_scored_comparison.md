# Security Scored Comparison

**Session ID**: `phase01-session04-security-tools-and-integrations-domains`
**Domain**: `DOM-SECURITY`
**Context Pack**: `CP-P01-S04-001`
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

## Capability 1: Input Validation and Sanitization

Comparison of input validation depth, sanitization coverage, and output escaping across all trust boundaries.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-SEC-001` | `SRC-AXI` | 5 | 4 | 5 | 4 | 4 | 4.50 | `adopt` |
| `FEAT-MXC-SEC-001` | `SRC-MXC` | 4 | 4 | 4 | 4 | 3 | 3.85 | `adopt` |
| `FEAT-BAA-SEC-001` | `SRC-BAA` | 4 | 4 | 4 | 3 | 3 | 3.70 | `investigate` |

### Evidence Notes

#### FEAT-AXI-SEC-001 - SRC-AXI

- Coverage: 5 - Custom domain sanitizers (session ID, bounded int, JSON payload) plus all WP core sanitizers. Tool call structure validation. Output escaping for both instructions and AI responses. Best coverage of domain-specific validation needs.
- Reliability: 4 - Returns `WP_Error` on validation failures. JSON payload size limit prevents DoS. `wp_unslash` handling for both modern and legacy WordPress behavior.
- Security: 5 - API keys encrypted at rest. Custom sanitizers prevent domain-specific injection vectors (oversized session IDs, JSON bombs, unbounded integers). Tool call validation prevents malformed data reaching provider APIs. Best security posture.
- Operability: 4 - Centralized sanitization helpers reusable across codebase. JSON size limit configurable. Clear error messages on validation failure.
- Extensibility: 4 - Sanitization helpers are standalone functions, easily imported. But no filter hooks for extending validation rules.

- Weighted Summary: 4.50 (adopt - strongest input validation)
- Recommendation: adopt
- Residual Risks: None significant. Comprehensive coverage of domain-specific risks.

#### FEAT-MXC-SEC-001 - SRC-MXC

- Coverage: 4 - Per-field type sanitization on all admin AJAX endpoints. API key format regex. Whitelist validation for models. Threshold and limit bounds. Output escaping in UI.
- Reliability: 4 - Field-specific validation per endpoint. Error responses are sanitized. Bounds checking prevents invalid configuration states.
- Security: 4 - Nonce verification on all endpoints. Capability checks enforced. Field validation prevents injection. But `stripslashes()` on POST values could re-introduce escaping issues.
- Operability: 4 - Validation logic is per-endpoint, easy to maintain. Error messages are internationalized.
- Extensibility: 3 - Validation is inline per endpoint, not extracted into reusable helpers. Adding new field types requires per-endpoint changes.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: `stripslashes()` usage. No custom domain sanitizers for complex types.

#### FEAT-BAA-SEC-001 - SRC-BAA

- Coverage: 4 - Most granular bounds checking of all sources (per-field min/max ranges). Provider-specific API key regex. Color sanitization. Array sanitization. Comprehensive for settings-page scope.
- Reliability: 4 - Conservative bounds prevent invalid config. Error notices on validation failures. Conditional updates only on valid input.
- Security: 4 - `check_admin_referer()` nonce verification. `$wpdb->prepare()` throughout. Hook parameters filtered to exclude core settings (prevents key leakage). Provider whitelist.
- Operability: 3 - Validation is settings-page only. No centralized sanitization helpers for reuse. Error messaging is admin-notice based.
- Extensibility: 3 - Hook-based settings save allows extensions. But validation rules are hardcoded, not configurable.

- Weighted Summary: 3.70 (investigate)
- Recommendation: investigate
- Residual Risks: Scope limited to settings page. No API key encryption. No custom sanitizers for runtime data.

---

## Capability 2: Authentication and Capability Enforcement

Comparison of nonce patterns, capability checks, API key management, and access control models.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-SEC-002` | `SRC-AXI` | 4 | 4 | 5 | 4 | 4 | 4.20 | `adopt` |
| `FEAT-MXC-SEC-002` | `SRC-MXC` | 4 | 4 | 4 | 4 | 3 | 3.85 | `adopt` |
| `FEAT-BAA-SEC-002` | `SRC-BAA` | 3 | 3 | 3 | 3 | 4 | 3.15 | `investigate` |

### Evidence Notes

#### FEAT-AXI-SEC-002 - SRC-AXI

- Coverage: 4 - Nonce verification on chat AJAX. Encrypted API key storage. Per-bot capability settings. Session-based authentication. Admin endpoints with capability checks.
- Reliability: 4 - Consistent nonce pattern. Encryption failures degrade gracefully. Session validation enforced early in pipeline.
- Security: 5 - API keys encrypted at rest (unique among sources). Per-bot access control. Session ID validated against UUID pattern. Encrypted key retrieval only at call time minimizes exposure window.
- Operability: 4 - API key encryption transparent to admin users. Per-bot capability configuration in admin UI.
- Extensibility: 4 - Capability settings model extensible per bot. But encryption implementation is internal (not overridable).

- Weighted Summary: 4.20 (adopt - strongest auth/capability)
- Recommendation: adopt
- Residual Risks: Public chat endpoint has no `current_user_can()` (by design for guest access). Session ID predictability not fully assessed.

#### FEAT-MXC-SEC-002 - SRC-MXC

- Coverage: 4 - Dual-check pattern (nonce + capability) on every admin endpoint. 6 distinct nonces for Actions UI. Admin-only access enforced consistently.
- Reliability: 4 - Consistent enforcement pattern across all endpoints. Failure returns sanitized error.
- Security: 4 - Strong admin access control. But API keys stored as plaintext WP options (no encryption at rest). No per-bot capability settings.
- Operability: 4 - Nonce and capability checks are boilerplate pattern, easy to audit.
- Extensibility: 3 - No per-bot access control. No external auth integration. Adding new capability levels requires code changes.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: Plaintext API key storage. No per-bot capability differentiation.

#### FEAT-BAA-SEC-002 - SRC-BAA

- Coverage: 3 - Nonce verification on settings save. Capability check on uninstall. Option namespacing. Hook parameter filtering. But fewer access control surfaces than other sources.
- Reliability: 3 - Settings save flow is reliable. But fewer enforcement points means less validation coverage.
- Security: 3 - Basic nonce + capability pattern. Plaintext API keys. Hook parameter filtering prevents accidental key leakage (unique strength).
- Operability: 3 - Simple admin-referer pattern.
- Extensibility: 4 - Hook-based settings save with filtered parameters is the most extensibility-aware auth pattern. Extensions cannot accidentally access core settings.

- Weighted Summary: 3.15 (investigate)
- Recommendation: investigate
- Residual Risks: Fewer enforcement surfaces. Plaintext API keys. `activate_plugins` capability for uninstall is more restrictive than necessary.

---

## Capability 3: Content Moderation and Abuse Prevention

Comparison of moderation pipelines, rate limiting, anti-bot measures, and abuse controls.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-SEC-003` | `SRC-AXI` | 5 | 4 | 5 | 4 | 4 | 4.50 | `adopt` |
| `FEAT-MXC-SEC-003` | `SRC-MXC` | 2 | 3 | 2 | 2 | 3 | 2.35 | `reject` |
| `FEAT-BAA-SEC-003` | `SRC-BAA` | 1 | 2 | 1 | 1 | 1 | 1.20 | `reject` |

### Evidence Notes

#### FEAT-AXI-SEC-003 - SRC-AXI

- Coverage: 5 - 5-layer moderation: honeypot, rate limiting, spam signatures, banned words regex, OpenAI Moderation API. IP detection with proxy awareness (Cloudflare, X-Forwarded-For). CIDR matching for IPv4/IPv6 ban lists. Best-in-class moderation breadth.
- Reliability: 4 - Graceful fallback on external API failure (permits rather than blocks). IP validation via `FILTER_VALIDATE_IP`. Rate limiter is session-scoped to prevent bypass. Spam signature detection catches known patterns.
- Security: 5 - Multi-layer defense-in-depth. External moderation API catches content that regex cannot. Rate limiting prevents automated abuse. IP sanitization prevents header spoofing. Strongest moderation security.
- Operability: 4 - Banned words configurable via admin. Rate limits configurable. OpenAI moderation toggle. IP ban list management.
- Extensibility: 4 - Moderation check callable as standalone function. Rate limiter is per-session/per-bot extensible. But no filter hooks for adding custom moderation layers.

- Weighted Summary: 4.50 (adopt - clear winner)
- Recommendation: adopt
- Residual Risks: External moderation requires OpenAI API key. Honeypot is client-side bypassable. IP header spoofing partially mitigated.

#### FEAT-MXC-SEC-003 - SRC-MXC

- Coverage: 2 - Core plugin has only CSRF protection (nonces). Moderation is a paid add-on (`mxchat-moderation`) providing IP/email blocking and content filtering. No built-in rate limiting, honeypot, or external moderation.
- Reliability: 3 - Nonce verification is reliable for CSRF. Addon moderation reliability unknown (not in core).
- Security: 2 - Below threshold. Core plugin lacks runtime abuse prevention. Paid addon required for moderation. DIM-SECURITY <= 2 triggers reject band.
- Operability: 2 - No moderation controls in free plugin. Must install and configure separate addon.
- Extensibility: 3 - Addon architecture allows moderation to be added/removed. But moderation is not a plugin-level extensibility point.

- Weighted Summary: 2.35 (reject - moderation behind paywall)
- Recommendation: reject
- Residual Risks: Free plugin has no abuse prevention. Addon dependency for basic security.

#### FEAT-BAA-SEC-003 - SRC-BAA

- Coverage: 1 - No dedicated moderation. Only basic input length constraints via bounds checking.
- Reliability: 2 - Bounds checking is reliable but insufficient for abuse prevention.
- Security: 1 - No rate limiting, no content filtering, no IP controls. Minimal security posture for public-facing chat.
- Operability: 1 - No moderation configuration UI.
- Extensibility: 1 - No moderation hooks or extension points.

- Weighted Summary: 1.20 (reject - no meaningful moderation)
- Recommendation: reject
- Residual Risks: Public-facing chat with no abuse controls is a significant security gap.

---

## Capability 4: Privacy Controls and Usage Limits

Comparison of user privacy protections, data minimization, conversation limits, and API key security.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-SEC-004` | `SRC-AXI` | 4 | 4 | 5 | 4 | 3 | 4.05 | `adopt` |
| `FEAT-MXC-SEC-004` | `SRC-MXC` | 2 | 3 | 2 | 2 | 2 | 2.20 | `reject` |
| `FEAT-BAA-SEC-004` | `SRC-BAA` | 2 | 3 | 2 | 2 | 2 | 2.20 | `reject` |

### Evidence Notes

#### FEAT-AXI-SEC-004 - SRC-AXI

- Coverage: 4 - Daily usage limits with configurable behavior. Privacy-aware IP handling (hidden when tracking disabled). Encrypted API keys. Session-based rate limiting. Debug data suppressed in production.
- Reliability: 4 - Usage limits enforced consistently per session. Encryption handles edge cases (empty keys, migration).
- Security: 5 - API key encryption at rest is unique among sources. IP privacy awareness prevents unnecessary data collection. Usage limits prevent cost overruns. Debug suppression prevents information leakage.
- Operability: 4 - Usage limits and rate limits configurable via admin. IP privacy follows tracking preference automatically.
- Extensibility: 3 - Usage limit behavior configurable (hard/soft). But privacy controls not pluggable.

- Weighted Summary: 4.05 (adopt)
- Recommendation: adopt
- Residual Risks: Encryption implementation is internal -- key rotation not documented.

#### FEAT-MXC-SEC-004 - SRC-MXC

- Coverage: 2 - No dedicated privacy controls. No usage limits. API key format validation only. No encryption.
- Reliability: 3 - What exists (format validation) works reliably.
- Security: 2 - Plaintext API keys in database. No IP privacy controls. No conversation limits. DIM-SECURITY <= 2.
- Operability: 2 - No privacy configuration UI.
- Extensibility: 2 - No privacy or limit extension points.

- Weighted Summary: 2.20 (reject)
- Recommendation: reject
- Residual Risks: Plaintext API keys. No usage or privacy controls.

#### FEAT-BAA-SEC-004 - SRC-BAA

- Coverage: 2 - Memory limit (1-50 messages) is only privacy-adjacent control. No dedicated privacy features.
- Reliability: 3 - Memory limit works reliably.
- Security: 2 - Plaintext API keys. No IP controls. No usage limits beyond memory. DIM-SECURITY <= 2.
- Operability: 2 - Memory limit configurable in settings. No other privacy controls.
- Extensibility: 2 - No privacy extension points.

- Weighted Summary: 2.20 (reject)
- Recommendation: reject
- Residual Risks: Plaintext API keys. Minimal privacy controls.

---

## Capability 5: Audit Logging and Diagnostics

Comparison of security event logging, audit trails, and diagnostic capabilities.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-SEC-005` | `SRC-AXI` | 4 | 4 | 4 | 4 | 3 | 3.85 | `adopt` |
| `FEAT-MXC-SEC-001` | `SRC-MXC` | 2 | 3 | 3 | 2 | 2 | 2.40 | `reject` |
| `FEAT-BAA-SEC-001` | `SRC-BAA` | 1 | 2 | 2 | 1 | 1 | 1.40 | `reject` |

### Evidence Notes

#### FEAT-AXI-SEC-005 - SRC-AXI

- Coverage: 4 - Conditional debug logging. Context retrieval preview. Provider call logging. Tool execution timing. Moderation decision logging. Rate limit logging. Webhook execution logging with DB table. Error captures with context.
- Reliability: 4 - Logging is conditional (AICHAT_DEBUG) to avoid production overhead. Webhook logging gracefully handles missing table. Duration tracking for performance diagnostics.
- Security: 4 - No secrets in log output. Debug headers suppressed in production. Webhook payload excerpts truncated (1000 chars) to prevent log bloat with sensitive data.
- Operability: 4 - Debug toggle via constant. Webhook logs queryable from DB. Error captures include enough context for troubleshooting.
- Extensibility: 3 - Logging is function-based, not class-based. No structured log level hierarchy. No filter hooks for log destinations.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: No structured log level system. No log rotation or retention policy.

#### FEAT-MXC-SEC-001 - SRC-MXC (audit aspects)

- Coverage: 2 - Error responses are logged via AJAX error returns. No dedicated audit logging. No event tracking. No webhook or tool execution logging.
- Reliability: 3 - Error responses are consistent and sanitized.
- Security: 3 - Error responses don't leak internal details.
- Operability: 2 - No logging UI. No diagnostic tools. No debug mode.
- Extensibility: 2 - No logging extension points.

- Weighted Summary: 2.40 (reject)
- Recommendation: reject
- Residual Risks: No audit trail. No diagnostic capability beyond AJAX error responses.

#### FEAT-BAA-SEC-001 - SRC-BAA (audit aspects)

- Coverage: 1 - No audit logging. No debug mode. Error notices on settings page only.
- Reliability: 2 - Error notices are basic but functional.
- Security: 2 - Error notices don't leak sensitive data.
- Operability: 1 - No diagnostic tools.
- Extensibility: 1 - No logging extension points.

- Weighted Summary: 1.40 (reject)
- Recommendation: reject
- Residual Risks: No audit trail at all. No visibility into runtime security events.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all 5 scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 4.4 | 4.0 | 4.8 | 4.0 | 3.6 | 4.22 | `adopt` |
| `SRC-MXC` | 2.8 | 3.4 | 3.0 | 2.8 | 2.6 | 2.93 | `investigate` |
| `SRC-BAA` | 2.2 | 2.8 | 2.4 | 2.0 | 2.2 | 2.33 | `reject` |

**Notes on aggregation**: Averages computed across 5 capabilities, giving equal weight to each. SRC-AXI dominates with the highest security dimension score (4.8 average) driven by API key encryption, multi-layer moderation, and privacy-aware design. SRC-MXC is at the investigate threshold due to moderation behind paywall and plaintext API keys. SRC-BAA is below threshold in all capabilities due to minimal moderation and no dedicated privacy/audit controls.
