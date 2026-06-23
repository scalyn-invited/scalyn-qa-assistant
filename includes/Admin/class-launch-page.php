<?php
/**
 * Launch Page.
 *
 * Renders the launch readiness checklist.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Launch\Launch_Checker;
use Scalyn\QA\Models\Check_Item;
use Scalyn\QA\Models\Ignore_Rule;

/**
 * Class Launch_Page
 *
 * Renders the launch checklist template with stored results and pass/fail/warning counts.
 *
 * @since 1.0.0
 */
class Launch_Page {

	/**
	 * Render the launch checklist page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$results   = $this->get_launch_results();
		$last_scan = get_option( 'scalyn_qa_launch_last_scan', null );

		// Filter out launch-scoped ignored checks for count/score calculation.
		$launch_ignores = Ignore_Rule::get_by_context( 'launch' );
		$ignored_ids    = array();
		foreach ( $launch_ignores as $rule ) {
			$ignored_ids[ $rule->check_id ] = true;
		}

		$active_results = array_filter(
			$results,
			static fn( Check_Item $item ): bool => ! isset( $ignored_ids[ $item->id ] ),
		);

		$counts = $this->calculate_counts( $active_results );

		// Calculate per-category scores.
		$category_map = array(
			// SEO.
			'search_engine_visibility' => 'seo',
			'seo_plugin_installed'     => 'seo',
			'sitemap_exists'           => 'seo',
			'robots_txt'               => 'seo',
			'permalink_structure'      => 'seo',
			'llms_txt'                 => 'seo',
			'breadcrumbs_enabled'      => 'seo',
			'redirect_manager'         => 'seo',
			'local_business_schema'    => 'seo',
			'four_oh_four_monitor'     => 'seo',
			'cornerstone_content'      => 'seo',
			'instant_indexing'         => 'seo',
			'woocommerce_seo'          => 'seo',
			// Analytics.
			'ga4_configured'           => 'analytics',
			'gtm_configured'           => 'analytics',
			// Technical.
			'ssl_enabled'              => 'technical',
			'debug_mode_disabled'      => 'technical',
			'wp_core_updates'          => 'technical',
			'plugin_updates'           => 'technical',
			'wp_address_match'         => 'technical',
			'favicon_exists'           => 'technical',
			'php_version'              => 'technical',
			'php_memory_limit'         => 'technical',
			'php_max_execution_time'   => 'technical',
			'php_max_input_time'       => 'technical',
			'php_post_max_size'        => 'technical',
			'php_upload_max_size'      => 'technical',
			// Content.
			'contact_page_exists'      => 'content',
			'privacy_policy_exists'    => 'content',
			'default_content_cleanup'  => 'content',
			'default_tagline'          => 'content',
			'empty_pages'              => 'content',
			'four_oh_four_page'        => 'content',
			'menu_exists'              => 'content',
			// Plugin health.
			'default_plugins_cleanup'  => 'plugin_health',
			'plugin_conflicts'         => 'plugin_health',
			'security_plugin'          => 'plugin_health',
			'cache_plugin'             => 'plugin_health',
			'backup_plugin'            => 'plugin_health',
			'smtp_plugin'              => 'plugin_health',
			'image_optimization_plugin' => 'plugin_health',
			// Settings.
			'admin_username'           => 'settings',
			'timezone_set'             => 'settings',
			'comments_open'            => 'settings',
		);

		$category_counts = array();
		foreach ( $active_results as $item ) {
			$cat = $category_map[ $item->id ] ?? 'technical';
			if ( ! isset( $category_counts[ $cat ] ) ) {
				$category_counts[ $cat ] = array( 'pass' => 0, 'fail' => 0, 'warning' => 0, 'total' => 0 );
			}
			++$category_counts[ $cat ]['total'];
			match ( $item->status ) {
				'pass'    => ++$category_counts[ $cat ]['pass'],
				'fail'    => ++$category_counts[ $cat ]['fail'],
				'warning' => ++$category_counts[ $cat ]['warning'],
				default   => null,
			};
		}

		$category_scores = array();
		foreach ( $category_counts as $cat => $cc ) {
			$category_scores[ $cat ] = $this->calculate_score( $cc );
		}

		$data = array(
			'results'          => $results,
			'counts'           => $counts,
			'last_scan'        => $last_scan ? (int) $last_scan : null,
			'score'            => $this->calculate_score( $counts ),
			'category_scores'  => $category_scores,
			'category_counts'  => $category_counts,
		);

		$this->load_template( 'launch/checklist.php', $data );
	}

	/**
	 * Get the stored launch check results as Check_Item objects.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item[]
	 */
	private function get_launch_results(): array {
		$stored = get_option( 'scalyn_qa_launch_results', array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return array();
		}

		$items = array();

		foreach ( $stored as $item_data ) {
			if ( ! is_array( $item_data ) ) {
				continue;
			}

			// Skip pro-locked checks from previously stored results.
			$id = $item_data['id'] ?? '';
			if ( Launch_Checker::is_check_pro_locked( $id ) ) {
				continue;
			}

			$items[] = Check_Item::from_array( $item_data );
		}

		return $items;
	}

	/**
	 * Calculate pass/fail/warning counts from check results.
	 *
	 * @since 1.0.0
	 *
	 * @param Check_Item[] $results Array of check items.
	 * @return array{pass: int, fail: int, warning: int, total: int}
	 */
	private function calculate_counts( array $results ): array {
		$counts = array(
			'pass'    => 0,
			'fail'    => 0,
			'warning' => 0,
			'total'   => count( $results ),
		);

		foreach ( $results as $item ) {
			if ( ! $item instanceof Check_Item ) {
				continue;
			}

			match ( $item->status ) {
				'pass'    => ++$counts['pass'],
				'fail'    => ++$counts['fail'],
				'warning' => ++$counts['warning'],
				default   => null,
			};
		}

		return $counts;
	}

	/**
	 * Calculate the launch readiness score from counts.
	 *
	 * @since 1.0.0
	 *
	 * @param array{pass: int, fail: int, warning: int, total: int} $counts Status counts.
	 * @return int Score 0-100.
	 */
	private function calculate_score( array $counts ): int {
		if ( 0 === $counts['total'] ) {
			return 0;
		}

		// Pass = full credit, warning = half credit, fail = zero.
		$earned = $counts['pass'] + ( $counts['warning'] * 0.5 );
		$score  = (int) round( ( $earned / $counts['total'] ) * 100 );

		return max( 0, min( 100, $score ) );
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
