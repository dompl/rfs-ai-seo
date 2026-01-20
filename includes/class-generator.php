<?php
/**
 * Generator Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Generator Class
 *
 * Orchestrates the meta generation process.
 *
 * @class Generator
 */
class Generator {

	/**
	 * Content collector instance.
	 *
	 * @var Content_Collector
	 */
	private Content_Collector $collector;

	/**
	 * Prompt manager instance.
	 *
	 * @var Prompt_Manager
	 */
	private Prompt_Manager $prompt_manager;

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
		$this->collector = new Content_Collector();
		$this->prompt_manager = new Prompt_Manager();
		$this->settings = new Settings();
	}

	/**
	 * Generate meta for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array|\WP_Error Generated meta or error.
	 */
	public function generate_for_product(int $product_id): array|\WP_Error {
		return $this->generate_single($product_id, 'product');
	}

	/**
	 * Generate meta for a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array|\WP_Error Generated meta or error.
	 */
	public function generate_for_variation(int $variation_id): array|\WP_Error {
		return $this->generate_single($variation_id, 'variation');
	}

	/**
	 * Generate meta for a category.
	 *
	 * @param int $term_id Term ID.
	 * @return array|\WP_Error Generated meta or error.
	 */
	public function generate_for_category(int $term_id): array|\WP_Error {
		return $this->generate_single($term_id, 'category');
	}

	/**
	 * Generate meta for a single object.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $object_type Object type.
	 * @return array|\WP_Error Generated meta or error.
	 */
	public function generate_single(int $object_id, string $object_type): array|\WP_Error {
		// Check spending cap.
		$logger = new Usage_Logger();
		$cap_status = $logger->check_spending_cap();

		if (!$cap_status['allowed']) {
			return new \WP_Error(
				'spending_cap_reached',
				$cap_status['message']
			);
		}

		// Collect content data.
		$data = $this->collect_content_data($object_id, $object_type);

		if (empty($data)) {
			return new \WP_Error(
				'no_content',
				__('Unable to collect content data for this item.', 'rfs-ai-seo')
			);
		}

		// Get prompt template.
		$template = $this->prompt_manager->get_template($object_type);

		if (empty($template)) {
			return new \WP_Error(
				'no_template',
				__('No prompt template configured for this content type.', 'rfs-ai-seo')
			);
		}

		// Compile prompt.
		$prompt = $this->prompt_manager->compile_prompt($template, $data);

		// Get API key and model.
		$api_key = $this->settings->get_api_key();
		$model = $this->settings->get_model();

		if (empty($api_key)) {
			return new \WP_Error(
				'no_api_key',
				__('OpenAI API key is not configured.', 'rfs-ai-seo')
			);
		}

		// Create OpenAI client.
		$client = new OpenAI_Client($api_key, $model);

		// Generate meta.
		$result = $client->generate_meta($prompt);

		if (is_wp_error($result)) {
			return $result;
		}

		// Add current meta to response.
		$yoast = new Yoast_Integration();
		$current_meta = $yoast->get_meta($object_id, $object_type);

		// Log generation (reuse logger instance from cap check).
		$logger->log($object_id, $object_type, [
			'tokens_prompt' => $result['tokens']['prompt'],
			'tokens_completion' => $result['tokens']['completion'],
			'tokens_total' => $result['tokens']['total'],
			'model' => $model,
			'generated_title' => $result['title'],
			'generated_description' => $result['description'],
		]);

		return [
			'success' => true,
			'title' => $result['title'],
			'description' => $result['description'],
			'current' => $current_meta,
			'tokens' => $result['tokens'],
		];
	}

	/**
	 * Collect content data based on object type.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $object_type Object type.
	 * @return array Content data.
	 */
	private function collect_content_data(int $object_id, string $object_type): array {
		switch ($object_type) {
			case 'product':
				return $this->collector->collect_product_data($object_id);

			case 'variation':
				return $this->collector->collect_variation_data($object_id);

			case 'category':
			case 'product_cat':
				return $this->collector->collect_category_data($object_id);

			default:
				return [];
		}
	}
}
