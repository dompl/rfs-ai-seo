<?php
require __DIR__ . '/lib.php';

$base = dirname(__DIR__);

rfs_assert(!class_exists('RFS_AI_SEO\Credits_Manager'), 'Credits_Manager class gone');
rfs_assert(!class_exists('RFS_AI_SEO\Stripe_Client'), 'Stripe_Client class gone');
foreach (['includes/class-credits-manager.php', 'includes/class-stripe-client.php', 'admin/views/credits-page.php', 'admin/js/credits.js'] as $f) {
	rfs_assert(!file_exists("{$base}/{$f}"), "{$f} deleted");
}

$admin = file_get_contents("{$base}/includes/class-admin.php");
rfs_assert(!str_contains($admin, 'rfs-ai-credits'), 'Buy Credits submenu removed');
rfs_assert(!str_contains($admin, 'stripe'), 'no Stripe code left in class-admin.php');

$settings = new RFS_AI_SEO\Settings();
rfs_assert_same('£12.35', $settings->format_gbp(12.345), 'format_gbp rounds to pence');
rfs_assert_same('£0.00', $settings->format_gbp(0.0), 'format_gbp zero');
foreach (['get_api_key', 'get_cost_multiplier', 'get_spending_cap', 'get_gemini_api_key', 'get_image_model', 'get_display_currency', 'format_cost'] as $m) {
	rfs_assert(!method_exists($settings, $m), "Settings::{$m} removed");
}

rfs_assert(!has_action('wp_ajax_rfs_ai_seo_create_checkout'), 'checkout AJAX unhooked');
rfs_assert(!has_action('wp_ajax_rfs_ai_seo_test_stripe'), 'stripe test AJAX unhooked');

rfs_done('removed');
