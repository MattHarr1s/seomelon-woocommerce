<?php
/**
 * Manual Schema & FAQ editor (free-tier feature).
 *
 * Adds a metabox that lets free-tier users manually author JSON-LD product
 * schema and FAQ entries for their content. Paid tiers have this generated
 * automatically by the AI pipeline — this metabox always offers a nudge to
 * upgrade.
 *
 * Data is stored in the same post meta keys that the AI pipeline uses
 * (_seomelon_faq_schema, _seomelon_schema), so the frontend injection layer
 * works identically for manual and AI-generated content.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEOMelon_Manual_Schema
 */
class SEOMelon_Manual_Schema {

	/**
	 * Free-tier limit: number of posts a user can manually add schema to.
	 */
	private const FREE_TIER_LIMIT = 10;

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the manual schema meta box on supported post types.
	 */
	public function register_meta_boxes(): void {
		$settings      = get_option( 'seomelon_settings', array() );
		$content_types = $settings['content_types'] ?? array( 'product', 'post', 'page' );

		foreach ( $content_types as $type ) {
			if ( 'category' === $type ) {
				continue;
			}
			if ( 'product' === $type && ! SEOMelon::is_woocommerce_active() ) {
				continue;
			}
			if ( post_type_exists( $type ) ) {
				add_meta_box(
					'seomelon-manual-schema',
					__( '🍉 SEOMelon — Manual FAQ & Schema', 'seomelon' ),
					array( $this, 'render' ),
					$type,
					'normal',
					'default'
				);
			}
		}
	}

	/**
	 * Enqueue admin assets for the manual schema editor on post edit screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'seomelon-manual-schema',
			SEOMELON_PLUGIN_URL . 'admin/js/seomelon-manual-schema.js',
			array( 'jquery' ),
			SEOMELON_VERSION,
			true
		);
	}

	/**
	 * Render the manual schema meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render( WP_Post $post ): void {
		$post_id     = $post->ID;
		$plan_tier   = get_option( 'seomelon_plan_tier', 'free' );
		$is_free     = in_array( $plan_tier, array( 'free', '' ), true );
		$has_ai_faq  = (bool) get_post_meta( $post_id, '_seomelon_faq_schema', true );
		$has_ai_schema = (bool) get_post_meta( $post_id, '_seomelon_schema', true );

		// Read existing FAQ entries.
		$faq_json = get_post_meta( $post_id, '_seomelon_faq_schema', true );
		$faqs     = array();
		if ( ! empty( $faq_json ) ) {
			$decoded = is_string( $faq_json ) ? json_decode( $faq_json, true ) : $faq_json;
			if ( is_array( $decoded ) ) {
				$faqs = $decoded;
			}
		}

		// Read existing manual schema (if user has authored one).
		$schema_json = get_post_meta( $post_id, '_seomelon_schema', true );

		// Track how many posts have manual schema (for free-tier limit).
		$manual_count = $this->get_manual_schema_count();

		wp_nonce_field( 'seomelon_manual_schema', 'seomelon_manual_schema_nonce' );
		?>
		<div class="seomelon-manual-wrap">

			<?php if ( $is_free ) : ?>
				<div class="seomelon-manual-notice seomelon-manual-notice-info">
					<p>
						<strong>💡 <?php esc_html_e( 'Pro Tip:', 'seomelon' ); ?></strong>
						<?php
						printf(
							/* translators: %s: "Upgrade to Pro" link */
							esc_html__( 'Pro users get FAQ and schema generated automatically for every product using AI trained on competitors\' top-ranking content. %s to stop writing these by hand.', 'seomelon' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=seomelon-settings#pricing' ) ) . '"><strong>' . esc_html__( 'Upgrade to Pro', 'seomelon' ) . '</strong></a>'
						);
						?>
					</p>
					<?php if ( $manual_count >= self::FREE_TIER_LIMIT ) : ?>
						<p class="seomelon-manual-limit-warning">
							⚠️
							<?php
							printf(
								/* translators: 1: current count, 2: limit */
								esc_html__( 'You\'ve reached the free-tier limit of %1$d manual schemas (currently %2$d).', 'seomelon' ),
								self::FREE_TIER_LIMIT,
								$manual_count
							);
							?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-settings#pricing' ) ); ?>"><strong><?php esc_html_e( 'Upgrade to Pro for unlimited', 'seomelon' ); ?></strong></a>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_ai_faq || $has_ai_schema ) : ?>
				<div class="seomelon-manual-notice seomelon-manual-notice-success">
					<p>
						✅ <?php esc_html_e( 'AI-generated FAQ and schema are active on this post. Manual entries below will only be used if AI content is cleared.', 'seomelon' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="seomelon-manual-tabs">
				<button type="button" class="seomelon-manual-tab active" data-tab="faq">
					<?php esc_html_e( 'FAQ Schema', 'seomelon' ); ?>
				</button>
				<button type="button" class="seomelon-manual-tab" data-tab="schema">
					<?php esc_html_e( 'Product Schema (JSON-LD)', 'seomelon' ); ?>
				</button>
			</div>

			<!-- FAQ Editor Tab -->
			<div class="seomelon-manual-panel" data-panel="faq">
				<p class="description">
					<?php esc_html_e( 'Add frequently asked questions. These appear as an FAQ accordion on the frontend and generate FAQPage JSON-LD schema that Google and AI assistants can cite.', 'seomelon' ); ?>
				</p>

				<div id="seomelon-faq-list" class="seomelon-faq-list">
					<?php if ( ! empty( $faqs ) ) : ?>
						<?php foreach ( $faqs as $i => $faq ) : ?>
							<div class="seomelon-faq-item-edit" data-index="<?php echo esc_attr( (int) $i ); ?>">
								<div class="seomelon-faq-item-header">
									<span class="seomelon-faq-item-label"><?php echo esc_html( sprintf( __( 'Question %d', 'seomelon' ), $i + 1 ) ); ?></span>
									<button type="button" class="button-link seomelon-faq-remove" aria-label="<?php esc_attr_e( 'Remove this question', 'seomelon' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
								<input
									type="text"
									name="seomelon_faqs[<?php echo esc_attr( (int) $i ); ?>][question]"
									class="seomelon-faq-question widefat"
									placeholder="<?php esc_attr_e( 'What material is this product made from?', 'seomelon' ); ?>"
									value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>"
								/>
								<textarea
									name="seomelon_faqs[<?php echo esc_attr( (int) $i ); ?>][answer]"
									class="seomelon-faq-answer widefat"
									rows="3"
									placeholder="<?php esc_attr_e( 'Made from 100% sustainable bamboo with a natural oil finish.', 'seomelon' ); ?>"
								><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<button type="button" id="seomelon-faq-add" class="button">
					+ <?php esc_html_e( 'Add Question', 'seomelon' ); ?>
				</button>

				<!-- Hidden template for new FAQ items -->
				<script type="text/html" id="seomelon-faq-template">
					<div class="seomelon-faq-item-edit" data-index="{{INDEX}}">
						<div class="seomelon-faq-item-header">
							<span class="seomelon-faq-item-label"><?php esc_html_e( 'Question', 'seomelon' ); ?> {{NUM}}</span>
							<button type="button" class="button-link seomelon-faq-remove" aria-label="<?php esc_attr_e( 'Remove this question', 'seomelon' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
						<input type="text" name="seomelon_faqs[{{INDEX}}][question]" class="seomelon-faq-question widefat" placeholder="<?php esc_attr_e( 'What material is this product made from?', 'seomelon' ); ?>" value="" />
						<textarea name="seomelon_faqs[{{INDEX}}][answer]" class="seomelon-faq-answer widefat" rows="3" placeholder="<?php esc_attr_e( 'Made from 100% sustainable bamboo with a natural oil finish.', 'seomelon' ); ?>"></textarea>
					</div>
				</script>
			</div>

			<!-- Schema Editor Tab -->
			<div class="seomelon-manual-panel" data-panel="schema" style="display:none;">
				<p class="description">
					<?php esc_html_e( 'Paste a JSON-LD schema object. It will be injected into the page <head> as a <script type="application/ld+json"> tag. If you\'re on WooCommerce, we pre-fill a Product schema scaffold for you.', 'seomelon' ); ?>
				</p>

				<?php
				// If no schema yet and this is a WooCommerce product, pre-fill a scaffold.
				$scaffold = '';
				if ( empty( $schema_json ) && 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $post_id );
					if ( $product ) {
						$scaffold_data = array(
							'@context'    => 'https://schema.org',
							'@type'       => 'Product',
							'name'        => $product->get_name(),
							'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
							'image'       => wp_get_attachment_url( $product->get_image_id() ),
							'sku'         => $product->get_sku(),
							'offers'      => array(
								'@type'         => 'Offer',
								'price'         => $product->get_price(),
								'priceCurrency' => get_woocommerce_currency(),
								'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
								'url'           => get_permalink( $post_id ),
							),
						);
						$scaffold = wp_json_encode( $scaffold_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
					}
				}
				?>

				<textarea
					name="seomelon_manual_schema_json"
					id="seomelon-manual-schema-json"
					class="widefat code"
					rows="14"
					placeholder="<?php esc_attr_e( 'Paste your JSON-LD schema here. Example: {&quot;@context&quot;: &quot;https://schema.org&quot;, &quot;@type&quot;: &quot;Product&quot;, ...}', 'seomelon' ); ?>"
				><?php
					if ( ! empty( $schema_json ) ) {
						// Re-pretty-print if stored as compact JSON.
						$decoded = json_decode( $schema_json, true );
						echo $decoded ? esc_textarea( wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) : esc_textarea( $schema_json );
					} else {
						echo esc_textarea( $scaffold );
					}
				?></textarea>

				<div class="seomelon-manual-schema-actions">
					<button type="button" id="seomelon-schema-validate" class="button">
						<?php esc_html_e( 'Validate JSON', 'seomelon' ); ?>
					</button>
					<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener" class="button">
						<?php esc_html_e( 'Test on Google Rich Results Tester', 'seomelon' ); ?> ↗
					</a>
					<span id="seomelon-schema-validate-result"></span>
				</div>

				<p class="description" style="margin-top: 12px;">
					<strong><?php esc_html_e( 'Tip:', 'seomelon' ); ?></strong>
					<?php esc_html_e( 'Valid schema types include Product, Article, Recipe, Event, LocalBusiness, Organization, and more. See schema.org for the full list.', 'seomelon' ); ?>
				</p>
			</div>

		</div>

		<style>
			.seomelon-manual-wrap { padding: 8px 0; }
			.seomelon-manual-notice { padding: 10px 14px; border-left: 4px solid #ccc; background: #f6f7f7; margin-bottom: 16px; border-radius: 0 4px 4px 0; }
			.seomelon-manual-notice-info { border-left-color: #2e7d32; background: #f1f8e9; }
			.seomelon-manual-notice-success { border-left-color: #2e7d32; background: #e8f5e9; }
			.seomelon-manual-notice p { margin: 0; }
			.seomelon-manual-limit-warning { margin-top: 8px !important; color: #b45309; font-weight: 600; }
			.seomelon-manual-tabs { display: flex; gap: 4px; border-bottom: 1px solid #ddd; margin-bottom: 16px; }
			.seomelon-manual-tab { background: transparent; border: none; border-bottom: 2px solid transparent; padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer; color: #666; }
			.seomelon-manual-tab:hover { color: #2e7d32; }
			.seomelon-manual-tab.active { color: #2e7d32; border-bottom-color: #2e7d32; }
			.seomelon-manual-panel { padding-top: 8px; }
			.seomelon-faq-list { margin-bottom: 12px; }
			.seomelon-faq-item-edit { border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin-bottom: 10px; background: #fff; }
			.seomelon-faq-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
			.seomelon-faq-item-label { font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 0.5px; }
			.seomelon-faq-question { margin-bottom: 8px !important; }
			.seomelon-faq-remove { color: #a00; }
			.seomelon-faq-remove:hover { color: #dc3232; }
			.seomelon-manual-schema-actions { margin-top: 10px; display: flex; gap: 8px; align-items: center; }
			#seomelon-schema-validate-result.success { color: #2e7d32; font-weight: 600; }
			#seomelon-schema-validate-result.error { color: #a00; font-weight: 600; }
			#seomelon-manual-schema-json { font-family: ui-monospace, Menlo, Monaco, "Courier New", monospace; font-size: 12px; }
		</style>
		<?php
	}

	/**
	 * Save manual FAQ and schema entries when the post is saved.
	 *
	 * @param int     $post_id Post being saved.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( int $post_id, WP_Post $post ): void {
		// Bail on autosave, revision, or no nonce.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['seomelon_manual_schema_nonce'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! wp_verify_nonce( $_POST['seomelon_manual_schema_nonce'], 'seomelon_manual_schema' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// ── Enforce free-tier limit ─────────────────────────────────
		$plan_tier = get_option( 'seomelon_plan_tier', 'free' );
		$is_free   = in_array( $plan_tier, array( 'free', '' ), true );
		$has_existing = (bool) get_post_meta( $post_id, '_seomelon_faq_schema', true )
			|| (bool) get_post_meta( $post_id, '_seomelon_schema', true );

		if ( $is_free && ! $has_existing && $this->get_manual_schema_count() >= self::FREE_TIER_LIMIT ) {
			// Silently skip saves that would exceed the limit.
			// The UI already warned the user; we don't want to nuke their work.
			return;
		}

		// ── Save FAQ entries ────────────────────────────────────────
		// Don't overwrite AI-generated FAQ data unless the user deliberately edits.
		// The hidden form always POSTs the current state, so this is safe.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_faqs = $_POST['seomelon_faqs'] ?? array();

		if ( is_array( $raw_faqs ) ) {
			$faqs = array();
			foreach ( $raw_faqs as $faq ) {
				$question = isset( $faq['question'] ) ? sanitize_text_field( wp_unslash( $faq['question'] ) ) : '';
				$answer   = isset( $faq['answer'] ) ? sanitize_textarea_field( wp_unslash( $faq['answer'] ) ) : '';
				if ( ! empty( $question ) && ! empty( $answer ) ) {
					$faqs[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
			}

			if ( ! empty( $faqs ) ) {
				update_post_meta( $post_id, '_seomelon_faq_schema', wp_json_encode( $faqs ) );
			} elseif ( empty( $raw_faqs ) ) {
				// User explicitly cleared all FAQs.
				delete_post_meta( $post_id, '_seomelon_faq_schema' );
			}
		}

		// ── Save manual JSON-LD schema ──────────────────────────────
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$schema_raw = isset( $_POST['seomelon_manual_schema_json'] ) ? wp_unslash( $_POST['seomelon_manual_schema_json'] ) : '';
		$schema_raw = trim( $schema_raw );

		if ( ! empty( $schema_raw ) ) {
			// Validate JSON before saving.
			$decoded = json_decode( $schema_raw, true );
			if ( is_array( $decoded ) && json_last_error() === JSON_ERROR_NONE ) {
				// Store as compact JSON to match AI-generated format.
				update_post_meta( $post_id, '_seomelon_schema', wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			}
			// Invalid JSON is silently ignored — the validate button in the UI catches this client-side.
		} else {
			delete_post_meta( $post_id, '_seomelon_schema' );
		}
	}

	/**
	 * Count posts with manual schema (used for free-tier enforcement).
	 *
	 * Results are cached for 5 minutes to avoid repeated queries.
	 *
	 * @return int Number of posts with non-empty _seomelon_schema or _seomelon_faq_schema meta.
	 */
	private function get_manual_schema_count(): int {
		$cached = get_transient( 'seomelon_manual_schema_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key IN ('_seomelon_schema', '_seomelon_faq_schema')
			   AND meta_value != ''"
		);

		set_transient( 'seomelon_manual_schema_count', $count, 5 * MINUTE_IN_SECONDS );

		return $count;
	}
}
