# Task Checklist

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Total Tasks**: 22
**Estimated Duration**: 3-4 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0201]` = Session reference (phase 02, session 01 -- prerequisite)
- `[S0202]` = Session reference (phase 02, session 02 -- this session)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 6 | 6 | 0 |
| Implementation | 10 | 10 | 0 |
| Testing | 4 | 4 | 0 |
| **Total** | **22** | **22** | **0** |

---

## Setup (2 tasks)

Initial verification and workspace preparation.

- [x] T001 [S0202] Verify prerequisites met: confirm session 01 deliverables exist (`directory_structure.md`, `naming_conventions.md`, `module_boundaries.md`, `bootstrap_sequence.md`), confirm carry-forward items CFI-MIG-002, CFI-SEC-002, CFI-RUN-002 are accessible in `carry_forward_register_final.md`, and confirm v1 scope boundary document is available
  - Ref: `.spec_system/specs/phase02-session01-.../` (all 7 deliverables)
  - Ref: `.spec_system/specs/phase01-session06-.../carry_forward_register_final.md`
  - Ref: `.spec_system/specs/phase01-session06-.../v1_scope_boundary.md`

- [x] T002 [S0202] Create deliverable file stubs in session directory with document headers and section outlines for all 7 specification documents (`migration_runner_framework.md`, `database_table_schemas.md`, `options_schema.md`, `transient_usage_guide.md`, `encrypted_storage_spec.md`, `data_retention_policy.md`, `uninstall_behavior_contract.md`)
  - Target: `specs/phase02-session02-.../` (7 new files)

---

## Foundation (6 tasks)

Core framework specifications that other deliverables depend on.

- [x] T003 [S0202] Design migration runner framework specification: define version-ordered callback registry, migration entry format (`{version, domain, description, callback}`), execution ordering algorithm, and the `wpaic_register_migrations` hook contract (`migration_runner_framework.md`)
  - Ref: `mxchat-basic/mxchat-basic.php` (version_compare migration pattern, lines 270-509)
  - Ref: `axiachat-ai/axiachat-ai.php` (dbDelta activation pattern, lines 755-875)
  - Ref: CFI-MIG-002 resolution criteria
  - N/A: `baachal/` (no formal migration system)

- [x] T004 [S0202] Design migration execution logging and schema validation: define `wp_wpaic_migrations` log table schema (version, domain, status, started_at, completed_at, error_message), post-migration `SHOW COLUMNS` validation procedure, and migration lock mechanism using `wpaic_temp_migration_lock` transient with 5-minute TTL (`migration_runner_framework.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (SHOW COLUMNS schema validation pattern)
  - Ref: `mxchat-basic/mxchat-basic.php` (SHOW COLUMNS + ALTER TABLE per-column migration)
  - Ref: Session 01 `bootstrap_sequence.md` (migration lock design)

- [x] T005 [S0202] Design migration rollback strategy: document forward-only default with compensating migrations, manual rollback procedures, version downgrade safety rules, and backup-before-migrate recommendation (`migration_runner_framework.md`)
  - Ref: CFI-MIG-002 resolution criteria (rollback strategy documented)
  - Ref: `baachal/uninstall.php` (clean data removal pattern)
  - N/A: `mxchat-basic/` (no rollback mechanism)
  - N/A: `axiachat-ai/` (no rollback mechanism)

- [x] T006 [S0202] [P] Design encrypted storage specification: define AES-256-GCM parameters (cipher, IV length, tag length), key derivation from `wp_salt('auth')`, wire format for stored ciphertext (base64 envelope with IV+tag+ciphertext), sensitive field inventory, transparent encrypt/decrypt interface via settings manager, and key rotation strategy with re-encryption procedure (`encrypted_storage_spec.md`)
  - Ref: `axiachat-ai/includes/settings/encrypt-decrypt.php` (AES-256-GCM with random IV)
  - Ref: `axiachat-ai/includes/settings.php` (`aichat_get_setting`/`aichat_set_setting` auto-encrypt)
  - Ref: CFI-SEC-002 resolution criteria
  - Ref: RES-XD-001 (Policy-vs-Execution: security owns crypto, admin owns field designation)
  - N/A: `mxchat-basic/` (plaintext API keys -- anti-pattern)
  - N/A: `baachal/` (plaintext API keys -- anti-pattern)

- [x] T007 [S0202] [P] Define options schema with prefix conventions and field registry: enumerate all `wpaic_{domain}_{setting}` option keys across 6 MVP domains (provider, runtime, rag, admin, security, migration), document default values, data types, validation rules, and which fields are sensitive (requiring encryption) (`options_schema.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (individual `aichat_*` options with `add_option` defaults)
  - Ref: `mxchat-basic/mxchat-basic.php` (`mxchat_options[]` blob pattern -- anti-pattern to avoid)
  - Ref: `baachal/baachal.php` (individual `baachal_*` options with `update_option` defaults)
  - Ref: Session 01 `naming_conventions.md` (option naming rules)

- [x] T008 [S0202] [P] Define transient usage patterns guide: categorize transients by purpose (rate limiting, caching, processing locks, temporary state), document naming patterns per category, TTL policies, cleanup triggers on deactivation, and object cache compatibility notes (`transient_usage_guide.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_rl_*`, `aichat_block_*`, `aichat_lastmsg_*` transient patterns)
  - Ref: `mxchat-basic/mxchat-basic.php` (heavy transient usage: ~25+ patterns for session state, queue, notices)
  - Ref: `baachal/baachal.php` (`baachal_dynamic_terms`, `baachal_products_*` cache transients)
  - Ref: `lib/rate-limiter.php` (existing rate limit transient implementation)
  - Ref: Session 01 `naming_conventions.md` (transient naming rules)

---

## Implementation (10 tasks)

Schema definitions and policy documents for all data entities.

- [x] T009 [S0202] Design unified chat sessions table schema (`wp_wpaic_sessions`): define columns for session identity (id, session_token, user_id, bot_id), ownership validation (ip_address VARBINARY(16), user_agent_hash), lifecycle (status, created_at, updated_at, expires_at), aggregated token/cost tracking (total_prompt_tokens, total_completion_tokens, total_cost_micros), and metadata (page_id, provider, model). Include indexes and document ownership by runtime domain (`database_table_schemas.md`)
  - Ref: `axiachat-ai/includes/class-aichat-ajax.php` (per-message session_id + token/cost tracking)
  - Ref: `mxchat-basic/includes/class-mxchat-public.php` (session ownership via IP/user binding)
  - Ref: `baachal/baachal.php` (session as Custom Post Type -- pattern to replace)
  - Ref: CFI-RUN-002 resolution criteria (dedicated storage, ownership validation, token tracking, retention)

- [x] T010 [S0202] Design chat messages table schema (`wp_wpaic_messages`): define columns for message identity (id, session_id FK, role ENUM), content (message LONGTEXT, response LONGTEXT), per-message token/cost tracking (prompt_tokens, completion_tokens, cost_micros), provider attribution (provider, model), and timestamps. Include indexes on (session_id, id) and (created_at). Document ownership by runtime domain (`database_table_schemas.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_conversations` table schema, lines 795-830)
  - Ref: `mxchat-basic/mxchat-basic.php` (`mxchat_chat_transcripts` table schema)
  - N/A: `baachal/` (uses CPT posts, no message-level table)

- [x] T011 [S0202] [P] Design knowledge context table schema (`wp_wpaic_knowledge`): define columns for RAG context configuration (id, name, context_type, embedding_provider), autosync settings (autosync_enabled, autosync_mode, autosync_post_types, autosync_last_scan), processing status (processing_status, processing_progress, items_to_process), and indexing options. Include indexes. Document ownership by rag domain (`database_table_schemas.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_contexts` table schema, lines 755-794)
  - Ref: `mxchat-basic/admin/class-knowledge-manager.php` (knowledge base management patterns)
  - Ref: `baachal/includes/content-indexer.php` (content indexing with FULLTEXT search)

- [x] T012 [S0202] [P] Design content chunks table schema (`wp_wpaic_chunks`): define columns for chunk identity (id, knowledge_id FK, post_id, chunk_index), content (type, title, content MEDIUMTEXT, embedding LONGTEXT), token count, and timestamps. UNIQUE KEY on (post_id, knowledge_id, chunk_index). Document ownership by rag domain (`database_table_schemas.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_chunks` table schema with UNIQUE KEY)
  - Ref: `mxchat-basic/mxchat-basic.php` (`mxchat_system_prompt_content` with embedding_vector)
  - Ref: `baachal/includes/content-indexer.php` (`baachal_content_index` with FULLTEXT)

- [x] T013 [S0202] [P] Design ingestion queue table schema (`wp_wpaic_queue`): define columns for queue identity (id, queue_id, item_type), processing state (status ENUM, priority, attempts, max_attempts, error_message), bot association (bot_id), and timestamps (created_at, started_at, completed_at). Include indexes on (status, priority) and (queue_id). Document ownership by rag domain (`database_table_schemas.md`)
  - Ref: `mxchat-basic/mxchat-basic.php` (`mxchat_processing_queue` + `mxchat_queue_meta` tables)
  - N/A: `axiachat-ai/` (no explicit queue table -- uses inline processing)
  - N/A: `baachal/` (no queue mechanism)

- [x] T014 [S0202] [P] Design usage/cost daily aggregation table schema (`wp_wpaic_usage_daily`): define columns with composite primary key (date, provider, model), aggregated counters (prompt_tokens, completion_tokens, total_tokens, cost_micros, conversations). Document ownership by admin domain (`database_table_schemas.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_usage_daily` table schema with composite PK)
  - N/A: `mxchat-basic/` (no usage aggregation table)
  - N/A: `baachal/` (no usage tracking)

- [x] T015 [S0202] Design bot configuration table schema (`wp_wpaic_bots`): define columns for bot identity (id, name, slug UNIQUE), AI configuration (provider, model, temperature, max_tokens, instructions LONGTEXT), context association (context_mode, knowledge_id), UI configuration (ui_position, ui_color, ui_avatar, ui_suggestions), behavioral limits (input_max_length, max_messages, history_persistence), and lifecycle (is_active, created_at, updated_at). Document default bot seeding on activation. Document ownership by admin domain (`database_table_schemas.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_bots` table schema with UI columns)
  - Ref: `mxchat-basic/includes/class-mxchat-admin.php` (bot config via options)
  - Ref: `baachal/admin/tabs/general.php` (single-bot config via options)

- [x] T016 [S0202] Document deferred domain schema extension points: describe how Tools (`wp_wpaic_tools`, `wp_wpaic_macros`, `wp_wpaic_tool_calls`), WooCommerce (no custom tables -- reads from WC APIs), and Integrations (`wp_wpaic_webhooks`) domains will register their migrations via `wpaic_register_migrations` without coupling to MVP modules. Include source-tagged records pattern for cascade delete (`database_table_schemas.md`)
  - Ref: `axiachat-ai/axiachat-ai.php` (`aichat_tools`, `aichat_macros`, `aichat_tool_calls`, `aichat_custom_webhooks` schemas)
  - Ref: Session 01 `extension_points.md` (extension registry architecture)
  - N/A: `baachal/` (no tool/webhook tables)

- [x] T017 [S0202] Design data retention and cleanup policies: define per-data-type retention rules (chat sessions: configurable 30/90/365 days default 90, messages: follows session retention, knowledge indexes: indefinite until manual delete, usage logs: 365 days rolling, queue items: 7 days after completion, migration log: indefinite), cleanup schedule via WP-Cron, and admin-configurable retention period options (`data_retention_policy.md`)
  - Ref: `axiachat-ai/includes/aichat-cron.php` (cron-based cleanup patterns)
  - Ref: `baachal/baachal.php` (no retention -- anti-pattern)
  - Ref: `mxchat-basic/mxchat-basic.php` (no retention -- anti-pattern)
  - Ref: SECURITY-COMPLIANCE.md (GDPR data minimization requirement)

- [x] T018 [S0202] Design uninstall behavior contract: define staged cleanup sequence (check preservation flag -> fire `wpaic_uninstalling` action -> drop custom tables -> delete `wpaic_*` options -> delete `wpaic_*` transients -> remove uploaded knowledge files -> remove `wpaic_*` cron events -> remove `wpaic_*` user meta), document the `wpaic_admin_preserve_data` option behavior, and specify extension domain cleanup hook registration (`uninstall_behavior_contract.md`)
  - Ref: `baachal/uninstall.php` (`baachal_preserve_data_on_uninstall` flag + full cleanup -- winner pattern)
  - Ref: `axiachat-ai/axiachat-ai.php` (minimal 3-option delete -- anti-pattern)
  - Ref: `mxchat-basic/mxchat-basic.php` (no uninstall handler -- anti-pattern)
  - Ref: Session 01 `bootstrap_sequence.md` (uninstall sequence design)

---

## Testing (4 tasks)

Verification and quality assurance.

- [x] T019 [S0202] Cross-reference all 26 MVP capabilities against storage locations: verify every capability from `v1_scope_boundary.md` has at least one defined storage mechanism (table, option, or transient) in the deliverable documents. Flag any capability with no storage assignment.
  - Ref: `.spec_system/specs/phase01-session06-.../v1_scope_boundary.md`

- [x] T020 [S0202] Validate naming conventions: verify all table names follow `{$wpdb->prefix}wpaic_{entity}`, all option keys follow `wpaic_{domain}_{setting}`, all transient keys follow `wpaic_{purpose}_{identifier}`, and all PHP namespaces follow `WPAIC\{Domain}` per session 01 naming guide and CONVENTIONS.md
  - Ref: Session 01 `naming_conventions.md`
  - Ref: `.spec_system/CONVENTIONS.md` (Data and Storage section)

- [x] T021 [S0202] Verify schema extensibility for deferred capabilities: confirm the 12 deferred capabilities (4 Tools, 3 Integrations, 5 WooCommerce) can add tables and options through the migration registry without modifying MVP schemas or breaking existing migrations
  - Ref: `.spec_system/specs/phase01-session06-.../v1_scope_boundary.md` (12 deferred items)
  - Ref: Session 01 `extension_points.md`

- [x] T022 [S0202] Validate ASCII encoding, completeness, and carry-forward resolution: confirm all 7 deliverable files are ASCII-encoded with Unix LF line endings, verify CFI-MIG-002 is fully addressed in `migration_runner_framework.md`, CFI-SEC-002 is fully addressed in `encrypted_storage_spec.md`, and CFI-RUN-002 is fully addressed in the session table schema in `database_table_schemas.md`
  - Ref: `.spec_system/specs/phase01-session06-.../carry_forward_register_final.md`

---

## Completion Checklist

Before marking session complete:

- [x] All tasks marked `[x]`
- [x] All tests passing
- [x] All files ASCII-encoded
- [x] implementation-notes.md updated
- [x] Ready for `/validate`

---

## Next Steps

Run `/implement` to begin AI-led implementation.
