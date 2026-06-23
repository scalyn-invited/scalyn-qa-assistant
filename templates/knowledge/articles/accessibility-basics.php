<?php
/**
 * Knowledge Article: Accessibility Basics.
 *
 * Guide to essential web accessibility practices including alt text,
 * ARIA labels, color contrast, keyboard navigation, and semantic HTML.
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
		<h1><?php esc_html_e( 'Accessibility Basics', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<a href="<?php echo esc_url( $index_url ); ?>" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Knowledge Center', 'scalyn-qa-assistant' ); ?>
			</a>
		</div>
	</div>

	<div class="scalyn-card scalyn-card--article">

		<h2><?php esc_html_e( 'Why Accessibility Matters', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Web accessibility means designing and building websites that can be used by everyone, including people with disabilities. This includes people who are blind or have low vision, deaf or hard of hearing, have motor impairments, or have cognitive disabilities. According to the World Health Organization, over one billion people worldwide live with some form of disability.', 'scalyn-qa-assistant' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Beyond the ethical imperative, accessibility is increasingly a legal requirement in many jurisdictions. Many countries have laws requiring websites to meet accessibility standards, and lawsuits against inaccessible websites are becoming more common. Additionally, accessible websites tend to have better SEO, since search engines and screen readers interpret content in similar ways.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Alt Text for Images', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Alternative text (alt text) is a text description assigned to an image that is read aloud by screen readers and displayed when an image fails to load. Every meaningful image on your website needs descriptive alt text.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Writing Good Alt Text', 'scalyn-qa-assistant' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Be specific and concise. Describe what the image shows, not what it is. Instead of "photo," write "developer reviewing code on laptop screen."', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Do not start with "Image of" or "Photo of." Screen readers already announce that an element is an image.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'For decorative images (backgrounds, spacers, visual separators), use an empty alt attribute (alt="") to tell screen readers to skip them.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'If an image contains text (such as an infographic or chart), include that text in the alt attribute or provide a text alternative nearby.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'For linked images, the alt text should describe the link destination, not the image appearance.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'ARIA Labels and Roles', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Accessible Rich Internet Applications (ARIA) is a set of HTML attributes that provide additional information to assistive technologies. ARIA labels and roles are especially important for interactive elements that do not have native semantic meaning.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Common ARIA Attributes', 'scalyn-qa-assistant' ); ?></h3>
		<ul>
			<li>
				<strong><code><?php echo esc_html( 'aria-label' ); ?></code>:</strong>
				<?php esc_html_e( 'Provides a text label for elements that lack visible text. For example, a search button with only an icon should have aria-label="Search" so screen readers know its purpose.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'aria-labelledby' ); ?></code>:</strong>
				<?php esc_html_e( 'References another element by ID to use its text as the label. Useful when an existing heading or text already describes the element.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'aria-hidden="true"' ); ?></code>:</strong>
				<?php esc_html_e( 'Hides an element from screen readers while keeping it visually present. Use this for decorative icons that are already described by adjacent text.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><code><?php echo esc_html( 'role' ); ?></code>:</strong>
				<?php esc_html_e( 'Defines what an element is or does. Common roles include "button," "navigation," "main," "alert," and "dialog." Only use roles when native HTML elements are not available.', 'scalyn-qa-assistant' ); ?>
			</li>
		</ul>
		<p>
			<?php esc_html_e( 'The first rule of ARIA is: do not use ARIA if a native HTML element with the same semantics exists. For example, use a <button> element instead of a <div role="button">. Native elements already have built-in keyboard handling and screen reader support.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Color Contrast', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Sufficient color contrast between text and its background is essential for people with low vision or color blindness. The Web Content Accessibility Guidelines (WCAG) define minimum contrast ratios:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li>
				<strong><?php esc_html_e( 'Normal text (under 18px):', 'scalyn-qa-assistant' ); ?></strong>
				<?php esc_html_e( 'Minimum contrast ratio of 4.5:1.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Large text (18px and above, or 14px bold):', 'scalyn-qa-assistant' ); ?></strong>
				<?php esc_html_e( 'Minimum contrast ratio of 3:1.', 'scalyn-qa-assistant' ); ?>
			</li>
		</ul>
		<p>
			<?php esc_html_e( 'Use a contrast checker tool (such as the WebAIM Contrast Checker) to verify that your color combinations meet these standards. Common problems include light gray text on white backgrounds, and colored text on colored backgrounds without enough contrast.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Keyboard Navigation', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Many users navigate websites entirely with a keyboard, either by preference or necessity. Every interactive element on your site must be operable without a mouse. This includes links, buttons, forms, menus, and modal dialogs.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Key Principles', 'scalyn-qa-assistant' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'All interactive elements must be reachable by pressing the Tab key. The tab order should follow a logical reading sequence (left to right, top to bottom in most languages).', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Focused elements must have a visible focus indicator (outline or highlight). Never remove focus styles with CSS like outline: none without providing an alternative.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Custom interactive elements (built with divs or spans) need tabindex="0" to be focusable and JavaScript key event handlers for Enter and Space keys.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Dropdown menus should be navigable with arrow keys, and modal dialogs should trap focus within the dialog until it is closed.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Provide skip navigation links at the top of the page so keyboard users can jump directly to the main content without tabbing through the entire navigation menu.', 'scalyn-qa-assistant' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Semantic HTML', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Semantic HTML means using the correct HTML elements for their intended purpose. This provides built-in accessibility support and helps screen readers interpret your content correctly.', 'scalyn-qa-assistant' ); ?>
		</p>
		<ul>
			<li>
				<?php esc_html_e( 'Use <nav> for navigation menus, <main> for primary content, <header> for page headers, <footer> for footers, and <aside> for sidebar content.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Use <button> for actions and <a> for navigation. A button performs an action on the current page; a link navigates to another page or location.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Use heading tags (H1-H6) for content hierarchy, not for visual styling. See our Heading Structure article for details.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Use <ul> and <ol> for lists, <table> for tabular data, and <form> with proper <label> elements for input fields.', 'scalyn-qa-assistant' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Every form input must have an associated label. Use either a <label> element with a matching for attribute, or wrap the input inside a <label> element.', 'scalyn-qa-assistant' ); ?>
			</li>
		</ul>

		<h2><?php esc_html_e( 'Testing Accessibility', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'The best way to catch accessibility issues is to test regularly:', 'scalyn-qa-assistant' ); ?>
		</p>
		<ol>
			<li><?php esc_html_e( 'Navigate your site using only the keyboard. Can you reach every interactive element? Is the focus order logical?', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Use a screen reader (VoiceOver on Mac, NVDA on Windows) to experience your site as a blind user would.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Run automated tools like the browser Lighthouse audit, axe DevTools, or WAVE to catch common issues.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Check your color contrast ratios using a contrast checker tool.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Zoom your browser to 200% and verify that the layout remains usable and no content is clipped or hidden.', 'scalyn-qa-assistant' ); ?></li>
		</ol>

	</div>

</div>
