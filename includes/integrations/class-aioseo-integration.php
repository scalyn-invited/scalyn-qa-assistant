<?php
/**
 * All in One SEO integration.
 *
 * @package Scalyn\QA\Integrations
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class AIOSEO_Integration
 *
 * Integration with the All in One SEO plugin.
 *
 * @since 1.0.0
 */
class AIOSEO_Integration extends SEO_Integration {

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_name(): string {
		return 'All in One SEO';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_slug(): string {
		return 'all-in-one-seo-pack/all_in_one_seo_pack.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {
		if ( class_exists( '\\AIOSEO\\Plugin\\AIOSEO' ) ) {
			return true;
		}

		if ( function_exists( 'aioseo' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $this->get_plugin_slug() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_meta_title( int $post_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';

		// First check if AIOSEO table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			// Fallback to postmeta for older AIOSEO versions.
			$title = get_post_meta( $post_id, '_aioseo_title', true );
			return is_string( $title ) ? $title : '';
		}

		$title = $wpdb->get_var( $wpdb->prepare(
			"SELECT title FROM {$table} WHERE post_id = %d",
			$post_id
		) );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_meta_description( int $post_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';

		// First check if AIOSEO table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			// Fallback to postmeta for older AIOSEO versions.
			$description = get_post_meta( $post_id, '_aioseo_description', true );
			return is_string( $description ) ? $description : '';
		}

		$description = $wpdb->get_var( $wpdb->prepare(
			"SELECT description FROM {$table} WHERE post_id = %d",
			$post_id
		) );

		return is_string( $description ) ? $description : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta_title( int $post_id, string $title ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false !== update_post_meta( $post_id, '_aioseo_title', $title );
		}

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$table} WHERE post_id = %d",
			$post_id
		) );

		if ( $exists ) {
			return (bool) $wpdb->update( $table, array( 'title' => $title ), array( 'post_id' => $post_id ), array( '%s' ), array( '%d' ) );
		}

		return (bool) $wpdb->insert( $table, array( 'post_id' => $post_id, 'title' => $title ), array( '%d', '%s' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta_description( int $post_id, string $description ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false !== update_post_meta( $post_id, '_aioseo_description', $description );
		}

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$table} WHERE post_id = %d",
			$post_id
		) );

		if ( $exists ) {
			return (bool) $wpdb->update( $table, array( 'description' => $description ), array( 'post_id' => $post_id ), array( '%s' ), array( '%d' ) );
		}

		return (bool) $wpdb->insert( $table, array( 'post_id' => $post_id, 'description' => $description ), array( '%d', '%s' ) );
	}
}
