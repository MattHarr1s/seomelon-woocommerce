<?php
/**
 * Main plugin class.
 *
 * Bootstraps all plugin components and provides a singleton entry point.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon
 */
class SEOMelon {

	/**
	 * Singleton instance.
	 *
	 * @var SEOMelon|null
	 */
	private static ?SEOMelon $instance = null;

	/**
	 * API client instance.
	 *
	 * @var SEOMelon_API
	 */
	public SEOMelon_API $api;

	/**
	 * SEO plugin detector.
	 *
	 * @var SEOMelon_SEO_Detect
	 */
	public SEOMelon_SEO_Detect $seo_detect;

	/**
	 * Content sync handler.
	 *
	 * @var SEOMelon_Sync
	 */
	public SEOMelon_Sync $sync;

	/**
	 * Suggestion applier.
	 *
	 * @var SEOMelon_Apply
	 */
	public SEOMelon_Apply $apply;

	/**
	 * Admin handler (null on the frontend).
	 *
	 * @var SEOMelon_Admin|null
	 */
	public ?SEOMelon_Admin $admin = null;

	/**
	 * Column handler.
	 *
	 * @var SEOMelon_Columns
	 */
	public SEOMelon_Columns $columns;

	/**
	 * Editor meta box.
	 *
	 * @var SEOMelon_Metabox|null
	 */
	public ?SEOMelon_Metabox $metabox = null;

	/**
	 * Manual schema/FAQ editor meta box (free-tier feature).
	 *
	 * @var SEOMelon_Manual_Schema|null
	 */
	public ?SEOMelon_Manual_Schema $manual_schema = null;

	/**
	 * Frontend AEO content handler.
	 *
	 * @var SEOMelon_Frontend
	 */
	public SEOMelon_Frontend $frontend;

	/**
	 * Return the singleton instance.
	 *
	 * @return SEOMelon
	 */
	public static function instance(): SEOMelon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor. Wires up all components.
	 */
	private function __construct() {
		$this->seo_detect = new SEOMelon_SEO_Detect();
		$this->api        = new SEOMelon_API();
		$this->sync       = new SEOMelon_Sync( $this->api, $this->seo_detect );
		$this->apply      = new SEOMelon_Apply( $this->api, $this->seo_detect );
		$this->columns    = new SEOMelon_Columns();

		// Frontend AEO content injection (runs on all page loads).
		$this->frontend = new SEOMelon_Frontend();

		if ( is_admin() ) {
			$this->admin         = new SEOMelon_Admin( $this->api, $this->sync, $this->apply );
			$this->metabox       = new SEOMelon_Metabox( $this->api, $this->seo_detect );
			$this->manual_schema = new SEOMelon_Manual_Schema();
		}

		$this->register_hooks();
	}

	/**
	 * Register global hooks.
	 */
	private function register_hooks(): void {
		// Auto-sync cron action.
		add_action( 'seomelon_auto_sync', array( $this->sync, 'sync_all' ) );

		// Output schema markup on the front end.
		add_action( 'wp_head', array( $this->apply, 'output_schema' ) );

		// Add settings link on the plugins page.
		add_filter( 'plugin_action_links_' . SEOMELON_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
	}

	/**
	 * Add quick links to the Plugins list table.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_plugin_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=seomelon-settings' );
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'seomelon' ) )
		);

		return $links;
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Return the required capability for managing SEOMelon.
	 *
	 * Uses WooCommerce capability when available, otherwise falls back to
	 * the standard manage_options capability.
	 *
	 * @return string
	 */
	public static function capability(): string {
		return self::is_woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
	}
}
