<?php
/**
 * Partial: Score Badge.
 *
 * Renders a colored score badge indicating QA score status.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var int    $score  The score value (0-100).
 * @var string $status Traffic-light status: 'green', 'yellow', or 'red'.
 * @var string $size   Badge size: 'small' or 'large'. Default 'small'.
 */

defined( 'ABSPATH' ) || exit;

$score  = isset( $score ) ? (int) $score : 0;
$status = isset( $status ) ? $status : \Scalyn\QA\Models\Score::calculate_status( $score );
$size   = isset( $size ) && in_array( $size, array( 'small', 'large' ), true ) ? $size : 'small';

$status_labels = array(
	'green'  => __( 'Good', 'scalyn-qa-assistant' ),
	'yellow' => __( 'Needs Review', 'scalyn-qa-assistant' ),
	'red'    => __( 'Issues Found', 'scalyn-qa-assistant' ),
);

$label = $status_labels[ $status ] ?? __( 'Unknown', 'scalyn-qa-assistant' );
?>
<div class="scalyn-score-badge scalyn-score-badge--<?php echo esc_attr( $size ); ?> scalyn-score-badge--<?php echo esc_attr( $status ); ?>"
	title="<?php echo esc_attr( $label ); ?>"
	role="img"
	aria-label="<?php echo esc_attr( sprintf(
		/* translators: 1: Score value, 2: Status label. */
		__( 'Score: %1$d — %2$s', 'scalyn-qa-assistant' ),
		$score,
		$label,
	) ); ?>"
>
	<span class="scalyn-score-badge__value"><?php echo esc_html( (string) $score ); ?></span>
	<?php if ( 'large' === $size ) : ?>
		<span class="scalyn-score-badge__label"><?php echo esc_html( $label ); ?></span>
	<?php endif; ?>
</div>
