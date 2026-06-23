<?php
/**
 * Template: Dashboard Overview.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$project_scores          = isset( $project_scores ) && is_array( $project_scores ) ? $project_scores : array();
$pages_needing_attention = isset( $pages_needing_attention ) && is_array( $pages_needing_attention ) ? $pages_needing_attention : array();
$recent_scans            = isset( $recent_scans ) && is_array( $recent_scans ) ? $recent_scans : array();
$seo_plugin_status       = isset( $seo_plugin_status ) ? $seo_plugin_status : null;
$launch_summary          = isset( $launch_summary ) && is_array( $launch_summary ) ? $launch_summary : array();
$top_issues              = isset( $top_issues ) && is_array( $top_issues ) ? $top_issues : array();
$scan_coverage           = isset( $scan_coverage ) && is_array( $scan_coverage ) ? $scan_coverage : array();
$ai_status               = isset( $ai_status ) && is_array( $ai_status ) ? $ai_status : array();

$seo_ready    = (int) ( $project_scores['seo_ready'] ?? 0 );
$qa_ready     = (int) ( $project_scores['qa_ready'] ?? 0 );
$launch_ready = (int) ( $project_scores['launch_ready'] ?? 0 );
$overall      = (int) ( $project_scores['overall'] ?? 0 );

$launch_pass      = (int) ( $launch_summary['pass'] ?? 0 );
$launch_fail      = (int) ( $launch_summary['fail'] ?? 0 );
$launch_warning   = (int) ( $launch_summary['warning'] ?? 0 );
$launch_total     = (int) ( $launch_summary['total'] ?? 0 );
$launch_last_scan = $launch_summary['last_scan'] ?? null;

$scanned_count = (int) ( $scan_coverage['scanned'] ?? 0 );
$total_pages   = (int) ( $scan_coverage['total'] ?? 0 );

$ai_enabled  = ! empty( $ai_status['enabled'] );
$ai_provider = $ai_status['provider'] ?? '';
$ai_health   = $ai_status['status'] ?? 'not_configured';

$onboarding = isset( $onboarding ) && is_array( $onboarding ) ? $onboarding : array();

// Overall status
$overall_status = \Scalyn\QA\Models\Score::calculate_status( $overall );
$overall_label  = match ( $overall_status ) {
	'green'  => __( 'Looking Good', 'scalyn-qa-assistant' ),
	'yellow' => __( 'Needs Attention', 'scalyn-qa-assistant' ),
	'red'    => __( 'Action Required', 'scalyn-qa-assistant' ),
	default  => '',
};
?>
<div class="scalyn-wrap">
	<!-- Header -->
	<div class="scalyn-branded-header">
		<div class="scalyn-branded-header__left">
			<span class="scalyn-branded-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5.5 10c0-1.4 1-2.5 2.2-2.5.8 0 1.5.4 2 1l.3.4.3-.4c.5-.6 1.2-1 2-1C13.5 7.5 14.5 8.6 14.5 10s-1 2.5-2.2 2.5c-.8 0-1.5-.4-2-1l-.3-.4-.3.4c-.5.6-1.2 1-2 1C6.5 12.5 5.5 11.4 5.5 10zM3 10c0 2.6 2 4.7 4.7 4.7 1.3 0 2.5-.6 3.3-1.5.8.9 2 1.5 3.3 1.5C16.9 14.7 19 12.6 19 10s-2-4.7-4.7-4.7c-1.3 0-2.5.6-3.3 1.5-.8-.9-2-1.5-3.3-1.5C5.1 5.3 3 7.4 3 10z"/></svg>
			</span>
			<div class="scalyn-branded-header__text">
				<h1 class="scalyn-branded-header__title"><?php esc_html_e( 'Scalyn QA', 'scalyn-qa-assistant' ); ?></h1>
				<p class="scalyn-branded-header__description"><?php esc_html_e( 'Website QA & SEO', 'scalyn-qa-assistant' ); ?></p>
			</div>
		</div>
		<span class="scalyn-version"><?php echo esc_html( 'v' . SCALYN_QA_VERSION ); ?></span>
	</div>

	<?php if ( ! empty( $onboarding ) ) : ?>
		<?php include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/getting-started.php'; ?>
	<?php endif; ?>

	<!-- Hero: Overall Score + Category Scores + Scan All -->
	<div class="scalyn-dashboard-hero">
		<div class="scalyn-dashboard-hero__main">
			<div class="scalyn-score-circle scalyn-score-circle--large scalyn-score-circle--<?php echo esc_attr( $overall_status ); ?>"
				 style="--scalyn-score: <?php echo esc_attr( (string) $overall ); ?>">
				<span class="scalyn-score-circle__value"><?php echo esc_html( (string) $overall ); ?><span class="scalyn-score-circle__unit">%</span></span>
			</div>
			<div class="scalyn-dashboard-hero__meta">
				<span class="scalyn-dashboard-hero__label"><?php esc_html_e( 'Overall Score', 'scalyn-qa-assistant' ); ?></span>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $overall_status ); ?>"><?php echo esc_html( $overall_label ); ?></span>
				<span class="scalyn-dashboard-hero__formula"><?php esc_html_e( 'SEO 35% + QA 35% + Launch 30%', 'scalyn-qa-assistant' ); ?></span>
				<div class="scalyn-dashboard-hero__actions">
					<button type="button" id="scalyn-scan-all-pages" class="scalyn-btn scalyn-btn--small">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php
						printf(
							/* translators: 1: scanned count, 2: total count */
							esc_html__( 'Scan All Pages (%1$d/%2$d)', 'scalyn-qa-assistant' ),
							$scanned_count,
							$total_pages,
						);
						?>
					</button>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=scalyn_qa_generate_report' ), 'scalyn_qa_report' ) ); ?>" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary" target="_blank">
						<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
						<?php esc_html_e( 'Generate Report', 'scalyn-qa-assistant' ); ?>
					</a>
				</div>
			</div>
		</div>
		<div class="scalyn-dashboard-hero__categories">
			<?php
			$categories = array(
				array(
					'label' => __( 'SEO', 'scalyn-qa-assistant' ),
					'score' => $seo_ready,
					'icon'  => 'dashicons-search',
					'desc'  => __( 'Average SEO score across all scanned pages', 'scalyn-qa-assistant' ),
				),
				array(
					'label' => __( 'QA', 'scalyn-qa-assistant' ),
					'score' => $qa_ready,
					'icon'  => 'dashicons-yes-alt',
					'desc'  => __( 'Average content & functionality score across all scanned pages', 'scalyn-qa-assistant' ),
				),
				array(
					'label' => __( 'Launch', 'scalyn-qa-assistant' ),
					'score' => $launch_ready,
					'icon'  => 'dashicons-migrate',
					'desc'  => __( 'Launch checklist readiness score', 'scalyn-qa-assistant' ),
				),
			);
			foreach ( $categories as $cat ) :
				$cat_status = \Scalyn\QA\Models\Score::calculate_status( $cat['score'] );
			?>
				<div class="scalyn-category-score" title="<?php echo esc_attr( $cat['desc'] ); ?>">
					<div class="scalyn-category-score__header">
						<span class="dashicons <?php echo esc_attr( $cat['icon'] ); ?>" aria-hidden="true"></span>
						<span class="scalyn-category-score__label"><?php echo esc_html( $cat['label'] ); ?></span>
					</div>
					<div class="scalyn-category-score__bar">
						<div class="scalyn-category-score__fill scalyn-category-score__fill--<?php echo esc_attr( $cat_status ); ?>" style="width:<?php echo esc_attr( (string) $cat['score'] ); ?>%"></div>
					</div>
					<span class="scalyn-category-score__value"><?php echo esc_html( (string) $cat['score'] ); ?>%</span>
					<span class="scalyn-category-score__desc"><?php echo esc_html( $cat['desc'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Three Column: Site Status + Top Issues + Quick Actions -->
	<div class="scalyn-grid scalyn-grid--3">
		<!-- Site Status -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Site Status', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-kpi-list">
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo null !== $seo_plugin_status ? 'success' : 'danger'; ?>">
						<span class="dashicons <?php echo null !== $seo_plugin_status ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo null !== $seo_plugin_status ? esc_html( $seo_plugin_status ) : esc_html__( 'Not Installed', 'scalyn-qa-assistant' ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'SEO Plugin', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo $launch_fail > 0 ? 'danger' : ( $launch_warning > 0 ? 'warning' : 'success' ); ?>">
						<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( $launch_pass . '/' . $launch_total ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'Launch Checks Passed', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<?php
					$ai_icon_status = 'neutral';
					$ai_icon        = 'dashicons-admin-generic';
					$ai_display     = __( 'Not Configured', 'scalyn-qa-assistant' );
					if ( $ai_enabled && '' !== $ai_provider ) {
						$ai_display     = $ai_provider;
						$ai_icon        = 'dashicons-admin-customizer';
						$ai_icon_status = 'healthy' === $ai_health ? 'success' : ( 'degraded' === $ai_health ? 'warning' : 'primary' );
					}
					?>
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo esc_attr( $ai_icon_status ); ?>">
						<span class="dashicons <?php echo esc_attr( $ai_icon ); ?>" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( $ai_display ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'AI Provider', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<?php
					$coverage_pct    = $total_pages > 0 ? round( ( $scanned_count / $total_pages ) * 100 ) : 0;
					$coverage_status = $coverage_pct >= 80 ? 'success' : ( $coverage_pct >= 50 ? 'warning' : 'danger' );
					?>
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo esc_attr( $coverage_status ); ?>">
						<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( $scanned_count . '/' . $total_pages ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'Pages Scanned', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Top Issues -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Top Issues', 'scalyn-qa-assistant' ); ?></h2>
			<?php if ( empty( $top_issues ) ) : ?>
				<p class="scalyn-empty"><?php esc_html_e( 'No issues found. Your site is in great shape!', 'scalyn-qa-assistant' ); ?></p>
			<?php else : ?>
				<div class="scalyn-issue-list">
					<?php foreach ( $top_issues as $issue ) :
						$issue_status = $issue['count'] >= 5 ? 'danger' : ( $issue['count'] >= 2 ? 'warning' : 'neutral' );
					?>
						<div class="scalyn-issue-row">
							<div class="scalyn-issue-row__icon scalyn-kpi__icon--<?php echo esc_attr( $issue_status ); ?>">
								<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $issue_status === 'danger' ? 'red' : ( $issue_status === 'warning' ? 'yellow' : 'green' ) ); ?>"><?php echo esc_html( (string) $issue['count'] ); ?></span>
							</div>
							<div class="scalyn-issue-row__content">
								<span class="scalyn-issue-row__label"><?php echo esc_html( $issue['label'] ); ?></span>
								<span class="scalyn-issue-row__meta"><?php
									printf(
										/* translators: 1: count, 2: category */
										esc_html__( '%1$d pages affected | %2$s', 'scalyn-qa-assistant' ),
										$issue['count'],
										ucfirst( $issue['category'] ),
									);
								?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Quick Actions -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Quick Actions', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-actions-list">
				<?php
				// Contextual actions based on actual issues.
				$contextual_actions = array();

				foreach ( $top_issues as $issue ) {
					if ( count( $contextual_actions ) >= 3 ) {
						break;
					}
					$contextual_actions[] = array(
						'icon'  => 'dashicons-admin-tools',
						'label' => sprintf(
							/* translators: 1: count, 2: issue label */
							__( 'Fix %1$d pages: %2$s', 'scalyn-qa-assistant' ),
							$issue['count'],
							$issue['label'],
						),
						'url'   => admin_url( 'admin.php?page=scalyn-qa-audits' ),
					);
				}

				if ( empty( $contextual_actions ) ) :
				?>
					<div class="scalyn-empty" style="padding:0.5rem 0;">
						<span class="dashicons dashicons-yes-alt" style="color:var(--scalyn-success);margin-right:4px;" aria-hidden="true"></span>
						<?php esc_html_e( 'No critical issues to fix!', 'scalyn-qa-assistant' ); ?>
					</div>
				<?php
				else :
					foreach ( $contextual_actions as $action ) :
				?>
					<a href="<?php echo esc_url( $action['url'] ); ?>" class="scalyn-action-link">
						<span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $action['label'] ); ?></span>
					</a>
				<?php
					endforeach;
				endif;
				?>

				<hr style="border:none;border-top:1px solid var(--scalyn-border-light);margin:0.5rem 0;">

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-audits' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span><?php esc_html_e( 'View All Page Audits', 'scalyn-qa-assistant' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-launch' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Run Launch Checklist', 'scalyn-qa-assistant' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-settings&tab=ai-providers' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Configure AI Providers', 'scalyn-qa-assistant' ); ?></span>
				</a>
			</div>
		</div>
	</div>

	<!-- Two Column: Pages Needing Attention + Recent Scans -->
	<div class="scalyn-grid scalyn-grid--2">
		<?php
		$pages = $pages_needing_attention;
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/pages-attention.php';
		?>
		<?php
		$scans = $recent_scans;
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/recent-scans.php';
		?>
	</div>
</div>
