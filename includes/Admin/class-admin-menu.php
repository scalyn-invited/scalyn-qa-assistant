<?php
/**
 * Admin Menu.
 *
 * Registers the top-level and submenu pages for the Scalyn QA plugin.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Traits\Singleton;
use Scalyn\QA\Traits\Has_Hooks;

/**
 * Class Admin_Menu
 *
 * Registers the admin menu hierarchy on the 'admin_menu' hook.
 *
 * @since 1.0.0
 */
final class Admin_Menu {

	use Singleton;
	use Has_Hooks;

	/**
	 * Top-level menu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'scalyn-qa';

	/**
	 * Page slugs for each submenu.
	 *
	 * @var array<string, string>
	 */
	public const PAGE_SLUGS = array(
		'dashboard'   => 'scalyn-qa',
		'audits'      => 'scalyn-qa-audits',
		'launch'      => 'scalyn-qa-launch',
		'knowledge'   => 'scalyn-qa-knowledge',
		'settings'    => 'scalyn-qa-settings',
		'system-info' => 'scalyn-qa-system-info',
	);

	/**
	 * Page class instances (lazy-loaded).
	 *
	 * @var array<string, object>
	 */
	private array $pages = array();

	/**
	 * Register all WordPress hooks.
	 */
	protected function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
	}

	/**
	 * Add the top-level menu and all submenu pages.
	 *
	 * @since 1.0.0
	 */
	public function add_menus(): void {
		// Top-level menu (renders the Dashboard page).
		add_menu_page(
			__( 'Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'Scalyn QA', 'scalyn-qa-assistant' ),
			'edit_posts',
			self::PAGE_SLUGS['dashboard'],
			array( $this->get_page( 'dashboard' ), 'render' ),
			self::get_menu_icon(),
			80,
		);

		// Dashboard (same as parent — relabeled).
		add_submenu_page(
			self::PAGE_SLUGS['dashboard'],
			__( 'Dashboard — Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'Dashboard', 'scalyn-qa-assistant' ),
			'edit_posts',
			self::PAGE_SLUGS['dashboard'],
			array( $this->get_page( 'dashboard' ), 'render' ),
		);

		// Page Audits.
		add_submenu_page(
			self::PAGE_SLUGS['dashboard'],
			__( 'Page Audits — Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'Page Audits', 'scalyn-qa-assistant' ),
			'edit_posts',
			self::PAGE_SLUGS['audits'],
			array( $this->get_page( 'audits' ), 'render' ),
		);

		// Launch Checklist.
		add_submenu_page(
			self::PAGE_SLUGS['dashboard'],
			__( 'Launch Checklist — Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'Launch Checklist', 'scalyn-qa-assistant' ),
			'manage_options',
			self::PAGE_SLUGS['launch'],
			array( $this->get_page( 'launch' ), 'render' ),
		);

		// Knowledge Center.
		add_submenu_page(
			self::PAGE_SLUGS['dashboard'],
			__( 'Knowledge Center — Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'Knowledge Center', 'scalyn-qa-assistant' ),
			'edit_posts',
			self::PAGE_SLUGS['knowledge'],
			array( $this->get_page( 'knowledge' ), 'render' ),
		);

		// Settings.
		add_submenu_page(
			self::PAGE_SLUGS['dashboard'],
			__( 'Settings — Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'Settings', 'scalyn-qa-assistant' ),
			'manage_options',
			self::PAGE_SLUGS['settings'],
			array( $this->get_page( 'settings' ), 'render' ),
		);

		// System Info.
		add_submenu_page(
			self::PAGE_SLUGS['dashboard'],
			__( 'System Info — Scalyn QA', 'scalyn-qa-assistant' ),
			__( 'System Info', 'scalyn-qa-assistant' ),
			'manage_options',
			self::PAGE_SLUGS['system-info'],
			array( $this->get_page( 'system-info' ), 'render' ),
		);
	}

	/**
	 * Lazy-load a page class by key.
	 *
	 * @param string $key Page identifier.
	 * @return object The page instance with a render() method.
	 */
	private function get_page( string $key ): object {
		if ( ! isset( $this->pages[ $key ] ) ) {
			$this->pages[ $key ] = match ( $key ) {
				'dashboard'   => new Dashboard_Page(),
				'audits'      => new Audit_Page(),
				'launch'      => new Launch_Page(),
				'knowledge'   => new Knowledge_Page(),
				'settings'    => new Settings_Page(),
				'system-info' => new System_Info_Page(),
			};
		}

		return $this->pages[ $key ];
	}

	/**
	 * Check whether the current admin screen belongs to this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	/**
	 * Get the base64-encoded SVG icon for the admin menu.
	 *
	 * @since 1.0.2
	 *
	 * @return string Data URI for the menu icon.
	 */
	private static function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black">'
			. '<path d="M5.5 10c0-1.4 1-2.5 2.2-2.5.8 0 1.5.4 2 1l.3.4.3-.4c.5-.6 1.2-1 2-1C13.5 7.5 14.5 8.6 14.5 10s-1 2.5-2.2 2.5c-.8 0-1.5-.4-2-1l-.3-.4-.3.4c-.5.6-1.2 1-2 1C6.5 12.5 5.5 11.4 5.5 10zM3 10c0 2.6 2 4.7 4.7 4.7 1.3 0 2.5-.6 3.3-1.5.8.9 2 1.5 3.3 1.5C16.9 14.7 19 12.6 19 10s-2-4.7-4.7-4.7c-1.3 0-2.5.6-3.3 1.5-.8-.9-2-1.5-3.3-1.5C5.1 5.3 3 7.4 3 10z"/>'
			. '</svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public static function is_plugin_page(): bool {
		return null !== self::get_current_page_key();
	}

	/**
	 * Get the current plugin page key (dashboard, audits, etc.) or null.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public static function get_current_page_key(): ?string {
		$screen = get_current_screen();

		if ( null === $screen ) {
			return null;
		}

		$screen_id = $screen->id;

		// Check longer (more specific) slugs first to avoid false matches.
		// e.g. 'scalyn-qa' is a substring of 'scalyn-qa-settings', so dashboard
		// would match every page if checked first.
		$slugs_by_length = self::PAGE_SLUGS;
		uasort( $slugs_by_length, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

		foreach ( $slugs_by_length as $key => $slug ) {
			if ( str_contains( $screen_id, $slug ) ) {
				return $key;
			}
		}

		return null;
	}
}
