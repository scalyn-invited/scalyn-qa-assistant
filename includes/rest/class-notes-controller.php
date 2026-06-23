<?php
/**
 * Notes Controller.
 *
 * REST endpoints for managing per-post QA notes.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notes_Controller
 *
 * Handles listing, adding, and deleting notes attached to posts.
 *
 * @since 1.0.0
 */
class Notes_Controller extends REST_Controller {

	/**
	 * Post meta key for notes.
	 *
	 * @var string
	 */
	private const META_NOTES = '_scalyn_qa_notes';

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
			'/notes/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notes' ),
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
					'callback'            => array( $this, 'add_note' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
						),
						'content' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => static fn( $v ): bool => is_string( $v ) && '' !== trim( $v ),
						),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/notes/(?P<post_id>\d+)/(?P<index>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_note' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
					'index'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			),
		);
	}

	/**
	 * Get notes for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_notes( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$notes = $this->load_notes( $post_id );

		return $this->success(
			array(
				'post_id' => $post_id,
				'notes'   => $notes,
				'total'   => count( $notes ),
			),
		);
	}

	/**
	 * Add a note to a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_note( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$content = sanitize_textarea_field( $request->get_param( 'content' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		if ( '' === trim( $content ) ) {
			return $this->error(
				'empty_content',
				__( 'Note content cannot be empty.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		$current_user = wp_get_current_user();

		$note = array(
			'content'    => $content,
			'author'     => $current_user->display_name ?: $current_user->user_login,
			'author_id'  => $current_user->ID,
			'created_at' => gmdate( 'c' ),
		);

		$notes   = $this->load_notes( $post_id );
		$notes[] = $note;

		update_post_meta( $post_id, self::META_NOTES, $notes );

		return $this->success(
			array(
				'post_id' => $post_id,
				'notes'   => $notes,
				'total'   => count( $notes ),
			),
			201,
		);
	}

	/**
	 * Delete a note from a post by its index.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_note( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$index   = absint( $request->get_param( 'index' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$notes = $this->load_notes( $post_id );

		if ( ! isset( $notes[ $index ] ) ) {
			return $this->error(
				'note_not_found',
				__( 'Note not found at the specified index.', 'scalyn-qa-assistant' ),
				404,
			);
		}

		array_splice( $notes, $index, 1 );

		if ( empty( $notes ) ) {
			delete_post_meta( $post_id, self::META_NOTES );
		} else {
			update_post_meta( $post_id, self::META_NOTES, $notes );
		}

		return $this->success(
			array(
				'post_id' => $post_id,
				'notes'   => $notes,
				'total'   => count( $notes ),
			),
		);
	}

	/**
	 * Load notes from post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array[]
	 */
	private function load_notes( int $post_id ): array {
		$notes = get_post_meta( $post_id, self::META_NOTES, true );

		if ( ! is_array( $notes ) ) {
			return array();
		}

		// Sanitize each note on read.
		return array_values(
			array_map(
				static fn( array $note ): array => array(
					'content'    => sanitize_textarea_field( $note['content'] ?? '' ),
					'author'     => sanitize_text_field( $note['author'] ?? '' ),
					'author_id'  => absint( $note['author_id'] ?? 0 ),
					'created_at' => sanitize_text_field( $note['created_at'] ?? '' ),
				),
				array_filter( $notes, 'is_array' ),
			),
		);
	}
}
