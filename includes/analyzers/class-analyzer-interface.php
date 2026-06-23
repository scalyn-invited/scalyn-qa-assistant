<?php
/**
 * Analyzer Interface.
 *
 * Defines the contract for all QA analyzers.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Interface Analyzer_Interface
 *
 * All analyzers must implement this interface to be registered in the Analyzer_Registry.
 *
 * @since 1.0.0
 */
interface Analyzer_Interface {

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string One of 'seo', 'content', or 'functionality'.
	 */
	public function get_category(): string;

	/**
	 * Run analysis on a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[] Array of check results.
	 */
	public function analyze( int $post_id ): array;
}
