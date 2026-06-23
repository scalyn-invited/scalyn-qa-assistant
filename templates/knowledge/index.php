<?php
/**
 * Template: Knowledge Center Index.
 *
 * Renders a card grid of available knowledge base articles.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array $articles Array of article data: slug => [title, description, icon, url].
 */

defined( 'ABSPATH' ) || exit;

$articles = isset( $articles ) ? $articles : array();
?>
<div class="scalyn-wrap">

	<div class="scalyn-page-header">
		<div class="scalyn-page-header__intro">
			<h1><?php esc_html_e( 'Knowledge Center', 'scalyn-qa-assistant' ); ?></h1>
			<p class="scalyn-page-header__description"><?php esc_html_e( 'Learn best practices for SEO, accessibility, and website quality assurance.', 'scalyn-qa-assistant' ); ?></p>
		</div>
	</div>

	<?php if ( ! empty( $articles ) ) : ?>
		<div class="scalyn-knowledge-grid">
			<?php foreach ( $articles as $article_slug => $article ) : ?>
				<a href="<?php echo esc_url( $article['url'] ); ?>" class="scalyn-knowledge-item">
					<span class="scalyn-knowledge-item__icon dashicons <?php echo esc_attr( $article['icon'] ); ?>" aria-hidden="true"></span>
					<div class="scalyn-knowledge-item__content">
						<h3 class="scalyn-knowledge-item__title"><?php echo esc_html( $article['title'] ); ?></h3>
						<p class="scalyn-knowledge-item__description"><?php echo esc_html( $article['description'] ); ?></p>
					</div>
					<span class="scalyn-knowledge-item__arrow dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="scalyn-card">
			<p><?php esc_html_e( 'No articles available at this time.', 'scalyn-qa-assistant' ); ?></p>
		</div>
	<?php endif; ?>

</div>
