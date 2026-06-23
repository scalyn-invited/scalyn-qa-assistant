<?php
/**
 * Template: QA Report (print-to-PDF).
 *
 * Self-contained HTML document designed for browser printing.
 * All styles are embedded so the output is fully portable.
 *
 * @package Scalyn\QA\Templates
 * @since   1.4.0
 */

defined( 'ABSPATH' ) || exit;

$site_name       = $site_name ?? '';
$site_url        = $site_url ?? '';
$wp_version      = $wp_version ?? '';
$php_version     = $php_version ?? '';
$plugin_version  = $plugin_version ?? '';
$generated_at    = $generated_at ?? '';
$generated_by    = $generated_by ?? '';
$project_scores  = $project_scores ?? array();
$scan_coverage   = $scan_coverage ?? array();
$all_pages       = $all_pages ?? array();
$top_issues      = $top_issues ?? array();
$launch_results  = $launch_results ?? array();
$launch_summary  = $launch_summary ?? array();
$seo_plugin      = $seo_plugin ?? null;
$logo_data_uri   = $logo_data_uri ?? '';

$overall      = (int) ( $project_scores['overall'] ?? 0 );
$seo_ready    = (int) ( $project_scores['seo_ready'] ?? 0 );
$qa_ready     = (int) ( $project_scores['qa_ready'] ?? 0 );
$launch_ready = (int) ( $project_scores['launch_ready'] ?? 0 );

$scanned = (int) ( $scan_coverage['scanned'] ?? 0 );
$total   = (int) ( $scan_coverage['total'] ?? 0 );

// Score distribution.
$green_count  = 0;
$yellow_count = 0;
$red_count    = 0;
foreach ( $all_pages as $page ) {
	match ( $page['status'] ) {
		'green'  => ++$green_count,
		'yellow' => ++$yellow_count,
		default  => ++$red_count,
	};
}

/**
 * Get CSS color for a status.
 */
function scalyn_report_status_color( string $status ): string {
	return match ( $status ) {
		'green', 'pass' => '#10B981',
		'yellow', 'warning' => '#F59E0B',
		default => '#EF4444',
	};
}

/**
 * Get label for a status.
 */
function scalyn_report_status_label( string $status ): string {
	return match ( $status ) {
		'green', 'pass' => 'Pass',
		'yellow', 'warning' => 'Warning',
		default => 'Fail',
	};
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php printf( '%s - QA Report - %s', esc_html( $site_name ), esc_html( $generated_at ) ); ?></title>
<style>
/* Reset & Base */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
	font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, sans-serif;
	font-size: 11px;
	line-height: 1.5;
	color: #374151;
	background: #fff;
	padding: 0;
}

/* Print Controls */
.report-actions {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	background: #0f172a;
	color: #fff;
	padding: 0.75rem 1.5rem;
	display: flex;
	align-items: center;
	justify-content: space-between;
	z-index: 1000;
	font-size: 13px;
}
.report-actions__label { font-weight: 600; }
.report-actions__buttons { display: flex; gap: 0.5rem; }
.report-actions button {
	padding: 0.5rem 1.25rem;
	border: none;
	border-radius: 6px;
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
}
.btn-print { background: #4F46E5; color: #fff; }
.btn-print:hover { background: #4338CA; }
.btn-close { background: #374151; color: #fff; }
.btn-close:hover { background: #1f2937; }

/* Report container */
.report { max-width: 900px; margin: 60px auto 2rem; padding: 0 1.5rem; }

/* Header */
.report-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 1.5rem 0;
	border-bottom: 2px solid #4F46E5;
	margin-bottom: 1.5rem;
}
.report-header__brand { display: flex; align-items: center; gap: 0.75rem; }
.report-header__logo { height: 40px; width: auto; }
.report-header__info { text-align: right; font-size: 10px; color: #64748b; line-height: 1.6; }
.report-header__info strong { color: #0f172a; font-size: 11px; }

/* Section */
.report-section { margin-bottom: 1.75rem; }
.report-section__title {
	font-size: 14px;
	font-weight: 700;
	color: #0f172a;
	padding-bottom: 0.375rem;
	border-bottom: 1px solid #e5e7eb;
	margin-bottom: 0.75rem;
}

/* Score Cards */
.score-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem; }
.score-card {
	text-align: center;
	padding: 1rem;
	border-radius: 8px;
	border: 1px solid #e5e7eb;
}
.score-card--overall { border-color: #4F46E5; background: #f5f3ff; }
.score-card__value {
	font-size: 28px;
	font-weight: 800;
	line-height: 1.2;
}
.score-card__value--green { color: #10B981; }
.score-card__value--yellow { color: #F59E0B; }
.score-card__value--red { color: #EF4444; }
.score-card__label {
	font-size: 10px;
	font-weight: 600;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-top: 0.25rem;
}

/* Stats Row */
.stats-row { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.stat-item {
	flex: 1;
	padding: 0.625rem 0.875rem;
	background: #f9fafb;
	border-radius: 6px;
	border: 1px solid #e5e7eb;
	font-size: 10px;
}
.stat-item__value { font-size: 16px; font-weight: 700; color: #0f172a; }
.stat-item__label { color: #64748b; }

/* Score Distribution */
.distribution-bar {
	display: flex;
	height: 10px;
	border-radius: 999px;
	overflow: hidden;
	background: #f3f4f6;
	margin: 0.5rem 0;
}
.distribution-bar__segment { height: 100%; transition: width 0.3s; }
.distribution-legend { display: flex; gap: 1rem; font-size: 10px; color: #64748b; }
.distribution-legend span::before {
	content: '';
	display: inline-block;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	margin-right: 4px;
	vertical-align: middle;
}
.legend-green::before { background: #10B981; }
.legend-yellow::before { background: #F59E0B; }
.legend-red::before { background: #EF4444; }

/* Tables */
table {
	width: 100%;
	border-collapse: collapse;
	font-size: 10px;
}
th {
	background: #f9fafb;
	font-weight: 600;
	color: #374151;
	text-align: left;
	padding: 0.5rem 0.625rem;
	border-bottom: 2px solid #e5e7eb;
	font-size: 9px;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}
td {
	padding: 0.4375rem 0.625rem;
	border-bottom: 1px solid #f3f4f6;
	vertical-align: top;
}
tr:nth-child(even) { background: #fafbfc; }

/* Status Badge */
.status-badge {
	display: inline-flex;
	align-items: center;
	padding: 0.125rem 0.5rem;
	border-radius: 999px;
	font-size: 9px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}
.status-badge--pass    { background: #ecfdf5; color: #059669; }
.status-badge--warning { background: #fffbeb; color: #d97706; }
.status-badge--fail    { background: #fef2f2; color: #dc2626; }
.status-badge--green   { background: #ecfdf5; color: #059669; }
.status-badge--yellow  { background: #fffbeb; color: #d97706; }
.status-badge--red     { background: #fef2f2; color: #dc2626; }

/* Score inline */
.score-inline {
	font-weight: 700;
	font-size: 11px;
}

/* Category badge */
.cat-badge {
	display: inline-block;
	padding: 0.0625rem 0.375rem;
	border-radius: 4px;
	font-size: 9px;
	font-weight: 600;
	background: #f3f4f6;
	color: #6b7280;
	text-transform: capitalize;
}

/* Footer */
.report-footer {
	margin-top: 2rem;
	padding-top: 0.75rem;
	border-top: 1px solid #e5e7eb;
	text-align: center;
	font-size: 9px;
	color: #9ca3af;
}

/* Truncate long URLs */
.url-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #6b7280; font-size: 9px; }

/* Print overrides */
@media print {
	body { padding: 0; font-size: 10px; }
	.report-actions { display: none !important; }
	.report { margin: 0; padding: 0; max-width: 100%; }
	.report-header { padding-top: 0; }
	.score-card, .stat-item, .status-badge, .distribution-bar__segment {
		-webkit-print-color-adjust: exact;
		print-color-adjust: exact;
	}
	table { page-break-inside: auto; }
	tr { page-break-inside: avoid; }
	.page-break { page-break-before: always; }
	.report-section { page-break-inside: avoid; }
}
</style>
</head>
<body>

<!-- Print Controls (hidden when printing) -->
<div class="report-actions">
	<span class="report-actions__label">Scalyn QA Report</span>
	<div class="report-actions__buttons">
		<button class="btn-print" onclick="window.print()">Save as PDF</button>
		<button class="btn-close" onclick="window.close()">Close</button>
	</div>
</div>

<div class="report">

	<!-- Header -->
	<div class="report-header">
		<div class="report-header__brand">
			<?php if ( '' !== $logo_data_uri ) : ?>
				<img src="<?php echo esc_attr( $logo_data_uri ); ?>" alt="Scalyn QA" class="report-header__logo">
			<?php else : ?>
				<strong style="font-size: 18px; color: #4F46E5;">Scalyn QA</strong>
			<?php endif; ?>
		</div>
		<div class="report-header__info">
			<strong><?php echo esc_html( $site_name ); ?></strong><br>
			<?php echo esc_html( $site_url ); ?><br>
			<?php
			printf(
				'Generated: %s by %s',
				esc_html( $generated_at ),
				esc_html( $generated_by ),
			);
			?>
		</div>
	</div>

	<!-- Section 1: Executive Summary -->
	<div class="report-section">
		<h2 class="report-section__title">Executive Summary</h2>

		<div class="score-grid">
			<div class="score-card score-card--overall">
				<div class="score-card__value" style="color: <?php echo esc_attr( scalyn_report_status_color( $overall >= 80 ? 'green' : ( $overall >= 50 ? 'yellow' : 'red' ) ) ); ?>;">
					<?php echo esc_html( (string) $overall ); ?>%
				</div>
				<div class="score-card__label">Overall Score</div>
			</div>
			<div class="score-card">
				<div class="score-card__value" style="color: <?php echo esc_attr( scalyn_report_status_color( $seo_ready >= 80 ? 'green' : ( $seo_ready >= 50 ? 'yellow' : 'red' ) ) ); ?>;">
					<?php echo esc_html( (string) $seo_ready ); ?>%
				</div>
				<div class="score-card__label">SEO Ready</div>
			</div>
			<div class="score-card">
				<div class="score-card__value" style="color: <?php echo esc_attr( scalyn_report_status_color( $qa_ready >= 80 ? 'green' : ( $qa_ready >= 50 ? 'yellow' : 'red' ) ) ); ?>;">
					<?php echo esc_html( (string) $qa_ready ); ?>%
				</div>
				<div class="score-card__label">QA Ready</div>
			</div>
			<div class="score-card">
				<div class="score-card__value" style="color: <?php echo esc_attr( scalyn_report_status_color( $launch_ready >= 80 ? 'green' : ( $launch_ready >= 50 ? 'yellow' : 'red' ) ) ); ?>;">
					<?php echo esc_html( (string) $launch_ready ); ?>%
				</div>
				<div class="score-card__label">Launch Ready</div>
			</div>
		</div>

		<div class="stats-row">
			<div class="stat-item">
				<div class="stat-item__value"><?php echo esc_html( (string) $scanned ); ?> / <?php echo esc_html( (string) $total ); ?></div>
				<div class="stat-item__label">Pages Scanned</div>
			</div>
			<div class="stat-item">
				<div class="stat-item__value"><?php echo esc_html( $seo_plugin ?? 'None' ); ?></div>
				<div class="stat-item__label">SEO Plugin</div>
			</div>
			<div class="stat-item">
				<div class="stat-item__value">WordPress <?php echo esc_html( $wp_version ); ?></div>
				<div class="stat-item__label">Platform</div>
			</div>
			<div class="stat-item">
				<div class="stat-item__value">PHP <?php echo esc_html( $php_version ); ?></div>
				<div class="stat-item__label">Server</div>
			</div>
		</div>

		<?php if ( ! empty( $all_pages ) ) : ?>
		<div style="margin-top: 0.5rem;">
			<?php
			$total_pages = count( $all_pages );
			$green_pct   = $total_pages > 0 ? round( ( $green_count / $total_pages ) * 100 ) : 0;
			$yellow_pct  = $total_pages > 0 ? round( ( $yellow_count / $total_pages ) * 100 ) : 0;
			$red_pct     = $total_pages > 0 ? round( ( $red_count / $total_pages ) * 100 ) : 0;
			?>
			<div class="distribution-bar">
				<?php if ( $green_pct > 0 ) : ?><div class="distribution-bar__segment" style="width:<?php echo esc_attr( (string) $green_pct ); ?>%;background:#10B981;"></div><?php endif; ?>
				<?php if ( $yellow_pct > 0 ) : ?><div class="distribution-bar__segment" style="width:<?php echo esc_attr( (string) $yellow_pct ); ?>%;background:#F59E0B;"></div><?php endif; ?>
				<?php if ( $red_pct > 0 ) : ?><div class="distribution-bar__segment" style="width:<?php echo esc_attr( (string) $red_pct ); ?>%;background:#EF4444;"></div><?php endif; ?>
			</div>
			<div class="distribution-legend">
				<span class="legend-green"><?php echo esc_html( (string) $green_count ); ?> Passing</span>
				<span class="legend-yellow"><?php echo esc_html( (string) $yellow_count ); ?> Needs Review</span>
				<span class="legend-red"><?php echo esc_html( (string) $red_count ); ?> Failing</span>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $top_issues ) ) : ?>
	<!-- Section 2: Top Issues -->
	<div class="report-section">
		<h2 class="report-section__title">Top Issues</h2>
		<table>
			<thead>
				<tr>
					<th style="width: 40%;">Issue</th>
					<th>Category</th>
					<th style="text-align: center;">Pages Affected</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_issues as $issue ) : ?>
				<tr>
					<td><?php echo esc_html( $issue['label'] ); ?></td>
					<td><span class="cat-badge"><?php echo esc_html( $issue['category'] ); ?></span></td>
					<td style="text-align: center; font-weight: 600;"><?php echo esc_html( (string) $issue['count'] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $all_pages ) ) : ?>
	<!-- Section 3: Page Scores -->
	<div class="report-section">
		<h2 class="report-section__title">Page Scores (<?php echo esc_html( (string) count( $all_pages ) ); ?> pages)</h2>
		<table>
			<thead>
				<tr>
					<th style="width: 35%;">Page</th>
					<th style="text-align: center;">Overall</th>
					<th style="text-align: center;">SEO</th>
					<th style="text-align: center;">Content</th>
					<th style="text-align: center;">Functionality</th>
					<th style="text-align: center;">Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_pages as $page ) : ?>
				<tr>
					<td>
						<?php echo esc_html( $page['title'] ); ?>
						<div class="url-cell"><?php echo esc_html( $page['url'] ); ?></div>
					</td>
					<td style="text-align: center;">
						<span class="score-inline" style="color: <?php echo esc_attr( scalyn_report_status_color( $page['status'] ) ); ?>;">
							<?php echo esc_html( (string) $page['overall'] ); ?>
						</span>
					</td>
					<td style="text-align: center;"><?php echo esc_html( (string) $page['seo'] ); ?></td>
					<td style="text-align: center;"><?php echo esc_html( (string) $page['content'] ); ?></td>
					<td style="text-align: center;"><?php echo esc_html( (string) $page['functionality'] ); ?></td>
					<td style="text-align: center;">
						<span class="status-badge status-badge--<?php echo esc_attr( $page['status'] ); ?>">
							<?php echo esc_html( scalyn_report_status_label( $page['status'] ) ); ?>
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $launch_results ) ) : ?>
	<!-- Section 4: Launch Checklist -->
	<div class="report-section page-break">
		<h2 class="report-section__title">
			Launch Checklist
			<span style="font-weight: 400; font-size: 11px; color: #64748b; margin-left: 0.5rem;">
				<?php
				printf(
					'%d passed, %d failed, %d warnings',
					(int) ( $launch_summary['pass'] ?? 0 ),
					(int) ( $launch_summary['fail'] ?? 0 ),
					(int) ( $launch_summary['warning'] ?? 0 ),
				);
				?>
			</span>
		</h2>
		<table>
			<thead>
				<tr>
					<th style="width: 5%; text-align: center;">Status</th>
					<th style="width: 25%;">Check</th>
					<th>Details</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $launch_results as $item ) : ?>
				<tr>
					<td style="text-align: center;">
						<span class="status-badge status-badge--<?php echo esc_attr( $item['status'] ); ?>">
							<?php echo esc_html( scalyn_report_status_label( $item['status'] ) ); ?>
						</span>
					</td>
					<td style="font-weight: 600;"><?php echo esc_html( $item['label'] ); ?></td>
					<td style="color: #64748b;"><?php echo esc_html( $item['message'] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Footer -->
	<div class="report-footer">
		<?php
		printf(
			'Generated by Scalyn QA v%s | %s | %s',
			esc_html( $plugin_version ),
			esc_html( $generated_at ),
			esc_html( $site_url ),
		);
		?>
	</div>

</div>

</body>
</html>
