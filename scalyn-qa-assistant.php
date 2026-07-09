<?php
/**
 * Plugin Name: Scalyn QA Assistant
 * Plugin URI:  https://github.com/scalyn-invited/scalyn-qa-assistant
 * Description: Website QA, SEO validation, and launch readiness tool for WordPress.
 * Version:     1.4.9
 * Author:      Scalyn
 * Author URI:  https://scalyn.global/
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: scalyn-qa-assistant
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package Scalyn\QA
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SCALYN_QA_VERSION', '1.4.9' );
define( 'SCALYN_QA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCALYN_QA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCALYN_QA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SCALYN_QA_PLUGIN_FILE', __FILE__ );

/**
 * Require the Composer autoloader.
 *
 * Shows an admin notice if the autoloader has not been generated.
 */
$scalyn_qa_autoloader = SCALYN_QA_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! file_exists( $scalyn_qa_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			$message = esc_html__(
				'Scalyn QA Assistant requires Composer dependencies. Please run "composer install" in the plugin directory.',
				'scalyn-qa-assistant'
			);
			printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
		}
	);
	return;
}

require_once $scalyn_qa_autoloader;

// Activation and deactivation hooks.
register_activation_hook( __FILE__, [ \Scalyn\QA\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Scalyn\QA\Deactivator::class, 'deactivate' ] );

/**
 * Initialize the plugin on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\Scalyn\QA\Plugin::instance();
	}
);

/**
 * Add a "Settings" link on the Plugins page.
 */
add_filter(
	'plugin_action_links_' . SCALYN_QA_PLUGIN_BASENAME,
	static function ( array $links ): array {
		$settings_url  = admin_url( 'admin.php?page=scalyn-qa-settings' );
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'scalyn-qa-assistant' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
);
