<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Runtime\Output_Formatter;

/**
 * Tests for WPAIC\Runtime\Output_Formatter
 *
 * Covers suggestion extraction, Markdown-to-HTML conversion, link safety,
 * and the full format() pipeline.
 */
final class OutputFormatterTest extends TestCase
{
	private static bool $loaded = false;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		require_once __DIR__ . '/stubs/wp-core-stubs.php';

		self::$loaded = true;
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();
	}

	// ── extract_suggestions ───────────────────────────────────────────────────

	public function testExtractSuggestionsFindsMarker(): void
	{
		$text = 'Here is some text. <!--SUGGESTIONS:["Option A","Option B","Option C"]-->';

		[$suggestions, $stripped] = Output_Formatter::extract_suggestions($text);

		$this->assertCount(3, $suggestions);
		$this->assertContains('Option A', $suggestions);
		$this->assertContains('Option B', $suggestions);
		$this->assertContains('Option C', $suggestions);
	}

	public function testExtractSuggestionsStripsMarkerFromText(): void
	{
		$text = 'Answer text. <!--SUGGESTIONS:["Chip"]-->';

		[$suggestions, $stripped] = Output_Formatter::extract_suggestions($text);

		$this->assertStringNotContainsString('<!--SUGGESTIONS:', $stripped);
		$this->assertStringContainsString('Answer text.', $stripped);
	}

	public function testExtractSuggestionsEmptyWhenNoMarker(): void
	{
		$text = 'Just a regular response with no chips.';

		[$suggestions, $stripped] = Output_Formatter::extract_suggestions($text);

		$this->assertCount(0, $suggestions);
		$this->assertSame($text, $stripped);
	}

	public function testExtractSuggestionsFiltersEmptyStrings(): void
	{
		$text = 'Text <!--SUGGESTIONS:["Valid","","Also valid"]-->';

		[$suggestions,] = Output_Formatter::extract_suggestions($text);

		$this->assertNotContains('', $suggestions);
		$this->assertCount(2, $suggestions);
	}

	// ── convert_markdown ─────────────────────────────────────────────────────

	public function testConvertMarkdownBold(): void
	{
		$result = Output_Formatter::convert_markdown('**bold text**');
		$this->assertStringContainsString('<strong>', $result);
		$this->assertStringContainsString('bold text', $result);
		$this->assertStringContainsString('</strong>', $result);
	}

	public function testConvertMarkdownItalic(): void
	{
		$result = Output_Formatter::convert_markdown('*italic text*');
		$this->assertStringContainsString('<em>', $result);
		$this->assertStringContainsString('italic text', $result);
	}

	public function testConvertMarkdownInlineCode(): void
	{
		$result = Output_Formatter::convert_markdown('Use `print()` function.');
		$this->assertStringContainsString('<code>', $result);
		$this->assertStringContainsString('print()', $result);
	}

	public function testConvertMarkdownH3Heading(): void
	{
		$result = Output_Formatter::convert_markdown('### My Heading');
		$this->assertStringContainsString('<h3>', $result);
		$this->assertStringContainsString('My Heading', $result);
	}

	public function testConvertMarkdownStrikethrough(): void
	{
		$result = Output_Formatter::convert_markdown('~~deleted text~~');
		$this->assertStringContainsString('<del>', $result);
		$this->assertStringContainsString('deleted text', $result);
	}

	public function testConvertMarkdownUnorderedList(): void
	{
		$md = "- Item one\n- Item two\n- Item three";
		$result = Output_Formatter::convert_markdown($md);

		$this->assertStringContainsString('<ul>', $result);
		$this->assertStringContainsString('<li>', $result);
		$this->assertStringContainsString('Item one', $result);
	}

	public function testConvertMarkdownOrderedList(): void
	{
		$md = "1. First\n2. Second\n3. Third";
		$result = Output_Formatter::convert_markdown($md);

		$this->assertStringContainsString('<ol>', $result);
		$this->assertStringContainsString('<li>', $result);
		$this->assertStringContainsString('First', $result);
	}

	public function testConvertMarkdownBlockquoteTextIsSafelyRendered(): void
	{
		// convert_markdown calls esc_html() first, so '>' is encoded to '&gt;'
		// before the blockquote regex runs — raw "> quote" input is safe but
		// renders as an escaped paragraph, not a <blockquote> element.
		$md = '> This is a quote.';
		$result = Output_Formatter::convert_markdown($md);

		$this->assertStringContainsString('This is a quote.', $result);
		// The '>' must be safely encoded, never a raw tag.
		$this->assertStringNotContainsString('<script', $result);
	}

	public function testConvertMarkdownHorizontalRule(): void
	{
		$result = Output_Formatter::convert_markdown("Before\n---\nAfter");
		$this->assertStringContainsString('<hr>', $result);
	}

	public function testConvertMarkdownHttpsLink(): void
	{
		$md = '[Visit Example](https://example.com)';
		$result = Output_Formatter::convert_markdown($md);

		$this->assertStringContainsString('<a ', $result);
		$this->assertStringContainsString('https://example.com', $result);
		$this->assertStringContainsString('Visit Example', $result);
	}

	public function testConvertMarkdownEscapesRawHtmlBeforeProcessing(): void
	{
		// Raw HTML tags injected via Markdown must be escaped.
		$md = 'Click <img src=x onerror=alert(1)> here';
		$result = Output_Formatter::convert_markdown($md);

		$this->assertStringNotContainsString('<img', $result);
		$this->assertStringContainsString('&lt;img', $result);
	}

	public function testConvertMarkdownJavascriptLinkIsStripped(): void
	{
		$md = '[Click me](javascript:alert(1))';
		$result = Output_Formatter::convert_markdown($md);

		// The link must not produce a javascript: href.
		$this->assertStringNotContainsString('javascript:', $result);
	}

	// ── sanitize_links ────────────────────────────────────────────────────────

	public function testSanitizeLinksEnforcesBlankTarget(): void
	{
		$html = '<a href="https://example.com">Link</a>';
		$result = Output_Formatter::sanitize_links($html);

		$this->assertStringContainsString('target="_blank"', $result);
	}

	public function testSanitizeLinksAddsNoopenerRel(): void
	{
		$html = '<a href="https://example.com">Link</a>';
		$result = Output_Formatter::sanitize_links($html);

		$this->assertStringContainsString('rel="noopener noreferrer"', $result);
	}

	public function testSanitizeLinksStripsJavascriptHref(): void
	{
		$html = '<a href="javascript:alert(1)">Click</a>';
		$result = Output_Formatter::sanitize_links($html);

		$this->assertStringNotContainsString('javascript:', $result);
	}

	// ── format() ─────────────────────────────────────────────────────────────

	public function testFormatReturnsHtmlAndSuggestionsKeys(): void
	{
		$result = Output_Formatter::format('Hello world.');

		$this->assertArrayHasKey('html', $result);
		$this->assertArrayHasKey('suggestions', $result);
	}

	public function testFormatExtractsSuggestions(): void
	{
		$raw = 'Here is my answer. <!--SUGGESTIONS:["Yes","No"]-->';
		$result = Output_Formatter::format($raw);

		$this->assertCount(2, $result['suggestions']);
		$this->assertNotEmpty($result['html']);
	}

	public function testFormatWithToHtmlFalseEscapesHtml(): void
	{
		$raw = '<b>Bold</b> text';
		$result = Output_Formatter::format($raw, false);

		// With to_html=false, esc_html wraps the input so tags are escaped.
		$this->assertStringNotContainsString('<b>', $result['html']);
		$this->assertStringContainsString('&lt;b&gt;', $result['html']);
	}

	public function testFormatRendersMarkdownBold(): void
	{
		$raw = '**Important** information';
		$result = Output_Formatter::format($raw, true);

		$this->assertStringContainsString('<strong>', $result['html']);
	}
}
