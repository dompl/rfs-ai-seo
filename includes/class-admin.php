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
		add_action('admin_menu', [$this, 'register_menu'], 11);
		add_filter('parent_file', [$this, 'parent_file'], 20);

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
	/** Admin pages: slug => [page title, menu label, render callback]. The first entry is the dashboard. */
	public const PAGES = [
		'rfs-ai'                        => ['AI SEO Dashboard', 'Dashboard', 'render_dashboard_page'],
		'rfs-ai-settings'               => ['Settings - API & Configuration', 'Settings', 'render_settings_page'],
		'rfs-ai-trainer'                => ['AI Trainer - Teach AI About Your Business', 'AI Trainer', 'render_trainer_page'],
		'rfs-ai-seo-bulk'               => ['SEO Meta Generator - Titles & Descriptions', 'SEO Meta', 'render_seo_bulk_page'],
		'rfs-ai-description-bulk'       => ['Description Generator - Full Product Descriptions', 'Descriptions', 'render_description_bulk_page'],
		'rfs-ai-short-description-bulk' => ['Short Description Generator - Product Summaries', 'Short Descriptions', 'render_short_description_bulk_page'],
		'rfs-ai-image-bulk'             => ['Image Generator - AI Product Images', 'Images', 'render_image_bulk_page'],
		'rfs-ai-alt-text-bulk'          => ['Alt Text Generator - AI Image Alt Text', 'Alt Text', 'render_alt_text_bulk_page'],
		'rfs-ai-stats'                  => ['Usage Statistics - Track Token Usage & Costs', 'Usage', 'render_stats_page'],
	];

	/** Parent menu slug of the shared "ATS AI" menu registered by ats-ai-portal. */
	public const PORTAL_PARENT = 'ats-ai-credits';

	/**
	 * Whether the ats-ai-portal "ATS AI" parent menu exists (it registers at admin_menu priority 10, we run at 11).
	 */
	public static function portal_parent_exists(): bool {
		return isset($GLOBALS['admin_page_hooks'][self::PORTAL_PARENT]);
	}

	/**
	 * Register the admin menu.
	 *
	 * With ats-ai-portal active, AI SEO is a single entry under the shared "ATS AI" menu and its
	 * tool pages are hidden pages (parent '') reached through the in-page sub-navigation.
	 * Without it, the plugin keeps its own top-level menu with every page listed.
	 */
	public function register_menu(): void {
		$nested = self::portal_parent_exists();
		$first  = true;
		foreach (self::PAGES as $slug => [$title, $label, $callback]) {
			if ($first && $nested) {
				add_submenu_page(self::PORTAL_PARENT, __('AI SEO', 'rfs-ai-seo'), __('AI SEO', 'rfs-ai-seo'), 'manage_options', $slug, [$this, $callback]);
			} elseif ($first) {
				add_menu_page(__('AI SEO - AI-Powered Content Generation', 'rfs-ai-seo'), __('AI SEO', 'rfs-ai-seo'), 'manage_options', $slug, [$this, $callback], 'dashicons-superhero', 58);
				add_submenu_page($slug, __($title, 'rfs-ai-seo'), __($label, 'rfs-ai-seo'), 'manage_options', $slug, [$this, $callback]);
			} else {
				add_submenu_page($nested ? '' : 'rfs-ai', __($title, 'rfs-ai-seo'), __($label, 'rfs-ai-seo'), 'manage_options', $slug, [$this, $callback]);
			}
			$first = false;
		}
	}

	/**
	 * Keep the "ATS AI" menu open and "AI SEO" highlighted on the hidden tool pages.
	 */
	/**
	 * Remove this plugin's pages from the hidden ('' parent) submenu list.
	 *
	 * wp-admin/menu-header.php calls get_admin_page_parent() right after the
	 * `parent_file` filter; for a page registered under the hidden parent that
	 * call resets $parent_file to '' and the ATS AI menu collapses. The hidden
	 * entries are never rendered, so dropping them at this point is safe.
	 */
	private static function drop_hidden_entries(array $slugs): void {
		if (empty($GLOBALS['submenu'][''])) {
			return;
		}
		$GLOBALS['submenu'][''] = array_values(array_filter(
			$GLOBALS['submenu'][''],
			static fn($item) => !in_array((string) ($item[2] ?? ''), $slugs, true)
		));
	}

	public function parent_file(string $parent): string {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ($screen && self::portal_parent_exists() && RFS_AI_SEO::is_plugin_screen((string) $screen->id)) {
			$GLOBALS['submenu_file'] = 'rfs-ai';
			self::drop_hidden_entries(array_keys(self::PAGES));
			return self::PORTAL_PARENT;
		}
		return $parent;
	}

	/**
	 * Header args for the shared ats-ai-portal header: name, active menu, sub-navigation tabs.
	 */
	public static function header_args(string $current): array {
		$tabs = [];
		foreach (self::PAGES as $slug => [$title, $label]) {
			$tabs[$slug] = ['label' => __($label, 'rfs-ai-seo'), 'url' => admin_url('admin.php?page=' . $slug)];
		}
		return ['name' => __('AI SEO', 'rfs-ai-seo'), 'active' => 'rfs-ai', 'tabs' => $tabs, 'tab' => $current];
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
			echo '<h1>' . esc_html__('AI SEO Dashboard', 'rfs-ai-seo') . '</h1>';
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
			$posted_model = sanitize_text_field(wp_unslash($_POST['rfs_ai_seo_openai_model']));
			if (in_array($posted_model, Settings::MODELS, true)) {
				update_option('rfs_ai_seo_openai_model', $posted_model);
			}
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
		// Only the content tab posts this checkbox's tab-mate (the tone select); guard
		// so saving another tab never clobbers it back to '0'.
		if (isset($_POST['rfs_ai_seo_content_tone'])) {
			update_option('rfs_ai_seo_include_features', empty($_POST['rfs_ai_seo_include_features']) ? '0' : '1');
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
				'code'    => 'forbidden',
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
