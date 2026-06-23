<?php
/**
 * Partial: Tooltip.
 *
 * Renders a tooltip icon with hover content for contextual help.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var string $text The tooltip text to display on hover.
 */

defined( 'ABSPATH' ) || exit;

$text = isset( $text ) ? $text : '';

if ( empty( $text ) ) {
	return;
}
?>
<span class="scalyn-tooltip" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'scalyn-qa-assistant' ); ?>">
	<span class="dashicons dashicons-info" aria-hidden="true"></span>
	<span class="scalyn-tooltip__content"><?php echo esc_html( $text ); ?></span>
</span>
