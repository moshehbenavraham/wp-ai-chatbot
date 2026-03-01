# Session 02: Data Layer, Schema Design, and Migration Framework

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Not Started
**Estimated Tasks**: ~20
**Estimated Duration**: 3-4 hours

---

## Objective

Design the complete data layer for the plugin including database table schemas, options naming conventions, transient usage patterns, the migration runner framework, and data retention/cleanup policies.

---

## Scope

### In Scope (MVP)
- Custom database table designs (chat sessions, messages, knowledge chunks, ingestion queue, usage/cost tracking)
- WordPress options schema with prefix conventions and field registry
- Transient usage patterns (caching, rate limiting, temporary state)
- Migration runner framework architecture (versioned callbacks, rollback support)
- Version management and upgrade path design
- Data retention and cleanup policies (chat history, logs, indexes)
- Uninstall behavior contracts (what gets deleted, what gets preserved, user choice)
- Storage contracts for encrypted fields (API keys, sensitive config)

### Out of Scope
- Specific interface definitions for subsystems (sessions 03-06)
- Actual SQL DDL statements (Phase 03 implementation)
- Migration logic from source plugins (session 07)

---

## Prerequisites

- [ ] Session 01 completed (module map and directory structure)
- [ ] Phase 01 handoff bundle reviewed (storage patterns from all 3 sources)
- [ ] Carry-forward items reviewed: CFI-MIG-002 (migration registry), CFI-SEC-002 (API key encryption), CFI-RUN-002 (session storage)

---

## Deliverables

1. Database table schema specifications (all custom tables with column definitions, indexes, relationships)
2. Options schema document (prefix conventions, field registry, default values)
3. Transient usage guide (naming, TTLs, cleanup triggers)
4. Migration runner framework specification (version detection, callback registry, rollback protocol)
5. Data retention policy document (per-data-type retention rules, cleanup schedules)
6. Uninstall behavior contract (staged cleanup, user confirmation requirements)
7. Encrypted storage specification (which fields, encryption method, key management)

---

## Success Criteria

- [ ] Every data entity from the 26 MVP capabilities has a defined storage location
- [ ] Migration runner supports forward-only and rollback-aware migrations
- [ ] Options naming is collision-free with standard WordPress prefixing
- [ ] Uninstall path is safe (no orphaned data, no accidental deletion of user content)
- [ ] Carry-forward items CFI-MIG-002, CFI-SEC-002, and CFI-RUN-002 are addressed
- [ ] Schema design supports future extension (tools, WooCommerce) without breaking migrations
