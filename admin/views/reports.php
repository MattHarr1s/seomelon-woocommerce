<?php
/**
 * Reports admin page.
 *
 * Displays SEO reports including Growth Reports and Improvement Reports.
 *
 * @package SEOMelon
 */

defined( 'ABSPATH' ) || exit;

$api     = seomelon()->api;
$reports = array();

if ( $api->is_configured() ) {
	$result = $api->get_reports();
	if ( ! is_wp_error( $result ) ) {
		$reports = $result['reports'] ?? array();
	}
}

// Check if viewing a single report detail.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$report_id = isset( $_GET['report_id'] ) ? absint( $_GET['report_id'] ) : 0;

if ( $report_id ) :
	$report_result = $api->get_report( $report_id );
	$report        = null;
	if ( ! is_wp_error( $report_result ) ) {
		$report = $report_result['report'] ?? $report_result;
	}

	if ( ! $report ) :
		?>
		<div class="wrap seomelon-wrap">
			<h1><?php esc_html_e( 'Report Not Found', 'seomelon' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-reports' ) ); ?>">
					<?php esc_html_e( '&larr; Back to Reports', 'seomelon' ); ?>
				</a>
			</p>
		</div>
		<?php
		return;
	endif;
	?>
	<div class="wrap seomelon-wrap">
		<h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-reports' ) ); ?>" class="seomelon-back-link">
				<?php esc_html_e( '&larr; Reports', 'seomelon' ); ?>
			</a>
			&mdash;
			<?php echo esc_html( ( $report['report_type'] ?? 'Report' ) . ': ' . ( $report['period_label'] ?? '' ) ); ?>
		</h1>

		<div class="seomelon-report-detail">
			<div class="seomelon-report-actions" style="margin-bottom: 16px;">
				<button type="button" class="button" onclick="window.print();">
					<span class="dashicons dashicons-printer"></span>
					<?php esc_html_e( 'Print / Download PDF', 'seomelon' ); ?>
				</button>
			</div>

			<?php
			$summary = $report['summary'] ?? array();
			if ( is_string( $summary ) ) {
				$summary = json_decode( $summary, true ) ?? array();
			}
			?>

			<?php if ( ! empty( $summary ) ) : ?>
				<div class="seomelon-stats-bar">
					<?php if ( isset( $summary['avg_score'] ) ) : ?>
						<div class="seomelon-stat-card">
							<span class="seomelon-stat-label"><?php esc_html_e( 'Average Score', 'seomelon' ); ?></span>
							<span class="seomelon-stat-value"><?php echo esc_html( $summary['avg_score'] ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( isset( $summary['total_products'] ) ) : ?>
						<div class="seomelon-stat-card">
							<span class="seomelon-stat-label"><?php esc_html_e( 'Total Products', 'seomelon' ); ?></span>
							<span class="seomelon-stat-value"><?php echo esc_html( $summary['total_products'] ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( isset( $summary['optimized'] ) ) : ?>
						<div class="seomelon-stat-card">
							<span class="seomelon-stat-label"><?php esc_html_e( 'Optimized', 'seomelon' ); ?></span>
							<span class="seomelon-stat-value seomelon-text-green"><?php echo esc_html( $summary['optimized'] ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( isset( $summary['need_work'] ) ) : ?>
						<div class="seomelon-stat-card">
							<span class="seomelon-stat-label"><?php esc_html_e( 'Need Work', 'seomelon' ); ?></span>
							<span class="seomelon-stat-value seomelon-text-red"><?php echo esc_html( $summary['need_work'] ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			$sections = $report['sections'] ?? $report['detailed_sections'] ?? array();
			if ( is_string( $sections ) ) {
				$sections = json_decode( $sections, true ) ?? array();
			}
			?>

			<?php if ( ! empty( $sections ) ) : ?>
				<?php foreach ( $sections as $section ) : ?>
					<div class="seomelon-detail-card seomelon-detail-card-full" style="margin-bottom: 16px;">
						<h3><?php echo esc_html( $section['title'] ?? '' ); ?></h3>
						<?php if ( ! empty( $section['content'] ) ) : ?>
							<div class="seomelon-report-content">
								<?php echo wp_kses_post( $section['content'] ); ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $section['items'] ) && is_array( $section['items'] ) ) : ?>
							<table class="wp-list-table widefat fixed striped">
								<tbody>
									<?php foreach ( $section['items'] as $sitem ) : ?>
										<tr>
											<td><?php echo esc_html( $sitem['title'] ?? $sitem['name'] ?? '' ); ?></td>
											<td><?php echo esc_html( $sitem['value'] ?? $sitem['score'] ?? '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
endif;
?>
<div class="wrap seomelon-wrap">
	<h1 class="wp-heading-inline">
		<span class="seomelon-logo">&#127849;</span>
		<?php esc_html_e( 'SEOMelon Reports', 'seomelon' ); ?>
	</h1>

	<?php if ( empty( $reports ) ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'No reports generated yet. Reports are available on the Growth plan and above. Use the dashboard to scan and generate content, then reports will appear here.', 'seomelon' ); ?>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Report Type', 'seomelon' ); ?></th>
					<th><?php esc_html_e( 'Period', 'seomelon' ); ?></th>
					<th><?php esc_html_e( 'Generated', 'seomelon' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'seomelon' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $reports as $report ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $report['report_type'] ?? '' ) ) ); ?></strong>
						</td>
						<td><?php echo esc_html( $report['period_label'] ?? '&mdash;' ); ?></td>
						<td>
							<?php
							if ( ! empty( $report['created_at'] ) ) {
								echo esc_html( human_time_diff( strtotime( $report['created_at'] ) ) . ' ago' );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seomelon-reports&report_id=' . ( $report['id'] ?? '' ) ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View', 'seomelon' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
