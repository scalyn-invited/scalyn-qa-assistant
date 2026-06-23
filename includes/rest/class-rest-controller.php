<?php
/**
 * Base REST Controller.
 *
 * Abstract base for all Scalyn QA REST API controllers.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST_Controller
 *
 * Provides shared helpers for response formatting, error handling,
 * and permission checks used by every REST endpoint.
 *
 * @since 1.0.0
 */
abstract class REST_Controller extends \WP_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'scalyn-qa/v1';

	/**
	 * Build a standardized success response.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function success( mixed $data, int $status = 200 ): \WP_REST_Response {
		$body = array(
			'success' => true,
			'data'    => $data,
			'meta'    => array(
				'timestamp' => gmdate( 'c' ),
				'version'   => defined( 'SCALYN_QA_VERSION' ) ? SCALYN_QA_VERSION : '1.0.0',
			),
		);

		return new \WP_REST_Response( $body, $status );
	}

	/**
	 * Build a standardized error response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	protected function error( string $code, string $message, int $status ): \WP_Error {
		\Scalyn\QA\Debug_Logger::rest_error( $this->namespace, $message, [ 'code' => $code, 'status' => $status ] );

		return new \WP_Error(
			$code,
			$message,
			array( 'status' => $status ),
		);
	}

	/**
	 * Check whether the current user can manage options (admin).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check whether the current user can edit posts (editor+).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can edit a specific post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return bool
	 */
	protected function can_edit_post( int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}
}
