<?php
/**
 * Template: Launch Checklist Page.
 *
 * Renders the website launch readiness checklist with grouped checks and summary.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var \Scalyn\QA\Models\Check_Item[] $results   Array of Check_Item objects from launch scan.
 * @var array                          $counts    Array with pass, fail, warning, total keys.
 * @var int|null                       $last_scan Unix timestamp of last scan or null.
 * @var int                            $score     Launch readiness score (0-100).
 */

defined( 'ABSPATH' ) || exit;

$results          = isset( $results ) ? $results : array();
$counts           = isset( $counts ) ? $counts : array( 'pass' => 0, 'fail' => 0, 'warning' => 0, 'total' => 0 );
$last_scan        = isset( $last_scan ) ? $last_scan : null;
$score            = isset( $score ) ? (int) $score : 0;
$overall_score    = $score;
$category_scores  = isset( $category_scores ) && is_array( $category_scores ) ? $category_scores : array();

// Determine alert status.
if ( $score >= 80 ) {
	$alert_class  = 'scalyn-alert--green';
	$alert_label  = __( 'Launch Ready', 'scalyn-qa-assistant' );
} elseif ( $score >= 50 ) {
	$alert_class  = 'scalyn-alert--yellow';
	$alert_label  = __( 'Needs Review', 'scalyn-qa-assistant' );
} else {
	$alert_class  = 'scalyn-alert--red';
	$alert_label  = __( 'Not Ready', 'scalyn-qa-assistant' );
}

// Group checks by their ID prefix for category assignment.
$category_map = array(
	// SEO.
	'search_engine_visibility' => 'seo',
	'seo_plugin_installed'     => 'seo',
	'sitemap_exists'           => 'seo',
	'robots_txt'               => 'seo',
	'permalink_structure'      => 'seo',
	'llms_txt'                 => 'seo',
	'breadcrumbs_enabled'      => 'seo',
	'redirect_manager'         => 'seo',
	'local_business_schema'    => 'seo',
	'four_oh_four_monitor'     => 'seo',
	'cornerstone_content'      => 'seo',
	'instant_indexing'         => 'seo',
	'woocommerce_seo'          => 'seo',
	// Analytics.
	'ga4_configured'           => 'analytics',
	'gtm_configured'           => 'analytics',
	// Technical.
	'ssl_enabled'              => 'technical',
	'debug_mode_disabled'      => 'technical',
	'wp_core_updates'          => 'technical',
	'plugin_updates'           => 'technical',
	'wp_address_match'         => 'technical',
	'favicon_exists'           => 'technical',
	'php_version'              => 'technical',
	'php_memory_limit'         => 'technical',
	'php_max_execution_time'   => 'technical',
	'php_max_input_time'       => 'technical',
	'php_post_max_size'        => 'technical',
	'php_upload_max_size'      => 'technical',
	// Content.
	'contact_page_exists'      => 'content',
	'privacy_policy_exists'    => 'content',
	'default_content_cleanup'  => 'content',
	'default_tagline'          => 'content',
	'empty_pages'              => 'content',
	'four_oh_four_page'        => 'content',
	'menu_exists'              => 'content',
	// Plugin health.
	'default_plugins_cleanup'  => 'plugin_health',
	'plugin_conflicts'         => 'plugin_health',
	'security_plugin'          => 'plugin_health',
	'cache_plugin'             => 'plugin_health',
	'backup_plugin'            => 'plugin_health',
	'smtp_plugin'              => 'plugin_health',
	'image_optimization_plugin' => 'plugin_health',
	// Settings.
	'admin_username'           => 'settings',
	'timezone_set'             => 'settings',
	'comments_open'            => 'settings',
);

$category_labels = array(
	'seo'           => __( 'SEO Configuration', 'scalyn-qa-assistant' ),
	'analytics'     => __( 'Analytics', 'scalyn-qa-assistant' ),
	'technical'     => __( 'Technical', 'scalyn-qa-assistant' ),
	'content'       => __( 'Content', 'scalyn-qa-assistant' ),
	'plugin_health' => __( 'Plugin Health', 'scalyn-qa-assistant' ),
	'settings'      => __( 'WordPress Settings', 'scalyn-qa-assistant' ),
);

// Auto-fixable check IDs.
$auto_fixable = \Scalyn\QA\Launch\Launch_Checker::get_auto_fixable();

// Check if AI is configured.
$ai_manager    = new \Scalyn\QA\AI\AI_Manager();
$ai_configured = $ai_manager->is_enabled() && null !== $ai_manager->get_primary_provider();

// Load persisted AI-generated content.
$ai_content = get_option( 'scalyn_qa_launch_ai_content', array() );
$ai_content = is_array( $ai_content ) ? $ai_content : array();

// Load launch-scoped ignore rules.
$launch_ignores = \Scalyn\QA\Models\Ignore_Rule::get_by_context( 'launch' );
$ignored_ids    = array();
foreach ( $launch_ignores as $rule ) {
	$ignored_ids[ $rule->check_id ] = $rule;
}

// Sort results into categories.
$grouped = array(
	'seo'           => array(),
	'analytics'     => array(),
	'technical'     => array(),
	'content'       => array(),
	'plugin_health' => array(),
	'settings'      => array(),
);

$grouped_ignored = array(
	'seo'           => array(),
	'analytics'     => array(),
	'technical'     => array(),
	'content'       => array(),
	'plugin_health' => array(),
	'settings'      => array(),
);

foreach ( $results as $check ) {
	$check_id = $check->id;
	$group    = isset( $category_map[ $check_id ] ) ? $category_map[ $check_id ] : 'technical';

	if ( isset( $ignored_ids[ $check_id ] ) ) {
		$grouped_ignored[ $group ][] = $check;
	} else {
		$grouped[ $group ][] = $check;
	}
}

// Format the last scan time.
if ( null !== $last_scan && $last_scan > 0 ) {
	$time_diff = human_time_diff( $last_scan, time() );
	/* translators: %s: Human-readable time difference. */
	$last_scan_text = sprintf( __( '%s ago', 'scalyn-qa-assistant' ), $time_diff );
} else {
	$last_scan_text = __( 'Never', 'scalyn-qa-assistant' );
}

// Count auto-fixable failing checks.
$category_counts  = isset( $category_counts ) && is_array( $category_counts ) ? $category_counts : array();
$fixable_failing = 0;
$module_toggle_checks_list = array( 'breadcrumbs_enabled', 'redirect_manager', 'four_oh_four_monitor', 'instant_indexing' );
foreach ( $results as $check ) {
	if ( isset( $ignored_ids[ $check->id ] ) || 'pass' === $check->status ) {
		continue;
	}
	$is_module = in_array( $check->id, $module_toggle_checks_list, true );
	if ( isset( $auto_fixable[ $check->id ] ) && ( ! $is_module || 'auto_fix' === $check->quick_fix ) ) {
		++$fixable_failing;
	}
}
?>
<div class="scalyn-wrap">

	<!-- Header -->
	<div class="scalyn-page-header">
		<h1><?php esc_html_e( 'Website Launch Checklist', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<button type="button" id="scalyn-launch-scan" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Run Check', 'scalyn-qa-assistant' ); ?>
			</button>
			<?php if ( $fixable_failing > 0 ) : ?>
			<button type="button" id="scalyn-launch-auto-fix-all" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary">
				<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
				<?php printf( esc_html__( 'Auto Fix All (%d)', 'scalyn-qa-assistant' ), $fixable_failing ); ?>
			</button>
			<?php endif; ?>
			<?php if ( $ai_configured ) : ?>
			<button type="button" id="scalyn-launch-generate-ai" class="scalyn-btn scalyn-btn--small scalyn-btn--ai">
				<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
				<?php esc_html_e( 'Generate All with AI', 'scalyn-qa-assistant' ); ?>
			</button>
			<?php endif; ?>
		</div>
	</div>

	<p class="scalyn-page-header__meta" style="display:none;">
			<?php
			printf( esc_html__( 'Last checked: %s', 'scalyn-qa-assistant' ), esc_html( $last_scan_text ) );
			?>
			<?php if ( $counts['total'] > 0 ) : ?>
				<span class="scalyn-meta__sep">|</span>
				<?php esc_html_e( 'Score:', 'scalyn-qa-assistant' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $overall_score ) ); ?>">
					<?php echo esc_html( (string) $overall_score ); ?>
				</span>
				<span class="scalyn-meta__sep">|</span>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( str_replace( 'scalyn-alert--', '', $alert_class ) ); ?>">
					<?php echo esc_html( $alert_label ); ?>
				</span>
				<span class="scalyn-meta__sep">|</span>
				<?php
				printf(
					esc_html__( '%1$d/%2$d checks passed', 'scalyn-qa-assistant' ),
					(int) $counts['pass'],
					(int) $counts['total'],
				);
				?>
			<?php endif; ?>
		</p>

	<?php if ( $counts['total'] > 0 ) : ?>
		<!-- Hero: Overall Score + Category Bars -->
		<div class="scalyn-dashboard-hero">
			<div class="scalyn-dashboard-hero__main">
				<div class="scalyn-score-circle scalyn-score-circle--large scalyn-score-circle--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $overall_score ) ); ?>"
					 style="--scalyn-score: <?php echo esc_attr( (string) $overall_score ); ?>">
					<span class="scalyn-score-circle__value"><?php echo esc_html( (string) $overall_score ); ?><span class="scalyn-score-circle__unit">%</span></span>
				</div>
				<div class="scalyn-dashboard-hero__meta">
					<span class="scalyn-dashboard-hero__label"><?php esc_html_e( 'Launch Readiness', 'scalyn-qa-assistant' ); ?></span>
					<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( str_replace( 'scalyn-alert--', '', $alert_class ) ); ?>"><?php echo esc_html( $alert_label ); ?></span>
					<span class="scalyn-dashboard-hero__formula">
						<?php
						printf(
							esc_html__( '%1$d/%2$d checks passed | Last checked: %3$s', 'scalyn-qa-assistant' ),
							(int) $counts['pass'],
							(int) $counts['total'],
							esc_html( $last_scan_text ),
						);
						?>
					</span>
				</div>
			</div>
			<div class="scalyn-dashboard-hero__categories">
				<?php
				$hero_cats = array(
					'seo'           => array( 'label' => __( 'SEO', 'scalyn-qa-assistant' ),       'icon' => 'dashicons-search' ),
					'analytics'     => array( 'label' => __( 'Analytics', 'scalyn-qa-assistant' ), 'icon' => 'dashicons-chart-area' ),
					'technical'     => array( 'label' => __( 'Technical', 'scalyn-qa-assistant' ), 'icon' => 'dashicons-admin-tools' ),
					'content'       => array( 'label' => __( 'Content', 'scalyn-qa-assistant' ),   'icon' => 'dashicons-edit-page' ),
					'plugin_health' => array( 'label' => __( 'Plugins', 'scalyn-qa-assistant' ),   'icon' => 'dashicons-admin-plugins' ),
					'settings'      => array( 'label' => __( 'Settings', 'scalyn-qa-assistant' ),  'icon' => 'dashicons-admin-settings' ),
				);
				foreach ( $hero_cats as $hc_key => $hc ) :
					$hc_score  = $category_scores[ $hc_key ] ?? 0;
					$hc_status = \Scalyn\QA\Models\Score::calculate_status( $hc_score );
					$hc_c      = $category_counts[ $hc_key ] ?? array( 'pass' => 0, 'total' => 0 );
					$hc_desc   = sprintf( '%d/%d passed', (int) ( $hc_c['pass'] ?? 0 ), (int) ( $hc_c['total'] ?? 0 ) );
				?>
					<div class="scalyn-category-score" title="<?php echo esc_attr( $hc_desc ); ?>">
						<div class="scalyn-category-score__header">
							<span class="dashicons <?php echo esc_attr( $hc['icon'] ); ?>" aria-hidden="true"></span>
							<span class="scalyn-category-score__label"><?php echo esc_html( $hc['label'] ); ?></span>
						</div>
						<div class="scalyn-category-score__bar">
							<div class="scalyn-category-score__fill scalyn-category-score__fill--<?php echo esc_attr( $hc_status ); ?>" style="width:<?php echo esc_attr( (string) $hc_score ); ?>%"></div>
						</div>
						<span class="scalyn-category-score__value"><?php echo esc_html( (string) $hc_score ); ?>%</span>
						<span class="scalyn-category-score__desc"><?php echo esc_html( $hc_desc ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php else : ?>
		<!-- Empty State -->
		<div class="scalyn-card" style="text-align:center;padding:3rem 2rem;">
			<span class="dashicons dashicons-migrate" style="font-size:48px;width:48px;height:48px;color:var(--scalyn-text-faint);margin-bottom:1rem;" aria-hidden="true"></span>
			<h2 style="margin:0 0 0.5rem;font-size:1.25rem;color:var(--scalyn-text-strong);"><?php esc_html_e( 'Ready to check your site?', 'scalyn-qa-assistant' ); ?></h2>
			<p style="margin:0 0 1.5rem;color:var(--scalyn-text-muted);max-width:400px;margin-left:auto;margin-right:auto;">
				<?php esc_html_e( 'Run the launch checklist to scan your website for SEO, security, performance, and content issues before going live.', 'scalyn-qa-assistant' ); ?>
			</p>
			<button type="button" id="scalyn-launch-scan-empty" class="scalyn-btn">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Run Launch Check', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<!-- Check Items grouped by category -->
	<?php foreach ( $grouped as $group_key => $group_checks ) : ?>
		<?php if ( empty( $group_checks ) && $counts['total'] === 0 ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<?php
		$grp_score  = $category_scores[ $group_key ] ?? 0;
		$grp_status = \Scalyn\QA\Models\Score::calculate_status( $grp_score );
		$grp_c      = $category_counts[ $group_key ] ?? array( 'pass' => 0, 'fail' => 0, 'warning' => 0, 'total' => 0 );
		$grp_pass   = (int) ( $grp_c['pass'] ?? 0 );
		$grp_warn   = (int) ( $grp_c['warning'] ?? 0 );
		$grp_fail   = (int) ( $grp_c['fail'] ?? 0 );
		$grp_total  = (int) ( $grp_c['total'] ?? 0 );
		?>
		<div class="scalyn-card" id="scalyn-launch-<?php echo esc_attr( $group_key ); ?>">
			<div class="scalyn-launch-card-header">
				<h2 class="scalyn-card-title" style="margin:0;">
					<?php echo esc_html( $category_labels[ $group_key ] ); ?>
					<?php if ( $grp_total > 0 ) : ?>
						<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $grp_status ); ?>"><?php echo esc_html( (string) $grp_score ); ?></span>
					<?php endif; ?>
				</h2>
				<?php if ( $grp_total > 0 ) : ?>
				<div class="scalyn-launch-card-progress">
					<div class="scalyn-launch-card-progress__bar">
						<?php if ( $grp_pass > 0 ) : ?>
						<div class="scalyn-launch-card-progress__fill scalyn-launch-card-progress__fill--pass" style="width:<?php echo esc_attr( (string) round( $grp_pass / $grp_total * 100 ) ); ?>%"></div>
						<?php endif; ?>
						<?php if ( $grp_warn > 0 ) : ?>
						<div class="scalyn-launch-card-progress__fill scalyn-launch-card-progress__fill--warning" style="width:<?php echo esc_attr( (string) round( $grp_warn / $grp_total * 100 ) ); ?>%"></div>
						<?php endif; ?>
						<?php if ( $grp_fail > 0 ) : ?>
						<div class="scalyn-launch-card-progress__fill scalyn-launch-card-progress__fill--fail" style="width:<?php echo esc_attr( (string) round( $grp_fail / $grp_total * 100 ) ); ?>%"></div>
						<?php endif; ?>
					</div>
					<span class="scalyn-launch-card-progress__label">
						<?php printf( esc_html__( '%1$d passed, %2$d warning, %3$d failed', 'scalyn-qa-assistant' ), $grp_pass, $grp_warn, $grp_fail ); ?>
					</span>
				</div>
				<?php endif; ?>
			</div>

			<div class="scalyn-check-list">
				<?php if ( empty( $group_checks ) && empty( $grouped_ignored[ $group_key ] ) ) : ?>
					<p class="scalyn-card__empty">
						<?php esc_html_e( 'No checks in this category yet. Run a scan to populate results.', 'scalyn-qa-assistant' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $group_checks as $check ) : ?>
						<?php
						$item    = $check->to_array();
						$post_id = 0;
						$c_status = isset( $item['status'] ) ? $item['status'] : 'fail';
						$accent  = ( 'warning' === $c_status || 'fail' === $c_status ) ? ' scalyn-check-item--accent' : '';
						?>
						<div class="scalyn-check-item scalyn-check-item--<?php echo esc_attr( $c_status ); ?><?php echo esc_attr( $accent ); ?>"
							data-check-id="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>"
							data-status="<?php echo esc_attr( $c_status ); ?>"
							data-severity="<?php echo esc_attr( isset( $item['severity'] ) ? $item['severity'] : 'info' ); ?>"
						>
							<?php
							$status_icons = array(
								'pass'    => 'dashicons-yes-alt',
								'warning' => 'dashicons-warning',
								'fail'    => 'dashicons-dismiss',
							);
							$icon_class = isset( $status_icons[ $c_status ] ) ? $status_icons[ $c_status ] : 'dashicons-marker';
							?>
							<span class="scalyn-check-icon" aria-hidden="true">
								<span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
							</span>

							<div class="scalyn-check-content">
								<strong class="scalyn-check-label"><?php echo esc_html( isset( $item['label'] ) ? $item['label'] : '' ); ?></strong>
								<?php if ( ! empty( $item['message'] ) ) : ?>
									<span class="scalyn-check-message"><?php echo esc_html( $item['message'] ); ?></span>
								<?php endif; ?>
							</div>

							<div class="scalyn-check-actions">
								<?php if ( ! empty( $item['quick_fix'] ) ) : ?>
									<?php
									$action = $item['quick_fix'];
									include SCALYN_QA_PLUGIN_DIR . 'templates/partials/quick-fix-button.php';
									?>
								<?php endif; ?>

								<?php $check_id = $item['id'] ?? ''; ?>

								<?php if ( 'llms_txt' === $check_id ) : ?>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-llms-txt-editor"
									data-check-id="llms_txt"
								>
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<?php echo 'pass' === $c_status ? esc_html__( 'Edit', 'scalyn-qa-assistant' ) : esc_html__( 'Generate', 'scalyn-qa-assistant' ); ?>
								</button>

								<?php elseif ( 'local_business_schema' === $check_id ) : ?>
								<?php
								$lb_managed_by = '';
								$lb_edit_url   = '';
								if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
									$lb_managed_by = 'Rank Math Pro';
									$lb_edit_url   = admin_url( 'admin.php?page=rank-math-options-titles#setting-panel-local' );
								} elseif ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
									$lb_managed_by = 'SEOPress Pro';
									$lb_edit_url   = admin_url( 'admin.php?page=seopress-pro-page#tab=tab_seopress_local_business' );
								}
								?>
								<?php if ( '' !== $lb_edit_url ) : ?>
								<a
									href="<?php echo esc_url( $lb_edit_url ); ?>"
									class="scalyn-btn scalyn-btn--small scalyn-btn--secondary"
									title="<?php echo esc_attr( sprintf( __( 'Manage in %s', 'scalyn-qa-assistant' ), $lb_managed_by ) ); ?>"
								>
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<?php echo esc_html( sprintf( __( 'Edit in %s', 'scalyn-qa-assistant' ), $lb_managed_by ) ); ?>
								</a>
								<?php endif; ?>

								<?php endif; ?>

								<?php if ( 'favicon_exists' === $check_id && $ai_configured && empty( get_option( 'scalyn_qa_ai_favicons', array() ) ) ) : ?>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--small scalyn-btn--ai scalyn-generate-favicon"
									data-check-id="favicon_exists"
								>
									<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
									<?php esc_html_e( 'Generate with AI', 'scalyn-qa-assistant' ); ?>
								</button>
								<?php endif; ?>

								<?php
								// Show Auto Fix button if:
								// 1. Check is not passing AND
								// 2. Either the check is in AUTO_FIXABLE without needing quick_fix (simple fixes like comments, tagline)
								//    OR the check returned quick_fix = 'auto_fix' (conditional fixes like breadcrumbs, 404 monitor)
								$module_toggle_checks = array( 'breadcrumbs_enabled', 'redirect_manager', 'four_oh_four_monitor', 'instant_indexing' );
								$is_module_toggle     = in_array( $check_id, $module_toggle_checks, true );
								$show_auto_fix        = 'pass' !== $c_status && isset( $auto_fixable[ $check_id ] )
									&& ( ! $is_module_toggle || ( $item['quick_fix'] ?? '' ) === 'auto_fix' );
								?>
								<?php if ( $show_auto_fix ) : ?>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-launch-auto-fix"
									data-check-id="<?php echo esc_attr( $check_id ); ?>"
									title="<?php echo esc_attr( $auto_fixable[ $check_id ] ); ?>"
								>
									<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
									<?php esc_html_e( 'Auto Fix', 'scalyn-qa-assistant' ); ?>
								</button>
								<?php endif; ?>

								<?php if ( ! empty( $item['tooltip'] ) ) : ?>
									<?php
									$text = $item['tooltip'];
									include SCALYN_QA_PLUGIN_DIR . 'templates/partials/tooltip.php';
									?>
								<?php endif; ?>

								<?php if ( 'pass' !== $c_status ) : ?>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-ignore-check"
									data-check-id="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>"
									data-post-id="0"
									title="<?php esc_attr_e( 'Ignore this check', 'scalyn-qa-assistant' ); ?>"
								>
									<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
								</button>
								<?php endif; ?>
							</div>

							<?php
							// Inline AI favicon panel — load live from wp_options (not stored scan results).
							if ( 'favicon_exists' === $check_id ) :
								$fav_history     = get_option( 'scalyn_qa_ai_favicons', array() );
								$fav_history     = is_array( $fav_history ) ? $fav_history : array();
								$current_icon_id = (int) get_option( 'site_icon', 0 );
								$current_icon_url = get_site_icon_url();

								$ai_favicons = array();
								foreach ( $fav_history as $att_id ) {
									$att_id = (int) $att_id;
									$url    = wp_get_attachment_url( $att_id );
									if ( $url ) {
										$ai_favicons[] = array(
											'attachment_id' => $att_id,
											'url'           => $url,
											'filename'      => basename( get_attached_file( $att_id ) ?: '' ),
											'is_active'     => $att_id === $current_icon_id,
										);
									}
								}

								if ( ! empty( $ai_favicons ) ) :
							?>
							<div class="scalyn-ai-featured-image-results scalyn-favicon-preview" data-check-id="favicon_exists">
								<div class="scalyn-ai-inline-result">
									<div class="scalyn-ai-inline-result__content">
										<span class="scalyn-ai-inline-result__label"><?php esc_html_e( 'AI Generated Favicons', 'scalyn-qa-assistant' ); ?></span>
										<div class="scalyn-fi-grid">
											<?php foreach ( $ai_favicons as $fi_idx => $fav ) :
												$is_active   = $fav['is_active'];
												$is_selected = $is_active || ( ! $current_icon_id && 0 === $fi_idx );
											?>
											<label class="scalyn-fi-option<?php echo $is_selected ? ' selected' : ''; ?>">
												<img src="<?php echo esc_url( $fav['url'] ); ?>" alt="<?php echo esc_attr( $fav['filename'] ); ?>" />
												<div class="scalyn-fi-option-footer">
													<input type="radio" name="scalyn-favicon-choice" value="<?php echo esc_attr( (string) $fav['attachment_id'] ); ?>" <?php checked( $is_selected ); ?> class="scalyn-favicon-radio">
													<span><?php echo esc_html( $fav['filename'] ); ?></span>
													<?php if ( $is_active ) : ?>
														<span style="color:var(--scalyn-success);font-size:0.6875rem;margin-left:auto;"><?php esc_html_e( '(active)', 'scalyn-qa-assistant' ); ?></span>
													<?php endif; ?>
												</div>
											</label>
											<?php endforeach; ?>
										</div>
										<span class="scalyn-ai-inline-result__meta"></span>
									</div>
									<div class="scalyn-ai-inline-result__actions">
										<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-favicon-apply-selected" data-current="<?php echo esc_attr( (string) $current_icon_id ); ?>">
											<span class="dashicons dashicons-yes" aria-hidden="true"></span>
											<?php esc_html_e( 'Apply', 'scalyn-qa-assistant' ); ?>
										</button>
										<?php if ( $ai_configured ) : ?>
										<button type="button" class="scalyn-btn scalyn-btn--small scalyn-generate-favicon" data-check-id="favicon_exists">
											<span class="dashicons dashicons-update" aria-hidden="true"></span>
											<?php esc_html_e( 'Regenerate', 'scalyn-qa-assistant' ); ?>
										</button>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<?php
								endif;
							endif;
							?>

							<?php
							// Inline AI panel — visible as long as AI content exists for this check.
							$ai_map = array(
								'default_tagline'       => 'taglines',
								'privacy_policy_exists' => 'privacy_policy',
								'llms_txt'              => 'llms_txt',
								'cornerstone_content'   => 'cornerstone',
								'contact_page_exists'   => 'contact_page',
								'local_business_schema' => 'local_business',
							);
							if ( $ai_configured && isset( $ai_map[ $check_id ] ) && ! empty( $ai_content[ $ai_map[ $check_id ] ] ) ) :
								$ai_key  = $ai_map[ $check_id ];
								$ai_data = $ai_content[ $ai_key ];
								$ai_meta = '';
								if ( ! empty( $ai_content['provider'] ) ) {
									$ai_meta = sprintf( '%s / %s — %s',
										esc_html( $ai_content['provider'] ),
										esc_html( $ai_content['model'] ?? '' ),
										esc_html( $ai_content['generated_at'] ?? '' ),
									);
								}

								// For taglines: detect which one matches the current tagline.
								$current_tagline = '';
								if ( 'taglines' === $ai_key ) {
									$current_tagline = get_option( 'blogdescription', '' );
								}
							?>
							<div class="scalyn-ai-inline-result scalyn-launch-ai-panel" data-ai-key="<?php echo esc_attr( $ai_key ); ?>" data-check-id="<?php echo esc_attr( $check_id ); ?>">
								<div class="scalyn-ai-inline-result__content">
									<span class="scalyn-ai-inline-result__label"><?php esc_html_e( 'AI Suggestion:', 'scalyn-qa-assistant' ); ?></span>

									<?php if ( 'taglines' === $ai_key && is_array( $ai_data ) ) : ?>
										<?php foreach ( $ai_data as $i => $tagline ) :
											$is_active = ( '' !== $current_tagline && $current_tagline === $tagline );
										?>
										<label style="display:block;padding:6px 12px;margin:4px 0;border:1px solid <?php echo $is_active ? 'var(--scalyn-success)' : 'var(--scalyn-border-light)'; ?>;border-radius:6px;cursor:pointer;font-size:0.875rem;<?php echo $is_active ? 'background:var(--scalyn-success-light);' : ''; ?>">
											<input type="radio" name="scalyn-launch-ai-tagline" value="<?php echo esc_attr( $tagline ); ?>" <?php checked( $is_active || ( '' === $current_tagline && 0 === $i ) ); ?> style="margin-right:8px;">
											<?php echo esc_html( $tagline ); ?>
											<?php if ( $is_active ) : ?>
												<span style="color:var(--scalyn-success);font-size:0.75rem;margin-left:4px;"><?php esc_html_e( '(current)', 'scalyn-qa-assistant' ); ?></span>
											<?php endif; ?>
										</label>
										<?php endforeach; ?>

									<?php elseif ( 'cornerstone' === $ai_key && is_array( $ai_data ) ) : ?>
										<?php
										// Get currently marked cornerstone pages for comparison.
										global $wpdb;
										$current_cornerstone = array();
										// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$cs_rows = $wpdb->get_results(
											"SELECT p.post_title FROM {$wpdb->posts} p
											INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
											WHERE p.post_status = 'publish'
											AND ((pm.meta_key = '_yoast_wpseo_is_cornerstone' AND pm.meta_value = '1')
											OR (pm.meta_key = 'rank_math_pillar_content' AND pm.meta_value = 'on'))
											LIMIT 20",
										);
										foreach ( $cs_rows as $row ) {
											$current_cornerstone[] = mb_strtolower( $row->post_title );
										}
										?>
										<?php foreach ( $ai_data as $page_title ) :
											$is_marked = in_array( mb_strtolower( trim( $page_title ) ), $current_cornerstone, true );
										?>
										<label style="display:block;padding:6px 12px;margin:4px 0;border:1px solid <?php echo $is_marked ? 'var(--scalyn-success)' : 'var(--scalyn-border-light)'; ?>;border-radius:6px;cursor:pointer;font-size:0.875rem;<?php echo $is_marked ? 'background:var(--scalyn-success-light);' : ''; ?>">
											<input type="checkbox" name="scalyn-launch-ai-cornerstone[]" value="<?php echo esc_attr( $page_title ); ?>" <?php checked( true ); ?> style="margin-right:8px;">
											<?php echo esc_html( $page_title ); ?>
											<?php if ( $is_marked ) : ?>
												<span style="color:var(--scalyn-success);font-size:0.75rem;margin-left:4px;"><?php esc_html_e( '(already marked)', 'scalyn-qa-assistant' ); ?></span>
											<?php endif; ?>
										</label>
										<?php endforeach; ?>

									<?php elseif ( 'local_business' === $ai_key && is_array( $ai_data ) ) : ?>
										<?php
										$lb_ai = $ai_data;
										$lb_current = get_option( 'scalyn_qa_local_business_jsonld', array() );
										// Merge current values as defaults.
										if ( ! empty( $lb_current ) && is_array( $lb_current ) ) {
											$lb_ai = array_merge( array(
												'type'        => $lb_current['@type'] ?? '',
												'name'        => $lb_current['name'] ?? '',
												'description' => $lb_current['description'] ?? '',
												'phone'       => $lb_current['telephone'] ?? '',
												'email'       => $lb_current['email'] ?? '',
											), $lb_ai );
										}
										?>
										<div class="scalyn-lb-inline-form" style="display:grid;gap:0.5rem;margin:0.5rem 0;">
											<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;">
												<span style="min-width:80px;font-weight:600;"><?php esc_html_e( 'Type', 'scalyn-qa-assistant' ); ?></span>
												<input type="text" name="scalyn-lb-type" value="<?php echo esc_attr( $lb_ai['type'] ?? 'LocalBusiness' ); ?>" style="flex:1;padding:4px 8px;border:1px solid var(--scalyn-border-light);border-radius:4px;font-size:0.8125rem;">
											</label>
											<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;">
												<span style="min-width:80px;font-weight:600;"><?php esc_html_e( 'Name', 'scalyn-qa-assistant' ); ?></span>
												<input type="text" name="scalyn-lb-name" value="<?php echo esc_attr( $lb_ai['name'] ?? '' ); ?>" style="flex:1;padding:4px 8px;border:1px solid var(--scalyn-border-light);border-radius:4px;font-size:0.8125rem;">
											</label>
											<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;">
												<span style="min-width:80px;font-weight:600;"><?php esc_html_e( 'Description', 'scalyn-qa-assistant' ); ?></span>
												<input type="text" name="scalyn-lb-desc" value="<?php echo esc_attr( $lb_ai['description'] ?? '' ); ?>" style="flex:1;padding:4px 8px;border:1px solid var(--scalyn-border-light);border-radius:4px;font-size:0.8125rem;">
											</label>
											<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;">
												<span style="min-width:80px;font-weight:600;"><?php esc_html_e( 'Phone', 'scalyn-qa-assistant' ); ?></span>
												<input type="text" name="scalyn-lb-phone" value="<?php echo esc_attr( $lb_ai['phone'] ?? '' ); ?>" style="flex:1;padding:4px 8px;border:1px solid var(--scalyn-border-light);border-radius:4px;font-size:0.8125rem;">
											</label>
											<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;">
												<span style="min-width:80px;font-weight:600;"><?php esc_html_e( 'Email', 'scalyn-qa-assistant' ); ?></span>
												<input type="text" name="scalyn-lb-email" value="<?php echo esc_attr( $lb_ai['email'] ?? '' ); ?>" style="flex:1;padding:4px 8px;border:1px solid var(--scalyn-border-light);border-radius:4px;font-size:0.8125rem;">
											</label>
										</div>

									<?php elseif ( is_array( $ai_data ) ) : ?>
										<textarea class="scalyn-ai-inline-result__textarea" style="width:100%;height:180px;font-family:monospace;font-size:0.75rem;resize:vertical;padding:0.5rem;border:1px solid var(--scalyn-border-light);border-radius:4px;margin:0.5rem 0;"><?php echo esc_textarea( wp_json_encode( $ai_data, JSON_PRETTY_PRINT ) ); ?></textarea>

									<?php else : ?>
										<textarea class="scalyn-ai-inline-result__textarea" style="width:100%;height:180px;font-family:monospace;font-size:0.75rem;resize:vertical;padding:0.5rem;border:1px solid var(--scalyn-border-light);border-radius:4px;margin:0.5rem 0;"><?php echo esc_textarea( is_string( $ai_data ) ? $ai_data : '' ); ?></textarea>
									<?php endif; ?>

									<?php if ( $ai_meta ) : ?>
										<span class="scalyn-ai-inline-result__meta"><?php echo esc_html( $ai_meta ); ?></span>
									<?php endif; ?>
								</div>
								<div class="scalyn-ai-inline-result__actions">
									<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-launch-ai-apply" data-ai-key="<?php echo esc_attr( $ai_key ); ?>" data-check-id="<?php echo esc_attr( $check_id ); ?>" title="<?php esc_attr_e( 'Apply', 'scalyn-qa-assistant' ); ?>">
										<span class="dashicons dashicons-yes" aria-hidden="true"></span>
										<?php esc_html_e( 'Apply', 'scalyn-qa-assistant' ); ?>
									</button>
									<button type="button" class="scalyn-btn scalyn-btn--small scalyn-launch-ai-copy" data-ai-key="<?php echo esc_attr( $ai_key ); ?>" title="<?php esc_attr_e( 'Copy', 'scalyn-qa-assistant' ); ?>">
										<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
										<?php esc_html_e( 'Copy', 'scalyn-qa-assistant' ); ?>
									</button>
									<button type="button" class="scalyn-btn scalyn-btn--small scalyn-launch-ai-regenerate" data-ai-key="<?php echo esc_attr( $ai_key ); ?>" data-check-id="<?php echo esc_attr( $check_id ); ?>" title="<?php esc_attr_e( 'Regenerate with AI', 'scalyn-qa-assistant' ); ?>">
										<span class="dashicons dashicons-update" aria-hidden="true"></span>
										<?php esc_html_e( 'Regenerate', 'scalyn-qa-assistant' ); ?>
									</button>
								</div>
							</div>
							<?php endif; ?>

							<?php
							// Show Regenerate button even when no AI content exists yet for this check.
							if ( $ai_configured && isset( $ai_map[ $check_id ] ) && empty( $ai_content[ $ai_map[ $check_id ] ] ) ) :
								$ai_key_for_regen = $ai_map[ $check_id ];
							?>
							<div class="scalyn-ai-inline-result scalyn-launch-ai-panel" data-ai-key="<?php echo esc_attr( $ai_key_for_regen ); ?>" data-check-id="<?php echo esc_attr( $check_id ); ?>" style="opacity:0.7;">
								<div class="scalyn-ai-inline-result__content">
									<span class="scalyn-ai-inline-result__label"><?php esc_html_e( 'AI Suggestion:', 'scalyn-qa-assistant' ); ?></span>
									<p class="scalyn-ai-inline-result__text" style="color:var(--scalyn-text-muted);"><?php esc_html_e( 'No AI content generated yet for this check. Click Generate with AI or use the header "Generate All with AI" button.', 'scalyn-qa-assistant' ); ?></p>
								</div>
								<div class="scalyn-ai-inline-result__actions">
									<button type="button" class="scalyn-btn scalyn-btn--small scalyn-launch-ai-regenerate" data-ai-key="<?php echo esc_attr( $ai_key_for_regen ); ?>" data-check-id="<?php echo esc_attr( $check_id ); ?>" title="<?php esc_attr_e( 'Regenerate with AI', 'scalyn-qa-assistant' ); ?>">
										<span class="dashicons dashicons-update" aria-hidden="true"></span>
										<?php esc_html_e( 'Generate with AI', 'scalyn-qa-assistant' ); ?>
									</button>
								</div>
							</div>
							<?php endif; ?>

						</div>
					<?php endforeach; ?>

					<?php
					$cat_ignored = $grouped_ignored[ $group_key ] ?? array();
					if ( ! empty( $cat_ignored ) ) :
					?>
						<details class="scalyn-ignored-section">
							<summary class="scalyn-ignored-section__toggle">
								<?php
								printf(
									esc_html( _n(
										'%d ignored check',
										'%d ignored checks',
										count( $cat_ignored ),
										'scalyn-qa-assistant'
									) ),
									count( $cat_ignored )
								);
								?>
							</summary>
							<div class="scalyn-ignored-section__list">
								<?php foreach ( $cat_ignored as $check ) :
									$rule = $ignored_ids[ $check->id ] ?? null;
								?>
									<div class="scalyn-check-item scalyn-check-item--ignored" style="opacity:0.6;">
										<span class="scalyn-check-icon" aria-hidden="true"><span class="dashicons dashicons-hidden"></span></span>
										<div class="scalyn-check-content">
											<strong class="scalyn-check-label"><?php echo esc_html( $check->label ); ?></strong>
											<?php if ( $rule && ! empty( $rule->reason ) ) : ?>
												<span class="scalyn-check-message"><?php echo esc_html( $rule->reason ); ?></span>
											<?php endif; ?>
										</div>
										<div class="scalyn-check-actions">
											<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-remove-ignore" data-rule-id="<?php echo esc_attr( $rule ? $rule->id : '' ); ?>" title="<?php esc_attr_e( 'Restore', 'scalyn-qa-assistant' ); ?>">
												<span class="dashicons dashicons-visibility" aria-hidden="true"></span> <?php esc_html_e( 'Restore', 'scalyn-qa-assistant' ); ?>
											</button>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>

	<?php
	// Collect all ignored checks across categories.
	$all_ignored = array();
	foreach ( $grouped_ignored as $cat_ignored_items ) {
		$all_ignored = array_merge( $all_ignored, $cat_ignored_items );
	}
	?>
	<?php if ( ! empty( $all_ignored ) ) : ?>
	<!-- Ignored Checks Section -->
	<div class="scalyn-card" id="scalyn-launch-ignored">
		<h2 class="scalyn-card-title">
			<?php esc_html_e( 'Ignored Checks', 'scalyn-qa-assistant' ); ?>
			<span class="scalyn-badge scalyn-badge--neutral"><?php echo esc_html( (string) count( $all_ignored ) ); ?></span>
		</h2>
		<table class="scalyn-table scalyn-table--compact">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Check', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Type', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Created By', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'scalyn-qa-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_ignored as $check ) :
					$rule = $ignored_ids[ $check->id ] ?? null;
				?>
					<tr data-rule-id="<?php echo esc_attr( $rule ? $rule->id : '' ); ?>">
						<td><code><?php echo esc_html( $check->id ); ?></code></td>
						<td>
							<span class="scalyn-badge scalyn-badge--neutral">
								<?php echo esc_html( $rule ? ucfirst( $rule->type ) : 'Global' ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $rule ? $rule->reason : '' ); ?></td>
						<td><?php echo esc_html( $rule ? $rule->created_by : '' ); ?></td>
						<td>
							<button
								type="button"
								class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-remove-ignore"
								data-rule-id="<?php echo esc_attr( $rule ? $rule->id : '' ); ?>"
								title="<?php esc_attr_e( 'Remove ignore rule', 'scalyn-qa-assistant' ); ?>"
							>
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Loading overlay for AJAX scan -->
	<div id="scalyn-launch-loading" class="scalyn-loading" style="display: none;" aria-hidden="true">
		<span class="spinner is-active"></span>
		<span class="scalyn-loading__text"><?php esc_html_e( 'Running launch checks...', 'scalyn-qa-assistant' ); ?></span>
	</div>

</div>
