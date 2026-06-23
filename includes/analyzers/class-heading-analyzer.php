<?php
/**
 * Heading Analyzer.
 *
 * Analyzes heading structure in post content for proper hierarchy,
 * correct H1 usage, and empty headings.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Heading_Analyzer
 *
 * Checks heading tags for proper structure and accessibility.
 *
 * @since 1.0.0
 */
class Heading_Analyzer implements Analyzer_Interface {

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'headings';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Heading Analyzer', 'scalyn-qa-assistant' );
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
	 * Run all heading checks on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content  = $this->get_rendered_content( $post_id );
		$headings = $this->extract_headings( $content );

		$checks   = array();
		$checks[] = $this->check_h1_exists( $post_id, $headings );
		$checks[] = $this->check_heading_hierarchy( $headings );
		$checks[] = $this->check_empty_headings( $headings );

		return $checks;
	}

	/**
	 * Extract all headings from HTML content.
	 *
	 * Uses DOMDocument via HTML_Parser for reliable parsing instead of regex.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The HTML content to parse.
	 * @return array<int, array{tag: string, text: string, level: int}>
	 */
	public function extract_headings( string $content ): array {
		$parser       = new HTML_Parser( $content );
		$raw_headings = $parser->get_headings();
		$headings     = array();

		foreach ( $raw_headings as $heading ) {
			$headings[] = array(
				'tag'   => $heading['tag'],
				'text'  => $heading['text'],
				'level' => $heading['level'],
			);
		}

		return $headings;
	}

	/**
	 * Check that exactly one H1 tag exists.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{tag: string, text: string, level: int}> $headings Extracted headings.
	 * @return Check_Item
	 */
	private function check_h1_exists( int $post_id, array $headings ): Check_Item {
		$tooltip  = __( 'The H1 is the main heading of your page. Most themes automatically use the post title as H1. If using a page builder, you may need to add one manually.', 'scalyn-qa-assistant' );
		$h1_texts = array();

		foreach ( $headings as $heading ) {
			if ( 1 === $heading['level'] ) {
				$text       = $heading['text'];
				$h1_texts[] = '' !== $text
					? ( mb_strlen( $text ) > 80 ? mb_substr( $text, 0, 80 ) . '...' : $text )
					: __( '(empty H1)', 'scalyn-qa-assistant' );
			}
		}

		$h1_count = count( $h1_texts );

		if ( 1 === $h1_count ) {
			return new Check_Item(
				id:        'h1_exists',
				label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Page has exactly one H1 heading.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		if ( 0 === $h1_count ) {
			// Most themes render the post title as H1 outside of post_content.
			// Check if the post has a title — if so, the theme likely handles H1.
			$post_title = get_the_title( $post_id );

			if ( '' !== $post_title ) {
				// Verify by fetching the actual page HTML and checking for H1.
				$page_has_h1 = $this->check_frontend_h1( $post_id );

				if ( $page_has_h1 ) {
					return new Check_Item(
						id:        'h1_exists',
						label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
						status:    'pass',
						message:   sprintf(
							__( 'H1 rendered by theme from post title: "%s".', 'scalyn-qa-assistant' ),
							mb_strlen( $post_title ) > 60 ? mb_substr( $post_title, 0, 60 ) . '...' : $post_title,
						),
						category:  'content',
						severity:  'info',
						quick_fix: null,
						tooltip:   $tooltip,
					);
				}

				// Can't verify frontend but title exists — likely has H1.
				return new Check_Item(
					id:        'h1_exists',
					label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
					status:    'pass',
					message:   sprintf(
						__( 'No H1 in post content, but the post title "%s" is likely rendered as H1 by the theme.', 'scalyn-qa-assistant' ),
						mb_strlen( $post_title ) > 60 ? mb_substr( $post_title, 0, 60 ) . '...' : $post_title,
					),
					category:  'content',
					severity:  'info',
					quick_fix: null,
					tooltip:   $tooltip,
				);
			}

			return new Check_Item(
				id:        'h1_exists',
				label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'No H1 heading found and no post title set. Add an H1 in the post editor.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'critical',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'h1_exists',
			label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: %d: number of H1 tags found */
				__( 'Multiple H1 headings found (%d). Change the extra H1s to H2 or lower in the post editor — only one H1 should exist per page.', 'scalyn-qa-assistant' ),
				$h1_count,
			),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'h1_texts' => $h1_texts ),
		);
	}

	/**
	 * Check that headings follow a proper hierarchy without skipping levels.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{tag: string, text: string, level: int}> $headings Extracted headings.
	 * @return Check_Item
	 */
	private function check_heading_hierarchy( array $headings ): Check_Item {
		$tooltip = __( 'Headings should follow H1 → H2 → H3 order without skipping levels. Fix by changing the heading level in the post editor toolbar.', 'scalyn-qa-assistant' );

		if ( count( $headings ) === 0 ) {
			return new Check_Item(
				id:        'heading_hierarchy',
				label:     __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No headings found — not applicable.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$skipped_levels = array();
		$previous_level = 0;

		foreach ( $headings as $heading ) {
			$current_level = $heading['level'];

			if ( $previous_level > 0 && $current_level > $previous_level + 1 ) {
				$skip_description = sprintf(
					/* translators: 1: previous heading tag, 2: current heading tag */
					__( 'H%1$d to H%2$d', 'scalyn-qa-assistant' ),
					$previous_level,
					$current_level,
				);
				$skipped_levels[] = $skip_description;
			}

			$previous_level = $current_level;
		}

		if ( count( $skipped_levels ) === 0 ) {
			return new Check_Item(
				id:        'heading_hierarchy',
				label:     __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Heading hierarchy is correct. No levels are skipped.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'heading_hierarchy',
			label:     __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: %s: list of skipped heading levels */
				__( 'Heading levels are skipped: %s. Open the post editor, find the out-of-order headings, and adjust their level (e.g., change H4 to H3).', 'scalyn-qa-assistant' ),
				implode( ', ', $skipped_levels ),
			),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'skipped_levels' => $skipped_levels ),
		);
	}

	/**
	 * Check for headings with empty text content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{tag: string, text: string, level: int}> $headings Extracted headings.
	 * @return Check_Item
	 */
	private function check_empty_headings( array $headings ): Check_Item {
		$tooltip       = __( 'Empty headings hurt accessibility and SEO. Find them in the post editor and either add text or remove the empty heading block.', 'scalyn-qa-assistant' );
		$empty_tags    = array();

		foreach ( $headings as $heading ) {
			if ( '' === $heading['text'] ) {
				$empty_tags[] = strtoupper( $heading['tag'] );
			}
		}

		$empty_count = count( $empty_tags );

		if ( 0 === $empty_count ) {
			return new Check_Item(
				id:        'empty_headings',
				label:     __( 'Empty Headings', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No empty headings found.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'empty_headings',
			label:     __( 'Empty Headings', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   sprintf(
				/* translators: 1: count of empty headings, 2: list of heading levels */
				__( '%1$d empty heading(s) found: %2$s. Open the post editor and either add text to these headings or remove them.', 'scalyn-qa-assistant' ),
				$empty_count,
				implode( ', ', $empty_tags ),
			),
			category:  'content',
			severity:  'critical',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array(
				'empty_count' => $empty_count,
				'empty_tags'  => $empty_tags,
			),
		);
	}

	/**
	 * Check the actual frontend page for an H1 tag.
	 *
	 * Uses a cached HTTP request to the post's permalink.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if an H1 was found on the frontend.
	 */
	private function check_frontend_h1( int $post_id ): bool {
		$url = get_permalink( $post_id );

		if ( ! $url ) {
			return false;
		}

		$response = wp_remote_get( $url, array(
			'timeout'   => 10,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		return is_string( $body ) && (bool) preg_match( '/<h1[\s>]/i', $body );
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * Supports Elementor page builder content when available.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered HTML content.
	 */
	private function get_rendered_content( int $post_id ): string {
		// Check for Elementor-built content.
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

		/** This filter is documented in wp-includes/post-template.php */
		return (string) apply_filters( 'the_content', $raw_content );
	}
}
