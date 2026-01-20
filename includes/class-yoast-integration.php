<?php
/**
 * Yoast Integration Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Yoast Integration Class
 *
 * Handles reading and writing to Yoast SEO meta fields.
 *
 * @class Yoast_Integration
 */
class Yoast_Integration {

	/**
	 * Check if Yoast SEO is active.
	 *
	 * @return bool True if Yoast is active.
	 */
	public static function is_active(): bool {
		return defined('WPSEO_VERSION');
	}

	/**
	 * Get Yoast meta for an object.
	 *
	 * @param int    $object_id Object ID (post ID or term ID).
	 * @param string $object_type Object type (product, product_variation, product_cat).
	 * @return array Array with 'title' and 'description' keys.
	 */
	public function get_meta(int $object_id, string $object_type): array {
		if ($object_type === 'product_cat') {
			return [
				'title' => get_term_meta($object_id, 'wpseo_title', true),
				'description' => get_term_meta($object_id, 'wpseo_desc', true),
			];
		}

		// For products and variations.
		return [
			'title' => get_post_meta($object_id, '_yoast_wpseo_title', true),
			'description' => get_post_meta($object_id, '_yoast_wpseo_metadesc', true),
		];
	}

	/**
	 * Save meta to Yoast fields.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $object_type Object type.
	 * @param string $title Meta title.
	 * @param string $description Meta description.
	 * @return bool True on success.
	 */
	public function save_meta(int $object_id, string $object_type, string $title, string $description): bool {
		if ($object_type === 'product_cat') {
			update_term_meta($object_id, 'wpseo_title', sanitize_text_field($title));
			update_term_meta($object_id, 'wpseo_desc', sanitize_textarea_field($description));
			return true;
		}

		// For products and variations.
		update_post_meta($object_id, '_yoast_wpseo_title', sanitize_text_field($title));
		update_post_meta($object_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($description));
		return true;
	}

	/**
	 * Check if object has Yoast meta.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $object_type Object type.
	 * @return array Array with 'has_title', 'has_description', 'has_both' keys.
	 */
	public function has_meta(int $object_id, string $object_type): array {
		$meta = $this->get_meta($object_id, $object_type);

		return [
			'has_title' => !empty($meta['title']),
			'has_description' => !empty($meta['description']),
			'has_both' => !empty($meta['title']) && !empty($meta['description']),
		];
	}
}
