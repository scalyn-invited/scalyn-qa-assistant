<?php
/**
 * AI Controller.
 *
 * REST endpoints for AI-powered meta generation, application, and testing.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\AI\AI_Manager;
use Scalyn\QA\AI\AI_Health_Monitor;
use Scalyn\QA\AI\AI_Provider_Registry;
use Scalyn\QA\Integrations\SEO_Integration;

/**
 * Class AI_Controller
 *
 * Handles AI meta generation, application to SEO plugins,
 * connection testing, and draft retrieval.
 *
 * @since 1.0.0
 */
class AI_Controller extends REST_Controller {

	/**
	 * Valid generation types.
	 *
	 * @var string[]
	 */
	private const VALID_TYPES = array( 'both', 'title', 'description' );

	/**
	 * Post meta key for AI drafts.
	 *
	 * @var string
	 */
	private const META_DRAFTS = '_scalyn_qa_ai_drafts';

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
			'/ai/generate/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_meta' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
					'type'    => array(
						'default'           => 'both',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => in_array( $v, self::VALID_TYPES, true ),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/apply/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_meta' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
					'title'       => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => AI_Provider_Registry::has( (string) $v ),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/log',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ai_log' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/log',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_ai_log' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/drafts/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_drafts' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		// POST /ai/review/{post_id} — AI content review.
		register_rest_route(
			$this->namespace,
			'/ai/review/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'review_content' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/generate-alt/{post_id} — generate alt text for images.
		register_rest_route(
			$this->namespace,
			'/ai/generate-alt/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_alt_texts' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/generate-featured-image/{post_id} — generate featured image with DALL-E.
		register_rest_route(
			$this->namespace,
			'/ai/generate-featured-image/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_featured_image' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/generate-favicon — generate a site icon with DALL-E.
		register_rest_route(
			$this->namespace,
			'/ai/generate-favicon',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_favicon' ),
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
			),
		);

		// POST /ai/apply-featured-image/{post_id} — set an attachment as the featured image.
		register_rest_route(
			$this->namespace,
			'/ai/apply-featured-image/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_featured_image' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/titles-as-alt/{post_id} — use image titles as alt text.
		register_rest_route(
			$this->namespace,
			'/ai/titles-as-alt/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'use_titles_as_alt' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/apply-alt/{post_id} — apply alt text to an image.
		register_rest_route(
			$this->namespace,
			'/ai/apply-alt/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_alt_text' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/review/{post_id}/recheck — check if existing issues are fixed.
		register_rest_route(
			$this->namespace,
			'/ai/review/(?P<post_id>\d+)/recheck',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'recheck_review_issues' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/review/{post_id}/update — update review issue statuses.
		register_rest_route(
			$this->namespace,
			'/ai/review/(?P<post_id>\d+)/update',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_review_issues' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/generate-keywords/{post_id} — AI focus keyword suggestions.
		register_rest_route(
			$this->namespace,
			'/ai/generate-keywords/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_keywords' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		// POST /ai/apply-keyword/{post_id} — write focus keyword to SEO plugin.
		register_rest_route(
			$this->namespace,
			'/ai/apply-keyword/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_keyword' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);
	}

	/**
	 * Generate AI focus keyword suggestions for a post.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_keywords( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$ai_manager = new AI_Manager();
		if ( ! $ai_manager->is_enabled() ) {
			return $this->error( 'ai_not_enabled', __( 'AI features are not enabled.', 'scalyn-qa-assistant' ), 400 );
		}

		// Detect if pro SEO plugin is active.
		$has_pro = defined( 'RANK_MATH_PRO_VERSION' )
			|| defined( 'WPSEO_PREMIUM_FILE' )
			|| defined( 'AIOSEO_PRO_VERSION' )
			|| defined( 'SEOPRESS_PRO_VERSION' );

		$title   = get_the_title( $post );
		$url     = get_permalink( $post_id );
		$slug    = get_post_field( 'post_name', $post_id );
		$content = wp_strip_all_tags( $post->post_content );
		$words   = explode( ' ', $content );
		$content = implode( ' ', array_slice( $words, 0, 1500 ) );

		// Get meta title and description from SEO plugins.
		$meta_title = get_post_meta( $post_id, 'rank_math_title', true )
			?: get_post_meta( $post_id, '_yoast_wpseo_title', true )
			?: get_post_meta( $post_id, '_aioseo_title', true )
			?: $title;

		$meta_desc = get_post_meta( $post_id, 'rank_math_description', true )
			?: get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
			?: get_post_meta( $post_id, '_aioseo_description', true )
			?: '';

		// Get H1 from content, fall back to post title (most themes render it as H1).
		$h1_text = '';
		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/si', $post->post_content, $h1_match ) ) {
			$h1_text = wp_strip_all_tags( $h1_match[1] );
		}
		if ( '' === $h1_text ) {
			$h1_text = $title; // Theme likely renders post title as H1.
		}

		// Get first paragraph text.
		$first_para = '';
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/si', $post->post_content, $p_match ) ) {
			$first_para = wp_strip_all_tags( $p_match[1] );
		}

		$count = $has_pro ? 5 : 3;
		$count_label = $has_pro ? 'a PRIMARY focus keyword plus 4 secondary keywords (5 total)' : '3 focus keyword options';

		$prompt = <<<PROMPT
You are an SEO keyword expert. Analyze this page and discover the best focus keyword phrases that ALREADY EXIST in the content.

Page title: {$title}
Meta title: {$meta_title}
Meta description: {$meta_desc}
H1 heading: {$h1_text}
URL slug: {$slug}
First paragraph: {$first_para}
Content excerpt:
{$content}

CRITICAL RULES:
- Every keyword you suggest MUST be a phrase that already appears verbatim in at least one of the fields above (title, meta title, meta description, H1, first paragraph, URL slug, or content)
- Do NOT invent new keywords — only discover phrases that are already written in the page
- Prioritize phrases that appear in MULTIPLE locations (e.g. in both the title AND the content)
- Keywords should be 2-5 words long
- Rank by: how many locations contain the phrase, then by search relevance

Suggest {$count_label}.

PROMPT;

		if ( $has_pro ) {
			$prompt .= 'Return ONLY a JSON object: {"primary":"...","secondary":["...","...","...","..."]}';
		} else {
			$prompt .= 'Return ONLY a JSON object: {"keywords":["best keyword","second option","third option"]}';
		}

		try {
			$result = $ai_manager->generate_text( $prompt, 300 );
		} catch ( \RuntimeException $e ) {
			return $this->error( 'ai_rate_limit', $e->getMessage(), 429 );
		}

		if ( '' === $result['text'] ) {
			return $this->error( 'ai_failed', __( 'AI generation failed.', 'scalyn-qa-assistant' ), 500 );
		}

		$parsed = json_decode( $result['text'], true );
		if ( ! is_array( $parsed ) ) {
			if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $result['text'], $m ) ) {
				$parsed = json_decode( $m[1], true );
			}
		}

		if ( ! is_array( $parsed ) ) {
			return $this->error( 'ai_parse_failed', __( 'Could not parse AI response.', 'scalyn-qa-assistant' ), 500 );
		}

		$response_data = array(
			'has_pro'   => $has_pro,
			'primary'   => $parsed['primary'] ?? ( $parsed['keywords'][0] ?? '' ),
			'secondary' => $parsed['secondary'] ?? array(),
			'keywords'  => $parsed['keywords'] ?? array(),
			'provider'  => $result['provider'],
			'model'     => $result['model'],
		);

		// Persist to post meta so it survives page reloads.
		update_post_meta( $post_id, '_scalyn_qa_ai_keywords', $response_data );

		return $this->success( $response_data );
	}

	/**
	 * Apply a focus keyword to the active SEO plugin.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_keyword( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$params  = $request->get_json_params();
		$primary = sanitize_text_field( $params['primary'] ?? '' );
		$secondary = array_map( 'sanitize_text_field', (array) ( $params['secondary'] ?? array() ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission.', 'scalyn-qa-assistant' ), 403 );
		}

		if ( '' === $primary ) {
			return $this->error( 'missing_keyword', __( 'Primary keyword is required.', 'scalyn-qa-assistant' ), 400 );
		}

		$applied_to = '';

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$has_pro = defined( 'RANK_MATH_PRO_VERSION' );
			if ( $has_pro && ! empty( $secondary ) ) {
				// Pro: comma-separated (primary + up to 4 secondary).
				$all = array_merge( array( $primary ), array_slice( $secondary, 0, 4 ) );
				update_post_meta( $post_id, 'rank_math_focus_keyword', implode( ',', $all ) );
			} else {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $primary );
			}
			$applied_to = 'Rank Math';
		}

		// Yoast.
		elseif ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $primary );
			$has_pro = defined( 'WPSEO_PREMIUM_FILE' );
			if ( $has_pro && ! empty( $secondary ) ) {
				// Premium: JSON array of additional keyphrases.
				$additional = array_map( static fn( string $kw ): array => array( 'keyword' => $kw, 'score' => 0 ), array_slice( $secondary, 0, 4 ) );
				update_post_meta( $post_id, '_yoast_wpseo_focuskeywords', wp_json_encode( $additional ) );
			}
			$applied_to = 'Yoast SEO';
		}

		// AIOSEO.
		elseif ( defined( 'AIOSEO_VERSION' ) ) {
			$has_pro   = defined( 'AIOSEO_PRO_VERSION' );
			$keyphrases = array( 'focus' => array( 'keyphrase' => $primary, 'score' => 0, 'analysis' => array() ) );
			if ( $has_pro && ! empty( $secondary ) ) {
				$keyphrases['additional'] = array_map(
					static fn( string $kw ): array => array( 'keyphrase' => $kw, 'score' => 0, 'analysis' => array() ),
					array_slice( $secondary, 0, 4 ),
				);
			}
			update_post_meta( $post_id, '_aioseo_keyphrases', wp_json_encode( $keyphrases ) );
			$applied_to = 'AIOSEO';
		}

		// SEOPress.
		elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			$has_pro = defined( 'SEOPRESS_PRO_VERSION' );
			if ( $has_pro && ! empty( $secondary ) ) {
				$all = array_merge( array( $primary ), array_slice( $secondary, 0, 4 ) );
				update_post_meta( $post_id, '_seopress_analysis_target_kw', implode( ',', $all ) );
			} else {
				update_post_meta( $post_id, '_seopress_analysis_target_kw', $primary );
			}
			$applied_to = 'SEOPress';
		}

		if ( '' === $applied_to ) {
			return $this->error( 'no_seo_plugin', __( 'No supported SEO plugin detected.', 'scalyn-qa-assistant' ), 400 );
		}

		$count = 1 + count( $secondary );

		return $this->success( array(
			'applied'    => true,
			'plugin'     => $applied_to,
			'primary'    => $primary,
			'secondary'  => $secondary,
			'message'    => sprintf(
				/* translators: 1: keyword count, 2: SEO plugin name */
				__( '%1$d keyword(s) applied to %2$s.', 'scalyn-qa-assistant' ),
				$count,
				$applied_to,
			),
		) );
	}

	/**
	 * Review content for spelling, grammar, and readability using AI.
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function review_content( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error(
				'ai_not_enabled',
				__( 'AI features are not enabled. Configure an AI provider in Settings.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		try {
			$result = $ai_manager->review_content( $post_id );
		} catch ( \Throwable $e ) {
			return $this->error( 'review_failed', $e->getMessage(), 500 );
		}

		if ( empty( $result['summary'] ) && empty( $result['issues'] ) ) {
			return $this->error(
				'review_empty',
				__( 'AI content review returned no results. Try again or check your AI provider settings.', 'scalyn-qa-assistant' ),
				500,
			);
		}

		return $this->success( $result );
	}

	/**
	 * Generate AI alt text for images missing it.
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_alt_texts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error( 'ai_not_enabled', __( 'AI features are not enabled.', 'scalyn-qa-assistant' ), 400 );
		}

		// Allow extra time for processing many images.
		set_time_limit( 600 );

		try {
			$result = $ai_manager->generate_alt_texts( $post_id );
		} catch ( \Throwable $e ) {
			return $this->error( 'alt_text_failed', $e->getMessage(), 500 );
		}

		return $this->success( $result );
	}

	/**
	 * Generate a featured image using DALL-E and set it on the post.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_featured_image( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error( 'ai_not_enabled', __( 'AI features are not enabled.', 'scalyn-qa-assistant' ), 400 );
		}

		try {
			$result = $ai_manager->generate_featured_image( $post_id );
		} catch ( \Throwable $e ) {
			return $this->error( 'image_generation_failed', $e->getMessage(), 500 );
		}

		return $this->success( $result );
	}

	/**
	 * Generate a favicon/site icon using AI.
	 *
	 * @since 1.4.4
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_favicon( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params        = $request->get_json_params();
		$apply         = ! empty( $params['apply'] );
		$attachment_id = absint( $params['attachment_id'] ?? 0 );

		// If applying an already-generated image, just set it as site icon.
		if ( $apply && $attachment_id > 0 ) {
			update_option( 'site_icon', $attachment_id );
			return $this->success( array(
				'applied'       => true,
				'attachment_id' => $attachment_id,
				'message'       => __( 'Site icon updated.', 'scalyn-qa-assistant' ),
			) );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error( 'ai_not_enabled', __( 'AI features are not enabled.', 'scalyn-qa-assistant' ), 400 );
		}

		try {
			$result = $ai_manager->generate_favicon( false );
		} catch ( \Throwable $e ) {
			return $this->error( 'favicon_generation_failed', $e->getMessage(), 500 );
		}

		return $this->success( $result );
	}

	/**
	 * Apply a generated image as the post's featured image.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_featured_image( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$params  = $request->get_json_params();
		$attachment_id = absint( $params['attachment_id'] ?? 0 );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission.', 'scalyn-qa-assistant' ), 403 );
		}

		if ( 0 === $attachment_id ) {
			return $this->error( 'missing_attachment', __( 'Attachment ID is required.', 'scalyn-qa-assistant' ), 400 );
		}

		set_post_thumbnail( $post_id, $attachment_id );

		return $this->success( array(
			'applied'       => true,
			'attachment_id' => $attachment_id,
			'message'       => __( 'Featured image set successfully.', 'scalyn-qa-assistant' ),
		) );
	}

	/**
	 * Apply AI-generated alt text to an image attachment.
	 *
	 * Use image titles (attachment post_title) as alt text for images missing alt.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function use_titles_as_alt( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$params  = $request->get_json_params();
		$preview = ! empty( $params['preview'] );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		// Get rendered content (Elementor-aware).
		$content = '';
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( $elementor && method_exists( $elementor->db, 'is_built_with_elementor' ) && $elementor->db->is_built_with_elementor( $post_id ) ) {
				$content = $elementor->frontend->get_builder_content( $post_id, true );
			}
		}
		if ( '' === $content ) {
			$content = (string) apply_filters( 'the_content', $post->post_content );
		}
		$parser  = new \Scalyn\QA\Analyzers\HTML_Parser( $content );
		$images  = $parser->get_images();

		$results  = array();
		$updated  = $post->post_content;

		foreach ( $images as $image ) {
			if ( $image['has_alt'] || empty( $image['src'] ) ) {
				continue;
			}

			$src           = $image['src'];
			$attachment_id = attachment_url_to_postid( $src );

			// Try partial match for resized images.
			if ( 0 === $attachment_id ) {
				$upload_dir = wp_get_upload_dir();
				$relative   = str_replace( $upload_dir['baseurl'] . '/', '', $src );
				$base       = preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $relative );
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$attachment_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
						'%' . $wpdb->esc_like( $base ) . '%',
					),
				);
			}

			if ( 0 === $attachment_id ) {
				$results[] = array( 'src' => $src, 'error' => 'Attachment not found.' );
				continue;
			}

			$title = get_the_title( $attachment_id );
			if ( '' === $title ) {
				$title = pathinfo( basename( get_attached_file( $attachment_id ) ?: $src ), PATHINFO_FILENAME );
				$title = str_replace( array( '-', '_' ), ' ', $title );
				$title = ucfirst( $title );
			}

			$alt_text = sanitize_text_field( $title );

			// In preview mode, just return the data without applying.
			if ( $preview ) {
				$results[] = array(
					'src'           => $src,
					'alt_text'      => $alt_text,
					'attachment_id' => $attachment_id,
				);
				continue;
			}

			// Apply: save to attachment meta.
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

			// Update alt in post content HTML.
			$escaped_src = preg_quote( $src, '/' );
			$safe_alt    = esc_attr( $alt_text );

			$new_content = preg_replace(
				'/(<img[^>]*src=["\']' . $escaped_src . '["\'][^>]*?)alt=["\'][^"\']*["\']([^>]*?>)/i',
				'$1alt="' . $safe_alt . '"$2',
				$updated,
			);

			if ( $new_content === $updated ) {
				$new_content = preg_replace(
					'/(<img[^>]*src=["\']' . $escaped_src . '["\'][^>]*?)(\s*\/?>)/i',
					'$1 alt="' . $safe_alt . '"$2',
					$updated,
				);
			}

			$updated = $new_content;

			$results[] = array(
				'src'           => $src,
				'alt_text'      => $alt_text,
				'attachment_id' => $attachment_id,
			);
		}

		if ( ! $preview && $updated !== $post->post_content ) {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $updated,
			) );
		}

		// Clear Elementor cache so rescan picks up updated alt text.
		if ( ! $preview && class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		$applied_count = count( array_filter( $results, static fn( $r ) => ! isset( $r['error'] ) ) );

		return $this->success( array(
			'results' => $results,
			'applied' => $preview ? 0 : $applied_count,
			'preview' => $preview,
			'message' => $preview
				? sprintf( __( 'Found %d image titles to use as alt text.', 'scalyn-qa-assistant' ), $applied_count )
				: sprintf( __( 'Applied titles as alt text to %d images.', 'scalyn-qa-assistant' ), $applied_count ),
		) );
	}

	/**
	 * Apply AI-generated alt text to an image attachment.
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_alt_text( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id  = absint( $request->get_param( 'post_id' ) );
		$params   = $request->get_json_params();
		$src      = $params['src'] ?? '';
		$alt_text = sanitize_text_field( $params['alt_text'] ?? '' );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission.', 'scalyn-qa-assistant' ), 403 );
		}

		if ( empty( $src ) || empty( $alt_text ) ) {
			return $this->error( 'missing_params', __( 'Image src and alt_text are required.', 'scalyn-qa-assistant' ), 400 );
		}

		// Find the attachment ID by URL.
		$attachment_id = attachment_url_to_postid( $src );

		if ( 0 === $attachment_id ) {
			// Try with the upload dir stripped — handle relative or resized URLs.
			$upload_dir = wp_get_upload_dir();
			$relative   = str_replace( $upload_dir['baseurl'] . '/', '', $src );
			// Try finding by partial match in guid.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$attachment_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
					'%' . $wpdb->esc_like( $relative ) . '%',
				),
			);
		}

		// Save to attachment meta (Media Library).
		if ( $attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Also update alt attribute in post content HTML + Gutenberg block JSON.
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';

		if ( ! empty( $content ) ) {
			$escaped_src  = preg_quote( $src, '/' );
			$safe_alt     = esc_attr( $alt_text );
			$updated      = $content;

			// 1. Update alt="" in the <img> tag.
			$updated = preg_replace(
				'/(<img[^>]*src=["\']' . $escaped_src . '["\'][^>]*?)alt=["\'][^"\']*["\']([^>]*?>)/i',
				'$1alt="' . $safe_alt . '"$2',
				$updated,
			);

			if ( $updated === $content ) {
				// No existing alt — add one.
				$updated = preg_replace(
					'/(<img[^>]*src=["\']' . $escaped_src . '["\'][^>]*?)(\s*\/?>)/i',
					'$1 alt="' . $safe_alt . '"$2',
					$updated,
				);
			}

			// 2. Update Gutenberg block comment JSON to include alt.
			if ( $attachment_id > 0 ) {
				$updated = preg_replace_callback(
					'/(<!-- wp:image \{[^}]*"id":' . $attachment_id . '[^}]*)(}\s*-->)/i',
					function ( $matches ) use ( $alt_text ) {
						$json_part = $matches[1];
						// Remove existing alt key if present.
						$json_part = preg_replace( '/"alt":"[^"]*",?/', '', $json_part );
						// Add alt before closing brace.
						$json_part = rtrim( $json_part, ',' ) . ',"alt":"' . esc_attr( $alt_text ) . '"';
						return $json_part . $matches[2];
					},
					$updated,
				);
			}

			if ( $updated !== $content ) {
				wp_update_post( array(
					'ID'           => $post_id,
					'post_content' => $updated,
				) );
			}
		}

		// Clear Elementor cache so rescan picks up updated alt text.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return $this->success( array(
			'applied'       => true,
			'attachment_id' => $attachment_id,
			'alt_text'      => $alt_text,
		) );
	}

	/**
	 * Update review issue statuses (resolved/ignored).
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	/**
	 * Recheck existing review issues against the current post content.
	 *
	 * Auto-resolves issues where the problematic text has been fixed.
	 *
	 * @since 1.3.0
	 */
	public function recheck_review_issues( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission.', 'scalyn-qa-assistant' ), 403 );
		}

		$saved = get_post_meta( $post_id, '_scalyn_qa_content_review', true );

		if ( ! is_array( $saved ) || empty( $saved['issues'] ) ) {
			return $this->error( 'no_review', __( 'No review data found. Run "Regenerate with AI" first.', 'scalyn-qa-assistant' ), 404 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		// Get current content + meta for checking.
		$content   = wp_strip_all_tags( $post->post_content );
		$title     = get_the_title( $post_id );
		$meta_desc = get_post_meta( $post_id, 'rank_math_description', true )
			?: get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
			?: get_post_meta( $post_id, '_aioseo_description', true )
			?: '';

		$full_text    = mb_strtolower( $title . ' ' . $content . ' ' . $meta_desc );
		$resolved     = 0;
		$still_active = 0;

		foreach ( $saved['issues'] as &$issue ) {
			// Skip already resolved/ignored issues.
			if ( isset( $issue['status'] ) && in_array( $issue['status'], array( 'resolved', 'ignored' ), true ) ) {
				continue;
			}

			$is_fixed = false;

			// Check if the problematic text still exists in the content.
			$problem_text = mb_strtolower( trim( $issue['text'] ?? '' ) );

			if ( '' !== $problem_text && ! str_contains( $full_text, $problem_text ) ) {
				// The problematic text is gone — likely fixed.
				$is_fixed = true;
			}

			// Also check if the suggestion was applied (the suggested text now exists).
			if ( ! $is_fixed && ! empty( $issue['suggestion'] ) ) {
				$suggestion = mb_strtolower( trim( $issue['suggestion'] ) );
				if ( '' !== $suggestion && str_contains( $full_text, $suggestion ) ) {
					$is_fixed = true;
				}
			}

			if ( $is_fixed ) {
				$issue['status'] = 'resolved';
				++$resolved;
			} else {
				++$still_active;
			}
		}
		unset( $issue );

		// Recalculate score based on resolved issues.
		$total_issues = count( $saved['issues'] );
		$resolved_and_ignored = 0;
		foreach ( $saved['issues'] as $iss ) {
			if ( isset( $iss['status'] ) && in_array( $iss['status'], array( 'resolved', 'ignored' ), true ) ) {
				++$resolved_and_ignored;
			}
		}
		$new_score = $total_issues > 0
			? min( 100, (int) round( ( $resolved_and_ignored / $total_issues ) * 100 ) )
			: 100;

		// If all issues resolved, score is 100. Otherwise scale between original and 100.
		if ( $still_active > 0 ) {
			$original_score = (int) ( $saved['score'] ?? 0 );
			$fix_ratio      = $total_issues > 0 ? $resolved_and_ignored / $total_issues : 0;
			$new_score      = min( 100, (int) round( $original_score + ( ( 100 - $original_score ) * $fix_ratio ) ) );
		}

		$saved['score'] = $new_score;

		// Update summary to reflect current state.
		if ( $still_active === 0 ) {
			$saved['summary'] = __( 'All issues have been resolved. Content quality is excellent.', 'scalyn-qa-assistant' );
		}

		// Save updated issues and score.
		update_post_meta( $post_id, '_scalyn_qa_content_review', $saved );

		return $this->success( array(
			'resolved'     => $resolved,
			'still_active' => $still_active,
			'total'        => $total_issues,
			'issues'       => $saved['issues'],
			'summary'      => $saved['summary'],
			'score'        => $new_score,
		) );
	}

	public function update_review_issues( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$saved = get_post_meta( $post_id, '_scalyn_qa_content_review', true );

		if ( ! is_array( $saved ) ) {
			return $this->error( 'no_review', __( 'No review data found for this post.', 'scalyn-qa-assistant' ), 404 );
		}

		$params = $request->get_json_params();
		$issues = $params['issues'] ?? null;

		if ( ! is_array( $issues ) ) {
			return $this->error( 'invalid_issues', __( 'Issues array is required.', 'scalyn-qa-assistant' ), 400 );
		}

		$saved['issues'] = $issues;
		update_post_meta( $post_id, '_scalyn_qa_content_review', $saved );

		return $this->success( array( 'updated' => true ) );
	}

	/**
	 * Generate AI meta title and/or description for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_meta( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$type    = sanitize_text_field( $request->get_param( 'type' ) ?: 'both' );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error(
				'ai_not_enabled',
				__( 'AI features are not enabled. Configure an AI provider in settings.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		try {
			$result = $ai_manager->generate_meta( $post_id, $type );
		} catch ( \RuntimeException $e ) {
			return $this->error(
				'ai_rate_limit_exceeded',
				$e->getMessage(),
				429,
			);
		}

		if ( empty( $result['title'] ) && empty( $result['description'] ) ) {
			return $this->error(
				'ai_generation_failed',
				__( 'AI generation failed. Check your API key and provider configuration.', 'scalyn-qa-assistant' ),
				500,
			);
		}

		return $this->success(
			array(
				'title'       => $result['title'],
				'description' => $result['description'],
				'provider'    => $result['provider'],
				'model'       => $result['model'],
			),
			201,
		);
	}

	/**
	 * Apply meta title and/or description to a post via the active SEO plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_meta( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id     = absint( $request->get_param( 'post_id' ) );
		$title       = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$description = sanitize_text_field( $request->get_param( 'description' ) ?? '' );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		if ( '' === $title && '' === $description ) {
			return $this->error(
				'no_meta_provided',
				__( 'At least a title or description must be provided.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$integration = SEO_Integration::detect();

		if ( null === $integration ) {
			return $this->error(
				'no_seo_plugin',
				__( 'No supported SEO plugin detected. Install Rank Math, Yoast SEO, or All in One SEO.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		$applied = array();

		if ( '' !== $title ) {
			$integration->set_meta_title( $post_id, $title );
			$applied[] = 'title';
		}

		if ( '' !== $description ) {
			$integration->set_meta_description( $post_id, $description );
			$applied[] = 'description';
		}

		return $this->success(
			array(
				'applied'     => $applied,
				'plugin_name' => $integration->get_plugin_name(),
				'plugin_slug' => $integration->get_plugin_slug(),
				'post_id'     => $post_id,
			),
		);
	}

	/**
	 * Test an AI provider connection.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_text_field( $request->get_param( 'provider' ) );

		$ai_manager = new AI_Manager();
		$result     = $ai_manager->test_connection( $provider );

		return $this->success(
			array(
				'provider' => $provider,
				'success'  => $result['success'],
				'message'  => $result['message'],
			),
		);
	}

	/**
	 * GET /ai/log — retrieve AI usage log.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_ai_log( \WP_REST_Request $request ): \WP_REST_Response {
		$ai_manager = new AI_Manager();
		$log        = $ai_manager->get_log( 30 );

		return $this->success(
			array(
				'entries' => $log,
				'count'   => count( $log ),
			),
		);
	}

	/**
	 * DELETE /ai/log — clear AI usage log.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function clear_ai_log( \WP_REST_Request $request ): \WP_REST_Response {
		delete_option( 'scalyn_qa_ai_log' );

		return $this->success(
			array(
				'cleared' => true,
				'message' => __( 'AI usage log cleared.', 'scalyn-qa-assistant' ),
			),
		);
	}

	/**
	 * Get saved AI drafts for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_drafts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$drafts = get_post_meta( $post_id, self::META_DRAFTS, true );
		$drafts = is_array( $drafts ) ? $drafts : array();

		// Sanitize each draft entry.
		$sanitized = array_map(
			static fn( array $draft ): array => array(
				'title'       => sanitize_text_field( $draft['title'] ?? '' ),
				'description' => sanitize_text_field( $draft['description'] ?? '' ),
				'provider'    => sanitize_text_field( $draft['provider'] ?? '' ),
				'model'       => sanitize_text_field( $draft['model'] ?? '' ),
				'created_at'  => sanitize_text_field( $draft['created_at'] ?? '' ),
			),
			array_filter( $drafts, 'is_array' ),
		);

		return $this->success(
			array(
				'post_id' => $post_id,
				'drafts'  => array_values( $sanitized ),
			),
		);
	}

	/**
	 * GET /ai/health — retrieve health and usage stats for all registered providers.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_health( \WP_REST_Request $request ): \WP_REST_Response {
		$ai_manager = new AI_Manager();
		$config     = $ai_manager->get_config();
		$providers  = AI_Provider_Registry::get_all();
		$result     = [];

		foreach ( $providers as $slug => $registration ) {
			$health          = AI_Health_Monitor::get_health( $slug );
			$provider_config = $config['providers'][ $slug ] ?? [];
			$has_api_key     = ! empty( $provider_config['api_key'] );

			$result[] = [
				'slug'              => $slug,
				'name'              => $registration['name'],
				'category'          => $registration['category'],
				'is_configured'     => $has_api_key,
				'status'            => $health['status'],
				'last_success'      => $health['last_success'],
				'last_failure'      => $health['last_failure'],
				'last_error'        => $health['last_error'],
				'total_requests'    => $health['total_requests'],
				'success_rate'      => AI_Health_Monitor::get_success_rate( $slug ),
				'avg_response_time' => AI_Health_Monitor::get_avg_response_time( $slug ),
			];
		}

		return $this->success(
			[
				'providers' => $result,
				'count'     => count( $result ),
			],
		);
	}
}
