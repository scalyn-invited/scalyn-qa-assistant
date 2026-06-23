<?php
/**
 * Launch Checker.
 *
 * Runs pre-launch QA checks to verify site readiness.
 *
 * @package Scalyn\QA\Launch
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Launch;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Integrations\SEO_Integration;
use Scalyn\QA\Models\Check_Item;

/**
 * Class Launch_Checker
 *
 * Performs a suite of pre-launch checks and stores the results.
 *
 * @since 1.0.0
 */
class Launch_Checker {

	/**
	 * Option key for storing launch check results.
	 *
	 * @var string
	 */
	private const RESULTS_OPTION = 'scalyn_qa_launch_results';

	/**
	 * Option key for storing the last scan timestamp.
	 *
	 * @var string
	 */
	private const LAST_SCAN_OPTION = 'scalyn_qa_launch_last_scan';

	/**
	 * Auto-fixable check IDs and their labels.
	 *
	 * @var array<string, string>
	 */
	private const AUTO_FIXABLE = array(
		'default_plugins_cleanup'  => 'Remove default plugins',
		'security_plugin'          => 'Activate security plugin',
		'cache_plugin'             => 'Activate cache plugin',
		'backup_plugin'            => 'Activate backup plugin',
		'smtp_plugin'              => 'Activate mail plugin',
		'image_optimization_plugin' => 'Activate image optimization plugin',
		'comments_open'            => 'Close comments on new posts',
		'default_tagline'          => 'Clear default tagline',
		'default_content_cleanup'  => 'Trash sample content',
		'permalink_structure'      => 'Set permalinks to Post name',
		'search_engine_visibility' => 'Allow search engine indexing',
		'llms_txt'                 => 'Generate llms.txt',
		'privacy_policy_exists'    => 'Create a Privacy Policy page',
		'breadcrumbs_enabled'      => 'Enable breadcrumbs',
		'redirect_manager'         => 'Enable redirect manager',
		'four_oh_four_monitor'     => 'Enable 404 monitor',
		'instant_indexing'         => 'Enable instant indexing',
		'contact_page_exists'      => 'Create a Contact page',
		'robots_txt'               => 'Fix robots.txt',
		// Module toggles — kept callable but buttons are controlled by check methods via quick_fix.
		'breadcrumbs_enabled'      => 'Enable breadcrumbs',
		'redirect_manager'         => 'Enable redirect manager',
		'four_oh_four_monitor'     => 'Enable 404 monitor',
		'instant_indexing'         => 'Enable instant indexing',
	);

	/**
	 * Get the list of auto-fixable check IDs.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string> Check ID => human-readable action label.
	 */
	public static function get_auto_fixable(): array {
		return self::AUTO_FIXABLE;
	}

	/**
	 * Apply an auto-fix for a specific check.
	 *
	 * @since 1.3.0
	 *
	 * @param string $check_id The check ID to fix.
	 * @return array{success: bool, message: string}
	 */
	/**
	 * Checks that can be applied from AI panels (not shown as Auto Fix buttons).
	 */
	private const AI_APPLYABLE = array( 'cornerstone_content', 'local_business_schema' );

	public function auto_fix( string $check_id, string $content = '' ): array {
		if ( ! isset( self::AUTO_FIXABLE[ $check_id ] ) && ! in_array( $check_id, self::AI_APPLYABLE, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'This check cannot be auto-configured.', 'scalyn-qa-assistant' ),
			);
		}

		return match ( $check_id ) {
			'default_plugins_cleanup'  => $this->fix_default_plugins(),
			'comments_open'            => $this->fix_comments_open(),
			'default_tagline'          => $this->fix_default_tagline( $content ),
			'default_content_cleanup'  => $this->fix_default_content(),
			'permalink_structure'      => $this->fix_permalink_structure(),
			'search_engine_visibility' => $this->fix_search_engine_visibility(),
			'llms_txt'                 => $this->fix_llms_txt( $content ),
			'privacy_policy_exists'    => $this->fix_privacy_policy( $content ),
			'breadcrumbs_enabled'      => $this->fix_breadcrumbs(),
			'redirect_manager'         => $this->fix_redirect_manager(),
			'four_oh_four_monitor'     => $this->fix_404_monitor(),
			'instant_indexing'         => $this->fix_instant_indexing(),
			'robots_txt'               => $this->fix_robots_txt(),
			'contact_page_exists'      => $this->fix_contact_page( $content ),
			'cornerstone_content'      => $this->fix_cornerstone_content( $content ),
			'local_business_schema'    => $this->fix_local_business_schema( $content ),
			'security_plugin',
			'cache_plugin',
			'backup_plugin',
			'smtp_plugin',
			'image_optimization_plugin' => $this->fix_activate_plugin( $check_id ),
			default                    => array( 'success' => false, 'message' => __( 'Unknown fix.', 'scalyn-qa-assistant' ) ),
		};
	}

	/**
	 * Fix: close comments on new posts by default.
	 */
	private function fix_comments_open(): array {
		update_option( 'default_comment_status', 'closed' );
		return array( 'success' => true, 'message' => __( 'Comments closed on new posts.', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Fix: clear the default WordPress tagline.
	 */
	private function fix_default_tagline( string $content = '' ): array {
		if ( '' !== $content ) {
			update_option( 'blogdescription', sanitize_text_field( $content ) );
			return array( 'success' => true, 'message' => sprintf(
				__( 'Tagline updated to: "%s"', 'scalyn-qa-assistant' ),
				esc_html( $content ),
			) );
		}

		update_option( 'blogdescription', '' );
		return array( 'success' => true, 'message' => __( 'Default tagline cleared.', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Fix: trash default sample content.
	 */
	private function fix_default_content(): array {
		global $wpdb;
		$actions = array();

		// Trash "Hello World" post.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hello_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status != %s LIMIT 1",
			'hello-world',
			'trash',
		) );
		if ( $hello_id ) {
			wp_trash_post( (int) $hello_id );
			$actions[] = __( 'Trashed "Hello World" post', 'scalyn-qa-assistant' );
		}

		// Trash "Sample Page".
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sample_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status != %s LIMIT 1",
			'sample-page',
			'trash',
		) );
		if ( $sample_id ) {
			wp_trash_post( (int) $sample_id );
			$actions[] = __( 'Trashed "Sample Page"', 'scalyn-qa-assistant' );
		}

		// Delete default comment.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$comment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_author = %s LIMIT 1",
			'A WordPress Commenter',
		) );
		if ( $comment_id ) {
			wp_delete_comment( (int) $comment_id, true );
			$actions[] = __( 'Deleted sample comment', 'scalyn-qa-assistant' );
		}

		if ( empty( $actions ) ) {
			return array( 'success' => true, 'message' => __( 'No default content found to clean up.', 'scalyn-qa-assistant' ) );
		}

		return array( 'success' => true, 'message' => implode( '. ', $actions ) . '.' );
	}

	/**
	 * Fix: set permalink structure to /%postname%/.
	 */
	private function fix_permalink_structure(): array {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
		return array( 'success' => true, 'message' => __( 'Permalinks set to "Post name" (/%postname%/).', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Find the first installed-but-inactive plugin from a list.
	 *
	 * @param array<string, string> $plugins Plugin file => name map.
	 * @return array{file: string, name: string}|null
	 */
	private function find_inactive_plugin( array $plugins ): ?array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $plugins as $file => $name ) {
			if ( ! is_plugin_active( $file ) && file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
				return array( 'file' => $file, 'name' => $name );
			}
		}

		return null;
	}

	/**
	 * Plugin lists for each recommended-plugin check.
	 */
	private function get_recommended_plugins( string $check_id ): array {
		return match ( $check_id ) {
			'security_plugin' => array(
				'wordfence/wordfence.php'                     => 'Wordfence',
				'sucuri-scanner/sucuri.php'                   => 'Sucuri',
				'better-wp-security/better-wp-security.php'  => 'Solid Security',
				'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security',
				'wp-simple-firewall/icwp-wpsf.php'           => 'Shield Security',
				'defender-security/wp-defender.php'           => 'Defender',
				'malcare-security/malcare.php'               => 'MalCare',
				'secupress/secupress.php'                    => 'SecuPress',
				'bulletproof-security/bulletproof-security.php' => 'BulletProof Security',
				'wp-cerber/wp-cerber.php'                    => 'WP Cerber',
				'ninjafirewall/ninjafirewall.php'             => 'NinjaFirewall',
				'security-ninja/security-ninja.php'          => 'Security Ninja',
				'patchstack/patchstack.php'                  => 'Patchstack',
				'bbq-firewall/bbq-firewall.php'              => 'BBQ Firewall',
				'loginizer/loginizer.php'                    => 'Loginizer',
				'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => 'Limit Login Attempts',
			),
			'cache_plugin' => array(
				'wp-super-cache/wp-cache.php'                => 'WP Super Cache',
				'w3-total-cache/w3-total-cache.php'          => 'W3 Total Cache',
				'wp-fastest-cache/wpFastestCache.php'        => 'WP Fastest Cache',
				'litespeed-cache/litespeed-cache.php'        => 'LiteSpeed Cache',
				'wp-rocket/wp-rocket.php'                    => 'WP Rocket',
				'autoptimize/autoptimize.php'                => 'Autoptimize',
				'cache-enabler/cache-enabler.php'            => 'Cache Enabler',
				'sg-cachepress/sg-cachepress.php'            => 'SG Optimizer',
				'breeze/breeze.php'                          => 'Breeze',
				'hummingbird-performance/wp-hummingbird.php'  => 'Hummingbird',
				'nitropack/main.php'                         => 'NitroPack',
				'searchpro/berqwp.php'                       => 'BerqWP',
			),
			'backup_plugin' => array(
				'updraftplus/updraftplus.php'                => 'UpdraftPlus',
				'backwpup/backwpup.php'                     => 'BackWPup',
				'duplicator/duplicator.php'                  => 'Duplicator',
				'blogvault-real-time-backup/developer.php'   => 'BlogVault',
				'all-in-one-wp-migration/all-in-one-wp-migration.php' => 'All-in-One WP Migration',
				'All-In-One-WP-Migration-With-Import-master/all-in-one-wp-migration-wi.php' => 'All-in-One WP Migration',
				'jetpack/jetpack.php'                        => 'Jetpack',
				'backup-backup/developer.php'                => 'Starter Templates',
			),
			'smtp_plugin' => array(
				'wp-mail-smtp/wp_mail_smtp.php'              => 'WP Mail SMTP',
				'fluent-smtp/fluent-smtp.php'                => 'FluentSMTP',
				'post-smtp/postman-smtp.php'                 => 'Post SMTP',
				'easy-wp-smtp/easy-wp-smtp.php'              => 'Easy WP SMTP',
				'smtp-mailer/main.php'                       => 'SMTP Mailer',
				'wp-smtp/wp-smtp.php'                        => 'WP SMTP',
			),
			'image_optimization_plugin' => array(
				'wp-smushit/wp-smush.php'                    => 'Smush',
				'imagify/imagify.php'                        => 'Imagify',
				'shortpixel-image-optimiser/wp-shortpixel.php' => 'ShortPixel',
				'ewww-image-optimizer/ewww-image-optimizer.php' => 'EWWW Image Optimizer',
				'tiny-compress-images/tiny-compress-images.php' => 'TinyPNG',
				'optimole-wp/optimole-wp.php'                => 'Optimole',
			),
			default => array(),
		};
	}

	/**
	 * Fix: activate the first installed-but-inactive plugin for a check.
	 */
	private function fix_activate_plugin( string $check_id ): array {
		$plugins  = $this->get_recommended_plugins( $check_id );
		$inactive = $this->find_inactive_plugin( $plugins );

		if ( null === $inactive ) {
			return array( 'success' => false, 'message' => __( 'No installed plugin found to activate.', 'scalyn-qa-assistant' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Prevent plugins from redirecting during activation (kills REST response).
		add_filter( 'wp_redirect', '__return_false', 9999 );

		// Buffer output — some plugins echo content during activation.
		ob_start();

		// Use silent activation to prevent activation hooks from interfering.
		$result = activate_plugin( $inactive['file'], '', false, true );

		ob_end_clean();

		remove_filter( 'wp_redirect', '__return_false', 9999 );

		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'message' => $result->get_error_message() );
		}

		// Manually fire the activation hook now that we're safe.
		do_action( 'activated_plugin', $inactive['file'], false );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: plugin name */
				__( '%s activated successfully.', 'scalyn-qa-assistant' ),
				$inactive['name'],
			),
		);
	}

	/**
	 * Fix: allow search engines to index the site.
	 */
	private function fix_search_engine_visibility(): array {
		update_option( 'blog_public', '1' );
		return array( 'success' => true, 'message' => __( 'Search engine indexing enabled.', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Fix: create a Privacy Policy page and set it as the WP privacy page.
	 */
	private function fix_privacy_policy( string $content = '' ): array {
		global $wpdb;

		$page_content    = '' !== $content ? $content : $this->get_privacy_policy_template();
		$has_new_content = '' !== $content;

		// 1. Check if WP already has one assigned.
		$existing_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		if ( $existing_id > 0 ) {
			$page = get_post( $existing_id );

			// Page exists (even if trashed) — update and republish it.
			if ( $page && 'trash' !== $page->post_status ) {
				wp_update_post( array(
					'ID'           => $existing_id,
					'post_status'  => 'publish',
					'post_content' => $page_content,
				) );

				return array( 'success' => true, 'message' => sprintf(
					__( 'Privacy page "%s" (ID: %d) updated.', 'scalyn-qa-assistant' ),
					$page->post_title,
					$existing_id,
				) );
			}
		}

		// 2. Look for an existing page with "privacy-policy" slug specifically (not partial match).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')
				AND post_name = %s
				LIMIT 1",
				'page',
				'privacy-policy',
			),
		);

		if ( $found ) {
			wp_update_post( array(
				'ID'           => (int) $found->ID,
				'post_status'  => 'publish',
				'post_content' => $page_content,
			) );
			update_option( 'wp_page_for_privacy_policy', (int) $found->ID );

			return array( 'success' => true, 'message' => sprintf(
				__( 'Existing page "%s" (ID: %d) updated and assigned as privacy policy.', 'scalyn-qa-assistant' ),
				$found->post_title,
				(int) $found->ID,
			) );
		}

		// 3. No existing page — create a new one.
		$page_id = wp_insert_post( array(
			'post_title'   => __( 'Privacy Policy', 'scalyn-qa-assistant' ),
			'post_content' => $page_content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( is_wp_error( $page_id ) || 0 === $page_id ) {
			return array( 'success' => false, 'message' => __( 'Failed to create the Privacy Policy page.', 'scalyn-qa-assistant' ) );
		}

		update_option( 'wp_page_for_privacy_policy', $page_id );

		return array( 'success' => true, 'message' => sprintf(
			__( 'Privacy Policy page created (ID: %d) and set as the site privacy page. Edit it to match your specific data practices.', 'scalyn-qa-assistant' ),
			$page_id,
		) );
	}

	/**
	 * Fix: enable breadcrumbs in the active SEO plugin.
	 */
	/**
	 * Fix: write a proper robots.txt file.
	 *
	 * If a physical robots.txt exists with "Disallow: /", it replaces it.
	 * If no physical file exists, it creates one.
	 */
	private function fix_robots_txt(): array {
		$file_path = ABSPATH . 'robots.txt';
		$sitemap   = '';

		// Try to find the sitemap URL.
		$sitemap_paths = array( '/sitemap_index.xml', '/sitemap.xml', '/wp-sitemap.xml' );
		foreach ( $sitemap_paths as $path ) {
			$url      = home_url( $path );
			$response = wp_remote_head( $url, array( 'timeout' => 5, 'sslverify' => false ) );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$sitemap = $url;
				break;
			}
		}

		$lines = array();
		$lines[] = 'User-agent: *';
		$lines[] = 'Allow: /';
		$lines[] = '';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Allow: /wp-admin/admin-ajax.php';
		$lines[] = '';
		if ( '' !== $sitemap ) {
			$lines[] = 'Sitemap: ' . $sitemap;
			$lines[] = '';
		}

		$content = implode( "\n", $lines );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $file_path, $content );

		if ( false === $written ) {
			return array( 'success' => false, 'message' => __( 'Could not write robots.txt. Check file permissions on the WordPress root directory.', 'scalyn-qa-assistant' ) );
		}

		return array( 'success' => true, 'message' => __( 'robots.txt created with proper Allow/Disallow rules and sitemap reference.', 'scalyn-qa-assistant' ) );
	}

	private function fix_breadcrumbs(): array {
		// Rank Math: add 'breadcrumbs' to rank_math_modules.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( ! in_array( 'breadcrumbs', $modules, true ) ) {
				$modules[] = 'breadcrumbs';
				update_option( 'rank_math_modules', $modules );
			}
			return array( 'success' => true, 'message' => __( 'Breadcrumbs module enabled in Rank Math. Add the breadcrumb shortcode or widget to your theme.', 'scalyn-qa-assistant' ) );
		}

		// Yoast: enable breadcrumbs-enable option.
		if ( defined( 'WPSEO_VERSION' ) && class_exists( '\WPSEO_Options' ) ) {
			\WPSEO_Options::set( 'breadcrumbs-enable', true );
			return array( 'success' => true, 'message' => __( 'Breadcrumbs enabled in Yoast SEO. Add the yoast_breadcrumb() function to your theme.', 'scalyn-qa-assistant' ) );
		}

		// SEOPress: toggle breadcrumbs.
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$toggle = get_option( 'seopress_toggle', array() );
			$toggle['toggle-breadcrumbs'] = '1';
			update_option( 'seopress_toggle', $toggle );
			return array( 'success' => true, 'message' => __( 'Breadcrumbs enabled in SEOPress.', 'scalyn-qa-assistant' ) );
		}

		return array( 'success' => false, 'message' => __( 'No supported SEO plugin found to enable breadcrumbs.', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Fix: enable redirect manager module.
	 */
	private function fix_redirect_manager(): array {
		// Rank Math: add 'redirections' module.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( ! in_array( 'redirections', $modules, true ) ) {
				$modules[] = 'redirections';
				update_option( 'rank_math_modules', $modules );
			}
			return array( 'success' => true, 'message' => __( 'Redirections module enabled in Rank Math.', 'scalyn-qa-assistant' ) );
		}

		if ( defined( 'WPSEO_PREMIUM_FILE' ) ) {
			return array( 'success' => true, 'message' => __( 'Yoast Premium redirects are active by default.', 'scalyn-qa-assistant' ) );
		}

		$seo = $this->detect_seo_pro();
		$plugin_name = $seo['plugin'] ?: __( 'Your SEO plugin', 'scalyn-qa-assistant' );

		return array( 'success' => false, 'message' => sprintf(
			__( '%s does not support auto-enabling redirects. Install the free "Redirection" plugin or upgrade to a Pro SEO plugin.', 'scalyn-qa-assistant' ),
			$plugin_name,
		) );
	}

	/**
	 * Fix: enable 404 monitor module.
	 */
	private function fix_404_monitor(): array {
		// Rank Math: add '404-monitor' module.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( ! in_array( '404-monitor', $modules, true ) ) {
				$modules[] = '404-monitor';
				update_option( 'rank_math_modules', $modules );
			}
			return array( 'success' => true, 'message' => __( '404 Monitor module enabled in Rank Math.', 'scalyn-qa-assistant' ) );
		}

		$seo = $this->detect_seo_pro();
		$plugin_name = $seo['plugin'] ?: __( 'Your SEO plugin', 'scalyn-qa-assistant' );

		return array( 'success' => false, 'message' => sprintf(
			__( '%s does not have a built-in 404 monitor. Install the free "Redirection" or "404 to 301" plugin from the WordPress plugin directory.', 'scalyn-qa-assistant' ),
			$plugin_name,
		) );
	}

	/**
	 * Fix: enable instant indexing module.
	 */
	private function fix_instant_indexing(): array {
		// Rank Math Pro: add 'instant-indexing' module.
		if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( ! in_array( 'instant-indexing', $modules, true ) ) {
				$modules[] = 'instant-indexing';
				update_option( 'rank_math_modules', $modules );
			}
			return array( 'success' => true, 'message' => __( 'Instant Indexing module enabled in Rank Math Pro.', 'scalyn-qa-assistant' ) );
		}

		if ( defined( 'WPSEO_PREMIUM_FILE' ) && defined( 'WPSEO_VERSION' ) && version_compare( WPSEO_VERSION, '21.0', '>=' ) ) {
			return array( 'success' => true, 'message' => __( 'IndexNow is active by default in Yoast Premium v21+.', 'scalyn-qa-assistant' ) );
		}

		return array( 'success' => false, 'message' => __( 'Requires Rank Math Pro or Yoast Premium v21+. Or install the free IndexNow plugin.', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Fix: create a Contact page.
	 */
	private function fix_contact_page( string $content = '' ): array {
		global $wpdb;

		$page_content = '' !== $content ? $content : $this->get_contact_page_template();

		// Check if a contact page already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')
				AND (post_name LIKE %s OR post_title LIKE %s)
				LIMIT 1",
				'page',
				'%contact%',
				'%contact%',
			),
		);

		if ( $found ) {
			// Update existing page.
			if ( '' !== $content ) {
				wp_update_post( array( 'ID' => (int) $found->ID, 'post_content' => $page_content, 'post_status' => 'publish' ) );
				return array( 'success' => true, 'message' => sprintf(
					__( 'Contact page "%s" (ID: %d) updated.', 'scalyn-qa-assistant' ),
					$found->post_title,
					(int) $found->ID,
				) );
			}
			wp_update_post( array( 'ID' => (int) $found->ID, 'post_status' => 'publish' ) );
			return array( 'success' => true, 'message' => sprintf(
				__( 'Contact page "%s" (ID: %d) published.', 'scalyn-qa-assistant' ),
				$found->post_title,
				(int) $found->ID,
			) );
		}

		// Create new page.
		$page_id = wp_insert_post( array(
			'post_title'   => __( 'Contact', 'scalyn-qa-assistant' ),
			'post_content' => $page_content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( is_wp_error( $page_id ) || 0 === $page_id ) {
			return array( 'success' => false, 'message' => __( 'Failed to create Contact page.', 'scalyn-qa-assistant' ) );
		}

		return array( 'success' => true, 'message' => sprintf(
			__( 'Contact page created (ID: %d). Add a contact form plugin (Contact Form 7, WPForms) for the form.', 'scalyn-qa-assistant' ),
			$page_id,
		) );
	}

	/**
	 * Get a starter Contact page template.
	 */
	private function get_contact_page_template(): string {
		$site_name = get_bloginfo( 'name' );
		$email     = get_option( 'admin_email', '' );

		return <<<HTML
<!-- wp:heading -->
<h2>Get in Touch</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We'd love to hear from you. Whether you have a question, feedback, or just want to say hello, feel free to reach out.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Contact Information</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Email: {$email}</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Send Us a Message</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><em>Add a contact form here using Contact Form 7, WPForms, or your preferred form plugin.</em></p>
<!-- /wp:paragraph -->
HTML;
	}

	/**
	 * Fix: mark pages as cornerstone/pillar content.
	 *
	 * @param string $content JSON string of page titles, or comma-separated titles.
	 */
	private function fix_cornerstone_content( string $content = '' ): array {
		$page_titles = array();

		if ( '' !== $content ) {
			// Try JSON first.
			$decoded = json_decode( $content, true );
			if ( is_array( $decoded ) ) {
				$page_titles = $decoded;
			} else {
				$page_titles = array_map( 'trim', explode( ',', $content ) );
			}
		}

		// If no titles provided, try AI-stored suggestions.
		if ( empty( $page_titles ) ) {
			$ai_content = get_option( 'scalyn_qa_launch_ai_content', array() );
			if ( is_array( $ai_content ) && ! empty( $ai_content['cornerstone'] ) ) {
				$page_titles = (array) $ai_content['cornerstone'];
			}
		}

		if ( empty( $page_titles ) ) {
			return array( 'success' => false, 'message' => __( 'No page titles provided. Use "Generate with AI" first to get suggestions.', 'scalyn-qa-assistant' ) );
		}

		$marked  = array();
		$not_found = array();

		foreach ( $page_titles as $title ) {
			$title = trim( $title );
			if ( '' === $title ) {
				continue;
			}

			$page = get_page_by_title( $title, OBJECT, array( 'page', 'post' ) );

			if ( ! $page ) {
				// Try partial match.
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$page = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID, post_title FROM {$wpdb->posts}
						WHERE post_status = 'publish' AND post_title LIKE %s
						LIMIT 1",
						'%' . $wpdb->esc_like( $title ) . '%',
					),
				);
			}

			if ( $page ) {
				$page_id = is_object( $page ) ? (int) ( $page->ID ?? $page->id ?? 0 ) : 0;
				if ( $page_id > 0 ) {
					// Yoast.
					if ( defined( 'WPSEO_VERSION' ) ) {
						update_post_meta( $page_id, '_yoast_wpseo_is_cornerstone', '1' );
					}
					// Rank Math.
					if ( defined( 'RANK_MATH_VERSION' ) ) {
						update_post_meta( $page_id, 'rank_math_pillar_content', 'on' );
					}
					$marked[] = $page->post_title ?? $title;
				}
			} else {
				$not_found[] = $title;
			}
		}

		if ( empty( $marked ) ) {
			return array( 'success' => false, 'message' => sprintf(
				__( 'Could not find pages to mark: %s', 'scalyn-qa-assistant' ),
				implode( ', ', $not_found ),
			) );
		}

		$message = sprintf(
			_n( '%d page marked as cornerstone: %s.', '%d pages marked as cornerstone: %s.', count( $marked ), 'scalyn-qa-assistant' ),
			count( $marked ),
			implode( ', ', $marked ),
		);

		if ( ! empty( $not_found ) ) {
			$message .= ' ' . sprintf(
				__( 'Not found: %s.', 'scalyn-qa-assistant' ),
				implode( ', ', $not_found ),
			);
		}

		return array( 'success' => true, 'message' => $message );
	}

	/**
	 * Fix: configure Local Business schema.
	 *
	 * Writes to Rank Math Pro / SEOPress Pro settings if available.
	 * Otherwise stores JSON-LD in our own option and hooks it to wp_head.
	 *
	 * @param string $content JSON string with business data, or empty for AI-stored data.
	 */
	private function fix_local_business_schema( string $content = '' ): array {
		$data = array();

		if ( '' !== $content ) {
			$data = json_decode( $content, true );
		}

		// Fallback to AI-stored suggestions.
		if ( empty( $data ) || ! is_array( $data ) ) {
			$ai_content = get_option( 'scalyn_qa_launch_ai_content', array() );
			$data = is_array( $ai_content ) ? ( $ai_content['local_business'] ?? array() ) : array();
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'No business data provided. Use "Generate with AI" first.', 'scalyn-qa-assistant' ) );
		}

		// Update AI content option so the inline form reflects the latest values.
		$ai_stored = get_option( 'scalyn_qa_launch_ai_content', array() );
		if ( is_array( $ai_stored ) ) {
			$ai_stored['local_business'] = $data;
			update_option( 'scalyn_qa_launch_ai_content', $ai_stored, false );
		}

		$biz_type = sanitize_text_field( $data['type'] ?? 'LocalBusiness' );
		$biz_name = sanitize_text_field( $data['name'] ?? get_bloginfo( 'name' ) );
		$biz_desc = sanitize_text_field( $data['description'] ?? '' );
		$biz_phone = sanitize_text_field( $data['phone'] ?? '' );
		$biz_email = sanitize_email( $data['email'] ?? get_option( 'admin_email', '' ) );

		// Rank Math Pro: write to rank_math_titles option.
		if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( ! in_array( 'local-seo', $modules, true ) ) {
				$modules[] = 'local-seo';
				update_option( 'rank_math_modules', $modules );
			}

			$titles = get_option( 'rank_math_titles', array() );
			if ( ! is_array( $titles ) ) {
				$titles = array();
			}
			$titles['local_business_type'] = $biz_type;
			$titles['local_business_name'] = $biz_name;
			$titles['local_description']   = $biz_desc;
			$titles['local_phone']         = $biz_phone;
			$titles['local_email']         = $biz_email;
			$titles['local_url']           = home_url( '/' );
			update_option( 'rank_math_titles', $titles );

			return array( 'success' => true, 'message' => sprintf(
				__( 'Local Business schema configured in Rank Math Pro (type: %s).', 'scalyn-qa-assistant' ),
				$biz_type,
			) );
		}

		// SEOPress Pro: write to seopress_pro_option_name.
		if ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			$opts = get_option( 'seopress_pro_option_name', array() );
			if ( ! is_array( $opts ) ) {
				$opts = array();
			}
			$opts['seopress_local_business_type']  = $biz_type;
			$opts['seopress_local_business_name']  = $biz_name;
			$opts['seopress_local_business_phone'] = $biz_phone;
			$opts['seopress_local_business_email'] = $biz_email;
			$opts['seopress_local_business_url']   = home_url( '/' );
			update_option( 'seopress_pro_option_name', $opts );

			return array( 'success' => true, 'message' => sprintf(
				__( 'Local Business schema configured in SEOPress Pro (type: %s).', 'scalyn-qa-assistant' ),
				$biz_type,
			) );
		}

		// Fallback: store JSON-LD in our own option for wp_head output.
		$jsonld = array(
			'@context'    => 'https://schema.org',
			'@type'       => $biz_type,
			'name'        => $biz_name,
			'description' => $biz_desc,
			'url'         => home_url( '/' ),
		);
		if ( '' !== $biz_phone ) {
			$jsonld['telephone'] = $biz_phone;
		}
		if ( '' !== $biz_email ) {
			$jsonld['email'] = $biz_email;
		}

		update_option( 'scalyn_qa_local_business_jsonld', $jsonld, false );

		// Register the wp_head hook if not already.
		if ( ! has_action( 'wp_head', array( __CLASS__, 'output_local_business_jsonld' ) ) ) {
			add_action( 'wp_head', array( __CLASS__, 'output_local_business_jsonld' ) );
		}

		return array( 'success' => true, 'message' => sprintf(
			__( 'Local Business JSON-LD saved (type: %s). It will be output on your site\'s homepage via wp_head.', 'scalyn-qa-assistant' ),
			$biz_type,
		) );
	}

	/**
	 * Output stored Local Business JSON-LD in wp_head.
	 *
	 * Hooked during plugin init if the option exists.
	 */
	public static function output_local_business_jsonld(): void {
		$jsonld = get_option( 'scalyn_qa_local_business_jsonld', array() );
		if ( ! empty( $jsonld ) && is_array( $jsonld ) && is_front_page() ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	/**
	 * Get a starter Privacy Policy template.
	 *
	 * @return string Block editor HTML content.
	 */
	private function get_privacy_policy_template(): string {
		$site_name = get_bloginfo( 'name' );

		return <<<HTML
<!-- wp:heading -->
<h2>Who we are</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Our website address is: {$site_name}.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>What personal data we collect and why</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>Contact forms</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>When you submit a contact form, we collect the data shown in the form, including your name and email address, to respond to your inquiry.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Cookies</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you visit our website, we may set temporary cookies to determine if your browser accepts cookies. These cookies contain no personal data and are discarded when you close your browser.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Analytics</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may use analytics services such as Google Analytics to understand how visitors interact with our website. This data is collected anonymously and helps us improve our content and user experience.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>How long we retain your data</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you submit a contact form, the message and its metadata are retained for customer service purposes. You can request that we erase any personal data we hold about you at any time.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Your rights over your data</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you have submitted data to this site, you can request to receive an exported file of the personal data we hold about you, or request that we erase any personal data we hold about you.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Where your data is sent</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Visitor data may be checked through an automated spam detection service. Analytics data may be processed by third-party services such as Google.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>This is a starter privacy policy. Please review and update it to accurately reflect your site's specific data collection and processing practices.</em></p>
<!-- /wp:paragraph -->
HTML;
	}

	/**
	 * Fix: generate or update the llms.txt file.
	 *
	 * @param string $content Custom content, or empty to generate default.
	 */
	private function fix_llms_txt( string $content = '' ): array {
		$file_path = ABSPATH . 'llms.txt';

		if ( '' === $content ) {
			$content = $this->generate_llms_txt_default();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $file_path, $content );

		if ( false === $written ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write llms.txt. Check file permissions on the WordPress root directory.', 'scalyn-qa-assistant' ),
			);
		}

		return array( 'success' => true, 'message' => __( 'llms.txt saved successfully.', 'scalyn-qa-assistant' ) );
	}

	/**
	 * Generate default llms.txt content from site settings.
	 *
	 * @return string
	 */
	public function generate_llms_txt_default(): string {
		$site_name  = get_bloginfo( 'name' );
		$site_url   = home_url( '/' );
		$admin_email = get_option( 'admin_email', '' );

		$lines = array();
		$lines[] = 'User-agent: *';
		$lines[] = 'Allow: /';
		$lines[] = '';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Disallow: /wp-login.php';
		$lines[] = '';
		$lines[] = '# Attribution';
		$lines[] = 'Attribution: Required';
		$lines[] = 'Attribution-URL: ' . $site_url;
		$lines[] = '';
		$lines[] = '# Contact';

		if ( '' !== $admin_email ) {
			$lines[] = 'Contact: ' . $admin_email;
		}

		$lines[] = '';
		$lines[] = '# Site';
		$lines[] = 'Site-Name: ' . $site_name;
		$lines[] = 'Site-URL: ' . $site_url;

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Read the current llms.txt content from disk.
	 *
	 * @return string|null File content or null if not found.
	 */
	public function read_llms_txt(): ?string {
		$file_path = ABSPATH . 'llms.txt';

		if ( ! file_exists( $file_path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );

		return false !== $content ? $content : null;
	}

	/**
	 * Get launch checklist settings (thresholds + enabled checks).
	 */
	private function get_launch_settings(): array {
		$settings = get_option( 'scalyn_qa_launch_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Get a PHP threshold value from settings.
	 */
	private function get_threshold( string $key, int $default ): int {
		$settings   = $this->get_launch_settings();
		$thresholds = $settings['thresholds'] ?? array();
		return (int) ( $thresholds[ $key ] ?? $default );
	}

	/**
	 * Check if a specific check is enabled in launch settings.
	 */
	private function is_check_enabled( string $check_id ): bool {
		$settings = $this->get_launch_settings();
		$enabled  = $settings['enabled_checks'] ?? null;

		// If no settings saved yet, all checks are enabled.
		if ( null === $enabled || ! is_array( $enabled ) ) {
			return true;
		}

		return in_array( $check_id, $enabled, true );
	}

	/**
	 * Checks that require a pro SEO plugin to be meaningful.
	 */
	private const PRO_CHECKS = array(
		'redirect_manager',
		'local_business_schema',
		'cornerstone_content',
		'instant_indexing',
		'woocommerce_seo',
		'breadcrumbs_enabled',
	);

	/**
	 * Determine whether any installed SEO plugin is a pro/premium version.
	 */
	private function has_any_pro_seo(): bool {
		if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
			return true;
		}
		if ( defined( 'WPSEO_PREMIUM_FILE' ) ) {
			return true;
		}
		if ( defined( 'AIOSEO_PRO_VERSION' ) ) {
			return true;
		}
		if ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			return true;
		}
		if ( defined( 'THE_SEO_FRAMEWORK_EXTENSION_MANAGER_VERSION' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if a pro-enhanced check is locked (no pro SEO plugin detected).
	 */
	private function is_pro_locked( string $check_id ): bool {
		return self::is_check_pro_locked( $check_id );
	}

	/**
	 * Static helper: check if a pro-enhanced check is locked.
	 *
	 * @param string $check_id The check ID to test.
	 * @return bool True if the check requires pro and no pro SEO plugin is active.
	 */
	public static function is_check_pro_locked( string $check_id ): bool {
		if ( ! in_array( $check_id, self::PRO_CHECKS, true ) ) {
			return false;
		}

		return ! ( defined( 'RANK_MATH_PRO_VERSION' )
			|| defined( 'WPSEO_PREMIUM_FILE' )
			|| defined( 'AIOSEO_PRO_VERSION' )
			|| defined( 'SEOPRESS_PRO_VERSION' )
			|| defined( 'THE_SEO_FRAMEWORK_EXTENSION_MANAGER_VERSION' )
		);
	}

	public function run_checks(): array {
		$all_checks = array(
			// SEO.
			'search_engine_visibility' => $this->check_search_engine_visibility(),
			'seo_plugin_installed'     => $this->check_seo_plugin_installed(),
			'sitemap_exists'           => $this->check_sitemap_exists(),
			'llms_txt'                 => $this->check_llms_txt(),
			'robots_txt'               => $this->check_robots_txt(),
			'permalink_structure'      => $this->check_permalink_structure(),
			// Analytics.
			'ga4_configured'           => $this->check_ga4_configured(),
			'gtm_configured'           => $this->check_gtm_configured(),
			// Technical.
			'ssl_enabled'              => $this->check_ssl_enabled(),
			'favicon_exists'           => $this->check_favicon_exists(),
			'debug_mode_disabled'      => $this->check_debug_mode_disabled(),
			'wp_core_updates'          => $this->check_wp_core_updates(),
			'plugin_updates'           => $this->check_plugin_updates(),
			'wp_address_match'         => $this->check_wp_address_match(),
			'php_version'              => $this->check_php_version(),
			'php_memory_limit'         => $this->check_php_memory_limit(),
			'php_max_execution_time'   => $this->check_php_max_execution_time(),
			'php_max_input_time'       => $this->check_php_max_input_time(),
			'php_post_max_size'        => $this->check_php_post_max_size(),
			'php_upload_max_size'      => $this->check_php_upload_max_size(),
			// Content.
			'contact_page_exists'      => $this->check_contact_page_exists(),
			'privacy_policy_exists'    => $this->check_privacy_policy_exists(),
			'default_content_cleanup'  => $this->check_default_content_cleanup(),
			'default_tagline'          => $this->check_default_tagline(),
			'empty_pages'              => $this->check_empty_pages(),
			'four_oh_four_page'        => $this->check_404_page(),
			'menu_exists'              => $this->check_menu_exists(),
			// Plugin health.
			'default_plugins_cleanup'  => $this->check_default_plugins(),
			'plugin_conflicts'         => $this->check_plugin_conflicts(),
			'security_plugin'          => $this->check_security_plugin(),
			'cache_plugin'             => $this->check_cache_plugin(),
			'backup_plugin'            => $this->check_backup_plugin(),
			'smtp_plugin'              => $this->check_smtp_plugin(),
			'image_optimization_plugin' => $this->check_image_optimization_plugin(),
			// Settings.
			'admin_username'           => $this->check_admin_username(),
			'timezone_set'             => $this->check_timezone_set(),
			'comments_open'            => $this->check_comments_open(),
			'breadcrumbs_enabled'      => $this->check_breadcrumbs_enabled(),
			// Pro-enhanced.
			'redirect_manager'         => $this->check_redirect_manager(),
			'local_business_schema'    => $this->check_local_business_schema(),
			'four_oh_four_monitor'     => $this->check_404_monitor(),
			'cornerstone_content'      => $this->check_cornerstone_content(),
			'instant_indexing'         => $this->check_instant_indexing(),
			'woocommerce_seo'          => $this->check_woocommerce_seo(),
		);

		// Filter to only enabled checks; skip pro-locked checks on free SEO plugins.
		$checks = array();
		foreach ( $all_checks as $check_id => $check_item ) {
			if ( $this->is_pro_locked( $check_id ) ) {
				continue;
			}
			if ( $this->is_check_enabled( $check_id ) ) {
				$checks[] = $check_item;
			}
		}

		// Serialize results for storage.
		$serialized = array_map(
			static fn( Check_Item $item ): array => $item->to_array(),
			$checks,
		);

		update_option( self::RESULTS_OPTION, $serialized, false );
		update_option( self::LAST_SCAN_OPTION, time(), false );

		return $checks;
	}

	/**
	 * Check if an SEO plugin is installed and active.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	/**
	 * Check if search engines are discouraged from indexing the site.
	 *
	 * WordPress Settings → Reading → "Discourage search engines from indexing this site"
	 *
	 * @since 1.0.6
	 *
	 * @return Check_Item
	 */
	private function check_search_engine_visibility(): Check_Item {
		$discouraged = '0' === get_option( 'blog_public', '1' );

		if ( $discouraged ) {
			return new Check_Item(
				id:        'search_engine_visibility',
				label:     __( 'Search Engine Visibility', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'Search engines are discouraged from indexing this site. Go to Settings → Reading to fix this before launch.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'WordPress has a setting that tells search engines not to index your site. This must be disabled before launching or your site will not appear in Google.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'search_engine_visibility',
			label:     __( 'Search Engine Visibility', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'Search engines are allowed to index this site.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'critical',
			quick_fix: null,
			tooltip:   __( 'WordPress is configured to allow search engine indexing. This is correct for a live site.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if llms.txt exists at the site root.
	 *
	 * llms.txt is a proposed standard for providing LLM-friendly content,
	 * similar to robots.txt for search engines.
	 *
	 * @since 1.0.6
	 *
	 * @return Check_Item
	 */
	private function check_llms_txt(): Check_Item {
		$url      = home_url( '/llms.txt' );
		$response = wp_remote_head( $url, array( 'timeout' => 5, 'sslverify' => true ) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			return new Check_Item(
				id:        'llms_txt',
				label:     __( 'llms.txt', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'llms.txt file found. LLMs can discover your site content.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'llms.txt is a standard that helps AI language models understand your site content, similar to how robots.txt helps search engines.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'llms_txt',
			label:     __( 'llms.txt', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No llms.txt found. Consider adding one to help AI models discover your content.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'llms.txt is a proposed standard (llmstxt.org) that provides a markdown file at your site root to help LLMs understand your site. It is optional but recommended for AI discoverability.', 'scalyn-qa-assistant' ),
		);
	}

	private function check_seo_plugin_installed(): Check_Item {
		$integration = SEO_Integration::detect();

		if ( null !== $integration ) {
			return new Check_Item(
				id:        'seo_plugin_installed',
				label:     'SEO Plugin Installed',
				status:    'pass',
				message:   sprintf(
					/* translators: %s: SEO plugin name. */
					__( '%s is active and configured.', 'scalyn-qa-assistant' ),
					$integration->get_plugin_name(),
				),
				category:  'seo',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'An SEO plugin is essential for managing meta tags, sitemaps, and structured data.', 'scalyn-qa-assistant' ),
				details:   array(
					'plugin_name' => $integration->get_plugin_name(),
					'plugin_slug' => $integration->get_plugin_slug(),
				),
			);
		}

		return new Check_Item(
			id:        'seo_plugin_installed',
			label:     'SEO Plugin Installed',
			status:    'fail',
			message:   __( 'No SEO plugin detected. Install Rank Math, Yoast SEO, or All in One SEO.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'critical',
			quick_fix: 'install_seo_plugin',
			tooltip:   __( 'An SEO plugin is essential for managing meta tags, sitemaps, and structured data.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if an XML sitemap is accessible.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_sitemap_exists(): Check_Item {
		$site_url      = home_url();
		$sitemap_paths = array(
			'/sitemap_index.xml',
			'/sitemap.xml',
			'/wp-sitemap.xml',
		);

		foreach ( $sitemap_paths as $path ) {
			$url      = $site_url . $path;
			$response = wp_remote_head(
				$url,
				array(
					'timeout'   => 10,
					'sslverify' => false,
				),
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				return new Check_Item(
					id:        'sitemap_exists',
					label:     'XML Sitemap',
					status:    'pass',
					message:   sprintf(
						/* translators: %s: Sitemap URL. */
						__( 'Sitemap found at %s.', 'scalyn-qa-assistant' ),
						esc_url( $url ),
					),
					category:  'seo',
					severity:  'warning',
					tooltip:   __( 'XML sitemaps help search engines discover and index your pages efficiently.', 'scalyn-qa-assistant' ),
					details:   array( 'sitemap_url' => $url ),
				);
			}
		}

		return new Check_Item(
			id:        'sitemap_exists',
			label:     'XML Sitemap',
			status:    'warning',
			message:   __( 'No XML sitemap found. Check your SEO plugin settings or add a sitemap.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'warning',
			tooltip:   __( 'XML sitemaps help search engines discover and index your pages efficiently.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if Google Analytics 4 is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_ga4_configured(): Check_Item {
		// Check common GA4 option keys in the database.
		$ga4_option_keys = array(
			'woocommerce_ga_id',
			'monsterinsights_site_profile',
			'exactmetrics_site_profile',
			'gadwp_options',
			'google_analytics_id',
			'ga_google_analytics_id',
		);

		foreach ( $ga4_option_keys as $option_key ) {
			$value = get_option( $option_key );
			if ( ! empty( $value ) ) {
				return new Check_Item(
					id:        'ga4_configured',
					label:     'Google Analytics',
					status:    'pass',
					message:   __( 'Google Analytics configuration detected.', 'scalyn-qa-assistant' ),
					category:  'functionality',
					severity:  'warning',
					tooltip:   __( 'Google Analytics tracks visitor behavior and helps measure website performance.', 'scalyn-qa-assistant' ),
					details:   array( 'detected_via' => 'option_' . $option_key ),
				);
			}
		}

		// Fallback: fetch homepage and search for GA4/GTM markers.
		$html = $this->fetch_homepage_html();

		if ( '' !== $html ) {
			$ga4_patterns = array( 'gtag(', 'G-', 'GTM-', 'googletagmanager' );

			foreach ( $ga4_patterns as $pattern ) {
				if ( false !== stripos( $html, $pattern ) ) {
					return new Check_Item(
						id:        'ga4_configured',
						label:     'Google Analytics',
						status:    'pass',
						message:   __( 'Google Analytics or Tag Manager code detected on the homepage.', 'scalyn-qa-assistant' ),
						category:  'functionality',
						severity:  'warning',
						tooltip:   __( 'Google Analytics tracks visitor behavior and helps measure website performance.', 'scalyn-qa-assistant' ),
						details:   array( 'detected_via' => 'html_pattern_' . $pattern ),
					);
				}
			}
		}

		return new Check_Item(
			id:        'ga4_configured',
			label:     'Google Analytics',
			status:    'warning',
			message:   __( 'Google Analytics not detected. Consider adding GA4 for visitor tracking.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			tooltip:   __( 'Google Analytics tracks visitor behavior and helps measure website performance.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if Google Tag Manager is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_gtm_configured(): Check_Item {
		// Check common GTM option keys.
		$gtm_option_keys = array(
			'gtm4wp-options',
			'google_tag_manager_id',
			'gtm_id',
		);

		foreach ( $gtm_option_keys as $option_key ) {
			$value = get_option( $option_key );
			if ( ! empty( $value ) ) {
				return new Check_Item(
					id:        'gtm_configured',
					label:     'Google Tag Manager',
					status:    'pass',
					message:   __( 'Google Tag Manager configuration detected.', 'scalyn-qa-assistant' ),
					category:  'functionality',
					severity:  'info',
					tooltip:   __( 'Google Tag Manager centralizes tracking code management and simplifies analytics setup.', 'scalyn-qa-assistant' ),
					details:   array( 'detected_via' => 'option_' . $option_key ),
				);
			}
		}

		// Fallback: check homepage HTML for GTM container code.
		$html = $this->fetch_homepage_html();

		if ( '' !== $html && false !== stripos( $html, 'GTM-' ) ) {
			return new Check_Item(
				id:        'gtm_configured',
				label:     'Google Tag Manager',
				status:    'pass',
				message:   __( 'Google Tag Manager container code detected on the homepage.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				tooltip:   __( 'Google Tag Manager centralizes tracking code management and simplifies analytics setup.', 'scalyn-qa-assistant' ),
				details:   array( 'detected_via' => 'html_pattern_GTM-' ),
			);
		}

		return new Check_Item(
			id:        'gtm_configured',
			label:     'Google Tag Manager',
			status:    'warning',
			message:   __( 'Google Tag Manager not detected. GTM is optional but recommended for tag management.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'info',
			tooltip:   __( 'Google Tag Manager centralizes tracking code management and simplifies analytics setup.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if SSL is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_ssl_enabled(): Check_Item {
		$is_ssl       = is_ssl();
		$site_url     = get_option( 'siteurl', '' );
		$url_is_https = is_string( $site_url ) && str_starts_with( $site_url, 'https://' );

		if ( $is_ssl && $url_is_https ) {
			return new Check_Item(
				id:        'ssl_enabled',
				label:     'SSL / HTTPS',
				status:    'pass',
				message:   __( 'SSL is enabled and the site URL uses HTTPS.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'critical',
				tooltip:   __( 'SSL encryption protects user data and is a Google ranking factor.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'ssl_enabled',
			label:     'SSL / HTTPS',
			status:    'fail',
			message:   __( 'SSL is not fully configured. Ensure your site uses HTTPS.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'critical',
			tooltip:   __( 'SSL encryption protects user data and is a Google ranking factor.', 'scalyn-qa-assistant' ),
			details:   array(
				'is_ssl'       => $is_ssl,
				'url_is_https' => $url_is_https,
			),
		);
	}

	/**
	 * Check if a favicon / site icon is set.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_favicon_exists(): Check_Item {
		$icon_url    = get_site_icon_url();
		$icon_id     = (int) get_option( 'site_icon', 0 );
		$ai_history  = get_option( 'scalyn_qa_ai_favicons', array() );
		$ai_history  = is_array( $ai_history ) ? $ai_history : array();

		// Build AI favicon list with URLs.
		$ai_favicons = array();
		foreach ( $ai_history as $att_id ) {
			$att_id = (int) $att_id;
			$url    = wp_get_attachment_url( $att_id );
			if ( $url ) {
				$ai_favicons[] = array(
					'attachment_id' => $att_id,
					'url'           => $url,
					'filename'      => basename( get_attached_file( $att_id ) ?: '' ),
					'is_active'     => $att_id === $icon_id,
				);
			}
		}

		$details = array(
			'icon_url'    => $icon_url ?: '',
			'icon_id'     => $icon_id,
			'ai_favicons' => $ai_favicons,
		);

		if ( ! empty( $icon_url ) ) {
			return new Check_Item(
				id:        'favicon_exists',
				label:     'Favicon',
				status:    'pass',
				message:   __( 'Site icon (favicon) is configured.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'warning',
				quick_fix: 'generate_ai_favicon',
				tooltip:   __( 'A favicon improves brand recognition in browser tabs and bookmarks.', 'scalyn-qa-assistant' ),
				details:   $details,
			);
		}

		return new Check_Item(
			id:        'favicon_exists',
			label:     'Favicon',
			status:    'warning',
			message:   __( 'No site icon (favicon) found. Set one in Appearance > Customize > Site Identity, or generate one with AI.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			quick_fix: 'generate_ai_favicon',
			tooltip:   __( 'A favicon improves brand recognition in browser tabs and bookmarks.', 'scalyn-qa-assistant' ),
			details:   $details,
		);
	}

	/**
	 * Check if a contact page exists.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_contact_page_exists(): Check_Item {
		global $wpdb;

		// Search for a published page with 'contact' in the slug or title.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$contact_page = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND (post_name LIKE %s OR post_title LIKE %s)
				LIMIT 1",
				'page',
				'publish',
				'%contact%',
				'%contact%',
			),
		);

		if ( null !== $contact_page ) {
			return new Check_Item(
				id:        'contact_page_exists',
				label:     'Contact Page',
				status:    'pass',
				message:   sprintf(
					/* translators: %s: Page title. */
					__( 'Contact page found: "%s".', 'scalyn-qa-assistant' ),
					$contact_page->post_title,
				),
				category:  'content',
				severity:  'warning',
				tooltip:   __( 'A contact page builds trust and is expected by visitors and search engines.', 'scalyn-qa-assistant' ),
				details:   array(
					'page_id'    => (int) $contact_page->ID,
					'page_title' => $contact_page->post_title,
					'page_slug'  => $contact_page->post_name,
				),
			);
		}

		return new Check_Item(
			id:        'contact_page_exists',
			label:     'Contact Page',
			status:    'warning',
			message:   __( 'No contact page found. Consider creating one to improve visitor trust.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			tooltip:   __( 'A contact page builds trust and is expected by visitors and search engines.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a privacy policy page exists.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_privacy_policy_exists(): Check_Item {
		$tooltip = __( 'A privacy policy is legally required in many jurisdictions and builds user trust. Set one under Settings → Privacy.', 'scalyn-qa-assistant' );

		// Check the WordPress built-in privacy policy setting.
		$policy_page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		if ( $policy_page_id > 0 ) {
			$page = get_post( $policy_page_id );

			if ( $page && 'publish' === $page->post_status ) {
				return new Check_Item(
					id:        'privacy_policy_exists',
					label:     __( 'Privacy Policy', 'scalyn-qa-assistant' ),
					status:    'pass',
					message:   sprintf(
						/* translators: %s: Page title. */
						__( 'Privacy policy page configured: "%s".', 'scalyn-qa-assistant' ),
						$page->post_title,
					),
					category:  'content',
					severity:  'warning',
					tooltip:   $tooltip,
					details:   array( 'privacy_url' => get_permalink( $policy_page_id ) ),
				);
			}

			// Page ID is set but page is missing or not published.
			return new Check_Item(
				id:        'privacy_policy_exists',
				label:     __( 'Privacy Policy', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'A privacy policy page is assigned in Settings → Privacy but it is not published. Publish it or create a new one.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'warning',
				tooltip:   $tooltip,
			);
		}

		// Fallback: search for a published page with 'privacy' in the slug.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$privacy_page = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND post_name LIKE %s
				LIMIT 1",
				'page',
				'publish',
				'%privacy%',
			),
		);

		if ( null !== $privacy_page ) {
			return new Check_Item(
				id:        'privacy_policy_exists',
				label:     __( 'Privacy Policy', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %s: Page title. */
					__( 'Found a page "%s" but it is not set as the privacy policy in Settings → Privacy. Assign it or use Auto Fix.', 'scalyn-qa-assistant' ),
					$privacy_page->post_title,
				),
				category:  'content',
				severity:  'warning',
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'privacy_policy_exists',
			label:     __( 'Privacy Policy', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No privacy policy page found. Create one under Settings → Privacy or use Auto Fix.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check for conflicting plugin combinations.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	/**
	 * Check if default WordPress plugins (Akismet, Hello Dolly) are still installed.
	 *
	 * @since 1.4.4
	 */
	private function check_default_plugins(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$default_plugins = array(
			'akismet/akismet.php'          => 'Akismet Anti-spam',
			'hello.php'                    => 'Hello Dolly',
			'hello-dolly/hello.php'        => 'Hello Dolly',
		);

		$found = array();
		foreach ( $default_plugins as $file => $name ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
				$found[ $file ] = $name;
			}
		}

		if ( empty( $found ) ) {
			return new Check_Item(
				id:        'default_plugins_cleanup',
				label:     __( 'Default Plugins Cleanup', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Default WordPress plugins (Akismet, Hello Dolly) have been removed.', 'scalyn-qa-assistant' ),
				category:  'plugin_health',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Fresh WordPress installs include Akismet and Hello Dolly. These are unnecessary for most sites and should be removed to keep the plugin list clean.', 'scalyn-qa-assistant' ),
			);
		}

		$names = array_unique( array_values( $found ) );

		return new Check_Item(
			id:        'default_plugins_cleanup',
			label:     __( 'Default Plugins Cleanup', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				__( 'Default WordPress plugins still installed: %s. Click Auto Fix to remove them.', 'scalyn-qa-assistant' ),
				implode( ', ', $names ),
			),
			category:  'plugin_health',
			severity:  'info',
			quick_fix: 'auto_fix',
			tooltip:   __( 'Fresh WordPress installs include Akismet and Hello Dolly. These are unnecessary for most sites and should be removed to keep the plugin list clean.', 'scalyn-qa-assistant' ),
			details:   array( 'plugins' => $found ),
		);
	}

	/**
	 * Fix: deactivate and delete default WordPress plugins.
	 *
	 * @since 1.4.4
	 */
	private function fix_default_plugins(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$default_plugins = array(
			'akismet/akismet.php'   => 'Akismet',
			'hello.php'            => 'Hello Dolly',
			'hello-dolly/hello.php' => 'Hello Dolly',
		);

		$removed = array();

		foreach ( $default_plugins as $file => $name ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
				continue;
			}

			// Deactivate first if active.
			if ( is_plugin_active( $file ) ) {
				deactivate_plugins( $file, true );
			}

			// Delete the plugin.
			$result = delete_plugins( array( $file ) );

			if ( ! is_wp_error( $result ) ) {
				$removed[] = $name;
			}
		}

		if ( empty( $removed ) ) {
			return array( 'success' => false, 'message' => __( 'No default plugins found to remove.', 'scalyn-qa-assistant' ) );
		}

		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Removed: %s.', 'scalyn-qa-assistant' ),
				implode( ', ', array_unique( $removed ) ),
			),
		);
	}

	private function check_plugin_conflicts(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$conflicts = array();

		// Check for multiple SEO plugins active simultaneously.
		$seo_plugins = array(
			'Rank Math'      => 'seo-by-rank-math/rank-math.php',
			'Yoast SEO'      => 'wordpress-seo/wp-seo.php',
			'All in One SEO' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
		);

		$active_seo = array();
		foreach ( $seo_plugins as $name => $slug ) {
			if ( is_plugin_active( $slug ) ) {
				$active_seo[] = $name;
			}
		}

		if ( count( $active_seo ) > 1 ) {
			$conflicts[] = sprintf(
				/* translators: %s: Comma-separated list of active SEO plugin names. */
				__( 'Multiple SEO plugins active: %s. This can cause duplicate meta tags and conflicts.', 'scalyn-qa-assistant' ),
				implode( ', ', $active_seo ),
			);
		}

		// Check for multiple sitemap generators.
		$sitemap_plugins = array(
			'Google XML Sitemaps'  => 'google-sitemap-generator/sitemap.php',
			'XML Sitemap & Google News' => 'xml-sitemap-feed/xml-sitemap.php',
			'Jetpack Sitemaps'     => 'jetpack/jetpack.php',
		);

		$active_sitemap = array();
		foreach ( $sitemap_plugins as $name => $slug ) {
			if ( is_plugin_active( $slug ) ) {
				$active_sitemap[] = $name;
			}
		}

		// SEO plugins also generate sitemaps, so include active ones.
		$seo_with_sitemaps = array_merge( $active_seo, $active_sitemap );

		if ( count( $seo_with_sitemaps ) > 1 ) {
			$conflicts[] = sprintf(
				/* translators: %s: Comma-separated list of plugin names generating sitemaps. */
				__( 'Multiple plugins may generate sitemaps: %s. This can confuse search engines.', 'scalyn-qa-assistant' ),
				implode( ', ', $seo_with_sitemaps ),
			);
		}

		if ( ! empty( $conflicts ) ) {
			return new Check_Item(
				id:        'plugin_conflicts',
				label:     'Plugin Conflicts',
				status:    'warning',
				message:   implode( ' ', $conflicts ),
				category:  'functionality',
				severity:  'warning',
				tooltip:   __( 'Conflicting plugins can cause unexpected behavior, duplicate content, and SEO issues.', 'scalyn-qa-assistant' ),
				details:   array(
					'conflicts'          => $conflicts,
					'active_seo_plugins' => $active_seo,
				),
			);
		}

		return new Check_Item(
			id:        'plugin_conflicts',
			label:     'Plugin Conflicts',
			status:    'pass',
			message:   __( 'No known plugin conflicts detected.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			tooltip:   __( 'Conflicting plugins can cause unexpected behavior, duplicate content, and SEO issues.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Fetch the homepage HTML.
	 *
	 * Caches the result within the request to avoid duplicate HTTP calls.
	 *
	 * @since 1.0.0
	 *
	 * @return string The homepage HTML body, or empty string on failure.
	 */
	/**
	 * Public wrapper for homepage HTML — used by AI prompt builder.
	 *
	 * @since 1.3.0
	 */
	public function read_homepage_html_for_ai(): string {
		return $this->fetch_homepage_html();
	}

	private function fetch_homepage_html(): string {
		static $cached_html = null;

		if ( null !== $cached_html ) {
			return $cached_html;
		}

		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			),
		);

		if ( is_wp_error( $response ) ) {
			$cached_html = '';
			return $cached_html;
		}

		$body = wp_remote_retrieve_body( $response );

		$cached_html = is_string( $body ) ? $body : '';

		return $cached_html;
	}

	/**
	 * Parse a PHP size string (e.g. '128M') to megabytes.
	 */
	private function parse_size_mb( string $size ): int {
		$size  = trim( $size );
		$value = (int) $size;
		$unit  = strtolower( substr( $size, -1 ) );

		return match ( $unit ) {
			'g' => $value * 1024,
			'm' => $value,
			'k' => (int) round( $value / 1024 ),
			default => $value,
		};
	}

	/**
	 * Check PHP memory limit (minimum 512MB).
	 */
	/**
	 * Check PHP version meets minimum requirement.
	 */
	private function check_php_version(): Check_Item {
		$current   = PHP_VERSION;
		$settings  = $this->get_launch_settings();
		$threshold = $settings['thresholds']['php_version'] ?? '8.3.14';
		$pass      = version_compare( $current, $threshold, '>=' );

		return new Check_Item(
			id:        'php_version',
			label:     __( 'PHP Version', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'PHP version is %s.', 'scalyn-qa-assistant' ), esc_html( $current ) )
				: sprintf( __( 'PHP version is %s. Recommended: %s or higher.', 'scalyn-qa-assistant' ), esc_html( $current ), esc_html( $threshold ) ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Running an up-to-date PHP version ensures better performance, security, and compatibility with modern plugins.', 'scalyn-qa-assistant' ),
		);
	}

	private function check_php_memory_limit(): Check_Item {
		$raw       = ini_get( 'memory_limit' );
		$mb        = $this->parse_size_mb( $raw ?: '0' );
		$threshold = $this->get_threshold( 'memory_limit', 512 );
		$pass      = $mb >= $threshold || -1 === (int) $raw;

		return new Check_Item(
			id:        'php_memory_limit',
			label:     __( 'PHP Memory Limit', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Memory limit is %s.', 'scalyn-qa-assistant' ), esc_html( $raw ) )
				: sprintf( __( 'Memory limit is %s. Recommended: %dM or higher.', 'scalyn-qa-assistant' ), esc_html( $raw ), $threshold ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'WordPress recommends at least 256MB, but 512MB or more is ideal for plugins and page builders.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP max execution time (minimum 90s).
	 */
	private function check_php_max_execution_time(): Check_Item {
		$value     = (int) ini_get( 'max_execution_time' );
		$threshold = $this->get_threshold( 'max_execution_time', 90 );
		$pass      = 0 === $value || $value >= $threshold;

		return new Check_Item(
			id:        'php_max_execution_time',
			label:     __( 'PHP Max Execution Time', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Max execution time is %ds.', 'scalyn-qa-assistant' ), $value )
				: sprintf( __( 'Max execution time is %ds. Recommended: %ds or higher.', 'scalyn-qa-assistant' ), $value, $threshold ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Scripts that run longer than this limit will be terminated. 90 seconds is recommended for complex operations.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP max input time (minimum 90s).
	 */
	private function check_php_max_input_time(): Check_Item {
		$value     = (int) ini_get( 'max_input_time' );
		$threshold = $this->get_threshold( 'max_input_time', 90 );
		$pass      = -1 === $value || $value >= $threshold;

		return new Check_Item(
			id:        'php_max_input_time',
			label:     __( 'PHP Max Input Time', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Max input time is %ds.', 'scalyn-qa-assistant' ), $value )
				: sprintf( __( 'Max input time is %ds. Recommended: %ds or higher.', 'scalyn-qa-assistant' ), $value, $threshold ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Maximum time PHP will spend parsing input data such as POST and file uploads.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP post_max_size (minimum 128MB).
	 */
	private function check_php_post_max_size(): Check_Item {
		$raw       = ini_get( 'post_max_size' );
		$mb        = $this->parse_size_mb( $raw ?: '0' );
		$threshold = $this->get_threshold( 'post_max_size', 128 );
		$pass      = $mb >= $threshold;

		return new Check_Item(
			id:        'php_post_max_size',
			label:     __( 'PHP Post Max Size', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Post max size is %s.', 'scalyn-qa-assistant' ), esc_html( $raw ) )
				: sprintf( __( 'Post max size is %s. Recommended: %dM or higher.', 'scalyn-qa-assistant' ), esc_html( $raw ), $threshold ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Maximum size of POST data that PHP will accept. Affects large form submissions and imports.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP upload_max_filesize (minimum 64MB).
	 */
	private function check_php_upload_max_size(): Check_Item {
		$raw       = ini_get( 'upload_max_filesize' );
		$mb        = $this->parse_size_mb( $raw ?: '0' );
		$threshold = $this->get_threshold( 'upload_max_size', 64 );
		$pass      = $mb >= $threshold;

		return new Check_Item(
			id:        'php_upload_max_size',
			label:     __( 'PHP Upload Max Size', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Upload max size is %s.', 'scalyn-qa-assistant' ), esc_html( $raw ) )
				: sprintf( __( 'Upload max size is %s. Recommended: %dM or higher.', 'scalyn-qa-assistant' ), esc_html( $raw ), $threshold ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Maximum file size for uploads. Should be at least 64MB for media-heavy sites.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a security plugin is installed and active.
	 */
	private function check_security_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$security_plugins = $this->get_recommended_plugins( 'security_plugin' );

		$found = array();
		foreach ( $security_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'security_plugin',
				label:     __( 'Security Plugin', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Security plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'A security plugin helps protect your site from brute force attacks, malware, and vulnerabilities.', 'scalyn-qa-assistant' ),
			);
		}

		$inactive = $this->find_inactive_plugin( $security_plugins );

		return new Check_Item(
			id:        'security_plugin',
			label:     __( 'Security Plugin', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   null !== $inactive
				? sprintf( __( '%s is deactivated. Click Auto Fix to activate %s.', 'scalyn-qa-assistant' ), $inactive['name'], $inactive['name'] )
				: __( 'No security plugin detected. Consider installing Wordfence, Sucuri, or Solid Security.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null !== $inactive ? 'auto_fix' : null,
			tooltip:   __( 'A security plugin adds firewall protection, malware scanning, and login security to your WordPress site.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a caching plugin is installed and active.
	 */
	private function check_cache_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$cache_plugins = $this->get_recommended_plugins( 'cache_plugin' );

		$found = array();
		foreach ( $cache_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'cache_plugin',
				label:     __( 'Cache Plugin', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Cache plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'A caching plugin improves page load speed by serving static versions of your pages.', 'scalyn-qa-assistant' ),
			);
		}

		$inactive = $this->find_inactive_plugin( $cache_plugins );

		return new Check_Item(
			id:        'cache_plugin',
			label:     __( 'Cache Plugin', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   null !== $inactive
				? sprintf( __( '%s is deactivated. Click Auto Fix to activate %s.', 'scalyn-qa-assistant' ), $inactive['name'], $inactive['name'] )
				: __( 'No caching plugin detected. Consider installing WP Rocket, LiteSpeed Cache, or WP Super Cache.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null !== $inactive ? 'auto_fix' : null,
			tooltip:   __( 'Caching significantly improves page load times and reduces server load. Essential for production sites.', 'scalyn-qa-assistant' ),
		);
	}

	// ------------------------------------------------------------------
	// Essential checks
	// ------------------------------------------------------------------

	/**
	 * Check if WP_DEBUG is disabled.
	 *
	 * @since 1.3.0
	 */
	private function check_debug_mode_disabled(): Check_Item {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return new Check_Item(
				id:        'debug_mode_disabled',
				label:     __( 'Debug Mode', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'WP_DEBUG is enabled. This exposes error details to visitors. Disable it in wp-config.php before launch.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'WP_DEBUG should be set to false in wp-config.php on production sites. When enabled, PHP errors and warnings may be visible to visitors.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'debug_mode_disabled',
			label:     __( 'Debug Mode', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'WP_DEBUG is disabled. Good for production.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'critical',
			quick_fix: null,
			tooltip:   __( 'Debug mode is correctly disabled for a production environment.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check for default "Hello World" post, sample page, or sample comment.
	 *
	 * @since 1.3.0
	 */
	private function check_default_content_cleanup(): Check_Item {
		global $wpdb;

		$leftovers = array();

		// Check for "Hello World" post (ID 1, slug "hello-world").
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hello = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status != %s LIMIT 1",
				'hello-world',
				'trash',
			),
		);
		if ( $hello ) {
			$leftovers[] = __( '"Hello World" post', 'scalyn-qa-assistant' );
		}

		// Check for "Sample Page" (ID 2, slug "sample-page").
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sample = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status != %s LIMIT 1",
				'sample-page',
				'trash',
			),
		);
		if ( $sample ) {
			$leftovers[] = __( '"Sample Page"', 'scalyn-qa-assistant' );
		}

		// Check for default comment on post ID 1.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$comment = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_author = %s LIMIT 1",
				'A WordPress Commenter',
			),
		);
		if ( $comment ) {
			$leftovers[] = __( 'default sample comment', 'scalyn-qa-assistant' );
		}

		if ( ! empty( $leftovers ) ) {
			return new Check_Item(
				id:        'default_content_cleanup',
				label:     __( 'Default Content', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %s: comma-separated list of default content items */
					__( 'Default WordPress content still exists: %s. Delete these before launch.', 'scalyn-qa-assistant' ),
					implode( ', ', $leftovers ),
				),
				category:  'content',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'WordPress ships with sample content that should be removed before a site goes live.', 'scalyn-qa-assistant' ),
				details:   array( 'leftovers' => $leftovers ),
			);
		}

		return new Check_Item(
			id:        'default_content_cleanup',
			label:     __( 'Default Content', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'No default WordPress sample content found.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Default sample posts, pages, and comments have been cleaned up.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if permalink structure is set (not default ?p=123).
	 *
	 * @since 1.3.0
	 */
	private function check_permalink_structure(): Check_Item {
		$structure = get_option( 'permalink_structure', '' );

		if ( '' === $structure ) {
			return new Check_Item(
				id:        'permalink_structure',
				label:     __( 'Permalink Structure', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'Using default "Plain" permalinks (?p=123). Go to Settings → Permalinks and select "Post name" or a custom structure.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'SEO-friendly URLs are essential for search rankings. "Post name" (/%postname%/) is the most recommended structure.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'permalink_structure',
			label:     __( 'Permalink Structure', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: %s: permalink structure */
				__( 'Permalink structure: %s', 'scalyn-qa-assistant' ),
				esc_html( $structure ),
			),
			category:  'seo',
			severity:  'critical',
			quick_fix: null,
			tooltip:   __( 'SEO-friendly URLs are configured.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if WordPress core is up to date.
	 *
	 * @since 1.3.0
	 */
	private function check_wp_core_updates(): Check_Item {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates();

		if ( is_array( $updates ) && ! empty( $updates ) && isset( $updates[0]->response ) && 'upgrade' === $updates[0]->response ) {
			return new Check_Item(
				id:        'wp_core_updates',
				label:     __( 'WordPress Updates', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: 1: current version, 2: latest version */
					__( 'WordPress %1$s is installed but %2$s is available. Update before launch.', 'scalyn-qa-assistant' ),
					get_bloginfo( 'version' ),
					$updates[0]->current ?? '',
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'Running the latest WordPress version ensures you have the newest security patches and features.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'wp_core_updates',
			label:     __( 'WordPress Updates', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: %s: WordPress version */
				__( 'WordPress %s is up to date.', 'scalyn-qa-assistant' ),
				get_bloginfo( 'version' ),
			),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'WordPress core is running the latest version.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if any active plugins have pending updates.
	 *
	 * @since 1.3.0
	 */
	private function check_plugin_updates(): Check_Item {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$updates = get_plugin_updates();

		if ( ! empty( $updates ) ) {
			$names = array();
			foreach ( $updates as $file => $data ) {
				$names[] = $data->Name ?? $file;
			}

			return new Check_Item(
				id:        'plugin_updates',
				label:     __( 'Plugin Updates', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %d: number of plugins with updates */
					_n(
						'%d plugin has an update available.',
						'%d plugins have updates available.',
						count( $names ),
						'scalyn-qa-assistant',
					),
					count( $names ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'Keep plugins up to date for security, performance, and compatibility.', 'scalyn-qa-assistant' ),
				details:   array( 'outdated_plugins' => $names ),
			);
		}

		return new Check_Item(
			id:        'plugin_updates',
			label:     __( 'Plugin Updates', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'All plugins are up to date.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'All active plugins are running their latest versions.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if the default site tagline is still set.
	 *
	 * @since 1.3.0
	 */
	private function check_default_tagline(): Check_Item {
		$tagline    = get_option( 'blogdescription', '' );
		$normalized = strtolower( trim( (string) $tagline, " \t\n\r\0\x0B." ) );

		$defaults = array(
			'just another wordpress site',
			'my wordpress blog',
			'another wordpress site',
			'my blog',
			'my site',
		);

		$is_default = in_array( $normalized, $defaults, true );

		if ( $is_default ) {
			return new Check_Item(
				id:        'default_tagline',
				label:     __( 'Site Tagline', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %s: the current tagline */
					__( 'Site tagline is "%s" — this looks like a default. Update it in Settings → General or use Auto Fix to clear it.', 'scalyn-qa-assistant' ),
					esc_html( $tagline ),
				),
				category:  'content',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'The default WordPress tagline appears in search results and browser tabs. Set a meaningful tagline that describes your site.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'default_tagline',
			label:     __( 'Site Tagline', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   '' === $tagline
				? __( 'Site tagline is empty (acceptable).', 'scalyn-qa-assistant' )
				: sprintf(
					/* translators: %s: the tagline */
					__( 'Site tagline: "%s"', 'scalyn-qa-assistant' ),
					esc_html( $tagline ),
				),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'The site tagline is customized or intentionally left empty.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if robots.txt is accessible and not blocking all crawlers.
	 *
	 * @since 1.3.0
	 */
	private function check_robots_txt(): Check_Item {
		$url      = home_url( '/robots.txt' );
		$response = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => false ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new Check_Item(
				id:        'robots_txt',
				label:     __( 'robots.txt', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'robots.txt not accessible. WordPress usually generates this automatically — check your server configuration.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'robots.txt tells search engines which pages they can crawl. WordPress generates a virtual one by default.', 'scalyn-qa-assistant' ),
			);
		}

		$body = wp_remote_retrieve_body( $response );

		// Check if it disallows everything.
		if ( is_string( $body ) && preg_match( '/Disallow:\s*\/\s*$/m', $body ) ) {
			return new Check_Item(
				id:        'robots_txt',
				label:     __( 'robots.txt', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'robots.txt is blocking all crawlers with "Disallow: /". Remove this rule before launch or search engines cannot index your site.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'A "Disallow: /" directive blocks all search engine crawlers from your entire site.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'robots_txt',
			label:     __( 'robots.txt', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'robots.txt is accessible and not blocking all crawlers.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'robots.txt is properly configured for search engine access.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a user with the "admin" login exists.
	 *
	 * @since 1.3.0
	 */
	private function check_admin_username(): Check_Item {
		$admin_user = get_user_by( 'login', 'admin' );

		if ( $admin_user ) {
			return new Check_Item(
				id:        'admin_username',
				label:     __( 'Admin Username', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'A user with the login "admin" exists. This is the #1 brute-force target. Create a new admin account and delete this one.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'The "admin" username is the most common target for brute-force attacks. Using a unique username greatly improves security.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'admin_username',
			label:     __( 'Admin Username', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'No user with the "admin" login found. Good security practice.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Avoiding the default "admin" username reduces brute-force attack risk.', 'scalyn-qa-assistant' ),
		);
	}

	// ------------------------------------------------------------------
	// Important checks
	// ------------------------------------------------------------------

	/**
	 * Check if an SMTP / mail plugin is installed.
	 *
	 * @since 1.3.0
	 */
	private function check_smtp_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$smtp_plugins = $this->get_recommended_plugins( 'smtp_plugin' );

		$found = array();
		foreach ( $smtp_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'smtp_plugin',
				label:     __( 'SMTP / Mail Plugin', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Mail plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'An SMTP plugin ensures reliable email delivery. Without one, WordPress emails often land in spam.', 'scalyn-qa-assistant' ),
			);
		}

		$inactive = $this->find_inactive_plugin( $smtp_plugins );

		return new Check_Item(
			id:        'smtp_plugin',
			label:     __( 'SMTP / Mail Plugin', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   null !== $inactive
				? sprintf( __( '%s is deactivated. Click Auto Fix to activate %s.', 'scalyn-qa-assistant' ), $inactive['name'], $inactive['name'] )
				: __( 'No SMTP plugin detected. WordPress default mail often lands in spam. Consider WP Mail SMTP or FluentSMTP.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null !== $inactive ? 'auto_fix' : null,
			tooltip:   __( 'WordPress uses PHP mail() by default which is unreliable. An SMTP plugin routes emails through a proper mail server.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a backup plugin is installed.
	 *
	 * @since 1.3.0
	 */
	private function check_backup_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$backup_plugins = $this->get_recommended_plugins( 'backup_plugin' );

		$found = array();
		foreach ( $backup_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'backup_plugin',
				label:     __( 'Backup Plugin', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Backup plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'Regular backups protect against data loss from hacks, failed updates, or server issues.', 'scalyn-qa-assistant' ),
			);
		}

		$inactive = $this->find_inactive_plugin( $backup_plugins );

		return new Check_Item(
			id:        'backup_plugin',
			label:     __( 'Backup Plugin', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   null !== $inactive
				? sprintf( __( '%s is deactivated. Click Auto Fix to activate %s.', 'scalyn-qa-assistant' ), $inactive['name'], $inactive['name'] )
				: __( 'No backup plugin detected. Install UpdraftPlus, BlogVault, or BackWPup to protect your data.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null !== $inactive ? 'auto_fix' : null,
			tooltip:   __( 'Without a backup solution, a failed update, hack, or server crash could mean permanent data loss.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if an image optimization plugin is installed.
	 *
	 * @since 1.3.0
	 */
	private function check_image_optimization_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$img_plugins = $this->get_recommended_plugins( 'image_optimization_plugin' );

		$found = array();
		foreach ( $img_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'image_optimization_plugin',
				label:     __( 'Image Optimization', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Image optimization plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Image optimization plugins automatically compress uploads to improve page load speed.', 'scalyn-qa-assistant' ),
			);
		}

		$inactive = $this->find_inactive_plugin( $img_plugins );

		return new Check_Item(
			id:        'image_optimization_plugin',
			label:     __( 'Image Optimization', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   null !== $inactive
				? sprintf( __( '%s is deactivated. Click Auto Fix to activate %s.', 'scalyn-qa-assistant' ), $inactive['name'], $inactive['name'] )
				: __( 'No image optimization plugin detected. Consider ShortPixel, Imagify, or Smush for faster page loads.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null !== $inactive ? 'auto_fix' : null,
			tooltip:   __( 'Unoptimized images are the #1 cause of slow page loads. An optimization plugin compresses images automatically on upload.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if WordPress Address and Site Address match.
	 *
	 * @since 1.3.0
	 */
	private function check_wp_address_match(): Check_Item {
		$site_url = get_option( 'siteurl', '' );
		$home_url = get_option( 'home', '' );

		if ( is_string( $site_url ) && is_string( $home_url ) && rtrim( $site_url, '/' ) !== rtrim( $home_url, '/' ) ) {
			return new Check_Item(
				id:        'wp_address_match',
				label:     __( 'WP Address Match', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: 1: WordPress address, 2: Site address */
					__( 'WordPress Address (%1$s) and Site Address (%2$s) are different. Verify this is intentional in Settings → General.', 'scalyn-qa-assistant' ),
					esc_html( $site_url ),
					esc_html( $home_url ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'Mismatched addresses can cause redirect loops or broken assets. They should match unless you have WordPress installed in a subdirectory on purpose.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'wp_address_match',
			label:     __( 'WP Address Match', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'WordPress Address and Site Address match.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Both addresses point to the same location, which is the standard configuration.', 'scalyn-qa-assistant' ),
		);
	}

	// ------------------------------------------------------------------
	// Nice-to-have checks
	// ------------------------------------------------------------------

	/**
	 * Check for published pages with very little content.
	 *
	 * @since 1.3.0
	 */
	private function check_empty_pages(): Check_Item {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$empty_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type IN ('post', 'page')
				AND post_status = %s
				AND LENGTH(post_content) < %d
				ORDER BY post_date DESC
				LIMIT 10",
				'publish',
				50,
			),
		);

		if ( ! empty( $empty_pages ) ) {
			$labels = array();
			foreach ( $empty_pages as $p ) {
				$labels[] = sprintf( '"%s" (ID: %d)', $p->post_title ?: __( '(no title)', 'scalyn-qa-assistant' ), (int) $p->ID );
			}

			return new Check_Item(
				id:        'empty_pages',
				label:     __( 'Empty Pages', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %d: number of empty pages */
					_n(
						'%d published page has little or no content.',
						'%d published pages have little or no content.',
						count( $empty_pages ),
						'scalyn-qa-assistant',
					),
					count( $empty_pages ),
				),
				category:  'content',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'Published pages with no content look broken to visitors and hurt SEO. Add content or unpublish them.', 'scalyn-qa-assistant' ),
				details:   array( 'empty_pages' => $labels ),
			);
		}

		return new Check_Item(
			id:        'empty_pages',
			label:     __( 'Empty Pages', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'All published pages have content.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'No empty or near-empty published pages were found.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if the theme has a custom 404 page template.
	 *
	 * @since 1.3.0
	 */
	private function check_404_page(): Check_Item {
		$tooltip  = __( 'A custom 404 page helps visitors find what they need when they hit a broken link.', 'scalyn-qa-assistant' );
		$template = get_stylesheet_directory() . '/404.php';
		$has_custom_404 = file_exists( $template );
		$source         = __( 'Theme has a custom 404 page template.', 'scalyn-qa-assistant' );

		// Elementor Theme Builder: check for a published 404 template.
		if ( ! $has_custom_404 && did_action( 'elementor/loaded' ) ) {
			$elementor_404 = get_posts( array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_elementor_template_type',
						'value' => 'error-404',
					),
				),
				'fields'         => 'ids',
			) );
			if ( ! empty( $elementor_404 ) ) {
				$has_custom_404 = true;
				$source         = __( 'Custom 404 page built with Elementor Theme Builder.', 'scalyn-qa-assistant' );
			}
		}

		if ( $has_custom_404 ) {
			return new Check_Item(
				id:        'four_oh_four_page',
				label:     __( '404 Page', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   $source,
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'four_oh_four_page',
			label:     __( '404 Page', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No custom 404 page found. Visitors hitting broken links will see a generic error page.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'info',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check if at least one navigation menu is assigned.
	 *
	 * @since 1.3.0
	 */
	private function check_menu_exists(): Check_Item {
		$locations = get_nav_menu_locations();
		$assigned  = array_filter( $locations, static fn( $id ) => (int) $id > 0 );

		if ( ! empty( $assigned ) ) {
			return new Check_Item(
				id:        'menu_exists',
				label:     __( 'Navigation Menu', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: number of assigned menu locations */
					_n(
						'%d navigation menu location assigned.',
						'%d navigation menu locations assigned.',
						count( $assigned ),
						'scalyn-qa-assistant',
					),
					count( $assigned ),
				),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Navigation menus help visitors find their way around your site.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'menu_exists',
			label:     __( 'Navigation Menu', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No navigation menu assigned to any location. Go to Appearance → Menus to create and assign one.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Without a navigation menu, visitors have no way to browse your site beyond the homepage.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if the timezone is set (not default UTC+0).
	 *
	 * @since 1.3.0
	 */
	private function check_timezone_set(): Check_Item {
		$timezone_string = get_option( 'timezone_string', '' );
		$gmt_offset      = get_option( 'gmt_offset', '0' );

		// If a named timezone is set, it's configured.
		if ( ! empty( $timezone_string ) ) {
			return new Check_Item(
				id:        'timezone_set',
				label:     __( 'Timezone', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %s: timezone name */
					__( 'Timezone set to %s.', 'scalyn-qa-assistant' ),
					esc_html( $timezone_string ),
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'A correct timezone ensures scheduled posts, cron jobs, and timestamps work properly.', 'scalyn-qa-assistant' ),
			);
		}

		// No named timezone — check if offset is 0 (likely default / unconfigured).
		if ( '0' === (string) $gmt_offset || '' === (string) $gmt_offset ) {
			return new Check_Item(
				id:        'timezone_set',
				label:     __( 'Timezone', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'Timezone is UTC+0 (may be unconfigured). Go to Settings → General and select your city timezone.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'WordPress defaults to UTC+0. If your site is not in that timezone, scheduled posts and timestamps will be wrong.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'timezone_set',
			label:     __( 'Timezone', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: %s: UTC offset */
				__( 'Timezone set to UTC%s.', 'scalyn-qa-assistant' ),
				( $gmt_offset >= 0 ? '+' : '' ) . $gmt_offset,
			),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Timezone offset is configured. A named timezone (e.g. "America/New_York") is preferred for DST handling.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if comments are open site-wide.
	 *
	 * @since 1.3.0
	 */
	private function check_comments_open(): Check_Item {
		$default_status = get_option( 'default_comment_status', 'open' );

		if ( 'open' === $default_status ) {
			return new Check_Item(
				id:        'comments_open',
				label:     __( 'Comments', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'Comments are open by default on new posts. If this is not a blog, consider disabling them in Settings → Discussion.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Open comments can attract spam on non-blog sites. Disable them if your site does not need user comments.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'comments_open',
			label:     __( 'Comments', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'Comments are closed by default on new posts.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Comments are disabled by default, reducing spam risk on non-blog sites.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if breadcrumbs are enabled via an SEO plugin or breadcrumb plugin.
	 *
	 * Supports: Rank Math, Yoast, AIOSEO, SEOPress, Breadcrumb NavXT.
	 *
	 * @since 1.3.0
	 */
	private function check_breadcrumbs_enabled(): Check_Item {
		$tooltip = __( 'Breadcrumbs improve navigation and SEO by showing the page hierarchy. Enable them in your SEO plugin or install a breadcrumb plugin.', 'scalyn-qa-assistant' );

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_modules = (array) get_option( 'rank_math_modules', array() );
			if ( in_array( 'breadcrumbs', $rm_modules, true ) ) {
				return new Check_Item(
					id: 'breadcrumbs_enabled', label: __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
					status: 'pass', message: __( 'Breadcrumbs enabled via Rank Math.', 'scalyn-qa-assistant' ),
					category: 'seo', severity: 'info', tooltip: $tooltip,
				);
			}
		}

		// Yoast.
		if ( defined( 'WPSEO_VERSION' ) && class_exists( '\WPSEO_Options' ) ) {
			$yoast_breadcrumbs = \WPSEO_Options::get( 'breadcrumbs-enable', false );
			if ( $yoast_breadcrumbs ) {
				return new Check_Item(
					id: 'breadcrumbs_enabled', label: __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
					status: 'pass', message: __( 'Breadcrumbs enabled via Yoast SEO.', 'scalyn-qa-assistant' ),
					category: 'seo', severity: 'info', tooltip: $tooltip,
				);
			}
		}

		// SEOPress.
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$sp_breadcrumbs = get_option( 'seopress_toggle', array() );
			if ( is_array( $sp_breadcrumbs ) && ! empty( $sp_breadcrumbs['toggle-breadcrumbs'] ) ) {
				return new Check_Item(
					id: 'breadcrumbs_enabled', label: __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
					status: 'pass', message: __( 'Breadcrumbs enabled via SEOPress.', 'scalyn-qa-assistant' ),
					category: 'seo', severity: 'info', tooltip: $tooltip,
				);
			}
		}

		// Breadcrumb NavXT plugin.
		if ( function_exists( 'bcn_display' ) ) {
			return new Check_Item(
				id: 'breadcrumbs_enabled', label: __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
				status: 'pass', message: __( 'Breadcrumbs enabled via Breadcrumb NavXT.', 'scalyn-qa-assistant' ),
				category: 'seo', severity: 'info', tooltip: $tooltip,
			);
		}

		// Check homepage HTML for breadcrumb markup.
		$html = $this->fetch_homepage_html();
		if ( '' !== $html && ( stripos( $html, 'BreadcrumbList' ) !== false || stripos( $html, 'breadcrumb' ) !== false ) ) {
			return new Check_Item(
				id: 'breadcrumbs_enabled', label: __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
				status: 'pass', message: __( 'Breadcrumb markup detected on the homepage.', 'scalyn-qa-assistant' ),
				category: 'seo', severity: 'info', tooltip: $tooltip,
			);
		}

		$can_auto_fix = defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );

		return new Check_Item(
			id:        'breadcrumbs_enabled',
			label:     __( 'Breadcrumbs', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   $can_auto_fix
				? __( 'No breadcrumbs detected. Use Auto Fix to enable them in your SEO plugin.', 'scalyn-qa-assistant' )
				: __( 'No breadcrumbs detected. Install an SEO plugin (Rank Math, Yoast, or SEOPress) to enable breadcrumbs.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'info',
			quick_fix: $can_auto_fix ? 'auto_fix' : null,
			tooltip:   $tooltip,
		);
	}

	// ------------------------------------------------------------------
	// Pro-enhanced checks
	// ------------------------------------------------------------------

	/**
	 * Detect pro SEO plugin status.
	 *
	 * @return array{plugin: string, has_pro: bool}
	 */
	private function detect_seo_pro(): array {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return array( 'plugin' => 'Rank Math', 'has_pro' => defined( 'RANK_MATH_PRO_VERSION' ) );
		}
		if ( defined( 'WPSEO_VERSION' ) ) {
			return array( 'plugin' => 'Yoast SEO', 'has_pro' => defined( 'WPSEO_PREMIUM_FILE' ) );
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return array( 'plugin' => 'AIOSEO', 'has_pro' => defined( 'AIOSEO_PRO_VERSION' ) );
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return array( 'plugin' => 'SEOPress', 'has_pro' => defined( 'SEOPRESS_PRO_VERSION' ) );
		}
		return array( 'plugin' => '', 'has_pro' => false );
	}

	/**
	 * Check if a redirect manager is active and configured.
	 *
	 * @since 1.3.0
	 */
	private function check_redirect_manager(): Check_Item {
		$tooltip = __( 'A redirect manager catches 404 errors and lets you create redirects. Essential for site migrations and avoiding broken links in search results.', 'scalyn-qa-assistant' );
		$seo     = $this->detect_seo_pro();

		if ( '' === $seo['plugin'] ) {
			return new Check_Item( id: 'redirect_manager', label: __( 'Redirect Manager', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'No SEO plugin detected — install one for redirect management.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		// Rank Math: redirections module.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( in_array( 'redirections', $modules, true ) ) {
				return new Check_Item( id: 'redirect_manager', label: __( 'Redirect Manager', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'Redirect manager enabled via Rank Math.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
			}
		}

		// Yoast Premium: redirects are automatic.
		if ( defined( 'WPSEO_PREMIUM_FILE' ) ) {
			return new Check_Item( id: 'redirect_manager', label: __( 'Redirect Manager', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'Redirect manager available via Yoast Premium.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		// SEOPress Pro: redirections module.
		if ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			return new Check_Item( id: 'redirect_manager', label: __( 'Redirect Manager', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'Redirect manager available via SEOPress Pro.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		// Standalone redirect plugins.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'redirection/redirection.php' ) ) {
			return new Check_Item( id: 'redirect_manager', label: __( 'Redirect Manager', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'Redirect manager enabled via Redirection plugin.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		$can_auto_fix = defined( 'RANK_MATH_VERSION' );

		$message = $can_auto_fix
			? __( 'Rank Math is installed but the Redirections module is not enabled. Use Auto Fix to enable it.', 'scalyn-qa-assistant' )
			: ( $seo['has_pro']
				? __( 'Redirect module is available but not enabled. Enable it in your SEO plugin settings.', 'scalyn-qa-assistant' )
				: sprintf( __( '%s Free detected — upgrade to Pro for redirect management, or install the free Redirection plugin.', 'scalyn-qa-assistant' ), $seo['plugin'] ) );

		return new Check_Item( id: 'redirect_manager', label: __( 'Redirect Manager', 'scalyn-qa-assistant' ), status: 'warning', message: $message, category: 'seo', severity: 'info', quick_fix: $can_auto_fix ? 'auto_fix' : null, tooltip: $tooltip );
	}

	/**
	 * Check if Local Business schema is configured.
	 *
	 * @since 1.3.0
	 */
	private function check_local_business_schema(): Check_Item {
		$tooltip = __( 'Local Business schema helps your business appear in Google Maps and local search results with rich info (address, hours, phone).', 'scalyn-qa-assistant' );
		$seo     = $this->detect_seo_pro();

		// Rank Math Pro: local SEO module.
		if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( in_array( 'local-seo', $modules, true ) ) {
				$titles = get_option( 'rank_math_titles', array() );
				$name   = $titles['local_business_type'] ?? '';
				if ( '' !== $name && 'Organization' !== $name ) {
					return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'pass', message: sprintf( __( 'Local SEO configured via Rank Math Pro (type: %s).', 'scalyn-qa-assistant' ), esc_html( $name ) ), category: 'seo', severity: 'info', tooltip: $tooltip );
				}
				return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'warning', message: __( 'Local SEO module is enabled in Rank Math Pro but business type is not set. Configure it in Rank Math → Titles & Meta → Local SEO.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'warning', tooltip: $tooltip );
			}
		}

		// SEOPress Pro: local business.
		if ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			$local = get_option( 'seopress_pro_option_name', array() );
			if ( is_array( $local ) && ! empty( $local['seopress_local_business_type'] ) ) {
				return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'Local business schema configured via SEOPress Pro.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
			}
		}

		// Check our own stored JSON-LD (from auto-fix).
		$own_jsonld = get_option( 'scalyn_qa_local_business_jsonld', array() );
		if ( ! empty( $own_jsonld ) && is_array( $own_jsonld ) ) {
			$type = $own_jsonld['@type'] ?? 'LocalBusiness';
			return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'pass', message: sprintf( __( 'Local Business schema configured via Scalyn QA (type: %s).', 'scalyn-qa-assistant' ), esc_html( $type ) ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		// Check for JSON-LD LocalBusiness in homepage HTML.
		$html = $this->fetch_homepage_html();
		if ( '' !== $html && stripos( $html, 'LocalBusiness' ) !== false ) {
			return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'LocalBusiness schema detected on homepage.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		if ( '' === $seo['plugin'] ) {
			return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'warning', message: __( 'No local business schema detected. If you are a local business, add schema via an SEO plugin Pro version.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		$message = $seo['has_pro']
			? __( 'Pro SEO plugin detected but Local Business schema not configured. Set it up in your SEO plugin settings.', 'scalyn-qa-assistant' )
			: sprintf( __( '%s Free does not include Local SEO. Upgrade to Pro or add LocalBusiness schema manually.', 'scalyn-qa-assistant' ), $seo['plugin'] );

		return new Check_Item( id: 'local_business_schema', label: __( 'Local Business Schema', 'scalyn-qa-assistant' ), status: 'warning', message: $message, category: 'seo', severity: 'info', tooltip: $tooltip );
	}

	/**
	 * Check if 404 monitoring is enabled.
	 *
	 * @since 1.3.0
	 */
	private function check_404_monitor(): Check_Item {
		$tooltip = __( '404 monitoring tracks broken URLs visitors hit, helping you create redirects and fix link rot before it hurts SEO.', 'scalyn-qa-assistant' );

		// Rank Math: 404 monitor module (available in free).
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( in_array( '404-monitor', $modules, true ) ) {
				return new Check_Item( id: 'four_oh_four_monitor', label: __( '404 Monitor', 'scalyn-qa-assistant' ), status: 'pass', message: __( '404 monitoring enabled via Rank Math.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
			}
		}

		// SEOPress Pro: 404 monitoring.
		if ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			$toggle = get_option( 'seopress_toggle', array() );
			if ( is_array( $toggle ) && ! empty( $toggle['toggle-404'] ) ) {
				return new Check_Item( id: 'four_oh_four_monitor', label: __( '404 Monitor', 'scalyn-qa-assistant' ), status: 'pass', message: __( '404 monitoring enabled via SEOPress Pro.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
			}
		}

		// Standalone 404 plugins.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( '404-to-301/404-to-301.php' ) || is_plugin_active( 'redirection/redirection.php' ) ) {
			return new Check_Item( id: 'four_oh_four_monitor', label: __( '404 Monitor', 'scalyn-qa-assistant' ), status: 'pass', message: __( '404 monitoring available via installed redirect/404 plugin.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		$seo = $this->detect_seo_pro();
		$can_auto_fix = defined( 'RANK_MATH_VERSION' );

		if ( '' !== $seo['plugin'] && 'Rank Math' !== $seo['plugin'] ) {
			$message = sprintf(
				__( 'No 404 monitoring detected. %s does not include a 404 monitor. Install the free "Redirection" or "404 to 301" plugin.', 'scalyn-qa-assistant' ),
				$seo['plugin'],
			);
		} elseif ( $can_auto_fix ) {
			$message = __( 'Rank Math is installed but the 404 Monitor module is not enabled. Use Auto Fix to enable it.', 'scalyn-qa-assistant' );
		} else {
			$message = __( 'No 404 monitoring detected. Install Rank Math (free, has built-in 404 monitor) or the free "Redirection" plugin.', 'scalyn-qa-assistant' );
		}

		return new Check_Item( id: 'four_oh_four_monitor', label: __( '404 Monitor', 'scalyn-qa-assistant' ), status: 'warning', message: $message, category: 'seo', severity: 'info', quick_fix: $can_auto_fix ? 'auto_fix' : null, tooltip: $tooltip );
	}

	/**
	 * Check if cornerstone/pillar content is set (Yoast Premium feature).
	 *
	 * @since 1.3.0
	 */
	private function check_cornerstone_content(): Check_Item {
		$tooltip = __( 'Cornerstone content marks your most important pages, helping SEO plugins optimize internal linking and prioritize them in search results.', 'scalyn-qa-assistant' );

		$cornerstone_pages = array();

		// Yoast: _yoast_wpseo_is_cornerstone = '1'.
		if ( defined( 'WPSEO_VERSION' ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = '_yoast_wpseo_is_cornerstone' AND pm.meta_value = '1'
				AND p.post_status = 'publish'
				LIMIT 10",
			);
			foreach ( $results as $row ) {
				$cornerstone_pages[] = $row->post_title;
			}
		}

		// Rank Math: rank_math_pillar_content = 'on'.
		if ( empty( $cornerstone_pages ) && defined( 'RANK_MATH_VERSION' ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = 'rank_math_pillar_content' AND pm.meta_value = 'on'
				AND p.post_status = 'publish'
				LIMIT 10",
			);
			foreach ( $results as $row ) {
				$cornerstone_pages[] = $row->post_title;
			}
		}

		if ( ! empty( $cornerstone_pages ) ) {
			return new Check_Item(
				id:       'cornerstone_content',
				label:    __( 'Cornerstone Content', 'scalyn-qa-assistant' ),
				status:   'pass',
				message:  sprintf(
					_n( '%d cornerstone page set.', '%d cornerstone pages set.', count( $cornerstone_pages ), 'scalyn-qa-assistant' ),
					count( $cornerstone_pages ),
				),
				category: 'seo',
				severity: 'info',
				tooltip:  $tooltip,
				details:  array( 'cornerstone_pages' => $cornerstone_pages ),
			);
		}

		$seo = $this->detect_seo_pro();

		if ( '' === $seo['plugin'] ) {
			return new Check_Item( id: 'cornerstone_content', label: __( 'Cornerstone Content', 'scalyn-qa-assistant' ), status: 'warning', message: __( 'No cornerstone content set. Install an SEO plugin and mark your most important pages as cornerstone.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		$feature_name = defined( 'RANK_MATH_VERSION' ) ? __( 'Pillar Content', 'scalyn-qa-assistant' ) : __( 'Cornerstone Content', 'scalyn-qa-assistant' );

		return new Check_Item(
			id:       'cornerstone_content',
			label:    __( 'Cornerstone Content', 'scalyn-qa-assistant' ),
			status:   'warning',
			message:  sprintf(
				__( 'No pages marked as %1$s. Open your most important pages and enable the %1$s toggle in %2$s.', 'scalyn-qa-assistant' ),
				$feature_name,
				$seo['plugin'],
			),
			category: 'seo',
			severity: 'info',
			tooltip:  $tooltip,
		);
	}

	/**
	 * Check if Instant Indexing / IndexNow is configured.
	 *
	 * @since 1.3.0
	 */
	private function check_instant_indexing(): Check_Item {
		$tooltip = __( 'Instant Indexing (IndexNow) notifies search engines immediately when you publish or update content, instead of waiting for them to crawl.', 'scalyn-qa-assistant' );

		// Rank Math Pro: instant indexing module.
		if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( in_array( 'instant-indexing', $modules, true ) ) {
				return new Check_Item( id: 'instant_indexing', label: __( 'Instant Indexing', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'Instant Indexing enabled via Rank Math Pro.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
			}
		}

		// IndexNow standalone plugin.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'indexnow/indexnow.php' ) || is_plugin_active( 'microsoft-indexnow/microsoft-indexnow.php' ) ) {
			return new Check_Item( id: 'instant_indexing', label: __( 'Instant Indexing', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'IndexNow plugin detected.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		// Yoast Premium has IndexNow built-in since v21.
		if ( defined( 'WPSEO_PREMIUM_FILE' ) && defined( 'WPSEO_VERSION' ) && version_compare( WPSEO_VERSION, '21.0', '>=' ) ) {
			return new Check_Item( id: 'instant_indexing', label: __( 'Instant Indexing', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'IndexNow available via Yoast Premium.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		$can_auto_fix = defined( 'RANK_MATH_PRO_VERSION' );
		$seo          = $this->detect_seo_pro();

		if ( $can_auto_fix ) {
			$message = __( 'Rank Math Pro is installed but Instant Indexing module is not enabled. Use Auto Fix to enable it.', 'scalyn-qa-assistant' );
		} elseif ( $seo['has_pro'] ) {
			$message = __( 'Pro SEO plugin detected but Instant Indexing not enabled. Enable it in your SEO plugin settings.', 'scalyn-qa-assistant' );
		} else {
			$message = __( 'No Instant Indexing detected. Available in Rank Math Pro, Yoast Premium, or the free IndexNow plugin.', 'scalyn-qa-assistant' );
		}

		return new Check_Item( id: 'instant_indexing', label: __( 'Instant Indexing', 'scalyn-qa-assistant' ), status: 'warning', message: $message, category: 'seo', severity: 'info', quick_fix: $can_auto_fix ? 'auto_fix' : null, tooltip: $tooltip );
	}

	/**
	 * Check WooCommerce SEO configuration (if WooCommerce is active).
	 *
	 * @since 1.3.0
	 */
	private function check_woocommerce_seo(): Check_Item {
		$tooltip = __( 'WooCommerce SEO ensures your products have proper schema markup, meta templates, and are optimized for search.', 'scalyn-qa-assistant' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return new Check_Item( id: 'woocommerce_seo', label: __( 'WooCommerce SEO', 'scalyn-qa-assistant' ), status: 'pass', message: __( 'WooCommerce not active — not applicable.', 'scalyn-qa-assistant' ), category: 'seo', severity: 'info', tooltip: $tooltip );
		}

		$issues = array();
		$passes = array();
		$seo    = $this->detect_seo_pro();

		// Check if an SEO plugin is handling WooCommerce.
		if ( '' === $seo['plugin'] ) {
			$issues[] = __( 'No SEO plugin to manage product meta and schema', 'scalyn-qa-assistant' );
		} else {
			$passes[] = sprintf( __( '%s handling product SEO', 'scalyn-qa-assistant' ), $seo['plugin'] );
		}

		// Rank Math Pro: WooCommerce module.
		if ( defined( 'RANK_MATH_PRO_VERSION' ) ) {
			$modules = (array) get_option( 'rank_math_modules', array() );
			if ( in_array( 'woocommerce', $modules, true ) ) {
				$passes[] = __( 'WooCommerce SEO module enabled', 'scalyn-qa-assistant' );
			}
		}

		// Yoast WooCommerce addon.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'wpseo-woocommerce/wpseo-woocommerce.php' ) ) {
			$passes[] = __( 'Yoast WooCommerce SEO addon active', 'scalyn-qa-assistant' );
		}

		// Check for products without descriptions.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$empty_products = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status = %s AND LENGTH(post_content) < %d",
				'product',
				'publish',
				50,
			),
		);
		if ( $empty_products > 0 ) {
			$issues[] = sprintf(
				_n( '%d product with little or no description', '%d products with little or no description', $empty_products, 'scalyn-qa-assistant' ),
				$empty_products,
			);
		}

		if ( empty( $issues ) ) {
			return new Check_Item( id: 'woocommerce_seo', label: __( 'WooCommerce SEO', 'scalyn-qa-assistant' ), status: 'pass', message: implode( '. ', $passes ) . '.', category: 'seo', severity: 'info', tooltip: $tooltip, details: array( 'passes' => $passes ) );
		}

		return new Check_Item(
			id:       'woocommerce_seo',
			label:    __( 'WooCommerce SEO', 'scalyn-qa-assistant' ),
			status:   'warning',
			message:  implode( '. ', array_merge( $passes, $issues ) ) . '.',
			category: 'seo',
			severity: 'warning',
			tooltip:  $tooltip,
			details:  array( 'issues' => $issues, 'passes' => $passes ),
		);
	}
}
