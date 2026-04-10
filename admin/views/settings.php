<?php
/**
 * Settings admin page.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

$api_key       = get_option( 'seomelon_api_key', '' );
$api_url       = get_option( 'seomelon_api_url', SEOMELON_API_URL );
$settings      = get_option( 'seomelon_settings', array() );
$content_types = $settings['content_types'] ?? array( 'product', 'post', 'page' );
$tone          = $settings['tone'] ?? 'professional';
$auto_sync     = $settings['auto_sync'] ?? 'manual';
$has_woo       = SEOMelon::is_woocommerce_active();
$seo_plugin    = seomelon()->seo_detect->get_active_plugin_name();
?>
<div class="wrap seomelon-wrap">
	<h1>
		<span class="seomelon-logo">&#127817;</span>
		<?php esc_html_e( 'SEOMelon Settings', 'seomelon' ); ?>
	</h1>

	<div class="seomelon-settings-grid">

		<?php
		// Detect connection state
		$is_passport  = $api_key && str_starts_with( $api_key, 'eyJ' );
		$is_api_key   = $api_key && str_starts_with( $api_key, 'sm_live_' );
		$is_connected = $is_passport || $is_api_key;
		?>

		<?php if ( ! $is_connected ) : ?>
			<!-- Onboarding: One-Click Connect (shown when not connected) -->
			<div class="seomelon-settings-section seomelon-onboarding-hero">
				<div style="text-align: center; padding: 20px 0;">
					<h2 style="font-size: 1.5em; margin-bottom: 8px;">
						<?php esc_html_e( 'Connect Your Store to SEOMelon', 'seomelon' ); ?>
					</h2>
					<p class="description" style="font-size: 14px; margin-bottom: 24px;">
						<?php esc_html_e( 'One click to connect. We\'ll analyze your products, research your competitors, and generate optimized SEO content.', 'seomelon' ); ?>
					</p>

					<input type="email"
						id="seomelon-register-email"
						value="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>"
						class="regular-text"
						placeholder="you@example.com"
						style="max-width: 320px; margin-bottom: 16px; display: block; margin-left: auto; margin-right: auto;" />
					<input type="hidden" id="seomelon-register-name" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />

					<button type="button" class="button button-primary button-hero" id="seomelon-register">
						<span class="dashicons dashicons-admin-network" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Connect to SEOMelon — Free', 'seomelon' ); ?>
					</button>
					<span class="spinner" id="seomelon-register-spinner"></span>
					<div id="seomelon-register-result" class="seomelon-status-message" style="margin-top: 12px;"></div>

					<p class="description" style="margin-top: 20px; font-size: 12px; color: #888;">
						<?php esc_html_e( 'No credit card required. Free plan includes 5 product optimizations per month.', 'seomelon' ); ?>
						<br />
						<a href="#" id="seomelon-show-advanced-connect" style="font-size: 12px;">
							<?php esc_html_e( 'Advanced: enter API key manually', 'seomelon' ); ?>
						</a>
					</p>
				</div>
			</div>

			<!-- Advanced Connection (hidden by default when not connected) -->
			<div class="seomelon-settings-section" id="seomelon-advanced-connect" style="display: none;">
				<h2><?php esc_html_e( 'Manual API Connection', 'seomelon' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="seomelon-api-key"><?php esc_html_e( 'API Key', 'seomelon' ); ?></label>
						</th>
						<td>
							<div class="seomelon-input-group">
								<input type="password"
									id="seomelon-api-key"
									name="api_key"
									value=""
									class="regular-text"
									autocomplete="off" />
								<button type="button" class="button" id="seomelon-toggle-key" title="<?php esc_attr_e( 'Show/hide API key', 'seomelon' ); ?>">
									<span class="dashicons dashicons-visibility"></span>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Paste an API key if you already have one from another installation.', 'seomelon' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="seomelon-api-url"><?php esc_html_e( 'API URL', 'seomelon' ); ?></label>
						</th>
						<td>
							<input type="url"
								id="seomelon-api-url"
								name="api_url"
								value="<?php echo esc_url( $api_url ); ?>"
								class="regular-text"
								placeholder="https://seomelon.app/api/v1" />
							<p class="description">
								<?php esc_html_e( 'Change only if self-hosting.', 'seomelon' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		<?php else : ?>
			<!-- Connected State -->
			<div class="seomelon-settings-section">
				<h2><?php esc_html_e( 'Connection', 'seomelon' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'seomelon' ); ?></th>
						<td>
							<span class="seomelon-badge seomelon-badge-green">
								<?php echo $is_passport ? esc_html__( 'Connected via SEOMelon', 'seomelon' ) : esc_html__( 'Connected (API Key)', 'seomelon' ); ?>
							</span>
							<input type="hidden" id="seomelon-api-key" name="api_key" value="<?php echo esc_attr( $api_key ); ?>" />
							<input type="hidden" id="seomelon-api-url" name="api_url" value="<?php echo esc_url( $api_url ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection Test', 'seomelon' ); ?></th>
						<td>
							<button type="button" class="button" id="seomelon-test-connection">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Test Connection', 'seomelon' ); ?>
							</button>
							<span class="spinner" id="seomelon-test-spinner"></span>
							<span id="seomelon-test-result" class="seomelon-status-message"></span>
						</td>
					</tr>
					<tr>
						<th scope="row">&nbsp;</th>
						<td>
							<button type="button" class="button" id="seomelon-disconnect">
								<span class="dashicons dashicons-dismiss" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Disconnect', 'seomelon' ); ?>
							</button>
						</td>
					</tr>
				</table>
			</div>

			<!-- Pricing / Plan -->
			<div class="seomelon-settings-section">
				<h2><?php esc_html_e( 'Plan', 'seomelon' ); ?></h2>
				<div class="seomelon-pricing-cards" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
					<?php
					$plans = array(
						array(
							'id'            => 'free',
							'name'          => 'Free',
							'price'         => '$0',
							'period'        => '/month',
							'badge'         => '',
							'standard_price' => null,
							'features'      => array( '5 optimizations/month', 'SEO health scoring', 'SERP preview', 'AEO readiness audit' ),
						),
						array(
							'id'            => 'pro',
							'name'          => 'Pro',
							'price'         => '$39',
							'period'        => '/month',
							'badge'         => 'Pioneer — locked for life',
							'standard_price' => '$49',
							'features'      => array( '75 optimizations/month', 'All 7 AI agents', 'Schema + JSON-LD markup', 'Competitive intelligence', 'Scheduled scans' ),
						),
						array(
							'id'            => 'advisor',
							'name'          => 'Advisor',
							'price'         => '$99',
							'period'        => '/month',
							'badge'         => 'Pioneer — locked for life',
							'standard_price' => '$149',
							'features'      => array( 'Unlimited optimizations', 'Answer Engine Optimization', 'Multi-language SEO (12)', 'Auto-approve mode', 'Google Search Console', 'Founding Member badge' ),
						),
					);

					$current_plan = get_option( 'seomelon_plan_tier', 'free' );

					foreach ( $plans as $plan ) :
						$is_current = false;
						if ( 'free' === $plan['id'] && in_array( $current_plan, array( 'free', '' ), true ) ) {
							$is_current = true;
						} elseif ( 'pro' === $plan['id'] && in_array( $current_plan, array( 'pro', 'growth', 'starter' ), true ) ) {
							$is_current = true;
						} elseif ( 'advisor' === $plan['id'] && in_array( $current_plan, array( 'advisor', 'premium' ), true ) ) {
							$is_current = true;
						}
						?>
						<div class="seomelon-plan-card" style="border: 1px solid <?php echo $is_current ? '#2e7d32' : '#ddd'; ?>; border-radius: 8px; padding: 20px; text-align: center; background: <?php echo $is_current ? '#f1f8e9' : '#fff'; ?>;">
							<?php if ( $plan['badge'] ) : ?>
								<span style="background: #ff9800; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
									<?php echo esc_html( $plan['badge'] ); ?>
								</span>
							<?php endif; ?>
							<h3 style="margin: 12px 0 4px;"><?php echo esc_html( $plan['name'] ); ?></h3>
							<?php if ( ! empty( $plan['standard_price'] ) ) : ?>
								<div style="font-size: 13px; color: #999; text-decoration: line-through;"><?php echo esc_html( $plan['standard_price'] ); ?> /month</div>
							<?php endif; ?>
							<div style="font-size: 28px; font-weight: 700; margin: 4px 0 8px; color: <?php echo ! empty( $plan['standard_price'] ) ? '#2e7d32' : 'inherit'; ?>;">
								<?php echo esc_html( $plan['price'] ); ?>
								<span style="font-size: 14px; font-weight: 400; color: #666;"><?php echo esc_html( $plan['period'] ); ?></span>
							</div>
							<ul style="text-align: left; list-style: none; padding: 0; margin: 16px 0;">
								<?php foreach ( $plan['features'] as $feature ) : ?>
									<li style="padding: 4px 0; font-size: 13px;">&#10003; <?php echo esc_html( $feature ); ?></li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $is_current ) : ?>
								<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'Current Plan', 'seomelon' ); ?></span>
							<?php elseif ( 'free' !== $plan['id'] ) : ?>
								<button type="button" class="button button-primary seomelon-upgrade-btn" data-plan="<?php echo esc_attr( $plan['id'] ); ?>">
									<?php
									/* translators: %s: plan name */
									printf( esc_html__( 'Upgrade to %s', 'seomelon' ), esc_html( $plan['name'] ) );
									?>
								</button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="description" style="margin-top: 12px; text-align: center;">
					<?php esc_html_e( 'Pioneer Pricing — locked for life. Limited to first 200 Founding Members.', 'seomelon' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<!-- Content Settings -->
		<div class="seomelon-settings-section">
			<h2><?php esc_html_e( 'Content Settings', 'seomelon' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Content Types', 'seomelon' ); ?></th>
					<td>
						<fieldset>
							<?php
							// Auto-discover all public post types.
							$all_post_types = get_post_types(
								array(
									'public'  => true,
									'show_ui' => true,
								),
								'objects'
							);

							// Always show built-in types first.
							$priority_types = array( 'product', 'post', 'page' );

							foreach ( $priority_types as $pt_slug ) :
								if ( ! isset( $all_post_types[ $pt_slug ] ) ) {
									continue;
								}
								$pt = $all_post_types[ $pt_slug ];
								if ( 'product' === $pt_slug && ! $has_woo ) {
									continue;
								}
								$label = 'product' === $pt_slug
									? __( 'WooCommerce Products', 'seomelon' )
									: $pt->labels->name;
								?>
								<label>
									<input type="checkbox"
										name="content_types[]"
										value="<?php echo esc_attr( $pt_slug ); ?>"
										<?php checked( in_array( $pt_slug, $content_types, true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label><br />
							<?php endforeach; ?>

							<?php
							// Show all custom post types (non-built-in).
							foreach ( $all_post_types as $pt_slug => $pt ) :
								// Skip built-in and already shown types.
								if ( in_array( $pt_slug, array( 'product', 'post', 'page', 'attachment' ), true ) ) {
									continue;
								}
								?>
								<label>
									<input type="checkbox"
										name="content_types[]"
										value="<?php echo esc_attr( $pt_slug ); ?>"
										<?php checked( in_array( $pt_slug, $content_types, true ) ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
									<span style="color: #646970; font-size: 12px;">(<?php echo esc_html( $pt_slug ); ?>)</span>
								</label><br />
							<?php endforeach; ?>

							<!-- Categories -->
							<label>
								<input type="checkbox"
									name="content_types[]"
									value="category"
									<?php checked( in_array( 'category', $content_types, true ) ); ?> />
								<?php esc_html_e( 'Categories', 'seomelon' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="seomelon-tone"><?php esc_html_e( 'Content Tone', 'seomelon' ); ?></label>
					</th>
					<td>
						<select id="seomelon-tone" name="tone">
							<option value="professional" <?php selected( $tone, 'professional' ); ?>>
								<?php esc_html_e( 'Professional', 'seomelon' ); ?>
							</option>
							<option value="casual" <?php selected( $tone, 'casual' ); ?>>
								<?php esc_html_e( 'Casual', 'seomelon' ); ?>
							</option>
							<option value="friendly" <?php selected( $tone, 'friendly' ); ?>>
								<?php esc_html_e( 'Friendly', 'seomelon' ); ?>
							</option>
							<option value="authoritative" <?php selected( $tone, 'authoritative' ); ?>>
								<?php esc_html_e( 'Authoritative', 'seomelon' ); ?>
							</option>
							<option value="playful" <?php selected( $tone, 'playful' ); ?>>
								<?php esc_html_e( 'Playful', 'seomelon' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="seomelon-auto-sync"><?php esc_html_e( 'Auto-Sync', 'seomelon' ); ?></label>
					</th>
					<td>
						<select id="seomelon-auto-sync" name="auto_sync">
							<option value="manual" <?php selected( $auto_sync, 'manual' ); ?>>
								<?php esc_html_e( 'Manual Only', 'seomelon' ); ?>
							</option>
							<option value="daily" <?php selected( $auto_sync, 'daily' ); ?>>
								<?php esc_html_e( 'Daily', 'seomelon' ); ?>
							</option>
							<option value="weekly" <?php selected( $auto_sync, 'weekly' ); ?>>
								<?php esc_html_e( 'Weekly', 'seomelon' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Automatically sync your content to SEOMelon on a schedule.', 'seomelon' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Multi-Language SEO -->
		<div class="seomelon-settings-section">
			<h2><?php esc_html_e( 'Multi-Language SEO', 'seomelon' ); ?> <span class="seomelon-badge seomelon-badge-blue"><?php esc_html_e( 'Advisor', 'seomelon' ); ?></span></h2>
			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'Generate SEO metadata in additional languages. Available on the Advisor plan.', 'seomelon' ); ?>
			</p>

			<?php
			$available_locales = array(
				'es' => __( 'Spanish', 'seomelon' ),
				'fr' => __( 'French', 'seomelon' ),
				'de' => __( 'German', 'seomelon' ),
				'it' => __( 'Italian', 'seomelon' ),
				'pt' => __( 'Portuguese', 'seomelon' ),
				'ja' => __( 'Japanese', 'seomelon' ),
				'ko' => __( 'Korean', 'seomelon' ),
				'zh' => __( 'Chinese', 'seomelon' ),
				'ar' => __( 'Arabic', 'seomelon' ),
				'nl' => __( 'Dutch', 'seomelon' ),
				'sv' => __( 'Swedish', 'seomelon' ),
				'pl' => __( 'Polish', 'seomelon' ),
			);
			$selected_locales = $settings['target_locales'] ?? array();
			?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Target Languages', 'seomelon' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $available_locales as $code => $name ) : ?>
								<label>
									<input type="checkbox"
										name="target_locales[]"
										value="<?php echo esc_attr( $code ); ?>"
										<?php checked( in_array( $code, $selected_locales, true ) ); ?> />
									<?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)
								</label><br />
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select languages for multi-language SEO generation.', 'seomelon' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Google Search Console -->
		<div class="seomelon-settings-section">
			<h2><?php esc_html_e( 'Google Search Console', 'seomelon' ); ?></h2>
			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'Connect your Google Search Console to track real search performance for your content.', 'seomelon' ); ?>
			</p>

			<?php
			$gsc_status    = null;
			$gsc_connected = false;
			if ( $api_key ) {
				$gsc_status = seomelon()->api->get_gsc_status();
				if ( ! is_wp_error( $gsc_status ) ) {
					$gsc_connected = ! empty( $gsc_status['connected'] );
				}
			}
			?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'seomelon' ); ?></th>
					<td>
						<?php if ( $gsc_connected ) : ?>
							<div id="seomelon-gsc-status">
								<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'Connected', 'seomelon' ); ?></span>
								<?php if ( ! empty( $gsc_status['site_url'] ) ) : ?>
									<span style="margin-left: 8px;">
										<?php
										printf(
											/* translators: %s: site URL connected to GSC */
											esc_html__( 'Site: %s', 'seomelon' ),
											'<strong id="seomelon-gsc-url">' . esc_html( $gsc_status['site_url'] ) . '</strong>'
										);
										?>
									</span>
								<?php endif; ?>
								<?php if ( ! empty( $gsc_status['connected_at'] ) ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: date the GSC was connected */
											esc_html__( 'Connected on %s', 'seomelon' ),
											esc_html( wp_date( get_option( 'date_format' ), strtotime( $gsc_status['connected_at'] ) ) )
										);
										?>
									</p>
								<?php endif; ?>
								<p style="margin-top: 8px;">
									<button type="button" class="button" id="seomelon-gsc-disconnect">
										<span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
										<?php esc_html_e( 'Disconnect', 'seomelon' ); ?>
									</button>
								</p>
							</div>
						<?php else : ?>
							<div id="seomelon-gsc-not-connected">
								<p class="description" style="margin-bottom: 8px;">
									<?php esc_html_e( 'Track impressions, clicks, and search rankings for your content.', 'seomelon' ); ?>
								</p>
								<button type="button" class="button button-primary" id="seomelon-gsc-connect">
									<span class="dashicons dashicons-admin-site" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Connect Google Search Console', 'seomelon' ); ?>
								</button>
								<span class="spinner" id="seomelon-gsc-spinner"></span>
								<span id="seomelon-gsc-result" class="seomelon-status-message"></span>
							</div>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Environment Info -->
		<div class="seomelon-settings-section">
			<h2><?php esc_html_e( 'Environment', 'seomelon' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'SEO Plugin', 'seomelon' ); ?></th>
					<td>
						<strong><?php echo esc_html( $seo_plugin ); ?></strong>
						<p class="description">
							<?php esc_html_e( 'SEOMelon will read and write meta titles/descriptions using this plugin\'s fields.', 'seomelon' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WooCommerce', 'seomelon' ); ?></th>
					<td>
						<?php if ( $has_woo ) : ?>
							<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'Active', 'seomelon' ); ?></span>
						<?php else : ?>
							<span class="seomelon-badge seomelon-badge-grey"><?php esc_html_e( 'Not installed', 'seomelon' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Install WooCommerce to enable product scanning and optimization.', 'seomelon' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Plugin Version', 'seomelon' ); ?></th>
					<td><?php echo esc_html( SEOMELON_VERSION ); ?></td>
				</tr>
			</table>
		</div>

	</div>

	<!-- Save Button -->
	<p class="submit">
		<button type="button" class="button button-primary button-hero" id="seomelon-save-settings">
			<?php esc_html_e( 'Save Settings', 'seomelon' ); ?>
		</button>
		<span class="spinner" id="seomelon-save-spinner"></span>
		<span id="seomelon-save-result" class="seomelon-status-message"></span>
	</p>
</div>
