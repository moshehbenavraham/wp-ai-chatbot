# Option Migration Framework

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-MIG-003

---

## 1. Overview

The option migration framework (`includes/migration/class-option-migrator.php`) provides a
versioned, rollback-aware, idempotent mechanism for migrating WordPress options from source
plugin formats to WPAIC format. It operates on top of the migration registry
(`class-migration-registry.php`, session 02) and is called as part of the plugin upgrade
lifecycle.

This framework resolves CFI-MIG-003: "Generic option migration framework (key mapping, value
transforms)."

---

## 2. Option Migrator Interface

```php
namespace WPAIC\Migration;

class Option_Migrator {

    /**
     * Define a migration mapping from source option(s) to target option.
     *
     * @param string   $source_key     Source option key (e.g., 'aichat_openai_api_key').
     * @param string   $target_key     Target option key (e.g., 'wpaic_provider_openai_key').
     * @param callable $transform      Transform function: function($raw_value): mixed
     *                                 Receives raw WP option value; returns transformed value.
     *                                 Return null to skip writing target (no source data).
     * @param array    $options        Migration options:
     *                                   'delete_source' => bool (default false -- delete after migration)
     *                                   'encrypt_target' => bool (default false -- encrypt output value)
     */
    public function define(
        string $source_key,
        string $target_key,
        callable $transform,
        array $options = []
    ): void;

    /**
     * Define a multi-source mapping (e.g., blob decomposition for MxChat).
     *
     * @param string   $source_key     Source blob option key (e.g., 'mxchat_options').
     * @param string   $sub_key        Sub-key within the blob array.
     * @param string   $target_key     Target option key.
     * @param callable $transform      Transform function applied to $source_blob[$sub_key].
     * @param array    $options        Same as define().
     */
    public function define_from_blob(
        string $source_key,
        string $sub_key,
        string $target_key,
        callable $transform,
        array $options = []
    ): void;

    /**
     * Execute all defined migrations.
     *
     * @param bool $dry_run  If true, return report without writing anything.
     * @return Migration_Report
     */
    public function execute( bool $dry_run = false ): Migration_Report;

    /**
     * Check if migration has already run (idempotency guard).
     * Uses wpaic_migration_completed_{migration_id} option as sentinel.
     *
     * @param string $migration_id  Unique ID for this migration set.
     * @return bool
     */
    public function has_run( string $migration_id ): bool;

    /**
     * Mark migration as complete (call after execute() succeeds).
     *
     * @param string $migration_id
     */
    public function mark_complete( string $migration_id ): void;
}
```

---

## 3. Migration Report Contract

```php
class Migration_Report {
    public bool   $dry_run;
    public int    $total_mappings;
    public int    $migrated;           // Successfully written to target
    public int    $skipped;            // Source empty or transform returned null
    public int    $already_set;        // Target already had a value (not overwritten)
    public int    $errors;             // Transform threw or wp_update_option failed
    public array  $details;            // Per-mapping: ['source', 'target', 'status', 'note']
}
```

---

## 4. Transform Function Contract

Transform functions receive the raw value from `get_option($source_key)` and return the value
to write to `update_option($target_key, ...)`. Rules:

- Return `null` to skip writing (source empty, not applicable)
- Return the transformed value (any WP-serializable type) to write
- MUST NOT throw exceptions (use try/catch internally; return null on failure)
- MUST be idempotent (calling twice with same input returns same output)
- MUST handle all possible source value shapes (missing = false, old format, new format)

### 4.1 Built-In Transform Helpers

```php
// Included in class-option-migrator.php as static helpers:

// Pass-through (no transform needed)
Option_Migrator::identity()  // returns fn($v) => $v ?: null

// Cast to boolean
Option_Migrator::to_bool()   // returns fn($v) => (bool)$v

// Cast to integer with bounds
Option_Migrator::to_int(int $min, int $max)

// Cast to float with bounds
Option_Migrator::to_float(float $min, float $max)

// String trim + sanitize
Option_Migrator::to_string()  // returns fn($v) => sanitize_text_field((string)$v) ?: null

// Map source string value to target string value
Option_Migrator::map_value(array $map, $default = null)
// Example: Option_Migrator::map_value(['gpt-4' => 'gpt-4o', 'gpt-3.5' => 'gpt-3.5-turbo'])
```

---

## 5. Idempotency Strategy

Each migration set is guarded by a sentinel option:
```
wpaic_migration_completed_{migration_id} = '1'
```

On `execute()`:
1. Check `has_run($migration_id)` -- if true, return empty report (already done)
2. Run all mappings
3. Call `mark_complete($migration_id)`

Per-field idempotency (target-already-set check):
- If `get_option($target_key)` already returns a non-empty value: status = `already_set`,
  do not overwrite (unless `force_overwrite` option passed)

---

## 6. Rollback Strategy

The framework does not delete source options during migration (unless `delete_source = true`
is explicitly passed). This means rollback is available by:
1. Deactivating the plugin
2. Re-activating the source plugin (its options are still present in `wp_options`)

When `delete_source = true` is used (Phase 10 cleanup pass), a backup of source options is
written to `wpaic_migration_backup_{migration_id}` before deletion.

---

## 7. Migration Ordering Within Registry

Migration callbacks registered via `Migration_Registry` follow version ordering:

```
v1.0.0: axiachat_migration   -- migrate AXI options (highest complexity)
v1.0.0: mxchat_migration     -- migrate MXC options (blob decomposition)
v1.0.0: baachal_migration    -- migrate BAA options (simple key renames)
v1.0.0: encryption_pass      -- encrypt all plaintext sensitive fields
```

The encryption pass runs LAST (after all key migrations) to handle both native WPAIC fields
and migrated-from-source fields in one pass.

---

## 8. CFI-MIG-003 Resolution

| Requirement | Resolution | Section |
|-------------|-----------|---------|
| Key mapping (source to target) | define() and define_from_blob() methods | Section 2 |
| Value transforms | callable transform + built-in helpers | Sections 4, 4.1 |
| Versioned execution | Integrates with Migration_Registry version order | Section 7 |
| Rollback awareness | Source options retained by default; backup on delete | Section 6 |
| Idempotency | Sentinel option + already_set check | Section 5 |
| Dry-run mode | execute(dry_run=true) returns report without writes | Section 2 |
| Blob decomposition (MxChat) | define_from_blob() for sub-key extraction | Section 2 |

**Status**: CFI-MIG-003 fully resolved.

---

## 9. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Versioned migration callbacks | `mxchat-basic/includes/class-mxchat-admin.php` version check pattern | Adopted; formalized in Migration_Registry |
| Idempotent migration sentinel | `axiachat-ai/axiachat-ai.php` version check in activation | Adopted; extended with per-migration IDs |
| Clean source option removal | `baachal/uninstall.php` delete_option patterns | Adapted for selective per-migration cleanup |
