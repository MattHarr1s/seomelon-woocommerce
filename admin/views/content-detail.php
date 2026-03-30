<?php
/**
 * Single content item detail view.
 *
 * Shows suggestions with SERP preview, character counts, current vs proposed
 * SEO metadata, and apply controls for an individual content item.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$content_id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$api         = seomelon()->api;
$item        = null;
$suggestions = null;

if ( $content_id && $api->is_configured() ) {
	// Use the single-item endpoint instead of fetching all content.
	$content_result = $api->get_content_by_id( $content_id );
	if ( ! is_wp_error( $content_result ) ) {
		$item = $content_result['item'] ?? $content_result;
	}

	if ( $item ) {
		$suggestions = $api->get_suggestions( $content_id );
		if ( is_wp_error( $suggestions ) ) {
			$suggestions = null;
		}
	}
}

if ( ! $item ) :
	?>
	<div class="wrap seomelon-wrap">
		<h1><?php esc_html_e( 'Content Not Found', 'seomelon' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon' ) ); ?>">
				<?php esc_html_e( '&larr; Back to Dashboard', 'seomelon' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
endif;

$score = $item['seo_score'] ?? 0;
if ( $score >= 70 ) {
	$score_class = 'seomelon-score-good';
} elseif ( $score >= 50 ) {
	$score_class = 'seomelon-score-ok';
} else {
	$score_class = 'seomelon-score-poor';
}

$content_type = $item['content_type'] ?? 'post';
$platform_id  = $item['platform_id'] ?? $item['shopify_product_id'] ?? '';
?>
<div class="wrap seomelon-wrap">
	<h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon' ) ); ?>" class="seomelon-back-link">
			<?php esc_html_e( '&larr; Dashboard', 'seomelon' ); ?>
		</a>
		&mdash;
		<?php echo esc_html( $item['title'] ?? '' ); ?>
	</h1>

	<div class="seomelon-detail-header">
		<div class="seomelon-detail-meta">
			<span class="seomelon-badge seomelon-badge-blue">
				<?php echo esc_html( ucfirst( $content_type ) ); ?>
			</span>
			<?php if ( $score > 0 ) : ?>
				<span class="seomelon-score-badge seomelon-score-large <?php echo esc_attr( $score_class ); ?>">
					<?php echo esc_html( $score ); ?>/100
				</span>
			<?php endif; ?>
		</div>
		<div class="seomelon-detail-actions">
			<button type="button" class="button seomelon-action-generate" data-content-id="<?php echo esc_attr( $content_id ); ?>">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate', 'seomelon' ); ?>
			</button>
			<button type="button" class="button button-primary seomelon-action-apply"
					data-content-id="<?php echo esc_attr( $content_id ); ?>"
					data-post-id="<?php echo esc_attr( $platform_id ); ?>"
					data-content-type="<?php echo esc_attr( $content_type ); ?>">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Apply All', 'seomelon' ); ?>
			</button>
			<span class="spinner" id="seomelon-detail-spinner"></span>
			<span id="seomelon-detail-status" class="seomelon-status-message"></span>
		</div>
	</div>

	<?php if ( ! empty( $item['issues'] ) ) : ?>
		<?php
		$issues = is_string( $item['issues'] ) ? json_decode( $item['issues'], true ) : $item['issues'];
		if ( is_array( $issues ) && ! empty( $issues ) ) :
			?>
			<div class="seomelon-detail-issues">
				<h3><?php esc_html_e( 'Issues Found', 'seomelon' ); ?></h3>
				<ul>
					<?php foreach ( $issues as $issue ) : ?>
						<li><?php echo esc_html( str_replace( '_', ' ', ucfirst( $issue ) ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $suggestions ) : ?>
		<div class="seomelon-detail-grid">

			<!-- Meta Title with SERP Preview & Character Count -->
			<div class="seomelon-detail-card">
				<h3><?php esc_html_e( 'Meta Title', 'seomelon' ); ?></h3>
				<div class="seomelon-comparison">
					<div class="seomelon-current">
						<label><?php esc_html_e( 'Current', 'seomelon' ); ?></label>
						<p><?php echo esc_html( $item['current_meta_title'] ?? '(empty)' ); ?></p>
						<?php
						$current_title_len = mb_strlen( $item['current_meta_title'] ?? '' );
						$title_class       = ( $current_title_len >= 30 && $current_title_len <= 60 ) ? 'seomelon-charcount-ok' : 'seomelon-charcount-warn';
						?>
						<span class="seomelon-charcount <?php echo esc_attr( $title_class ); ?>">
							<?php echo esc_html( $current_title_len ); ?>/60 <?php esc_html_e( 'chars', 'seomelon' ); ?>
						</span>
					</div>
					<?php if ( ! empty( $suggestions['meta_title'] ) ) : ?>
						<div class="seomelon-suggested">
							<label><?php esc_html_e( 'Suggested', 'seomelon' ); ?></label>
							<p><?php echo esc_html( $suggestions['meta_title'] ); ?></p>
							<?php
							$sug_title_len = mb_strlen( $suggestions['meta_title'] );
							$sug_class     = ( $sug_title_len >= 30 && $sug_title_len <= 60 ) ? 'seomelon-charcount-ok' : 'seomelon-charcount-warn';
							?>
							<span class="seomelon-charcount <?php echo esc_attr( $sug_class ); ?>">
								<?php echo esc_html( $sug_title_len ); ?>/60 <?php esc_html_e( 'chars', 'seomelon' ); ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Meta Description with Character Count -->
			<div class="seomelon-detail-card">
				<h3><?php esc_html_e( 'Meta Description', 'seomelon' ); ?></h3>
				<div class="seomelon-comparison">
					<div class="seomelon-current">
						<label><?php esc_html_e( 'Current', 'seomelon' ); ?></label>
						<p><?php echo esc_html( $item['current_meta_description'] ?? '(empty)' ); ?></p>
						<?php
						$current_desc_len = mb_strlen( $item['current_meta_description'] ?? '' );
						$desc_class       = ( $current_desc_len >= 70 && $current_desc_len <= 160 ) ? 'seomelon-charcount-ok' : 'seomelon-charcount-warn';
						?>
						<span class="seomelon-charcount <?php echo esc_attr( $desc_class ); ?>">
							<?php echo esc_html( $current_desc_len ); ?>/160 <?php esc_html_e( 'chars', 'seomelon' ); ?>
						</span>
					</div>
					<?php if ( ! empty( $suggestions['meta_description'] ) ) : ?>
						<div class="seomelon-suggested">
							<label><?php esc_html_e( 'Suggested', 'seomelon' ); ?></label>
							<p><?php echo esc_html( $suggestions['meta_description'] ); ?></p>
							<?php
							$sug_desc_len = mb_strlen( $suggestions['meta_description'] );
							$sug_d_class  = ( $sug_desc_len >= 70 && $sug_desc_len <= 160 ) ? 'seomelon-charcount-ok' : 'seomelon-charcount-warn';
							?>
							<span class="seomelon-charcount <?php echo esc_attr( $sug_d_class ); ?>">
								<?php echo esc_html( $sug_desc_len ); ?>/160 <?php esc_html_e( 'chars', 'seomelon' ); ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- SERP Preview -->
			<?php if ( ! empty( $suggestions['meta_title'] ) && ! empty( $suggestions['meta_description'] ) ) : ?>
				<div class="seomelon-detail-card seomelon-detail-card-full">
					<h3><?php esc_html_e( 'Google Search Preview', 'seomelon' ); ?></h3>
					<div class="seomelon-serp-preview">
						<div class="seomelon-serp-title">
							<?php echo esc_html( mb_substr( $suggestions['meta_title'], 0, 60 ) ); ?>
						</div>
						<div class="seomelon-serp-url">
							<?php
							$url = $item['url'] ?? $item['handle'] ?? '';
							if ( ! $url && ! empty( $platform_id ) ) {
								$url = get_permalink( (int) $platform_id );
							}
							echo esc_html( $url ?: home_url( '/' . ( $item['handle'] ?? '' ) ) );
							?>
						</div>
						<div class="seomelon-serp-description">
							<?php echo esc_html( mb_substr( $suggestions['meta_description'], 0, 160 ) ); ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- AEO Description -->
			<?php if ( ! empty( $suggestions['aeo_description'] ) ) : ?>
				<div class="seomelon-detail-card seomelon-detail-card-full">
					<h3><?php esc_html_e( 'AEO Description (Answer Engine Optimization)', 'seomelon' ); ?></h3>
					<p><?php echo esc_html( $suggestions['aeo_description'] ); ?></p>
				</div>
			<?php endif; ?>

			<!-- OG Tags with Social Preview -->
			<?php if ( ! empty( $suggestions['og_title'] ) || ! empty( $suggestions['og_description'] ) ) : ?>
				<div class="seomelon-detail-card seomelon-detail-card-full">
					<h3><?php esc_html_e( 'Social Media Preview', 'seomelon' ); ?></h3>
					<div class="seomelon-social-preview">
						<div class="seomelon-social-card">
							<div class="seomelon-social-domain">
								<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>
							</div>
							<div class="seomelon-social-title">
								<?php echo esc_html( $suggestions['og_title'] ?? $suggestions['meta_title'] ?? '' ); ?>
							</div>
							<div class="seomelon-social-desc">
								<?php echo esc_html( $suggestions['og_description'] ?? $suggestions['meta_description'] ?? '' ); ?>
							</div>
						</div>
					</div>
					<?php if ( ! empty( $suggestions['og_title'] ) ) : ?>
						<div style="margin-top: 12px;">
							<strong><?php esc_html_e( 'OG Title:', 'seomelon' ); ?></strong>
							<?php echo esc_html( $suggestions['og_title'] ); ?>
							<span class="seomelon-charcount seomelon-charcount-ok">
								<?php echo esc_html( mb_strlen( $suggestions['og_title'] ) ); ?>/60
							</span>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $suggestions['og_description'] ) ) : ?>
						<div style="margin-top: 4px;">
							<strong><?php esc_html_e( 'OG Description:', 'seomelon' ); ?></strong>
							<?php echo esc_html( $suggestions['og_description'] ); ?>
							<span class="seomelon-charcount seomelon-charcount-ok">
								<?php echo esc_html( mb_strlen( $suggestions['og_description'] ) ); ?>/200
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- FAQ Schema -->
			<?php if ( ! empty( $suggestions['faq_schema'] ) ) : ?>
				<?php
				$faqs = is_string( $suggestions['faq_schema'] )
					? json_decode( $suggestions['faq_schema'], true )
					: $suggestions['faq_schema'];
				?>
				<?php if ( is_array( $faqs ) && ! empty( $faqs ) ) : ?>
					<div class="seomelon-detail-card seomelon-detail-card-full">
						<h3><?php esc_html_e( 'FAQ Schema', 'seomelon' ); ?></h3>
						<dl class="seomelon-faq-list">
							<?php foreach ( $faqs as $faq ) : ?>
								<dt><?php echo esc_html( $faq['question'] ?? '' ); ?></dt>
								<dd><?php echo esc_html( $faq['answer'] ?? '' ); ?></dd>
							<?php endforeach; ?>
						</dl>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Image Alt Texts -->
			<?php if ( ! empty( $suggestions['image_alt_texts'] ) ) : ?>
				<?php
				$alt_texts = is_string( $suggestions['image_alt_texts'] )
					? json_decode( $suggestions['image_alt_texts'], true )
					: $suggestions['image_alt_texts'];
				?>
				<?php if ( is_array( $alt_texts ) && ! empty( $alt_texts ) ) : ?>
					<div class="seomelon-detail-card seomelon-detail-card-full">
						<h3><?php esc_html_e( 'Image Alt Texts', 'seomelon' ); ?></h3>
						<ol>
							<?php foreach ( $alt_texts as $alt ) : ?>
								<li><?php echo esc_html( $alt ); ?></li>
							<?php endforeach; ?>
						</ol>
					</div>
				<?php endif; ?>
			<?php endif; ?>

		</div>
	<?php else : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'No suggestions generated yet. Click "Generate" to create AI-optimized content for this item.', 'seomelon' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>
