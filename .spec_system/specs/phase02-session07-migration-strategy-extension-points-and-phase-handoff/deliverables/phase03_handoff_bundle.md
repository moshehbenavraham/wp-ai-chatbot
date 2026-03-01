# Phase 03 Handoff Bundle

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Purpose

This handoff bundle is the primary input for Phase 03 implementation. A Phase 03 developer can
implement the plugin skeleton without re-reading Phase 01 scoring documents or reference plugin
source code. All necessary contracts, schemas, and decisions are referenced here.

---

## 2. Phase 02 Deliverable Index

Quick-reference index to all Phase 02 contract documents. Listed by implementation dependency order.

### 2.1 Foundation Contracts (implement first)

| Document | Location | Key Content |
|----------|---------|------------|
| Directory Structure | `phase02-session01.../directory_structure.md` | Full directory tree, constants, separation principles |
| Naming Conventions | `phase02-session01.../naming_conventions.md` | wpaic_ prefix, file naming, class naming |
| Bootstrap Sequence | `phase02-session01.../bootstrap_sequence.md` | Plugin init order, autoloader, WooCommerce detection |
| Module Dependency Graph | `phase02-session01.../module_dependency_graph.md` | Which modules depend on which |
| Extension Points | `phase02-session01.../extension_points.md` | Hook registration points in bootstrap |
| Database Table Schemas | `phase02-session02.../database_table_schemas.md` | All 6 custom tables with columns/indexes |
| Options Schema | `phase02-session02.../options_schema.md` | All 64+ option keys with types/defaults/validation |
| Migration Runner Framework | `phase02-session02.../migration_runner_framework.md` | Migration registry, version tracking |
| Encrypted Storage Spec | `phase02-session02.../encrypted_storage_spec.md` | AES-256-GCM, key derivation, wire format |
| Uninstall Behavior Contract | `phase02-session02.../uninstall_behavior_contract.md` | Data preservation flag, cleanup order |

### 2.2 Provider Layer (implement second)

| Document | Location | Key Content |
|----------|---------|------------|
| Provider Interface Contract | `phase02-session03.../provider_interface_contract.md` | Provider_Interface, compliance rules CR-01--CR-05 |
| Provider Registry Dispatcher | `phase02-session03.../provider_registry_dispatcher.md` | Registration, resolution, fallback dispatch |
| Model Registry Data Model | `phase02-session03.../model_registry_data_model.md` | Model catalog, deprecation, cost metadata |
| Streaming Transport Layer | `phase02-session03.../streaming_transport_layer.md` | SSE protocol, normalized chunks |
| Error Handling/Fallback | `phase02-session03.../error_handling_fallback_contract.md` | Retry policy, fallback chain |
| Cost Accounting Interface | `phase02-session03.../cost_accounting_interface.md` | Token tracking, cost calculation |
| Chat Payload Schemas | `phase02-session03.../chat_payload_schemas.md` | Request/response JSON schemas |

### 2.3 Security Layer (implement third -- required by all other layers)

| Document | Location | Key Content |
|----------|---------|------------|
| Sanitization Library Spec | `phase02-session06.../sanitization_library_spec.md` | 12 TYPE_* constants, compliance rules SCR-01--07 |
| Auth Enforcement Spec | `phase02-session06.../auth_enforcement_spec.md` | Auth matrix for all 16 endpoints |
| Moderation Pipeline Spec | `phase02-session06.../moderation_pipeline_spec.md` | 5-layer pipeline, config options |
| Privacy and Audit Trail | `phase02-session06.../privacy_audit_trail_spec.md` | Privacy controls, event taxonomy |
| Structured Logging Integration | `phase02-session06.../structured_logging_integration.md` | 13 subsystem tags, log levels |

### 2.4 Runtime Layer (implement fourth)

| Document | Location | Key Content |
|----------|---------|------------|
| Session 04 deliverables | `phase02-session04.../deliverables/` | Message pipeline, session model, endpoints, frontend |

### 2.5 RAG Layer (implement fifth)

| Document | Location | Key Content |
|----------|---------|------------|
| Content Source Adapter Interface | `phase02-session05.../content_source_adapter_interface.md` | Source_Adapter_Interface |
| Ingestion Queue Spec | `phase02-session05.../ingestion_queue_spec.md` | Queue lifecycle, worker, retry |
| Chunking Module Interface | `phase02-session05.../chunking_module_interface.md` | Chunker contract |
| Embedding Provider Interface | `phase02-session05.../embedding_provider_interface.md` | Embedding_Interface |
| Retrieval Ranking Contract | `phase02-session05.../retrieval_ranking_contract.md` | 4 retrieval modes, ANN decision |
| Context Assembly Spec | `phase02-session05.../context_assembly_spec.md` | Prompt construction, security policy |
| Knowledge Admin Contract | `phase02-session05.../knowledge_admin_contract.md` | REST endpoints for knowledge CRUD |

### 2.6 Admin Layer (implement sixth)

| Document | Location | Key Content |
|----------|---------|------------|
| Admin Settings Architecture | `phase02-session06.../admin_settings_architecture.md` | 7 pages, WP Settings API |
| Diagnostics Dashboard Spec | `phase02-session06.../diagnostics_dashboard_spec.md` | Panel registry, SSE test, admin JS |

### 2.7 Migration and Extension (implement last / parallel)

| Document | Location | Key Content |
|----------|---------|------------|
| Option Migration Framework | `phase02-session07.../option_migration_framework.md` | Option_Migrator contract |
| AxiaChat Migration Strategy | `phase02-session07.../axiachat_migration_strategy.md` | AXI option map (30+ keys) |
| MxChat Migration Strategy | `phase02-session07.../mxchat_migration_strategy.md` | MXC blob decomposition |
| Baachal Migration Strategy | `phase02-session07.../baachal_migration_strategy.md` | BAA option renames |
| Hook and Filter Architecture | `phase02-session07.../hook_filter_architecture.md` | All 40+ hooks with signatures |
| Extension Point Registry | (see below) | Tools/integrations/WooCommerce stubs |

---

## 3. Phase 03 Implementation Order

Recommended module implementation order based on the dependency graph:

```
Pass 1 (no dependencies):
  - wp-ai-chatbot.php (bootstrap: constants, autoloader, class-plugin.php init)
  - uninstall.php (lifecycle: clean uninstall, data preservation flag)
  - includes/autoloader.php
  - includes/class-plugin.php (module wiring skeleton)
  - lib/ (already complete -- health, rate-limiter, backup-manager, structured-logger)

Pass 2 (depends on Pass 1):
  - includes/migration/class-migration-registry.php (versioned DB schema migration)
  - includes/migration/class-lifecycle-manager.php (activate/deactivate hooks)
  - DB schema creation (CREATE TABLE statements from database_table_schemas.md)

Pass 3 (depends on Pass 2):
  - includes/security/class-encryption.php (AES-256-GCM)
  - includes/security/class-sanitizer.php (12 type constants)
  - includes/security/class-auth-enforcer.php (dual-check)
  - includes/security/class-audit-logger.php

Pass 4 (depends on Pass 3):
  - includes/provider/interface-provider.php
  - includes/provider/interface-embedding.php
  - includes/provider/class-provider-registry.php
  - includes/provider/class-model-registry.php
  - includes/provider/adapters/ (OpenAI adapter first)

Pass 5 (depends on Passes 3, 4):
  - includes/security/class-moderator.php
  - includes/security/class-privacy-manager.php
  - includes/runtime/class-session-manager.php
  - includes/runtime/class-message-pipeline.php
  - includes/runtime/class-stream-handler.php

Pass 6 (depends on Pass 4):
  - includes/rag/ (all 5 files)

Pass 7 (depends on all above):
  - includes/admin/ (all admin controllers and pages)
  - admin/js/admin.js
  - assets/js/chatbot.js
  - templates/chatbot-widget.php

Pass 8 (can run parallel with Pass 7):
  - includes/migration/class-option-migrator.php
  - Migration strategy implementation per source plugin
```

---

## 4. Open Decisions

The following decisions were deferred and must be made during Phase 03 or later:

| Decision | Context | Current State | Recommended Phase |
|----------|---------|--------------|-----------------|
| jQuery dependency in frontend | CFI-RUN-004 resolved as "vanilla JS" | No jQuery allowed in chatbot.js | Resolved -- enforce in Phase 05 |
| Default bot configuration | What is the out-of-box experience for a new install? | No default bot defined yet | Phase 03 |
| WP version minimum enforcement | Must drop support for WP < 5.0? | Constant WPAIC_MIN_WP = '5.0' in spec | Phase 03 bootstrap |
| Single-bot vs multi-bot MVP | wp_wpaic_bots table exists but single-bot admin page first? | Single-bot is simpler MVP path | Phase 03 decision |
| Migration UI (admin page) | When does uwpaic-migration page appear? | On source plugin detection or always? | Phase 10 |
| Cron reliability guarantee | Sites with low traffic may miss queue processing | Documented: cron-triggered by page loads | Phase 06 (queue) |

---

## 5. Phase 03 Session Ordering Guidance

Phase 03 scope covers: Plugin bootstrap, constants, autoloading, hook registration, activation/
deactivation lifecycle, core DB schema creation, migration runner, WP-Cron scheduling.

Recommended session breakdown:
1. **Session 01**: Bootstrap, constants, autoloader, class-plugin.php, wp-ai-chatbot.php skeleton
2. **Session 02**: Activation lifecycle, uninstall handler, migration registry, DB schema creation
3. **Session 03**: Security foundation (encryption, sanitizer, auth enforcer) -- used by all later sessions
4. **Session 04**: Provider registry + first adapter (OpenAI) -- enables integration testing
5. **Session 05**: Admin skeleton (router, settings manager, general page) -- enables settings testing

Phase 03 does NOT include: chat runtime, RAG pipeline, advanced providers, moderation (Phase 05+).

---

## 6. Known Limitations at Phase 02 Exit

| Limitation | Impact | Mitigation |
|-----------|--------|-----------|
| No per-bot configuration in admin MVP | Only global defaults configurable | Single-bot is sufficient for MVP; multi-bot in Phase 05 |
| RAG retrieval bounded at ~10K chunks for local mode | Large knowledge bases require Pinecone | Documented in retrieval_ranking_contract.md |
| AI moderation disabled by default | Hate speech filter not active OOTB | Rate limit + spam + banned words layers active |
| No real-time alerting | Errors go to log files only | Admin log viewer + structured logger cover basic ops |
| WP-Cron reliability on low-traffic sites | Queue may process slowly | Documented; real cron setup recommended in admin notice |
