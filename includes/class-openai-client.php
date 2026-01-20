<?php
/**
 * OpenAI Client Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * OpenAI Client Class
 *
 * Handles all OpenAI API communication.
 *
 * @class OpenAI_Client
 */
class OpenAI_Client {

	/**
	 * OpenAI API endpoint.
	 */
	private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Request timeout in seconds.
	 */
	private const TIMEOUT = 30;

	/**
	 * Maximum number of retries.
	 */
	private const MAX_RETRIES = 1;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Constructor.
	 *
	 * @param string $api_key OpenAI API key.
	 * @param string $model Model name (default: gpt-4o).
	 */
	public function __construct(string $api_key, string $model = 'gpt-4o') {
		$this->api_key = $api_key;
		$this->model = $model;
	}

	/**
	 * Generate meta title and description.
	 *
	 * @param string $prompt The prompt to send to OpenAI.
	 * @return array|\WP_Error Array with title, description, and token usage, or WP_Error on failure.
	 */
	public function generate_meta(string $prompt): array|\WP_Error {
		if (empty($this->api_key)) {
			return new \WP_Error(
				'no_api_key',
				__('OpenAI API key is not configured. Please add your API key in the settings.', 'rfs-ai-seo')
			);
		}

		if (empty($prompt)) {
			return new \WP_Error(
				'empty_prompt',
				__('Prompt cannot be empty.', 'rfs-ai-seo')
			);
		}

		// Build the request payload.
		$payload = $this->build_payload($prompt);

		// Make the API request.
		$response = $this->make_request($payload);

		if (is_wp_error($response)) {
			return $response;
		}

		// Parse the response.
		$parsed = $this->parse_response($response);

		if (is_wp_error($parsed)) {
			return $parsed;
		}

		return $parsed;
	}

	/**
	 * Test the OpenAI API connection.
	 *
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection(): bool|\WP_Error {
		if (empty($this->api_key)) {
			return new \WP_Error(
				'no_api_key',
				__('API key is required to test the connection.', 'rfs-ai-seo')
			);
		}

		// Simple test prompt.
		$test_prompt = 'Respond with valid JSON: {"status": "ok"}';
		$payload = $this->build_payload($test_prompt);

		// Make the request.
		$response = $this->make_request($payload);

		if (is_wp_error($response)) {
			return $response;
		}

		// If we got here, connection is successful.
		return true;
	}

	/**
	 * Build the API request payload.
	 *
	 * @param string $prompt User prompt.
	 * @return array Request payload.
	 */
	private function build_payload(string $prompt): array {
		return [
			'model' => $this->model,
			'messages' => [
				[
					'role' => 'system',
					'content' => 'You are an SEO specialist. Respond only in valid JSON format with "title" and "description" keys.',
				],
				[
					'role' => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => 0.7,
			'max_tokens' => 500,
		];
	}

	/**
	 * Make an API request to OpenAI.
	 *
	 * @param array $payload Request payload.
	 * @param int   $retry_count Current retry attempt.
	 * @return array|\WP_Error Response data or WP_Error.
	 */
	private function make_request(array $payload, int $retry_count = 0): array|\WP_Error {
		// Log the request (without the full API key).
		error_log('RFS AI SEO: Making OpenAI API request - Model: ' . $this->model);
		error_log('RFS AI SEO: API Key prefix: ' . substr($this->api_key, 0, 10) . '...');

		$response = wp_remote_post(
			self::API_ENDPOINT,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode($payload),
				'timeout' => self::TIMEOUT,
			]
		);

		// Check for network errors.
		if (is_wp_error($response)) {
			error_log('RFS AI SEO: Network error: ' . $response->get_error_message());

			// Retry once on network error.
			if ($retry_count < self::MAX_RETRIES) {
				return $this->make_request($payload, $retry_count + 1);
			}

			return new \WP_Error(
				'network_error',
				sprintf(
					/* translators: %s: error message */
					__('Network error: %s', 'rfs-ai-seo'),
					$response->get_error_message()
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		// Log the response.
		error_log('RFS AI SEO: API Response Code: ' . $response_code);
		error_log('RFS AI SEO: API Response Body (first 200 chars): ' . substr($response_body, 0, 200));

		// Decode the response.
		$data = json_decode($response_body, true);

		// Handle HTTP errors.
		if ($response_code !== 200) {
			return $this->handle_api_error($response_code, $data);
		}

		// Check if we have a valid response structure.
		if (!isset($data['choices'][0]['message']['content'])) {
			error_log('RFS AI SEO: Invalid response structure - missing choices/message/content');
			return new \WP_Error(
				'invalid_response',
				__('Invalid response structure from OpenAI API.', 'rfs-ai-seo')
			);
		}

		return $data;
	}

	/**
	 * Handle API errors.
	 *
	 * @param int   $response_code HTTP response code.
	 * @param array $data Response data.
	 * @return \WP_Error Error object.
	 */
	private function handle_api_error(int $response_code, ?array $data): \WP_Error {
		$error_message = $data['error']['message'] ?? __('Unknown error occurred.', 'rfs-ai-seo');

		switch ($response_code) {
			case 401:
				return new \WP_Error(
					'invalid_api_key',
					__('Invalid API key. Please check your OpenAI API key in the settings.', 'rfs-ai-seo')
				);

			case 429:
				return new \WP_Error(
					'rate_limit',
					__('Rate limit exceeded. Please wait a moment and try again.', 'rfs-ai-seo')
				);

			case 500:
			case 502:
			case 503:
				return new \WP_Error(
					'server_error',
					__('OpenAI server error. Please try again later.', 'rfs-ai-seo')
				);

			default:
				return new \WP_Error(
					'api_error',
					sprintf(
						/* translators: 1: HTTP code, 2: error message */
						__('API error (%1$d): %2$s', 'rfs-ai-seo'),
						$response_code,
						$error_message
					)
				);
		}
	}

	/**
	 * Parse the OpenAI API response.
	 *
	 * @param array $response API response.
	 * @return array|\WP_Error Parsed data or WP_Error.
	 */
	private function parse_response(array $response): array|\WP_Error {
		// Extract the content.
		$content = $response['choices'][0]['message']['content'] ?? '';

		if (empty($content)) {
			return new \WP_Error(
				'empty_response',
				__('OpenAI returned an empty response.', 'rfs-ai-seo')
			);
		}

		// Parse JSON from content.
		$parsed = json_decode($content, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			// Log the raw response for debugging.
			error_log('RFS AI SEO: Invalid JSON response: ' . $content);

			return new \WP_Error(
				'invalid_json',
				__('OpenAI did not return valid JSON. Please try again.', 'rfs-ai-seo')
			);
		}

		// Validate required fields.
		if (!isset($parsed['title']) || !isset($parsed['description'])) {
			error_log('RFS AI SEO: Missing title or description in response: ' . $content);

			return new \WP_Error(
				'missing_fields',
				__('OpenAI response is missing required fields (title or description).', 'rfs-ai-seo')
			);
		}

		// Extract token usage.
		$tokens = [
			'prompt' => $response['usage']['prompt_tokens'] ?? 0,
			'completion' => $response['usage']['completion_tokens'] ?? 0,
			'total' => $response['usage']['total_tokens'] ?? 0,
		];

		return [
			'title' => trim($parsed['title']),
			'description' => trim($parsed['description']),
			'tokens' => $tokens,
		];
	}
}
