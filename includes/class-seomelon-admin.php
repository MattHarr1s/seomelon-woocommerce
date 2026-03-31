<?php
/**
 * Admin pages, menus, and AJAX handlers.
 *
 * Registers the SEOMelon menu (under WooCommerce when available,
 * under Tools otherwise), enqueues admin assets, and processes
 * all AJAX actions for syncing, scanning, generating, and applying.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Admin
 */
class SEOMelon_Admin {

	/**
	 * API client.
	 *
	 * @var SEOMelon_API
	 */
	private SEOMelon_API $api;

	/**
	 * Sync handler.
	 *
	 * @var SEOMelon_Sync
	 */
	private SEOMelon_Sync $sync;

	/**
	 * Apply handler.
	 *
	 * @var SEOMelon_Apply
	 */
	private SEOMelon_Apply $apply;

	/**
	 * Constructor.
	 *
	 * @param SEOMelon_API   $api   API client.
	 * @param SEOMelon_Sync  $sync  Sync handler.
	 * @param SEOMelon_Apply $apply Apply handler.
	 */
	public function __construct( SEOMelon_API $api, SEOMelon_Sync $sync, SEOMelon_Apply $apply ) {
		$this->api   = $api;
		$this->sync  = $sync;
		$this->apply = $apply;

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_seomelon_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_seomelon_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_seomelon_register', array( $this, 'ajax_register' ) );
		add_action( 'wp_ajax_seomelon_sync', array( $this, 'ajax_sync' ) );
		add_action( 'wp_ajax_seomelon_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_seomelon_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_seomelon_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_seomelon_job_status', array( $this, 'ajax_job_status' ) );
		add_action( 'wp_ajax_seomelon_get_content', array( $this, 'ajax_get_content' ) );
		add_action( 'wp_ajax_seomelon_gsc_connect', array( $this, 'ajax_gsc_connect' ) );
		add_action( 'wp_ajax_seomelon_gsc_disconnect', array( $this, 'ajax_gsc_disconnect' ) );
		add_action( 'wp_ajax_seomelon_gsc_status', array( $this, 'ajax_gsc_status' ) );
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menus(): void {
		$capability = SEOMelon::capability();

		if ( SEOMelon::is_woocommerce_active() ) {
			// Under WooCommerce menu.
			add_submenu_page(
				'woocommerce',
				__( 'SEOMelon', 'seomelon' ),
				__( 'SEOMelon', 'seomelon' ),
				$capability,
				'seomelon',
				array( $this, 'render_dashboard' )
			);
		} else {
			// Standalone top-level menu.
			add_menu_page(
				__( 'SEOMelon', 'seomelon' ),
				__( 'SEOMelon', 'seomelon' ),
				$capability,
				'seomelon',
				array( $this, 'render_dashboard' ),
				'dashicons-chart-line',
				30
			);
		}

		$parent = SEOMelon::is_woocommerce_active() ? 'woocommerce' : 'seomelon';

		add_submenu_page(
			$parent,
			__( 'SEOMelon Insights', 'seomelon' ),
			__( 'SEOMelon Insights', 'seomelon' ),
			$capability,
			'seomelon-insights',
			array( $this, 'render_insights' )
		);

		add_submenu_page(
			$parent,
			__( 'SEOMelon Reports', 'seomelon' ),
			__( 'SEOMelon Reports', 'seomelon' ),
			$capability,
			'seomelon-reports',
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			$parent,
			__( 'SEOMelon Settings', 'seomelon' ),
			__( 'SEOMelon Settings', 'seomelon' ),
			$capability,
			'seomelon-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on SEOMelon pages and list tables.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		// Always enqueue minimal CSS for score badges on list tables.
		wp_enqueue_style(
			'seomelon-admin',
			SEOMELON_PLUGIN_URL . 'admin/css/seomelon-admin.css',
			array(),
			SEOMELON_VERSION
		);

		// Only enqueue JS on SEOMelon pages.
		$seomelon_pages = array(
			'woocommerce_page_seomelon',
			'woocommerce_page_seomelon-insights',
			'woocommerce_page_seomelon-reports',
			'woocommerce_page_seomelon-settings',
			'toplevel_page_seomelon',
			'seomelon_page_seomelon-insights',
			'seomelon_page_seomelon-reports',
			'seomelon_page_seomelon-settings',
		);

		if ( ! in_array( $hook, $seomelon_pages, true ) ) {
			return;
		}

		wp_enqueue_script(
			'seomelon-admin',
			SEOMELON_PLUGIN_URL . 'admin/js/seomelon-admin.js',
			array( 'jquery' ),
			SEOMELON_VERSION,
			true
		);

		wp_localize_script(
			'seomelon-admin',
			'seomelon',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'seomelon_nonce' ),
				'i18n'     => array(
					'syncing'      => __( 'Syncing content...', 'seomelon' ),
					'scanning'     => __( 'Scanning...', 'seomelon' ),
					'generating'   => __( 'Generating AI content...', 'seomelon' ),
					'applying'     => __( 'Applying suggestions...', 'seomelon' ),
					'testing'      => __( 'Testing connection...', 'seomelon' ),
					'saving'       => __( 'Saving settings...', 'seomelon' ),
					'registering'  => __( 'Registering your site...', 'seomelon' ),
					'success'      => __( 'Success!', 'seomelon' ),
					'error'        => __( 'An error occurred.', 'seomelon' ),
					'connected'    => __( 'Connected', 'seomelon' ),
					'disconnected' => __( 'Not connected', 'seomelon' ),
					'confirm_bulk' => __( 'Are you sure? This may take a few minutes.', 'seomelon' ),
				),
			)
		);
	}

	/**
	 * Render the main dashboard page.
	 *
	 * Routes to the detail view when the view=detail parameter is present.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( SEOMelon::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seomelon' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['view'] ) && 'detail' === $_GET['view'] ) {
			include SEOMELON_PLUGIN_DIR . 'admin/views/content-detail.php';
			return;
		}

		include SEOMELON_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the insights page.
	 */
	public function render_insights(): void {
		if ( ! current_user_can( SEOMelon::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seomelon' ) );
		}

		include SEOMELON_PLUGIN_DIR . 'admin/views/insights.php';
	}

	/**
	 * Render the reports page.
	 */
	public function render_reports(): void {
		if ( ! current_user_can( SEOMelon::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seomelon' ) );
		}

		include SEOMELON_PLUGIN_DIR . 'admin/views/reports.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( SEOMelon::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seomelon' ) );
		}

		include SEOMELON_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * AJAX: Test API connection.
	 */
	public function ajax_test_connection(): void {
		$this->verify_ajax_request();

		$result = $this->api->verify();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Register site with the SEOMelon API and receive an API key.
	 */
	public function ajax_register(): void {
		$this->verify_ajax_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_request().
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$store_name = isset( $_POST['store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['store_name'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A valid email address is required.', 'seomelon' ) ) );
		}

		$site_url = home_url();

		// Use quick_connect for seamless token-based auth (no API key visible).
		$result = $this->api->quick_connect( $site_url, $email, $store_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Token is auto-saved by quick_connect(). Also handle legacy API key.
		if ( ! empty( $result['api_key'] ) ) {
			$this->api->set_api_key( $result['api_key'] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings(): void {
		$this->verify_ajax_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_request().
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$api_url = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : SEOMELON_API_URL;

		$content_types = array();
		if ( ! empty( $_POST['content_types'] ) && is_array( $_POST['content_types'] ) ) {
			// Accept any registered post type + 'category' for taxonomy.
			foreach ( $_POST['content_types'] as $type ) {
				$type = sanitize_key( wp_unslash( $type ) );
				if ( 'category' === $type || post_type_exists( $type ) ) {
					$content_types[] = $type;
				}
			}
		}

		$tone      = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'professional';
		$auto_sync = isset( $_POST['auto_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync'] ) ) : 'manual';

		// Whitelist allowed values.
		$allowed_tones = array( 'professional', 'casual', 'friendly', 'authoritative', 'playful' );
		if ( ! in_array( $tone, $allowed_tones, true ) ) {
			$tone = 'professional';
		}

		$allowed_sync = array( 'manual', 'daily', 'weekly' );
		if ( ! in_array( $auto_sync, $allowed_sync, true ) ) {
			$auto_sync = 'manual';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Save individual options.
		// Only update the API key if a value was provided, to prevent
		// accidentally wiping it when the password field is left blank.
		if ( ! empty( $api_key ) ) {
			update_option( 'seomelon_api_key', $api_key );
		}
		update_option( 'seomelon_api_url', $api_url );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target_locales = isset( $_POST['target_locales'] ) ? array_map( 'sanitize_text_field', (array) $_POST['target_locales'] ) : array();
		$valid_locales  = array( 'es', 'fr', 'de', 'it', 'pt', 'ja', 'ko', 'zh', 'ar', 'nl', 'sv', 'pl' );
		$target_locales = array_intersect( $target_locales, $valid_locales );

		// Save settings array.
		update_option(
			'seomelon_settings',
			array(
				'content_types'  => $content_types,
				'tone'           => $tone,
				'auto_sync'      => $auto_sync,
				'target_locales' => $target_locales,
			)
		);

		// Reschedule cron.
		$timestamp = wp_next_scheduled( 'seomelon_auto_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'seomelon_auto_sync' );
		}

		if ( 'manual' !== $auto_sync ) {
			$recurrence = 'daily' === $auto_sync ? 'daily' : 'weekly';
			wp_schedule_event( time(), $recurrence, 'seomelon_auto_sync' );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'seomelon' ) ) );
	}

	/**
	 * AJAX: Sync content to API.
	 */
	public function ajax_sync(): void {
		$this->verify_ajax_request();

		$result = $this->sync->sync_all();

		if ( ! empty( $result['errors'] ) ) {
			wp_send_json_error(
				array(
					'message' => implode( '; ', $result['errors'] ),
					'synced'  => $result['synced'],
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Trigger SEO scan.
	 */
	public function ajax_scan(): void {
		$this->verify_ajax_request();

		$result = $this->api->trigger_scan();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Trigger AI content generation.
	 */
	public function ajax_generate(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$content_ids = isset( $_POST['content_ids'] ) ? array_map( 'absint', (array) $_POST['content_ids'] ) : null;

		$result = $this->api->trigger_generate( $content_ids );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Apply suggestions to a specific post.
	 */
	public function ajax_apply(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_id      = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$content_id   = isset( $_POST['content_id'] ) ? absint( $_POST['content_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( wp_unslash( $_POST['content_type'] ) ) : 'post';

		if ( ! $post_id || ! $content_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing post or content ID.', 'seomelon' ) ) );
		}

		// Fetch suggestions from API (already normalized by the API client).
		$suggestions = $this->api->get_suggestions( $content_id );

		if ( is_wp_error( $suggestions ) ) {
			wp_send_json_error( array( 'message' => $suggestions->get_error_message() ) );
		}

		// Merge user-edited fields (from the editable detail page) into suggestions.
		$editable_keys = array( 'meta_title', 'meta_description', 'og_title', 'og_description', 'aeo_description' );
		foreach ( $editable_keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $_POST[ $key ] ) ) {
				$suggestions[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		// Categories are terms, not posts — use the term-aware apply method.
		if ( 'category' === $content_type ) {
			$applied = $this->apply->apply_to_term( $post_id, $suggestions );
		} else {
			$applied = $this->apply->apply( $post_id, $suggestions );
		}

		if ( $applied ) {
			wp_send_json_success( array( 'message' => __( 'Suggestions applied successfully.', 'seomelon' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'No suggestions available to apply.', 'seomelon' ) ) );
		}
	}

	/**
	 * AJAX: Poll job status.
	 */
	public function ajax_job_status(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tracking_id = isset( $_POST['tracking_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_id'] ) ) : '';

		if ( empty( $tracking_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing tracking ID.', 'seomelon' ) ) );
		}

		$result = $this->api->get_job_status( $tracking_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get content list from API (for dashboard refresh).
	 */
	public function ajax_get_content(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type = isset( $_POST['content_type'] ) ? sanitize_text_field( wp_unslash( $_POST['content_type'] ) ) : null;

		// Flush cache to get fresh data.
		$this->api->flush_cache();

		$result = $this->api->get_content( $type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get Google Search Console OAuth connect URL.
	 */
	public function ajax_gsc_connect(): void {
		$this->verify_ajax_request();

		$result = $this->api->get_gsc_connect_url();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Disconnect Google Search Console.
	 */
	public function ajax_gsc_disconnect(): void {
		$this->verify_ajax_request();

		$result = $this->api->disconnect_gsc();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Flush cached GSC data.
		$this->api->flush_cache();

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get Google Search Console connection status.
	 */
	public function ajax_gsc_status(): void {
		$this->verify_ajax_request();

		$result = $this->api->get_gsc_status();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Verify AJAX request nonce and capability.
	 */
	private function verify_ajax_request(): void {
		check_ajax_referer( 'seomelon_nonce', 'nonce' );

		if ( ! current_user_can( SEOMelon::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seomelon' ) ), 403 );
		}
	}
}
