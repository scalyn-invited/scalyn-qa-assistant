<?php
/**
 * Template: Settings — Launch Checklist Tab.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.6
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'launch';

$launch = isset( $settings['launch_settings'] ) && is_array( $settings['launch_settings'] ) ? $settings['launch_settings'] : array();

// PHP requirement defaults.
$thresholds = $launch['thresholds'] ?? array();
$php_version      = $thresholds['php_version'] ?? '8.3.14';
$memory_limit     = (int) ( $thresholds['memory_limit'] ?? 512 );
$max_execution    = (int) ( $thresholds['max_execution_time'] ?? 90 );
$max_input        = (int) ( $thresholds['max_input_time'] ?? 90 );
$post_max         = (int) ( $thresholds['post_max_size'] ?? 128 );
$upload_max       = (int) ( $thresholds['upload_max_size'] ?? 64 );

// Enabled checks — all enabled by default.
$enabled_checks = $launch['enabled_checks'] ?? array();

$check_categories = array(
	'seo' => array(
		'label'  => __( 'SEO Configuration', 'scalyn-qa-assistant' ),
		'checks' => array(
			'search_engine_visibility' => __( 'Search Engine Visibility', 'scalyn-qa-assistant' ),
			'seo_plugin_installed'     => __( 'SEO Plugin Installed', 'scalyn-qa-assistant' ),
			'sitemap_exists'           => __( 'Sitemap Exists', 'scalyn-qa-assistant' ),
			'robots_txt'               => __( 'robots.txt Accessible', 'scalyn-qa-assistant' ),
			'permalink_structure'      => __( 'Permalink Structure', 'scalyn-qa-assistant' ),
			'llms_txt'                 => __( 'llms.txt', 'scalyn-qa-assistant' ),
			'breadcrumbs_enabled'      => __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
			'redirect_manager'         => __( 'Redirect Manager', 'scalyn-qa-assistant' ),
			'local_business_schema'    => __( 'Local Business Schema', 'scalyn-qa-assistant' ),
			'four_oh_four_monitor'     => __( '404 Monitor', 'scalyn-qa-assistant' ),
			'cornerstone_content'      => __( 'Cornerstone Content', 'scalyn-qa-assistant' ),
			'instant_indexing'         => __( 'Instant Indexing', 'scalyn-qa-assistant' ),
			'woocommerce_seo'          => __( 'WooCommerce SEO', 'scalyn-qa-assistant' ),
		),
	),
	'analytics' => array(
		'label'  => __( 'Analytics', 'scalyn-qa-assistant' ),
		'checks' => array(
			'ga4_configured' => __( 'Google Analytics (GA4)', 'scalyn-qa-assistant' ),
			'gtm_configured' => __( 'Google Tag Manager', 'scalyn-qa-assistant' ),
		),
	),
	'technical' => array(
		'label'  => __( 'Technical', 'scalyn-qa-assistant' ),
		'checks' => array(
			'ssl_enabled'            => __( 'SSL Enabled', 'scalyn-qa-assistant' ),
			'debug_mode_disabled'    => __( 'Debug Mode Disabled', 'scalyn-qa-assistant' ),
			'wp_core_updates'        => __( 'WordPress Updates', 'scalyn-qa-assistant' ),
			'plugin_updates'         => __( 'Plugin Updates', 'scalyn-qa-assistant' ),
			'wp_address_match'       => __( 'WP Address Match', 'scalyn-qa-assistant' ),
			'favicon_exists'         => __( 'Favicon', 'scalyn-qa-assistant' ),
			'php_version'            => __( 'PHP Version', 'scalyn-qa-assistant' ),
			'php_memory_limit'       => __( 'PHP Memory Limit', 'scalyn-qa-assistant' ),
			'php_max_execution_time' => __( 'PHP Max Execution Time', 'scalyn-qa-assistant' ),
			'php_max_input_time'     => __( 'PHP Max Input Time', 'scalyn-qa-assistant' ),
			'php_post_max_size'      => __( 'PHP Post Max Size', 'scalyn-qa-assistant' ),
			'php_upload_max_size'    => __( 'PHP Upload Max Size', 'scalyn-qa-assistant' ),
		),
	),
	'content' => array(
		'label'  => __( 'Content', 'scalyn-qa-assistant' ),
		'checks' => array(
			'contact_page_exists'    => __( 'Contact Page', 'scalyn-qa-assistant' ),
			'privacy_policy_exists'  => __( 'Privacy Policy', 'scalyn-qa-assistant' ),
			'default_content_cleanup' => __( 'Default Content Cleanup', 'scalyn-qa-assistant' ),
			'default_tagline'        => __( 'Site Tagline', 'scalyn-qa-assistant' ),
			'empty_pages'            => __( 'Empty Pages', 'scalyn-qa-assistant' ),
			'four_oh_four_page'      => __( '404 Page', 'scalyn-qa-assistant' ),
			'menu_exists'            => __( 'Navigation Menu', 'scalyn-qa-assistant' ),
		),
	),
	'plugin_health' => array(
		'label'  => __( 'Plugin Health', 'scalyn-qa-assistant' ),
		'checks' => array(
			'default_plugins_cleanup'   => __( 'Default Plugins Cleanup', 'scalyn-qa-assistant' ),
			'plugin_conflicts'          => __( 'Plugin Conflicts', 'scalyn-qa-assistant' ),
			'security_plugin'           => __( 'Security Plugin', 'scalyn-qa-assistant' ),
			'cache_plugin'              => __( 'Cache Plugin', 'scalyn-qa-assistant' ),
			'backup_plugin'             => __( 'Backup Plugin', 'scalyn-qa-assistant' ),
			'smtp_plugin'               => __( 'SMTP / Mail Plugin', 'scalyn-qa-assistant' ),
			'image_optimization_plugin' => __( 'Image Optimization Plugin', 'scalyn-qa-assistant' ),
		),
	),
	'settings' => array(
		'label'  => __( 'WordPress Settings', 'scalyn-qa-assistant' ),
		'checks' => array(
			'admin_username' => __( 'Admin Username', 'scalyn-qa-assistant' ),
			'timezone_set'   => __( 'Timezone', 'scalyn-qa-assistant' ),
			'comments_open'  => __( 'Comments', 'scalyn-qa-assistant' ),
		),
	),
);

// Detect SEO plugins and pro versions (same as page-audits.php).
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$seo_plugins_detected = array();
if ( defined( 'RANK_MATH_VERSION' ) ) {
	$seo_plugins_detected['rankmath'] = defined( 'RANK_MATH_PRO_VERSION' ) ? 'pro' : 'free';
}
if ( defined( 'WPSEO_VERSION' ) ) {
	$seo_plugins_detected['yoast'] = defined( 'WPSEO_PREMIUM_FILE' ) ? 'pro' : 'free';
}
if ( defined( 'AIOSEO_VERSION' ) ) {
	$seo_plugins_detected['aioseo'] = defined( 'AIOSEO_PRO_VERSION' ) ? 'pro' : 'free';
}
if ( defined( 'SEOPRESS_VERSION' ) ) {
	$seo_plugins_detected['seopress'] = defined( 'SEOPRESS_PRO_VERSION' ) ? 'pro' : 'free';
}
if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
	$seo_plugins_detected['tsf'] = defined( 'THE_SEO_FRAMEWORK_EXTENSION_MANAGER_VERSION' ) ? 'pro' : 'free';
}

$has_any_pro        = in_array( 'pro', $seo_plugins_detected, true );
$has_any_seo_plugin = ! empty( $seo_plugins_detected );

// Pro-enhanced checks with descriptions.
$pro_enhanced_checks = array(
	'redirect_manager'      => __( 'Pro: Auto redirects', 'scalyn-qa-assistant' ),
	'local_business_schema' => __( 'Pro: Local SEO module', 'scalyn-qa-assistant' ),
	'cornerstone_content'   => __( 'Pro: Internal linking boost', 'scalyn-qa-assistant' ),
	'instant_indexing'      => __( 'Pro: IndexNow support', 'scalyn-qa-assistant' ),
	'woocommerce_seo'       => __( 'Pro: Product schema', 'scalyn-qa-assistant' ),
	'breadcrumbs_enabled'   => __( 'Pro: Advanced breadcrumbs', 'scalyn-qa-assistant' ),
);

// Checks that need an SEO plugin to be useful.
$requires_seo_plugin = array(
	'redirect_manager',
	'local_business_schema',
	'cornerstone_content',
	'instant_indexing',
);

// If no settings saved yet, all checks are enabled.
$has_saved = ! empty( $enabled_checks );
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

	<form id="scalyn-launch-settings-form">

		<?php if ( ! empty( $seo_plugins_detected ) ) : ?>
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Detected SEO Plugins', 'scalyn-qa-assistant' ); ?></h2>
			<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
				<?php
				$plugin_names = array(
					'rankmath' => 'Rank Math',
					'yoast'    => 'Yoast SEO',
					'aioseo'   => 'All in One SEO',
					'seopress' => 'SEOPress',
					'tsf'      => 'The SEO Framework',
				);
				foreach ( $seo_plugins_detected as $slug => $tier ) :
					$name   = $plugin_names[ $slug ] ?? $slug;
					$is_pro = 'pro' === $tier;
					$color  = $is_pro ? 'var(--scalyn-success)' : 'var(--scalyn-text-muted)';
					$bg     = $is_pro ? 'var(--scalyn-success-light)' : 'var(--scalyn-surface-subtle, #f3f4f6)';
				?>
					<span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.25rem 0.625rem;border-radius:999px;font-size:0.75rem;font-weight:600;color:<?php echo esc_attr( $color ); ?>;background:<?php echo esc_attr( $bg ); ?>;border:1px solid <?php echo esc_attr( $color ); ?>;">
						<?php echo esc_html( $name ); ?>
						<span style="font-weight:400;"><?php echo $is_pro ? esc_html__( '(Pro)', 'scalyn-qa-assistant' ) : esc_html__( '(Free)', 'scalyn-qa-assistant' ); ?></span>
					</span>
				<?php endforeach; ?>
			</div>
			<?php if ( ! $has_any_pro ) : ?>
				<p class="scalyn-field-description" style="margin-top:0.5rem;">
					<?php esc_html_e( 'Pro-enhanced checks are unchecked by default. Upgrade your SEO plugin to unlock richer analysis.', 'scalyn-qa-assistant' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- PHP Requirements -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'PHP Requirements', 'scalyn-qa-assistant' ); ?></h2>
			<p class="scalyn-card__subtitle"><?php esc_html_e( 'Set minimum PHP thresholds for the launch checklist. Checks will show a warning if the server value is below these.', 'scalyn-qa-assistant' ); ?></p>

			<table class="scalyn-form-table">
				<tr>
					<th scope="row"><label for="scalyn-threshold-php-version"><?php esc_html_e( 'PHP Version', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="text" id="scalyn-threshold-php-version" name="php_version" value="<?php echo esc_attr( $php_version ); ?>" class="scalyn-input" style="width:120px;" placeholder="8.3.14">
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( PHP_VERSION ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-memory"><?php esc_html_e( 'Memory Limit', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-memory" name="memory_limit" value="<?php echo esc_attr( (string) $memory_limit ); ?>" min="64" max="4096" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'MB', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( ini_get( 'memory_limit' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-execution"><?php esc_html_e( 'Max Execution Time', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-execution" name="max_execution_time" value="<?php echo esc_attr( (string) $max_execution ); ?>" min="30" max="600" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'seconds', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %ss', 'scalyn-qa-assistant' ), esc_html( ini_get( 'max_execution_time' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-input"><?php esc_html_e( 'Max Input Time', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-input" name="max_input_time" value="<?php echo esc_attr( (string) $max_input ); ?>" min="30" max="600" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'seconds', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %ss', 'scalyn-qa-assistant' ), esc_html( ini_get( 'max_input_time' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-post"><?php esc_html_e( 'Post Max Size', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-post" name="post_max_size" value="<?php echo esc_attr( (string) $post_max ); ?>" min="8" max="2048" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'MB', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( ini_get( 'post_max_size' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-upload"><?php esc_html_e( 'Upload Max Size', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-upload" name="upload_max_size" value="<?php echo esc_attr( (string) $upload_max ); ?>" min="2" max="2048" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'MB', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( ini_get( 'upload_max_filesize' ) ) ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Enabled Checks -->
		<?php foreach ( $check_categories as $cat_key => $category ) : ?>
			<div class="scalyn-card">
				<h2 class="scalyn-card-title"><?php echo esc_html( $category['label'] ); ?></h2>

				<div class="scalyn-checks-grid">
					<?php foreach ( $category['checks'] as $check_id => $check_label ) :
						$is_pro_check    = isset( $pro_enhanced_checks[ $check_id ] );
						$needs_seo       = in_array( $check_id, $requires_seo_plugin, true );
						$is_pro_locked   = $is_pro_check && ! $has_any_pro;
						$is_seo_missing  = $needs_seo && ! $has_any_seo_plugin;
						$is_disabled     = $is_pro_locked || $is_seo_missing;

						if ( $has_saved ) {
							$is_enabled = $is_disabled ? false : in_array( $check_id, $enabled_checks, true );
						} else {
							$is_enabled = $is_pro_check ? $has_any_pro : true;
						}
					?>
						<label class="scalyn-checkbox-label" style="display:flex;align-items:center;gap:0.375rem;<?php echo $is_disabled ? 'opacity:0.5;cursor:not-allowed;' : ''; ?>">
							<input
								type="checkbox"
								name="enabled_checks[]"
								value="<?php echo esc_attr( $check_id ); ?>"
								<?php checked( $is_enabled ); ?>
								<?php echo $is_disabled ? 'disabled' : ''; ?>
							>
							<?php echo esc_html( $check_label ); ?>
							<?php if ( $is_pro_check ) : ?>
								<span style="font-size:0.625rem;font-weight:600;padding:0.1rem 0.375rem;border-radius:999px;background:<?php echo $has_any_pro ? 'var(--scalyn-success-light)' : 'var(--scalyn-surface-subtle, #f3f4f6)'; ?>;color:<?php echo $has_any_pro ? 'var(--scalyn-success)' : 'var(--scalyn-text-muted)'; ?>;border:1px solid <?php echo $has_any_pro ? 'var(--scalyn-success)' : 'var(--scalyn-border-light)'; ?>;">
									<?php esc_html_e( 'PRO', 'scalyn-qa-assistant' ); ?>
								</span>
								<?php if ( $is_pro_locked ) : ?>
									<span style="font-size:0.6875rem;color:var(--scalyn-text-muted);"><?php esc_html_e( 'Upgrade SEO plugin to Pro to unlock', 'scalyn-qa-assistant' ); ?></span>
								<?php else : ?>
									<span style="font-size:0.6875rem;color:var(--scalyn-text-muted);"><?php echo esc_html( $pro_enhanced_checks[ $check_id ] ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( $is_seo_missing ) : ?>
								<span style="font-size:0.6875rem;color:var(--scalyn-text-muted);"><?php esc_html_e( '(requires SEO plugin)', 'scalyn-qa-assistant' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="scalyn-form-actions">
			<button type="submit" class="scalyn-btn" id="scalyn-save-launch-settings">
				<?php esc_html_e( 'Save Launch Settings', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</form>
</div>
