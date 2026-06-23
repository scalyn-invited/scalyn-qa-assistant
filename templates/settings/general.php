<?php
/**
 * Template: Settings — General Tab.
 *
 * Renders the general settings form with post types, scanning, scoring,
 * and link checking configuration.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 * @var array  $post_types  Available post types (slug => label).
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'general';
$post_types  = isset( $post_types ) ? $post_types : array();

// Retrieve settings with defaults.
$defaults = \Scalyn\QA\Admin\Settings_Page::get_defaults();

$auto_scan_on_save  = isset( $settings['auto_scan_on_save'] ) ? (bool) $settings['auto_scan_on_save'] : $defaults['auto_scan_on_save'];
$selected_types     = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : $defaults['post_types'];
$score_green        = isset( $settings['green_threshold'] ) ? (int) $settings['green_threshold'] : $defaults['green_threshold'];
$score_yellow       = isset( $settings['yellow_threshold'] ) ? (int) $settings['yellow_threshold'] : $defaults['yellow_threshold'];
$link_timeout       = isset( $settings['link_timeout'] ) ? (int) $settings['link_timeout'] : 10;
$link_cache_hours   = isset( $settings['link_cache_hours'] ) ? (int) $settings['link_cache_hours'] : 24;
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
				aria-controls="scalyn-tab-panel-<?php echo esc_attr( $tab_slug ); ?>"
			>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- General Settings Card -->
	<div class="scalyn-card" id="scalyn-tab-panel-general" role="tabpanel">
		<form id="scalyn-settings-form" method="post">

			<table class="scalyn-form-table">
				<!-- Auto-scan on save -->
				<tr>
					<th scope="row">
						<label for="scalyn-auto-scan">
							<?php esc_html_e( 'Auto-scan on save', 'scalyn-qa-assistant' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								id="scalyn-auto-scan"
								name="auto_scan_on_save"
								value="1"
								<?php checked( $auto_scan_on_save ); ?>
							>
							<?php esc_html_e( 'Enable automatic scanning when saving posts', 'scalyn-qa-assistant' ); ?>
						</label>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'When enabled, the QA scan runs automatically each time a post is published or updated.', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>

				<!-- Post types to scan -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Post types to scan', 'scalyn-qa-assistant' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<?php esc_html_e( 'Post types to scan', 'scalyn-qa-assistant' ); ?>
							</legend>
							<?php if ( ! empty( $post_types ) ) : ?>
								<?php foreach ( $post_types as $type_slug => $type_label ) : ?>
									<label class="scalyn-checkbox-label">
										<input
											type="checkbox"
											name="post_types[]"
											value="<?php echo esc_attr( $type_slug ); ?>"
											<?php checked( in_array( $type_slug, $selected_types, true ) ); ?>
										>
										<?php echo esc_html( $type_label ); ?>
										<code>(<?php echo esc_html( $type_slug ); ?>)</code>
									</label><br>
								<?php endforeach; ?>
							<?php else : ?>
								<p class="scalyn-field-description">
									<?php esc_html_e( 'No public post types found.', 'scalyn-qa-assistant' ); ?>
								</p>
							<?php endif; ?>
						</fieldset>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'Select which post types the QA scanner should analyze.', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>

				<!-- Score Thresholds -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Score Thresholds', 'scalyn-qa-assistant' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<?php esc_html_e( 'Score Thresholds', 'scalyn-qa-assistant' ); ?>
							</legend>
							<label class="scalyn-inline-label">
								<?php esc_html_e( 'Green (Pass):', 'scalyn-qa-assistant' ); ?>
								<input
									type="number"
									name="score_green"
									value="<?php echo esc_attr( (string) $score_green ); ?>"
									min="0"
									max="100"
									class="scalyn-input scalyn-input--small"
								>
								<?php esc_html_e( 'and above', 'scalyn-qa-assistant' ); ?>
							</label>
							<br>
							<label class="scalyn-inline-label">
								<?php esc_html_e( 'Yellow (Review):', 'scalyn-qa-assistant' ); ?>
								<input
									type="number"
									name="score_yellow"
									value="<?php echo esc_attr( (string) $score_yellow ); ?>"
									min="0"
									max="100"
									class="scalyn-input scalyn-input--small"
								>
								<?php esc_html_e( 'to above value', 'scalyn-qa-assistant' ); ?>
							</label>
						</fieldset>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'Scores below the yellow threshold are marked as red (fail). Adjust these values to match your quality standards.', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>

				<!-- Link Check Timeout -->
				<tr>
					<th scope="row">
						<label for="scalyn-link-timeout">
							<?php esc_html_e( 'Link Check Timeout', 'scalyn-qa-assistant' ); ?>
						</label>
					</th>
					<td>
						<label class="scalyn-inline-label">
							<input
								type="number"
								id="scalyn-link-timeout"
								name="link_timeout"
								value="<?php echo esc_attr( (string) $link_timeout ); ?>"
								min="1"
								max="30"
								class="scalyn-input scalyn-input--small"
							>
							<?php esc_html_e( 'seconds', 'scalyn-qa-assistant' ); ?>
						</label>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'Maximum time in seconds to wait for each link response during broken link checks.', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>

				<!-- Cache Link Results -->
				<tr>
					<th scope="row">
						<label for="scalyn-link-cache">
							<?php esc_html_e( 'Cache Link Results', 'scalyn-qa-assistant' ); ?>
						</label>
					</th>
					<td>
						<label class="scalyn-inline-label">
							<input
								type="number"
								id="scalyn-link-cache"
								name="link_cache_hours"
								value="<?php echo esc_attr( (string) $link_cache_hours ); ?>"
								min="1"
								max="168"
								class="scalyn-input scalyn-input--small"
							>
							<?php esc_html_e( 'hours', 'scalyn-qa-assistant' ); ?>
						</label>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'How long to cache link check results before re-checking. Maximum 168 hours (7 days).', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="scalyn-form-actions">
				<button type="submit" class="scalyn-btn" id="scalyn-save-general">
					<?php esc_html_e( 'Save Settings', 'scalyn-qa-assistant' ); ?>
				</button>
				<span class="scalyn-save-status" id="scalyn-general-status" aria-live="polite"></span>
			</div>

		</form>
	</div>

</div>
