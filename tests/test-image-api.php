<?php
require __DIR__ . '/lib.php';

use RFS_AI_SEO\Image_Generator;
use RFS_AI_SEO\Settings;

$gen = new Image_Generator(new Settings());
$call = \Closure::bind(function (string $prompt, string $size, ?array $source, string $ref = '') {
	return $this->call_image_api($prompt, $size, $source, $ref);
}, $gen, Image_Generator::class);
$size_for = \Closure::bind(fn(string $r) => $this->size_for($r), $gen, Image_Generator::class);

$png = ['data' => base64_encode('fakepng'), 'mime_type' => 'image/png'];
$ok = rfs_response(200, ['data' => [['b64_json' => base64_encode('outputpng')]]], ['x-charged-gbp' => '0.0400', 'x-balance-gbp' => '9.96']);

// 1. With a source photo: multipart edit.
rfs_mock_http([$ok]);
$r = $call('Clean studio shot', '1024x1024', $png, '555');
rfs_assert(!is_wp_error($r), 'edit succeeds');
$c = rfs_last_call();
rfs_assert(str_ends_with($c['url'], '/openai/v1/images/edits'), 'edits endpoint with a source');
rfs_assert(str_starts_with($c['args']['headers']['Content-Type'], 'multipart/form-data; boundary='), 'multipart content type');
rfs_assert_same('Bearer ' . ATS_PORTAL_KEY_IMAGES, $c['args']['headers']['Authorization'], 'images key used');
rfs_assert_same('ats:image:product', $c['args']['headers']['X-Purpose'], 'image purpose');
rfs_assert_same('555', $c['args']['headers']['X-Reference'], 'reference');
rfs_assert(str_contains($c['args']['body'], 'name="image[]"; filename="source.png"'), 'image[] part present');
rfs_assert(str_contains($c['args']['body'], "name=\"model\"\r\n\r\ngpt-image-1"), 'model field present');
rfs_assert(str_contains($c['args']['body'], "name=\"size\"\r\n\r\n1024x1024"), 'size field present');
rfs_assert(str_contains($c['args']['body'], "name=\"quality\"\r\n\r\n"), 'quality field present');
rfs_assert(str_contains($c['args']['body'], 'fakepng'), 'decoded source bytes in body');
rfs_assert_same(base64_encode('outputpng'), $r['image_data'], 'b64 passed through');
rfs_assert_same('image/png', $r['mime_type'], 'png mime');
rfs_assert_same(0.04, $r['charged_gbp'], 'charge read');
rfs_assert_same(Image_Generator::IMAGE_TIMEOUT, $c['args']['timeout'], 'long timeout');

// 2. No source: JSON generation.
rfs_mock_http([$ok]);
$r = $call('A diamond blade on white', '1536x1024', null);
$c = rfs_last_call();
rfs_assert(str_ends_with($c['url'], '/openai/v1/images/generations'), 'generations endpoint without a source');
rfs_assert_same('application/json', $c['args']['headers']['Content-Type'], 'JSON for generations');
$body = json_decode($c['args']['body'], true);
rfs_assert_same('gpt-image-1', $body['model'], 'model in JSON');
rfs_assert_same('1536x1024', $body['size'], 'size in JSON');

// 3. Errors.
rfs_mock_http([rfs_response(402, ['error' => ['code' => 'insufficient_balance', 'message' => 'no']])]);
$r = $call('x', '1024x1024', null);
rfs_assert_same('ats_portal_out_of_credit', $r->get_error_code(), '402 maps');

rfs_mock_http([rfs_response(200, ['data' => []])]);
$r = $call('x', '1024x1024', null);
rfs_assert_same('no_image', $r->get_error_code(), 'empty data → no_image');

rfs_mock_http([new WP_Error('http_request_failed', 'timeout')]);
$r = $call('x', '1024x1024', null);
rfs_assert_same('ats_portal_unavailable', $r->get_error_code(), 'network → unavailable');

// 4. Aspect ratio mapping.
rfs_assert_same('1024x1024', $size_for('1:1'), 'square');
rfs_assert_same('1536x1024', $size_for('16:9'), 'landscape');
rfs_assert_same('1024x1536', $size_for('9:16'), 'portrait');
rfs_assert_same((new Settings())->get_image_size(), $size_for('weird'), 'unknown → settings size');

rfs_done('image-api');
