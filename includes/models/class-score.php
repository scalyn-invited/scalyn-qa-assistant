<?php
/**
 * Score model.
 *
 * Represents the QA scores for a scanned page.
 *
 * @package Scalyn\QA\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class Score
 *
 * Immutable value object holding per-category and overall QA scores.
 *
 * @since 1.0.0
 */
class Score {

	/**
	 * Valid status values.
	 *
	 * @var string[]
	 */
	private const VALID_STATUSES = array( 'green', 'yellow', 'red' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $seo           SEO category score (0-100).
	 * @param int    $content       Content category score (0-100).
	 * @param int    $functionality Functionality category score (0-100).
	 * @param int    $overall       Overall score (0-100).
	 * @param string $status        Traffic-light status: 'green', 'yellow', or 'red'.
	 */
	public function __construct(
		public readonly int $seo,
		public readonly int $content,
		public readonly int $functionality,
		public readonly int $overall,
		public readonly string $status,
	) {
	}

	/**
	 * Calculates the traffic-light status for a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score            The score to evaluate (0-100).
	 * @param int $green_threshold  Minimum score for 'green' status. Default 80.
	 * @param int $yellow_threshold Minimum score for 'yellow' status. Default 50.
	 * @return string 'green', 'yellow', or 'red'.
	 */
	public static function calculate_status(
		int $score,
		int $green_threshold = 80,
		int $yellow_threshold = 50,
	): string {
		if ( $score >= $green_threshold ) {
			return 'green';
		}

		if ( $score >= $yellow_threshold ) {
			return 'yellow';
		}

		return 'red';
	}

	/**
	 * Creates a Score from an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Associative array of score data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$seo           = self::clamp_score( (int) ( $data['seo'] ?? 0 ) );
		$content       = self::clamp_score( (int) ( $data['content'] ?? 0 ) );
		$functionality = self::clamp_score( (int) ( $data['functionality'] ?? 0 ) );
		$overall       = self::clamp_score( (int) ( $data['overall'] ?? 0 ) );

		$status = $data['status'] ?? self::calculate_status( $overall );

		if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
			$status = self::calculate_status( $overall );
		}

		return new self(
			seo:           $seo,
			content:       $content,
			functionality: $functionality,
			overall:       $overall,
			status:        $status,
		);
	}

	/**
	 * Converts the Score to an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'seo'           => $this->seo,
			'content'       => $this->content,
			'functionality' => $this->functionality,
			'overall'       => $this->overall,
			'status'        => $this->status,
		);
	}

	/**
	 * Clamps a score to the 0-100 range.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score The score to clamp.
	 * @return int
	 */
	private static function clamp_score( int $score ): int {
		return max( 0, min( 100, $score ) );
	}
}
