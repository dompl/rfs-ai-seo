<?php
/**
 * Content Collector Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Content Collector Class
 *
 * Collects data from WooCommerce products and categories.
 *
 * @class Content_Collector
 */
class Content_Collector {

	/**
	 * Collect product data.
	 *
	 * @param int $product_id Product ID.
	 * @return array Product data array.
	 */
	public function collect_product_data(int $product_id): array {
		$product = wc_get_product($product_id);

		if (!$product) {
			return [];
		}

		return [
			'product_name' => $product->get_name(),
			'product_description' => wp_strip_all_tags($product->get_description()),
			'short_description' => wp_strip_all_tags($product->get_short_description()),
			'sku' => $product->get_sku() ?: '',
			'price' => $this->format_price($product),
			'regular_price' => $product->get_regular_price() ? wc_price($product->get_regular_price()) : '',
			'sale_price' => $product->get_sale_price() ? wc_price($product->get_sale_price()) : '',
			'categories' => $this->get_product_categories($product_id),
			'category_hierarchy' => $this->get_category_hierarchy($product_id),
			'tags' => $this->get_product_tags($product_id),
			'attributes' => $this->get_product_attributes($product),
			'stock_status' => $product->is_in_stock() ? __('In stock', 'rfs-ai-seo') : __('Out of stock', 'rfs-ai-seo'),
			'average_rating' => $product->get_average_rating(),
			'review_count' => $product->get_review_count(),
			'acf_all' => '', // Will be populated by ACF handler if enabled.
		];
	}

	/**
	 * Collect variation data.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array Variation data array.
	 */
	public function collect_variation_data(int $variation_id): array {
		$variation = wc_get_product($variation_id);

		if (!$variation || !$variation->is_type('variation')) {
			return [];
		}

		$parent_id = $variation->get_parent_id();
		$parent = wc_get_product($parent_id);

		return [
			'product_name' => $parent ? $parent->get_name() : '',
			'variation_description' => wp_strip_all_tags($variation->get_description()),
			'variation_attributes' => $this->format_variation_attributes($variation),
			'variation_price' => $this->format_price($variation),
			'variation_sku' => $variation->get_sku() ?: '',
			'short_description' => $parent ? wp_strip_all_tags($parent->get_short_description()) : '',
			'category_hierarchy' => $this->get_category_hierarchy($parent_id),
			'acf_all' => '',
		];
	}

	/**
	 * Collect category data.
	 *
	 * @param int $term_id Category term ID.
	 * @return array Category data array.
	 */
	public function collect_category_data(int $term_id): array {
		$term = get_term($term_id, 'product_cat');

		if (!$term || is_wp_error($term)) {
			return [];
		}

		return [
			'category_name' => $term->name,
			'category_description' => wp_strip_all_tags($term->description),
			'parent_category' => $this->get_parent_category_name($term->parent),
			'category_hierarchy' => $this->get_term_hierarchy($term_id),
			'product_count' => $term->count,
			'acf_all' => '',
		];
	}

	/**
	 * Format product price.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Formatted price.
	 */
	private function format_price(\WC_Product $product): string {
		return $product->get_price() ? wc_price($product->get_price()) : '';
	}

	/**
	 * Get product categories.
	 *
	 * @param int $product_id Product ID.
	 * @return string Comma-separated category names.
	 */
	private function get_product_categories(int $product_id): string {
		$terms = get_the_terms($product_id, 'product_cat');

		if (!$terms || is_wp_error($terms)) {
			return '';
		}

		return implode(', ', wp_list_pluck($terms, 'name'));
	}

	/**
	 * Get category hierarchy.
	 *
	 * @param int $product_id Product ID.
	 * @return string Category hierarchy (e.g., "Parent > Child > Grandchild").
	 */
	private function get_category_hierarchy(int $product_id): string {
		$terms = get_the_terms($product_id, 'product_cat');

		if (!$terms || is_wp_error($terms)) {
			return '';
		}

		// Get the primary category (first one).
		$primary_term = reset($terms);

		return $this->get_term_hierarchy($primary_term->term_id);
	}

	/**
	 * Get term hierarchy.
	 *
	 * @param int $term_id Term ID.
	 * @return string Term hierarchy.
	 */
	private function get_term_hierarchy(int $term_id): string {
		$hierarchy = [];
		$term = get_term($term_id, 'product_cat');

		while ($term && !is_wp_error($term)) {
			array_unshift($hierarchy, $term->name);

			if ($term->parent === 0) {
				break;
			}

			$term = get_term($term->parent, 'product_cat');
		}

		return implode(' > ', $hierarchy);
	}

	/**
	 * Get product tags.
	 *
	 * @param int $product_id Product ID.
	 * @return string Comma-separated tag names.
	 */
	private function get_product_tags(int $product_id): string {
		$terms = get_the_terms($product_id, 'product_tag');

		if (!$terms || is_wp_error($terms)) {
			return '';
		}

		return implode(', ', wp_list_pluck($terms, 'name'));
	}

	/**
	 * Get product attributes.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Formatted attributes.
	 */
	private function get_product_attributes(\WC_Product $product): string {
		$attributes = $product->get_attributes();

		if (empty($attributes)) {
			return '';
		}

		$output = [];

		foreach ($attributes as $attribute) {
			if ($attribute->is_taxonomy()) {
				$terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
				$output[] = wc_attribute_label($attribute->get_name()) . ': ' . implode(', ', $terms);
			} else {
				$output[] = $attribute->get_name() . ': ' . implode(', ', $attribute->get_options());
			}
		}

		return implode(', ', $output);
	}

	/**
	 * Format variation attributes.
	 *
	 * @param \WC_Product_Variation $variation Variation object.
	 * @return string Formatted variation attributes.
	 */
	private function format_variation_attributes(\WC_Product_Variation $variation): string {
		$attributes = $variation->get_variation_attributes();

		if (empty($attributes)) {
			return '';
		}

		$output = [];

		foreach ($attributes as $key => $value) {
			$attribute_name = str_replace('attribute_', '', $key);
			$attribute_name = wc_attribute_label($attribute_name);
			$output[] = $attribute_name . ': ' . $value;
		}

		return implode(', ', $output);
	}

	/**
	 * Get parent category name.
	 *
	 * @param int $parent_id Parent term ID.
	 * @return string Parent category name.
	 */
	private function get_parent_category_name(int $parent_id): string {
		if ($parent_id === 0) {
			return '';
		}

		$parent = get_term($parent_id, 'product_cat');

		return $parent && !is_wp_error($parent) ? $parent->name : '';
	}
}
