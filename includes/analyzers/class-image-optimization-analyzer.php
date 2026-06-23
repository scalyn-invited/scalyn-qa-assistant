<?php
/**
 * Image Optimization Analyzer.
 *
 * Checks images for broken sources, missing dimensions, lazy loading,
 * and oversized file sizes.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.3.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Image_Optimization_Analyzer
 *
 * Analyzes images in post content for performance and optimization issues.
 *
 * @since 1.3.0
 */
class Image_Optimization_Analyzer implements Analyzer_Interface {

	/**
	 * Default maximum image file size in KB.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_FILE_SIZE_KB = 900;

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'image_optimization';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Image Optimization', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'seo';
	}

	/**
	 * Run all image optimization checks on a post.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content = $this->get_rendered_content( $post_id );
		$parser  = new HTML_Parser( $content );
		$nodes   = $parser->query( '//img' );

		$checks   = array();
		$checks[] = $this->check_broken_media( $nodes );
		$checks[] = $this->check_image_dimensions( $nodes );
		$checks[] = $this->check_image_lazy_loading( $nodes );
		$checks[] = $this->check_image_file_size( $nodes );

		return $checks;
	}

	/**
	 * Check for images with missing or broken src attributes.
	 *
	 * @since 1.3.0
	 *
	 * @param \DOMNodeList $nodes Image DOM nodes.
	 * @return Check_Item
	 */
	private function check_broken_media( \DOMNodeList $nodes ): Check_Item {
		$tooltip = __( 'Broken images hurt user experience and SEO. Check that each image file exists and the URL is correct. Re-upload or update the image in the post editor.', 'scalyn-qa-assistant' );

		if ( 0 === $nodes->length ) {
			return new Check_Item(
				id:        'broken_media',
				label:     __( 'Broken / Missing Media', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No images found in the content — not applicable.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$broken     = array();
		$upload_dir = wp_get_upload_dir();

		foreach ( $nodes as $node ) {
			$src = trim( $node->getAttribute( 'src' ) );

			// Empty or missing src.
			if ( '' === $src ) {
				$broken[] = __( '(empty src)', 'scalyn-qa-assistant' );
				continue;
			}

			// Check local images — resolve URL to file path.
			$file_path = $this->resolve_local_path( $src, $upload_dir );

			if ( null !== $file_path && ! file_exists( $file_path ) ) {
				$label = mb_strlen( $src ) > 80 ? mb_substr( $src, 0, 80 ) . '...' : $src;
				$broken[] = $label;
			}
		}

		$broken_count = count( $broken );

		if ( 0 === $broken_count ) {
			return new Check_Item(
				id:        'broken_media',
				label:     __( 'Broken / Missing Media', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: total image count */
					__( 'All %d images have valid sources.', 'scalyn-qa-assistant' ),
					$nodes->length,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'broken_media',
			label:     __( 'Broken / Missing Media', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   sprintf(
				/* translators: 1: broken count, 2: total count */
				__( '%1$d of %2$d images have broken or missing sources. Re-upload or fix the image URLs in the post editor.', 'scalyn-qa-assistant' ),
				$broken_count,
				$nodes->length,
			),
			category:  'seo',
			severity:  'critical',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'broken_images' => $broken ),
		);
	}

	/**
	 * Check for images missing width and height attributes.
	 *
	 * Missing dimensions cause layout shifts (poor CLS score).
	 *
	 * @since 1.3.0
	 *
	 * @param \DOMNodeList $nodes Image DOM nodes.
	 * @return Check_Item
	 */
	private function check_image_dimensions( \DOMNodeList $nodes ): Check_Item {
		$tooltip = __( 'Images without width and height attributes cause layout shifts as the page loads, hurting Core Web Vitals (CLS). Add explicit dimensions to each image in the post editor or theme code.', 'scalyn-qa-assistant' );

		if ( 0 === $nodes->length ) {
			return new Check_Item(
				id:        'image_dimensions',
				label:     __( 'Image Dimensions', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No images found in the content — not applicable.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$missing = array();

		foreach ( $nodes as $node ) {
			$has_width  = $node->hasAttribute( 'width' ) && '' !== trim( $node->getAttribute( 'width' ) );
			$has_height = $node->hasAttribute( 'height' ) && '' !== trim( $node->getAttribute( 'height' ) );

			if ( ! $has_width || ! $has_height ) {
				$src = $node->getAttribute( 'src' );
				$missing[] = mb_strlen( $src ) > 80 ? mb_substr( $src, 0, 80 ) . '...' : $src;
			}
		}

		$missing_count = count( $missing );

		if ( 0 === $missing_count ) {
			return new Check_Item(
				id:        'image_dimensions',
				label:     __( 'Image Dimensions', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: total image count */
					__( 'All %d images have width and height attributes.', 'scalyn-qa-assistant' ),
					$nodes->length,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'image_dimensions',
			label:     __( 'Image Dimensions', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: 1: missing count, 2: total count */
				__( '%1$d of %2$d images are missing width/height attributes, which causes layout shifts (poor CLS).', 'scalyn-qa-assistant' ),
				$missing_count,
				$nodes->length,
			),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'missing_dimensions' => $missing ),
		);
	}

	/**
	 * Check for images missing the loading="lazy" attribute.
	 *
	 * The first image is excluded since it should be eagerly loaded for LCP.
	 *
	 * @since 1.3.0
	 *
	 * @param \DOMNodeList $nodes Image DOM nodes.
	 * @return Check_Item
	 */
	private function check_image_lazy_loading( \DOMNodeList $nodes ): Check_Item {
		$tooltip = __( 'Lazy loading defers offscreen images, improving initial page load speed. Add loading="lazy" to images below the fold. The first image should remain eagerly loaded for Largest Contentful Paint (LCP).', 'scalyn-qa-assistant' );

		// Need at least 2 images (first is excluded as LCP candidate).
		if ( $nodes->length < 2 ) {
			return new Check_Item(
				id:        'image_lazy_loading',
				label:     __( 'Image Lazy Loading', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   $nodes->length === 0
					? __( 'No images found in the content — not applicable.', 'scalyn-qa-assistant' )
					: __( 'Only one image found — it should be eagerly loaded for LCP.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$missing = array();
		$index   = 0;

		foreach ( $nodes as $node ) {
			++$index;

			// Skip first image — it should be eager for LCP.
			if ( 1 === $index ) {
				continue;
			}

			$loading = strtolower( trim( $node->getAttribute( 'loading' ) ) );

			if ( 'lazy' !== $loading ) {
				$src = $node->getAttribute( 'src' );
				$missing[] = mb_strlen( $src ) > 80 ? mb_substr( $src, 0, 80 ) . '...' : $src;
			}
		}

		$missing_count    = count( $missing );
		$checkable_count  = $nodes->length - 1; // Exclude first image.

		if ( 0 === $missing_count ) {
			return new Check_Item(
				id:        'image_lazy_loading',
				label:     __( 'Image Lazy Loading', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: lazy-loaded image count */
					__( 'All %d below-the-fold images have lazy loading enabled.', 'scalyn-qa-assistant' ),
					$checkable_count,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'image_lazy_loading',
			label:     __( 'Image Lazy Loading', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: 1: missing count, 2: checkable count */
				__( '%1$d of %2$d below-the-fold images are missing loading="lazy". Add it to improve page load performance.', 'scalyn-qa-assistant' ),
				$missing_count,
				$checkable_count,
			),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'missing_lazy' => $missing ),
		);
	}

	/**
	 * Check for images that exceed the configured file size threshold.
	 *
	 * Only checks local WordPress uploads where the file can be measured.
	 *
	 * @since 1.3.0
	 *
	 * @param \DOMNodeList $nodes Image DOM nodes.
	 * @return Check_Item
	 */
	private function check_image_file_size( \DOMNodeList $nodes ): Check_Item {
		$max_kb  = $this->get_max_file_size_kb();
		$tooltip = sprintf(
			/* translators: %d: max file size in KB */
			__( 'Large images slow down page load. Compress or resize images to stay under %dKB. Use tools like ShortPixel, Imagify, or Smush to optimize images in your Media Library.', 'scalyn-qa-assistant' ),
			$max_kb,
		);

		if ( 0 === $nodes->length ) {
			return new Check_Item(
				id:        'image_file_size',
				label:     __( 'Image File Size', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No images found in the content — not applicable.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$oversized  = array();
		$checked    = 0;
		$upload_dir = wp_get_upload_dir();
		$max_bytes  = $max_kb * 1024;

		foreach ( $nodes as $node ) {
			$src = trim( $node->getAttribute( 'src' ) );

			if ( '' === $src ) {
				continue;
			}

			$file_path = $this->resolve_local_path( $src, $upload_dir );

			if ( null === $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			++$checked;
			$size = filesize( $file_path );

			if ( false !== $size && $size > $max_bytes ) {
				$size_kb = round( $size / 1024 );
				$label   = mb_strlen( $src ) > 60 ? mb_substr( $src, 0, 60 ) . '...' : $src;
				$oversized[] = sprintf( '%s (%dKB)', $label, $size_kb );
			}
		}

		if ( 0 === $checked ) {
			return new Check_Item(
				id:        'image_file_size',
				label:     __( 'Image File Size', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No local images to check file sizes for.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$oversized_count = count( $oversized );

		if ( 0 === $oversized_count ) {
			return new Check_Item(
				id:        'image_file_size',
				label:     __( 'Image File Size', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: 1: checked count, 2: max size in KB */
					__( 'All %1$d local images are under %2$dKB.', 'scalyn-qa-assistant' ),
					$checked,
					$max_kb,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'image_file_size',
			label:     __( 'Image File Size', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: 1: oversized count, 2: checked count, 3: max size in KB */
				__( '%1$d of %2$d local images exceed %3$dKB. Compress or resize them to improve page load speed.', 'scalyn-qa-assistant' ),
				$oversized_count,
				$checked,
				$max_kb,
			),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'oversized_images' => $oversized ),
		);
	}

	/**
	 * Get the configured maximum image file size in KB.
	 *
	 * @since 1.3.0
	 *
	 * @return int
	 */
	private function get_max_file_size_kb(): int {
		$settings = get_option( 'scalyn_qa_page_audit_settings', array() );

		if ( is_array( $settings ) && isset( $settings['max_image_file_size'] ) ) {
			return max( 1, (int) $settings['max_image_file_size'] );
		}

		return self::DEFAULT_MAX_FILE_SIZE_KB;
	}

	/**
	 * Resolve an image URL to a local file path if it is a WordPress upload.
	 *
	 * @since 1.3.0
	 *
	 * @param string $url        The image URL.
	 * @param array  $upload_dir Result of wp_get_upload_dir().
	 * @return string|null Local file path or null if not a local upload.
	 */
	private function resolve_local_path( string $url, array $upload_dir ): ?string {
		$base_url = $upload_dir['baseurl'] ?? '';
		$base_dir = $upload_dir['basedir'] ?? '';

		if ( '' === $base_url || '' === $base_dir ) {
			return null;
		}

		// Handle protocol-relative URLs.
		$normalized_url = $url;
		if ( str_starts_with( $normalized_url, '//' ) ) {
			$normalized_url = 'https:' . $normalized_url;
		}

		$normalized_base = $base_url;
		if ( str_starts_with( $normalized_base, '//' ) ) {
			$normalized_base = 'https:' . $normalized_base;
		}

		if ( ! str_starts_with( $normalized_url, $normalized_base ) ) {
			return null;
		}

		$relative  = substr( $normalized_url, strlen( $normalized_base ) );
		$file_path = $base_dir . str_replace( '/', DIRECTORY_SEPARATOR, $relative );

		return $file_path;
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * Supports Elementor page builder content when available.
	 *
	 * @since 1.3.0
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
