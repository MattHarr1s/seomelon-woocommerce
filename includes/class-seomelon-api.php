<?php
/**
 * API client for communicating with the SEOMelon Laravel backend.
 *
 * All external HTTP calls are routed through this class so that
 * authentication, caching, and error handling stay in one place.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_API
 */
class SEOMelon_API {

	/**
	 * Base URL of the SEOMelon API.
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * Bearer token used for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Default cache TTL in seconds (5 minutes).
	 */
	private const CACHE_TTL = 300;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_url = untrailingslashit( get_option( 'seomelon_api_url', SEOMELON_API_URL ) );
		$this->api_key = get_option( 'seomelon_api_key', '' );
	}

	/**
	 * Register a new store and receive an API key.
	 *
	 * This is the only unauthenticated endpoint. It sends the site URL,
	 * email, and platform to obtain a new API key.
	 *
	 * @param string $site_url   The WordPress site URL.
	 * @param string $email      Admin email address.
	 * @param string $store_name Optional human-readable store name.
	 * @return array|WP_Error
	 */
	public function register( string $site_url, string $email, string $store_name = '' ) {
		$platform = SEOMelon::is_woocommerce_active() ? 'woocommerce' : 'wordpress';

		$data = array(
			'site_url'   => $site_url,
			'email'      => $email,
			'platform'   => $platform,
			'store_name' => $store_name,
		);

		return $this->request_unauthenticated( 'POST', '/auth/register', $data );
	}

	/**
	 * Quick Connect: register or connect in one step.
	 *
	 * Returns a Passport access token — no API key visible to the user.
	 * Falls back to the legacy /auth/register if /connect is unavailable.
	 *
	 * @param string $site_url   The WordPress site URL.
	 * @param string $email      Admin email address.
	 * @param string $store_name Optional store name.
	 * @return array|WP_Error
	 */
	public function quick_connect( string $site_url, string $email, string $store_name = '' ) {
		$platform = SEOMelon::is_woocommerce_active() ? 'woocommerce' : 'wordpress';

		$data = array(
			'site_url'   => $site_url,
			'email'      => $email,
			'platform'   => $platform,
			'store_name' => $store_name,
		);

		$result = $this->request_unauthenticated( 'POST', '/connect', $data );

		if ( is_wp_error( $result ) ) {
			// Fall back to legacy registration
			return $this->register( $site_url, $email, $store_name );
		}

		// Store the access token (Passport token or API key)
		if ( ! empty( $result['access_token'] ) ) {
			$this->set_api_key( $result['access_token'] );
		} elseif ( ! empty( $result['api_key'] ) ) {
			$this->set_api_key( $result['api_key'] );
		}

		return $result;
	}

	/**
	 * Verify the API connection and return account details.
	 *
	 * @return array|WP_Error
	 */
	public function verify() {
		return $this->request( 'GET', '/auth/verify' );
	}

	/**
	 * Sync local content items to the API.
	 *
	 * @param array $items Content items to sync.
	 * @return array|WP_Error
	 */
	public function sync_content( array $items ) {
		return $this->request( 'POST', '/content/sync', array( 'items' => $items ) );
	}

	/**
	 * Retrieve content items from the API.
	 *
	 * The Laravel backend returns { items: [...] }.
	 *
	 * @param string|null $type Optional content type filter.
	 * @return array|WP_Error
	 */
	public function get_content( ?string $type = null ) {
		$endpoint = '/content';
		if ( $type ) {
			$endpoint .= '?type=' . rawurlencode( $type );
		}

		$cache_key = 'seomelon_content_' . md5( $endpoint );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', $endpoint );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Get a single content item by its remote ID.
	 *
	 * @param int $content_id Remote content ID.
	 * @return array|WP_Error
	 */
	public function get_content_by_id( int $content_id ) {
		$cache_key = 'seomelon_content_item_' . $content_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/content/' . $content_id );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Get a single report by ID.
	 *
	 * @param int $report_id Remote report ID.
	 * @return array|WP_Error
	 */
	public function get_report( int $report_id ) {
		$cache_key = 'seomelon_report_' . $report_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/reports/' . $report_id );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Generate multi-language translations for content items.
	 *
	 * @param string   $locale         Target locale (e.g. 'es', 'fr', 'de').
	 * @param int[]    $content_ids    Specific content IDs (optional).
	 * @param string   $scope          'all' to generate for all content (optional).
	 * @return array|WP_Error
	 */
	public function generate_translations( string $locale, array $content_ids = array(), string $scope = '' ) {
		$body = array( 'locale' => $locale );

		if ( ! empty( $content_ids ) ) {
			$body['product_seo_ids'] = $content_ids;
		} elseif ( 'all' === $scope ) {
			$body['scope'] = 'all';
		}

		return $this->request( 'POST', '/translations/generate', $body );
	}

	/**
	 * Get translations for a specific content item.
	 *
	 * @param int    $content_id Remote content ID.
	 * @param string $locale     Optional specific locale.
	 * @return array|WP_Error
	 */
	public function get_translations( int $content_id, string $locale = '' ) {
		$path = '/translations/' . $content_id;
		if ( $locale ) {
			$path .= '/' . $locale;
		}

		return $this->request( 'GET', $path );
	}

	/**
	 * Get SEO suggestions for a specific content item.
	 *
	 * The Laravel backend returns suggested_ prefixed fields.
	 * This method normalizes them to shorter names for the plugin.
	 *
	 * @param int $content_id Remote content ID.
	 * @return array|WP_Error
	 */
	public function get_suggestions( int $content_id ) {
		$cache_key = 'seomelon_suggestions_' . $content_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/content/' . $content_id . '/suggestions' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Normalize the suggested_ prefix fields into shorter keys
		// that the Apply class expects.
		$normalized = array(
			'meta_title'       => $result['suggested_meta_title'] ?? '',
			'meta_description' => $result['suggested_meta_description'] ?? '',
			'og_title'         => $result['suggested_og_title'] ?? '',
			'og_description'   => $result['suggested_og_description'] ?? '',
			'schema'           => $result['suggested_schema_markup'] ?? '',
			'faq_schema'       => $result['suggested_faq_schema'] ?? '',
			'aeo_description'  => $result['suggested_aeo_description'] ?? '',
			'image_alt_texts'  => $result['suggested_image_alt_texts'] ?? array(),
			'seo_score'        => $result['seo_score'] ?? null,
			'status'           => $result['status'] ?? '',
			'platform_id'      => $result['platform_id'] ?? '',
		);

		set_transient( $cache_key, $normalized, self::CACHE_TTL );

		return $normalized;
	}

	/**
	 * Get full gamification overview (health score, achievements, streak, weather).
	 *
	 * @return array|WP_Error
	 */
	public function get_gamification_overview() {
		$cache_key = 'seomelon_gamification';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/gamification/overview' );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Get health score with component breakdown.
	 *
	 * @return array|WP_Error
	 */
	public function get_health_score() {
		return $this->request( 'GET', '/gamification/health-score' );
	}

	/**
	 * Trigger an SEO scan of synced content.
	 *
	 * @return array|WP_Error
	 */
	public function trigger_scan() {
		return $this->request( 'POST', '/scan' );
	}

	/**
	 * Trigger AI content generation.
	 *
	 * When no specific content IDs are given, sends scope=all so
	 * the Laravel backend generates for all eligible items.
	 *
	 * @param array|null $content_ids Optional content IDs to generate for.
	 * @return array|WP_Error
	 */
	public function trigger_generate( ?array $content_ids = null ) {
		$data = array();
		if ( $content_ids ) {
			$data['content_ids'] = $content_ids;
		} else {
			$data['scope'] = 'all';
		}

		return $this->request( 'POST', '/generate', $data );
	}

	/**
	 * Poll job status by tracking ID.
	 *
	 * @param string $tracking_id Job tracking identifier.
	 * @return array|WP_Error
	 */
	public function get_job_status( string $tracking_id ) {
		return $this->request( 'GET', '/job-status/' . rawurlencode( $tracking_id ) );
	}

	/**
	 * Retrieve AI-generated business insights.
	 *
	 * The Laravel backend returns { insights: [...], plan: '...' }.
	 *
	 * @return array|WP_Error
	 */
	public function get_insights() {
		$cache_key = 'seomelon_insights';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/insights' );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Retrieve SEO reports.
	 *
	 * @return array|WP_Error
	 */
	public function get_reports() {
		$cache_key = 'seomelon_reports';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/reports' );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Get the Google Search Console OAuth connect URL.
	 *
	 * @return array|WP_Error Response with 'url' key on success.
	 */
	public function get_gsc_connect_url() {
		return $this->request( 'POST', '/gsc/connect-url' );
	}

	/**
	 * Exchange an OAuth authorization code for GSC tokens.
	 *
	 * @param string $code Authorization code from Google OAuth callback.
	 * @return array|WP_Error
	 */
	public function gsc_callback( string $code ) {
		return $this->request( 'POST', '/gsc/callback', array( 'code' => $code ) );
	}

	/**
	 * Get the current GSC connection status.
	 *
	 * @return array|WP_Error Response with 'connected', 'site_url', 'connected_at'.
	 */
	public function get_gsc_status() {
		return $this->request( 'GET', '/gsc/status' );
	}

	/**
	 * Disconnect Google Search Console.
	 *
	 * @return array|WP_Error
	 */
	public function disconnect_gsc() {
		return $this->request( 'POST', '/gsc/disconnect' );
	}

	/**
	 * Get GSC performance data (impressions, clicks, queries).
	 *
	 * @param int $days Number of days of data to retrieve (default 30).
	 * @return array|WP_Error Performance data on success.
	 */
	public function get_gsc_performance( int $days = 30 ) {
		$cache_key = 'seomelon_gsc_performance_' . $days;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/gsc/performance?days=' . $days );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Clear all transient caches created by this plugin.
	 */
	public function flush_cache(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_seomelon_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_seomelon_' ) . '%'
			)
		);
	}

	/**
	 * Check whether an API key has been configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_key );
	}

	/**
	 * Update the stored API key (used after registration).
	 *
	 * @param string $key New API key value.
	 */
	public function set_api_key( string $key ): void {
		$this->api_key = $key;
		update_option( 'seomelon_api_key', $key );
	}

	/**
	 * Perform an unauthenticated HTTP request (used only for registration).
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint path.
	 * @param array  $data     Request body data.
	 * @return array|WP_Error
	 */
	private function request_unauthenticated( string $method, string $endpoint, array $data = array() ) {
		$url = $this->api_url . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'X-Platform'   => 'wordpress',
				'X-Plugin-Ver' => SEOMELON_VERSION,
			),
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		return $this->parse_response( wp_remote_request( $url, $args ) );
	}

	/**
	 * Perform an authenticated HTTP request to the SEOMelon API.
	 *
	 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
	 * @param string $endpoint API endpoint path (e.g. '/content').
	 * @param array  $data     Request body data for POST/PUT.
	 * @return array|WP_Error  Decoded JSON response or WP_Error on failure.
	 */
	private function request( string $method, string $endpoint, array $data = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'seomelon_no_api_key',
				__( 'SEOMelon API key is not configured. Please add your API key in Settings.', 'seomelon' )
			);
		}

		$url = $this->api_url . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'X-Platform'    => 'wordpress',
				'X-Plugin-Ver'  => SEOMELON_VERSION,
			),
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		return $this->parse_response( wp_remote_request( $url, $args ) );
	}

	/**
	 * Parse the raw HTTP response into a decoded array or WP_Error.
	 *
	 * @param array|WP_Error $response Raw wp_remote_request response.
	 * @return array|WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = $json['message'] ?? $json['error'] ?? __( 'Unknown API error.', 'seomelon' );
			return new WP_Error(
				'seomelon_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'API error %1$d: %2$s', 'seomelon' ),
					$code,
					$message
				),
				array( 'status' => $code )
			);
		}

		if ( null === $json ) {
			return new WP_Error(
				'seomelon_invalid_response',
				__( 'Invalid JSON response from SEOMelon API.', 'seomelon' )
			);
		}

		return $json;
	}
}
