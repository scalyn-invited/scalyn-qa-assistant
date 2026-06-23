<?php
/**
 * Rank Math SEO integration.
 *
 * @package Scalyn\QA\Integrations
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class RankMath_Integration
 *
 * Integration with the Rank Math SEO plugin.
 *
 * @since 1.0.0
 */
class RankMath_Integration extends SEO_Integration {

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_name(): string {
		return 'Rank Math';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_plugin_slug(): string {
		return 'seo-by-rank-math/rank-math.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {
		if ( class_exists( '\\RankMath' ) ) {
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
		$title = get_post_meta( $post_id, 'rank_math_title', true );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_meta_description( int $post_id ): string {
		$description = get_post_meta( $post_id, 'rank_math_description', true );

		return is_string( $description ) ? $description : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta_title( int $post_id, string $title ): bool {
		$result = update_post_meta( $post_id, 'rank_math_title', $title );

		return false !== $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta_description( int $post_id, string $description ): bool {
		$result = update_post_meta( $post_id, 'rank_math_description', $description );

		return false !== $result;
	}
}
