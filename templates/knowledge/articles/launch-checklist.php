<?php
/**
 * Knowledge Article: Launch Checklist.
 *
 * Pre-launch guide covering essential steps for SEO, analytics, SSL,
 * legal pages, and testing before going live.
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
		<h1><?php esc_html_e( 'Launch Checklist Guide', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<a href="<?php echo esc_url( $index_url ); ?>" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Knowledge Center', 'scalyn-qa-assistant' ); ?>
			</a>
		</div>
	</div>

	<div class="scalyn-card scalyn-card--article">

		<h2><?php esc_html_e( 'Why a Launch Checklist Matters', 'scalyn-qa-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'Launching a website is exciting, but rushing the process often leads to missed issues that can hurt your SEO, frustrate visitors, and create a poor first impression. A systematic pre-launch checklist ensures that every critical element is in place before your site goes live.', 'scalyn-qa-assistant' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'The Scalyn QA plugin automates many of these checks, but understanding the reasoning behind each one helps you make better decisions about your website quality.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'SEO Setup', 'scalyn-qa-assistant' ); ?></h2>

		<h3><?php esc_html_e( 'Install and Configure an SEO Plugin', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'An SEO plugin is essential for managing meta tags, sitemaps, and structured data. Popular options include Rank Math, Yoast SEO, and All in One SEO. Install one before launch and run through its setup wizard. Ensure that your XML sitemap is generated and accessible at a standard URL such as /sitemap_index.xml or /sitemap.xml.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Review Meta Tags on Key Pages', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Check that your homepage, about page, contact page, and main service or product pages all have custom meta titles and descriptions. Relying on auto-generated metadata for your most important pages is a missed opportunity. Write unique, keyword-optimized titles and compelling descriptions for each.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Submit Your Sitemap', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'After launching, submit your XML sitemap to Google Search Console and Bing Webmaster Tools. This tells search engines about all the pages on your site and speeds up the indexing process.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Analytics and Tracking', 'scalyn-qa-assistant' ); ?></h2>

		<h3><?php esc_html_e( 'Set Up Google Analytics 4', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Google Analytics 4 (GA4) is the current standard for tracking website visitors. Create a GA4 property, get your Measurement ID (starting with G-), and add the tracking code to your site. You can use a plugin like MonsterInsights or Site Kit, or add the code manually to your theme header.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Consider Google Tag Manager', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Google Tag Manager (GTM) is optional but recommended for sites that need to manage multiple tracking scripts. It provides a centralized interface for deploying analytics, marketing, and conversion tracking tags without modifying your website code each time.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'SSL and Security', 'scalyn-qa-assistant' ); ?></h2>

		<h3><?php esc_html_e( 'Enable SSL/HTTPS', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'SSL encryption is no longer optional. It protects your visitors data, is a Google ranking factor, and is required by most modern browsers for full functionality. Ensure your SSL certificate is installed, your site URL starts with https://, and all internal links and resources use HTTPS. Most hosting providers offer free SSL certificates through Lets Encrypt.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Check for Mixed Content', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Mixed content occurs when an HTTPS page loads resources (images, scripts, stylesheets) over HTTP. This triggers browser security warnings and can break page functionality. Use your browser developer console to identify and fix any mixed content warnings.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Legal Pages', 'scalyn-qa-assistant' ); ?></h2>

		<h3><?php esc_html_e( 'Privacy Policy', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'A privacy policy is legally required if you collect any user data, including analytics cookies, contact form submissions, or newsletter signups. WordPress includes a built-in Privacy Policy page generator under Settings > Privacy. Customize it to accurately describe your data practices.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Contact Information', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Every business website should have a contact page with clear ways for visitors to reach you. This builds trust with both users and search engines. Include at minimum an email address or contact form, and optionally your physical address, phone number, and business hours.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Functional Testing', 'scalyn-qa-assistant' ); ?></h2>

		<h3><?php esc_html_e( 'Check All Links', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Broken links frustrate visitors and send negative signals to search engines. Use the Scalyn QA link checker to scan your pages for broken internal and external links. Fix or remove any broken links before launch.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Test Forms and Interactive Elements', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Submit every form on your site (contact forms, newsletter signups, search) to verify they work correctly. Check that confirmation messages appear, email notifications are sent, and form data is stored properly.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h3><?php esc_html_e( 'Favicon and Branding', 'scalyn-qa-assistant' ); ?></h3>
		<p>
			<?php esc_html_e( 'Set a site icon (favicon) under Appearance > Customize > Site Identity. This small image appears in browser tabs, bookmarks, and mobile home screens. A missing favicon looks unprofessional and is a sign of an incomplete website.', 'scalyn-qa-assistant' ); ?>
		</p>

		<h2><?php esc_html_e( 'Post-Launch Steps', 'scalyn-qa-assistant' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Submit your sitemap to Google Search Console.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Verify that analytics tracking is capturing data by checking real-time reports.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Test your site on multiple devices and browsers (Chrome, Firefox, Safari, Edge).', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Run a page speed test using Google PageSpeed Insights and address critical issues.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Monitor search engine indexing over the first few weeks and fix any crawl errors.', 'scalyn-qa-assistant' ); ?></li>
			<li><?php esc_html_e( 'Set up automated backups and security monitoring.', 'scalyn-qa-assistant' ); ?></li>
		</ol>

	</div>

</div>
