<?php
/**
 * Version Migration Runner.
 *
 * Handles sequential database and option migrations when the plugin
 * is upgraded to a new version.
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
 * Class Migrator
 *
 * Runs versioned migrations in order, keeps an auditable log of
 * completed migrations, and fires an action when all migrations finish.
 *
 * @since 1.0.0
 */
final class Migrator {

	/**
	 * Map of version => migration method name.
	 *
	 * Migrations are executed in the order they appear here for every
	 * version that is newer than the currently stored plugin version.
	 *
	 * @var array<string, string>
	 */
	private const MIGRATIONS = [
		// '1.0.1' => 'migrate_to_1_0_1',
		// Future migrations go here.
	];

	/**
	 * Run all pending migrations up to the current plugin version.
	 *
	 * Compares the stored version against SCALYN_QA_VERSION and
	 * executes every migration method whose version key falls between
	 * the two. After all migrations complete, the stored version is
	 * updated and the `scalyn_qa_migrated` action fires.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function run(): void {
		$current = get_option( 'scalyn_qa_version', '0.0.0' );
		$target  = SCALYN_QA_VERSION;

		if ( version_compare( $current, $target, '>=' ) ) {
			return; // Already up to date.
		}

		foreach ( self::MIGRATIONS as $version => $method ) {
			if ( version_compare( $current, $version, '<' ) && method_exists( self::class, $method ) ) {
				self::$method();

				// Log migration.
				self::log( "Migrated to {$version}" );
			}
		}

		// Update stored version.
		update_option( 'scalyn_qa_version', $target );

		/**
		 * Fires after all migrations have completed.
		 *
		 * @since 1.0.0
		 *
		 * @param string $current The version migrated from.
		 * @param string $target  The version migrated to.
		 */
		do_action( 'scalyn_qa_migrated', $current, $target );
	}

	/**
	 * Append a timestamped entry to the migration log.
	 *
	 * Keeps the log trimmed to the last 50 entries to avoid
	 * unbounded option growth.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The log message to record.
	 * @return void
	 */
	private static function log( string $message ): void {
		$log   = get_option( 'scalyn_qa_migration_log', [] );
		$log[] = [
			'date'    => gmdate( 'c' ),
			'message' => $message,
		];

		// Keep last 50 entries.
		if ( count( $log ) > 50 ) {
			$log = array_slice( $log, -50 );
		}

		update_option( 'scalyn_qa_migration_log', $log );
	}

	// Example migration for future use:
	// private static function migrate_to_1_0_1(): void {
	//     $settings = get_option( 'scalyn_qa_settings', [] );
	//     $settings['new_setting'] = 'default_value';
	//     update_option( 'scalyn_qa_settings', $settings );
	// }
}
