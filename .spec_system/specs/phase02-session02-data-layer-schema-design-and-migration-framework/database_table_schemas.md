# Database Table Schemas

**Session ID**: `phase02-session02-data-layer-schema-design-and-migration-framework`
**Status**: Complete
**Created**: 2026-03-01
**Resolves**: CFI-RUN-002 (unified session storage)

---

## 1. Overview

All custom database tables follow the naming pattern `{$wpdb->prefix}wpaic_{entity}`. The `$wpdb->prefix` is never hardcoded as `wp_` -- it varies per site and per subsite in multisite installations.

All tables use:
- `$wpdb->get_charset_collate()` for CHARACTER SET and COLLATION (typically `utf8mb4_unicode_ci`)
- `InnoDB` engine (WordPress default, supports foreign keys and transactions)
- `dbDelta()` for idempotent creation via `wp-admin/includes/upgrade.php`

### 1.1 MVP Table Registry

| # | Table | Domain Owner | Migration Version | Purpose |
|---|-------|-------------|-------------------|---------|
| 1 | `wp_wpaic_migrations` | migration | 0.0.1 | Migration execution log (self-bootstrapping) |
| 2 | `wp_wpaic_sessions` | runtime | 1.0.0 | Chat session storage (CFI-RUN-002) |
| 3 | `wp_wpaic_messages` | runtime | 1.0.0 | Message history with token tracking |
| 4 | `wp_wpaic_knowledge` | rag | 1.0.0 | Knowledge base context configuration |
| 5 | `wp_wpaic_chunks` | rag | 1.0.0 | Content chunks with embeddings |
| 6 | `wp_wpaic_queue` | rag | 1.0.0 | Ingestion processing queue |
| 7 | `wp_wpaic_usage_daily` | admin | 1.0.0 | Daily usage/cost aggregation |
| 8 | `wp_wpaic_bots` | admin | 1.0.0 | Bot configuration |

---

## 2. Sessions Table (`wp_wpaic_sessions`)

**Domain owner**: runtime
**Migration version**: 1.0.0
**Resolves**: CFI-RUN-002 -- Replaces three divergent source formats:
- AxiaChat: Per-message `session_id` in `aichat_conversations` (no dedicated session table)
- MxChat: Session state in `wp_options` transients (`mxchat_history_{session_id}`)
- Baachal: Conversations as Custom Post Type `baachal_conversation`

### 2.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `session_token` | `VARCHAR(64)` | NO | UNI | | Unique session identifier (UUID v4) |
| `user_id` | `BIGINT UNSIGNED` | NO | IDX | `0` | WordPress user ID (0 = anonymous) |
| `bot_id` | `BIGINT UNSIGNED` | YES | IDX | `NULL` | FK to `wp_wpaic_bots.id` |
| `ip_address` | `VARBINARY(16)` | YES | | `NULL` | IPv4 (4 bytes) or IPv6 (16 bytes) via `inet_pton()` |
| `user_agent_hash` | `VARCHAR(64)` | YES | | `NULL` | SHA-256 of User-Agent (ownership validation) |
| `status` | `ENUM('active','closed','expired')` | NO | IDX | `'active'` | Session lifecycle state |
| `provider` | `VARCHAR(40)` | YES | | `NULL` | AI provider used (denormalized for query convenience) |
| `model` | `VARCHAR(100)` | YES | | `NULL` | AI model used (denormalized) |
| `page_id` | `BIGINT UNSIGNED` | YES | IDX | `NULL` | WordPress page ID where session started |
| `total_prompt_tokens` | `INT UNSIGNED` | NO | | `0` | Aggregated prompt tokens across all messages |
| `total_completion_tokens` | `INT UNSIGNED` | NO | | `0` | Aggregated completion tokens |
| `total_cost_micros` | `BIGINT` | NO | | `0` | Aggregated cost in microcurrency (1/1,000,000 USD) |
| `message_count` | `INT UNSIGNED` | NO | | `0` | Number of messages in session (denormalized) |
| `created_at` | `DATETIME` | NO | IDX | `CURRENT_TIMESTAMP` | Session creation timestamp |
| `updated_at` | `DATETIME` | NO | | `CURRENT_TIMESTAMP` | Last activity timestamp |
| `expires_at` | `DATETIME` | YES | IDX | `NULL` | Session expiration (for retention cleanup) |

### 2.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(id)` | Row identity |
| `session_token` | UNIQUE | `(session_token)` | Session lookup by token |
| `idx_user_status` | KEY | `(user_id, status)` | List active sessions for a user |
| `idx_bot_id` | KEY | `(bot_id)` | Sessions per bot |
| `idx_status` | KEY | `(status)` | Filter by lifecycle state |
| `idx_page_id` | KEY | `(page_id)` | Sessions per page |
| `idx_created_at` | KEY | `(created_at)` | Chronological listing, retention cleanup |
| `idx_expires_at` | KEY | `(expires_at)` | Retention cleanup (find expired sessions) |

### 2.3 Ownership Validation

Session ownership is validated by matching `(session_token, ip_address, user_agent_hash)` or `(session_token, user_id)` for authenticated users. This prevents session hijacking when IP changes (mobile network switching) by falling back to user_id for logged-in users.

### 2.4 Token/Cost Tracking

Aggregated totals are updated on each message via `UPDATE ... SET total_prompt_tokens = total_prompt_tokens + ?`. This avoids the cost of `SUM()` queries across the messages table for session-level reporting.

### 2.5 Source Anchor Comparison

| Feature | AxiaChat | MxChat | Baachal | WPAIC |
|---------|----------|--------|---------|-------|
| Session storage | Implicit (per-message session_id) | wp_options transients | Custom Post Type | Dedicated table |
| Ownership | IP in `aichat_conversations` | IP/user binding | None | IP + user_agent_hash + user_id |
| Token tracking | Per-message columns | None | None | Per-message + session aggregate |
| Expiration | None | Transient TTL | None | `expires_at` column |

---

## 3. Messages Table (`wp_wpaic_messages`)

**Domain owner**: runtime
**Migration version**: 1.0.0

### 3.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `session_id` | `BIGINT UNSIGNED` | NO | IDX | | FK to `wp_wpaic_sessions.id` |
| `role` | `ENUM('user','assistant','system')` | NO | | | Message sender role |
| `message` | `LONGTEXT` | NO | | | User message or system prompt content |
| `response` | `LONGTEXT` | YES | | `NULL` | AI response (NULL for user/system messages) |
| `provider` | `VARCHAR(40)` | YES | | `NULL` | Provider used for this message |
| `model` | `VARCHAR(100)` | YES | IDX | `NULL` | Model used for this message |
| `prompt_tokens` | `INT UNSIGNED` | YES | | `NULL` | Prompt tokens for this exchange |
| `completion_tokens` | `INT UNSIGNED` | YES | | `NULL` | Completion tokens for this exchange |
| `cost_micros` | `BIGINT` | YES | | `NULL` | Cost in microcurrency |
| `rag_context` | `LONGTEXT` | YES | | `NULL` | RAG context injected (for debugging/audit) |
| `created_at` | `DATETIME` | NO | IDX | `CURRENT_TIMESTAMP` | Message timestamp |

### 3.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(id)` | Row identity |
| `idx_session_id` | KEY | `(session_id, id)` | Conversation retrieval in order |
| `idx_model` | KEY | `(model)` | Usage analysis by model |
| `idx_created_at` | KEY | `(created_at)` | Chronological listing, retention cleanup |

### 3.3 Design Decision: Separate message and response columns

Following the AxiaChat pattern (`aichat_conversations`), user messages and AI responses are stored as separate columns in the same row rather than as separate rows. This:
- Simplifies paired retrieval (one row = one exchange)
- Keeps token/cost data naturally associated with the exchange
- Reduces row count for conversation history queries

System messages (role='system') use the `message` column only, with `response` NULL.

### 3.4 Source Anchor Comparison

| Feature | AxiaChat | MxChat | WPAIC |
|---------|----------|--------|-------|
| Table | `aichat_conversations` | `mxchat_chat_transcripts` | `wpaic_messages` |
| Structure | message + response per row | role + message per row | message + response per row (AXI pattern) |
| Token tracking | Per-row columns | None | Per-row columns |
| RAG context | Not stored | `rag_context` column | `rag_context` column (MXC pattern) |

---

## 4. Knowledge Table (`wp_wpaic_knowledge`)

**Domain owner**: rag
**Migration version**: 1.0.0

### 4.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `name` | `VARCHAR(255)` | NO | | | Knowledge context display name |
| `context_type` | `ENUM('local','pinecone')` | NO | | `'local'` | Storage backend |
| `embedding_provider` | `VARCHAR(40)` | YES | | `NULL` | Embedding model provider |
| `embedding_model` | `VARCHAR(100)` | YES | | `NULL` | Embedding model identifier |
| `processing_status` | `VARCHAR(20)` | YES | IDX | `'pending'` | Current processing state |
| `processing_progress` | `INT` | YES | | `0` | Progress percentage (0-100) |
| `items_to_process` | `LONGTEXT` | YES | | `NULL` | Serialized pending items |
| `autosync_enabled` | `TINYINT(1)` | NO | | `0` | Auto-sync on post save |
| `autosync_mode` | `ENUM('updates','updates_and_new')` | NO | | `'updates'` | Sync scope |
| `autosync_post_types` | `VARCHAR(255)` | YES | | `NULL` | Comma-separated post types |
| `autosync_last_scan` | `DATETIME` | YES | | `NULL` | Last auto-sync timestamp |
| `indexing_options` | `LONGTEXT` | YES | | `NULL` | JSON configuration |
| `created_at` | `DATETIME` | NO | | `CURRENT_TIMESTAMP` | Creation timestamp |
| `updated_at` | `DATETIME` | YES | | `NULL` | Last modification timestamp |

### 4.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(id)` | Row identity |
| `idx_processing_status` | KEY | `(processing_status)` | Find contexts needing processing |

### 4.3 Source Anchor

Adopted from AxiaChat `aichat_contexts` table with minor adjustments:
- Removed `remote_type`, `remote_api_key`, `remote_endpoint` (Pinecone config moved to options)
- Added `embedding_model` column for per-context model tracking
- Renamed `autosync` to `autosync_enabled` for clarity

---

## 5. Chunks Table (`wp_wpaic_chunks`)

**Domain owner**: rag
**Migration version**: 1.0.0

### 5.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `knowledge_id` | `BIGINT UNSIGNED` | YES | IDX | `NULL` | FK to `wp_wpaic_knowledge.id` |
| `post_id` | `BIGINT UNSIGNED` | NO | | | WordPress post ID |
| `chunk_index` | `INT` | NO | | `0` | Chunk sequence within post |
| `type` | `VARCHAR(20)` | YES | | `NULL` | Content type (post, page, product, etc.) |
| `title` | `VARCHAR(255)` | YES | | `NULL` | Source post title |
| `content` | `MEDIUMTEXT` | NO | | | Chunk text content |
| `embedding` | `LONGTEXT` | YES | | `NULL` | Vector embedding (JSON float array) |
| `tokens` | `INT` | YES | | `NULL` | Token count for this chunk |
| `created_at` | `DATETIME` | NO | | `CURRENT_TIMESTAMP` | Creation timestamp |
| `updated_at` | `DATETIME` | YES | | `NULL` | Last update timestamp |

### 5.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(id)` | Row identity |
| `uniq_post_knowledge_chunk` | UNIQUE | `(post_id, knowledge_id, chunk_index)` | Prevents duplicate chunks |
| `idx_knowledge_id` | KEY | `(knowledge_id)` | Chunks for a context |
| `idx_post_knowledge` | KEY | `(post_id, knowledge_id)` | All chunks for a post in a context |

### 5.3 Embedding Storage

Embeddings are stored as JSON-encoded float arrays in LONGTEXT. This column **cannot be indexed** by MySQL -- retrieval performance depends on application-level vector similarity computation (cosine similarity in PHP) or external vector databases (Pinecone).

For the `local` retrieval mode, similarity computation loads embeddings into memory. Performance is bounded by knowledge context size -- this is acceptable for small-to-medium knowledge bases (< 10,000 chunks). The `pinecone` mode offloads similarity search to Pinecone's infrastructure.

### 5.4 Source Anchor

Adopted from AxiaChat `aichat_chunks` with the improved unique key `uniq_post_knowledge_chunk` (AxiaChat migrated from `unique_post_context` to `uniq_post_ctx_chunk` in a later version).

---

## 6. Queue Table (`wp_wpaic_queue`)

**Domain owner**: rag
**Migration version**: 1.0.0

### 6.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `queue_id` | `VARCHAR(64)` | NO | IDX | | Queue batch identifier |
| `item_type` | `VARCHAR(20)` | NO | IDX | | Item type (post, url, file, etc.) |
| `item_data` | `LONGTEXT` | NO | | | Serialized item data (post ID, URL, etc.) |
| `status` | `ENUM('pending','processing','completed','failed','stalled')` | NO | IDX | `'pending'` | Processing state |
| `bot_id` | `VARCHAR(50)` | NO | | `'default'` | Associated bot identifier |
| `priority` | `INT` | NO | IDX | `0` | Processing priority (higher = first) |
| `attempts` | `INT` | NO | | `0` | Number of processing attempts |
| `max_attempts` | `INT` | NO | | `3` | Maximum retry attempts |
| `error_message` | `TEXT` | YES | | `NULL` | Last error details |
| `created_at` | `DATETIME` | NO | | | Enqueue timestamp |
| `started_at` | `DATETIME` | YES | | `NULL` | Processing start timestamp |
| `completed_at` | `DATETIME` | YES | | `NULL` | Processing completion timestamp |

### 6.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(id)` | Row identity |
| `idx_queue_id` | KEY | `(queue_id)` | Items in a batch |
| `idx_status_priority` | KEY | `(status, priority)` | Fetch next pending item |
| `idx_item_type` | KEY | `(item_type)` | Filter by type |

### 6.3 Stall Detection

Items with `status='processing'` and `started_at` older than 10 minutes are considered stalled. A cron job marks them as `stalled` and resets `attempts` if under `max_attempts`.

### 6.4 Source Anchor

Adopted from MxChat `mxchat_processing_queue` table. The companion `mxchat_queue_meta` table is NOT adopted -- metadata is stored inline in `item_data` (JSON) to reduce join complexity.

---

## 7. Usage Daily Table (`wp_wpaic_usage_daily`)

**Domain owner**: admin
**Migration version**: 1.0.0

### 7.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `date` | `DATE` | NO | PK | | Aggregation date |
| `provider` | `VARCHAR(40)` | NO | PK | `'openai'` | Provider name |
| `model` | `VARCHAR(100)` | NO | PK | `''` | Model name |
| `prompt_tokens` | `BIGINT UNSIGNED` | NO | | `0` | Daily prompt tokens |
| `completion_tokens` | `BIGINT UNSIGNED` | NO | | `0` | Daily completion tokens |
| `total_tokens` | `BIGINT UNSIGNED` | NO | | `0` | Daily total tokens |
| `cost_micros` | `BIGINT UNSIGNED` | NO | | `0` | Daily cost in microcurrency |
| `conversations` | `BIGINT UNSIGNED` | NO | | `0` | Daily conversation count |

### 7.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(date, provider, model)` | Composite PK for daily aggregation |

### 7.3 Update Pattern

Usage is updated via `INSERT ... ON DUPLICATE KEY UPDATE`:

```sql
INSERT INTO {table} (date, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_micros, conversations)
VALUES (%s, %s, %s, %d, %d, %d, %d, 1)
ON DUPLICATE KEY UPDATE
    prompt_tokens = prompt_tokens + VALUES(prompt_tokens),
    completion_tokens = completion_tokens + VALUES(completion_tokens),
    total_tokens = total_tokens + VALUES(total_tokens),
    cost_micros = cost_micros + VALUES(cost_micros),
    conversations = conversations + VALUES(conversations)
```

### 7.4 Source Anchor

Adopted directly from AxiaChat `aichat_usage_daily` table with identical structure.

---

## 8. Bots Table (`wp_wpaic_bots`)

**Domain owner**: admin
**Migration version**: 1.0.0

### 8.1 Column Definitions

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | `BIGINT UNSIGNED` | NO | PK | `AUTO_INCREMENT` | Primary key |
| `name` | `VARCHAR(100)` | NO | | `''` | Bot display name |
| `slug` | `VARCHAR(100)` | NO | UNI | | Unique bot identifier |
| `instructions` | `LONGTEXT` | YES | | `NULL` | System prompt / instructions |
| `provider` | `VARCHAR(40)` | NO | IDX | `'openai'` | AI provider |
| `model` | `VARCHAR(100)` | NO | | `'gpt-4o'` | AI model |
| `temperature` | `DECIMAL(3,2)` | NO | | `0.70` | Sampling temperature |
| `max_tokens` | `INT` | NO | | `2048` | Max response tokens |
| `context_mode` | `ENUM('embeddings','page','none')` | NO | | `'embeddings'` | Context source mode |
| `knowledge_id` | `BIGINT UNSIGNED` | YES | IDX | `NULL` | FK to `wp_wpaic_knowledge.id` |
| `input_max_length` | `INT` | NO | | `512` | Max user input characters |
| `max_messages` | `INT` | NO | | `20` | Max conversation history |
| `history_persistence` | `TINYINT(1)` | NO | | `1` | Store conversation history |
| `ui_position` | `ENUM('br','bl','tr','tl')` | NO | | `'br'` | Widget position |
| `ui_color` | `VARCHAR(7)` | NO | | `'#1a73e8'` | Widget primary color (hex) |
| `ui_avatar` | `VARCHAR(255)` | YES | | `NULL` | Avatar image URL |
| `ui_start_sentence` | `VARCHAR(255)` | YES | | `NULL` | Initial greeting |
| `ui_placeholder` | `VARCHAR(255)` | NO | | `'Write your question...'` | Input placeholder |
| `ui_suggestions_enabled` | `TINYINT(1)` | NO | | `0` | Show suggestion chips |
| `ui_suggestions_count` | `INT` | NO | | `3` | Number of suggestions |
| `is_active` | `TINYINT(1)` | NO | IDX | `1` | Bot active flag |
| `created_at` | `DATETIME` | NO | | `CURRENT_TIMESTAMP` | Creation timestamp |
| `updated_at` | `DATETIME` | NO | | `CURRENT_TIMESTAMP` | Last update timestamp |

### 8.2 Indexes

| Name | Type | Columns | Purpose |
|------|------|---------|---------|
| `PRIMARY` | PRIMARY | `(id)` | Row identity |
| `slug` | UNIQUE | `(slug)` | Bot lookup by slug |
| `idx_provider` | KEY | `(provider)` | Bots per provider |
| `idx_knowledge_id` | KEY | `(knowledge_id)` | Bots per knowledge context |
| `idx_is_active` | KEY | `(is_active)` | Active bot listing |

### 8.3 Default Bot Seeding

On activation, if no bots exist, a default bot is seeded:

```php
$wpdb->insert($bots_table, [
    'name'   => 'AI Assistant',
    'slug'   => 'default',
    'is_active' => 1,
    // All other columns use defaults
]);
```

This is idempotent -- seeding only runs when the bots table is empty.

### 8.4 Simplification from Source

The AxiaChat `aichat_bots` table has 40+ columns including many UI-specific fields (`ui_width`, `ui_height`, `ui_closable`, `ui_minimizable`, `ui_draggable`, `ui_superminimized_default`, `ui_avatar_bubble`, `ui_css_force`, etc.). The WPAIC design consolidates these to essential UI settings. Extended UI customization will be handled via a JSON `ui_config` column if needed in a future migration.

---

## 9. Migrations Log Table (`wp_wpaic_migrations`)

**Domain owner**: migration
**Migration version**: 0.0.1 (self-bootstrapping)

Full schema defined in `migration_runner_framework.md` Section 3. Summary:

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| `id` | `BIGINT UNSIGNED` | PK | Auto-increment |
| `version` | `VARCHAR(20)` | UNI (with domain) | Migration version |
| `domain` | `VARCHAR(30)` | UNI (with version) | Domain that registered migration |
| `description` | `VARCHAR(255)` | | Human-readable description |
| `status` | `ENUM(...)` | IDX | pending/running/completed/failed |
| `started_at` | `DATETIME` | | Execution start |
| `completed_at` | `DATETIME` | IDX | Execution end |
| `duration_ms` | `INT UNSIGNED` | | Execution time |
| `error_message` | `TEXT` | | Error details if failed |
| `checksum` | `VARCHAR(64)` | | Callback hash for debugging |

---

## 10. Deferred Domain Extension Points

These tables are NOT created in MVP. They are documented here so that Phase 03+ implementations can register their migrations via `wpaic_register_migrations` without coupling to MVP modules.

### 10.1 Tools Domain Tables (Deferred)

| Table | Purpose | Source Anchor |
|-------|---------|--------------|
| `wp_wpaic_tools` | Tool registration with JSON Schema definitions | `aichat_tools` |
| `wp_wpaic_macros` | Macro (tool sequence) registry with source tracking | `aichat_macros` |
| `wp_wpaic_tool_calls` | Tool execution history with timing and error tracking | `aichat_tool_calls` |
| `wp_wpaic_tool_states` | Tool-pending state for multi-round tool execution | `aichat_tool_states` |

**Registration pattern**:
```php
add_action('wpaic_register_migrations', function ($registry) {
    $registry->register([
        'version' => '2.0.0', // After MVP tables
        'domain'  => 'tools',
        'description' => 'Create tools and macros tables',
        'callback' => function () { /* dbDelta() */ },
    ]);
});
```

### 10.2 WooCommerce Domain Tables (Deferred)

No custom tables are planned for WooCommerce. The WooCommerce module reads from WC APIs and enriches RAG results at query time. Product catalog search uses `WP_Query` or the RAG `wp_wpaic_chunks` table (products indexed as content chunks).

### 10.3 Integrations Domain Tables (Deferred)

| Table | Purpose | Source Anchor |
|-------|---------|--------------|
| `wp_wpaic_webhooks` | Webhook endpoint configurations with event mapping | `aichat_custom_webhooks` |

### 10.4 Cascade Delete Pattern

When a source entity is removed, dependent records are cleaned up:

| Source Deletion | Cascade Target | Mechanism |
|-----------------|---------------|-----------|
| Bot deleted | Sessions for that bot | `DELETE FROM wp_wpaic_sessions WHERE bot_id = ?` |
| Knowledge context deleted | Chunks for that context | `ON DELETE SET NULL` FK + cleanup hook |
| MCP server removed | Tools with `source='mcp', source_id=?` | Application-level `Tool_Registry::remove_by_source()` |
| Session expired | Messages for that session | Retention cleanup cron job |

---

## 11. Table Summary

### 11.1 Complete Table Registry

| # | Table | Domain | Columns | Indexes | Est. Row Growth | Migration |
|---|-------|--------|---------|---------|----------------|-----------|
| 1 | `wp_wpaic_migrations` | migration | 10 | 3 | ~50 rows total | 0.0.1 |
| 2 | `wp_wpaic_sessions` | runtime | 17 | 8 | ~100/day active site | 1.0.0 |
| 3 | `wp_wpaic_messages` | runtime | 12 | 4 | ~1000/day active site | 1.0.0 |
| 4 | `wp_wpaic_knowledge` | rag | 15 | 2 | ~10 contexts | 1.0.0 |
| 5 | `wp_wpaic_chunks` | rag | 11 | 4 | ~10,000 chunks | 1.0.0 |
| 6 | `wp_wpaic_queue` | rag | 13 | 4 | Bounded by cleanup | 1.0.0 |
| 7 | `wp_wpaic_usage_daily` | admin | 8 | 1 | ~365 rows/year/model | 1.0.0 |
| 8 | `wp_wpaic_bots` | admin | 23 | 5 | ~1-10 bots | 1.0.0 |

### 11.2 Foreign Key Relationships

```
wp_wpaic_sessions.bot_id -> wp_wpaic_bots.id
wp_wpaic_messages.session_id -> wp_wpaic_sessions.id
wp_wpaic_chunks.knowledge_id -> wp_wpaic_knowledge.id
wp_wpaic_bots.knowledge_id -> wp_wpaic_knowledge.id
```

**Note**: Foreign keys are expressed as application-level constraints, not MySQL `FOREIGN KEY` clauses. This is because `dbDelta()` does not reliably handle foreign key syntax, and WordPress core convention avoids MySQL-level FK constraints. Referential integrity is enforced in the PHP code and via cascade delete hooks.

Exception: The `chunks.knowledge_id` FK uses `ON DELETE SET NULL` (applied via a manual `ALTER TABLE` after `dbDelta()`) because orphaned chunks from a deleted context should be identifiable for cleanup.

### 11.3 Index Strategy Notes

- **Composite indexes** are ordered by selectivity (most selective column first) for optimal query performance
- **Covering indexes** for common queries: `idx_session_id (session_id, id)` on messages covers conversation retrieval without table lookup
- **No FULLTEXT indexes** on MVP tables -- FULLTEXT search is handled by the Baachal-style `MATCH...AGAINST` pattern only if a dedicated search table is needed; MVP uses vector similarity
- **LONGTEXT columns** (embedding, message, response) cannot be indexed -- application-level retrieval handles these
