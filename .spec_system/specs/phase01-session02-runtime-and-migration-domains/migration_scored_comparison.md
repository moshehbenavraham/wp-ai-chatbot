# Migration Scored Comparison

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Domain**: `DOM-MIGRATION`
**Context Pack**: `CP-P01-S02-002`
**Scoring Rubric**: `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
**Created**: 2026-03-01

---

## Scoring Scale Reference

| Score | Meaning |
|---|---|
| 0 | Not present or unusable |
| 1 | Bare-minimum with major limits |
| 2 | Partial with clear constraints |
| 3 | Solid for common use cases |
| 4 | Strong with low friction |
| 5 | Best-in-class among reference plugins |

**Weighted Total**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

---

## Capability 1: Plugin Lifecycle Management

Comparison of activate/deactivate/uninstall hook implementations.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-MIG-001` | `SRC-AXI` | 4 | 4 | 3 | 3 | 3 | 3.50 | `investigate` |
| `FEAT-MXC-MIG-001` | `SRC-MXC` | 4 | 4 | 2 | 3 | 3 | 3.30 | `reject` |
| `FEAT-BAA-MIG-001` | `SRC-BAA` | 5 | 4 | 5 | 4 | 3 | 4.35 | `adopt` |

### Evidence Notes

#### FEAT-AXI-MIG-001 - SRC-AXI

- Coverage: 4 - Three-hook lifecycle (activate, deactivate, uninstall). Activation creates 6 tables, seeds default options, seeds default bot, syncs tools. Good activation coverage. But deactivation is empty and uninstall deletes only 3 of 20+ options.
- Reliability: 4 - `dbDelta()` with IF NOT EXISTS ensures idempotent table creation. Conditional bot seeding prevents duplicates. Tool sync is repeatable. Tables survive deactivation and uninstall.
- Security: 3 - No capability checks in activation (standard WP pattern). Uninstall hook runs in WP context (inherent security). But tables and most options survive uninstall, leaving data on disk.
- Operability: 3 - Easy Config redirect on first activation. Default options provide working baseline. But no feedback on activation success/failure beyond WP admin notice.
- Extensibility: 3 - No hooks for activation/deactivation lifecycle events. Adding new tables requires modifying activation function directly.

- Weighted Summary: 3.50 (investigate)
- Recommendation: investigate
- Residual Risks: Minimal uninstall cleanup leaves data residue. No deactivation logic means cron/transient cleanup is deferred. No lifecycle hooks for extensions.

#### FEAT-MXC-MIG-001 - SRC-MXC

- Coverage: 4 - Two-hook lifecycle (activate, deactivate -- no uninstall). Activation creates 8+ tables, seeds options, sets up cron jobs with WP-Cron fallback. Deactivation clears cron jobs and fallback flags. Most complex activation among sources.
- Reliability: 4 - `dbDelta()` for tables. Cron setup with disabled-WP-Cron fallback. Safety net re-runs activation if tables missing. Deactivation properly cleans up scheduled events.
- Security: 2 - No uninstall hook -- data (including API keys in options, chat transcripts) persists indefinitely after plugin removal. This is a significant privacy/security gap. No capability checks.
- Operability: 3 - Cron fallback ensures rate limiting works even with WP-Cron disabled. But 8+ tables and 70+ options create operational complexity.
- Extensibility: 3 - No lifecycle hooks. Adding new cron jobs requires modifying setup function. Cron abstraction (fallback pattern) is reusable.

- Weighted Summary: 3.30 (reject -- DIM-SECURITY=2 triggers automatic rejection per rubric)
- Recommendation: reject
- Residual Risks: Missing uninstall hook is a critical gap for GDPR compliance and data cleanup. API keys left on disk after removal.

#### FEAT-BAA-MIG-001 - SRC-BAA

- Coverage: 5 - Three-hook lifecycle with the most complete uninstall among sources. Activation registers CPT, creates table, seeds 24 options. Deactivation clears transients and flushes rewrites. Uninstall checks capability, reads user-preference flag, conditionally cleans all options, drops table, deletes all CPT posts, clears transients. Fallback uninstall.php with duplicate logic.
- Reliability: 4 - Uninstall fallback file ensures cleanup even if class is unavailable. `add_option()` is idempotent. CPT registration in activation ensures rewrite rules are current.
- Security: 5 - Uninstall checks `current_user_can('activate_plugins')`. User-controllable `baachal_preserve_data_on_uninstall` flag (default: preserve). When not preserving, thorough cleanup of all plugin artifacts. Best security/privacy posture for lifecycle.
- Operability: 4 - User-facing data preservation choice. Clean transient cleanup on deactivation. Fallback uninstall.php as safety net. Clear separation of lifecycle concerns.
- Extensibility: 3 - No lifecycle hooks. Uninstall cleanup list must be manually maintained (24 explicit option names). Adding new options requires updating both class method and uninstall.php.

- Weighted Summary: 4.35 (adopt)
- Recommendation: adopt
- Residual Risks: Dual-path uninstall (class + file) requires maintenance synchronization. Default preserves data (good for user intent but potential GDPR concern if admin forgets).

---

## Capability 2: Schema Versioning and Upgrade Routines

Comparison of version tracking and database migration patterns.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-MIG-002` | `SRC-AXI` | 3 | 3 | 3 | 2 | 2 | 2.70 | `reject` |
| `FEAT-MXC-MIG-002` | `SRC-MXC` | 5 | 5 | 3 | 4 | 4 | 4.30 | `adopt` |
| `FEAT-BAA-MIG-002` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-MIG-002 - SRC-AXI

- Coverage: 3 - Inline schema evolution via `SHOW COLUMNS`/`SHOW INDEX` checks. Handles missing columns and outdated indexes. `dbDelta()` for table creation. But no stored version, no version comparison, no formal migration registry.
- Reliability: 3 - Idempotent operations (check-before-apply). IF NOT EXISTS on tables. But no guarantee that all schema changes are discovered -- relies on manual `SHOW` queries per known change.
- Security: 3 - No security concerns with schema checks. ALTER TABLE operations are safe. But no rollback capability.
- Operability: 2 - No version tracking means no way to know what migrations have run. No migration logging. No admin visibility into schema state.
- Extensibility: 2 - Adding a new migration requires adding inline code to activation function. No migration registry, no migration ordering, no hook for extensions to register their own migrations.

- Weighted Summary: 2.70 (reject - below threshold)
- Recommendation: reject
- Residual Risks: Ad-hoc approach will not scale as schema evolves. No rollback. No migration history.

#### FEAT-MXC-MIG-002 - SRC-MXC

- Coverage: 5 - Stored version option with comparison on every page load. 10+ version-conditional migrations spanning v2.1.8 to v3.0.6. Column additions, table creation, data migrations (deprecated models), orphaned data cleanup, value format migrations. Safety net re-runs activation if tables missing.
- Reliability: 5 - Version comparison ensures migrations run exactly once. Safety net catches edge cases (manual table deletion). Version stored after all migrations complete (prevents partial state). Post-migration activation ensures table consistency.
- Security: 3 - No security concerns with migration logic. Migrations run in admin context. But deprecated model data migrations could theoretically corrupt active settings if buggy.
- Operability: 4 - Version check on every page load catches missed upgrades (e.g., after manual file replacement). Clear migration function naming by version. Version stored in options (queryable).
- Extensibility: 4 - Version-conditional pattern is extensible -- new migrations just add a new version check block. But no formal migration registry or hook for addons to register migrations.

- Weighted Summary: 4.30 (adopt)
- Recommendation: adopt
- Residual Risks: Page-load version check adds minor overhead. No formal migration registry -- just sequential version checks. No rollback mechanism.

#### FEAT-BAA-MIG-002 - SRC-BAA

- Coverage: 0 - No schema versioning. No stored version. No version comparison. No version-conditional migrations. Single provider migration runs on every activation regardless of version.
- Reliability: 0 - N/A (not implemented).
- Security: 0 - N/A (not implemented).
- Operability: 0 - N/A (not implemented).
- Extensibility: 0 - N/A (not implemented).

- Weighted Summary: 0.00 (reject - not implemented)
- Recommendation: reject
- Residual Risks: N/A entry. Without version tracking, schema changes would need to be idempotent-only, limiting migration capabilities.

---

## Capability 3: Settings Migration, Compatibility Repair, and Data Cleanup

Comparison of option migration, deprecated value handling, and data maintenance.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-MIG-003` | `SRC-AXI` | 3 | 3 | 3 | 3 | 2 | 2.85 | `investigate` |
| `FEAT-MXC-MIG-003` | `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 | `adopt` |
| `FEAT-BAA-MIG-003` | `SRC-BAA` | 4 | 3 | 4 | 4 | 3 | 3.65 | `investigate` |

### Evidence Notes

#### FEAT-AXI-MIG-003 - SRC-AXI

- Coverage: 3 - Default options via `add_option()` on activation. Conditional bot seeding. Tool sync to DB. But no settings migration between versions -- options are additive only. No deprecated option handling. No option cleanup.
- Reliability: 3 - `add_option()` is safe (won't overwrite existing). Tool sync is repeatable. But no mechanism to update existing option values when defaults change between versions.
- Security: 3 - Security policy defaults are well-structured JSON. API key handling through WP options (standard). No sensitive data in default values.
- Operability: 3 - Easy Config redirect on first activation provides guided setup. Default options create a working baseline. But no migration status reporting.
- Extensibility: 2 - No hooks for settings migration. No option mapping registry. Adding new defaults requires modifying activation function.

- Weighted Summary: 2.85 (investigate)
- Recommendation: investigate
- Residual Risks: No option migration means stale defaults persist from initial activation. New version features relying on updated defaults won't work for upgrading users.

#### FEAT-MXC-MIG-003 - SRC-MXC

- Coverage: 5 - Deprecated model migration (Claude, OpenAI, Gemini). Orphaned chat history cleanup (pre-2.1.8). Live agent status value migration. Cron lifecycle management with WP-Cron fallback. Nested options config initialization. Most comprehensive settings migration.
- Reliability: 4 - Model migrations map specific old IDs to new IDs (deterministic). Orphaned cleanup uses targeted deletion. Cron fallback handles edge case (WP-Cron disabled). But model mapping is hardcoded per version.
- Security: 3 - Model migrations don't touch security-sensitive options. Cron cleanup is safe. But no validation that migrated values are valid (trusts mapping table).
- Operability: 4 - Cron management handles activation/deactivation lifecycle. Fallback rate limiting ensures functionality even with WP-Cron disabled. Version-gated migrations are predictable.
- Extensibility: 3 - Model mapping pattern is extensible (add new mapping per version). Cron setup function is callable. But no hook for addon-specific migrations.

- Weighted Summary: 3.95 (adopt)
- Recommendation: adopt
- Residual Risks: Hardcoded model mappings require maintenance each time providers deprecate models. No generic option value migration framework.

**Cross-domain note**: Cron lifecycle management connects to DOM-ADMIN (scheduled maintenance) and DOM-RUNTIME (rate limiting). Settings migration of deprecated models connects to DOM-PROVIDER (model registry from Session 01).

#### FEAT-BAA-MIG-003 - SRC-BAA

- Coverage: 4 - Provider settings migration with model ID mapping (4 Gemini models). API key field migration (old to new). 24 default options via `add_option()`. User-controllable data preservation on uninstall. Thorough cleanup when not preserving (options, table, CPT posts, transients). Fallback uninstall with explicit option list.
- Reliability: 3 - Model mapping is deterministic. `add_option()` is safe. But provider migration runs on every activation (not version-gated), which is wasteful though harmless.
- Security: 4 - `current_user_can('activate_plugins')` check on uninstall. Data preservation flag gives admin control. When cleaning up, uses both LIKE pattern and explicit list for thoroughness.
- Operability: 4 - Data preservation UX is user-friendly (default: preserve). Clean transient cleanup on deactivation. Explicit option list in uninstall.php provides clarity.
- Extensibility: 3 - Provider migration pattern is extensible (add new mappings). But explicit option list in uninstall.php requires manual maintenance. No hook for addon cleanup.

- Weighted Summary: 3.65 (investigate)
- Recommendation: investigate
- Residual Risks: Provider migration not version-gated (runs every activation). Dual-path uninstall requires synchronization. Limited to provider settings only.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 3.33 | 3.33 | 3.00 | 2.67 | 2.33 | 3.02 | `investigate` |
| `SRC-MXC` | 4.67 | 4.33 | 2.67 | 3.67 | 3.33 | 3.85 | `investigate` |
| `SRC-BAA` | 3.00 | 2.33 | 3.00 | 2.67 | 2.00 | 2.67 | `reject` |

**Notes on aggregation**: Averages computed across the 3 capabilities scored above, giving equal weight to each capability. SRC-MXC leads on schema versioning (4.25) and settings migration (3.90). SRC-BAA's aggregate is pulled down by the schema versioning N/A (0 scores); excluding that capability, SRC-BAA averages 3.95 (adopt). SRC-AXI is consistently investigate-level across all capabilities.

**Important caveat**: Like the runtime domain, one N/A capability (SRC-BAA schema versioning) has outsized impact on the aggregate. The decision log should assess per-capability winners rather than relying solely on aggregates.
