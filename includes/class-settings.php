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
			'rfs_ai_seo_openai_api_key',
			[
				'type' => 'string',
				'sanitize_callback' => [$this, 'sanitize_api_key'],
				'default' => '',
			]
		);

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
	 * Sanitize and encrypt API key.
	 *
	 * @param string $value API key value.
	 * @return string Encrypted API key.
	 */
	public function sanitize_api_key(string $value): string {
		$value = sanitize_text_field($value);

		// If empty, keep the existing value.
		if (empty($value)) {
			return get_option('rfs_ai_seo_openai_api_key', '');
		}

		// Encrypt the new key.
		return $this->encrypt_api_key($value);
	}

	/**
	 * Encrypt API key using WordPress salt.
	 *
	 * @param string $key API key to encrypt.
	 * @return string Encrypted API key.
	 */
	public function encrypt_api_key(string $key): string {
		if (empty($key)) {
			return '';
		}

		$salt = wp_salt('auth');
		$iv = openssl_random_pseudo_bytes(16);
		$encrypted = openssl_encrypt($key, 'AES-256-CBC', $salt, 0, $iv);

		return base64_encode($iv . $encrypted);
	}

	/**
	 * Decrypt API key.
	 *
	 * @param string $encrypted Encrypted API key.
	 * @return string Decrypted API key.
	 */
	public function decrypt_api_key(string $encrypted): string {
		if (empty($encrypted)) {
			error_log('RFS AI SEO: decrypt_api_key - Empty encrypted value');
			return '';
		}

		$salt = wp_salt('auth');
		$data = base64_decode($encrypted);

		if ($data === false) {
			error_log('RFS AI SEO: decrypt_api_key - Base64 decode failed');
			return '';
		}

		if (strlen($data) < 16) {
			error_log('RFS AI SEO: decrypt_api_key - Data too short: ' . strlen($data) . ' bytes');
			return '';
		}

		$iv = substr($data, 0, 16);
		$encrypted_data = substr($data, 16);

		$decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $salt, 0, $iv);

		if ($decrypted === false) {
			error_log('RFS AI SEO: decrypt_api_key - Decryption failed');
		} else {
			error_log('RFS AI SEO: decrypt_api_key - Decrypted successfully, length: ' . strlen($decrypted));
		}

		return $decrypted !== false ? $decrypted : '';
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
		$value = get_option($key, $default);

		// Decrypt API key if requested.
		if ($key === 'rfs_ai_seo_openai_api_key' && !empty($value)) {
			return $this->decrypt_api_key($value);
		}

		return $value;
	}

	/**
	 * Update plugin option.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @return bool True on success, false on failure.
	 */
	public function update_option(string $key, $value): bool {
		// Encrypt API key if being updated.
		if ($key === 'rfs_ai_seo_openai_api_key' && !empty($value)) {
			error_log('RFS AI SEO: update_option - Encrypting API key, original length: ' . strlen($value));
			$value = $this->encrypt_api_key($value);
			error_log('RFS AI SEO: update_option - Encrypted API key, encrypted length: ' . strlen($value));
		}

		return update_option($key, $value);
	}

	/**
	 * Get decrypted API key.
	 *
	 * @return string Decrypted API key or empty string.
	 */
	public function get_api_key(): string {
		// Check if API key is defined as a constant in wp-config.php (recommended).
		if (defined('RFS_AI_SEO_OPEN_AI_KEY') && !empty(RFS_AI_SEO_OPEN_AI_KEY)) {
			return RFS_AI_SEO_OPEN_AI_KEY;
		}

		// Fall back to database stored key.
		return $this->get_option('rfs_ai_seo_openai_api_key', '');
	}

	/**
	 * Check if API key is defined as constant.
	 *
	 * @return bool True if constant is defined.
	 */
	public function is_api_key_constant(): bool {
		return defined('RFS_AI_SEO_OPEN_AI_KEY') && !empty(RFS_AI_SEO_OPEN_AI_KEY);
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
	 * Get cost multiplier from wp-config.
	 *
	 * @return float Cost multiplier (default: 100).
	 */
	public function get_cost_multiplier(): float {
		if (defined('RFS_AI_SEO_MULTIPLYER') && RFS_AI_SEO_MULTIPLYER > 0) {
			return (float) RFS_AI_SEO_MULTIPLYER;
		}

		return 100.0;
	}

	/**
	 * Get monthly spending cap from wp-config.
	 *
	 * @return float Spending cap in dollars (default: 10).
	 */
	public function get_spending_cap(): float {
		if (defined('RFS_AI_SEO_CAP') && RFS_AI_SEO_CAP > 0) {
			return (float) RFS_AI_SEO_CAP;
		}

		return 10.0;
	}

	/**
	 * Check if spending cap is defined.
	 *
	 * @return bool True if cap is defined.
	 */
	public function has_spending_cap(): bool {
		return defined('RFS_AI_SEO_CAP') && RFS_AI_SEO_CAP > 0;
	}
}
