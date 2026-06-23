<?php
/**
 * SEO Integration abstract class.
 *
 * Provides a unified interface for interacting with third-party SEO plugins.
 *
 * @package Scalyn\QA\Integrations
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Integration
 *
 * Abstract base class for SEO plugin integrations.
 *
 * @since 1.0.0
 */
abstract class SEO_Integration {

	/**
	 * Get the human-readable plugin name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract public function get_plugin_name(): string;

	/**
	 * Get the plugin slug (relative path used by WordPress).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract public function get_plugin_slug(): string;

	/**
	 * Check whether the SEO plugin is installed and active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	abstract public function is_active(): bool;

	/**
	 * Get the meta title for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The meta title, or empty string if not set.
	 */
	abstract public function get_meta_title( int $post_id ): string;

	/**
	 * Get the meta description for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The meta description, or empty string if not set.
	 */
	abstract public function get_meta_description( int $post_id ): string;

	/**
	 * Set the meta title for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The post ID.
	 * @param string $title   The meta title to set.
	 * @return bool True on success, false on failure.
	 */
	abstract public function set_meta_title( int $post_id, string $title ): bool;

	/**
	 * Set the meta description for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $description The meta description to set.
	 * @return bool True on success, false on failure.
	 */
	abstract public function set_meta_description( int $post_id, string $description ): bool;

	/**
	 * Detect which SEO plugin is active and return the appropriate integration.
	 *
	 * Checks in order: Rank Math, Yoast SEO, All in One SEO.
	 *
	 * @since 1.0.0
	 *
	 * @return self|null The active SEO integration instance, or null if none detected.
	 */
	public static function detect(): ?SEO_Integration {
		$integrations = array(
			new RankMath_Integration(),
			new Yoast_Integration(),
			new AIOSEO_Integration(),
		);

		foreach ( $integrations as $integration ) {
			if ( $integration->is_active() ) {
				return $integration;
			}
		}

		return null;
	}
}
