<?php
/**
 * Launch Controller.
 *
 * REST endpoints for pre-launch QA checks.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Launch\Launch_Checker;
use Scalyn\QA\Models\Check_Item;

/**
 * Class Launch_Controller
 *
 * Handles running and retrieving pre-launch site checks.
 *
 * @since 1.0.0
 */
class Launch_Controller extends REST_Controller {

	/**
	 * Option key for stored launch results.
	 *
	 * @var string
	 */
	private const RESULTS_OPTION = 'scalyn_qa_launch_results';

	/**
	 * Option key for last launch scan timestamp.
	 *
	 * @var string
	 */
	private const LAST_SCAN_OPTION = 'scalyn_qa_launch_last_scan';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/launch',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_launch_results' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/launch/scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_launch_scan' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/launch/auto-fix',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'auto_fix_check' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/launch/llms_txt',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_llms_txt' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/launch/local_business',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_local_business' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/launch/ai_generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'ai_generate' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);
	}

	/**
	 * Get stored launch check results.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_launch_results( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$results_data = get_option( self::RESULTS_OPTION, array() );
		$last_scan    = get_option( self::LAST_SCAN_OPTION, '' );

		if ( empty( $results_data ) || ! is_array( $results_data ) ) {
			return $this->error(
				'launch_not_scanned',
				__( 'No launch check results found. Run a scan first.', 'scalyn-qa-assistant' ),
				404,
			);
		}

		// Calculate summary counts.
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
			'total'   => count( $results_data ),
		);

		foreach ( $results_data as $item_data ) {
			$status = $item_data['status'] ?? '';

			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			}
		}

		// Calculate a simple score percentage.
		$total = $summary['total'];
		$score = 0;

		if ( $total > 0 ) {
			$score = (int) round( ( $summary['pass'] / $total ) * 100 );
		}

		return $this->success(
			array(
				'checks'    => $results_data,
				'summary'   => $summary,
				'score'     => $score,
				'scanned_at' => is_numeric( $last_scan )
					? gmdate( 'c', (int) $last_scan )
					: '',
			),
		);
	}

	/**
	 * Run launch checks.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function run_launch_scan( \WP_REST_Request $request ): \WP_REST_Response {
		$checker = new Launch_Checker();
		$checks  = $checker->run_checks();

		$results_data = array_map(
			static fn( Check_Item $item ): array => $item->to_array(),
			$checks,
		);

		// Calculate summary counts.
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
			'total'   => count( $results_data ),
		);

		foreach ( $checks as $item ) {
			if ( isset( $summary[ $item->status ] ) ) {
				++$summary[ $item->status ];
			}
		}

		$score = 0;

		if ( $summary['total'] > 0 ) {
			$score = (int) round( ( $summary['pass'] / $summary['total'] ) * 100 );
		}

		return $this->success(
			array(
				'checks'     => $results_data,
				'summary'    => $summary,
				'score'      => $score,
				'scanned_at' => gmdate( 'c' ),
			),
		);
	}

	/**
	 * Auto-fix a specific launch check.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function auto_fix_check( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params   = $request->get_json_params();
		$check_id = isset( $params['check_id'] ) ? sanitize_key( $params['check_id'] ) : '';

		if ( '' === $check_id ) {
			return $this->error( 'missing_check_id', __( 'check_id is required.', 'scalyn-qa-assistant' ), 400 );
		}

		$content = isset( $params['content'] ) && is_string( $params['content'] ) ? $params['content'] : '';
		$checker = new Launch_Checker();
		$result  = $checker->auto_fix( $check_id, $content );

		if ( ! $result['success'] ) {
			return $this->error( 'auto_fix_failed', $result['message'], 400 );
		}


		return $this->success( $result );
	}

	/**
	 * GET /launch/llms-txt — read current llms.txt or generate default.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	/**
	 * GET /launch/local_business — read current local business schema data.
	 *
	 * @since 1.3.0
	 */
	public function get_local_business( \WP_REST_Request $request ): \WP_REST_Response {
		// Check our own stored JSON-LD first.
		$own = get_option( 'scalyn_qa_local_business_jsonld', array() );
		if ( ! empty( $own ) && is_array( $own ) ) {
			return $this->success( array(
				'source' => 'scalyn',
				'data'   => array(
					'type'        => $own['@type'] ?? 'LocalBusiness',
					'name'        => $own['name'] ?? '',
					'description' => $own['description'] ?? '',
					'phone'       => $own['telephone'] ?? '',
					'email'       => $own['email'] ?? '',
				),
			) );
		}

		// Return empty defaults for new configuration.
		return $this->success( array(
			'source' => 'none',
			'data'   => array(
				'type'        => 'LocalBusiness',
				'name'        => get_bloginfo( 'name' ),
				'description' => '',
				'phone'       => '',
				'email'       => get_option( 'admin_email', '' ),
			),
		) );
	}

	public function get_llms_txt( \WP_REST_Request $request ): \WP_REST_Response {
		$checker  = new Launch_Checker();
		$existing = $checker->read_llms_txt();

		return $this->success( array(
			'exists'  => null !== $existing,
			'content' => $existing ?? $checker->generate_llms_txt_default(),
		) );
	}

	/**
	 * POST /launch/ai_generate — generate launch content with AI.
	 *
	 * Supports types: tagline, privacy_policy, llms_txt.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ai_generate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ai_manager = new \Scalyn\QA\AI\AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error( 'ai_not_enabled', __( 'AI features are not enabled. Configure an AI provider in Settings.', 'scalyn-qa-assistant' ), 400 );
		}

		$params        = $request->get_json_params();
		$single_type   = isset( $params['type'] ) ? sanitize_key( $params['type'] ) : '';
		$valid_singles = array( 'taglines', 'privacy_policy', 'llms_txt', 'cornerstone', 'local_business', 'contact_page' );
		$is_single     = in_array( $single_type, $valid_singles, true );

		$prompt = $is_single
			? $this->build_launch_prompt_single( $single_type )
			: $this->build_launch_prompt_all();

		try {
			$result = $ai_manager->generate_text( $prompt, 3000 );
		} catch ( \RuntimeException $e ) {
			return $this->error( 'ai_rate_limit', $e->getMessage(), 429 );
		}

		if ( '' === $result['text'] ) {
			return $this->error( 'ai_failed', __( 'AI generation failed. Check your provider settings.', 'scalyn-qa-assistant' ), 500 );
		}

		// Parse JSON response.
		$parsed = json_decode( $result['text'], true );

		if ( ! is_array( $parsed ) ) {
			if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $result['text'], $m ) ) {
				$parsed = json_decode( $m[1], true );
			}
		}

		// For single-type, the AI might return just the value directly.
		if ( $is_single && ! is_array( $parsed ) ) {
			$parsed = array( $single_type => 'taglines' === $single_type
				? array_filter( array_map( 'trim', explode( "\n", $result['text'] ) ) )
				: $result['text'],
			);
		}

		if ( ! is_array( $parsed ) ) {
			return $this->error( 'ai_parse_failed', __( 'Could not parse AI response. Try again.', 'scalyn-qa-assistant' ), 500 );
		}

		// Merge into existing stored content (so other keys aren't wiped on single regenerate).
		$existing = get_option( 'scalyn_qa_launch_ai_content', array() );
		$existing = is_array( $existing ) ? $existing : array();

		$stored = array_merge( $existing, array(
			'provider'     => $result['provider'],
			'model'        => $result['model'],
			'generated_at' => gmdate( 'c' ),
		) );

		if ( $is_single ) {
			$stored[ $single_type ] = $parsed[ $single_type ] ?? $parsed;
		} else {
			$stored['taglines']       = $parsed['taglines'] ?? array();
			$stored['privacy_policy'] = $parsed['privacy_policy'] ?? '';
			$stored['llms_txt']       = $parsed['llms_txt'] ?? '';
			$stored['cornerstone']    = $parsed['cornerstone'] ?? array();
			$stored['contact_page']   = $parsed['contact_page'] ?? '';
			$stored['local_business'] = $parsed['local_business'] ?? array();
		}
		update_option( 'scalyn_qa_launch_ai_content', $stored, false );

		return $this->success( $stored );
	}

	/**
	 * Build a single prompt that generates all launch content at once.
	 *
	 * @return string
	 */
	private function build_launch_prompt_all(): string {
		$site_name   = get_bloginfo( 'name' );
		$site_url    = home_url( '/' );
		$admin_email = get_option( 'admin_email', '' );

		// Gather site context.
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'fields'         => 'ids',
		) );
		$page_list = array();
		foreach ( $pages as $page_id ) {
			$page_list[] = get_the_title( $page_id ) . ' — ' . get_permalink( $page_id );
		}

		// Detect data-collecting plugins/services.
		$data_services = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$data_services[] = 'WooCommerce (e-commerce, collects billing/shipping/payment data)';
		}
		if ( defined( 'WPCF7_VERSION' ) ) {
			$data_services[] = 'Contact Form 7 (contact forms, collects name/email/message)';
		}
		if ( class_exists( 'GFForms' ) ) {
			$data_services[] = 'Gravity Forms (forms, collects user-submitted data)';
		}
		if ( defined( 'JETPACK__VERSION' ) ) {
			$data_services[] = 'Jetpack (analytics, security, may set cookies)';
		}

		$homepage_html = ( new Launch_Checker() )->read_homepage_html_for_ai();
		if ( ! empty( $homepage_html ) && ( stripos( $homepage_html, 'gtag(' ) !== false || stripos( $homepage_html, 'G-' ) !== false ) ) {
			$data_services[] = 'Google Analytics (tracking cookies, visitor behavior data)';
		}
		if ( ! empty( $homepage_html ) && stripos( $homepage_html, 'GTM-' ) !== false ) {
			$data_services[] = 'Google Tag Manager (may load various tracking scripts)';
		}

		$page_list_str   = $this->format_list( $page_list );
		$services_str    = $this->format_list( $data_services ?: array( 'No specific data-collecting plugins detected — write a general policy' ) );

		return <<<PROMPT
You are a website launch expert. Generate 3 pieces of content for this WordPress website in a SINGLE JSON response.

Site name: {$site_name}
Site URL: {$site_url}
Contact email: {$admin_email}
Published pages:
{$page_list_str}
Active data-collecting plugins/services:
{$services_str}

Generate a JSON object with these 6 keys:

1. "taglines" — An array of 3 short, professional tagline options (each under 60 characters, relevant to what the site does, no quotes)

2. "privacy_policy" — A complete Privacy Policy in WordPress Gutenberg block HTML (<!-- wp:heading --> and <!-- wp:paragraph --> blocks). Include these sections EXACTLY ONCE in order: Who We Are, Data Collection, Cookies, Analytics, Data Retention, Your Rights, Contact Info. Do NOT repeat or duplicate any section. Tailor it to the detected services. Use the actual site name and email.

3. "llms_txt" — An llms.txt file with User-agent rules, Allow/Disallow (block /wp-admin/ and /wp-login.php), Attribution section with site URL, Contact section with email, and a Site section listing key pages.

4. "cornerstone" — An array of page titles (from the published pages list above) that should be marked as cornerstone/pillar content. Pick the 3-5 most important pages that represent the site's core topics. Only include pages that actually exist in the list.

5. "contact_page" — A Contact page in WordPress Gutenberg block HTML (<!-- wp:heading --> and <!-- wp:paragraph --> blocks). Write each section EXACTLY ONCE — do NOT repeat or duplicate any section. Include: heading, intro paragraph, email address, business hours, and a placeholder for contact form. Use the actual site name and email.

6. "local_business" — A JSON object with local business schema suggestions: {"type":"LocalBusiness or a more specific subtype","name":"business name","description":"1-2 sentence description","phone":"if detectable","email":"contact email"}. Infer the business type from the site name and pages. If the site is clearly not a local business, set type to "Organization".

Return ONLY valid JSON — no markdown, no code blocks, no preamble. Example structure:
{"taglines":["..."],"privacy_policy":"...","llms_txt":"...","cornerstone":["Page 1"],"contact_page":"<!-- wp:heading -->...","local_business":{"type":"LocalBusiness","name":"..."}}
PROMPT;
	}

	/**
	 * Build a prompt for regenerating a single content type.
	 *
	 * @param string $type One of: taglines, privacy_policy, llms_txt.
	 * @return string
	 */
	private function build_launch_prompt_single( string $type ): string {
		$site_name   = get_bloginfo( 'name' );
		$site_url    = home_url( '/' );
		$admin_email = get_option( 'admin_email', '' );

		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 20, 'fields' => 'ids' ) );
		$page_list = array();
		foreach ( $pages as $pid ) {
			$page_list[] = get_the_title( $pid ) . ' — ' . get_permalink( $pid );
		}

		if ( 'taglines' === $type ) {
			$pl = $this->format_list( $page_list );
			return <<<PROMPT
Generate 3 short, professional tagline options for this website. Site: {$site_name} ({$site_url}). Pages:
{$pl}

Each under 60 characters, relevant to the site. Return ONLY a JSON object: {"taglines":["...","...","..."]}
PROMPT;
		}

		if ( 'privacy_policy' === $type ) {
			$services = $this->detect_data_services();
			$sl       = $this->format_list( $services ?: array( 'General website' ) );
			return <<<PROMPT
Generate a complete Privacy Policy for: {$site_name} ({$site_url}), email: {$admin_email}. Services:
{$sl}

IMPORTANT: Write the policy as clean, well-structured HTML using WordPress Gutenberg block format. Each section should appear EXACTLY ONCE. Do NOT repeat or duplicate any section. The output must be a single coherent document.

Include these sections in order: Who We Are, Data Collection, Cookies, Analytics, Data Retention, Your Rights, Contact Information.

Return ONLY a JSON object: {"privacy_policy":"<!-- wp:heading --><h2>Who We Are</h2><!-- /wp:heading --><!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->..."}
PROMPT;
		}

		if ( 'contact_page' === $type ) {
			return <<<PROMPT
Generate a complete Contact page for: {$site_name} ({$site_url}), email: {$admin_email}.

IMPORTANT: Use WordPress Gutenberg block HTML (<!-- wp:heading --> and <!-- wp:paragraph --> blocks). Write each section EXACTLY ONCE — do NOT repeat or duplicate any content. The output must be a single coherent page.

Include in order: Contact Us heading, intro paragraph, email address, business hours, and a placeholder note for a contact form plugin.

Return ONLY a JSON object: {"contact_page":"<!-- wp:heading --><h2>Contact Us</h2><!-- /wp:heading --><!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->..."}
PROMPT;
		}

		if ( 'cornerstone' === $type ) {
			$pl = $this->format_list( $page_list );
			return <<<PROMPT
Analyze these pages and suggest which 3-5 should be cornerstone/pillar content for: {$site_name} ({$site_url}).
Pages:
{$pl}

Pick the most important pages that represent core topics. Only include pages from the list above.
Return ONLY a JSON object: {"cornerstone":["Page Title 1","Page Title 2","Page Title 3"]}
PROMPT;
		}

		if ( 'local_business' === $type ) {
			$pl = $this->format_list( $page_list );
			return <<<PROMPT
Suggest Local Business schema for: {$site_name} ({$site_url}), email: {$admin_email}.
Pages:
{$pl}

Infer the business type from the site name and pages. If clearly not a local business, use "Organization".
Return ONLY a JSON object: {"local_business":{"type":"specific LocalBusiness subtype","name":"business name","description":"1-2 sentences","phone":"","email":"{$admin_email}"}}
PROMPT;
		}

		// llms_txt
		$pl = $this->format_list( $page_list );
		return <<<PROMPT
Generate an llms.txt for: {$site_name} ({$site_url}), email: {$admin_email}. Pages:
{$pl}

Include User-agent rules, block /wp-admin/ and /wp-login.php, Attribution, Contact, key pages.
Return ONLY a JSON object: {"llms_txt":"User-agent: *\n..."}
PROMPT;
	}

	/**
	 * Detect active data-collecting services for privacy policy prompts.
	 *
	 * @return string[]
	 */
	private function detect_data_services(): array {
		$services = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$services[] = 'WooCommerce (e-commerce)';
		}
		if ( defined( 'WPCF7_VERSION' ) ) {
			$services[] = 'Contact Form 7';
		}
		if ( class_exists( 'GFForms' ) ) {
			$services[] = 'Gravity Forms';
		}
		if ( defined( 'JETPACK__VERSION' ) ) {
			$services[] = 'Jetpack';
		}
		$html = ( new Launch_Checker() )->read_homepage_html_for_ai();
		if ( ! empty( $html ) && ( stripos( $html, 'gtag(' ) !== false || stripos( $html, 'G-' ) !== false ) ) {
			$services[] = 'Google Analytics';
		}
		if ( ! empty( $html ) && stripos( $html, 'GTM-' ) !== false ) {
			$services[] = 'Google Tag Manager';
		}
		return $services;
	}

	/**
	 * Format an array as a bullet list for prompts.
	 */
	private function format_list( array $items ): string {
		if ( empty( $items ) ) {
			return '  (none)';
		}
		return implode( "\n", array_map( static fn( string $item ): string => '  - ' . $item, $items ) );
	}
}
