<?php
/**
 * Gemini Provider.
 *
 * AI provider implementation for Google's Gemini (Generative Language) API.
 *
 * @package Scalyn\QA\AI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Gemini_Provider
 *
 * Implements the AI_Provider contract for Google's generativelanguage REST API.
 *
 * @since 1.0.0
 */
class Gemini_Provider extends AI_Provider {

	/**
	 * Gemini API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models(): array {
		return array(
			'gemini-2.0-flash' => 'Gemini 2.0 Flash',
			'gemini-2.5-flash' => 'Gemini 2.5 Flash',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( string $prompt, int $max_tokens = 300 ): string {
		$model = $this->model ?: 'gemini-2.0-flash';
		$url   = self::API_BASE . rawurlencode( $model ) . ':generateContent';

		$body = wp_json_encode(
			array(
				'contents' => array(
					array(
						'parts' => array(
							array(
								'text' => $prompt,
							),
						),
					),
				),
			)
		);

		if ( false === $body ) {
			throw new \RuntimeException( 'Failed to encode request body for Gemini.' );
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'Gemini request failed: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = $data['error']['message'] ?? 'Unknown error';
			throw new \RuntimeException(
				sprintf( 'Gemini API error (%d): %s', $status, $error_message )
			);
		}

		// Gemini returns candidates[0].content.parts[0].text.
		$candidates = $data['candidates'] ?? array();

		if ( ! is_array( $candidates ) || empty( $candidates ) ) {
			throw new \RuntimeException( 'Gemini returned no candidates.' );
		}

		$parts = $candidates[0]['content']['parts'] ?? array();

		if ( ! is_array( $parts ) || empty( $parts ) ) {
			throw new \RuntimeException( 'Gemini returned an empty response.' );
		}

		$text = $parts[0]['text'] ?? '';

		if ( ! is_string( $text ) || '' === $text ) {
			throw new \RuntimeException( 'Gemini returned no text content.' );
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
					__( 'Gemini connection successful. Response: %s', 'scalyn-qa-assistant' ),
					mb_substr( $result, 0, 100 )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: The error message. */
					__( 'Gemini connection failed: %s', 'scalyn-qa-assistant' ),
					$e->getMessage()
				),
			);
		}
	}
}
