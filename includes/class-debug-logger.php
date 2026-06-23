<?php
/**
 * Debug Logger.
 *
 * Provides structured debug logging that can be toggled on/off via
 * the plugin settings. Logs are stored in wp_options and viewable
 * from the Advanced settings tab.
 *
 * @package Scalyn\QA
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Debug_Logger
 *
 * Static utility for writing, reading, and clearing categorised debug
 * log entries. Logging is only active when `debug_mode` is enabled in
 * the plugin settings.
 *
 * @since 1.0.0
 */
final class Debug_Logger {

	/**
	 * Option key where debug log entries are stored.
	 *
	 * @var string
	 */
	private const LOG_OPTION = 'scalyn_qa_debug_log';

	/**
	 * Maximum number of entries kept in the log.
	 *
	 * @var int
	 */
	private const MAX_ENTRIES = 500;

	/**
	 * Check whether debug mode is enabled in settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = get_option( 'scalyn_qa_settings', [] );

		return ! empty( $settings['debug_mode'] );
	}

	/**
	 * Write a categorised log entry.
	 *
	 * Does nothing when debug mode is disabled. Entries are pruned
	 * to {@see MAX_ENTRIES} to prevent unbounded growth.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category Short category key (e.g. 'ai', 'link_checker').
	 * @param string $message  Human-readable log message.
	 * @param array  $context  Optional associative array of contextual data.
	 * @return void
	 */
	public static function log( string $category, string $message, array $context = [] ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$entries   = get_option( self::LOG_OPTION, [] );
		$entries[] = [
			'date'     => gmdate( 'c' ),
			'category' => sanitize_key( $category ),
			'message'  => sanitize_text_field( $message ),
			'context'  => $context,
			'user_id'  => get_current_user_id(),
		];

		// Prune to max entries.
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		update_option( self::LOG_OPTION, $entries, false ); // no autoload.
	}

	/**
	 * Retrieve log entries, optionally filtered by category.
	 *
	 * Returns entries in newest-first order, limited to the
	 * requested count.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $limit    Maximum number of entries to return.
	 * @param string|null $category Optional category filter.
	 * @return array List of log entry arrays.
	 */
	public static function get_log( int $limit = 100, ?string $category = null ): array {
		$entries = get_option( self::LOG_OPTION, [] );

		if ( null !== $category ) {
			$entries = array_filter(
				$entries,
				fn( $e ) => ( $e['category'] ?? '' ) === $category,
			);
		}

		// Return newest first, limited.
		return array_slice( array_reverse( $entries ), 0, $limit );
	}

	/**
	 * Delete all debug log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::LOG_OPTION );
	}

	// ------------------------------------------------------------------
	// Convenience methods
	// ------------------------------------------------------------------

	/**
	 * Log an AI provider failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider name or key.
	 * @param string $error    Error message.
	 * @param array  $context  Optional context data.
	 * @return void
	 */
	public static function ai_failure( string $provider, string $error, array $context = [] ): void {
		self::log( 'ai', "AI failure ({$provider}): {$error}", $context );
	}

	/**
	 * Log a link checker failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url     The URL that failed.
	 * @param string $error   Error message.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	public static function link_failure( string $url, string $error, array $context = [] ): void {
		self::log( 'link_checker', "Link check failed ({$url}): {$error}", $context );
	}

	/**
	 * Log a REST API error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint The REST endpoint.
	 * @param string $error    Error message.
	 * @param array  $context  Optional context data.
	 * @return void
	 */
	public static function rest_error( string $endpoint, string $error, array $context = [] ): void {
		self::log( 'rest_api', "REST error ({$endpoint}): {$error}", $context );
	}
}
