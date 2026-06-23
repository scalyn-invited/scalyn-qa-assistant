<?php
/**
 * Abstract AI Provider.
 *
 * Base class for all AI provider integrations (OpenAI, Claude, Gemini, etc.).
 *
 * @package Scalyn\QA\AI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Provider
 *
 * Abstract base that each concrete AI provider must extend.
 * Provides a shared prompt-building method and declares the contract
 * for generation, testing, and model listing.
 *
 * @since 1.0.0
 */
abstract class AI_Provider {

	/**
	 * The decrypted API key for this provider.
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * The model identifier to use for requests.
	 *
	 * @var string
	 */
	protected string $model;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key The API key (already decrypted).
	 * @param string $model   The model identifier.
	 */
	public function __construct( string $api_key, string $model = '' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Get the human-readable provider name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Get the provider slug identifier.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * Whether this provider supports custom API endpoints (for local/proxy use).
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	public function supports_custom_endpoint(): bool {
		return false;
	}

	/**
	 * Set a custom API endpoint URL.
	 *
	 * Override in providers that support custom endpoints.
	 *
	 * @since 1.4.0
	 *
	 * @param string $url The custom endpoint URL.
	 */
	public function set_endpoint( string $url ): void {
		// Override in providers that support custom endpoints.
	}

	/**
	 * Get the available models for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Model ID => Display name.
	 */
	abstract public function get_models(): array;

	/**
	 * Generate text from a prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prompt     The prompt to send.
	 * @param int    $max_tokens Maximum tokens for the response.
	 * @return string The generated text.
	 *
	 * @throws \RuntimeException On API failure.
	 */
	abstract public function generate( string $prompt, int $max_tokens = 300 ): string;

	/**
	 * Test the provider connection and API key validity.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string}
	 */
	abstract public function test(): array;

	/**
	 * Build a meta-tag generation prompt.
	 *
	 * Shared across all providers so that the SEO instructions stay consistent
	 * regardless of which AI back-end is used.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    What to generate: 'title', 'description', or 'both'.
	 * @param string $title   The post/page title.
	 * @param string $url     The post/page permalink.
	 * @param string $content The post/page content (first ~2 000 words).
	 * @return string The assembled prompt.
	 */
	public function build_meta_prompt( string $type, string $title, string $url, string $content ): string {
		$instructions = "You are an expert SEO copywriter. Analyze the following web page and generate optimized meta tags.\n\n";
		$instructions .= "Page Title: {$title}\n";
		$instructions .= "Page URL: {$url}\n\n";
		$instructions .= "Page Content (excerpt):\n{$content}\n\n";

		switch ( $type ) {
			case 'title':
				$instructions .= "Generate an SEO-optimized meta title for this page.\n\n";
				$instructions .= "Requirements:\n";
				$instructions .= "- Length: 50-60 characters (hard limit)\n";
				$instructions .= "- Include the primary keyword naturally\n";
				$instructions .= "- Make it compelling and click-worthy\n";
				$instructions .= "- Do not use clickbait or misleading phrasing\n\n";
				$instructions .= "Respond with ONLY the meta title text, nothing else.";
				break;

			case 'description':
				$instructions .= "Generate an SEO-optimized meta description for this page.\n\n";
				$instructions .= "Requirements:\n";
				$instructions .= "- Length: 120-160 characters (hard limit)\n";
				$instructions .= "- Summarize the page content accurately\n";
				$instructions .= "- Include a clear call-to-action or value proposition\n";
				$instructions .= "- Use active voice\n\n";
				$instructions .= "Respond with ONLY the meta description text, nothing else.";
				break;

			case 'both':
			default:
				$instructions .= "Generate both an SEO-optimized meta title and meta description for this page.\n\n";
				$instructions .= "Requirements for the meta title:\n";
				$instructions .= "- Length: 50-60 characters (hard limit)\n";
				$instructions .= "- Include the primary keyword naturally\n";
				$instructions .= "- Make it compelling and click-worthy\n\n";
				$instructions .= "Requirements for the meta description:\n";
				$instructions .= "- Length: 120-160 characters (hard limit)\n";
				$instructions .= "- Summarize the page content accurately\n";
				$instructions .= "- Include a clear call-to-action or value proposition\n";
				$instructions .= "- Use active voice\n\n";
				$instructions .= "Respond with valid JSON only, using this exact format:\n";
				$instructions .= '{"title": "Your meta title here", "description": "Your meta description here"}';
				break;
		}

		return $instructions;
	}

	/**
	 * Build a prompt for AI content review (spelling, grammar, tone, readability).
	 *
	 * @since 1.0.7
	 *
	 * @param string $title   The page title.
	 * @param string $content The page content (plain text).
	 * @return string The prompt string.
	 */
	public function build_content_review_prompt( string $title, string $content ): string {
		$prompt  = "You are a professional editor and proofreader. Review the following web page content for quality issues.\n\n";
		$prompt .= "Page Title: {$title}\n\n";
		$prompt .= "Content:\n{$content}\n\n";
		$prompt .= "Check for the following:\n";
		$prompt .= "1. **Spelling errors** — misspelled words\n";
		$prompt .= "2. **Grammar issues** — subject-verb agreement, tense consistency, sentence fragments\n";
		$prompt .= "3. **Capitalization** — incorrect capitalization in headings, sentences, or proper nouns\n";
		$prompt .= "4. **Punctuation** — missing periods, commas, or other punctuation issues\n";
		$prompt .= "5. **Readability** — overly complex sentences, passive voice overuse, jargon\n\n";
		$prompt .= "Respond with valid JSON only, using this exact format:\n";
		$prompt .= "{\n";
		$prompt .= '  "summary": "Brief overall assessment (1-2 sentences)",' . "\n";
		$prompt .= '  "score": 85,' . "\n";
		$prompt .= '  "issues": [' . "\n";
		$prompt .= '    {' . "\n";
		$prompt .= '      "type": "spelling|grammar|capitalization|punctuation|readability",' . "\n";
		$prompt .= '      "severity": "error|warning|suggestion",' . "\n";
		$prompt .= '      "text": "the problematic text",' . "\n";
		$prompt .= '      "suggestion": "the corrected text or advice",' . "\n";
		$prompt .= '      "context": "the sentence containing the issue"' . "\n";
		$prompt .= '    }' . "\n";
		$prompt .= '  ]' . "\n";
		$prompt .= "}\n\n";
		$prompt .= "Rules:\n";
		$prompt .= "- score is 0-100 (100 = perfect)\n";
		$prompt .= "- Only report real issues, not stylistic preferences\n";
		$prompt .= "- If no issues found, return an empty issues array with score 100\n";
		$prompt .= "- Limit to 20 most important issues maximum\n";
		$prompt .= "- Respond with ONLY the JSON, no markdown fences or extra text";

		return $prompt;
	}
}
