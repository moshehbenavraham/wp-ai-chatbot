# Session Specification

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Not Started
**Created**: 2026-03-01

---

## 1. Session Overview

This session designs the complete data layer for the unified WP AI Chatbot plugin. It covers every custom database table schema, the WordPress options registry with prefix conventions, transient usage patterns, the migration runner framework, encrypted storage for sensitive fields, data retention policies, and the uninstall behavior contract. Every data entity required by the 26 MVP capabilities must have a defined storage location by session end.

The data layer design builds directly on the module map, naming conventions, and dependency graph established in session 01. The `migration` domain module (`includes/migration/`) owns the migration runner and lifecycle management. Individual domains (runtime, rag, provider, admin, security) register their own table creation callbacks through the `wpaic_register_migrations` hook, maintaining the unidirectional dependency graph where `migration` remains a leaf module with no imports from other domains.

This session produces design documents only -- no implementation code. The schemas and specifications created here will be consumed by Phase 02 sessions 03-07 (subsystem contracts) and implemented in Phase 03 (Core Plugin Skeleton). Three carry-forward items from Phase 01 are directly resolved: CFI-MIG-002 (migration registry), CFI-SEC-002 (API key encryption at rest), and CFI-RUN-002 (unified session storage).

---

## 2. Objectives

1. Design database table schemas for all custom tables with column definitions, data types, indexes, relationships, and domain ownership assignments.
2. Formalize the migration runner framework specification with version-ordered entries, execution logging, post-migration schema validation, and rollback strategy (CFI-MIG-002).
3. Define the encrypted storage specification for API keys and sensitive configuration fields using AES-256-GCM with documented key rotation strategy (CFI-SEC-002).
4. Design the unified chat session table schema that replaces three divergent source formats with ownership validation and token/cost tracking (CFI-RUN-002).
5. Establish the complete options schema with `wpaic_{domain}_{setting}` prefix conventions and a field registry covering all 26 MVP capabilities.
6. Define transient usage patterns with naming conventions, TTL policies, and cleanup triggers.
7. Document data retention policies per data type and the uninstall behavior contract with staged cleanup and user-configurable data preservation.

---

## 3. Prerequisites

### Required Sessions
- [x] `phase02-session01-module-map-directory-architecture-and-bootstrap` - Module map, directory structure, naming conventions, bootstrap sequence, dependency graph, extension points
- [x] `phase01-session06-winner-consolidation-and-phase-handoff` - Winner summaries, handoff bundle, carry-forward register, v1 scope boundary
- [x] `phase01-session02-runtime-and-migration-domains` - Runtime and migration domain scoring with storage pattern analysis

### Required Tools/Knowledge
- Session 01 deliverables: `directory_structure.md`, `naming_conventions.md`, `module_boundaries.md`, `bootstrap_sequence.md`
- Phase 02 handoff bundle (`phase02_handoff_bundle.md`) -- per-domain storage winner patterns
- v1 scope boundary (`v1_scope_boundary.md`) -- 26 MVP + 12 deferred capabilities
- Carry-forward register (`carry_forward_register_final.md`) -- items CFI-MIG-002, CFI-SEC-002, CFI-RUN-002

### Environment Requirements
- Read access to `axiachat-ai/`, `mxchat-basic/`, `baachal/` reference directories
- No build tools or runtime environments needed (documentation session)

---

## 4. Scope

### In Scope (MVP)
- Custom database table schemas for all MVP data entities (sessions, messages, knowledge contexts, chunks, ingestion queue, usage/cost tracking, migration log, bot configuration) - full column definitions with types, indexes, and constraints
- WordPress options schema with `wpaic_{domain}_{setting}` prefix conventions and complete field registry with defaults - covering all 26 MVP capabilities
- Transient usage patterns guide with naming, TTL policies, and deterministic cleanup triggers - categorized by purpose (caching, rate limiting, temporary state, processing locks)
- Migration runner framework specification with version-ordered callback registry, execution logging, post-migration `SHOW COLUMNS` validation, and documented rollback strategy - resolving CFI-MIG-002
- Encrypted storage specification for API keys using AES-256-GCM with IV management, key derivation from `wp_salt()`, transparent encrypt/decrypt interface, and key rotation strategy - resolving CFI-SEC-002
- Unified session table schema replacing three source formats (AXI per-message table, MXC wp_options transients, BAA custom post type) with ownership validation and token/cost tracking - resolving CFI-RUN-002
- Data retention policies per data type (chat history, knowledge indexes, usage logs, processing queue) with configurable retention periods and cleanup schedules
- Uninstall behavior contract with staged cleanup sequence, admin-configurable data preservation flag, and extension domain cleanup hooks
- Deferred domain schema extension points documenting how Tools, WooCommerce, and Integrations domains will add tables without breaking existing migrations

### Out of Scope (Deferred)
- Actual SQL DDL implementation code -- *Reason: Phase 03 implementation*
- PHP interface definitions for subsystem contracts -- *Reason: Sessions 03-06 scope*
- Source plugin settings migration logic and mapping -- *Reason: Session 07 scope*
- Specific tool/macro table schemas for deferred domains -- *Reason: Documented as extension points only*

---

## 5. Technical Approach

### Architecture
The data layer follows the domain-owned migration pattern established in session 01. Each domain registers its table creation and schema upgrade callbacks through the `wpaic_register_migrations` action hook. The migration module (`WPAIC\Migration`) executes these callbacks in version order but never imports domain classes directly -- it receives anonymous/closure callbacks only.

Storage is split across three WordPress-native mechanisms: custom tables (via `$wpdb` and `dbDelta()`) for structured relational data, `wp_options` for configuration with the `wpaic_` prefix, and transients for short-lived cache/state with deterministic cleanup. Sensitive option values (API keys) pass through the security module's AES-256-GCM encrypt/decrypt layer transparently via the settings manager interface.

### Design Patterns
- **Domain-owned migrations**: Each domain pushes versioned migration callbacks to the central registry; the registry owns execution order and logging
- **Transparent encryption**: Settings manager wraps `get_option`/`update_option` with auto-encrypt/decrypt for designated sensitive fields (Policy-vs-Execution pattern from RES-XD-001)
- **Idempotent schema creation**: All `CREATE TABLE` specifications use `dbDelta()` compatible DDL with `IF NOT EXISTS` semantics
- **Version-gated upgrades**: Migration runner compares stored `wpaic_migration_version` against `WPAIC_VERSION` constant and runs only pending callbacks
- **Staged uninstall**: Cleanup respects `wpaic_admin_preserve_data` flag and fires `wpaic_uninstalling` action for extension domains

### Technology Stack
- WordPress `$wpdb` API with `dbDelta()` for table creation
- WordPress Options API for configuration storage
- WordPress Transients API for caching and temporary state
- PHP `openssl_encrypt`/`openssl_decrypt` with AES-256-GCM for sensitive field encryption
- No external dependencies (documentation deliverables only)

---

## 6. Deliverables

### Files to Create
| File | Purpose | Est. Lines |
|------|---------|------------|
| `specs/phase02-session02-.../migration_runner_framework.md` | Migration registry architecture, version ordering, execution logging, rollback strategy, schema validation | ~250 |
| `specs/phase02-session02-.../database_table_schemas.md` | All custom table definitions with columns, types, indexes, relationships, domain ownership | ~400 |
| `specs/phase02-session02-.../options_schema.md` | Options prefix conventions, field registry per domain, default values, validation rules | ~250 |
| `specs/phase02-session02-.../transient_usage_guide.md` | Transient naming patterns, TTL policies, cleanup triggers, categories | ~120 |
| `specs/phase02-session02-.../encrypted_storage_spec.md` | AES-256-GCM specification, field inventory, key derivation, rotation strategy, migration from plaintext | ~180 |
| `specs/phase02-session02-.../data_retention_policy.md` | Per-data-type retention rules, cleanup schedules, configurable periods | ~120 |
| `specs/phase02-session02-.../uninstall_behavior_contract.md` | Staged cleanup sequence, data preservation flag, extension domain hooks | ~150 |

### Files to Modify
| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Set current_session, add history entry | ~5 |

---

## 7. Success Criteria

### Functional Requirements
- [ ] Every data entity from the 26 MVP capabilities has a defined storage location (table, option, or transient)
- [ ] Migration runner supports version-ordered forward migrations with execution logging and post-migration schema validation
- [ ] Rollback strategy is documented with clear limitations and recovery procedures
- [ ] Options naming follows `wpaic_{domain}_{setting}` convention and is collision-free with WordPress core
- [ ] Encrypted storage specification covers all API key fields with AES-256-GCM and key rotation strategy
- [ ] Unified session table replaces three source formats with ownership validation and token/cost tracking
- [ ] Uninstall path is safe (no orphaned data, no accidental deletion of user content, respects preservation flag)
- [ ] Schema design supports future extension (Tools, WooCommerce, Integrations) without breaking migrations

### Testing Requirements
- [ ] All 26 MVP capabilities cross-referenced against storage locations
- [ ] Naming conventions cross-referenced with CONVENTIONS.md and session 01 naming guide
- [ ] Carry-forward items CFI-MIG-002, CFI-SEC-002, and CFI-RUN-002 fully addressed
- [ ] 12 deferred capabilities verified for schema extensibility without migration breaks

### Non-Functional Requirements
- [ ] All deliverables follow ASCII encoding with Unix LF line endings
- [ ] Documents are self-contained for Phase 02 sessions 03-07 to consume without re-reading full source repos
- [ ] Table schemas include index strategy notes for query performance

### Quality Gates
- [ ] All files ASCII-encoded
- [ ] Unix LF line endings
- [ ] Code follows project conventions

---

## 8. Implementation Notes

### Key Considerations
- The `wpaic_` prefix is the authoritative prefix for all tables, options, transients, and user meta (established in session 01)
- Table naming follows `{$wpdb->prefix}wpaic_{entity}` -- never hardcode `wp_`
- The `migration` module is a leaf module (Level 0 in dependency graph) -- it cannot import from any other domain
- Domain modules register migrations via `add_action('wpaic_register_migrations', ...)` with version string and idempotent callback
- Settings manager in `includes/admin/` owns option read/write with transparent encrypt/decrypt for sensitive fields
- Model registry is in-memory (PHP array), not a DB table -- per Phase 01 provider domain winner decision
- SRC-AXI deliberately moved tool-pending state from transients to a DB table to avoid object cache race conditions -- this pattern should be adopted

### Potential Challenges
- **Schema evolution without data loss**: Migration runner must support column additions via `dbDelta()` (which handles `ALTER TABLE` for new columns) plus manual `ALTER TABLE` for column modifications that `dbDelta()` does not handle (type changes, column drops)
- **Encryption key derivation stability**: Using `wp_salt()` ties encryption to the site's `AUTH_KEY` constant; if the constant changes (e.g., site migration), encrypted values become unreadable -- the spec must document this risk and mitigation
- **Session migration complexity**: Three different source storage formats (AXI custom table, MXC wp_options transients, BAA custom post type) require three distinct migration routines -- session 07 scope but schema must accommodate all source fields
- **Index performance vs storage**: Embedding columns (LONGTEXT JSON arrays) cannot be indexed directly -- retrieval performance depends on application-level vector similarity, not DB indexes

### Relevant Considerations
- **Anchor-first extraction** (Lesson Learned): All schema decisions reference specific table definitions from the three reference plugins
- **Canonical vocabulary with alias tables** (Lesson Learned): Table and option naming uses the 9-domain canonical IDs from Phase 00 taxonomy
- **CFI-MIG-002** (Tier 1, HIGH): Migration registry with version-ordered entries, execution logging, schema validation, rollback strategy -- primary deliverable of this session
- **CFI-SEC-002** (Tier 1, HIGH): API key encryption at rest with AES-256-GCM, migration from plaintext, key rotation -- encrypted storage spec deliverable
- **CFI-RUN-002** (Tier 2, HIGH): Unified session storage replacing 3 source formats with ownership validation, token tracking, lifecycle hooks -- session table schema deliverable

### Reference Anchors

#### Database Table Schemas (Source Patterns)
| Source | File | Tables/Pattern |
|--------|------|----------------|
| AxiaChat | `axiachat-ai/axiachat-ai.php` (lines 755-875) | 15+ tables: `aichat_contexts`, `aichat_conversations`, `aichat_chunks`, `aichat_usage_daily`, `aichat_tool_states`, `aichat_bots`, `aichat_tool_calls`, `aichat_macros`, `aichat_tools` |
| AxiaChat | `axiachat-ai/includes/providers/class-openai-provider.php` | Token/cost tracking per request |
| MxChat | `mxchat-basic/mxchat-basic.php` (lines 270-509) | 8 tables: `mxchat_chat_transcripts`, `mxchat_system_prompt_content`, `mxchat_intents`, `mxchat_url_clicks`, `mxchat_processing_queue`, `mxchat_queue_meta` |
| MxChat | `mxchat-basic/includes/class-mxchat-admin.php` | Options blob pattern (`mxchat_options[]`) |
| Baachal | `baachal/baachal.php` | 1 custom table: `baachal_content_index` (FULLTEXT search); conversations via Custom Post Type |
| Baachal | `baachal/includes/content-indexer.php` | `dbDelta()` schema creation, FULLTEXT index |
| Baachal | `baachal/uninstall.php` | Clean uninstall with `baachal_preserve_data_on_uninstall` flag |

#### Migration and Versioning Patterns
| Source | File | Pattern |
|--------|------|---------|
| MxChat | `mxchat-basic/mxchat-basic.php` | `mxchat_db_version` option + `version_compare()` conditional blocks (winner for versioning) |
| MxChat | `mxchat-basic/mxchat-basic.php` | `SHOW COLUMNS` + `ALTER TABLE ADD COLUMN` per-column migrations |
| AxiaChat | `axiachat-ai/axiachat-ai.php` | `dbDelta()` at activation + `admin_init` upgrade hooks (winner for schema creation) |
| Baachal | `baachal/baachal.php` | Three-hook lifecycle coverage: `register_activation_hook`, `register_deactivation_hook`, `register_uninstall_hook` |

#### Encryption and Security Patterns
| Source | File | Pattern |
|--------|------|---------|
| AxiaChat | `axiachat-ai/includes/settings/encrypt-decrypt.php` | AES-256-GCM encryption with random IV per value, `AUTH_KEY` derivation |
| AxiaChat | `axiachat-ai/includes/settings.php` | `aichat_get_setting()`/`aichat_set_setting()` with auto-encrypt/decrypt |
| MxChat | `mxchat-basic/includes/class-mxchat-admin.php` | Plaintext API keys in `mxchat_options[]` blob (anti-pattern to replace) |
| Baachal | `baachal/admin/tabs/general.php` | Plaintext API keys in individual options (anti-pattern to replace) |

#### Session and Conversation Storage Patterns
| Source | File | Pattern |
|--------|------|---------|
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | Per-message rows in `aichat_conversations` with `session_id`, token/cost columns |
| MxChat | `mxchat-basic/includes/class-mxchat-public.php` | Session state in wp_options transients, transcripts in `mxchat_chat_transcripts` |
| Baachal | `baachal/baachal.php` | Conversations as Custom Post Type `baachal_conversation` |

#### Uninstall and Retention Patterns
| Source | File | Pattern |
|--------|------|---------|
| AxiaChat | `axiachat-ai/axiachat-ai.php` | Minimal uninstall (deletes 3 options, keeps tables -- anti-pattern) |
| MxChat | `mxchat-basic/mxchat-basic.php` | No uninstall handler (anti-pattern) |
| Baachal | `baachal/uninstall.php` | Full cleanup gated by `baachal_preserve_data_on_uninstall` flag (winner pattern) |

---

## 9. Testing Strategy

### Unit Tests
- N/A (documentation session -- no code produced)

### Integration Tests
- N/A (documentation session)

### Manual Testing
- Cross-reference every MVP capability against the storage location registry to confirm full coverage
- Verify all table names, option keys, and transient names follow session 01 naming conventions
- Trace the migration runner sequence from fresh install through version upgrade to confirm deterministic behavior
- Verify encrypted storage specification addresses plaintext-to-encrypted migration path
- Confirm uninstall contract handles all data artifacts (tables, options, transients, user meta, cron events, uploads)

### Edge Cases
- Multisite WordPress installations: table prefix variations with `$wpdb->prefix` per site
- Object cache plugins that bypass transient TTLs (persistent object cache)
- Encryption key loss when `AUTH_KEY` changes during site migration
- Concurrent migration execution on high-traffic sites (lock contention)
- Large embedding columns (LONGTEXT) and their impact on table size and backup performance
- Session ownership when IP changes mid-conversation (mobile network switching)

---

## 10. Dependencies

### External Libraries
- None (documentation session)

### Other Sessions
- **Depends on**: `phase02-session01-module-map-directory-architecture-and-bootstrap` (module map, naming conventions, bootstrap sequence)
- **Depended by**: `phase02-session03-provider-orchestration-and-model-registry-contracts` (provider options, encrypted key storage), `phase02-session04-chat-runtime-session-model-and-frontend-contracts` (session/message table schemas), `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts` (knowledge/chunks/queue table schemas), `phase02-session06-security-admin-contracts-and-observability` (audit logging, retention policies), `phase02-session07-migration-strategy-extension-points-and-phase-handoff` (migration runner, source plugin migration paths)

---

## Next Steps

Run `/implement` to begin AI-led implementation.
