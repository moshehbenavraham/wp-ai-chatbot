# Ingestion Queue Specification

**Session**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-RAG-001 (queue infrastructure merged from MXC + AXI content cleaning)

---

## 1. Overview

The ingestion queue is the reliable asynchronous backbone of the RAG pipeline. It decouples content
discovery from processing, ensuring that large reindex operations do not block WordPress page loads
or exhaust PHP execution time limits.

The queue is backed by `wp_wpaic_queue` (defined in session 02 `database_table_schemas.md`).
Processing is driven by WP-Cron scheduled events. The design merges MxChat's production-quality
DB-backed queue (priority ordering, retry, stall detection) with AxiaChat's content cleaning
pipeline (Elementor walker, shortcode expansion).

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| MxChat | `mxchat-basic/admin/class-knowledge-manager.php` | Primary: `mxchat_processing_queue` table, stall detection, retry, browser-driven processing |
| AxiaChat | `axiachat-ai/includes/contexto-create.php` | Content cleaning before chunk/embed; no formal queue |
| AxiaChat | `axiachat-ai/includes/contexto-modify.php` | Delete-then-insert reindex pattern |
| Baachal | `baachal/includes/content-indexer.php` | N/A -- synchronous indexing only, no queue |

---

## 2. Queue Table Reference

From session 02 `database_table_schemas.md`, Section 6 (`wp_wpaic_queue`):

| Column | Type | Purpose |
|--------|------|---------|
| `id` | `BIGINT UNSIGNED` PK | Auto-increment primary key |
| `queue_id` | `VARCHAR(64)` | Batch identifier (UUID for each indexing run) |
| `item_type` | `VARCHAR(20)` | Source adapter item type (e.g., `wp_post`, `url`) |
| `item_data` | `LONGTEXT` | JSON-encoded serialized item data |
| `status` | `ENUM(pending,processing,completed,failed,stalled)` | Item lifecycle state |
| `bot_id` | `VARCHAR(50)` | Bot/knowledge context identifier |
| `priority` | `INT` | Processing priority (higher = processed first) |
| `attempts` | `INT` | Retry attempt counter |
| `max_attempts` | `INT` | Maximum retries (default 3) |
| `error_message` | `TEXT` | Last error details |
| `created_at` | `DATETIME` | Enqueue timestamp |
| `started_at` | `DATETIME` | Processing start timestamp |
| `completed_at` | `DATETIME` | Processing completion timestamp |

---

## 3. Queue Item Lifecycle

### State Machine

```
          enqueue()
              |
              v
         [pending]
              |
      worker picks up item
              |
              v
        [processing]
         /          \
   success           failure
        |                |
        v                v
  [completed]       attempts < max_attempts?
                         |          |
                        yes         no
                         |          |
                     [pending]  [failed]
                   (incremented  (dead letter)
                    attempts)
```

Additionally:

- Items with `status='processing'` and `started_at` older than **10 minutes** are marked `stalled`
  by the stall detection cron job. Stalled items with `attempts < max_attempts` are reset to
  `pending`. Stalled items at `max_attempts` remain `stalled` (surfaced in admin).

### State Definitions

| State | Description | Admin Visibility |
|-------|-------------|-----------------|
| `pending` | Awaiting processing | Count shown in queue dashboard |
| `processing` | Actively being processed by worker | Count shown with started_at |
| `completed` | Successfully processed and indexed | Count shown in success summary |
| `failed` | Max attempts exhausted, manual retry required | Error table in admin |
| `stalled` | Stuck in processing > 10min, attempts exhausted | Error table with stall indicator |

---

## 4. Queue API

### Class: `WPAIC\RAG\Queue`

Located at `includes/rag/class-queue.php`.

#### `Queue::enqueue()`

```php
/**
 * Enqueue one or more items for ingestion processing.
 *
 * @param string $knowledge_id  Knowledge context ID (maps to wp_wpaic_knowledge.id as string).
 * @param string $adapter_id    Source adapter ID (e.g., 'wp_post').
 * @param string $item_type     Item type constant (e.g., 'wp_post').
 * @param array  $item_data     Serializable item data for the source adapter.
 * @param int    $priority      Processing priority (higher = first, default 0).
 * @return int|false            Queue item ID on success, false on DB error.
 */
public static function enqueue(
    $knowledge_id,
    $adapter_id,
    $item_type,
    $item_data,
    $priority = 0
);
```

**Example usage**:
```php
Queue::enqueue( '1', 'wp_post', 'wp_post', array( 'post_id' => 42 ), 10 );
```

#### `Queue::enqueue_batch()`

```php
/**
 * Enqueue multiple items atomically with a shared batch queue_id.
 *
 * @param string  $knowledge_id Knowledge context ID.
 * @param array[] $items        Array of item arrays, each with keys:
 *     @type string $adapter_id
 *     @type string $item_type
 *     @type array  $item_data
 *     @type int    $priority   (optional, default 0)
 * @return string|false         Batch queue_id UUID on success, false on error.
 */
public static function enqueue_batch( $knowledge_id, $items );
```

#### `Queue::get_status()`

```php
/**
 * Get queue status summary for a knowledge context.
 *
 * @param string $knowledge_id Knowledge context ID.
 * @return array {
 *     @type int $pending     Count of pending items.
 *     @type int $processing  Count of actively processing items.
 *     @type int $completed   Count of completed items.
 *     @type int $failed      Count of permanently failed items.
 *     @type int $stalled     Count of stalled items.
 *     @type int $total       Total items in queue for this context.
 * }
 */
public static function get_status( $knowledge_id );
```

#### `Queue::retry_failed()`

```php
/**
 * Reset failed/stalled items back to pending for retry.
 *
 * @param string   $knowledge_id Knowledge context ID.
 * @param int|null $item_id      Specific item ID to retry, or null for all failed.
 * @return int Number of items reset.
 */
public static function retry_failed( $knowledge_id, $item_id = null );
```

#### `Queue::clear()`

```php
/**
 * Delete all queue items for a knowledge context.
 *
 * Only completed and failed items are deleted by default. Pass $all = true
 * to delete pending/processing items as well (used during full reindex).
 *
 * @param string $knowledge_id Knowledge context ID.
 * @param bool   $all          Delete all items including pending (default false).
 * @return int Number of items deleted.
 */
public static function clear( $knowledge_id, $all = false );
```

---

## 5. Cron Worker Design

### WP-Cron Schedule

| Setting | Value |
|---------|-------|
| Cron hook | `wpaic_rag_process_queue` |
| Schedule | Every 3 minutes (custom `wpaic_every_3_minutes` interval) |
| Batch size | 5 items per cron tick |
| PHP time limit | 45 seconds max per cron invocation (leaves buffer for WP teardown) |

Custom cron interval registration:

```php
add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['wpaic_every_3_minutes'] = array(
        'interval' => 180,
        'display'  => __( 'Every 3 Minutes (WPAIC RAG Queue)', 'wp-ai-chatbot' ),
    );
    return $schedules;
} );
```

### Worker Lifecycle

```
wpaic_rag_process_queue fires
    |
    v
1. Stall check: UPDATE status='stalled' WHERE status='processing' AND started_at < NOW() - INTERVAL 10 MINUTE
2. Stall recovery: UPDATE status='pending' WHERE status='stalled' AND attempts < max_attempts
3. Fetch batch: SELECT ... WHERE status='pending' ORDER BY priority DESC, id ASC LIMIT 5 FOR UPDATE SKIP LOCKED
4. For each item:
    a. UPDATE status='processing', started_at=NOW(), attempts=attempts+1
    b. Dispatch to source adapter: $text = $adapter->extract_content($item_type, $item_data)
    c. If $text is null: UPDATE status='completed', error_message='no_content'
    d. Chunk text: $chunks = Chunker::chunk($text, $chunker_options)
    e. Generate embeddings: $embeddings = Embedding_Client::embed_batch($chunks, $knowledge_id)
    f. Upsert chunks: INSERT INTO wp_wpaic_chunks ... ON DUPLICATE KEY UPDATE
    g. UPDATE status='completed', completed_at=NOW()
    h. On any exception: UPDATE status='pending'/'failed' based on attempts
5. Update wp_wpaic_knowledge.processing_progress
6. Fire action: wpaic_rag_batch_processed($knowledge_id, $processed_count, $failed_count)
```

### Idempotency

The worker uses `FOR UPDATE SKIP LOCKED` to prevent concurrent cron ticks from picking up the same
items. If `SKIP LOCKED` is unavailable (MySQL < 8.0 without InnoDB SKIP LOCKED support), fall back
to selecting by queue_id with application-level locking via a transient:

```php
$lock_key = 'wpaic_queue_lock_' . $knowledge_id;
if ( get_transient( $lock_key ) ) {
    return; // Another worker is active
}
set_transient( $lock_key, 1, 60 ); // 60-second lock
```

---

## 6. Retry Policy

### Exponential Backoff

Items that fail are reset to `pending` with an updated `created_at` offset to delay reprocessing:

| Attempt | Delay Before Retry |
|---------|-------------------|
| 1 | 0 minutes (immediate) |
| 2 | 2 minutes |
| 3 (final) | 4 minutes |

The delay is enforced by setting `created_at = NOW() + INTERVAL {delay} MINUTE` on failure reset.
The worker's `ORDER BY id ASC` ensures freshly-created items from discovery precede delayed retries.

### Error Classification

| Error Type | Retry? | Behavior |
|-----------|--------|----------|
| Extraction returned null (no content) | No | Mark `completed` with `no_content` note |
| Embedding API HTTP 429 (rate limit) | Yes | Delay 4 minutes |
| Embedding API HTTP 5xx | Yes | Standard backoff |
| Embedding API HTTP 401 | No | Mark `failed` immediately (bad API key) |
| Embedding returned empty array | Yes | Standard backoff |
| DB insert error | Yes | Standard backoff |
| PHP memory exhausted | No (logged) | Mark `failed`, log to `lib/structured-logger.php` |

---

## 7. Auto-Sync Integration

### `save_post` Hook

The `Ingestion_Pipeline` registers on `save_post` (priority 20, after WooCommerce and ACF hooks)
to auto-enqueue updated posts when auto-sync is enabled.

```php
add_action( 'save_post', array( $pipeline, 'handle_post_save' ), 20, 3 );
```

**`handle_post_save()` contract**:

1. Skip if `wp_is_post_autosave($post_id)` or `wp_is_post_revision($post_id)`
2. Skip if post status is not `publish`
3. Check `_wpaic_exclude_from_index` meta -- skip if set
4. For each active knowledge context with `autosync_enabled = 1`:
   - If `autosync_mode = 'updates'`: only enqueue if chunks already exist for this post
   - If `autosync_mode = 'updates_and_new'`: enqueue regardless
5. Enqueue with `priority = 5` (lower priority than manual reindex which uses 10)
6. Fire action `wpaic_rag_post_queued($post_id, $knowledge_id)`

### `delete_post` Hook

```php
add_action( 'before_delete_post', array( $pipeline, 'handle_post_delete' ), 10, 1 );
```

**`handle_post_delete()` contract**:

1. Delete all chunks for the post from `wp_wpaic_chunks`: `DELETE WHERE post_id = ?`
2. Cancel any pending queue items for the post
3. Log deletion to structured logger at `debug` level

### ACF Integration

When Advanced Custom Fields is active, register an additional hook on `acf/save_post`:

```php
add_action( 'acf/save_post', array( $pipeline, 'handle_acf_save' ), 30 );
```

This fires after ACF saves its meta values, ensuring field data is available during extraction.

---

## 8. Queue Status Hooks

### Action: `wpaic_rag_batch_processed`

```php
/**
 * Fires after each cron worker batch completes.
 *
 * @param string $knowledge_id   The knowledge context ID.
 * @param int    $processed      Number of items completed in this batch.
 * @param int    $failed         Number of items that failed (moved to pending/failed).
 */
do_action( 'wpaic_rag_batch_processed', $knowledge_id, $processed, $failed );
```

### Action: `wpaic_rag_queue_completed`

```php
/**
 * Fires when all items in a queue batch reach terminal state (completed/failed).
 * Used to trigger notifications and update wp_wpaic_knowledge.processing_status.
 *
 * @param string $knowledge_id The knowledge context ID.
 * @param array  $summary {
 *     @type int $completed  Total completed items.
 *     @type int $failed     Total failed items.
 *     @type int $no_content Items with no extractable content.
 * }
 */
do_action( 'wpaic_rag_queue_completed', $knowledge_id, $summary );
```

---

## 9. Processing Progress Tracking

The `wp_wpaic_knowledge.processing_progress` column (INT 0-100) is updated after each batch:

```php
$total = Queue::count_by_status($knowledge_id, array('pending','processing','completed','failed'));
$done  = Queue::count_by_status($knowledge_id, array('completed','failed'));
$progress = ($total > 0) ? (int) (($done / $total) * 100) : 0;
$wpdb->update('wp_wpaic_knowledge', array('processing_progress' => $progress), array('id' => $knowledge_id));
```

`wp_wpaic_knowledge.processing_status` transitions:
- `pending` -> `processing` (first batch starts)
- `processing` -> `completed` (all items terminal, zero failures)
- `processing` -> `completed_with_errors` (all items terminal, some failures)
- `completed` / `completed_with_errors` -> `processing` (reindex triggered)

---

## 10. Source Anchor Comparison

| Feature | MxChat | AxiaChat | Baachal | WPAIC |
|---------|--------|----------|---------|-------|
| Queue storage | `mxchat_processing_queue` DB table | None (synchronous) | None (synchronous) | `wp_wpaic_queue` table |
| Retry | 3 attempts (hardcoded) | N/A | N/A | 3 attempts, configurable per item |
| Stall detection | 5-minute timeout | N/A | N/A | 10-minute timeout (matches session 02 schema) |
| Worker trigger | Browser-driven (page must stay open) | None | None | WP-Cron (background, reliable) |
| Auto-sync | `save_post` + ACF | None | `save_post` (no ACF) | `save_post` + ACF (MXC pattern) |
| Priority ordering | Yes (INT priority) | N/A | N/A | Yes (INT priority, higher = first) |
| Batch size | 5 concurrent (browser) | N/A | N/A | 5 items per cron tick |
