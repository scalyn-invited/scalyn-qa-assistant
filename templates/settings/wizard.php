<?php
/**
 * Template: Settings — SEO Wizard Tab.
 *
 * Renders the SEO setup wizard that detects installed SEO plugins
 * and offers quick installation options if none are found.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'wizard';

// Detect current SEO plugin.
$seo_detected      = null;
$wizard_dismissed   = ! empty( $settings['wizard_dismissed'] );

try {
	$integration = \Scalyn\QA\Integrations\SEO_Integration::detect();
	if ( null !== $integration ) {
		$seo_detected = $integration->get_plugin_name();
	}
} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	// Integration detection failed; treat as not detected.
}

// Check if plugins are installed (but possibly inactive).
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$rankmath_plugin_file     = 'seo-by-rank-math/rank-math.php';
$rankmath_pro_plugin_file = 'seo-by-rank-math-pro/rank-math-pro.php';
$yoast_plugin_file        = 'wordpress-seo/wp-seo.php';
$yoast_pro_plugin_file    = 'wordpress-seo-premium/wp-seo-premium.php';

$rankmath_installed     = file_exists( WP_PLUGIN_DIR . '/' . $rankmath_plugin_file );
$rankmath_pro_installed = file_exists( WP_PLUGIN_DIR . '/' . $rankmath_pro_plugin_file );
$rankmath_active        = is_plugin_active( $rankmath_plugin_file );
$rankmath_pro_active    = is_plugin_active( $rankmath_pro_plugin_file );

$yoast_installed     = file_exists( WP_PLUGIN_DIR . '/' . $yoast_plugin_file );
$yoast_pro_installed = file_exists( WP_PLUGIN_DIR . '/' . $yoast_pro_plugin_file );
$yoast_active        = is_plugin_active( $yoast_plugin_file );
$yoast_pro_active    = is_plugin_active( $yoast_pro_plugin_file );

// Build the appropriate URL and label for each plugin.
if ( $rankmath_installed ) {
	$rankmath_url   = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $rankmath_plugin_file ) ), 'activate-plugin_' . $rankmath_plugin_file );
	$rankmath_label = __( 'Activate Rank Math', 'scalyn-qa-assistant' );
	$rankmath_icon  = 'admin-plugins';
} else {
	$rankmath_url   = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=seo-by-rank-math' ), 'install-plugin_seo-by-rank-math' );
	$rankmath_label = __( 'Install Rank Math', 'scalyn-qa-assistant' );
	$rankmath_icon  = 'download';
}

if ( $rankmath_pro_installed && ! $rankmath_pro_active ) {
	$rankmath_pro_url   = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $rankmath_pro_plugin_file ) ), 'activate-plugin_' . $rankmath_pro_plugin_file );
	$rankmath_pro_label = __( 'Activate Rank Math Pro', 'scalyn-qa-assistant' );
}

if ( $yoast_installed ) {
	$yoast_url   = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $yoast_plugin_file ) ), 'activate-plugin_' . $yoast_plugin_file );
	$yoast_label = __( 'Activate Yoast SEO', 'scalyn-qa-assistant' );
	$yoast_icon  = 'admin-plugins';
} else {
	$yoast_url   = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=wordpress-seo' ), 'install-plugin_wordpress-seo' );
	$yoast_label = __( 'Install Yoast SEO', 'scalyn-qa-assistant' );
	$yoast_icon  = 'download';
}

if ( $yoast_pro_installed && ! $yoast_pro_active ) {
	$yoast_pro_url   = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $yoast_pro_plugin_file ) ), 'activate-plugin_' . $yoast_pro_plugin_file );
	$yoast_pro_label = __( 'Activate Yoast Premium', 'scalyn-qa-assistant' );
}
?>
<div class="scalyn-wrap">

	<div class="scalyn-page-header">
		<div class="scalyn-page-header__intro">
			<h1><?php esc_html_e( 'Settings', 'scalyn-qa-assistant' ); ?></h1>
			<p class="scalyn-page-header__description"><?php esc_html_e( 'Configure scanning, scoring, AI providers, and plugin behavior.', 'scalyn-qa-assistant' ); ?></p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="scalyn-tabs" role="tablist">
		<?php foreach ( $tabs as $tab_slug => $tab ) : ?>
			<a
				href="<?php echo esc_url( $tab['url'] ); ?>"
				class="scalyn-tab <?php echo $tab['active'] ? 'scalyn-tab--active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $tab['active'] ? 'true' : 'false'; ?>"
			>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Wizard Content -->
	<div class="scalyn-card" id="scalyn-tab-panel-wizard" role="tabpanel">
		<h2 class="scalyn-card__title">
			<?php esc_html_e( 'SEO Plugin Setup Wizard', 'scalyn-qa-assistant' ); ?>
		</h2>

		<?php if ( null !== $seo_detected ) : ?>
			<!-- SEO Plugin Detected — Success State -->
			<div class="scalyn-alert scalyn-alert--green">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div class="scalyn-alert__body">
					<strong>
						<?php
						printf(
							/* translators: %s: SEO plugin name. */
							esc_html__( '%s is installed and active', 'scalyn-qa-assistant' ),
							esc_html( $seo_detected ),
						);
						?>
					</strong>
					<p>
						<?php esc_html_e( 'Your SEO plugin is properly configured. Scalyn QA will use its data for meta title and description analysis.', 'scalyn-qa-assistant' ); ?>
					</p>
				</div>
			</div>

			<div class="scalyn-wizard-info">
				<h3><?php esc_html_e( 'Integration Details', 'scalyn-qa-assistant' ); ?></h3>
				<ul class="scalyn-list">
					<li>
						<span class="dashicons dashicons-yes" aria-hidden="true"></span>
						<?php esc_html_e( 'Meta title and description reading is active', 'scalyn-qa-assistant' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes" aria-hidden="true"></span>
						<?php esc_html_e( 'Sitemap detection is configured', 'scalyn-qa-assistant' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes" aria-hidden="true"></span>
						<?php esc_html_e( 'Open Graph tag analysis is enabled', 'scalyn-qa-assistant' ); ?>
					</li>
				</ul>
			</div>

		<?php elseif ( ! $wizard_dismissed ) : ?>
			<!-- No SEO Plugin — Wizard UI -->
			<div class="scalyn-alert scalyn-alert--yellow">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div class="scalyn-alert__body">
					<strong><?php esc_html_e( 'No SEO Plugin Detected', 'scalyn-qa-assistant' ); ?></strong>
					<p>
						<?php esc_html_e( 'An SEO plugin is strongly recommended for managing meta tags, sitemaps, and structured data. Choose one below to install, or skip if you handle SEO manually.', 'scalyn-qa-assistant' ); ?>
					</p>
				</div>
			</div>

			<div class="scalyn-wizard-options">
				<!-- Rank Math -->
				<div class="scalyn-wizard-option">
					<div class="scalyn-wizard-option__header">
						<h3><?php esc_html_e( 'Rank Math SEO', 'scalyn-qa-assistant' ); ?></h3>
						<span class="scalyn-badge scalyn-badge--green">
							<?php esc_html_e( 'Recommended', 'scalyn-qa-assistant' ); ?>
						</span>
					</div>
					<p>
						<?php esc_html_e( 'Feature-rich SEO plugin with built-in schema markup, sitemap generation, and advanced analytics. Free version includes most essential features.', 'scalyn-qa-assistant' ); ?>
					</p>
					<ul class="scalyn-list scalyn-list--compact">
						<li><?php esc_html_e( 'Advanced schema markup', 'scalyn-qa-assistant' ); ?></li>
						<li><?php esc_html_e( 'Built-in SEO audit tools', 'scalyn-qa-assistant' ); ?></li>
						<li><?php esc_html_e( 'Google Search Console integration', 'scalyn-qa-assistant' ); ?></li>
					</ul>
					<?php if ( $rankmath_installed ) : ?>
					<button
						type="button"
						class="scalyn-btn scalyn-activate-seo-plugin"
						data-plugin="rank-math"
					>
						<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						<?php
						if ( $rankmath_pro_installed ) {
							esc_html_e( 'Activate Rank Math + Pro', 'scalyn-qa-assistant' );
						} else {
							esc_html_e( 'Activate Rank Math', 'scalyn-qa-assistant' );
						}
						?>
					</button>
					<?php else : ?>
					<button
						type="button"
						class="scalyn-btn scalyn-install-seo-plugin"
						data-plugin="rank-math"
					>
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Install Rank Math', 'scalyn-qa-assistant' ); ?>
					</button>
					<?php endif; ?>
				</div>

				<!-- Yoast SEO -->
				<div class="scalyn-wizard-option">
					<div class="scalyn-wizard-option__header">
						<h3><?php esc_html_e( 'Yoast SEO', 'scalyn-qa-assistant' ); ?></h3>
					</div>
					<p>
						<?php esc_html_e( 'The most popular WordPress SEO plugin. Provides content analysis, XML sitemaps, breadcrumbs, and social media integration.', 'scalyn-qa-assistant' ); ?>
					</p>
					<ul class="scalyn-list scalyn-list--compact">
						<li><?php esc_html_e( 'Content readability analysis', 'scalyn-qa-assistant' ); ?></li>
						<li><?php esc_html_e( 'XML sitemap generation', 'scalyn-qa-assistant' ); ?></li>
						<li><?php esc_html_e( 'Social media previews', 'scalyn-qa-assistant' ); ?></li>
					</ul>
					<?php if ( $yoast_installed ) : ?>
					<button
						type="button"
						class="scalyn-btn scalyn-btn--secondary scalyn-activate-seo-plugin"
						data-plugin="yoast"
					>
						<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						<?php
						if ( $yoast_pro_installed ) {
							esc_html_e( 'Activate Yoast SEO + Premium', 'scalyn-qa-assistant' );
						} else {
							esc_html_e( 'Activate Yoast SEO', 'scalyn-qa-assistant' );
						}
						?>
					</button>
					<?php else : ?>
					<button
						type="button"
						class="scalyn-btn scalyn-btn--secondary scalyn-install-seo-plugin"
						data-plugin="yoast"
					>
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Install Yoast SEO', 'scalyn-qa-assistant' ); ?>
					</button>
					<?php endif; ?>
				</div>
			</div>

			<!-- Skip Button -->
			<div class="scalyn-wizard-skip">
				<button
					type="button"
					id="scalyn-wizard-dismiss"
					class="scalyn-btn scalyn-btn--ghost"
				>
					<?php esc_html_e( 'Skip — I handle SEO manually', 'scalyn-qa-assistant' ); ?>
				</button>
				<p class="scalyn-field-description">
					<?php esc_html_e( 'You can always return to this page later to install an SEO plugin.', 'scalyn-qa-assistant' ); ?>
				</p>
			</div>

		<?php else : ?>
			<!-- Wizard was dismissed -->
			<div class="scalyn-alert scalyn-alert--neutral">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<div class="scalyn-alert__body">
					<strong><?php esc_html_e( 'SEO Setup Wizard Dismissed', 'scalyn-qa-assistant' ); ?></strong>
					<p>
						<?php esc_html_e( 'You previously dismissed the SEO plugin setup wizard. Scalyn QA will continue to work, but some SEO-specific checks may have limited functionality without a dedicated SEO plugin.', 'scalyn-qa-assistant' ); ?>
					</p>
				</div>
			</div>

			<div class="scalyn-wizard-reset">
				<button
					type="button"
					id="scalyn-wizard-reset"
					class="scalyn-btn scalyn-btn--secondary"
				>
					<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
					<?php esc_html_e( 'Reset Wizard', 'scalyn-qa-assistant' ); ?>
				</button>
				<p class="scalyn-field-description">
					<?php esc_html_e( 'Click to show the SEO plugin installation options again.', 'scalyn-qa-assistant' ); ?>
				</p>
			</div>
		<?php endif; ?>

	</div>

</div>
