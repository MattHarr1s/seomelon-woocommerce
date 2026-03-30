<?php
/**
 * Admin list table column additions.
 *
 * Registers an "SEO Score" column on the WooCommerce Products,
 * Posts, and Pages list tables so scores are visible at a glance.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Columns
 */
class SEOMelon_Columns {

	/**
	 * Constructor. Registers column hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_columns' ) );
	}

	/**
	 * Register column hooks for all supported post types.
	 */
	public function register_columns(): void {
		// Posts.
		add_filter( 'manage_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', array( $this, 'sortable_column' ) );

		// Pages.
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-page_sortable_columns', array( $this, 'sortable_column' ) );

		// WooCommerce products.
		if ( SEOMelon::is_woocommerce_active() ) {
			add_filter( 'manage_edit-product_columns', array( $this, 'add_column' ) );
			add_action( 'manage_product_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
			add_filter( 'manage_edit-product_sortable_columns', array( $this, 'sortable_column' ) );
		}

		// Handle sorting by SEO score.
		add_action( 'pre_get_posts', array( $this, 'handle_sort' ) );
	}

	/**
	 * Add the SEO Score column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( array $columns ): array {
		$columns['seomelon_score'] = __( 'SEO Score', 'seomelon' );
		return $columns;
	}

	/**
	 * Render the SEO Score column content.
	 *
	 * @param string $column_name Column identifier.
	 * @param int    $post_id     Post ID for the current row.
	 */
	public function render_column( string $column_name, int $post_id ): void {
		if ( 'seomelon_score' !== $column_name ) {
			return;
		}

		$score = (int) get_post_meta( $post_id, '_seomelon_seo_score', true );

		if ( $score <= 0 ) {
			echo '<span class="seomelon-score-badge seomelon-score-none" title="' . esc_attr__( 'Not scanned', 'seomelon' ) . '">&mdash;</span>';
			return;
		}

		if ( $score >= 70 ) {
			$class = 'seomelon-score-good';
		} elseif ( $score >= 50 ) {
			$class = 'seomelon-score-ok';
		} else {
			$class = 'seomelon-score-poor';
		}

		printf(
			'<span class="seomelon-score-badge %s">%d</span>',
			esc_attr( $class ),
			$score
		);
	}

	/**
	 * Make the SEO Score column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_column( array $columns ): array {
		$columns['seomelon_score'] = 'seomelon_score';
		return $columns;
	}

	/**
	 * Handle sorting by _seomelon_seo_score meta value.
	 *
	 * @param WP_Query $query Main query being modified.
	 */
	public function handle_sort( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'seomelon_score' === $query->get( 'orderby' ) ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_seomelon_seo_score',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_seomelon_seo_score',
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
