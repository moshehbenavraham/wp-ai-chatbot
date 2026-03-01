<?php

/**
 * Minimal WP_Error stub for unit tests outside WordPress.
 */
class WP_Error
{
	/** @var string */
	public string $code;

	/** @var string */
	public string $message;

	/** @var array<string, mixed> */
	public array $error_data;

	/**
	 * @param string              $code    Error code.
	 * @param string              $message Error message.
	 * @param array<string,mixed> $data    Optional error data.
	 */
	public function __construct(string $code = '', string $message = '', array $data = [])
	{
		$this->code = $code;
		$this->message = $message;
		$this->error_data = $data;
	}

	public function get_error_code(): string
	{
		return $this->code;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_error_data(): array
	{
		return $this->error_data;
	}
}
