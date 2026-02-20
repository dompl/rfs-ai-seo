<?php
/**
 * Stripe Client Class
 *
 * Handles Stripe API communication using wp_remote_post/wp_remote_get.
 * No Stripe PHP SDK required.
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Stripe Client Class
 *
 * @class Stripe_Client
 */
class Stripe_Client {

	/**
	 * Stripe API base URL.
	 */
	private const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Expected Stripe account email.
	 */
	private const EXPECTED_EMAIL = 'info@redfrogstudio.co.uk';

	/**
	 * Request timeout in seconds.
	 */
	private const TIMEOUT = 30;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Constructor.
	 *
	 * @param string $secret_key Stripe secret key.
	 */
	public function __construct(string $secret_key) {
		$this->secret_key = $secret_key;
	}

	/**
	 * Verify the Stripe account belongs to Red Frog Studio.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function verify_account() {
		$response = $this->make_request('GET', '/account');

		if (is_wp_error($response)) {
			return $response;
		}

		$email = $response['email'] ?? '';

		if ($email !== self::EXPECTED_EMAIL) {
			return new \WP_Error(
				'stripe_account_mismatch',
				sprintf(
					/* translators: %s: expected email */
					__('Stripe account does not match %s. Please use the Red Frog Studio Stripe account.', 'rfs-ai-seo'),
					self::EXPECTED_EMAIL
				)
			);
		}

		return true;
	}

	/**
	 * Create a Stripe Checkout Session.
	 *
	 * @param array  $package     Package definition from Credits_Manager::get_packages().
	 * @param string $success_url URL to redirect to on successful payment.
	 * @param string $cancel_url  URL to redirect to on cancelled payment.
	 * @return array|\WP_Error Session data or error.
	 */
	public function create_checkout_session(array $package, string $success_url, string $cancel_url) {
		$price_pence = (int) round($package['price_gbp'] * 100);

		$body = [
			'mode'                 => 'payment',
			'success_url'          => $success_url,
			'cancel_url'           => $cancel_url,
			'line_items[0][price_data][currency]'    => 'gbp',
			'line_items[0][price_data][product_data][name]' => $package['label'] . ' - RFS AI Credits',
			'line_items[0][price_data][unit_amount]' => $price_pence,
			'line_items[0][quantity]' => 1,
			'metadata[package_id]'   => $package['id'],
			'metadata[credits_gbp]'  => $package['credits_gbp'],
			'metadata[site_url]'     => get_site_url(),
		];

		$response = $this->make_request('POST', '/checkout/sessions', $body);

		if (is_wp_error($response)) {
			return $response;
		}

		if (empty($response['id']) || empty($response['url'])) {
			return new \WP_Error(
				'stripe_session_error',
				__('Stripe did not return a valid checkout session.', 'rfs-ai-seo')
			);
		}

		return [
			'session_id'   => $response['id'],
			'checkout_url' => $response['url'],
		];
	}

	/**
	 * Retrieve a Stripe Checkout Session to verify payment.
	 *
	 * @param string $session_id Stripe Session ID.
	 * @return array|\WP_Error Session data or error.
	 */
	public function retrieve_checkout_session(string $session_id) {
		$response = $this->make_request('GET', '/checkout/sessions/' . urlencode($session_id));

		if (is_wp_error($response)) {
			return $response;
		}

		return $response;
	}

	/**
	 * Make a request to the Stripe API.
	 *
	 * @param string     $method HTTP method (GET or POST).
	 * @param string     $endpoint API endpoint path.
	 * @param array|null $body Request body for POST requests.
	 * @return array|\WP_Error Decoded response or error.
	 */
	private function make_request(string $method, string $endpoint, ?array $body = null) {
		$url = self::API_BASE . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($this->secret_key . ':'),
			],
		];

		if ($method === 'POST' && $body !== null) {
			$args['body'] = $body;
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return new \WP_Error(
				'stripe_network_error',
				__('Could not connect to Stripe. Please try again.', 'rfs-ai-seo')
			);
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);
		$data = json_decode($response_body, true);

		if ($response_code >= 400) {
			$error_message = $data['error']['message']
				?? __('Unknown Stripe API error.', 'rfs-ai-seo');

			return new \WP_Error('stripe_api_error', $error_message);
		}

		if (!is_array($data)) {
			return new \WP_Error(
				'stripe_parse_error',
				__('Invalid response from Stripe API.', 'rfs-ai-seo')
			);
		}

		return $data;
	}
}
