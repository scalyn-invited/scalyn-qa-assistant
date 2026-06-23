<?php
/**
 * Custom Endpoint Provider.
 *
 * AI provider implementation for any OpenAI-compatible API endpoint,
 * including Ollama, LM Studio, vLLM, and internal/proxy APIs.
 *
 * @package Scalyn\QA\AI
 * @since   1.4.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Custom_Endpoint_Provider
 *
 * Implements the AI_Provider contract for arbitrary OpenAI-compatible
 * API endpoints. Supports configurable URL, model name, headers,
 * and optional Bearer-token authentication.
 *
 * @since 1.4.0
 */
class Custom_Endpoint_Provider extends AI_Provider {

	/**
	 * The custom API endpoint URL.
	 *
	 * @var string
	 */
	private string $endpoint = '';

	/**
	 * Additional HTTP headers to send with each request.
	 *
	 * @var array<string, string>
	 */
	private array $custom_headers = [];

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'custom';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Custom Endpoint';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models(): array {
		// Custom endpoints define their own model — return a placeholder.
		return array(
			'custom' => 'Custom Model',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_custom_endpoint(): bool {
		return true;
	}

	/**
	 * Set the custom API endpoint URL.
	 *
	 * @since 1.4.0
	 *
	 * @param string $url The endpoint URL.
	 */
	public function set_endpoint( string $url ): void {
		$this->endpoint = $url;
	}

	/**
	 * Set additional HTTP headers for requests.
	 *
	 * @since 1.4.0
	 *
	 * @param array<string, string> $headers Associative array of header name => value.
	 */
	public function set_custom_headers( array $headers ): void {
		$this->custom_headers = $headers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( string $prompt, int $max_tokens = 300 ): string {
		if ( empty( $this->endpoint ) ) {
			throw new \RuntimeException( 'Custom endpoint URL is not configured.' );
		}

		$model = $this->model ?: 'default';

		// OpenAI-compatible format (works with Ollama, LM Studio, vLLM, etc.).
		$body = wp_json_encode(
			array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are an expert SEO copywriter and editor. Follow the instructions precisely and respond only with the requested output.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.7,
				'max_tokens'  => $max_tokens,
			)
		);

		if ( false === $body ) {
			throw new \RuntimeException( 'Failed to encode request body for custom endpoint.' );
		}

		$headers = array_merge(
			array( 'Content-Type' => 'application/json' ),
			$this->custom_headers
		);

		// Add API key as Bearer token if provided.
		if ( ! empty( $this->api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'Custom endpoint request failed: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = is_array( $data )
				? ( $data['error']['message'] ?? $data['error'] ?? 'Unknown error' )
				: 'HTTP ' . $status;

			if ( ! is_string( $error_message ) ) {
				$error_message = wp_json_encode( $error_message );
			}

			throw new \RuntimeException(
				sprintf( 'Custom endpoint error (%d): %s', $status, $error_message )
			);
		}

		// Try OpenAI format first, then common alternative response shapes.
		$content = '';

		if ( is_array( $data ) ) {
			$content = $data['choices'][0]['message']['content']
				?? $data['response']
				?? $data['text']
				?? $data['output']
				?? '';
		}

		if ( ! is_string( $content ) || '' === $content ) {
			throw new \RuntimeException( 'Custom endpoint returned an empty or unparseable response.' );
		}

		return trim( $content );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test(): array {
		try {
			$result = $this->generate( 'Say "Connection successful" and nothing else.' );

			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: The AI model response text. */
					__( 'Custom endpoint connection successful. Response: %s', 'scalyn-qa-assistant' ),
					mb_substr( $result, 0, 100 )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: The error message. */
					__( 'Custom endpoint connection failed: %s', 'scalyn-qa-assistant' ),
					$e->getMessage()
				),
			);
		}
	}
}
