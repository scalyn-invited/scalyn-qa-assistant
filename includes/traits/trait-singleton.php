<?php
/**
 * Singleton trait.
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
 * Trait Singleton
 *
 * Provides a reusable singleton pattern for service classes.
 */
trait Singleton {

	/**
	 * The single instance of the class.
	 *
	 * @var static|null
	 */
	private static ?self $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return static
	 */
	public static function instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		if ( method_exists( $this, 'register_hooks' ) ) {
			$this->register_hooks();
		}

		if ( method_exists( $this, 'init' ) ) {
			$this->init();
		}
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone(): void {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \RuntimeException Always.
	 */
	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize a singleton.' );
	}
}
