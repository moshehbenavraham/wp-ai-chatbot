# Task Checklist

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Total Tasks**: 20
**Estimated Duration**: 3-4 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0201]` = Session reference (phase 02, session 01)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 6 | 6 | 0 |
| Implementation | 8 | 8 | 0 |
| Testing | 4 | 4 | 0 |
| **Total** | **20** | **20** | **0** |

---

## Setup (2 tasks)

Initial configuration and environment preparation.

- [x] T001 [S0201] Verify prerequisites met: confirm Phase 01 handoff bundle, v1 scope boundary, and carry-forward register are accessible and reviewed (`specs/phase01-session06-.../phase02_handoff_bundle.md`, `v1_scope_boundary.md`, `carry_forward_register_final.md`)
  - Ref: `axiachat-ai/axiachat-ai.php`, `mxchat-basic/mxchat-basic.php`, `baachal/baachal.php`
- [x] T002 [S0201] Create deliverable file stubs for all 7 architecture documents in session directory (`specs/phase02-session01-.../`)
  - Ref: `axiachat-ai/includes/` (directory structure pattern), `mxchat-basic/includes/` (class-per-concern pattern), `baachal/includes/` (minimal structure pattern)

---

## Foundation (6 tasks)

Core architecture design documents.

- [x] T003 [S0201] [P] Define plugin root directory tree with module-per-domain layout, file annotations, and rationale for each top-level directory (`directory_structure.md`)
  - Ref: `axiachat-ai/includes/` (38+ files, subdirs: interfaces/, providers/, settings/, add-ons/), `mxchat-basic/includes/` (17 class files), `baachal/includes/` (single file), `baachal/admin/tabs/` (tab-based admin)
- [x] T004 [S0201] [P] Define naming conventions for files, classes, functions, hooks, filters, options, and transients with prefix rules and examples (`naming_conventions.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (AICHAT_ constants, aichat_ functions), `mxchat-basic/mxchat-basic.php` (mxchat_ prefix), `baachal/baachal.php` (BAACHAL_ constants, baachal_ prefix)
- [x] T005 [S0201] Define module boundary ownership rules: which code belongs in each domain directory, dependency direction, and forbidden cross-module imports (`module_boundaries.md`)
  - Ref: `axiachat-ai/includes/interfaces/interface-aichat-provider.php` (interface boundary), `mxchat-basic/includes/class-mxchat-integrator.php` (provider dispatch), Phase 01 cross-domain resolution patterns (6 patterns)
- [x] T006 [S0201] Create module dependency graph in visual ASCII art and textual description, verifying no circular dependencies exist (`module_dependency_graph.md`)
  - Ref: Phase 02 handoff bundle cross-domain integration points table (12 integration points), `axiachat-ai/includes/class-aichat-provider-registry.php` (registry pattern)
- [x] T007 [S0201] [P] Define constants and global configuration architecture: plugin version, paths, URLs, feature flags, debug mode (`directory_structure.md` constants section)
  - Ref: `axiachat-ai/axiachat-ai.php` (AICHAT_VERSION, AICHAT_PLUGIN_DIR, AICHAT_PLUGIN_URL, AICHAT_DEBUG), `baachal/baachal.php` (BAACHAL_VERSION, BAACHAL_PLUGIN_URL, BAACHAL_PLUGIN_PATH)
- [x] T008 [S0201] Define autoloading strategy with PSR-4 namespace mapping, fallback for PHP 7.4 environments without Composer, and class-to-file resolution rules (`autoloading_design.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (file-level require_once includes), `mxchat-basic/mxchat-basic.php` (class-mxchat-*.php naming pattern), `axiachat-ai/includes/interfaces/` (interface file naming)

---

## Implementation (8 tasks)

Detailed architecture specifications.

- [x] T009 [S0201] Design bootstrap sequence with hook registration order, initialization phases, and conditional loading paths for admin vs frontend vs REST (`bootstrap_sequence.md`)
  - Ref: `baachal/baachal.php` (filterable hook array with foreach registration), `axiachat-ai/axiachat-ai.php` (sequential includes, Freemius init), `mxchat-basic/mxchat-basic.php` (text domain, cache exclusions, conditional loading)
- [x] T010 [S0201] Map all 26 MVP capabilities to specific module homes with file path targets and source anchor cross-references (`module_boundaries.md` capability mapping section)
  - Ref: `v1_scope_boundary.md` (26 in-scope capabilities), `phase02_handoff_bundle.md` (per-domain winner summaries)
- [x] T011 [S0201] Map all 12 deferred capabilities to extension point stubs with target module locations (`module_boundaries.md` deferred section)
  - Ref: `v1_scope_boundary.md` (12 deferred capabilities), `carry_forward_register_final.md` (Tier 3-4 items)
- [x] T012 [S0201] [P] Design extension point architecture for Tools domain: tool registration hooks, execution lifecycle hooks, macro registry stub (`extension_points.md` tools section)
  - Ref: `axiachat-ai/includes/add-ons/ai-tools/api.php` (tool registration with JSON Schema), `axiachat-ai/includes/add-ons/ai-tools/macro-api.php` (macro registry), `mxchat-basic/includes/admin-actions-page.php` (action admin UI)
- [x] T013 [S0201] [P] Design extension point architecture for Integrations domain: MCP client hooks, webhook event hooks, extension model registration (`extension_points.md` integrations section)
  - Ref: `axiachat-ai/includes/add-ons/mcp/class-mcp-client-manager.php` (MCP JSON-RPC sessions), `axiachat-ai/includes/add-ons/custom-webhooks/class-webhook-executor.php` (webhook executor), `mxchat-basic/includes/class-mxchat-addons.php` (marketplace UI)
- [x] T014 [S0201] [P] Design extension point architecture for WooCommerce domain: product context hooks, cart operation hooks, HPOS compatibility declaration (`extension_points.md` WooCommerce section)
  - Ref: `baachal/baachal.php` (product context injection, HPOS FeaturesUtil::declare_compatibility()), `mxchat-basic/includes/class-mxchat-woocommerce.php` (AJAX cart operations), `baachal/admin/tabs/woocommerce.php` (WC admin settings)
- [x] T015 [S0201] Design admin decomposition architecture: per-page controllers, tab registration hooks, settings API integration, max 600 LOC per controller (CFI-ADM-001) (`module_boundaries.md` admin section)
  - Ref: `mxchat-basic/includes/class-mxchat-admin.php` (monolithic 9036-line anti-pattern), `mxchat-basic/admin/` (page templates), `baachal/admin/tabs/` (tab-based settings), `axiachat-ai/includes/settings/` (settings sub-modules)
- [x] T016 [S0201] Design combined plugin lifecycle with extension hooks for activate/deactivate/uninstall and migration runner entry points (CFI-MIG-001) (`bootstrap_sequence.md` lifecycle section)
  - Ref: `baachal/baachal.php` (three-hook coverage, capability checks), `baachal/uninstall.php` (data preservation flag), `mxchat-basic/mxchat-basic.php` (version-conditional migrations, 10+ migrations)

---

## Testing (4 tasks)

Verification and quality assurance.

- [x] T017 [S0201] Validate module dependency graph has no circular dependencies and all cross-domain integration points from Phase 01 are represented
  - Ref: `phase02_handoff_bundle.md` (12 cross-domain integration points), `carry_forward_register_final.md` (2 circular dependency pairs: CFI-PRO-001/CFI-RUN-003, CFI-RUN-004/CFI-ADM-005)
- [x] T018 [S0201] Verify all 38 capabilities (26 MVP + 12 deferred) have assigned module homes and no gaps exist
  - Ref: `v1_scope_boundary.md` (complete capability listing with decision IDs)
- [x] T019 [S0201] Cross-reference naming conventions and file structure with CONVENTIONS.md; verify carry-forward items CFI-ADM-001 and CFI-MIG-001 are addressed
  - Ref: `.spec_system/CONVENTIONS.md` (naming, files and structure, WordPress integration sections)
- [x] T020 [S0201] Verify all deliverable files are ASCII-encoded with Unix LF line endings
  - Ref: N/A (quality gate -- no source anchor required)

---

## Completion Checklist

Before marking session complete:

- [x] All tasks marked `[x]`
- [x] All 7 architecture documents created and internally consistent
- [x] Module dependency graph verified cycle-free
- [x] All 38 capabilities mapped to module homes
- [x] All files ASCII-encoded
- [x] implementation-notes.md updated
- [x] Ready for `/validate`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
