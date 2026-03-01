<?php

/**
 * Embedding Provider Interface — vector embedding adapter contract.
 *
 * Implemented by every embedding backend (OpenAI, Gemini, etc.).
 * All costs are expressed in micro-cents (1/1,000,000 of a USD cent)
 * to avoid floating-point rounding errors.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Interface Embedding_Provider
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
interface Embedding_Provider
{
	/**
	 * Unique machine-readable ID (e.g. 'openai', 'gemini').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable label for admin display.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Return supported embedding model IDs and their labels.
	 *
	 * @return array<string, string> [model_id => label]
	 */
	public function get_models(): array;

	/**
	 * Embed a single text string.
	 *
	 * @param  string $text  Text to embed (should be pre-chunked).
	 * @param  string $model Embedding model ID.
	 * @return float[]|\WP_Error Float vector on success, WP_Error on failure.
	 */
	public function embed(string $text, string $model);

	/**
	 * Embed a batch of texts in a single API call where supported.
	 *
	 * @param  string[] $texts Texts to embed.
	 * @param  string   $model Embedding model ID.
	 * @return array<int, float[]>|\WP_Error Indexed float vectors or WP_Error.
	 */
	public function embed_batch(array $texts, string $model);

	/**
	 * Return the vector dimensionality for a model.
	 *
	 * @param  string $model Model ID.
	 * @return int
	 */
	public function get_dimensions(string $model): int;

	/**
	 * Calculate the embedding cost in micro-cents.
	 *
	 * @param  int    $token_count Number of input tokens.
	 * @param  string $model       Model ID.
	 * @return int Cost in micro-cents (1/1,000,000 USD cent).
	 */
	public function calculate_cost(int $token_count, string $model): int;
}
