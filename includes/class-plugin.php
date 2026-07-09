<?php
/**
 * Main Plugin class.
 *
 * @package Scalyn\QA
 */

declare(strict_types=1);

namespace Scalyn\QA;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Scalyn\QA\Traits\Singleton;

/**
 * Class Plugin
 *
 * Bootstraps the entire Scalyn QA Assistant plugin.
 */
final class Plugin {

	use Singleton;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $version = SCALYN_QA_VERSION;

	/**
	 * Initialized service instances.
	 *
	 * @var array<string, object>
	 */
	private array $services = [];

	/**
	 * Initialize the plugin.
	 *
	 * Called automatically from the Singleton constructor.
	 */
	private function init(): void {
		Migrator::run();
		$this->register_services();
		$this->register_hooks();

		/**
		 * Fires after the Scalyn QA plugin has fully loaded.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'scalyn_qa_loaded', $this );
	}

	/**
	 * Register all plugin services.
	 */
	private function register_services(): void {
		// Admin services.
		$this->services['admin_menu']   = Admin\Admin_Menu::instance();
		$this->services['admin_assets'] = Admin\Admin_Assets::instance();
		// Metabox disabled — QA Inspector on frontend replaces in-editor checklist.
		// $this->services['metabox']      = Admin\Metabox::instance();
		$this->services['toolbar']      = Admin\Toolbar::instance();

		// GitHub updater — register update hooks.
		$this->services['github_updater'] = new Updates\GitHub_Updater();
		$this->services['github_updater']->register_hooks();

		// Analyzer registry — register all built-in analyzers.
		$this->services['analyzer_registry'] = new Analyzers\Analyzer_Registry();
		$registry = $this->services['analyzer_registry'];

		add_action(
			'init',
			function () use ( $registry ): void {
				// Register AI providers before analyzers.
				AI\AI_Provider_Registry::register_defaults();

				$registry->register( new Analyzers\SEO_Analyzer() );
				$registry->register( new Analyzers\Content_Analyzer() );
				// Note: Heading_Analyzer is NOT registered separately — Content_Analyzer delegates to it.
				$registry->register( new Analyzers\Link_Checker() );
				$registry->register( new Analyzers\Form_Button_Analyzer() );
				$registry->register( new Analyzers\Image_Optimization_Analyzer() );
				$registry->register( new Analyzers\HTML_Quality_Analyzer() );

				/**
				 * Fires when Scalyn QA analyzers should be registered.
				 * Use this hook to register custom analyzers.
				 *
				 * @param Analyzers\Analyzer_Registry $registry The analyzer registry.
				 */
				do_action( 'scalyn_qa_register_analyzers', $registry );

				// Expose the registry via filter so the metabox auto-scan can access it.
				add_filter(
					'scalyn_qa_analyzer_registry',
					static function () use ( $registry ): Analyzers\Analyzer_Registry {
						return $registry;
					}
				);
			}
		);

		// REST API controllers — registered on rest_api_init.
		add_action(
			'rest_api_init',
			function () use ( $registry ): void {
				$controllers = [
					new Rest\Scan_Controller( $registry ),
					new Rest\Score_Controller(),
					new Rest\Launch_Controller(),
					new Rest\AI_Controller(),
					new Rest\Ignore_Controller(),
					new Rest\Notes_Controller(),
					new Rest\Snapshot_Controller(),
					new Rest\Settings_Controller(),
				];

				foreach ( $controllers as $controller ) {
					$controller->register_routes();
				}
			}
		);
	}

	/**
	 * Register global plugin hooks.
	 */
	private function register_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Output Local Business JSON-LD if configured via Launch Checklist auto-fix.
		if ( get_option( 'scalyn_qa_local_business_jsonld' ) ) {
			add_action( 'wp_head', [ Launch\Launch_Checker::class, 'output_local_business_jsonld' ] );
		}

		// Generate QA Report (admin-post handler).
		add_action( 'admin_post_scalyn_qa_generate_report', [ Admin\Report_Generator::class, 'handle_request' ] );
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'scalyn-qa-assistant',
			false,
			dirname( SCALYN_QA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}
}
