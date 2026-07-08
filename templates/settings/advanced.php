<?php
/**
 * Template: Settings — Advanced Tab.
 *
 * Renders advanced settings including data deletion on uninstall,
 * settings export/import, and AI usage controls.
 *
 * @package Scalyn\QA\Templates
 * @since   1.1.0
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 * @var array  $post_types  Available post types (slug => label).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'advanced';

// Retrieve settings with defaults.
$defaults = \Scalyn\QA\Admin\Settings_Page::get_defaults();

$delete_data_on_uninstall = isset( $settings['delete_data_on_uninstall'] ) ? (bool) $settings['delete_data_on_uninstall'] : $defaults['delete_data_on_uninstall'];
$max_ai_requests_per_day  = isset( $settings['max_ai_requests_per_day'] ) ? (int) $settings['max_ai_requests_per_day'] : $defaults['max_ai_requests_per_day'];
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

	<!-- GitHub Updates Card -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'GitHub Updates', 'scalyn-qa-assistant' ); ?></h2>
		<p class="scalyn-field-description">
			<?php esc_html_e( 'This plugin checks for updates from GitHub Releases. No WordPress.org account is required.', 'scalyn-qa-assistant' ); ?>
		</p>

		<table class="scalyn-form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Installed Version', 'scalyn-qa-assistant' ); ?></th>
				<td><code><?php echo esc_html( SCALYN_QA_VERSION ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Latest Version', 'scalyn-qa-assistant' ); ?></th>
				<td><span id="scalyn-github-latest-version"><?php esc_html_e( 'Not checked', 'scalyn-qa-assistant' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last Checked', 'scalyn-qa-assistant' ); ?></th>
				<td>
					<span id="scalyn-github-last-checked">
						<?php
						$last_check = get_option( 'scalyn_qa_github_last_check', '' );
						if ( $last_check ) {
							echo esc_html( human_time_diff( strtotime( $last_check ), time() ) . ' ' . __( 'ago', 'scalyn-qa-assistant' ) );
						} else {
							esc_html_e( 'Never', 'scalyn-qa-assistant' );
						}
						?>
					</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Repository Owner', 'scalyn-qa-assistant' ); ?></th>
				<td>
					<input type="text" id="scalyn-github-owner" class="scalyn-input"
					       value="<?php echo esc_attr( $settings['github_owner'] ?? 'scalyn-invited' ); ?>"
					       placeholder="scalyn-invited">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Repository Name', 'scalyn-qa-assistant' ); ?></th>
				<td>
					<input type="text" id="scalyn-github-repo" class="scalyn-input"
					       value="<?php echo esc_attr( $settings['github_repo'] ?? 'scalyn-qa-assistant' ); ?>"
					       placeholder="scalyn-qa-assistant">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="scalyn-github-token">
						<?php esc_html_e( 'GitHub Token', 'scalyn-qa-assistant' ); ?>
					</label>
				</th>
				<td>
					<input type="password" id="scalyn-github-token" class="scalyn-input"
					       value="" placeholder="<?php esc_attr_e( 'Optional — for higher API limits', 'scalyn-qa-assistant' ); ?>">
					<p class="scalyn-field-description">
						<?php esc_html_e( 'Optional. Only needed for higher API rate limits or private repositories. Leave empty for public repositories.', 'scalyn-qa-assistant' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="scalyn-form-actions">
			<button type="button" class="scalyn-btn" id="scalyn-check-updates">
				<?php esc_html_e( 'Check for Updates', 'scalyn-qa-assistant' ); ?>
			</button>
			<button type="button" class="scalyn-btn scalyn-btn--secondary" id="scalyn-update-now" style="display:none;">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Update Now', 'scalyn-qa-assistant' ); ?>
			</button>
			<button type="button" class="scalyn-btn scalyn-btn--secondary" id="scalyn-save-github-settings">
				<?php esc_html_e( 'Save GitHub Settings', 'scalyn-qa-assistant' ); ?>
			</button>
			<span id="scalyn-github-status"></span>
		</div>
	</div>

	<!-- Data Management Card -->
	<div class="scalyn-card" id="scalyn-tab-panel-advanced" role="tabpanel">
		<h2><?php esc_html_e( 'Data Management', 'scalyn-qa-assistant' ); ?></h2>

		<form id="scalyn-advanced-settings-form" method="post">
			<table class="scalyn-form-table">
				<!-- Delete data on uninstall -->
				<tr>
					<th scope="row">
						<label for="scalyn-delete-data-on-uninstall">
							<?php esc_html_e( 'Delete all plugin data on uninstall', 'scalyn-qa-assistant' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								id="scalyn-delete-data-on-uninstall"
								name="delete_data_on_uninstall"
								value="1"
								<?php checked( $delete_data_on_uninstall ); ?>
							>
							<?php esc_html_e( 'Remove all plugin data when uninstalled', 'scalyn-qa-assistant' ); ?>
						</label>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'When enabled, all scan results, scores, notes, snapshots, settings, AI configurations, and templates will be permanently deleted when the plugin is uninstalled. When disabled, data is preserved so it\'s available if you reinstall.', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="scalyn-form-actions">
				<button type="submit" class="scalyn-btn" id="scalyn-save-advanced">
					<?php esc_html_e( 'Save Settings', 'scalyn-qa-assistant' ); ?>
				</button>
				<span class="scalyn-save-status" id="scalyn-advanced-status" aria-live="polite"></span>
			</div>
		</form>
	</div>

	<!-- Export / Import Card -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'Export / Import Settings', 'scalyn-qa-assistant' ); ?></h2>

		<table class="scalyn-form-table">
			<!-- Export -->
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Export Settings', 'scalyn-qa-assistant' ); ?>
				</th>
				<td>
					<button type="button" class="scalyn-btn" id="scalyn-export-settings">
						<?php esc_html_e( 'Export Settings', 'scalyn-qa-assistant' ); ?>
					</button>
					<p class="scalyn-field-description">
						<?php esc_html_e( 'Download a JSON file containing all plugin settings, templates, and configuration. API keys are masked and will not be exported.', 'scalyn-qa-assistant' ); ?>
					</p>
				</td>
			</tr>

			<!-- Import -->
			<tr>
				<th scope="row">
					<label for="scalyn-import-file">
						<?php esc_html_e( 'Import Settings', 'scalyn-qa-assistant' ); ?>
					</label>
				</th>
				<td>
					<input
						type="file"
						id="scalyn-import-file"
						accept=".json"
						class="scalyn-input"
					>
					<button type="button" class="scalyn-btn" id="scalyn-import-settings">
						<?php esc_html_e( 'Import Settings', 'scalyn-qa-assistant' ); ?>
					</button>
					<p class="scalyn-field-description">
						<?php esc_html_e( 'Upload a previously exported JSON file to restore settings. API keys will not be overwritten.', 'scalyn-qa-assistant' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php
		$backup = get_option( 'scalyn_qa_settings_backup', null );
		$has_backup = is_array( $backup ) && ! empty( $backup );
		?>
		<div id="scalyn-backup-info" class="scalyn-card scalyn-card--subtle" data-has-backup="<?php echo $has_backup ? '1' : '0'; ?>" style="<?php echo $has_backup ? '' : 'display:none;'; ?>">
			<p>
				<strong><?php esc_html_e( 'Last backup:', 'scalyn-qa-assistant' ); ?></strong>
				<span id="scalyn-backup-date"><?php
					if ( $has_backup && ! empty( $backup['created_at'] ) ) {
						echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $backup['created_at'] ) ) );
					}
				?></span>
				<?php esc_html_e( 'by', 'scalyn-qa-assistant' ); ?>
				<span id="scalyn-backup-by"><?php echo esc_html( $backup['created_by'] ?? '' ); ?></span>
			</p>
			<button id="scalyn-rollback-settings" type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--danger">
				<?php esc_html_e( 'Rollback to Previous Settings', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</div>

	<!-- AI Usage Card -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'AI Usage', 'scalyn-qa-assistant' ); ?></h2>

		<form id="scalyn-ai-usage-form" method="post">
			<table class="scalyn-form-table">
				<!-- Max AI Requests Per Day -->
				<tr>
					<th scope="row">
						<label for="scalyn-max-ai-requests">
							<?php esc_html_e( 'Max AI Requests Per Day', 'scalyn-qa-assistant' ); ?>
						</label>
					</th>
					<td>
						<select
							id="scalyn-max-ai-requests"
							name="max_ai_requests_per_day"
							class="scalyn-input"
						>
							<option value="0" <?php selected( $max_ai_requests_per_day, 0 ); ?>>
								<?php esc_html_e( 'Unlimited', 'scalyn-qa-assistant' ); ?>
							</option>
							<option value="100" <?php selected( $max_ai_requests_per_day, 100 ); ?>>
								100
							</option>
							<option value="500" <?php selected( $max_ai_requests_per_day, 500 ); ?>>
								500
							</option>
							<option value="1000" <?php selected( $max_ai_requests_per_day, 1000 ); ?>>
								1000
							</option>
						</select>
						<p class="scalyn-field-description">
							<?php esc_html_e( 'Limit the number of AI requests that can be made per day. Set to "Unlimited" for no restriction.', 'scalyn-qa-assistant' ); ?>
						</p>
					</td>
				</tr>

				<!-- AI Usage Log -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'AI Usage Log', 'scalyn-qa-assistant' ); ?>
					</th>
					<td>
						<div id="scalyn-ai-log-container">
							<p class="scalyn-field-description">
								<?php esc_html_e( 'Loading AI usage log...', 'scalyn-qa-assistant' ); ?>
							</p>
						</div>
						<div style="margin-top:0.5rem;">
							<button type="button" id="scalyn-clear-ai-log" class="scalyn-btn scalyn-btn--small scalyn-btn--danger">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								<?php esc_html_e( 'Clear AI Log', 'scalyn-qa-assistant' ); ?>
							</button>
						</div>
					</td>
				</tr>
			</table>

			<div class="scalyn-form-actions">
				<button type="submit" class="scalyn-btn" id="scalyn-save-ai-usage">
					<?php esc_html_e( 'Save AI Limits', 'scalyn-qa-assistant' ); ?>
				</button>
				<span class="scalyn-save-status" id="scalyn-ai-usage-status" aria-live="polite"></span>
			</div>
		</form>
	</div>

	<!-- Debug Mode Card -->
	<div class="scalyn-card">
		<h2 class="scalyn-card-title"><?php esc_html_e( 'Debug Mode', 'scalyn-qa-assistant' ); ?></h2>
		<div class="scalyn-toggle-row">
			<input
				type="checkbox"
				id="scalyn-debug-mode"
				<?php checked( ! empty( $settings['debug_mode'] ) ); ?>
				class="scalyn-toggle-checkbox"
			>
			<label for="scalyn-debug-mode" class="scalyn-toggle-label">
				<strong><?php esc_html_e( 'Enable Debug Logging', 'scalyn-qa-assistant' ); ?></strong>
				<span class="scalyn-field-description">
					<?php esc_html_e( 'When enabled, logs AI failures, link checker failures, and REST API errors. Useful for troubleshooting.', 'scalyn-qa-assistant' ); ?>
				</span>
			</label>
		</div>
		<div class="scalyn-card">
			<h3><?php esc_html_e( 'Debug Log', 'scalyn-qa-assistant' ); ?></h3>
			<div class="scalyn-header-actions">
				<select id="scalyn-debug-filter">
					<option value=""><?php esc_html_e( 'All Categories', 'scalyn-qa-assistant' ); ?></option>
					<option value="ai"><?php esc_html_e( 'AI', 'scalyn-qa-assistant' ); ?></option>
					<option value="link_checker"><?php esc_html_e( 'Link Checker', 'scalyn-qa-assistant' ); ?></option>
					<option value="rest_api"><?php esc_html_e( 'REST API', 'scalyn-qa-assistant' ); ?></option>
				</select>
				<button id="scalyn-clear-debug-log" class="scalyn-btn scalyn-btn--danger" type="button">
					<?php esc_html_e( 'Clear Log', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
			<div id="scalyn-debug-log-container">
				<p><?php esc_html_e( 'Loading debug log...', 'scalyn-qa-assistant' ); ?></p>
			</div>
		</div>
	</div>

</div>
