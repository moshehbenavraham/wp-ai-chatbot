# Session Specification

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Session Overview

Session 07 is the Phase 02 closing session. It defines migration strategies from each source
plugin, the hook and filter architecture for extensibility, the extension point registry for
deferred capabilities (tools, integrations, WooCommerce), reconciles all 33 carry-forward items,
validates Phase 02 exit criteria, and produces the Phase 03 handoff bundle.

Phase 01 established the migration domain winners: Baachal's uninstall lifecycle pattern wins
for clean uninstall; MxChat's migration registry concept wins for versioned option transforms;
AxiaChat provides the most complex migration scenario (per-bot table data, encrypted keys,
multiple option keys). Session 02 established the `class-migration-registry.php` and
`class-option-migrator.php` module locations. This session fills in the migration strategies
and resolves remaining CFI-MIG items.

---

## 2. Objectives

1. Produce per-source migration strategies for axiachat-ai, mxchat-basic, and baachal -- option
   key mappings, value transforms, data compatibility notes, and migration ordering.
2. Define the generic option migration framework design (`class-option-migrator.php` contract)
   resolving CFI-MIG-003.
3. Design the hook and filter architecture: naming conventions, priority rules, action vs filter
   distinction, documentation contract for all registered hooks.
4. Design the extension point registry for deferred capabilities (tools, integrations, WooCommerce)
   that allows Phase 07+ to add these without modifying MVP code.
5. Reconcile all 33 carry-forward items: confirmed-resolved, re-deferred with rationale, or
   identified as gap requiring remediation.
6. Validate Phase 02 exit criteria (all module contracts fixed and implementation-ready).
7. Produce the Phase 03 handoff bundle with prioritized implementation order, open decisions,
   and per-session quick-reference index.

---

## 3. Prerequisites

### Required Sessions

- [x] All sessions 01-06 complete (all module contracts, schemas, and security/admin contracts)
- [x] Session 02 migration_runner_framework.md -- migration registry pattern, version tracking,
  `wpaic_db_version` option, `class-migration-registry.php` location
- [x] Session 01 module_dependency_graph.md -- dependency order (security < provider < rag < runtime
  < admin) -- required for Phase 03 implementation ordering

### Required Knowledge

- Phase 01 DOM-MIGRATION winner summary:
  - DEC-MIG-001: BAA lifecycle (clean uninstall) + AXI multi-table
  - DEC-MIG-002: MXC migration registry (versioned callbacks)
  - DEC-MIG-003: Generic option migration framework
- Carry-forward register from session 01-06: all 33 items, current status

---

## 4. Scope

### In Scope

- axiachat-ai, mxchat-basic, baachal migration strategies (option maps, value transforms, ordering)
- Generic option migration framework (`class-option-migrator.php` contract)
- Hook and filter architecture (naming convention, registration pattern, documentation standard)
- Extension point registry for deferred domains (tools, integrations, WooCommerce)
- Carry-forward reconciliation (all 33 items)
- Phase 02 exit validation (completeness check)
- Phase 03 handoff bundle

### Out of Scope

- Actual migration code implementation -- Phase 10
- Deferred capability implementation details -- Phases 07-08
- Phase 03 session-level task planning -- Phase 03 planning session

---

## 5. Technical Approach

### Migration Architecture

Migration proceeds in three passes per source plugin:
1. **Option migration**: key-rename + value transform (via `class-option-migrator.php`)
2. **Data migration**: row copy from source tables to `wp_wpaic_*` tables (if applicable)
3. **Encryption pass**: encrypt plaintext keys from source (via `Encryption::encrypt()`)

The migration runner (`class-migration-registry.php`) coordinates passes via versioned
callbacks. Each migration step is idempotent (safe to re-run). Dry-run mode available.

### Extension Point Pattern

Deferred domains (tools, integrations, WooCommerce) expose entry points via:
1. Registration hooks: `do_action('wpaic_register_tools', $registry)`
2. Execution filters: `apply_filters('wpaic_tool_execute_{slug}', $result, $params)`
3. Empty stub directories: `includes/tools/`, `includes/integrations/`, `includes/woocommerce/`

---

## 6. Deliverables

| File | Purpose |
|------|---------|
| `deliverables/axiachat_migration_strategy.md` | AXI option maps, data transforms, encryption migration |
| `deliverables/mxchat_migration_strategy.md` | MXC option maps (blob decomposition), data transforms |
| `deliverables/baachal_migration_strategy.md` | BAA option maps, simple data migration |
| `deliverables/option_migration_framework.md` | class-option-migrator.php contract, CFI-MIG-003 |
| `deliverables/hook_filter_architecture.md` | Naming conventions, priority rules, hook registry |
| `deliverables/extension_point_registry.md` | Deferred domain extension points |
| `deliverables/carry_forward_reconciliation.md` | All 33 CFI items: resolved/re-deferred/gap |
| `deliverables/phase02_exit_validation.md` | Exit criteria check, readiness assessment |
| `deliverables/phase03_handoff_bundle.md` | Implementation order, open decisions, session index |
| `implementation-notes.md` | Session summary, lessons learned |

---

## 7. Dependencies

- **Depends on**: All sessions 01-06
- **Depended by**: Phase 03 implementation sessions (handoff bundle is the input)

---

## Next Steps

Session complete. Phase 02 complete. Proceed to Phase 03 planning.
