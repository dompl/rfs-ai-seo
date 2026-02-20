<?php
/**
 * Alt Text Generator Class
 *
 * Handles AI-powered image alt text generation.
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Alt_Text_Generator class.
 */
class Alt_Text_Generator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('wp_ajax_rfs_ai_generate_alt_text', [$this, 'ajax_generate_alt_text']);
		add_action('wp_ajax_rfs_ai_save_alt_text', [$this, 'ajax_save_alt_text']);
		add_action('wp_ajax_rfs_ai_bulk_generate_alt_text', [$this, 'ajax_bulk_generate_alt_text']);
		add_action('wp_ajax_rfs_ai_get_alt_text_items', [$this, 'ajax_get_alt_text_items']);
	}

	/**
	 * Generate alt text for a single image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $product_id   Product ID for context.
	 * @return array|\WP_Error Result array or WP_Error on failure.
	 */
	public function generate_for_image(int $attachment_id, int $product_id): array|\WP_Error {
		// Check spending cap / credit balance.
		$logger = new Usage_Logger();
		$cap_check = $logger->check_spending_cap();
		if (!$cap_check['allowed']) {
			return new \WP_Error('spending_cap', $cap_check['message']);
		}

		// Validate attachment exists and is an image.
		$attachment = get_post($attachment_id);
		if (!$attachment || !wp_attachment_is_image($attachment_id)) {
			return new \WP_Error('invalid_attachment', __('Invalid image attachment.', 'rfs-ai-seo'));
		}

		// Collect product context.
		$content_collector = new Content_Collector();
		$product = wc_get_product($product_id);
		if (!$product) {
			return new \WP_Error('invalid_product', __('Invalid product ID.', 'rfs-ai-seo'));
		}

		// Determine if this is a variation.
		$data = $product->is_type('variation')
			? $content_collector->collect_variation_data($product_id)
			: $content_collector->collect_product_data($product_id);

		// Gather image-specific context.
		$settings = new Settings();
		$context = [
			'image_title'            => $attachment->post_title,
			'image_caption'          => $attachment->post_excerpt,
			'current_alt'            => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
			'company_name'           => $settings->get_company_name(),
			'product_name'           => $data['product_name'] ?? '',
			'categories'             => $data['categories'] ?? '',
			'short_description'      => $data['short_description'] ?? '',
			'product_description'    => $data['product_description'] ?? '',
			'variation_description'  => $data['variation_description'] ?? '',
		];

		// Build prompt.
		$prompt = $this->build_alt_text_prompt($context);

		// Get OpenAI client.
		$api_key = $settings->get_api_key();
		$model = $settings->get_option('rfs_ai_seo_openai_model', 'gpt-4o');

		if (empty($api_key)) {
			return new \WP_Error('no_api_key', __('OpenAI API key not configured.', 'rfs-ai-seo'));
		}

		$openai = new OpenAI_Client($api_key, $model);
		$result = $openai->generate_alt_text($prompt);

		if (is_wp_error($result)) {
			return $result;
		}

		// Log generation.
		$logger->log(
			$attachment_id,
			'alt_text',
			[
				'tokens_prompt'        => $result['tokens']['prompt'] ?? 0,
				'tokens_completion'    => $result['tokens']['completion'] ?? 0,
				'tokens_total'         => $result['tokens']['total'] ?? 0,
				'model'                => $model,
				'generated_title'      => '',
				'generated_description' => $result['alt_text'],
			]
		);

		return [
			'alt_text' => $result['alt_text'],
			'tokens'   => $result['tokens'],
		];
	}

	/**
	 * Build the alt text generation prompt.
	 *
	 * @param array $context Image and product context.
	 * @return string Prompt text.
	 */
	private function build_alt_text_prompt(array $context): string {
		$prompt = "Generate SEO-friendly alt text for this product image.\n\n";
		$prompt .= "IMAGE CONTEXT:\n";
		$prompt .= "- Image title: {$context['image_title']}\n";
		$prompt .= "- Image caption: {$context['image_caption']}\n\n";

		$prompt .= "PRODUCT CONTEXT:\n";

		if (!empty($context['company_name'])) {
			$prompt .= "- Company: {$context['company_name']}\n";
		}

		$prompt .= "- Product name: {$context['product_name']}\n";

		if (!empty($context['categories'])) {
			$prompt .= "- Categories: {$context['categories']}\n";
		}

		if (!empty($context['short_description'])) {
			$prompt .= "- Short description: {$context['short_description']}\n";
		}

		if (!empty($context['product_description'])) {
			$prompt .= "- Description: " . wp_trim_words(wp_strip_all_tags($context['product_description']), 60, '...') . "\n";
		}

		if (!empty($context['variation_description'])) {
			$prompt .= "- Variation: {$context['variation_description']}\n";
		}

		$prompt .= "\nREQUIREMENTS:\n";
		$prompt .= "- Maximum 125 characters\n";
		$prompt .= "- Describe what the image likely shows in relation to the product\n";
		$prompt .= "- Include the product name naturally\n";

		if (!empty($context['company_name'])) {
			$prompt .= "- Include the company/brand name if it fits\n";
		}

		$prompt .= "- Do not start with \"Image of\" or \"Photo of\"\n";
		$prompt .= "- Be specific, not generic\n\n";

		$prompt .= "RESPOND IN JSON FORMAT ONLY:\n";
		$prompt .= '{"alt_text": "your alt text here"}';

		return $prompt;
	}

	/**
	 * Get all images for a product (featured + gallery + variation images, deduplicated).
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of image data arrays.
	 */
	public function get_product_images(int $product_id): array {
		$product = wc_get_product($product_id);
		if (!$product) {
			return [];
		}

		$images = [];
		$seen_ids = [];

		// Featured image.
		$featured_id = $product->get_image_id();
		if ($featured_id) {
			$seen_ids[] = $featured_id;
			$images[] = $this->build_image_data($featured_id, 'featured');
		}

		// Gallery images.
		$gallery_ids = $product->get_gallery_image_ids();
		foreach ($gallery_ids as $gallery_id) {
			if (!in_array($gallery_id, $seen_ids, true)) {
				$seen_ids[] = $gallery_id;
				$images[] = $this->build_image_data($gallery_id, 'gallery');
			}
		}

		// Variation images.
		if ($product->is_type('variable')) {
			$variations = $product->get_available_variations();
			foreach ($variations as $variation) {
				$var_image_id = $variation['image_id'] ?? 0;
				if ($var_image_id && !in_array($var_image_id, $seen_ids, true)) {
					$seen_ids[] = $var_image_id;
					$images[] = $this->build_image_data($var_image_id, 'variation');
				}
			}
		}

		return $images;
	}

	/**
	 * Build image data array for a single attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $type          Image type (featured, gallery, variation).
	 * @return array Image data.
	 */
	private function build_image_data(int $attachment_id, string $type): array {
		$thumbnail = wp_get_attachment_image_url($attachment_id, 'thumbnail');
		$current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

		return [
			'attachment_id' => $attachment_id,
			'type'          => $type,
			'thumbnail'     => $thumbnail ?: '',
			'current_alt'   => $current_alt ?: '',
			'has_alt'       => !empty($current_alt),
		];
	}

	/**
	 * AJAX handler: generate alt text for a single image (preview for meta box).
	 *
	 * @return void
	 */
	public function ajax_generate_alt_text(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$attachment_id || !$product_id) {
			wp_send_json_error(['message' => __('Invalid attachment or product ID.', 'rfs-ai-seo')]);
		}

		$result = $this->generate_for_image($attachment_id, $product_id);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler: save alt text to attachment.
	 *
	 * @return void
	 */
	public function ajax_save_alt_text(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
		$alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';

		if (!$attachment_id || empty($alt_text)) {
			wp_send_json_error(['message' => __('Invalid data.', 'rfs-ai-seo')]);
		}

		update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

		wp_send_json_success(['message' => __('Alt text saved successfully.', 'rfs-ai-seo')]);
	}

	/**
	 * AJAX handler: generate and auto-save alt text (for bulk page).
	 *
	 * @return void
	 */
	public function ajax_bulk_generate_alt_text(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$attachment_id || !$product_id) {
			wp_send_json_error(['message' => __('Invalid attachment or product ID.', 'rfs-ai-seo')]);
		}

		$result = $this->generate_for_image($attachment_id, $product_id);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		// Auto-save alt text.
		update_post_meta($attachment_id, '_wp_attachment_image_alt', $result['alt_text']);

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler: get products with image alt text status for bulk table.
	 *
	 * @return void
	 */
	public function ajax_get_alt_text_items(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$category = isset($_POST['category']) ? absint($_POST['category']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
		$search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
		$per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 100;
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;

		$args = [
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
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
		$items = [];

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$product_id = get_the_ID();

				$product_images = $this->get_product_images($product_id);
				$total_images = count($product_images);

				if ($total_images === 0) {
					continue;
				}

				$missing_alt = 0;
				$image_list = [];
				foreach ($product_images as $img) {
					if (!$img['has_alt']) {
						$missing_alt++;
					}
					$image_list[] = [
						'attachment_id' => $img['attachment_id'],
						'type'          => $img['type'],
						'thumbnail'     => $img['thumbnail'],
						'current_alt'   => $img['current_alt'],
						'has_alt'       => $img['has_alt'],
					];
				}

				// Filter by status.
				if ($status === 'missing_alt' && $missing_alt === 0) {
					continue;
				}
				if ($status === 'has_alt' && $missing_alt > 0) {
					continue;
				}

				$thumbnail = get_the_post_thumbnail_url($product_id, 'thumbnail');

				$items[] = [
					'id'            => $product_id,
					'name'          => get_the_title(),
					'edit_url'      => get_edit_post_link($product_id),
					'thumbnail'     => $thumbnail ?: '',
					'total_images'  => $total_images,
					'missing_alt'   => $missing_alt,
					'images'        => $image_list,
				];
			}

			wp_reset_postdata();
		}

		wp_send_json_success([
			'items' => $items,
			'total' => count($items),
		]);
	}

}
