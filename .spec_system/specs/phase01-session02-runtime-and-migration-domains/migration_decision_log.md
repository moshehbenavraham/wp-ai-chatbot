# Migration Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S02-MIG` |
| `session_id` | `phase01-session02-runtime-and-migration-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-MIGRATION"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S02-002` |

---

## Entry: `DEC-MIG-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-MIG-001` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Plugin lifecycle management` |
| `capability_summary` | WordPress plugin activation, deactivation, and uninstall hook implementations covering table creation, default option seeding, cron setup, transient cleanup, and data removal. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-MIG-AXI-001` | `axiachat-ai/axiachat-ai.php` | `FEAT-AXI-MIG-001` | `implemented` |
| `SRC-MXC` | `ANC-MIG-MXC-001` | `mxchat-basic/mxchat-basic.php` | `FEAT-MXC-MIG-001` | `implemented` |
| `SRC-BAA` | `ANC-MIG-BAA-001` | `baachal/baachal.php`, `baachal/uninstall.php` | `FEAT-BAA-MIG-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 3 | 3 | 3 | 3.50 |
| `SRC-MXC` | 4 | 4 | 2 | 3 | 3 | 3.30 (reject: DIM-SECURITY=2) |
| `SRC-BAA` | 5 | 4 | 5 | 4 | 3 | 4.35 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-BAA` |
| `recommendation_band` | `adopt` |
| `rationale` | Baachal has the most complete lifecycle implementation: three-hook coverage (activate, deactivate, uninstall), capability checks on uninstall, user-controllable data preservation flag, fallback uninstall.php, thorough cleanup (options, tables, CPT, transients). SRC-MXC's missing uninstall hook is a critical GDPR gap. SRC-AXI's minimal uninstall (3 of 20+ options) leaves significant data residue. The combined plugin should adopt SRC-BAA's uninstall pattern with SRC-AXI's multi-table activation and SRC-MXC's cron setup. |
| `modification_notes` | Adopt SRC-BAA uninstall design (capability check, data preservation flag, comprehensive cleanup, fallback file). Adopt SRC-AXI activation pattern for multi-table schema creation via `dbDelta()`. Integrate SRC-MXC cron lifecycle (setup on activate, cleanup on deactivate). Add lifecycle hooks for extensions to register their own activation/deactivation/uninstall handlers. |
| `carry_forward_ids` | `["CFI-MIG-001"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-SECURITY"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Combined plugin will need unified activation creating all tables from all 3 sources. Uninstall must handle cleanup of all data artifacts. Data preservation flag should be admin-configurable. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-MIG-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-MIG-002` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Schema versioning and upgrade routines` |
| `capability_summary` | Version tracking via stored options, version-conditional database schema migrations, data migrations for deprecated values, and safety mechanisms for missed upgrades. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-MIG-AXI-001` | `axiachat-ai/axiachat-ai.php` | `FEAT-AXI-MIG-002` | `implemented` |
| `SRC-MXC` | `ANC-MIG-MXC-001` | `mxchat-basic/mxchat-basic.php` | `FEAT-MXC-MIG-002` | `implemented` |
| `SRC-BAA` | `ANC-MIG-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-MIG-002` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 3 | 3 | 3 | 2 | 2 | 2.70 |
| `SRC-MXC` | 5 | 5 | 3 | 4 | 4 | 4.30 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat's version-conditional migration system is the only production-proven approach among the 3 sources, with 10+ migrations spanning 6+ release versions. Stored version option, page-load update check, safety net for missing tables, and version stored after completion provide robust guarantees. SRC-AXI's inline SHOW COLUMNS approach is ad-hoc and won't scale. SRC-BAA has no versioning at all. The combined plugin should formalize SRC-MXC's pattern into a migration registry. |
| `modification_notes` | Adopt SRC-MXC version-conditional pattern as the baseline. Formalize into a migration registry (ordered array of {version, callback} entries) rather than inline version checks. Add migration logging (which migrations ran, when, success/failure). Consider migration rollback mechanism (not present in any source). Add SRC-AXI's `SHOW COLUMNS` approach as a safety validation (verify schema state matches expected after migrations). |
| `carry_forward_ids` | `["CFI-MIG-002"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN"]` |
| `risk_level` | `high` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | The combined plugin's initial version must establish the migration registry with a baseline schema version. All future schema changes go through this registry. First-install path uses `dbDelta()` for all tables; upgrade path uses version-conditional migrations. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-MIG-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-MIG-003` |
| `domain_id` | `DOM-MIGRATION` |
| `capability_name` | `Settings migration, compatibility repair, and data cleanup` |
| `capability_summary` | Option value migration for deprecated models, cron lifecycle management, orphaned data cleanup, and user-controllable uninstall data handling. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-MIG-AXI-001` | `axiachat-ai/axiachat-ai.php` | `FEAT-AXI-MIG-003` | `implemented` |
| `SRC-MXC` | `ANC-MIG-MXC-001` | `mxchat-basic/mxchat-basic.php` | `FEAT-MXC-MIG-003` | `implemented` |
| `SRC-BAA` | `ANC-MIG-BAA-001` | `baachal/baachal.php` | `FEAT-BAA-MIG-003` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 3 | 3 | 3 | 3 | 2 | 2.85 |
| `SRC-MXC` | 5 | 4 | 3 | 4 | 3 | 3.95 |
| `SRC-BAA` | 4 | 3 | 4 | 4 | 3 | 3.65 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat's settings migration is the most comprehensive: deprecated model mapping across providers, orphaned data cleanup, cron lifecycle with WP-Cron fallback, and live agent status value migration. SRC-BAA's user-controllable uninstall data handling and provider settings migration pattern should be integrated. SRC-AXI's approach (additive-only via `add_option`) is insufficient for evolving settings. |
| `modification_notes` | Adopt SRC-MXC model migration pattern (version-gated deprecated model mapping). Integrate SRC-BAA data preservation flag for uninstall. Formalize option migration into the version registry (each migration can include option value transforms). Add SRC-MXC cron lifecycle management. Create a generic option migration framework (old_key -> new_key, value_transform) rather than per-migration hardcoded logic. |
| `carry_forward_ids` | `["CFI-MIG-003"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-PROVIDER", "DOM-RUNTIME"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Model deprecation mapping connects to DOM-PROVIDER model registry (DEC-PRO-003 from Session 01). Cron lifecycle connects to DOM-ADMIN and DOM-RUNTIME (rate limiting). Data cleanup patterns inform DOM-MIGRATION lifecycle design. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-MIG-001

| Field | Value |
|---|---|
| `item_id` | `CFI-MIG-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-MIGRATION` |
| `description` | Design combined plugin lifecycle with SRC-BAA uninstall pattern (capability check, data preservation flag, fallback file), SRC-AXI multi-table activation, and SRC-MXC cron lifecycle. Add lifecycle hooks for extension registration. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-MIG-001` |
| `related_anchor_ids` | `["ANC-MIG-AXI-001", "ANC-MIG-MXC-001", "ANC-MIG-BAA-001"]` |
| `resolution_criteria` | Plugin has three-hook lifecycle with data preservation flag, comprehensive cleanup, fallback uninstall file, cron management, and extension hooks for activation/deactivation/uninstall. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-MIG-002

| Field | Value |
|---|---|
| `item_id` | `CFI-MIG-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-MIGRATION` |
| `description` | Formalize SRC-MXC version-conditional migration into a migration registry with ordered callbacks, migration logging, schema validation, and optional rollback support. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-MIG-002` |
| `related_anchor_ids` | `["ANC-MIG-MXC-001"]` |
| `resolution_criteria` | Migration registry with version-ordered entries, execution logging, post-migration schema validation, and documented rollback strategy. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-MIG-003

| Field | Value |
|---|---|
| `item_id` | `CFI-MIG-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session02-runtime-and-migration-domains` |
| `domain_id` | `DOM-MIGRATION` |
| `description` | Create generic option migration framework (key mapping, value transforms, deprecated model mapping) integrated with version registry. Include SRC-BAA data preservation flag and SRC-MXC cron lifecycle. |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-MIG-003` |
| `related_anchor_ids` | `["ANC-MIG-AXI-001", "ANC-MIG-MXC-001", "ANC-MIG-BAA-001"]` |
| `resolution_criteria` | Generic option migration supports key renaming, value transformation, deprecated model mapping, and integrates with version registry. Data preservation flag is admin-configurable. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-MIGRATION x DOM-RUNTIME

- Session storage changes (CFI-RUN-002) create migration requirements: moving from WP options to dedicated session table requires data migration routines.
- Streaming capability addition requires frontend asset versioning -- migration system must handle JS enqueue changes.
- Runtime conversation logging table (SRC-AXI `wp_aichat_conversations`) is a schema concern managed by migration.

### DOM-MIGRATION x DOM-PROVIDER

- Model registry (DEC-PRO-003 from Session 01) supports migration via alias and deprecated model ID mappings. New deprecated models should register in the migration framework as version-gated option value transforms.
- Provider API key storage format changes (if any) must go through migration framework.

### DOM-MIGRATION x DOM-ADMIN

- Admin settings are the primary target of option value migrations. Settings page must handle graceful rendering when options are mid-migration.
- Data preservation flag (DEC-MIG-001) is an admin-configured setting that governs uninstall behavior.
- Cron lifecycle (DEC-MIG-003) creates scheduled admin operations (transcript cleanup, rate limit reset).

### DOM-MIGRATION x DOM-SECURITY

- Uninstall must ensure complete cleanup of sensitive data (API keys, conversation history) when user opts out of data preservation.
- Migration routines that touch security-relevant options (security policy, API keys) must preserve existing values and not reset to defaults.
- GDPR compliance requires that uninstall can fully remove personal data (chat transcripts, IP addresses, user identifiers).

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-MIG-001` | Plugin lifecycle management | `SRC-BAA` | `adopt_with_modifications` | `adopt` | 4.35 |
| `DEC-MIG-002` | Schema versioning and upgrade routines | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.30 |
| `DEC-MIG-003` | Settings migration and data cleanup | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 3.95 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 3 |
| Adopt-As-Is | 0 |
| Adopt-With-Modifications | 3 |
| Reject/Defer | 0 |
| Domain Winner | `mixed (SRC-BAA: lifecycle; SRC-MXC: versioning, settings migration)` |
| Dominant Maturity | `production_stable` |
| Key Risks | Formalized migration registry is net-new (not present in any source as-is); combined schema from 3 sources increases migration complexity; uninstall must handle all data artifacts |
| Cross-Domain Dependencies | `DOM-RUNTIME`, `DOM-PROVIDER`, `DOM-ADMIN`, `DOM-SECURITY` |
| Carry-Forward Items | `CFI-MIG-001`, `CFI-MIG-002`, `CFI-MIG-003` |
