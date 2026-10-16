<?php
/**
 * Description Generator Class
 *
 * Handles AI-powered product description generation.
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
 * Description_Generator class.
 */
class Description_Generator {

	/**
	 * Tone key to tone-of-voice guidance text.
	 */
	public const TONE_GUIDE = [
		'professional' => 'professional and confident, written for trade buyers',
		'friendly' => 'warm and conversational, plain English',
		'technical' => 'precise and specification-led, for engineers and specifiers',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register AJAX handlers for long description.
		add_action('wp_ajax_rfs_ai_generate_description', [$this, 'ajax_generate_description']);
		add_action('wp_ajax_rfs_ai_save_description', [$this, 'ajax_save_description']);
		add_action('wp_ajax_rfs_ai_restore_description', [$this, 'ajax_restore_description']);
		add_action('wp_ajax_rfs_ai_bulk_generate_description', [$this, 'ajax_bulk_generate_description']);
		add_action('wp_ajax_rfs_ai_get_description_items', [$this, 'ajax_get_description_items']);

		// Register AJAX handlers for short description.
		add_action('wp_ajax_rfs_ai_generate_short_description', [$this, 'ajax_generate_short_description']);
		add_action('wp_ajax_rfs_ai_save_short_description', [$this, 'ajax_save_short_description']);
		add_action('wp_ajax_rfs_ai_restore_short_description', [$this, 'ajax_restore_short_description']);
		add_action('wp_ajax_rfs_ai_bulk_generate_short_description', [$this, 'ajax_bulk_generate_short_description']);
		add_action('wp_ajax_rfs_ai_get_short_description_items', [$this, 'ajax_get_short_description_items']);
	}

	/**
	 * Generate description for a product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $options Content controls (tone, words, keywords, features).
	 * @return array|WP_Error Result array or WP_Error on failure.
	 */
	public function generate_for_product(int $product_id, array $options = []): array|\WP_Error {
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

		// Resolve content controls and build prompt.
		$opts = $this->resolve_options($options, 'description', $product_id);
		$prompt = $this->build_description_prompt($data, $business_context, $opts);

		// Get OpenAI client.
		$settings = new Settings();
		$model = $settings->get_option('rfs_ai_seo_openai_model', 'gpt-4o');

		$openai = new OpenAI_Client($model, 'ats:content:description', (string) $product_id);

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
				'charged_gbp' => $result['charged_gbp'] ?? 0,
			]
		);

		return [
			'success' => true,
			'description' => $result['description'],
			'tokens' => $result['tokens'],
			'charged_gbp' => $result['charged_gbp'] ?? null,
			'balance_gbp' => Portal::balance(),
			'current_description' => $product->get_description(),
		];
	}

	/**
	 * Resolve content controls for a generation request, falling back to the
	 * plugin's saved settings and the product's Yoast focus keyword.
	 *
	 * @param array  $options Raw options from the caller (AJAX request or direct call).
	 * @param string $kind 'description' or 'short'.
	 * @param int    $product_id Product ID, used for the Yoast keyword fallback.
	 * @return array Resolved options.
	 */
	private function resolve_options(array $options, string $kind, int $product_id): array {
		$settings = new Settings();
		$tone = (string) ($options['tone'] ?? '');
		$keywords = trim((string) ($options['keywords'] ?? ''));
		if ($keywords === '') {
			$keywords = trim((string) get_post_meta($product_id, '_yoast_wpseo_focuskw', true));
		}

		$resolved = [
			'tone' => array_key_exists($tone, self::TONE_GUIDE) ? $tone : $settings->get_content_tone(),
			'keywords' => $keywords,
			'features' => array_key_exists('features', $options) ? (bool) $options['features'] : $settings->get_include_features(),
		];

		if ($kind === 'short') {
			$resolved['sentences'] = max(1, min(6, (int) ($options['sentences'] ?? 0) ?: $settings->get_short_sentences()));
		} else {
			$resolved['words'] = max(50, min(1000, (int) ($options['words'] ?? 0) ?: $settings->get_description_words()));
		}

		return $resolved;
	}

	/**
	 * Whether a product already has content for the given kind, used by the
	 * bulk generators' skip-existing option.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $kind 'description' or 'short'.
	 * @return bool True when the product already has non-empty content.
	 */
	private function should_skip(int $product_id, string $kind): bool {
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}

		$text = $kind === 'short' ? $product->get_short_description() : $product->get_description();

		return trim(wp_strip_all_tags((string) $text)) !== '';
	}

	/**
	 * Read the content controls posted by a generator or bulk-generator AJAX call.
	 *
	 * @return array Raw options, ready for resolve_options().
	 */
	private function read_options_from_request(): array {
		$options = [
			'tone' => sanitize_key($_POST['tone'] ?? ''),
			'words' => absint($_POST['words'] ?? 0),
			'sentences' => absint($_POST['sentences'] ?? 0),
			'keywords' => sanitize_text_field(wp_unslash($_POST['keywords'] ?? '')),
		];

		if (isset($_POST['features'])) {
			$options['features'] = $_POST['features'] === '1';
		}

		return $options;
	}

	/**
	 * Build description generation prompt.
	 *
	 * @param array  $data Product data.
	 * @param string $business_context Business context from AI Trainer.
	 * @param array  $options Content controls (tone, words, keywords, features).
	 * @return string Prompt text.
	 */
	public function build_description_prompt(array $data, string $business_context, array $options = []): string {
		$prompt = "Generate a compelling product description for this WooCommerce product.\n\n";

		// Add business context if available.
		if (!empty($business_context)) {
			$prompt .= "BUSINESS CONTEXT:\n";
			$prompt .= $business_context . "\n\n";
		}

		// Add product data.
		$prompt .= "PRODUCT DATA:\n";
		$prompt .= "- Name: {$data['product_name']}\n";
		$prompt .= "- Product URL: {$data['product_url']}\n";

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

		// Add similar products for linking.
		if (!empty($data['similar_products'])) {
			$prompt .= "\nSIMILAR PRODUCTS (for potential cross-linking):\n";
			$prompt .= $data['similar_products'] . "\n";
		}

		$prompt .= "\nREQUIREMENTS:\n";
		$prompt .= "- Create a detailed, engaging product description in HTML format\n";
		$prompt .= "- Use proper HTML formatting:\n";
		$prompt .= "  * <h2>, <h3>, <h4> for section headings\n";
		$prompt .= "  * <strong> for bold/emphasis\n";
		$prompt .= "  * <ul> and <li> for bullet point lists\n";
		$prompt .= "  * <a href=\"URL\">text</a> for links to similar products (when relevant)\n";
		$prompt .= "- Structure the content with clear sections (e.g., Overview, Key Features, Specifications, Applications)\n";
		$prompt .= "- Highlight key features and benefits in bullet points\n";
		$prompt .= "- Use the business context to maintain brand voice and style\n";
		$prompt .= "- If similar products exist, naturally mention and link to 1-2 relevant ones where appropriate\n";
		$prompt .= "- If a current description exists, improve upon it while keeping valuable details\n";
		$prompt .= "- Make it SEO-friendly but natural-sounding\n";

		$tone = self::TONE_GUIDE[$options['tone'] ?? 'professional'] ?? self::TONE_GUIDE['professional'];
		$words = (int) ($options['words'] ?? 150);
		$lines = [
			"- Tone of voice: {$tone}",
			"- Length: about {$words} words",
		];
		if (!empty($options['keywords'])) {
			$lines[] = '- Work these focus keywords in naturally: ' . $options['keywords'];
		}
		$lines[] = !empty($options['features'])
			? '- Include a bullet-point list of key features'
			: '- Do not use bullet lists; write flowing paragraphs';
		$control_block = implode("\n", $lines);

		$prompt .= $control_block . "\n";
		$prompt .= "- DO NOT include opening/closing <p> tags or wrapping divs - just the content HTML\n\n";

		$prompt .= "RESPOND IN JSON FORMAT ONLY:\n";
		$prompt .= '{"description": "your HTML-formatted product description here"}';

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
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$options = $this->read_options_from_request();

		$result = $this->generate_for_product($product_id, $options);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()]);
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
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';

		if (!$product_id || empty($description)) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid data.', 'rfs-ai-seo')]);
		}

		// Save original first.
		$this->save_original_description($product_id);

		// Update product description.
		$product = wc_get_product($product_id);
		if (!$product) {
			wp_send_json_error(['code' => 'product_not_found', 'message' => __('Product not found.', 'rfs-ai-seo')]);
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
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$restored = $this->restore_original_description($product_id);

		if (!$restored) {
			wp_send_json_error(['code' => 'no_original_description', 'message' => __('No original description found.', 'rfs-ai-seo')]);
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
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$skip_existing = ($_POST['skip_existing'] ?? '1') === '1';
		if ($skip_existing && $this->should_skip($product_id, 'description')) {
			wp_send_json_success(['skipped' => true, 'message' => __('Already has content', 'rfs-ai-seo')]);
		}

		$options = $this->read_options_from_request();

		$result = $this->generate_for_product($product_id, $options);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()]);
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

	/**
	 * AJAX handler for getting filtered items for bulk generator.
	 *
	 * @return void
	 */
	public function ajax_get_description_items(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$category = isset($_POST['category']) ? absint($_POST['category']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
		$search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
		$per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;

		// Build query args.
		$args = [
			'post_type' => 'product',
			'posts_per_page' => $per_page,
			'paged' => $page,
			'orderby' => 'title',
			'order' => 'ASC',
			'post_status' => 'publish',
		];

		// Filter by category.
		if ($category > 0) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field' => 'term_id',
					'terms' => $category,
				],
			];
		}

		// Filter by search.
		if (!empty($search)) {
			$args['s'] = $search;
		}

		// Execute query.
		$query = new \WP_Query($args);
		$items = [];

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$product_id = get_the_ID();
				$product = wc_get_product($product_id);

				if (!$product) {
					continue;
				}

				$description = $product->get_description();
				$has_description = !empty($description);

				// Filter by status.
				if ($status === 'empty' && $has_description) {
					continue;
				}
				if ($status === 'has_description' && !$has_description) {
					continue;
				}

				// Get product thumbnail.
				$thumbnail = get_the_post_thumbnail_url($product_id, 'thumbnail');

				$items[] = [
					'id' => $product_id,
					'name' => $product->get_name(),
					'edit_url' => get_edit_post_link($product_id),
					'thumbnail' => $thumbnail,
					'current_description' => $has_description ? wp_trim_words($description, 20, '...') : '',
					'has_description' => $has_description,
				];
			}

			wp_reset_postdata();
		}

		wp_send_json_success([
			'items' => $items,
			'total' => $query->found_posts,
		]);
	}

	/**
	 * Generate short description for a product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $options Content controls (tone, sentences, keywords, features).
	 * @return array|WP_Error Result array or WP_Error on failure.
	 */
	public function generate_short_description_for_product(int $product_id, array $options = []): array|\WP_Error {
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

		// Resolve content controls and build prompt.
		$opts = $this->resolve_options($options, 'short', $product_id);
		$prompt = $this->build_short_description_prompt($data, $business_context, $opts);

		// Get OpenAI client.
		$settings = new Settings();
		$model = $settings->get_option('rfs_ai_seo_openai_model', 'gpt-4o');

		$openai = new OpenAI_Client($model, 'ats:content:short-description', (string) $product_id);

		// Generate short description.
		$result = $openai->generate_description($prompt);

		if (is_wp_error($result)) {
			return $result;
		}

		// Log generation.
		$logger = new Usage_Logger();
		$logger->log(
			$product_id,
			'product_short_description',
			[
				'tokens_prompt' => $result['tokens']['prompt'] ?? 0,
				'tokens_completion' => $result['tokens']['completion'] ?? 0,
				'tokens_total' => $result['tokens']['total'] ?? 0,
				'model' => $model,
				'generated_title' => '',
				'generated_description' => $result['description'],
				'charged_gbp' => $result['charged_gbp'] ?? 0,
			]
		);

		return [
			'success' => true,
			'description' => $result['description'],
			'tokens' => $result['tokens'],
			'charged_gbp' => $result['charged_gbp'] ?? null,
			'balance_gbp' => Portal::balance(),
			'current_description' => $product->get_short_description(),
		];
	}

	/**
	 * Build short description generation prompt.
	 *
	 * @param array  $data Product data.
	 * @param string $business_context Business context from AI Trainer.
	 * @param array  $options Content controls (tone, sentences, keywords, features).
	 * @return string Prompt text.
	 */
	public function build_short_description_prompt(array $data, string $business_context, array $options = []): string {
		$prompt = "Generate a SHORT product description for this WooCommerce product.\n\n";

		// Add business context if available.
		if (!empty($business_context)) {
			$prompt .= "BUSINESS CONTEXT:\n";
			$prompt .= $business_context . "\n\n";
		}

		// Add product data.
		$prompt .= "PRODUCT DATA:\n";
		$prompt .= "- Name: {$data['product_name']}\n";

		if (!empty($data['short_description'])) {
			$prompt .= "- Current Short Description: {$data['short_description']}\n";
		}

		if (!empty($data['product_description'])) {
			$prompt .= "- Full Description: {$data['product_description']}\n";
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
		$prompt .= "- Create a SHORT, concise product summary\n";
		$prompt .= "- Focus on the main selling point and key feature\n";
		$prompt .= "- Use the business context to maintain brand voice\n";
		$prompt .= "- If a current short description exists, improve upon it\n";
		$prompt .= "- Make it attention-grabbing and SEO-friendly\n";

		$tone = self::TONE_GUIDE[$options['tone'] ?? 'professional'] ?? self::TONE_GUIDE['professional'];
		$sentences = (int) ($options['sentences'] ?? 2);
		$lines = [
			"- Tone of voice: {$tone}",
			"- Length: exactly {$sentences} sentences",
		];
		if (!empty($options['keywords'])) {
			$lines[] = '- Work these focus keywords in naturally: ' . $options['keywords'];
		}
		$lines[] = '- No bullet points or lists, just flowing sentences';
		$prompt .= implode("\n", $lines) . "\n\n";

		$prompt .= "RESPOND IN JSON FORMAT ONLY:\n";
		$prompt .= '{"description": "your short product description here"}';

		return $prompt;
	}

	/**
	 * Save original short description before overwriting.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function save_original_short_description(int $product_id): void {
		// Check if we already have original saved.
		$existing_original = get_post_meta($product_id, '_rfs_ai_original_short_description', true);

		if (empty($existing_original)) {
			// Save current short description as original.
			$product = wc_get_product($product_id);
			if ($product) {
				$current_short_description = $product->get_short_description();
				if (!empty($current_short_description)) {
					update_post_meta($product_id, '_rfs_ai_original_short_description', $current_short_description);
					update_post_meta($product_id, '_rfs_ai_original_short_saved_at', current_time('mysql'));
				}
			}
		}
	}

	/**
	 * Restore original short description.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True on success, false on failure.
	 */
	public function restore_original_short_description(int $product_id): bool {
		$original_short_description = get_post_meta($product_id, '_rfs_ai_original_short_description', true);

		if (empty($original_short_description)) {
			return false;
		}

		// Update product short description.
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}

		$product->set_short_description($original_short_description);
		$product->save();

		// Clear the saved original.
		delete_post_meta($product_id, '_rfs_ai_original_short_description');
		delete_post_meta($product_id, '_rfs_ai_original_short_saved_at');

		return true;
	}

	/**
	 * AJAX handler for generating short description.
	 *
	 * @return void
	 */
	public function ajax_generate_short_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$options = $this->read_options_from_request();

		$result = $this->generate_short_description_for_product($product_id, $options);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()]);
		}

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler for saving short description.
	 *
	 * @return void
	 */
	public function ajax_save_short_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';

		if (!$product_id || empty($description)) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid data.', 'rfs-ai-seo')]);
		}

		// Save original first.
		$this->save_original_short_description($product_id);

		// Update product short description.
		$product = wc_get_product($product_id);
		if (!$product) {
			wp_send_json_error(['code' => 'product_not_found', 'message' => __('Product not found.', 'rfs-ai-seo')]);
		}

		$product->set_short_description($description);
		$product->save();

		wp_send_json_success(['message' => __('Short description saved successfully.', 'rfs-ai-seo')]);
	}

	/**
	 * AJAX handler for restoring original short description.
	 *
	 * @return void
	 */
	public function ajax_restore_short_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$restored = $this->restore_original_short_description($product_id);

		if (!$restored) {
			wp_send_json_error(['code' => 'no_original_description', 'message' => __('No original short description found.', 'rfs-ai-seo')]);
		}

		// Get the restored short description.
		$product = wc_get_product($product_id);
		$description = $product ? $product->get_short_description() : '';

		wp_send_json_success([
			'message' => __('Original short description restored.', 'rfs-ai-seo'),
			'description' => $description,
		]);
	}

	/**
	 * AJAX handler for bulk short description generation.
	 *
	 * @return void
	 */
	public function ajax_bulk_generate_short_description(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['code' => 'invalid_request', 'message' => __('Invalid product ID.', 'rfs-ai-seo')]);
		}

		$skip_existing = ($_POST['skip_existing'] ?? '1') === '1';
		if ($skip_existing && $this->should_skip($product_id, 'short')) {
			wp_send_json_success(['skipped' => true, 'message' => __('Already has content', 'rfs-ai-seo')]);
		}

		$options = $this->read_options_from_request();

		$result = $this->generate_short_description_for_product($product_id, $options);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()]);
		}

		// Auto-save the short description.
		$this->save_original_short_description($product_id);

		$product = wc_get_product($product_id);
		if ($product) {
			$product->set_short_description($result['description']);
			$product->save();
		}

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler for getting filtered items for short description bulk generator.
	 *
	 * @return void
	 */
	public function ajax_get_short_description_items(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['code' => 'forbidden', 'message' => __('Insufficient permissions.', 'rfs-ai-seo')]);
		}

		$category = isset($_POST['category']) ? absint($_POST['category']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
		$search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
		$per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;

		// Build query args.
		$args = [
			'post_type' => 'product',
			'posts_per_page' => $per_page,
			'paged' => $page,
			'orderby' => 'title',
			'order' => 'ASC',
			'post_status' => 'publish',
		];

		// Filter by category.
		if ($category > 0) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field' => 'term_id',
					'terms' => $category,
				],
			];
		}

		// Filter by search.
		if (!empty($search)) {
			$args['s'] = $search;
		}

		// Execute query.
		$query = new \WP_Query($args);
		$items = [];

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$product_id = get_the_ID();
				$product = wc_get_product($product_id);

				if (!$product) {
					continue;
				}

				$short_description = $product->get_short_description();
				$has_short_description = !empty($short_description);

				// Filter by status.
				if ($status === 'empty' && $has_short_description) {
					continue;
				}
				if ($status === 'has_description' && !$has_short_description) {
					continue;
				}

				// Get product thumbnail.
				$thumbnail = get_the_post_thumbnail_url($product_id, 'thumbnail');

				$items[] = [
					'id' => $product_id,
					'name' => $product->get_name(),
					'edit_url' => get_edit_post_link($product_id),
					'thumbnail' => $thumbnail,
					'current_description' => $has_short_description ? wp_trim_words($short_description, 15, '...') : '',
					'has_description' => $has_short_description,
				];
			}

			wp_reset_postdata();
		}

		wp_send_json_success([
			'items' => $items,
			'total' => $query->found_posts,
		]);
	}
}
