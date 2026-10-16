<?php
require __DIR__ . '/lib.php';

use RFS_AI_SEO\Settings;

$s = new Settings();
foreach (['rfs_ai_seo_image_quality', 'rfs_ai_seo_image_size', 'rfs_ai_seo_content_tone', 'rfs_ai_seo_description_words', 'rfs_ai_seo_short_sentences', 'rfs_ai_seo_include_features'] as $o) {
	delete_option($o);
}

rfs_assert_same('medium', $s->get_image_quality(), 'quality default');
rfs_assert_same('1024x1024', $s->get_image_size(), 'size default');
rfs_assert_same('professional', $s->get_content_tone(), 'tone default');
rfs_assert_same(150, $s->get_description_words(), 'words default');
rfs_assert_same(2, $s->get_short_sentences(), 'sentences default');
rfs_assert_same(true, $s->get_include_features(), 'features default on');

update_option('rfs_ai_seo_image_quality', 'ultra');
rfs_assert_same('medium', $s->get_image_quality(), 'unknown quality falls back');
update_option('rfs_ai_seo_image_quality', 'high');
rfs_assert_same('high', $s->get_image_quality(), 'valid quality read');
update_option('rfs_ai_seo_image_size', '1536x1024');
rfs_assert_same('1536x1024', $s->get_image_size(), 'landscape size read');
update_option('rfs_ai_seo_content_tone', 'friendly');
rfs_assert_same('friendly', $s->get_content_tone(), 'tone read');
update_option('rfs_ai_seo_description_words', '5000');
rfs_assert_same(1000, $s->get_description_words(), 'words clamped high');
update_option('rfs_ai_seo_description_words', '10');
rfs_assert_same(50, $s->get_description_words(), 'words clamped low');
update_option('rfs_ai_seo_description_words', '0');
rfs_assert_same(50, $s->get_description_words(), 'words clamped at zero');
update_option('rfs_ai_seo_short_sentences', '9');
rfs_assert_same(6, $s->get_short_sentences(), 'sentences clamped');
update_option('rfs_ai_seo_short_sentences', '0');
rfs_assert_same(1, $s->get_short_sentences(), 'sentences clamped at zero');
update_option('rfs_ai_seo_include_features', '0');
rfs_assert_same(false, $s->get_include_features(), 'features off');
rfs_assert_same(['professional', 'friendly', 'technical'], array_keys(Settings::tones()), 'tones list');

foreach (['rfs_ai_seo_image_quality', 'rfs_ai_seo_image_size', 'rfs_ai_seo_content_tone', 'rfs_ai_seo_description_words', 'rfs_ai_seo_short_sentences', 'rfs_ai_seo_include_features'] as $o) {
	delete_option($o);
}

// R10 regression: saving a tab that has no content-tab fields must not clobber the
// "include features" checkbox back to '0'. save_settings() is private; reach it through
// the public render_settings_page() handler, same as the real admin-post flow.
update_option('rfs_ai_seo_include_features', '1');
$prior_user = get_current_user_id();
wp_set_current_user(1);
if (current_user_can('manage_options')) {
	$_GET['tab'] = 'api';
	$_POST = [
		'rfs_ai_seo_settings_nonce' => wp_create_nonce('rfs_ai_seo_settings'),
		'rfs_ai_seo_openai_model' => 'gpt-4o',
	];
	$admin = new \RFS_AI_SEO\Admin();
	ob_start();
	$admin->render_settings_page();
	ob_get_clean();
	rfs_assert_same('1', get_option('rfs_ai_seo_include_features'), 'checkbox untouched by api-tab save');
	$_POST = [];
	unset($_GET['tab']);
} else {
	\WP_CLI::warning('skipped checkbox-guard regression: user 1 lacks manage_options in this environment');
}
wp_set_current_user($prior_user);
delete_option('rfs_ai_seo_include_features');

rfs_done('settings');
