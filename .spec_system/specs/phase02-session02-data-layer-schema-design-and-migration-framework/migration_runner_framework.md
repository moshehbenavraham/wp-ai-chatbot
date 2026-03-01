# Migration Runner Framework

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-MIG-002

---

## 1. Overview

The migration runner manages all database schema changes across the plugin's lifecycle. It combines the best patterns from the three reference plugins:

- **MxChat**: Stored version option + `version_compare()` conditional migration blocks (winner for version-gated upgrades)
- **AxiaChat**: `dbDelta()` at activation + `admin_init` per-column `SHOW COLUMNS`/`ALTER TABLE` upgrades (winner for schema creation and evolution)
- **Baachal**: Three-hook lifecycle coverage (`register_activation_hook`, `register_deactivation_hook`, `register_uninstall_hook`)

The migration module (`WPAIC\Migration`) is a **leaf module** (Level 0 in the dependency graph). It never imports from any domain module. Domain modules register their migrations through the `wpaic_register_migrations` action hook, providing versioned callbacks that the migration runner executes in deterministic order.

**Owner module**: `includes/migration/class-migration-registry.php`

---

## 2. Migration Registry Architecture

### 2.1 Hook Contract

Domain modules register migrations via the `wpaic_register_migrations` action:

```php
do_action('wpaic_register_migrations', Migration_Registry $registry);
```

This hook fires during `plugins_loaded` (priority 10) when a version mismatch is detected between the stored `wpaic_migration_version` option and the `WPAIC_VERSION` constant.

### 2.2 Migration Entry Format

Each migration entry has the following structure:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `version` | `string` | Yes | Semantic version string (e.g., `'1.0.0'`) |
| `domain` | `string` | Yes | Canonical domain ID (`provider`, `runtime`, `rag`, `admin`, `security`, `migration`) |
| `description` | `string` | Yes | Human-readable description of the migration |
| `callback` | `callable` | Yes | Idempotent callback (`Closure` or `[class, method]`) |

### 2.3 Registration Interface

```php
// Domain registers its migrations
add_action('wpaic_register_migrations', function (Migration_Registry $registry) {
    $registry->register([
        'version'     => '1.0.0',
        'domain'      => 'runtime',
        'description' => 'Create sessions and messages tables',
        'callback'    => function () {
            // CREATE TABLE via dbDelta() -- idempotent
        },
    ]);

    $registry->register([
        'version'     => '1.1.0',
        'domain'      => 'runtime',
        'description' => 'Add cost_micros column to messages table',
        'callback'    => function () {
            // ALTER TABLE ADD COLUMN -- with SHOW COLUMNS guard
        },
    ]);
});
```

### 2.4 Registration Validation

`Migration_Registry::register()` validates each entry:

| Rule | Error Behavior |
|------|----------------|
| `version` must be a valid semver string | Throws `InvalidArgumentException` |
| `domain` must be one of the 9 canonical domain IDs | Throws `InvalidArgumentException` |
| `description` must be non-empty | Throws `InvalidArgumentException` |
| `callback` must be callable | Throws `InvalidArgumentException` |
| Duplicate `(version, domain)` pair | Throws `InvalidArgumentException` |

Validation errors are logged via the structured logger and the migration is skipped (never silently ignored).

### 2.5 Execution Ordering Algorithm

Migrations are sorted and executed in the following deterministic order:

1. **Primary sort**: `version` ascending via `version_compare()` (semver ordering)
2. **Secondary sort**: Domain dependency level ascending (leaf modules first)

Domain dependency levels (from `module_dependency_graph.md`):

| Level | Domains |
|-------|---------|
| 0 (leaf) | `migration`, `security` |
| 1 | `provider` |
| 2 | `rag`, `runtime` |
| 3 | `admin` |
| 4+ (deferred) | `tools`, `integrations`, `woocommerce` |

This ensures infrastructure tables (migration log, encryption support) exist before domain-specific tables that may depend on them.

### 2.6 Execution Entry Point

Migration check runs on every `plugins_loaded` hook call:

```
1. Read stored version: get_option('wpaic_migration_version', '0.0.0')
2. Compare with WPAIC_VERSION constant
3. If versions match: return (no-op)
4. If versions differ:
   a. Acquire migration lock (see Section 5)
   b. If lock acquisition fails: return (another process is migrating)
   c. Fire wpaic_register_migrations action to collect all entries
   d. Filter to pending migrations (version > stored version)
   e. Sort by version ASC, then domain level ASC
   f. Fire wpaic_before_migration action
   g. Execute each migration in order (see Section 3 for logging)
   h. Update stored version: update_option('wpaic_migration_version', WPAIC_VERSION)
   i. Fire wpaic_after_migration action
   j. Release migration lock
```

### 2.7 Idempotency Requirements

Every migration callback MUST be idempotent:

- **Table creation**: Use `dbDelta()` which handles `CREATE TABLE IF NOT EXISTS` semantics
- **Column addition**: Check with `SHOW COLUMNS FROM {table} LIKE '{column}'` before `ALTER TABLE ADD COLUMN`
- **Index changes**: Check with `SHOW INDEX FROM {table} WHERE Key_name = '{name}'` before `DROP INDEX` / `ADD INDEX`
- **Data migrations**: Use `INSERT ... ON DUPLICATE KEY UPDATE` or existence checks

This follows the AxiaChat `admin_init` pattern where schema checks run on every admin page load and safely skip already-applied changes.

---

## 3. Migration Log Table Schema

### 3.1 Table: `{$wpdb->prefix}wpaic_migrations`

Records every migration execution attempt for auditability and debugging.

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `version` | `VARCHAR(20)` | NO | IDX | | Migration version string |
| `domain` | `VARCHAR(30)` | NO | IDX | | Domain that registered this migration |
| `description` | `VARCHAR(255)` | NO | | | Human-readable migration description |
| `status` | `ENUM('pending','running','completed','failed')` | NO | IDX | `'pending'` | Execution status |
| `started_at` | `DATETIME` | YES | | `NULL` | Execution start timestamp |
| `completed_at` | `DATETIME` | YES | | `NULL` | Execution end timestamp |
| `duration_ms` | `INT UNSIGNED` | YES | | `NULL` | Execution duration in milliseconds |
| `error_message` | `TEXT` | YES | | `NULL` | Error details if status is `failed` |
| `checksum` | `VARCHAR(64)` | YES | | `NULL` | SHA-256 of callback source (debugging aid) |

**Indexes**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY version_domain (version, domain)` -- prevents duplicate log entries
- `KEY status (status)` -- for querying failed/pending migrations
- `KEY completed_at (completed_at)` -- for retention cleanup

**Charset**: `$wpdb->get_charset_collate()` (UTF-8MB4)

**Domain ownership**: `migration`

### 3.2 Logging Procedure

For each migration callback execution:

```
1. INSERT row with status='running', started_at=NOW()
2. Execute callback in try/catch
3. On success:
   a. UPDATE status='completed', completed_at=NOW(), duration_ms=elapsed
4. On failure:
   a. UPDATE status='failed', completed_at=NOW(), error_message=exception message
   b. Log to structured logger with full exception context
   c. STOP migration sequence (do not continue to next migration)
   d. DO NOT update wpaic_migration_version (allows retry on next load)
```

### 3.3 Self-Bootstrapping

The migrations log table is created by the migration runner itself as the very first migration (version `0.0.1`, domain `migration`). This migration is hardcoded in `Migration_Registry` and always runs first:

```php
// Hardcoded bootstrap migration -- always first
$this->register([
    'version'     => '0.0.1',
    'domain'      => 'migration',
    'description' => 'Create migration log table',
    'callback'    => [$this, 'create_migrations_table'],
]);
```

If the migrations table does not yet exist (fresh install), the runner creates it via `dbDelta()` before logging any other migrations. The bootstrap migration itself is logged as the first entry.

---

## 4. Schema Validation

### 4.1 Post-Migration Validation

After each successful migration, the runner optionally validates that the expected schema changes were applied. This catches silent `dbDelta()` failures (e.g., unsupported column type changes).

**Validation method**: `SHOW COLUMNS FROM {table}` compared against expected column list.

### 4.2 Validation Interface

Migration entries may include an optional `validate` field:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `validate` | `array` | No | Array of `['table' => 'table_name', 'columns' => ['col1', 'col2']]` expectations |

```php
$registry->register([
    'version'     => '1.0.0',
    'domain'      => 'runtime',
    'description' => 'Create sessions table',
    'callback'    => function () { /* dbDelta CREATE TABLE */ },
    'validate'    => [
        [
            'table'   => 'wpaic_sessions',
            'columns' => ['id', 'session_token', 'user_id', 'status', 'created_at'],
        ],
    ],
]);
```

### 4.3 Validation Procedure

```
1. For each validation entry:
   a. Run: SHOW COLUMNS FROM {$wpdb->prefix}{table}
   b. Extract column names from result
   c. Check that every expected column exists in the result
   d. If any column is missing:
      - Log warning via structured logger
      - Record in migration log: error_message = "Validation failed: missing columns [...]"
      - Do NOT mark migration as failed (schema may be partially applied)
      - Continue to next migration
```

### 4.4 Validation Scope

Validation checks column **existence** only, not types or constraints. This is intentional:
- `dbDelta()` sometimes silently skips type changes
- Type validation would require parsing MySQL type strings which is fragile
- Column existence is sufficient to confirm the migration ran

---

## 5. Migration Lock Mechanism

### 5.1 Lock Design

Concurrent migration execution (e.g., two admin page loads during upgrade) is prevented by a transient-based lock:

| Property | Value |
|----------|-------|
| Lock key | `wpaic_temp_migration_lock` |
| TTL | 300 seconds (5 minutes) |
| Lock value | `{hostname}:{pid}:{timestamp}` |
| Storage | WordPress transients API |

### 5.2 Lock Acquisition

```php
function acquire_lock(): bool {
    $lock_value = gethostname() . ':' . getmypid() . ':' . time();
    // set_transient returns false if key already exists (with autoload)
    // Use wp_cache/option-level check for atomicity
    if (get_transient('wpaic_temp_migration_lock') !== false) {
        return false; // Another process holds the lock
    }
    set_transient('wpaic_temp_migration_lock', $lock_value, 300);
    // Double-check pattern: re-read to confirm we own it
    $stored = get_transient('wpaic_temp_migration_lock');
    return ($stored === $lock_value);
}
```

### 5.3 Lock Release

```php
function release_lock(): void {
    delete_transient('wpaic_temp_migration_lock');
}
```

### 5.4 Stale Lock Recovery

If a migration process crashes, the lock expires after 5 minutes (TTL). On next `plugins_loaded`, the lock will be stale and a new process can acquire it.

The structured logger records lock acquisition failures so administrators can diagnose stuck migrations.

### 5.5 Object Cache Compatibility

On sites with persistent object caches (Redis, Memcached), transients bypass the database and use the object cache. This is acceptable for migration locks because:
- Object cache entries respect TTL
- The lock only needs to be visible within the same site (not across servers in a multi-server setup)
- WordPress core uses the same pattern for cron locks

**Risk**: In multi-server setups with server-local object caches, two servers could acquire the lock simultaneously. Mitigation: migrations are idempotent, so concurrent execution produces correct results (at worst, duplicate work).

---

## 6. Rollback Strategy

### 6.1 Forward-Only Default

The migration runner uses a **forward-only** strategy. There are no automatic rollback migrations. This is a deliberate design choice:

- `dbDelta()` cannot undo schema changes
- WordPress plugins universally use forward-only migrations
- Automatic rollback of data migrations risks data loss

### 6.2 Compensating Migrations

When a schema change needs to be reversed, a new forward migration is registered at the next version:

```php
// Version 1.1.0 added a column
$registry->register([
    'version'     => '1.1.0',
    'domain'      => 'runtime',
    'description' => 'Add experimental_feature column',
    'callback'    => function () { /* ALTER TABLE ADD COLUMN */ },
]);

// Version 1.2.0 removes it via compensating migration
$registry->register([
    'version'     => '1.2.0',
    'domain'      => 'runtime',
    'description' => 'Remove experimental_feature column (reverted)',
    'callback'    => function () { /* ALTER TABLE DROP COLUMN */ },
]);
```

### 6.3 Manual Rollback Procedures

For emergency rollback scenarios (e.g., broken migration in production):

1. **Identify the failed migration**: Check `wp_wpaic_migrations` table for `status='failed'`
2. **Fix the migration callback**: Deploy a patched version with corrected callback code
3. **Delete the failed log entry**: `DELETE FROM wp_wpaic_migrations WHERE version='X.Y.Z' AND domain='domain_name' AND status='failed'`
4. **Reset the stored version**: `UPDATE wp_options SET option_value='previous_version' WHERE option_name='wpaic_migration_version'`
5. **Trigger re-run**: Reload any admin page (triggers `plugins_loaded` version check)

### 6.4 Version Downgrade Safety

If a site downgrades the plugin version (e.g., from 1.2.0 to 1.1.0):

- The stored `wpaic_migration_version` (1.2.0) is higher than `WPAIC_VERSION` (1.1.0)
- The migration runner detects this as a version mismatch but finds **no pending migrations** (all registered migrations are <= 1.1.0, all already completed)
- **Result**: No migrations run, no data loss
- The stored version is NOT downgraded automatically (prevents re-running migrations on upgrade)

### 6.5 Backup-Before-Migrate Recommendation

The plugin does NOT automatically back up the database before migrations. Instead:

- The admin settings page displays a recommendation to back up before upgrading
- The `wpaic_before_migration` action hook allows backup plugins to hook in
- The `lib/backup-manager.php` export endpoint can be called programmatically before migration

---

## 7. CFI-MIG-002 Resolution

CFI-MIG-002 required: "Migration registry with versioned callbacks, execution logging, schema validation, rollback strategy."

| Requirement | Resolution | Section |
|-------------|-----------|---------|
| Versioned callbacks | `wpaic_register_migrations` hook with `{version, domain, description, callback}` entries | Section 2 |
| Execution logging | `wp_wpaic_migrations` table with per-migration status, timing, error messages | Section 3 |
| Schema validation | Post-migration `SHOW COLUMNS` check against expected column list | Section 4 |
| Rollback strategy | Forward-only default with compensating migrations and manual procedures documented | Section 6 |
| Version ordering | `version_compare()` ascending with domain dependency level tiebreak | Section 2.5 |
| Migration lock | Transient-based 5-minute TTL lock preventing concurrent execution | Section 5 |
| Idempotency | All callbacks must be idempotent; `dbDelta()` for creation, `SHOW COLUMNS` guards for alterations | Section 2.7 |

**Status**: CFI-MIG-002 fully resolved.

---

## 8. Hooks Reference

| Hook | Type | When | Purpose |
|------|------|------|---------|
| `wpaic_register_migrations` | action | Version mismatch detected | Domains register migration entries |
| `wpaic_before_migration` | action | Before first migration runs | Pre-migration hooks (backup, logging) |
| `wpaic_after_migration` | action | After all migrations complete | Post-migration hooks (cache flush, notifications) |

---

## 9. Source Anchors

| Pattern | Source | Adopted/Modified |
|---------|--------|-----------------|
| Stored version + `version_compare()` | `mxchat-basic/mxchat-basic.php` | Adopted: version-gated conditional blocks |
| `dbDelta()` activation pattern | `axiachat-ai/axiachat-ai.php` (lines 755-875) | Adopted: idempotent table creation |
| `SHOW COLUMNS` + `ALTER TABLE` | `axiachat-ai/axiachat-ai.php` (lines 1274-1385) | Adopted: per-column migration guards |
| `SHOW COLUMNS` + `ALTER TABLE` | `mxchat-basic/mxchat-basic.php` (lines 270-509) | Adopted: column existence checks |
| Three-hook lifecycle | `baachal/baachal.php` | Adopted: activate/deactivate/uninstall coverage |
| Tool state in DB (not transients) | `axiachat-ai/axiachat-ai.php` (`aichat_tool_states`) | Adopted: DB-backed lock avoids object cache races |
