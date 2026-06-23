<?php
/**
 * Widget: Getting Started Onboarding Journey.
 *
 * Renders a step-by-step guide for new users on the dashboard.
 *
 * @package Scalyn\QA\Templates
 * @since   1.4.0
 *
 * @var array $onboarding Onboarding data with core_steps, optional, completed_count, etc.
 */

defined( 'ABSPATH' ) || exit;

$core_steps      = $onboarding['core_steps'] ?? array();
$optional        = $onboarding['optional'] ?? array();
$completed_count = (int) ( $onboarding['completed_count'] ?? 0 );
$total_count     = (int) ( $onboarding['total_count'] ?? 4 );
$all_complete    = ! empty( $onboarding['all_complete'] );
$progress_pct    = $total_count > 0 ? round( ( $completed_count / $total_count ) * 100 ) : 0;

// Determine the current (next incomplete) step index.
$current_index = $total_count; // default: all done
foreach ( $core_steps as $i => $step ) {
	if ( ! $step['complete'] ) {
		$current_index = $i;
		break;
	}
}
?>
<div class="scalyn-onboarding <?php echo $all_complete ? 'scalyn-onboarding--complete' : ''; ?>" id="scalyn-onboarding">

	<!-- Header -->
	<div class="scalyn-onboarding__header">
		<div class="scalyn-onboarding__header-left">
			<h2 class="scalyn-onboarding__title">
				<?php echo $all_complete ? esc_html__( 'All Set!', 'scalyn-qa-assistant' ) : esc_html__( 'Getting Started', 'scalyn-qa-assistant' ); ?>
			</h2>
			<span class="scalyn-onboarding__progress-text">
				<?php if ( $all_complete ) : ?>
					<?php esc_html_e( 'All steps complete', 'scalyn-qa-assistant' ); ?>
				<?php else : ?>
					<?php
					printf(
						/* translators: 1: completed count, 2: total count */
						esc_html__( '%1$d of %2$d complete', 'scalyn-qa-assistant' ),
						$completed_count,
						$total_count,
					);
					?>
				<?php endif; ?>
			</span>
		</div>
	</div>

	<!-- Progress Bar -->
	<div class="scalyn-onboarding__progress-bar">
		<div class="scalyn-onboarding__progress-fill" style="width: <?php echo esc_attr( (string) $progress_pct ); ?>%;"></div>
	</div>

	<!-- Core Steps -->
	<div class="scalyn-onboarding__steps">
		<?php foreach ( $core_steps as $i => $step ) :
			$is_complete = $step['complete'];
			$is_current  = ( $i === $current_index );
			$is_locked   = ( $i > $current_index );
			$step_num    = $i + 1;

			if ( $is_complete ) {
				$state_class = 'scalyn-onboarding-step--complete';
			} elseif ( $is_current ) {
				$state_class = 'scalyn-onboarding-step--active';
			} else {
				$state_class = 'scalyn-onboarding-step--pending';
			}
		?>
		<div class="scalyn-onboarding-step <?php echo esc_attr( $state_class ); ?>">
			<div class="scalyn-onboarding-step__icon">
				<?php if ( $is_complete ) : ?>
					<svg viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
				<?php else : ?>
					<span class="scalyn-onboarding-step__number"><?php echo esc_html( (string) $step_num ); ?></span>
				<?php endif; ?>
			</div>
			<div class="scalyn-onboarding-step__content">
				<span class="scalyn-onboarding-step__label"><?php echo esc_html( $step['label'] ); ?></span>
				<span class="scalyn-onboarding-step__desc"><?php echo esc_html( $step['desc'] ); ?></span>
			</div>
			<div class="scalyn-onboarding-step__action">
				<?php if ( $is_complete ) : ?>
					<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( 'Done', 'scalyn-qa-assistant' ); ?></span>
				<?php elseif ( $is_current ) : ?>
					<a href="<?php echo esc_url( $step['url'] ); ?>" class="scalyn-btn scalyn-btn--small" <?php echo ! empty( $step['target'] ) ? 'target="' . esc_attr( $step['target'] ) . '"' : ''; ?>><?php echo esc_html( $step['cta'] ); ?></a>
				<?php else : ?>
					<span class="scalyn-onboarding-step__locked">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Optional Enhancements -->
	<div class="scalyn-onboarding__optional">
		<span class="scalyn-onboarding__optional-label"><?php esc_html_e( 'Enhance Your Workflow', 'scalyn-qa-assistant' ); ?></span>
		<span class="scalyn-onboarding__optional-hint"><?php esc_html_e( '(optional)', 'scalyn-qa-assistant' ); ?></span>
		<div class="scalyn-onboarding__optional-items">
			<?php foreach ( $optional as $opt ) : ?>
			<div class="scalyn-onboarding-opt <?php echo $opt['complete'] ? 'scalyn-onboarding-opt--complete' : ''; ?>">
				<?php if ( $opt['complete'] ) : ?>
					<span class="scalyn-onboarding-opt__check">
						<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
					</span>
				<?php endif; ?>
				<div class="scalyn-onboarding-opt__text">
					<span class="scalyn-onboarding-opt__label"><?php echo esc_html( $opt['label'] ); ?></span>
					<span class="scalyn-onboarding-opt__desc"><?php echo esc_html( $opt['desc'] ); ?></span>
				</div>
				<?php if ( ! $opt['complete'] ) : ?>
					<a href="<?php echo esc_url( $opt['url'] ); ?>" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost"><?php echo esc_html( $opt['cta'] ); ?></a>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

</div>
