<?php
/**
 * Bulk Generator Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Bulk Generator Class
 *
 * Handles bulk generation of SEO meta for multiple items.
 *
 * @class Bulk_Generator
 */
class Bulk_Generator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register AJAX handlers.
		add_action('wp_ajax_rfs_ai_seo_get_items', [$this, 'ajax_get_items']);
		add_action('wp_ajax_rfs_ai_seo_bulk_generate', [$this, 'ajax_bulk_generate']);
	}

	/**
	 * Render bulk generator page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Include bulk generator template.
		include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/bulk-generator-page.php';
	}

	/**
	 * AJAX handler for getting items.
	 *
	 * @return void
	 */
	public function ajax_get_items(): void {
		// Verify nonce.
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		// Check capabilities.
		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'code'    => 'forbidden',
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		// Get filter parameters.
		$filters = [
			'object_type' => isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : 'product',
			'category' => isset($_POST['category']) ? absint($_POST['category']) : 0,
			'meta_status' => isset($_POST['meta_status']) ? sanitize_text_field($_POST['meta_status']) : 'all',
			'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
			'per_page' => isset($_POST['per_page']) ? absint($_POST['per_page']) : 20,
			'page' => isset($_POST['page']) ? absint($_POST['page']) : 1,
		];

		// Get items.
		$result = $this->get_items($filters);

		wp_send_json_success($result);
	}

	/**
	 * Get items based on filters.
	 *
	 * @param array $filters Filter parameters.
	 * @return array Items array with pagination.
	 */
	public function get_items(array $filters): array {
		$items = [];
		$total = 0;

		if ($filters['object_type'] === 'product_cat') {
			// Get categories.
			$result = $this->get_category_items($filters);
			$items = $result['items'];
			$total = $result['total'];
		} else {
			// Get products.
			$result = $this->get_product_items($filters);
			$items = $result['items'];
			$total = $result['total'];
		}

		return [
			'items' => $items,
			'total' => $total,
			'pages' => ceil($total / $filters['per_page']),
		];
	}

	/**
	 * Get product items.
	 *
	 * @param array $filters Filter parameters.
	 * @return array Products array.
	 */
	private function get_product_items(array $filters): array {
		$args = [
			'post_type' => 'product',
			'posts_per_page' => $filters['per_page'],
			'paged' => $filters['page'],
			'orderby' => 'title',
			'order' => 'ASC',
			'post_status' => 'publish',
		];

		// Apply category filter.
		if ($filters['category'] > 0) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field' => 'term_id',
					'terms' => $filters['category'],
				],
			];
		}

		// Apply search filter.
		if (!empty($filters['search'])) {
			$args['s'] = $filters['search'];
		}

		// Apply meta status filter.
		if ($filters['meta_status'] !== 'all') {
			$args['meta_query'] = $this->get_meta_query($filters['meta_status']);
		}

		$query = new \WP_Query($args);
		$yoast = new Yoast_Integration();

		$items = [];
		foreach ($query->posts as $post) {
			$product = wc_get_product($post->ID);
			$meta = $yoast->get_meta($post->ID, 'product');
			$status = $this->get_meta_status($meta);

			$items[] = [
				'id' => $post->ID,
				'name' => $post->post_title,
				'edit_url' => get_edit_post_link($post->ID),
				'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
				'current_title' => $meta['title'] ?: null,
				'current_description' => $meta['description'] ?: null,
				'status' => $status,
			];
		}

		return [
			'items' => $items,
			'total' => $query->found_posts,
		];
	}

	/**
	 * Get category items.
	 *
	 * @param array $filters Filter parameters.
	 * @return array Categories array.
	 */
	private function get_category_items(array $filters): array {
		$args = [
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'number' => $filters['per_page'],
			'offset' => ($filters['page'] - 1) * $filters['per_page'],
			'orderby' => 'name',
			'order' => 'ASC',
		];

		// Apply search filter.
		if (!empty($filters['search'])) {
			$args['search'] = $filters['search'];
		}

		$terms = get_terms($args);
		$yoast = new Yoast_Integration();

		$items = [];
		foreach ($terms as $term) {
			$meta = $yoast->get_meta($term->term_id, 'product_cat');
			$status = $this->get_meta_status($meta);

			// Apply meta status filter.
			if ($filters['meta_status'] !== 'all' && !$this->matches_meta_status($status, $filters['meta_status'])) {
				continue;
			}

			$items[] = [
				'id' => $term->term_id,
				'name' => $term->name,
				'edit_url' => get_edit_term_link($term->term_id, 'product_cat'),
				'thumbnail' => null,
				'current_title' => $meta['title'] ?: null,
				'current_description' => $meta['description'] ?: null,
				'status' => $status,
			];
		}

		// Get total count.
		$count_args = ['taxonomy' => 'product_cat', 'hide_empty' => false];
		if (!empty($filters['search'])) {
			$count_args['search'] = $filters['search'];
		}
		$total = wp_count_terms($count_args);

		return [
			'items' => $items,
			'total' => is_wp_error($total) ? 0 : $total,
		];
	}

	/**
	 * Get meta query for filtering.
	 *
	 * @param string $status Meta status filter.
	 * @return array Meta query.
	 */
	private function get_meta_query(string $status): array {
		switch ($status) {
			case 'missing_both':
				return [
					'relation' => 'OR',
					[
						'key' => '_yoast_wpseo_title',
						'compare' => 'NOT EXISTS',
					],
					[
						'key' => '_yoast_wpseo_title',
						'value' => '',
						'compare' => '=',
					],
				];

			case 'missing_title':
				return [
					'relation' => 'OR',
					[
						'key' => '_yoast_wpseo_title',
						'compare' => 'NOT EXISTS',
					],
					[
						'key' => '_yoast_wpseo_title',
						'value' => '',
						'compare' => '=',
					],
				];

			case 'missing_description':
				return [
					'relation' => 'OR',
					[
						'key' => '_yoast_wpseo_metadesc',
						'compare' => 'NOT EXISTS',
					],
					[
						'key' => '_yoast_wpseo_metadesc',
						'value' => '',
						'compare' => '=',
					],
				];

			case 'has_existing':
				return [
					'relation' => 'AND',
					[
						'key' => '_yoast_wpseo_title',
						'compare' => 'EXISTS',
					],
					[
						'key' => '_yoast_wpseo_title',
						'value' => '',
						'compare' => '!=',
					],
				];

			default:
				return [];
		}
	}

	/**
	 * Get meta status.
	 *
	 * @param array $meta Meta data.
	 * @return string Status (complete, partial, empty).
	 */
	private function get_meta_status(array $meta): string {
		$has_title = !empty($meta['title']);
		$has_desc = !empty($meta['description']);

		if ($has_title && $has_desc) {
			return 'complete';
		} elseif ($has_title || $has_desc) {
			return 'partial';
		}

		return 'empty';
	}

	/**
	 * Check if status matches filter.
	 *
	 * @param string $status Current status.
	 * @param string $filter Filter status.
	 * @return bool Matches filter.
	 */
	private function matches_meta_status(string $status, string $filter): bool {
		switch ($filter) {
			case 'missing_both':
				return $status === 'empty';
			case 'missing_title':
			case 'missing_description':
				return $status !== 'complete';
			case 'has_existing':
				return $status === 'complete' || $status === 'partial';
			default:
				return true;
		}
	}

	/**
	 * AJAX handler for bulk generation.
	 *
	 * @return void
	 */
	public function ajax_bulk_generate(): void {
		// Verify nonce.
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		// Check capabilities.
		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'code'    => 'forbidden',
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		// Get parameters.
		$object_id = isset($_POST['object_id']) ? absint($_POST['object_id']) : 0;
		$object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : 'product';
		$overwrite_title = !empty($_POST['overwrite_title']);
		$overwrite_description = !empty($_POST['overwrite_description']);

		if (!$object_id) {
			wp_send_json_error([
				'code'    => 'invalid_request',
				'message' => __('Invalid object ID.', 'rfs-ai-seo'),
			]);
		}

		// Check existing meta to decide what to save.
		$yoast = new Yoast_Integration();
		$existing_meta = $yoast->get_meta($object_id, $object_type);
		$has_existing_title = !empty($existing_meta['title']);
		$has_existing_description = !empty($existing_meta['description']);

		// Skip entirely if both exist and neither overwrite is checked.
		if ($has_existing_title && !$overwrite_title && $has_existing_description && !$overwrite_description) {
			wp_send_json_success([
				'message' => __('Skipped — both title and description already exist.', 'rfs-ai-seo'),
				'title' => $existing_meta['title'],
				'description' => $existing_meta['description'],
				'tokens' => ['prompt' => 0, 'completion' => 0, 'total' => 0],
				'skipped' => true,
			]);
		}

		// Generate meta.
		$generator = new Generator();
		$result = $generator->generate_single($object_id, $object_type);

		if (is_wp_error($result)) {
			wp_send_json_error([
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			]);
		}

		// Determine which values to save.
		$title_to_save = $result['title'];
		$description_to_save = $result['description'];

		if ($has_existing_title && !$overwrite_title) {
			$title_to_save = $existing_meta['title'];
		}
		if ($has_existing_description && !$overwrite_description) {
			$description_to_save = $existing_meta['description'];
		}

		// Auto-save to Yoast.
		$saved = $yoast->save_meta($object_id, $object_type, $title_to_save, $description_to_save);

		if (!$saved) {
			wp_send_json_error([
				'code'    => 'save_failed',
				'message' => __('Failed to save meta data.', 'rfs-ai-seo'),
			]);
		}

		// Return success with the saved data.
		wp_send_json_success([
			'message' => __('Meta generated and saved successfully.', 'rfs-ai-seo'),
			'title' => $title_to_save,
			'description' => $description_to_save,
			'tokens' => $result['tokens'],
		]);
	}
}
