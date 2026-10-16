<?php
require __DIR__ . '/lib.php';

use RFS_AI_SEO\Usage_Logger;

global $wpdb;
$table = $wpdb->prefix . 'rfs_ai_seo_log';
$logger = new Usage_Logger();

// Clean slate for the test object type.
$wpdb->delete($table, ['object_type' => 'test_type']);

$id = $logger->log(999001, 'test_type', [
	'tokens_prompt' => 10, 'tokens_completion' => 5, 'tokens_total' => 15,
	'model' => 'gpt-4o', 'charged_gbp' => 0.0125,
]);
rfs_assert(is_int($id) && $id > 0, 'log() returns an insert id');
rfs_assert_same('0.0125', $wpdb->get_var($wpdb->prepare("SELECT charged_gbp FROM {$table} WHERE id = %d", $id)), 'charged_gbp stored');

$logger->log(999002, 'test_type', ['model' => 'gpt-4o']);
rfs_assert_same('0.0000', $wpdb->get_var("SELECT charged_gbp FROM {$table} WHERE object_id = 999002"), 'charged_gbp defaults to 0');

$logger->log(999003, 'test_type', ['model' => 'gpt-4o', 'charged_gbp' => 0.0375]);

// average_charge ignores zero rows and limits to the last N.
rfs_assert_same(0.025, round((float) $logger->average_charge('test_type'), 4), 'average over charged rows only');
rfs_assert_same(0.0375, round((float) $logger->average_charge('test_type', 1), 4), 'last-N limit applies');
rfs_assert_same(null, $logger->average_charge('no_such_type'), 'null when nothing charged');

// Stats sum the real charge.
$stats = $logger->get_stats();
rfs_assert(array_key_exists('charged_gbp', $stats), 'stats expose charged_gbp');
rfs_assert(!array_key_exists('estimated_cost', $stats), 'estimated_cost is gone');

// The old helpers are gone.
rfs_assert(!method_exists($logger, 'check_spending_cap'), 'check_spending_cap removed');
rfs_assert(!method_exists($logger, 'calculate_cost'), 'calculate_cost removed');

$wpdb->delete($table, ['object_type' => 'test_type']);
rfs_done('logger');
