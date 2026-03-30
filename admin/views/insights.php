<?php
/**
 * Insights admin page.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

$api           = seomelon()->api;
$is_configured = $api->is_configured();
$insights      = array();
$categories    = array();
$plan          = 'free';

if ( $is_configured ) {
	$result = $api->get_insights();
	if ( ! is_wp_error( $result ) ) {
		// Laravel returns { insights: [...], plan: '...' }.
		$insights = $result['insights'] ?? $result['data'] ?? $result;
		$plan     = $result['plan'] ?? 'free';
		if ( is_array( $insights ) ) {
			foreach ( $insights as $insight ) {
				$cat = $insight['category'] ?? 'general';
				if ( ! in_array( $cat, $categories, true ) ) {
					$categories[] = $cat;
				}
			}
		} else {
			$insights = array();
		}
	}
}

// Category display labels and colors.
$category_config = array(
	'seo_quick_wins'  => array(
		'label' => __( 'SEO Quick Wins', 'seomelon' ),
		'class' => 'seomelon-cat-quickwins',
	),
	'keyword_gaps'    => array(
		'label' => __( 'Keyword Gaps', 'seomelon' ),
		'class' => 'seomelon-cat-keywords',
	),
	'catalog_gaps'    => array(
		'label' => __( 'Catalog Gaps', 'seomelon' ),
		'class' => 'seomelon-cat-catalog',
	),
	'promotions'      => array(
		'label' => __( 'Promotions', 'seomelon' ),
		'class' => 'seomelon-cat-promotions',
	),
	'seasonal'        => array(
		'label' => __( 'Seasonal', 'seomelon' ),
		'class' => 'seomelon-cat-seasonal',
	),
	'content'         => array(
		'label' => __( 'Content', 'seomelon' ),
		'class' => 'seomelon-cat-general',
	),
	'general'         => array(
		'label' => __( 'General', 'seomelon' ),
		'class' => 'seomelon-cat-general',
	),
);
?>
<div class="wrap seomelon-wrap">
	<h1>
		<span class="seomelon-logo">&#127849;</span>
		<?php esc_html_e( 'SEOMelon Insights', 'seomelon' ); ?>
	</h1>

	<?php if ( ! $is_configured ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					wp_kses(
						__( 'SEOMelon is not configured yet. Please <a href="%s">add your API key</a> to get started.', 'seomelon' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'admin.php?page=seomelon-settings' ) )
				);
				?>
			</p>
		</div>
	<?php elseif ( empty( $insights ) ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'No insights available yet. Sync and scan your content first to generate business insights.', 'seomelon' ); ?>
			</p>
		</div>
	<?php else : ?>

		<!-- Category Tabs -->
		<h2 class="nav-tab-wrapper seomelon-tabs" id="seomelon-insight-tabs">
			<a href="#all" class="nav-tab nav-tab-active" data-category="all">
				<?php esc_html_e( 'All', 'seomelon' ); ?>
				<span class="seomelon-tab-count"><?php echo esc_html( count( $insights ) ); ?></span>
			</a>
			<?php foreach ( $categories as $cat ) : ?>
				<?php
				$config = $category_config[ $cat ] ?? $category_config['general'];
				$count  = count(
					array_filter(
						$insights,
						function ( $i ) use ( $cat ) {
							return ( $i['category'] ?? 'general' ) === $cat;
						}
					)
				);
				?>
				<a href="#<?php echo esc_attr( $cat ); ?>" class="nav-tab" data-category="<?php echo esc_attr( $cat ); ?>">
					<?php echo esc_html( $config['label'] ); ?>
					<span class="seomelon-tab-count"><?php echo esc_html( $count ); ?></span>
				</a>
			<?php endforeach; ?>
		</h2>

		<!-- Insight Cards -->
		<div class="seomelon-insights-grid" id="seomelon-insights-grid">
			<?php foreach ( $insights as $insight ) : ?>
				<?php
				$cat    = $insight['category'] ?? 'general';
				$config = $category_config[ $cat ] ?? $category_config['general'];
				?>
				<div class="seomelon-insight-card" data-insight-id="<?php echo esc_attr( $insight['id'] ?? '' ); ?>" data-category="<?php echo esc_attr( $cat ); ?>">
					<div class="seomelon-insight-header">
						<span class="seomelon-insight-category <?php echo esc_attr( $config['class'] ); ?>">
							<?php echo esc_html( $config['label'] ); ?>
						</span>
						<?php if ( ! empty( $insight['impact'] ) ) : ?>
							<span class="seomelon-insight-priority seomelon-priority-<?php echo esc_attr( $insight['impact'] ); ?>">
								<?php echo esc_html( ucfirst( $insight['impact'] ) ); ?>
							</span>
						<?php endif; ?>
					</div>

					<h3 class="seomelon-insight-title">
						<?php echo esc_html( $insight['title'] ?? '' ); ?>
					</h3>

					<p class="seomelon-insight-description">
						<?php echo esc_html( $insight['description'] ?? '' ); ?>
					</p>

					<?php if ( ! empty( $insight['action'] ) ) : ?>
						<div class="seomelon-insight-action">
							<strong><?php esc_html_e( 'Recommended:', 'seomelon' ); ?></strong>
							<?php echo esc_html( $insight['action'] ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $insight['keywords'] ) ) : ?>
						<div class="seomelon-insight-keywords">
							<?php foreach ( (array) $insight['keywords'] as $keyword ) : ?>
								<span class="seomelon-keyword-tag"><?php echo esc_html( $keyword ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $insight['referenced_products'] ) ) : ?>
						<div class="seomelon-insight-products">
							<strong><?php esc_html_e( 'Related:', 'seomelon' ); ?></strong>
							<?php echo esc_html( implode( ', ', (array) $insight['referenced_products'] ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $insight['confidence'] ) ) : ?>
						<div class="seomelon-insight-confidence">
							<?php
							printf(
								/* translators: %d: confidence percentage */
								esc_html__( 'Confidence: %d%%', 'seomelon' ),
								absint( $insight['confidence'] )
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>
</div>
