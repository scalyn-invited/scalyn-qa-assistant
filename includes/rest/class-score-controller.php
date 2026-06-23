<?php
/**
 * Score Controller.
 *
 * REST endpoints for retrieving and listing QA scores.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Scan_Result;
use Scalyn\QA\Scoring\Scoring_Engine;

/**
 * Class Score_Controller
 *
 * Handles score listing, single-post scores, and project-wide summary.
 *
 * @since 1.0.0
 */
class Score_Controller extends REST_Controller {

	/**
	 * Valid orderby columns.
	 *
	 * @var string[]
	 */
	private const VALID_ORDERBY = array( 'overall', 'seo', 'content', 'functionality' );

	/**
	 * Valid order directions.
	 *
	 * @var string[]
	 */
	private const VALID_ORDER = array( 'asc', 'desc' );

	/**
	 * Valid status filters.
	 *
	 * @var string[]
	 */
	private const VALID_STATUSES = array( 'green', 'yellow', 'red' );

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/scores',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_scores' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && (int) $v >= 1,
					),
					'per_page' => array(
						'default'           => 20,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && (int) $v >= 1 && (int) $v <= 100,
					),
					'orderby'  => array(
						'default'           => 'overall',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => in_array( $v, self::VALID_ORDERBY, true ),
					),
					'order'    => array(
						'default'           => 'desc',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => in_array( strtolower( $v ), self::VALID_ORDER, true ),
					),
					'status'   => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => '' === $v || in_array( $v, self::VALID_STATUSES, true ),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/scores/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_single_score' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/scores/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'can_edit' ),
			),
		);
	}

	/**
	 * Get paginated list of posts with their scores.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_scores( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page     = absint( $request->get_param( 'page' ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		$orderby  = sanitize_text_field( $request->get_param( 'orderby' ) );
		$order    = strtolower( sanitize_text_field( $request->get_param( 'order' ) ) );
		$status   = sanitize_text_field( $request->get_param( 'status' ) );

		// Fetch all scanned post IDs.
		$scanned_post_ids = $this->get_scanned_post_ids();

		if ( empty( $scanned_post_ids ) ) {
			$response = $this->success( array() );
			$response->header( 'X-WP-Total', '0' );
			$response->header( 'X-WP-TotalPages', '0' );
			return $response;
		}

		// Build score entries for all scanned posts.
		$entries = array();

		foreach ( $scanned_post_ids as $post_id ) {
			$scan_result = Scan_Result::load( $post_id );

			if ( null === $scan_result ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$scores_array = $scan_result->scores->to_array();

			// Apply status filter.
			if ( '' !== $status && $scores_array['status'] !== $status ) {
				continue;
			}

			$entries[] = array(
				'post_id'    => $post_id,
				'post_title' => get_the_title( $post ),
				'post_type'  => $post->post_type,
				'post_url'   => get_permalink( $post ),
				'scores'     => $scores_array,
				'scanned_at' => $scan_result->scanned_at,
			);
		}

		// Sort entries.
		usort(
			$entries,
			static function ( array $a, array $b ) use ( $orderby, $order ): int {
				$a_val = $a['scores'][ $orderby ] ?? 0;
				$b_val = $b['scores'][ $orderby ] ?? 0;

				$cmp = $a_val <=> $b_val;

				return 'desc' === $order ? -$cmp : $cmp;
			},
		);

		// Paginate.
		$total       = count( $entries );
		$total_pages = (int) ceil( $total / $per_page );
		$offset      = ( $page - 1 ) * $per_page;
		$paged       = array_slice( $entries, $offset, $per_page );

		$response = $this->success( $paged );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Get score for a single post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_single_score( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$scan_result = Scan_Result::load( $post_id );

		if ( null === $scan_result ) {
			return $this->error(
				'score_not_found',
				__( 'No score found for this post.', 'scalyn-qa-assistant' ),
				404,
			);
		}

		$post = get_post( $post_id );

		$data = array(
			'post_id'    => $post_id,
			'post_title' => $post ? get_the_title( $post ) : '',
			'scores'     => $scan_result->scores->to_array(),
			'scanned_at' => $scan_result->scanned_at,
		);

		return $this->success( $data );
	}

	/**
	 * Get project-wide score summary.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_summary( \WP_REST_Request $request ): \WP_REST_Response {
		$project_scores = Scoring_Engine::get_project_scores();

		return $this->success( $project_scores );
	}

	/**
	 * Retrieve all post IDs that have scan results stored.
	 *
	 * @since 1.0.0
	 *
	 * @return int[]
	 */
	private function get_scanned_post_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_scalyn_qa_scan_results'
			)
		);

		return array_map( 'absint', $ids );
	}
}
