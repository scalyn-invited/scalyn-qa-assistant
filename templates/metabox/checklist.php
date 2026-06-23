<?php
/**
 * Template: Metabox Checklist.
 *
 * Compact layout for the post edit sidebar showing QA scan results,
 * score badge, expandable check list, and action buttons.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var \WP_Post                            $post        The current post object.
 * @var int                                 $post_id     The post ID.
 * @var \Scalyn\QA\Models\Scan_Result|null  $scan_result The scan result or null if not scanned.
 * @var array                               $settings    Plugin settings array.
 */

defined( 'ABSPATH' ) || exit;

$post_id     = isset( $post_id ) ? (int) $post_id : 0;
$scan_result = isset( $scan_result ) ? $scan_result : null;
$settings    = isset( $settings ) ? $settings : array();

$has_results = null !== $scan_result;
$ai_enabled  = ! empty( $settings['enable_ai'] );

// Score and status.
$overall_score = $has_results ? $scan_result->scores->overall : 0;
$status        = $has_results ? $scan_result->scores->status : 'red';
$scanned_at    = $has_results ? $scan_result->scanned_at : '';

// Collect all check items across categories.
$all_checks  = array();
$pass_count  = 0;
$total_count = 0;

if ( $has_results ) {
	foreach ( $scan_result->results as $category => $items ) {
		foreach ( $items as $check_item ) {
			$all_checks[] = $check_item;
			++$total_count;
			if ( $check_item->is_passed() ) {
				++$pass_count;
			}
		}
	}
}

// Format last scan time.
$last_scan_text = '';
if ( ! empty( $scanned_at ) ) {
	$timestamp = strtotime( $scanned_at );
	if ( false !== $timestamp ) {
		/* translators: %s: Human-readable time difference. */
		$last_scan_text = sprintf( __( 'Scanned %s ago', 'scalyn-qa-assistant' ), human_time_diff( $timestamp, time() ) );
	}
}

// Audit page URL.
$audit_url = admin_url( 'admin.php?page=scalyn-qa-audits&post_id=' . $post_id );
?>
<div class="scalyn-metabox" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">

	<?php if ( $has_results ) : ?>

		<!-- Score Badge -->
		<div class="scalyn-metabox__score">
			<?php
			$score = $overall_score;
			$size  = 'large';
			include SCALYN_QA_PLUGIN_DIR . 'templates/partials/score-badge.php';
			?>
		</div>

		<!-- Summary Line -->
		<div class="scalyn-metabox__summary">
			<span class="scalyn-metabox__counts">
				<?php
				printf(
					/* translators: 1: Number of passed checks, 2: Total number of checks. */
					esc_html__( '%1$d/%2$d checks passed', 'scalyn-qa-assistant' ),
					$pass_count,
					$total_count,
				);
				?>
			</span>
			<?php if ( ! empty( $last_scan_text ) ) : ?>
				<span class="scalyn-metabox__time"><?php echo esc_html( $last_scan_text ); ?></span>
			<?php endif; ?>
		</div>

		<!-- Compact Check List -->
		<div class="scalyn-metabox__checks">
			<?php
			// Show failing/warning checks first, then passing.
			$issues  = array();
			$passing = array();

			foreach ( $all_checks as $check_item ) {
				if ( $check_item->is_passed() ) {
					$passing[] = $check_item;
				} else {
					$issues[] = $check_item;
				}
			}
			?>

			<?php if ( ! empty( $issues ) ) : ?>
				<div class="scalyn-metabox__section scalyn-metabox__section--issues">
					<button
						type="button"
						class="scalyn-metabox__toggle scalyn-metabox__toggle--expanded"
						aria-expanded="true"
						aria-controls="scalyn-issues-list"
					>
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<?php
						printf(
							/* translators: %d: Number of issues. */
							esc_html( _n( '%d Issue', '%d Issues', count( $issues ), 'scalyn-qa-assistant' ) ),
							count( $issues ),
						);
						?>
						<span class="scalyn-metabox__arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<div id="scalyn-issues-list" class="scalyn-metabox__list">
						<?php foreach ( $issues as $check_item ) : ?>
							<?php
							$item = $check_item->to_array();
							include SCALYN_QA_PLUGIN_DIR . 'templates/partials/check-item.php';
							?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $passing ) ) : ?>
				<div class="scalyn-metabox__section scalyn-metabox__section--passing">
					<button
						type="button"
						class="scalyn-metabox__toggle"
						aria-expanded="false"
						aria-controls="scalyn-passing-list"
					>
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php
						printf(
							/* translators: %d: Number of passed checks. */
							esc_html( _n( '%d Passed', '%d Passed', count( $passing ), 'scalyn-qa-assistant' ) ),
							count( $passing ),
						);
						?>
						<span class="scalyn-metabox__arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<div id="scalyn-passing-list" class="scalyn-metabox__list" style="display: none;">
						<?php foreach ( $passing as $check_item ) : ?>
							<?php
							$item = $check_item->to_array();
							include SCALYN_QA_PLUGIN_DIR . 'templates/partials/check-item.php';
							?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<!-- No scan results -->
		<div class="scalyn-metabox__empty">
			<span class="dashicons dashicons-info" aria-hidden="true"></span>
			<p>
				<?php esc_html_e( 'No scan results yet. Click "Scan Now" to analyze this content, or save the post to trigger an automatic scan.', 'scalyn-qa-assistant' ); ?>
			</p>
		</div>

	<?php endif; ?>

	<!-- Action Buttons -->
	<div class="scalyn-metabox__actions">
		<button
			type="button"
			id="scalyn-metabox-rescan"
			class="scalyn-btn scalyn-btn--small scalyn-btn--full-width"
			data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
		>
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Scan Now', 'scalyn-qa-assistant' ); ?>
		</button>

		<?php if ( $has_results ) : ?>
			<a
				href="<?php echo esc_url( $audit_url ); ?>"
				class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-btn--full-width"
				target="_blank"
			>
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'View Full Audit', 'scalyn-qa-assistant' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( $ai_enabled ) : ?>
			<button
				type="button"
				id="scalyn-metabox-ai-meta"
				class="scalyn-btn scalyn-btn--small scalyn-btn--ai scalyn-btn--full-width"
				data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
			>
				<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
				<?php esc_html_e( 'Generate AI Meta', 'scalyn-qa-assistant' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<!-- Loading overlay -->
	<div class="scalyn-metabox__loading" style="display: none;" aria-hidden="true">
		<span class="spinner is-active"></span>
		<span><?php esc_html_e( 'Scanning...', 'scalyn-qa-assistant' ); ?></span>
	</div>

</div>
