<?php
/**
 * Template: System Information.
 *
 * Displays diagnostic information about the plugin, environment,
 * SEO/AI integrations, data counts, PHP extensions, and migration log.
 *
 * @package Scalyn\QA\Templates
 * @since   1.2.0
 *
 * @var array $info Collected system information.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$info = isset( $info ) ? $info : array();

$post_types_display = ! empty( $info['post_types'] ) && is_array( $info['post_types'] )
	? implode( ', ', $info['post_types'] )
	: 'post, page';

// --- Status helper functions ---------------------------------------------------

/**
 * Compare a version string against thresholds and return 'green', 'yellow', or 'red'.
 */
function scalyn_version_status( string $version, string $green_min, string $yellow_min ): string {
	if ( version_compare( $version, $green_min, '>=' ) ) {
		return 'green';
	}
	if ( version_compare( $version, $yellow_min, '>=' ) ) {
		return 'yellow';
	}
	return 'red';
}

/**
 * Parse a memory string like "256M" or "512M" to megabytes.
 */
function scalyn_parse_memory_mb( string $memory ): int {
	$memory = strtoupper( trim( $memory ) );
	$value  = (int) $memory;
	if ( strpos( $memory, 'G' ) !== false ) {
		$value *= 1024;
	}
	return $value;
}

/**
 * Return a recommendation note for a given status color.
 */
function scalyn_version_note( string $status, string $label, string $recommended ): string {
	if ( 'green' === $status ) {
		return 'Meets requirements';
	}
	if ( 'yellow' === $status ) {
		return 'Acceptable &mdash; ' . esc_html( $recommended ) . '+ recommended';
	}
	return esc_html( $label ) . ' is below minimum &mdash; upgrade to ' . esc_html( $recommended ) . '+';
}

// Pre-compute statuses for environment rows.
$php_version_raw = $info['php_version'] ?? '0';
$php_status      = scalyn_version_status( $php_version_raw, '8.2', '8.0' );
$php_note        = scalyn_version_note( $php_status, 'PHP', '8.2' );

$wp_version_raw = $info['wp_version'] ?? '0';
$wp_status      = scalyn_version_status( $wp_version_raw, '6.0', '5.0' );
$wp_note        = scalyn_version_note( $wp_status, 'WordPress', '6.0' );

$mysql_version_raw = $info['mysql_version'] ?? '0';
$mysql_status      = scalyn_version_status( $mysql_version_raw, '5.7', '5.6' );
$mysql_note        = scalyn_version_note( $mysql_status, 'MySQL', '5.7' );

$memory_raw = $info['memory_limit'] ?? '0';
$memory_mb  = scalyn_parse_memory_mb( (string) $memory_raw );
if ( $memory_mb >= 256 ) {
	$memory_status = 'green';
	$memory_note   = 'Meets requirements';
} elseif ( $memory_mb >= 128 ) {
	$memory_status = 'yellow';
	$memory_note   = 'Acceptable &mdash; 256M+ recommended';
} else {
	$memory_status = 'red';
	$memory_note   = 'Too low &mdash; increase to 256M+';
}

$exec_time_raw = (int) ( $info['max_execution_time'] ?? 0 );
if ( $exec_time_raw >= 60 ) {
	$exec_status = 'green';
	$exec_note   = 'Meets requirements';
} elseif ( $exec_time_raw >= 30 ) {
	$exec_status = 'yellow';
	$exec_note   = 'Acceptable &mdash; 60s+ recommended';
} else {
	$exec_status = 'red';
	$exec_note   = 'Too low &mdash; increase to 60s+';
}

// SEO & AI statuses.
$seo_plugin_name = ! empty( $info['seo_plugin'] ) ? $info['seo_plugin'] : '';
$seo_status      = $seo_plugin_name ? 'green' : 'gray';
$seo_display     = $seo_plugin_name ? $seo_plugin_name : 'Not Installed';
$seo_note        = $seo_plugin_name ? 'Detected and active' : 'Install an SEO plugin for full QA analysis';

$ai_provider_name = ! empty( $info['ai_provider'] ) ? $info['ai_provider'] : '';
$ai_status        = $ai_provider_name ? 'green' : 'gray';
$ai_display       = $ai_provider_name ? $ai_provider_name : 'Not Configured';
$ai_note          = $ai_provider_name ? 'Configured and ready' : 'Configure an AI provider in Settings';

$ai_enabled       = ! empty( $info['ai_enabled'] );
$ai_enabled_status = $ai_enabled ? 'green' : 'gray';

$debug_mode      = ! empty( $info['debug_mode'] );
$debug_status    = $debug_mode ? 'blue' : 'gray';
$debug_note      = $debug_mode ? 'Verbose logging active &mdash; disable in production' : 'Normal operation';

$auto_scan         = ! empty( $info['auto_scan_on_save'] );
$auto_scan_status  = $auto_scan ? 'green' : 'gray';
$auto_scan_note    = $auto_scan ? 'Posts are scanned automatically on save' : 'Manual scans only';

// PHP extension descriptions.
$ext_descriptions = array(
	'openssl'  => 'Required for API key encryption',
	'mbstring' => 'Required for multi-byte content analysis',
	'curl'     => 'Required for HTTP requests (link checking, AI)',
	'dom'      => 'Required for HTML parsing (DOMDocument)',
	'json'     => 'Required for REST API and data storage',
);

// Build plain-text version for clipboard copy.
$plain_text  = "=== Scalyn QA System Info ===\n\n";
$plain_text .= "--- Plugin ---\n";
$plain_text .= 'Plugin Version: ' . esc_html( $info['plugin_version'] ?? 'Unknown' ) . "\n";
$plain_text .= 'Debug Mode: ' . ( $debug_mode ? 'Enabled' : 'Disabled' ) . "\n";
$plain_text .= 'Auto-Scan on Save: ' . ( $auto_scan ? 'Enabled' : 'Disabled' ) . "\n";
$plain_text .= 'Post Types Scanned: ' . $post_types_display . "\n\n";

$plain_text .= "--- Environment ---\n";
$plain_text .= 'WordPress Version: ' . esc_html( $info['wp_version'] ?? '' ) . "\n";
$plain_text .= 'PHP Version: ' . esc_html( $info['php_version'] ?? '' ) . "\n";
$plain_text .= 'MySQL Version: ' . esc_html( $info['mysql_version'] ?? '' ) . "\n";
$plain_text .= 'Web Server: ' . esc_html( $info['web_server'] ?? '' ) . "\n";
$plain_text .= 'Active Theme: ' . esc_html( $info['active_theme'] ?? '' ) . "\n";
$plain_text .= 'Memory Limit: ' . esc_html( $info['memory_limit'] ?? '' ) . "\n";
$plain_text .= 'Max Execution Time: ' . esc_html( $info['max_execution_time'] ?? '' ) . "s\n";
$plain_text .= 'Max Input Time: ' . esc_html( $info['max_input_time'] ?? '' ) . "s\n";
$plain_text .= 'Post Max Size: ' . esc_html( $info['post_max_size'] ?? '' ) . "\n";
$plain_text .= 'Upload Max Size: ' . esc_html( $info['upload_max_size'] ?? '' ) . "\n\n";

$plain_text .= "--- SEO & AI ---\n";
$plain_text .= 'Active SEO Plugin: ' . ( $seo_plugin_name ? esc_html( $seo_plugin_name ) : 'None' ) . "\n";
$plain_text .= 'AI Enabled: ' . ( $ai_enabled ? 'Yes' : 'No' ) . "\n";
$plain_text .= 'AI Provider: ' . ( $ai_provider_name ? esc_html( $ai_provider_name ) : 'Not Configured' ) . "\n\n";

$plain_text .= "--- Data ---\n";
$plain_text .= 'Total Posts Scanned: ' . (int) ( $info['total_scanned'] ?? 0 ) . "\n";
$plain_text .= 'Total Snapshots: ' . (int) ( $info['total_snapshots'] ?? 0 ) . "\n";
$plain_text .= 'Link Cache Entries: ' . (int) ( $info['link_cache_count'] ?? 0 ) . "\n\n";

$plain_text .= "--- PHP Extensions ---\n";
if ( ! empty( $info['php_extensions'] ) && is_array( $info['php_extensions'] ) ) {
	foreach ( $info['php_extensions'] as $ext => $loaded ) {
		$plain_text .= $ext . ': ' . ( $loaded ? 'Installed' : 'Missing' ) . "\n";
	}
}
$plain_text .= "\n";

$plain_text .= "--- Migration Log ---\n";
if ( ! empty( $info['migration_log'] ) && is_array( $info['migration_log'] ) ) {
	foreach ( $info['migration_log'] as $entry ) {
		$version     = $entry['version'] ?? 'unknown';
		$date        = $entry['date'] ?? $entry['migrated_at'] ?? 'unknown';
		$entry_status = $entry['status'] ?? 'completed';
		$plain_text .= $version . ' - ' . $date . ' (' . $entry_status . ")\n";
	}
} else {
	$plain_text .= "No migrations run\n";
}
?>
<div class="scalyn-wrap">

	<div class="scalyn-page-header">
		<div class="scalyn-page-header__intro">
			<h1><?php esc_html_e( 'System Information', 'scalyn-qa-assistant' ); ?></h1>
			<p class="scalyn-page-header__description"><?php esc_html_e( 'Environment details, plugin configuration, and diagnostic information.', 'scalyn-qa-assistant' ); ?></p>
		</div>
		<div class="scalyn-page-header__actions">
			<button id="scalyn-copy-sysinfo" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary" type="button">
				<?php esc_html_e( 'Copy to Clipboard', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</div>

	<!-- ================================================================
	     Plugin
	     ================================================================ -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'Plugin', 'scalyn-qa-assistant' ); ?></h2>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Plugin Version', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $info['plugin_version'] ?? 'Unknown' ); ?></span>
			<span class="scalyn-status-row__extra"></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $debug_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Debug Mode', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php if ( $debug_mode ) : ?>
					<span class="scalyn-badge scalyn-badge--blue"><?php esc_html_e( 'Enabled', 'scalyn-qa-assistant' ); ?></span>
				<?php else : ?>
					<span class="scalyn-badge scalyn-badge--gray"><?php esc_html_e( 'Disabled', 'scalyn-qa-assistant' ); ?></span>
				<?php endif; ?>
			</span>
			<span class="scalyn-status-row__extra"><?php echo $debug_note; ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $auto_scan_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Auto-Scan on Save', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php if ( $auto_scan ) : ?>
					<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( 'Enabled', 'scalyn-qa-assistant' ); ?></span>
				<?php else : ?>
					<span class="scalyn-badge scalyn-badge--gray"><?php esc_html_e( 'Disabled', 'scalyn-qa-assistant' ); ?></span>
				<?php endif; ?>
			</span>
			<span class="scalyn-status-row__extra"><?php echo esc_html( $auto_scan_note ); ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Post Types Scanned', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $post_types_display ); ?></span>
			<span class="scalyn-status-row__extra"></span>
		</div>
	</div>

	<!-- ================================================================
	     Environment
	     ================================================================ -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'Environment', 'scalyn-qa-assistant' ); ?></h2>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $wp_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'WordPress Version', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php echo esc_html( $info['wp_version'] ?? 'Unknown' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $wp_status ); ?>">
					<?php echo 'green' === $wp_status ? 'OK' : ( 'yellow' === $wp_status ? 'Acceptable' : 'Outdated' ); ?>
				</span>
			</span>
			<span class="scalyn-status-row__extra"><?php echo $wp_note; ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $php_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'PHP Version', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php echo esc_html( $info['php_version'] ?? 'Unknown' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $php_status ); ?>">
					<?php echo 'green' === $php_status ? 'OK' : ( 'yellow' === $php_status ? 'Acceptable' : 'Outdated' ); ?>
				</span>
			</span>
			<span class="scalyn-status-row__extra"><?php echo $php_note; ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $mysql_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'MySQL Version', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php echo esc_html( $info['mysql_version'] ?? 'Unknown' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $mysql_status ); ?>">
					<?php echo 'green' === $mysql_status ? 'OK' : ( 'yellow' === $mysql_status ? 'Acceptable' : 'Outdated' ); ?>
				</span>
			</span>
			<span class="scalyn-status-row__extra"><?php echo $mysql_note; ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Web Server', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $info['web_server'] ?? 'Unknown' ); ?></span>
			<span class="scalyn-status-row__extra"><?php esc_html_e( 'Informational', 'scalyn-qa-assistant' ); ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Active Theme', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $info['active_theme'] ?? 'Unknown' ); ?></span>
			<span class="scalyn-status-row__extra"><?php esc_html_e( 'Informational', 'scalyn-qa-assistant' ); ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $memory_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Memory Limit', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php echo esc_html( $info['memory_limit'] ?? 'Unknown' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $memory_status ); ?>">
					<?php echo 'green' === $memory_status ? 'OK' : ( 'yellow' === $memory_status ? 'Low' : 'Critical' ); ?>
				</span>
			</span>
			<span class="scalyn-status-row__extra"><?php echo $memory_note; ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $exec_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Max Execution Time', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php echo esc_html( $info['max_execution_time'] ?? '0' ); ?>s
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $exec_status ); ?>">
					<?php echo 'green' === $exec_status ? 'OK' : ( 'yellow' === $exec_status ? 'Low' : 'Critical' ); ?>
				</span>
			</span>
			<span class="scalyn-status-row__extra"><?php echo $exec_note; ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Max Input Time', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $info['max_input_time'] ?? '0' ); ?>s</span>
			<span class="scalyn-status-row__extra"><?php esc_html_e( 'Maximum time to parse input data', 'scalyn-qa-assistant' ); ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Post Max Size', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $info['post_max_size'] ?? 'Unknown' ); ?></span>
			<span class="scalyn-status-row__extra"><?php esc_html_e( 'Maximum size of POST data', 'scalyn-qa-assistant' ); ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--green"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Upload Max Size', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( $info['upload_max_size'] ?? 'Unknown' ); ?></span>
			<span class="scalyn-status-row__extra"><?php esc_html_e( 'Maximum file upload size', 'scalyn-qa-assistant' ); ?></span>
		</div>
	</div>

	<!-- ================================================================
	     SEO & AI
	     ================================================================ -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'SEO & AI', 'scalyn-qa-assistant' ); ?></h2>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $seo_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Active SEO Plugin', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php if ( $seo_plugin_name ) : ?>
					<?php echo esc_html( $seo_display ); ?>
					<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( 'Detected', 'scalyn-qa-assistant' ); ?></span>
				<?php else : ?>
					<span class="scalyn-badge scalyn-badge--gray"><?php esc_html_e( 'Not Installed', 'scalyn-qa-assistant' ); ?></span>
				<?php endif; ?>
			</span>
			<span class="scalyn-status-row__extra"><?php echo esc_html( $seo_note ); ?></span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $ai_enabled_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'AI Enabled', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php if ( $ai_enabled ) : ?>
					<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( 'Yes', 'scalyn-qa-assistant' ); ?></span>
				<?php else : ?>
					<span class="scalyn-badge scalyn-badge--gray"><?php esc_html_e( 'No', 'scalyn-qa-assistant' ); ?></span>
				<?php endif; ?>
			</span>
			<span class="scalyn-status-row__extra">
				<?php echo $ai_enabled
					? esc_html__( 'AI-powered suggestions are active', 'scalyn-qa-assistant' )
					: esc_html__( 'Enable AI in Settings for content suggestions', 'scalyn-qa-assistant' ); ?>
			</span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $ai_status ); ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'AI Provider', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value">
				<?php if ( $ai_provider_name ) : ?>
					<?php echo esc_html( $ai_display ); ?>
					<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( 'Configured', 'scalyn-qa-assistant' ); ?></span>
				<?php else : ?>
					<span class="scalyn-badge scalyn-badge--gray"><?php esc_html_e( 'Not Configured', 'scalyn-qa-assistant' ); ?></span>
				<?php endif; ?>
			</span>
			<span class="scalyn-status-row__extra"><?php echo esc_html( $ai_note ); ?></span>
		</div>
	</div>

	<!-- ================================================================
	     Data
	     ================================================================ -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'Data', 'scalyn-qa-assistant' ); ?></h2>

		<?php
		$total_scanned    = (int) ( $info['total_scanned'] ?? 0 );
		$total_snapshots  = (int) ( $info['total_snapshots'] ?? 0 );
		$link_cache_count = (int) ( $info['link_cache_count'] ?? 0 );
		?>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo $total_scanned > 0 ? 'green' : 'gray'; ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Total Posts Scanned', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( number_format_i18n( $total_scanned ) ); ?></span>
			<span class="scalyn-status-row__extra">
				<?php echo $total_scanned > 0
					? esc_html__( 'Scans have been performed', 'scalyn-qa-assistant' )
					: esc_html__( 'No scans yet &mdash; run your first scan', 'scalyn-qa-assistant' ); ?>
			</span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo $total_snapshots > 0 ? 'green' : 'gray'; ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Total Snapshots', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( number_format_i18n( $total_snapshots ) ); ?></span>
			<span class="scalyn-status-row__extra">
				<?php echo $total_snapshots > 0
					? esc_html__( 'Content snapshots stored', 'scalyn-qa-assistant' )
					: esc_html__( 'No snapshots recorded', 'scalyn-qa-assistant' ); ?>
			</span>
		</div>

		<div class="scalyn-status-row">
			<span class="scalyn-status__dot scalyn-status__dot--<?php echo $link_cache_count > 0 ? 'green' : 'gray'; ?>"></span>
			<span class="scalyn-status-row__label"><?php esc_html_e( 'Link Cache Entries', 'scalyn-qa-assistant' ); ?></span>
			<span class="scalyn-status-row__value"><?php echo esc_html( number_format_i18n( $link_cache_count ) ); ?></span>
			<span class="scalyn-status-row__extra">
				<?php echo $link_cache_count > 0
					? esc_html__( 'Cached link check results', 'scalyn-qa-assistant' )
					: esc_html__( 'Cache is empty', 'scalyn-qa-assistant' ); ?>
			</span>
		</div>
	</div>

	<!-- ================================================================
	     PHP Extensions
	     ================================================================ -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'PHP Extensions', 'scalyn-qa-assistant' ); ?></h2>

		<?php if ( ! empty( $info['php_extensions'] ) && is_array( $info['php_extensions'] ) ) : ?>
			<?php foreach ( $info['php_extensions'] as $ext_name => $ext_loaded ) : ?>
				<?php
				$ext_status = $ext_loaded ? 'green' : 'red';
				$ext_desc   = $ext_descriptions[ $ext_name ] ?? '';
				?>
				<div class="scalyn-status-row">
					<span class="scalyn-status__dot scalyn-status__dot--<?php echo esc_attr( $ext_status ); ?>"></span>
					<span class="scalyn-status-row__label"><code><?php echo esc_html( $ext_name ); ?></code></span>
					<span class="scalyn-status-row__value">
						<?php if ( $ext_loaded ) : ?>
							<span class="scalyn-badge scalyn-badge--green"><?php esc_html_e( 'Installed', 'scalyn-qa-assistant' ); ?></span>
						<?php else : ?>
							<span class="scalyn-badge scalyn-badge--red"><?php esc_html_e( 'Missing', 'scalyn-qa-assistant' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="scalyn-status-row__extra">
						<?php echo esc_html( $ext_desc ); ?>
						<?php if ( ! $ext_loaded && $ext_desc ) : ?>
							&mdash; <strong><?php esc_html_e( 'action needed', 'scalyn-qa-assistant' ); ?></strong>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<p class="scalyn-card__subtitle"><?php esc_html_e( 'No extension data available.', 'scalyn-qa-assistant' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- ================================================================
	     Migration Log
	     ================================================================ -->
	<div class="scalyn-card">
		<h2><?php esc_html_e( 'Migration Log', 'scalyn-qa-assistant' ); ?></h2>

		<?php if ( ! empty( $info['migration_log'] ) && is_array( $info['migration_log'] ) ) : ?>
			<table class="scalyn-table scalyn-table--striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Version', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Date', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Status', 'scalyn-qa-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $info['migration_log'] as $entry ) : ?>
						<?php
						$m_version = $entry['version'] ?? 'unknown';
						$m_date    = $entry['date'] ?? $entry['migrated_at'] ?? 'unknown';
						$m_status  = $entry['status'] ?? 'completed';
						if ( 'completed' === $m_status ) {
							$m_badge = 'green';
						} elseif ( 'failed' === $m_status ) {
							$m_badge = 'red';
						} else {
							$m_badge = 'yellow';
						}
						?>
						<tr>
							<td><?php echo esc_html( $m_version ); ?></td>
							<td><?php echo esc_html( $m_date ); ?></td>
							<td>
								<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $m_badge ); ?>">
									<?php echo esc_html( ucfirst( $m_status ) ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="scalyn-card__subtitle"><?php esc_html_e( 'No migrations have been run.', 'scalyn-qa-assistant' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Hidden textarea for clipboard copy -->
	<textarea id="scalyn-sysinfo-text" style="position:absolute;left:-9999px;" readonly><?php echo esc_textarea( $plain_text ); ?></textarea>

</div>

<script>
(function () {
	'use strict';

	var copyBtn = document.getElementById('scalyn-copy-sysinfo');
	if (!copyBtn) return;

	copyBtn.addEventListener('click', function () {
		var textarea = document.getElementById('scalyn-sysinfo-text');
		if (!textarea) return;

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(textarea.value).then(function () {
				copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'scalyn-qa-assistant' ) ); ?>';
				setTimeout(function () {
					copyBtn.textContent = '<?php echo esc_js( __( 'Copy to Clipboard', 'scalyn-qa-assistant' ) ); ?>';
				}, 2000);
			}).catch(function () {
				fallbackCopy(textarea);
			});
		} else {
			fallbackCopy(textarea);
		}
	});

	function fallbackCopy(textarea) {
		textarea.style.position = 'static';
		textarea.style.left = 'auto';
		textarea.select();

		try {
			document.execCommand('copy');
			copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'scalyn-qa-assistant' ) ); ?>';
			setTimeout(function () {
				copyBtn.textContent = '<?php echo esc_js( __( 'Copy to Clipboard', 'scalyn-qa-assistant' ) ); ?>';
			}, 2000);
		} catch (err) {
			copyBtn.textContent = '<?php echo esc_js( __( 'Copy failed', 'scalyn-qa-assistant' ) ); ?>';
		}

		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
	}
})();
</script>
