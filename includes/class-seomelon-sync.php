<?php
/**
 * Content synchronization between WordPress and the SEOMelon API.
 *
 * Extracts WooCommerce products, posts, pages, and categories from the
 * local database and pushes them to the remote API in batches.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Sync
 */
class SEOMelon_Sync {

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
	 * Number of items per API batch.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Constructor.
	 *
	 * @param SEOMelon_API        $api        API client instance.
	 * @param SEOMelon_SEO_Detect $seo_detect SEO detection instance.
	 */
	public function __construct( SEOMelon_API $api, SEOMelon_SEO_Detect $seo_detect ) {
		$this->api        = $api;
		$this->seo_detect = $seo_detect;
	}

	/**
	 * Extract WooCommerce products.
	 *
	 * @param int $limit Maximum products to retrieve.
	 * @return array
	 */
	public function get_products( int $limit = 100 ): array {
		if ( ! SEOMelon::is_woocommerce_active() ) {
			return array();
		}

		$args = array(
			'status' => 'publish',
			'limit'  => $limit,
			'return' => 'ids',
		);

		$product_ids = wc_get_products( $args );
		$items       = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$images = array();
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				$url = wp_get_attachment_url( $image_id );
				if ( $url ) {
					$images[] = $url;
				}
			}

			foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
				$url = wp_get_attachment_url( $gallery_id );
				if ( $url ) {
					$images[] = $url;
				}
			}

			$items[] = array(
				'platform_id'            => $product_id,
				'content_type'           => 'product',
				'title'                  => $product->get_name(),
				'description'            => $product->get_description(),
				'handle'                 => $product->get_slug(),
				'product_type'           => $this->get_product_type_label( $product ),
				'current_meta_title'     => $this->seo_detect->get_meta_title( $product_id ),
				'current_meta_description' => $this->seo_detect->get_meta_description( $product_id ),
				'images'                 => $images,
				'url'                    => get_permalink( $product_id ),
				'price'                  => $product->get_price(),
				'status'                 => $product->get_status(),
			);
		}

		return $items;
	}

	/**
	 * Extract published WordPress posts.
	 *
	 * @param int $limit Maximum posts to retrieve.
	 * @return array
	 */
	public function get_posts( int $limit = 100 ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
			)
		);

		$items = array();

		foreach ( $query->posts as $post_id ) {
			$post    = get_post( $post_id );
			$images  = array();
			$thumb   = get_the_post_thumbnail_url( $post_id, 'full' );

			if ( $thumb ) {
				$images[] = $thumb;
			}

			$items[] = array(
				'platform_id'            => $post_id,
				'content_type'           => 'post',
				'title'                  => $post->post_title,
				'description'            => wp_strip_all_tags( $post->post_content ),
				'handle'                 => $post->post_name,
				'product_type'           => '',
				'current_meta_title'     => $this->seo_detect->get_meta_title( $post_id ),
				'current_meta_description' => $this->seo_detect->get_meta_description( $post_id ),
				'images'                 => $images,
				'url'                    => get_permalink( $post_id ),
				'status'                 => $post->post_status,
			);
		}

		return $items;
	}

	/**
	 * Extract published WordPress pages.
	 *
	 * @param int $limit Maximum pages to retrieve.
	 * @return array
	 */
	public function get_pages( int $limit = 100 ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
			)
		);

		$items = array();

		foreach ( $query->posts as $post_id ) {
			$post    = get_post( $post_id );
			$images  = array();
			$thumb   = get_the_post_thumbnail_url( $post_id, 'full' );

			if ( $thumb ) {
				$images[] = $thumb;
			}

			$items[] = array(
				'platform_id'            => $post_id,
				'content_type'           => 'page',
				'title'                  => $post->post_title,
				'description'            => wp_strip_all_tags( $post->post_content ),
				'handle'                 => $post->post_name,
				'product_type'           => '',
				'current_meta_title'     => $this->seo_detect->get_meta_title( $post_id ),
				'current_meta_description' => $this->seo_detect->get_meta_description( $post_id ),
				'images'                 => $images,
				'url'                    => get_permalink( $post_id ),
				'status'                 => $post->post_status,
			);
		}

		return $items;
	}

	/**
	 * Extract categories (WooCommerce product categories and/or WordPress categories).
	 *
	 * @return array
	 */
	public function get_categories(): array {
		$items = array();

		// WooCommerce product categories.
		if ( SEOMelon::is_woocommerce_active() ) {
			$product_cats = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);

			if ( ! is_wp_error( $product_cats ) ) {
				foreach ( $product_cats as $term ) {
					$items[] = array(
						'platform_id'            => $term->term_id,
						'content_type'           => 'category',
						'title'                  => $term->name,
						'description'            => $term->description,
						'handle'                 => $term->slug,
						'product_type'           => 'product_category',
						'current_meta_title'     => $term->name,
						'current_meta_description' => $term->description,
						'images'                 => array(),
						'url'                    => get_term_link( $term ),
						'status'                 => 'publish',
					);
				}
			}
		}

		// WordPress post categories.
		$post_cats = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $post_cats ) ) {
			foreach ( $post_cats as $term ) {
				$items[] = array(
					'platform_id'            => $term->term_id,
					'content_type'           => 'category',
					'title'                  => $term->name,
					'description'            => $term->description,
					'handle'                 => $term->slug,
					'product_type'           => 'post_category',
					'current_meta_title'     => $term->name,
					'current_meta_description' => $term->description,
					'images'                 => array(),
					'url'                    => get_term_link( $term ),
					'status'                 => 'publish',
				);
			}
		}

		return $items;
	}

	/**
	 * Sync all enabled content types to the SEOMelon API.
	 *
	 * Content is sent in batches to avoid memory and timeout issues.
	 *
	 * @return array{synced: int, errors: array}
	 */
	public function sync_all(): array {
		$settings      = get_option( 'seomelon_settings', array() );
		$content_types = $settings['content_types'] ?? array( 'product', 'post', 'page' );

		$all_items = array();

		if ( in_array( 'product', $content_types, true ) ) {
			$all_items = array_merge( $all_items, $this->get_products() );
		}

		if ( in_array( 'post', $content_types, true ) ) {
			$all_items = array_merge( $all_items, $this->get_posts() );
		}

		if ( in_array( 'page', $content_types, true ) ) {
			$all_items = array_merge( $all_items, $this->get_pages() );
		}

		if ( in_array( 'category', $content_types, true ) ) {
			$all_items = array_merge( $all_items, $this->get_categories() );
		}

		$synced = 0;
		$errors = array();

		// Send in batches.
		$batches = array_chunk( $all_items, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$result = $this->api->sync_content( $batch );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$synced += count( $batch );
			}
		}

		// Update last sync timestamp.
		update_option( 'seomelon_last_sync', current_time( 'mysql' ) );

		// Flush content cache so dashboard reflects new data.
		$this->api->flush_cache();

		return array(
			'synced' => $synced,
			'errors' => $errors,
			'total'  => count( $all_items ),
		);
	}

	/**
	 * Sync a single content item by its WordPress post ID and type.
	 *
	 * @param int    $post_id      WordPress post ID.
	 * @param string $content_type One of 'product', 'post', 'page'.
	 * @return array|WP_Error
	 */
	public function sync_single( int $post_id, string $content_type ) {
		$item = null;

		switch ( $content_type ) {
			case 'product':
				if ( SEOMelon::is_woocommerce_active() ) {
					$product = wc_get_product( $post_id );
					if ( $product ) {
						$item = $this->build_product_item( $product );
					}
				}
				break;

			case 'post':
			case 'page':
				$post = get_post( $post_id );
				if ( $post && $post->post_type === $content_type ) {
					$images = array();
					$thumb  = get_the_post_thumbnail_url( $post_id, 'full' );
					if ( $thumb ) {
						$images[] = $thumb;
					}

					$item = array(
						'platform_id'              => $post_id,
						'content_type'             => $content_type,
						'title'                    => $post->post_title,
						'description'              => wp_strip_all_tags( $post->post_content ),
						'handle'                   => $post->post_name,
						'product_type'             => '',
						'current_meta_title'       => $this->seo_detect->get_meta_title( $post_id ),
						'current_meta_description' => $this->seo_detect->get_meta_description( $post_id ),
						'images'                   => $images,
						'url'                      => get_permalink( $post_id ),
						'status'                   => $post->post_status,
					);
				}
				break;
		}

		if ( null === $item ) {
			return new WP_Error( 'seomelon_not_found', __( 'Content item not found.', 'seomelon' ) );
		}

		return $this->api->sync_content( array( $item ) );
	}

	/**
	 * Build an API-ready array from a WC_Product.
	 *
	 * @param WC_Product $product WooCommerce product object.
	 * @return array
	 */
	private function build_product_item( $product ): array {
		$product_id = $product->get_id();
		$images     = array();
		$image_id   = $product->get_image_id();

		if ( $image_id ) {
			$url = wp_get_attachment_url( $image_id );
			if ( $url ) {
				$images[] = $url;
			}
		}

		foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
			$url = wp_get_attachment_url( $gallery_id );
			if ( $url ) {
				$images[] = $url;
			}
		}

		return array(
			'platform_id'              => $product_id,
			'content_type'             => 'product',
			'title'                    => $product->get_name(),
			'description'              => $product->get_description(),
			'handle'                   => $product->get_slug(),
			'product_type'             => $this->get_product_type_label( $product ),
			'current_meta_title'       => $this->seo_detect->get_meta_title( $product_id ),
			'current_meta_description' => $this->seo_detect->get_meta_description( $product_id ),
			'images'                   => $images,
			'url'                      => get_permalink( $product_id ),
			'price'                    => $product->get_price(),
			'status'                   => $product->get_status(),
		);
	}

	/**
	 * Derive a human-readable product type label.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	private function get_product_type_label( $product ): string {
		$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );

		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			return implode( ', ', $cats );
		}

		return $product->get_type();
	}
}
