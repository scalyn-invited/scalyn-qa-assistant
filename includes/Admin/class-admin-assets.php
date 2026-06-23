<?php
/**
 * Admin Assets.
 *
 * Enqueues CSS and JavaScript on plugin admin pages and the front-end toolbar.
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
 * Class Admin_Assets
 *
 * Conditionally enqueues plugin assets on admin pages and the front-end toolbar.
 *
 * @since 1.0.0
 */
final class Admin_Assets {

	use Singleton;
	use Has_Hooks;

	/**
	 * Register all WordPress hooks.
	 */
	protected function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_toolbar_assets' ) );
	}

	/**
	 * Enqueue admin styles and scripts on plugin pages only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! Admin_Menu::is_plugin_page() ) {
			return;
		}

		$version    = SCALYN_QA_VERSION;
		$plugin_url = SCALYN_QA_PLUGIN_URL;

		// Global admin CSS.
		wp_enqueue_style(
			'scalyn-qa-admin',
			$plugin_url . 'assets/css/admin.css',
			array(),
			$version,
		);

		// SweetAlert2 vendor assets.
		wp_enqueue_style(
			'scalyn-qa-sweetalert2',
			$plugin_url . 'assets/vendor/sweetalert2/sweetalert2.min.css',
			array(),
			$version,
		);

		wp_enqueue_script(
			'scalyn-qa-sweetalert2',
			$plugin_url . 'assets/vendor/sweetalert2/sweetalert2.min.js',
			array(),
			$version,
			true,
		);

		// SweetAlert2 wrapper (ScalynAlert).
		wp_enqueue_script(
			'scalyn-qa-sweetalert-init',
			$plugin_url . 'assets/js/sweetalert-init.js',
			array( 'scalyn-qa-sweetalert2' ),
			$version,
			true,
		);

		// Page-specific JS.
		$page_key = Admin_Menu::get_current_page_key();
		$page_scripts = array(
			'dashboard' => 'admin-dashboard.js',
			'audits'    => 'admin-audit.js',
			'settings'  => 'admin-settings.js',
			'launch'    => 'admin-dashboard.js',
		);

		if ( null !== $page_key && isset( $page_scripts[ $page_key ] ) ) {
			$script_file   = $page_scripts[ $page_key ];
			$script_handle = 'scalyn-qa-' . $page_key;

			wp_enqueue_script(
				$script_handle,
				$plugin_url . 'assets/js/' . $script_file,
				array( 'scalyn-qa-sweetalert-init' ),
				$version,
				true,
			);
		}

		// Enqueue WP media uploader on the settings page (for report logo upload).
		if ( 'settings' === $page_key ) {
			wp_enqueue_media();
		}

		// Localize with the scalynQA object.
		$localize_handle = isset( $script_handle ) ? $script_handle : 'scalyn-qa-sweetalert2';

		wp_localize_script(
			$localize_handle,
			'scalynQA',
			$this->get_localized_data(),
		);
	}

	/**
	 * Enqueue toolbar assets on the front end when the admin bar is showing.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_toolbar_assets(): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$version    = SCALYN_QA_VERSION;
		$plugin_url = SCALYN_QA_PLUGIN_URL;

		wp_enqueue_style(
			'scalyn-qa-toolbar',
			$plugin_url . 'assets/css/toolbar.css',
			array(),
			$version,
		);

		wp_enqueue_script(
			'scalyn-qa-toolbar',
			$plugin_url . 'assets/js/toolbar.js',
			array( 'jquery' ),
			$version,
			true,
		);

		wp_localize_script(
			'scalyn-qa-toolbar',
			'scalynQA',
			$this->get_localized_data(),
		);

		// Enqueue QA Inspector assets for administrators.
		if ( current_user_can( 'manage_options' ) && is_singular() ) {
			wp_enqueue_style(
				'scalyn-qa-inspector',
				$plugin_url . 'assets/css/inspector.css',
				array(),
				$version,
			);

			wp_enqueue_script(
				'scalyn-qa-inspector',
				$plugin_url . 'assets/js/inspector.js',
				array( 'scalyn-qa-toolbar' ),
				$version,
				true,
			);

			// Pass scan data + AI review for the inspector.
			$inspector_post_id = get_queried_object_id();
			$scan_result       = \Scalyn\QA\Models\Scan_Result::load( $inspector_post_id );
			$content_review    = get_post_meta( $inspector_post_id, '_scalyn_qa_content_review', true );
			$ai_drafts_raw     = get_post_meta( $inspector_post_id, '_scalyn_qa_ai_drafts', true );
			$ai_drafts         = is_array( $ai_drafts_raw ) && ! empty( $ai_drafts_raw ) ? end( $ai_drafts_raw ) : null;
			$ai_alt_texts      = get_post_meta( $inspector_post_id, '_scalyn_qa_ai_alt_texts', true );
			$ai_keywords       = get_post_meta( $inspector_post_id, '_scalyn_qa_ai_keywords', true );
			$ai_featured_ids   = get_post_meta( $inspector_post_id, '_scalyn_qa_ai_featured_images', true );
			$ai_featured_ids   = is_array( $ai_featured_ids ) ? $ai_featured_ids : array();
			$ai_featured_opts  = array();
			foreach ( $ai_featured_ids as $fid ) {
				$fid = (int) $fid;
				$url = wp_get_attachment_image_url( $fid, 'thumbnail' );
				if ( $url ) {
					$ai_featured_opts[] = array(
						'id'       => $fid,
						'url'      => $url,
						'filename' => basename( get_attached_file( $fid ) ?: '' ),
					);
				}
			}
			$current_thumb_id = (int) get_post_thumbnail_id( $inspector_post_id );

			// Collect ignored check IDs for this post (global + post-specific).
			$ignored_ids   = array();
			$audit_ignores = \Scalyn\QA\Models\Ignore_Rule::get_for_post( $inspector_post_id );
			foreach ( $audit_ignores as $rule ) {
				$ignored_ids[] = $rule->check_id;
			}

			$inspector_data    = array(
				'postId'           => $inspector_post_id,
				'hasScan'          => null !== $scan_result,
				'results'          => null !== $scan_result ? $scan_result->to_array() : null,
				'contentReview'    => is_array( $content_review ) ? $content_review : null,
				'aiDrafts'         => is_array( $ai_drafts ) ? $ai_drafts : null,
				'aiAltTexts'       => is_array( $ai_alt_texts ) && ! empty( $ai_alt_texts['results'] ) ? true : false,
				'aiKeywords'       => is_array( $ai_keywords ) && ! empty( $ai_keywords ) ? $ai_keywords : false,
				'aiFeatured'       => ! empty( $ai_featured_opts ) ? $ai_featured_opts : false,
				'currentThumbnail' => $current_thumb_id,
				'ignoredChecks'    => array_values( array_unique( $ignored_ids ) ),
				'notes'            => get_post_meta( $inspector_post_id, '_scalyn_qa_notes', true ) ?: array(),
			);

			wp_localize_script(
				'scalyn-qa-inspector',
				'scalynInspector',
				$inspector_data,
			);
		}
	}

	/**
	 * Build the localized data array for the scalynQA JS object.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_localized_data(): array {
		$data = array(
			'restUrl'   => rest_url( 'scalyn-qa/v1/' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => SCALYN_QA_PLUGIN_URL,
			'settings'  => get_option( 'scalyn_qa_settings', array() ),
		);

		// Add current post ID when editing a post.
		$current_post_id = $this->get_current_post_id();

		if ( null !== $current_post_id ) {
			$data['currentPostId'] = $current_post_id;
		}

		return $data;
	}

	/**
	 * Get the current post ID from the editing context.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null Post ID or null if not on a post editing screen.
	 */
	private function get_current_post_id(): ?int {
		// Admin context: check for the post query var.
		if ( is_admin() ) {
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $post_id > 0 ) {
				return $post_id;
			}

			global $post;

			if ( $post instanceof \WP_Post ) {
				return $post->ID;
			}

			return null;
		}

		// Front-end context: get the queried object.
		$queried_object = get_queried_object();

		if ( $queried_object instanceof \WP_Post ) {
			return $queried_object->ID;
		}

		return null;
	}
}
