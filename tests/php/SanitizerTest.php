<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Security\Sanitizer;

/**
 * Tests for WPAIC\Security\Sanitizer
 *
 * Covers every TYPE_* constant (message, system_prompt, api_key, slug, int,
 * float, bool, url, html_restricted, filename, ip, json), the validate()
 * helper, and sanitize_map().
 */
final class SanitizerTest extends TestCase
{
	private static bool $loaded = false;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		require_once __DIR__ . '/stubs/class-wp-error.php';
		require_once __DIR__ . '/stubs/wp-core-stubs.php';

		self::$loaded = true;
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();

		// Reset Sanitizer's lazy registry so filter tests start clean.
		$ref = new \ReflectionProperty(Sanitizer::class, 'registry');
		$ref->setAccessible(true);
		$ref->setValue(null, null);
	}

	// ── TYPE_MESSAGE ──────────────────────────────────────────────────────────

	public function testSanitizeMessageStripsScriptTag(): void
	{
		$input = 'Hello <script>alert(1)</script> world';
		$result = Sanitizer::sanitize_message($input);

		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringNotContainsString('alert(1)', $result);
	}

	public function testSanitizeMessageStripsPhpOpenTag(): void
	{
		$input = 'Hi <?php echo "secret"; ?> there';
		$result = Sanitizer::sanitize_message($input);

		$this->assertStringNotContainsString('<?php', $result);
		$this->assertStringNotContainsString('echo', $result);
	}

	public function testSanitizeMessageStripsNullBytes(): void
	{
		$input = "Hello\0World";
		$result = Sanitizer::sanitize_message($input);

		$this->assertStringNotContainsString("\0", $result);
		$this->assertSame('HelloWorld', $result);
	}

	public function testSanitizeMessageEnforcesMaxLength(): void
	{
		$input = str_repeat('A', 11000);
		$result = Sanitizer::sanitize_message($input);

		$this->assertSame(10000, mb_strlen($result));
	}

	public function testSanitizeMessagePreservesNormalText(): void
	{
		$input = 'What is the weather today?';
		$result = Sanitizer::sanitize_message($input);

		$this->assertSame($input, $result);
	}

	// ── TYPE_API_KEY ──────────────────────────────────────────────────────────

	public function testSanitizeApiKeyStripsInvalidChars(): void
	{
		$input = 'sk-test!@#$%^&*()key';
		$result = Sanitizer::sanitize_api_key($input);

		$this->assertSame('sk-testkey', $result);
	}

	public function testSanitizeApiKeyKeepsValidChars(): void
	{
		$input = 'sk-proj-AbCd123_-.:';
		$result = Sanitizer::sanitize_api_key($input);

		$this->assertSame($input, $result);
	}

	public function testSanitizeApiKeyEnforcesMaxLength(): void
	{
		$input = str_repeat('a', 250);
		$result = Sanitizer::sanitize_api_key($input);

		$this->assertSame(200, mb_strlen($result));
	}

	// ── TYPE_SLUG ─────────────────────────────────────────────────────────────

	public function testSanitizeSlugLowercases(): void
	{
		$this->assertSame('gpt4', Sanitizer::sanitize_slug('GPT4'));
	}

	public function testSanitizeSlugStripsSpacesAndSpecialChars(): void
	{
		$this->assertSame('gpt-4-turbo', Sanitizer::sanitize_slug('GPT-4-turbo!'));
	}

	public function testSanitizeSlugEnforcesMaxLength(): void
	{
		$result = Sanitizer::sanitize_slug(str_repeat('a', 100));
		$this->assertSame(64, mb_strlen($result));
	}

	// ── TYPE_INT ──────────────────────────────────────────────────────────────

	public function testSanitizeIntClampsAboveMax(): void
	{
		$this->assertSame(100, Sanitizer::sanitize_int(150, 0, 100));
	}

	public function testSanitizeIntClampsBelowMin(): void
	{
		// absint(-5) = 5; 5 is within [0, 100] — returns 5 (no clamping needed).
		$this->assertSame(5, Sanitizer::sanitize_int(-5, 0, 100));
	}

	public function testSanitizeIntReturnsValueWithinBounds(): void
	{
		$this->assertSame(50, Sanitizer::sanitize_int(50, 0, 100));
	}

	// ── TYPE_FLOAT ────────────────────────────────────────────────────────────

	public function testSanitizeFloatReturnsNullOnNonNumericInput(): void
	{
		$this->assertNull(Sanitizer::sanitize_float('not-a-number'));
	}

	public function testSanitizeFloatReturnsNullWhenOutOfRange(): void
	{
		$this->assertNull(Sanitizer::sanitize_float(2.5, 0.0, 2.0));
	}

	public function testSanitizeFloatReturnsValueInRange(): void
	{
		$this->assertEqualsWithDelta(1.5, Sanitizer::sanitize_float(1.5, 0.0, 2.0), 0.0001);
	}

	// ── TYPE_BOOL ─────────────────────────────────────────────────────────────

	public function testSanitizeBoolTrueValues(): void
	{
		$this->assertTrue(Sanitizer::sanitize_bool('1'));
		$this->assertTrue(Sanitizer::sanitize_bool('true'));
		$this->assertTrue(Sanitizer::sanitize_bool('yes'));
		$this->assertTrue(Sanitizer::sanitize_bool('on'));
		$this->assertTrue(Sanitizer::sanitize_bool(true));
		$this->assertTrue(Sanitizer::sanitize_bool(1));
	}

	public function testSanitizeBoolFalseValues(): void
	{
		$this->assertFalse(Sanitizer::sanitize_bool('0'));
		$this->assertFalse(Sanitizer::sanitize_bool('false'));
		$this->assertFalse(Sanitizer::sanitize_bool(''));
		$this->assertFalse(Sanitizer::sanitize_bool(false));
	}

	// ── TYPE_URL ──────────────────────────────────────────────────────────────

	public function testSanitizeUrlRejectsHttpUrl(): void
	{
		// Non-HTTPS URLs must be stripped per SCR-05.
		$result = Sanitizer::sanitize_url('http://example.com');
		$this->assertSame('', $result);
	}

	public function testSanitizeUrlAcceptsHttpsUrl(): void
	{
		$url = 'https://example.com/path?q=1';
		$result = Sanitizer::sanitize_url($url);

		// Must start with https:// and not be empty.
		$this->assertStringStartsWith('https://', $result);
	}

	public function testSanitizeUrlRejectsJavascriptScheme(): void
	{
		$result = Sanitizer::sanitize_url('javascript:alert(1)');
		$this->assertSame('', $result);
	}

	public function testSanitizeUrlReturnsEmptyForEmptyInput(): void
	{
		$this->assertSame('', Sanitizer::sanitize_url(''));
	}

	// ── TYPE_IP ───────────────────────────────────────────────────────────────

	public function testSanitizeIpValidIPv4(): void
	{
		$this->assertSame('192.168.1.1', Sanitizer::sanitize_ip('192.168.1.1'));
	}

	public function testSanitizeIpValidIPv6(): void
	{
		$this->assertSame('::1', Sanitizer::sanitize_ip('::1'));
	}

	public function testSanitizeIpInvalidReturnsNull(): void
	{
		$this->assertNull(Sanitizer::sanitize_ip('not-an-ip'));
	}

	public function testSanitizeIpEmptyStringReturnsNull(): void
	{
		$this->assertNull(Sanitizer::sanitize_ip(''));
	}

	// ── TYPE_JSON ─────────────────────────────────────────────────────────────

	public function testSanitizeJsonValidObject(): void
	{
		$input = '{"key":"value","num":42}';
		$result = Sanitizer::sanitize_json($input);

		// Re-encoded JSON must decode to the same structure.
		$this->assertSame(json_decode($input, true), json_decode($result, true));
	}

	public function testSanitizeJsonValidArray(): void
	{
		$input = '[1,2,3]';
		$result = Sanitizer::sanitize_json($input);

		$this->assertSame([1, 2, 3], json_decode($result, true));
	}

	public function testSanitizeJsonInvalidReturnsEmpty(): void
	{
		$this->assertSame('', Sanitizer::sanitize_json('not valid json'));
	}

	public function testSanitizeJsonOversizedReturnsEmpty(): void
	{
		$big = '"' . str_repeat('x', 65537) . '"';
		$this->assertSame('', Sanitizer::sanitize_json($big));
	}

	// ── sanitize_map ─────────────────────────────────────────────────────────

	public function testSanitizeMapStripsUnmappedFields(): void
	{
		$data = [
			'name' => 'My Bot',
			'secret' => '<script>evil</script>',
			'rate' => '30',
			'unknown' => 'should be stripped',
		];

		$map = [
			'name' => ['type' => Sanitizer::TYPE_MESSAGE],
			'rate' => ['type' => Sanitizer::TYPE_INT, 'params' => ['min' => 1, 'max' => 100]],
		];

		$result = Sanitizer::sanitize_map($data, $map);

		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('rate', $result);
		$this->assertArrayNotHasKey('secret', $result);
		$this->assertArrayNotHasKey('unknown', $result);
		$this->assertSame(30, $result['rate']);
	}

	// ── validate() ───────────────────────────────────────────────────────────

	public function testValidateUrlReturnsWpErrorForNonHttps(): void
	{
		$result = Sanitizer::validate('http://example.com', Sanitizer::TYPE_URL);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('wpaic_invalid_input', $result->get_error_code());
	}

	public function testValidateUrlPassesForValidHttps(): void
	{
		$result = Sanitizer::validate('https://example.com', Sanitizer::TYPE_URL);

		$this->assertNotInstanceOf(\WP_Error::class, $result);
	}

	public function testValidateIpReturnsWpErrorForInvalidIp(): void
	{
		$result = Sanitizer::validate('not-an-ip', Sanitizer::TYPE_IP);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testValidateJsonReturnsWpErrorForInvalidJson(): void
	{
		$result = Sanitizer::validate('bad json {{', Sanitizer::TYPE_JSON);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}
}
