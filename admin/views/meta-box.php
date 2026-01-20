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
</div>
