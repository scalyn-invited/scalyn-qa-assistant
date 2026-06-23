<?php
/**
 * Knowledge Article: Heading Structure.
 *
 * Educational content about HTML heading hierarchy (H1-H6),
 * its importance for accessibility and SEO, and common mistakes.
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
		<h1><?php esc_html_e( 'Heading Structure', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<a href="<?php echo esc_url( $index_url ); ?>" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Knowledge Center', 'scalyn-qa-assistant' ); ?>
			</a>
		</div>
	</div>

	<div class="scalyn-card scalyn-card--article">

		<h2><?php esc_html_e( 'Why Headings Matter', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'HTML headings (H1 through H6) are more than just visual formatting tools. They create a hierarchical outline of your content that serves three critical purposes: they help search engines understand the structure and topic of your page, they allow screen readers to navigate your content efficiently, and they make your pages easier for visitors to scan and read.', 'scalyn-qa-assistant' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Think of headings as the table of contents for your page. Just as a book organizes chapters, sections, and subsections, your web page should use headings to create a logical, nested structure that readers and machines can follow.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'The Heading Hierarchy Explained', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Each heading level represents a different depth in your content outline:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li>
				<strong><?php esc_html_e( 'H1 — Page Title:', 'scalyn-qa-assistant' ); ?></strong>
				<?php esc_html_e( 'This is the main title of your page. Every page should have exactly one H1 that clearly describes the page topic. In WordPress, the post title usually becomes the H1 automatically. Avoid adding additional H1 tags in your content.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'H2 — Major Sections:', 'scalyn-qa-assistant' ); ?></strong>
				<?php esc_html_e( 'Use H2 tags to divide your content into main sections. These are the top-level topics within your page, similar to chapter titles in a book.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'H3 — Subsections:', 'scalyn-qa-assistant' ); ?></strong>
				<?php esc_html_e( 'Use H3 tags for subtopics within an H2 section. These break down a major section into smaller, focused points.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'H4-H6 — Deeper Levels:', 'scalyn-qa-assistant' ); ?></strong>
				<?php esc_html_e( 'These are used for further nesting within subsections. Most pages rarely need to go beyond H4. If you find yourself using H5 or H6, consider whether your content could be restructured or split into separate pages.', 'scalyn-qa-assistant' ); ?>
			</li>
		</ul>

		<h2><?php esc_html_e( 'A Correct Heading Structure Example', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Here is what a well-structured heading hierarchy looks like:', 'scalyn-qa-assistant' ); ?>
		</p>
		<pre class="scalyn-code-block"><code><?php echo esc_html(
			"H1: How to Train a Puppy\n" .
			"  H2: Getting Started\n" .
			"    H3: Choosing the Right Breed\n" .
			"    H3: Essential Supplies\n" .
			"  H2: Basic Commands\n" .
			"    H3: Sit\n" .
			"    H3: Stay\n" .
			"    H3: Come\n" .
			"  H2: Common Mistakes\n" .
			"    H3: Inconsistent Training\n" .
			"    H3: Skipping Socialization"
		); ?></code></pre>
		<p>
			<?php esc_html_e( 'Notice how each heading level is nested inside the one above it. You never jump from an H2 directly to an H4 because that breaks the logical outline.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Common Mistakes to Avoid', 'scalyn-qa-assistant' ); ?></h2>

		<h3><?php esc_html_e( 'Multiple H1 Tags', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Having more than one H1 on a page confuses search engines about the primary topic. In WordPress, your post title is typically the H1, so you should start your content body with H2 headings. If your theme already outputs the post title as an H1, do not add another one in the editor.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Skipping Heading Levels', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Jumping from H2 to H4 (skipping H3) breaks the logical hierarchy. This is a common accessibility issue because screen reader users navigate pages by heading level. When levels are skipped, the content outline becomes confusing and harder to follow.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Using Headings for Styling', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Never use a heading tag just to make text bigger or bolder. Headings carry semantic meaning and should only be used to define content structure. If you need visual emphasis, use CSS classes or bold text instead. For example, using an H3 for a sidebar callout when it does not represent a subsection of the content above it would be incorrect.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Empty Headings', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'An empty heading tag with no text content creates confusion for screen readers and provides no SEO value. If a heading is not needed, remove the tag entirely rather than leaving it empty.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'How to Fix Heading Issues', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'The Scalyn QA plugin automatically checks your heading structure and flags problems. Here are common fixes:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ol>
			<li><?php esc_html_e( 'If you have multiple H1 tags, change the extra ones to H2 or remove them. Your WordPress post title handles the H1.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'If levels are skipped (e.g., H2 followed by H4), add the missing intermediate level or adjust the levels to follow the correct sequence.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'If you are using headings for visual effect, replace them with styled paragraphs or spans with CSS classes.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Use the WordPress block editor heading block, which clearly labels the heading level and makes it easy to select the correct one.', 'scalyn-qa-assistant' ); ?></li>
		</ol>

		<h2><?php esc_html_e( 'Tips for Developers', 'scalyn-qa-assistant' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'When building custom themes, ensure the post title is wrapped in an H1 tag on single post/page views and an H2 on archive/listing pages.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Use the WordPress Accessibility Checker or browser developer tools to inspect heading outlines on your pages.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Educate content editors about heading hierarchy. Include guidelines in your site documentation or editor notes.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Test your heading structure with a screen reader to understand how assistive technology users experience your content.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

	</div>

</div>
