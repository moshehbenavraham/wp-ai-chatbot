<?php

/**
 * Minimal WP_REST_Request stub for unit tests outside WordPress.
 */
class WP_REST_Request
{
	/** @var array<string, mixed> */
	private array $params = [];

	/** @var array<string, mixed>|null */
	private ?array $json_params = null;

	/**
	 * @param array<string, mixed>|null $json_params Simulated JSON body.
	 */
	public function __construct(?array $json_params = null)
	{
		$this->json_params = $json_params;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function get_param(string $key)
	{
		return $this->params[$key] ?? null;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set_param(string $key, $value): void
	{
		$this->params[$key] = $value;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_json_params(): ?array
	{
		return $this->json_params;
	}
}
