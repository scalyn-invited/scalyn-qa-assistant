<?php
/**
 * Template: Settings — Page Audits Tab.
 *
 * Renders the page audit checks configuration, allowing users to
 * enable or disable individual QA checks that run during page scans.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.7
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'page-audits';

$page_audit = isset( $settings['page_audit_settings'] ) && is_array( $settings['page_audit_settings'] ) ? $settings['page_audit_settings'] : array();

// Enabled checks — all enabled by default.
$enabled_checks = $page_audit['enabled_checks'] ?? array();

$max_image_file_size = $page_audit['max_image_file_size'] ?? 900;

// Detect SEO plugins and pro/premium versions.
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$seo_plugins_detected = array();

// Rank Math.
if ( defined( 'RANK_MATH_VERSION' ) ) {
	$is_pro = defined( 'RANK_MATH_PRO_VERSION' ) || is_plugin_active( 'seo-by-rank-math-pro/rank-math-pro.php' );
	$seo_plugins_detected['rankmath'] = $is_pro ? 'pro' : 'free';
}

// Yoast SEO.
if ( defined( 'WPSEO_VERSION' ) ) {
	$is_pro = defined( 'WPSEO_PREMIUM_FILE' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' );
	$seo_plugins_detected['yoast'] = $is_pro ? 'pro' : 'free';
}

// AIOSEO.
if ( defined( 'AIOSEO_VERSION' ) ) {
	$is_pro = defined( 'AIOSEO_PRO_VERSION' ) || is_plugin_active( 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' );
	$seo_plugins_detected['aioseo'] = $is_pro ? 'pro' : 'free';
}

// SEOPress.
if ( defined( 'SEOPRESS_VERSION' ) ) {
	$is_pro = defined( 'SEOPRESS_PRO_VERSION' ) || is_plugin_active( 'wp-seopress-pro/seopress-pro.php' );
	$seo_plugins_detected['seopress'] = $is_pro ? 'pro' : 'free';
}

// The SEO Framework.
if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
	$is_pro = defined( 'THE_SEO_FRAMEWORK_EXTENSION_MANAGER_VERSION' );
	$seo_plugins_detected['tsf'] = $is_pro ? 'pro' : 'free';
}

$has_any_pro = in_array( 'pro', $seo_plugins_detected, true );

// Checks that benefit from pro SEO features.
// These work with free versions but provide richer data with pro.
$pro_enhanced_checks = array(
	'focus_keyword'           => __( 'Pro: Multiple keywords', 'scalyn-qa-assistant' ),
	'schema_markup'           => __( 'Pro: Advanced schema', 'scalyn-qa-assistant' ),
	'seo_score'               => __( 'Pro: Deeper analysis', 'scalyn-qa-assistant' ),
	'social_image_dimensions' => __( 'Pro: Social previews', 'scalyn-qa-assistant' ),
	'readability_score'       => __( 'Pro: Content AI scoring', 'scalyn-qa-assistant' ),
);

// Checks that require an SEO plugin (free or pro) to function.
$requires_seo_plugin = array(
	'focus_keyword',
	'seo_score',
	'canonical_url',
	'noindex_nofollow',
	'open_graph_tags',
);

$has_any_seo_plugin = ! empty( $seo_plugins_detected );

$check_categories = array(
	'seo' => array(
		'label'  => __( 'SEO Checks', 'scalyn-qa-assistant' ),
		'checks' => array(
			'meta_title_exists'       => __( 'Meta Title', 'scalyn-qa-assistant' ),
			'meta_description_exists' => __( 'Meta Description', 'scalyn-qa-assistant' ),
			'image_alt_text'          => __( 'Image Alt Text', 'scalyn-qa-assistant' ),
			'featured_image_exists'   => __( 'Featured Image Set', 'scalyn-qa-assistant' ),
			'internal_links_present'  => __( 'Internal Links Present', 'scalyn-qa-assistant' ),
			'external_links_present'  => __( 'External Links Present', 'scalyn-qa-assistant' ),
			'canonical_url'           => __( 'Canonical URL', 'scalyn-qa-assistant' ),
			'noindex_nofollow'        => __( 'Noindex / Nofollow', 'scalyn-qa-assistant' ),
			'open_graph_tags'         => __( 'Open Graph Tags', 'scalyn-qa-assistant' ),
			'broken_media'            => __( 'Broken / Missing Media', 'scalyn-qa-assistant' ),
			'image_dimensions'        => __( 'Image Dimensions (CLS)', 'scalyn-qa-assistant' ),
			'image_lazy_loading'      => __( 'Image Lazy Loading', 'scalyn-qa-assistant' ),
			'image_file_size'         => __( 'Image File Size', 'scalyn-qa-assistant' ),
			'focus_keyword'           => __( 'Focus Keyword', 'scalyn-qa-assistant' ),
			'schema_markup'           => __( 'Schema Markup', 'scalyn-qa-assistant' ),
			'seo_score'               => __( 'SEO Plugin Score', 'scalyn-qa-assistant' ),
			'social_image_dimensions' => __( 'Social Share Image', 'scalyn-qa-assistant' ),
		),
	),
	'content' => array(
		'label'  => __( 'Content Checks', 'scalyn-qa-assistant' ),
		'checks' => array(
			'h1_exists'               => __( 'H1 Heading Exists', 'scalyn-qa-assistant' ),
			'heading_hierarchy'       => __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
			'empty_headings'          => __( 'Empty Headings', 'scalyn-qa-assistant' ),
			'content_length'          => __( 'Content Length', 'scalyn-qa-assistant' ),
			'heading_capitalization'  => __( 'Heading Capitalization', 'scalyn-qa-assistant' ),
			'paragraph_punctuation'   => __( 'Paragraph Punctuation', 'scalyn-qa-assistant' ),
			'short_paragraphs'        => __( 'Paragraph Quality', 'scalyn-qa-assistant' ),
			'readability_score'       => __( 'Readability Score', 'scalyn-qa-assistant' ),
		),
	),
	'functionality' => array(
		'label'  => __( 'Functionality Checks', 'scalyn-qa-assistant' ),
		'checks' => array(
			'broken_links'      => __( 'Broken Link Check', 'scalyn-qa-assistant' ),
			'links_summary'     => __( 'Links Summary', 'scalyn-qa-assistant' ),
			'empty_buttons'     => __( 'Empty Buttons', 'scalyn-qa-assistant' ),
			'placeholder_links' => __( 'Placeholder Links', 'scalyn-qa-assistant' ),
			'form_has_submit'   => __( 'Form Submit Buttons', 'scalyn-qa-assistant' ),
			'popup_triggers'    => __( 'Popup Triggers', 'scalyn-qa-assistant' ),
		),
	),
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

	<form id="scalyn-page-audit-settings-form">

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
					$name    = $plugin_names[ $slug ] ?? $slug;
					$is_pro  = 'pro' === $tier;
					$color   = $is_pro ? 'var(--scalyn-success)' : 'var(--scalyn-text-muted)';
					$bg      = $is_pro ? 'var(--scalyn-success-light)' : 'var(--scalyn-surface-subtle, #f3f4f6)';
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

		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Image Optimization Settings', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-form-row" style="display: flex; align-items: center; gap: 0.5rem;">
				<label for="max_image_file_size">
					<?php esc_html_e( 'Max image file size:', 'scalyn-qa-assistant' ); ?>
				</label>
				<input
					type="number"
					id="max_image_file_size"
					name="max_image_file_size"
					value="<?php echo esc_attr( $max_image_file_size ); ?>"
					min="1"
					max="10000"
					style="width: 100px;"
				>
				<span class="scalyn-field-description"><?php esc_html_e( 'KB — images exceeding this size will be flagged.', 'scalyn-qa-assistant' ); ?></span>
			</div>
		</div>

		<?php foreach ( $check_categories as $cat_key => $category ) : ?>
			<div class="scalyn-card">
				<h2 class="scalyn-card-title"><?php echo esc_html( $category['label'] ); ?></h2>

				<div class="scalyn-template-checks">
					<fieldset>
						<legend class="screen-reader-text">
							<?php echo esc_html( $category['label'] ); ?>
						</legend>
						<?php foreach ( $category['checks'] as $check_id => $check_label ) :
							$is_pro_check      = isset( $pro_enhanced_checks[ $check_id ] );
							$needs_seo_plugin  = in_array( $check_id, $requires_seo_plugin, true );
							$is_pro_locked     = $is_pro_check && ! $has_any_pro;
							$is_seo_missing    = $needs_seo_plugin && ! $has_any_seo_plugin;
							$is_disabled       = $is_pro_locked || $is_seo_missing;

							if ( $has_saved ) {
								$is_checked = $is_disabled ? false : in_array( $check_id, $enabled_checks, true );
							} else {
								$is_checked = $is_pro_check ? $has_any_pro : true;
							}
						?>
							<label class="scalyn-checkbox-label scalyn-template-check" style="display:flex;align-items:center;gap:0.375rem;<?php echo $is_disabled ? 'opacity:0.5;cursor:not-allowed;' : ''; ?>">
								<input
									type="checkbox"
									name="enabled_checks[]"
									value="<?php echo esc_attr( $check_id ); ?>"
									<?php checked( $is_checked ); ?>
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
					</fieldset>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="scalyn-form-actions" style="margin-top: 1rem;">
			<button type="submit" class="scalyn-btn">
				<?php esc_html_e( 'Save Settings', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</form>

</div>
