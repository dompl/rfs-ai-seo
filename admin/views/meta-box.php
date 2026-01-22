<?php
/**
 * Meta Box Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="rfs-ai-seo-meta-box">
	<!-- Current Meta Display -->
	<div class="rfs-ai-seo-current-meta">
		<p>
			<span class="label"><?php esc_html_e('Current Title:', 'rfs-ai-seo'); ?></span><br>
			<span class="value <?php echo empty($current_meta['title']) ? 'not-set' : ''; ?>">
				<?php echo !empty($current_meta['title']) ? esc_html($current_meta['title']) : esc_html__('Not set', 'rfs-ai-seo'); ?>
			</span>
		</p>
		<p>
			<span class="label"><?php esc_html_e('Current Description:', 'rfs-ai-seo'); ?></span><br>
			<span class="value <?php echo empty($current_meta['description']) ? 'not-set' : ''; ?>">
				<?php echo !empty($current_meta['description']) ? esc_html($current_meta['description']) : esc_html__('Not set', 'rfs-ai-seo'); ?>
			</span>
		</p>
	</div>

	<!-- Generate Button -->
	<div class="rfs-ai-seo-generate-btn">
		<button type="button" class="button button-primary button-large" id="rfs_ai_seo_generate_btn">
			<?php esc_html_e('Generate with AI', 'rfs-ai-seo'); ?>
		</button>
		<span class="spinner" id="rfs_ai_seo_generate_spinner"></span>
	</div>

	<!-- Preview Panel (hidden by default) -->
	<div class="rfs-ai-seo-preview" id="rfs_ai_seo_preview">
		<h4><?php esc_html_e('Generated Meta', 'rfs-ai-seo'); ?></h4>

		<!-- Meta Title Field -->
		<div class="rfs-ai-seo-field">
			<label for="rfs_ai_seo_preview_title">
				<?php esc_html_e('Meta Title', 'rfs-ai-seo'); ?>
			</label>
			<div class="rfs-ai-seo-char-counter" id="rfs_ai_seo_title_counter">
				<span class="count">0/<?php echo esc_html($title_max); ?></span>
				<span class="bar"></span>
			</div>
			<input type="text"
				   id="rfs_ai_seo_preview_title"
				   class="widefat"
				   maxlength="100">
		</div>

		<!-- Meta Description Field -->
		<div class="rfs-ai-seo-field">
			<label for="rfs_ai_seo_preview_description">
				<?php esc_html_e('Meta Description', 'rfs-ai-seo'); ?>
			</label>
			<div class="rfs-ai-seo-char-counter" id="rfs_ai_seo_desc_counter">
				<span class="count">0/<?php echo esc_html($desc_max); ?></span>
				<span class="bar"></span>
			</div>
			<textarea id="rfs_ai_seo_preview_description"
					  class="widefat"
					  rows="4"
					  maxlength="200"></textarea>
		</div>

		<!-- Action Buttons -->
		<div class="rfs-ai-seo-actions">
			<button type="button" class="button button-primary" id="rfs_ai_seo_save_btn">
				<?php esc_html_e('Save to Yoast', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_ai_seo_regenerate_btn">
				<?php esc_html_e('Regenerate', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_ai_seo_cancel_btn">
				<?php esc_html_e('Cancel', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" id="rfs_ai_seo_save_spinner"></span>
		</div>

		<!-- Token Usage -->
		<p class="rfs-ai-seo-token-info" id="rfs_ai_seo_token_info" style="font-size: 11px; color: #666; margin-top: 10px;"></p>
	</div>

	<!-- Hidden field to store object ID -->
	<input type="hidden" id="rfs_ai_seo_object_id" value="<?php echo esc_attr($post->ID); ?>">
	<input type="hidden" id="rfs_ai_seo_object_type" value="product">
	<input type="hidden" id="rfs_ai_seo_title_max" value="<?php echo esc_attr($title_max); ?>">
	<input type="hidden" id="rfs_ai_seo_desc_max" value="<?php echo esc_attr($desc_max); ?>">

	<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">

	<!-- Product Description Section -->
	<h4 style="margin: 15px 0 10px 0;"><?php esc_html_e('Product Description', 'rfs-ai-seo'); ?></h4>

	<!-- Current Description Status -->
	<div class="rfs-description-status" style="margin-bottom: 15px;">
		<p style="margin: 5px 0; font-size: 12px;">
			<strong><?php esc_html_e('Current Description:', 'rfs-ai-seo'); ?></strong>
			<?php if (!empty($current_description)): ?>
				<span style="color: #46b450;">✓ <?php esc_html_e('Set', 'rfs-ai-seo'); ?></span>
				<span style="color: #666;">(<?php echo esc_html(str_word_count($current_description)); ?> <?php esc_html_e('words', 'rfs-ai-seo'); ?>)</span>
			<?php else: ?>
				<span style="color: #dc3232;">✗ <?php esc_html_e('Empty', 'rfs-ai-seo'); ?></span>
			<?php endif; ?>
		</p>

		<?php if ($has_original): ?>
			<p style="margin: 5px 0; font-size: 12px; padding: 5px; background: #fff3cd; border-left: 3px solid #ffc107;">
				<strong><?php esc_html_e('Original Backup:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Available', 'rfs-ai-seo'); ?>
				<?php if ($original_saved_at): ?>
					<span style="color: #666;">(<?php echo esc_html(human_time_diff(strtotime($original_saved_at), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'rfs-ai-seo'); ?>)</span>
				<?php endif; ?>
				<br>
				<button type="button" class="button button-small" id="rfs_restore_description_btn" style="margin-top: 5px;">
					<?php esc_html_e('Restore Original', 'rfs-ai-seo'); ?>
				</button>
			</p>
		<?php endif; ?>
	</div>

	<!-- Generate Description Button -->
	<div style="margin-bottom: 10px;">
		<button type="button" class="button button-secondary button-large" id="rfs_generate_description_btn" style="width: 100%;">
			<?php esc_html_e('Generate Description with AI', 'rfs-ai-seo'); ?>
		</button>
		<span class="spinner" id="rfs_description_spinner" style="float: none; margin: 8px auto 0; display: none;"></span>
	</div>

	<!-- Description Preview Panel (hidden by default) -->
	<div class="rfs-description-preview" id="rfs_description_preview" style="display: none; background: #f6f7f7; padding: 15px; border-radius: 4px; margin-top: 10px;">
		<h4 style="margin-top: 0;"><?php esc_html_e('Generated Description', 'rfs-ai-seo'); ?></h4>

		<textarea id="rfs_description_preview_text"
				  class="widefat"
				  rows="10"
				  style="font-family: inherit; margin-bottom: 10px;"></textarea>

		<div style="margin-bottom: 10px;">
			<span id="rfs_description_word_count" style="font-size: 12px; color: #666;"></span>
		</div>

		<div class="rfs-ai-seo-actions">
			<button type="button" class="button button-primary" id="rfs_save_description_btn">
				<?php esc_html_e('Save Description', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_regenerate_description_btn">
				<?php esc_html_e('Regenerate', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_cancel_description_btn">
				<?php esc_html_e('Cancel', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" id="rfs_save_description_spinner" style="float: none; margin: 8px 0 0 8px; display: none;"></span>
		</div>

		<p id="rfs_description_token_info" style="font-size: 11px; color: #666; margin-top: 10px;"></p>
	</div>

	<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">

	<!-- Product Short Description Section -->
	<h4 style="margin: 15px 0 10px 0;"><?php esc_html_e('Product Short Description', 'rfs-ai-seo'); ?></h4>

	<!-- Current Short Description Status -->
	<div class="rfs-short-description-status" style="margin-bottom: 15px;">
		<p style="margin: 5px 0; font-size: 12px;">
			<strong><?php esc_html_e('Current Short Description:', 'rfs-ai-seo'); ?></strong>
			<?php if (!empty($current_short_description)): ?>
				<span style="color: #46b450;">✓ <?php esc_html_e('Set', 'rfs-ai-seo'); ?></span>
				<span style="color: #666;">(<?php echo esc_html(str_word_count($current_short_description)); ?> <?php esc_html_e('words', 'rfs-ai-seo'); ?>)</span>
			<?php else: ?>
				<span style="color: #dc3232;">✗ <?php esc_html_e('Empty', 'rfs-ai-seo'); ?></span>
			<?php endif; ?>
		</p>

		<?php if ($has_original_short): ?>
			<p style="margin: 5px 0; font-size: 12px; padding: 5px; background: #fff3cd; border-left: 3px solid #ffc107;">
				<strong><?php esc_html_e('Original Backup:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Available', 'rfs-ai-seo'); ?>
				<?php if ($original_short_saved_at): ?>
					<span style="color: #666;">(<?php echo esc_html(human_time_diff(strtotime($original_short_saved_at), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'rfs-ai-seo'); ?>)</span>
				<?php endif; ?>
				<br>
				<button type="button" class="button button-small" id="rfs_restore_short_description_btn" style="margin-top: 5px;">
					<?php esc_html_e('Restore Original', 'rfs-ai-seo'); ?>
				</button>
			</p>
		<?php endif; ?>
	</div>

	<!-- Generate Short Description Button -->
	<div style="margin-bottom: 10px;">
		<button type="button" class="button button-secondary button-large" id="rfs_generate_short_description_btn" style="width: 100%;">
			<?php esc_html_e('Generate Short Description with AI', 'rfs-ai-seo'); ?>
		</button>
		<span class="spinner" id="rfs_short_description_spinner" style="float: none; margin: 8px auto 0; display: none;"></span>
	</div>

	<!-- Short Description Preview Panel (hidden by default) -->
	<div class="rfs-short-description-preview" id="rfs_short_description_preview" style="display: none; background: #f6f7f7; padding: 15px; border-radius: 4px; margin-top: 10px;">
		<h4 style="margin-top: 0;"><?php esc_html_e('Generated Short Description', 'rfs-ai-seo'); ?></h4>

		<textarea id="rfs_short_description_preview_text"
				  class="widefat"
				  rows="4"
				  style="font-family: inherit; margin-bottom: 10px;"></textarea>

		<div style="margin-bottom: 10px;">
			<span id="rfs_short_description_word_count" style="font-size: 12px; color: #666;"></span>
		</div>

		<div class="rfs-ai-seo-actions">
			<button type="button" class="button button-primary" id="rfs_save_short_description_btn">
				<?php esc_html_e('Save Short Description', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_regenerate_short_description_btn">
				<?php esc_html_e('Regenerate', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_cancel_short_description_btn">
				<?php esc_html_e('Cancel', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" id="rfs_save_short_description_spinner" style="float: none; margin: 8px 0 0 8px; display: none;"></span>
		</div>

		<p id="rfs_short_description_token_info" style="font-size: 11px; color: #666; margin-top: 10px;"></p>
	</div>
</div>
