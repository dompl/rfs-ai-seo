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

		// Gemini API Key.
		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_gemini_api_key',
			[
				'type' => 'string',
				'sanitize_callback' => [$this, 'sanitize_gemini_api_key'],
				'default' => '',
			]
		);

		// Image Generation Settings.
		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_image_model',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'gemini-2.5-flash-image',
			]
		);

		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_image_aspect_ratio',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '1:1',
			]
		);

		// Display Currency.
		register_setting(
			'rfs_ai_seo_settings',
			'rfs_ai_seo_display_currency',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'USD',
			]
		);
	}

	/**
	 * Sanitize API key.
	 *
	 * Note: Encryption is handled by Settings::update_option(), not here.
	 * This callback is only for sanitization to avoid double-encryption.
	 *
	 * @param string $value API key value.
	 * @return string Sanitized value (passed through to update_option which encrypts).
	 */
	public function sanitize_api_key(string $value): string {
		// Just sanitize — do NOT encrypt here.
		// Encryption is handled by Settings::update_option().
		return sanitize_text_field($value);
	}

	/**
	 * Sanitize Gemini API key.
	 *
	 * Note: Encryption is handled by Settings::update_option(), not here.
	 * This callback is only for sanitization to avoid double-encryption.
	 *
	 * @param string $value Gemini API key value.
	 * @return string Sanitized value (passed through to update_option which encrypts).
	 */
	public function sanitize_gemini_api_key(string $value): string {
		// Just sanitize — do NOT encrypt here.
		return sanitize_text_field($value);
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

		// Decrypt API keys if requested.
		$encrypted_keys = [
			'rfs_ai_seo_openai_api_key',
			'rfs_ai_seo_gemini_api_key',
			'rfs_ai_seo_stripe_secret_key',
		];
		if (in_array($key, $encrypted_keys, true) && !empty($value)) {
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
		// Encrypt API keys if being updated.
		$encrypted_keys = [
			'rfs_ai_seo_openai_api_key',
			'rfs_ai_seo_gemini_api_key',
			'rfs_ai_seo_stripe_secret_key',
		];
		if (in_array($key, $encrypted_keys, true) && !empty($value)) {
			$value = $this->encrypt_api_key($value);
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
	 * Uses model-specific constants:
	 *   - RFS_AI_SEO_GPT_MULTIPLIER    (for OpenAI GPT models, default: 100)
	 *   - RFS_AI_SEO_GEMINI_MULTIPLIER  (for Google Gemini models, default: 100)
	 *
	 * Falls back to the legacy RFS_AI_SEO_MULTIPLYER constant if model-specific ones are not defined.
	 *
	 * @param string $model Optional model name to determine which multiplier to use.
	 * @return float Cost multiplier (default: 100).
	 */
	public function get_cost_multiplier(string $model = ''): float {
		$is_gemini = str_contains($model, 'gemini');

		// Check model-specific constants first.
		if ($is_gemini && defined('RFS_AI_SEO_GEMINI_MULTIPLIER') && RFS_AI_SEO_GEMINI_MULTIPLIER > 0) {
			return (float) RFS_AI_SEO_GEMINI_MULTIPLIER;
		}

		if (!$is_gemini && defined('RFS_AI_SEO_GPT_MULTIPLIER') && RFS_AI_SEO_GPT_MULTIPLIER > 0) {
			return (float) RFS_AI_SEO_GPT_MULTIPLIER;
		}

		// Fall back to legacy constant.
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

	/**
	 * Get company name.
	 *
	 * @return string Company name.
	 */
	public function get_company_name(): string {
		return get_option('rfs_ai_seo_company_name', '');
	}

	/**
	 * Get decrypted Gemini API key.
	 *
	 * @return string Decrypted Gemini API key or empty string.
	 */
	public function get_gemini_api_key(): string {
		if (defined('RFS_AI_SEO_GEMINI_KEY') && !empty(RFS_AI_SEO_GEMINI_KEY)) {
			return RFS_AI_SEO_GEMINI_KEY;
		}

		return $this->get_option('rfs_ai_seo_gemini_api_key', '');
	}

	/**
	 * Check if Gemini API key is defined as constant.
	 *
	 * @return bool True if constant is defined.
	 */
	public function is_gemini_key_constant(): bool {
		return defined('RFS_AI_SEO_GEMINI_KEY') && !empty(RFS_AI_SEO_GEMINI_KEY);
	}

	/**
	 * Get image generation model.
	 *
	 * @return string Image model name.
	 */
	public function get_image_model(): string {
		return $this->get_option('rfs_ai_seo_image_model', 'gemini-2.5-flash-image');
	}

	/**
	 * Get image aspect ratio.
	 *
	 * @return string Aspect ratio (1:1, 16:9, etc.).
	 */
	public function get_image_aspect_ratio(): string {
		return $this->get_option('rfs_ai_seo_image_aspect_ratio', '1:1');
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
	 * Get the display currency.
	 *
	 * @return string Currency code (USD, GBP, EUR).
	 */
	public function get_display_currency(): string {
		return $this->get_option('rfs_ai_seo_display_currency', 'USD');
	}

	/**
	 * Get the currency symbol for the display currency.
	 *
	 * @return string Currency symbol ($, £, or €).
	 */
	public function get_currency_symbol(): string {
		$symbols = [
			'USD' => '$',
			'GBP' => "\xC2\xA3",
			'EUR' => "\xE2\x82\xAC",
		];

		$currency = $this->get_display_currency();

		return $symbols[$currency] ?? '$';
	}

	/**
	 * Get exchange rates (USD base), cached in a transient.
	 *
	 * @return array Associative array of currency code => rate from USD.
	 */
	public function get_exchange_rates(): array {
		$cached = get_transient('rfs_ai_seo_exchange_rates');

		if ($cached !== false && is_array($cached)) {
			return $cached;
		}

		$rates = $this->fetch_ecb_rates();

		if (!empty($rates)) {
			set_transient('rfs_ai_seo_exchange_rates', $rates, DAY_IN_SECONDS);
			return $rates;
		}

		// Fallback hardcoded rates if ECB unavailable.
		$fallback = [
			'USD' => 1.0,
			'GBP' => 0.79,
			'EUR' => 0.92,
		];

		set_transient('rfs_ai_seo_exchange_rates', $fallback, HOUR_IN_SECONDS);

		return $fallback;
	}

	/**
	 * Fetch exchange rates from the ECB daily XML feed.
	 *
	 * Returns rates relative to USD (i.e., how much 1 USD is worth in each currency).
	 *
	 * @return array Associative array of currency code => rate from USD, or empty on failure.
	 */
	private function fetch_ecb_rates(): array {
		$response = wp_remote_get(
			'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
			['timeout' => 10]
		);

		if (is_wp_error($response)) {
			return [];
		}

		$body = wp_remote_retrieve_body($response);

		if (empty($body)) {
			return [];
		}

		// Suppress XML errors.
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		libxml_clear_errors();

		if ($xml === false) {
			return [];
		}

		// ECB rates are EUR-based. Extract USD and GBP rates from EUR.
		$eur_to_usd = 0;
		$eur_to_gbp = 0;

		$namespaces = $xml->getNamespaces(true);
		$cube_ns = $namespaces[''] ?? '';

		foreach ($xml->Cube->Cube->Cube as $rate) {
			$currency = (string) $rate['currency'];
			$value = (float) $rate['rate'];

			if ($currency === 'USD') {
				$eur_to_usd = $value;
			} elseif ($currency === 'GBP') {
				$eur_to_gbp = $value;
			}
		}

		if ($eur_to_usd <= 0) {
			return [];
		}

		// Convert to USD-based rates.
		return [
			'USD' => 1.0,
			'GBP' => $eur_to_gbp / $eur_to_usd,
			'EUR' => 1.0 / $eur_to_usd,
		];
	}

	/**
	 * Get decrypted Stripe secret key.
	 *
	 * @return string Stripe secret key or empty string.
	 */
	public function get_stripe_secret_key(): string {
		if (defined('RFS_AI_SEO_STRIPE_SECRET_KEY') && !empty(RFS_AI_SEO_STRIPE_SECRET_KEY)) {
			return RFS_AI_SEO_STRIPE_SECRET_KEY;
		}

		return $this->get_option('rfs_ai_seo_stripe_secret_key', '');
	}

	/**
	 * Get Stripe publishable key.
	 *
	 * @return string Stripe publishable key or empty string.
	 */
	public function get_stripe_publishable_key(): string {
		if (defined('RFS_AI_SEO_STRIPE_PUBLISHABLE_KEY') && !empty(RFS_AI_SEO_STRIPE_PUBLISHABLE_KEY)) {
			return RFS_AI_SEO_STRIPE_PUBLISHABLE_KEY;
		}

		return get_option('rfs_ai_seo_stripe_publishable_key', '');
	}

	/**
	 * Check if Stripe secret key is defined as constant.
	 *
	 * @return bool True if constant is defined.
	 */
	public function is_stripe_secret_constant(): bool {
		return defined('RFS_AI_SEO_STRIPE_SECRET_KEY') && !empty(RFS_AI_SEO_STRIPE_SECRET_KEY);
	}

	/**
	 * Check if Stripe publishable key is defined as constant.
	 *
	 * @return bool True if constant is defined.
	 */
	public function is_stripe_publishable_constant(): bool {
		return defined('RFS_AI_SEO_STRIPE_PUBLISHABLE_KEY') && !empty(RFS_AI_SEO_STRIPE_PUBLISHABLE_KEY);
	}

	/**
	 * Convert an amount from USD to the display currency.
	 *
	 * @param float $amount Amount in USD.
	 * @return float Converted amount.
	 */
	public function convert_from_usd(float $amount): float {
		$currency = $this->get_display_currency();

		if ($currency === 'USD') {
			return $amount;
		}

		$rates = $this->get_exchange_rates();
		$rate = $rates[$currency] ?? 1.0;

		return $amount * $rate;
	}

	/**
	 * Format a cost in the display currency.
	 *
	 * @param float $amount_usd Amount in USD.
	 * @param int   $decimals   Number of decimal places (default: 2).
	 * @return string Formatted cost string (e.g., "$1.23" or "£0.98").
	 */
	public function format_cost(float $amount_usd, int $decimals = 2): string {
		$converted = $this->convert_from_usd($amount_usd);
		$symbol = $this->get_currency_symbol();

		return $symbol . number_format($converted, $decimals);
	}
}
