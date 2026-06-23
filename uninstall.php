<?php
/**
 * Uninstall handler for Scalyn Website QA & SEO Assistant.
 *
 * Fires when the plugin is deleted via the WordPress admin.
 * Removes all plugin data from the database only if the setting is enabled.
 *
 * @package Scalyn\QA
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings    = get_option( 'scalyn_qa_settings', [] );
$delete_data = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $delete_data ) {
	// Only remove the plugin version marker, keep all other data.
	delete_option( 'scalyn_qa_version' );
	return;
}

global $wpdb;

/*
 * 1. Delete all wp_options starting with 'scalyn_qa_'.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'scalyn_qa_' ) . '%'
	)
);

/*
 * 2. Delete all postmeta with keys starting with '_scalyn_qa_'.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_scalyn_qa_' ) . '%'
	)
);

/*
 * 3. Delete all transients starting with 'scalyn_qa_'.
 *    Transients are stored as '_transient_<name>' and '_transient_timeout_<name>' in wp_options.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_scalyn_qa_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_scalyn_qa_' ) . '%'
	)
);

/*
 * 4. Delete all usermeta with keys starting with 'scalyn_qa_'.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'scalyn_qa_' ) . '%'
	)
);

/*
 * 5. Clear any lingering object cache entries.
 */
wp_cache_flush();
