<?php
/**
 * Analyzer Registry.
 *
 * Manages registration and execution of all QA analyzers.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Analyzer_Registry
 *
 * Central registry for all QA analyzers. Handles registration, retrieval,
 * and orchestrated execution of analyzer checks.
 *
 * @since 1.0.0
 */
class Analyzer_Registry {

	/**
	 * Page audit checks that require a pro SEO plugin.
	 */
	private const PRO_CHECKS = array(
		'focus_keyword',
		'schema_markup',
		'seo_score',
		'social_image_dimensions',
		'readability_score',
	);

	/**
	 * Page audit checks that require any SEO plugin (free or pro).
	 */
	private const SEO_REQUIRED_CHECKS = array(
		'focus_keyword',
		'seo_score',
		'canonical_url',
		'noindex_nofollow',
		'open_graph_tags',
	);

	/**
	 * Registered analyzers keyed by their ID.
	 *
	 * @var array<string, Analyzer_Interface>
	 */
	private array $analyzers = array();

	/**
	 * Register an analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @param Analyzer_Interface $analyzer The analyzer to register.
	 * @return void
	 */
	public function register( Analyzer_Interface $analyzer ): void {
		$this->analyzers[ $analyzer->get_id() ] = $analyzer;
	}

	/**
	 * Get all registered analyzers.
	 *
	 * @since 1.0.0
	 *
	 * @return Analyzer_Interface[]
	 */
	public function get_all(): array {
		return $this->analyzers;
	}

	/**
	 * Get analyzers filtered by category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to filter by ('seo', 'content', or 'functionality').
	 * @return Analyzer_Interface[]
	 */
	public function get_by_category( string $category ): array {
		return array_filter(
			$this->analyzers,
			static fn( Analyzer_Interface $analyzer ): bool => $analyzer->get_category() === $category,
		);
	}

	/**
	 * Run all registered analyzers on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return array{seo: Check_Item[], content: Check_Item[], functionality: Check_Item[]}
	 */
	public function run_all( int $post_id ): array {
		$results = array(
			'seo'           => array(),
			'content'       => array(),
			'functionality' => array(),
		);

		$enabled_checks = $this->get_enabled_checks();
		$locked_checks  = $this->get_locked_checks();

		foreach ( $this->analyzers as $analyzer ) {
			$category = $analyzer->get_category();
			$items    = $analyzer->analyze( $post_id );

			// Filter out disabled, pro-locked, and SEO-required checks.
			$items = array_filter(
				$items,
				static function ( Check_Item $item ) use ( $enabled_checks, $locked_checks ): bool {
					// Always skip locked checks (pro-only or requires SEO plugin).
					if ( in_array( $item->id, $locked_checks, true ) ) {
						return false;
					}

					// If no saved settings, all remaining checks are enabled.
					if ( null === $enabled_checks ) {
						return true;
					}

					return in_array( $item->id, $enabled_checks, true )
						|| ( str_starts_with( $item->id, 'link_' ) && in_array( 'broken_links', $enabled_checks, true ) );
				},
			);

			if ( isset( $results[ $category ] ) ) {
				$results[ $category ] = array_merge( $results[ $category ], array_values( $items ) );
			}
		}

		return $results;
	}

	/**
	 * Get all checks that should be locked based on installed SEO plugins.
	 *
	 * Combines pro-only checks (need pro SEO plugin) and SEO-required checks
	 * (need any SEO plugin at all).
	 *
	 * @return string[]
	 */
	private function get_locked_checks(): array {
		$has_any_seo = defined( 'RANK_MATH_VERSION' )
			|| defined( 'WPSEO_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| defined( 'THE_SEO_FRAMEWORK_VERSION' );

		$has_pro = defined( 'RANK_MATH_PRO_VERSION' )
			|| defined( 'WPSEO_PREMIUM_FILE' )
			|| defined( 'AIOSEO_PRO_VERSION' )
			|| defined( 'SEOPRESS_PRO_VERSION' )
			|| defined( 'THE_SEO_FRAMEWORK_EXTENSION_MANAGER_VERSION' );

		$locked = array();

		if ( ! $has_pro ) {
			$locked = self::PRO_CHECKS;
		}

		if ( ! $has_any_seo ) {
			$locked = array_unique( array_merge( $locked, self::SEO_REQUIRED_CHECKS ) );
		}

		return $locked;
	}

	/**
	 * Get the list of enabled check IDs from page audit settings.
	 *
	 * Returns null if no settings have been saved (all checks enabled by default).
	 *
	 * @return string[]|null
	 */
	private function get_enabled_checks(): ?array {
		$settings = get_option( 'scalyn_qa_page_audit_settings', array() );

		if ( ! is_array( $settings ) || ! isset( $settings['enabled_checks'] ) ) {
			return null;
		}

		$checks = $settings['enabled_checks'];

		return is_array( $checks ) && ! empty( $checks ) ? $checks : null;
	}

	/**
	 * Run all analyzers in a specific category on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to run ('seo', 'content', or 'functionality').
	 * @param int    $post_id  The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function run_category( string $category, int $post_id ): array {
		$results   = array();
		$analyzers = $this->get_by_category( $category );

		foreach ( $analyzers as $analyzer ) {
			$items   = $analyzer->analyze( $post_id );
			$results = array_merge( $results, $items );
		}

		return $results;
	}
}
