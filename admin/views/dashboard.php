<?php
/**
 * Dashboard admin page.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

$api           = seomelon()->api;
$is_configured = $api->is_configured();
$last_sync     = get_option( 'seomelon_last_sync', '' );
$settings      = get_option( 'seomelon_settings', array() );
$content_types = $settings['content_types'] ?? array( 'product', 'post', 'page' );
$has_woo       = SEOMelon::is_woocommerce_active();

// Fetch connection status and content from API.
$connection = null;
$content    = array();
$stats      = array(
	'total'     => 0,
	'optimized' => 0,
	'need_work' => 0,
);

if ( $is_configured ) {
	// Cache the verify result for 5 minutes to avoid an external HTTP
	// request on every dashboard page load.
	$connection = get_transient( 'seomelon_connection_status' );
	if ( false === $connection ) {
		$connection = $api->verify();
		if ( ! is_wp_error( $connection ) ) {
			set_transient( 'seomelon_connection_status', $connection, 300 );
		}
	}
	$content = $api->get_content();

	if ( ! is_wp_error( $content ) && is_array( $content ) ) {
		// Laravel returns { items: [...] }.
		$items = $content['items'] ?? $content['data'] ?? $content;
		if ( is_array( $items ) ) {
			$stats['total'] = count( $items );
			foreach ( $items as $item ) {
				$score = $item['seo_score'] ?? 0;
				if ( $score >= 70 ) {
					$stats['optimized']++;
				} else {
					$stats['need_work']++;
				}
			}
		}
	}
}
?>
<div class="wrap seomelon-wrap">
	<h1 class="wp-heading-inline">
		<span class="seomelon-logo">&#127849;</span>
		<?php esc_html_e( 'SEOMelon Dashboard', 'seomelon' ); ?>
	</h1>

	<?php if ( ! $is_configured ) : ?>
		<div class="seomelon-onboarding">
			<div class="seomelon-onboarding-card">
				<h2><?php esc_html_e( 'Welcome to SEOMelon! 🍈', 'seomelon' ); ?></h2>
				<p><?php esc_html_e( 'Get started in 3 easy steps to optimize your SEO with AI:', 'seomelon' ); ?></p>
				<ol class="seomelon-onboarding-steps">
					<li class="seomelon-step-active">
						<strong><?php esc_html_e( 'Connect your API key', 'seomelon' ); ?></strong>
						<span><?php esc_html_e( 'Register or add your key in Settings', 'seomelon' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Sync & Scan your content', 'seomelon' ); ?></strong>
						<span><?php esc_html_e( 'SEOMelon will analyze your products, posts, and pages', 'seomelon' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Generate & Apply AI SEO', 'seomelon' ); ?></strong>
						<span><?php esc_html_e( 'Review suggestions and apply with one click', 'seomelon' ); ?></span>
					</li>
				</ol>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-settings' ) ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Go to Settings', 'seomelon' ); ?>
					</a>
				</p>
			</div>
		</div>
	<?php else : ?>

		<!-- Connection & Stats Bar -->
		<div class="seomelon-stats-bar">
			<div class="seomelon-stat-card">
				<span class="seomelon-stat-label"><?php esc_html_e( 'Connection', 'seomelon' ); ?></span>
				<span class="seomelon-stat-value" id="seomelon-connection-status">
					<?php if ( ! is_wp_error( $connection ) ) : ?>
						<span class="seomelon-badge seomelon-badge-green"><?php esc_html_e( 'Connected', 'seomelon' ); ?></span>
					<?php else : ?>
						<span class="seomelon-badge seomelon-badge-red"><?php esc_html_e( 'Error', 'seomelon' ); ?></span>
					<?php endif; ?>
				</span>
			</div>
			<div class="seomelon-stat-card">
				<span class="seomelon-stat-label"><?php esc_html_e( 'Plan', 'seomelon' ); ?></span>
				<span class="seomelon-stat-value">
					<?php
					if ( ! is_wp_error( $connection ) && isset( $connection['plan'] ) ) {
						echo esc_html( ucfirst( $connection['plan'] ) );
					} else {
						echo '&mdash;';
					}
					?>
				</span>
			</div>
			<div class="seomelon-stat-card">
				<span class="seomelon-stat-label"><?php esc_html_e( 'Total Synced', 'seomelon' ); ?></span>
				<span class="seomelon-stat-value"><?php echo esc_html( $stats['total'] ); ?></span>
			</div>
			<div class="seomelon-stat-card">
				<span class="seomelon-stat-label"><?php esc_html_e( 'Optimized', 'seomelon' ); ?></span>
				<span class="seomelon-stat-value seomelon-text-green"><?php echo esc_html( $stats['optimized'] ); ?></span>
			</div>
			<div class="seomelon-stat-card">
				<span class="seomelon-stat-label"><?php esc_html_e( 'Need Work', 'seomelon' ); ?></span>
				<span class="seomelon-stat-value seomelon-text-red"><?php echo esc_html( $stats['need_work'] ); ?></span>
			</div>
			<div class="seomelon-stat-card">
				<span class="seomelon-stat-label"><?php esc_html_e( 'Last Sync', 'seomelon' ); ?></span>
				<span class="seomelon-stat-value">
					<?php echo $last_sync ? esc_html( human_time_diff( strtotime( $last_sync ) ) . ' ago' ) : '&mdash;'; ?>
				</span>
			</div>
		</div>

		<!-- Bulk Actions -->
		<div class="seomelon-bulk-actions">
			<button type="button" class="button button-primary" id="seomelon-sync-all">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Sync All Content', 'seomelon' ); ?>
			</button>
			<button type="button" class="button" id="seomelon-scan-all">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Scan All', 'seomelon' ); ?>
			</button>
			<button type="button" class="button" id="seomelon-generate-all">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate All', 'seomelon' ); ?>
			</button>
			<button type="button" class="button" id="seomelon-apply-all">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Apply All', 'seomelon' ); ?>
			</button>
			<span class="spinner" id="seomelon-bulk-spinner"></span>
			<span id="seomelon-bulk-status" class="seomelon-status-message"></span>
		</div>

		<!-- Content Type Tabs -->
		<h2 class="nav-tab-wrapper seomelon-tabs" id="seomelon-content-tabs">
			<a href="#all" class="nav-tab nav-tab-active" data-type="all">
				<?php esc_html_e( 'All', 'seomelon' ); ?>
			</a>
			<?php if ( $has_woo && in_array( 'product', $content_types, true ) ) : ?>
				<a href="#products" class="nav-tab" data-type="product">
					<?php esc_html_e( 'Products', 'seomelon' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( in_array( 'post', $content_types, true ) ) : ?>
				<a href="#posts" class="nav-tab" data-type="post">
					<?php esc_html_e( 'Posts', 'seomelon' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( in_array( 'page', $content_types, true ) ) : ?>
				<a href="#pages" class="nav-tab" data-type="page">
					<?php esc_html_e( 'Pages', 'seomelon' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( in_array( 'category', $content_types, true ) ) : ?>
				<a href="#categories" class="nav-tab" data-type="category">
					<?php esc_html_e( 'Categories', 'seomelon' ); ?>
				</a>
			<?php endif; ?>
		</h2>

		<!-- Content Table -->
		<div id="seomelon-content-table-wrap">
			<table class="wp-list-table widefat fixed striped" id="seomelon-content-table">
				<thead>
					<tr>
						<th class="column-title"><?php esc_html_e( 'Title', 'seomelon' ); ?></th>
						<th class="column-type"><?php esc_html_e( 'Type', 'seomelon' ); ?></th>
						<th class="column-score"><?php esc_html_e( 'SEO Score', 'seomelon' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'seomelon' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'seomelon' ); ?></th>
					</tr>
				</thead>
				<tbody id="seomelon-content-body">
					<?php
					if ( ! is_wp_error( $content ) && is_array( $content ) ) :
						$items = $content['items'] ?? $content['data'] ?? $content;
						if ( is_array( $items ) && ! empty( $items ) ) :
							foreach ( $items as $item ) :
								$score = $item['seo_score'] ?? 0;
								if ( $score >= 70 ) {
									$score_class = 'seomelon-score-good';
								} elseif ( $score >= 50 ) {
									$score_class = 'seomelon-score-ok';
								} else {
									$score_class = 'seomelon-score-poor';
								}

								$status_label = $item['status'] ?? 'pending';
								if ( 'optimized' === $status_label || 'generated' === $status_label ) {
									$status_class = 'seomelon-badge-green';
								} elseif ( 'scanned' === $status_label || 'synced' === $status_label ) {
									$status_class = 'seomelon-badge-blue';
								} else {
									$status_class = 'seomelon-badge-grey';
								}
								?>
								<tr data-content-id="<?php echo esc_attr( $item['id'] ?? '' ); ?>"
									data-platform-id="<?php echo esc_attr( $item['platform_id'] ?? '' ); ?>"
									data-content-type="<?php echo esc_attr( $item['content_type'] ?? '' ); ?>">
									<td class="column-title">
										<strong>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon&view=detail&id=' . ( $item['id'] ?? '' ) ) ); ?>">
												<?php echo esc_html( $item['title'] ?? __( '(Untitled)', 'seomelon' ) ); ?>
											</a>
										</strong>
									</td>
									<td class="column-type">
										<?php echo esc_html( ucfirst( $item['content_type'] ?? '' ) ); ?>
									</td>
									<td class="column-score">
										<?php if ( $score > 0 ) : ?>
											<span class="seomelon-score-badge <?php echo esc_attr( $score_class ); ?>">
												<?php echo esc_html( $score ); ?>
											</span>
										<?php else : ?>
											<span class="seomelon-score-badge seomelon-score-none">&mdash;</span>
										<?php endif; ?>
									</td>
									<td class="column-status">
										<span class="seomelon-badge <?php echo esc_attr( $status_class ); ?>">
											<?php echo esc_html( ucfirst( $status_label ) ); ?>
										</span>
									</td>
									<td class="column-actions">
										<button type="button" class="button button-small seomelon-action-generate"
												data-content-id="<?php echo esc_attr( $item['id'] ?? '' ); ?>">
											<?php esc_html_e( 'Generate', 'seomelon' ); ?>
										</button>
										<button type="button" class="button button-small seomelon-action-apply"
												data-content-id="<?php echo esc_attr( $item['id'] ?? '' ); ?>"
												data-post-id="<?php echo esc_attr( $item['platform_id'] ?? '' ); ?>"
												data-content-type="<?php echo esc_attr( $item['content_type'] ?? 'post' ); ?>">
											<?php esc_html_e( 'Apply', 'seomelon' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="5">
									<?php esc_html_e( 'No content synced yet. Click "Sync All Content" to get started.', 'seomelon' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					<?php else : ?>
						<tr>
							<td colspan="5">
								<?php
								if ( is_wp_error( $content ) ) {
									echo esc_html( $content->get_error_message() );
								} else {
									esc_html_e( 'No content synced yet. Click "Sync All Content" to get started.', 'seomelon' );
								}
								?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<?php
		$items_array  = ( ! is_wp_error( $content ) && is_array( $content ) ) ? ( $content['items'] ?? $content['data'] ?? $content ) : array();
		$total_items  = is_array( $items_array ) ? count( $items_array ) : 0;
		$per_page     = 20;
		$total_pages  = max( 1, (int) ceil( $total_items / $per_page ) );
		?>
		<?php if ( $total_items > $per_page ) : ?>
			<div class="seomelon-pagination">
				<span class="seomelon-pagination-info">
					<?php
					printf(
						/* translators: %d: total items count */
						esc_html__( 'Showing %d items', 'seomelon' ),
						$total_items
					);
					?>
				</span>
				<div class="seomelon-pagination-links" id="seomelon-pagination">
					<button type="button" class="button" data-page="prev" id="seomelon-page-prev">&laquo; <?php esc_html_e( 'Prev', 'seomelon' ); ?></button>
					<span class="button disabled" id="seomelon-page-display">1 / <?php echo esc_html( $total_pages ); ?></span>
					<button type="button" class="button" data-page="next" id="seomelon-page-next"><?php esc_html_e( 'Next', 'seomelon' ); ?> &raquo;</button>
				</div>
			</div>
		<?php endif; ?>

		<!-- Job Progress Modal -->
		<div id="seomelon-progress-modal" class="seomelon-modal" style="display:none;">
			<div class="seomelon-modal-content">
				<h3 id="seomelon-progress-title"><?php esc_html_e( 'Processing...', 'seomelon' ); ?></h3>
				<div class="seomelon-progress-bar">
					<div class="seomelon-progress-fill" id="seomelon-progress-fill" style="width:0%"></div>
				</div>
				<p id="seomelon-progress-message"><?php esc_html_e( 'Please wait while your content is being processed.', 'seomelon' ); ?></p>
				<button type="button" class="button" id="seomelon-progress-close" style="display:none;">
					<?php esc_html_e( 'Close', 'seomelon' ); ?>
				</button>
			</div>
		</div>

	<?php endif; ?>
</div>
