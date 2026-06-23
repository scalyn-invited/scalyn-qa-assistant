<?php
/**
 * Plugin deactivator.
 *
 * @package Scalyn\QA
 */

declare(strict_types=1);

namespace Scalyn\QA;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Handles plugin deactivation tasks.
 */
final class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * Flushes rewrite rules and clears plugin transients.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();

		self::clear_transients();
	}

	/**
	 * Clear all plugin transients.
	 */
	private static function clear_transients(): void {
		delete_transient( 'scalyn_qa_activation_redirect' );

		global $wpdb;

		// Clear any link-check cache transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_scalyn_qa_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_scalyn_qa_' ) . '%'
			)
		);
	}
}
