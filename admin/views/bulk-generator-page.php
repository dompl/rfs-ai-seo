<?php
/**
 * Bulk Generator Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap rfs-ai-seo-bulk-generator">
	<h1><?php esc_html_e('Bulk Generate SEO Meta', 'rfs-ai-seo'); ?></h1>

	<!-- Filters -->
	<div class="rfs-ai-seo-bulk-filters">
		<div class="filter-group">
			<label for="rfs_bulk_object_type"><?php esc_html_e('Content Type', 'rfs-ai-seo'); ?></label>
			<select id="rfs_bulk_object_type">
				<option value="product"><?php esc_html_e('Products', 'rfs-ai-seo'); ?></option>
				<option value="product_cat"><?php esc_html_e('Categories', 'rfs-ai-seo'); ?></option>
			</select>
		</div>

		<div class="filter-group" id="rfs_category_filter">
			<label for="rfs_bulk_category"><?php esc_html_e('Category', 'rfs-ai-seo'); ?></label>
			<select id="rfs_bulk_category">
				<option value="0"><?php esc_html_e('All Categories', 'rfs-ai-seo'); ?></option>
				<?php
				$categories = get_terms([
					'taxonomy' => 'product_cat',
					'hide_empty' => false,
				]);
				foreach ($categories as $category) {
					echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
				}
				?>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_bulk_meta_status"><?php esc_html_e('Meta Status', 'rfs-ai-seo'); ?></label>
			<select id="rfs_bulk_meta_status">
				<option value="all"><?php esc_html_e('All Items', 'rfs-ai-seo'); ?></option>
				<option value="missing_both" selected><?php esc_html_e('Missing Both', 'rfs-ai-seo'); ?></option>
				<option value="missing_title"><?php esc_html_e('Missing Title', 'rfs-ai-seo'); ?></option>
				<option value="missing_description"><?php esc_html_e('Missing Description', 'rfs-ai-seo'); ?></option>
				<option value="has_existing"><?php esc_html_e('Has Existing', 'rfs-ai-seo'); ?></option>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_bulk_search"><?php esc_html_e('Search', 'rfs-ai-seo'); ?></label>
			<input type="text" id="rfs_bulk_search" placeholder="<?php esc_attr_e('Search by name...', 'rfs-ai-seo'); ?>">
		</div>

		<div class="filter-group">
			<label>&nbsp;</label>
			<button type="button" class="button button-secondary" id="rfs_bulk_filter_btn">
				<?php esc_html_e('Filter', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>

	<!-- Results Table -->
	<div class="rfs-ai-seo-bulk-results">
		<div class="tablenav top">
			<div class="alignleft actions">
				<input type="checkbox" id="rfs_bulk_select_all">
				<label for="rfs_bulk_select_all"><?php esc_html_e('Select All', 'rfs-ai-seo'); ?></label>
				<span id="rfs_bulk_selected_count" style="margin-left: 10px;"></span>
			</div>
			<div class="alignright actions">
				<button type="button" class="button button-primary" id="rfs_bulk_generate_selected" disabled>
					<?php esc_html_e('Generate for Selected', 'rfs-ai-seo'); ?>
				</button>
				<button type="button" class="button button-secondary" id="rfs_bulk_generate_all" disabled>
					<?php esc_html_e('Generate for All Filtered', 'rfs-ai-seo'); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped rfs-ai-seo-bulk-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="rfs_bulk_select_all_header"></th>
					<th class="column-thumbnail"><?php esc_html_e('Image', 'rfs-ai-seo'); ?></th>
					<th class="column-name"><?php esc_html_e('Name', 'rfs-ai-seo'); ?></th>
					<th class="column-title"><?php esc_html_e('Current Title', 'rfs-ai-seo'); ?></th>
					<th class="column-description"><?php esc_html_e('Current Description', 'rfs-ai-seo'); ?></th>
					<th class="column-status"><?php esc_html_e('Status', 'rfs-ai-seo'); ?></th>
				</tr>
			</thead>
			<tbody id="rfs_bulk_items_tbody">
				<tr>
					<td colspan="6" style="text-align: center; padding: 40px;">
						<?php esc_html_e('Click "Filter" to load items...', 'rfs-ai-seo'); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft">
				<span id="rfs_bulk_total_count"></span>
			</div>
		</div>
	</div>

	<!-- Progress Panel (hidden by default) -->
	<div class="rfs-ai-seo-progress" id="rfs_bulk_progress">
		<div class="rfs-progress-header">
			<div style="display: flex; align-items: center; gap: 12px;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #1d2327;">
					<?php esc_html_e('Generating SEO Meta...', 'rfs-ai-seo'); ?>
				</h2>
			</div>
			<button type="button" class="button button-secondary" id="rfs_bulk_stop_btn">
				<?php esc_html_e('Stop', 'rfs-ai-seo'); ?>
			</button>
		</div>

		<div class="rfs-ai-seo-progress-bar-container">
			<div class="rfs-progress-bar-label">
				<span id="rfs_progress_count">0 / 0 (0%)</span>
			</div>
			<div class="rfs-ai-seo-progress-bar">
				<div class="rfs-ai-seo-progress-bar-fill" id="rfs_progress_bar" style="width: 0%;"></div>
			</div>
		</div>

		<div class="rfs-progress-current-item">
			<strong><?php esc_html_e('Currently processing:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_progress_current"><?php esc_html_e('Initializing...', 'rfs-ai-seo'); ?></span>
		</div>

		<div class="rfs-ai-seo-progress-stats">
			<div class="rfs-stat-card success">
				<div class="rfs-stat-icon">✓</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_progress_success">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Success', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card error">
				<div class="rfs-stat-icon">✗</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_progress_failed">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Failed', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card info">
				<div class="rfs-stat-icon">🎯</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_progress_tokens">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Tokens', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
		</div>

		<div class="rfs-progress-log-container">
			<h3><?php esc_html_e('Activity Log', 'rfs-ai-seo'); ?></h3>
			<div class="rfs-ai-seo-progress-log" id="rfs_progress_log"></div>
		</div>

		<!-- Completion Summary (hidden) -->
		<div class="rfs-ai-seo-completion-summary" id="rfs_completion_summary" style="display: none;">
			<h3><?php esc_html_e('Generation Complete', 'rfs-ai-seo'); ?></h3>
			<p>
				<strong><?php esc_html_e('Total Processed:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_summary_total">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Successful:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_summary_success">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Failed:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_summary_failed">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Total Tokens:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_summary_tokens">0</span>
			</p>
			<button type="button" class="button button-primary" id="rfs_bulk_close_btn">
				<?php esc_html_e('Close', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>
</div>

<style>
.rfs-ai-seo-bulk-table .column-thumbnail {
	width: 60px;
}
.rfs-ai-seo-bulk-table .column-status {
	width: 100px;
	text-align: center;
}
.rfs-ai-seo-bulk-table .column-title,
.rfs-ai-seo-bulk-table .column-description {
	width: 250px;
}
.rfs-ai-seo-bulk-table img {
	width: 40px;
	height: 40px;
	object-fit: cover;
	border-radius: 4px;
}
.rfs-ai-seo-status {
	display: inline-flex;
	gap: 3px;
	align-items: center;
}
.rfs-ai-seo-status-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	background: #c3c4c7;
}
.rfs-ai-seo-status-dot.filled {
	background: #00a32a;
}
.rfs-ai-seo-log-entry {
	padding: 5px 0;
	border-bottom: 1px solid #f0f0f0;
}
.rfs-ai-seo-log-entry.success {
	color: #00a32a;
}
.rfs-ai-seo-log-entry.error {
	color: #d63638;
}
</style>
