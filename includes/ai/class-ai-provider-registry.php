<?php
/**
 * AI Provider Registry.
 *
 * Manages registration and lookup of AI provider classes,
 * replacing the hardcoded provider list in AI_Manager.
 *
 * @package Scalyn\QA\AI
 * @since   1.4.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Provider_Registry
 *
 * Static registry for AI provider classes. Third-party plugins can
 * register custom providers via the `scalyn_qa_register_ai_providers` hook.
 *
 * @since 1.4.0
 */
final class AI_Provider_Registry {

	/**
	 * Registered providers keyed by slug.
	 *
	 * @var array<string, array{class: class-string<AI_Provider>, name: string, description: string, website: string, supports_local: bool, category: string}>
	 */
	private static array $providers = [];

	/**
	 * Register a provider class.
	 *
	 * @since 1.4.0
	 *
	 * @param string $slug  Provider identifier (e.g., 'openai', 'ollama').
	 * @param string $class Fully qualified class name extending AI_Provider.
	 * @param array  $meta  Provider metadata: name, description, website, supports_local, category.
	 */
	public static function register( string $slug, string $class, array $meta = [] ): void {
		if ( ! is_subclass_of( $class, AI_Provider::class ) ) {
			return;
		}

		self::$providers[ $slug ] = [
			'class'          => $class,
			'name'           => $meta['name'] ?? $slug,
			'description'    => $meta['description'] ?? '',
			'website'        => $meta['website'] ?? '',
			'supports_local' => $meta['supports_local'] ?? false,
			'category'       => $meta['category'] ?? 'cloud', // 'cloud', 'local', 'enterprise'
		];
	}

	/**
	 * Get all registered providers.
	 *
	 * @since 1.4.0
	 *
	 * @return array<string, array>
	 */
	public static function get_all(): array {
		return self::$providers;
	}

	/**
	 * Get a single provider registration.
	 *
	 * @since 1.4.0
	 *
	 * @param string $slug Provider slug.
	 * @return array|null Registration data or null if not found.
	 */
	public static function get( string $slug ): ?array {
		return self::$providers[ $slug ] ?? null;
	}

	/**
	 * Check if a provider is registered.
	 *
	 * @since 1.4.0
	 *
	 * @param string $slug Provider slug.
	 * @return bool
	 */
	public static function has( string $slug ): bool {
		return isset( self::$providers[ $slug ] );
	}

	/**
	 * Get providers by category.
	 *
	 * @since 1.4.0
	 *
	 * @param string $category Provider category ('cloud', 'local', 'enterprise').
	 * @return array<string, array>
	 */
	public static function get_by_category( string $category ): array {
		return array_filter( self::$providers, fn( $p ) => $p['category'] === $category );
	}

	/**
	 * Register all built-in providers. Called on plugin init.
	 *
	 * @since 1.4.0
	 */
	public static function register_defaults(): void {
		self::register( 'openai', OpenAI_Provider::class, [
			'name'        => 'OpenAI',
			'description' => 'GPT-4o, GPT-4o Mini, GPT-4.1',
			'website'     => 'https://platform.openai.com',
			'category'    => 'cloud',
		] );

		self::register( 'claude', Claude_Provider::class, [
			'name'        => 'Claude (Anthropic)',
			'description' => 'Claude Sonnet 4, Claude Haiku',
			'website'     => 'https://console.anthropic.com',
			'category'    => 'cloud',
		] );

		self::register( 'gemini', Gemini_Provider::class, [
			'name'        => 'Gemini (Google)',
			'description' => 'Gemini 2.0 Flash, Gemini 2.5 Flash',
			'website'     => 'https://ai.google.dev',
			'category'    => 'cloud',
		] );

		self::register( 'openrouter', OpenRouter_Provider::class, [
			'name'        => 'OpenRouter',
			'description' => 'Access multiple AI models through a single API key',
			'website'     => 'https://openrouter.ai',
			'category'    => 'cloud',
		] );

		self::register( 'custom', Custom_Endpoint_Provider::class, [
			'name'           => 'Custom Endpoint',
			'description'    => 'Connect to any OpenAI-compatible API (Ollama, LM Studio, etc.)',
			'website'        => '',
			'supports_local' => true,
			'category'       => 'local',
		] );

		/**
		 * Fires after built-in AI providers are registered.
		 * Use this hook to register custom/third-party providers.
		 *
		 * Example:
		 *   add_action('scalyn_qa_register_ai_providers', function() {
		 *       AI_Provider_Registry::register('ollama', My_Ollama_Provider::class, [
		 *           'name'           => 'Ollama (Local)',
		 *           'supports_local' => true,
		 *           'category'       => 'local',
		 *       ]);
		 *   });
		 *
		 * @since 1.4.0
		 */
		do_action( 'scalyn_qa_register_ai_providers' );
	}
}
