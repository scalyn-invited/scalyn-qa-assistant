<?php
/**
 * Knowledge Article: Metadata Guide.
 *
 * Comprehensive guide to meta titles, descriptions, Open Graph tags,
 * character limits, and writing tips for effective metadata.
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
		<h1><?php esc_html_e( 'Metadata Guide', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<a href="<?php echo esc_url( $index_url ); ?>" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Knowledge Center', 'scalyn-qa-assistant' ); ?>
			</a>
		</div>
	</div>

	<div class="scalyn-card scalyn-card--article">

		<h2><?php esc_html_e( 'What Is Metadata?', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'In the context of web pages, metadata refers to information about your page that is embedded in the HTML but not directly visible to visitors on the page itself. Instead, this data is used by search engines, social media platforms, and browsers to understand, display, and categorize your content.', 'scalyn-qa-assistant' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'The most important types of metadata for WordPress developers are meta titles, meta descriptions, and Open Graph (OG) tags. Getting these right has a direct impact on how your pages appear in search results and social media shares.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Meta Titles', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'The meta title (also called the title tag) is the text that appears as the clickable headline in search engine results. It is one of the strongest on-page SEO signals and directly affects your click-through rate.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Character Limits', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Google typically displays the first 50 to 60 characters of a title tag. If your title is longer, it may be truncated with an ellipsis. Aim for 50 to 60 characters to ensure the full title is visible in search results.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Writing Tips for Meta Titles', 'scalyn-qa-assistant' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Place your primary keyword near the beginning of the title for maximum SEO impact.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Make each title unique across your entire site. Duplicate titles confuse search engines.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Include your brand name at the end, separated by a pipe (|) or dash (-). For example: "SEO Guide for Beginners | Your Brand".', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Avoid keyword stuffing. One to two keywords per title is sufficient.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Write for humans, not just search engines. A compelling title gets more clicks.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Meta Descriptions', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'The meta description is the summary text displayed below the title in search results. While Google has stated that meta descriptions are not a direct ranking factor, they have a significant impact on click-through rates. A well-written description convinces searchers to choose your page over competitors.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Character Limits', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Google typically shows 120 to 160 characters of a meta description on desktop and slightly fewer on mobile. Write descriptions within this range to avoid truncation. If no meta description is provided, Google will automatically generate one from your page content, which may not represent your page as well as a custom-written description.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Writing Tips for Meta Descriptions', 'scalyn-qa-assistant' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Summarize the page content accurately. Misleading descriptions hurt your bounce rate and reputation.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Include a clear call to action such as "Learn how," "Discover," or "Find out."', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Incorporate your target keyword naturally. Google bolds matching search terms in the description, making your result stand out.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Each page must have a unique meta description. Reusing the same description across multiple pages dilutes its effectiveness.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Avoid using quotation marks in descriptions, as Google may cut them off at the quote character.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Open Graph Tags', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Open Graph (OG) tags control how your pages appear when shared on social media platforms like Facebook, LinkedIn, and Twitter (X). Without OG tags, these platforms guess which image, title, and description to display, often with poor results.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Essential Open Graph Tags', 'scalyn-qa-assistant' ); ?></h3>
		<ul>
			<li>
				<strong><code><?php echo esc_html( 'og:title' ); ?></code>:</strong>
				<?php esc_html_e( 'The title displayed in social shares. Can differ from your meta title to be more conversational or attention-grabbing.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'og:description' ); ?></code>:</strong>
				<?php esc_html_e( 'The description shown in social shares. Aim for a concise, engaging summary.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'og:image' ); ?></code>:</strong>
				<?php esc_html_e( 'The image displayed in the social preview card. Use images at least 1200 x 630 pixels for optimal display. This is often the most impactful OG tag for engagement.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'og:url' ); ?></code>:</strong>
				<?php esc_html_e( 'The canonical URL of the page. This prevents duplicate content issues in social sharing.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'og:type' ); ?></code>:</strong>
				<?php esc_html_e( 'The type of content (e.g., "article", "website"). Use "article" for blog posts and "website" for your homepage.', 'scalyn-qa-assistant' ); ?>
			</li>
		</ul>

		<h2><?php esc_html_e( 'Setting Metadata in WordPress', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Most WordPress SEO plugins (Rank Math, Yoast SEO, All in One SEO) provide user-friendly interfaces for editing meta titles, descriptions, and OG tags directly in the post editor. They also offer preview tools that show how your page will look in Google search results and social media shares.', 'scalyn-qa-assistant' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'If you do not have an SEO plugin, WordPress generates a basic title from your post title, but it will not create meta descriptions or OG tags automatically. Installing an SEO plugin is strongly recommended for any production website.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Quick Reference', 'scalyn-qa-assistant' ); ?></h2>
		<table class="scalyn-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tag', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Recommended Length', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'scalyn-qa-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Meta Title', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( '50-60 characters', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( 'Search result headline', 'scalyn-qa-assistant' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Meta Description', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( '120-160 characters', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( 'Search result summary', 'scalyn-qa-assistant' ); ?></td>
				</tr>
				<tr>
					<td><code><?php echo esc_html( 'og:title' ); ?></code></td>
					<td><?php esc_html_e( '40-60 characters', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( 'Social share headline', 'scalyn-qa-assistant' ); ?></td>
				</tr>
				<tr>
					<td><code><?php echo esc_html( 'og:description' ); ?></code></td>
					<td><?php esc_html_e( '60-110 characters', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( 'Social share summary', 'scalyn-qa-assistant' ); ?></td>
				</tr>
				<tr>
					<td><code><?php echo esc_html( 'og:image' ); ?></code></td>
					<td><?php esc_html_e( '1200 x 630 px minimum', 'scalyn-qa-assistant' ); ?></td>
					<td><?php esc_html_e( 'Social share image', 'scalyn-qa-assistant' ); ?></td>
				</tr>
			</tbody>
		</table>

	</div>

</div>
