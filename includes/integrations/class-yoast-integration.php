<?php
/**
 * Yoast SEO integration.
 *
 * @package Scalyn\QA\Integrations
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Yoast_Integration
 *
 * Integration with the Yoast SEO plugin.
 *
 * @since 1.0.0
 */
class Yoast_Integration extends SEO_Integration {

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_name(): string {
		return 'Yoast SEO';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_slug(): string {
		return 'wordpress-seo/wp-seo.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {
		if ( class_exists( '\\WPSEO_Options' ) ) {
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
		$title = get_post_meta( $post_id, '_yoast_wpseo_title', true );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_meta_description( int $post_id ): string {
		$description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );

		return is_string( $description ) ? $description : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta_title( int $post_id, string $title ): bool {
		$result = update_post_meta( $post_id, '_yoast_wpseo_title', $title );

		return false !== $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta_description( int $post_id, string $description ): bool {
		$result = update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );

		return false !== $result;
	}
}
