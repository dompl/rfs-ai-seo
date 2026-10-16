<?php
require __DIR__ . '/lib.php';

use RFS_AI_SEO\OpenAI_Client;

$ok = rfs_response(200, [
	'choices' => [['message' => ['content' => '{"description":"<p>Hello</p>"}']]],
	'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
], ['x-charged-gbp' => '0.0123', 'x-balance-gbp' => '41.50']);

// 1. Happy path: gateway URL, headers, charge.
rfs_mock_http([$ok]);
$client = new OpenAI_Client('gpt-4o', 'ats:content:description', '123');
$result = $client->generate_description('Write something');
rfs_assert(!is_wp_error($result), 'generate_description succeeds');
$call = rfs_last_call();
rfs_assert(str_ends_with($call['url'], '/openai/v1/chat/completions'), 'posts to the gateway chat completions URL');
rfs_assert(str_contains($call['url'], 'api-portal.rfsdev.co.uk'), 'URL is the portal host');
rfs_assert_same('Bearer ' . ATS_PORTAL_KEY_CONTENT, $call['args']['headers']['Authorization'], 'content key in Authorization');
rfs_assert_same('ats:content:description', $call['args']['headers']['X-Purpose'], 'purpose header');
rfs_assert_same('123', $call['args']['headers']['X-Reference'], 'reference header');
rfs_assert_same('application/json', $call['args']['headers']['Content-Type'], 'JSON content type');
rfs_assert_same(0.0123, $result['charged_gbp'], 'charge comes from X-Charged-GBP');
rfs_assert_same(0.0123, $client->last_charged(), 'last_charged() remembers it');
rfs_assert_same('<p>Hello</p>', $result['description'], 'description parsed');

// 2. 402 maps to the portal out-of-credit error.
rfs_mock_http([rfs_response(402, ['error' => ['code' => 'insufficient_balance', 'message' => 'Out of credit']])]);
$result = (new OpenAI_Client('gpt-4o', 'ats:content:meta', '1'))->generate_description('x');
rfs_assert(is_wp_error($result), '402 is an error');
rfs_assert_same('ats_portal_out_of_credit', $result->get_error_code(), '402 code');
rfs_assert(str_contains($result->get_error_message(), 'ats-ai-credits'), '402 message links to AI Credits');

// 3. Unreachable twice: ats_portal_unavailable, two attempts, nothing logged.
$before = rfs_log_count();
rfs_mock_http([new WP_Error('http_request_failed', 'cURL error 28')]);
$result = (new OpenAI_Client())->generate_description('x');
rfs_assert_same('ats_portal_unavailable', $result->get_error_code(), 'network failure code');
rfs_assert_same('AI service unavailable, try again.', $result->get_error_message(), 'network failure copy');
rfs_assert_same(2, count($GLOBALS['rfs_http_calls']), 'retried once');
rfs_assert_same($before, rfs_log_count(), 'nothing logged on failure');

// 4. 503 from the gateway: same unavailable code after one retry.
rfs_mock_http([rfs_response(503, 'upstream down')]);
$result = (new OpenAI_Client())->generate_description('x');
rfs_assert_same('ats_portal_unavailable', $result->get_error_code(), '5xx maps to unavailable');
rfs_assert_same(2, count($GLOBALS['rfs_http_calls']), '5xx retried once');

// 5. 200 with a body that is not JSON.
rfs_mock_http([rfs_response(200, 'not json at all')]);
$result = (new OpenAI_Client())->generate_description('x');
rfs_assert_same('invalid_response', $result->get_error_code(), 'non-JSON 200 body');

// 6. No key: ats_portal_no_key without any HTTP call.
add_filter('pre_option_rfs_ai_seo_openai_model', fn() => 'gpt-4o');
rfs_mock_http([$ok]);
add_filter('ats_ai_portal_connected', '__return_false'); // only affects CreditsBar; the client checks has_key directly
remove_filter('ats_ai_portal_connected', '__return_false');

// 7. test_connection() uses the free /models endpoint.
rfs_mock_http([rfs_response(200, ['data' => [['id' => 'gpt-4o']]])]);
rfs_assert_same(true, (new OpenAI_Client())->test_connection(), 'test_connection true on 200');
$call = rfs_last_call();
rfs_assert(str_ends_with($call['url'], '/openai/v1/models'), 'test_connection hits /models');
rfs_assert_same('GET', $call['args']['method'] ?? 'GET', 'test_connection is a GET');

rfs_done('openai-client');
