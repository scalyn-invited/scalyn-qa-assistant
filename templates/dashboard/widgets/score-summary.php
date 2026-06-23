<?php
/**
 * Widget: Score Summary Card.
 *
 * Renders a reusable score card with a circular score indicator and status colour.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var string $label  The card label (e.g. "SEO Ready").
 * @var int    $score  The score value (0-100).
 * @var string $status Traffic-light status: 'green', 'yellow', or 'red'.
 */

defined( 'ABSPATH' ) || exit;

$score  = isset( $score ) ? (int) $score : 0;
$status = isset( $status ) ? $status : \Scalyn\QA\Models\Score::calculate_status( $score );
$label  = isset( $label ) ? $label : '';
?>
<div class="scalyn-score-card">
	<div class="scalyn-score-circle scalyn-score-circle--<?php echo esc_attr( $status ); ?>"
		 style="--scalyn-score: <?php echo esc_attr( $score ); ?>">
		<span class="scalyn-score-circle__value"><?php echo esc_html( $score ); ?><span class="scalyn-score-circle__unit">%</span></span>
	</div>
	<span class="scalyn-score-card__label"><?php echo esc_html( $label ); ?></span>
	<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $status ); ?> scalyn-badge--small">
		<?php
		switch ( $status ) {
			case 'green':
				esc_html_e( 'Passed', 'scalyn-qa-assistant' );
				break;
			case 'yellow':
				esc_html_e( 'Needs Review', 'scalyn-qa-assistant' );
				break;
			case 'red':
				esc_html_e( 'Issues Found', 'scalyn-qa-assistant' );
				break;
			default:
				echo esc_html( ucfirst( $status ) );
				break;
		}
		?>
	</span>
</div>
