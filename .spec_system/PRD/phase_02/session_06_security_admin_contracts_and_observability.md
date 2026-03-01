# Session 06: Security, Admin Contracts, and Observability

**Session ID**: `phase02-session06-security-admin-contracts-and-observability`
**Status**: Not Started
**Estimated Tasks**: ~18
**Estimated Duration**: 3-4 hours

---

## Objective

Design the security subsystem contracts (sanitization, auth, moderation, privacy), admin settings architecture (decomposed from monolith), diagnostics dashboard, structured logging integration, and audit trail interfaces.

---

## Scope

### In Scope (MVP)
- Unified sanitization library contracts (domain-specific sanitizers from AXI winner)
- Auth enforcement patterns (nonce verification, capability checks, per-endpoint rules)
- Moderation pipeline interface (5-layer from AXI: honeypot, rate limit, spam signatures, banned words, AI moderation)
- API key encryption interface and storage (AES-256-GCM from AXI/MXC patterns)
- Admin settings architecture (multi-page decomposition from MXC winner, resolving CFI-ADM-001)
- Diagnostics dashboard contracts (SSE streaming diagnostics, panel registration from hosting pattern)
- Structured logging integration (connecting existing `lib/structured-logger.php` to subsystems)
- Privacy controls contract (data export, erasure, retention configuration)
- Audit trail interface (what gets logged, where, retention, queryability)

### Out of Scope
- Full GDPR compliance implementation (Phase 09)
- Performance profiling and optimization (Phase 09)
- External audit tooling integration (Phase 09)

---

## Prerequisites

- [ ] Session 01 completed (module map with security and admin module locations)
- [ ] Session 02 completed (encrypted storage specification, audit log storage)
- [ ] Sessions 03-05 completed (subsystem contracts define what needs securing)
- [ ] Phase 01 DOM-SECURITY winner summary reviewed (SRC-AXI 5/5)
- [ ] Phase 01 DOM-ADMIN winner summary reviewed (mixed MXC+AXI)
- [ ] Carry-forward items reviewed: CFI-SEC-001 (sanitization), CFI-SEC-002 (encryption), CFI-SEC-003 (privacy), CFI-SEC-004 (audit logging), CFI-ADM-001 through CFI-ADM-005

---

## Deliverables

1. Unified sanitization library specification (sanitizer registry, domain rules, validation contracts)
2. Auth enforcement specification (per-endpoint auth matrix, nonce strategy, capability mapping)
3. Moderation pipeline specification (5 layers, configuration interface, bypass rules)
4. API key encryption specification (encryption method, key derivation, rotation support)
5. Admin settings architecture (page decomposition, tab structure, Settings API integration)
6. Diagnostics dashboard specification (panel registry, data sources, SSE streaming contract)
7. Structured logging integration specification (log levels, structured fields, rotation, subsystem tagging)
8. Privacy and audit trail specification (data inventory, export/erasure endpoints, audit log schema)

---

## Success Criteria

- [ ] Sanitization library covers all input types across runtime, admin, and RAG surfaces
- [ ] Auth enforcement matrix has no gaps (every endpoint has defined auth requirements)
- [ ] Moderation pipeline is configurable per-deployment (layers can be enabled/disabled)
- [ ] Admin decomposition eliminates the monolithic class pattern identified in Phase 01
- [ ] Diagnostics dashboard supports panel registration from other modules (hosting pattern)
- [ ] Structured logger integration plan connects to existing `lib/structured-logger.php`
- [ ] Carry-forward items CFI-SEC-001 through CFI-SEC-004 and CFI-ADM-001 through CFI-ADM-005 are addressed
