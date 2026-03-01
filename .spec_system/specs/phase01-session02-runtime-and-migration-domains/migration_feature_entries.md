# Migration Feature Entries

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Domain**: `DOM-MIGRATION`
**Context Pack**: `CP-P01-S02-002`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-MIG-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-MIG-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-MIG-AXI-001` |
| `source_path` | `axiachat-ai/axiachat-ai.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Plugin lifecycle management` |
| `capability_summary` | Three-hook lifecycle: `register_activation_hook`, `register_deactivation_hook`, `register_uninstall_hook`. Activation creates 6 database tables via `dbDelta()` (contexts, conversations, chunks, usage_daily, tool_states, bots), seeds default options via `add_option()`, conditionally seeds default bot via flag, syncs local tools to DB. Deactivation is empty (data preserved). Uninstall deletes only 3 options (API key, color, position) -- all tables and most options survive uninstall. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

### FEAT-AXI-MIG-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-MIG-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-MIG-AXI-001` |
| `source_path` | `axiachat-ai/axiachat-ai.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Schema versioning and upgrade routines` |
| `capability_summary` | Inline schema evolution during activation: uses `SHOW COLUMNS` and `SHOW INDEX` to detect missing columns and outdated indexes, applies `ALTER TABLE` for missing `chunk_index` column, replaces unique keys (old `unique_post_context` to new `uniq_post_ctx_chunk`). All operations are idempotent (check-before-apply). No stored version option -- version constant (`AICHAT_VERSION = 3.0.2`) used only for asset cache-busting. No version comparison logic. Tables created with `IF NOT EXISTS` via `dbDelta()`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

### FEAT-AXI-MIG-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-MIG-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-MIG-AXI-001` |
| `source_path` | `axiachat-ai/axiachat-ai.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Settings migration and default options` |
| `capability_summary` | Default options created via `add_option()` on activation: API keys, chat color, position, branding (enabled, text, URL, icon), security policy (elaborate JSON defaults), datetime injection, user context injection. Easy Config redirect flag set on first activation. Conditional bot seeding via `aichat_default_bot_seeded` flag prevents duplicate default bots. Tool sync via `aichat_sync_local_tools_to_db()` ensures DB reflects local tool definitions. No settings migration between versions -- options are additive only via `add_option()` (no `update_option()` for existing keys). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-MIG-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-MIG-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-MIG-MXC-001` |
| `source_path` | `mxchat-basic/mxchat-basic.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Plugin lifecycle management` |
| `capability_summary` | Two-hook lifecycle: `register_activation_hook` and `register_deactivation_hook` (no uninstall hook). Activation creates 8+ tables via `dbDelta()` (transcripts, knowledge base, Pinecone roles, intents, processing queue + meta, live agent conversations), seeds default options, sets up cron jobs. Deactivation clears all cron jobs via `wp_clear_scheduled_hook()` and removes fallback flags but preserves all tables and data. No uninstall hook -- data persists indefinitely even after plugin removal. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

### FEAT-MXC-MIG-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-MIG-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-MIG-MXC-001` |
| `source_path` | `mxchat-basic/mxchat-basic.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Schema versioning and upgrade routines` |
| `capability_summary` | Most sophisticated migration system among 3 sources. Version stored in `mxchat_plugin_version` option, compared against current constant on every page load via `mxchat_check_for_update()` (init hook). 10+ version-conditional migrations: v2.1.8 (orphaned history cleanup), v2.4.1 (role_restriction column), v2.4.4 (enabled_bots JSON), v2.5.1 (deprecated Claude models), v2.5.2 (queue tables + URL fix + Pinecone bot_id), v2.5.6 (content_type column), v2.6.0 (rag_context column), v3.0.5 (Gemini embedding model), v3.0.6 (deprecated OpenAI/Claude models). Safety net: re-runs full activation if critical tables missing regardless of version. Version stored after all migrations complete. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

### FEAT-MXC-MIG-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-MIG-003` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-MIG-MXC-001` |
| `source_path` | `mxchat-basic/mxchat-basic.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Settings migration, cron lifecycle, and data cleanup` |
| `capability_summary` | Settings initialized via `add_option('mxchat_options', [...])` with nested config. Deprecated model migration maps old model IDs to current: Claude models, OpenAI models, Gemini embedding model. Cron lifecycle: schedules hourly rate limit reset, daily transcript cleanup (3 AM, configurable), delayed transcript send. WP-Cron fallback: detects disabled WP-Cron, sets fallback flags with manual-trigger timestamps. Live agent status value migration (`online/offline` to `on/off`). Orphaned chat history cleanup for pre-2.1.8 entries. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-MIG-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-MIG-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-MIG-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Plugin lifecycle management` |
| `capability_summary` | Three-hook lifecycle with best uninstall design among sources: `register_activation_hook`, `register_deactivation_hook`, `register_uninstall_hook`. Activation registers CPT, creates content index table, seeds 24 default options. Deactivation clears transients and flushes rewrite rules. Uninstall checks `current_user_can('activate_plugins')` permission, reads `baachal_preserve_data_on_uninstall` flag (default: preserve), then conditionally deletes all options, drops table, force-deletes all CPT posts, clears transients. Fallback `uninstall.php` duplicates cleanup logic. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-BAA-MIG-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-MIG-002` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-MIG-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Schema versioning and upgrade routines` |
| `capability_summary` | [N/A] No formal schema versioning. Version constant (`BAACHAL_VERSION = 1.0.4`) used for asset cache-busting only. No stored version option, no version comparison, no version-conditional migrations. Content index table created with `IF NOT EXISTS` on activation and also checked in indexer constructor. The only "migration" is `migrate_provider_settings()` which runs during activation to map old Gemini-only options to multi-provider format -- but this is not version-gated. |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `na_reason` | `No stored version tracking, no version comparison logic, no version-conditional upgrade routines. Single provider migration is activation-only and not version-gated.` |

---

### FEAT-BAA-MIG-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-MIG-003` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-MIG-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Settings migration and data cleanup` |
| `capability_summary` | Provider settings migration maps deprecated Gemini model IDs to current: `gemini-2.0-flash-exp` -> `gemini-2.5-flash`, `gemini-1.5-flash` -> `gemini-2.5-flash`, `gemini-1.5-pro` -> `gemini-2.5-pro`, `gemini-pro` -> `gemini-2.5-flash`. Migrates old API key field to new multi-provider field if needed. Default options (24) created via `add_option()`. User-controllable data preservation flag (`baachal_preserve_data_on_uninstall`) determines uninstall behavior. Uninstall cleanup: LIKE pattern option deletion, table drop, CPT post force-deletion, transient cleanup. Fallback `uninstall.php` uses explicit 24-option name list. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 3 | 3 | 0 |
| `SRC-MXC` | 3 | 3 | 0 |
| `SRC-BAA` | 3 | 2 | 1 (schema versioning) |
| **Total** | **9** | **8** | **1** |
