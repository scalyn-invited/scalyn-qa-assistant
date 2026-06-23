<?php
/**
 * Knowledge Page.
 *
 * Renders the Knowledge Center index and individual articles.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Knowledge_Page
 *
 * Renders the knowledge base index or a specific article template.
 *
 * @since 1.0.0
 */
class Knowledge_Page {

	/**
	 * Available knowledge articles.
	 *
	 * @var array<string, array{title: string, description: string, icon: string}>
	 */
	private const ARTICLES = array(
		'seo-basics' => array(
			'title'       => 'SEO Basics',
			'description' => 'Learn the fundamentals of search engine optimization for WordPress websites.',
			'icon'        => 'dashicons-search',
		),
		'heading-structure' => array(
			'title'       => 'Heading Structure',
			'description' => 'Understand how to use HTML headings (H1-H6) for accessibility and SEO.',
			'icon'        => 'dashicons-editor-textcolor',
		),
		'metadata-guide' => array(
			'title'       => 'Metadata Guide',
			'description' => 'A comprehensive guide to meta titles, descriptions, and Open Graph tags.',
			'icon'        => 'dashicons-admin-generic',
		),
		'launch-checklist' => array(
			'title'       => 'Launch Checklist',
			'description' => 'Complete pre-launch checklist to ensure your website is ready to go live.',
			'icon'        => 'dashicons-yes-alt',
		),
		'accessibility-basics' => array(
			'title'       => 'Accessibility Basics',
			'description' => 'Essential accessibility practices to make your website usable for everyone.',
			'icon'        => 'dashicons-universal-access',
		),
	);

	/**
	 * Render the knowledge page.
	 *
	 * Routes to a specific article or the index based on the `article` query parameter.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$article_slug = isset( $_GET['article'] ) ? sanitize_key( $_GET['article'] ) : '';

		if ( ! empty( $article_slug ) && $this->is_valid_article( $article_slug ) ) {
			$this->render_article( $article_slug );
		} else {
			$this->render_index();
		}
	}

	/**
	 * Render the knowledge center index page.
	 *
	 * @since 1.0.0
	 */
	private function render_index(): void {
		$articles = array();

		foreach ( self::ARTICLES as $slug => $article ) {
			$articles[ $slug ] = array_merge(
				$article,
				array(
					'slug' => $slug,
					'url'  => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['knowledge'] . '&article=' . $slug ),
				),
			);
		}

		$data = array(
			'articles' => $articles,
		);

		$this->load_template( 'knowledge/index.php', $data );
	}

	/**
	 * Render a specific knowledge article.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The article slug.
	 */
	private function render_article( string $slug ): void {
		$article = self::ARTICLES[ $slug ] ?? null;

		if ( null === $article ) {
			$this->render_index();
			return;
		}

		$data = array(
			'slug'      => $slug,
			'title'     => $article['title'],
			'icon'      => $article['icon'],
			'index_url' => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['knowledge'] ),
			'articles'  => self::ARTICLES,
		);

		$template_file    = 'knowledge/' . $slug . '.php';
		$template_file_alt = 'knowledge/articles/' . $slug . '.php';

		// Try article-specific template, then articles/ subdirectory, fall back to generic article wrapper.
		if ( file_exists( SCALYN_QA_PLUGIN_DIR . 'templates/' . $template_file ) ) {
			$this->load_template( $template_file, $data );
		} elseif ( file_exists( SCALYN_QA_PLUGIN_DIR . 'templates/' . $template_file_alt ) ) {
			$this->load_template( $template_file_alt, $data );
		} else {
			$this->load_template( 'knowledge/article.php', $data );
		}
	}

	/**
	 * Check whether an article slug is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The article slug to validate.
	 * @return bool
	 */
	private function is_valid_article( string $slug ): bool {
		return array_key_exists( $slug, self::ARTICLES );
	}

	/**
	 * Get the list of available articles.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{title: string, description: string, icon: string}>
	 */
	public static function get_articles(): array {
		return self::ARTICLES;
	}

	/**
	 * Load a template file with the given data extracted into scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Relative template path (from templates/ directory).
	 * @param array  $data     Data to extract into the template scope.
	 */
	private function load_template( string $template, array $data = array() ): void {
		$template_path = SCALYN_QA_PLUGIN_DIR . 'templates/' . $template;

		if ( ! file_exists( $template_path ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: Template file path. */
						__( 'Template not found: %s', 'scalyn-qa-assistant' ),
						$template,
					),
				),
			);
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );

		include $template_path;
	}
}
