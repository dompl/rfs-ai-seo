<?php
/**
 * Admin Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Admin Class
 *
 * Handles admin menu registration and admin-related functionality.
 *
 * @class Admin
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register admin menu.
		add_action('admin_menu', [$this, 'register_menu']);

		// Add admin notices.
		add_action('admin_notices', [$this, 'add_admin_notices']);

		// Register AJAX handlers.
		add_action('wp_ajax_rfs_ai_seo_test_connection', [$this, 'ajax_test_connection']);
		add_action('wp_ajax_rfs_ai_seo_create_checkout', [$this, 'ajax_create_checkout']);
		add_action('wp_ajax_rfs_ai_seo_test_stripe', [$this, 'ajax_test_stripe']);

		// Register admin post handlers.
		add_action('admin_post_rfs_ai_seo_export_csv', [$this, 'export_csv']);
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Add top-level RFS AI menu.
		add_menu_page(
			__('RFS AI - AI-Powered Content Generation', 'rfs-ai-seo'),
			__('RFS AI', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai',
			[$this, 'render_dashboard_page'],
			'dashicons-superhero',
			58
		);

		// Dashboard (Overview) - default page.
		add_submenu_page(
			'rfs-ai',
			__('RFS AI Dashboard', 'rfs-ai-seo'),
			__('Dashboard', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai',
			[$this, 'render_dashboard_page']
		);

		// Settings - API configuration.
		add_submenu_page(
			'rfs-ai',
			__('Settings - API & Configuration', 'rfs-ai-seo'),
			__('Settings', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-settings',
			[$this, 'render_settings_page']
		);

		// AI Trainer - Business context.
		add_submenu_page(
			'rfs-ai',
			__('AI Trainer - Teach AI About Your Business', 'rfs-ai-seo'),
			__('AI Trainer', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-trainer',
			[$this, 'render_trainer_page']
		);

		// SEO Meta Generator.
		add_submenu_page(
			'rfs-ai',
			__('SEO Meta Generator - Titles & Descriptions', 'rfs-ai-seo'),
			__('SEO Meta Generator', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-seo-bulk',
			[$this, 'render_seo_bulk_page']
		);

		// Product Description Generator.
		add_submenu_page(
			'rfs-ai',
			__('Description Generator - Full Product Descriptions', 'rfs-ai-seo'),
			__('Description Generator', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-description-bulk',
			[$this, 'render_description_bulk_page']
		);

		// Short Description Generator.
		add_submenu_page(
			'rfs-ai',
			__('Short Description Generator - Product Summaries', 'rfs-ai-seo'),
			__('Short Description Generator', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-short-description-bulk',
			[$this, 'render_short_description_bulk_page']
		);

		// Image Generator.
		add_submenu_page(
			'rfs-ai',
			__('Image Generator - AI Product Images', 'rfs-ai-seo'),
			__('Image Generator', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-image-bulk',
			[$this, 'render_image_bulk_page']
		);

		// Alt Text Generator.
		add_submenu_page(
			'rfs-ai',
			__('Alt Text Generator - AI Image Alt Text', 'rfs-ai-seo'),
			__('Alt Text Generator', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-alt-text-bulk',
			[$this, 'render_alt_text_bulk_page']
		);

		// Buy Credits.
		add_submenu_page(
			'rfs-ai',
			__('Buy Credits - Pre-paid AI Generation Credits', 'rfs-ai-seo'),
			__('Buy Credits', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-credits',
			[$this, 'render_credits_page']
		);

		// Usage Statistics.
		add_submenu_page(
			'rfs-ai',
			__('Usage Statistics - Track Token Usage & Costs', 'rfs-ai-seo'),
			__('Usage Statistics', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-stats',
			[$this, 'render_stats_page']
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Get settings instance.
		$settings = new Settings();

		// Handle form submission.
		if (isset($_POST['rfs_ai_seo_settings_nonce']) && wp_verify_nonce($_POST['rfs_ai_seo_settings_nonce'], 'rfs_ai_seo_settings')) {
			$this->save_settings();
		}

		// Get current tab.
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';

		// Include settings page template.
		include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Include dashboard page template.
		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/dashboard-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/dashboard-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('RFS AI Dashboard', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('Dashboard functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render SEO bulk generator page.
	 *
	 * @return void
	 */
	public function render_seo_bulk_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Include bulk generator page template.
		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/bulk-generator-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/bulk-generator-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Bulk Generate', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('Bulk generation functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render usage statistics page.
	 *
	 * @return void
	 */
	public function render_stats_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Get logger instance.
		$logger = new Usage_Logger();

		// Get stats for current month.
		$stats = $logger->get_stats('month');

		// Get recent logs.
		$recent_logs = $logger->get_recent_logs(50);

		// Include stats page template.
		include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/usage-stats-page.php';
	}

	/**
	 * Render description bulk generator page.
	 *
	 * @return void
	 */
	public function render_description_bulk_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Include description bulk generator page template.
		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/description-bulk-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/description-bulk-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Product Description Generator', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('Description generation functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render short description bulk generator page.
	 *
	 * @return void
	 */
	public function render_short_description_bulk_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Include short description bulk generator page template.
		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/short-description-bulk-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/short-description-bulk-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Product Short Description Generator', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('Short description generation functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render AI Trainer page.
	 *
	 * @return void
	 */
	public function render_trainer_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Get settings instance.
		$settings = new Settings();

		// Handle form submission.
		if (isset($_POST['rfs_ai_trainer_nonce']) && wp_verify_nonce($_POST['rfs_ai_trainer_nonce'], 'rfs_ai_trainer_save')) {
			$this->save_trainer();
		}

		// Include AI Trainer page template.
		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/ai-trainer-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/ai-trainer-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('AI Trainer', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('AI Trainer functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render image bulk generator page.
	 *
	 * @return void
	 */
	public function render_image_bulk_page(): void {
		// Check user capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Include image bulk generator page template.
		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/image-bulk-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/image-bulk-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Image Generator', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('Image generation functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render alt text bulk generator page.
	 *
	 * @return void
	 */
	public function render_alt_text_bulk_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		if (file_exists(RFS_AI_SEO_PLUGIN_DIR . 'admin/views/alt-text-bulk-page.php')) {
			include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/alt-text-bulk-page.php';
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Alt Text Generator', 'rfs-ai-seo') . '</h1>';
			echo '<p>' . esc_html__('Alt text generation functionality coming soon...', 'rfs-ai-seo') . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Render credits page.
	 *
	 * @return void
	 */
	public function render_credits_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rfs-ai-seo'));
		}

		// Ensure balance is initialised.
		Credits_Manager::maybe_init_balance();

		// Handle Stripe return.
		if (isset($_GET['stripe_session_id'])) {
			$this->handle_stripe_return(sanitize_text_field($_GET['stripe_session_id']));
		}

		include RFS_AI_SEO_PLUGIN_DIR . 'admin/views/credits-page.php';
	}

	/**
	 * Handle Stripe return after payment.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return void
	 */
	private function handle_stripe_return(string $session_id): void {
		$settings = new Settings();
		$secret_key = $settings->get_stripe_secret_key();

		if (empty($secret_key)) {
			add_settings_error(
				'rfs_ai_seo_messages',
				'rfs_ai_seo_message',
				__('Stripe is not configured. Cannot verify payment.', 'rfs-ai-seo'),
				'error'
			);
			return;
		}

		$stripe = new Stripe_Client($secret_key);
		$session = $stripe->retrieve_checkout_session($session_id);

		if (is_wp_error($session)) {
			add_settings_error(
				'rfs_ai_seo_messages',
				'rfs_ai_seo_message',
				$session->get_error_message(),
				'error'
			);
			return;
		}

		$payment_status = $session['payment_status'] ?? '';

		if ($payment_status !== 'paid') {
			add_settings_error(
				'rfs_ai_seo_messages',
				'rfs_ai_seo_message',
				__('Payment was not completed. Please try again.', 'rfs-ai-seo'),
				'error'
			);
			return;
		}

		$metadata = $session['metadata'] ?? [];
		$credits_gbp = (float) ($metadata['credits_gbp'] ?? 0);
		$package_id = $metadata['package_id'] ?? '';

		if ($credits_gbp <= 0) {
			add_settings_error(
				'rfs_ai_seo_messages',
				'rfs_ai_seo_message',
				__('Invalid credit amount in payment session.', 'rfs-ai-seo'),
				'error'
			);
			return;
		}

		$added = Credits_Manager::add_credits($credits_gbp, $session_id, $package_id);

		if ($added) {
			add_settings_error(
				'rfs_ai_seo_messages',
				'rfs_ai_seo_message',
				sprintf(
					/* translators: %s: credit amount */
					__('Payment successful! %s credits have been added to your balance.', 'rfs-ai-seo'),
					"\xC2\xA3" . number_format($credits_gbp, 2)
				),
				'success'
			);
		} else {
			add_settings_error(
				'rfs_ai_seo_messages',
				'rfs_ai_seo_message',
				__('This payment has already been processed.', 'rfs-ai-seo'),
				'info'
			);
		}
	}

	/**
	 * AJAX handler for creating a Stripe Checkout Session.
	 *
	 * @return void
	 */
	public function ajax_create_checkout(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
		$packages = Credits_Manager::get_packages();

		if (!isset($packages[$package_id])) {
			wp_send_json_error([
				'message' => __('Invalid package selected.', 'rfs-ai-seo'),
			]);
		}

		$settings = new Settings();
		$secret_key = $settings->get_stripe_secret_key();

		if (empty($secret_key)) {
			wp_send_json_error([
				'message' => __('Stripe is not configured. Please add your Stripe keys in Settings.', 'rfs-ai-seo'),
			]);
		}

		$package = $packages[$package_id];
		$credits_page_url = admin_url('admin.php?page=rfs-ai-credits');
		$success_url = add_query_arg('stripe_session_id', '{CHECKOUT_SESSION_ID}', $credits_page_url);
		$cancel_url = $credits_page_url;

		$stripe = new Stripe_Client($secret_key);
		$result = $stripe->create_checkout_session($package, $success_url, $cancel_url);

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
			]);
		}

		wp_send_json_success([
			'checkout_url' => $result['checkout_url'],
		]);
	}

	/**
	 * AJAX handler for testing Stripe connection.
	 *
	 * @return void
	 */
	public function ajax_test_stripe(): void {
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		$settings = new Settings();
		$secret_key = $settings->get_stripe_secret_key();

		if (empty($secret_key)) {
			wp_send_json_error([
				'message' => __('Stripe secret key is not configured.', 'rfs-ai-seo'),
			]);
		}

		$stripe = new Stripe_Client($secret_key);
		$result = $stripe->verify_account();

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
			]);
		}

		wp_send_json_success([
			'message' => __('Stripe account verified successfully! Connected to Red Frog Studio account.', 'rfs-ai-seo'),
		]);
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	private function save_settings(): void {
		$settings = new Settings();

		// Save API settings.
		// Only save API key to database if not defined as constant.
		if (!$settings->is_api_key_constant() && isset($_POST['rfs_ai_seo_openai_api_key'])) {
			$api_key = sanitize_text_field($_POST['rfs_ai_seo_openai_api_key']);
			// Only update if a new key was entered (non-empty).
			// Empty value means keep the existing key.
			if (!empty($api_key)) {
				$settings->update_option('rfs_ai_seo_openai_api_key', $api_key);
			}
		}

		if (isset($_POST['rfs_ai_seo_openai_model'])) {
			update_option('rfs_ai_seo_openai_model', sanitize_text_field($_POST['rfs_ai_seo_openai_model']));
		}

		// Save prompt templates.
		if (isset($_POST['rfs_ai_seo_prompt_product'])) {
			update_option('rfs_ai_seo_prompt_product', sanitize_textarea_field($_POST['rfs_ai_seo_prompt_product']));
		}

		if (isset($_POST['rfs_ai_seo_prompt_variation'])) {
			update_option('rfs_ai_seo_prompt_variation', sanitize_textarea_field($_POST['rfs_ai_seo_prompt_variation']));
		}

		if (isset($_POST['rfs_ai_seo_prompt_category'])) {
			update_option('rfs_ai_seo_prompt_category', sanitize_textarea_field($_POST['rfs_ai_seo_prompt_category']));
		}

		// Save image prompt templates.
		if (isset($_POST['rfs_ai_seo_image_prompts_title'])) {
			$titles = array_map('sanitize_text_field', $_POST['rfs_ai_seo_image_prompts_title']);
			$descriptions = isset($_POST['rfs_ai_seo_image_prompts_description'])
				? array_map('sanitize_textarea_field', $_POST['rfs_ai_seo_image_prompts_description'])
				: [];

			$image_prompts = [];
			foreach ($titles as $i => $title) {
				$desc = $descriptions[$i] ?? '';
				// Only save rows that have at least a title or description.
				if (!empty($title) || !empty($desc)) {
					$image_prompts[] = [
						'title' => $title,
						'description' => $desc,
					];
				}
			}
			update_option('rfs_ai_seo_image_prompts', $image_prompts);
		} elseif (isset($_POST['rfs_ai_seo_settings_nonce'])) {
			// If on the prompts tab but no image prompts submitted, clear them.
			$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';
			if ($active_tab === 'prompts') {
				update_option('rfs_ai_seo_image_prompts', []);
			}
		}

		// Save company name.
		if (isset($_POST['rfs_ai_seo_company_name'])) {
			update_option('rfs_ai_seo_company_name', sanitize_text_field($_POST['rfs_ai_seo_company_name']));
		}

		// Save content settings.
		if (isset($_POST['rfs_ai_seo_title_max_length'])) {
			update_option('rfs_ai_seo_title_max_length', absint($_POST['rfs_ai_seo_title_max_length']));
		}

		if (isset($_POST['rfs_ai_seo_description_max_length'])) {
			update_option('rfs_ai_seo_description_max_length', absint($_POST['rfs_ai_seo_description_max_length']));
		}

		// Save ACF fields.
		if (isset($_POST['rfs_ai_seo_acf_fields'])) {
			$acf_fields = array_map('sanitize_text_field', array_filter($_POST['rfs_ai_seo_acf_fields']));
			update_option('rfs_ai_seo_acf_fields', $acf_fields);
		}

		// Save Stripe keys.
		if (!$settings->is_stripe_secret_constant() && isset($_POST['rfs_ai_seo_stripe_secret_key'])) {
			$stripe_secret = sanitize_text_field($_POST['rfs_ai_seo_stripe_secret_key']);
			if (!empty($stripe_secret)) {
				$settings->update_option('rfs_ai_seo_stripe_secret_key', $stripe_secret);
			}
		}

		if (!$settings->is_stripe_publishable_constant() && isset($_POST['rfs_ai_seo_stripe_publishable_key'])) {
			$stripe_pub = sanitize_text_field($_POST['rfs_ai_seo_stripe_publishable_key']);
			// Publishable key is not a secret, allow saving even if empty (to clear it).
			update_option('rfs_ai_seo_stripe_publishable_key', $stripe_pub);
		}

		// Save Gemini API key.
		if (!$settings->is_gemini_key_constant() && isset($_POST['rfs_ai_seo_gemini_api_key'])) {
			$gemini_key = sanitize_text_field($_POST['rfs_ai_seo_gemini_api_key']);
			if (!empty($gemini_key)) {
				$settings->update_option('rfs_ai_seo_gemini_api_key', $gemini_key);
			}
		}

		// Save image generation settings.
		if (isset($_POST['rfs_ai_seo_image_model'])) {
			update_option('rfs_ai_seo_image_model', sanitize_text_field($_POST['rfs_ai_seo_image_model']));
		}

		if (isset($_POST['rfs_ai_seo_image_aspect_ratio'])) {
			update_option('rfs_ai_seo_image_aspect_ratio', sanitize_text_field($_POST['rfs_ai_seo_image_aspect_ratio']));
		}

		// Save display currency.
		if (isset($_POST['rfs_ai_seo_display_currency'])) {
			$currency = sanitize_text_field($_POST['rfs_ai_seo_display_currency']);
			if (in_array($currency, ['USD', 'GBP', 'EUR'], true)) {
				update_option('rfs_ai_seo_display_currency', $currency);
				// Clear cached exchange rates so fresh rates are fetched.
				delete_transient('rfs_ai_seo_exchange_rates');
			}
		}

		// Add success notice.
		add_settings_error(
			'rfs_ai_seo_messages',
			'rfs_ai_seo_message',
			__('Settings saved successfully.', 'rfs-ai-seo'),
			'success'
		);
	}

	/**
	 * Save AI Trainer data.
	 *
	 * @return void
	 */
	private function save_trainer(): void {
		// Save business context.
		if (isset($_POST['rfs_ai_trainer_business_context'])) {
			update_option('rfs_ai_trainer_business_context', wp_kses_post($_POST['rfs_ai_trainer_business_context']));
		}

		// Add success notice.
		add_settings_error(
			'rfs_ai_seo_messages',
			'rfs_ai_seo_message',
			__('AI Trainer saved successfully.', 'rfs-ai-seo'),
			'success'
		);
	}

	/**
	 * Display admin notices.
	 *
	 * @return void
	 */
	public function add_admin_notices(): void {
		settings_errors('rfs_ai_seo_messages');
	}

	/**
	 * AJAX handler for testing OpenAI connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		// Verify nonce.
		check_ajax_referer('rfs_ai_seo_nonce', 'nonce');

		// Check capabilities.
		if (!current_user_can('manage_options')) {
			wp_send_json_error([
				'message' => __('You do not have permission to perform this action.', 'rfs-ai-seo'),
			]);
		}

		// Get settings.
		$settings = new Settings();
		$api_key = $settings->get_api_key();
		$model = $settings->get_model();
		$is_constant = $settings->is_api_key_constant();

		// Debug logging.
		error_log('RFS AI SEO Test Connection - API Key Source: ' . ($is_constant ? 'wp-config.php constant' : 'database'));
		error_log('RFS AI SEO Test Connection - API Key Length: ' . strlen($api_key));
		error_log('RFS AI SEO Test Connection - API Key Prefix: ' . substr($api_key, 0, 10) . '...');
		error_log('RFS AI SEO Test Connection - Model: ' . $model);

		// Check if API key is set.
		if (empty($api_key)) {
			wp_send_json_error([
				'message' => __('Please enter an API key and save settings before testing the connection.', 'rfs-ai-seo'),
			]);
		}

		// Create OpenAI client.
		$client = new OpenAI_Client($api_key, $model);

		// Test connection.
		$result = $client->test_connection();

		if (is_wp_error($result)) {
			error_log('RFS AI SEO Test Connection Error: ' . $result->get_error_message());
			wp_send_json_error([
				'message' => $result->get_error_message(),
			]);
		}

		// Success.
		wp_send_json_success([
			'message' => sprintf(
				/* translators: %s: model name */
				__('Connection successful! Using model: %s', 'rfs-ai-seo'),
				$model
			),
		]);
	}

	/**
	 * Export CSV handler.
	 *
	 * @return void
	 */
	public function export_csv(): void {
		// Check nonce.
		if (!isset($_POST['rfs_ai_seo_export_nonce']) || !wp_verify_nonce($_POST['rfs_ai_seo_export_nonce'], 'rfs_ai_seo_export_csv')) {
			wp_die(esc_html__('Security check failed.', 'rfs-ai-seo'));
		}

		// Check capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'rfs-ai-seo'));
		}

		// Get date range.
		$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
		$end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

		// Get logger.
		$logger = new Usage_Logger();

		// Generate CSV.
		$csv = $logger->export_csv($start_date, $end_date);

		// Set headers for download.
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=rfs-ai-seo-usage-' . $start_date . '-to-' . $end_date . '.csv');
		header('Pragma: no-cache');
		header('Expires: 0');

		// Output CSV.
		echo $csv;
		exit;
	}
}
