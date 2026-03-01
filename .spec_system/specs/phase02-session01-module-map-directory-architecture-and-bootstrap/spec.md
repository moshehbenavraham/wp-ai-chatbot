# Session Specification

**Session ID**: `phase02-session01-module-map-directory-architecture-and-bootstrap`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session defines the complete plugin directory structure, module boundaries, dependency rules, autoloading strategy, bootstrap sequence, naming conventions, and extension point architecture for the unified WP AI Chatbot plugin. It is the foundational architecture session for Phase 02 -- every subsequent session in this phase depends on the module map and conventions established here.

The plugin must house all 26 MVP capabilities (across Provider, Runtime, Migration, RAG, Admin, and Security domains) and provide clean extension points for the 12 deferred capabilities (Tools, Integrations, WooCommerce). The architecture draws from proven patterns in all three reference plugins: AxiaChat's interface-driven provider abstraction, MxChat's class-per-concern organization, and Baachal's centralized hook registration.

This session produces design documents only -- no implementation code. All deliverables are architecture specifications that Phase 02 sessions 02-07 will consume, and Phase 03 will implement.

---

## 2. Objectives

1. Define a plugin directory tree that maps every domain to a clear module home with single-responsibility files.
2. Establish module dependency rules that prevent circular imports and enforce unidirectional data flow.
3. Design an autoloading strategy compatible with PHP >=7.4 and WordPress plugin conventions.
4. Specify a deterministic bootstrap sequence covering hook registration order, initialization phases, and conditional loading.
5. Codify naming conventions for all PHP, JS, hook, option, and transient identifiers.
6. Design extension point architecture for deferred domains (Tools, Integrations, WooCommerce) that does not couple to MVP modules.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase00-session01-reference-inventory-baseline` - Plugin inventories and file indexes
- [x] `phase00-session02-domain-taxonomy-feature-schema` - 9-domain taxonomy with canonical IDs
- [x] `phase01-session06-winner-consolidation-and-phase-handoff` - Winner summaries, handoff bundle, carry-forward register, v1 scope boundary

### Required Tools/Knowledge
- Phase 02 handoff bundle (`phase02_handoff_bundle.md`) -- per-domain winner patterns and cross-domain resolution patterns
- v1 scope boundary (`v1_scope_boundary.md`) -- 26 MVP + 12 deferred capabilities
- Carry-forward register (`carry_forward_register_final.md`) -- 33 items, Tier 1 items especially
- Reference plugin source structures (read-only)

### Environment Requirements
- Read access to `axiachat-ai/`, `mxchat-basic/`, `baachal/` reference directories
- No build tools or runtime environments needed (documentation session)

---

## 4. Scope

### In Scope (MVP)
- Plugin root directory structure with module-per-domain layout -- design document covering all file/folder locations
- Module boundary definitions with ownership and dependency direction rules -- formal boundary doc
- Module dependency graph with allowed/forbidden cross-module imports -- visual ASCII + textual
- Autoloading strategy (PSR-4 namespacing vs WordPress file conventions) -- design decision + mapping
- Bootstrap sequence design (hook registration order, initialization phases, conditional loading) -- specification
- Constants and global configuration architecture -- centralized constant definitions
- Naming conventions for files, classes, functions, hooks, filters, options, and transients -- guide
- Extension point stubs for deferred capabilities (Tools, Integrations, WooCommerce) -- architecture

### Out of Scope (Deferred)
- Database schema design -- *Reason: session 02 scope*
- Detailed PHP interface definitions for subsystems -- *Reason: sessions 03-06 scope*
- Implementation code -- *Reason: Phase 03+ scope*
- jQuery vs vanilla JS final decision for admin -- *Reason: session 04/06 scope (CFI-RUN-004/CFI-ADM-005)*

---

## 5. Technical Approach

### Architecture
The plugin follows a module-per-domain layout where each domain (provider, runtime, rag, admin, security, migration) owns a directory under `includes/`. Cross-domain communication follows the 6 resolution patterns established in Phase 01: Policy-vs-Execution, Registry Centralization, Natural Interface Boundary, Layered Ownership, Hosting Pattern, and Dual Exposure.

### Design Patterns
- **Module-per-domain**: Each domain maps to one directory with clear ownership boundaries
- **Dependency inversion**: Modules depend on interfaces, not concrete implementations
- **Centralized bootstrap**: Single entry point wires all hooks, inspired by Baachal's filterable hook array
- **Extension registry**: Deferred domains register via hooks without direct coupling to MVP modules

### Technology Stack
- PHP >=7.4 (WordPress plugin)
- WordPress Plugin API (hooks, filters, options, transients)
- No external dependencies for architecture (documentation deliverables only)

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `specs/phase02-session01-.../directory_structure.md` | Complete plugin directory tree with annotations and constants | ~200 |
| `specs/phase02-session01-.../module_boundaries.md` | Module ownership, dependency rules, capability mapping | ~250 |
| `specs/phase02-session01-.../module_dependency_graph.md` | Visual ASCII + textual dependency graph | ~100 |
| `specs/phase02-session01-.../autoloading_design.md` | PSR-4 namespace mapping and loading strategy | ~120 |
| `specs/phase02-session01-.../bootstrap_sequence.md` | Hook registration order, init phases, conditional loading | ~150 |
| `specs/phase02-session01-.../naming_conventions.md` | File, class, function, hook, option, transient naming rules | ~150 |
| `specs/phase02-session01-.../extension_points.md` | Extension architecture for Tools, Integrations, WooCommerce | ~180 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Every MVP capability from v1 scope (26 items) has a clear module home
- [ ] Module dependency graph has no circular dependencies
- [ ] Bootstrap sequence is deterministic and documented with hook priority ordering
- [ ] Naming conventions are unambiguous and consistent with WordPress ecosystem
- [ ] Extension points exist for Tools, Integrations, and WooCommerce without coupling to MVP modules
- [ ] Carry-forward items CFI-ADM-001 (admin decomposition) and CFI-MIG-001 (lifecycle hooks) are addressed

### Testing Requirements
- [ ] Dependency graph validated for no cycles
- [ ] Capability-to-module mapping covers all 38 capabilities (26 MVP + 12 deferred)
- [ ] Naming conventions cross-referenced with CONVENTIONS.md

### Non-Functional Requirements
- [ ] All deliverables follow ASCII encoding with Unix LF line endings
- [ ] Documents are self-contained enough for Phase 02 sessions 02-07 to proceed without re-reading full source repos

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Code follows project conventions

---

## 8. Implementation Notes

### Key Considerations
- The plugin prefix `wpaic_` (or similar) must be established for all globally scoped identifiers
- PSR-4 autoloading must be compatible with PHP 7.4 without Composer autoload in production (WordPress convention)
- Bootstrap must support conditional loading (e.g., admin-only files, WooCommerce detection)
- Module boundaries must align with the 6 cross-domain resolution patterns from Phase 01

### Potential Challenges
- **Autoloading without Composer**: WordPress plugins typically use manual includes or a custom autoloader; PSR-4 namespacing with a lightweight autoloader is the target
- **Admin decomposition**: The monolithic admin class pattern (MxChat 9036-line class) must be prevented by design via per-page controllers (CFI-ADM-001)
- **Extension point coupling**: Deferred domains must register via hooks, not direct function calls, to avoid creating dependencies on unimplemented code

### Relevant Considerations
- **Anchor-first extraction** (Lesson Learned): All architecture decisions reference specific source files from the three reference plugins
- **Canonical vocabulary with alias tables** (Lesson Learned): Module names use the 9-domain canonical IDs from Phase 00 taxonomy (provider, runtime, migration, rag, admin, security, tools, integrations, woocommerce)
- CFI-ADM-001: Admin class decomposition into per-page controllers (<600 LOC each) with hook extensibility -- addressed in module boundary design
- CFI-MIG-001: Combined plugin lifecycle with extension hooks -- addressed in bootstrap sequence design

### Reference Anchors

#### Directory Structure and Bootstrap Patterns
| Source | File | Pattern |
|--------|------|---------|
| AxiaChat | `axiachat-ai/axiachat-ai.php` | Constants, Freemius SDK, file-level includes |
| AxiaChat | `axiachat-ai/includes/` | 38+ files, subdirectories: interfaces/, providers/, settings/, add-ons/, traits/, lib/ |
| AxiaChat | `axiachat-ai/includes/interfaces/interface-aichat-provider.php` | Provider interface contract |
| AxiaChat | `axiachat-ai/includes/class-aichat-provider-registry.php` | Singleton+Factory registry pattern |
| MxChat | `mxchat-basic/mxchat-basic.php` | Dynamic version, text domain loading, cache exclusions |
| MxChat | `mxchat-basic/includes/` | 17 class files, class-per-concern naming |
| MxChat | `mxchat-basic/includes/class-mxchat-admin.php` | Monolithic admin (anti-pattern to avoid) |
| MxChat | `mxchat-basic/admin/` | Admin page templates, AJAX handlers |
| Baachal | `baachal/baachal.php` | Constants, WC HPOS, centralized filterable hook array |
| Baachal | `baachal/includes/content-indexer.php` | Single includes file (minimal structure) |
| Baachal | `baachal/admin/tabs/` | Tab-based admin settings |
| Baachal | `baachal/templates/` | Frontend templates |
| Baachal | `baachal/uninstall.php` | Clean uninstall with data preservation flag |

#### Cross-Domain Resolution Patterns (Phase 01 Session 05)
| Pattern | Application in This Session |
|---------|----------------------------|
| Policy-vs-Execution | Security -> Runtime/RAG/Admin module boundary design |
| Registry Centralization | Provider registry, tool registry module placement |
| Natural Interface Boundary | Admin -> Provider key read/write boundary |
| Layered Ownership | RAG -> WooCommerce enrichment boundary |
| Hosting Pattern | Admin diagnostics -> Security panel registration |
| Dual Exposure | WooCommerce cart ops via AJAX + Tools |

---

## 9. Testing Strategy

### Unit Tests
- N/A (documentation session -- no code produced)

### Integration Tests
- N/A (documentation session)

### Manual Testing
- Review each deliverable document for internal consistency
- Verify module dependency graph has no cycles using manual trace
- Cross-reference capability mapping against v1_scope_boundary.md (all 38 items accounted for)

### Edge Cases
- WooCommerce conditional loading when WC is not installed
- Multi-bot support requiring per-bot isolation in module design
- Admin pages needing cross-domain data (hosting pattern validation)

---

## 10. Dependencies

### External Libraries
- None (documentation session)

### Other Sessions
- **Depends on**: Phase 00 (all 4 sessions), Phase 01 (all 6 sessions) -- completed
- **Depended by**: Phase 02 sessions 02-07 (all subsequent architecture sessions)

---

## Next Steps

Run `/implement` to begin AI-led implementation.
