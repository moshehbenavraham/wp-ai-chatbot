# Session Specification

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Session Overview

This session designs the security subsystem contracts, admin settings architecture, diagnostics
dashboard, structured logging integration plan, privacy controls, and audit trail interface. It
closes all six security carry-forward items (CFI-SEC-001 through CFI-SEC-004 -- two already
resolved in session 02) and all five admin carry-forward items (CFI-ADM-001 through CFI-ADM-005).

Phase 01 identified AxiaChat as the domain winner for security (5/5 score: domain sanitizers,
AES-256-GCM encryption, 5-layer moderation, IP privacy controls, structured audit logging).
MxChat wins admin architecture breadth (multi-page, auto-save, diagnostics panel). Baachal
contributes the tab-based conditional field reveal pattern. This session merges these patterns
into a coherent, implementable security and admin contract for Phase 03+.

Note: CFI-SEC-002 (API key encryption) and CFI-ADM-002 (field-level encryption in admin) were
already fully resolved in session 02 (`encrypted_storage_spec.md`). This session references those
decisions and focuses on the remaining gaps.

---

## 2. Objectives

1. Define the unified sanitization library contract (`class-sanitizer.php`) with domain-specific
   sanitizers, a validator registry, and compliance rules (resolves CFI-SEC-001).
2. Produce an auth enforcement specification covering every plugin endpoint with defined nonce
   strategy, capability requirements, and the dual-check pattern (nonce + capability on every call).
3. Specify the 5-layer moderation pipeline interface (honeypot, rate limit, spam signatures, banned
   words, AI moderation), including configuration, per-layer enable/disable, and bypass rules.
4. Define the admin settings multi-page decomposition architecture consuming the 64 options defined
   in session 02, with WP Settings API integration, auto-save, and per-page controller contracts
   (resolves CFI-ADM-001, CFI-ADM-003, CFI-ADM-004, CFI-ADM-005).
5. Design the diagnostics dashboard panel registry pattern (hosting pattern from AXI/MXC), SSE
   streaming test contract, and operational controls contract (resolves CFI-ADM-003, CFI-ADM-004).
6. Specify privacy controls configuration (usage limits, IP privacy, data retention configuration)
   and the data erasure/export mechanism (resolves CFI-SEC-003).
7. Design the audit trail interface: what events are logged, log schema, retention policy, and
   queryability interface (resolves CFI-SEC-004).
8. Document how the existing `lib/structured-logger.php` integrates with each subsystem.

---

## 3. Prerequisites

### Required Sessions

- [x] `phase02-session01-module-map-directory-architecture-and-bootstrap` -- Module map with
  `includes/security/` layout (6 files), `includes/admin/` layout (3 + 6 page controllers),
  naming conventions, dependency graph (security is depended on by all other modules; admin is
  a leaf module that depends on all others)
- [x] `phase02-session02-data-layer-schema-design-and-migration-framework` -- 64 options schema,
  encrypted_storage_spec (CFI-SEC-002 resolved), data_retention_policy, audit log storage in
  `wp_wpaic_messages` and options (no separate audit table -- structured logger handles log files)
- [x] `phase02-session03-provider-orchestration-and-model-registry-contracts` -- Provider interface,
  cost accounting interface (inputs to admin diagnostics panels and security audit events)
- [x] `phase02-session04-chat-runtime-session-model-and-frontend-contracts` -- Endpoint list,
  nonce strategy per endpoint, session auth model, AJAX/REST auth patterns
- [x] `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts` -- Knowledge admin
  endpoints (inputs to auth enforcement matrix), security policy injection in context assembly

### Required Knowledge

- Phase 01 DOM-SECURITY winner summary: SRC-AXI wins all 5 sub-categories (5/5)
  - DEC-SEC-001: AXI domain sanitizers (adopt-as-is)
  - DEC-SEC-002: AXI AES-256-GCM encryption (adopt-as-is, resolved in session 02)
  - DEC-SEC-003: AXI 5-layer moderation pipeline (adopt-as-is)
  - DEC-SEC-004: AXI+MXC privacy/usage limits (merge: AXI limits + MXC daily reset logic)
  - DEC-SEC-005: AXI structured audit logging (adopt-as-is, integrate with lib/)
- Phase 01 DOM-ADMIN winner summary: mixed MXC+AXI
  - DEC-ADM-001: Decompose MXC monolith, AXI per-page pattern (<600 LOC each)
  - DEC-ADM-002: AXI field-level encrypt/sanitize (resolved in session 02)
  - DEC-ADM-003: AXI+MXC diagnostics dashboard, SSE streaming test (hosting pattern)
  - DEC-ADM-004: MXC operational controls breadth with AXI structure
  - DEC-ADM-005: BAA+AXI vanilla JS (no jQuery), auto-save from MXC
- Carry-forward items: CFI-SEC-001, CFI-SEC-003, CFI-SEC-004, CFI-ADM-001, CFI-ADM-003,
  CFI-ADM-004, CFI-ADM-005 (all open -- resolved here)
- CFI-SEC-002 and CFI-ADM-002: already resolved in session 02 (referenced, not re-derived)

---

## 4. Scope

### In Scope (MVP)

- Unified sanitization library contract (domain-specific sanitizers, validator registry, compliance
  rules) -- resolves CFI-SEC-001
- Auth enforcement matrix (every endpoint, nonce strategy, capability requirement, dual-check rule)
- 5-layer moderation pipeline interface (honeypot through AI moderation), configuration, bypass
- Admin settings multi-page decomposition: page registry, per-page controller contract, tab layout,
  WP Settings API integration, field definitions, conditional field reveal (BAA pattern)
- Admin UX vanilla JS contract: instance model, auto-save, model selector, i18n integration,
  toast system -- resolves CFI-ADM-005
- Diagnostics dashboard: panel registry, data providers, SSE streaming test, analytics panel,
  log viewer panel -- resolves CFI-ADM-003
- Operational controls contract: embed origins, GDPR inline gating, multi-bot management,
  rate limit configuration -- resolves CFI-ADM-004
- Privacy controls: usage limits (daily, lifetime), IP privacy mode, retention TTL configuration,
  data export endpoint, erasure endpoint -- resolves CFI-SEC-003
- Audit trail: event taxonomy, log entry schema, retention policy, query interface -- resolves
  CFI-SEC-004
- Structured logger integration plan: subsystem tags, log levels, rotation hooks

### Out of Scope

- Full GDPR compliance implementation -- Phase 09
- External audit tooling integration (Splunk, CloudWatch) -- Phase 09
- Performance profiling -- Phase 09
- Real-time alerting webhooks -- Phase 09

---

## 5. Technical Approach

### Security Module Architecture

Security lives in `includes/security/` as six leaf files. All other modules depend on security
but security depends only on `lib/` utilities and WordPress core. This dependency direction
is enforced: no security file imports from `includes/provider/`, `includes/runtime/`, or
`includes/admin/`.

```
includes/security/
  class-sanitizer.php       -- Unified sanitizer registry (CFI-SEC-001)
  class-auth-enforcer.php   -- Nonce + capability dual-check
  class-moderator.php       -- 5-layer moderation pipeline
  class-encryption.php      -- AES-256-GCM (session 02, CFI-SEC-002)
  class-privacy-manager.php -- Usage limits, IP privacy, GDPR hooks (CFI-SEC-003)
  class-audit-logger.php    -- Structured event logging (CFI-SEC-004)
```

### Admin Module Architecture

Admin follows the decomposed per-page pattern (CFI-ADM-001). Each page is under 600 LOC. The
admin router registers all WP admin menus and dispatches to per-page controllers. The settings
manager (session 02) provides the WP Settings API integration layer. Admin JS is vanilla (no
jQuery) following the BAA+AXI decision (CFI-ADM-005).

---

## 6. Deliverables

### Files Created

| File | Purpose | CFI Resolved |
|------|---------|-------------|
| `deliverables/sanitization_library_spec.md` | Sanitizer registry, domain rules, compliance | CFI-SEC-001 |
| `deliverables/auth_enforcement_spec.md` | Per-endpoint auth matrix, nonce strategy | -- |
| `deliverables/moderation_pipeline_spec.md` | 5-layer pipeline interface, config | -- |
| `deliverables/admin_settings_architecture.md` | Page decomposition, WP Settings API, tabs | CFI-ADM-001, CFI-ADM-002 |
| `deliverables/admin_ux_js_contract.md` | Vanilla JS model, auto-save, toast, i18n | CFI-ADM-005 |
| `deliverables/diagnostics_dashboard_spec.md` | Panel registry, SSE test, log viewer | CFI-ADM-003 |
| `deliverables/operational_controls_contract.md` | Embed origins, GDPR, rate limits, multi-bot | CFI-ADM-004 |
| `deliverables/privacy_audit_trail_spec.md` | Privacy controls, data export, audit trail | CFI-SEC-003, CFI-SEC-004 |
| `deliverables/structured_logging_integration.md` | Logger integration plan, subsystem tagging | -- |
| `implementation-notes.md` | Decisions, CFI resolution table, phase 07 inputs | -- |

---

## 7. Success Criteria

### Functional Requirements

- [x] Sanitization library covers all input types: user message, API key, integer settings, bool,
  slug, URL, HTML (restricted), system prompt text
- [x] Auth enforcement matrix has no gaps -- every endpoint (AJAX + REST) has defined auth
- [x] Moderation pipeline is layer-by-layer configurable; each layer can be disabled independently
- [x] Admin decomposition eliminates the monolithic class pattern (600 LOC limit per file)
- [x] Diagnostics dashboard supports panel registration from external modules (hook-based)
- [x] Structured logger integration plan maps all subsystems to tags + levels
- [x] CFI-SEC-001, CFI-SEC-003, CFI-SEC-004, CFI-ADM-001, CFI-ADM-003, CFI-ADM-004,
  CFI-ADM-005 resolved in deliverables (CFI-SEC-002 and CFI-ADM-002 resolved in session 02)

### Quality Gates

- [x] All files ASCII-encoded, Unix LF
- [x] Consistent `wpaic_` prefix for all hooks, options, endpoint names
- [x] File paths match session 01 module map (`includes/security/`, `includes/admin/`)
- [x] Every deliverable cites source anchors from >= 2 reference plugins

---

## 8. Dependencies

### Reference Anchors

#### Security Module
- `axiachat-ai/includes/moderation.php` -- 5-layer moderation (honeypot, rate-limit, spam, banned, AI)
- `axiachat-ai/includes/sanitize-helpers.php` -- Domain sanitizers (message, slug, int, bool, URL)
- `axiachat-ai/includes/settings/encrypt-decrypt.php` -- AES-256-GCM pattern (session 02)
- `axiachat-ai/includes/logs.php` -- Structured logging pattern
- `axiachat-ai/includes/usage.php` -- Usage tracking, daily limits, memory cap
- `mxchat-basic/admin/class-ajax-handler.php` -- AJAX security patterns, nonce verification
- `mxchat-basic/mxchat-basic.php` -- Capability checks (`manage_options`)
- `baachal/baachal.php` -- Security nonce patterns, honeypot field

#### Admin Module
- `axiachat-ai/includes/class-aichat-admin.php` -- Per-section admin structure, Settings API hooks
- `mxchat-basic/includes/class-mxchat-admin.php` -- Multi-page admin (anti-pattern -- decompose)
- `mxchat-basic/js/mxchat-admin.js` -- Auto-save pattern (800ms success state)
- `baachal/admin/settings-page.php` -- Tab-based layout, conditional field reveal
- `baachal/assets/admin.js` -- Conditional field reveal on provider change

### Other Sessions

- **Depends on**: sessions 01-05 (all subsystem contracts established)
- **Depended by**: `phase02-session07` (migration strategy consumes auth patterns, admin page list,
  sensitive field inventory)

---

## Next Steps

Session complete. See `implementation-notes.md` for CFI resolution table and phase 07 inputs.
