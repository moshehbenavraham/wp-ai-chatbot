# Migration Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S02-002` |
| `session_id` | `phase01-session02-runtime-and-migration-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-MIGRATION"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-MIGRATION` - `migration`

**Slice Count**: 5 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

---

### Slice 1 - AxiaChat Plugin Bootstrap and Lifecycle

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-002-MIG-01` |
| `anchor_id` | `ANC-MIG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/axiachat-ai.php` |
| `symbol_or_scope` | `aichat_activation()`, `aichat_deactivation()`, `aichat_uninstall()`, schema creation, default options |
| `excerpt_summary` | Main plugin bootstrap (version 3.0.2) with lifecycle hooks. Activation creates 6 database tables via `dbDelta()`: wp_aichat_contexts (RAG embeddings), wp_aichat_conversations (chat history with tokens/costs), wp_aichat_chunks (indexed content with UNIQUE KEY on post_id+context+chunk_index), wp_aichat_usage_daily (aggregated daily usage), wp_aichat_tool_states (tool pending handshake), wp_aichat_bots (bot configuration). Default options via `add_option()`: API keys, chat color, position, branding, security policy, datetime/context injection flags. Conditional bot seeding via `aichat_default_bot_seeded` flag. Tool sync via `aichat_sync_local_tools_to_db()`. Inline schema evolution: checks for missing columns (`chunk_index`) and replaces unique keys via `SHOW COLUMNS`/`SHOW INDEX`. Deactivation is empty (data preserved). Uninstall deletes only 3 options (API key, color, position) -- tables preserved. Version constant `AICHAT_VERSION` used for asset cache-busting only; no stored version option or version comparison. |
| `excerpt_lines` | `L1-L1400` (lifecycle-relevant portions) |
| `token_estimate` | `9000` |
| `notes` | No formal version tracking. Schema evolution is idempotent (IF NOT EXISTS + ALTER TABLE checks). 6 tables is the most complex schema among 3 sources. Minimal uninstall cleanup -- tables survive uninstall. |

---

### Slice 2 - MxChat Plugin Bootstrap and Version Migration

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-002-MIG-02` |
| `anchor_id` | `ANC-MIG-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/mxchat-basic.php` |
| `symbol_or_scope` | `mxchat_activate()`, `mxchat_deactivate()`, `mxchat_check_for_update()`, version-conditional migrations |
| `excerpt_summary` | Plugin bootstrap with the most sophisticated migration system among the 3 sources. Version tracking via stored option `mxchat_plugin_version` compared against current constant. Update check runs on every page load via `init` hook (`mxchat_check_for_update()`). Creates 8+ tables via `dbDelta()`: transcripts, knowledge base, Pinecone roles, intents, processing queue (+ meta), live agent conversations. Version-conditional migrations: v2.1.8 (orphaned history cleanup), v2.4.1 (role_restriction column), v2.4.4 (enabled_bots JSON column), v2.5.1 (deprecated Claude models), v2.5.2 (queue tables + URL column fix + Pinecone bot_id migration), v2.5.6 (content_type column), v2.6.0 (rag_context column), v3.0.5 (Gemini embedding model), v3.0.6 (deprecated OpenAI/Claude models). Post-migration: re-runs activation to ensure all tables present, migrates live agent status values, then stores current version. Cron setup: hourly rate limit reset, daily transcript cleanup (3 AM, if enabled), delayed transcript send. Fallback for disabled WP-Cron via flag + next-check timestamp. Deactivation clears all cron jobs and fallback flags; preserves tables and data. No uninstall hook -- data persists indefinitely. |
| `excerpt_lines` | `L1-L1400` (lifecycle + migration methods) |
| `token_estimate` | `12000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | 10+ version-conditional migrations spanning 1.5 years of releases. Most robust version tracking. Safety net: if tables missing, re-runs full activation regardless of version. No uninstall hook is a notable gap. Cron management is migration-adjacent (lifecycle concern). |

---

### Slice 3 - Baachal Plugin Bootstrap and Lifecycle

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-002-MIG-03` |
| `anchor_id` | `ANC-MIG-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php` |
| `symbol_or_scope` | `Baachal::activate_plugin()`, `Baachal::deactivate_plugin()`, `Baachal::uninstall_plugin()`, `set_default_options()`, `migrate_provider_settings()` |
| `excerpt_summary` | Plugin bootstrap (version 1.0.4) with complete lifecycle hooks. Activation: registers `baachal_conversation` custom post type, creates content index table (`wp_baachal_content_index` with FULLTEXT index), calls `set_default_options()` which creates 24 options via `add_option()` (enabled, title, welcome message, provider/model, UI options, WooCommerce integration, content indexing, `baachal_preserve_data_on_uninstall` flag). Provider migration: `migrate_provider_settings()` detects old Gemini-only options and maps deprecated model IDs to current (gemini-2.0-flash-exp -> gemini-2.5-flash, gemini-1.5-flash -> gemini-2.5-flash, etc.). Deactivation: clears `_transient_baachal_*` transients, flushes rewrite rules, preserves data. Uninstall: checks `current_user_can('activate_plugins')`, reads `baachal_preserve_data_on_uninstall` flag; if not preserving: deletes all `baachal_%` options, drops content index table, force-deletes all `baachal_conversation` posts, clears transients. |
| `excerpt_lines` | `L1-L350` (lifecycle methods) |
| `token_estimate` | `4500` |
| `notes` | Simplest migration but best uninstall design (user-controllable data preservation flag, capability check). Provider migration is one-time activation-only (no version tracking). Custom post type for conversations means WordPress handles some data lifecycle automatically. |

---

### Slice 4 - Baachal Uninstall File

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-002-MIG-04` |
| `anchor_id` | `ANC-MIG-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/uninstall.php` |
| `symbol_or_scope` | Standalone uninstall script |
| `excerpt_summary` | Fallback uninstall file (111 lines) for when plugin class is unavailable. Checks `WP_UNINSTALL_PLUGIN` constant. Attempts to call `Baachal::uninstall_plugin()` if class loaded; otherwise duplicates cleanup logic directly: deletes 24+ specific options by name (not LIKE pattern), drops `wp_baachal_content_index` table, deletes all `baachal_conversation` posts via `wp_delete_post($post->ID, true)`, clears transients. Respects `baachal_preserve_data_on_uninstall` flag. |
| `excerpt_lines` | `L1-L111` |
| `token_estimate` | `1500` |
| `notes` | Redundant with class uninstall method but provides safety net. Uses explicit option name list (24 entries) instead of LIKE pattern -- more predictable but requires maintenance on new options. |

---

### Slice 5 - MxChat Cron and Background Lifecycle

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S02-002-MIG-05` |
| `anchor_id` | `ANC-MIG-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/mxchat-basic.php` |
| `symbol_or_scope` | `mxchat_setup_cron_jobs()`, `mxchat_cleanup_old_transcripts()`, cron fallback logic |
| `excerpt_summary` | Cron-based lifecycle management. Schedules: `mxchat_reset_rate_limits` (hourly), `mxchat_cleanup_old_transcripts` (daily at 3 AM if auto-delete enabled), `mxchat_send_delayed_transcript`. WP-Cron fallback: detects disabled WP-Cron, sets `mxchat_use_fallback_rate_limits` flag with `mxchat_next_rate_limit_check` timestamp for manual-trigger rate limit resets. Transcript cleanup uses configurable retention period. All cron hooks cleared on deactivation via `wp_clear_scheduled_hook()`. |
| `excerpt_lines` | `L1300-L1380` (cron methods) |
| `token_estimate` | `2000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Cron management is lifecycle/migration adjacent -- scheduled jobs must be cleaned on deactivation and re-established on reactivation. Fallback for disabled WP-Cron is a resilience pattern worth noting for the combined plugin. |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `5` |
| `total_token_estimate` | `29000` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-MIGRATION` | `CP-P01-S02-002-MIG-01` | `CP-P01-S02-002-MIG-02`, `MIG-05` | `CP-P01-S02-002-MIG-03`, `MIG-04` |

### Gaps and Risks

- **Version tracking asymmetry**: SRC-MXC has formal version comparison with stored option; SRC-AXI and SRC-BAA use constants only with no stored version. The combined plugin should adopt SRC-MXC's pattern.
- **Uninstall coverage gap**: SRC-MXC has no uninstall hook (data persists forever). SRC-AXI deletes only 3 options (tables survive). SRC-BAA has the most thorough uninstall with user-controllable data preservation flag.
- **Schema complexity**: SRC-AXI (6 tables), SRC-MXC (8+ tables), SRC-BAA (1 table + CPT). Combined plugin's migration system must handle merging/reconciling these schemas.
- **Migration maturity**: SRC-MXC has 10+ version-conditional migrations across 6+ release versions -- a proven pattern for incremental schema evolution. SRC-AXI and SRC-BAA have ad-hoc or one-time migrations.
- **Cron lifecycle**: Only SRC-MXC manages scheduled jobs as part of lifecycle. Combined plugin will need cron management in activation/deactivation hooks.
