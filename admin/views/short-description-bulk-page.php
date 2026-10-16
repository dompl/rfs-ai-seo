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

	<?php
	$settings = new \RFS_AI_SEO\Settings();
	$estimate = (new \RFS_AI_SEO\Usage_Logger())->average_charge('product_short_description');
	$connected = \RedFrogStudio\AtsAiPortal\Portal::has_key('content');
	do_action('ats_ai_portal_credits_bar');
	?>
	<div class="rfs-bulk-controls" style="display:flex;gap:16px;flex-wrap:wrap;align-items:end;margin:12px 0">
		<label><?php esc_html_e('Tone', 'rfs-ai-seo'); ?><br>
			<select id="rfs_short_desc_bulk_tone">
				<?php foreach (\RFS_AI_SEO\Settings::tones() as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($settings->get_content_tone(), $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e('Sentences', 'rfs-ai-seo'); ?><br>
			<input type="number" id="rfs_short_desc_bulk_sentences" min="1" max="6" step="1" value="<?php echo esc_attr((string) $settings->get_short_sentences()); ?>" style="width:90px">
		</label>
		<label><input type="checkbox" id="rfs_short_desc_bulk_skip_existing" checked> <?php esc_html_e('Skip products that already have a short description', 'rfs-ai-seo'); ?></label>
		<span id="rfs_short_desc_bulk_estimate" class="description">
			<?php if ($estimate) : ?>
				<?php printf(esc_html__('About %s per product, based on your last 20', 'rfs-ai-seo'), esc_html($settings->format_gbp($estimate))); ?>
			<?php endif; ?>
		</span>
	</div>
	<div id="rfs_short_desc_bulk_out_of_credit" class="notice notice-error inline" style="display:none"><p>
		<?php esc_html_e('Out of AI credit. The run has stopped.', 'rfs-ai-seo'); ?>
		<a class="button button-primary" href="<?php echo esc_url(\RedFrogStudio\AtsAiPortal\Portal::credits_url()); ?>"><?php esc_html_e('Top up', 'rfs-ai-seo'); ?></a>
	</p></div>
	<div id="rfs_short_desc_bulk_paused" class="notice notice-warning inline" style="display:none"><p>
		<?php esc_html_e('AI service unavailable. The run is paused.', 'rfs-ai-seo'); ?>
		<button type="button" class="button" id="rfs_short_desc_bulk_resume_btn"><?php esc_html_e('Resume', 'rfs-ai-seo'); ?></button>
	</p></div>

	<div class="rfs-info-box">
		<p>
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
				<button type="button" class="button button-primary" id="rfs_short_desc_bulk_generate_selected" disabled <?php disabled(!$connected); ?>>
					<?php esc_html_e('Generate for Selected', 'rfs-ai-seo'); ?>
				</button>
				<button type="button" class="button button-secondary" id="rfs_short_desc_bulk_generate_all" disabled <?php disabled(!$connected); ?>>
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
					<td colspan="5" class="rfs-empty-state">
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
			<span id="rfs_short_desc_bulk_total" class="description" style="margin-left:8px"></span>
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
			<div class="rfs-stat-card rfs-stat-success">
				<div class="rfs-stat-icon">&#10003;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_short_desc_progress_success">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Success', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-error">
				<div class="rfs-stat-icon">&#10005;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_short_desc_progress_failed">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Failed', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-info">
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
				<strong><?php esc_html_e('Skipped:', 'rfs-ai-seo'); ?></strong>
				<span id="rfs_short_desc_summary_skipped">0</span>
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
