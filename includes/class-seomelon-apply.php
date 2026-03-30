<?php
/**
 * Apply SEO suggestions from the SEOMelon API to local WordPress content.
 *
 * Writes generated meta titles, descriptions, AEO content, FAQ schema,
 * and OG tags back to the appropriate SEO plugin meta fields.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Apply
 */
class SEOMelon_Apply {

	/**
	 * API client.
	 *
	 * @var SEOMelon_API
	 */
	private SEOMelon_API $api;

	/**
	 * SEO plugin detector.
	 *
	 * @var SEOMelon_SEO_Detect
	 */
	private SEOMelon_SEO_Detect $seo_detect;

	/**
	 * Constructor.
	 *
	 * @param SEOMelon_API        $api        API client.
	 * @param SEOMelon_SEO_Detect $seo_detect SEO detection instance.
	 */
	public function __construct( SEOMelon_API $api, SEOMelon_SEO_Detect $seo_detect ) {
		$this->api        = $api;
		$this->seo_detect = $seo_detect;
	}

	/**
	 * Apply generated suggestions to a local content item.
	 *
	 * Fetches the latest suggestions from the API for the given content
	 * ID, then writes each supported field to the appropriate meta keys.
	 *
	 * @param int   $post_id     WordPress post ID.
	 * @param array $suggestions Pre-fetched suggestions array (optional).
	 * @return bool True on success, false on failure.
	 */
	public function apply( int $post_id, array $suggestions = array() ): bool {
		if ( empty( $suggestions ) ) {
			return false;
		}

		$applied = false;

		// Meta title.
		if ( ! empty( $suggestions['meta_title'] ) ) {
			$this->seo_detect->set_meta_title( $post_id, $suggestions['meta_title'] );
			$applied = true;
		}

		// Meta description.
		if ( ! empty( $suggestions['meta_description'] ) ) {
			$this->seo_detect->set_meta_description( $post_id, $suggestions['meta_description'] );
			$applied = true;
		}

		// AEO description (stored in custom meta).
		if ( ! empty( $suggestions['aeo_description'] ) ) {
			update_post_meta(
				$post_id,
				'_seomelon_aeo_description',
				sanitize_textarea_field( $suggestions['aeo_description'] )
			);
			$applied = true;
		}

		// FAQ schema (stored as JSON in custom meta, output via wp_head).
		if ( ! empty( $suggestions['faq_schema'] ) ) {
			$faq_data = is_string( $suggestions['faq_schema'] )
				? json_decode( $suggestions['faq_schema'], true )
				: $suggestions['faq_schema'];

			if ( is_array( $faq_data ) ) {
				update_post_meta( $post_id, '_seomelon_faq_schema', wp_json_encode( $faq_data ) );
				$applied = true;
			}
		}

		// Product schema (stored as JSON in custom meta, output via wp_head).
		if ( ! empty( $suggestions['schema'] ) ) {
			$schema_data = is_string( $suggestions['schema'] )
				? json_decode( $suggestions['schema'], true )
				: $suggestions['schema'];

			if ( is_array( $schema_data ) ) {
				update_post_meta( $post_id, '_seomelon_schema', wp_json_encode( $schema_data ) );
				$applied = true;
			}
		}

		// OG tags (write to the active SEO plugin or custom meta).
		if ( ! empty( $suggestions['og_title'] ) || ! empty( $suggestions['og_description'] ) ) {
			$og_keys = $this->seo_detect->get_og_keys();

			if ( ! empty( $suggestions['og_title'] ) ) {
				if ( $og_keys['og_title'] ) {
					update_post_meta( $post_id, $og_keys['og_title'], sanitize_text_field( $suggestions['og_title'] ) );
				}
				update_post_meta( $post_id, '_seomelon_og_title', sanitize_text_field( $suggestions['og_title'] ) );
				$applied = true;
			}

			if ( ! empty( $suggestions['og_description'] ) ) {
				if ( $og_keys['og_description'] ) {
					update_post_meta( $post_id, $og_keys['og_description'], sanitize_textarea_field( $suggestions['og_description'] ) );
				}
				update_post_meta( $post_id, '_seomelon_og_description', sanitize_textarea_field( $suggestions['og_description'] ) );
				$applied = true;
			}
		}

		// SEO score.
		if ( isset( $suggestions['seo_score'] ) ) {
			update_post_meta( $post_id, '_seomelon_seo_score', absint( $suggestions['seo_score'] ) );
		}

		// Mark content as having had suggestions applied.
		if ( $applied ) {
			update_post_meta( $post_id, '_seomelon_applied_at', current_time( 'mysql' ) );
		}

		return $applied;
	}

	/**
	 * Output JSON-LD schema markup on the front end.
	 *
	 * Hooked to wp_head. Only outputs on singular pages that have
	 * schema data stored in post meta.
	 */
	public function output_schema(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Output FAQ schema.
		$faq_json = get_post_meta( $post_id, '_seomelon_faq_schema', true );
		if ( ! empty( $faq_json ) ) {
			$faq = json_decode( $faq_json, true );
			if ( is_array( $faq ) ) {
				$faq_schema = array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => array(),
				);

				foreach ( $faq as $item ) {
					if ( ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
						$faq_schema['mainEntity'][] = array(
							'@type'          => 'Question',
							'name'           => $item['question'],
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => $item['answer'],
							),
						);
					}
				}

				if ( ! empty( $faq_schema['mainEntity'] ) ) {
					printf(
						'<script type="application/ld+json">%s</script>' . "\n",
						wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
					);
				}
			}
		}

		// Output custom product/page schema.
		$schema_json = get_post_meta( $post_id, '_seomelon_schema', true );
		if ( ! empty( $schema_json ) ) {
			$schema = json_decode( $schema_json, true );
			if ( is_array( $schema ) ) {
				printf(
					'<script type="application/ld+json">%s</script>' . "\n",
					wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
				);
			}
		}
	}
}
