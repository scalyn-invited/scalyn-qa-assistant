<?php
/**
 * Snapshot Controller.
 *
 * REST endpoints for managing QA score snapshots and trend tracking.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;
use Scalyn\QA\Models\Scan_Result;
use Scalyn\QA\Models\Snapshot;

/**
 * Class Snapshot_Controller
 *
 * Handles listing snapshots, creating new snapshots from current scan data,
 * and returning trend information.
 *
 * @since 1.0.0
 */
class Snapshot_Controller extends REST_Controller {

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
			'/snapshots/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_snapshots' ),
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
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_snapshot' ),
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
			),
		);
	}

	/**
	 * Get all snapshots for a post with trend data.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_snapshots( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$snapshots = Snapshot::get_for_post( $post_id );
		$trend     = Snapshot::get_trend( $post_id );

		$data = array_map(
			static fn( Snapshot $snapshot ): array => $snapshot->to_array(),
			$snapshots,
		);

		return $this->success(
			array(
				'post_id'   => $post_id,
				'snapshots' => $data,
				'trend'     => $trend,
				'total'     => count( $data ),
			),
		);
	}

	/**
	 * Create a new snapshot from the current scan results.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_snapshot( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$scan_result = Scan_Result::load( $post_id );

		if ( null === $scan_result ) {
			return $this->error(
				'no_scan_results',
				__( 'No scan results found for this post. Run a scan first.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		// Flatten all check items from the scan results.
		$all_items = array();

		foreach ( $scan_result->results as $items ) {
			foreach ( $items as $item ) {
				if ( $item instanceof Check_Item ) {
					$all_items[] = $item;
				}
			}
		}

		$snapshot = Snapshot::create( $post_id, $scan_result->scores, $all_items );
		$trend    = Snapshot::get_trend( $post_id );

		return $this->success(
			array(
				'snapshot' => $snapshot->to_array(),
				'trend'    => $trend,
			),
			201,
		);
	}
}
