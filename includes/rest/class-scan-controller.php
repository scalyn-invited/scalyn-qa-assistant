<?php
/**
 * Scan Controller.
 *
 * REST endpoints for running and retrieving page scans.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Analyzers\Analyzer_Registry;
use Scalyn\QA\Models\Scan_Result;
use Scalyn\QA\Models\Snapshot;
use Scalyn\QA\Scoring\Scoring_Engine;

/**
 * Class Scan_Controller
 *
 * Handles scan execution and retrieval via the REST API.
 *
 * @since 1.0.0
 */
class Scan_Controller extends REST_Controller {

	/**
	 * The analyzer registry instance.
	 *
	 * @var Analyzer_Registry
	 */
	private Analyzer_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Analyzer_Registry $registry The analyzer registry.
	 */
	public function __construct( Analyzer_Registry $registry ) {
		$this->registry = $registry;
	}

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
			'/scan/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_scan' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_post_id' ),
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scan' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_post_id' ),
						),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/scan/post-ids',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_scannable_post_ids' ),
				'permission_callback' => array( $this, 'can_edit' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/scan/batch',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_batch_scan' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_ids' => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array( 'type' => 'integer' ),
						'sanitize_callback' => static function ( $value ): array {
							if ( ! is_array( $value ) ) {
								return array();
							}
							return array_map( 'absint', $value );
						},
						'validate_callback' => static function ( $value ): bool {
							return is_array( $value ) && ! empty( $value );
						},
					),
				),
			),
		);
	}

	/**
	 * Run a scan on a single post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_scan( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		// Clear Elementor cache before scanning so we get fresh rendered content.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		$scan_result = $this->execute_scan( $post_id );

		return $this->success( $scan_result->to_array(), 201 );
	}

	/**
	 * Get an existing scan result for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_scan( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$scan_result = Scan_Result::load( $post_id );

		if ( null === $scan_result ) {
			return $this->error(
				'scan_not_found',
				__( 'No scan results found for this post.', 'scalyn-qa-assistant' ),
				404,
			);
		}

		return $this->success( $scan_result->to_array() );
	}

	/**
	 * Run scans on a batch of posts.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	/**
	 * GET /scan/post-ids — return all scannable published post IDs.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_scannable_post_ids( \WP_REST_Request $request ): \WP_REST_Response {
		$settings   = get_option( 'scalyn_qa_settings', array() );
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );

		$posts = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		return $this->success( array(
			'post_ids' => array_map( 'intval', $posts ),
			'count'    => count( $posts ),
		) );
	}

	public function run_batch_scan( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_ids = $request->get_param( 'post_ids' );

		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return $this->error(
				'invalid_post_ids',
				__( 'A non-empty array of post IDs is required.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		// Enforce maximum batch size.
		$max_batch_size = 20;

		if ( count( $post_ids ) > $max_batch_size ) {
			return $this->error(
				'batch_too_large',
				sprintf(
					/* translators: %d: Maximum batch size. */
					__( 'Maximum batch size is %d posts.', 'scalyn-qa-assistant' ),
					$max_batch_size,
				),
				400,
			);
		}

		$results  = array();
		$failures = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			$post    = get_post( $post_id );

			if ( ! $post ) {
				$failures[] = array(
					'post_id' => $post_id,
					'error'   => __( 'Post not found.', 'scalyn-qa-assistant' ),
				);
				continue;
			}

			$scan_result = $this->execute_scan( $post_id );
			$results[]   = $scan_result->to_array();
		}

		return $this->success(
			array(
				'results'  => $results,
				'failures' => $failures,
				'total'    => count( $results ),
				'failed'   => count( $failures ),
			),
			201,
		);
	}

	/**
	 * Validate that a post ID corresponds to an existing post.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to validate.
	 * @return bool
	 */
	public function validate_post_id( mixed $value ): bool {
		return is_numeric( $value ) && absint( $value ) > 0;
	}

	/**
	 * Execute a scan on a single post: analyze, score, save, and return.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to scan.
	 * @return Scan_Result
	 */
	private function execute_scan( int $post_id ): Scan_Result {
		$results = $this->registry->run_all( $post_id );
		$scores  = Scoring_Engine::calculate( $results, $post_id );

		$scan_result = new Scan_Result(
			post_id:    $post_id,
			results:    $results,
			scores:     $scores,
			scanned_at: gmdate( 'c' ),
		);

		$scan_result->save();

		// Create a snapshot for trend tracking.
		try {
			$all_items = array();
			foreach ( $results as $category_items ) {
				if ( is_array( $category_items ) ) {
					$all_items = array_merge( $all_items, $category_items );
				}
			}

			Snapshot::create( $post_id, $scores, $all_items );
		} catch ( \Throwable $e ) {
			\Scalyn\QA\Debug_Logger::log( 'scan', 'Snapshot creation failed: ' . $e->getMessage() );
		}

		return $scan_result;
	}
}
