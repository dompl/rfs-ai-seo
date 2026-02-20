<?php
/**
 * Credits Manager Class
 *
 * Manages pre-paid credit balance, packages, and purchase history.
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Credits Manager Class
 *
 * @class Credits_Manager
 */
class Credits_Manager {

	/**
	 * Option key for credits balance (GBP).
	 */
	private const BALANCE_OPTION = 'rfs_ai_seo_credits_balance';

	/**
	 * Option key for purchase history.
	 */
	private const PURCHASES_OPTION = 'rfs_ai_seo_credits_purchases';

	/**
	 * Maximum purchase history entries to keep.
	 */
	private const MAX_HISTORY_ENTRIES = 200;

	/**
	 * Default secret key for the top-up URL.
	 * Override with RFS_AI_SEO_TOPUP_SECRET constant in wp-config.php.
	 */
	private const DEFAULT_TOPUP_SECRET = 'ats-9kQ7xm2Rv4pL';

	/**
	 * Register the secret top-up URL handler.
	 *
	 * Call this once during plugin initialization.
	 *
	 * @return void
	 */
	public static function register_topup_handler(): void {
		add_action('admin_init', [self::class, 'handle_topup_request']);
	}

	/**
	 * Handle a secret top-up request.
	 *
	 * URL format: /wp-admin/?ats_gx_topup=SECRET&amount=20
	 *
	 * @return void
	 */
	public static function handle_topup_request(): void {
		if (!isset($_GET['ats_gx_topup']) || !isset($_GET['amount'])) {
			return;
		}

		$provided_secret = sanitize_text_field(wp_unslash($_GET['ats_gx_topup']));
		$expected_secret = defined('RFS_AI_SEO_TOPUP_SECRET')
			? RFS_AI_SEO_TOPUP_SECRET
			: self::DEFAULT_TOPUP_SECRET;

		if (!hash_equals($expected_secret, $provided_secret)) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		$amount = floatval($_GET['amount']);

		if ($amount <= 0 || $amount > 10000) {
			return;
		}

		// Add credits directly to balance.
		$current_balance = self::get_balance();
		$new_balance = $current_balance + $amount;
		update_option(self::BALANCE_OPTION, $new_balance);

		// Record in purchase history.
		$session_id = 'manual-topup-' . wp_generate_uuid4();
		$purchases = get_option(self::PURCHASES_OPTION, []);
		if (!is_array($purchases)) {
			$purchases = [];
		}

		$purchases[] = [
			'session_id'  => $session_id,
			'package_id'  => 'manual_topup',
			'credits_gbp' => $amount,
			'date'        => current_time('mysql'),
			'user_id'     => get_current_user_id(),
		];

		if (count($purchases) > self::MAX_HISTORY_ENTRIES) {
			$purchases = array_slice($purchases, -self::MAX_HISTORY_ENTRIES);
		}

		update_option(self::PURCHASES_OPTION, $purchases);

		// Redirect to credits page with success notice.
		$settings = new Settings();
		$symbol = $settings->get_currency_symbol();

		set_transient(
			'rfs_ai_seo_topup_notice',
			sprintf('%s%s credited. New balance: %s%s', $symbol, number_format($amount, 2), $symbol, number_format($new_balance, 2)),
			30
		);

		wp_safe_redirect(admin_url('admin.php?page=rfs-ai-credits&topup=success'));
		exit;
	}

	/**
	 * Get credit packages.
	 *
	 * @return array Array of package definitions.
	 */
	public static function get_packages(): array {
		return [
			'credits_10' => [
				'id'          => 'credits_10',
				'label'       => "\xC2\xA310 Pack",
				'price_gbp'   => 10.00,
				'credits_gbp' => 10.00,
				'bonus_pct'   => 0,
			],
			'credits_25' => [
				'id'          => 'credits_25',
				'label'       => "\xC2\xA325 Pack",
				'price_gbp'   => 25.00,
				'credits_gbp' => 26.25,
				'bonus_pct'   => 5,
			],
			'credits_100' => [
				'id'          => 'credits_100',
				'label'       => "\xC2\xA3100 Pack",
				'price_gbp'   => 100.00,
				'credits_gbp' => 115.00,
				'bonus_pct'   => 15,
			],
		];
	}

	/**
	 * Get free allowance amount in GBP.
	 *
	 * @return float Free allowance in GBP.
	 */
	public static function get_free_allowance(): float {
		if (defined('RFS_AI_SEO_FREE_ALLOWANCE') && is_numeric(RFS_AI_SEO_FREE_ALLOWANCE)) {
			return (float) RFS_AI_SEO_FREE_ALLOWANCE;
		}

		return 2.00;
	}

	/**
	 * Get current credit balance.
	 *
	 * @return float Balance in GBP.
	 */
	public static function get_balance(): float {
		self::maybe_init_balance();

		return (float) get_option(self::BALANCE_OPTION, 0.0);
	}

	/**
	 * Initialise balance with free allowance if not yet set.
	 *
	 * @return void
	 */
	public static function maybe_init_balance(): void {
		if (get_option(self::BALANCE_OPTION) === false) {
			update_option(self::BALANCE_OPTION, self::get_free_allowance());
		}
	}

	/**
	 * Add credits to the balance (idempotent — checks session hasn't been processed).
	 *
	 * @param float  $credits_gbp Amount of credits in GBP.
	 * @param string $session_id  Stripe Checkout Session ID.
	 * @param string $package_id  Package identifier.
	 * @return bool True if credits were added, false if session was already processed.
	 */
	public static function add_credits(float $credits_gbp, string $session_id, string $package_id): bool {
		// Check if this session has already been processed.
		if (self::session_already_processed($session_id)) {
			return false;
		}

		// Add credits to balance.
		$current_balance = self::get_balance();
		$new_balance = $current_balance + $credits_gbp;
		update_option(self::BALANCE_OPTION, $new_balance);

		// Record the purchase.
		$purchases = get_option(self::PURCHASES_OPTION, []);
		if (!is_array($purchases)) {
			$purchases = [];
		}

		$purchases[] = [
			'session_id'  => $session_id,
			'package_id'  => $package_id,
			'credits_gbp' => $credits_gbp,
			'date'        => current_time('mysql'),
			'user_id'     => get_current_user_id(),
		];

		// Cap history length.
		if (count($purchases) > self::MAX_HISTORY_ENTRIES) {
			$purchases = array_slice($purchases, -self::MAX_HISTORY_ENTRIES);
		}

		update_option(self::PURCHASES_OPTION, $purchases);

		return true;
	}

	/**
	 * Deduct cost from credit balance.
	 *
	 * Converts the USD cost to GBP using ECB exchange rates before deducting.
	 *
	 * @param float $cost_usd Cost in USD.
	 * @return float Amount deducted in GBP.
	 */
	public static function deduct_cost(float $cost_usd): float {
		if ($cost_usd <= 0) {
			return 0.0;
		}

		$settings = new Settings();
		$rates = $settings->get_exchange_rates();
		$gbp_rate = $rates['GBP'] ?? 0.79;

		$cost_gbp = $cost_usd * $gbp_rate;

		$current_balance = self::get_balance();
		$new_balance = max(0, $current_balance - $cost_gbp);
		update_option(self::BALANCE_OPTION, $new_balance);

		return $cost_gbp;
	}

	/**
	 * Check if the balance allows generation.
	 *
	 * @return array Status array with 'allowed', 'balance', 'message'.
	 */
	public static function check_balance(): array {
		$balance = self::get_balance();
		$allowed = $balance > 0;

		$message = '';
		if (!$allowed) {
			$credits_url = admin_url('admin.php?page=rfs-ai-credits');
			$message = sprintf(
				/* translators: %s: URL to Buy Credits page */
				__('Your credit balance is empty. <a href="%s">Buy credits</a> to continue generating.', 'rfs-ai-seo'),
				$credits_url
			);
		} elseif ($balance < 1.00) {
			$credits_url = admin_url('admin.php?page=rfs-ai-credits');
			$message = sprintf(
				/* translators: %1$s: balance amount, %2$s: URL to Buy Credits page */
				__('Low credit balance: %1$s remaining. <a href="%2$s">Top up credits</a>.', 'rfs-ai-seo'),
				"\xC2\xA3" . number_format($balance, 2),
				$credits_url
			);
		}

		return [
			'allowed' => $allowed,
			'balance' => $balance,
			'message' => $message,
		];
	}

	/**
	 * Get purchase history.
	 *
	 * @param int $limit Maximum entries to return.
	 * @return array Purchase history (newest first).
	 */
	public static function get_purchase_history(int $limit = 20): array {
		$purchases = get_option(self::PURCHASES_OPTION, []);

		if (!is_array($purchases)) {
			return [];
		}

		// Return newest first.
		$purchases = array_reverse($purchases);

		return array_slice($purchases, 0, $limit);
	}

	/**
	 * Check if a Stripe session has already been processed.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return bool True if already processed.
	 */
	private static function session_already_processed(string $session_id): bool {
		$purchases = get_option(self::PURCHASES_OPTION, []);

		if (!is_array($purchases)) {
			return false;
		}

		foreach ($purchases as $purchase) {
			if (isset($purchase['session_id']) && $purchase['session_id'] === $session_id) {
				return true;
			}
		}

		return false;
	}
}
