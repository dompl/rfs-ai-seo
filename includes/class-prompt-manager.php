<?php
/**
 * Prompt Manager Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Prompt Manager Class
 *
 * Handles prompt template loading and variable replacement.
 *
 * @class Prompt_Manager
 */
class Prompt_Manager {

	/**
	 * Get prompt template for a type.
	 *
	 * @param string $type Template type (product, variation, category).
	 * @return string Prompt template.
	 */
	public function get_template(string $type): string {
		$settings = new Settings();
		return $settings->get_prompt($type);
	}

	/**
	 * Compile prompt with variable replacement.
	 *
	 * @param string $template Prompt template.
	 * @param array  $data Data array with variable values.
	 * @return string Compiled prompt.
	 */
	public function compile_prompt(string $template, array $data): string {
		$settings = new Settings();

		// Add system variables.
		$data['max_title_length'] = $settings->get_title_max_length();
		$data['max_description_length'] = $settings->get_description_max_length();

		// Replace all {variable} placeholders.
		return preg_replace_callback('/\{([a-z_:]+)\}/', function ($matches) use ($data) {
			$key = $matches[1];
			return $data[$key] ?? '';
		}, $template);
	}

	/**
	 * Get available variables for a template type.
	 *
	 * @param string $type Template type.
	 * @return array Array of available variable names.
	 */
	public function get_available_variables(string $type): array {
		$common = [
			'max_title_length',
			'max_description_length',
			'acf_all',
		];

		switch ($type) {
			case 'product':
				return array_merge($common, [
					'product_name',
					'product_description',
					'short_description',
					'sku',
					'price',
					'regular_price',
					'sale_price',
					'categories',
					'category_hierarchy',
					'tags',
					'attributes',
					'stock_status',
					'average_rating',
					'review_count',
				]);

			case 'variation':
				return array_merge($common, [
					'product_name',
					'variation_description',
					'variation_attributes',
					'variation_price',
					'variation_sku',
					'short_description',
					'category_hierarchy',
				]);

			case 'category':
				return array_merge($common, [
					'category_name',
					'category_description',
					'parent_category',
					'category_hierarchy',
					'product_count',
				]);

			default:
				return $common;
		}
	}
}
