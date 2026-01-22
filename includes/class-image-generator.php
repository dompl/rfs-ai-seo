<?php
/**
 * Image Generator Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Image Generator Class
 *
 * Handles AI-powered image generation using DALL-E.
 *
 * @class Image_Generator
 */
class Image_Generator {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = new Settings();

		// Register AJAX handlers.
		add_action('wp_ajax_rfs_ai_generate_image', [$this, 'ajax_generate_image']);

		// Add custom fields to attachment details.
		add_filter('attachment_fields_to_edit', [$this, 'add_attachment_fields'], 10, 2);
		add_filter('attachment_fields_to_save', [$this, 'save_attachment_fields'], 10, 2);

		// Add custom column to media library.
		add_filter('manage_media_columns', [$this, 'add_media_column']);
		add_action('manage_media_custom_column', [$this, 'render_media_column'], 10, 2);
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
		$original_id = get_post_meta($post->ID, '_rfs_ai_original_image_id', true);

		// Add AI Image Description field.
		$form_fields['rfs_ai_image_description'] = [
			'label' => __('AI Image Description', 'rfs-ai-seo'),
			'input' => 'textarea',
			'value' => $ai_description,
			'helps' => __('Additional description for AI image generation. This will be used along with the standard description when regenerating the image.', 'rfs-ai-seo'),
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
			$form_fields['rfs_ai_original'] = [
				'label' => __('Original Image', 'rfs-ai-seo'),
				'input' => 'html',
				'html' => sprintf(
					'<a href="%s" target="_blank"><img src="%s" alt="" style="max-width: 150px; border: 1px solid #ddd; border-radius: 4px;"></a>',
					wp_get_attachment_url($original_id),
					$original_image
				),
			];
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
		if (isset($attachment['rfs_ai_image_description'])) {
			update_post_meta(
				$post['ID'],
				'_rfs_ai_image_description',
				sanitize_textarea_field($attachment['rfs_ai_image_description'])
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
		ob_start();
		?>
		<div class="rfs-ai-image-generator">
			<button
				type="button"
				class="button button-primary rfs-ai-regenerate-image-btn"
				data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
				<span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
				<?php esc_html_e('Regenerate Image with AI', 'rfs-ai-seo'); ?>
			</button>
			<span class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
			<p class="description">
				<?php esc_html_e('Generate a new version of this image using AI. The original image will be preserved.', 'rfs-ai-seo'); ?>
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

		if (!$attachment_id) {
			wp_send_json_error([
				'message' => __('Invalid attachment ID.', 'rfs-ai-seo'),
			]);
		}

		// Generate image.
		$result = $this->generate_image($attachment_id);

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
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
			return new \WP_Error('empty_prompt', __('Could not generate a prompt for this image. Please add a description or AI image description.', 'rfs-ai-seo'));
		}

		// Get API key.
		$api_key = $this->settings->get_api_key();

		if (empty($api_key)) {
			return new \WP_Error('no_api_key', __('OpenAI API key is not configured.', 'rfs-ai-seo'));
		}

		// Call DALL-E API.
		$response = $this->call_dalle_api($api_key, $prompt);

		if (is_wp_error($response)) {
			return $response;
		}

		// Download and save the new image.
		$new_attachment_id = $this->download_and_save_image($response['url'], $attachment);

		if (is_wp_error($new_attachment_id)) {
			return $new_attachment_id;
		}

		// Store reference to original image.
		update_post_meta($new_attachment_id, '_rfs_ai_original_image_id', $attachment_id);
		update_post_meta($new_attachment_id, '_rfs_ai_generated', true);
		update_post_meta($new_attachment_id, '_rfs_ai_prompt', $prompt);

		return [
			'new_attachment_id' => $new_attachment_id,
			'new_attachment_url' => wp_get_attachment_url($new_attachment_id),
			'thumbnail_url' => wp_get_attachment_image_url($new_attachment_id, 'medium'),
			'prompt' => $prompt,
		];
	}

	/**
	 * Build prompt from attachment data.
	 *
	 * @param \WP_Post $attachment Attachment post object.
	 * @return string Prompt text.
	 */
	private function build_prompt(\WP_Post $attachment): string {
		$parts = [];

		// Get attachment title.
		if (!empty($attachment->post_title)) {
			$parts[] = $attachment->post_title;
		}

		// Get attachment description (content).
		if (!empty($attachment->post_content)) {
			$parts[] = $attachment->post_content;
		}

		// Get AI image description.
		$ai_description = get_post_meta($attachment->ID, '_rfs_ai_image_description', true);
		if (!empty($ai_description)) {
			$parts[] = $ai_description;
		}

		// Get alt text.
		$alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
		if (!empty($alt_text)) {
			$parts[] = $alt_text;
		}

		if (empty($parts)) {
			return '';
		}

		return implode('. ', $parts);
	}

	/**
	 * Call DALL-E API to generate image.
	 *
	 * @param string $api_key OpenAI API key.
	 * @param string $prompt Image prompt.
	 * @return array|\WP_Error API response or error.
	 */
	private function call_dalle_api(string $api_key, string $prompt) {
		$model = $this->settings->get_image_model();
		$size = $this->settings->get_image_size();
		$quality = $this->settings->get_image_quality();
		$style = $this->settings->get_image_style();

		$body = [
			'model' => $model,
			'prompt' => $prompt,
			'n' => 1,
			'size' => $size,
		];

		// Add quality and style for DALL-E 3.
		if ($model === 'dall-e-3') {
			$body['quality'] = $quality;
			$body['style'] = $style;
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/images/generations',
			[
				'timeout' => 60,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode($body),
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (empty($data['data'][0]['url'])) {
			$error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API error.', 'rfs-ai-seo');
			return new \WP_Error('api_error', $error_message);
		}

		return [
			'url' => $data['data'][0]['url'],
			'revised_prompt' => $data['data'][0]['revised_prompt'] ?? $prompt,
		];
	}

	/**
	 * Download and save generated image.
	 *
	 * @param string   $image_url Image URL from API.
	 * @param \WP_Post $original_attachment Original attachment post.
	 * @return int|\WP_Error New attachment ID or error.
	 */
	private function download_and_save_image(string $image_url, \WP_Post $original_attachment) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the image.
		$temp_file = download_url($image_url);

		if (is_wp_error($temp_file)) {
			return $temp_file;
		}

		// Prepare file array.
		$file = [
			'name' => 'ai-generated-' . time() . '.png',
			'type' => 'image/png',
			'tmp_name' => $temp_file,
			'error' => 0,
			'size' => filesize($temp_file),
		];

		// Upload the file.
		$overrides = [
			'test_form' => false,
			'test_type' => false,
		];

		$uploaded = wp_handle_sideload($file, $overrides);

		if (isset($uploaded['error'])) {
			@unlink($temp_file);
			return new \WP_Error('upload_error', $uploaded['error']);
		}

		// Create attachment.
		$attachment_data = [
			'post_mime_type' => $uploaded['type'],
			'post_title' => $original_attachment->post_title . ' (AI Generated)',
			'post_content' => $original_attachment->post_content,
			'post_status' => 'inherit',
		];

		$attachment_id = wp_insert_attachment($attachment_data, $uploaded['file']);

		if (is_wp_error($attachment_id)) {
			@unlink($uploaded['file']);
			return $attachment_id;
		}

		// Generate attachment metadata.
		$attach_data = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
		wp_update_attachment_metadata($attachment_id, $attach_data);

		return $attachment_id;
	}
}
