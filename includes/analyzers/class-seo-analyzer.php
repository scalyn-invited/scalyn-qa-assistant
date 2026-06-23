<?php
/**
 * SEO Analyzer.
 *
 * Performs SEO-related checks on a post including meta title, description,
 * featured image, alt text, and link analysis.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class SEO_Analyzer
 *
 * Analyzes posts for SEO best practices including meta tags, images, and links.
 *
 * @since 1.0.0
 */
class SEO_Analyzer implements Analyzer_Interface {

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'seo';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'SEO Analyzer', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'seo';
	}

	/**
	 * Run all SEO checks on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content = $this->get_rendered_content( $post_id );
		$parser  = new HTML_Parser( $content );

		$checks   = array();
		$checks[] = $this->check_meta_title( $post_id );
		$checks[] = $this->check_meta_description( $post_id );
		$checks[] = $this->check_featured_image( $post_id );
		$checks[] = $this->check_image_alt_text( $parser );
		$checks[] = $this->check_internal_links( $parser );
		$checks[] = $this->check_external_links( $parser );
		$checks[] = $this->check_canonical_url( $post_id );
		$checks[] = $this->check_noindex_nofollow( $post_id );
		$checks[] = $this->check_open_graph( $post_id );
		$checks[] = $this->check_focus_keyword( $post_id, $parser, $content );
		$checks[] = $this->check_schema_markup( $content );
		$checks[] = $this->check_seo_score( $post_id );
		$checks[] = $this->check_social_image_dimensions( $post_id );

		return $checks;
	}

	/**
	 * Check if a meta title is set.
	 *
	 * Checks Rank Math, Yoast, AIOSEO, and falls back to the post title.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_meta_title( int $post_id ): Check_Item {
		$tooltip = __( 'The meta title appears in search results and browser tabs. Set it in your SEO plugin (Yoast, Rank Math, or AIOSEO) under the post editor. Aim for 50-60 characters.', 'scalyn-qa-assistant' );
		$source  = '';
		$title   = '';

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rank_math_title = get_post_meta( $post_id, 'rank_math_title', true );
			if ( is_string( $rank_math_title ) && '' !== $rank_math_title ) {
				$title  = $rank_math_title;
				$source = 'Rank Math';
			}
		}

		// Yoast.
		if ( '' === $title && defined( 'WPSEO_VERSION' ) ) {
			$yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
			if ( is_string( $yoast_title ) && '' !== $yoast_title ) {
				$title  = $yoast_title;
				$source = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $title && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo_title = get_post_meta( $post_id, '_aioseo_title', true );
			if ( is_string( $aioseo_title ) && '' !== $aioseo_title ) {
				$title  = $aioseo_title;
				$source = 'AIOSEO';
			}
		}

		// Fallback: post title.
		if ( '' === $title ) {
			$post_title = get_the_title( $post_id );
			if ( '' !== $post_title ) {
				$char_count = mb_strlen( $post_title );
				return new Check_Item(
					id:        'meta_title_exists',
					label:     __( 'Meta Title', 'scalyn-qa-assistant' ),
					status:    'warning',
					message:   sprintf(
						/* translators: %d: character count */
						__( 'Using the page title as meta title (%d characters). Set a custom SEO title in your SEO plugin settings below the post editor.', 'scalyn-qa-assistant' ),
						$char_count,
					),
					category:  'seo',
					severity:  'warning',
					quick_fix: 'generate_ai_meta',
					tooltip:   $tooltip,
					details:   array(
						'title'       => $post_title,
						'char_count'  => $char_count,
						'source'      => 'post_title',
					),
				);
			}

			return new Check_Item(
				id:        'meta_title_exists',
				label:     __( 'Meta Title', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'No meta title found. Use Generate with AI or set one in your SEO plugin panel.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'critical',
				quick_fix: 'generate_ai_meta',
				tooltip:   $tooltip,
			);
		}

		$char_count = mb_strlen( $title );

		return new Check_Item(
			id:        'meta_title_exists',
			label:     __( 'Meta Title', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: 1: source plugin name, 2: character count */
				__( 'Meta title set via %1$s (%2$d characters).', 'scalyn-qa-assistant' ),
				$source,
				$char_count,
			),
			category:  'seo',
			severity:  'info',
			quick_fix: 'regenerate_ai_meta',
			tooltip:   $tooltip,
			details:   array(
				'title'      => $title,
				'char_count' => $char_count,
				'source'     => $source,
			),
		);
	}

	/**
	 * Check if a meta description is set.
	 *
	 * Checks Rank Math, Yoast, AIOSEO in order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_meta_description( int $post_id ): Check_Item {
		$tooltip     = __( 'The meta description appears below the title in search results. Set it in your SEO plugin (Yoast, Rank Math, or AIOSEO) under the post editor. Aim for 120-160 characters.', 'scalyn-qa-assistant' );
		$source      = '';
		$description = '';

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rank_math_desc = get_post_meta( $post_id, 'rank_math_description', true );
			if ( is_string( $rank_math_desc ) && '' !== $rank_math_desc ) {
				$description = $rank_math_desc;
				$source      = 'Rank Math';
			}
		}

		// Yoast.
		if ( '' === $description && defined( 'WPSEO_VERSION' ) ) {
			$yoast_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			if ( is_string( $yoast_desc ) && '' !== $yoast_desc ) {
				$description = $yoast_desc;
				$source      = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $description && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo_desc = get_post_meta( $post_id, '_aioseo_description', true );
			if ( is_string( $aioseo_desc ) && '' !== $aioseo_desc ) {
				$description = $aioseo_desc;
				$source      = 'AIOSEO';
			}
		}

		if ( '' === $description ) {
			return new Check_Item(
				id:        'meta_description_exists',
				label:     __( 'Meta Description', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'No meta description found. Use Generate with AI or add one in your SEO plugin panel.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'critical',
				quick_fix: 'generate_ai_meta',
				tooltip:   $tooltip,
			);
		}

		$char_count = mb_strlen( $description );

		return new Check_Item(
			id:        'meta_description_exists',
			label:     __( 'Meta Description', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: 1: source plugin name, 2: character count */
				__( 'Meta description set via %1$s (%2$d characters).', 'scalyn-qa-assistant' ),
				$source,
				$char_count,
			),
			category:  'seo',
			severity:  'info',
			quick_fix: 'regenerate_ai_meta',
			tooltip:   $tooltip,
			details:   array(
				'description' => $description,
				'char_count'  => $char_count,
				'source'      => $source,
			),
		);
	}

	/**
	 * Check if a featured image is set.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_featured_image( int $post_id ): Check_Item {
		$tooltip = __( 'Featured images appear in social media shares and search results. Set one in the post editor via the "Featured Image" panel in the right sidebar.', 'scalyn-qa-assistant' );

		// Build the list of AI-generated images for this post.
		$ai_history   = get_post_meta( $post_id, '_scalyn_qa_ai_featured_images', true );
		$ai_history   = is_array( $ai_history ) ? $ai_history : array();

		// Backfill: find AI-generated images attached to this post by filename pattern.
		if ( empty( $ai_history ) ) {
			$attached = get_posts( array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_parent'    => $post_id,
				'posts_per_page' => 20,
				'post_status'    => 'inherit',
				'fields'         => 'ids',
			) );
			foreach ( $attached as $att_id ) {
				$file = basename( get_attached_file( $att_id ) ?: '' );
				if ( str_contains( $file, '-ai-featured' ) ) {
					$ai_history[] = $att_id;
				}
			}
			if ( ! empty( $ai_history ) ) {
				update_post_meta( $post_id, '_scalyn_qa_ai_featured_images', $ai_history );
			}
		}

		$ai_images = array();
		foreach ( $ai_history as $aid ) {
			$aid = (int) $aid;
			$url = wp_get_attachment_image_url( $aid, 'medium' );
			if ( $url ) {
				$ai_images[] = array(
					'attachment_id' => $aid,
					'url'           => $url,
					'filename'      => basename( get_attached_file( $aid ) ?: '' ),
				);
			}
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$thumb_id  = (int) get_post_thumbnail_id( $post_id );
			$thumb_url = get_the_post_thumbnail_url( $post_id, 'medium' );
			$full_url  = get_the_post_thumbnail_url( $post_id, 'full' );
			$filename  = $thumb_id > 0 ? basename( get_attached_file( $thumb_id ) ?: '' ) : '';

			return new Check_Item(
				id:        'featured_image_exists',
				label:     __( 'Featured Image', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Featured image is set.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: 'regenerate_ai_featured_image',
				tooltip:   $tooltip,
				details:   array(
					'thumbnail_url'   => $thumb_url ?: '',
					'full_url'        => $full_url ?: '',
					'attachment_id'   => $thumb_id,
					'filename'        => $filename,
					'ai_images'       => $ai_images,
				),
			);
		}

		return new Check_Item(
			id:        'featured_image_exists',
			label:     __( 'Featured Image', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   __( 'No featured image set. Use "Generate with AI" to create one, or open the post editor and add one manually.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'critical',
			quick_fix: 'generate_ai_featured_image',
			details:   array(
				'ai_images' => $ai_images,
			),
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check that all images have alt text.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_image_alt_text( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'Alt text improves accessibility and SEO. Edit each image in the post editor or Media Library and fill in the "Alternative Text" field.', 'scalyn-qa-assistant' );
		$images  = $parser->get_images();

		if ( 0 === count( $images ) ) {
			return new Check_Item(
				id:        'image_alt_text',
				label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No images found in the content — not applicable.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$total_images = count( $images );
		$missing_alt  = array();

		foreach ( $images as $image ) {
			if ( ! $image['has_alt'] ) {
				$src = $image['src'];

				if ( mb_strlen( $src ) > 80 ) {
					$src = mb_substr( $src, 0, 80 ) . '...';
				}

				$missing_alt[] = $src;
			}
		}

		$missing_count = count( $missing_alt );

		if ( 0 === $missing_count ) {
			return new Check_Item(
				id:        'image_alt_text',
				label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: total image count */
					__( 'All %d images have alt text.', 'scalyn-qa-assistant' ),
					$total_images,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		if ( $missing_count === $total_images ) {
			return new Check_Item(
				id:        'image_alt_text',
				label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   sprintf(
					/* translators: %d: number of images */
					__( 'None of the %d images have alt text. Use Quick Fix to apply image titles as alt text, or Generate with AI.', 'scalyn-qa-assistant' ),
					$total_images,
				),
				category:  'seo',
				severity:  'warning',
				quick_fix: 'use_titles_as_alt',
				tooltip:   $tooltip,
				details:   array( 'missing_alt_images' => $missing_alt ),
			);
		}

		return new Check_Item(
			id:        'image_alt_text',
			label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: 1: missing count, 2: total count */
				__( '%1$d of %2$d images are missing alt text. Use Quick Fix to apply image titles as alt text, or Generate with AI.', 'scalyn-qa-assistant' ),
				$missing_count,
				$total_images,
			),
			category:  'seo',
			severity:  'warning',
			quick_fix: 'use_titles_as_alt',
			tooltip:   $tooltip,
			details:   array( 'missing_alt_images' => $missing_alt ),
		);
	}

	/**
	 * Check for internal links in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_internal_links( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'Internal links help search engines discover your other pages and keep visitors on your site. Add links to related posts or pages in the post editor.', 'scalyn-qa-assistant' );
		$links   = $this->categorize_links( $parser );

		$internal_count = count( $links['internal'] );

		if ( $internal_count > 0 ) {
			return new Check_Item(
				id:        'internal_links_present',
				label:     __( 'Internal Links', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: number of internal links */
					_n(
						'%d internal link found.',
						'%d internal links found.',
						$internal_count,
						'scalyn-qa-assistant',
					),
					$internal_count,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'internal_links' => $links['internal'] ),
			);
		}

		return new Check_Item(
			id:        'internal_links_present',
			label:     __( 'Internal Links', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No internal links found. Add links to related pages or posts in the post editor to improve SEO and user navigation.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check for external links in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_external_links( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'External links to authoritative sources build trust and can improve SEO. Add them naturally within your content in the post editor.', 'scalyn-qa-assistant' );
		$links   = $this->categorize_links( $parser );

		$external_count = count( $links['external'] );

		if ( $external_count > 0 ) {
			return new Check_Item(
				id:        'external_links_present',
				label:     __( 'External Links', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: number of external links */
					_n(
						'%d external link found.',
						'%d external links found.',
						$external_count,
						'scalyn-qa-assistant',
					),
					$external_count,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'external_links' => $links['external'] ),
			);
		}

		return new Check_Item(
			id:        'external_links_present',
			label:     __( 'External Links', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'No external links found. This is fine but linking to authoritative sources may help.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'info',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Categorize all links as internal or external using HTML_Parser.
	 *
	 * Results are cached per request to avoid re-parsing.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return array{internal: string[], external: string[]}
	 */
	private function categorize_links( HTML_Parser $parser ): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$internal   = array();
		$external   = array();
		$all_links  = $parser->get_links();

		if ( 0 === count( $all_links ) ) {
			$cache = array(
				'internal' => $internal,
				'external' => $external,
			);
			return $cache;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $all_links as $link ) {
			$url = trim( $link['url'] );

			// Skip non-HTTP links.
			if ( str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) || str_starts_with( $url, '#' ) || str_starts_with( $url, 'javascript:' ) ) {
				continue;
			}

			$parsed_host = wp_parse_url( $url, PHP_URL_HOST );

			// Relative URLs are internal.
			if ( null === $parsed_host || false === $parsed_host ) {
				$internal[] = $url;
				continue;
			}

			if ( is_string( $site_host ) && strcasecmp( $parsed_host, $site_host ) === 0 ) {
				$internal[] = $url;
			} else {
				$external[] = $url;
			}
		}

		$cache = array(
			'internal' => $internal,
			'external' => $external,
		);

		return $cache;
	}

	/**
	 * Check if a canonical URL is properly configured.
	 *
	 * Checks for explicit canonical overrides in Rank Math, Yoast, and AIOSEO,
	 * and verifies that an SEO plugin or WordPress core is generating the tag.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_canonical_url( int $post_id ): Check_Item {
		$tooltip = __( 'A canonical URL tells search engines which version of a page is the primary one, preventing duplicate content issues. Your SEO plugin or WordPress generates this automatically.', 'scalyn-qa-assistant' );

		$canonical = '';
		$source    = '';

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_canonical = get_post_meta( $post_id, 'rank_math_canonical_url', true );
			if ( is_string( $rm_canonical ) && '' !== $rm_canonical ) {
				$canonical = $rm_canonical;
				$source    = 'Rank Math';
			}
		}

		// Yoast.
		if ( '' === $canonical && defined( 'WPSEO_VERSION' ) ) {
			$yoast_canonical = get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
			if ( is_string( $yoast_canonical ) && '' !== $yoast_canonical ) {
				$canonical = $yoast_canonical;
				$source    = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $canonical && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo_canonical = get_post_meta( $post_id, '_aioseo_canonical_url', true );
			if ( is_string( $aioseo_canonical ) && '' !== $aioseo_canonical ) {
				$canonical = $aioseo_canonical;
				$source    = 'AIOSEO';
			}
		}

		// Custom canonical is explicitly set.
		if ( '' !== $canonical ) {
			$permalink           = get_permalink( $post_id );
			$is_self_referencing = is_string( $permalink ) && rtrim( $canonical, '/' ) === rtrim( $permalink, '/' );

			return new Check_Item(
				id:        'canonical_url',
				label:     __( 'Canonical URL', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   $is_self_referencing
					? sprintf(
						/* translators: %s: SEO plugin name */
						__( 'Self-referencing canonical URL set via %s.', 'scalyn-qa-assistant' ),
						$source,
					)
					: sprintf(
						/* translators: 1: SEO plugin name, 2: canonical URL */
						__( 'Custom canonical URL set via %1$s: %2$s', 'scalyn-qa-assistant' ),
						$source,
						$canonical,
					),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array(
					'canonical'          => $canonical,
					'source'             => $source,
					'is_self_referencing' => $is_self_referencing,
				),
			);
		}

		// No explicit canonical — check if an SEO plugin auto-generates it.
		$has_seo_plugin = defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' );

		if ( $has_seo_plugin ) {
			return new Check_Item(
				id:        'canonical_url',
				label:     __( 'Canonical URL', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Canonical URL auto-generated by your SEO plugin (defaults to page permalink).', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		// WordPress core generates canonical via rel_canonical() since WP 2.9.
		return new Check_Item(
			id:        'canonical_url',
			label:     __( 'Canonical URL', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'Canonical URL generated by WordPress core. Install an SEO plugin for more control over canonicalization.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'info',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check if the page is accidentally set to noindex or nofollow.
	 *
	 * Reads robots directives from Rank Math, Yoast, and AIOSEO.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_noindex_nofollow( int $post_id ): Check_Item {
		$tooltip = __( 'Noindex tells search engines not to index a page. Nofollow tells them not to follow links. Accidentally setting these can remove pages from search results. Check your SEO plugin settings for this post.', 'scalyn-qa-assistant' );

		$is_noindex  = false;
		$is_nofollow = false;
		$source      = '';

		// Rank Math — stores robots as a serialized array.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_robots = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( is_array( $rm_robots ) ) {
				$is_noindex  = in_array( 'noindex', $rm_robots, true );
				$is_nofollow = in_array( 'nofollow', $rm_robots, true );
				if ( $is_noindex || $is_nofollow ) {
					$source = 'Rank Math';
				}
			}
		}

		// Yoast.
		if ( '' === $source && defined( 'WPSEO_VERSION' ) ) {
			$yoast_noindex  = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
			$yoast_nofollow = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true );
			if ( '1' === $yoast_noindex || '1' === $yoast_nofollow ) {
				$is_noindex  = '1' === $yoast_noindex;
				$is_nofollow = '1' === $yoast_nofollow;
				$source      = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $source && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo_noindex  = get_post_meta( $post_id, '_aioseo_noindex', true );
			$aioseo_nofollow = get_post_meta( $post_id, '_aioseo_nofollow', true );
			if ( '1' === $aioseo_noindex || '1' === $aioseo_nofollow ) {
				$is_noindex  = '1' === $aioseo_noindex;
				$is_nofollow = '1' === $aioseo_nofollow;
				$source      = 'AIOSEO';
			}
		}

		if ( $is_noindex && $is_nofollow ) {
			return new Check_Item(
				id:        'noindex_nofollow',
				label:     __( 'Noindex / Nofollow', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   sprintf(
					/* translators: %s: SEO plugin name */
					__( 'Page is set to noindex AND nofollow via %s. It will not appear in search results and links will not be followed. Remove these in the SEO plugin settings if unintentional.', 'scalyn-qa-assistant' ),
					$source,
				),
				category:  'seo',
				severity:  'critical',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array(
					'noindex'  => true,
					'nofollow' => true,
					'source'   => $source,
				),
			);
		}

		if ( $is_noindex ) {
			return new Check_Item(
				id:        'noindex_nofollow',
				label:     __( 'Noindex / Nofollow', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %s: SEO plugin name */
					__( 'Page is set to noindex via %s. It will not appear in search results. Remove noindex in the SEO plugin settings if this is unintentional.', 'scalyn-qa-assistant' ),
					$source,
				),
				category:  'seo',
				severity:  'warning',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array(
					'noindex'  => true,
					'nofollow' => false,
					'source'   => $source,
				),
			);
		}

		if ( $is_nofollow ) {
			return new Check_Item(
				id:        'noindex_nofollow',
				label:     __( 'Noindex / Nofollow', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   sprintf(
					/* translators: %s: SEO plugin name */
					__( 'Page is set to nofollow via %s. Search engines will not follow links on this page.', 'scalyn-qa-assistant' ),
					$source,
				),
				category:  'seo',
				severity:  'warning',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array(
					'noindex'  => false,
					'nofollow' => true,
					'source'   => $source,
				),
			);
		}

		return new Check_Item(
			id:        'noindex_nofollow',
			label:     __( 'Noindex / Nofollow', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'Page is indexable and links are followable.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'info',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check if Open Graph tags are configured for social sharing.
	 *
	 * Verifies an SEO plugin is active to generate OG tags and checks
	 * for the presence of a social share image.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_open_graph( int $post_id ): Check_Item {
		$tooltip = __( 'Open Graph tags control how your page appears when shared on social media (Facebook, LinkedIn, Twitter). They are typically generated by SEO plugins using your meta title, description, and featured image.', 'scalyn-qa-assistant' );

		$has_rank_math = defined( 'RANK_MATH_VERSION' );
		$has_yoast     = defined( 'WPSEO_VERSION' );
		$has_aioseo    = defined( 'AIOSEO_VERSION' );

		// Determine which SEO plugin is active and check for custom OG image.
		$og_image = '';
		$source   = '';

		if ( $has_rank_math ) {
			$source   = 'Rank Math';
			$og_image = get_post_meta( $post_id, 'rank_math_facebook_image', true );
		} elseif ( $has_yoast ) {
			$source   = 'Yoast SEO';
			$og_image = get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true );
		} elseif ( $has_aioseo ) {
			$source   = 'AIOSEO';
			$og_image = get_post_meta( $post_id, '_aioseo_og_image', true );
		}

		if ( '' !== $source ) {
			$has_image = has_post_thumbnail( $post_id ) || ( is_string( $og_image ) && '' !== $og_image );

			if ( ! $has_image ) {
				return new Check_Item(
					id:        'open_graph_tags',
					label:     __( 'Open Graph Tags', 'scalyn-qa-assistant' ),
					status:    'warning',
					message:   sprintf(
						/* translators: %s: SEO plugin name */
						__( 'OG tags auto-generated by %s, but no featured image or custom OG image set. Social shares may lack a preview image.', 'scalyn-qa-assistant' ),
						$source,
					),
					category:  'seo',
					severity:  'warning',
					quick_fix: null,
					tooltip:   $tooltip,
				);
			}

			return new Check_Item(
				id:        'open_graph_tags',
				label:     __( 'Open Graph Tags', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %s: SEO plugin name */
					__( 'Open Graph tags managed by %s.', 'scalyn-qa-assistant' ),
					$source,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		// No SEO plugin detected.
		return new Check_Item(
			id:        'open_graph_tags',
			label:     __( 'Open Graph Tags', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   __( 'No SEO plugin detected to generate Open Graph tags. Social media shares will use generic defaults. Install Rank Math or Yoast SEO for proper social sharing previews.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'critical',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check if a focus keyword is set and used in key locations.
	 *
	 * Supports: Rank Math, Yoast, AIOSEO, SEOPress, The SEO Framework.
	 *
	 * @since 1.3.0
	 *
	 * @param int         $post_id The post ID.
	 * @param HTML_Parser $parser  The HTML parser.
	 * @param string      $content The rendered content.
	 * @return Check_Item
	 */
	private function check_focus_keyword( int $post_id, HTML_Parser $parser, string $content ): Check_Item {
		$tooltip = __( 'A focus keyword tells search engines what your page is about. Set it in your SEO plugin and use it in the title, H1, first paragraph, URL, and meta description.', 'scalyn-qa-assistant' );

		// Read focus keyword from the active SEO plugin.
		$keyword = '';
		$source  = '';

		// Rank Math.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_kw = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( is_string( $rm_kw ) && '' !== $rm_kw ) {
				$keyword = explode( ',', $rm_kw )[0]; // First keyword.
				$source  = 'Rank Math';
			}
		}

		// Yoast.
		if ( '' === $keyword && defined( 'WPSEO_VERSION' ) ) {
			$yoast_kw = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			if ( is_string( $yoast_kw ) && '' !== $yoast_kw ) {
				$keyword = $yoast_kw;
				$source  = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $keyword && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo_kw = get_post_meta( $post_id, '_aioseo_keyphrases', true );
			if ( is_string( $aioseo_kw ) && '' !== $aioseo_kw ) {
				$parsed = json_decode( $aioseo_kw, true );
				if ( is_array( $parsed ) && ! empty( $parsed['focus'] ) && ! empty( $parsed['focus']['keyphrase'] ) ) {
					$keyword = $parsed['focus']['keyphrase'];
					$source  = 'AIOSEO';
				}
			}
		}

		// SEOPress.
		if ( '' === $keyword && defined( 'SEOPRESS_VERSION' ) ) {
			$sp_kw = get_post_meta( $post_id, '_seopress_analysis_target_kw', true );
			if ( is_string( $sp_kw ) && '' !== $sp_kw ) {
				$keyword = explode( ',', $sp_kw )[0];
				$source  = 'SEOPress';
			}
		}

		if ( '' === $keyword ) {
			return new Check_Item(
				id:        'focus_keyword',
				label:     __( 'Focus Keyword', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'No focus keyword set. Add one in your SEO plugin or use "Generate with AI".', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'warning',
				quick_fix: 'generate_ai_keyword',
				tooltip:   $tooltip,
			);
		}

		// Check keyword placement in key locations.
		$keyword_lower = mb_strtolower( $keyword );
		$placements    = array();
		$missing       = array();

		// Title.
		$title = mb_strtolower( get_the_title( $post_id ) );
		if ( str_contains( $title, $keyword_lower ) ) {
			$placements[] = __( 'Page Title', 'scalyn-qa-assistant' );
		} else {
			$missing[] = __( 'Page Title', 'scalyn-qa-assistant' );
		}

		// H1 — check content headings first, then fall back to post title (most themes render it as H1).
		$headings = $parser->get_headings();
		$in_h1    = false;
		foreach ( $headings as $h ) {
			if ( 1 === $h['level'] && str_contains( mb_strtolower( $h['text'] ), $keyword_lower ) ) {
				$in_h1 = true;
				break;
			}
		}
		if ( ! $in_h1 ) {
			// Most themes render the post title as H1 — check title as H1 proxy.
			if ( str_contains( $title, $keyword_lower ) ) {
				$in_h1 = true;
			}
		}
		if ( $in_h1 ) {
			$placements[] = __( 'H1', 'scalyn-qa-assistant' );
		} else {
			$missing[] = __( 'H1', 'scalyn-qa-assistant' );
		}

		// First paragraph.
		$plain = mb_strtolower( wp_strip_all_tags( $content ) );
		$first_300 = mb_substr( $plain, 0, 300 );
		if ( str_contains( $first_300, $keyword_lower ) ) {
			$placements[] = __( 'First paragraph', 'scalyn-qa-assistant' );
		} else {
			$missing[] = __( 'First paragraph', 'scalyn-qa-assistant' );
		}

		// URL/slug.
		$slug = get_post_field( 'post_name', $post_id );
		if ( is_string( $slug ) && str_contains( mb_strtolower( $slug ), str_replace( ' ', '-', $keyword_lower ) ) ) {
			$placements[] = __( 'URL slug', 'scalyn-qa-assistant' );
		} else {
			$missing[] = __( 'URL slug', 'scalyn-qa-assistant' );
		}

		// Meta description (only from active SEO plugin).
		$meta_desc = '';
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$meta_desc = get_post_meta( $post_id, 'rank_math_description', true ) ?: '';
		}
		if ( '' === $meta_desc && defined( 'WPSEO_VERSION' ) ) {
			$meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ?: '';
		}
		if ( '' === $meta_desc && defined( 'AIOSEO_VERSION' ) ) {
			$meta_desc = get_post_meta( $post_id, '_aioseo_description', true ) ?: '';
		}
		if ( is_string( $meta_desc ) && str_contains( mb_strtolower( $meta_desc ), $keyword_lower ) ) {
			$placements[] = __( 'Meta description', 'scalyn-qa-assistant' );
		} else {
			$missing[] = __( 'Meta description', 'scalyn-qa-assistant' );
		}

		$total   = count( $placements ) + count( $missing );
		$found   = count( $placements );
		$status  = $found >= 4 ? 'pass' : ( $found >= 2 ? 'warning' : 'fail' );

		return new Check_Item(
			id:        'focus_keyword',
			label:     __( 'Focus Keyword', 'scalyn-qa-assistant' ),
			status:    $status,
			message:   sprintf(
				/* translators: 1: keyword, 2: source, 3: found count, 4: total locations */
				__( '"%1$s" (via %2$s) found in %3$d of %4$d key locations.', 'scalyn-qa-assistant' ),
				esc_html( $keyword ),
				$source,
				$found,
				$total,
			),
			category:  'seo',
			severity:  'pass' === $status ? 'info' : 'warning',
			quick_fix: 'pass' !== $status ? 'regenerate_ai_keyword' : null,
			tooltip:   $tooltip,
			details:   array(
				'keyword'    => $keyword,
				'source'     => $source,
				'found_in'   => $placements,
				'missing_in' => $missing,
			),
		);
	}

	/**
	 * Check for Schema/Structured Data markup on the page.
	 *
	 * Detects JSON-LD scripts and microdata in the rendered content,
	 * plus common schema plugins.
	 *
	 * @since 1.3.0
	 *
	 * @param string $content The rendered HTML content.
	 * @return Check_Item
	 */
	private function check_schema_markup( string $content ): Check_Item {
		$tooltip = __( 'Schema markup helps search engines understand your content and can enable rich snippets in search results. Most SEO plugins add basic schema automatically.', 'scalyn-qa-assistant' );

		$found_types = array();

		// Check for JSON-LD scripts.
		if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $content, $matches ) ) {
			foreach ( $matches[1] as $json_str ) {
				$data = json_decode( $json_str, true );
				if ( is_array( $data ) && ! empty( $data['@type'] ) ) {
					$found_types[] = is_array( $data['@type'] ) ? implode( ', ', $data['@type'] ) : $data['@type'];
				}
			}
		}

		// Check for microdata.
		if ( preg_match( '/itemtype=["\']https?:\/\/schema\.org\//i', $content ) ) {
			$found_types[] = __( 'Microdata detected', 'scalyn-qa-assistant' );
		}

		// Check if SEO plugin or schema plugin is active (they auto-add schema).
		$has_schema_source = defined( 'RANK_MATH_VERSION' )
			|| defined( 'WPSEO_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| defined( 'THE_SEO_FRAMEWORK_VERSION' );

		if ( ! empty( $found_types ) ) {
			return new Check_Item(
				id:        'schema_markup',
				label:     __( 'Schema Markup', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %s: comma-separated schema types */
					__( 'Schema markup found: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', array_unique( $found_types ) ) ),
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'schema_types' => $found_types ),
			);
		}

		if ( $has_schema_source ) {
			return new Check_Item(
				id:        'schema_markup',
				label:     __( 'Schema Markup', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'An SEO plugin is active and likely generates schema markup automatically on the frontend.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'schema_markup',
			label:     __( 'Schema Markup', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No schema markup detected. Install an SEO plugin or add JSON-LD structured data to improve rich snippet eligibility.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Read and display the SEO score from the active SEO plugin.
	 *
	 * Supports: Rank Math, Yoast, AIOSEO, SEOPress.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The post ID.
	 * @return Check_Item
	 */
	private function check_seo_score( int $post_id ): Check_Item {
		$tooltip = __( 'This is the SEO score from your SEO plugin. It reflects how well optimized the content is for search engines according to the plugin\'s analysis.', 'scalyn-qa-assistant' );

		$score  = 0;
		$source = '';

		// Rank Math (0-100).
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_score = get_post_meta( $post_id, 'rank_math_seo_score', true );
			if ( is_numeric( $rm_score ) && (int) $rm_score > 0 ) {
				$score  = (int) $rm_score;
				$source = 'Rank Math';
			}
		}

		// Yoast (0-100, stored as "linkdex").
		if ( 0 === $score && defined( 'WPSEO_VERSION' ) ) {
			$yoast_score = get_post_meta( $post_id, '_yoast_wpseo_linkdex', true );
			if ( is_numeric( $yoast_score ) && (int) $yoast_score > 0 ) {
				$score  = (int) $yoast_score;
				$source = 'Yoast SEO';
			}
		}

		// SEOPress.
		if ( 0 === $score && defined( 'SEOPRESS_VERSION' ) ) {
			$sp_data = get_post_meta( $post_id, '_seopress_analysis_data', true );
			if ( is_array( $sp_data ) && isset( $sp_data['score'] ) ) {
				$score  = (int) $sp_data['score'];
				$source = 'SEOPress';
			}
		}

		if ( 0 === $score ) {
			return new Check_Item(
				id:        'seo_score',
				label:     __( 'SEO Plugin Score', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No SEO score available. Analyze the page in your SEO plugin to generate one.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$status = $score >= 80 ? 'pass' : ( $score >= 50 ? 'warning' : 'fail' );

		return new Check_Item(
			id:        'seo_score',
			label:     __( 'SEO Plugin Score', 'scalyn-qa-assistant' ),
			status:    $status,
			message:   sprintf(
				/* translators: 1: score, 2: SEO plugin name */
				__( '%1$d/100 (via %2$s).', 'scalyn-qa-assistant' ),
				$score,
				$source,
			),
			category:  'seo',
			severity:  'pass' === $status ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'score' => $score, 'source' => $source ),
		);
	}

	/**
	 * Check OG/social share image dimensions (minimum 1200x630).
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The post ID.
	 * @return Check_Item
	 */
	private function check_social_image_dimensions( int $post_id ): Check_Item {
		$tooltip = __( 'Social platforms recommend images of at least 1200x630 pixels for optimal display in shared links. Check your featured image or OG image in the SEO plugin.', 'scalyn-qa-assistant' );

		// Get the OG image or featured image.
		$image_id = 0;

		// Check SEO plugin OG image first (only from active plugin).
		$og_image_id = '';
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$og_image_id = get_post_meta( $post_id, 'rank_math_facebook_image_id', true ) ?: '';
		}
		if ( '' === $og_image_id && defined( 'WPSEO_VERSION' ) ) {
			$og_image_id = get_post_meta( $post_id, '_yoast_wpseo_opengraph-image-id', true ) ?: '';
		}

		if ( is_numeric( $og_image_id ) && (int) $og_image_id > 0 ) {
			$image_id = (int) $og_image_id;
		}

		// Fallback to featured image.
		if ( 0 === $image_id ) {
			$image_id = (int) get_post_thumbnail_id( $post_id );
		}

		if ( 0 === $image_id ) {
			return new Check_Item(
				id:        'social_image_dimensions',
				label:     __( 'Social Share Image', 'scalyn-qa-assistant' ),
				status:    'warning',
				message:   __( 'No featured image or OG image set. Social shares will lack a preview image.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'warning',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$metadata = wp_get_attachment_metadata( $image_id );

		if ( ! is_array( $metadata ) || empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
			return new Check_Item(
				id:        'social_image_dimensions',
				label:     __( 'Social Share Image', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Social share image is set but dimensions could not be verified.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$width  = (int) $metadata['width'];
		$height = (int) $metadata['height'];
		$pass   = $width >= 1200 && $height >= 630;

		return new Check_Item(
			id:        'social_image_dimensions',
			label:     __( 'Social Share Image', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Social image is %1$dx%2$d — meets the 1200x630 minimum.', 'scalyn-qa-assistant' ), $width, $height )
				: sprintf( __( 'Social image is %1$dx%2$d — below the recommended 1200x630. Upload a larger image for better social sharing.', 'scalyn-qa-assistant' ), $width, $height ),
			category:  'seo',
			severity:  $pass ? 'info' : 'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'width' => $width, 'height' => $height ),
		);
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * Supports Elementor page builder content when available.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered HTML content.
	 */
	private function get_rendered_content( int $post_id ): string {
		// Check for Elementor-built content.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( $elementor && method_exists( $elementor->db, 'is_built_with_elementor' ) && $elementor->db->is_built_with_elementor( $post_id ) ) {
				$elementor_content = $elementor->frontend->get_builder_content( $post_id, true );
				if ( '' !== $elementor_content ) {
					return $elementor_content;
				}
			}
		}

		$raw_content = get_post_field( 'post_content', $post_id );

		if ( is_wp_error( $raw_content ) || ! is_string( $raw_content ) ) {
			return '';
		}

		/** This filter is documented in wp-includes/post-template.php */
		return (string) apply_filters( 'the_content', $raw_content );
	}
}
