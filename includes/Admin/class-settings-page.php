<?php
/**
 * Settings Page.
 *
 * Renders the plugin settings with tab routing.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Page
 *
 * Renders the settings page with tabbed navigation (general, ai-providers,
 * templates, wizard). Saving is handled via REST API.
 *
 * @since 1.0.0
 */
class Settings_Page {

	/**
	 * Available settings tabs.
	 *
	 * @var array<string, string>
	 */
	private const TABS = array(
		'general'      => 'General',
		'ai-providers' => 'AI Providers',
		'page-audits'  => 'Page Audits',
		'launch'       => 'Launch Checklist',
		'wizard'       => 'Setup Wizard',
		'advanced'     => 'Advanced',
		'report'       => 'Generate Report',
	);

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		if ( ! array_key_exists( $current_tab, self::TABS ) ) {
			$current_tab = 'general';
		}

		$settings = get_option( 'scalyn_qa_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$tabs = array();
		foreach ( self::TABS as $slug => $label ) {
			$tabs[ $slug ] = array(
				'label'  => __( $label, 'scalyn-qa-assistant' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				'url'    => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['settings'] . '&tab=' . $slug ),
				'active' => $slug === $current_tab,
			);
		}

		// Merge AI config into settings for template access.
		$ai_config = get_option( 'scalyn_qa_ai_config', array() );
		if ( is_array( $ai_config ) ) {
			// Map AI config values to the flat field names the template expects.
			if ( ! empty( $ai_config['providers'] ) && is_array( $ai_config['providers'] ) ) {
				foreach ( $ai_config['providers'] as $provider_key => $provider_data ) {
					if ( ! empty( $provider_data['api_key'] ) ) {
						// Mark as configured — don't put any value in the field.
						// The template will show a placeholder instead.
						$settings[ $provider_key . '_api_key' ] = '__configured__';
					}
					if ( ! empty( $provider_data['model'] ) ) {
						$settings[ $provider_key . '_model' ] = $provider_data['model'];
					}
					// Determine role from primary/fallback config.
					if ( ( $ai_config['primary'] ?? '' ) === $provider_key ) {
						$settings[ $provider_key . '_role' ] = 'primary';
					} elseif ( ( $ai_config['fallback'] ?? '' ) === $provider_key ) {
						$settings[ $provider_key . '_role' ] = 'fallback';
					} else {
						$settings[ $provider_key . '_role' ] = 'disabled';
					}
				}
			}
			// Custom endpoint extra fields.
			if ( ! empty( $ai_config['providers']['custom'] ) ) {
				$custom = $ai_config['providers']['custom'];
				if ( ! empty( $custom['endpoint'] ) ) {
					$settings['custom_endpoint'] = $custom['endpoint'];
				}
				if ( ! empty( $custom['model_name'] ) ) {
					$settings['custom_model_name'] = $custom['model_name'];
				}
				if ( ! empty( $custom['custom_headers'] ) ) {
					$settings['custom_headers'] = is_array( $custom['custom_headers'] )
						? wp_json_encode( $custom['custom_headers'], JSON_PRETTY_PRINT )
						: $custom['custom_headers'];
				}
			}

			if ( ! empty( $ai_config['enabled'] ) ) {
				$settings['enable_ai'] = true;
			}
		}

		// Merge page audit settings.
		$page_audit_settings = get_option( 'scalyn_qa_page_audit_settings', array() );
		if ( is_array( $page_audit_settings ) ) {
			$settings['page_audit_settings'] = $page_audit_settings;
		}

		// Merge launch checklist settings.
		$launch_settings = get_option( 'scalyn_qa_launch_settings', array() );
		if ( is_array( $launch_settings ) ) {
			$settings['launch_settings'] = $launch_settings;
		}

		// Merge report settings.
		$report_settings = get_option( 'scalyn_qa_report_settings', array() );
		if ( is_array( $report_settings ) ) {
			$settings['report_settings'] = $report_settings;
		}

		$data = array(
			'tabs'        => $tabs,
			'current_tab' => $current_tab,
			'settings'    => $settings,
			'post_types'  => $this->get_available_post_types(),
		);

		$template = $this->get_tab_template( $current_tab );

		$this->load_template( $template, $data );
	}

	/**
	 * Get the template path for a given tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab The current tab slug.
	 * @return string Template path relative to templates/.
	 */
	private function get_tab_template( string $tab ): string {
		return match ( $tab ) {
			'ai-providers' => 'settings/ai-providers.php',
			'page-audits'  => 'settings/page-audits.php',
			'launch'       => 'settings/launch.php',
			'wizard'       => 'settings/wizard.php',
			'advanced'     => 'settings/advanced.php',
			'report'       => 'settings/report.php',
			default        => 'settings/general.php',
		};
	}

	/**
	 * Get all public post types available for configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Post type slug => label.
	 */
	private function get_available_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects',
		);

		$options = array();

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$options[ $post_type->name ] = $post_type->label;
		}

		return $options;
	}

	/**
	 * Get the default settings values.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'post_types'               => array( 'post', 'page' ),
			'auto_scan_on_save'        => true,
			'green_threshold'          => 80,
			'yellow_threshold'         => 50,
			'enable_toolbar'           => true,
			'enable_ai'               => false,
			'ai_provider'              => '',
			'ai_api_key'               => '',
			'ai_model'                 => '',
			'check_links'              => true,
			'check_images'             => true,
			'min_word_count'           => 300,
			'max_title_length'         => 60,
			'max_desc_length'          => 160,
			'delete_data_on_uninstall' => false,
			'max_ai_requests_per_day'  => 0,
			'debug_mode'               => false,
			'github_owner'             => 'scalyn-invited',
			'github_repo'              => 'scalyn-qa-assistant',
			'github_token'             => '',
		);
	}

	/**
	 * Load a template file with the given data extracted into scope.
	 *
	 * @since 1.0.0
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
