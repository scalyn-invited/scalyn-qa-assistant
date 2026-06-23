<?php
/**
 * AI Health Monitor.
 *
 * Tracks provider health status and usage statistics for diagnostics
 * and the admin health dashboard.
 *
 * @package Scalyn\QA\AI
 * @since   1.4.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Health_Monitor
 *
 * Records per-provider request outcomes (success/failure), response times,
 * and exposes health metrics via static helpers.
 *
 * @since 1.4.0
 */
final class AI_Health_Monitor {

	/**
	 * WordPress option key for health data.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'scalyn_qa_ai_health';

	/**
	 * Record a successful request.
	 *
	 * @since 1.4.0
	 *
	 * @param string $provider        Provider slug.
	 * @param float  $response_time_ms Response time in milliseconds.
	 */
	public static function record_success( string $provider, float $response_time_ms ): void {
		$health = self::get_all_health();

		if ( ! isset( $health[ $provider ] ) ) {
			$health[ $provider ] = self::default_health();
		}

		$health[ $provider ]['status']                 = 'connected';
		$health[ $provider ]['last_success']            = gmdate( 'c' );
		$health[ $provider ]['total_requests']++;
		$health[ $provider ]['successful_requests']++;
		$health[ $provider ]['total_response_time_ms'] += $response_time_ms;
		$health[ $provider ]['last_error']              = '';

		update_option( self::OPTION_KEY, $health, false );
	}

	/**
	 * Record a failed request.
	 *
	 * @since 1.4.0
	 *
	 * @param string $provider   Provider slug.
	 * @param string $error      Error message.
	 * @param string $error_type Error classification: 'api_error', 'rate_limited', 'disconnected'.
	 */
	public static function record_failure( string $provider, string $error, string $error_type = 'api_error' ): void {
		$health = self::get_all_health();

		if ( ! isset( $health[ $provider ] ) ) {
			$health[ $provider ] = self::default_health();
		}

		$health[ $provider ]['status']       = $error_type; // 'api_error', 'rate_limited', 'disconnected'
		$health[ $provider ]['last_failure']  = gmdate( 'c' );
		$health[ $provider ]['total_requests']++;
		$health[ $provider ]['failed_requests']++;
		$health[ $provider ]['last_error']    = sanitize_text_field( substr( $error, 0, 200 ) );

		update_option( self::OPTION_KEY, $health, false );
	}

	/**
	 * Get health status for a specific provider.
	 *
	 * @since 1.4.0
	 *
	 * @param string $provider Provider slug.
	 * @return array Health data.
	 */
	public static function get_health( string $provider ): array {
		$all = self::get_all_health();
		return $all[ $provider ] ?? self::default_health();
	}

	/**
	 * Get all provider health data.
	 *
	 * @since 1.4.0
	 *
	 * @return array<string, array>
	 */
	public static function get_all_health(): array {
		$health = get_option( self::OPTION_KEY, [] );
		return is_array( $health ) ? $health : [];
	}

	/**
	 * Get success rate for a provider (0-100).
	 *
	 * @since 1.4.0
	 *
	 * @param string $provider Provider slug.
	 * @return int Success rate percentage.
	 */
	public static function get_success_rate( string $provider ): int {
		$health = self::get_health( $provider );
		if ( $health['total_requests'] === 0 ) {
			return 0;
		}
		return (int) round( ( $health['successful_requests'] / $health['total_requests'] ) * 100 );
	}

	/**
	 * Get average response time for a provider in ms.
	 *
	 * @since 1.4.0
	 *
	 * @param string $provider Provider slug.
	 * @return int Average response time in milliseconds.
	 */
	public static function get_avg_response_time( string $provider ): int {
		$health = self::get_health( $provider );
		if ( $health['successful_requests'] === 0 ) {
			return 0;
		}
		return (int) round( $health['total_response_time_ms'] / $health['successful_requests'] );
	}

	/**
	 * Reset health data for a provider.
	 *
	 * @since 1.4.0
	 *
	 * @param string $provider Provider slug.
	 */
	public static function reset( string $provider ): void {
		$health              = self::get_all_health();
		$health[ $provider ] = self::default_health();
		update_option( self::OPTION_KEY, $health, false );
	}

	/**
	 * Reset all health data.
	 *
	 * @since 1.4.0
	 */
	public static function reset_all(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Default health data structure for a new provider.
	 *
	 * @return array
	 */
	private static function default_health(): array {
		return [
			'status'                 => 'unknown',
			'last_success'           => '',
			'last_failure'           => '',
			'last_error'             => '',
			'total_requests'         => 0,
			'successful_requests'    => 0,
			'failed_requests'        => 0,
			'total_response_time_ms' => 0.0,
		];
	}
}
