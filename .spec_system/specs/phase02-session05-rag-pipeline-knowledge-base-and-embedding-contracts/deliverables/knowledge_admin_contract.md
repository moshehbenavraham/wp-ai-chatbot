# Knowledge Admin Contract

**Session**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

This document defines the REST API contracts and admin UI surface for the knowledge base module.
The admin surface is consumed by the `class-knowledge-page.php` controller under `includes/admin/pages/`
(session 01 module map).

Admin endpoints follow the WordPress REST API conventions using the namespace `wpaic/v1`.
All knowledge management endpoints require the `manage_options` capability.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| MxChat | `mxchat-basic/js/knowledge-processing.js` | Primary: queue progress UI, polling, error display |
| MxChat | `mxchat-basic/admin/class-knowledge-manager.php` | Admin operations, bulk actions |
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | Context create/modify AJAX patterns |
| Baachal | `baachal/admin/tabs/content.php` | Simple index stats reference (content type config) |
| Baachal | `baachal/admin/tabs/search.php` | Test search pattern (inline test search console) |

---

## 2. Endpoint Summary

| Route | Method | Action |
|-------|--------|--------|
| `/wpaic/v1/knowledge` | GET | List all knowledge contexts |
| `/wpaic/v1/knowledge` | POST | Create a new knowledge context |
| `/wpaic/v1/knowledge/{id}` | GET | Get a single knowledge context |
| `/wpaic/v1/knowledge/{id}` | PUT | Update a knowledge context |
| `/wpaic/v1/knowledge/{id}` | DELETE | Delete a knowledge context and its chunks |
| `/wpaic/v1/knowledge/{id}/reindex` | POST | Trigger full reindex for a knowledge context |
| `/wpaic/v1/knowledge/{id}/queue` | GET | Get queue status for a knowledge context |
| `/wpaic/v1/knowledge/{id}/search` | POST | Test search against a knowledge context |
| `/wpaic/v1/knowledge/queue/retry` | POST | Retry all failed queue items |
| `/wpaic/v1/knowledge/queue/clear-failed` | DELETE | Clear all failed queue items |

All endpoints:
- Require: WordPress nonce header `X-WP-Nonce` with action `wp_rest`
- Require: `manage_options` capability
- Return: `application/json` response body
- Base URL: `{site_url}/wp-json/wpaic/v1/knowledge`

---

## 3. Endpoint Specifications

### 3.1 List Knowledge Contexts

**Route**: `GET /wpaic/v1/knowledge`

**Request Parameters** (query string):

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number for pagination |
| `per_page` | int | 20 | Items per page (max 100) |
| `status` | string | `all` | Filter by `processing_status`: `all`, `pending`, `processing`, `completed`, `completed_with_errors` |

**Response** (200):

```json
{
  "contexts": [
    {
      "id": 1,
      "name": "Main Site Knowledge",
      "context_type": "local",
      "embedding_provider": "openai",
      "embedding_model": "text-embedding-3-small",
      "processing_status": "completed",
      "processing_progress": 100,
      "chunk_count": 342,
      "autosync_enabled": true,
      "autosync_mode": "updates",
      "autosync_post_types": ["post", "page"],
      "created_at": "2026-03-01T10:00:00Z",
      "updated_at": "2026-03-01T12:30:00Z"
    }
  ],
  "total": 3,
  "pages": 1
}
```

**Error Codes**:
- `401` -- Unauthorized (nonce invalid or expired)
- `403` -- Forbidden (insufficient capability)

---

### 3.2 Create Knowledge Context

**Route**: `POST /wpaic/v1/knowledge`

**Request Body** (application/json):

```json
{
  "name": "Product Knowledge Base",
  "context_type": "local",
  "embedding_provider": "openai",
  "embedding_model": "text-embedding-3-small",
  "autosync_enabled": false,
  "autosync_mode": "updates",
  "autosync_post_types": ["product"],
  "indexing_options": {
    "chunk_size": 1000,
    "overlap_size": 150,
    "include_excerpt": false,
    "include_url": true,
    "include_categories": false,
    "include_tags": false,
    "include_custom_fields": []
  }
}
```

**Validation**:

| Field | Rules |
|-------|-------|
| `name` | Required, string, 1-255 chars, `sanitize_text_field()` |
| `context_type` | Required, enum: `local`, `pinecone` |
| `embedding_provider` | Optional, must be a registered embedding provider ID or null |
| `embedding_model` | Optional, must be a valid model for the embedding_provider |
| `autosync_enabled` | Optional, boolean |
| `autosync_mode` | Optional, enum: `updates`, `updates_and_new` |
| `autosync_post_types` | Optional, array of strings, each sanitized as `sanitize_key()` |
| `indexing_options.chunk_size` | Optional, int 200-5000, default 1000 |
| `indexing_options.overlap_size` | Optional, int 0-500, default 150 |

**Response** (201 Created):

```json
{
  "id": 2,
  "name": "Product Knowledge Base",
  "context_type": "local",
  "embedding_provider": "openai",
  "embedding_model": "text-embedding-3-small",
  "processing_status": "pending",
  "processing_progress": 0,
  "chunk_count": 0,
  "created_at": "2026-03-01T14:00:00Z",
  "updated_at": "2026-03-01T14:00:00Z"
}
```

**Error Codes**:
- `400` -- Validation error. Body: `{"code": "invalid_param", "message": "...", "field": "name"}`
- `409` -- Conflict: context with same name already exists

---

### 3.3 Get Knowledge Context

**Route**: `GET /wpaic/v1/knowledge/{id}`

**Path Parameters**: `id` (integer, required)

**Response** (200): Full context object (same shape as list item, plus `indexing_options`)

**Error Codes**:
- `404` -- Context not found: `{"code": "knowledge_not_found", "message": "..."}`

---

### 3.4 Update Knowledge Context

**Route**: `PUT /wpaic/v1/knowledge/{id}`

**Request Body**: Same fields as Create (all optional for partial update).

**Special Behavior**:
- Changing `embedding_provider` or `embedding_model` on a context with existing chunks triggers
  a response warning:
  ```json
  {
    "id": 1,
    "name": "...",
    "_warnings": [
      {
        "code": "model_changed",
        "message": "Embedding model changed. A full reindex is required to update stored embeddings."
      }
    ]
  }
  ```
- A reindex is NOT automatically triggered. The admin must initiate it explicitly.

**Response** (200): Updated context object with `_warnings` array (may be empty).

**Error Codes**:
- `400` -- Validation error
- `404` -- Context not found

---

### 3.5 Delete Knowledge Context

**Route**: `DELETE /wpaic/v1/knowledge/{id}`

**Behavior**:
1. Delete all rows from `wp_wpaic_chunks WHERE knowledge_id = {id}`
2. Delete all rows from `wp_wpaic_queue WHERE bot_id = {id}` (using knowledge_id stored in bot_id field)
3. Delete row from `wp_wpaic_knowledge WHERE id = {id}`
4. Update any `wp_wpaic_bots.knowledge_id = {id}` to NULL

**Response** (200):

```json
{
  "deleted": true,
  "chunks_removed": 342,
  "queue_items_removed": 0
}
```

**Error Codes**:
- `404` -- Context not found

---

### 3.6 Trigger Reindex

**Route**: `POST /wpaic/v1/knowledge/{id}/reindex`

**Request Body**:

```json
{
  "sources": ["wp_post", "wp_page"],
  "clear_existing": true,
  "priority": 10
}
```

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `sources` | string[] | all adapters | Source adapter IDs to reindex |
| `clear_existing` | bool | `true` | Delete existing chunks before reindex (full reindex) |
| `priority` | int | `10` | Queue priority for this batch |

**Behavior**:
1. If `clear_existing = true`: delete chunks for the context, cancel pending queue items
2. Call each specified adapter's `discover_items()` for the context
3. Enqueue all discovered items via `Queue::enqueue_batch()`
4. Update `wp_wpaic_knowledge.processing_status = 'processing'`, `processing_progress = 0`

**Response** (202 Accepted):

```json
{
  "queued": 847,
  "queue_id": "550e8400-e29b-41d4-a716-446655440000",
  "estimated_batches": 170
}
```

**Error Codes**:
- `404` -- Context not found
- `409` -- Reindex already in progress (processing_status = 'processing')
  Body: `{"code": "reindex_in_progress", "message": "A reindex is already running."}`

---

### 3.7 Get Queue Status

**Route**: `GET /wpaic/v1/knowledge/{id}/queue`

**Response** (200):

```json
{
  "knowledge_id": 1,
  "processing_status": "processing",
  "processing_progress": 62,
  "queue": {
    "pending": 185,
    "processing": 5,
    "completed": 521,
    "failed": 3,
    "stalled": 1,
    "total": 715
  },
  "recent_errors": [
    {
      "id": 42,
      "item_type": "wp_post",
      "item_title": "My Post Title",
      "attempts": 3,
      "error_message": "Embedding API rate limit exceeded.",
      "created_at": "2026-03-01T11:00:00Z"
    }
  ],
  "last_updated": "2026-03-01T14:35:00Z"
}
```

**Admin Polling Contract**:

The admin JS polls this endpoint every **3 seconds** while `processing_status = 'processing'`.
Polling stops when `processing_status` becomes `completed` or `completed_with_errors`.

Polling implementation pattern (from MXC `knowledge-processing.js`):

```javascript
function pollQueueStatus(knowledgeId) {
    const poll = setInterval(async () => {
        const response = await wpAICApiRequest(`/wpaic/v1/knowledge/${knowledgeId}/queue`);
        updateProgressBar(response.processing_progress);
        updateQueueStats(response.queue);
        if (['completed', 'completed_with_errors'].includes(response.processing_status)) {
            clearInterval(poll);
            onIndexingComplete(response);
        }
    }, 3000);
}
```

---

### 3.8 Test Search

**Route**: `POST /wpaic/v1/knowledge/{id}/search`

**Purpose**: Inline test search console in admin (Baachal pattern from `tabs/search.php`).

**Request Body**:

```json
{
  "query": "How do I reset my password?",
  "top_k": 5,
  "mode": "auto"
}
```

| Field | Type | Default | Validation |
|-------|------|---------|------------|
| `query` | string | required | 1-500 chars, sanitized |
| `top_k` | int | 5 | 1-20 |
| `mode` | string | `auto` | enum: `local`, `pinecone`, `page`, `auto` |

**Response** (200):

```json
{
  "query": "How do I reset my password?",
  "mode_used": "local",
  "results": [
    {
      "chunk_id": "1#15#2",
      "title": "Account Help",
      "content": "To reset your password, click the Forgot Password link...",
      "score": 0.89,
      "url": "https://example.com/account-help/",
      "post_id": 15,
      "type": "wp_page"
    }
  ],
  "elapsed_ms": 42
}
```

**Error Codes**:
- `400` -- Invalid query
- `503` -- Embedding API unavailable

---

### 3.9 Retry Failed Queue Items

**Route**: `POST /wpaic/v1/knowledge/queue/retry`

**Request Body**:

```json
{
  "knowledge_id": 1,
  "item_ids": [42, 43]
}
```

`item_ids` is optional -- omit to retry all failed items for `knowledge_id`.

**Response** (200):

```json
{
  "retried": 2
}
```

---

### 3.10 Clear Failed Queue Items

**Route**: `DELETE /wpaic/v1/knowledge/queue/clear-failed`

**Request Parameters**: `knowledge_id` (int, required)

**Response** (200):

```json
{
  "deleted": 3
}
```

---

## 4. Admin Page Structure

The Knowledge admin page (`includes/admin/pages/class-knowledge-page.php`) renders:

### 4.1 Source Management (top section)

- Table of existing knowledge contexts with: name, context_type, embedding_model, status badge,
  chunk count, last indexed date, action buttons (Edit, Reindex, Delete)
- "Add Knowledge Base" button opens the Source Configuration Drawer

### 4.2 Source Configuration Drawer

Inline edit drawer (not a modal -- routine operation) with fields:

| Field | Input Type | Validation |
|-------|-----------|------------|
| Name | text | Required, 1-255 chars |
| Context Type | select: Local / Pinecone | Required |
| Embedding Provider | select (populated from registry) | Required |
| Embedding Model | select (populated from provider.get_embedding_models()) | Required |
| Auto-Sync Enabled | toggle | -- |
| Auto-Sync Mode | select: Updates only / Updates and new | Visible when auto-sync enabled |
| Post Types | checkboxes (registered post types) | Min 1 when auto-sync enabled |
| Chunk Size | range slider + number input (200-5000) | Integer |
| Overlap Size | range slider + number input (0-500) | Integer, < chunk_size |

### 4.3 Reindex Dashboard

When a reindex is in progress:
- Progress bar showing `processing_progress` percentage
- Stats: Completed / Failed / Pending / Total
- Error expansion table (from `recent_errors` in queue status response)
- "Retry Failed" button (calls 3.9)
- "Cancel" button (calls `Queue::clear($knowledge_id, false)` -- leaves pending items)

### 4.4 Test Search Console

Inline search panel (collapsed by default, Baachal pattern):
- Query input
- Mode select (auto/local/pinecone/page)
- Results displayed inline with score and excerpt

### 4.5 Index Stats Card

Per knowledge context:
- Chunk count
- Last indexed timestamp
- Embedding model in use
- Memory usage estimate for local mode: `chunk_count * 12KB`
- Warning banner if chunk_count > 10,000 and mode = 'local'

---

## 5. Error Response Format

All error responses use the WordPress REST API error format:

```json
{
  "code": "knowledge_not_found",
  "message": "No knowledge context exists with the specified ID.",
  "data": {
    "status": 404
  }
}
```

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `knowledge_not_found` | 404 | Context ID does not exist |
| `invalid_param` | 400 | Field validation failed |
| `reindex_in_progress` | 409 | Cannot start new reindex while one is running |
| `embedding_provider_unavailable` | 503 | Embedding API call failed during test search |
| `insufficient_permissions` | 403 | `manage_options` capability check failed |
| `invalid_nonce` | 401 | WP REST nonce validation failed |
