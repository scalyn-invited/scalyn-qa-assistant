<?php
/**
 * Ignore Controller.
 *
 * REST endpoints for managing QA check ignore/suppress rules.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Ignore_Rule;

/**
 * Class Ignore_Controller
 *
 * Handles listing, creating, and deleting ignore rules.
 *
 * @since 1.0.0
 */
class Ignore_Controller extends REST_Controller {

	/**
	 * Valid rule types.
	 *
	 * @var string[]
	 */
	private const VALID_TYPES = array( 'check', 'page', 'global' );

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
			'/ignore',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rules' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_rule' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'type'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => static fn( $v ): bool => in_array( $v, self::VALID_TYPES, true ),
						),
						'check_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => static fn( $v ): bool => is_string( $v ) && '' !== trim( $v ),
						),
						'post_id'  => array(
							'default'           => null,
							'type'              => array( 'integer', 'null' ),
							'sanitize_callback' => static fn( $v ): ?int => null !== $v ? absint( $v ) : null,
						),
						'reason'   => array(
							'default'           => '',
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ignore/(?P<rule_id>[a-f0-9-]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_rule' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'rule_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && '' !== trim( $v ),
					),
				),
			),
		);
	}

	/**
	 * Get all ignore rules.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_rules( \WP_REST_Request $request ): \WP_REST_Response {
		$rules = Ignore_Rule::get_all();

		// Also gather per-post rules from all posts that have them.
		$per_post_rules = $this->get_all_per_post_rules();
		$all_rules      = array_merge( $rules, $per_post_rules );

		$data = array_map(
			static fn( Ignore_Rule $rule ): array => $rule->to_array(),
			$all_rules,
		);

		return $this->success(
			array(
				'rules' => $data,
				'total' => count( $data ),
			),
		);
	}

	/**
	 * Create a new ignore rule.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_rule( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$type     = sanitize_text_field( $request->get_param( 'type' ) );
		$check_id = sanitize_key( $request->get_param( 'check_id' ) );
		$post_id  = $request->get_param( 'post_id' );
		$reason   = sanitize_text_field( $request->get_param( 'reason' ) ?? '' );
		$context  = sanitize_key( $request->get_param( 'context' ) ?? 'audit' );
		if ( ! in_array( $context, array( 'audit', 'launch' ), true ) ) {
			$context = 'audit';
		}

		// Validate type-specific requirements.
		if ( 'page' === $type && ( null === $post_id || 0 === absint( $post_id ) ) ) {
			return $this->error(
				'post_id_required',
				__( 'A valid post_id is required for page-level ignore rules.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		if ( 'check' === $type && null !== $post_id ) {
			$post_id = absint( $post_id );

			if ( $post_id > 0 && ! get_post( $post_id ) ) {
				return $this->error(
					'post_not_found',
					__( 'The specified post does not exist.', 'scalyn-qa-assistant' ),
					404,
				);
			}
		}

		$current_user = wp_get_current_user();

		$rule = new Ignore_Rule(
			id:         wp_generate_uuid4(),
			type:       $type,
			check_id:   $check_id,
			post_id:    null !== $post_id ? absint( $post_id ) : null,
			reason:     $reason,
			created_by: $current_user->display_name ?: $current_user->user_login,
			created_at: gmdate( 'c' ),
			context:    $context,
		);

		Ignore_Rule::add( $rule );

		return $this->success( $rule->to_array(), 201 );
	}

	/**
	 * Delete an ignore rule.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_rule( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$rule_id = sanitize_key( $request->get_param( 'rule_id' ) );

		$removed = Ignore_Rule::remove( $rule_id );

		if ( ! $removed ) {
			return $this->error(
				'rule_not_found',
				__( 'Ignore rule not found.', 'scalyn-qa-assistant' ),
				404,
			);
		}

		return $this->success(
			array(
				'deleted' => true,
				'rule_id' => $rule_id,
			),
		);
	}

	/**
	 * Retrieve all per-post ignore rules from all posts.
	 *
	 * @since 1.0.0
	 *
	 * @return Ignore_Rule[]
	 */
	private function get_all_per_post_rules(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_scalyn_qa_ignore_rules',
			),
		);

		$rules = array();

		foreach ( $post_ids as $post_id ) {
			$post_data = get_post_meta( absint( $post_id ), '_scalyn_qa_ignore_rules', true );

			if ( ! is_array( $post_data ) ) {
				continue;
			}

			foreach ( $post_data as $rule_data ) {
				if ( is_array( $rule_data ) ) {
					$rules[] = Ignore_Rule::from_array( $rule_data );
				}
			}
		}

		return $rules;
	}
}
