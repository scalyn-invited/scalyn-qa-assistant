<?php
/**
 * Form & Button Analyzer.
 *
 * Checks for accessibility and usability issues with buttons, links, forms,
 * and popup triggers in post content.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Form_Button_Analyzer
 *
 * Analyzes forms, buttons, and interactive elements for usability issues.
 *
 * @since 1.0.0
 */
class Form_Button_Analyzer implements Analyzer_Interface {

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'form_button';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Form & Button Analyzer', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'functionality';
	}

	/**
	 * Run all form and button checks on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content = $this->get_rendered_content( $post_id );
		$parser  = new HTML_Parser( $content );

		$checks   = array();
		$checks[] = $this->check_empty_buttons( $parser );
		$checks[] = $this->check_placeholder_links( $parser );
		$checks[] = $this->check_form_has_submit( $parser );
		$checks[] = $this->check_popup_triggers( $parser );

		return $checks;
	}

	/**
	 * Check for buttons without text content or aria-label.
	 *
	 * Inspects both <button> elements and <a role="button"> elements.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_empty_buttons( HTML_Parser $parser ): Check_Item {
		$tooltip       = __( 'Buttons need visible text or an aria-label for accessibility. Find the empty buttons in the post editor and add text content or an aria-label attribute.', 'scalyn-qa-assistant' );
		$empty_labels  = array();
		$buttons       = $parser->get_buttons();

		foreach ( $buttons as $index => $button ) {
			$text       = trim( wp_strip_all_tags( $button['text'] ) );
			$aria_label = trim( $button['aria_label'] );
			$title      = trim( $button['title'] );

			if ( '' === $text && '' === $aria_label && '' === $title ) {
				$element = 'button' === $button['tag'] ? '<button>' : '<a role="button">';
				$href    = $button['href'] ? ' href="' . $button['href'] . '"' : '';
				$empty_labels[] = sprintf( '%s%s (#%d)', $element, $href, $index + 1 );
			}
		}

		$empty_count = count( $empty_labels );

		if ( 0 === $empty_count ) {
			return new Check_Item(
				id:        'empty_buttons',
				label:     __( 'Empty Buttons', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'All buttons have text content or accessible labels.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'empty_buttons',
			label:     __( 'Empty Buttons', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   sprintf(
				/* translators: %d: number of empty buttons */
				_n(
					'%d button found without text or aria-label.',
					'%d buttons found without text or aria-label.',
					$empty_count,
					'scalyn-qa-assistant',
				),
				$empty_count,
			),
			category:  'functionality',
			severity:  'critical',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'empty_buttons' => $empty_labels ),
		);
	}

	/**
	 * Check for placeholder links (href="#" or "javascript:void(0)").
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_placeholder_links( HTML_Parser $parser ): Check_Item {
		$tooltip          = __( 'Links with href="#" or "javascript:void(0)" are placeholders. In the post editor, replace them with real URLs or convert them to button elements.', 'scalyn-qa-assistant' );
		$raw_placeholders = $parser->get_placeholder_links();
		$placeholder_labels = array();

		foreach ( $raw_placeholders as $link ) {
			$text = $link['text'] ?: __( '(no text)', 'scalyn-qa-assistant' );
			$placeholder_labels[] = sprintf( '"%s" (%s)', $text, $link['url'] );
		}

		$placeholder_count = count( $placeholder_labels );

		if ( 0 === $placeholder_count ) {
			return new Check_Item(
				id:        'placeholder_links',
				label:     __( 'Placeholder Links', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No placeholder links found.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'placeholder_links',
			label:     __( 'Placeholder Links', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: %d: number of placeholder links */
				_n(
					'%d placeholder link found.',
					'%d placeholder links found.',
					$placeholder_count,
					'scalyn-qa-assistant',
				),
				$placeholder_count,
			),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'placeholder_links' => $placeholder_labels ),
		);
	}

	/**
	 * Check that all forms have a submit button.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_form_has_submit( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'Every form needs a submit button so users can complete their action. Add a <button type="submit"> or <input type="submit"> inside the form in the post editor or page builder.', 'scalyn-qa-assistant' );
		$forms   = $parser->get_forms();

		if ( 0 === count( $forms ) ) {
			return new Check_Item(
				id:        'form_has_submit',
				label:     __( 'Form Submit Buttons', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No forms found on this page.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$total_forms          = count( $forms );
		$forms_without_submit = 0;

		foreach ( $forms as $form ) {
			if ( ! $form['has_submit'] ) {
				++$forms_without_submit;
			}
		}

		if ( 0 === $forms_without_submit ) {
			return new Check_Item(
				id:        'form_has_submit',
				label:     __( 'Form Submit Buttons', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: number of forms */
					_n(
						'%d form found, all have submit buttons.',
						'%d forms found, all have submit buttons.',
						$total_forms,
						'scalyn-qa-assistant',
					),
					$total_forms,
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'form_has_submit',
			label:     __( 'Form Submit Buttons', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: 1: number of forms without submit, 2: total forms */
				__( '%1$d of %2$d forms are missing a submit button.', 'scalyn-qa-assistant' ),
				$forms_without_submit,
				$total_forms,
			),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array(
				'total_forms'          => $total_forms,
				'forms_without_submit' => $forms_without_submit,
			),
		);
	}

	/**
	 * Check for popup trigger elements and validate their targets.
	 *
	 * Looks for common popup/modal trigger attributes used by WordPress
	 * page builders and popup plugins.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_popup_triggers( HTML_Parser $parser ): Check_Item {
		$tooltip      = __( 'Popup trigger elements should reference a valid popup ID. Verify in your page builder or theme settings that each trigger points to an existing popup.', 'scalyn-qa-assistant' );
		$raw_triggers = $parser->get_popup_triggers();
		$triggers     = array();

		foreach ( $raw_triggers as $trigger ) {
			$target = $trigger['target'];
			$valid  = $this->validate_popup_target( $target, $parser );

			$triggers[] = array(
				'type'   => $trigger['type'],
				'target' => $target,
				'valid'  => $valid,
			);
		}

		if ( 0 === count( $triggers ) ) {
			return new Check_Item(
				id:        'popup_triggers',
				label:     __( 'Popup Triggers', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No popup triggers found on this page.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$invalid_labels = array();

		foreach ( $triggers as $trigger ) {
			if ( false === $trigger['valid'] ) {
				$target = $trigger['target'] ?: __( '(no target)', 'scalyn-qa-assistant' );
				$invalid_labels[] = sprintf( '%s on <%s> (target: %s)', $trigger['type'], $trigger['element'] ?? 'unknown', $target );
			}
		}

		$invalid_count = count( $invalid_labels );

		if ( $invalid_count > 0 ) {
			return new Check_Item(
				id:        'popup_triggers',
				label:     __( 'Popup Triggers', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: 1: number of invalid triggers, 2: total triggers */
					__( '%1$d of %2$d popup triggers may have invalid targets.', 'scalyn-qa-assistant' ),
					$invalid_count,
					count( $triggers ),
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'invalid_triggers' => $invalid_labels ),
			);
		}

		return new Check_Item(
			id:        'popup_triggers',
			label:     __( 'Popup Triggers', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: %d: number of popup triggers found */
				_n(
					'%d popup trigger found.',
					'%d popup triggers found.',
					count( $triggers ),
					'scalyn-qa-assistant',
				),
				count( $triggers ),
			),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Validate that a popup target exists in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $target The popup target identifier.
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return bool|null True if valid, false if invalid, null if cannot determine.
	 */
	private function validate_popup_target( string $target, HTML_Parser $parser ): ?bool {
		if ( '' === $target ) {
			return null;
		}

		// If the target starts with #, look for the element ID.
		if ( str_starts_with( $target, '#' ) ) {
			$target_id = ltrim( $target, '#' );
			return $parser->has_element_id( $target_id );
		}

		// For numeric targets (common with Elementor popups), we cannot validate in-content.
		if ( is_numeric( $target ) ) {
			return null;
		}

		// For other string targets, check if an element with matching ID exists.
		if ( $parser->has_element_id( $target ) ) {
			return true;
		}

		return false;
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
