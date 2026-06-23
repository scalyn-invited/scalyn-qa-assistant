<?php
/**
 * Link Checker.
 *
 * Validates links in post content by checking HTTP status codes,
 * mailto/tel formats, and anchor targets.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Link_Checker
 *
 * Smart link checker that validates all links found in post content.
 * Includes SSRF protection, bot-blocking domain awareness, and transient caching.
 *
 * @since 1.0.0
 */
class Link_Checker implements Analyzer_Interface {

	/**
	 * Maximum number of links to check per scan.
	 *
	 * @var int
	 */
	private const MAX_LINKS_PER_SCAN = 50;

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 10;

	/**
	 * Default cache expiry in seconds (24 hours).
	 *
	 * @var int
	 */
	private const DEFAULT_CACHE_EXPIRY = DAY_IN_SECONDS;

	/**
	 * Domains known to block automated checks.
	 *
	 * @var string[]
	 */
	private const BOT_BLOCKING_DOMAINS = array(
		'facebook.com',
		'www.facebook.com',
		'linkedin.com',
		'www.linkedin.com',
		'instagram.com',
		'www.instagram.com',
	);

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'link_checker';
	}

	/**
	 * Generate a unique check ID for a specific link URL.
	 *
	 * @param string $url The link URL.
	 * @return string Unique ID like 'link_a1b2c3d4'.
	 */
	private function link_check_id( string $url ): string {
		return 'link_' . substr( md5( $url ), 0, 8 );
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Link Checker', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'functionality';
	}

	/**
	 * Run link analysis on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content = $this->get_rendered_content( $post_id );
		$parser  = new HTML_Parser( $content );
		$links   = $this->extract_links( $content );
		$results = array();

		$total_checked = 0;
		$broken_count  = 0;
		$warning_count = 0;

		foreach ( $links as $link ) {
			if ( $total_checked >= self::MAX_LINKS_PER_SCAN ) {
				break;
			}

			$check_result = $this->check_single_link( $link, $parser );

			if ( null !== $check_result ) {
				$results[] = $check_result;

				if ( 'fail' === $check_result->status ) {
					++$broken_count;
				} elseif ( 'warning' === $check_result->status ) {
					++$warning_count;
				}
			}

			++$total_checked;
		}

		// Add a pass result when no broken links were found.
		if ( 0 === $broken_count && 0 === $warning_count ) {
			$results[] = new Check_Item(
				id:        'broken_links',
				label:     __( 'Broken Link Check', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   0 === $total_checked
					? __( 'No links found to check.', 'scalyn-qa-assistant' )
					: sprintf(
						_n( '%d link checked — no broken links found.', '%d links checked — no broken links found.', $total_checked, 'scalyn-qa-assistant' ),
						$total_checked,
					),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'All links on this page are working correctly.', 'scalyn-qa-assistant' ),
			);
		}

		// Add summary check item.
		$results[] = $this->create_summary( $total_checked, $broken_count, $warning_count, count( $links ) );

		return $results;
	}

	/**
	 * Extract all links from HTML content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The HTML content to parse.
	 * @return array<int, array{url: string, text: string, type: string}>
	 */
	public function extract_links( string $content ): array {
		$parser    = new HTML_Parser( $content );
		$raw_links = $parser->get_links();
		$links     = array();

		foreach ( $raw_links as $link ) {
			$url  = trim( $link['url'] );
			$text = trim( wp_strip_all_tags( $link['text'] ) );
			$type = $this->classify_link_type( $url );

			$links[] = array(
				'url'  => $url,
				'text' => $text,
				'type' => $type,
			);
		}

		return $links;
	}

	/**
	 * Check a single URL and return its status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 * @return array{status_code: int, reachable: bool, error: string|null}
	 */
	public function check_url( string $url ): array {
		// Check transient cache first.
		$cache_key = 'scalyn_qa_link_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// SSRF protection: block private IPs — but allow the site's own domain.
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $host ) && $host !== $site_host && $this->is_private_ip( $host ) ) {
			$result = array(
				'status_code' => 0,
				'reachable'   => false,
				'error'       => __( 'Blocked: private/internal IP address.', 'scalyn-qa-assistant' ),
			);
			$this->cache_result( $cache_key, $result );
			return $result;
		}

		// Try HEAD request first.
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 5,
				'sslverify'   => true,
				'user-agent'  => 'Scalyn QA Assistant/1.0 (WordPress Link Checker)',
			),
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$result        = array(
				'status_code' => 0,
				'reachable'   => false,
				'error'       => $error_message,
			);
			\Scalyn\QA\Debug_Logger::link_failure( $url, $error_message, [ 'status_code' => 0 ] );
			$this->cache_result( $cache_key, $result );
			return $result;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// If HEAD returns 405 Method Not Allowed, try GET.
		if ( 405 === $status_code ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => self::REQUEST_TIMEOUT,
					'redirection' => 5,
					'sslverify'   => true,
					'user-agent'  => 'Scalyn QA Assistant/1.0 (WordPress Link Checker)',
				),
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				$result        = array(
					'status_code' => 0,
					'reachable'   => false,
					'error'       => $error_message,
				);
				\Scalyn\QA\Debug_Logger::link_failure( $url, $error_message, [ 'status_code' => 0 ] );
				$this->cache_result( $cache_key, $result );
				return $result;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
		}

		$result = array(
			'status_code' => (int) $status_code,
			'reachable'   => $status_code >= 200 && $status_code < 400,
			'error'       => null,
		);

		$this->cache_result( $cache_key, $result );

		return $result;
	}

	/**
	 * Classify the severity of a link issue based on type and status code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type        The link type ('internal', 'external', 'mailto', 'tel', 'download', 'anchor').
	 * @param int    $status_code The HTTP status code (0 for non-HTTP links).
	 * @return string The severity: 'critical', 'warning', or 'info'.
	 */
	public function classify_severity( string $type, int $status_code ): string {
		// Mailto and tel links are informational checks.
		if ( 'mailto' === $type || 'tel' === $type ) {
			return 'info';
		}

		// 404 and 5xx errors are critical.
		if ( 404 === $status_code || ( $status_code >= 500 && $status_code < 600 ) ) {
			return 'critical';
		}

		// Redirects are warnings.
		if ( 301 === $status_code || 302 === $status_code ) {
			return 'warning';
		}

		// Unreachable (status 0) is critical.
		if ( 0 === $status_code ) {
			return 'critical';
		}

		// Other 4xx errors are critical.
		if ( $status_code >= 400 && $status_code < 500 ) {
			return 'critical';
		}

		return 'info';
	}

	/**
	 * Check a single link and return a Check_Item if there is an issue.
	 *
	 * @since 1.0.0
	 *
	 * @param array{url: string, text: string, type: string} $link   The link data.
	 * @param HTML_Parser                                    $parser The HTML parser for anchor checks.
	 * @return Check_Item|null A check item if there is an issue, null if the link is fine.
	 */
	private function check_single_link( array $link, HTML_Parser $parser ): ?Check_Item {
		$url  = $link['url'];
		$text = $link['text'];
		$type = $link['type'];

		switch ( $type ) {
			case 'mailto':
				return $this->check_mailto( $url, $text );

			case 'tel':
				return $this->check_tel( $url, $text );

			case 'anchor':
				return $this->check_anchor( $url, $text, $parser );

			case 'internal':
			case 'external':
				return $this->check_http_link( $url, $text, $type );

			default:
				return null;
		}
	}

	/**
	 * Check a mailto link for valid format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url  The mailto URL.
	 * @param string $text The link text.
	 * @return Check_Item|null
	 */
	private function check_mailto( string $url, string $text ): ?Check_Item {
		$email = str_replace( 'mailto:', '', $url );
		$email = explode( '?', $email )[0]; // Remove query parameters.

		if ( ! is_email( $email ) ) {
			return new Check_Item(
				id:        $this->link_check_id( $url ),
				label:     __( 'Broken Link', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: 1: the invalid email, 2: the link text */
					__( 'Invalid email format "%1$s" in link "%2$s".', 'scalyn-qa-assistant' ),
					esc_html( $email ),
					esc_html( $text ?: $url ),
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Mailto links should use a valid email format (e.g., mailto:name@example.com). Fix the href attribute in the post editor.', 'scalyn-qa-assistant' ),
				details:   array(
					'url'  => $url,
					'text' => $text,
					'type' => 'mailto',
				),
			);
		}

		return null;
	}

	/**
	 * Check a tel link for valid format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url  The tel URL.
	 * @param string $text The link text.
	 * @return Check_Item|null
	 */
	private function check_tel( string $url, string $text ): ?Check_Item {
		$phone = str_replace( 'tel:', '', $url );

		// Allow digits, plus sign, hyphens, spaces, parentheses, and dots.
		if ( ! preg_match( '/^\+?[\d\s\-().]+$/', $phone ) ) {
			return new Check_Item(
				id:        $this->link_check_id( $url ),
				label:     __( 'Broken Link', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: 1: the invalid phone number, 2: the link text */
					__( 'Invalid phone format "%1$s" in link "%2$s".', 'scalyn-qa-assistant' ),
					esc_html( $phone ),
					esc_html( $text ?: $url ),
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Tel links should use a valid phone format (e.g., tel:+1234567890). Fix the href attribute in the post editor.', 'scalyn-qa-assistant' ),
				details:   array(
					'url'  => $url,
					'text' => $text,
					'type' => 'tel',
				),
			);
		}

		return null;
	}

	/**
	 * Check an anchor link (#target) to see if the target ID exists in content.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $url    The anchor URL (e.g. "#section-1").
	 * @param string      $text   The link text.
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item|null
	 */
	private function check_anchor( string $url, string $text, HTML_Parser $parser ): ?Check_Item {
		$anchor_id = ltrim( $url, '#' );

		if ( '' === $anchor_id ) {
			return null; // Skip bare "#" links — those are handled by Form_Button_Analyzer.
		}

		// Check if the ID exists in the content using DOMXPath.
		if ( ! $parser->has_element_id( $anchor_id ) ) {
			return new Check_Item(
				id:        $this->link_check_id( $url ),
				label:     __( 'Broken Link', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: 1: the anchor target ID, 2: the link text */
					__( 'Anchor link "#%1$s" target not found in content (link text: "%2$s").', 'scalyn-qa-assistant' ),
					esc_html( $anchor_id ),
					esc_html( $text ?: $url ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'Anchor links (e.g., #section) must match an element ID on the page. Either add the matching ID to the target element, or update the link in the post editor.', 'scalyn-qa-assistant' ),
				details:   array(
					'url'       => $url,
					'text'      => $text,
					'type'      => 'anchor',
					'anchor_id' => $anchor_id,
				),
			);
		}

		return null;
	}

	/**
	 * Check an HTTP/HTTPS link by making a request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url  The URL to check.
	 * @param string $text The link text.
	 * @param string $type The link type ('internal' or 'external').
	 * @return Check_Item|null
	 */
	private function check_http_link( string $url, string $text, string $type ): ?Check_Item {
		// Check for bot-blocking domains.
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( is_string( $host ) && $this->is_bot_blocking_domain( $host ) ) {
			return new Check_Item(
				id:        $this->link_check_id( $url ),
				label:     __( 'Link Check Skipped', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: 1: the domain name, 2: the link text */
					__( 'Cannot verify %1$s: site blocks automated checks (link text: "%2$s").', 'scalyn-qa-assistant' ),
					esc_html( $host ),
					esc_html( $text ?: $url ),
				),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Some websites block automated link checks. This link was skipped.', 'scalyn-qa-assistant' ),
				details:   array(
					'url'  => $url,
					'text' => $text,
					'type' => $type,
				),
			);
		}

		$result      = $this->check_url( $url );
		$status_code = $result['status_code'];

		// If the link is reachable, no issue to report.
		if ( $result['reachable'] ) {
			return null;
		}

		$severity = $this->classify_severity( $type, $status_code );

		// Determine status based on severity.
		$status = match ( $severity ) {
			'critical' => 'fail',
			'warning'  => 'warning',
			default    => 'pass',
		};

		$message = $this->build_link_error_message( $url, $text, $status_code, $result['error'] );

		return new Check_Item(
			id:        'broken_links',
			label:     __( 'Broken Link', 'scalyn-qa-assistant' ),
			status:    $status,
			message:   $message,
			category:  'functionality',
			severity:  $severity,
			quick_fix: null,
			tooltip:   __( 'Broken links frustrate visitors and hurt SEO rankings. Update or remove the broken URL in the post editor.', 'scalyn-qa-assistant' ),
			details:   array(
				'url'         => $url,
				'text'        => $text,
				'type'        => $type,
				'status_code' => $status_code,
				'error'       => $result['error'],
			),
		);
	}

	/**
	 * Build an error message for a broken link.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $url         The broken URL.
	 * @param string      $text        The link text.
	 * @param int         $status_code The HTTP status code.
	 * @param string|null $error       The error message if any.
	 * @return string
	 */
	private function build_link_error_message( string $url, string $text, int $status_code, ?string $error ): string {
		$truncated_url = mb_strlen( $url ) > 80 ? mb_substr( $url, 0, 80 ) . '...' : $url;
		$display_text  = $text ?: $truncated_url;

		if ( 0 === $status_code && null !== $error ) {
			return sprintf(
				/* translators: 1: link text, 2: error message */
				__( 'Link "%1$s" is unreachable: %2$s', 'scalyn-qa-assistant' ),
				esc_html( $display_text ),
				esc_html( $error ),
			);
		}

		return sprintf(
			/* translators: 1: link text, 2: HTTP status code */
			__( 'Link "%1$s" returned HTTP %2$d.', 'scalyn-qa-assistant' ),
			esc_html( $display_text ),
			$status_code,
		);
	}

	/**
	 * Create a summary Check_Item for the link check results.
	 *
	 * @since 1.0.0
	 *
	 * @param int $checked  Total links checked.
	 * @param int $broken   Number of broken links.
	 * @param int $warnings Number of warning-level links.
	 * @param int $total    Total links found.
	 * @return Check_Item
	 */
	private function create_summary( int $checked, int $broken, int $warnings, int $total ): Check_Item {
		$status = 'pass';
		if ( $broken > 0 ) {
			$status = 'fail';
		} elseif ( $warnings > 0 ) {
			$status = 'warning';
		}

		$severity = 'info';
		if ( $broken > 0 ) {
			$severity = 'critical';
		} elseif ( $warnings > 0 ) {
			$severity = 'warning';
		}

		$message_parts = array();

		$message_parts[] = sprintf(
			/* translators: %d: number of links checked */
			_n( '%d link checked', '%d links checked', $checked, 'scalyn-qa-assistant' ),
			$checked,
		);

		if ( $total > self::MAX_LINKS_PER_SCAN ) {
			$message_parts[] = sprintf(
				/* translators: %d: total number of links */
				__( '(%d total, limit reached)', 'scalyn-qa-assistant' ),
				$total,
			);
		}

		$message_parts[] = sprintf(
			/* translators: %d: number of broken links */
			_n( '%d broken', '%d broken', $broken, 'scalyn-qa-assistant' ),
			$broken,
		);

		$message_parts[] = sprintf(
			/* translators: %d: number of warnings */
			_n( '%d warning', '%d warnings', $warnings, 'scalyn-qa-assistant' ),
			$warnings,
		);

		return new Check_Item(
			id:        'links_summary',
			label:     __( 'Links Summary', 'scalyn-qa-assistant' ),
			status:    $status,
			message:   implode( ', ', $message_parts ) . '.',
			category:  'functionality',
			severity:  $severity,
			quick_fix: null,
			tooltip:   __( 'Overview of all link checks performed on this page.', 'scalyn-qa-assistant' ),
			details:   array(
				'total'    => $total,
				'checked'  => $checked,
				'broken'   => $broken,
				'warnings' => $warnings,
			),
		);
	}

	/**
	 * Classify the type of a link URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to classify.
	 * @return string One of 'internal', 'external', 'mailto', 'tel', 'download', 'anchor'.
	 */
	private function classify_link_type( string $url ): string {
		if ( str_starts_with( $url, 'mailto:' ) ) {
			return 'mailto';
		}

		if ( str_starts_with( $url, 'tel:' ) ) {
			return 'tel';
		}

		if ( str_starts_with( $url, '#' ) ) {
			return 'anchor';
		}

		// Check for common download file extensions.
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( is_string( $path ) ) {
			$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( in_array( $extension, array( 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv' ), true ) ) {
				return 'download';
			}
		}

		// Check internal vs external.
		$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$parsed_host = wp_parse_url( $url, PHP_URL_HOST );

		// Relative URLs are internal.
		if ( null === $parsed_host || false === $parsed_host ) {
			return 'internal';
		}

		if ( is_string( $site_host ) && strcasecmp( $parsed_host, $site_host ) === 0 ) {
			return 'internal';
		}

		return 'external';
	}

	/**
	 * Check if a hostname resolves to a private/internal IP address.
	 *
	 * Performs DNS resolution and checks all returned IPs to prevent
	 * DNS rebinding attacks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host The hostname to check.
	 * @return bool True if the host resolves to a private IP.
	 */
	private function is_private_ip( string $host ): bool {
		// Direct IP check.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $this->is_ip_private( $host );
		}

		// Resolve hostname — check all DNS records to prevent DNS rebinding.
		$records = dns_get_record( $host, DNS_A | DNS_AAAA );

		if ( false === $records || empty( $records ) ) {
			// Fallback to gethostbyname if dns_get_record fails.
			$ip = gethostbyname( $host );

			// gethostbyname returns the hostname if resolution fails.
			if ( $ip === $host ) {
				return false;
			}

			return $this->is_ip_private( $ip );
		}

		// If any resolved IP is private, block the request.
		foreach ( $records as $record ) {
			$ip = $record['ip'] ?? $record['ipv6'] ?? null;

			if ( null !== $ip && $this->is_ip_private( $ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP address is in a private range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip The IP address to check.
	 * @return bool True if the IP is private.
	 */
	private function is_ip_private( string $ip ): bool {
		// IPv6 loopback.
		if ( '::1' === $ip ) {
			return true;
		}

		return ! filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
		);
	}

	/**
	 * Check if a domain is known to block automated checks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host The hostname to check.
	 * @return bool True if the domain blocks bots.
	 */
	private function is_bot_blocking_domain( string $host ): bool {
		$host = strtolower( $host );

		foreach ( self::BOT_BLOCKING_DOMAINS as $blocked_domain ) {
			if ( $host === $blocked_domain || str_ends_with( $host, '.' . $blocked_domain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cache a URL check result in a transient.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_key The transient key.
	 * @param array  $result    The result to cache.
	 * @return void
	 */
	private function cache_result( string $cache_key, array $result ): void {
		/**
		 * Filters the cache expiry time for link check results.
		 *
		 * @since 1.0.0
		 *
		 * @param int $expiry Cache expiry in seconds. Default 24 hours.
		 */
		$expiry = (int) apply_filters( 'scalyn_qa_link_cache_expiry', self::DEFAULT_CACHE_EXPIRY );

		set_transient( $cache_key, $result, $expiry );
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * Supports Elementor page builder content when available.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered HTML content.
	 */
	private function get_rendered_content( int $post_id ): string {
		// Check for Elementor-built content.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( $elementor && method_exists( $elementor->db, 'is_built_with_elementor' ) && $elementor->db->is_built_with_elementor( $post_id ) ) {
				$elementor_content = $elementor->frontend->get_builder_content( $post_id, true );
				if ( '' !== $elementor_content ) {
					return $elementor_content;
				}
			}
		}

		$raw_content = get_post_field( 'post_content', $post_id );

		if ( is_wp_error( $raw_content ) || ! is_string( $raw_content ) ) {
			return '';
		}

		/** This filter is documented in wp-includes/post-template.php */
		return (string) apply_filters( 'the_content', $raw_content );
	}
}
