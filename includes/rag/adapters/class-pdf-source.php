<?php

/**
 * PDF Content Source Adapter — indexes PDF files from the WordPress Media Library.
 *
 * Enumerates all `application/pdf` attachments in the Media Library.
 * Text extraction uses smalot/pdfparser when available (recommended),
 * falling back to a lightweight pure-PHP extractor for environments
 * where the Composer vendor package has not been installed.
 *
 * To enable full extraction, add `smalot/pdfparser` to the plugin's
 * production Composer dependencies and bundle vendor/ in the ZIP.
 *
 * Scanned (image-only) PDFs produce empty content and are silently skipped.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag\Adapters;

use WPAIC\Rag\Content_Source;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Pdf_Source
 *
 * @since 1.0.0
 * @package WPAIC\Rag\Adapters
 */
class Pdf_Source implements Content_Source
{
	/** Source machine ID. */
	private const SOURCE_ID = 'pdf';

	/** {@inheritdoc} */
	public function get_id(): string
	{
		return self::SOURCE_ID;
	}

	/** {@inheritdoc} */
	public function get_label(): string
	{
		return __('PDF Files', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_items(array $options = []): array
	{
		$query = new \WP_Query([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => 'application/pdf',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'fields' => 'ids',
		]);

		if (empty($query->posts)) {
			return [];
		}

		$items = [];

		foreach ((array) $query->posts as $attachment_id) {
			$post = get_post((int) $attachment_id);

			if (! $post instanceof \WP_Post) {
				continue;
			}

			$file = (string) get_attached_file($post->ID);
			$title = ('' !== trim($post->post_title)) ? $post->post_title : basename($file);

			$items[] = [
				'id' => (int) $post->ID,
				'title' => $title,
				'modified' => $post->post_modified,
				'type' => 'pdf',
			];
		}

		return $items;
	}

	/** {@inheritdoc} */
	public function get_content(int $item_id)
	{
		$file_path = (string) get_attached_file($item_id);

		if ('' === $file_path || ! is_readable($file_path)) {
			return false;
		}

		$post = get_post($item_id);

		if (! $post instanceof \WP_Post) {
			return false;
		}

		$text = $this->extract_text($file_path);

		if ('' === $text) {
			return false;
		}

		$title = ('' !== trim($post->post_title)) ? $post->post_title : basename($file_path);

		return [
			'title' => $title,
			'content' => $text,
			'url' => (string) wp_get_attachment_url($item_id),
			'type' => 'pdf',
			'meta' => [
				'attachment_id' => $item_id,
				'filename' => basename($file_path),
				'file_size' => (int) filesize($file_path),
				'modified' => $post->post_modified,
			],
		];
	}

	// =========================================================================
	// Text extraction
	// =========================================================================

	/**
	 * Extract text from a PDF file.
	 *
	 * Tries smalot/pdfparser first; falls back to the basic extractor.
	 *
	 * @param  string $file_path Absolute path to the PDF file.
	 * @return string Extracted plain text, or empty string on failure.
	 */
	private function extract_text(string $file_path): string
	{
		if (class_exists('\\Smalot\\PdfParser\\Parser')) {
			$text = $this->extract_via_pdfparser($file_path);
			if ('' !== $text) {
				return $text;
			}
		}

		return $this->extract_basic($file_path);
	}

	/**
	 * Extract text using smalot/pdfparser.
	 *
	 * Iterates pages in document order, prefixes each with a page marker,
	 * and concatenates with double newlines.
	 *
	 * @param  string $file_path Absolute path to the PDF file.
	 * @return string Extracted text, or empty string on any exception.
	 */
	private function extract_via_pdfparser(string $file_path): string
	{
		try {
			// @phpstan-ignore-next-line class.notFound -- smalot/pdfparser is an optional runtime dep; usage guarded by class_exists() in get_content().
			$parser = new \Smalot\PdfParser\Parser();
			$pdf = $parser->parseFile($file_path);
			$pages = $pdf->getPages();

			$parts = [];
			$page_num = 1;

			foreach ($pages as $page) {
				$page_text = trim($page->getText());

				if ('' !== $page_text) {
					/* translators: %d: page number in PDF document */
					$parts[] = sprintf(__('--- Page %d ---', 'wp-ai-chatbot'), $page_num) . "\n" . $page_text;
				}

				$page_num++;
			}

			return implode("\n\n", $parts);
		} catch (\Exception $e) {
			return '';
		}
	}

	/**
	 * Basic pure-PHP PDF text extractor (no external dependencies).
	 *
	 * Reads BT…ET text blocks from the raw PDF byte stream and reconstructs
	 * readable text using the Tj and TJ operators. Suitable for standard
	 * ASCII/Latin-1 encoded PDFs; may produce incomplete output for
	 * CIDFont/Unicode encoded documents.
	 *
	 * @param  string $file_path Absolute path to the PDF file.
	 * @return string Extracted text, possibly partial.
	 */
	private function extract_basic(string $file_path): string
	{
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = @file_get_contents($file_path);

		if (false === $content || '' === $content) {
			return '';
		}

		$text = '';

		// Extract text from BT … ET blocks (Begin Text / End Text).
		if (preg_match_all('/BT\s([\s\S]*?)\sET/', $content, $blocks)) {
			foreach ($blocks[1] as $block) {
				// Literal string: (text) Tj
				if (preg_match_all('/\(((?:[^)(\\\\]|\\\\.)*)\)\s*Tj/', $block, $tj)) {
					foreach ($tj[1] as $s) {
						$text .= ' ' . $this->decode_pdf_string($s);
					}
				}

				// Array string: [(text) ...] TJ
				if (preg_match_all('/\[((?:[^\[\]]|\[(?:[^\[\]])*\])*)\]\s*TJ/', $block, $tjarr)) {
					foreach ($tjarr[1] as $arr) {
						if (preg_match_all('/\(((?:[^)(\\\\]|\\\\.)*)\)/', $arr, $strs)) {
							foreach ($strs[1] as $s) {
								$text .= $this->decode_pdf_string($s);
							}
						}
					}
				}
			}
		}

		// Normalise whitespace.
		$text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);
		$text = (string) preg_replace('/\s{3,}/', "\n\n", $text);

		return trim($text);
	}

	/**
	 * Unescape PDF string escape sequences.
	 *
	 * @param  string $s Raw PDF string operand (without surrounding parentheses).
	 * @return string Unescaped string.
	 */
	private function decode_pdf_string(string $s): string
	{
		$s = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $s);
		$s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);

		return $s;
	}
}
