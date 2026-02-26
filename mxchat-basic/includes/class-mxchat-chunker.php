<?php
/**
 * MxChat Chunker - Text chunking utility for RAG optimization
 *
 * Splits large content into chunks for improved semantic retrieval.
 * All chunks for a URL are reassembled before sending to AI, so no overlap is needed.
 *
 * @package MxChat
 * @since 2.6.3
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Chunker {

    /**
     * Maximum characters per chunk
     * @var int
     */
    private $chunk_size;

    /**
     * Constructor
     *
     * @param int $chunk_size Characters per chunk (default 4000 ≈ 1000 tokens)
     */
    public function __construct($chunk_size = 4000) {
        $this->chunk_size = max(1000, min(10000, intval($chunk_size)));
    }

    /**
     * Get chunking settings from WordPress options
     *
     * @return array Array with chunk_size and chunking_enabled
     */
    public static function get_settings() {
        $options = get_option('mxchat_options', array());

        return array(
            'chunk_size' => isset($options['chunk_size']) ? intval($options['chunk_size']) : 4000,
            'chunking_enabled' => isset($options['chunking_enabled']) ? (bool) $options['chunking_enabled'] : true
        );
    }

    /**
     * Create a chunker instance with settings from WordPress options
     *
     * @return MxChat_Chunker
     */
    public static function from_settings() {
        $settings = self::get_settings();
        return new self($settings['chunk_size']);
    }

    /**
     * Check if content should be chunked
     *
     * @param string $text Content to evaluate
     * @return bool True if content should be chunked
     */
    public function should_chunk($text) {
        $settings = self::get_settings();

        // Check if chunking is enabled globally
        if (!$settings['chunking_enabled']) {
            return false;
        }

        // Only chunk if content exceeds chunk size
        return strlen($text) > $this->chunk_size;
    }

    /**
     * Split text into chunks
     *
     * Algorithm:
     * 1. Split content by paragraph boundaries
     * 2. Accumulate paragraphs until chunk size exceeded
     * 3. Start new chunk (no overlap needed since we reassemble all chunks)
     *
     * @param string $text Content to chunk
     * @return array Array of chunk strings
     */
    public function chunk_text($text) {
        // Handle empty content
        if (empty(trim($text))) {
            return array();
        }

        // Handle content smaller than chunk size - return as single chunk
        if (strlen($text) <= $this->chunk_size) {
            return array(trim($text));
        }

        $chunks = array();
        $paragraphs = preg_split('/\n\s*\n/', $text); // Split by paragraph boundaries
        $current_chunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            // Skip empty paragraphs
            if (empty($paragraph)) {
                continue;
            }

            // Calculate size if we add this paragraph
            $separator = empty($current_chunk) ? '' : "\n\n";
            $potential_size = strlen($current_chunk) + strlen($separator) + strlen($paragraph);

            // If adding this paragraph exceeds chunk size
            if ($potential_size > $this->chunk_size && !empty($current_chunk)) {
                // Save current chunk and start fresh
                $chunks[] = trim($current_chunk);
                $current_chunk = $paragraph;
            } else {
                // Add paragraph to current chunk
                $current_chunk .= $separator . $paragraph;
            }

            // Handle very long paragraphs that exceed chunk size on their own
            if (strlen($current_chunk) > $this->chunk_size) {
                $split_chunks = $this->split_long_paragraph($current_chunk);

                // Add all but the last split chunk
                for ($i = 0; $i < count($split_chunks) - 1; $i++) {
                    $chunks[] = trim($split_chunks[$i]);
                }

                // Keep the last one as current chunk (may accumulate more)
                $current_chunk = $split_chunks[count($split_chunks) - 1];
            }
        }

        // Add final chunk if not empty
        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Split a very long paragraph into chunks
     *
     * Used when a single paragraph exceeds chunk size.
     * Splits by sentences, then by words if needed.
     *
     * @param string $paragraph Long paragraph to split
     * @return array Array of chunk strings
     */
    private function split_long_paragraph($paragraph) {
        $chunks = array();

        // First try splitting by sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
        $current_chunk = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            // If single sentence is too long, split by words
            if (strlen($sentence) > $this->chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = '';
                }

                // Split long sentence by words
                $word_chunks = $this->split_by_words($sentence);
                foreach ($word_chunks as $word_chunk) {
                    $chunks[] = $word_chunk;
                }
                continue;
            }

            $separator = empty($current_chunk) ? '' : ' ';
            $potential_size = strlen($current_chunk) + strlen($separator) + strlen($sentence);

            if ($potential_size > $this->chunk_size && !empty($current_chunk)) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $sentence;
            } else {
                $current_chunk .= $separator . $sentence;
            }
        }

        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Split text by words when sentences are too long
     *
     * Last resort splitting method for very long unbroken text.
     *
     * @param string $text Text to split
     * @return array Array of chunk strings
     */
    private function split_by_words($text) {
        $chunks = array();
        $words = preg_split('/\s+/', $text);
        $current_chunk = '';

        foreach ($words as $word) {
            $separator = empty($current_chunk) ? '' : ' ';
            $potential_size = strlen($current_chunk) + strlen($separator) + strlen($word);

            if ($potential_size > $this->chunk_size && !empty($current_chunk)) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $word;
            } else {
                $current_chunk .= $separator . $word;
            }
        }

        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Create chunk metadata for storage
     *
     * @param int $chunk_index 0-based index of this chunk
     * @param int $total_chunks Total number of chunks for this content
     * @param string $source_url Original source URL
     * @return array Metadata array
     */
    public static function create_chunk_metadata($chunk_index, $total_chunks, $source_url) {
        return array(
            'document_type' => 'chunked',
            'chunk_index' => intval($chunk_index),
            'total_chunks' => intval($total_chunks),
            'source_url' => $source_url,
            'parent_url_hash' => md5($source_url)
        );
    }

    /**
     * Format chunk content with metadata prefix (for WordPress DB storage)
     *
     * @param string $chunk_content The chunk text
     * @param array $metadata Chunk metadata
     * @return string Formatted content with JSON prefix
     */
    public static function format_chunk_for_storage($chunk_content, $metadata) {
        return wp_json_encode($metadata) . "\n---\n" . $chunk_content;
    }

    /**
     * Parse chunk content to extract metadata and text
     *
     * @param string $stored_content Content from database
     * @return array Array with 'metadata' and 'text' keys
     */
    public static function parse_stored_chunk($stored_content) {
        // Check if content has metadata prefix
        if (strpos($stored_content, '{"document_type"') === 0) {
            $parts = explode("\n---\n", $stored_content, 2);

            if (count($parts) === 2) {
                $metadata = json_decode($parts[0], true);
                return array(
                    'metadata' => $metadata ?: array(),
                    'text' => $parts[1],
                    'is_chunked' => isset($metadata['document_type']) && $metadata['document_type'] === 'chunked'
                );
            }
        }

        // Non-chunked content
        return array(
            'metadata' => array(),
            'text' => $stored_content,
            'is_chunked' => false
        );
    }

    /**
     * Generate vector ID for a chunk
     *
     * @param string $source_url Original source URL
     * @param int $chunk_index 0-based chunk index
     * @return string Vector ID in format: {md5(url)}_chunk_{index}
     */
    public static function generate_chunk_vector_id($source_url, $chunk_index) {
        $base_id = md5($source_url);
        return $base_id . '_chunk_' . intval($chunk_index);
    }

    /**
     * Extract base URL hash from a chunk vector ID
     *
     * @param string $vector_id Vector ID to parse
     * @return string|null Base URL hash or null if not a chunk ID
     */
    public static function get_base_hash_from_vector_id($vector_id) {
        if (preg_match('/^([a-f0-9]{32})_chunk_\d+$/', $vector_id, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Check if a vector ID is a chunk ID
     *
     * @param string $vector_id Vector ID to check
     * @return bool True if this is a chunk vector ID
     */
    public static function is_chunk_vector_id($vector_id) {
        return (bool) preg_match('/^[a-f0-9]{32}_chunk_\d+$/', $vector_id);
    }
}
