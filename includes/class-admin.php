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
	 * Save settings.
	 *
	 * @return void
	 */
	private function save_settings(): void {
		$settings = new Settings();

		if (isset($_POST['rfs_ai_seo_openai_model'])) {
			update_option('rfs_ai_seo_openai_model', sanitize_text_field($_POST['rfs_ai_seo_openai_model']));
		}

		// Save image and content default settings.
		foreach (['rfs_ai_seo_image_quality', 'rfs_ai_seo_image_size', 'rfs_ai_seo_content_tone'] as $name) {
			if (isset($_POST[$name])) {
				update_option($name, sanitize_text_field(wp_unslash($_POST[$name])));
			}
		}
		foreach (['rfs_ai_seo_description_words', 'rfs_ai_seo_short_sentences'] as $name) {
			if (isset($_POST[$name])) {
				update_option($name, (string) absint($_POST[$name]));
			}
		}
		update_option('rfs_ai_seo_include_features', empty($_POST['rfs_ai_seo_include_features']) ? '0' : '1');

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
		$model = $settings->get_model();

		$result = (new OpenAI_Client($model, 'ats:content:meta'))->test_connection();

		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
				'code' => $result->get_error_code(),
			]);
		}

		wp_send_json_success([
			'message' => __('Connected to the Red Frog AI portal.', 'rfs-ai-seo'),
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
