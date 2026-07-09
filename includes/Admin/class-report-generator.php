<?php
/**
 * Report Generator.
 *
 * Collects site-wide QA data and renders a printable HTML report.
 *
 * @package Scalyn\QA\Admin
 * @since   1.4.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Scoring\Scoring_Engine;
use Scalyn\QA\Models\Check_Item;
use Scalyn\QA\Models\Ignore_Rule;
use Scalyn\QA\Integrations\SEO_Integration;

/**
 * Class Report_Generator
 *
 * Generates a self-contained HTML report designed for browser print-to-PDF.
 *
 * @since 1.4.0
 */
class Report_Generator {

	/**
	 * Handle the admin-post request to generate a report.
	 */
	public static function handle_request(): void {
		check_admin_referer( 'scalyn_qa_report' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate reports.', 'scalyn-qa-assistant' ) );
		}

		update_user_meta( get_current_user_id(), 'scalyn_qa_report_generated', true );

		$generator = new self();
		$generator->output();
	}

	/**
	 * Output the full HTML report and exit.
	 */
	public function output(): void {
		$data = $this->get_report_data();

		// Clear any buffered output from WordPress admin bootstrap.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/html; charset=utf-8' );

		// Extract data into scope and render the template.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data );

		include SCALYN_QA_PLUGIN_DIR . 'templates/report/qa-report.php';
		exit;
	}

	/**
	 * Collect all data needed for the report.
	 *
	 * @return array
	 */
	public function get_report_data(): array {
		$settings = get_option( 'scalyn_qa_report_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$include_page_scores = (bool) ( $settings['include_page_scores'] ?? true );
		$include_top_issues  = (bool) ( $settings['include_top_issues'] ?? true );
		$include_launch      = (bool) ( $settings['include_launch'] ?? true );
		$max_pages           = (int) ( $settings['max_pages'] ?? 500 );

		return array(
			'site_name'            => get_bloginfo( 'name' ),
			'site_url'             => home_url(),
			'wp_version'           => get_bloginfo( 'version' ),
			'php_version'          => PHP_VERSION,
			'plugin_version'       => defined( 'SCALYN_QA_VERSION' ) ? SCALYN_QA_VERSION : '1.0.0',
			'generated_at'         => current_time( 'Y-m-d H:i:s' ),
			'generated_by'         => wp_get_current_user()->display_name,
			'project_scores'       => Scoring_Engine::get_project_scores(),
			'scan_coverage'        => $this->get_scan_coverage(),
			'all_pages'            => $include_page_scores ? $this->get_all_scanned_pages( $max_pages ) : array(),
			'top_issues'           => $include_top_issues ? $this->get_top_issues() : array(),
			'launch_results'       => $include_launch ? $this->get_launch_results() : array(),
			'launch_summary'       => $include_launch ? $this->get_launch_summary() : array(),
			'seo_plugin'           => $this->get_seo_plugin_name(),
			'logo_data_uri'        => $this->get_logo_data_uri(),
			'include_page_scores'  => $include_page_scores,
			'include_top_issues'   => $include_top_issues,
			'include_launch'       => $include_launch,
		);
	}

	/**
	 * Get all scanned pages with scores.
	 *
	 * @return array
	 */
	private function get_all_scanned_pages( int $limit = 500 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				 WHERE pm.meta_key = '_scalyn_qa_scores'
				 AND p.post_status = 'publish'
				 LIMIT %d",
				$limit,
			),
		);

		if ( empty( $post_ids ) ) {
			return array();
		}

		$pages = array();

		foreach ( $post_ids as $post_id ) {
			$post_id     = (int) $post_id;
			$scores_data = get_post_meta( $post_id, '_scalyn_qa_scores', true );

			if ( ! is_array( $scores_data ) || ! isset( $scores_data['overall'] ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$pages[] = array(
				'title'         => get_the_title( $post_id ),
				'url'           => get_permalink( $post_id ),
				'overall'       => (int) ( $scores_data['overall'] ?? 0 ),
				'seo'           => (int) ( $scores_data['seo'] ?? 0 ),
				'content'       => (int) ( $scores_data['content'] ?? 0 ),
				'functionality' => (int) ( $scores_data['functionality'] ?? 0 ),
				'status'        => $scores_data['status'] ?? 'red',
			);
		}

		usort( $pages, static fn( array $a, array $b ): int => $a['overall'] <=> $b['overall'] );

		return $pages;
	}

	/**
	 * Get top failing issues across all pages.
	 *
	 * @return array
	 */
	private function get_top_issues(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_scalyn_qa_scan_results'",
		);

		if ( empty( $post_ids ) ) {
			return array();
		}

		$counts = array();
		$labels = array();
		$cats   = array();

		foreach ( $post_ids as $post_id ) {
			$post_id   = (int) $post_id;
			$scan_data = get_post_meta( $post_id, '_scalyn_qa_scan_results', true );
			if ( ! is_array( $scan_data ) ) {
				continue;
			}

			foreach ( $scan_data as $category => $checks ) {
				if ( ! is_array( $checks ) ) {
					continue;
				}
				foreach ( $checks as $check ) {
					if ( ! is_array( $check ) || 'pass' === ( $check['status'] ?? 'pass' ) ) {
						continue;
					}
					$id = $check['id'] ?? '';
					if ( '' === $id ) {
						continue;
					}
					if ( ! isset( $counts[ $id ] ) ) {
						$counts[ $id ] = 0;
						$labels[ $id ] = $check['label'] ?? $id;
						$cats[ $id ]   = $category;
					}
					++$counts[ $id ];
				}
			}
		}

		arsort( $counts );

		$top = array();
		$i   = 0;
		foreach ( $counts as $id => $count ) {
			if ( ++$i > 15 ) {
				break;
			}
			$top[] = array(
				'id'       => $id,
				'label'    => $labels[ $id ],
				'count'    => $count,
				'category' => $cats[ $id ],
			);
		}

		return $top;
	}

	/**
	 * Get scan coverage stats.
	 *
	 * @return array{scanned: int, total: int}
	 */
	private function get_scan_coverage(): array {
		$settings   = get_option( 'scalyn_qa_settings', array() );
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );

		$total = 0;
		foreach ( $post_types as $pt ) {
			$count_obj = wp_count_posts( $pt );
			$total    += (int) ( $count_obj->publish ?? 0 );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$scanned = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT pm.post_id)
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_scalyn_qa_scores'
			 AND p.post_status = 'publish'",
		);

		return array( 'scanned' => $scanned, 'total' => $total );
	}

	/**
	 * Get launch checklist results for the report.
	 *
	 * @return array
	 */
	private function get_launch_results(): array {
		$stored = get_option( 'scalyn_qa_launch_results', array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return array();
		}

		$ignored_ids = array();
		$ignores     = Ignore_Rule::get_by_context( 'launch' );
		foreach ( $ignores as $rule ) {
			$ignored_ids[ $rule->check_id ] = true;
		}

		$items = array();
		foreach ( $stored as $item ) {
			if ( ! is_array( $item ) || isset( $ignored_ids[ $item['id'] ?? '' ] ) ) {
				continue;
			}
			$items[] = array(
				'label'   => $item['label'] ?? $item['id'] ?? '',
				'status'  => $item['status'] ?? 'fail',
				'message' => $item['message'] ?? '',
			);
		}

		return $items;
	}

	/**
	 * Get launch summary counts.
	 *
	 * @return array
	 */
	private function get_launch_summary(): array {
		$results   = $this->get_launch_results();
		$last_scan = get_option( 'scalyn_qa_launch_last_scan', null );

		$summary = array( 'pass' => 0, 'fail' => 0, 'warning' => 0, 'total' => 0, 'last_scan' => $last_scan ? (int) $last_scan : null );

		foreach ( $results as $item ) {
			++$summary['total'];
			match ( $item['status'] ) {
				'pass'    => ++$summary['pass'],
				'fail'    => ++$summary['fail'],
				'warning' => ++$summary['warning'],
				default   => null,
			};
		}

		return $summary;
	}

	/**
	 * Get active SEO plugin name.
	 *
	 * @return string|null
	 */
	private function get_seo_plugin_name(): ?string {
		$integration = SEO_Integration::detect();
		return null !== $integration ? $integration->get_plugin_name() : null;
	}

	/**
	 * Get the logo as a base64 data URI for embedding.
	 *
	 * @return string
	 */
	private function get_logo_data_uri(): string {
		$settings         = get_option( 'scalyn_qa_report_settings', array() );
		$company_logo_id  = (int) ( $settings['company_logo_id'] ?? 0 );

		// Use company logo if set, then fall back to site logo from Customizer.
		if ( 0 === $company_logo_id ) {
			$company_logo_id = (int) get_theme_mod( 'custom_logo', 0 );
		}

		if ( $company_logo_id > 0 ) {
			$file = get_attached_file( $company_logo_id );
			if ( $file && file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$data = file_get_contents( $file );
				if ( false !== $data ) {
					$mime = get_post_mime_type( $company_logo_id ) ?: 'image/png';
					return 'data:' . $mime . ';base64,' . base64_encode( $data );
				}
			}
		}

		// Fallback to default Scalyn logo.
		$path = SCALYN_QA_PLUGIN_DIR . 'assets/images/scalyn-logo.png';
		if ( ! file_exists( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = file_get_contents( $path );
		if ( false === $data ) {
			return '';
		}

		return 'data:image/png;base64,' . base64_encode( $data );
	}
}
