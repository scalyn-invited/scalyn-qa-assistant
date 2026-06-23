<?php
/**
 * Knowledge Article: SEO Basics.
 *
 * Educational content about fundamental SEO concepts including
 * on-page factors, meta tags, images, links, and content quality.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var string $slug      The article slug.
 * @var string $title     The article title.
 * @var string $icon      The dashicons icon class.
 * @var string $index_url URL to the knowledge center index.
 */

defined( 'ABSPATH' ) || exit;

$index_url = isset( $index_url ) ? $index_url : '';
?>
<div class="scalyn-wrap">

	<div class="scalyn-page-header">
		<h1><?php esc_html_e( 'SEO Basics', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<a href="<?php echo esc_url( $index_url ); ?>" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Knowledge Center', 'scalyn-qa-assistant' ); ?>
			</a>
		</div>
	</div>

	<div class="scalyn-card scalyn-card--article">

		<h2><?php esc_html_e( 'What is SEO?', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Search Engine Optimization (SEO) is the practice of improving your website to increase its visibility in search engine results. When people search for products, services, or information related to your business, SEO helps your pages appear higher in those results.', 'scalyn-qa-assistant' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'SEO is not about tricking search engines. It is about making your website genuinely useful, well-structured, and easy for both users and search engines to understand.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'On-Page SEO Factors', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'On-page SEO refers to the elements you control directly on your website. These include:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li><strong><?php esc_html_e( 'Meta Titles', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'The title tag that appears in search results. Keep it under 60 characters and include your primary keyword.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Meta Descriptions', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'A brief summary shown below the title in search results. Aim for 120-160 characters.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Heading Structure', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Use a single H1 for the main title and organize content with H2-H6 subheadings.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'URL Structure', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Use clean, readable URLs that include relevant keywords.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Internal Links', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Link to other relevant pages on your site to help search engines discover content.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Images and SEO', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Images play an important role in SEO. Every image should have descriptive alt text that explains what the image shows. This helps:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li><?php esc_html_e( 'Search engines understand image content', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Screen readers describe images to visually impaired users', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Images appear in Google Image Search results', 'scalyn-qa-assistant' ); ?></li>
		</ul>
		<p>
			<?php esc_html_e( 'Also ensure images are optimized for file size to improve page load speed, which is a ranking factor.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Content Quality', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'High-quality content is the foundation of good SEO. Search engines prioritize content that is:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li><strong><?php esc_html_e( 'Relevant', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Directly addresses what users are searching for.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Original', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Provides unique value not found elsewhere.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Well-Structured', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Uses headings, paragraphs, and lists for readability.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Comprehensive', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Covers the topic thoroughly without unnecessary padding.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Links and Authority', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Links serve two purposes in SEO:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li><strong><?php esc_html_e( 'Internal Links', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Connect pages within your site, helping search engines crawl and understand your site structure.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'External Links', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Linking to authoritative external sources can enhance your content credibility.', 'scalyn-qa-assistant' ); ?></li>
		</ul>
		<p>
			<?php esc_html_e( 'Ensure all links work correctly. Broken links create a poor user experience and can negatively impact your rankings.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Technical SEO Essentials', 'scalyn-qa-assistant' ); ?></h2>
		<ul>
			<li><strong><?php esc_html_e( 'SSL Certificate', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'HTTPS is a ranking signal. Ensure your site uses SSL.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'XML Sitemap', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Submit a sitemap to help search engines find all your pages.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Page Speed', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Faster pages rank better and provide a better user experience.', 'scalyn-qa-assistant' ); ?></li>
			<li><strong><?php esc_html_e( 'Mobile Responsiveness', 'scalyn-qa-assistant' ); ?></strong> — <?php esc_html_e( 'Google uses mobile-first indexing, so your site must work well on mobile devices.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Quick SEO Checklist', 'scalyn-qa-assistant' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Every page has a unique meta title (50-60 characters)', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Every page has a meta description (120-160 characters)', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Every page has exactly one H1 heading', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'All images have descriptive alt text', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Internal links connect related pages', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'No broken links exist on the site', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'SSL is enabled (HTTPS)', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'An XML sitemap is accessible', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'An SEO plugin is installed and configured', 'scalyn-qa-assistant' ); ?></li>
		</ul>

	</div>

</div>
