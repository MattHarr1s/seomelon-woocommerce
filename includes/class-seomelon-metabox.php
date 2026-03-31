<?php
/**
 * SEOMelon meta box for the post/page/product editor.
 *
 * Displays a Yoast-style panel below the editor showing the SEOMelon SEO
 * score, issues, current/suggested meta, AEO status, and quick
 * generate/apply actions for the individual item.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Metabox
 */
class SEOMelon_Metabox {

	/**
	 * API client.
	 *
	 * @var SEOMelon_API
	 */
	private SEOMelon_API $api;

	/**
	 * SEO detect.
	 *
	 * @var SEOMelon_SEO_Detect
	 */
	private SEOMelon_SEO_Detect $seo_detect;

	/**
	 * Constructor.
	 */
	public function __construct( SEOMelon_API $api, SEOMelon_SEO_Detect $seo_detect ) {
		$this->api        = $api;
		$this->seo_detect = $seo_detect;

		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
	}

	/**
	 * Register the SEOMelon meta box on all enabled post types.
	 */
	public function register_meta_boxes(): void {
		$settings      = get_option( 'seomelon_settings', array() );
		$content_types = $settings['content_types'] ?? array( 'product', 'post', 'page' );

		// Register on all selected post types (including custom ones).
		$post_types = array();
		foreach ( $content_types as $type ) {
			if ( 'category' === $type ) {
				continue; // Categories are terms, not post types.
			}
			if ( 'product' === $type && ! SEOMelon::is_woocommerce_active() ) {
				continue;
			}
			if ( post_type_exists( $type ) ) {
				$post_types[] = $type;
			}
		}

		foreach ( $post_types as $pt ) {
			add_meta_box(
				'seomelon-seo-metabox',
				__( '🍈 SEOMelon — SEO & AEO', 'seomelon' ),
				array( $this, 'render' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render( WP_Post $post ): void {
		$post_id = $post->ID;
		$score   = (int) get_post_meta( $post_id, '_seomelon_seo_score', true );

		// Current SEO meta (read from SEO plugin or our stored data).
		$current_title = $this->seo_detect->get_meta_title( $post_id );
		$current_desc  = $this->seo_detect->get_meta_description( $post_id );

		// SEOMelon stored suggestions.
		$sug_title    = get_post_meta( $post_id, '_seomelon_meta_title', true );
		$sug_desc     = get_post_meta( $post_id, '_seomelon_meta_description', true );
		$aeo_desc     = get_post_meta( $post_id, '_seomelon_aeo_description', true );
		$faq_schema   = get_post_meta( $post_id, '_seomelon_faq_schema', true );
		$schema       = get_post_meta( $post_id, '_seomelon_schema', true );
		$og_title     = get_post_meta( $post_id, '_seomelon_og_title', true );
		$og_desc      = get_post_meta( $post_id, '_seomelon_og_description', true );
		$applied_at   = get_post_meta( $post_id, '_seomelon_applied_at', true );

		// Score badge.
		if ( $score >= 70 ) {
			$score_class = 'seomelon-score-good';
			$score_label = __( 'Good', 'seomelon' );
		} elseif ( $score >= 50 ) {
			$score_class = 'seomelon-score-ok';
			$score_label = __( 'Needs Work', 'seomelon' );
		} elseif ( $score > 0 ) {
			$score_class = 'seomelon-score-poor';
			$score_label = __( 'Poor', 'seomelon' );
		} else {
			$score_class = 'seomelon-score-none';
			$score_label = __( 'Not Scanned', 'seomelon' );
		}

		$is_configured = $this->api->is_configured();

		// Detect issues.
		$issues = array();
		if ( empty( $current_title ) || mb_strlen( $current_title ) < 10 ) {
			$issues[] = __( 'Meta title is missing or too short', 'seomelon' );
		} elseif ( mb_strlen( $current_title ) > 60 ) {
			$issues[] = __( 'Meta title is too long (over 60 characters)', 'seomelon' );
		}
		if ( empty( $current_desc ) || mb_strlen( $current_desc ) < 50 ) {
			$issues[] = __( 'Meta description is missing or too short', 'seomelon' );
		} elseif ( mb_strlen( $current_desc ) > 160 ) {
			$issues[] = __( 'Meta description is too long (over 160 characters)', 'seomelon' );
		}

		// Check images for alt text.
		$content_images = array();
		if ( has_post_thumbnail( $post_id ) ) {
			$thumb_id  = get_post_thumbnail_id( $post_id );
			$thumb_alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			if ( empty( $thumb_alt ) ) {
				$issues[] = __( 'Featured image is missing alt text', 'seomelon' );
			}
		}

		$has_suggestions = ! empty( $sug_title ) || ! empty( $sug_desc );
		$has_aeo         = ! empty( $aeo_desc ) || ! empty( $faq_schema );
		?>
		<div class="seomelon-metabox-wrap">

			<!-- Header with score -->
			<div class="seomelon-metabox-header">
				<div class="seomelon-metabox-score">
					<?php if ( $score > 0 ) : ?>
						<span class="seomelon-score-badge <?php echo esc_attr( $score_class ); ?>">
							<?php echo esc_html( $score ); ?>/100
						</span>
						<span class="seomelon-metabox-score-label"><?php echo esc_html( $score_label ); ?></span>
					<?php else : ?>
						<span class="seomelon-score-badge seomelon-score-none">&mdash;</span>
						<span class="seomelon-metabox-score-label"><?php echo esc_html( $score_label ); ?></span>
					<?php endif; ?>
				</div>
				<div class="seomelon-metabox-actions">
					<?php if ( $is_configured ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon' ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Open SEOMelon Dashboard', 'seomelon' ); ?>">
							<?php esc_html_e( 'Dashboard', 'seomelon' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-settings' ) ); ?>" class="button button-small button-primary">
							<?php esc_html_e( 'Connect API', 'seomelon' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $is_configured ) : ?>
				<p class="seomelon-metabox-notice">
					<?php esc_html_e( 'Connect to SEOMelon to scan and optimize this content.', 'seomelon' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-settings' ) ); ?>" class="button button-small" style="margin-left: 8px;">
						<?php esc_html_e( 'Connect', 'seomelon' ); ?>
					</a>
				</p>
			<?php else : ?>

				<!-- Issues -->
				<?php if ( ! empty( $issues ) ) : ?>
					<div class="seomelon-metabox-section seomelon-metabox-issues">
						<h4><?php esc_html_e( 'Issues', 'seomelon' ); ?> <span class="seomelon-issue-count"><?php echo count( $issues ); ?></span></h4>
						<ul>
							<?php foreach ( $issues as $issue ) : ?>
								<li><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $issue ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php elseif ( $score >= 70 ) : ?>
					<div class="seomelon-metabox-section seomelon-metabox-ok">
						<p><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'No major SEO issues found.', 'seomelon' ); ?></p>
					</div>
				<?php endif; ?>

				<!-- Current SEO -->
				<div class="seomelon-metabox-section">
					<h4><?php esc_html_e( 'Current SEO', 'seomelon' ); ?></h4>
					<table class="seomelon-metabox-table">
						<tr>
							<th><?php esc_html_e( 'Meta Title', 'seomelon' ); ?></th>
							<td>
								<?php if ( $current_title ) : ?>
									<?php echo esc_html( $current_title ); ?>
									<span class="seomelon-charcount <?php echo ( mb_strlen( $current_title ) <= 60 ) ? 'seomelon-charcount-ok' : 'seomelon-charcount-warn'; ?>">
										<?php echo esc_html( mb_strlen( $current_title ) ); ?>/60
									</span>
								<?php else : ?>
									<em class="seomelon-text-muted"><?php esc_html_e( '(not set)', 'seomelon' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Meta Desc', 'seomelon' ); ?></th>
							<td>
								<?php if ( $current_desc ) : ?>
									<?php echo esc_html( wp_trim_words( $current_desc, 20, '...' ) ); ?>
									<span class="seomelon-charcount <?php echo ( mb_strlen( $current_desc ) <= 160 ) ? 'seomelon-charcount-ok' : 'seomelon-charcount-warn'; ?>">
										<?php echo esc_html( mb_strlen( $current_desc ) ); ?>/160
									</span>
								<?php else : ?>
									<em class="seomelon-text-muted"><?php esc_html_e( '(not set)', 'seomelon' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $applied_at ) : ?>
							<tr>
								<th><?php esc_html_e( 'Last Applied', 'seomelon' ); ?></th>
								<td><?php echo esc_html( human_time_diff( strtotime( $applied_at ) ) . ' ago' ); ?></td>
							</tr>
						<?php endif; ?>
					</table>
				</div>

				<!-- AI Suggestions -->
				<?php if ( $has_suggestions ) : ?>
					<div class="seomelon-metabox-section">
						<h4>
							<?php esc_html_e( 'AI Suggestions', 'seomelon' ); ?>
							<span class="seomelon-badge seomelon-badge-blue"><?php esc_html_e( 'Ready', 'seomelon' ); ?></span>
						</h4>
						<table class="seomelon-metabox-table">
							<?php if ( $sug_title ) : ?>
								<tr>
									<th><?php esc_html_e( 'Title', 'seomelon' ); ?></th>
									<td>
										<?php echo esc_html( $sug_title ); ?>
										<span class="seomelon-charcount seomelon-charcount-ok"><?php echo esc_html( mb_strlen( $sug_title ) ); ?>/60</span>
									</td>
								</tr>
							<?php endif; ?>
							<?php if ( $sug_desc ) : ?>
								<tr>
									<th><?php esc_html_e( 'Description', 'seomelon' ); ?></th>
									<td>
										<?php echo esc_html( wp_trim_words( $sug_desc, 20, '...' ) ); ?>
										<span class="seomelon-charcount seomelon-charcount-ok"><?php echo esc_html( mb_strlen( $sug_desc ) ); ?>/160</span>
									</td>
								</tr>
							<?php endif; ?>
							<?php if ( $og_title ) : ?>
								<tr>
									<th><?php esc_html_e( 'OG Title', 'seomelon' ); ?></th>
									<td><?php echo esc_html( $og_title ); ?></td>
								</tr>
							<?php endif; ?>
						</table>
					</div>
				<?php endif; ?>

				<!-- AEO Status -->
				<div class="seomelon-metabox-section">
					<h4><?php esc_html_e( 'Answer Engine Optimization', 'seomelon' ); ?></h4>
					<?php if ( $has_aeo ) : ?>
						<table class="seomelon-metabox-table">
							<?php if ( $aeo_desc ) : ?>
								<tr>
									<th><?php esc_html_e( 'AEO Desc', 'seomelon' ); ?></th>
									<td>
										<?php echo esc_html( wp_trim_words( $aeo_desc, 15, '...' ) ); ?>
										<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'Active', 'seomelon' ); ?></span>
									</td>
								</tr>
							<?php endif; ?>
							<?php if ( $faq_schema ) : ?>
								<?php
								$faqs = is_string( $faq_schema ) ? json_decode( $faq_schema, true ) : $faq_schema;
								$faq_count = is_array( $faqs ) ? count( $faqs ) : 0;
								?>
								<tr>
									<th><?php esc_html_e( 'FAQ Schema', 'seomelon' ); ?></th>
									<td>
										<?php
										printf(
											/* translators: %d: number of FAQ questions */
											esc_html__( '%d questions', 'seomelon' ),
											$faq_count
										);
										?>
										<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'Active', 'seomelon' ); ?></span>
									</td>
								</tr>
							<?php endif; ?>
							<?php if ( $schema ) : ?>
								<tr>
									<th><?php esc_html_e( 'Schema', 'seomelon' ); ?></th>
									<td>
										<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'JSON-LD Active', 'seomelon' ); ?></span>
									</td>
								</tr>
							<?php endif; ?>
						</table>
					<?php else : ?>
						<p class="seomelon-text-muted">
							<?php esc_html_e( 'No AEO content generated yet. Generate from the SEOMelon dashboard.', 'seomelon' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<!-- SERP Preview -->
				<?php
				$preview_title = $sug_title ?: $current_title ?: $post->post_title;
				$preview_desc  = $sug_desc ?: $current_desc ?: wp_trim_words( $post->post_content, 25, '...' );
				?>
				<div class="seomelon-metabox-section">
					<h4><?php esc_html_e( 'Search Preview', 'seomelon' ); ?></h4>
					<div class="seomelon-serp-preview seomelon-serp-preview-compact">
						<div class="seomelon-serp-title"><?php echo esc_html( mb_substr( $preview_title, 0, 60 ) ); ?></div>
						<div class="seomelon-serp-url"><?php echo esc_html( get_permalink( $post_id ) ); ?></div>
						<div class="seomelon-serp-description"><?php echo esc_html( mb_substr( $preview_desc, 0, 160 ) ); ?></div>
					</div>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}
}
