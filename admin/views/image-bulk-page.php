<?php
/**
 * Bulk Image Generator Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

$settings = new \RFS_AI_SEO\Settings();
$image_prompts = $settings->get_image_prompts();
$aspect_ratio = '1:1';
$cost_estimate = (new \RFS_AI_SEO\Usage_Logger())->average_charge('image');
?>

<div class="wrap rfs-ai-seo-bulk-generator">
	<h1><?php esc_html_e('Bulk Generate Product Images', 'rfs-ai-seo'); ?></h1>

	<div class="rfs-info-box">
		<p>
			<?php esc_html_e('Generate AI-powered product images. Select individual images, choose a prompt template, and the AI will regenerate each image. You can compare, regenerate for a different version, or replace the original.', 'rfs-ai-seo'); ?>
		</p>
	</div>

	<!-- Filters -->
	<div class="rfs-ai-seo-bulk-filters">
		<div class="filter-group">
			<label for="rfs_image_bulk_category"><?php esc_html_e('Category', 'rfs-ai-seo'); ?></label>
			<select id="rfs_image_bulk_category">
				<option value="0"><?php esc_html_e('All Categories', 'rfs-ai-seo'); ?></option>
				<?php
				$categories = get_terms([
					'taxonomy' => 'product_cat',
					'hide_empty' => false,
				]);
				if (!is_wp_error($categories) && !empty($categories)) {
					foreach ($categories as $category) {
						echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
					}
				}
				?>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_image_bulk_status"><?php esc_html_e('Image Status', 'rfs-ai-seo'); ?></label>
			<select id="rfs_image_bulk_status">
				<option value="all"><?php esc_html_e('All Products', 'rfs-ai-seo'); ?></option>
				<option value="has_images" selected><?php esc_html_e('Has Images', 'rfs-ai-seo'); ?></option>
				<option value="no_images"><?php esc_html_e('No Images', 'rfs-ai-seo'); ?></option>
			</select>
		</div>

		<div class="filter-group">
			<label for="rfs_image_bulk_search"><?php esc_html_e('Search', 'rfs-ai-seo'); ?></label>
			<input type="text" id="rfs_image_bulk_search" placeholder="<?php esc_attr_e('Search by name...', 'rfs-ai-seo'); ?>">
		</div>

		<div class="filter-group">
			<label>&nbsp;</label>
			<button type="button" class="button button-secondary" id="rfs_image_bulk_filter_btn">
				<?php esc_html_e('Filter', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>

	<!-- Image Generation Settings -->
	<div class="rfs-ai-seo-image-settings" id="rfs_image_bulk_settings" style="display: none;">
		<h3><?php esc_html_e('Generation Settings', 'rfs-ai-seo'); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rfs_image_bulk_prompt"><?php esc_html_e('Prompt Template', 'rfs-ai-seo'); ?></label>
				</th>
				<td>
					<select id="rfs_image_bulk_prompt">
						<option value=""><?php esc_html_e('-- Use Custom Prompt --', 'rfs-ai-seo'); ?></option>
						<?php foreach ($image_prompts as $index => $prompt) : ?>
							<option value="<?php echo esc_attr($index); ?>">
								<?php echo esc_html($prompt['title']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rfs_image_bulk_custom_prompt"><?php esc_html_e('Custom Prompt', 'rfs-ai-seo'); ?></label>
				</th>
				<td>
					<textarea id="rfs_image_bulk_custom_prompt" rows="3" class="large-text" placeholder="<?php esc_attr_e('Describe how you want the images to be regenerated...', 'rfs-ai-seo'); ?>"></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rfs_image_bulk_aspect_ratio"><?php esc_html_e('Aspect Ratio', 'rfs-ai-seo'); ?></label>
				</th>
				<td>
					<select id="rfs_image_bulk_aspect_ratio">
						<option value="1:1" <?php selected($aspect_ratio, '1:1'); ?>><?php esc_html_e('1:1 (Square)', 'rfs-ai-seo'); ?></option>
						<option value="16:9" <?php selected($aspect_ratio, '16:9'); ?>><?php esc_html_e('16:9 (Landscape)', 'rfs-ai-seo'); ?></option>
						<option value="9:16" <?php selected($aspect_ratio, '9:16'); ?>><?php esc_html_e('9:16 (Portrait)', 'rfs-ai-seo'); ?></option>
						<option value="4:3" <?php selected($aspect_ratio, '4:3'); ?>><?php esc_html_e('4:3 (Standard)', 'rfs-ai-seo'); ?></option>
						<option value="3:4" <?php selected($aspect_ratio, '3:4'); ?>><?php esc_html_e('3:4 (Portrait Standard)', 'rfs-ai-seo'); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<p class="description">
			<?php if ($cost_estimate !== null) : ?>
				<?php
				printf(
					/* translators: %s: average cost per image from recent generations */
					esc_html__('Average cost per image (last 20 generations): %s', 'rfs-ai-seo'),
					esc_html($settings->format_gbp($cost_estimate))
				);
				?>
			<?php else : ?>
				<?php esc_html_e('No image generations recorded yet.', 'rfs-ai-seo'); ?>
			<?php endif; ?>
		</p>
	</div>

	<!-- Results Table -->
	<div class="rfs-ai-seo-bulk-results">
		<div class="tablenav top">
			<div class="alignleft actions">
				<input type="checkbox" id="rfs_image_bulk_select_all">
				<label for="rfs_image_bulk_select_all"><?php esc_html_e('Select All', 'rfs-ai-seo'); ?></label>
				<span id="rfs_image_bulk_selected_count" style="margin-left: 10px;"></span>
				<span id="rfs_image_bulk_cost_estimate" style="margin-left: 10px;"></span>
			</div>
			<div class="alignright actions">
				<button type="button" class="button button-primary" id="rfs_image_bulk_generate_selected" disabled>
					<?php esc_html_e('Generate for Selected', 'rfs-ai-seo'); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped rfs-ai-seo-bulk-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="rfs_image_bulk_select_all_header"></th>
					<th class="column-thumbnail"><?php esc_html_e('Image', 'rfs-ai-seo'); ?></th>
					<th class="column-name"><?php esc_html_e('Product Name', 'rfs-ai-seo'); ?></th>
					<th class="column-type"><?php esc_html_e('Type', 'rfs-ai-seo'); ?></th>
					<th class="column-status"><?php esc_html_e('Status', 'rfs-ai-seo'); ?></th>
				</tr>
			</thead>
			<tbody id="rfs_image_bulk_items_tbody">
				<tr>
					<td colspan="5" class="rfs-empty-state">
						<?php esc_html_e('Click "Filter" to load images...', 'rfs-ai-seo'); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft">
				<span id="rfs_image_bulk_total_count"></span>
			</div>
		</div>
	</div>

	<!-- Progress Panel (hidden by default) -->
	<div class="rfs-ai-seo-progress" id="rfs_image_bulk_progress" style="display: none;">
		<div class="rfs-progress-header">
			<div style="display: flex; align-items: center; gap: 12px;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #1d2327;">
					<?php esc_html_e('Generating Images...', 'rfs-ai-seo'); ?>
				</h2>
			</div>
			<button type="button" class="button button-secondary" id="rfs_image_bulk_stop_btn">
				<?php esc_html_e('Stop', 'rfs-ai-seo'); ?>
			</button>
		</div>

		<div class="rfs-ai-seo-progress-bar-container">
			<div class="rfs-progress-bar-label">
				<span id="rfs_image_progress_count">0 / 0 (0%)</span>
			</div>
			<div class="rfs-ai-seo-progress-bar">
				<div class="rfs-ai-seo-progress-bar-fill" id="rfs_image_progress_bar" style="width: 0%;"></div>
			</div>
		</div>

		<div class="rfs-progress-current-item">
			<strong><?php esc_html_e('Currently processing:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_image_progress_current"><?php esc_html_e('Initializing...', 'rfs-ai-seo'); ?></span>
		</div>

		<div class="rfs-ai-seo-progress-stats">
			<div class="rfs-stat-card rfs-stat-success">
				<div class="rfs-stat-icon">&#10003;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_image_progress_replaced">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Replaced', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-warning">
				<div class="rfs-stat-icon">&#8212;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_image_progress_skipped">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Skipped', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-error">
				<div class="rfs-stat-icon">&#10005;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_image_progress_failed">0</div>
					<div class="rfs-stat-label"><?php esc_html_e('Failed', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
			<div class="rfs-stat-card rfs-stat-info">
				<div class="rfs-stat-icon">&#9873;</div>
				<div class="rfs-stat-content">
					<div class="rfs-stat-value" id="rfs_image_progress_cost">
						&#163;0.0000
					</div>
					<div class="rfs-stat-label"><?php esc_html_e('Cost', 'rfs-ai-seo'); ?></div>
				</div>
			</div>
		</div>

		<div class="rfs-progress-log-container">
			<h3><?php esc_html_e('Activity Log', 'rfs-ai-seo'); ?></h3>
			<div class="rfs-ai-seo-progress-log" id="rfs_image_progress_log"></div>
		</div>
	</div>

	<!-- Comparison Panel (hidden by default) -->
	<div class="rfs-ai-seo-comparison" id="rfs_image_comparison_panel" style="display: none;">
		<div class="rfs-comparison-header">
			<h3>
				<span id="rfs_image_comparison_product"></span>
				<span id="rfs_image_comparison_label"></span>
				<span id="rfs_image_comparison_counter" class="rfs-comparison-counter"></span>
			</h3>
		</div>

		<div class="rfs-comparison-tabs">
			<button type="button" class="rfs-comparison-tab" data-tab="original">
				<?php esc_html_e('Original', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="rfs-comparison-tab active" data-tab="generated">
				<?php esc_html_e('Generated', 'rfs-ai-seo'); ?>
			</button>
		</div>

		<div class="rfs-comparison-images">
			<img id="rfs_image_comparison_original" class="rfs-comparison-img" src="" alt="<?php esc_attr_e('Original', 'rfs-ai-seo'); ?>">
			<img id="rfs_image_comparison_generated" class="rfs-comparison-img active" src="" alt="<?php esc_attr_e('Generated', 'rfs-ai-seo'); ?>">
		</div>

		<div class="rfs-comparison-actions">
			<button type="button" class="button button-primary" id="rfs_image_replace_btn">
				<?php esc_html_e('Replace', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button button-secondary" id="rfs_image_regenerate_btn">
				<?php esc_html_e('Regenerate', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button button-secondary" id="rfs_image_skip_btn">
				<?php esc_html_e('Skip', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_image_comparison_stop_btn" style="margin-left: auto; color: #d63638; border-color: #d63638;">
				<?php esc_html_e('Stop Remaining', 'rfs-ai-seo'); ?>
			</button>
		</div>
	</div>

	<!-- Completion Summary (hidden by default) -->
	<div class="rfs-ai-seo-completion-summary" id="rfs_image_completion_summary" style="display: none;">
		<h3><?php esc_html_e('Generation Complete', 'rfs-ai-seo'); ?></h3>
		<p>
			<strong><?php esc_html_e('Total Processed:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_image_summary_total">0</span>
		</p>
		<p>
			<strong><?php esc_html_e('Replaced:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_image_summary_replaced">0</span>
		</p>
		<p>
			<strong><?php esc_html_e('Skipped:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_image_summary_skipped">0</span>
		</p>
		<p>
			<strong><?php esc_html_e('Failed:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_image_summary_failed">0</span>
		</p>
		<p>
			<strong><?php esc_html_e('Total Cost:', 'rfs-ai-seo'); ?></strong>
			<span id="rfs_image_summary_cost">0</span>
		</p>
		<button type="button" class="button button-primary" id="rfs_image_bulk_close_btn">
			<?php esc_html_e('Close', 'rfs-ai-seo'); ?>
		</button>
	</div>
</div>
