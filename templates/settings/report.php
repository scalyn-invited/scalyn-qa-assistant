<?php
/**
 * Template: Settings — Generate Report Tab.
 *
 * @package Scalyn\QA\Templates
 * @since   1.4.0
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data.
 * @var string $current_tab The current active tab slug.
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'report';

$report_settings = isset( $settings['report_settings'] ) && is_array( $settings['report_settings'] ) ? $settings['report_settings'] : array();

$include_page_scores     = $report_settings['include_page_scores'] ?? true;
$include_launch          = $report_settings['include_launch'] ?? true;
$include_top_issues      = $report_settings['include_top_issues'] ?? true;
$max_pages               = (int) ( $report_settings['max_pages'] ?? 500 );
$company_logo_id         = (int) ( $report_settings['company_logo_id'] ?? 0 );
$company_logo_url        = '';

if ( $company_logo_id > 0 ) {
	$url = wp_get_attachment_image_url( $company_logo_id, 'medium' );
	if ( $url ) {
		$company_logo_url = $url;
	}
}

$report_url = wp_nonce_url( admin_url( 'admin-post.php?action=scalyn_qa_generate_report' ), 'scalyn_qa_report' );
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

	<form id="scalyn-report-settings-form">

		<!-- Report Sections -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Report Sections', 'scalyn-qa-assistant' ); ?></h2>
			<p class="scalyn-card__subtitle"><?php esc_html_e( 'Choose which sections to include in the generated report.', 'scalyn-qa-assistant' ); ?></p>

			<div class="scalyn-checks-grid" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.75rem;">
				<label class="scalyn-checkbox-label" style="display: flex; align-items: center; gap: 0.5rem;">
					<input type="checkbox" name="include_page_scores" value="1" <?php checked( $include_page_scores ); ?>>
					<div>
						<strong><?php esc_html_e( 'Page Scores', 'scalyn-qa-assistant' ); ?></strong>
						<p class="scalyn-field-description" style="margin: 0;"><?php esc_html_e( 'Full table of all scanned pages with Overall, SEO, Content, and Functionality scores.', 'scalyn-qa-assistant' ); ?></p>
					</div>
				</label>
				<label class="scalyn-checkbox-label" style="display: flex; align-items: center; gap: 0.5rem;">
					<input type="checkbox" name="include_top_issues" value="1" <?php checked( $include_top_issues ); ?>>
					<div>
						<strong><?php esc_html_e( 'Top Issues', 'scalyn-qa-assistant' ); ?></strong>
						<p class="scalyn-field-description" style="margin: 0;"><?php esc_html_e( 'Most common failing checks across all pages with affected page count.', 'scalyn-qa-assistant' ); ?></p>
					</div>
				</label>
				<label class="scalyn-checkbox-label" style="display: flex; align-items: center; gap: 0.5rem;">
					<input type="checkbox" name="include_launch" value="1" <?php checked( $include_launch ); ?>>
					<div>
						<strong><?php esc_html_e( 'Launch Checklist', 'scalyn-qa-assistant' ); ?></strong>
						<p class="scalyn-field-description" style="margin: 0;"><?php esc_html_e( 'Site-wide readiness checks with pass/fail/warning status and details.', 'scalyn-qa-assistant' ); ?></p>
					</div>
				</label>
			</div>
		</div>

		<!-- Report Options -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Report Options', 'scalyn-qa-assistant' ); ?></h2>

			<table class="scalyn-form-table">
				<tr>
					<th scope="row">
						<label for="scalyn-max-pages"><?php esc_html_e( 'Max Pages', 'scalyn-qa-assistant' ); ?></label>
					</th>
					<td>
						<input type="number" id="scalyn-max-pages" name="max_pages" value="<?php echo esc_attr( (string) $max_pages ); ?>" min="10" max="1000" class="scalyn-input" style="width: 100px;">
						<p class="scalyn-field-description"><?php esc_html_e( 'Maximum number of pages to include in the Page Scores section.', 'scalyn-qa-assistant' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Company Logo', 'scalyn-qa-assistant' ); ?></label>
					</th>
					<td>
						<div id="scalyn-logo-preview" style="margin-bottom: 0.5rem; <?php echo '' === $company_logo_url ? 'display:none;' : ''; ?>">
							<?php if ( '' !== $company_logo_url ) : ?>
								<img src="<?php echo esc_url( $company_logo_url ); ?>" alt="" style="max-height: 60px; border-radius: 6px; border: 1px solid var(--scalyn-border-light);">
							<?php endif; ?>
						</div>
						<input type="hidden" id="scalyn-company-logo-id" name="company_logo_id" value="<?php echo esc_attr( (string) $company_logo_id ); ?>">
						<button type="button" id="scalyn-upload-logo" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary">
							<span class="dashicons dashicons-upload" aria-hidden="true"></span>
							<?php echo 0 === $company_logo_id ? esc_html__( 'Upload Logo', 'scalyn-qa-assistant' ) : esc_html__( 'Change Logo', 'scalyn-qa-assistant' ); ?>
						</button>
						<button
							type="button"
							id="scalyn-detect-logo"
							class="scalyn-btn scalyn-btn--small scalyn-btn--secondary"
							style="margin-left: 0.25rem;"
						>
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							<?php esc_html_e( 'Detect Site Logo', 'scalyn-qa-assistant' ); ?>
						</button>
						<?php if ( $company_logo_id > 0 ) : ?>
						<button type="button" id="scalyn-remove-logo" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost" style="margin-left: 0.25rem;">
							<?php esc_html_e( 'Remove', 'scalyn-qa-assistant' ); ?>
						</button>
						<?php endif; ?>
						<p class="scalyn-field-description"><?php esc_html_e( 'Upload a logo, or click "Detect Site Logo" to auto-detect the logo currently used on your site.', 'scalyn-qa-assistant' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Save + Generate -->
		<div class="scalyn-form-actions" style="display: flex; gap: 0.5rem; margin-top: 1rem;">
			<button type="submit" class="scalyn-btn">
				<?php esc_html_e( 'Save Settings', 'scalyn-qa-assistant' ); ?>
			</button>
			<a href="<?php echo esc_url( $report_url ); ?>" class="scalyn-btn scalyn-btn--secondary" target="_blank">
				<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
				<?php esc_html_e( 'Generate Report', 'scalyn-qa-assistant' ); ?>
			</a>
		</div>

	</form>

</div>
