<?php
/**
 * Frontend AEO content injection.
 *
 * Automatically appends FAQ accordions and AEO descriptions to posts,
 * pages, and WooCommerce products using universal WordPress hooks.
 * Works regardless of theme.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Frontend
 */
class SEOMelon_Frontend {

	/**
	 * Whether AEO content has already been rendered for the current post
	 * (prevents duplicate output from multiple hook firings).
	 *
	 * @var int[]
	 */
	private array $rendered = array();

	/**
	 * Constructor. Registers all frontend hooks.
	 */
	public function __construct() {
		// Append AEO content to posts and pages via the_content filter.
		add_filter( 'the_content', array( $this, 'append_aeo_to_content' ), 99 );

		// WooCommerce: add a FAQ tab on product pages.
		if ( SEOMelon::is_woocommerce_active() ) {
			add_filter( 'woocommerce_product_tabs', array( $this, 'add_product_faq_tab' ) );
			add_action( 'woocommerce_product_after_tabs', array( $this, 'output_product_aeo_description' ) );
		}

		// Enqueue minimal frontend CSS when AEO content is present.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_styles' ) );
	}

	/**
	 * Append FAQ accordion and AEO description to post/page content.
	 *
	 * Fires on the_content filter. Skips WooCommerce products (handled
	 * separately via product tabs) and non-singular views.
	 *
	 * @param string $content The post content.
	 * @return string Modified content with AEO appended.
	 */
	public function append_aeo_to_content( string $content ): string {
		if ( ! is_singular() || is_admin() || wp_doing_ajax() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Skip WooCommerce products — they use the tab system instead.
		if ( 'product' === get_post_type( $post_id ) ) {
			return $content;
		}

		// Prevent duplicate rendering.
		if ( in_array( $post_id, $this->rendered, true ) ) {
			return $content;
		}

		$aeo_html = $this->build_aeo_html( $post_id );
		if ( empty( $aeo_html ) ) {
			return $content;
		}

		$this->rendered[] = $post_id;

		return $content . "\n" . $aeo_html;
	}

	/**
	 * Add a FAQ tab to WooCommerce product pages.
	 *
	 * @param array $tabs Existing product tabs.
	 * @return array Modified tabs.
	 */
	public function add_product_faq_tab( array $tabs ): array {
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return $tabs;
		}

		$faq_json = get_post_meta( $product_id, '_seomelon_faq_schema', true );
		if ( empty( $faq_json ) ) {
			return $tabs;
		}

		$faqs = json_decode( $faq_json, true );
		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return $tabs;
		}

		$tabs['seomelon_faq'] = array(
			'title'    => __( 'FAQ', 'seomelon' ),
			'priority' => 35,
			'callback' => array( $this, 'render_product_faq_tab' ),
		);

		return $tabs;
	}

	/**
	 * Render the FAQ tab content for WooCommerce products.
	 */
	public function render_product_faq_tab(): void {
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		$faq_html = $this->build_faq_html( $product_id );
		if ( ! empty( $faq_html ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is built with esc_html() internally.
			echo $faq_html;
		}
	}

	/**
	 * Output the AEO description below WooCommerce product tabs.
	 *
	 * Uses woocommerce_product_after_tabs so it appears after the tab
	 * panels, visible on all themes.
	 */
	public function output_product_aeo_description(): void {
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		if ( in_array( $product_id, $this->rendered, true ) ) {
			return;
		}

		$aeo_desc = get_post_meta( $product_id, '_seomelon_aeo_description', true );
		if ( empty( $aeo_desc ) ) {
			return;
		}

		$this->rendered[] = $product_id;

		echo '<div class="seomelon-aeo-section">';
		echo '<div class="seomelon-aeo-description">';
		echo wp_kses_post( wpautop( $aeo_desc ) );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Enqueue frontend styles only on pages that have AEO content.
	 */
	public function maybe_enqueue_styles(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$has_faq = ! empty( get_post_meta( $post_id, '_seomelon_faq_schema', true ) );
		$has_aeo = ! empty( get_post_meta( $post_id, '_seomelon_aeo_description', true ) );

		if ( $has_faq || $has_aeo ) {
			wp_enqueue_style(
				'seomelon-frontend',
				SEOMELON_PLUGIN_URL . 'assets/css/seomelon-frontend.css',
				array(),
				SEOMELON_VERSION
			);
		}
	}

	/**
	 * Build complete AEO HTML for a post (FAQ + AEO description).
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string HTML output.
	 */
	private function build_aeo_html( int $post_id ): string {
		$html = '';

		$faq_html = $this->build_faq_html( $post_id );
		if ( ! empty( $faq_html ) ) {
			$html .= $faq_html;
		}

		$aeo_desc = get_post_meta( $post_id, '_seomelon_aeo_description', true );
		if ( ! empty( $aeo_desc ) ) {
			$html .= '<div class="seomelon-aeo-section">';
			$html .= '<div class="seomelon-aeo-description">';
			$html .= wp_kses_post( wpautop( $aeo_desc ) );
			$html .= '</div>';
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Build FAQ accordion HTML from stored FAQ schema data.
	 *
	 * Uses native HTML <details>/<summary> elements for zero-JS
	 * accordion behavior that works in every modern browser and theme.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string FAQ HTML or empty string.
	 */
	private function build_faq_html( int $post_id ): string {
		$faq_json = get_post_meta( $post_id, '_seomelon_faq_schema', true );
		if ( empty( $faq_json ) ) {
			return '';
		}

		$faqs = json_decode( $faq_json, true );
		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return '';
		}

		$html  = '<div class="seomelon-faq-section">';
		$html .= '<h2 class="seomelon-faq-heading">' . esc_html__( 'Frequently Asked Questions', 'seomelon' ) . '</h2>';

		foreach ( $faqs as $faq ) {
			$question = $faq['question'] ?? '';
			$answer   = $faq['answer'] ?? '';

			if ( empty( $question ) || empty( $answer ) ) {
				continue;
			}

			$html .= '<details class="seomelon-faq-item">';
			$html .= '<summary class="seomelon-faq-question">' . esc_html( $question ) . '</summary>';
			$html .= '<div class="seomelon-faq-answer">' . wp_kses_post( wpautop( $answer ) ) . '</div>';
			$html .= '</details>';
		}

		$html .= '</div>';

		return $html;
	}
}
