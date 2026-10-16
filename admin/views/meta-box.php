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

$rfs_settings = new \RFS_AI_SEO\Settings();
$rfs_connected = \RedFrogStudio\AtsAiPortal\Portal::has_key('content');
$rfs_focuskw = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
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
		<p class="rfs-ai-seo-token-info" id="rfs_ai_seo_token_info"></p>
	</div>

	<!-- Hidden field to store object ID -->
	<input type="hidden" id="rfs_ai_seo_object_id" value="<?php echo esc_attr($post->ID); ?>">
	<input type="hidden" id="rfs_ai_seo_object_type" value="product">
	<input type="hidden" id="rfs_ai_seo_title_max" value="<?php echo esc_attr($title_max); ?>">
	<input type="hidden" id="rfs_ai_seo_desc_max" value="<?php echo esc_attr($desc_max); ?>">

	<hr>

	<!-- Product Description Section -->
	<h4 class="rfs-section-title"><?php esc_html_e('Product Description', 'rfs-ai-seo'); ?></h4>

	<!-- Current Description Status -->
	<div class="rfs-description-status">
		<p>
			<strong><?php esc_html_e('Current Description:', 'rfs-ai-seo'); ?></strong>
			<?php if (!empty($current_description)): ?>
				<span class="rfs-status-set"><?php echo esc_html("\xE2\x9C\x93"); ?> <?php esc_html_e('Set', 'rfs-ai-seo'); ?></span>
				<span class="rfs-status-word-count">(<?php echo esc_html(str_word_count(wp_strip_all_tags($current_description))); ?> <?php esc_html_e('words', 'rfs-ai-seo'); ?>)</span>
			<?php else: ?>
				<span class="rfs-status-empty"><?php echo esc_html("\xE2\x9C\x97"); ?> <?php esc_html_e('Empty', 'rfs-ai-seo'); ?></span>
			<?php endif; ?>
		</p>

		<?php if ($has_original): ?>
			<div class="rfs-backup-notice">
				<strong><?php esc_html_e('Original Backup:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Available', 'rfs-ai-seo'); ?>
				<?php if ($original_saved_at): ?>
					<span class="rfs-backup-time">(<?php echo esc_html(human_time_diff(strtotime($original_saved_at), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'rfs-ai-seo'); ?>)</span>
				<?php endif; ?>
				<br>
				<button type="button" class="button button-small" id="rfs_restore_description_btn">
					<?php esc_html_e('Restore Original', 'rfs-ai-seo'); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<div class="rfs-generate-controls" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin:8px 0">
		<label><?php esc_html_e('Tone', 'rfs-ai-seo'); ?><br>
			<select id="rfs_description_tone">
				<?php foreach (\RFS_AI_SEO\Settings::tones() as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($rfs_settings->get_content_tone(), $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e('Words', 'rfs-ai-seo'); ?><br>
			<input type="number" id="rfs_description_words" min="50" max="1000" step="10" value="<?php echo esc_attr((string) $rfs_settings->get_description_words()); ?>" style="width:90px">
		</label>
		<label><?php esc_html_e('Keywords', 'rfs-ai-seo'); ?><br>
			<input type="text" id="rfs_description_keywords" value="<?php echo esc_attr($rfs_focuskw); ?>" style="width:180px">
		</label>
	</div>

	<!-- Generate Description Button -->
	<div class="rfs-generate-btn-wrap">
		<button type="button" class="button button-secondary button-large" id="rfs_generate_description_btn" <?php disabled(!$rfs_connected); ?>>
			<?php esc_html_e('Generate Description with AI', 'rfs-ai-seo'); ?>
		</button>
		<span class="spinner" id="rfs_description_spinner"></span>
		<div class="notice notice-error inline" id="rfs_description_generate_error" style="display:none"></div>
	</div>

	<!-- Description Preview Panel (hidden by default) -->
	<div class="rfs-description-preview" id="rfs_description_preview">
		<h4><?php esc_html_e('Generated Description', 'rfs-ai-seo'); ?></h4>

		<!-- HTML Preview -->
		<div>
			<label class="rfs-html-preview-label">
				<?php esc_html_e('Preview (How it will look):', 'rfs-ai-seo'); ?>
			</label>
			<div class="rfs-html-preview" id="rfs_description_html_preview"></div>
		</div>

		<!-- HTML Source -->
		<div>
			<label for="rfs_description_preview_text" class="rfs-html-source-label">
				<?php esc_html_e('HTML Source (Editable):', 'rfs-ai-seo'); ?>
			</label>
		</div>
		<textarea id="rfs_description_preview_text"
				  class="widefat rfs-html-source"
				  rows="10"></textarea>

		<span class="rfs-word-count" id="rfs_description_word_count"></span>

		<div class="rfs-ai-seo-actions">
			<button type="button" class="button button-primary" id="rfs_save_description_btn">
				<?php esc_html_e('Apply', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_regenerate_description_btn">
				<?php esc_html_e('Regenerate', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_cancel_description_btn">
				<?php esc_html_e('Discard', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" id="rfs_save_description_spinner"></span>
		</div>

		<p class="rfs-description-token-info" id="rfs_description_token_info"></p>
	</div>

	<hr>

	<!-- Product Short Description Section -->
	<h4 class="rfs-section-title"><?php esc_html_e('Product Short Description', 'rfs-ai-seo'); ?></h4>

	<!-- Current Short Description Status -->
	<div class="rfs-short-description-status">
		<p>
			<strong><?php esc_html_e('Current Short Description:', 'rfs-ai-seo'); ?></strong>
			<?php if (!empty($current_short_description)): ?>
				<span class="rfs-status-set"><?php echo esc_html("\xE2\x9C\x93"); ?> <?php esc_html_e('Set', 'rfs-ai-seo'); ?></span>
				<span class="rfs-status-word-count">(<?php echo esc_html(str_word_count(wp_strip_all_tags($current_short_description))); ?> <?php esc_html_e('words', 'rfs-ai-seo'); ?>)</span>
			<?php else: ?>
				<span class="rfs-status-empty"><?php echo esc_html("\xE2\x9C\x97"); ?> <?php esc_html_e('Empty', 'rfs-ai-seo'); ?></span>
			<?php endif; ?>
		</p>

		<?php if ($has_original_short): ?>
			<div class="rfs-backup-notice">
				<strong><?php esc_html_e('Original Backup:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Available', 'rfs-ai-seo'); ?>
				<?php if ($original_short_saved_at): ?>
					<span class="rfs-backup-time">(<?php echo esc_html(human_time_diff(strtotime($original_short_saved_at), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'rfs-ai-seo'); ?>)</span>
				<?php endif; ?>
				<br>
				<button type="button" class="button button-small" id="rfs_restore_short_description_btn">
					<?php esc_html_e('Restore Original', 'rfs-ai-seo'); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<div class="rfs-generate-controls" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin:8px 0">
		<label><?php esc_html_e('Tone', 'rfs-ai-seo'); ?><br>
			<select id="rfs_short_description_tone">
				<?php foreach (\RFS_AI_SEO\Settings::tones() as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($rfs_settings->get_content_tone(), $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e('Sentences', 'rfs-ai-seo'); ?><br>
			<input type="number" id="rfs_short_description_sentences" min="1" max="6" step="1" value="<?php echo esc_attr((string) $rfs_settings->get_short_sentences()); ?>" style="width:90px">
		</label>
		<label><?php esc_html_e('Keywords', 'rfs-ai-seo'); ?><br>
			<input type="text" id="rfs_short_description_keywords" value="<?php echo esc_attr($rfs_focuskw); ?>" style="width:180px">
		</label>
	</div>

	<!-- Generate Short Description Button -->
	<div class="rfs-generate-btn-wrap">
		<button type="button" class="button button-secondary button-large" id="rfs_generate_short_description_btn" <?php disabled(!$rfs_connected); ?>>
			<?php esc_html_e('Generate Short Description with AI', 'rfs-ai-seo'); ?>
		</button>
		<span class="spinner" id="rfs_short_description_spinner"></span>
		<div class="notice notice-error inline" id="rfs_short_description_generate_error" style="display:none"></div>
	</div>

	<!-- Short Description Preview Panel (hidden by default) -->
	<div class="rfs-short-description-preview" id="rfs_short_description_preview">
		<h4><?php esc_html_e('Generated Short Description', 'rfs-ai-seo'); ?></h4>

		<textarea id="rfs_short_description_preview_text"
				  class="widefat"
				  rows="4"></textarea>

		<span class="rfs-word-count" id="rfs_short_description_word_count"></span>

		<div class="rfs-ai-seo-actions">
			<button type="button" class="button button-primary" id="rfs_save_short_description_btn">
				<?php esc_html_e('Apply', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_regenerate_short_description_btn">
				<?php esc_html_e('Regenerate', 'rfs-ai-seo'); ?>
			</button>
			<button type="button" class="button" id="rfs_cancel_short_description_btn">
				<?php esc_html_e('Discard', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" id="rfs_save_short_description_spinner"></span>
		</div>

		<p class="rfs-description-token-info" id="rfs_short_description_token_info"></p>
	</div>

	<?php if (!empty($product_images)): ?>
	<hr>

	<!-- Image Alt Text Section -->
	<h4 class="rfs-section-title"><?php esc_html_e('Image Alt Text', 'rfs-ai-seo'); ?></h4>

	<?php
	$images_with_alt = count(array_filter($product_images, function($img) { return $img['has_alt']; }));
	$total_images = count($product_images);
	?>

	<div class="rfs-alt-text-summary">
		<p>
			<strong><?php
				printf(
					/* translators: 1: count with alt, 2: total count */
					esc_html__('%1$d of %2$d images have alt text', 'rfs-ai-seo'),
					$images_with_alt,
					$total_images
				);
			?></strong>
		</p>
		<?php if ($images_with_alt < $total_images): ?>
			<button type="button" class="button button-secondary button-small" id="rfs_generate_all_alt_text_btn">
				<?php esc_html_e('Generate All Missing', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" id="rfs_all_alt_text_spinner"></span>
		<?php endif; ?>
	</div>

	<div class="rfs-alt-text-image-list" id="rfs_alt_text_image_list">
		<?php foreach ($product_images as $img): ?>
			<div class="rfs-alt-text-image-row" data-attachment-id="<?php echo esc_attr($img['attachment_id']); ?>">
				<div class="rfs-alt-text-thumb">
					<?php if ($img['thumbnail']): ?>
						<img src="<?php echo esc_url($img['thumbnail']); ?>" alt="" width="40" height="40">
					<?php else: ?>
						<span class="dashicons dashicons-format-image"></span>
					<?php endif; ?>
				</div>
				<div class="rfs-alt-text-info">
					<span class="rfs-alt-text-type-badge rfs-alt-text-type-<?php echo esc_attr($img['type']); ?>">
						<?php echo esc_html(ucfirst($img['type'])); ?>
					</span>
					<div class="rfs-alt-text-current">
						<?php if ($img['has_alt']): ?>
							<span class="rfs-alt-text-value"><?php echo esc_html($img['current_alt']); ?></span>
						<?php else: ?>
							<span class="rfs-status-empty"><?php esc_html_e('Not set', 'rfs-ai-seo'); ?></span>
						<?php endif; ?>
					</div>
					<!-- Inline edit (hidden by default) -->
					<div class="rfs-alt-text-edit" style="display: none;">
						<input type="text" class="widefat rfs-alt-text-input" value="<?php echo esc_attr($img['current_alt']); ?>" maxlength="125">
						<div class="rfs-alt-text-edit-actions">
							<button type="button" class="button button-small button-primary rfs-alt-text-save-btn"><?php esc_html_e('Save', 'rfs-ai-seo'); ?></button>
							<button type="button" class="button button-small rfs-alt-text-cancel-btn"><?php esc_html_e('Cancel', 'rfs-ai-seo'); ?></button>
							<span class="spinner rfs-alt-text-save-spinner"></span>
						</div>
					</div>
				</div>
				<div class="rfs-alt-text-actions">
					<button type="button" class="button button-small rfs-alt-text-generate-btn" title="<?php esc_attr_e('Generate', 'rfs-ai-seo'); ?>">
						<?php esc_html_e('Generate', 'rfs-ai-seo'); ?>
					</button>
					<span class="spinner rfs-alt-text-spinner"></span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>
