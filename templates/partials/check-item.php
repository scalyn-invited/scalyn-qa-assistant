<?php
/**
 * Partial: Single Check Item.
 *
 * Renders one QA check result row with status icon, label, message, severity,
 * quick fix action, and tooltip.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array $item    Associative array with keys: id, label, status, message, severity, quick_fix, tooltip.
 * @var int   $post_id The post ID this check belongs to (optional, defaults to 0).
 */

defined( 'ABSPATH' ) || exit;

$item    = isset( $item ) ? $item : array();
$post_id = isset( $post_id ) ? (int) $post_id : 0;

$item_id    = isset( $item['id'] ) ? $item['id'] : '';
$label      = isset( $item['label'] ) ? $item['label'] : '';
$status     = isset( $item['status'] ) ? $item['status'] : 'fail';
$message    = isset( $item['message'] ) ? $item['message'] : '';
$severity   = isset( $item['severity'] ) ? $item['severity'] : 'info';
$quick_fix  = isset( $item['quick_fix'] ) ? $item['quick_fix'] : '';
$tooltip    = isset( $item['tooltip'] ) ? $item['tooltip'] : '';
$details    = isset( $item['details'] ) && is_array( $item['details'] ) ? $item['details'] : array();

// Extract the first array-valued detail for the expandable list.
// Skip keys used for structured data (not meant for the expandable list).
$details_list = array();
$details_skip_keys = array( 'ai_images' );
foreach ( $details as $detail_key => $detail_value ) {
	if ( in_array( $detail_key, $details_skip_keys, true ) ) {
		continue;
	}
	if ( is_array( $detail_value ) && ! empty( $detail_value ) ) {
		$details_list = $detail_value;
		break;
	}
}

$status_icons = array(
	'pass'    => 'dashicons-yes-alt',
	'warning' => 'dashicons-warning',
	'fail'    => 'dashicons-dismiss',
);

$icon_class = isset( $status_icons[ $status ] ) ? $status_icons[ $status ] : 'dashicons-marker';
?>
<div
	class="scalyn-check-item scalyn-check-item--<?php echo esc_attr( $status ); ?><?php echo 'pass' !== $status ? ' scalyn-check-item--' . esc_attr( $severity ) : ''; ?>"
	data-check-id="<?php echo esc_attr( $item_id ); ?>"
	data-status="<?php echo esc_attr( $status ); ?>"
	data-severity="<?php echo esc_attr( $severity ); ?>"
>
	<span class="scalyn-check-icon" aria-hidden="true">
		<span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
	</span>

	<div class="scalyn-check-content">
		<strong class="scalyn-check-label"><?php echo esc_html( $label ); ?></strong>
		<?php if ( ! empty( $message ) ) : ?>
			<span class="scalyn-check-message"><?php echo esc_html( $message ); ?></span>
		<?php endif; ?>
	</div>

	<?php
	// Determine generate vs regenerate based on existing AI data.
	$has_ai_data_map = array();
	if ( $post_id > 0 ) {
		$has_ai_data_map = array(
			'generate_ai_meta'           => ! empty( get_post_meta( $post_id, '_scalyn_qa_ai_drafts', true ) ),
			'regenerate_ai_meta'         => true, // pass state already has content
			'generate_ai_alt'            => ! empty( get_post_meta( $post_id, '_scalyn_qa_ai_alt_texts', true ) ),
			'generate_ai_keyword'        => ! empty( get_post_meta( $post_id, '_scalyn_qa_ai_keywords', true ) ),
			'regenerate_ai_keyword'      => true,
			'generate_ai_featured_image' => ! empty( get_post_meta( $post_id, '_scalyn_qa_ai_featured_images', true ) ),
			'regenerate_ai_featured_image' => true,
		);
	}

	// Swap generate → regenerate if AI data already exists.
	$swap_map = array(
		'generate_ai_meta'           => 'regenerate_ai_meta',
		'generate_ai_alt'            => 'regenerate_ai_alt',
		'generate_ai_keyword'        => 'regenerate_ai_keyword',
		'generate_ai_featured_image' => 'regenerate_ai_featured_image',
	);

	$resolved_quick_fix = $quick_fix;
	if ( ! empty( $quick_fix ) && isset( $swap_map[ $quick_fix ] ) && ! empty( $has_ai_data_map[ $quick_fix ] ) ) {
		$resolved_quick_fix = $swap_map[ $quick_fix ];
	}
	?>
	<div class="scalyn-check-actions">
		<?php if ( ! empty( $resolved_quick_fix ) ) : ?>
			<?php
			$action = $resolved_quick_fix;
			include SCALYN_QA_PLUGIN_DIR . 'templates/partials/quick-fix-button.php';
			?>
		<?php endif; ?>

		<?php if ( 'image_alt_text' === $item_id && 'pass' !== $status ) : ?>
			<?php
			$action = ! empty( $has_ai_data_map['generate_ai_alt'] ) ? 'regenerate_ai_alt' : 'generate_ai_alt';
			include SCALYN_QA_PLUGIN_DIR . 'templates/partials/quick-fix-button.php';
			?>
		<?php endif; ?>


		<?php if ( ! empty( $tooltip ) ) : ?>
			<?php
			$text = $tooltip;
			include SCALYN_QA_PLUGIN_DIR . 'templates/partials/tooltip.php';
			?>
		<?php endif; ?>

		<?php if ( 'pass' !== $status ) : ?>
		<button
			type="button"
			class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-ignore-check"
			data-check-id="<?php echo esc_attr( $item_id ); ?>"
			data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
			title="<?php esc_attr_e( 'Ignore this check', 'scalyn-qa-assistant' ); ?>"
		>
			<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
		</button>
		<?php endif; ?>
	</div>

	<?php
	$check_item_ai_enabled = ( new \Scalyn\QA\AI\AI_Manager() )->is_enabled();
	?>
	<?php if ( $check_item_ai_enabled && in_array( $item_id, array( 'meta_title_exists', 'meta_description_exists' ), true ) ) : ?>
	<!-- Inline AI result panel (hidden until generated) -->
	<div class="scalyn-ai-inline-result" data-check-id="<?php echo esc_attr( $item_id ); ?>" style="display:none;">
		<div class="scalyn-ai-inline-result__content">
			<span class="scalyn-ai-inline-result__label"><?php esc_html_e( 'AI Suggestion:', 'scalyn-qa-assistant' ); ?></span>
			<p class="scalyn-ai-inline-result__text"></p>
			<span class="scalyn-ai-inline-result__meta"></span>
		</div>
		<div class="scalyn-ai-inline-result__actions">
			<button type="button" class="scalyn-btn scalyn-btn--small scalyn-ai-inline-copy" title="<?php esc_attr_e( 'Copy', 'scalyn-qa-assistant' ); ?>">
				<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				<?php esc_html_e( 'Copy', 'scalyn-qa-assistant' ); ?>
			</button>
			<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-ai-inline-apply" data-field="<?php echo esc_attr( str_contains( $item_id, 'title' ) ? 'title' : 'description' ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" title="<?php esc_attr_e( 'Apply to SEO plugin', 'scalyn-qa-assistant' ); ?>">
				<span class="dashicons dashicons-yes" aria-hidden="true"></span>
				<?php esc_html_e( 'Apply', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $check_item_ai_enabled && 'focus_keyword' === $item_id ) : ?>
	<?php
	// Load persisted keyword suggestions from post meta.
	$kw_suggestions = $post_id > 0 ? get_post_meta( $post_id, '_scalyn_qa_ai_keywords', true ) : array();
	$kw_suggestions = is_array( $kw_suggestions ) ? $kw_suggestions : array();
	$kw_has_data    = ! empty( $kw_suggestions['keywords'] ) || ! empty( $kw_suggestions['primary'] );
	?>
	<!-- Inline AI keyword suggestions — same layout as meta title/description -->
	<div class="scalyn-ai-keyword-results" data-check-id="focus_keyword" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" style="<?php echo $kw_has_data ? '' : 'display:none;'; ?>">
		<?php if ( $kw_has_data ) : ?>
		<?php
		$kw_is_pro = ! empty( $kw_suggestions['has_pro'] );

		// Build the suggestion list.
		if ( $kw_is_pro && ! empty( $kw_suggestions['primary'] ) ) {
			$kw_list = array_merge( array( $kw_suggestions['primary'] ), $kw_suggestions['secondary'] ?? array() );
		} else {
			$kw_list = $kw_suggestions['keywords'] ?? array();
			if ( empty( $kw_list ) && ! empty( $kw_suggestions['primary'] ) ) {
				$kw_list = array( $kw_suggestions['primary'] );
			}
		}

		// Collect ALL currently applied keywords from the active SEO plugin only.
		$kw_current_all = array();

		// Rank Math: comma-separated (free: 1, pro: up to 5).
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$rm_kw = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( is_string( $rm_kw ) && '' !== $rm_kw ) {
				$kw_current_all = array_map( 'trim', explode( ',', $rm_kw ) );
			}
		}

		// Yoast: primary + premium additional (JSON array).
		if ( empty( $kw_current_all ) && defined( 'WPSEO_VERSION' ) ) {
			$yoast_primary = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			if ( is_string( $yoast_primary ) && '' !== $yoast_primary ) {
				$kw_current_all[] = $yoast_primary;
				$yoast_extra = get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true );
				if ( is_string( $yoast_extra ) && '' !== $yoast_extra ) {
					$parsed_extra = json_decode( $yoast_extra, true );
					if ( is_array( $parsed_extra ) ) {
						foreach ( $parsed_extra as $extra ) {
							if ( ! empty( $extra['keyword'] ) ) {
								$kw_current_all[] = $extra['keyword'];
							}
						}
					}
				}
			}
		}

		// AIOSEO: JSON keyphrases.
		if ( empty( $kw_current_all ) && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo_kp = get_post_meta( $post_id, '_aioseo_keyphrases', true );
			if ( is_string( $aioseo_kp ) && '' !== $aioseo_kp ) {
				$parsed_kp = json_decode( $aioseo_kp, true );
				if ( is_array( $parsed_kp ) ) {
					if ( ! empty( $parsed_kp['focus']['keyphrase'] ) ) {
						$kw_current_all[] = $parsed_kp['focus']['keyphrase'];
					}
					if ( ! empty( $parsed_kp['additional'] ) && is_array( $parsed_kp['additional'] ) ) {
						foreach ( $parsed_kp['additional'] as $add ) {
							if ( ! empty( $add['keyphrase'] ) ) {
								$kw_current_all[] = $add['keyphrase'];
							}
						}
					}
				}
			}
		}

		// SEOPress: comma-separated.
		if ( empty( $kw_current_all ) && defined( 'SEOPRESS_VERSION' ) ) {
			$sp_kw = get_post_meta( $post_id, '_seopress_analysis_target_kw', true );
			if ( is_string( $sp_kw ) && '' !== $sp_kw ) {
				$kw_current_all = array_map( 'trim', explode( ',', $sp_kw ) );
			}
		}

		$kw_current_all_lower = array_map( 'mb_strtolower', array_filter( $kw_current_all ) );
		?>
		<div class="scalyn-ai-inline-result" data-check-id="focus_keyword">
			<div class="scalyn-ai-inline-result__content">
				<span class="scalyn-ai-inline-result__label"><?php esc_html_e( 'AI Suggestion:', 'scalyn-qa-assistant' ); ?></span>
				<div class="scalyn-ai-inline-result__text" style="margin:0;">
					<?php foreach ( $kw_list as $ki => $kw_option ) :
						$is_primary  = $kw_is_pro && 0 === $ki;
						$is_current  = in_array( mb_strtolower( trim( $kw_option ) ), $kw_current_all_lower, true );
						$border      = ( $is_primary || $is_current ) ? 'var(--scalyn-success)' : 'var(--scalyn-border-light)';
						$bg          = ( $is_primary || $is_current ) ? 'var(--scalyn-success-light)' : 'transparent';
						$input_type  = $kw_is_pro ? 'checkbox' : 'radio';
						$input_name  = $kw_is_pro ? 'scalyn-ai-keyword[]' : 'scalyn-ai-keyword';
						// Pro: primary always checked, secondary checked if current. Free: first or current checked.
						$is_checked  = $kw_is_pro
							? ( $is_primary || $is_current )
							: ( $is_current || ( empty( $kw_current_all ) && 0 === $ki ) );
					?>
					<label style="display:block;padding:6px 12px;margin:4px 0;border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:6px;cursor:pointer;font-size:0.8125rem;background:<?php echo esc_attr( $bg ); ?>;">
						<input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $kw_option ); ?>" <?php checked( $is_checked ); ?> style="margin-right:8px;">
						<?php echo esc_html( $kw_option ); ?>
						<?php if ( $is_primary ) : ?>
							<span style="color:var(--scalyn-success);font-size:0.6875rem;"><?php esc_html_e( '(primary)', 'scalyn-qa-assistant' ); ?></span>
						<?php elseif ( $kw_is_pro && $ki > 0 ) : ?>
							<span style="color:var(--scalyn-text-muted);font-size:0.6875rem;"><?php esc_html_e( '(secondary)', 'scalyn-qa-assistant' ); ?></span>
						<?php endif; ?>
						<?php if ( $is_current ) : ?>
							<span style="color:var(--scalyn-success);font-size:0.6875rem;"><?php esc_html_e( '(applied)', 'scalyn-qa-assistant' ); ?></span>
						<?php endif; ?>
					</label>
					<?php endforeach; ?>
				</div>
				<span class="scalyn-ai-inline-result__meta">
					<?php
					if ( ! empty( $kw_suggestions['provider'] ) ) {
						echo esc_html( $kw_suggestions['provider'] . ( ! empty( $kw_suggestions['model'] ) ? ' / ' . $kw_suggestions['model'] : '' ) );
					}
					?>
				</span>
			</div>
			<div class="scalyn-ai-inline-result__actions">
				<button type="button" class="scalyn-btn scalyn-btn--small scalyn-keyword-copy" title="<?php esc_attr_e( 'Copy', 'scalyn-qa-assistant' ); ?>">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Copy', 'scalyn-qa-assistant' ); ?>
				</button>
				<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-keyword-apply-selected" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-is-pro="<?php echo $kw_is_pro ? '1' : '0'; ?>" title="<?php echo $kw_is_pro ? esc_attr__( 'Apply checked keywords', 'scalyn-qa-assistant' ) : esc_attr__( 'Apply selected keyword', 'scalyn-qa-assistant' ); ?>">
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					<?php esc_html_e( 'Apply', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $check_item_ai_enabled && 'featured_image_exists' === $item_id ) : ?>
	<?php
	$fi_thumb_url     = $details['thumbnail_url'] ?? '';
	$fi_attachment_id = (int) ( $details['attachment_id'] ?? 0 );
	$fi_filename      = $details['filename'] ?? '';
	$fi_has_image     = 'pass' === $status && '' !== $fi_thumb_url;
	$fi_ai_images     = $details['ai_images'] ?? array();
	$fi_has_any       = $fi_has_image || ! empty( $fi_ai_images );

	// Build the list of all images to show (current + AI history, deduplicated).
	$fi_all_options = array();
	$fi_seen_ids    = array();

	// Current featured image first (if set).
	if ( $fi_has_image && $fi_attachment_id > 0 ) {
		$fi_all_options[] = array(
			'attachment_id' => $fi_attachment_id,
			'url'           => $fi_thumb_url,
			'filename'      => $fi_filename,
			'is_current'    => true,
		);
		$fi_seen_ids[] = $fi_attachment_id;
	}

	// AI-generated images (skip the current one if it's already shown).
	foreach ( $fi_ai_images as $fi_ai ) {
		$aid = (int) $fi_ai['attachment_id'];
		if ( in_array( $aid, $fi_seen_ids, true ) ) {
			continue;
		}
		$fi_all_options[] = array(
			'attachment_id' => $aid,
			'url'           => $fi_ai['url'],
			'filename'      => $fi_ai['filename'],
			'is_current'    => false,
		);
		$fi_seen_ids[] = $aid;
	}

	$fi_label = count( $fi_all_options ) > 1
		? __( 'Featured Image Options', 'scalyn-qa-assistant' )
		: ( $fi_has_image ? __( 'Current Featured Image', 'scalyn-qa-assistant' ) : __( 'AI Generated Images', 'scalyn-qa-assistant' ) );
	?>
	<!-- Inline AI featured image panel -->
	<div
		class="scalyn-ai-featured-image-results"
		data-check-id="featured_image_exists"
		data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
		style="<?php echo $fi_has_any ? '' : 'display:none;'; ?>"
	>
		<div class="scalyn-ai-inline-result">
			<div class="scalyn-ai-inline-result__content">
				<span class="scalyn-ai-inline-result__label"><?php echo esc_html( $fi_label ); ?></span>
				<div class="scalyn-fi-grid">
					<?php foreach ( $fi_all_options as $fi_idx => $fi_opt ) : ?>
					<?php $fi_is_selected = $fi_opt['is_current'] || ( ! $fi_has_image && 0 === $fi_idx ); ?>
					<label class="scalyn-fi-option<?php echo $fi_is_selected ? ' selected' : ''; ?>">
						<img src="<?php echo esc_url( $fi_opt['url'] ); ?>" alt="<?php echo esc_attr( $fi_opt['filename'] ); ?>" />
						<div class="scalyn-fi-option-footer">
							<input type="radio" name="scalyn-fi-select-<?php echo esc_attr( (string) $post_id ); ?>" value="<?php echo esc_attr( (string) $fi_opt['attachment_id'] ); ?>"<?php checked( $fi_is_selected ); ?>>
							<span><?php echo esc_html( $fi_opt['filename'] ); ?></span>
							<?php if ( $fi_opt['is_current'] ) : ?>
								<span style="color:var(--scalyn-success);font-size:0.6875rem;margin-left:auto;"><?php esc_html_e( '(current)', 'scalyn-qa-assistant' ); ?></span>
							<?php endif; ?>
						</div>
					</label>
					<?php endforeach; ?>
				</div>
				<span class="scalyn-ai-inline-result__meta scalyn-fi-meta"></span>
			</div>
			<div class="scalyn-ai-inline-result__actions">
				<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-fi-apply" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"<?php echo $fi_has_any ? '' : ' disabled'; ?> title="<?php esc_attr_e( 'Set as featured image', 'scalyn-qa-assistant' ); ?>">
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					<?php esc_html_e( 'Apply', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $check_item_ai_enabled && 'image_alt_text' === $item_id && 'pass' !== $status ) : ?>
	<!-- Inline AI alt text results (hidden until generated) -->
	<div class="scalyn-ai-alt-results" data-check-id="image_alt_text" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" style="display:none;">
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $details_list ) ) : ?>
	<?php $details_uid = 'details-' . esc_attr( $item_id ) . '-' . wp_unique_id(); ?>
	<div class="scalyn-check-details">
		<button type="button" class="scalyn-check-details__toggle" data-target="<?php echo esc_attr( $details_uid ); ?>">
			<span class="dashicons dashicons-arrow-right-alt2"></span>
			<?php
			printf(
				/* translators: %d: number of affected items */
				esc_html( _n( 'Show %d affected item', 'Show %d affected items', count( $details_list ), 'scalyn-qa-assistant' ) ),
				count( $details_list ),
			);
			?>
		</button>
		<ul class="scalyn-check-details__list" id="<?php echo esc_attr( $details_uid ); ?>" style="display:none;">
			<?php foreach ( $details_list as $detail_item ) : ?>
				<li><?php echo esc_html( (string) $detail_item ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
</div>
