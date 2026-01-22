<?php
/**
 * Bulk Short Description Generator Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap rfs-ai-seo-bulk-generator">
	<h1><?php esc_html_e('Bulk Generate Product Short Descriptions', 'rfs-ai-seo'); ?></h1>

	<div class="rfs-bulk-intro" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0; border-radius: 3px;">
		<p style="margin: 0;">
			<?php esc_html_e('Generate AI-powered short product descriptions for multiple products at once. Short descriptions are concise summaries (20-50 words) that appear in product listings and catalog views.', 'rfs-ai-seo'); ?>
		</p>
	</div>

	<!-- Filters -->
	<div class="rfs-ai-seo-bulk-filters">
		<div class="filter-group">
			<label for="rfs_short_desc_bulk_category"><?php esc_html_e('Category', 'rfs-ai-seo'); ?></label>
			<select id="rfs_short_desc_bulk_category">
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
			<label for="rfs_short_desc_bulk_status"><?php esc_html_e('Description Status', 'rfs-ai-seo'); ?></label>
			<select id="rfs_short_desc_bulk_status">
				<option value="all"><?php esc_html_e('All Products', 'rfs-ai-seo'); ?></option>
				<option value="empty" selected><?php esc_html_e('Empty Short Descriptions', 'rfs-ai-seo'); ?></option>
				<option value="has_description"><?php esc_html_e('Has Short Description', 'rfs-ai-seo'); ?></option>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_short_desc_bulk_search"><?php esc_html_e('Search', 'rfs-ai-seo'); ?></label>
			<input type="text" id="rfs_short_desc_bulk_search" placeholder="<?php esc_attr_e('Search by name...', 'rfs-ai-seo'); ?>">
		</div>

		<div class="filter-group">
			<label>&nbsp;</label>
			<button type="button" class="button button-secondary" id="rfs_short_desc_bulk_filter_btn">
				<?php esc_html_e('Filter', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>

	<!-- Results Table -->
	<div class="rfs-ai-seo-bulk-results">
		<div class="tablenav top">
			<div class="alignleft actions">
				<input type="checkbox" id="rfs_short_desc_bulk_select_all">
				<label for="rfs_short_desc_bulk_select_all"><?php esc_html_e('Select All', 'rfs-ai-seo'); ?></label>
				<span id="rfs_short_desc_bulk_selected_count" style="margin-left: 10px;"></span>
			</div>
			<div class="alignright actions">
				<button type="button" class="button button-primary" id="rfs_short_desc_bulk_generate_selected" disabled>
					<?php esc_html_e('Generate for Selected', 'rfs-ai-seo'); ?>
				</button>
				<button type="button" class="button button-secondary" id="rfs_short_desc_bulk_generate_all" disabled>
					<?php esc_html_e('Generate for All Filtered', 'rfs-ai-seo'); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped rfs-ai-seo-bulk-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="rfs_short_desc_bulk_select_all_header"></th>
					<th class="column-thumbnail"><?php esc_html_e('Image', 'rfs-ai-seo'); ?></th>
					<th class="column-name"><?php esc_html_e('Product Name', 'rfs-ai-seo'); ?></th>
					<th class="column-description"><?php esc_html_e('Current Short Description', 'rfs-ai-seo'); ?></th>
					<th class="column-status"><?php esc_html_e('Status', 'rfs-ai-seo'); ?></th>
				</tr>
			</thead>
			<tbody id="rfs_short_desc_bulk_items_tbody">
				<tr>
					<td colspan="5" style="text-align: center; padding: 40px;">
						<?php esc_html_e('Click "Filter" to load products...', 'rfs-ai-seo'); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft">
				<span id="rfs_short_desc_bulk_total_count"></span>
			</div>
		</div>
	</div>

	<!-- Progress Panel (hidden by default) -->
	<div class="rfs-ai-seo-progress" id="rfs_short_desc_bulk_progress" style="display: none;">
		<div class="rfs-progress-header">
			<div style="display: flex; align-items: center; gap: 12px;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #1d2327;">
					<?php esc_html_e('Generating Short Descriptions...', 'rfs-ai-seo'); ?>
				</h2>
			</div>
			<button type="button" class="button button-secondary" id="rfs_short_desc_bulk_stop_btn">
				<?php esc_html_e('Stop', 'rfs-ai-seo'); ?>
			</button>
		</div>

		<div class="rfs-ai-seo-progress-bar-container">
			<div class="rfs-progress-bar-label">
				<span id="rfs_short_desc_progress_count">0 / 0 (0%)</span>
			</div>
			<div class="rfs-ai-seo-progress-bar">
				<div class="rfs-ai-seo-progress-bar-fill" id="rfs_short_desc_progress_bar" style="width: 0%;"></div>
			</div>
		</div>

		<div class="rfs-progress-current-item">
			<strong><?php esc_html_e('Currently processing:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_short_desc_progress_current"><?php esc_html_e('Initializing...', 'rfs-ai-seo'); ?></span>
		</div>

		<div class="rfs-ai-seo-progress-stats">
			<div class="rfs-stat-card success">
				<div class="rfs-stat-icon">&#10003;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_short_desc_progress_success">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Success', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card error">
				<div class="rfs-stat-icon">&#10005;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_short_desc_progress_failed">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Failed', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card info">
				<div class="rfs-stat-icon">&#9873;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_short_desc_progress_tokens">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Tokens', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
		</div>

		<div class="rfs-progress-log-container">
			<h3><?php esc_html_e('Activity Log', 'rfs-ai-seo'); ?></h3>
			<div class="rfs-ai-seo-progress-log" id="rfs_short_desc_progress_log"></div>
		</div>

		<!-- Completion Summary (hidden) -->
		<div class="rfs-ai-seo-completion-summary" id="rfs_short_desc_completion_summary" style="display: none;">
			<h3><?php esc_html_e('Generation Complete', 'rfs-ai-seo'); ?></h3>
			<p>
				<strong><?php esc_html_e('Total Processed:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_short_desc_summary_total">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Successful:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_short_desc_summary_success">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Failed:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_short_desc_summary_failed">0</span>
			</p>
			<p>
				<strong><?php esc_html_e('Total Tokens:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_short_desc_summary_tokens">0</span>
			</p>
			<button type="button" class="button button-primary" id="rfs_short_desc_bulk_close_btn">
				<?php esc_html_e('Close', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>
</div>
