# Chunking Module Interface

**Session**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-RAG-002 (unified chunking: AXI overlap-based + MXC configurable size + MXC deterministic IDs)

---

## 1. Overview

The `Chunker` class splits plain text content into appropriately sized, overlapping segments for
embedding and storage. It merges the winning patterns from both adopt-scored sources:

- **AXI**: Overlap-based chunking (1000 words target, 180 words overlap) preserves context at
  boundaries -- selected as tie-break winner in DEC-RAG-002
- **MXC**: Admin-configurable chunk size, deterministic vector IDs for reliable upsert/delete
- **WPAIC addition**: DB column storage (not inline JSON metadata prefix as in MXC)

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/contexto-functions.php` | Primary: overlap-based chunking, word-count targeting, token count tracking |
| MxChat | `mxchat-basic/includes/class-mxchat-chunker.php` | Configurable chunk size, deterministic vector IDs, 3-tier splitting |
| Baachal | `baachal/includes/content-indexer.php` | N/A -- no chunking, stores full post content as single record |

---

## 2. Chunker Class Interface

Located at `includes/rag/class-chunker.php`, namespace `WPAIC\RAG`.

### `Chunker::chunk()`

```php
<?php
namespace WPAIC\RAG;

/**
 * Chunker
 *
 * Splits text content into overlapping segments for embedding.
 *
 * Merges AxiaChat's overlap-based word-count chunking with MxChat's
 * configurable chunk size and deterministic vector ID generation.
 *
 * @since 1.0.0
 * @package WPAIC\RAG
 */
class Chunker {

    /**
     * Split text into overlapping chunks.
     *
     * @param string $text    Clean plain text to chunk (post-extraction, post-filter).
     * @param array  $options {
     *     Chunking configuration.
     *
     *     @type int    $chunk_size        Target chunk size in words (default: 1000, range: 200-5000).
     *     @type int    $overlap_size      Overlap size in words (default: 150, range: 0-500).
     *                                    MUST be less than chunk_size.
     *     @type string $knowledge_id      Knowledge context ID (for deterministic chunk IDs).
     *     @type int    $post_id           WordPress post ID (for deterministic chunk IDs, 0 for non-WP).
     *     @type bool   $count_tokens      Whether to estimate token count per chunk (default: true).
     * }
     * @return array[] Array of chunk arrays. Empty array if text is empty.
     *     Each chunk:
     *     @type string $chunk_id      Deterministic ID: "{knowledge_id}#{post_id}#{chunk_index}".
     *     @type int    $chunk_index   Zero-based position in the chunk sequence.
     *     @type string $content       Chunk text content.
     *     @type int    $token_count   Estimated token count (0 if count_tokens = false).
     *     @type int    $word_count    Actual word count of this chunk.
     *     @type int    $overlap_start Word position where overlap with previous chunk begins.
     *     @type int    $overlap_end   Word position where this chunk's overlap with next chunk ends.
     */
    public static function chunk( $text, $options = array() );
}
```

### Option Defaults and Validation

| Option | Default | Min | Max | Behavior on Violation |
|--------|---------|-----|-----|----------------------|
| `chunk_size` | `1000` | `200` | `5000` | Clamp to range, log warning |
| `overlap_size` | `150` | `0` | `500` | Clamp to range, log warning |
| `overlap_size` vs `chunk_size` | must be < `chunk_size` | — | — | Set to `chunk_size / 5` if violated |
| `knowledge_id` | `'0'` | — | — | Use `'0'` as fallback |
| `post_id` | `0` | — | — | Use `0` as fallback |
| `count_tokens` | `true` | — | — | — |

---

## 3. Chunking Algorithm

### 3.1 Word-Count Split with Overlap (AXI Pattern)

```
Input: text (plain text)
       chunk_size = W words
       overlap_size = O words

1. Tokenize text into words array by splitting on whitespace
2. total_words = count(words)
3. If total_words <= chunk_size: return [single chunk with all text]
4. step = chunk_size - overlap_size   (effective advance per chunk)
5. i = 0, chunk_index = 0
6. While i < total_words:
     start = max(0, i - overlap_start_compensation)
     end   = min(total_words, i + chunk_size)
     chunk_words = words[start:end]
     chunk_text  = implode(' ', chunk_words)
     overlap_start = 0 if i == 0 else (i - start)
     overlap_end   = end - (i + chunk_size - overlap_size)  [words that will overlap with next]
     emit chunk with chunk_index
     chunk_index++
     i += step
```

**Example** (chunk_size=5, overlap_size=2, text="A B C D E F G H I J"):

| Chunk | Words | Content | Overlap Start | Overlap End |
|-------|-------|---------|--------------|-------------|
| 0 | 5 | A B C D E | 0 | 2 (D E overlap into chunk 1) |
| 1 | 5 | D E F G H | 2 | 2 (G H overlap into chunk 2) |
| 2 | 5 | G H I J _ | 2 | 0 (last chunk, no overlap) |

### 3.2 Deterministic Chunk ID (MXC Pattern)

```
chunk_id = "{knowledge_id}#{post_id}#{chunk_index}"
```

Examples:
- `"1#42#0"` -- knowledge context 1, post 42, first chunk
- `"1#42#1"` -- knowledge context 1, post 42, second chunk
- `"2#0#0"` -- knowledge context 2, non-WP source (post_id=0), first chunk

The deterministic ID enables idempotent upsert:
```sql
INSERT INTO wp_wpaic_chunks (knowledge_id, post_id, chunk_index, content, ...)
VALUES (...)
ON DUPLICATE KEY UPDATE content = VALUES(content), embedding = NULL, updated_at = NOW()
```

Setting `embedding = NULL` on content update forces re-embedding on the next worker tick.

### 3.3 Token Count Estimation

Token count is estimated (not exact) using the heuristic: `words * 1.33` rounded up.
This approximates OpenAI's tokenization where 1 word ≈ 1.3 tokens.

For exact token counts (required for Pinecone and some embedding providers), the
`Embedding_Client` recalculates tokens using the provider's actual tokenizer if available.
The chunker's estimate is stored as `wp_wpaic_chunks.tokens` and updated after embedding.

---

## 4. Storage Contract

After chunking, the worker stores results in `wp_wpaic_chunks` (session 02 schema):

```php
foreach ( $chunks as $chunk ) {
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$chunks_table}
             (knowledge_id, post_id, chunk_index, type, title, content, tokens, created_at)
             VALUES (%d, %d, %d, %s, %s, %s, %d, NOW())
             ON DUPLICATE KEY UPDATE
               content     = VALUES(content),
               tokens      = VALUES(tokens),
               embedding   = NULL,
               updated_at  = NOW()",
            $knowledge_id,
            $post_id,
            $chunk['chunk_index'],
            $item_type,
            $metadata['title'],
            $chunk['content'],
            $chunk['token_count']
        )
    );
}
```

**Note**: `embedding` is set to NULL on content update so the worker re-embeds on the next tick.
The upsert uses the `uniq_post_knowledge_chunk (post_id, knowledge_id, chunk_index)` index from
the session 02 schema.

---

## 5. Pre-Chunk and Post-Chunk Hooks

### Filter: `wpaic_rag_pre_chunk`

Applied by the source adapter before calling `Chunker::chunk()` (see
`content_source_adapter_interface.md`). The Chunker does NOT apply this filter internally --
it receives already-filtered text.

### Filter: `wpaic_rag_chunk_options`

Allows per-knowledge-context chunk option overrides:

```php
/**
 * @param array  $options      Default chunk options (chunk_size, overlap_size, etc.).
 * @param string $knowledge_id The knowledge context ID.
 * @param string $item_type    The item type being chunked.
 * @return array Modified chunk options.
 */
$options = apply_filters( 'wpaic_rag_chunk_options', $options, $knowledge_id, $item_type );
```

### Action: `wpaic_rag_chunks_created`

```php
/**
 * @param array  $chunks       Array of chunk arrays from Chunker::chunk().
 * @param string $knowledge_id The knowledge context ID.
 * @param int    $post_id      WordPress post ID.
 * @param string $item_type    The item type constant.
 */
do_action( 'wpaic_rag_chunks_created', $chunks, $knowledge_id, $post_id, $item_type );
```

---

## 6. Edge Cases and Constraints

### Empty Content

If `$text` is empty, whitespace-only, or fewer than 20 characters, `Chunker::chunk()` returns
an empty array `[]`. The worker marks the queue item as `completed` with `error_message = 'no_content'`.

### Single-Chunk Content

Content shorter than `chunk_size` words is returned as a single chunk (index 0) with
`overlap_start = 0` and `overlap_end = 0`.

### Very Long Words

Non-Latin scripts and compound words may have high char-to-word ratios. The chunker uses
`preg_split('/\s+/', trim($text))` for word splitting, which correctly handles Unicode text.

### Memory Safety

The chunker operates on the words array in memory. For the 500K character content limit defined
in `content_source_adapter_interface.md`, the maximum words array is approximately:
- 500,000 chars / 5 chars/word average = ~100,000 words
- 100,000 words at 40 bytes/word = ~4MB peak memory
- This is safe within typical WP PHP memory limits (64-256MB)

---

## 7. Admin Configuration

Chunk settings are stored in `wp_wpaic_knowledge.indexing_options` JSON:

```json
{
  "chunk_size": 1000,
  "overlap_size": 150,
  "include_excerpt": false,
  "include_url": true,
  "include_categories": false,
  "include_tags": false,
  "include_custom_fields": []
}
```

Default values are used when `indexing_options` is NULL. Admin UI provides number inputs for
`chunk_size` (range slider: 200-5000, default 1000) and `overlap_size` (range slider: 0-500,
default 150) on the Knowledge context configuration drawer.

---

## 8. Source Anchor Comparison

| Feature | AxiaChat | MxChat | Baachal | WPAIC |
|---------|----------|--------|---------|-------|
| Chunking strategy | Word-count with overlap (1000/180) | 3-tier: paragraph/sentence/word (4000 chars default) | None (full post as one record) | Word-count with overlap (configurable 1000/150 default) |
| Chunk ID | None (auto-increment only) | Deterministic hash (base64) | N/A | Deterministic `{knowledge_id}#{post_id}#{chunk_index}` |
| Overlap | Yes (180 words) | No overlap (reassembles at retrieval) | N/A | Yes (configurable, default 150 words) |
| Configurable size | No (code-level constants) | Yes (admin UI, 1000-10000 chars) | N/A | Yes (admin UI, 200-5000 words) |
| Token tracking | Yes (per chunk) | No (estimated via phrase length) | N/A | Yes (estimated, updated after embed) |
| Storage | Separate DB columns | Inline JSON metadata prefix | Single full-text record | Separate DB columns (AXI pattern) |
