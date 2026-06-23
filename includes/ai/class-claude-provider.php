<?php
/**
 * Claude Provider.
 *
 * AI provider implementation for the Anthropic Messages API (Claude models).
 *
 * @package Scalyn\QA\AI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Claude_Provider
 *
 * Implements the AI_Provider contract for Anthropic's Messages API.
 *
 * @since 1.0.0
 */
class Claude_Provider extends AI_Provider {

	/**
	 * Anthropic Messages API endpoint.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version header value.
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Claude';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'claude';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models(): array {
		return array(
			'claude-sonnet-4-6-20250514' => 'Claude Sonnet 4.6',
			'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( string $prompt, int $max_tokens = 300 ): string {
		$model = $this->model ?: 'claude-sonnet-4-6-20250514';

		$body = wp_json_encode(
			array(
				'model'      => $model,
				'max_tokens' => $max_tokens,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			)
		);

		if ( false === $body ) {
			throw new \RuntimeException( 'Failed to encode request body for Claude.' );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'Claude request failed: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = $data['error']['message'] ?? 'Unknown error';
			throw new \RuntimeException(
				sprintf( 'Claude API error (%d): %s', $status, $error_message )
			);
		}

		// Anthropic returns content as an array of content blocks.
		$content_blocks = $data['content'] ?? array();

		if ( ! is_array( $content_blocks ) || empty( $content_blocks ) ) {
			throw new \RuntimeException( 'Claude returned an empty response.' );
		}

		// Extract text from the first text block.
		$text = '';
		foreach ( $content_blocks as $block ) {
			if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
				$text = $block['text'];
				break;
			}
		}

		if ( '' === $text ) {
			throw new \RuntimeException( 'Claude returned no text content.' );
		}

		return trim( $text );
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
					__( 'Claude connection successful. Response: %s', 'scalyn-qa-assistant' ),
					mb_substr( $result, 0, 100 )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: The error message. */
					__( 'Claude connection failed: %s', 'scalyn-qa-assistant' ),
					$e->getMessage()
				),
			);
		}
	}
}
