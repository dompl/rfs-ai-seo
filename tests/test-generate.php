<?php
require __DIR__ . '/lib.php';

use RFS_AI_SEO\Description_Generator;

$product = new WC_Product_Simple();
$product->set_name('Harness Test Product');
$product->set_description('');
$product->set_status('draft');
$pid = $product->save();
rfs_assert($pid > 0, 'test product created');

$gen = new Description_Generator();
$ok = rfs_response(200, [
    'choices' => [['message' => ['content' => '{"description":"<p>Generated</p>"}']]],
    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
], ['x-charged-gbp' => '0.0200', 'x-balance-gbp' => '5.00']);

// Happy path with options and charge.
$before = rfs_log_count();
rfs_mock_http([$ok]);
$r = $gen->generate_for_product($pid, ['tone' => 'friendly', 'words' => 120, 'keywords' => 'harness']);
rfs_assert(!is_wp_error($r), 'generate ok');
rfs_assert_same(0.02, $r['charged_gbp'], 'charge returned');
rfs_assert_same(5.0, $r['balance_gbp'], 'balance returned');
rfs_assert_same($before + 1, rfs_log_count(), 'one log row');
$sent = json_decode(rfs_last_call()['args']['body'], true);
$prompt = $sent['messages'][count($sent['messages']) - 1]['content'];
rfs_assert(str_contains($prompt, 'about 120 words'), 'option reached the prompt');
rfs_assert(str_contains($prompt, 'harness'), 'keywords reached the prompt');

// 402 mid-run: error with code, no log row.
$before = rfs_log_count();
rfs_mock_http([rfs_response(402, ['error' => ['code' => 'insufficient_balance', 'message' => 'no']])]);
$r = $gen->generate_for_product($pid);
rfs_assert_same('ats_portal_out_of_credit', $r->get_error_code(), '402 surfaces');
rfs_assert_same($before, rfs_log_count(), 'no log row on 402');

// Yoast focus keyword is the default keywords.
update_post_meta($pid, '_yoast_wpseo_focuskw', 'yoastkw');
rfs_mock_http([$ok]);
$gen->generate_for_product($pid);
$sent = json_decode(rfs_last_call()['args']['body'], true);
rfs_assert(str_contains($sent['messages'][count($sent['messages']) - 1]['content'], 'yoastkw'), 'Yoast focus keyword used by default');

// should_skip: empty -> false, has content -> true. Bound directly to the private method; the bulk AJAX handler itself is not exercised here.
$skip = \Closure::bind(fn(int $id, string $k) => $this->should_skip($id, $k), $gen, Description_Generator::class);
rfs_assert_same(false, $skip($pid, 'description'), 'no description -> not skipped');
$product = wc_get_product($pid);
$product->set_description('<p>Existing</p>');
$product->save();
rfs_assert_same(true, $skip($pid, 'description'), 'existing description -> skipped');

// resolve_options: words clamp to Settings::get_description_words() range (50-1000),
// sentences clamp to Settings::get_short_sentences() range (1-6). A zero value is
// falsy, so it falls back to the Settings default (2) rather than clamping up to 1.
$resolve = \Closure::bind(fn(array $o, string $k, int $id) => $this->resolve_options($o, $k, $id), $gen, Description_Generator::class);
rfs_assert_same(50, $resolve(['words' => 10], 'description', $pid)['words'], 'words 10 clamps up to 50');
rfs_assert_same(1000, $resolve(['words' => 5000], 'description', $pid)['words'], 'words 5000 clamps down to 1000');
rfs_assert_same(2, $resolve(['sentences' => 0], 'short', $pid)['sentences'], 'sentences 0 falls back to the Settings default (2)');
rfs_assert_same(6, $resolve(['sentences' => 9], 'short', $pid)['sentences'], 'sentences 9 clamps down to 6');

wp_delete_post($pid, true);
rfs_done('generate');
