<?php
/**
 * Partial: Quick Fix Button.
 *
 * Renders the appropriate quick fix button based on action type.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var string $action  The quick fix action identifier.
 * @var int    $post_id The post ID associated with the fix.
 */

defined( 'ABSPATH' ) || exit;

$action  = isset( $action ) ? $action : '';
$post_id = isset( $post_id ) ? (int) $post_id : 0;

if ( empty( $action ) ) {
	return;
}

$button_config = array(
	'generate_ai_meta'     => array(
		'label'    => __( 'Generate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-admin-customizer',
		'class'    => 'scalyn-btn--ai',
		'data_key' => 'generate-ai-meta',
	),
	'regenerate_ai_meta'   => array(
		'label'    => __( 'Regenerate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-update',
		'class'    => 'scalyn-btn--ghost',
		'data_key' => 'generate-ai-meta',
	),
	'generate_ai_featured_image' => array(
		'label'    => __( 'Generate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-admin-customizer',
		'class'    => 'scalyn-btn--ai',
		'data_key' => 'generate-ai-featured-image',
	),
	'regenerate_ai_featured_image' => array(
		'label'    => __( 'Regenerate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-update',
		'class'    => 'scalyn-btn--ghost',
		'data_key' => 'generate-ai-featured-image',
	),
	'upload_featured_image' => array(
		'label'    => __( 'Upload Image', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-format-image',
		'class'    => 'scalyn-btn--secondary',
		'data_key' => 'upload-featured-image',
	),
	'jump_to_heading'      => array(
		'label'    => __( 'Jump to Issue', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-editor-textcolor',
		'class'    => 'scalyn-btn--secondary',
		'data_key' => 'jump-to-heading',
		'is_link'  => true,
	),
	'edit_link'            => array(
		'label'    => __( 'Edit Link', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-admin-links',
		'class'    => 'scalyn-btn--secondary',
		'data_key' => 'edit-link',
	),
	'install_seo_plugin'   => array(
		'label'    => __( 'Install SEO Plugin', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-download',
		'class'    => 'scalyn-btn--primary',
		'data_key' => 'install-seo-plugin',
		'is_link'  => true,
	),
	'generate_ai_alt' => array(
		'label'    => __( 'Generate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-admin-customizer',
		'class'    => 'scalyn-btn--ai',
		'data_key' => 'generate-ai-alt',
	),
	'regenerate_ai_alt' => array(
		'label'    => __( 'Regenerate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-update',
		'class'    => 'scalyn-btn--ghost',
		'data_key' => 'generate-ai-alt',
	),
	'use_titles_as_alt' => array(
		'label'    => __( 'Quick Fix', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-admin-generic',
		'class'    => 'scalyn-btn--secondary',
		'data_key' => 'use-titles-as-alt',
	),
	'generate_ai_keyword' => array(
		'label'    => __( 'Generate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-admin-customizer',
		'class'    => 'scalyn-btn--ai',
		'data_key' => 'generate-ai-keyword',
	),
	'regenerate_ai_keyword' => array(
		'label'    => __( 'Regenerate with AI', 'scalyn-qa-assistant' ),
		'icon'     => 'dashicons-update',
		'class'    => 'scalyn-btn--ghost',
		'data_key' => 'generate-ai-keyword',
	),
);

if ( ! isset( $button_config[ $action ] ) ) {
	return;
}

// Hide AI-related quick fix buttons when AI is disabled.
$ai_actions = array( 'generate_ai_meta', 'regenerate_ai_meta', 'generate_ai_keyword', 'regenerate_ai_keyword', 'generate_ai_featured_image', 'regenerate_ai_featured_image', 'generate_ai_alt', 'regenerate_ai_alt' );
if ( in_array( $action, $ai_actions, true ) && ! ( new \Scalyn\QA\AI\AI_Manager() )->is_enabled() ) {
	return;
}

$config = $button_config[ $action ];
$is_link = ! empty( $config['is_link'] );
?>
<?php if ( $is_link && 'install_seo_plugin' === $action ) : ?>
	<a
		href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-settings&tab=wizard' ) ); ?>"
		class="scalyn-btn scalyn-btn--small <?php echo esc_attr( $config['class'] ); ?> scalyn-quick-fix"
		data-action="<?php echo esc_attr( $config['data_key'] ); ?>"
		data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
	>
		<span class="dashicons <?php echo esc_attr( $config['icon'] ); ?>" aria-hidden="true"></span>
		<?php echo esc_html( $config['label'] ); ?>
	</a>
<?php elseif ( $is_link ) : ?>
	<a
		href="#"
		class="scalyn-btn scalyn-btn--small <?php echo esc_attr( $config['class'] ); ?> scalyn-quick-fix"
		data-action="<?php echo esc_attr( $config['data_key'] ); ?>"
		data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
	>
		<span class="dashicons <?php echo esc_attr( $config['icon'] ); ?>" aria-hidden="true"></span>
		<?php echo esc_html( $config['label'] ); ?>
	</a>
<?php else : ?>
	<button
		type="button"
		class="scalyn-btn scalyn-btn--small <?php echo esc_attr( $config['class'] ); ?> scalyn-quick-fix"
		data-action="<?php echo esc_attr( $config['data_key'] ); ?>"
		data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
	>
		<span class="dashicons <?php echo esc_attr( $config['icon'] ); ?>" aria-hidden="true"></span>
		<?php echo esc_html( $config['label'] ); ?>
	</button>
<?php endif; ?>
