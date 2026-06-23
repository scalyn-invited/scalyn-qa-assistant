<?php
/**
 * HTML Parser.
 *
 * Shared DOMDocument/DOMXPath helper for parsing HTML content.
 * Used by all analyzers to replace regex-based HTML parsing.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.1.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

/**
 * Class HTML_Parser
 *
 * Provides DOMDocument-based HTML parsing with convenient methods for
 * extracting images, links, headings, buttons, forms, and popup triggers.
 *
 * @since 1.1.0
 */
final class HTML_Parser {

	/**
	 * The parsed DOM document.
	 *
	 * @var \DOMDocument
	 */
	private \DOMDocument $doc;

	/**
	 * The XPath query engine for the document.
	 *
	 * @var \DOMXPath
	 */
	private \DOMXPath $xpath;

	/**
	 * Constructor.
	 *
	 * Parses HTML content into a DOMDocument with XPath support.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html The HTML content to parse.
	 */
	public function __construct( string $html ) {
		$this->doc = new \DOMDocument();

		// Suppress warnings from malformed HTML.
		libxml_use_internal_errors( true );

		// Wrap in UTF-8 encoding declaration to handle multibyte.
		$this->doc->loadHTML(
			'<?xml encoding="UTF-8"><div id="scalyn-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR,
		);

		libxml_clear_errors();

		$this->xpath = new \DOMXPath( $this->doc );
	}

	/**
	 * Get all elements matching an XPath query.
	 *
	 * @since 1.1.0
	 *
	 * @param string       $expression The XPath expression.
	 * @param \DOMNode|null $context    Optional context node.
	 * @return \DOMNodeList<\DOMElement>
	 */
	public function query( string $expression, ?\DOMNode $context = null ): \DOMNodeList {
		$result = null !== $context
			? $this->xpath->query( $expression, $context )
			: $this->xpath->query( $expression );

		return $result ?: new \DOMNodeList();
	}

	/**
	 * Get all images.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{src: string, alt: string, has_alt: bool}>
	 */
	public function get_images(): array {
		$images = array();
		$nodes  = $this->query( '//img' );

		foreach ( $nodes as $node ) {
			$alt      = $node->getAttribute( 'alt' );
			$images[] = array(
				'src'     => $node->getAttribute( 'src' ),
				'alt'     => $alt,
				'has_alt' => $node->hasAttribute( 'alt' ) && '' !== trim( $alt ),
			);
		}

		return $images;
	}

	/**
	 * Get all links.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{url: string, text: string, target: string, rel: string, attributes: array<string, string>}>
	 */
	public function get_links(): array {
		$links = array();
		$nodes = $this->query( '//a[@href]' );

		foreach ( $nodes as $node ) {
			$links[] = array(
				'url'        => $node->getAttribute( 'href' ),
				'text'       => trim( $node->textContent ),
				'target'     => $node->getAttribute( 'target' ),
				'rel'        => $node->getAttribute( 'rel' ),
				'attributes' => $this->get_all_attributes( $node ),
			);
		}

		return $links;
	}

	/**
	 * Get all headings (h1-h6).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{tag: string, level: int, text: string, is_empty: bool}>
	 */
	public function get_headings(): array {
		$headings = array();
		$nodes    = $this->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

		foreach ( $nodes as $node ) {
			$tag  = strtolower( $node->nodeName );
			$text = trim( $node->textContent );

			$headings[] = array(
				'tag'      => $tag,
				'level'    => (int) substr( $tag, 1 ),
				'text'     => $text,
				'is_empty' => '' === $text,
			);
		}

		return $headings;
	}

	/**
	 * Get all buttons (button elements + a[role=button]).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{tag: string, text: string, type: string, href: string, aria_label: string, title: string}>
	 */
	public function get_buttons(): array {
		$buttons = array();

		// <button> elements.
		$nodes = $this->query( '//button' );
		foreach ( $nodes as $node ) {
			$buttons[] = array(
				'tag'        => 'button',
				'text'       => trim( $node->textContent ),
				'type'       => $node->getAttribute( 'type' ) ?: 'submit',
				'href'       => '',
				'aria_label' => $node->getAttribute( 'aria-label' ),
				'title'      => $node->getAttribute( 'title' ),
			);
		}

		// <a role="button"> elements.
		$nodes = $this->query( '//a[@role="button"]' );
		foreach ( $nodes as $node ) {
			$buttons[] = array(
				'tag'        => 'a',
				'text'       => trim( $node->textContent ),
				'type'       => '',
				'href'       => $node->getAttribute( 'href' ),
				'aria_label' => $node->getAttribute( 'aria-label' ),
				'title'      => $node->getAttribute( 'title' ),
			);
		}

		return $buttons;
	}

	/**
	 * Get all forms with their submit buttons.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{action: string, method: string, has_submit: bool}>
	 */
	public function get_forms(): array {
		$forms = array();
		$nodes = $this->query( '//form' );

		foreach ( $nodes as $node ) {
			// Check for submit buttons within this form.
			$submits = $this->query(
				'.//button[@type="submit"]|.//input[@type="submit"]|.//button[not(@type)]',
				$node,
			);

			$forms[] = array(
				'action'     => $node->getAttribute( 'action' ),
				'method'     => $node->getAttribute( 'method' ) ?: 'get',
				'has_submit' => $submits->length > 0,
			);
		}

		return $forms;
	}

	/**
	 * Get all placeholder links (href="#" or javascript:void).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{url: string, text: string}>
	 */
	public function get_placeholder_links(): array {
		$placeholders = array();
		$nodes        = $this->query( '//a[@href]' );

		foreach ( $nodes as $node ) {
			$href = trim( $node->getAttribute( 'href' ) );

			if (
				'#' === $href
				|| 'javascript:void(0)' === strtolower( $href )
				|| 'javascript:void(0);' === strtolower( $href )
				|| 'javascript:;' === strtolower( $href )
			) {
				$placeholders[] = array(
					'url'  => $href,
					'text' => trim( $node->textContent ),
				);
			}
		}

		return $placeholders;
	}

	/**
	 * Get popup trigger elements.
	 *
	 * Checks for common popup/modal trigger attributes used by WordPress
	 * page builders and popup plugins, plus class-based triggers.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{type: string, target: string, element: string}>
	 */
	public function get_popup_triggers(): array {
		$triggers = array();

		$popup_attrs = array(
			'data-popup',
			'data-modal',
			'data-toggle',
			'data-bs-toggle',
			'data-elementor-open-lightbox',
			'data-elementor-action',
			'data-fancybox',
			'data-lightbox',
		);

		foreach ( $popup_attrs as $attr ) {
			$nodes = $this->query( "//*[@{$attr}]" );

			foreach ( $nodes as $node ) {
				$value  = $node->getAttribute( $attr );
				$target = '';

				// Bootstrap modal: data-target or data-bs-target.
				if ( in_array( $attr, array( 'data-toggle', 'data-bs-toggle' ), true ) && 'modal' === $value ) {
					$target = $node->getAttribute( 'data-target' ) ?: $node->getAttribute( 'data-bs-target' );
				} else {
					$target = $value;
				}

				$triggers[] = array(
					'type'    => $attr,
					'target'  => $target,
					'element' => $node->nodeName,
				);
			}
		}

		// Also check common popup CSS classes.
		$popup_classes = array( 'popup-trigger', 'modal-trigger', 'lightbox-trigger', 'open-popup', 'fancybox' );

		foreach ( $popup_classes as $class ) {
			$nodes = $this->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]" );

			foreach ( $nodes as $node ) {
				$triggers[] = array(
					'type'    => 'css_class',
					'target'  => $class,
					'element' => $node->nodeName,
				);
			}
		}

		return $triggers;
	}

	/**
	 * Check if an element with a specific ID exists.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id The element ID to search for.
	 * @return bool True if the element exists.
	 */
	public function has_element_id( string $id ): bool {
		$nodes = $this->query( "//*[@id='{$id}']" );
		return $nodes->length > 0;
	}

	/**
	 * Get all attributes of a DOMElement as an associative array.
	 *
	 * @since 1.1.0
	 *
	 * @param \DOMElement $node The DOM element.
	 * @return array<string, string>
	 */
	private function get_all_attributes( \DOMElement $node ): array {
		$attrs = array();

		foreach ( $node->attributes as $attr ) {
			$attrs[ $attr->name ] = $attr->value;
		}

		return $attrs;
	}
}
