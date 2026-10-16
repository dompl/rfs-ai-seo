<?php
/**
 * OpenAI Client Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

use RedFrogStudio\AtsAiPortal\Portal;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * OpenAI Client Class
 *
 * Talks to OpenAI through the Red Frog API portal gateway.
 *
 * @class OpenAI_Client
 */
class OpenAI_Client {

	/**
	 * Request timeout in seconds.
	 */
	private const TIMEOUT = 30;

	/**
	 * Maximum number of retries.
	 */
	private const MAX_RETRIES = 1;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private string $model;

	/** Gateway purpose header, e.g. ats:content:description. */
	private string $purpose;

	/** Gateway reference header (product/post/attachment id). */
	private string $reference;

	/** Charge in pounds reported by the gateway for the last successful request. */
	private ?float $last_charged = null;

	/**
	 * Constructor.
	 *
	 * @param string $model     OpenAI text model (gpt-4o or gpt-4o-mini).
	 * @param string $purpose   One of the ats:content:* purposes.
	 * @param string $reference Product/post/attachment id for the portal activity log.
	 */
	public function __construct(string $model = 'gpt-4o', string $purpose = 'ats:content:description', string $reference = '') {
		$this->model = $model;
		$this->purpose = $purpose;
		$this->reference = $reference;
	}

	/** Pounds charged by the gateway for the last successful request, null before any. */
	public function last_charged(): ?float {
		return $this->last_charged;
	}

	/**
	 * Generate meta title and description.
	 *
	 * @param string $prompt The prompt to send to OpenAI.
	 * @return array|\WP_Error Array with title, description, and token usage, or WP_Error on failure.
	 */
	public function generate_meta(string $prompt): array|\WP_Error {
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
	 * Check the gateway accepts this site's content key. Free: /models is not charged.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection(): true|\WP_Error {
		if (!Portal::has_key('content')) {
			return new \WP_Error('ats_portal_no_key', __('AI is not connected. Run: wp ats secret set gateway_key_content', 'rfs-ai-seo'));
		}
		$response = wp_remote_get(
			Portal::openai_url('/models'),
			['headers' => Portal::headers('content', 'ats:content:meta'), 'timeout' => 15]
		);
		if (is_wp_error($response)) {
			return new \WP_Error('ats_portal_unavailable', __('AI service unavailable, try again.', 'rfs-ai-seo'));
		}
		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code !== 200) {
			return Portal::error($code, (string) wp_remote_retrieve_body($response));
		}
		return true;
	}

	/**
	 * Generate product description.
	 *
	 * @param string $prompt The prompt to send to OpenAI.
	 * @return array|\WP_Error Array with description and token usage, or WP_Error on failure.
	 */
	public function generate_description(string $prompt): array|\WP_Error {
		if (empty($prompt)) {
			return new \WP_Error(
				'empty_prompt',
				__('Prompt cannot be empty.', 'rfs-ai-seo')
			);
		}

		// Build payload for description generation.
		$payload = [
			'model' => $this->model,
			'messages' => [
				[
					'role' => 'system',
					'content' => 'You are a product description specialist. Respond only in valid JSON format with a "description" key.',
				],
				[
					'role' => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => 0.7,
			'max_tokens' => 1000,
		];

		// Make the API request.
		$response = $this->make_request($payload);

		if (is_wp_error($response)) {
			return $response;
		}

		// Parse the description response.
		$parsed = $this->parse_description_response($response);

		if (is_wp_error($parsed)) {
			return $parsed;
		}

		return $parsed;
	}

	/**
	 * Generate alt text for an image.
	 *
	 * @param string $prompt The prompt to send to OpenAI.
	 * @return array|\WP_Error Array with alt_text and token usage, or WP_Error on failure.
	 */
	public function generate_alt_text(string $prompt): array|\WP_Error {
		if (empty($prompt)) {
			return new \WP_Error(
				'empty_prompt',
				__('Prompt cannot be empty.', 'rfs-ai-seo')
			);
		}

		$payload = [
			'model' => $this->model,
			'messages' => [
				[
					'role' => 'system',
					'content' => 'You are an accessibility and SEO specialist. Respond only in valid JSON format with an "alt_text" key.',
				],
				[
					'role' => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => 0.7,
			'max_tokens' => 300,
		];

		$response = $this->make_request($payload);

		if (is_wp_error($response)) {
			return $response;
		}

		return $this->parse_alt_text_response($response);
	}

	/**
	 * Parse the alt text generation response.
	 *
	 * @param array $response API response.
	 * @return array|\WP_Error Parsed data or WP_Error.
	 */
	private function parse_alt_text_response(array $response): array|\WP_Error {
		$content = $response['choices'][0]['message']['content'] ?? '';

		if (empty($content)) {
			return new \WP_Error(
				'empty_response',
				__('OpenAI returned an empty response.', 'rfs-ai-seo')
			);
		}

		$content = $this->extract_json($content);

		$parsed = json_decode($content, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new \WP_Error(
				'invalid_json',
				__('OpenAI did not return valid JSON. Please try again.', 'rfs-ai-seo')
			);
		}

		if (!isset($parsed['alt_text'])) {
			return new \WP_Error(
				'missing_fields',
				__('OpenAI response is missing the alt_text field.', 'rfs-ai-seo')
			);
		}

		$tokens = [
			'prompt' => $response['usage']['prompt_tokens'] ?? 0,
			'completion' => $response['usage']['completion_tokens'] ?? 0,
			'total' => $response['usage']['total_tokens'] ?? 0,
		];

		return [
			'alt_text' => trim($parsed['alt_text']),
			'tokens' => $tokens,
			'charged_gbp' => $response['charged_gbp'] ?? null,
		];
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
	 * Post a chat completion to the Red Frog portal gateway.
	 *
	 * Network failures and 5xx responses are retried once, then surface as
	 * ats_portal_unavailable. 4xx responses map through Portal::error().
	 *
	 * @param array $payload     Chat completion payload.
	 * @param int   $retry_count Internal retry counter.
	 * @return array|\WP_Error Decoded response with 'charged_gbp' added.
	 */
	private function make_request(array $payload, int $retry_count = 0): array|\WP_Error {
		if (!Portal::has_key('content')) {
			return new \WP_Error(
				'ats_portal_no_key',
				__('AI is not connected. Run: wp ats secret set gateway_key_content', 'rfs-ai-seo')
			);
		}

		$response = wp_remote_post(
			Portal::openai_url('/chat/completions'),
			[
				'headers' => Portal::headers('content', $this->purpose, $this->reference),
				'body' => wp_json_encode($payload),
				'timeout' => self::TIMEOUT,
			]
		);

		$code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

		if (is_wp_error($response) || $code >= 500) {
			if ($retry_count < self::MAX_RETRIES) {
				return $this->make_request($payload, $retry_count + 1);
			}
			return new \WP_Error('ats_portal_unavailable', __('AI service unavailable, try again.', 'rfs-ai-seo'));
		}

		$body = (string) wp_remote_retrieve_body($response);

		if ($code !== 200) {
			return Portal::error($code, $body);
		}

		$data = json_decode($body, true);
		if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
			return new \WP_Error('invalid_response', __('Invalid response structure from the AI service.', 'rfs-ai-seo'));
		}

		$this->last_charged = Portal::observe($response);
		$data['charged_gbp'] = $this->last_charged;

		return $data;
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

		// Strip markdown code blocks if present (```json ... ``` or ``` ... ```).
		$content = $this->extract_json($content);

		// Parse JSON from content.
		$parsed = json_decode($content, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new \WP_Error(
				'invalid_json',
				__('OpenAI did not return valid JSON. Please try again.', 'rfs-ai-seo')
			);
		}

		// Validate required fields.
		if (!isset($parsed['title']) || !isset($parsed['description'])) {
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
			'charged_gbp' => $response['charged_gbp'] ?? null,
		];
	}

	/**
	 * Parse the description generation response.
	 *
	 * @param array $response API response.
	 * @return array|\WP_Error Parsed data or WP_Error.
	 */
	private function parse_description_response(array $response): array|\WP_Error {
		// Extract the content.
		$content = $response['choices'][0]['message']['content'] ?? '';

		if (empty($content)) {
			return new \WP_Error(
				'empty_response',
				__('OpenAI returned an empty response.', 'rfs-ai-seo')
			);
		}

		// Strip markdown code blocks if present.
		$content = $this->extract_json($content);

		// Parse JSON from content.
		$parsed = json_decode($content, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new \WP_Error(
				'invalid_json',
				__('OpenAI did not return valid JSON. Please try again.', 'rfs-ai-seo')
			);
		}

		// Validate required field.
		if (!isset($parsed['description'])) {
			return new \WP_Error(
				'missing_fields',
				__('OpenAI response is missing the description field.', 'rfs-ai-seo')
			);
		}

		// Extract token usage.
		$tokens = [
			'prompt' => $response['usage']['prompt_tokens'] ?? 0,
			'completion' => $response['usage']['completion_tokens'] ?? 0,
			'total' => $response['usage']['total_tokens'] ?? 0,
		];

		return [
			'description' => trim($parsed['description']),
			'tokens' => $tokens,
			'charged_gbp' => $response['charged_gbp'] ?? null,
		];
	}

	/**
	 * Extract JSON from content that may be wrapped in markdown code blocks.
	 *
	 * @param string $content Raw content from OpenAI.
	 * @return string Cleaned JSON string.
	 */
	private function extract_json(string $content): string {
		$content = trim($content);

		// Strip markdown code blocks: ```json ... ``` or ``` ... ```
		if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $matches)) {
			return trim($matches[1]);
		}

		// Try to extract a JSON object if there's extra text around it.
		if (preg_match('/(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})/s', $content, $matches)) {
			return trim($matches[1]);
		}

		return $content;
	}
}
