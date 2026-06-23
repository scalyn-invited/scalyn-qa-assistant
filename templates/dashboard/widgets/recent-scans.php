<?php
/**
 * Widget: Recent Scans.
 *
 * Renders a table of the most recently scanned pages with human-readable time.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array $scans Array of scan data arrays with keys:
 *                   post_id, title, score, status, scanned_at, edit_url.
 */

defined( 'ABSPATH' ) || exit;

$scans = isset( $scans ) && is_array( $scans ) ? $scans : array();
?>
<div class="scalyn-card">
	<h2 class="scalyn-card-title"><?php esc_html_e( 'Recent Scans', 'scalyn-qa-assistant' ); ?></h2>

	<?php if ( empty( $scans ) ) : ?>
		<p class="scalyn-empty"><?php esc_html_e( 'No scans have been performed yet.', 'scalyn-qa-assistant' ); ?></p>
	<?php else : ?>
		<table class="scalyn-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Score', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Scanned', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'scalyn-qa-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $scans as $scan ) : ?>
					<?php
					$post_id    = isset( $scan['post_id'] ) ? (int) $scan['post_id'] : 0;
					$title      = isset( $scan['title'] ) ? $scan['title'] : '';
					$score      = isset( $scan['score'] ) ? (int) $scan['score'] : 0;
					$status     = isset( $scan['status'] ) ? $scan['status'] : 'red';
					$scanned_at = isset( $scan['scanned_at'] ) ? $scan['scanned_at'] : '';
					$audit_url  = admin_url( 'admin.php?page=scalyn-qa-audits&post_id=' . $post_id );

					// Calculate human-readable time difference.
					$time_diff = '';
					if ( ! empty( $scanned_at ) ) {
						$scan_timestamp = strtotime( $scanned_at );
						if ( false !== $scan_timestamp ) {
							$time_diff = sprintf(
								/* translators: %s: Human-readable time difference. */
								esc_html__( '%s ago', 'scalyn-qa-assistant' ),
								human_time_diff( $scan_timestamp, time() )
							);
						}
					}
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $audit_url ); ?>">
								<?php echo esc_html( $title ); ?>
							</a>
						</td>
						<td>
							<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $score ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $time_diff ) ) : ?>
								<span title="<?php echo esc_attr( $scanned_at ); ?>">
									<?php echo esc_html( $time_diff ); ?>
								</span>
							<?php else : ?>
								<span class="scalyn-text-muted">&mdash;</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $audit_url ); ?>" class="scalyn-btn scalyn-btn--small">
								<?php esc_html_e( 'View', 'scalyn-qa-assistant' ); ?>
							</a>
							<button
								type="button"
								class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-rescan"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
							>
								<?php esc_html_e( 'Rescan', 'scalyn-qa-assistant' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
