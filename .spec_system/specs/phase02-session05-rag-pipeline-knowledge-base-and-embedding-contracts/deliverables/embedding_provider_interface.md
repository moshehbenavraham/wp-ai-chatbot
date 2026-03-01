# Embedding Provider Interface

**Session**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-RAG-004 (unified embedding provider architecture; Voyage AI; bot-specific keys; RES-XD-008)

---

## 1. Overview

This document defines the `Embedding_Interface` that all embedding provider adapters must implement.
Embedding providers are architecturally separate from chat providers because:

1. The method contract is fundamentally different (`embed()` vs `chat()`)
2. Embedding providers may be different from the chat provider for a given bot
3. Some providers offer chat but not embedding (e.g., Claude), and vice versa (Voyage AI)

However, embedding providers share the same registry infrastructure as chat providers (RES-XD-008):
they register via `wpaic_register_providers` with a `supports_embedding` capability flag, receive
the same configuration injection pattern, and are subject to the same compliance rules.

This design adopts AXI's `aichat_generate_embedding_override` extensibility (highest scored at 5)
and integrates MXC's Voyage AI provider and bot-specific key resolution.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/contexto-functions.php` | Primary: `generate_embedding()`, `aichat_generate_embedding_override` filter, per-context provider auto-detection |
| MxChat | `mxchat-basic/admin/class-knowledge-manager.php` | Voyage AI support, bot-specific API key/model resolution, dimension normalization |
| Baachal | `baachal/includes/content-indexer.php` | N/A -- no embedding capability |

---

## 2. Embedding_Interface

Located at `includes/provider/interface-embedding.php`, namespace `WPAIC\Provider`.

### PHP Signature

```php
<?php
namespace WPAIC\Provider;

/**
 * Embedding_Interface
 *
 * Contract for embedding provider adapters. Adapters that support embedding
 * generation must implement this interface in addition to Provider_Interface.
 *
 * The provider registry checks `$adapter instanceof Embedding_Interface`
 * and the `supports_embedding` capability flag to route embedding requests.
 *
 * Resolves: CFI-RAG-004 (unified embedding architecture; RES-XD-008 registry alignment)
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
interface Embedding_Interface {

    /**
     * Return the unique canonical embedding provider identifier.
     *
     * MAY match the chat provider ID (e.g., 'openai' for both chat and embedding)
     * or MAY differ for embedding-only providers (e.g., 'voyage' has no chat).
     *
     * Stored in `wp_wpaic_knowledge.embedding_provider` -- must not change once
     * published.
     *
     * @return string Embedding provider ID (lowercase, alphanumeric, hyphens allowed).
     */
    public function get_embedding_id();

    /**
     * Generate embeddings for a batch of text strings.
     *
     * Returns an array of float arrays, one per input text. The output array
     * MUST have the same count as the input $texts array. On partial failure,
     * failed positions return null (not omitted).
     *
     * The adapter is responsible for splitting inputs into batches if the
     * provider has per-request limits (see Section 4 for batch limits).
     *
     * @param string[] $texts  Array of text strings to embed (1 to batch_limit).
     *                         Empty strings in the array are skipped; their
     *                         positions return null.
     * @param string   $model  The embedding model identifier. Must be a model
     *                         supported by this provider.
     * @param array    $params {
     *     Optional parameters.
     *
     *     @type int    $dimensions     Target output dimensions (for truncatable models).
     *     @type string $input_type     Hint for Voyage AI: 'document' or 'query'.
     *     @type string $request_id     UUID for tracing (optional).
     * }
     * @return (float[]|null)[] Array of embedding float arrays. null for failed
     *                          or empty-string positions.
     */
    public function embed( $texts, $model, $params = array() );

    /**
     * Return supported embedding models with metadata.
     *
     * Used by the admin UI to populate model selection dropdowns and
     * validate per-context model configuration.
     *
     * @return array[] Array of model descriptor arrays:
     *     @type string $model_id    Model identifier (e.g., 'text-embedding-3-small').
     *     @type string $label       Human-readable model name.
     *     @type int    $dimensions  Output vector dimensions.
     *     @type int    $max_tokens  Maximum input tokens per text string.
     *     @type int    $batch_limit Maximum texts per embed() call for this model.
     *     @type float  $cost_per_1m Cost per 1 million input tokens (USD). 0 for free.
     */
    public function get_embedding_models();

    /**
     * Check whether embedding is currently available for this provider.
     *
     * Allows adapters to dynamically disable embedding based on API key
     * availability or quota status.
     *
     * @return bool True if embedding is available.
     */
    public function supports_embedding();
}
```

---

## 3. Relationship to Provider_Interface

The `Embedding_Interface` is independent of `Provider_Interface`. An adapter can implement:

| Scenario | Interfaces Implemented |
|----------|----------------------|
| Chat only (e.g., Claude -- no native embedding API) | `Provider_Interface` only |
| Chat + Embedding (e.g., OpenAI, Gemini) | `Provider_Interface` + `Embedding_Interface` |
| Embedding only (e.g., Voyage AI) | `Embedding_Interface` only |

The provider registry accommodates all three scenarios:

```php
// Registration with embedding capability
$registry->register( new OpenAI_Adapter( $config ), array(
    'supports_chat'      => true,
    'supports_streaming' => true,
    'supports_embedding' => true,
) );

$registry->register( new Voyage_Embedding_Adapter( $config ), array(
    'supports_chat'      => false,
    'supports_embedding' => true,
) );
```

---

## 4. Per-Provider Batch Limits and Model Registry

### Supported Embedding Providers

| Provider ID | Class | Chat? | Embedding? | Batch Limit | Default Model | Dimensions |
|-------------|-------|-------|-----------|-------------|---------------|-----------|
| `openai` | `OpenAI_Adapter` | Yes | Yes | 2048 texts | `text-embedding-3-small` | 1536 |
| `gemini` | `Gemini_Adapter` | Yes | Yes | 1 text | `text-embedding-004` | 768 |
| `voyage` | `Voyage_Embedding_Adapter` | No | Yes | 128 texts | `voyage-3-lite` | 512 |

**Note on Claude**: Claude (Anthropic) has no native embedding API. The Claude chat adapter does
NOT implement `Embedding_Interface`. Sites using Claude as their chat provider MUST configure a
separate embedding provider (typically OpenAI or Voyage AI).

### Model Registry Entries

```php
// OpenAI embedding models
'text-embedding-3-small' => array(
    'dimensions'  => 1536,
    'max_tokens'  => 8191,
    'batch_limit' => 2048,
    'cost_per_1m' => 0.02,  // USD per 1M tokens
),
'text-embedding-3-large' => array(
    'dimensions'  => 3072,
    'max_tokens'  => 8191,
    'batch_limit' => 2048,
    'cost_per_1m' => 0.13,
),
'text-embedding-ada-002' => array(
    'dimensions'  => 1536,
    'max_tokens'  => 8191,
    'batch_limit' => 2048,
    'cost_per_1m' => 0.10,
    'deprecated'  => true,
    'superseded_by' => 'text-embedding-3-small',
),

// Voyage AI embedding models
'voyage-3'      => array( 'dimensions' => 1024, 'max_tokens' => 32000, 'batch_limit' => 128, 'cost_per_1m' => 0.06 ),
'voyage-3-lite' => array( 'dimensions' => 512,  'max_tokens' => 32000, 'batch_limit' => 128, 'cost_per_1m' => 0.02 ),

// Gemini embedding models
'text-embedding-004' => array( 'dimensions' => 768, 'max_tokens' => 2048, 'batch_limit' => 1, 'cost_per_1m' => 0.00 ),
```

---

## 5. Embedding_Client: Dispatch Layer

The `Embedding_Client` class (`includes/rag/class-embedding-client.php`) is the RAG module's
interface to embedding providers. It encapsulates:
- Provider resolution from registry
- Override filter (`wpaic_generate_embedding_override`)
- Batch splitting for large inputs
- Error handling and null-fill on failure
- Cost tracking (stores usage in `wp_wpaic_usage_daily`)

### Class Interface

```php
<?php
namespace WPAIC\RAG;

class Embedding_Client {

    /**
     * Generate embeddings for an array of chunk texts.
     *
     * Resolves the embedding provider via:
     * 1. Check `wpaic_generate_embedding_override` filter (external provider hook)
     * 2. Look up `wp_wpaic_knowledge.embedding_provider` for bot-specific override
     * 3. Fall back to global `wpaic_embedding_provider` option
     * 4. Fall back to first available provider with supports_embedding=true
     *
     * @param array  $chunks      Array of chunk arrays from Chunker::chunk().
     * @param string $knowledge_id Knowledge context ID.
     * @return array Modified chunks with 'embedding' key added (float[]|null).
     */
    public function embed_chunks( $chunks, $knowledge_id );

    /**
     * Generate a single query embedding for retrieval.
     *
     * @param string $query       The user's search query text.
     * @param string $knowledge_id Knowledge context ID (for provider resolution).
     * @return float[]|null       Embedding float array, or null on failure.
     */
    public function embed_query( $query, $knowledge_id );
}
```

### Override Filter: `wpaic_generate_embedding_override`

Adopted verbatim from AXI's `aichat_generate_embedding_override` (DEC-RAG-005 extensibility score 5):

```php
/**
 * Override embedding generation with an external provider.
 *
 * Return a non-null value to bypass the built-in embedding dispatch.
 * The returned value MUST be a float array of the correct dimension.
 *
 * @param float[]|null $embedding    null (hook default).
 * @param string       $text         The text to embed.
 * @param string       $knowledge_id The knowledge context ID.
 * @param string       $model        The configured embedding model.
 * @return float[]|null              Embedding float array, or null to use built-in dispatch.
 */
$embedding = apply_filters( 'wpaic_generate_embedding_override', null, $text, $knowledge_id, $model );
```

---

## 6. Dimension Normalization (MXC Pattern)

When embeddings from different providers are mixed within the same knowledge context (e.g., after
changing the embedding provider mid-index), dimension mismatch causes cosine similarity to fail.

### Normalization Contract

- Each `wp_wpaic_knowledge` record stores `embedding_provider` and `embedding_model`
- On reindex, all existing embeddings for the context are deleted and regenerated
- The admin UI warns when changing `embedding_model` on an existing context: "Changing the
  embedding model requires re-indexing all content. Existing embeddings will be invalidated."
- Mixed-dimension chunks are detected at retrieval time: if `count($embedding) !== $expected_dim`,
  the chunk is skipped with a debug log entry

There is no runtime dimension normalization (e.g., padding or PCA). The invariant is: all chunks
in a knowledge context MUST use the same model. The admin UI enforces this via the warning and the
full-reindex requirement.

---

## 7. Embedding Adapter Compliance Rules

| Rule | Check | Failure Behavior |
|------|-------|------------------|
| ECR-01 | Class implements `Embedding_Interface` | `supports_embedding` capability ignored |
| ECR-02 | `get_embedding_id()` returns non-empty string | Registry rejects |
| ECR-03 | `embed()` returns array with same count as input | `Embedding_Client` logs mismatch, null-fills |
| ECR-04 | `embed()` never throws exceptions | Uncaught exception logged, null returned for batch |
| ECR-05 | `get_embedding_models()` returns non-empty array | Admin UI shows "no models available" error |

---

## 8. Adapter File and Class Naming

| Provider | File | Class | Namespace |
|----------|------|-------|-----------|
| OpenAI | `includes/provider/adapters/class-openai-adapter.php` | `OpenAI_Adapter` | `WPAIC\Provider\Adapters` |
| Gemini | `includes/provider/adapters/class-gemini-adapter.php` | `Gemini_Adapter` | `WPAIC\Provider\Adapters` |
| Voyage AI | `includes/provider/adapters/class-voyage-embedding-adapter.php` | `Voyage_Embedding_Adapter` | `WPAIC\Provider\Adapters` |

The interface file:

| File | Interface |
|------|-----------|
| `includes/provider/interface-embedding.php` | `WPAIC\Provider\Embedding_Interface` |

---

## 9. Source Anchor Comparison

| Feature | AxiaChat | MxChat | Baachal | WPAIC |
|---------|----------|--------|---------|-------|
| Providers | OpenAI, Gemini (Claude fallback to OpenAI) | OpenAI, Voyage AI, Gemini | None | OpenAI, Voyage AI, Gemini |
| Override filter | `aichat_generate_embedding_override` | None | N/A | `wpaic_generate_embedding_override` |
| Bot-specific keys | Per-context provider persistence | Per-bot API key + model | N/A | Per-knowledge-context config |
| Dimension normalization | None (assumes consistent model) | Yes (cross-provider) | N/A | Warning + full-reindex enforcement |
| Batch support | No (1 text per call) | Yes (Voyage AI batch) | N/A | Yes (per provider batch_limit) |
| Registry integration | No formal registry | No formal registry | N/A | Unified Provider_Registry with `supports_embedding` flag |
