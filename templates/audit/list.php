<?php
/**
 * Template: Audit List.
 *
 * Lists all pages/posts with their QA scores, filterable by status,
 * with pagination and bulk scan capability.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array  $items          Array of post items with scores.
 * @var int    $total_posts    Total number of posts found.
 * @var int    $total_pages    Total number of pagination pages.
 * @var int    $current_page   Current page number.
 * @var int    $per_page       Items per page.
 * @var array  $post_types     Configured post types.
 * @var string $current_type   Currently selected post type filter.
 * @var string $current_status Currently selected status filter.
 * @var string $base_url       Base URL for the audit list page.
 */

defined( 'ABSPATH' ) || exit;

// Ensure variables have safe defaults.
$items          = isset( $items ) && is_array( $items ) ? $items : array();
$total_posts    = isset( $total_posts ) ? (int) $total_posts : 0;
$total_pages    = isset( $total_pages ) ? (int) $total_pages : 1;
$current_page   = isset( $current_page ) ? (int) $current_page : 1;
$per_page       = isset( $per_page ) ? (int) $per_page : 20;
$post_types     = isset( $post_types ) && is_array( $post_types ) ? $post_types : array( 'post', 'page' );
$current_type   = isset( $current_type ) ? $current_type : '';
$current_status = isset( $current_status ) ? $current_status : '';
$base_url       = isset( $base_url ) ? $base_url : admin_url( 'admin.php?page=scalyn-qa-audits' );
$status_summary = isset( $status_summary ) && is_array( $status_summary ) ? $status_summary : array();
$sum_green      = (int) ( $status_summary['green'] ?? 0 );
$sum_yellow     = (int) ( $status_summary['yellow'] ?? 0 );
$sum_red        = (int) ( $status_summary['red'] ?? 0 );
$sum_unscanned  = (int) ( $status_summary['unscanned'] ?? 0 );
$sum_total      = $sum_green + $sum_yellow + $sum_red + $sum_unscanned;
?>
<div class="scalyn-wrap">
	<div class="scalyn-page-header">
		<div class="scalyn-page-header__intro">
			<h1><?php esc_html_e( 'Page Audits', 'scalyn-qa-assistant' ); ?></h1>
			<p class="scalyn-page-header__description"><?php esc_html_e( 'Review SEO, content, and functionality scores for every page on your site.', 'scalyn-qa-assistant' ); ?></p>
		</div>
		<div class="scalyn-page-header__actions">
			<button type="button" id="scalyn-scan-selected" class="scalyn-btn scalyn-btn--secondary" style="display:none;">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Scan Selected', 'scalyn-qa-assistant' ); ?>
				(<span id="scalyn-selected-count">0</span>)
			</button>
			<button type="button" id="scalyn-scan-all" class="scalyn-btn">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php printf( esc_html__( 'Scan All Pages (%d)', 'scalyn-qa-assistant' ), $total_posts ); ?>
			</button>

			<select id="scalyn-filter-status" class="scalyn-select" data-base-url="<?php echo esc_attr( $base_url ); ?>">
				<option value=""<?php selected( $current_status, '' ); ?>>
					<?php esc_html_e( 'All Statuses', 'scalyn-qa-assistant' ); ?>
				</option>
				<option value="green"<?php selected( $current_status, 'green' ); ?>>
					<?php esc_html_e( 'Passed (Green)', 'scalyn-qa-assistant' ); ?>
				</option>
				<option value="yellow"<?php selected( $current_status, 'yellow' ); ?>>
					<?php esc_html_e( 'Needs Review (Yellow)', 'scalyn-qa-assistant' ); ?>
				</option>
				<option value="red"<?php selected( $current_status, 'red' ); ?>>
					<?php esc_html_e( 'Issues Found (Red)', 'scalyn-qa-assistant' ); ?>
				</option>
				<option value="unscanned"<?php selected( $current_status, 'unscanned' ); ?>>
					<?php esc_html_e( 'Not Scanned', 'scalyn-qa-assistant' ); ?>
				</option>
			</select>

			<?php if ( count( $post_types ) > 1 ) : ?>
				<select id="scalyn-filter-type" class="scalyn-select" data-base-url="<?php echo esc_attr( $base_url ); ?>">
					<option value=""<?php selected( $current_type, '' ); ?>>
						<?php esc_html_e( 'All Types', 'scalyn-qa-assistant' ); ?>
					</option>
					<?php foreach ( $post_types as $pt ) : ?>
						<?php
						$type_obj  = get_post_type_object( $pt );
						$type_name = $type_obj ? $type_obj->labels->singular_name : $pt;
						?>
						<option value="<?php echo esc_attr( $pt ); ?>"<?php selected( $current_type, $pt ); ?>>
							<?php echo esc_html( $type_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
	</div>

	<!-- Summary Stats -->
	<?php if ( $sum_total > 0 ) : ?>
	<div class="scalyn-grid scalyn-grid--4" style="margin-bottom:0;">
		<div class="scalyn-card scalyn-audit-stat">
			<span class="scalyn-audit-stat__value scalyn-text--green"><?php echo esc_html( (string) $sum_green ); ?></span>
			<span class="scalyn-audit-stat__label"><?php esc_html_e( 'Passed', 'scalyn-qa-assistant' ); ?></span>
		</div>
		<div class="scalyn-card scalyn-audit-stat">
			<span class="scalyn-audit-stat__value scalyn-text--yellow"><?php echo esc_html( (string) $sum_yellow ); ?></span>
			<span class="scalyn-audit-stat__label"><?php esc_html_e( 'Need Review', 'scalyn-qa-assistant' ); ?></span>
		</div>
		<div class="scalyn-card scalyn-audit-stat">
			<span class="scalyn-audit-stat__value scalyn-text--red"><?php echo esc_html( (string) $sum_red ); ?></span>
			<span class="scalyn-audit-stat__label"><?php esc_html_e( 'Issues Found', 'scalyn-qa-assistant' ); ?></span>
		</div>
		<div class="scalyn-card scalyn-audit-stat">
			<span class="scalyn-audit-stat__value" style="color:var(--scalyn-text-muted);"><?php echo esc_html( (string) $sum_unscanned ); ?></span>
			<span class="scalyn-audit-stat__label"><?php esc_html_e( 'Not Scanned', 'scalyn-qa-assistant' ); ?></span>
		</div>
	</div>
	<?php endif; ?>

	<!-- Progress bar (hidden by default, shown during scan all) -->
	<div id="scalyn-scan-progress" class="scalyn-card" style="display:none;">
		<div class="scalyn-progress scalyn-progress--large">
			<div class="scalyn-progress__bar" style="width: 0%;"></div>
		</div>
		<p class="scalyn-progress__text">
			<?php esc_html_e( 'Scanning...', 'scalyn-qa-assistant' ); ?>
			<span id="scalyn-scan-count">0</span> / <span id="scalyn-scan-total">0</span>
			<?php esc_html_e( 'pages', 'scalyn-qa-assistant' ); ?>
			(<span id="scalyn-scan-percent">0</span>%)
		</p>
	</div>

	<div class="scalyn-card scalyn-card--flush">
		<div class="scalyn-table-wrap">
		<table class="scalyn-table">
			<thead>
				<tr>
					<th class="scalyn-table__col--narrow"><input type="checkbox" id="scalyn-select-all" title="<?php esc_attr_e( 'Select all', 'scalyn-qa-assistant' ); ?>"></th>
					<th class="scalyn-table__col--narrow"><?php esc_html_e( '#', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Page', 'scalyn-qa-assistant' ); ?></th>
					<th class="scalyn-table__col--narrow"><?php esc_html_e( 'Type', 'scalyn-qa-assistant' ); ?></th>
					<th class="scalyn-table__col--narrow"><?php esc_html_e( 'SEO', 'scalyn-qa-assistant' ); ?></th>
					<th class="scalyn-table__col--narrow"><?php esc_html_e( 'Content', 'scalyn-qa-assistant' ); ?></th>
					<th class="scalyn-table__col--narrow"><?php esc_html_e( 'Func.', 'scalyn-qa-assistant' ); ?></th>
					<th class="scalyn-table__col--narrow"><?php esc_html_e( 'Overall', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Last Scan', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'scalyn-qa-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr>
						<td colspan="10" style="text-align:center;padding:2rem;">
							<span class="dashicons dashicons-search" style="font-size:32px;width:32px;height:32px;color:var(--scalyn-text-faint);display:block;margin:0 auto 0.5rem;" aria-hidden="true"></span>
							<strong style="display:block;margin-bottom:0.25rem;"><?php esc_html_e( 'No pages found', 'scalyn-qa-assistant' ); ?></strong>
							<span style="color:var(--scalyn-text-muted);font-size:0.8125rem;"><?php esc_html_e( 'Try adjusting your filters or run a scan to get started.', 'scalyn-qa-assistant' ); ?></span>
						</td>
					</tr>
				<?php else : ?>
					<?php
					$row_number = ( $current_page - 1 ) * $per_page;
					foreach ( $items as $item ) :
						++$row_number;

						$post_id   = isset( $item['post_id'] ) ? (int) $item['post_id'] : 0;
						$title     = isset( $item['title'] ) ? $item['title'] : '';
						$post_type = isset( $item['post_type'] ) ? $item['post_type'] : '';
						$score     = $item['score'];
						$status    = $item['status'];
						$last_scan = isset( $item['last_scan'] ) ? $item['last_scan'] : null;
						$audit_url = isset( $item['audit_url'] ) ? $item['audit_url'] : '';
						$edit_url  = isset( $item['edit_url'] ) ? $item['edit_url'] : '';

						// Fetch individual category scores.
						$scores_data = get_post_meta( $post_id, '_scalyn_qa_scores', true );
						$scores_data = is_array( $scores_data ) ? $scores_data : array();

						$seo_score  = isset( $scores_data['seo'] ) ? (int) $scores_data['seo'] : null;
						$cont_score = isset( $scores_data['content'] ) ? (int) $scores_data['content'] : null;
						$func_score = isset( $scores_data['functionality'] ) ? (int) $scores_data['functionality'] : null;

						// Post type display name.
						$type_obj  = get_post_type_object( $post_type );
						$type_name = $type_obj ? $type_obj->labels->singular_name : $post_type;

						// Human-readable scan time.
						$scan_time = '';
						if ( ! empty( $last_scan ) ) {
							$scan_timestamp = strtotime( $last_scan );
							if ( false !== $scan_timestamp ) {
								$scan_time = sprintf(
									/* translators: %s: Human-readable time difference. */
									esc_html__( '%s ago', 'scalyn-qa-assistant' ),
									human_time_diff( $scan_timestamp, time() )
								);
							}
						}
						?>
						<tr data-post-id="<?php echo esc_attr( $post_id ); ?>">
							<td class="scalyn-table__col--narrow"><input type="checkbox" class="scalyn-select-page" value="<?php echo esc_attr( $post_id ); ?>"></td>
							<td class="scalyn-table__col--narrow"><?php echo esc_html( $row_number ); ?></td>
							<td>
								<a href="<?php echo esc_url( $audit_url ); ?>">
									<?php echo esc_html( $title ); ?>
								</a>
							</td>
							<td class="scalyn-table__col--narrow">
								<span class="scalyn-text-muted"><?php echo esc_html( $type_name ); ?></span>
							</td>
							<td class="scalyn-table__col--narrow">
								<?php if ( null !== $seo_score ) : ?>
									<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $seo_score ) ); ?>">
										<?php echo esc_html( $seo_score ); ?>
									</span>
								<?php else : ?>
									<span class="scalyn-text-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="scalyn-table__col--narrow">
								<?php if ( null !== $cont_score ) : ?>
									<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $cont_score ) ); ?>">
										<?php echo esc_html( $cont_score ); ?>
									</span>
								<?php else : ?>
									<span class="scalyn-text-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="scalyn-table__col--narrow">
								<?php if ( null !== $func_score ) : ?>
									<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $func_score ) ); ?>">
										<?php echo esc_html( $func_score ); ?>
									</span>
								<?php else : ?>
									<span class="scalyn-text-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="scalyn-table__col--narrow">
								<?php if ( null !== $score ) : ?>
									<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $status ); ?>">
										<?php echo esc_html( $score ); ?>
									</span>
								<?php else : ?>
									<span class="scalyn-text-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $scan_time ) ) : ?>
									<span title="<?php echo esc_attr( $last_scan ); ?>">
										<?php echo esc_html( $scan_time ); ?>
									</span>
								<?php else : ?>
									<span class="scalyn-text-muted"><?php esc_html_e( 'Never', 'scalyn-qa-assistant' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<div class="scalyn-action-group">
									<a href="<?php echo esc_url( $audit_url ); ?>" class="scalyn-btn scalyn-btn--small">
										<?php esc_html_e( 'View Audit', 'scalyn-qa-assistant' ); ?>
									</a>
									<button
										type="button"
										class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-rescan"
										data-post-id="<?php echo esc_attr( $post_id ); ?>"
									>
										<?php esc_html_e( 'Rescan', 'scalyn-qa-assistant' ); ?>
									</button>
									<?php $row_permalink = get_permalink( $post_id ); ?>
									<?php if ( $row_permalink ) : ?>
									<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $row_permalink ) ); ?>" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Test Speed', 'scalyn-qa-assistant' ); ?>">
										<span class="dashicons dashicons-performance" aria-hidden="true"></span>
									</a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		</div><!-- /.scalyn-table-wrap -->

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="scalyn-pagination">
				<span class="scalyn-pagination__info">
					<?php
					printf(
						/* translators: 1: Current page, 2: Total pages, 3: Total posts. */
						esc_html__( 'Page %1$d of %2$d (%3$d items)', 'scalyn-qa-assistant' ),
						$current_page,
						$total_pages,
						$total_posts
					);
					?>
				</span>

				<div class="scalyn-pagination__links">
					<?php
					// Build pagination URL parameters.
					$url_params = array();
					if ( ! empty( $current_status ) ) {
						$url_params['status'] = $current_status;
					}
					if ( ! empty( $current_type ) ) {
						$url_params['post_type'] = $current_type;
					}

					// Previous page.
					if ( $current_page > 1 ) :
						$prev_url = add_query_arg(
							array_merge( $url_params, array( 'paged' => $current_page - 1 ) ),
							$base_url
						);
						?>
						<a href="<?php echo esc_url( $prev_url ); ?>" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary">
							<?php esc_html_e( '&laquo; Previous', 'scalyn-qa-assistant' ); ?>
						</a>
					<?php endif; ?>

					<?php
					// Page numbers.
					$range = 2;
					$start = max( 1, $current_page - $range );
					$end   = min( $total_pages, $current_page + $range );

					if ( $start > 1 ) :
						$first_url = add_query_arg(
							array_merge( $url_params, array( 'paged' => 1 ) ),
							$base_url
						);
						?>
						<a href="<?php echo esc_url( $first_url ); ?>" class="scalyn-pagination__page">1</a>
						<?php if ( $start > 2 ) : ?>
							<span class="scalyn-pagination__dots">&hellip;</span>
						<?php endif; ?>
					<?php endif; ?>

					<?php for ( $i = $start; $i <= $end; $i++ ) : ?>
						<?php if ( $i === $current_page ) : ?>
							<span class="scalyn-pagination__page scalyn-pagination__page--current"><?php echo esc_html( $i ); ?></span>
						<?php else :
							$page_url = add_query_arg(
								array_merge( $url_params, array( 'paged' => $i ) ),
								$base_url
							);
							?>
							<a href="<?php echo esc_url( $page_url ); ?>" class="scalyn-pagination__page"><?php echo esc_html( $i ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>

					<?php if ( $end < $total_pages ) : ?>
						<?php if ( $end < $total_pages - 1 ) : ?>
							<span class="scalyn-pagination__dots">&hellip;</span>
						<?php endif; ?>
						<?php
						$last_url = add_query_arg(
							array_merge( $url_params, array( 'paged' => $total_pages ) ),
							$base_url
						);
						?>
						<a href="<?php echo esc_url( $last_url ); ?>" class="scalyn-pagination__page"><?php echo esc_html( $total_pages ); ?></a>
					<?php endif; ?>

					<?php
					// Next page.
					if ( $current_page < $total_pages ) :
						$next_url = add_query_arg(
							array_merge( $url_params, array( 'paged' => $current_page + 1 ) ),
							$base_url
						);
						?>
						<a href="<?php echo esc_url( $next_url ); ?>" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary">
							<?php esc_html_e( 'Next &raquo;', 'scalyn-qa-assistant' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Legend -->
	<div class="scalyn-legend">
		<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( '80-100 Passed', 'scalyn-qa-assistant' ); ?></span>
		<span class="scalyn-badge scalyn-badge--yellow"><?php esc_html_e( '50-79 Review', 'scalyn-qa-assistant' ); ?></span>
		<span class="scalyn-badge scalyn-badge--red"><?php esc_html_e( '0-49 Issues', 'scalyn-qa-assistant' ); ?></span>
	</div>
</div>
