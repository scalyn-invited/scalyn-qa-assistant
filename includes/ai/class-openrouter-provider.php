<?php
/**
 * OpenRouter Provider.
 *
 * AI provider implementation for the OpenRouter API, which provides
 * access to multiple AI models (Claude, GPT, Gemini, etc.) through
 * a single OpenAI-compatible endpoint.
 *
 * @package Scalyn\QA\AI
 * @since   1.4.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenRouter_Provider
 *
 * Implements the AI_Provider contract for the OpenRouter API.
 * OpenRouter uses an OpenAI-compatible chat completions format and
 * requires `HTTP-Referer` and `X-Title` headers for attribution.
 *
 * @since 1.4.0
 */
class OpenRouter_Provider extends AI_Provider {

	/**
	 * OpenRouter Chat Completions endpoint.
	 *
	 * @var string
	 */
	private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'openrouter';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'OpenRouter';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models(): array {
		return array(
			'anthropic/claude-sonnet-4'          => 'Claude Sonnet 4',
			'anthropic/claude-3.5-sonnet'        => 'Claude 3.5 Sonnet',
			'openai/gpt-4o'                      => 'GPT-4o',
			'openai/gpt-4o-mini'                 => 'GPT-4o Mini',
			'google/gemini-2.0-flash-exp'        => 'Gemini 2.0 Flash',
			'deepseek/deepseek-chat'             => 'DeepSeek V3',
			'mistralai/mistral-large-latest'     => 'Mistral Large',
			'meta-llama/llama-3.1-70b-instruct'  => 'Llama 3.1 70B',
			'qwen/qwen-2.5-72b-instruct'        => 'Qwen 2.5 72B',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( string $prompt, int $max_tokens = 300 ): string {
		$model = $this->model ?: 'anthropic/claude-sonnet-4';

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
			throw new \RuntimeException( 'Failed to encode request body for OpenRouter.' );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'Scalyn QA Assistant',
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'OpenRouter request failed: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = $data['error']['message'] ?? 'Unknown error';
			throw new \RuntimeException(
				sprintf( 'OpenRouter API error (%d): %s', $status, $error_message )
			);
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === $content ) {
			throw new \RuntimeException( 'OpenRouter returned an empty response.' );
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
					__( 'OpenRouter connection successful. Response: %s', 'scalyn-qa-assistant' ),
					mb_substr( $result, 0, 100 )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: The error message. */
					__( 'OpenRouter connection failed: %s', 'scalyn-qa-assistant' ),
					$e->getMessage()
				),
			);
		}
	}
}
