<?php
/**
 * Shared helpers for rfs-ai-seo test scripts.
 * Run each test with: wp eval-file wp-content/plugins/rfs-ai-seo/tests/<file>.php --skip-themes
 */
declare(strict_types=1);

if (!defined('WP_CLI') || !WP_CLI) {
	fwrite(STDERR, "Run with: wp eval-file tests/<file>.php --skip-themes\n");
	exit(1);
}

// Dummy gateway keys so Portal::has_key() is true and no sealed secret is touched.
// Config constants win over sealed secrets inside this process only.
if (!defined('ATS_PORTAL_KEY_CONTENT')) {
	define('ATS_PORTAL_KEY_CONTENT', 'rfs_testcontent_' . str_repeat('a', 40));
}
if (!defined('ATS_PORTAL_KEY_IMAGES')) {
	define('ATS_PORTAL_KEY_IMAGES', 'rfs_testimages0_' . str_repeat('b', 40));
}

// Start every test with a clean portal cache so a previous run's backoff cannot short-circuit.
foreach ([\RedFrogStudio\AtsAiPortal\Portal::CACHE, \RedFrogStudio\AtsAiPortal\Portal::BACKOFF, \RedFrogStudio\AtsAiPortal\Portal::ACCOUNT] as $t) {
	delete_transient($t);
}

function rfs_assert(bool $ok, string $what): void {
	if (!$ok) {
		\WP_CLI::error("FAIL: {$what}");
	}
	\WP_CLI::log("ok - {$what}");
}

function rfs_assert_same($expected, $actual, string $what): void {
	if ($expected !== $actual) {
		\WP_CLI::error("FAIL: {$what}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true));
	}
	\WP_CLI::log("ok - {$what}");
}

/**
 * Build a wp_remote_* style response array. Header names must be lowercase.
 */
function rfs_response(int $code, $body, array $headers = []): array {
	return [
		'headers' => $headers,
		'body' => is_string($body) ? $body : wp_json_encode($body),
		'response' => ['code' => $code, 'message' => 'OK'],
		'cookies' => [],
		'filename' => null,
	];
}

/**
 * Answer HTTP requests from a queue. The last entry repeats. An entry may be a
 * response array, a WP_Error, or a callable (url, args) => response.
 */
function rfs_mock_http(array $responses): void {
	$GLOBALS['rfs_http_calls'] = [];
	$GLOBALS['rfs_http_queue'] = array_values($responses);
	remove_all_filters('pre_http_request');
	add_filter('pre_http_request', function ($pre, $args, $url) {
		$GLOBALS['rfs_http_calls'][] = ['url' => $url, 'args' => $args];
		$queue = $GLOBALS['rfs_http_queue'];
		$next = count($queue) > 1 ? array_shift($GLOBALS['rfs_http_queue']) : $queue[0];
		return is_callable($next) ? $next($url, $args) : $next;
	}, 10, 3);
}

function rfs_last_call(): array {
	$calls = $GLOBALS['rfs_http_calls'] ?? [];
	return $calls ? $calls[count($calls) - 1] : [];
}

function rfs_log_count(): int {
	global $wpdb;
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rfs_ai_seo_log");
}

function rfs_done(string $name): void {
	remove_all_filters('pre_http_request');
	\WP_CLI::success("{$name} passed");
}
