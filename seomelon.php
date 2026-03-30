<?php
/**
 * Plugin Name: SEOMelon - AI SEO & Business Intelligence
 * Plugin URI:  https://seomelon.app
 * Description: AI-powered SEO advisor that researches competitors, generates optimized content, and delivers business insights. Works with WooCommerce products, posts, and pages.
 * Version:     1.0.0
 * Author:      Sandia Software Services
 * Author URI:  https://seomelon.app
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seomelon
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'SEOMELON_VERSION', '1.0.0' );
define( 'SEOMELON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOMELON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEOMELON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SEOMELON_API_URL', 'https://seomelon.app/api/v1' );

/**
 * Autoload plugin classes.
 */
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-api.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-admin.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-sync.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-apply.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-seo-detect.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-columns.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-metabox.php';
require_once SEOMELON_PLUGIN_DIR . 'includes/class-seomelon-frontend.php';

/**
 * Activation hook.
 *
 * Sets default options and schedules cron events on first activation.
 */
function seomelon_activate(): void {
	if ( false === get_option( 'seomelon_api_url' ) ) {
		add_option( 'seomelon_api_url', SEOMELON_API_URL );
	}

	if ( false === get_option( 'seomelon_settings' ) ) {
		add_option(
			'seomelon_settings',
			array(
				'content_types' => array( 'product', 'post', 'page' ),
				'tone'          => 'professional',
				'auto_sync'     => 'manual',
			)
		);
	}

	$settings  = get_option( 'seomelon_settings', array() );
	$frequency = $settings['auto_sync'] ?? 'manual';

	if ( 'manual' !== $frequency && ! wp_next_scheduled( 'seomelon_auto_sync' ) ) {
		$recurrence = 'daily' === $frequency ? 'daily' : 'weekly';
		wp_schedule_event( time(), $recurrence, 'seomelon_auto_sync' );
	}
}
register_activation_hook( __FILE__, 'seomelon_activate' );

/**
 * Deactivation hook.
 *
 * Clears scheduled cron events.
 */
function seomelon_deactivate(): void {
	$timestamp = wp_next_scheduled( 'seomelon_auto_sync' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'seomelon_auto_sync' );
	}
}
register_deactivation_hook( __FILE__, 'seomelon_deactivate' );

/**
 * Declare HPOS compatibility for WooCommerce.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Initialize the plugin.
 *
 * @return SEOMelon
 */
function seomelon(): SEOMelon {
	return SEOMelon::instance();
}

add_action( 'plugins_loaded', 'seomelon' );
