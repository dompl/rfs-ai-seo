<?php
/**
 * Image Generator Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

use RedFrogStudio\AtsAiPortal\Portal;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Image Generator Class
 *
 * Handles AI-powered image generation using gpt-image-1 through the Red Frog portal gateway.
 *
 * @class Image_Generator
 */
class Image_Generator {

	/** Seconds to wait for gpt-image-1 (slow at high quality). */
	public const IMAGE_TIMEOUT = 120;

	private const IMAGE_MODEL = 'gpt-image-1';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings|null $settings Settings instance (optional, for testing).
	 */
	public function __construct(?Settings $settings = null) {
		$this->settings = $settings ?? new Settings();

		// Register AJAX handlers.
		add_action('wp_ajax_rfs_ai_generate_image', [$this, 'ajax_generate_image']);
		add_action('wp_ajax_rfs_ai_generate_featured_image', [$this, 'ajax_generate_featured_image']);
		add_action('wp_ajax_rfs_ai_set_featured_image', [$this, 'ajax_set_featured_image']);
		add_action('wp_ajax_rfs_ai_generate_product_image', [$this, 'ajax_generate_product_image']);
		add_action('wp_ajax_rfs_ai_replace_product_image', [$this, 'ajax_replace_product_image']);
		add_action('wp_ajax_rfs_ai_get_image_items', [$this, 'ajax_get_image_items']);

		// Add custom fields to attachment details.
		add_filter('attachment_fields_to_edit', [$this, 'add_attachment_fields'], 10, 2);
		add_filter('attachment_fields_to_save', [$this, 'save_attachment_fields'], 10, 2);

		// Add custom column to media library.
		add_filter('manage_media_columns', [$this, 'add_media_column']);
		add_action('manage_media_custom_column', [$this, 'render_media_column'], 10, 2);

		// Add featured image generator meta box.
		add_action('add_meta_boxes', [$this, 'register_featured_image_meta_box']);
	}

	/**
	 * Add custom fields to attachment edit screen.
	 *
	 * @param array    $form_fields Form fields.
	 * @param \WP_Post $post Attachment post object.
	 * @return array Modified form fields.
	 */
	public function add_attachment_fields(array $form_fields, \WP_Post $post): array {
		// Get existing meta.
		$ai_description = get_post_meta($post->ID, '_rfs_ai_image_description', true);
		$selected_prompt = get_post_meta($post->ID, '_rfs_ai_image_prompt_template', true);
		$original_id = get_post_meta($post->ID, '_rfs_ai_original_image_id', true);

		// Add predefined prompt template dropdown.
		$image_prompts = $this->settings->get_image_prompts();
		if (!empty($image_prompts)) {
			$prompt_select_html = sprintf(
				'<select name="attachments[%d][rfs_ai_image_prompt_template]" id="attachments-%d-rfs_ai_image_prompt_template" class="rfs-ai-prompt-template-select" data-attachment-id="%d" style="width: 100%%;">',
				$post->ID,
				$post->ID,
				$post->ID
			);
			$prompt_select_html .= sprintf(
				'<option value="">%s</option>',
				esc_html__('-- No template (use description below) --', 'rfs-ai-seo')
			);
			foreach ($image_prompts as $index => $prompt) {
				$prompt_select_html .= sprintf(
					'<option value="%d"%s>%s</option>',
					$index,
					selected($selected_prompt, (string) $index, false),
					esc_html($prompt['title'])
				);
			}
			$prompt_select_html .= '</select>';
			$prompt_select_html .= '<p class="description">' . esc_html__('Select a predefined prompt template, or leave empty to use the description below.', 'rfs-ai-seo') . '</p>';

			$form_fields['rfs_ai_image_prompt_template'] = [
				'label' => __('Image Prompt Template', 'rfs-ai-seo'),
				'input' => 'html',
				'html' => $prompt_select_html,
			];
		}

		// Add AI Image Description field with custom HTML for a bigger textarea.
		$form_fields['rfs_ai_image_description'] = [
			'label' => __('AI Image Description', 'rfs-ai-seo'),
			'input' => 'html',
			'html' => sprintf(
				'<textarea name="attachments[%d][rfs_ai_image_description]" id="attachments-%d-rfs_ai_image_description" rows="6" cols="60" style="width: 100%%; min-height: 120px;">%s</textarea>'
				. '<p class="description">%s</p>',
				$post->ID,
				$post->ID,
				esc_textarea($ai_description),
				esc_html__('Describe what you want the generated image to look like. Only used if no prompt template is selected above.', 'rfs-ai-seo')
			),
		];

		// Add aspect ratio selector.
		$saved_ratio = get_post_meta($post->ID, '_rfs_ai_image_aspect_ratio', true) ?: '1:1';
		$ratios = [
			'1:1'  => __('1:1 (Square)', 'rfs-ai-seo'),
			'3:4'  => __('3:4 (Portrait)', 'rfs-ai-seo'),
			'4:3'  => __('4:3 (Landscape)', 'rfs-ai-seo'),
			'16:9' => __('16:9 (Widescreen)', 'rfs-ai-seo'),
			'9:16' => __('9:16 (Tall)', 'rfs-ai-seo'),
		];
		$select_html = sprintf(
			'<select name="attachments[%d][rfs_ai_image_aspect_ratio]" id="attachments-%d-rfs_ai_image_aspect_ratio" class="rfs-ai-aspect-ratio-select" data-attachment-id="%d">',
			$post->ID,
			$post->ID,
			$post->ID
		);
		foreach ($ratios as $value => $label) {
			$select_html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr($value),
				selected($saved_ratio, $value, false),
				esc_html($label)
			);
		}
		$select_html .= '</select>';

		$form_fields['rfs_ai_image_aspect_ratio'] = [
			'label' => __('Image Aspect Ratio', 'rfs-ai-seo'),
			'input' => 'html',
			'html' => $select_html,
		];

		// Add Regenerate with AI button.
		$form_fields['rfs_ai_regenerate'] = [
			'label' => __('AI Image Generation', 'rfs-ai-seo'),
			'input' => 'html',
			'html' => $this->render_regenerate_button($post->ID),
		];

		// Show original image info if this is an AI-generated image.
		if ($original_id) {
			$original_image = wp_get_attachment_image_url($original_id, 'thumbnail');
			if ($original_image) {
				$form_fields['rfs_ai_original'] = [
					'label' => __('Original Image', 'rfs-ai-seo'),
					'input' => 'html',
					'html' => sprintf(
						'<a href="%s" target="_blank"><img src="%s" alt="" style="max-width: 150px; border: 1px solid #ddd; border-radius: 4px;"></a>',
						esc_url(wp_get_attachment_url($original_id)),
						esc_url($original_image)
					),
				];
			}
		}

		return $form_fields;
	}

	/**
	 * Save custom attachment fields.
	 *
	 * @param array $post Attachment post data.
	 * @param array $attachment Attachment form data.
	 * @return array Modified post data.
	 */
	public function save_attachment_fields(array $post, array $attachment): array {
		if (isset($attachment['rfs_ai_image_prompt_template'])) {
			$template_value = sanitize_text_field($attachment['rfs_ai_image_prompt_template']);
			if ($template_value === '') {
				delete_post_meta($post['ID'], '_rfs_ai_image_prompt_template');
			} else {
				update_post_meta($post['ID'], '_rfs_ai_image_prompt_template', $template_value);
			}
		}

		if (isset($attachment['rfs_ai_image_description'])) {
			update_post_meta(
				$post['ID'],
				'_rfs_ai_image_description',
				sanitize_textarea_field($attachment['rfs_ai_image_description'])
			);
		}

		if (isset($attachment['rfs_ai_image_aspect_ratio'])) {
			update_post_meta(
				$post['ID'],
				'_rfs_ai_image_aspect_ratio',
				sanitize_text_field($attachment['rfs_ai_image_aspect_ratio'])
			);
		}

		return $post;
	}

	/**
	 * Render regenerate button HTML.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Button HTML.
	 */
	private function render_regenerate_button(int $attachment_id): string {
		$has_key = Portal::has_key('images');

		ob_start();
		?>
		<div class="rfs-ai-image-generator">
			<button
				type="button"
				class="button button-primary rfs-ai-regenerate-image-btn"
				data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
				<?php echo $has_key ? '' : 'disabled title="' . esc_attr__('AI not connected', 'rfs-ai-seo') . '"'; ?>>
				<span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
				<?php esc_html_e('Regenerate with AI', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
			<p class="description">
				<?php if ($has_key): ?>
					<?php esc_html_e('Generate a new version of this image with AI. The original image will be preserved.', 'rfs-ai-seo'); ?>
				<?php else: ?>
					<?php esc_html_e('AI is not connected. Ask an administrator to run: wp ats secret set gateway_key_images', 'rfs-ai-seo'); ?>
				<?php endif; ?>
			</p>
			<div class="rfs-ai-image-result" style="margin-top: 10px; display: none;">
				<div class="rfs-ai-image-preview" style="max-width: 300px; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f9f9f9;"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add AI status column to media library.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_media_column(array $columns): array {
		$columns['rfs_ai_generated'] = __('AI Generated', 'rfs-ai-seo');
		return $columns;
	}

	/**
	 * Render AI status column content.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id Attachment ID.
	 * @return void
	 */
	public function render_media_column(string $column_name, int $post_id): void {
		if ($column_name === 'rfs_ai_generated') {
			$original_id = get_post_meta($post_id, '_rfs_ai_original_image_id', true);
			if ($original_id) {
				echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__('AI Generated', 'rfs-ai-seo') . '"></span>';
			} else {
				echo '—';
			}
		}
	}

	/**
	 * AJAX handler for image generation.
	 *
	 * @return void
	 */
	public function ajax_generate_image(): void {
		// Verify nonce.
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		// Check capabilities.
		if (!current_user_can('upload_files')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		// Get attachment ID.
		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
		$aspect_ratio = isset($_POST['aspect_ratio']) ? sanitize_text_field($_POST['aspect_ratio']) : '';
		$prompt_template = isset($_POST['prompt_template']) ? sanitize_text_field($_POST['prompt_template']) : '';

		if (!$attachment_id) {
			wp_send_json_error([
				'message' => __('Invalid attachment ID.', 'rfs-ai-seo'),
			]);
		}

		// Save the aspect ratio choice to the attachment.
		if (!empty($aspect_ratio)) {
			update_post_meta($attachment_id, '_rfs_ai_image_aspect_ratio', $aspect_ratio);
		}

		// Save the prompt template selection.
		error_log('RFS AI SEO: AJAX received prompt_template value: "' . $prompt_template . '"');
		if ($prompt_template !== '') {
			update_post_meta($attachment_id, '_rfs_ai_image_prompt_template', $prompt_template);
			error_log('RFS AI SEO: Saved prompt template index ' . $prompt_template . ' to attachment ' . $attachment_id);
		} else {
			delete_post_meta($attachment_id, '_rfs_ai_image_prompt_template');
			error_log('RFS AI SEO: No prompt template selected, cleared meta for attachment ' . $attachment_id);
		}

		// Generate image.
		$result = $this->generate_image($attachment_id);

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
				'code' => $result->get_error_code(),
			]);
		}

		wp_send_json_success($result);
	}

	/**
	 * Generate a new image using AI.
	 *
	 * @param int $attachment_id Source attachment ID.
	 * @return array|\WP_Error Generation result or error.
	 */
	public function generate_image(int $attachment_id) {
		// Get attachment.
		$attachment = get_post($attachment_id);

		if (!$attachment || $attachment->post_type !== 'attachment') {
			return new \WP_Error('invalid_attachment', __('Invalid attachment.', 'rfs-ai-seo'));
		}

		// Build prompt from attachment data.
		$prompt = $this->build_prompt($attachment);

		if (empty($prompt)) {
			return new \WP_Error('empty_prompt', __('Could not generate a prompt for this image. Please add an AI image description.', 'rfs-ai-seo'));
		}

		// Get per-image aspect ratio, fall back to a sensible default.
		$aspect_ratio = get_post_meta($attachment_id, '_rfs_ai_image_aspect_ratio', true);
		if (empty($aspect_ratio)) {
			$aspect_ratio = '1:1';
		}

		// Read the source image file to send alongside the prompt.
		$source_image_data = $this->get_source_image_data($attachment_id);

		// Call the AI service with the source image.
		$response = $this->call_image_api($prompt, $this->size_for($aspect_ratio), $source_image_data, (string) $attachment_id);

		if (is_wp_error($response)) {
			return $response;
		}

		// Save the image from base64 data.
		$new_attachment_id = $this->save_base64_image($response['image_data'], $response['mime_type'], $attachment);

		if (is_wp_error($new_attachment_id)) {
			return $new_attachment_id;
		}

		// Store reference to original image.
		update_post_meta($new_attachment_id, '_rfs_ai_original_image_id', $attachment_id);
		update_post_meta($new_attachment_id, '_rfs_ai_generated', true);
		update_post_meta($new_attachment_id, '_rfs_ai_prompt', $prompt);

		$charged = (float) ($response['charged_gbp'] ?? 0);
		$logger = new Usage_Logger();
		$logger->log($new_attachment_id, 'image_generation', [
			'tokens_prompt' => 0,
			'tokens_completion' => 0,
			'tokens_total' => 0,
			'model' => self::IMAGE_MODEL,
			'charged_gbp' => $charged,
			'generated_title' => $attachment->post_title,
			'generated_description' => mb_substr($prompt, 0, 500),
		]);

		// Get URLs — fall back to full URL if specific sizes aren't available.
		$full_url = wp_get_attachment_url($new_attachment_id);
		$medium_url = wp_get_attachment_image_url($new_attachment_id, 'medium');
		$thumbnail_url = $medium_url ?: $full_url;

		return [
			'new_attachment_id' => $new_attachment_id,
			'new_attachment_url' => $full_url,
			'thumbnail_url' => $thumbnail_url,
			'prompt' => $prompt,
			'cost' => round($charged, 4),
			'cost_formatted' => $this->settings->format_gbp($charged),
			'balance_gbp' => Portal::balance(),
		];
	}

	/**
	 * Build prompt from attachment data.
	 *
	 * @param \WP_Post $attachment Attachment post object.
	 * @return string Prompt text.
	 */
	private function build_prompt(\WP_Post $attachment): string {
		// Predefined prompt template takes highest priority.
		$template_index = get_post_meta($attachment->ID, '_rfs_ai_image_prompt_template', true);
		error_log('RFS AI SEO: build_prompt - template_index from meta: "' . var_export($template_index, true) . '"');

		if ($template_index !== '' && $template_index !== false) {
			$image_prompts = $this->settings->get_image_prompts();
			$index = (int) $template_index;
			error_log('RFS AI SEO: build_prompt - Found ' . count($image_prompts) . ' image prompts, looking for index ' . $index);

			if (isset($image_prompts[$index]) && !empty($image_prompts[$index]['description'])) {
				$prompt = $image_prompts[$index]['description'];
				error_log('RFS AI SEO: build_prompt - Using predefined template "' . ($image_prompts[$index]['title'] ?? 'untitled') . '": ' . substr($prompt, 0, 100) . '...');
				return $prompt;
			} else {
				error_log('RFS AI SEO: build_prompt - Template index ' . $index . ' not found or empty description');
			}
		}

		// AI image description takes second priority.
		$ai_description = get_post_meta($attachment->ID, '_rfs_ai_image_description', true);
		if (!empty($ai_description)) {
			error_log('RFS AI SEO: build_prompt - Using manual AI description: ' . substr($ai_description, 0, 100) . '...');
			return $ai_description;
		}

		$parts = [];

		// Get alt text.
		$alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
		if (!empty($alt_text)) {
			$parts[] = $alt_text;
		}

		// Get attachment description (content).
		if (!empty($attachment->post_content)) {
			$parts[] = wp_strip_all_tags($attachment->post_content);
		}

		// Get attachment caption.
		if (!empty($attachment->post_excerpt)) {
			$parts[] = $attachment->post_excerpt;
		}

		if (empty($parts)) {
			return '';
		}

		return 'Generate an image: ' . implode('. ', $parts);
	}

	/**
	 * Read source image as base64 data for sending to the AI service.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Array with 'data' (base64) and 'mime_type', or null if unreadable.
	 */
	private function get_source_image_data(int $attachment_id): ?array {
		$file_path = get_attached_file($attachment_id);

		if (!$file_path || !file_exists($file_path)) {
			error_log('RFS AI SEO: Source image file not found: ' . ($file_path ?: 'no path'));
			return null;
		}

		$mime_type = get_post_mime_type($attachment_id);

		// Only include image types that gpt-image-1 edits accept.
		$supported = ['image/jpeg', 'image/png', 'image/webp'];
		if (!in_array($mime_type, $supported, true)) {
			error_log('RFS AI SEO: Unsupported source image type: ' . $mime_type);
			return null;
		}

		$image_data = file_get_contents($file_path);
		if ($image_data === false) {
			error_log('RFS AI SEO: Could not read source image file');
			return null;
		}

		error_log('RFS AI SEO: Source image loaded - ' . $mime_type . ', ' . strlen($image_data) . ' bytes');

		return [
			'data' => base64_encode($image_data),
			'mime_type' => $mime_type,
		];
	}

	/**
	 * Call the portal's OpenAI image endpoint (gpt-image-1).
	 *
	 * @param string      $prompt Image prompt.
	 * @param string      $size Image size, eg '1024x1024'.
	 * @param array|null  $source_image Source image data (base64 + mime_type) to edit, or null to generate from scratch.
	 * @param string      $reference Reference id for portal usage tracking.
	 * @return array|\WP_Error API response with base64 image data, or error.
	 */
	private function call_image_api(string $prompt, string $size, ?array $source_image, string $reference = ''): array|\WP_Error {
		if (!Portal::has_key('images')) {
			return new \WP_Error('ats_portal_no_key', __('AI is not connected. Run: wp ats secret set gateway_key_images', 'rfs-ai-seo'));
		}

		$quality = $this->settings->get_image_quality();
		$headers = Portal::headers('images', 'ats:image:product', $reference);

		if ($source_image) {
			$boundary = 'rfs' . wp_generate_password(24, false);
			$ext = match ($source_image['mime_type']) {
				'image/jpeg' => 'jpg',
				'image/webp' => 'webp',
				default => 'png',
			};
			$fields = [
				'model' => self::IMAGE_MODEL,
				'prompt' => $prompt,
				'size' => $size,
				'quality' => $quality,
				'n' => '1',
			];
			$body = '';
			foreach ($fields as $name => $value) {
				$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
			}
			$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"image[]\"; filename=\"source.{$ext}\"\r\n";
			$body .= "Content-Type: {$source_image['mime_type']}\r\n\r\n" . base64_decode($source_image['data']) . "\r\n";
			$body .= "--{$boundary}--\r\n";
			$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
			$url = Portal::openai_url('/images/edits');
		} else {
			$body = wp_json_encode([
				'model' => self::IMAGE_MODEL,
				'prompt' => $prompt,
				'size' => $size,
				'quality' => $quality,
				'n' => 1,
			]);
			$url = Portal::openai_url('/images/generations');
		}

		$response = wp_remote_post($url, ['headers' => $headers, 'body' => $body, 'timeout' => self::IMAGE_TIMEOUT]);
		$code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

		if (is_wp_error($response) || $code >= 500) {
			return new \WP_Error('ats_portal_unavailable', __('AI service unavailable, try again.', 'rfs-ai-seo'));
		}
		$raw = (string) wp_remote_retrieve_body($response);
		if ($code !== 200) {
			return Portal::error($code, $raw);
		}
		$data = json_decode($raw, true);
		$b64 = $data['data'][0]['b64_json'] ?? '';
		if (!is_string($b64) || $b64 === '') {
			return new \WP_Error('no_image', __('The AI service returned no image.', 'rfs-ai-seo'));
		}

		return [
			'image_data' => $b64,
			'mime_type' => 'image/png',
			'charged_gbp' => Portal::observe($response),
		];
	}

	/**
	 * Map an aspect ratio token to the nearest gpt-image-1 size.
	 *
	 * @param string $aspect_ratio Aspect ratio, eg '1:1', '16:9'.
	 * @return string Image size, eg '1024x1024'.
	 */
	private function size_for(string $aspect_ratio): string {
		return match ($aspect_ratio) {
			'1:1' => '1024x1024',
			'16:9', '4:3', '3:2' => '1536x1024',
			'9:16', '3:4', '2:3' => '1024x1536',
			default => $this->settings->get_image_size(),
		};
	}

	/**
	 * Save base64 image data as a WordPress attachment.
	 *
	 * @param string   $base64_data Base64-encoded image data.
	 * @param string   $mime_type Image MIME type.
	 * @param \WP_Post $original_attachment Original attachment post.
	 * @return int|\WP_Error New attachment ID or error.
	 */
	private function save_base64_image(string $base64_data, string $mime_type, \WP_Post $original_attachment) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Decode base64 data.
		$image_data = base64_decode($base64_data);

		if ($image_data === false) {
			error_log('RFS AI SEO: save_base64_image - Failed to decode base64 data');
			return new \WP_Error('decode_error', __('Failed to decode image data.', 'rfs-ai-seo'));
		}

		error_log('RFS AI SEO: save_base64_image - Decoded image data: ' . strlen($image_data) . ' bytes, mime: ' . $mime_type);

		// Determine file extension from MIME type.
		$extensions = [
			'image/png' => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		];
		$ext = $extensions[$mime_type] ?? 'png';

		// Create a temporary file with correct extension.
		$temp_base = wp_tempnam('rfs_ai_img_');
		$temp_file = $temp_base . '.' . $ext;

		// Remove the extensionless temp file created by wp_tempnam.
		if ($temp_base !== $temp_file && file_exists($temp_base)) {
			@unlink($temp_base);
		}

		$written = file_put_contents($temp_file, $image_data);

		if ($written === false) {
			error_log('RFS AI SEO: save_base64_image - Failed to write temp file: ' . $temp_file);
			return new \WP_Error('write_error', __('Failed to write temporary image file.', 'rfs-ai-seo'));
		}

		error_log('RFS AI SEO: save_base64_image - Wrote ' . $written . ' bytes to temp file: ' . $temp_file);

		// Build a clean filename from the original title.
		$clean_name = sanitize_file_name($original_attachment->post_title);
		if (empty($clean_name)) {
			$clean_name = 'ai-generated-' . time();
		}

		// Prepare file array.
		$file = [
			'name' => $clean_name . '.' . $ext,
			'type' => $mime_type,
			'tmp_name' => $temp_file,
			'error' => 0,
			'size' => filesize($temp_file),
		];

		// Upload the file — allow any type since we control the source.
		$overrides = [
			'test_form' => false,
			'test_type' => false,
		];

		$uploaded = wp_handle_sideload($file, $overrides);

		if (isset($uploaded['error'])) {
			error_log('RFS AI SEO: save_base64_image - Sideload error: ' . $uploaded['error']);
			@unlink($temp_file);
			return new \WP_Error('upload_error', $uploaded['error']);
		}

		// Use the known MIME type — wp_handle_sideload can return empty type with test_type=false.
		$actual_mime = !empty($uploaded['type']) ? $uploaded['type'] : $mime_type;

		error_log('RFS AI SEO: save_base64_image - File uploaded to: ' . $uploaded['file'] . ' (sideload type: "' . $uploaded['type'] . '", using: "' . $actual_mime . '")');

		// Verify the uploaded file actually exists.
		if (!file_exists($uploaded['file'])) {
			error_log('RFS AI SEO: save_base64_image - Uploaded file does not exist at: ' . $uploaded['file']);
			return new \WP_Error('file_missing', __('Uploaded file not found after sideload.', 'rfs-ai-seo'));
		}

		// Create attachment - use the original title.
		$attachment_data = [
			'post_mime_type' => $actual_mime,
			'post_title' => $original_attachment->post_title,
			'post_content' => $original_attachment->post_content,
			'post_excerpt' => $original_attachment->post_excerpt,
			'post_status' => 'inherit',
		];

		$attachment_id = wp_insert_attachment($attachment_data, $uploaded['file']);

		if (is_wp_error($attachment_id)) {
			error_log('RFS AI SEO: save_base64_image - wp_insert_attachment failed: ' . $attachment_id->get_error_message());
			@unlink($uploaded['file']);
			return $attachment_id;
		}

		error_log('RFS AI SEO: save_base64_image - Attachment created with ID: ' . $attachment_id);

		// Copy alt text from original.
		$alt_text = get_post_meta($original_attachment->ID, '_wp_attachment_image_alt', true);
		if (!empty($alt_text)) {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
		}

		// Generate attachment metadata (thumbnails, sizes, etc.).
		$attach_data = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);

		if (empty($attach_data)) {
			error_log('RFS AI SEO: save_base64_image - wp_generate_attachment_metadata returned EMPTY data for file: ' . $uploaded['file']);
		} elseif (empty($attach_data['sizes'])) {
			error_log('RFS AI SEO: save_base64_image - Metadata generated but NO SIZES (thumbnails). Width: ' . ($attach_data['width'] ?? 'unknown') . ', Height: ' . ($attach_data['height'] ?? 'unknown'));
			error_log('RFS AI SEO: save_base64_image - Metadata: ' . wp_json_encode($attach_data));
		} else {
			$size_names = array_keys($attach_data['sizes']);
			error_log('RFS AI SEO: save_base64_image - Metadata generated successfully. Sizes: ' . implode(', ', $size_names) . '. Dimensions: ' . $attach_data['width'] . 'x' . $attach_data['height']);
		}

		wp_update_attachment_metadata($attachment_id, $attach_data);

		// Verify the thumbnail was created.
		$thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
		$medium_url = wp_get_attachment_image_url($attachment_id, 'medium');
		$full_url = wp_get_attachment_url($attachment_id);
		error_log('RFS AI SEO: save_base64_image - URLs: full=' . ($full_url ?: 'MISSING') . ', medium=' . ($medium_url ?: 'MISSING') . ', thumb=' . ($thumb_url ?: 'MISSING'));

		return $attachment_id;
	}

	/**
	 * Register featured image generator meta box on post types with thumbnail support.
	 *
	 * @return void
	 */
	public function register_featured_image_meta_box(): void {
		$post_types = get_post_types(['public' => true], 'names');

		foreach ($post_types as $post_type) {
			if (post_type_supports($post_type, 'thumbnail')) {
				add_meta_box(
					'rfs-ai-featured-image-generator',
					__('AI Image Generator', 'rfs-ai-seo'),
					[$this, 'render_featured_image_meta_box'],
					$post_type,
					'side',
					'low'
				);
			}
		}
	}

	/**
	 * Render featured image generator meta box.
	 *
	 * Routes to product image grid for WooCommerce products,
	 * or simple featured image UI for all other post types.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_featured_image_meta_box(\WP_Post $post): void {
		$has_key = Portal::has_key('images');

		if (!$has_key) {
			echo '<p class="description" style="color: #d63638;">';
			esc_html_e('AI is not connected. Ask an administrator to run: wp ats secret set gateway_key_images', 'rfs-ai-seo');
			echo '</p>';
			return;
		}

		if ($post->post_type === 'product' && function_exists('wc_get_product')) {
			$this->render_product_image_grid($post);
		} else {
			$this->render_simple_featured_image_ui($post);
		}
	}

	/**
	 * Render the simple featured image generator UI (for non-product post types).
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	private function render_simple_featured_image_ui(\WP_Post $post): void {
		$image_prompts = $this->settings->get_image_prompts();
		?>
		<div id="rfs-ai-featured-generator" data-post-id="<?php echo esc_attr($post->ID); ?>">
			<?php if (!empty($image_prompts)) : ?>
			<div style="margin-bottom: 8px;">
				<label for="rfs-ai-featured-prompt" style="display: block; margin-bottom: 4px; font-weight: 600;">
					<?php esc_html_e('Prompt Template', 'rfs-ai-seo'); ?>
				</label>
				<select id="rfs-ai-featured-prompt" style="width: 100%;">
					<option value=""><?php esc_html_e('-- Select a template --', 'rfs-ai-seo'); ?></option>
					<?php foreach ($image_prompts as $index => $prompt) : ?>
						<option value="<?php echo esc_attr($index); ?>">
							<?php echo esc_html($prompt['title']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div style="margin-bottom: 8px;">
				<label for="rfs-ai-featured-custom-prompt" style="display: block; margin-bottom: 4px; font-weight: 600;">
					<?php esc_html_e('Custom Prompt', 'rfs-ai-seo'); ?>
				</label>
				<textarea id="rfs-ai-featured-custom-prompt" rows="3" style="width: 100%;" placeholder="<?php esc_attr_e('Describe the image you want to generate...', 'rfs-ai-seo'); ?>"></textarea>
				<p class="description"><?php esc_html_e('Used when no template is selected above.', 'rfs-ai-seo'); ?></p>
			</div>

			<div style="margin-bottom: 8px;">
				<label for="rfs-ai-featured-ratio" style="display: block; margin-bottom: 4px; font-weight: 600;">
					<?php esc_html_e('Aspect Ratio', 'rfs-ai-seo'); ?>
				</label>
				<select id="rfs-ai-featured-ratio" style="width: 100%;">
					<option value="1:1"><?php esc_html_e('1:1 (Square)', 'rfs-ai-seo'); ?></option>
					<option value="3:4"><?php esc_html_e('3:4 (Portrait)', 'rfs-ai-seo'); ?></option>
					<option value="4:3"><?php esc_html_e('4:3 (Landscape)', 'rfs-ai-seo'); ?></option>
					<option value="16:9"><?php esc_html_e('16:9 (Widescreen)', 'rfs-ai-seo'); ?></option>
					<option value="9:16"><?php esc_html_e('9:16 (Tall)', 'rfs-ai-seo'); ?></option>
				</select>
			</div>

			<div style="margin-bottom: 8px;">
				<button type="button" class="button button-primary" id="rfs-ai-featured-generate-btn" style="width: 100%;">
					<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>
					<?php esc_html_e('Generate AI Image', 'rfs-ai-seo'); ?>
				</button>
			</div>

			<div id="rfs-ai-featured-progress" style="display: none; text-align: center; padding: 10px 0;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<p class="description"><?php esc_html_e('Generating image...', 'rfs-ai-seo'); ?></p>
			</div>

			<div id="rfs-ai-featured-result" style="display: none;">
				<div style="margin-bottom: 8px; border: 1px solid #ddd; border-radius: 4px; padding: 8px; background: #f9f9f9;">
					<p style="margin: 0 0 8px; font-weight: 600;"><?php esc_html_e('Generated Image:', 'rfs-ai-seo'); ?></p>
					<img id="rfs-ai-featured-preview-img" src="" alt="" style="max-width: 100%; height: auto; border-radius: 4px; display: block;">
				</div>

				<div style="display: flex; gap: 8px;">
					<button type="button" class="button button-primary" id="rfs-ai-featured-set-btn" style="flex: 1;">
						<?php esc_html_e('Set as Featured Image', 'rfs-ai-seo'); ?>
					</button>
					<button type="button" class="button" id="rfs-ai-featured-discard-btn">
						<?php esc_html_e('Discard', 'rfs-ai-seo'); ?>
					</button>
				</div>
			</div>

			<div id="rfs-ai-featured-cost" style="display: none; margin-top: 8px; font-size: 11px; color: #50575e;"></div>

			<?php $estimate = (new Usage_Logger())->average_charge('image_generation'); ?>
			<?php if ($estimate !== null) : ?>
			<p class="description rfs-ai-cost-estimate" style="margin-top: 8px; font-size: 11px;">
				<?php
				printf(
					/* translators: %s: estimated cost */
					esc_html__('About %s per image, based on recent runs', 'rfs-ai-seo'),
					'<strong>' . esc_html($this->settings->format_gbp($estimate)) . '</strong>'
				);
				?>
			</p>
			<?php endif; ?>

			<div id="rfs-ai-featured-status" style="display: none; margin-top: 8px;"></div>
		</div>
		<?php
	}

	/**
	 * Get all product images (featured, gallery, and variation images).
	 *
	 * @param \WC_Product $product WooCommerce product.
	 * @return array Array of image items with id, type, label, thumbnail, product_id, variation_id.
	 */
	private function get_product_images(\WC_Product $product): array {
		$images = [];
		$seen_ids = [];
		$product_id = $product->get_id();

		// 1. Featured/product image.
		$featured_id = $product->get_image_id();
		if ($featured_id) {
			$thumbnail = wp_get_attachment_image_url($featured_id, 'thumbnail');
			if ($thumbnail) {
				$images[] = [
					'id'           => (int) $featured_id,
					'type'         => 'product',
					'label'        => __('Product Image', 'rfs-ai-seo'),
					'thumbnail'    => $thumbnail,
					'product_id'   => $product_id,
					'variation_id' => 0,
				];
				$seen_ids[] = (int) $featured_id;
			}
		}

		// 2. Gallery images.
		$gallery_ids = $product->get_gallery_image_ids();
		if (!empty($gallery_ids)) {
			$gallery_index = 1;
			foreach ($gallery_ids as $gallery_id) {
				$gallery_id = (int) $gallery_id;
				if (in_array($gallery_id, $seen_ids, true)) {
					continue;
				}
				$thumbnail = wp_get_attachment_image_url($gallery_id, 'thumbnail');
				if ($thumbnail) {
					$images[] = [
						'id'           => $gallery_id,
						'type'         => 'gallery',
						'label'        => sprintf(__('Gallery %d', 'rfs-ai-seo'), $gallery_index),
						'thumbnail'    => $thumbnail,
						'product_id'   => $product_id,
						'variation_id' => 0,
					];
					$seen_ids[] = $gallery_id;
					$gallery_index++;
				}
			}
		}

		// 3. Variation images (variable products only).
		if ($product->is_type('variable')) {
			$children = $product->get_children();
			if (!empty($children)) {
				foreach ($children as $variation_id) {
					$variation = wc_get_product($variation_id);
					if (!$variation) {
						continue;
					}

					$var_image_id = $variation->get_image_id();
					if (!$var_image_id || in_array((int) $var_image_id, $seen_ids, true)) {
						continue;
					}

					$thumbnail = wp_get_attachment_image_url($var_image_id, 'thumbnail');
					if (!$thumbnail) {
						continue;
					}

					// Build label from variation attributes.
					$attributes = $variation->get_attributes();
					$attr_parts = [];
					foreach ($attributes as $attr_name => $attr_value) {
						if (!empty($attr_value)) {
							$attr_parts[] = ucfirst(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $attr_value));
						}
					}
					$label = !empty($attr_parts)
						? sprintf(__('Variation: %s', 'rfs-ai-seo'), implode(', ', $attr_parts))
						: sprintf(__('Variation #%d', 'rfs-ai-seo'), $variation_id);

					$images[] = [
						'id'           => (int) $var_image_id,
						'type'         => 'variation',
						'label'        => $label,
						'thumbnail'    => $thumbnail,
						'product_id'   => $product_id,
						'variation_id' => (int) $variation_id,
					];
					$seen_ids[] = (int) $var_image_id;
				}
			}
		}

		return $images;
	}

	/**
	 * Render the product image grid UI for WooCommerce products.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	private function render_product_image_grid(\WP_Post $post): void {
		$product = wc_get_product($post->ID);
		if (!$product) {
			$this->render_simple_featured_image_ui($post);
			return;
		}

		$images = $this->get_product_images($product);
		$image_prompts = $this->settings->get_image_prompts();
		?>
		<div id="rfs-product-image-generator" data-post-id="<?php echo esc_attr($post->ID); ?>">

			<?php if (empty($images)) : ?>
				<p class="description" style="margin-bottom: 8px;">
					<?php esc_html_e('No product images to regenerate. You can generate a new image from a prompt below.', 'rfs-ai-seo'); ?>
				</p>
				<?php
				// Fall back to the simple featured image generator UI when no product images exist.
				$this->render_simple_featured_image_ui($post);
				?>
			<?php else : ?>

				<!-- Image Grid -->
				<div class="rfs-image-grid-header">
					<span class="rfs-image-grid-count">
						<?php echo esc_html(sprintf(_n('%d image', '%d images', count($images), 'rfs-ai-seo'), count($images))); ?>
					</span>
					<span class="rfs-image-grid-select-links">
						<a href="#" id="rfs-select-all"><?php esc_html_e('Select All', 'rfs-ai-seo'); ?></a>
						<span class="sep">|</span>
						<a href="#" id="rfs-deselect-all"><?php esc_html_e('Deselect All', 'rfs-ai-seo'); ?></a>
					</span>
				</div>

				<div class="rfs-image-grid-container">
					<?php foreach ($images as $image) : ?>
						<div class="rfs-image-item" data-image="<?php echo esc_attr(wp_json_encode($image)); ?>">
							<img src="<?php echo esc_url($image['thumbnail']); ?>" alt="<?php echo esc_attr($image['label']); ?>">
							<span class="rfs-image-type-badge rfs-badge-<?php echo esc_attr($image['type']); ?>">
								<?php
								switch ($image['type']) {
									case 'product':
										esc_html_e('Product', 'rfs-ai-seo');
										break;
									case 'gallery':
										esc_html_e('Gallery', 'rfs-ai-seo');
										break;
									case 'variation':
										esc_html_e('Variation', 'rfs-ai-seo');
										break;
								}
								?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Controls -->
				<div id="rfs-image-controls">
					<?php if (!empty($image_prompts)) : ?>
					<div style="margin-bottom: 8px;">
						<label for="rfs-product-prompt" style="display: block; margin-bottom: 4px; font-weight: 600;">
							<?php esc_html_e('Prompt Template', 'rfs-ai-seo'); ?>
						</label>
						<select id="rfs-product-prompt" style="width: 100%;">
							<option value=""><?php esc_html_e('-- Select a template --', 'rfs-ai-seo'); ?></option>
							<?php foreach ($image_prompts as $index => $prompt) : ?>
								<option value="<?php echo esc_attr($index); ?>">
									<?php echo esc_html($prompt['title']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<div style="margin-bottom: 8px;">
						<label for="rfs-product-custom-prompt" style="display: block; margin-bottom: 4px; font-weight: 600;">
							<?php esc_html_e('Custom Prompt', 'rfs-ai-seo'); ?>
						</label>
						<textarea id="rfs-product-custom-prompt" rows="3" style="width: 100%;" placeholder="<?php esc_attr_e('Describe the image style you want...', 'rfs-ai-seo'); ?>"></textarea>
						<p class="description"><?php esc_html_e('Used when no template is selected above.', 'rfs-ai-seo'); ?></p>
					</div>

					<div style="margin-bottom: 8px;">
						<label for="rfs-product-ratio" style="display: block; margin-bottom: 4px; font-weight: 600;">
							<?php esc_html_e('Aspect Ratio', 'rfs-ai-seo'); ?>
						</label>
						<select id="rfs-product-ratio" style="width: 100%;">
							<option value="1:1"><?php esc_html_e('1:1 (Square)', 'rfs-ai-seo'); ?></option>
							<option value="3:4"><?php esc_html_e('3:4 (Portrait)', 'rfs-ai-seo'); ?></option>
							<option value="4:3"><?php esc_html_e('4:3 (Landscape)', 'rfs-ai-seo'); ?></option>
							<option value="16:9"><?php esc_html_e('16:9 (Widescreen)', 'rfs-ai-seo'); ?></option>
							<option value="9:16"><?php esc_html_e('9:16 (Tall)', 'rfs-ai-seo'); ?></option>
						</select>
					</div>

					<div style="margin-bottom: 8px;">
						<span id="rfs-selected-count" style="font-size: 12px; color: #50575e;">
							<?php esc_html_e('0 images selected', 'rfs-ai-seo'); ?>
						</span>
						<span id="rfs-cost-estimate" style="font-size: 11px; color: #50575e; display: block; margin-top: 2px;"></span>
					</div>

					<button type="button" class="button button-primary" id="rfs-product-generate-btn" style="width: 100%;" disabled>
						<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>
						<?php esc_html_e('Generate AI Versions', 'rfs-ai-seo'); ?>
					</button>
				</div>

				<!-- Progress Panel -->
				<div id="rfs-progress-panel" style="display: none;">
					<h4 style="margin: 0 0 8px;"><?php esc_html_e('Generating...', 'rfs-ai-seo'); ?></h4>
					<div class="rfs-product-progress-bar">
						<div class="rfs-product-progress-bar-fill" style="width: 0%;"></div>
					</div>
					<p id="rfs-progress-current" class="description" style="margin: 8px 0;">
						<?php esc_html_e('Preparing...', 'rfs-ai-seo'); ?>
					</p>
					<div id="rfs-progress-log" class="rfs-product-progress-log"></div>
					<button type="button" class="button" id="rfs-stop-btn" style="margin-top: 8px;">
						<?php esc_html_e('Stop', 'rfs-ai-seo'); ?>
					</button>
				</div>

				<!-- Comparison Panel -->
				<div id="rfs-comparison-panel" style="display: none;">
					<h4 style="margin: 0 0 8px;">
						<span id="rfs-comparison-label"></span>
						<span id="rfs-comparison-counter" style="font-weight: normal; color: #50575e;"></span>
					</h4>
					<div class="rfs-comparison-tabs">
						<button type="button" class="rfs-comparison-tab active" data-tab="original">
							<?php esc_html_e('Original', 'rfs-ai-seo'); ?>
						</button>
						<button type="button" class="rfs-comparison-tab" data-tab="generated">
							<?php esc_html_e('Generated', 'rfs-ai-seo'); ?>
						</button>
					</div>
					<div class="rfs-comparison-image-wrap">
						<img id="rfs-comparison-original" src="" alt="" class="rfs-comparison-img active">
						<img id="rfs-comparison-generated" src="" alt="" class="rfs-comparison-img">
					</div>
					<div style="display: flex; gap: 8px; margin-top: 10px;">
						<button type="button" class="button button-primary" id="rfs-replace-btn" style="flex: 1;">
							<?php esc_html_e('Replace', 'rfs-ai-seo'); ?>
						</button>
						<button type="button" class="button" id="rfs-skip-btn" style="flex: 1;">
							<?php esc_html_e('Skip', 'rfs-ai-seo'); ?>
						</button>
					</div>
				</div>

				<!-- Completion Panel -->
				<div id="rfs-completion-panel" style="display: none;">
					<h4 style="margin: 0 0 8px;"><?php esc_html_e('Complete', 'rfs-ai-seo'); ?></h4>
					<div id="rfs-completion-stats" class="rfs-completion-stats"></div>
					<button type="button" class="button" id="rfs-generate-more-btn" style="width: 100%; margin-top: 10px;">
						<?php esc_html_e('Generate More', 'rfs-ai-seo'); ?>
					</button>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for generating a featured image from prompt.
	 *
	 * @return void
	 */
	public function ajax_generate_featured_image(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$prompt_template = isset($_POST['prompt_template']) ? sanitize_text_field($_POST['prompt_template']) : '';
		$custom_prompt = isset($_POST['custom_prompt']) ? sanitize_textarea_field($_POST['custom_prompt']) : '';
		$aspect_ratio = isset($_POST['aspect_ratio']) ? sanitize_text_field($_POST['aspect_ratio']) : '1:1';

		if (!$post_id) {
			wp_send_json_error([
				'message' => __('Invalid post ID.', 'rfs-ai-seo'),
			]);
		}

		// Determine the prompt to use.
		$prompt = '';
		if ($prompt_template !== '') {
			$image_prompts = $this->settings->get_image_prompts();
			$index = (int) $prompt_template;
			if (isset($image_prompts[$index]) && !empty($image_prompts[$index]['description'])) {
				$prompt = $image_prompts[$index]['description'];
			}
		}

		if (empty($prompt) && !empty($custom_prompt)) {
			$prompt = $custom_prompt;
		}

		if (empty($prompt)) {
			wp_send_json_error([
				'message' => __('Please select a prompt template or enter a custom prompt.', 'rfs-ai-seo'),
			]);
		}

		// If there's a current featured image, include it as source for context.
		$source_image = null;
		$current_thumbnail_id = get_post_thumbnail_id($post_id);
		if ($current_thumbnail_id) {
			$source_image = $this->get_source_image_data($current_thumbnail_id);
		}

		// Call the AI service.
		$response = $this->call_image_api($prompt, $this->size_for($aspect_ratio), $source_image, (string) $post_id);

		if (is_wp_error($response)) {
			wp_send_json_error([
				'message' => $response->get_error_message(),
				'code' => $response->get_error_code(),
			]);
		}

		// Save the generated image.
		$post = get_post($post_id);
		$post_title = $post ? $post->post_title : 'AI Generated Image';

		$new_attachment_id = $this->save_featured_image(
			$response['image_data'],
			$response['mime_type'],
			$post_title,
			$post_id
		);

		if (is_wp_error($new_attachment_id)) {
			wp_send_json_error([
				'message' => $new_attachment_id->get_error_message(),
				'code' => $new_attachment_id->get_error_code(),
			]);
		}

		// Mark as AI generated.
		update_post_meta($new_attachment_id, '_rfs_ai_generated', true);
		update_post_meta($new_attachment_id, '_rfs_ai_prompt', $prompt);

		$charged = (float) ($response['charged_gbp'] ?? 0);
		$logger = new Usage_Logger();
		$logger->log($new_attachment_id, 'image_generation', [
			'tokens_prompt' => 0,
			'tokens_completion' => 0,
			'tokens_total' => 0,
			'model' => self::IMAGE_MODEL,
			'charged_gbp' => $charged,
			'generated_title' => $post_title,
			'generated_description' => mb_substr($prompt, 0, 500),
		]);

		// Get URLs.
		$full_url = wp_get_attachment_url($new_attachment_id);
		$medium_url = wp_get_attachment_image_url($new_attachment_id, 'medium');
		$thumbnail_url = $medium_url ?: $full_url;

		wp_send_json_success([
			'attachment_id' => $new_attachment_id,
			'url' => $full_url,
			'thumbnail_url' => $thumbnail_url,
			'cost' => round($charged, 4),
			'cost_formatted' => $this->settings->format_gbp($charged),
			'balance_gbp' => Portal::balance(),
		]);
	}

	/**
	 * AJAX handler for setting generated image as featured image.
	 *
	 * @return void
	 */
	public function ajax_set_featured_image(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('edit_posts')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

		if (!$post_id || !$attachment_id) {
			wp_send_json_error([
				'message' => __('Invalid post or attachment ID.', 'rfs-ai-seo'),
			]);
		}

		$result = set_post_thumbnail($post_id, $attachment_id);

		if ($result) {
			// Get the thumbnail HTML for the postimagediv.
			$thumbnail_html = _wp_post_thumbnail_html($attachment_id, $post_id);

			wp_send_json_success([
				'message' => __('Featured image updated.', 'rfs-ai-seo'),
				'thumbnail_html' => $thumbnail_html,
			]);
		} else {
			wp_send_json_error([
				'message' => __('Failed to set featured image.', 'rfs-ai-seo'),
			]);
		}
	}

	/**
	 * Save a generated image as a WordPress attachment (for featured image use).
	 *
	 * @param string $base64_data Base64-encoded image data.
	 * @param string $mime_type Image MIME type.
	 * @param string $title Title for the attachment.
	 * @param int    $parent_id Parent post ID.
	 * @return int|\WP_Error New attachment ID or error.
	 */
	private function save_featured_image(string $base64_data, string $mime_type, string $title, int $parent_id) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$image_data = base64_decode($base64_data);

		if ($image_data === false) {
			return new \WP_Error('decode_error', __('Failed to decode image data.', 'rfs-ai-seo'));
		}

		$extensions = [
			'image/png' => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		];
		$ext = $extensions[$mime_type] ?? 'png';

		$temp_base = wp_tempnam('rfs_ai_feat_');
		$temp_file = $temp_base . '.' . $ext;

		if ($temp_base !== $temp_file && file_exists($temp_base)) {
			@unlink($temp_base);
		}

		$written = file_put_contents($temp_file, $image_data);

		if ($written === false) {
			return new \WP_Error('write_error', __('Failed to write temporary image file.', 'rfs-ai-seo'));
		}

		$clean_name = sanitize_file_name($title);
		if (empty($clean_name)) {
			$clean_name = 'ai-generated-' . time();
		}

		$file = [
			'name' => $clean_name . '-ai.' . $ext,
			'type' => $mime_type,
			'tmp_name' => $temp_file,
			'error' => 0,
			'size' => filesize($temp_file),
		];

		$overrides = [
			'test_form' => false,
			'test_type' => false,
		];

		$uploaded = wp_handle_sideload($file, $overrides);

		if (isset($uploaded['error'])) {
			@unlink($temp_file);
			return new \WP_Error('upload_error', $uploaded['error']);
		}

		$actual_mime = !empty($uploaded['type']) ? $uploaded['type'] : $mime_type;

		$attachment_data = [
			'post_mime_type' => $actual_mime,
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'inherit',
			'post_parent' => $parent_id,
		];

		$attachment_id = wp_insert_attachment($attachment_data, $uploaded['file'], $parent_id);

		if (is_wp_error($attachment_id)) {
			@unlink($uploaded['file']);
			return $attachment_id;
		}

		$attach_data = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
		wp_update_attachment_metadata($attachment_id, $attach_data);

		return $attachment_id;
	}

	/**
	 * Resolve prompt from template index or custom prompt text.
	 *
	 * @param string $template_index Template index or empty string.
	 * @param string $custom_prompt Custom prompt text.
	 * @return string Resolved prompt.
	 */
	private function get_prompt_from_template_or_custom(string $template_index, string $custom_prompt): string {
		if ($template_index !== '') {
			$image_prompts = $this->settings->get_image_prompts();
			$index = (int) $template_index;
			if (isset($image_prompts[$index]) && !empty($image_prompts[$index]['description'])) {
				return $image_prompts[$index]['description'];
			}
		}

		return $custom_prompt;
	}

	/**
	 * AJAX handler for generating AI version of a single product image.
	 *
	 * @return void
	 */
	public function ajax_generate_product_image(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
		$prompt_template = isset($_POST['prompt_template']) ? sanitize_text_field($_POST['prompt_template']) : '';
		$custom_prompt = isset($_POST['custom_prompt']) ? sanitize_textarea_field($_POST['custom_prompt']) : '';
		$aspect_ratio = isset($_POST['aspect_ratio']) ? sanitize_text_field($_POST['aspect_ratio']) : '1:1';

		if (!$attachment_id) {
			wp_send_json_error([
				'message' => __('Invalid attachment ID.', 'rfs-ai-seo'),
			]);
		}

		// Determine prompt.
		$prompt = $this->get_prompt_from_template_or_custom($prompt_template, $custom_prompt);

		if (empty($prompt)) {
			wp_send_json_error([
				'message' => __('Please select a prompt template or enter a custom prompt.', 'rfs-ai-seo'),
			]);
		}

		// Get source image data.
		$source_image = $this->get_source_image_data($attachment_id);

		// Call the AI service.
		$response = $this->call_image_api($prompt, $this->size_for($aspect_ratio), $source_image, (string) $attachment_id);

		if (is_wp_error($response)) {
			wp_send_json_error([
				'message' => $response->get_error_message(),
				'code' => $response->get_error_code(),
			]);
		}

		// Save generated image using existing attachment as template.
		$original_attachment = get_post($attachment_id);
		if (!$original_attachment) {
			wp_send_json_error([
				'message' => __('Original attachment not found.', 'rfs-ai-seo'),
			]);
		}

		$new_attachment_id = $this->save_base64_image(
			$response['image_data'],
			$response['mime_type'],
			$original_attachment
		);

		if (is_wp_error($new_attachment_id)) {
			wp_send_json_error([
				'message' => $new_attachment_id->get_error_message(),
				'code' => $new_attachment_id->get_error_code(),
			]);
		}

		// Store reference to original image.
		update_post_meta($new_attachment_id, '_rfs_ai_original_image_id', $attachment_id);
		update_post_meta($new_attachment_id, '_rfs_ai_generated', true);
		update_post_meta($new_attachment_id, '_rfs_ai_prompt', $prompt);

		$charged = (float) ($response['charged_gbp'] ?? 0);
		$logger = new Usage_Logger();
		$logger->log($new_attachment_id, 'image_generation', [
			'tokens_prompt' => 0,
			'tokens_completion' => 0,
			'tokens_total' => 0,
			'model' => self::IMAGE_MODEL,
			'charged_gbp' => $charged,
			'generated_title' => $original_attachment->post_title,
			'generated_description' => mb_substr($prompt, 0, 500),
		]);

		// Get URLs for comparison display.
		$original_medium = wp_get_attachment_image_url($attachment_id, 'medium');
		$original_url = $original_medium ?: wp_get_attachment_url($attachment_id);

		$generated_medium = wp_get_attachment_image_url($new_attachment_id, 'medium');
		$generated_url = $generated_medium ?: wp_get_attachment_url($new_attachment_id);

		wp_send_json_success([
			'new_attachment_id' => $new_attachment_id,
			'original_url' => $original_url,
			'generated_url' => $generated_url,
			'cost' => round($charged, 4),
			'cost_formatted' => $this->settings->format_gbp($charged),
			'balance_gbp' => Portal::balance(),
		]);
	}

	/**
	 * AJAX handler for replacing a product image (featured, gallery, or variation).
	 *
	 * @return void
	 */
	public function ajax_replace_product_image(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('edit_products')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$image_type = isset($_POST['image_type']) ? sanitize_text_field($_POST['image_type']) : '';
		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$old_attachment_id = isset($_POST['old_attachment_id']) ? absint($_POST['old_attachment_id']) : 0;
		$new_attachment_id = isset($_POST['new_attachment_id']) ? absint($_POST['new_attachment_id']) : 0;
		$variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;

		if (!$product_id || !$old_attachment_id || !$new_attachment_id || !$image_type) {
			wp_send_json_error([
				'message' => __('Missing required parameters.', 'rfs-ai-seo'),
			]);
		}

		switch ($image_type) {
			case 'product':
				// Replace featured/product image.
				set_post_thumbnail($product_id, $new_attachment_id);
				$thumbnail_html = _wp_post_thumbnail_html($new_attachment_id, $product_id);

				wp_send_json_success([
					'message' => __('Product image replaced.', 'rfs-ai-seo'),
					'thumbnail_html' => $thumbnail_html,
				]);
				break;

			case 'gallery':
				// Replace gallery image.
				$product = wc_get_product($product_id);
				if (!$product) {
					wp_send_json_error([
						'message' => __('Product not found.', 'rfs-ai-seo'),
					]);
				}

				$gallery_ids = $product->get_gallery_image_ids();
				$updated_ids = array_map(function ($id) use ($old_attachment_id, $new_attachment_id) {
					return (int) $id === (int) $old_attachment_id ? (int) $new_attachment_id : (int) $id;
				}, $gallery_ids);

				$product->set_gallery_image_ids($updated_ids);
				$product->save();

				$gallery_html = $this->get_gallery_item_html($new_attachment_id);
				$gallery_ids_string = implode(',', $updated_ids);

				wp_send_json_success([
					'message' => __('Gallery image replaced.', 'rfs-ai-seo'),
					'gallery_html' => $gallery_html,
					'gallery_ids' => $gallery_ids_string,
				]);
				break;

			case 'variation':
				// Replace variation image.
				if (!$variation_id) {
					wp_send_json_error([
						'message' => __('Variation ID is required.', 'rfs-ai-seo'),
					]);
				}

				update_post_meta($variation_id, '_thumbnail_id', $new_attachment_id);
				$new_thumb_url = wp_get_attachment_image_url($new_attachment_id, 'thumbnail');

				wp_send_json_success([
					'message' => __('Variation image replaced.', 'rfs-ai-seo'),
					'thumbnail_url' => $new_thumb_url,
				]);
				break;

			default:
				wp_send_json_error([
					'message' => __('Invalid image type.', 'rfs-ai-seo'),
				]);
		}
	}

	/**
	 * AJAX handler for getting individual images for bulk image generator.
	 *
	 * Returns a flat list of images (one row per image) instead of grouping by product.
	 *
	 * @return void
	 */
	public function ajax_get_image_items(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$category = isset($_POST['category']) ? absint($_POST['category']) : 0;
		$image_status = isset($_POST['image_status']) ? sanitize_text_field($_POST['image_status']) : 'all';
		$search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

		// Build WP_Query args.
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ($category > 0) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category,
				],
			];
		}

		if (!empty($search)) {
			$args['s'] = $search;
		}

		$query = new \WP_Query($args);
		$product_ids = $query->posts;

		$items = [];
		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if (!$product) {
				continue;
			}

			$images = $this->get_product_images($product);

			// Skip products with no images if filter is 'has_images'.
			if ($image_status === 'has_images' && empty($images)) {
				continue;
			}
			// Skip products with images if filter is 'no_images'.
			if ($image_status === 'no_images' && !empty($images)) {
				continue;
			}

			$product_name = $product->get_name();
			$edit_url = get_edit_post_link($product_id, 'raw');

			foreach ($images as $img) {
				$items[] = [
					'attachment_id'  => $img['id'],
					'product_id'     => $product_id,
					'product_name'   => $product_name,
					'edit_url'       => $edit_url,
					'thumbnail'      => $img['thumbnail'],
					'type'           => $img['type'],
					'label'          => $img['label'],
					'variation_id'   => $img['variation_id'] ?? 0,
				];
			}
		}

		wp_send_json_success([
			'items' => $items,
			'total' => count($items),
		]);
	}

	/**
	 * Get WooCommerce-compatible gallery item HTML for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Gallery item HTML.
	 */
	private function get_gallery_item_html(int $attachment_id): string {
		$image = wp_get_attachment_image($attachment_id, 'thumbnail');

		return sprintf(
			'<li class="image" data-attachment_id="%d"><div class="attachment-preview"><div class="thumbnail">%s</div></div><ul class="actions"><li><a href="#" class="delete" title="%s">%s</a></li></ul></li>',
			$attachment_id,
			$image,
			esc_attr__('Remove image', 'rfs-ai-seo'),
			esc_html__('Delete', 'rfs-ai-seo')
		);
	}
}
