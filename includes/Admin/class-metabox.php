<?php
/**
 * Metabox.
 *
 * Registers and renders the Scalyn QA Checklist metabox on post edit screens.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Traits\Singleton;
use Scalyn\QA\Traits\Has_Hooks;
use Scalyn\QA\Models\Scan_Result;
use Scalyn\QA\Scoring\Scoring_Engine;
use Scalyn\QA\Analyzers\Analyzer_Registry;

/**
 * Class Metabox
 *
 * Registers the QA checklist metabox on configured post types and handles
 * auto-scan on save when enabled.
 *
 * @since 1.0.0
 */
final class Metabox {

	use Singleton;
	use Has_Hooks;

	/**
	 * Metabox ID.
	 *
	 * @var string
	 */
	private const METABOX_ID = 'scalyn_qa_checklist';

	/**
	 * Nonce action for metabox saves.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'scalyn_qa_metabox_save';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'scalyn_qa_metabox_nonce';

	/**
	 * Register all WordPress hooks.
	 */
	protected function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'handle_save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_assets' ) );
	}

	/**
	 * Register the QA Checklist metabox on configured post types.
	 *
	 * @since 1.0.0
	 */
	public function register_metabox(): void {
		$post_types = $this->get_configured_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				self::METABOX_ID,
				__( 'Scalyn QA Checklist', 'scalyn-qa-assistant' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'high',
			);
		}
	}

	/**
	 * Render the metabox content.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render( \WP_Post $post ): void {
		// Output nonce field for verification on save.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$scan_result = Scan_Result::load( $post->ID );
		$settings    = get_option( 'scalyn_qa_settings', array() );

		$data = array(
			'post'        => $post,
			'post_id'     => $post->ID,
			'scan_result' => $scan_result,
			'settings'    => is_array( $settings ) ? $settings : array(),
		);

		$template_path = SCALYN_QA_PLUGIN_DIR . 'templates/metabox/checklist.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $data, EXTR_SKIP );
			include $template_path;
		} else {
			$this->render_fallback( $scan_result, $post->ID );
		}
	}

	/**
	 * Handle the save_post action.
	 *
	 * Verifies the nonce and optionally runs a scan if auto_scan_on_save is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 */
	public function handle_save_post( int $post_id, \WP_Post $post ): void {
		// Skip autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Verify nonce.
		if (
			! isset( $_POST[ self::NONCE_NAME ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ),
				self::NONCE_ACTION,
			)
		) {
			return;
		}

		// Check user capability.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Only process configured post types.
		$post_types = $this->get_configured_post_types();

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Check if auto-scan on save is enabled.
		$settings = get_option( 'scalyn_qa_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$auto_scan = $settings['auto_scan_on_save'] ?? true;

		if ( ! $auto_scan ) {
			return;
		}

		// Only scan published posts.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Prevent infinite loops from re-saving.
		remove_action( 'save_post', array( $this, 'handle_save_post' ), 10 );

		$this->run_scan( $post_id );

		// Re-add the hook.
		add_action( 'save_post', array( $this, 'handle_save_post' ), 10, 2 );
	}

	/**
	 * Enqueue metabox-specific JavaScript on post edit screens.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_metabox_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen ) {
			return;
		}

		$post_types = $this->get_configured_post_types();

		if ( ! in_array( $screen->post_type, $post_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'scalyn-qa-metabox',
			SCALYN_QA_PLUGIN_URL . 'assets/js/metabox.js',
			array( 'jquery' ),
			SCALYN_QA_VERSION,
			true,
		);

		wp_localize_script(
			'scalyn-qa-metabox',
			'scalynQA',
			array(
				'restUrl'       => rest_url( 'scalyn-qa/v1/' ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'pluginUrl'     => SCALYN_QA_PLUGIN_URL,
				'currentPostId' => get_the_ID(),
				'settings'      => get_option( 'scalyn_qa_settings', array() ),
			),
		);
	}

	/**
	 * Run a scan on the given post and save results.
	 *
	 * During auto-scan on save, slow analyzers (e.g., link checker) are skipped
	 * to avoid timeouts. Cached results from the last full scan are merged in.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to scan.
	 */
	private function run_scan( int $post_id ): void {
		/**
		 * Filters the analyzer registry instance used for scanning.
		 *
		 * @since 1.0.0
		 *
		 * @param Analyzer_Registry|null $registry The analyzer registry or null to use default.
		 */
		$registry = apply_filters( 'scalyn_qa_analyzer_registry', null );

		if ( ! $registry instanceof Analyzer_Registry ) {
			return;
		}

		/**
		 * Filters the list of analyzer IDs to skip during auto-scan on save.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $skip_ids Analyzer IDs to skip.
		 */
		$skip_on_save = (array) apply_filters( 'scalyn_qa_skip_on_autoscan', array( 'link_checker' ) );

		// Run only fast analyzers during auto-scan.
		$results = array();
		foreach ( $registry->get_all() as $analyzer ) {
			if ( in_array( $analyzer->get_id(), $skip_on_save, true ) ) {
				continue;
			}
			$category = $analyzer->get_category();
			if ( ! isset( $results[ $category ] ) ) {
				$results[ $category ] = array();
			}
			$results[ $category ] = array_merge( $results[ $category ], $analyzer->analyze( $post_id ) );
		}

		// Merge cached results from skipped analyzers (from the last full scan).
		$previous_scan = Scan_Result::load( $post_id );
		if ( $previous_scan instanceof Scan_Result ) {
			$previous_results = $previous_scan->results;
			foreach ( $registry->get_all() as $analyzer ) {
				if ( ! in_array( $analyzer->get_id(), $skip_on_save, true ) ) {
					continue;
				}
				$category = $analyzer->get_category();
				if ( isset( $previous_results[ $category ] ) && ! empty( $previous_results[ $category ] ) ) {
					if ( ! isset( $results[ $category ] ) ) {
						$results[ $category ] = array();
					}
					$results[ $category ] = array_merge( $results[ $category ], $previous_results[ $category ] );
				}
			}
		}

		$scores = Scoring_Engine::calculate( $results, $post_id );

		$scan_result = new Scan_Result(
			post_id:    $post_id,
			results:    $results,
			scores:     $scores,
			scanned_at: gmdate( 'c' ),
		);

		$scan_result->save();

		// Create a snapshot for trend tracking.
		$all_items = array();
		foreach ( $results as $category_items ) {
			$all_items = array_merge( $all_items, $category_items );
		}

		\Scalyn\QA\Models\Snapshot::create( $post_id, $scores, $all_items );
	}

	/**
	 * Render a fallback when the template file is missing.
	 *
	 * @since 1.0.0
	 *
	 * @param Scan_Result|null $scan_result The scan result or null.
	 * @param int              $post_id     The post ID.
	 */
	private function render_fallback( ?Scan_Result $scan_result, int $post_id ): void {
		echo '<div class="scalyn-qa-metabox">';

		if ( null === $scan_result ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'No scan results yet. Save or publish this post to run the QA scan.', 'scalyn-qa-assistant' ),
			);
		} else {
			printf(
				'<p><strong>%s:</strong> %d/100 (%s)</p>',
				esc_html__( 'Score', 'scalyn-qa-assistant' ),
				$scan_result->scores->overall,
				esc_html( $scan_result->scores->status ),
			);

			$issue_count = $scan_result->get_issue_count();

			if ( $issue_count > 0 ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %d: Number of issues found. */
							_n( '%d issue found.', '%d issues found.', $issue_count, 'scalyn-qa-assistant' ),
							$issue_count,
						),
					),
				);
			} else {
				printf(
					'<p>%s</p>',
					esc_html__( 'All checks passed!', 'scalyn-qa-assistant' ),
				);
			}
		}

		printf(
			'<p><a href="%s" class="button">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] . '&post_id=' . $post_id ) ),
			esc_html__( 'View Full Audit', 'scalyn-qa-assistant' ),
		);

		echo '</div>';
	}

	/**
	 * Get the configured post types from plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private function get_configured_post_types(): array {
		$settings   = get_option( 'scalyn_qa_settings', array() );
		$post_types = is_array( $settings ) ? ( $settings['post_types'] ?? array( 'post', 'page' ) ) : array( 'post', 'page' );

		if ( ! is_array( $post_types ) || empty( $post_types ) ) {
			return array( 'post', 'page' );
		}

		return $post_types;
	}
}
