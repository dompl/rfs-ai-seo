<?php
/**
 * Plugin Name: RFS AI SEO
 * Plugin URI: https://github.com/redfrogstudio/rfs-ai-seo
 * Description: AI-powered SEO meta generation for WooCommerce products and categories using OpenAI, with Yoast SEO integration.
 * Version: 1.0.0
 * Author: Red Frog Studio
 * Author URI: https://redfrogstudio.com
 * Text Domain: rfs-ai-seo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants.
define('RFS_AI_SEO_VERSION', '2.0.0');
define('RFS_AI_SEO_PLUGIN_FILE', __FILE__);
define('RFS_AI_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RFS_AI_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RFS_AI_SEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require main plugin class.
require_once RFS_AI_SEO_PLUGIN_DIR . 'includes/class-rfs-ai-seo.php';

/**
 * Initialize the plugin.
 *
 * @return RFS_AI_SEO\RFS_AI_SEO
 */
function rfs_ai_seo() {
	return RFS_AI_SEO\RFS_AI_SEO::instance();
}

// Initialize plugin.
add_action('plugins_loaded', 'rfs_ai_seo');

/**
 * Activation hook.
 */
register_activation_hook(__FILE__, ['RFS_AI_SEO\RFS_AI_SEO', 'activate']);

/**
 * Deactivation hook.
 */
register_deactivation_hook(__FILE__, ['RFS_AI_SEO\RFS_AI_SEO', 'deactivate']);
