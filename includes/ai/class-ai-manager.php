<?php
/**
 * AI Manager.
 *
 * Orchestrates AI providers for meta-tag generation, connection testing,
 * and configuration management.
 *
 * @package Scalyn\QA\AI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Manager
 *
 * Single entry-point for all AI-related operations. Manages provider
 * instantiation, primary/fallback logic, configuration persistence,
 * and API-key encryption.
 *
 * @since 1.0.0
 */
class AI_Manager {

	/**
	 * Option name for the AI configuration.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'scalyn_qa_ai_config';

	/**
	 * Option name for the AI usage log.
	 *
	 * @var string
	 */
	private const LOG_OPTION_KEY = 'scalyn_qa_ai_log';

	/**
	 * Encryption cipher.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * Loaded configuration array.
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * Loads configuration from wp_options.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->config = $this->load_config();
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Whether AI features are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return ! empty( $this->config['enabled'] );
	}

	/**
	 * Get the full configuration array.
	 *
	 * API keys are returned in their encrypted form.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Instantiate the primary AI provider, if configured.
	 *
	 * @since 1.0.0
	 *
	 * @return AI_Provider|null
	 */
	public function get_primary_provider(): ?AI_Provider {
		return $this->build_provider( 'primary' );
	}

	/**
	 * Instantiate the fallback AI provider, if configured.
	 *
	 * @since 1.0.0
	 *
	 * @return AI_Provider|null
	 */
	public function get_fallback_provider(): ?AI_Provider {
		return $this->build_provider( 'fallback' );
	}

	/**
	 * Get the priority chain: [primary, fallback, secondary_fallback].
	 *
	 * Returns a deduplicated ordered list of provider slugs to try.
	 *
	 * @since 1.4.0
	 *
	 * @return string[]
	 */
	public function get_priority_chain(): array {
		$chain = [];

		if ( ! empty( $this->config['primary'] ) ) {
			$chain[] = $this->config['primary'];
		}
		if ( ! empty( $this->config['fallback'] ) ) {
			$chain[] = $this->config['fallback'];
		}
		if ( ! empty( $this->config['secondary_fallback'] ) ) {
			$chain[] = $this->config['secondary_fallback'];
		}

		return array_values( array_unique( $chain ) );
	}

	/**
	 * Generate meta title and/or description for a post using AI.
	 *
	 * Tries the primary provider first; on failure falls back to the
	 * fallback provider. Saves drafts to post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The WordPress post ID.
	 * @param string $type    What to generate: 'title', 'description', or 'both'.
	 * @return array{title: string, description: string, provider: string, model: string}
	 */
	public function generate_meta( int $post_id, string $type = 'both' ): array {
		$empty_result = array(
			'title'       => '',
			'description' => '',
			'provider'    => '',
			'model'       => '',
		);

		if ( ! $this->is_enabled() ) {
			return $empty_result;
		}

		// Check rate limit before proceeding.
		if ( ! $this->check_rate_limit() ) {
			throw new \RuntimeException(
				__( 'Daily AI request limit reached. Please try again tomorrow or increase the limit in Advanced settings.', 'scalyn-qa-assistant' )
			);
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $empty_result;
		}

		$title   = get_the_title( $post );
		$url     = get_permalink( $post );
		$content = wp_strip_all_tags( $post->post_content );

		// Limit to ~2 000 words.
		$words          = explode( ' ', $content );
		$content        = implode( ' ', array_slice( $words, 0, 2000 ) );
		$content_length = strlen( $content );

		// Try each provider in the priority chain.
		$chain = $this->get_priority_chain();

		foreach ( $chain as $provider_key ) {
			$provider = $this->build_provider_by_key( $provider_key );

			if ( null === $provider ) {
				continue;
			}

			$start  = microtime( true );
			$result = $this->attempt_generation( $provider, $type, $title, (string) $url, $content );
			$elapsed = ( microtime( true ) - $start ) * 1000;

			$success = ! empty( $result['title'] ) || ! empty( $result['description'] );
			$this->log_request( $post_id, $result['provider'], $result['model'], $success, $content_length );

			if ( $success ) {
				AI_Health_Monitor::record_success( $provider_key, $elapsed );
				$this->save_drafts( $post_id, $result );
				return $result;
			}

			// Record failure so health stats stay accurate.
			AI_Health_Monitor::record_failure( $provider_key, 'Generation returned empty result.' );
		}

		return $empty_result;
	}

	/**
	 * Review content for spelling, grammar, capitalization, punctuation, and readability.
	 *
	 * @since 1.0.7
	 *
	 * @param int $post_id The post ID to review.
	 * @return array{summary: string, score: int, issues: array, provider: string, model: string}
	 */
	public function review_content( int $post_id ): array {
		$empty_result = array(
			'summary'  => '',
			'score'    => 0,
			'issues'   => array(),
			'provider' => '',
			'model'    => '',
		);

		if ( ! $this->is_enabled() ) {
			return $empty_result;
		}

		if ( ! $this->check_rate_limit() ) {
			throw new \RuntimeException(
				__( 'Daily AI request limit reached. Please try again tomorrow or increase the limit in Advanced settings.', 'scalyn-qa-assistant' )
			);
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $empty_result;
		}

		$title   = get_the_title( $post );
		$content = wp_strip_all_tags( $post->post_content );

		// Limit to ~2,000 words.
		$words          = explode( ' ', $content );
		$content        = implode( ' ', array_slice( $words, 0, 2000 ) );
		$content_length = strlen( $content );

		$chain     = $this->get_priority_chain();
		$last_error = '';

		foreach ( $chain as $provider_key ) {
			$provider = $this->build_provider_by_key( $provider_key );

			if ( null === $provider ) {
				$last_error = sprintf( 'Provider "%s" could not be built.', $provider_key );
				continue;
			}

			try {
				$prompt   = $provider->build_content_review_prompt( $title, $content );
				$start    = microtime( true );
				$response = $provider->generate( $prompt, 2000 );
				$elapsed  = ( microtime( true ) - $start ) * 1000;

				// Strip markdown code fences if present.
				$response = trim( $response );
				$response = preg_replace( '/^```(?:json)?\s*/i', '', $response );
				$response = preg_replace( '/\s*```$/', '', $response );

				// Try to extract JSON from the response if it contains extra text.
				$json_start = strpos( $response, '{' );
				$json_end   = strrpos( $response, '}' );
				if ( false !== $json_start && false !== $json_end ) {
					$response = substr( $response, $json_start, $json_end - $json_start + 1 );
				}

				$data = json_decode( $response, true );

				if ( ! is_array( $data ) ) {
					$last_error = 'AI returned invalid JSON. Raw: ' . mb_substr( $response, 0, 200 );
					AI_Health_Monitor::record_failure( $provider_key, 'Invalid JSON response for content review.' );
					continue;
				}

				$this->log_request( $post_id, $provider_key, $provider->get_slug(), true, $content_length );
				AI_Health_Monitor::record_success( $provider_key, $elapsed );

				$review_result = array(
					'summary'    => $data['summary'] ?? '',
					'score'      => max( 0, min( 100, (int) ( $data['score'] ?? 0 ) ) ),
					'issues'     => $data['issues'] ?? array(),
					'provider'   => $provider->get_name(),
					'model'      => $provider->get_slug(),
					'reviewed_at' => gmdate( 'c' ),
				);

				// Save to post meta so results persist across page loads.
				update_post_meta( $post_id, '_scalyn_qa_content_review', $review_result );

				return $review_result;
			} catch ( \Throwable $e ) {
				$last_error = $e->getMessage();
				AI_Health_Monitor::record_failure( $provider_key, $e->getMessage() );
				continue;
			}
		}

		if ( ! empty( $last_error ) ) {
			throw new \RuntimeException( $last_error );
		}

		return $empty_result;
	}

	/**
	 * Generate alt text for images missing it in a post.
	 *
	 * @since 1.0.7
	 *
	 * @param int $post_id The post ID.
	 * @return array{results: array, provider: string}
	 */
	public function generate_alt_texts( int $post_id ): array {
		if ( ! $this->is_enabled() ) {
			throw new \RuntimeException( __( 'AI features are not enabled.', 'scalyn-qa-assistant' ) );
		}

		if ( ! $this->check_rate_limit() ) {
			throw new \RuntimeException( __( 'Daily AI request limit reached.', 'scalyn-qa-assistant' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \RuntimeException( __( 'Post not found.', 'scalyn-qa-assistant' ) );
		}

		// Get rendered content (Elementor-aware) and find images missing alt text.
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
		$parser = new \Scalyn\QA\Analyzers\HTML_Parser( $content );
		$images  = $parser->get_images();

		$missing = array();
		foreach ( $images as $image ) {
			if ( ! $image['has_alt'] && ! empty( $image['src'] ) ) {
				$missing[] = $image['src'];
			}
		}

		if ( empty( $missing ) ) {
			return array( 'results' => array(), 'provider' => '' );
		}

		// Find a provider that supports vision (OpenAI).
		$chain       = $this->get_priority_chain();
		$provider    = null;
		$provider_key = '';

		foreach ( $chain as $key ) {
			$p = $this->build_provider_by_key( $key );
			if ( $p instanceof \Scalyn\QA\AI\OpenAI_Provider ) {
				$provider     = $p;
				$provider_key = $key;
				break;
			}
		}

		if ( null === $provider ) {
			throw new \RuntimeException( __( 'AI image analysis requires an OpenAI provider. Configure one in Settings → AI Providers.', 'scalyn-qa-assistant' ) );
		}

		$results = array();
		$site_url = home_url();

		foreach ( $missing as $src ) {
			// Make relative URLs absolute.
			$image_url = $src;
			if ( ! str_starts_with( $src, 'http' ) ) {
				$image_url = $site_url . '/' . ltrim( $src, '/' );
			}

			try {
				$alt_text = $provider->generate_alt_text( $image_url );
				$this->log_request( $post_id, $provider_key, 'gpt-4o-mini', true, strlen( $image_url ) );
				AI_Health_Monitor::record_success( $provider_key, 0 );

				$results[] = array(
					'src'      => $src,
					'alt_text' => $alt_text,
				);
			} catch ( \Throwable $e ) {
				AI_Health_Monitor::record_failure( $provider_key, $e->getMessage() );
				$results[] = array(
					'src'   => $src,
					'error' => $e->getMessage(),
				);
			}
		}

		// Save results to post meta for persistence.
		update_post_meta( $post_id, '_scalyn_qa_ai_alt_texts', array(
			'results'      => $results,
			'provider'     => $provider->get_name(),
			'generated_at' => gmdate( 'c' ),
		) );

		return array(
			'results'  => $results,
			'provider' => $provider->get_name(),
		);
	}

	/**
	 * Generate a featured image for a post using DALL-E and set it as the post thumbnail.
	 *
	 * @since 1.4.0
	 *
	 * @param int $post_id The post ID.
	 * @return array{attachment_id: int, url: string, provider: string}
	 */
	public function generate_featured_image( int $post_id ): array {
		if ( ! $this->is_enabled() ) {
			throw new \RuntimeException( __( 'AI features are not enabled.', 'scalyn-qa-assistant' ) );
		}

		if ( ! $this->check_rate_limit() ) {
			throw new \RuntimeException( __( 'Daily AI request limit reached.', 'scalyn-qa-assistant' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \RuntimeException( __( 'Post not found.', 'scalyn-qa-assistant' ) );
		}

		// Find an OpenAI provider (DALL-E requires OpenAI).
		$chain    = $this->get_priority_chain();
		$provider = null;

		foreach ( $chain as $key ) {
			$p = $this->build_provider_by_key( $key );
			if ( $p instanceof \Scalyn\QA\AI\OpenAI_Provider ) {
				$provider = $p;
				break;
			}
		}

		if ( null === $provider ) {
			throw new \RuntimeException( __( 'AI image generation requires an OpenAI provider. Configure one in Settings → AI Providers.', 'scalyn-qa-assistant' ) );
		}

		// Build a prompt from the post content.
		$title   = get_the_title( $post );
		$content = wp_strip_all_tags( $post->post_content );
		$words   = explode( ' ', $content );
		$excerpt = implode( ' ', array_slice( $words, 0, 200 ) );

		$prompt = "Create a professional, high-quality blog featured image for an article titled \"{$title}\". ";
		$prompt .= "The image should be visually appealing, modern, and relevant to the topic. ";
		$prompt .= "Content summary: {$excerpt}. ";
		$prompt .= "Style: Clean, professional, suitable for a business website or blog. ";
		$prompt .= "CRITICAL: The image must contain absolutely NO text, NO words, NO letters, NO numbers, NO watermarks, NO logos, NO captions, NO titles, NO labels, NO writing of any kind. Pure visual imagery only.";

		$start    = microtime( true );
		$b64_data = $provider->generate_image( $prompt );
		$elapsed  = ( microtime( true ) - $start ) * 1000;

		AI_Health_Monitor::record_success( 'openai', $elapsed );
		$this->log_request( $post_id, 'OpenAI', 'gpt-image-1', true, strlen( $prompt ) );

		// Save base64 image data to the media library.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$filename  = sanitize_file_name( sanitize_title( $title ) . '-ai-featured.png' );
		$tmp       = wp_tempnam( $filename );
		$decoded   = base64_decode( $b64_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			throw new \RuntimeException( __( 'Failed to decode generated image data.', 'scalyn-qa-assistant' ) );
		}

		file_put_contents( $tmp, $decoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new \RuntimeException( 'Failed to save image: ' . $attachment_id->get_error_message() );
		}

		// Track all AI-generated featured image IDs for this post.
		$history = get_post_meta( $post_id, '_scalyn_qa_ai_featured_images', true );
		$history = is_array( $history ) ? $history : array();
		$history[] = $attachment_id;
		update_post_meta( $post_id, '_scalyn_qa_ai_featured_images', array_unique( $history ) );

		return array(
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'provider'      => 'OpenAI (GPT Image)',
		);
	}

	/**
	 * Generate a favicon/site icon using AI image generation.
	 *
	 * Creates a 512x512 icon and optionally sets it as the site icon.
	 *
	 * @since 1.4.4
	 *
	 * @param bool $apply Whether to immediately set as site icon.
	 * @return array{attachment_id: int, url: string, provider: string}
	 */
	public function generate_favicon( bool $apply = false ): array {
		if ( ! $this->is_enabled() ) {
			throw new \RuntimeException( __( 'AI features are not enabled.', 'scalyn-qa-assistant' ) );
		}

		if ( ! $this->check_rate_limit() ) {
			throw new \RuntimeException( __( 'Daily AI request limit reached.', 'scalyn-qa-assistant' ) );
		}

		// Find an OpenAI provider (DALL-E requires OpenAI).
		$chain    = $this->get_priority_chain();
		$provider = null;

		foreach ( $chain as $key ) {
			$p = $this->build_provider_by_key( $key );
			if ( $p instanceof \Scalyn\QA\AI\OpenAI_Provider ) {
				$provider = $p;
				break;
			}
		}

		if ( null === $provider ) {
			throw new \RuntimeException( __( 'AI image generation requires an OpenAI provider. Configure one in Settings → AI Providers.', 'scalyn-qa-assistant' ) );
		}

		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );

		$prompt  = "Create a professional, minimal favicon/site icon for a website called \"{$site_name}\". ";
		if ( ! empty( $site_desc ) ) {
			$prompt .= "Description: {$site_desc}. ";
		}
		$prompt .= 'Style: Clean, modern, minimal icon design suitable for a browser tab favicon. ';
		$prompt .= 'Use bold, simple shapes with strong contrast. Should be recognizable at very small sizes (16x16px). ';
		$prompt .= 'CRITICAL: The image must contain absolutely NO text, NO words, NO letters, NO numbers. Pure iconic imagery only. ';
		$prompt .= 'Use a clean background. The icon should work as a small square favicon.';

		$start    = microtime( true );
		$b64_data = $provider->generate_image( $prompt, '1024x1024' );
		$elapsed  = ( microtime( true ) - $start ) * 1000;

		AI_Health_Monitor::record_success( 'openai', $elapsed );
		$this->log_request( 0, 'OpenAI', 'gpt-image-1', true, strlen( $prompt ) );

		// Save to media library.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$filename = sanitize_file_name( sanitize_title( $site_name ) . '-favicon-ai.png' );
		$tmp      = wp_tempnam( $filename );
		$decoded  = base64_decode( $b64_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			throw new \RuntimeException( __( 'Failed to decode generated image data.', 'scalyn-qa-assistant' ) );
		}

		file_put_contents( $tmp, $decoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, $site_name . ' Favicon' );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new \RuntimeException( 'Failed to save favicon: ' . $attachment_id->get_error_message() );
		}

		// Track AI-generated favicon history.
		$history   = get_option( 'scalyn_qa_ai_favicons', array() );
		$history   = is_array( $history ) ? $history : array();
		$history[] = $attachment_id;
		update_option( 'scalyn_qa_ai_favicons', array_unique( $history ), false );

		if ( $apply ) {
			update_option( 'site_icon', $attachment_id );
		}

		return array(
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'filename'      => basename( get_attached_file( $attachment_id ) ?: '' ),
			'applied'       => $apply,
			'provider'      => 'OpenAI (GPT Image)',
		);
	}

	/**
	 * Test a provider connection by its key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_key Provider slug (e.g. 'openai').
	 * @return array{success: bool, message: string}
	 */
	public function test_connection( string $provider_key ): array {
		$provider = $this->build_provider_by_key( $provider_key );

		if ( null === $provider ) {
			return array(
				'success' => false,
				'message' => __( 'Provider not configured or unknown.', 'scalyn-qa-assistant' ),
			);
		}

		try {
			return $provider->test();
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Save (persist) a new AI configuration.
	 *
	 * API keys are encrypted before storage.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config The configuration to save.
	 */
	public function save_config( array $config ): void {
		// Encrypt API keys for every known provider.
		foreach ( AI_Provider_Registry::get_all() as $key => $registration ) {
			if ( ! empty( $config['providers'][ $key ]['api_key'] ) ) {
				$raw = $config['providers'][ $key ]['api_key'];

				// Only encrypt if the key is not already encrypted.
				if ( ! str_starts_with( $raw, 'enc:' ) ) {
					$config['providers'][ $key ]['api_key'] = self::encrypt_key( $raw );
				}
			}
		}

		update_option( self::OPTION_KEY, $config, false );
		$this->config = $config;
	}

	/**
	 * Check whether the AI rate limit has been reached for today.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if within limit (request allowed), false if over limit.
	 */
	public function check_rate_limit(): bool {
		$settings  = get_option( 'scalyn_qa_settings', array() );
		$max_daily = isset( $settings['max_ai_requests_per_day'] ) ? (int) $settings['max_ai_requests_per_day'] : 0;

		// 0 = unlimited.
		if ( 0 === $max_daily ) {
			return true;
		}

		$log   = $this->get_log( 1 );
		$today = gmdate( 'Y-m-d' );
		$count = 0;

		foreach ( $log as $entry ) {
			if ( isset( $entry['date'] ) && str_starts_with( $entry['date'], $today ) ) {
				++$count;
			}
		}

		return $count < $max_daily;
	}

	/**
	 * Log an AI request for usage tracking.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id        The post ID the request was for.
	 * @param string $provider       The AI provider name.
	 * @param string $model          The model used.
	 * @param bool   $success        Whether the request succeeded.
	 * @param int    $content_length Length of content sent.
	 */
	public function log_request( int $post_id, string $provider, string $model, bool $success, int $content_length ): void {
		$log = get_option( self::LOG_OPTION_KEY, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$current_user = wp_get_current_user();

		$log[] = array(
			'user_id'        => get_current_user_id(),
			'user_name'      => $current_user->display_name,
			'provider'       => $provider,
			'model'          => $model,
			'post_id'        => $post_id,
			'date'           => gmdate( 'c' ),
			'success'        => $success,
			'content_length' => $content_length,
		);

		// Prune entries older than 30 days.
		$cutoff = gmdate( 'c', time() - ( 30 * DAY_IN_SECONDS ) );
		$log    = array_values(
			array_filter(
				$log,
				static fn( array $entry ): bool => isset( $entry['date'] ) && $entry['date'] >= $cutoff,
			)
		);

		update_option( self::LOG_OPTION_KEY, $log, false );
	}

	/**
	 * Get AI usage log entries from the last N days.
	 *
	 * @since 1.1.0
	 *
	 * @param int $days Number of days to retrieve (default 30).
	 * @return array Log entries.
	 */
	public function get_log( int $days = 30 ): array {
		$log = get_option( self::LOG_OPTION_KEY, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		$cutoff = gmdate( 'c', time() - ( $days * DAY_IN_SECONDS ) );

		return array_values(
			array_filter(
				$log,
				static fn( array $entry ): bool => isset( $entry['date'] ) && $entry['date'] >= $cutoff,
			)
		);
	}

	/**
	 * Get available models for a specific provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_key Provider slug.
	 * @return array<string, string> Model ID => Display name.
	 */
	public function get_available_models( string $provider_key ): array {
		$registration = AI_Provider_Registry::get( $provider_key );

		if ( null === $registration ) {
			return array();
		}

		$class = $registration['class'];

		/** @var AI_Provider $instance */
		$instance = new $class( '', '' );

		return $instance->get_models();
	}

	// ------------------------------------------------------------------
	// Encryption helpers (static)
	// ------------------------------------------------------------------

	/**
	 * Encrypt an API key using AES-256-CBC with the WordPress auth salt.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Plain-text API key.
	 * @return string Encrypted key prefixed with "enc:".
	 */
	public static function encrypt_key( string $key ): string {
		if ( '' === $key ) {
			return '';
		}

		$salt      = self::get_encryption_salt();
		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $iv_length ) {
			return $key; // Graceful fallback — store plain if OpenSSL unavailable.
		}

		$iv        = random_bytes( $iv_length );
		$encrypted = openssl_encrypt( $key, self::CIPHER, $salt, 0, $iv );

		if ( false === $encrypted ) {
			return $key;
		}

		// Prefix with "enc:" so we can detect already-encrypted values.
		// Store IV + ciphertext together, base64-encoded.
		return 'enc:' . base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt an API key encrypted with encrypt_key().
	 *
	 * @since 1.0.0
	 *
	 * @param string $encrypted The encrypted string (with "enc:" prefix).
	 * @return string Decrypted plain-text key, or original string on failure.
	 */
	public static function decrypt_key( string $encrypted ): string {
		if ( '' === $encrypted || ! str_starts_with( $encrypted, 'enc:' ) ) {
			return $encrypted;
		}

		$salt      = self::get_encryption_salt();
		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $iv_length ) {
			return $encrypted;
		}

		$raw = base64_decode( substr( $encrypted, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw || strlen( $raw ) <= $iv_length ) {
			return $encrypted;
		}

		$iv         = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );

		$decrypted = openssl_decrypt( $ciphertext, self::CIPHER, $salt, 0, $iv );

		return ( false !== $decrypted ) ? $decrypted : $encrypted;
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Load configuration from wp_options.
	 *
	 * @return array
	 */
	private function load_config(): array {
		$config = get_option( self::OPTION_KEY, array() );

		return is_array( $config ) ? $config : array();
	}

	/**
	 * Build a provider instance from the "primary" or "fallback" slot.
	 *
	 * @param string $slot 'primary' or 'fallback'.
	 * @return AI_Provider|null
	 */
	private function build_provider( string $slot ): ?AI_Provider {
		$provider_key = $this->config[ $slot ] ?? '';

		if ( empty( $provider_key ) || ! is_string( $provider_key ) ) {
			return null;
		}

		return $this->build_provider_by_key( $provider_key );
	}

	/**
	 * Instantiate a provider by its slug using stored configuration.
	 *
	 * @param string $provider_key Provider slug (e.g. 'openai').
	 * @return AI_Provider|null
	 */
	private function build_provider_by_key( string $provider_key ): ?AI_Provider {
		$registration = AI_Provider_Registry::get( $provider_key );

		if ( null === $registration ) {
			return null;
		}

		$provider_config = $this->config['providers'][ $provider_key ] ?? array();
		$encrypted_key   = $provider_config['api_key'] ?? '';
		$model           = $provider_config['model'] ?? '';

		if ( empty( $encrypted_key ) ) {
			return null;
		}

		$api_key  = self::decrypt_key( $encrypted_key );
		$class    = $registration['class'];
		$provider = new $class( $api_key, $model );

		// Configure custom endpoint if supported and configured.
		if ( $provider->supports_custom_endpoint() ) {
			$endpoint = $provider_config['endpoint'] ?? '';
			if ( ! empty( $endpoint ) ) {
				$provider->set_endpoint( $endpoint );
			}

			$custom_headers = $provider_config['custom_headers'] ?? [];
			if ( ! empty( $custom_headers ) && is_array( $custom_headers ) && method_exists( $provider, 'set_custom_headers' ) ) {
				$provider->set_custom_headers( $custom_headers );
			}
		}

		return $provider;
	}

	/**
	 * Attempt to generate meta content with a specific provider.
	 *
	 * @param AI_Provider $provider The provider instance.
	 * @param string      $type     'title', 'description', or 'both'.
	 * @param string      $title    Post title.
	 * @param string      $url      Post URL.
	 * @param string      $content  Post content excerpt.
	 * @return array{title: string, description: string, provider: string, model: string}
	 */
	private function attempt_generation(
		AI_Provider $provider,
		string $type,
		string $title,
		string $url,
		string $content,
	): array {
		$result = array(
			'title'       => '',
			'description' => '',
			'provider'    => $provider->get_name(),
			'model'       => $this->get_provider_model_label( $provider ),
		);

		try {
			$prompt   = $provider->build_meta_prompt( $type, $title, $url, $content );
			$response = $provider->generate( $prompt );

			if ( 'both' === $type ) {
				$parsed = json_decode( $response, true );

				if ( is_array( $parsed ) ) {
					$result['title']       = sanitize_text_field( $parsed['title'] ?? '' );
					$result['description'] = sanitize_text_field( $parsed['description'] ?? '' );
				}
			} elseif ( 'title' === $type ) {
				$result['title'] = sanitize_text_field( trim( $response ) );
			} else {
				$result['description'] = sanitize_text_field( trim( $response ) );
			}
		} catch ( \Throwable $e ) {
			// Silently fail — the caller will try the next provider in the chain.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Scalyn QA AI error (' . $provider->get_name() . '): ' . $e->getMessage() );
			\Scalyn\QA\Debug_Logger::ai_failure( $provider->get_name(), $e->getMessage(), [ 'post_id' => 0 ] );
			AI_Health_Monitor::record_failure( $provider->get_slug(), $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Generate free-form text using a custom prompt.
	 *
	 * Tries each provider in the priority chain until one succeeds.
	 *
	 * @since 1.3.0
	 *
	 * @param string $prompt     The full prompt to send.
	 * @param int    $max_tokens Maximum response tokens.
	 * @return array{text: string, provider: string, model: string}
	 */
	public function generate_text( string $prompt, int $max_tokens = 1000 ): array {
		$empty = array( 'text' => '', 'provider' => '', 'model' => '' );

		if ( ! $this->is_enabled() ) {
			return $empty;
		}

		if ( ! $this->check_rate_limit() ) {
			throw new \RuntimeException(
				__( 'Daily AI request limit reached. Please try again tomorrow or increase the limit in Advanced settings.', 'scalyn-qa-assistant' )
			);
		}

		$chain = $this->get_priority_chain();

		foreach ( $chain as $provider_key ) {
			$provider = $this->build_provider_by_key( $provider_key );

			if ( null === $provider ) {
				continue;
			}

			try {
				$start    = microtime( true );
				$response = $provider->generate( $prompt, $max_tokens );
				$elapsed  = ( microtime( true ) - $start ) * 1000;

				if ( '' !== trim( $response ) ) {
					AI_Health_Monitor::record_success( $provider_key, $elapsed );
					$this->log_request( 0, $provider->get_name(), $this->get_provider_model_label( $provider ), true, strlen( $prompt ) );
					return array(
						'text'     => trim( $response ),
						'provider' => $provider->get_name(),
						'model'    => $this->get_provider_model_label( $provider ),
					);
				}
			} catch ( \Throwable $e ) {
				AI_Health_Monitor::record_failure( $provider_key, $e->getMessage() );
			}
		}

		return $empty;
	}

	/**
	 * Persist AI-generated meta drafts to postmeta.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $result  Generation result.
	 */
	private function save_drafts( int $post_id, array $result ): void {
		$drafts   = get_post_meta( $post_id, '_scalyn_qa_ai_drafts', true );
		$drafts   = is_array( $drafts ) ? $drafts : array();
		$drafts[] = array(
			'title'       => $result['title'],
			'description' => $result['description'],
			'provider'    => $result['provider'],
			'model'       => $result['model'],
			'created_at'  => gmdate( 'c' ),
		);

		update_post_meta( $post_id, '_scalyn_qa_ai_drafts', $drafts );
	}

	/**
	 * Get the display-friendly model label for a provider.
	 *
	 * @param AI_Provider $provider The provider instance.
	 * @return string
	 */
	private function get_provider_model_label( AI_Provider $provider ): string {
		$models = $provider->get_models();

		// Access the protected model property via reflection-free approach.
		// The concrete providers expose it via their models list.
		foreach ( $models as $id => $label ) {
			// We cannot directly read protected $model, so return the first model
			// as the label if we cannot match. The concrete generate() method
			// already uses the correct model internally.
			return $label;
		}

		return $provider->get_name();
	}

	/**
	 * Get the encryption salt derived from WordPress auth constants.
	 *
	 * @return string
	 */
	private static function get_encryption_salt(): string {
		if ( defined( 'AUTH_SALT' ) && '' !== AUTH_SALT ) {
			return hash( 'sha256', AUTH_SALT . 'scalyn_qa_ai' );
		}

		// Fallback using always-available WordPress constants.
		return hash( 'sha256', ABSPATH . DB_NAME . NONCE_SALT );
	}
}
