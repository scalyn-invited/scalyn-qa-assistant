<?php
/**
 * HTML Quality Analyzer.
 *
 * Detects broken/malformed HTML and missing URLs (empty href/src attributes)
 * in post content.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.4.10
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class HTML_Quality_Analyzer
 *
 * Checks for HTML parsing errors and elements with missing URLs.
 *
 * @since 1.4.10
 */
class HTML_Quality_Analyzer implements Analyzer_Interface {

	/**
	 * Maximum number of HTML errors to report.
	 *
	 * @var int
	 */
	private const MAX_ERRORS_REPORTED = 10;

	/**
	 * libxml error patterns to ignore (noise from page builders, scripts, etc.).
	 *
	 * @var string[]
	 */
	private const IGNORED_PATTERNS = array(
		'Tag svg invalid',
		'Tag path invalid',
		'Tag section invalid',
		'Tag header invalid',
		'Tag footer invalid',
		'Tag nav invalid',
		'Tag main invalid',
		'Tag article invalid',
		'Tag aside invalid',
		'Tag figure invalid',
		'Tag figcaption invalid',
		'Tag details invalid',
		'Tag summary invalid',
		'Tag video invalid',
		'Tag audio invalid',
		'Tag source invalid',
		'Tag canvas invalid',
		'Tag picture invalid',
		'Tag template invalid',
		'Tag slot invalid',
		'Unexpected end tag',
		'htmlParseEntityRef',
		'Namespace prefix',
	);

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.4.10
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'html_quality';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.4.10
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'HTML Quality Analyzer', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.4.10
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'functionality';
	}

	/**
	 * Run all HTML quality checks on a post.
	 *
	 * @since 1.4.10
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content = $this->get_rendered_content( $post_id );
		$checks  = array();

		$checks[] = $this->check_broken_html( $content );
		$checks[] = $this->check_missing_urls( $content );

		return $checks;
	}

	/**
	 * Check for broken/malformed HTML by parsing through DOMDocument and
	 * inspecting libxml errors.
	 *
	 * @since 1.4.10
	 *
	 * @param string $content The rendered HTML content.
	 * @return Check_Item
	 */
	private function check_broken_html( string $content ): Check_Item {
		$tooltip = __( 'Broken HTML (unclosed tags, invalid nesting) can cause layout issues and hurt SEO. Fix the flagged issues in the post editor or page builder.', 'scalyn-qa-assistant' );

		if ( '' === trim( $content ) ) {
			return new Check_Item(
				id:        'broken_html',
				label:     __( 'HTML Validation', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No content to check.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		libxml_use_internal_errors( true );
		libxml_clear_errors();

		$doc = new \DOMDocument();
		$doc->loadHTML(
			'<?xml encoding="UTF-8"><div>' . $content . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR,
		);

		$raw_errors = libxml_get_errors();
		libxml_clear_errors();

		// Filter out noise — HTML5 tags, entity refs, etc.
		$meaningful_errors = array();
		foreach ( $raw_errors as $error ) {
			$msg = trim( $error->message );
			if ( '' === $msg ) {
				continue;
			}

			$dominated = false;
			foreach ( self::IGNORED_PATTERNS as $pattern ) {
				if ( stripos( $msg, $pattern ) !== false ) {
					$dominated = true;
					break;
				}
			}

			if ( ! $dominated ) {
				$meaningful_errors[] = sprintf(
					/* translators: 1: line number, 2: error message */
					__( 'Line %1$d: %2$s', 'scalyn-qa-assistant' ),
					$error->line,
					$msg,
				);
			}
		}

		$error_count = count( $meaningful_errors );

		if ( 0 === $error_count ) {
			return new Check_Item(
				id:        'broken_html',
				label:     __( 'HTML Validation', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No HTML errors detected.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$displayed = array_slice( $meaningful_errors, 0, self::MAX_ERRORS_REPORTED );

		return new Check_Item(
			id:        'broken_html',
			label:     __( 'HTML Validation', 'scalyn-qa-assistant' ),
			status:    $error_count >= 5 ? 'fail' : 'warning',
			message:   sprintf(
				/* translators: %d: number of HTML errors */
				_n(
					'%d HTML error detected.',
					'%d HTML errors detected.',
					$error_count,
					'scalyn-qa-assistant',
				),
				$error_count,
			),
			category:  'functionality',
			severity:  $error_count >= 5 ? 'critical' : 'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array(
				'errors'      => $displayed,
				'total_count' => $error_count,
			),
		);
	}

	/**
	 * Check for elements with missing or empty URLs:
	 * - <a> tags with empty or missing href
	 * - <img> tags with empty or missing src
	 * - <iframe> tags with empty or missing src
	 *
	 * @since 1.4.10
	 *
	 * @param string $content The rendered HTML content.
	 * @return Check_Item
	 */
	private function check_missing_urls( string $content ): Check_Item {
		$tooltip = __( 'Elements with empty or missing URLs (href, src) are broken and won\'t work for visitors. Fix them in the post editor by adding a valid URL or removing the element.', 'scalyn-qa-assistant' );

		if ( '' === trim( $content ) ) {
			return new Check_Item(
				id:        'missing_urls',
				label:     __( 'Missing URLs', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No content to check.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$parser = new HTML_Parser( $content );
		$issues = array();

		// Check <a> tags.
		$links = $parser->get_links();
		foreach ( $links as $link ) {
			$href = trim( $link['url'] );
			if ( '' === $href ) {
				$text     = trim( $link['text'] ) ?: __( '(no text)', 'scalyn-qa-assistant' );
				$issues[] = sprintf(
					/* translators: %s: link text */
					__( '<a> with empty href: "%s"', 'scalyn-qa-assistant' ),
					mb_strlen( $text ) > 50 ? mb_substr( $text, 0, 50 ) . '...' : $text,
				);
			}
		}

		// Check <a> tags without href attribute at all.
		$anchors_no_href = $parser->query( '//a[not(@href)]' );
		foreach ( $anchors_no_href as $node ) {
			$text     = trim( $node->textContent ) ?: __( '(no text)', 'scalyn-qa-assistant' );
			$issues[] = sprintf(
				/* translators: %s: link text */
				__( '<a> missing href attribute: "%s"', 'scalyn-qa-assistant' ),
				mb_strlen( $text ) > 50 ? mb_substr( $text, 0, 50 ) . '...' : $text,
			);
		}

		// Check <img> tags.
		$images = $parser->get_images();
		foreach ( $images as $image ) {
			$src = trim( $image['src'] );
			if ( '' === $src ) {
				$alt      = trim( $image['alt'] ) ?: __( '(no alt)', 'scalyn-qa-assistant' );
				$issues[] = sprintf(
					/* translators: %s: image alt text */
					__( '<img> with empty src (alt: "%s")', 'scalyn-qa-assistant' ),
					mb_strlen( $alt ) > 50 ? mb_substr( $alt, 0, 50 ) . '...' : $alt,
				);
			}
		}

		// Check <img> tags without src attribute at all.
		$images_no_src = $parser->query( '//img[not(@src)]' );
		foreach ( $images_no_src as $node ) {
			$alt      = trim( $node->getAttribute( 'alt' ) ) ?: __( '(no alt)', 'scalyn-qa-assistant' );
			$issues[] = sprintf(
				/* translators: %s: image alt text */
				__( '<img> missing src attribute (alt: "%s")', 'scalyn-qa-assistant' ),
				mb_strlen( $alt ) > 50 ? mb_substr( $alt, 0, 50 ) . '...' : $alt,
			);
		}

		// Check <iframe> tags.
		$iframes = $parser->query( '//iframe' );
		foreach ( $iframes as $node ) {
			$src = trim( $node->getAttribute( 'src' ) );
			if ( '' === $src && '' === trim( $node->getAttribute( 'srcdoc' ) ) ) {
				$title    = trim( $node->getAttribute( 'title' ) ) ?: __( '(no title)', 'scalyn-qa-assistant' );
				$issues[] = sprintf(
					/* translators: %s: iframe title */
					__( '<iframe> with empty src (title: "%s")', 'scalyn-qa-assistant' ),
					mb_strlen( $title ) > 50 ? mb_substr( $title, 0, 50 ) . '...' : $title,
				);
			}
		}

		$issue_count = count( $issues );

		if ( 0 === $issue_count ) {
			return new Check_Item(
				id:        'missing_urls',
				label:     __( 'Missing URLs', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'All links, images, and iframes have valid URLs.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$displayed = array_slice( $issues, 0, self::MAX_ERRORS_REPORTED );

		return new Check_Item(
			id:        'missing_urls',
			label:     __( 'Missing URLs', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   sprintf(
				/* translators: %d: number of elements with missing URLs */
				_n(
					'%d element found with a missing or empty URL.',
					'%d elements found with missing or empty URLs.',
					$issue_count,
					'scalyn-qa-assistant',
				),
				$issue_count,
			),
			category:  'functionality',
			severity:  'critical',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array(
				'issues'      => $displayed,
				'total_count' => $issue_count,
			),
		);
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * Supports Elementor page builder content when available.
	 *
	 * @since 1.4.10
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

		/** This filter is documented in wp-includes/post-template.php */
		return (string) apply_filters( 'the_content', $raw_content );
	}
}
