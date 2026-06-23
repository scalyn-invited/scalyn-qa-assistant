<?php
/**
 * Has_Hooks trait.
 *
 * @package Scalyn\QA\Traits
 */

declare(strict_types=1);

namespace Scalyn\QA\Traits;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Has_Hooks
 *
 * Enforces a register_hooks() contract on classes that use it.
 * When combined with the Singleton trait, register_hooks() is
 * called automatically from the Singleton constructor.
 */
trait Has_Hooks {

	/**
	 * Register all WordPress hooks for this service.
	 */
	abstract protected function register_hooks(): void;
}
