<?php
/**
 * Uninstall RFS AI SEO
 *
 * @package RFS_AI_SEO
 */

// Exit if not called by WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

// Delete all plugin options.
$options = [
	'rfs_ai_seo_openai_api_key',
	'rfs_ai_seo_openai_model',
	'rfs_ai_seo_prompt_product',
	'rfs_ai_seo_prompt_variation',
	'rfs_ai_seo_prompt_category',
	'rfs_ai_seo_acf_fields',
	'rfs_ai_seo_title_max_length',
	'rfs_ai_seo_description_max_length',
	'rfs_ai_seo_db_version',
];

foreach ($options as $option) {
	delete_option($option);
}

// Drop custom table.
$table_name = $wpdb->prefix . 'rfs_ai_seo_log';
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table_name));

// Clear any transients.
delete_transient('rfs_ai_seo_connection_test');
