<?php
/**
 * Content Analyzer.
 *
 * Performs content quality checks including heading structure,
 * content length, capitalization, punctuation, and paragraph quality.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Content_Analyzer
 *
 * Orchestrates content-level analysis by delegating to specialized sub-analyzers
 * and running its own text quality checks.
 *
 * @since 1.0.0
 */
class Content_Analyzer implements Analyzer_Interface {

	/**
	 * Minimum recommended word count.
	 *
	 * @var int
	 */
	private const MIN_WORD_COUNT = 300;

	/**
	 * The heading analyzer instance.
	 *
	 * @var Heading_Analyzer
	 */
	private Heading_Analyzer $heading_analyzer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->heading_analyzer = new Heading_Analyzer();
	}

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'content';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Content Analyzer', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'content';
	}

	/**
	 * Run all content checks on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$checks = $this->heading_analyzer->analyze( $post_id );

		$content    = $this->get_rendered_content( $post_id );
		$plain_text = wp_strip_all_tags( $content );

		$checks[] = $this->check_content_length( $plain_text );
		$checks[] = $this->check_heading_capitalization( $content );
		$checks[] = $this->check_paragraph_punctuation( $content );
		$checks[] = $this->check_short_paragraphs( $content );
		$checks[] = $this->check_readability( $post_id, $plain_text );

		return $checks;
	}

	/**
	 * Check if content meets minimum word count.
	 */
	private function check_content_length( string $plain_text ): Check_Item {
		$words      = preg_split( '/\s+/', trim( $plain_text ), -1, PREG_SPLIT_NO_EMPTY );
		$word_count = count( $words );
		$pass       = $word_count >= self::MIN_WORD_COUNT;

		return new Check_Item(
			id:        'content_length',
			label:     __( 'Content Length', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf(
					__( 'Content has %d words.', 'scalyn-qa-assistant' ),
					$word_count,
				)
				: sprintf(
					__( 'Content has only %d words. Aim for at least %d words for better SEO.', 'scalyn-qa-assistant' ),
					$word_count,
					self::MIN_WORD_COUNT,
				),
			category:  'content',
			severity:  $pass ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   __( 'Search engines prefer pages with substantial content. Add more text, examples, or sections in the post editor to reach at least 300 words.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check that headings use proper capitalization (not all lowercase).
	 */
	private function check_heading_capitalization( string $content ): Check_Item {
		$parser   = new HTML_Parser( $content );
		$headings = $parser->get_headings();

		if ( empty( $headings ) ) {
			return new Check_Item(
				id:        'heading_capitalization',
				label:     __( 'Heading Capitalization', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No headings to check.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Capitalize the first letter of each heading. Edit headings in the post editor to use sentence case (e.g., "Our services" instead of "our services").', 'scalyn-qa-assistant' ),
			);
		}

		$bad_headings = array();
		foreach ( $headings as $heading ) {
			$text = trim( $heading['text'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			// Flag if the first character is lowercase (not sentence/title case).
			$first_char = mb_substr( $text, 0, 1 );
			if ( $first_char !== mb_strtoupper( $first_char ) && preg_match( '/[a-z]/u', $first_char ) ) {
				$tag = strtoupper( $heading['tag'] ?? 'H?' );
				$bad_headings[] = $tag . ': "' . ( mb_strlen( $text ) > 50 ? mb_substr( $text, 0, 50 ) . '...' : $text ) . '"';
			}
		}

		$pass = empty( $bad_headings );

		return new Check_Item(
			id:        'heading_capitalization',
			label:     __( 'Heading Capitalization', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? __( 'All headings are properly capitalized.', 'scalyn-qa-assistant' )
				: sprintf(
					__( '%d heading(s) start with a lowercase letter.', 'scalyn-qa-assistant' ),
					count( $bad_headings ),
				),
			category:  'content',
			severity:  $pass ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   __( 'Capitalize the first letter of each heading. Edit headings in the post editor to use sentence case (e.g., "Our services" instead of "our services").', 'scalyn-qa-assistant' ),
			details:   $pass ? array() : array( 'bad_headings' => $bad_headings ),
		);
	}

	/**
	 * Check that paragraphs end with proper punctuation.
	 */
	private function check_paragraph_punctuation( string $content ): Check_Item {
		// Extract paragraph text from <p> tags.
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/si', $content, $matches ) ) {
			return new Check_Item(
				id:        'paragraph_punctuation',
				label:     __( 'Paragraph Punctuation', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No paragraphs to check.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Every paragraph should end with proper punctuation (. ! ? or :). Review the flagged paragraphs in the post editor and add the missing punctuation.', 'scalyn-qa-assistant' ),
			);
		}

		$bad_paragraphs = array();
		foreach ( $matches[1] as $paragraph_html ) {
			$text = trim( wp_strip_all_tags( $paragraph_html ) );

			// Skip empty, very short (likely a label/button), or paragraphs that are just a link/image.
			if ( mb_strlen( $text ) < 20 ) {
				continue;
			}

			$last_char = mb_substr( $text, -1 );

			// Valid ending punctuation.
			if ( ! in_array( $last_char, array( '.', '!', '?', ':', ';', '"', "'", "\u{201D}", ')' ), true ) ) {
				$preview          = mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 60 ) . '...' : $text;
				$bad_paragraphs[] = '"' . $preview . '"';
			}
		}

		$pass = empty( $bad_paragraphs );

		return new Check_Item(
			id:        'paragraph_punctuation',
			label:     __( 'Paragraph Punctuation', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? __( 'All paragraphs end with proper punctuation.', 'scalyn-qa-assistant' )
				: sprintf(
					__( '%d paragraph(s) missing ending punctuation.', 'scalyn-qa-assistant' ),
					count( $bad_paragraphs ),
				),
			category:  'content',
			severity:  $pass ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   __( 'Every paragraph should end with proper punctuation (. ! ? or :). Review the flagged paragraphs in the post editor and add the missing punctuation.', 'scalyn-qa-assistant' ),
			details:   $pass ? array() : array( 'bad_paragraphs' => $bad_paragraphs ),
		);
	}

	/**
	 * Check for consecutive very short paragraphs (1 sentence each).
	 */
	private function check_short_paragraphs( string $content ): Check_Item {
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/si', $content, $matches ) ) {
			return new Check_Item(
				id:        'short_paragraphs',
				label:     __( 'Paragraph Quality', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No paragraphs to check.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Avoid many consecutive single-sentence paragraphs. Group related sentences together.', 'scalyn-qa-assistant' ),
			);
		}

		$consecutive     = 0;
		$max_streak      = 0;
		$total_short     = 0;
		$current_streak  = array();
		$worst_streak    = array();

		foreach ( $matches[1] as $paragraph_html ) {
			$text = trim( wp_strip_all_tags( $paragraph_html ) );

			if ( mb_strlen( $text ) < 10 ) {
				continue; // Skip empty or trivial content.
			}

			// Count sentences (split by . ! ?).
			$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
			$sentence_count = count( array_filter( $sentences, fn( $s ) => mb_strlen( trim( $s ) ) > 5 ) );

			if ( $sentence_count <= 1 && mb_strlen( $text ) < 80 ) {
				++$consecutive;
				++$total_short;
				$current_streak[] = mb_strlen( $text ) > 100 ? mb_substr( $text, 0, 100 ) . '...' : $text;
				if ( $consecutive > $max_streak ) {
					$max_streak   = $consecutive;
					$worst_streak = $current_streak;
				}
			} else {
				$consecutive    = 0;
				$current_streak = array();
			}
		}

		$pass = $max_streak < 4;

		return new Check_Item(
			id:        'short_paragraphs',
			label:     __( 'Paragraph Quality', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? __( 'Paragraph structure looks good.', 'scalyn-qa-assistant' )
				: sprintf(
					__( 'Found %d consecutive single-sentence paragraphs. Consider combining related sentences for better readability.', 'scalyn-qa-assistant' ),
					$max_streak,
				),
			category:  'content',
			severity:  $pass ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   __( 'Multiple single-sentence paragraphs in a row can feel choppy. In the post editor, combine related short paragraphs into fuller ones with 2-3 sentences each.', 'scalyn-qa-assistant' ),
			details:   $pass ? array() : array( 'short_paragraphs' => $worst_streak ),
		);
	}

	/**
	 * Check readability using SEO plugin score or a basic Flesch-like calculation.
	 *
	 * Supports: Yoast (content_score), Rank Math, SEOPress.
	 * Falls back to a simple average-sentence-length / average-word-length score.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $plain_text Plain text content.
	 * @return Check_Item
	 */
	private function check_readability( int $post_id, string $plain_text ): Check_Item {
		$tooltip = __( 'Readability measures how easy your content is to understand. Aim for short sentences and simple words. Tools like Hemingway Editor can help.', 'scalyn-qa-assistant' );

		// Try to read readability score from the active SEO plugin.
		$score  = 0;
		$source = '';

		// Rank Math content AI score.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_rs = get_post_meta( $post_id, 'rank_math_contentai_score', true );
			if ( is_numeric( $rm_rs ) && (int) $rm_rs > 0 ) {
				$score  = (int) $rm_rs;
				$source = 'Rank Math';
			}
		}

		// Yoast readability score (0-100).
		if ( 0 === $score && defined( 'WPSEO_VERSION' ) ) {
			$yoast_rs = get_post_meta( $post_id, '_yoast_wpseo_content_score', true );
			if ( is_numeric( $yoast_rs ) && (int) $yoast_rs > 0 ) {
				$score  = (int) $yoast_rs;
				$source = 'Yoast SEO';
			}
		}

		// If no plugin score, calculate a basic one.
		if ( 0 === $score && mb_strlen( $plain_text ) > 100 ) {
			$score  = $this->calculate_basic_readability( $plain_text );
			$source = __( 'basic analysis', 'scalyn-qa-assistant' );
		}

		if ( 0 === $score ) {
			return new Check_Item(
				id:        'readability_score',
				label:     __( 'Readability', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Not enough content to assess readability.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$status = $score >= 60 ? 'pass' : ( $score >= 40 ? 'warning' : 'fail' );
		$label  = $score >= 80 ? __( 'Very Easy', 'scalyn-qa-assistant' )
			: ( $score >= 60 ? __( 'Good', 'scalyn-qa-assistant' )
			: ( $score >= 40 ? __( 'Needs Improvement', 'scalyn-qa-assistant' )
			: __( 'Difficult', 'scalyn-qa-assistant' ) ) );

		return new Check_Item(
			id:        'readability_score',
			label:     __( 'Readability', 'scalyn-qa-assistant' ),
			status:    $status,
			message:   sprintf(
				/* translators: 1: score, 2: label, 3: source */
				__( '%1$d/100 — %2$s (via %3$s).', 'scalyn-qa-assistant' ),
				$score,
				$label,
				$source,
			),
			category:  'content',
			severity:  'pass' === $status ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'score' => $score, 'label' => $label, 'source' => $source ),
		);
	}

	/**
	 * Calculate a basic readability score (0-100) from plain text.
	 *
	 * Uses average sentence length and average word length as proxies.
	 * Not a real Flesch-Kincaid, but a reasonable approximation.
	 *
	 * @param string $text Plain text.
	 * @return int Score 0-100.
	 */
	private function calculate_basic_readability( string $text ): int {
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array_filter( $sentences, fn( $s ) => mb_strlen( trim( $s ) ) > 5 );

		if ( count( $sentences ) < 3 ) {
			return 0;
		}

		$words          = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$total_words    = count( $words );
		$total_syllables = 0;

		foreach ( $words as $word ) {
			$total_syllables += $this->count_syllables( $word );
		}

		$avg_sentence_length = $total_words / count( $sentences );
		$avg_syllables       = $total_syllables / max( 1, $total_words );

		// Simplified Flesch Reading Ease formula.
		$flesch = 206.835 - ( 1.015 * $avg_sentence_length ) - ( 84.6 * $avg_syllables );

		return max( 0, min( 100, (int) round( $flesch ) ) );
	}

	/**
	 * Estimate syllable count for an English word.
	 *
	 * @param string $word The word.
	 * @return int Estimated syllable count.
	 */
	private function count_syllables( string $word ): int {
		$word = strtolower( preg_replace( '/[^a-z]/', '', $word ) );

		if ( strlen( $word ) <= 3 ) {
			return 1;
		}

		$count = (int) preg_match_all( '/[aeiouy]+/', $word );

		// Adjust for silent 'e' at end.
		if ( str_ends_with( $word, 'e' ) && ! str_ends_with( $word, 'le' ) ) {
			--$count;
		}

		return max( 1, $count );
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered HTML content.
	 */
	private function get_rendered_content( int $post_id ): string {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( $elementor && method_exists( $elementor->db, 'is_built_with_elementor' ) && $elementor->db->is_built_with_elementor( $post_id ) ) {
				$elementor_content = $elementor->frontend->get_builder_content( $post_id, true );
				if ( '' !== $elementor_content ) {
					return $elementor_content;
				}
			}
		}

		$raw_content = get_post_field( 'post_content', $post_id );

		if ( is_wp_error( $raw_content ) || ! is_string( $raw_content ) ) {
			return '';
		}

		return (string) apply_filters( 'the_content', $raw_content );
	}
}
