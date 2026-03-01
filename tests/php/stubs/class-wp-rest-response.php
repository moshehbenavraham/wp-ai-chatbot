<?php

/**
 * Minimal WP_REST_Response stub for unit tests outside WordPress.
 */
class WP_REST_Response
{
	/** @var mixed */
	public $data;

	/** @var int */
	public int $status;

	/** @var array<string, string> */
	public array $headers = [];

	/**
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 */
	public function __construct($data = null, int $status = 200)
	{
		$this->data = $data;
		$this->status = $status;
	}

	/**
	 * @return mixed
	 */
	public function get_data()
	{
		return $this->data;
	}

	public function get_status(): int
	{
		return $this->status;
	}

	public function header(string $name, string $value): void
	{
		$this->headers[$name] = $value;
	}
}
