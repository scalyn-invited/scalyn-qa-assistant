<?php
/**
 * Scan_Result model.
 *
 * Represents the full scan result for a single post/page.
 *
 * @package Scalyn\QA\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class Scan_Result
 *
 * Holds per-category check results, scores, and persistence logic for a page scan.
 *
 * @since 1.0.0
 */
class Scan_Result {

	/**
	 * Post meta key for scan results.
	 *
	 * @var string
	 */
	private const META_RESULTS = '_scalyn_qa_scan_results';

	/**
	 * Post meta key for scores.
	 *
	 * @var string
	 */
	private const META_SCORES = '_scalyn_qa_scores';

	/**
	 * Post meta key for last scan timestamp.
	 *
	 * @var string
	 */
	private const META_LAST_SCAN = '_scalyn_qa_last_scan';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id    The WordPress post ID.
	 * @param array  $results    Grouped by category: ['seo' => Check_Item[], 'content' => Check_Item[], 'functionality' => Check_Item[]].
	 * @param Score  $scores     The calculated scores.
	 * @param string $scanned_at ISO 8601 datetime of when the scan was performed.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly array $results,
		public readonly Score $scores,
		public readonly string $scanned_at,
	) {
	}

	/**
	 * Saves the scan result to post meta.
	 *
	 * Persists results, scores, and last-scan timestamp as separate meta entries.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void {
		$results_data = array();

		foreach ( $this->results as $category => $items ) {
			$results_data[ $category ] = array_map(
				static fn( Check_Item $item ): array => $item->to_array(),
				$items,
			);
		}

		update_post_meta( $this->post_id, self::META_RESULTS, $results_data );
		update_post_meta( $this->post_id, self::META_SCORES, $this->scores->to_array() );
		update_post_meta( $this->post_id, self::META_LAST_SCAN, $this->scanned_at );
	}

	/**
	 * Loads a scan result from post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The WordPress post ID.
	 * @return self|null The scan result or null if no scan data exists.
	 */
	public static function load( int $post_id ): ?self {
		$results_data = get_post_meta( $post_id, self::META_RESULTS, true );
		$scores_data  = get_post_meta( $post_id, self::META_SCORES, true );
		$scanned_at   = get_post_meta( $post_id, self::META_LAST_SCAN, true );

		if ( empty( $results_data ) || empty( $scores_data ) || empty( $scanned_at ) ) {
			return null;
		}

		if ( ! is_array( $results_data ) || ! is_array( $scores_data ) ) {
			return null;
		}

		$results = array();

		foreach ( $results_data as $category => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}

			$results[ $category ] = array_map(
				static fn( array $item_data ): Check_Item => Check_Item::from_array( $item_data ),
				$items,
			);
		}

		return new self(
			post_id:    $post_id,
			results:    $results,
			scores:     Score::from_array( $scores_data ),
			scanned_at: $scanned_at,
		);
	}

	/**
	 * Converts the scan result to an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		$results_data = array();

		foreach ( $this->results as $category => $items ) {
			$results_data[ $category ] = array_map(
				static fn( Check_Item $item ): array => $item->to_array(),
				$items,
			);
		}

		return array(
			'post_id'    => $this->post_id,
			'results'    => $results_data,
			'scores'     => $this->scores->to_array(),
			'scanned_at' => $this->scanned_at,
		);
	}

	/**
	 * Returns only non-passing Check_Items across all categories.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item[]
	 */
	public function get_issues(): array {
		$issues = array();

		foreach ( $this->results as $items ) {
			foreach ( $items as $item ) {
				if ( ! $item->is_passed() ) {
					$issues[] = $item;
				}
			}
		}

		return $issues;
	}

	/**
	 * Returns the total count of non-passing Check_Items.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_issue_count(): int {
		return count( $this->get_issues() );
	}
}
