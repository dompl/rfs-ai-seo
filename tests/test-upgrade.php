<?php
// Note: no declare(strict_types=1) here — wp eval-file runs this file's
// contents through eval(), and PHP forbids that declaration inside eval().
// lib.php (required normally, not eval()'d) keeps the declaration.
require __DIR__ . '/lib.php';

use RFS_AI_SEO\RFS_AI_SEO;

global $wpdb;
$table = $wpdb->prefix . 'rfs_ai_seo_log';

// Seed the old world: old version, a handful of removed options, no charged_gbp column.
update_option('rfs_ai_seo_version', '1.0.0');
update_option('rfs_ai_seo_credits_balance', '12.34');
update_option('rfs_ai_seo_gemini_api_key', 'x');
update_option('rfs_ai_seo_stripe_secret_key', 'x');
update_option('rfs_ai_seo_image_model', 'gemini-2.5-flash-image');
update_option('rfs_ai_seo_cost_multiplier', '3');
update_option('rfs_ai_seo_display_currency', 'USD');
$wpdb->query("ALTER TABLE {$table} DROP COLUMN charged_gbp"); // ignore error if absent

RFS_AI_SEO::instance()->maybe_upgrade();

rfs_assert_same(RFS_AI_SEO_VERSION, get_option(RFS_AI_SEO::VERSION_OPTION), 'version option updated');
rfs_assert_same('2.0.0', RFS_AI_SEO_VERSION, 'plugin version is 2.0.0');
foreach (['rfs_ai_seo_credits_balance', 'rfs_ai_seo_gemini_api_key', 'rfs_ai_seo_stripe_secret_key', 'rfs_ai_seo_image_model', 'rfs_ai_seo_cost_multiplier', 'rfs_ai_seo_display_currency'] as $name) {
	rfs_assert_same(false, get_option($name), "removed option {$name} is gone");
}
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'charged_gbp'");
rfs_assert_same(1, count($cols), 'charged_gbp column added once');

// Idempotent: run again, nothing breaks.
$wpdb->suppress_errors(false);
RFS_AI_SEO::instance()->maybe_upgrade();
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'charged_gbp'");
rfs_assert_same(1, count($cols), 'second run leaves exactly one charged_gbp column');
rfs_assert_same('', (string) $wpdb->last_error, 'no SQL error on the second run');

rfs_done('upgrade');
