<?php
/**
 * R15 regression: every wp_send_json_error() call in includes/*.php must carry a
 * 'code' key within the next 4 lines, so AJAX failures are machine-readable on the
 * front end. Mirrors the one-off checker used during the 2.0.0 port:
 *
 *   for f in includes/*.php; do
 *     for l in $(grep -n "wp_send_json_error" $f | cut -d: -f1); do
 *       sed -n "${l},$((l+4))p" $f | grep -q "'code'" || echo "MISSING $f:$l"
 *     done
 *   done
 */
require __DIR__ . '/lib.php';

$base = dirname(__DIR__);
$files = glob("{$base}/includes/*.php");
sort($files);
rfs_assert(count($files) > 0, 'includes/*.php files found to scan');

foreach ($files as $path) {
	$name = basename($path);
	$lines = file($path);
	$missing = [];

	foreach ($lines as $i => $line) {
		if (!str_contains($line, 'wp_send_json_error')) {
			continue;
		}
		$lineno = $i + 1;
		$window = array_slice($lines, $i, 5); // this line + next 4.
		$has_code = false;
		foreach ($window as $wline) {
			if (str_contains($wline, "'code'")) {
				$has_code = true;
				break;
			}
		}
		if (!$has_code) {
			$missing[] = $lineno;
		}
	}

	rfs_assert(empty($missing), "{$name}: every wp_send_json_error() has a 'code' within 4 lines" . (empty($missing) ? '' : ' (missing at line ' . implode(', ', $missing) . ')'));
}

rfs_done('ajax-codes');
