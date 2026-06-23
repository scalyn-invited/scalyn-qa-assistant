<?php
/**
 * Check_Item model.
 *
 * Represents a single QA check result for a page.
 *
 * @package Scalyn\QA\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class Check_Item
 *
 * Immutable value object representing the result of a single QA check.
 *
 * @since 1.0.0
 */
class Check_Item {

	/**
	 * Valid status values.
	 *
	 * @var string[]
	 */
	private const VALID_STATUSES = array( 'pass', 'warning', 'fail' );

	/**
	 * Valid category values.
	 *
	 * @var string[]
	 */
	private const VALID_CATEGORIES = array( 'seo', 'content', 'functionality' );

	/**
	 * Valid severity values.
	 *
	 * @var string[]
	 */
	private const VALID_SEVERITIES = array( 'critical', 'warning', 'info' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $id        Unique check identifier (e.g., 'meta_title_exists').
	 * @param string  $label     Human-readable label.
	 * @param string  $status    Check status: 'pass', 'warning', or 'fail'.
	 * @param string  $message   Descriptive result message.
	 * @param string  $category  Check category: 'seo', 'content', or 'functionality'.
	 * @param string  $severity  Check severity: 'critical', 'warning', or 'info'.
	 * @param ?string $quick_fix Quick fix action identifier or null.
	 * @param string  $tooltip   Educational tooltip text.
	 * @param array   $details   Additional data.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $status,
		public readonly string $message,
		public readonly string $category,
		public readonly string $severity,
		public readonly ?string $quick_fix = null,
		public readonly string $tooltip = '',
		public readonly array $details = array(),
	) {
	}

	/**
	 * Creates a Check_Item from an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Associative array of check item data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			id:        sanitize_key( $data['id'] ?? '' ),
			label:     sanitize_text_field( $data['label'] ?? '' ),
			status:    self::validate_enum( $data['status'] ?? 'fail', self::VALID_STATUSES, 'fail' ),
			message:   sanitize_text_field( $data['message'] ?? '' ),
			category:  self::validate_enum( $data['category'] ?? 'seo', self::VALID_CATEGORIES, 'seo' ),
			severity:  self::validate_enum( $data['severity'] ?? 'info', self::VALID_SEVERITIES, 'info' ),
			quick_fix: isset( $data['quick_fix'] ) ? sanitize_key( $data['quick_fix'] ) : null,
			tooltip:   sanitize_text_field( $data['tooltip'] ?? '' ),
			details:   is_array( $data['details'] ?? null ) ? $data['details'] : array(),
		);
	}

	/**
	 * Converts the Check_Item to an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'        => $this->id,
			'label'     => $this->label,
			'status'    => $this->status,
			'message'   => $this->message,
			'category'  => $this->category,
			'severity'  => $this->severity,
			'quick_fix' => $this->quick_fix,
			'tooltip'   => $this->tooltip,
			'details'   => $this->details,
		);
	}

	/**
	 * Checks whether this check item has passed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the status is 'pass'.
	 */
	public function is_passed(): bool {
		return 'pass' === $this->status;
	}

	/**
	 * Checks whether this check item has failed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the status is 'fail'.
	 */
	public function is_failed(): bool {
		return 'fail' === $this->status;
	}

	/**
	 * Validates a value against an allowed set, returning a default if invalid.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $value   The value to validate.
	 * @param string[] $allowed Allowed values.
	 * @param string   $default Default value if validation fails.
	 * @return string
	 */
	private static function validate_enum( string $value, array $allowed, string $default ): string {
		return in_array( $value, $allowed, true ) ? $value : $default;
	}
}
