<?php

/**
 * OpenAI Embedding Adapter — implements Embedding_Provider for OpenAI text-embedding models.
 *
 * Supported models:
 *   text-embedding-3-small  1536 dimensions  $0.00002/1K tokens
 *   text-embedding-3-large  3072 dimensions  $0.00013/1K tokens
 *   text-embedding-ada-002  1536 dimensions  $0.00010/1K tokens
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag\Adapters;

use WPAIC\Rag\Embedding_Provider;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class OpenAI_Embedding
 *
 * @since 1.0.0
 * @package WPAIC\Rag\Adapters
 */
class OpenAI_Embedding implements Embedding_Provider
{
	/** Embeddings API endpoint. */
	private const API_URL = 'https://api.openai.com/v1/embeddings';

	/**
	 * Pricing in micro-cents per 1K tokens.
	 * (1 micro-cent = 1/1,000,000 USD cent)
	 *
	 * @var array<string, int>
	 */
	private const PRICING = [
		'text-embedding-3-small' => 2,   // $0.00002/1K tokens = 2 µ¢/1K
		'text-embedding-3-large' => 13,  // $0.00013/1K tokens = 13 µ¢/1K
		'text-embedding-ada-002' => 10,  // $0.00010/1K tokens = 10 µ¢/1K
	];

	/**
	 * Embedding vector dimensions per model.
	 *
	 * @var array<string, int>
	 */
	private const DIMENSIONS = [
		'text-embedding-3-small' => 1536,
		'text-embedding-3-large' => 3072,
		'text-embedding-ada-002' => 1536,
	];

	/** @var string OpenAI API key (plaintext). */
	private $api_key;

	/**
	 * @param string $api_key Plaintext OpenAI API key.
	 */
	public function __construct(string $api_key)
	{
		$this->api_key = $api_key;
	}

	/** {@inheritdoc} */
	public function get_id(): string
	{
		return 'openai';
	}

	/** {@inheritdoc} */
	public function get_label(): string
	{
		return 'OpenAI';
	}

	/** {@inheritdoc} */
	public function get_models(): array
	{
		return [
			'text-embedding-3-small' => 'text-embedding-3-small (1536d, recommended)',
			'text-embedding-3-large' => 'text-embedding-3-large (3072d, best quality)',
			'text-embedding-ada-002' => 'text-embedding-ada-002 (1536d, legacy)',
		];
	}

	/** {@inheritdoc} */
	public function embed(string $text, string $model)
	{
		$result = $this->call_api([$text], $model);

		if (is_wp_error($result)) {
			return $result;
		}

		return $result[0] ?? new \WP_Error('wpaic_embed_empty', __('Empty embedding response.', 'wp-ai-chatbot'));
	}

	/** {@inheritdoc} */
	public function embed_batch(array $texts, string $model)
	{
		if (empty($texts)) {
			return [];
		}

		return $this->call_api($texts, $model);
	}

	/** {@inheritdoc} */
	public function get_dimensions(string $model): int
	{
		return self::DIMENSIONS[$model] ?? 1536;
	}

	/** {@inheritdoc} */
	public function calculate_cost(int $token_count, string $model): int
	{
		$rate = self::PRICING[$model] ?? 2;

		// Rate is micro-cents per 1K tokens.
		return (int) ceil(($token_count / 1000) * $rate);
	}

	// =========================================================================
	// Internal
	// =========================================================================

	/**
	 * POST to the OpenAI Embeddings API and return indexed float vectors.
	 *
	 * @param  string[] $texts Texts to embed.
	 * @param  string   $model Model ID.
	 * @return array<int, float[]>|\WP_Error
	 */
	private function call_api(array $texts, string $model)
	{
		if ('' === $this->api_key) {
			return new \WP_Error(
				'wpaic_embed_no_key',
				__('OpenAI API key is not configured.', 'wp-ai-chatbot')
			);
		}

		$body = wp_json_encode([
			'input' => $texts,
			'model' => $model,
		]);

		if (false === $body) {
			return new \WP_Error(
				'wpaic_embed_encode',
				__('Failed to encode embedding request.', 'wp-ai-chatbot')
			);
		}

		$response = wp_remote_post(self::API_URL, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type' => 'application/json',
			],
			'body' => $body,
			'timeout' => 30,
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$raw = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if (200 !== $code || ! is_array($data)) {
			$msg = is_array($data)
				? ($data['error']['message'] ?? 'HTTP ' . $code)
				: 'HTTP ' . $code;

			return new \WP_Error('wpaic_embed_api', $msg);
		}

		$embeddings = [];

		if (isset($data['data']) && is_array($data['data'])) {
			foreach ($data['data'] as $item) {
				if (is_array($item) && isset($item['index'], $item['embedding'])) {
					$embeddings[(int) $item['index']] = $item['embedding'];
				}
			}
		}

		ksort($embeddings);

		return array_values($embeddings);
	}
}
