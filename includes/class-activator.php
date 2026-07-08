<?php
/**
 * Plugin activator.
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
 * Class Activator
 *
 * Handles plugin activation tasks.
 */
final class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Sets default options, flushes rewrite rules, and sets a
	 * transient for the activation redirect.
	 */
	public static function activate(): void {
		self::set_default_options();

		flush_rewrite_rules();

		set_transient( 'scalyn_qa_activation_redirect', true, 30 );
	}

	/**
	 * Set default plugin options if they do not already exist.
	 */
	private static function set_default_options(): void {
		$defaults = [
			'auto_scan_on_save'        => true,
			'post_types'               => [ 'page', 'post' ],
			'score_green'              => 80,
			'score_yellow'             => 50,
			'link_timeout'             => 10,
			'link_cache_hours'         => 24,
			'delete_data_on_uninstall' => false,
			'max_ai_requests_per_day'  => 0,
			'debug_mode'               => false,
			'github_owner'             => 'scalyn-invited',
			'github_repo'              => 'scalyn-qa-assistant',
			'github_token'             => '',
		];

		if ( false === get_option( 'scalyn_qa_settings' ) ) {
			add_option( 'scalyn_qa_settings', $defaults );
		}

		update_option( 'scalyn_qa_version', SCALYN_QA_VERSION );
	}
}
