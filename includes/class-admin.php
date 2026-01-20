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

		// Register admin post handlers.
		add_action('admin_post_rfs_ai_seo_export_csv', [$this, 'export_csv']);
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Add main settings page under WooCommerce.
		add_submenu_page(
			'woocommerce',
			__('AI SEO Settings', 'rfs-ai-seo'),
			__('AI SEO', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-seo',
			[$this, 'render_settings_page']
		);

		// Bulk Generate submenu.
		add_submenu_page(
			'woocommerce',
			__('AI SEO Bulk Generate', 'rfs-ai-seo'),
			__('AI SEO Bulk', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-seo-bulk',
			[$this, 'render_bulk_page']
		);

		// Usage Stats submenu.
		add_submenu_page(
			'woocommerce',
			__('AI SEO Usage Statistics', 'rfs-ai-seo'),
			__('AI SEO Stats', 'rfs-ai-seo'),
			'manage_options',
			'rfs-ai-seo-stats',
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
	 * Render bulk generator page.
	 *
	 * @return void
	 */
	public function render_bulk_page(): void {
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

		// Add success notice.
		add_settings_error(
			'rfs_ai_seo_messages',
			'rfs_ai_seo_message',
			__('Settings saved successfully.', 'rfs-ai-seo'),
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
