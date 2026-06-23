<?php
/**
 * GitHub Updater.
 *
 * Checks a public GitHub repository for new releases and integrates
 * with the WordPress plugin update system so the plugin can be updated
 * directly from the WordPress dashboard.
 *
 * @package Scalyn\QA\Updates
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Updates;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\AI\AI_Manager;
use Scalyn\QA\Debug_Logger;

/**
 * Class GitHub_Updater
 *
 * Queries the GitHub Releases API for the latest release of the plugin
 * repository, injects update information into the WordPress transient,
 * supplies plugin details for the "View Details" modal, and corrects
 * the extracted folder name after installation.
 *
 * @since 1.0.0
 */
final class GitHub_Updater {

	/**
	 * GitHub Releases API URL template.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.github.com/repos/%s/%s/releases/latest';

	/**
	 * Transient key used to cache the latest release data.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'scalyn_qa_github_update';

	/**
	 * Cache time-to-live in seconds (3 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3 * HOUR_IN_SECONDS;

	/**
	 * Plugin basename (e.g. "scalyn-qa-assistant/scalyn-qa-assistant.php").
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Plugin slug used by WordPress (e.g. "scalyn-qa-assistant").
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Currently installed plugin version.
	 *
	 * @var string
	 */
	private string $current_version;

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private string $repo_owner;

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private string $repo_name;

	/**
	 * Constructor.
	 *
	 * Reads plugin constants and settings to initialise the updater.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin_basename = SCALYN_QA_PLUGIN_BASENAME;
		$this->plugin_slug     = 'scalyn-qa-assistant';
		$this->current_version = SCALYN_QA_VERSION;

		$settings         = get_option( 'scalyn_qa_settings', [] );
		$this->repo_owner = ! empty( $settings['github_owner'] ) ? $settings['github_owner'] : 'toxickim24';
		$this->repo_name  = ! empty( $settings['github_repo'] ) ? $settings['github_repo'] : 'scalyn-qa-assistant';
	}

	/**
	 * Register WordPress hooks for the update system.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );

		// Disable WordPress.org updates for this plugin.
		add_filter( 'http_request_args', [ $this, 'disable_wporg_update' ], 5, 2 );

		// Clear our GitHub cache when WordPress forces a fresh update check.
		add_action( 'load-plugins.php', [ $this, 'maybe_clear_cache' ] );
		add_action( 'load-update-core.php', [ $this, 'maybe_clear_cache' ] );
		add_action( 'wp_update_plugins', [ $this, 'maybe_clear_cache' ] );
	}

	/**
	 * Clear GitHub cache and force WordPress to re-check for updates.
	 *
	 * @since 1.4.2
	 */
	public function maybe_clear_cache(): void {
		delete_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Check GitHub for a newer release and inject into WordPress update transient.
	 *
	 * Hooked to: pre_set_site_transient_update_plugins
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient The update_plugins transient value.
	 * @return object Modified transient with update data (if available).
	 */
	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( is_wp_error( $release ) || empty( $release ) ) {
			return $transient;
		}

		$latest_version = $this->normalize_version( $release['tag_name'] ?? '' );

		if ( empty( $latest_version ) || version_compare( $this->current_version, $latest_version, '>=' ) ) {
			// No update needed — remove any stale entry.
			if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
				unset( $transient->response[ $this->plugin_basename ] );
			}

			// Add to no_update so WP knows we checked.
			$transient->no_update[ $this->plugin_basename ] = (object) [
				'id'          => $this->plugin_basename,
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $this->current_version,
				'url'         => $release['html_url'] ?? '',
				'package'     => '',
			];

			return $transient;
		}

		// Update available.
		$download_url = $this->get_download_url( $release );

		$transient->response[ $this->plugin_basename ] = (object) [
			'id'            => $this->plugin_basename,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_basename,
			'new_version'   => $latest_version,
			'url'           => $release['html_url'] ?? '',
			'package'       => $download_url,
			'icons'         => [],
			'banners'       => [],
			'tested'        => '',
			'requires_php'  => '8.2',
			'compatibility' => new \stdClass(),
		];

		return $transient;
	}

	/**
	 * Provide plugin info for the WordPress "View Details" modal.
	 *
	 * Hooked to: plugins_api
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $result The default result from WordPress.
	 * @param string $action The API action being performed.
	 * @param object $args   Plugin API arguments.
	 * @return mixed Plugin info object when this plugin is requested, or the default result.
	 */
	public function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( is_wp_error( $release ) || empty( $release ) ) {
			return $result;
		}

		$latest_version = $this->normalize_version( $release['tag_name'] ?? '' );
		$download_url   = $this->get_download_url( $release );

		$info                = new \stdClass();
		$info->name          = 'Scalyn QA Assistant';
		$info->slug          = $this->plugin_slug;
		$info->version       = $latest_version;
		$info->author        = '<a href="https://scalyn.com">Scalyn</a>';
		$info->homepage      = "https://github.com/{$this->repo_owner}/{$this->repo_name}";
		$info->requires      = '6.0';
		$info->requires_php  = '8.2';
		$info->tested        = get_bloginfo( 'version' );
		$info->download_link = $download_url;
		$info->trunk         = $download_url;
		$info->last_updated  = $release['published_at'] ?? '';

		// Parse changelog from release body (Markdown).
		$body = $release['body'] ?? '';

		if ( ! empty( $body ) ) {
			// Convert basic markdown to HTML.
			$changelog = esc_html( $body );
			$changelog = nl2br( $changelog );

			// Bold: **text**.
			$changelog = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $changelog );

			// Lists: - item or * item.
			$changelog = preg_replace( '/^[\-\*]\s+(.+)$/m', '<li>$1</li>', $changelog );
			$changelog = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog );

			$info->sections = [
				'description' => 'Website QA, SEO validation, and launch readiness tool for WordPress.',
				'changelog'   => wp_kses_post( $changelog ),
			];
		} else {
			$info->sections = [
				'description' => 'Website QA, SEO validation, and launch readiness tool for WordPress.',
			];
		}

		return $info;
	}

	/**
	 * After installing an update, fix the plugin folder name.
	 *
	 * GitHub source ZIPs extract to `repo-name-tag/` instead of `plugin-slug/`.
	 * This renames the extracted folder to the expected plugin slug.
	 *
	 * Hooked to: upgrader_post_install
	 *
	 * @since 1.0.0
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 * @return array Modified result data with corrected destination paths.
	 */
	public function post_install( mixed $response, array $hook_extra, array $result ): array {
		// Only act on our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $result;
		}

		global $wp_filesystem;

		$install_dir = $result['destination'];
		$proper_dir  = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

		// If the directory name doesn't match, rename it.
		if ( $install_dir !== $proper_dir && $wp_filesystem ) {
			$wp_filesystem->move( $install_dir, $proper_dir );
			$result['destination']      = $proper_dir;
			$result['destination_name'] = $this->plugin_slug;
		}

		// Re-activate if it was active before.
		if ( is_plugin_active( $this->plugin_basename ) ) {
			activate_plugin( $this->plugin_basename );
		}

		return $result;
	}

	/**
	 * Prevent WordPress from checking wordpress.org for this plugin's updates.
	 *
	 * Removes the plugin from the payload sent to the WordPress.org
	 * update-check API so that only GitHub releases are considered.
	 *
	 * Hooked to: http_request_args
	 *
	 * @since 1.0.0
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  The request URL.
	 * @return array Modified HTTP request arguments.
	 */
	public function disable_wporg_update( array $args, string $url ): array {
		if ( strpos( $url, 'api.wordpress.org/plugins/update-check' ) === false ) {
			return $args;
		}

		// Remove our plugin from the plugins list sent to wordpress.org.
		if ( isset( $args['body']['plugins'] ) ) {
			$plugins = json_decode( $args['body']['plugins'], true );

			if ( isset( $plugins['plugins'][ $this->plugin_basename ] ) ) {
				unset( $plugins['plugins'][ $this->plugin_basename ] );
			}

			if ( isset( $plugins['active'] ) && is_array( $plugins['active'] ) ) {
				$plugins['active'] = array_values(
					array_diff( $plugins['active'], [ $this->plugin_basename ] )
				);
			}

			$args['body']['plugins'] = wp_json_encode( $plugins );
		}

		return $args;
	}

	/**
	 * Fetch the latest release data from the GitHub API.
	 *
	 * Results are cached in a WordPress transient for CACHE_TTL seconds.
	 * Pass $force = true to bypass the cache and query the API directly.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Whether to bypass the transient cache.
	 * @return array|\WP_Error Release data array on success, or WP_Error on failure.
	 */
	public function get_latest_release( bool $force = false ): array|\WP_Error {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$url = sprintf( self::API_URL, $this->repo_owner, $this->repo_name );

		$headers = [
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'Scalyn-QA-Assistant/' . $this->current_version,
		];

		// Add optional token for higher rate limits.
		$token = $this->get_token();
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, [
			'headers' => $headers,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			Debug_Logger::log( 'updater', 'GitHub API request failed: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 403 === $code ) {
			$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );

			if ( '0' === $remaining ) {
				$error = new \WP_Error(
					'rate_limited',
					__( 'GitHub API rate limit exceeded. Try again later or add a GitHub token.', 'scalyn-qa-assistant' )
				);
				Debug_Logger::log( 'updater', 'GitHub API rate limited' );
				return $error;
			}
		}

		if ( 404 === $code ) {
			$error = new \WP_Error(
				'no_release',
				__( 'No GitHub release found for this repository.', 'scalyn-qa-assistant' )
			);
			Debug_Logger::log( 'updater', 'No GitHub release found' );
			return $error;
		}

		if ( 200 !== $code ) {
			$error = new \WP_Error(
				'api_error',
				__( 'GitHub API returned an unexpected response.', 'scalyn-qa-assistant' )
			);
			Debug_Logger::log( 'updater', "GitHub API error: HTTP {$code}" );
			return $error;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			$error = new \WP_Error(
				'invalid_response',
				__( 'Invalid response from GitHub API.', 'scalyn-qa-assistant' )
			);
			Debug_Logger::log( 'updater', 'Invalid GitHub API response format' );
			return $error;
		}

		// Cache the result.
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		// Store last check time.
		update_option( 'scalyn_qa_github_last_check', gmdate( 'c' ) );

		return $data;
	}

	/**
	 * Get the download URL for the latest release.
	 *
	 * Prefers an attached .zip asset. Falls back to the GitHub source
	 * zipball URL when no .zip asset is available.
	 *
	 * @since 1.0.0
	 *
	 * @param array $release Release data from the GitHub API.
	 * @return string Download URL for the release ZIP.
	 */
	private function get_download_url( array $release ): string {
		// Check for attached ZIP asset.
		$assets = $release['assets'] ?? [];

		foreach ( $assets as $asset ) {
			$name = $asset['name'] ?? '';
			if ( str_ends_with( $name, '.zip' ) && ! empty( $asset['browser_download_url'] ) ) {
				return $asset['browser_download_url'];
			}
		}

		// Fallback: GitHub source ZIP.
		return $release['zipball_url'] ?? '';
	}

	/**
	 * Normalize a version string by removing the leading "v" prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version Raw version string (e.g. "v1.2.3").
	 * @return string Cleaned version string (e.g. "1.2.3").
	 */
	private function normalize_version( string $version ): string {
		return ltrim( trim( $version ), 'vV' );
	}

	/**
	 * Get the optional GitHub personal access token (decrypted).
	 *
	 * Used to authenticate API requests for higher rate limits on
	 * public repositories or to access private repositories.
	 *
	 * @since 1.0.0
	 *
	 * @return string Decrypted token, or empty string if not configured.
	 */
	private function get_token(): string {
		$settings  = get_option( 'scalyn_qa_settings', [] );
		$encrypted = $settings['github_token'] ?? '';

		if ( empty( $encrypted ) ) {
			return '';
		}

		return AI_Manager::decrypt_key( $encrypted );
	}

	/**
	 * Perform a manual update check.
	 *
	 * Bypasses the transient cache and queries the GitHub API directly.
	 * Returns a structured result suitable for the REST API and settings UI.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Structured check result.
	 *
	 *     @type string      $status            One of 'update_available', 'up_to_date', or 'error'.
	 *     @type string      $message           Human-readable status message.
	 *     @type string      $installed_version Currently installed version.
	 *     @type string|null $latest_version    Latest available version, or null on error.
	 *     @type string      $release_url       URL to the GitHub release page (when available).
	 *     @type string      $release_date      ISO 8601 publication date (when available).
	 *     @type string      $last_checked      ISO 8601 timestamp of this check.
	 * }
	 */
	public function manual_check(): array {
		// Force fresh check (bypass cache).
		delete_transient( self::CACHE_KEY );

		$release = $this->get_latest_release( true );

		if ( is_wp_error( $release ) ) {
			return [
				'status'            => 'error',
				'message'           => $release->get_error_message(),
				'installed_version' => $this->current_version,
				'latest_version'    => null,
				'last_checked'      => gmdate( 'c' ),
			];
		}

		$latest     = $this->normalize_version( $release['tag_name'] ?? '' );
		$has_update = version_compare( $this->current_version, $latest, '<' );

		// Force WordPress to re-check.
		delete_site_transient( 'update_plugins' );

		return [
			'status'            => $has_update ? 'update_available' : 'up_to_date',
			'message'           => $has_update
				? sprintf(
					/* translators: %s: latest version number */
					__( 'Update available: v%s', 'scalyn-qa-assistant' ),
					$latest
				)
				: __( 'You are running the latest version.', 'scalyn-qa-assistant' ),
			'installed_version' => $this->current_version,
			'latest_version'    => $latest,
			'release_url'       => $release['html_url'] ?? '',
			'release_date'      => $release['published_at'] ?? '',
			'last_checked'      => gmdate( 'c' ),
		];
	}
}
