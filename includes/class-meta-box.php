<?php
/**
 * Meta Box Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Meta Box Class
 *
 * Registers and renders meta boxes on product edit screens.
 *
 * @class Meta_Box
 */
class Meta_Box {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register meta boxes.
		add_action('add_meta_boxes', [$this, 'register_meta_boxes']);

		// Register category fields.
		add_action('product_cat_edit_form_fields', [$this, 'render_category_fields'], 10, 2);

		// Register AJAX handlers.
		add_action('wp_ajax_rfs_ai_seo_generate', [$this, 'ajax_generate']);
		add_action('wp_ajax_rfs_ai_seo_save_meta', [$this, 'ajax_save_meta']);
	}

	/**
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		// Add meta box to product edit screen.
		add_meta_box(
			'rfs_ai_seo_meta_box',
			__('RFS AI SEO', 'rfs-ai-seo'),
			[$this, 'render_product_meta_box'],
			'product',
			'side',
			'high'
		);
	}

	/**
	 * Render product meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_product_meta_box(\WP_Post $post): void {
		// Get current Yoast meta.
		$yoast = new Yoast_Integration();
		$current_meta = $yoast->get_meta($post->ID, 'product');

		// Get settings for character limits.
		$settings = new Settings();
		$title_max = $settings->get_title_max_length();
		$desc_max = $settings->get_description_max_length();

		// Include meta box template.
		include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/meta-box.php';
	}

	/**
	 * Render category meta fields.
	 *
	 * @param \WP_Term $term Term object.
	 * @return void
	 */
	public function render_category_fields(\WP_Term $term): void {
		// Get current Yoast meta.
		$yoast = new Yoast_Integration();
		$current_meta = $yoast->get_meta($term->term_id, 'product_cat');

		// Get settings for character limits.
		$settings = new Settings();
		$title_max = $settings->get_title_max_length();
		$desc_max = $settings->get_description_max_length();

		// Set object ID and type for template.
		$object_id = $term->term_id;
		$object_type = 'product_cat';

		// Include category meta box template.
		include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/category-meta-box.php';
	}

	/**
	 * AJAX handler for generating meta.
	 *
	 * @return void
	 */
	public function ajax_generate(): void {
		// Verify nonce.
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		// Check capabilities.
		if (!current_user_can('edit_posts')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		// Get parameters.
		$object_id = isset($_POST['object_id']) ? absint($_POST['object_id']) : 0;
		$object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : 'product';

		if (!$object_id) {
			wp_send_json_error([
				'message' => __('Invalid object ID.', 'rfs-ai-seo'),
			]);
		}

		// Generate meta.
		$generator = new Generator();
		$result = $generator->generate_single($object_id, $object_type);

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
			]);
		}

		// Return success.
		wp_send_json_success($result);
	}

	/**
	 * AJAX handler for saving meta.
	 *
	 * @return void
	 */
	public function ajax_save_meta(): void {
		// Verify nonce.
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		// Check capabilities.
		if (!current_user_can('edit_posts')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		// Get parameters.
		$object_id = isset($_POST['object_id']) ? absint($_POST['object_id']) : 0;
		$object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : 'product';
		$title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

		if (!$object_id || empty($title) || empty($description)) {
			wp_send_json_error([
				'message' => __('Invalid data provided.', 'rfs-ai-seo'),
			]);
		}

		// Save to Yoast.
		$yoast = new Yoast_Integration();
		$saved = $yoast->save_meta($object_id, $object_type, $title, $description);

		if (!$saved) {
			wp_send_json_error([
				'message' => __('Failed to save meta data.', 'rfs-ai-seo'),
			]);
		}

		// Return success.
		wp_send_json_success([
			'message' => __('Meta saved successfully to Yoast SEO!', 'rfs-ai-seo'),
		]);
	}
}
