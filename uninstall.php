<?php
/**
 * Uninstall handler.
 *
 * Runs when the plugin is deleted through the WordPress admin.
 * Removes all options, post meta, and scheduled events created
 * by SEOMelon.
 *
 * @package SEOMelon
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// 1. Delete plugin options.
$options = array(
	'seomelon_api_key',
	'seomelon_api_url',
	'seomelon_settings',
	'seomelon_last_sync',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// 2. Delete all SEOMelon post meta across all post types.
$meta_keys = array(
	'_seomelon_seo_score',
	'_seomelon_meta_title',
	'_seomelon_meta_description',
	'_seomelon_aeo_description',
	'_seomelon_faq_schema',
	'_seomelon_schema',
	'_seomelon_og_title',
	'_seomelon_og_description',
	'_seomelon_applied_at',
);

foreach ( $meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $meta_key ),
		array( '%s' )
	);
}

// 3. Delete transient caches.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_seomelon_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_seomelon_' ) . '%'
	)
);

// 4. Remove scheduled cron events.
$timestamp = wp_next_scheduled( 'seomelon_auto_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'seomelon_auto_sync' );
}
