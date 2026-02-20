<?php
/**
 * Bulk Alt Text Generator Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap rfs-ai-seo-bulk-generator">
	<h1><?php esc_html_e('Bulk Generate Image Alt Text', 'rfs-ai-seo'); ?></h1>

	<div class="rfs-info-box">
		<p>
			<?php esc_html_e('Generate AI-powered alt text for product images. The AI uses product context (name, description, categories) to create descriptive, SEO-friendly alt text for accessibility compliance.', 'rfs-ai-seo'); ?>
		</p>
	</div>

	<!-- Filters -->
	<div class="rfs-ai-seo-bulk-filters">
		<div class="filter-group">
			<label for="rfs_alt_bulk_category"><?php esc_html_e('Category', 'rfs-ai-seo'); ?></label>
			<select id="rfs_alt_bulk_category">
				<option value="0"><?php esc_html_e('All Categories', 'rfs-ai-seo'); ?></option>
				<?php
				$categories = get_terms([
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				]);
				foreach ($categories as $category) {
					echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
				}
				?>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_alt_bulk_status"><?php esc_html_e('Alt Text Status', 'rfs-ai-seo'); ?></label>
			<select id="rfs_alt_bulk_status">
				<option value="all"><?php esc_html_e('All Images', 'rfs-ai-seo'); ?></option>
				<option value="missing_alt" selected><?php esc_html_e('Missing Alt Text', 'rfs-ai-seo'); ?></option>
				<option value="has_alt"><?php esc_html_e('Has Alt Text', 'rfs-ai-seo'); ?></option>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_alt_bulk_search"><?php esc_html_e('Search', 'rfs-ai-seo'); ?></label>
			<input type="text" id="rfs_alt_bulk_search" placeholder="<?php esc_attr_e('Search by product name...', 'rfs-ai-seo'); ?>">
		</div>

		<div class="filter-group">
			<label>&nbsp;</label>
			<button type="button" class="button button-secondary" id="rfs_alt_bulk_filter_btn">
				<?php esc_html_e('Filter', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>

	<!-- Results Table -->
	<div class="rfs-ai-seo-bulk-results">
		<div class="tablenav top">
			<div class="alignleft actions">
				<input type="checkbox" id="rfs_alt_bulk_select_all">
				<label for="rfs_alt_bulk_select_all"><?php esc_html_e('Select All', 'rfs-ai-seo'); ?></label>
				<span id="rfs_alt_bulk_selected_count" style="margin-left: 10px;"></span>
			</div>
			<div class="alignright actions" style="display: flex; align-items: center; gap: 16px;">
				<label style="display: inline-flex; align-items: center; gap: 4px; font-size: 13px;">
					<input type="checkbox" id="rfs_alt_bulk_overwrite">
					<?php esc_html_e('Overwrite if exists', 'rfs-ai-seo'); ?>
				</label>
				<button type="button" class="button button-primary" id="rfs_alt_bulk_generate_selected" disabled>
					<?php esc_html_e('Generate for Selected', 'rfs-ai-seo'); ?>
				</button>
				<button type="button" class="button button-secondary" id="rfs_alt_bulk_generate_all" disabled>
					<?php esc_html_e('Generate for All Filtered', 'rfs-ai-seo'); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped rfs-ai-seo-bulk-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="rfs_alt_bulk_select_all_header"></th>
					<th class="column-thumbnail"><?php esc_html_e('Image', 'rfs-ai-seo'); ?></th>
					<th class="column-name"><?php esc_html_e('Product Name', 'rfs-ai-seo'); ?></th>
					<th class="column-type"><?php esc_html_e('Type', 'rfs-ai-seo'); ?></th>
					<th class="column-alt-text"><?php esc_html_e('Current Alt Text', 'rfs-ai-seo'); ?></th>
					<th class="column-status"><?php esc_html_e('Status', 'rfs-ai-seo'); ?></th>
				</tr>
			</thead>
			<tbody id="rfs_alt_bulk_items_tbody">
				<tr>
					<td colspan="6" class="rfs-empty-state">
						<?php esc_html_e('Click "Filter" to load images...', 'rfs-ai-seo'); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft">
				<span id="rfs_alt_bulk_total_count"></span>
			</div>
		</div>
	</div>

	<!-- Progress Panel (hidden by default) -->
	<div class="rfs-ai-seo-progress" id="rfs_alt_bulk_progress" style="display: none;">
		<div class="rfs-progress-header">
			<div style="display: flex; align-items: center; gap: 12px;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #1d2327;">
					<?php esc_html_e('Generating Alt Text...', 'rfs-ai-seo'); ?>
				</h2>
			</div>
			<button type="button" class="button button-secondary" id="rfs_alt_bulk_stop_btn">
				<?php esc_html_e('Stop', 'rfs-ai-seo'); ?>
			</button>
		</div>

		<div class="rfs-ai-seo-progress-bar-container">
			<div class="rfs-progress-bar-label">
				<span id="rfs_alt_progress_count">0 / 0 (0%)</span>
			</div>
			<div class="rfs-ai-seo-progress-bar">
				<div class="rfs-ai-seo-progress-bar-fill" id="rfs_alt_progress_bar" style="width: 0%;"></div>
			</div>
		</div>

		<div class="rfs-progress-current-item">
			<strong><?php esc_html_e('Currently processing:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_alt_progress_current"><?php esc_html_e('Initializing...', 'rfs-ai-seo'); ?></span>
		</div>

		<div class="rfs-ai-seo-progress-stats">
			<div class="rfs-stat-card rfs-stat-success">
				<div class="rfs-stat-icon">&#10003;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_alt_progress_success">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Success', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-error">
				<div class="rfs-stat-icon">&#10005;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_alt_progress_failed">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Failed', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-info">
				<div class="rfs-stat-icon">&#9873;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_alt_progress_tokens">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Tokens', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
		</div>

		<div class="rfs-progress-log-container">
			<h3><?php esc_html_e('Activity Log', 'rfs-ai-seo'); ?></h3>
			<div class="rfs-ai-seo-progress-log" id="rfs_alt_progress_log"></div>
		</div>

		<!-- Completion Summary (hidden) -->
		<div class="rfs-ai-seo-completion-summary" id="rfs_alt_completion_summary" style="display: none;">
			<h3><?php esc_html_e('Generation Complete', 'rfs-ai-seo'); ?></h3>
			<p>
				<strong><?php esc_html_e('Total Images Processed:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_alt_summary_total">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Successful:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_alt_summary_success">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Failed:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_alt_summary_failed">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Total Tokens:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_alt_summary_tokens">0</span>
			</p>
			<button type="button" class="button button-primary" id="rfs_alt_bulk_close_btn">
				<?php esc_html_e('Close', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>
</div>
