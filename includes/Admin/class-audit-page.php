<?php
/**
 * Audit Page.
 *
 * Renders the page audits list and single audit view.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Scan_Result;
use Scalyn\QA\Models\Ignore_Rule;
use Scalyn\QA\Models\Snapshot;

/**
 * Class Audit_Page
 *
 * Renders the audit list with pagination or a single post audit view.
 *
 * @since 1.0.0
 */
class Audit_Page {

	/**
	 * Number of posts per page in the audit list.
	 *
	 * @var int
	 */
	private const PER_PAGE = 20;

	/**
	 * Render the audit page.
	 *
	 * Routes to either the single audit view or the list view based on the
	 * presence of a `post_id` query parameter.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( $post_id > 0 ) {
			$this->render_single( $post_id );
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render the single audit view for a specific post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The WordPress post ID.
	 */
	private function render_single( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Post not found.', 'scalyn-qa-assistant' ),
			);
			return;
		}

		$scan_result  = Scan_Result::load( $post_id );
		$ignore_rules = Ignore_Rule::get_for_post( $post_id );
		$snapshots    = Snapshot::get_for_post( $post_id );
		$notes        = $this->get_notes( $post_id );

		$data = array(
			'post'         => $post,
			'post_id'      => $post_id,
			'scan_result'  => $scan_result,
			'ignore_rules' => $ignore_rules,
			'snapshots'    => $snapshots,
			'notes'        => $notes,
			'audit_url'    => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] ),
		);

		$this->load_template( 'audit/single.php', $data );
	}

	/**
	 * Render the audit list view with pagination.
	 *
	 * @since 1.0.0
	 */
	private function render_list(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['filter_type'] ) ? sanitize_key( $_GET['filter_type'] ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$settings   = get_option( 'scalyn_qa_settings', array() );
		$post_types = $this->get_configured_post_types( $settings );

		$query_args = array(
			'post_type'      => ! empty( $post_type ) && in_array( $post_type, $post_types, true )
				? $post_type
				: $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $query_args );
		$posts = $query->posts;

		// Enrich posts with score data.
		$items = array();

		foreach ( $posts as $post ) {
			$scores_data = get_post_meta( $post->ID, '_scalyn_qa_scores', true );
			$last_scan   = get_post_meta( $post->ID, '_scalyn_qa_last_scan', true );

			$items[] = array(
				'post'       => $post,
				'post_id'    => $post->ID,
				'title'      => get_the_title( $post->ID ),
				'post_type'  => $post->post_type,
				'score'      => is_array( $scores_data ) ? (int) ( $scores_data['overall'] ?? 0 ) : null,
				'status'     => is_array( $scores_data ) ? ( $scores_data['status'] ?? null ) : null,
				'last_scan'  => ! empty( $last_scan ) ? $last_scan : null,
				'audit_url'  => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] . '&post_id=' . $post->ID ),
				'edit_url'   => get_edit_post_link( $post->ID, 'raw' ) ?? '',
			);
		}

		// Apply status filter if set.
		if ( ! empty( $status_filter ) && in_array( $status_filter, array( 'green', 'yellow', 'red', 'unscanned' ), true ) ) {
			$items = array_filter(
				$items,
				static function ( array $item ) use ( $status_filter ): bool {
					if ( 'unscanned' === $status_filter ) {
						return null === $item['score'];
					}
					return $item['status'] === $status_filter;
				},
			);
			$items = array_values( $items );
		}

		// Compute summary stats across ALL items (before status filter).
		$status_summary = array( 'green' => 0, 'yellow' => 0, 'red' => 0, 'unscanned' => 0 );
		foreach ( $items as $it ) {
			if ( null === $it['score'] ) {
				++$status_summary['unscanned'];
			} elseif ( isset( $it['status'] ) && isset( $status_summary[ $it['status'] ] ) ) {
				++$status_summary[ $it['status'] ];
			}
		}

		$data = array(
			'items'          => $items,
			'total_posts'    => $query->found_posts,
			'total_pages'    => $query->max_num_pages,
			'current_page'   => $paged,
			'per_page'       => self::PER_PAGE,
			'post_types'     => $post_types,
			'current_type'   => $post_type,
			'current_status' => $status_filter,
			'base_url'       => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] ),
			'status_summary' => $status_summary,
		);

		$this->load_template( 'audit/list.php', $data );
	}

	/**
	 * Get notes stored for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The WordPress post ID.
	 * @return array<int, array{id: string, content: string, author: string, created_at: string}>
	 */
	private function get_notes( int $post_id ): array {
		$notes = get_post_meta( $post_id, '_scalyn_qa_notes', true );

		if ( ! is_array( $notes ) ) {
			return array();
		}

		return $notes;
	}

	/**
	 * Get the configured post types to scan from settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings array.
	 * @return string[]
	 */
	private function get_configured_post_types( array $settings ): array {
		$post_types = $settings['post_types'] ?? array( 'post', 'page' );

		if ( ! is_array( $post_types ) || empty( $post_types ) ) {
			return array( 'post', 'page' );
		}

		return $post_types;
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
