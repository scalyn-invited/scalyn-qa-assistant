<?php
/**
 * Widget: Pages Requiring Attention.
 *
 * Renders a table of the lowest-scoring pages that need review.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array $pages Array of page data arrays with keys:
 *                   post_id, title, score, status, edit_url,
 *                   and optionally seo, content, functionality scores.
 */

defined( 'ABSPATH' ) || exit;

$pages = isset( $pages ) && is_array( $pages ) ? $pages : array();
?>
<div class="scalyn-card">
	<h2 class="scalyn-card-title"><?php esc_html_e( 'Pages Requiring Attention', 'scalyn-qa-assistant' ); ?></h2>

	<?php if ( empty( $pages ) ) : ?>
		<p class="scalyn-empty"><?php esc_html_e( 'No pages require attention. All pages are scoring well!', 'scalyn-qa-assistant' ); ?></p>
	<?php else : ?>
		<table class="scalyn-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page Title', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'SEO', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Content', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Func.', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Overall', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'scalyn-qa-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $page ) : ?>
					<?php
					$post_id  = isset( $page['post_id'] ) ? (int) $page['post_id'] : 0;
					$title    = isset( $page['title'] ) ? $page['title'] : '';
					$overall  = isset( $page['score'] ) ? (int) $page['score'] : 0;
					$status   = isset( $page['status'] ) ? $page['status'] : 'red';

					// Individual category scores (loaded from post meta if not provided).
					$scores_data = get_post_meta( $post_id, '_scalyn_qa_scores', true );
					$scores_data = is_array( $scores_data ) ? $scores_data : array();

					$seo_score  = isset( $scores_data['seo'] ) ? (int) $scores_data['seo'] : 0;
					$cont_score = isset( $scores_data['content'] ) ? (int) $scores_data['content'] : 0;
					$func_score = isset( $scores_data['functionality'] ) ? (int) $scores_data['functionality'] : 0;

					$audit_url = admin_url( 'admin.php?page=scalyn-qa-audits&post_id=' . $post_id );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $audit_url ); ?>">
								<?php echo esc_html( $title ); ?>
							</a>
						</td>
						<td>
							<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $seo_score ) ); ?>">
								<?php echo esc_html( $seo_score ); ?>
							</span>
						</td>
						<td>
							<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $cont_score ) ); ?>">
								<?php echo esc_html( $cont_score ); ?>
							</span>
						</td>
						<td>
							<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $func_score ) ); ?>">
								<?php echo esc_html( $func_score ); ?>
							</span>
						</td>
						<td>
							<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $overall ); ?>
							</span>
						</td>
						<td>
							<a href="<?php echo esc_url( $audit_url ); ?>" class="scalyn-btn scalyn-btn--small">
								<?php esc_html_e( 'View', 'scalyn-qa-assistant' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
