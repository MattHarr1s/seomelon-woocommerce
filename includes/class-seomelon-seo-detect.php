<?php
/**
 * SEO plugin detection and meta field abstraction.
 *
 * Detects which SEO plugin is active (Yoast, RankMath, SEOPress, AIOSEO)
 * and provides a unified interface for reading and writing meta titles
 * and descriptions regardless of which plugin is installed.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_SEO_Detect
 */
class SEOMelon_SEO_Detect {

	/**
	 * Meta key mapping for each supported SEO plugin.
	 *
	 * @var array<string, array{title: string, description: string}>
	 */
	private const META_KEYS = array(
		'yoast'    => array(
			'title'       => '_yoast_wpseo_title',
			'description' => '_yoast_wpseo_metadesc',
		),
		'rankmath' => array(
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
		),
		'seopress' => array(
			'title'       => '_seopress_titles_title',
			'description' => '_seopress_titles_desc',
		),
		'aioseo'   => array(
			'title'       => '_aioseo_title',
			'description' => '_aioseo_description',
		),
	);

	/**
	 * Cached detection result.
	 *
	 * @var string|null
	 */
	private ?string $detected = null;

	/**
	 * Detect the currently active SEO plugin.
	 *
	 * @return string One of 'yoast', 'rankmath', 'seopress', 'aioseo', or 'none'.
	 */
	public function get_active_plugin(): string {
		if ( null !== $this->detected ) {
			return $this->detected;
		}

		// Ensure is_plugin_active() is available (it is not loaded on the
		// frontend or during cron execution).
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( defined( 'WPSEO_VERSION' ) || is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
			$this->detected = 'yoast';
		} elseif ( defined( 'RANK_MATH_VERSION' ) || is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			$this->detected = 'rankmath';
		} elseif ( defined( 'SEOPRESS_VERSION' ) || is_plugin_active( 'wp-seopress/seopress.php' ) ) {
			$this->detected = 'seopress';
		} elseif ( defined( 'AIOSEO_VERSION' ) || is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) {
			$this->detected = 'aioseo';
		} else {
			$this->detected = 'none';
		}

		return $this->detected;
	}

	/**
	 * Get the display name of the active SEO plugin.
	 *
	 * @return string
	 */
	public function get_active_plugin_name(): string {
		$names = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'seopress' => 'SEOPress',
			'aioseo'   => 'All in One SEO',
			'none'     => 'None',
		);

		return $names[ $this->get_active_plugin() ] ?? 'Unknown';
	}

	/**
	 * Read the meta title for a given post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string
	 */
	public function get_meta_title( int $post_id ): string {
		$plugin = $this->get_active_plugin();

		if ( 'none' !== $plugin && isset( self::META_KEYS[ $plugin ] ) ) {
			$value = get_post_meta( $post_id, self::META_KEYS[ $plugin ]['title'], true );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		// Fallback: use the post title.
		return get_the_title( $post_id );
	}

	/**
	 * Write the meta title for a given post.
	 *
	 * @param int    $post_id WordPress post ID.
	 * @param string $title   New meta title value.
	 */
	public function set_meta_title( int $post_id, string $title ): void {
		$plugin = $this->get_active_plugin();

		if ( 'none' !== $plugin && isset( self::META_KEYS[ $plugin ] ) ) {
			update_post_meta( $post_id, self::META_KEYS[ $plugin ]['title'], sanitize_text_field( $title ) );
		}

		// Always store a copy in SEOMelon meta for reference.
		update_post_meta( $post_id, '_seomelon_meta_title', sanitize_text_field( $title ) );
	}

	/**
	 * Read the meta description for a given post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string
	 */
	public function get_meta_description( int $post_id ): string {
		$plugin = $this->get_active_plugin();

		if ( 'none' !== $plugin && isset( self::META_KEYS[ $plugin ] ) ) {
			$value = get_post_meta( $post_id, self::META_KEYS[ $plugin ]['description'], true );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		// Fallback: use the excerpt.
		$post = get_post( $post_id );
		return $post ? wp_strip_all_tags( $post->post_excerpt ) : '';
	}

	/**
	 * Write the meta description for a given post.
	 *
	 * @param int    $post_id WordPress post ID.
	 * @param string $desc    New meta description value.
	 */
	public function set_meta_description( int $post_id, string $desc ): void {
		$plugin = $this->get_active_plugin();

		if ( 'none' !== $plugin && isset( self::META_KEYS[ $plugin ] ) ) {
			update_post_meta( $post_id, self::META_KEYS[ $plugin ]['description'], sanitize_textarea_field( $desc ) );
		}

		// Always store a copy in SEOMelon meta for reference.
		update_post_meta( $post_id, '_seomelon_meta_description', sanitize_textarea_field( $desc ) );
	}

	/**
	 * Get the OG meta keys for the active plugin (if supported).
	 *
	 * @return array{og_title: string|null, og_description: string|null}
	 */
	public function get_og_keys(): array {
		$og_keys = array(
			'yoast'    => array(
				'og_title'       => '_yoast_wpseo_opengraph-title',
				'og_description' => '_yoast_wpseo_opengraph-description',
			),
			'rankmath' => array(
				'og_title'       => 'rank_math_facebook_title',
				'og_description' => 'rank_math_facebook_description',
			),
			'seopress' => array(
				'og_title'       => '_seopress_social_fb_title',
				'og_description' => '_seopress_social_fb_desc',
			),
			'aioseo'   => array(
				'og_title'       => '_aioseo_og_title',
				'og_description' => '_aioseo_og_description',
			),
		);

		$plugin = $this->get_active_plugin();

		return $og_keys[ $plugin ] ?? array(
			'og_title'       => null,
			'og_description' => null,
		);
	}
}
