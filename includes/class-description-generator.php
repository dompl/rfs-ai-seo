<?php
/**
 * Description Generator Class
 *
 * Handles AI-powered product description generation.
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Description_Generator class.
 */
class Description_Generator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register AJAX handlers.
		add_action('wp_ajax_rfs_ai_generate_description', [$this, 'ajax_generate_description']);
		add_action('wp_ajax_rfs_ai_save_description', [$this, 'ajax_save_description']);
		add_action('wp_ajax_rfs_ai_restore_description', [$this, 'ajax_restore_description']);
		add_action('wp_ajax_rfs_ai_bulk_generate_description', [$this, 'ajax_bulk_generate_description']);
	}

	/**
	 * Generate description for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array|WP_Error Result array or WP_Error on failure.
	 */
	public function generate_for_product(int $product_id): array|\WP_Error {
		// Get product.
		$product = wc_get_product($product_id);
		if (!$product) {
			return new \WP_Error('invalid_product', __('Invalid product ID.', 'rfs-ai-seo'));
		}

		// Collect product data.
		$content_collector = new Content_Collector();
		$data = $content_collector->collect_product_data($product_id);

		// Get AI trainer context.
		$business_context = get_option('rfs_ai_trainer_business_context', '');

		// Build prompt.
		$prompt = $this->build_description_prompt($data, $business_context);

		// Get OpenAI client.
		$settings = new Settings();
		$api_key = $settings->get_api_key();
		$model = $settings->get_option('rfs_ai_seo_openai_model', 'gpt-4o');

		if (empty($api_key)) {
			return new \WP_Error('no_api_key', __('OpenAI API key not configured.', 'rfs-ai-seo'));
		}

		$openai = new OpenAI_Client($api_key, $model);

		// Generate description.
		$result = $openai->generate_description($prompt);

		if (is_wp_error($result)) {
			return $result;
		}

		// Log generation.
		$logger = new Usage_Logger();
		$logger->log(
			$product_id,
			'product_description',
			[
				'tokens_prompt' => $result['tokens']['prompt'] ?? 0,
				'tokens_completion' => $result['tokens']['completion'] ?? 0,
				'tokens_total' => $result['tokens']['total'] ?? 0,
				'model' => $model,
				'generated_title' => '',
				'generated_description' => $result['description'],
			]
		);

		return [
			'success' => true,
			'description' => $result['description'],
			'tokens' => $result['tokens'],
			'current_description' => $product->get_description(),
		];
	}

	/**
	 * Build description generation prompt.
	 *
	 * @param array  $data Product data.
	 * @param string $business_context Business context from AI Trainer.
	 * @return string Prompt text.
	 */
	private function build_description_prompt(array $data, string $business_context): string {
		$prompt = "Generate a compelling product description for this WooCommerce product.\n\n";

		// Add business context if available.
		if (!empty($business_context)) {
			$prompt .= "BUSINESS CONTEXT:\n";
			$prompt .= $business_context . "\n\n";
		}

		// Add product data.
		$prompt .= "PRODUCT DATA:\n";
		$prompt .= "- Name: {$data['product_name']}\n";

		if (!empty($data['product_description'])) {
			$prompt .= "- Current Description: {$data['product_description']}\n";
		}

		if (!empty($data['short_description'])) {
			$prompt .= "- Short Description: {$data['short_description']}\n";
		}

		if (!empty($data['categories'])) {
			$prompt .= "- Categories: {$data['categories']}\n";
		}

		if (!empty($data['attributes'])) {
			$prompt .= "- Attributes: {$data['attributes']}\n";
		}

		if (!empty($data['acf_all'])) {
			$prompt .= "- Additional Information: {$data['acf_all']}\n";
		}

		$prompt .= "\nREQUIREMENTS:\n";
		$prompt .= "- Create a detailed, engaging product description\n";
		$prompt .= "- Highlight key features and benefits\n";
		$prompt .= "- Use the business context to maintain brand voice and style\n";
		$prompt .= "- If a current description exists, improve upon it while keeping valuable details\n";
		$prompt .= "- Make it SEO-friendly but natural-sounding\n";
		$prompt .= "- Length: 150-300 words\n\n";

		$prompt .= "RESPOND IN JSON FORMAT ONLY:\n";
		$prompt .= '{"description": "your product description here"}';

		return $prompt;
	}

	/**
	 * Save original description before overwriting.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function save_original_description(int $product_id): void {
		// Check if we already have original saved.
		$existing_original = get_post_meta($product_id, '_rfs_ai_original_description', true);

		if (empty($existing_original)) {
			// Save current description as original.
			$product = wc_get_product($product_id);
			if ($product) {
				$current_description = $product->get_description();
				if (!empty($current_description)) {
					update_post_meta($product_id, '_rfs_ai_original_description', $current_description);
					update_post_meta($product_id, '_rfs_ai_original_saved_at', current_time('mysql'));
				}
			}
		}
	}

	/**
	 * Restore original description.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True on success, false on failure.
	 */
	public function restore_original_description(int $product_id): bool {
		$original_description = get_post_meta($product_id, '_rfs_ai_original_description', true);

		if (empty($original_description)) {
			return false;
		}

		// Update product description.
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}

		$product->set_description($original_description);
		$product->save();

		// Clear the saved original.
		delete_post_meta($product_id, '_rfs_ai_original_description');
		delete_post_meta($product_id, '_rfs_ai_original_saved_at');

		return true;
	}

	/**
	 * AJAX handler for generating description.
	 *
	 * @return void
	 */
	public function ajax_generate_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$result = $this->generate_for_product($product_id);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler for saving description.
	 *
	 * @return void
	 */
	public function ajax_save_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';

		if (!$product_id || empty($description)) {
			wp_send_json_error(['message' => __('Invalid data.', 'rfs-ai-seo')]);
		}

		// Save original first.
		$this->save_original_description($product_id);

		// Update product description.
		$product = wc_get_product($product_id);
		if (!$product) {
			wp_send_json_error(['message' => __('Product not found.', 'rfs-ai-seo')]);
		}

		$product->set_description($description);
		$product->save();

		wp_send_json_success(['message' => __('Description saved successfully.', 'rfs-ai-seo')]);
	}

	/**
	 * AJAX handler for restoring original description.
	 *
	 * @return void
	 */
	public function ajax_restore_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$restored = $this->restore_original_description($product_id);

		if (!$restored) {
			wp_send_json_error(['message' => __('No original description found.', 'rfs-ai-seo')]);
		}

		// Get the restored description.
		$product = wc_get_product($product_id);
		$description = $product ? $product->get_description() : '';

		wp_send_json_success([
			'message' => __('Original description restored.', 'rfs-ai-seo'),
			'description' => $description,
		]);
	}

	/**
	 * AJAX handler for bulk description generation.
	 *
	 * @return void
	 */
	public function ajax_bulk_generate_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$result = $this->generate_for_product($product_id);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		// Auto-save the description.
		$this->save_original_description($product_id);

		$product = wc_get_product($product_id);
		if ($product) {
			$product->set_description($result['description']);
			$product->save();
		}

		wp_send_json_success($result);
	}
}
