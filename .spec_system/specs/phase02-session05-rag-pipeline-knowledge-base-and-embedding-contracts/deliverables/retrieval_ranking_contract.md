# Retrieval and Ranking Contract

**Session**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-RAG-003 (ANN scalability decision -- progressive local + Pinecone architecture)

---

## 1. Overview

The `Retriever` class implements multi-mode similarity search over indexed chunks. It adopts
AxiaChat's 4-mode retrieval architecture (DEC-RAG-003, score 4.30 -- strongest retrieval) and
integrates MxChat's multi-provider Pinecone support with bot-specific namespacing.

This document includes the ANN scalability decision (Section 5) which formally resolves CFI-RAG-003.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/contexto-functions.php` | Primary: 4-mode retrieval (local/Pinecone/page/auto), cosine similarity, query WC enrichment |
| MxChat | `mxchat-basic/admin/class-knowledge-manager.php` | Pinecone integration, bot-specific namespaces, similarity threshold |
| Baachal | `baachal/admin/tabs/search.php` | N/A -- FULLTEXT MySQL only, below adoption threshold |
| Baachal | `baachal/includes/content-indexer.php` | N/A -- no vector retrieval |

---

## 2. Retriever Class Interface

Located at `includes/rag/class-retriever.php`, namespace `WPAIC\RAG`.

### `Retriever::retrieve()`

```php
<?php
namespace WPAIC\RAG;

/**
 * Retriever
 *
 * Multi-mode vector similarity search over indexed knowledge chunks.
 *
 * Adopts AxiaChat's 4-mode architecture (DEC-RAG-003):
 * local, pinecone, page, auto.
 *
 * @since 1.0.0
 * @package WPAIC\RAG
 */
class Retriever {

    /**
     * Retrieve relevant chunks for a query.
     *
     * @param float[]|null $query_embedding The query vector (from Embedding_Client::embed_query()).
     *                                       Null causes page mode fallback if mode != 'page'.
     * @param string       $knowledge_id    Knowledge context ID.
     * @param array        $params {
     *     Retrieval parameters.
     *
     *     @type string $mode               Retrieval mode: 'local', 'pinecone', 'page', 'auto'.
     *                                       Default 'auto' (resolved from wp_wpaic_knowledge).
     *     @type int    $top_k             Number of results to return (default 5, max 20).
     *     @type float  $threshold         Minimum cosine similarity score (default 0.70, range 0.0-1.0).
     *     @type int    $page_id           WordPress page ID for 'page' mode (0 = current post).
     *     @type string $pinecone_namespace Pinecone namespace override (default: bot slug).
     *     @type bool   $include_metadata  Include full metadata in results (default true).
     * }
     * @return array[] Array of result arrays, ordered by score descending. Empty on failure.
     *     Each result:
     *     @type string $chunk_id   Deterministic chunk ID.
     *     @type int    $chunk_index Position in parent document.
     *     @type string $content    Chunk text content.
     *     @type string $title      Source document title.
     *     @type string $url        Source canonical URL (empty for manual text).
     *     @type float  $score      Similarity score (0.0-1.0). 1.0 for page-mode exact matches.
     *     @type int    $post_id    WordPress post ID (0 for non-WP sources).
     *     @type string $type       Content type (wp_post, wp_page, wc_product, etc.).
     */
    public function retrieve( $query_embedding, $knowledge_id, $params = array() );
}
```

---

## 3. Retrieval Modes

### Mode: `local`

Loads all chunk embeddings for the knowledge context from `wp_wpaic_chunks` and computes
cosine similarity in PHP.

**Algorithm**:

```
1. SELECT id, chunk_index, content, title, embedding, tokens, type, post_id
   FROM wp_wpaic_chunks
   WHERE knowledge_id = ? AND embedding IS NOT NULL
   ORDER BY id ASC

2. For each chunk:
   score = cosine_similarity(query_embedding, json_decode(chunk.embedding))
   if score >= threshold: add to candidates

3. Sort candidates by score DESC

4. Return top_k candidates
```

**Cosine Similarity**:

```php
/**
 * @param float[] $a Vector A (query embedding).
 * @param float[] $b Vector B (chunk embedding).
 * @return float Cosine similarity in range [-1.0, 1.0]. Returns 0.0 on dimension mismatch.
 */
private function cosine_similarity( array $a, array $b ): float {
    if ( count($a) !== count($b) ) {
        return 0.0; // Dimension mismatch -- log at debug level
    }
    $dot_product = 0.0;
    $norm_a = 0.0;
    $norm_b = 0.0;
    foreach ( $a as $i => $val ) {
        $dot_product += $val * $b[$i];
        $norm_a += $val * $val;
        $norm_b += $b[$i] * $b[$i];
    }
    if ( $norm_a === 0.0 || $norm_b === 0.0 ) {
        return 0.0;
    }
    return $dot_product / (sqrt($norm_a) * sqrt($norm_b));
}
```

**Memory constraint**: Loading all embeddings into PHP memory is bounded by chunk count and
embedding dimension. See Section 5 (ANN Decision) for the documented 10K chunk practical limit.

### Mode: `pinecone`

Queries the Pinecone vector database via HTTPS API.

**Pinecone Query Request**:

```php
$body = array(
    'namespace'     => $this->resolve_pinecone_namespace($knowledge_id, $params),
    'vector'        => $query_embedding,
    'topK'          => $params['top_k'],
    'includeValues' => false,
    'includeMetadata' => true,
    'filter'        => array(
        'knowledge_id' => $knowledge_id,
    ),
);
```

**Pinecone Endpoint Security** (AXI pattern verbatim):
- URL MUST start with `https://`
- URL domain MUST be in the `wpaic_pinecone_allowed_domains` filter allowlist
- Default allowlist: `['api.pinecone.io']`
- `apply_filters( 'wpaic_pinecone_allowed_domains', array( 'api.pinecone.io' ) )`
- Any violation results in `WP_Error` with code `pinecone_domain_not_allowed`

**Namespace Resolution**:

```php
private function resolve_pinecone_namespace( $knowledge_id, $params ) {
    // 1. Explicit override from params
    if ( ! empty( $params['pinecone_namespace'] ) ) {
        return sanitize_key( $params['pinecone_namespace'] );
    }
    // 2. Bot slug from knowledge context
    $knowledge = $this->get_knowledge_context( $knowledge_id );
    if ( $knowledge && ! empty( $knowledge->name ) ) {
        return sanitize_key( $knowledge->name );
    }
    // 3. Fallback
    return 'wpaic_default';
}
```

**Error handling**: On Pinecone API error (non-200), return empty array. Log error to structured
logger at `warning` level with HTTP status code and sanitized message (no API keys in log).

**Upsert on index**: When storing embeddings in Pinecone mode, each chunk is upserted to Pinecone
with metadata `{knowledge_id, post_id, chunk_index, type, title, url}`. The upsert happens during
the queue worker's embedding phase, not at retrieval time.

### Mode: `page`

Returns chunks matching the current WordPress page context. No embedding query needed.

```php
// Get page_id from params, fallback to get_the_ID()
$page_id = isset($params['page_id']) && $params['page_id'] > 0
    ? (int) $params['page_id']
    : (int) get_the_ID();

// Fetch all chunks for this page
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT id, chunk_index, content, title, type, post_id
     FROM {$chunks_table}
     WHERE knowledge_id = %s AND post_id = %d AND embedding IS NOT NULL
     ORDER BY chunk_index ASC
     LIMIT %d",
    $knowledge_id, $page_id, $params['top_k']
) );

// Page-mode chunks get score = 1.0 (exact match)
foreach ( $results as $row ) {
    $row->score = 1.0;
}
```

### Mode: `auto`

Resolves the retrieval mode from `wp_wpaic_knowledge.context_type`:

| `context_type` | Resolved Mode |
|----------------|---------------|
| `local` | `local` |
| `pinecone` | `pinecone` |
| (any) when on a WP post | `page` if current post has indexed chunks, else `local` |
| (default) | `local` |

The `auto` mode also applies query embedding fallback: if `$query_embedding` is null (embedding
API failed), `auto` falls back to `page` mode if a page_id is available, otherwise returns empty.

---

## 4. Result Format

All modes return results in the same normalized format:

```php
array(
    array(
        'chunk_id'    => '1#42#0',              // Deterministic chunk ID
        'chunk_index' => 0,                     // Position in parent document
        'content'     => 'This is chunk text.', // Plain text content
        'title'       => 'My Page Title',       // Source document title
        'url'         => 'https://example.com/my-page/', // Canonical URL
        'score'       => 0.87,                  // Similarity score (0.0-1.0)
        'post_id'     => 42,                    // WP post ID (0 for non-WP)
        'type'        => 'wp_page',             // Content type
    ),
    // ... more results
)
```

Results are always ordered by `score DESC`. The `Context_Assembler` consumes this format directly.

---

## 5. ANN Index Scalability Decision

### CFI-RAG-003 Resolution

**Decision**: Progressive local + Pinecone architecture. No ANN library required for MVP.

**Evaluated Options**:

| Option | Verdict | Rationale |
|--------|---------|-----------|
| SQLite VSS (sqlite-vss) | Rejected | Requires PHP `pdo_sqlite` extension with SQLite 3.38+ (not standard on shared hosting). Non-trivial installation. Small PHP ecosystem support. |
| HNSW in PHP | Rejected | No stable, maintained PHP HNSW library exists as of 2026. PHP is not the right language for this computation. |
| C extension for ANN | Rejected | Requires compilation. Incompatible with shared hosting environments. |
| Redis + RediSearch | Rejected | Requires Redis with RediSearch module. Not standard on WP hosting. Adds mandatory external dependency. |
| Pinecone (SaaS) | Accepted | Production-grade managed vector DB. Optional, not mandatory. Proven at scale. Well-integrated in AXI (score 4.30). |
| Brute-force PHP cosine | Accepted (MVP) | Simple, no dependencies, reliable. Bounded by practical limit. |

**Practical Limit Calculation for Local Mode**:

```
text-embedding-3-small: 1536 floats per vector
Storage per chunk: 1536 * 8 bytes (PHP float) = 12,288 bytes = ~12 KB
10,000 chunks: 10,000 * 12 KB = 122 MB PHP heap

With typical WP PHP memory limit of 256 MB:
- 10,000 chunks: ~48% memory utilization (safe)
- 15,000 chunks: ~72% memory utilization (warning threshold)
- 20,000 chunks: exceeds safe limit for most setups

Practical MVP limit: 10,000 chunks (logged as admin warning above this threshold)
```

**Admin Warning Trigger**:

When local mode is configured and `count(wp_wpaic_chunks WHERE knowledge_id=?) > 10000`:
- Admin shows: "Warning: This knowledge base has over 10,000 chunks. Local retrieval may be
  slow or run out of memory. Consider switching to Pinecone for production use."
- Logged at `info` level via structured logger

**Migration Path**: Sites that outgrow local mode configure Pinecone API key in settings,
switch `wp_wpaic_knowledge.context_type = 'pinecone'`, and trigger a reindex. The existing
`wp_wpaic_chunks` rows are upserted to Pinecone by the queue worker.

### Long-Term ANN Roadmap (Post-MVP)

Document for Phase 06+ consideration:
- SQLite VSS becomes viable when PHP hosting standardizes SQLite 3.38+ with `pdo_sqlite`
- pgvector via WP-managed PostgreSQL (WP Engine, Flywheel) -- possible extension point
- Extension hook `wpaic_retriever_local_search` allows third-party ANN plugins to override
  the brute-force cosine loop

---

## 6. Retrieval Hooks

### Filter: `wpaic_retriever_local_search`

Allows third-party ANN plugins to replace the brute-force cosine loop:

```php
/**
 * @param array[]|null $results          null (default -- use brute-force cosine).
 * @param float[]      $query_embedding  The query vector.
 * @param string       $knowledge_id     The knowledge context ID.
 * @param int          $top_k            Number of results to return.
 * @param float        $threshold        Minimum score threshold.
 * @return array[]|null  Array of result arrays (same format as Retriever output), or null to proceed with brute-force.
 */
$results = apply_filters( 'wpaic_retriever_local_search', null, $query_embedding, $knowledge_id, $top_k, $threshold );
```

### Filter: `wpaic_retrieval_results`

Post-processing hook on all retrieval results before they reach the Context_Assembler:

```php
/**
 * @param array[]  $results      Array of result arrays from Retriever.
 * @param string   $knowledge_id The knowledge context ID.
 * @param string   $mode         The retrieval mode used.
 * @return array[] Modified results array.
 */
$results = apply_filters( 'wpaic_retrieval_results', $results, $knowledge_id, $mode );
```

---

## 7. Source Anchor Comparison

| Feature | AxiaChat | MxChat | Baachal | WPAIC |
|---------|----------|--------|---------|-------|
| Retrieval modes | local, Pinecone, page, auto | local + Pinecone | FULLTEXT MySQL + LIKE | local, pinecone, page, auto (AXI) |
| Local algorithm | O(n) cosine similarity | O(n) cosine similarity | FULLTEXT/LIKE | O(n) cosine, ANN extension hook |
| Pinecone security | HTTPS-only, domain allowlist filter | API key in options | N/A | HTTPS-only, `wpaic_pinecone_allowed_domains` filter |
| Top-K | Configurable | Configurable (3-10) | Result limit config | 5 default, 20 max |
| Threshold | Not configurable (code-level) | Configurable (10-95%) | N/A | 0.70 default, configurable per context |
| Namespace | Per-context | Per-bot | N/A | Per-knowledge-context (resolved from context name) |
| ANN | None | None | N/A | None for MVP; extension hook for future |
