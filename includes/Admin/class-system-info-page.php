<?php
/**
 * System Info Page.
 *
 * Renders the system information diagnostic page.
 *
 * @package Scalyn\QA\Admin
 * @since   1.2.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Integrations\SEO_Integration;

/**
 * Class System_Info_Page
 *
 * Collects environment, plugin, and data diagnostics and renders
 * the system information template.
 *
 * @since 1.2.0
 */
class System_Info_Page {

	/**
	 * Render the system info page.
	 *
	 * @since 1.2.0
	 */
	public function render(): void {
		$data = array(
			'info' => $this->collect_info(),
		);

		$this->load_template( 'system-info.php', $data );
	}

	/**
	 * Collect all system information.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed>
	 */
	private function collect_info(): array {
		global $wpdb;

		$settings  = get_option( 'scalyn_qa_settings', array() );
		$ai_config = get_option( 'scalyn_qa_ai_config', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! is_array( $ai_config ) ) {
			$ai_config = array();
		}

		return array(
			// Plugin.
			'plugin_version'    => defined( 'SCALYN_QA_VERSION' ) ? SCALYN_QA_VERSION : 'Unknown',
			'debug_mode'        => ! empty( $settings['debug_mode'] ),
			'auto_scan_on_save' => $settings['auto_scan_on_save'] ?? true,
			'post_types'        => $settings['post_types'] ?? array( 'post', 'page' ),

			// Environment.
			'wp_version'         => get_bloginfo( 'version' ),
			'php_version'        => phpversion(),
			'mysql_version'      => $wpdb->db_version(),
			'web_server'         => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'active_theme'       => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'max_input_time'     => ini_get( 'max_input_time' ),
			'post_max_size'      => ini_get( 'post_max_size' ),
			'upload_max_size'    => ini_get( 'upload_max_filesize' ),

			// SEO & AI.
			'seo_plugin'     => $this->get_seo_plugin_name(),
			'ai_enabled'     => ! empty( $ai_config['enabled'] ),
			'ai_provider'    => $this->get_primary_provider_name( $ai_config ),

			// Data.
			'total_scanned'    => $this->count_scanned_posts(),
			'total_snapshots'  => $this->count_snapshots(),
			'link_cache_count' => $this->count_link_cache_entries(),

			// PHP Extensions.
			'php_extensions' => $this->check_extensions(),

			// Migration Log.
			'migration_log' => $this->get_migration_log(),
		);
	}

	/**
	 * Get the active SEO plugin name.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private function get_seo_plugin_name(): string {
		$integration = SEO_Integration::detect();

		return null !== $integration ? $integration->get_plugin_name() : 'None';
	}

	/**
	 * Get the primary AI provider name from config.
	 *
	 * @since 1.2.0
	 *
	 * @param array $ai_config The AI configuration array.
	 * @return string
	 */
	private function get_primary_provider_name( array $ai_config ): string {
		if ( empty( $ai_config['primary'] ) ) {
			return 'Not Configured';
		}

		$provider_names = array(
			'openai' => 'OpenAI',
			'claude' => 'Claude (Anthropic)',
			'gemini' => 'Gemini (Google)',
		);

		$primary = (string) $ai_config['primary'];

		return $provider_names[ $primary ] ?? ucfirst( $primary );
	}

	/**
	 * Count posts that have scan results meta.
	 *
	 * @since 1.2.0
	 *
	 * @return int
	 */
	private function count_scanned_posts(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT( DISTINCT post_id )
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_scalyn_qa_scan_results'",
		);

		return (int) $count;
	}

	/**
	 * Count total snapshots across all posts.
	 *
	 * @since 1.2.0
	 *
	 * @return int
	 */
	private function count_snapshots(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			"SELECT meta_value
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_scalyn_qa_snapshots'",
		);

		$total = 0;

		foreach ( $rows as $value ) {
			$snapshots = maybe_unserialize( $value );
			if ( is_array( $snapshots ) ) {
				$total += count( $snapshots );
			}
		}

		return $total;
	}

	/**
	 * Count link cache transient entries.
	 *
	 * @since 1.2.0
	 *
	 * @return int
	 */
	private function count_link_cache_entries(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_scalyn_qa_link_%'",
		);

		return (int) $count;
	}

	/**
	 * Check required PHP extensions.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, bool>
	 */
	private function check_extensions(): array {
		$required = array( 'openssl', 'mbstring', 'curl', 'dom', 'json' );
		$results  = array();

		foreach ( $required as $ext ) {
			$results[ $ext ] = extension_loaded( $ext );
		}

		return $results;
	}

	/**
	 * Get the last 5 migration log entries.
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */
	private function get_migration_log(): array {
		$log = get_option( 'scalyn_qa_migration_log', array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_slice( $log, -5 );
	}

	/**
	 * Load a template file with the given data extracted into scope.
	 *
	 * @since 1.2.0
	 *
	 * @param string $template Relative template path (from templates/ directory).
	 * @param array  $data     Data to extract into the template scope.
	 */
	private function load_template( string $template, array $data = array() ): void {
		$template_path = SCALYN_QA_PLUGIN_DIR . 'templates/' . $template;

		if ( ! file_exists( $template_path ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: Template file path. */
						__( 'Template not found: %s', 'scalyn-qa-assistant' ),
						$template,
					),
				),
			);
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );

		include $template_path;
	}
}
