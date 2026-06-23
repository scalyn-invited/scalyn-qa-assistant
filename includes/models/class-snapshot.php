<?php
/**
 * Snapshot model.
 *
 * Represents a point-in-time QA score snapshot for trend tracking.
 *
 * @package Scalyn\QA\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class Snapshot
 *
 * Value object and storage manager for historical QA score snapshots.
 *
 * @since 1.0.0
 */
class Snapshot {

	/**
	 * Post meta key for snapshots.
	 *
	 * @var string
	 */
	private const META_SNAPSHOTS = '_scalyn_qa_snapshots';

	/**
	 * Minimum number of snapshots required to determine a trend.
	 *
	 * @var int
	 */
	private const MIN_TREND_SNAPSHOTS = 2;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id         Unique snapshot ID.
	 * @param int    $post_id    The WordPress post ID.
	 * @param Score  $scores     The scores at the time of the snapshot.
	 * @param string $created_at ISO 8601 datetime of when the snapshot was created.
	 * @param array  $summary    Counts of pass/warning/fail results.
	 */
	public function __construct(
		public readonly string $id,
		public readonly int $post_id,
		public readonly Score $scores,
		public readonly string $created_at,
		public readonly array $summary,
	) {
	}

	/**
	 * Creates a Snapshot from an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Associative array of snapshot data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$scores_data = $data['scores'] ?? array();
		$scores      = $scores_data instanceof Score
			? $scores_data
			: Score::from_array( is_array( $scores_data ) ? $scores_data : array() );

		return new self(
			id:         sanitize_key( $data['id'] ?? wp_generate_uuid4() ),
			post_id:    absint( $data['post_id'] ?? 0 ),
			scores:     $scores,
			created_at: sanitize_text_field( $data['created_at'] ?? '' ),
			summary:    is_array( $data['summary'] ?? null ) ? $data['summary'] : array(),
		);
	}

	/**
	 * Converts the Snapshot to an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'post_id'    => $this->post_id,
			'scores'     => $this->scores->to_array(),
			'created_at' => $this->created_at,
			'summary'    => $this->summary,
		);
	}

	/**
	 * Retrieves all snapshots for a given post, ordered by date ascending.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The WordPress post ID.
	 * @return self[]
	 */
	public static function get_for_post( int $post_id ): array {
		$snapshots_data = get_post_meta( $post_id, self::META_SNAPSHOTS, true );

		if ( ! is_array( $snapshots_data ) || empty( $snapshots_data ) ) {
			return array();
		}

		$snapshots = array_map(
			static fn( array $data ): self => self::from_array( $data ),
			array_filter( $snapshots_data, 'is_array' ),
		);

		usort(
			$snapshots,
			static fn( self $a, self $b ): int => strcmp( $a->created_at, $b->created_at ),
		);

		return $snapshots;
	}

	/**
	 * Creates a new snapshot from the current scan results and appends it to storage.
	 *
	 * @since 1.0.0
	 *
	 * @param int          $post_id The WordPress post ID.
	 * @param Score        $scores  The current scores.
	 * @param Check_Item[] $results Flat array of all check items from the scan.
	 * @return self The newly created snapshot.
	 */
	public static function create( int $post_id, Score $scores, array $results ): self {
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
		);

		foreach ( $results as $item ) {
			if ( $item instanceof Check_Item && isset( $summary[ $item->status ] ) ) {
				++$summary[ $item->status ];
			}
		}

		$snapshot = new self(
			id:         wp_generate_uuid4(),
			post_id:    $post_id,
			scores:     $scores,
			created_at: gmdate( 'c' ),
			summary:    $summary,
		);

		$snapshots_data = get_post_meta( $post_id, self::META_SNAPSHOTS, true );
		$snapshots_data = is_array( $snapshots_data ) ? $snapshots_data : array();

		$snapshots_data[] = $snapshot->to_array();

		// Retain only the last N snapshots per post to prevent unbounded growth.
		$max_snapshots = (int) apply_filters( 'scalyn_qa_max_snapshots', 50 );
		if ( count( $snapshots_data ) > $max_snapshots ) {
			$snapshots_data = array_slice( $snapshots_data, -$max_snapshots );
		}

		update_post_meta( $post_id, self::META_SNAPSHOTS, $snapshots_data );

		return $snapshot;
	}

	/**
	 * Determines the score trend for a post based on historical snapshots.
	 *
	 * Compares the most recent overall score with the earliest overall score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The WordPress post ID.
	 * @return string 'improving', 'declining', or 'stable'.
	 */
	public static function get_trend( int $post_id ): string {
		$snapshots = self::get_for_post( $post_id );

		if ( count( $snapshots ) < self::MIN_TREND_SNAPSHOTS ) {
			return 'stable';
		}

		$first = $snapshots[0];
		$last  = $snapshots[ count( $snapshots ) - 1 ];

		$first_score = $first->scores->overall;
		$last_score  = $last->scores->overall;

		if ( $last_score > $first_score ) {
			return 'improving';
		}

		if ( $last_score < $first_score ) {
			return 'declining';
		}

		return 'stable';
	}
}
