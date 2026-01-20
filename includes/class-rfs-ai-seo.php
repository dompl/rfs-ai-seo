<?php
/**
 * Main Plugin Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Main RFS_AI_SEO Class
 *
 * @class RFS_AI_SEO
 */
final class RFS_AI_SEO {

	/**
	 * Plugin instance.
	 *
	 * @var RFS_AI_SEO|null
	 */
	private static ?RFS_AI_SEO $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	public ?Settings $settings = null;

	/**
	 * Admin instance.
	 *
	 * @var Admin|null
	 */
	public ?Admin $admin = null;

	/**
	 * Get plugin instance.
	 *
	 * @return RFS_AI_SEO
	 */
	public static function instance(): RFS_AI_SEO {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// Check dependencies first.
		if (!$this->check_dependencies()) {
			return;
		}

		// Load plugin textdomain.
		add_action('init', [$this, 'load_textdomain']);

		// Load plugin classes.
		$this->load_classes();

		// Initialize components.
		$this->settings = new Settings();
		$this->admin = new Admin();
		new Meta_Box(); // Initialize meta boxes.

		// Enqueue admin assets.
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

		// Add admin notices hook.
		add_action('admin_notices', [$this, 'display_dependency_warnings']);
	}

	/**
	 * Load required classes.
	 *
	 * @return void
	 */
	private function load_classes(): void {
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-settings.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-admin.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-openai-client.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-yoast-integration.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-content-collector.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-prompt-manager.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-generator.php';
		require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-meta-box.php';
	}

	/**
	 * Check if all required dependencies are active.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		$dependencies_met = true;

		// Check if WooCommerce is active.
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', function () {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: plugin name */
								__('<strong>%s</strong> requires WooCommerce to be installed and activated.', 'rfs-ai-seo'),
								'RFS AI SEO'
							)
						);
						?>
					</p>
				</div>
				<?php
			});
			$dependencies_met = false;
		}

		// Check if Yoast SEO is active.
		if (!defined('WPSEO_VERSION')) {
			add_action('admin_notices', function () {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: plugin name */
								__('<strong>%s</strong> requires Yoast SEO to be installed and activated.', 'rfs-ai-seo'),
								'RFS AI SEO'
							)
						);
						?>
					</p>
				</div>
				<?php
			});
			$dependencies_met = false;
		}

		return $dependencies_met;
	}

	/**
	 * Display dependency warnings.
	 *
	 * @return void
	 */
	public function display_dependency_warnings(): void {
		// This method is kept for potential future warnings.
		// Dependency checks are now handled in check_dependencies().
	}

	/**
	 * Load plugin textdomain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'rfs-ai-seo',
			false,
			dirname(RFS_AI_SEO_PLUGIN_BASENAME) . '/languages'
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets(string $hook): void {
		// Only load on our plugin pages and product/category edit screens.
		$allowed_hooks = [
			'toplevel_page_rfs-ai-seo',
			'rfs-ai-seo_page_rfs-ai-seo-bulk',
			'post.php',
			'post-new.php',
			'term.php',
			'edit-tags.php',
		];

		if (!in_array($hook, $allowed_hooks, true)) {
			return;
		}

		// Enqueue admin CSS.
		wp_enqueue_style(
			'rfs-ai-seo-admin',
			RFS_AI_SEO_PLUGIN_URL . 'admin/css/admin.css',
			[],
			RFS_AI_SEO_VERSION
		);

		// Enqueue admin JS.
		wp_enqueue_script(
			'rfs-ai-seo-admin',
			RFS_AI_SEO_PLUGIN_URL . 'admin/js/admin.js',
			['jquery'],
			RFS_AI_SEO_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce.
		wp_localize_script(
			'rfs-ai-seo-admin',
			'rfsAiSeo',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('rfs_ai_seo_nonce'),
				'strings' => [
					'generating' => __('Generating...', 'rfs-ai-seo'),
					'error' => __('An error occurred. Please try again.', 'rfs-ai-seo'),
					'confirmOverwrite' => __('This item already has meta data. Do you want to overwrite it?', 'rfs-ai-seo'),
				],
			]
		);
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Check PHP version.
		if (version_compare(PHP_VERSION, '8.0', '<')) {
			deactivate_plugins(RFS_AI_SEO_PLUGIN_BASENAME);
			wp_die(
				wp_kses_post(
					__('RFS AI SEO requires PHP 8.0 or higher. Please upgrade your PHP version.', 'rfs-ai-seo')
				)
			);
		}

		// Check WordPress version.
		if (version_compare(get_bloginfo('version'), '6.0', '<')) {
			deactivate_plugins(RFS_AI_SEO_PLUGIN_BASENAME);
			wp_die(
				wp_kses_post(
					__('RFS AI SEO requires WordPress 6.0 or higher. Please upgrade WordPress.', 'rfs-ai-seo')
				)
			);
		}

		// Check WooCommerce.
		if (!class_exists('WooCommerce')) {
			deactivate_plugins(RFS_AI_SEO_PLUGIN_BASENAME);
			wp_die(
				wp_kses_post(
					__('RFS AI SEO requires WooCommerce 8.0 or higher to be installed and activated.', 'rfs-ai-seo')
				)
			);
		}

		// Check Yoast SEO.
		if (!defined('WPSEO_VERSION')) {
			deactivate_plugins(RFS_AI_SEO_PLUGIN_BASENAME);
			wp_die(
				wp_kses_post(
					__('RFS AI SEO requires Yoast SEO to be installed and activated.', 'rfs-ai-seo')
				)
			);
		}

		// Create database tables.
		self::create_tables();

		// Set default options.
		self::set_default_options();

		// Set DB version.
		update_option('rfs_ai_seo_db_version', RFS_AI_SEO_VERSION);
	}

	/**
	 * Create plugin database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL,
			tokens_prompt int(11) NOT NULL DEFAULT 0,
			tokens_completion int(11) NOT NULL DEFAULT 0,
			tokens_total int(11) NOT NULL DEFAULT 0,
			model varchar(50) NOT NULL,
			generated_title varchar(255) DEFAULT NULL,
			generated_description text DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY object_id (object_id),
			KEY object_type (object_type),
			KEY created_at (created_at),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = [
			'rfs_ai_seo_openai_model' => 'gpt-4o',
			'rfs_ai_seo_title_max_length' => 60,
			'rfs_ai_seo_description_max_length' => 155,
			'rfs_ai_seo_acf_fields' => [],
		];

		foreach ($defaults as $key => $value) {
			if (get_option($key) === false) {
				update_option($key, $value);
			}
		}

		// Set default prompts from template files.
		self::set_default_prompts();
	}

	/**
	 * Set default prompt templates.
	 *
	 * @return void
	 */
	private static function set_default_prompts(): void {
		$prompts = [
			'product' => self::get_default_product_prompt(),
			'variation' => self::get_default_variation_prompt(),
			'category' => self::get_default_category_prompt(),
		];

		foreach ($prompts as $type => $prompt) {
			$option_key = "rfs_ai_seo_prompt_{$type}";
			if (get_option($option_key) === false) {
				update_option($option_key, $prompt);
			}
		}
	}

	/**
	 * Get default product prompt template.
	 *
	 * @return string
	 */
	private static function get_default_product_prompt(): string {
		return 'Generate SEO metadata for this WooCommerce product.

PRODUCT DATA:
- Name: {product_name}
- Categories: {category_hierarchy}
- Short description: {short_description}
- Full description: {product_description}
- Price: {price}
- Attributes: {attributes}
- Additional info: {acf_all}

REQUIREMENTS:
- Meta title: Maximum {max_title_length} characters. Include product name and key selling point. Make it compelling for clicks.
- Meta description: Maximum {max_description_length} characters. Summarize key benefits, include a subtle call-to-action. Do not use generic phrases like "Buy now" or "Shop today".

RESPOND IN JSON FORMAT ONLY:
{"title": "your meta title here", "description": "your meta description here"}';
	}

	/**
	 * Get default variation prompt template.
	 *
	 * @return string
	 */
	private static function get_default_variation_prompt(): string {
		return 'Generate SEO metadata for this product variation.

PARENT PRODUCT: {product_name}
VARIATION DETAILS:
- Attributes: {variation_attributes}
- Description: {variation_description}
- Price: {variation_price}
- SKU: {variation_sku}

PARENT CONTEXT:
- Categories: {category_hierarchy}
- Main description: {short_description}

REQUIREMENTS:
- Meta title: Maximum {max_title_length} characters. Include parent product name and variation-specific attributes (like color, size).
- Meta description: Maximum {max_description_length} characters. Focus on this specific variation\'s unique aspects while maintaining parent product context.

RESPOND IN JSON FORMAT ONLY:
{"title": "your meta title here", "description": "your meta description here"}';
	}

	/**
	 * Get default category prompt template.
	 *
	 * @return string
	 */
	private static function get_default_category_prompt(): string {
		return 'Generate SEO metadata for this product category.

CATEGORY DATA:
- Name: {category_name}
- Description: {category_description}
- Parent category: {parent_category}
- Number of products: {product_count}
- Additional info: {acf_all}

REQUIREMENTS:
- Meta title: Maximum {max_title_length} characters. Include category name, make it search-friendly and descriptive.
- Meta description: Maximum {max_description_length} characters. Describe what types of products are in this category, encourage browsing. Avoid generic phrases.

RESPOND IN JSON FORMAT ONLY:
{"title": "your meta title here", "description": "your meta description here"}';
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clean up transients.
		delete_transient('rfs_ai_seo_connection_test');

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserializing.
	 *
	 * @return void
	 */
	public function __wakeup() {
	}
}
