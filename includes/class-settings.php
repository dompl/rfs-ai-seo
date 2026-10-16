<?php
/**
 * Settings Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings Class
 *
 * Handles plugin settings, API key encryption, and options management.
 *
 * @class Settings
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register settings.
		add_action('admin_init', [$this, 'register_settings']);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// API Settings.
		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_openai_model',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'gpt-4o',
			]
		);

		// Prompt Templates.
		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_prompt_product',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default' => '',
			]
		);

		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_prompt_variation',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default' => '',
			]
		);

		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_prompt_category',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default' => '',
			]
		);

		// Content Settings.
		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_title_max_length',
			[
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 60,
			]
		);

		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_description_max_length',
			[
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 155,
			]
		);

		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_acf_fields',
			[
				'type' => 'array',
				'sanitize_callback' => [$this, 'sanitize_acf_fields'],
				'default' => [],
			]
		);

	}

	/**
	 * Sanitize ACF fields array.
	 *
	 * @param array|mixed $value ACF fields array.
	 * @return array Sanitized array.
	 */
	public function sanitize_acf_fields($value): array {
		if (!is_array($value)) {
			return [];
		}

		return array_map('sanitize_text_field', array_filter($value));
	}

	/**
	 * Get plugin option.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	public function get_option(string $key, $default = null) {
		return get_option($key, $default);
	}

	/**
	 * Update plugin option.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @return bool True on success, false on failure.
	 */
	public function update_option(string $key, $value): bool {
		return update_option($key, $value);
	}

	/**
	 * Get selected OpenAI model.
	 *
	 * @return string Model name.
	 */
	public function get_model(): string {
		return $this->get_option('rfs_ai_seo_openai_model', 'gpt-4o');
	}

	/**
	 * Get prompt template.
	 *
	 * @param string $type Prompt type (product, variation, category).
	 * @return string Prompt template.
	 */
	public function get_prompt(string $type): string {
		return $this->get_option("rfs_ai_seo_prompt_{$type}", '');
	}

	/**
	 * Get title max length.
	 *
	 * @return int Max length.
	 */
	public function get_title_max_length(): int {
		return (int) $this->get_option('rfs_ai_seo_title_max_length', 60);
	}

	/**
	 * Get description max length.
	 *
	 * @return int Max length.
	 */
	public function get_description_max_length(): int {
		return (int) $this->get_option('rfs_ai_seo_description_max_length', 155);
	}

	public const IMAGE_QUALITIES = ['low', 'medium', 'high'];
	public const IMAGE_SIZES = ['1024x1024', '1536x1024', '1024x1536'];

	/** Tone keys and labels for the content generator. */
	public static function tones(): array {
		return [
			'professional' => __('Professional', 'rfs-ai-seo'),
			'friendly' => __('Friendly', 'rfs-ai-seo'),
			'technical' => __('Technical', 'rfs-ai-seo'),
		];
	}

	public function get_image_quality(): string {
		$v = (string) $this->get_option('rfs_ai_seo_image_quality', 'medium');
		return in_array($v, self::IMAGE_QUALITIES, true) ? $v : 'medium';
	}

	public function get_image_size(): string {
		$v = (string) $this->get_option('rfs_ai_seo_image_size', '1024x1024');
		return in_array($v, self::IMAGE_SIZES, true) ? $v : '1024x1024';
	}

	public function get_content_tone(): string {
		$v = (string) $this->get_option('rfs_ai_seo_content_tone', 'professional');
		return array_key_exists($v, self::tones()) ? $v : 'professional';
	}

	public function get_description_words(): int {
		return max(50, min(1000, (int) $this->get_option('rfs_ai_seo_description_words', 150)));
	}

	public function get_short_sentences(): int {
		return max(1, min(6, (int) $this->get_option('rfs_ai_seo_short_sentences', 2)));
	}

	public function get_include_features(): bool {
		return $this->get_option('rfs_ai_seo_include_features', '1') === '1';
	}

	/**
	 * Get ACF fields list.
	 *
	 * @return array ACF field names.
	 */
	public function get_acf_fields(): array {
		$fields = $this->get_option('rfs_ai_seo_acf_fields', []);

		return is_array($fields) ? $fields : [];
	}

	/**
	 * Get company name.
	 *
	 * @return string Company name.
	 */
	public function get_company_name(): string {
		return get_option('rfs_ai_seo_company_name', '');
	}

	/**
	 * Get predefined image prompt templates.
	 *
	 * @return array Array of prompt templates with 'title' and 'description' keys.
	 */
	public function get_image_prompts(): array {
		$prompts = $this->get_option('rfs_ai_seo_image_prompts', []);

		return is_array($prompts) ? $prompts : [];
	}

	/**
	 * Format a pound amount for display.
	 *
	 * @param float $gbp Amount in GBP.
	 * @return string Formatted cost string (e.g., "£1.23").
	 */
	public function format_gbp(float $gbp): string {
		return '£' . number_format($gbp, 2);
	}
}
