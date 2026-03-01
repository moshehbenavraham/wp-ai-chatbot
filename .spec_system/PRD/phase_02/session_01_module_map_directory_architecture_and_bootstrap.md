# Session 01: Module Map, Directory Architecture, and Bootstrap Design

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Status**: Not Started
**Estimated Tasks**: ~20
**Estimated Duration**: 3-4 hours

---

## Objective

Define the complete plugin directory structure, module boundaries, dependency rules, autoloading strategy, and bootstrap sequence that will house all 26 MVP capabilities and accommodate the 12 deferred capabilities.

---

## Scope

### In Scope (MVP)
- Plugin root directory structure with module-per-domain layout
- Module boundary definitions (which code belongs where, dependency direction rules)
- Module dependency graph with allowed/forbidden cross-module imports
- Autoloading strategy (PSR-4 namespacing vs WordPress file conventions)
- Bootstrap sequence design (hook registration order, initialization phases)
- Constants and global configuration architecture
- Naming conventions for files, classes, functions, hooks, and filters
- Extension point stubs for deferred capabilities (tools, integrations, WooCommerce)

### Out of Scope
- Detailed interface definitions (sessions 03-06)
- Database schema design (session 02)
- Implementation code (Phase 03+)

---

## Prerequisites

- [ ] Phase 01 handoff bundle read (`phase02_handoff_bundle.md`)
- [ ] Carry-forward register reviewed (`carry_forward_register_final.md`, Tier 1 items)
- [ ] v1 scope boundary reviewed (`v1_scope_boundary.md`)

---

## Deliverables

1. Plugin directory structure specification (complete tree with annotations)
2. Module boundary document (ownership, dependency direction, forbidden imports)
3. Module dependency graph (visual and textual)
4. Autoloading and namespace design document
5. Bootstrap sequence specification (ordered hook registration, init phases)
6. Naming convention guide (files, classes, functions, hooks, options, transients)
7. Extension point registry design for deferred capabilities

---

## Success Criteria

- [ ] Every MVP capability from v1 scope has a clear module home
- [ ] Module dependency graph has no circular dependencies
- [ ] Bootstrap sequence is deterministic and documented
- [ ] Naming conventions are unambiguous and consistent with WordPress ecosystem
- [ ] Extension points exist for tools, integrations, and WooCommerce without coupling to MVP modules
- [ ] Carry-forward items CFI-ADM-001 (admin decomposition) and CFI-MIG-001 (lifecycle hooks) are addressed
