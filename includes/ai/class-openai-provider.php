<?php
/**
 * OpenAI Provider.
 *
 * AI provider implementation for the OpenAI API (GPT models).
 *
 * @package Scalyn\QA\AI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenAI_Provider
 *
 * Implements the AI_Provider contract for OpenAI's Chat Completions API.
 *
 * @since 1.0.0
 */
class OpenAI_Provider extends AI_Provider {

	/**
	 * OpenAI Chat Completions endpoint.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'OpenAI';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models(): array {
		return array(
			'gpt-4o-mini'    => 'GPT-4o Mini',
			'gpt-4o'         => 'GPT-4o',
			'gpt-4.1-mini'   => 'GPT-4.1 Mini',
			'gpt-4.1-nano'   => 'GPT-4.1 Nano',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( string $prompt, int $max_tokens = 300 ): string {
		$model = $this->model ?: 'gpt-4o-mini';

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
			throw new \RuntimeException( 'Failed to encode request body for OpenAI.' );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'OpenAI request failed: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = $data['error']['message'] ?? 'Unknown error';
			throw new \RuntimeException(
				sprintf( 'OpenAI API error (%d): %s', $status, $error_message )
			);
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === $content ) {
			throw new \RuntimeException( 'OpenAI returned an empty response.' );
		}

		return trim( $content );
	}

	/**
	 * Generate alt text for an image using GPT-4o vision.
	 *
	 * @since 1.0.7
	 *
	 * @param string $image_url The public URL of the image.
	 * @return string The generated alt text.
	 */
	public function generate_alt_text( string $image_url ): string {
		$model = 'gpt-4o-mini';

		// Convert local image to base64 since OpenAI can't access localhost.
		$image_data_url = $this->resolve_image_to_data_url( $image_url );

		$body = wp_json_encode(
			array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type' => 'text',
								'text' => 'Generate a concise, descriptive alt text for this image. The alt text should be 1-2 sentences, describe what is visually shown, and be useful for accessibility and SEO. Respond with ONLY the alt text, nothing else.',
							),
							array(
								'type'      => 'image_url',
								'image_url' => array(
									'url'    => $image_data_url,
									'detail' => 'low',
								),
							),
						),
					),
				),
				'max_tokens' => 100,
			)
		);

		if ( false === $body ) {
			throw new \RuntimeException( 'Failed to encode request body for OpenAI vision.' );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'OpenAI vision request failed: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = $data['error']['message'] ?? 'Unknown error';
			throw new \RuntimeException( sprintf( 'OpenAI API error (%d): %s', $status, $error_message ) );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === $content ) {
			throw new \RuntimeException( 'OpenAI returned an empty alt text response.' );
		}

		return trim( $content );
	}

	/**
	 * Resolve an image URL to a base64 data URL for the OpenAI vision API.
	 *
	 * Reads the file from the local filesystem if possible, otherwise downloads it.
	 *
	 * @param string $image_url The image URL.
	 * @return string A data:image/... URL or the original URL if public.
	 */
	private function resolve_image_to_data_url( string $image_url ): string {
		// Try to convert the URL to a local file path.
		$local_path = $this->url_to_local_path( $image_url );

		if ( null !== $local_path && file_exists( $local_path ) ) {
			$mime     = wp_check_filetype( $local_path )['type'] ?: 'image/jpeg';
			$contents = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $contents ) {
				return 'data:' . $mime . ';base64,' . base64_encode( $contents ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}

		// If not local, return the URL as-is (works for public URLs).
		return $image_url;
	}

	/**
	 * Convert a WordPress URL to a local file path.
	 *
	 * @param string $url The URL to convert.
	 * @return string|null The local path, or null if not resolvable.
	 */
	private function url_to_local_path( string $url ): ?string {
		$upload_dir = wp_get_upload_dir();

		// Check if URL is within the uploads directory.
		if ( str_starts_with( $url, $upload_dir['baseurl'] ) ) {
			return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		}

		// Check if URL is within the site URL.
		$site_url = site_url();
		if ( str_starts_with( $url, $site_url ) ) {
			return str_replace( $site_url, ABSPATH, $url );
		}

		return null;
	}

	/**
	 * Generate an image using OpenAI's image generation API.
	 *
	 * Tries gpt-image-1 first (b64_json), then dall-e-3 (URL download).
	 *
	 * @since 1.4.0
	 *
	 * @param string $prompt The image generation prompt.
	 * @return string Base64-encoded image data (PNG).
	 */
	public function generate_image( string $prompt, string $size = '' ): string {
		$gpt_size  = '' !== $size ? $size : '1536x1024';
		$dall_size = '' !== $size ? $size : '1792x1024';

		// DALL-E 3 only supports 1024x1024, 1024x1792, 1792x1024.
		$dalle_valid = array( '1024x1024', '1024x1792', '1792x1024' );
		if ( ! in_array( $dall_size, $dalle_valid, true ) ) {
			$dall_size = '1024x1024';
		}

		$models = array(
			array(
				'params' => array(
					'model'         => 'gpt-image-1',
					'prompt'        => $prompt,
					'n'             => 1,
					'size'          => $gpt_size,
					'output_format' => 'png',
				),
				'extract' => 'b64_json',
			),
			array(
				'params' => array(
					'model'   => 'dall-e-3',
					'prompt'  => $prompt,
					'n'       => 1,
					'size'    => $dall_size,
					'quality' => 'standard',
				),
				'extract' => 'url',
			),
		);

		$last_error = '';

		foreach ( $models as $entry ) {
			$body = wp_json_encode( $entry['params'] );

			if ( false === $body ) {
				throw new \RuntimeException( 'Failed to encode request body for image generation.' );
			}

			$response = wp_remote_post(
				'https://api.openai.com/v1/images/generations',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
					'timeout' => 120,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = 'Image generation request failed: ' . $response->get_error_message();
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );
			$raw    = wp_remote_retrieve_body( $response );
			$data   = json_decode( $raw, true );

			if ( $status < 200 || $status >= 300 ) {
				$last_error = sprintf(
					'%s error (%d): %s',
					$entry['params']['model'],
					$status,
					$data['error']['message'] ?? 'Unknown error'
				);
				continue;
			}

			// gpt-image-1 returns base64 directly.
			if ( 'b64_json' === $entry['extract'] ) {
				$b64 = $data['data'][0]['b64_json'] ?? '';
				if ( '' !== $b64 ) {
					return $b64;
				}
			}

			// dall-e-3 returns a URL — download and convert to base64.
			if ( 'url' === $entry['extract'] ) {
				$image_url = $data['data'][0]['url'] ?? '';
				if ( '' !== $image_url ) {
					$download = wp_remote_get( $image_url, array( 'timeout' => 60 ) );
					if ( ! is_wp_error( $download ) && 200 === wp_remote_retrieve_response_code( $download ) ) {
						return base64_encode( wp_remote_retrieve_body( $download ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					}
				}
			}

			$last_error = $entry['params']['model'] . ' returned no image data.';
		}

		throw new \RuntimeException( $last_error );
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
					__( 'OpenAI connection successful. Response: %s', 'scalyn-qa-assistant' ),
					mb_substr( $result, 0, 100 )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: The error message. */
					__( 'OpenAI connection failed: %s', 'scalyn-qa-assistant' ),
					$e->getMessage()
				),
			);
		}
	}
}
