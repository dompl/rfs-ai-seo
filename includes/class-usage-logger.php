<?php
/**
 * Usage Logger Class
 *
 * @package RFS_AI_SEO
 */

namespace RFS_AI_SEO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Usage Logger Class
 *
 * Logs generation usage and provides statistics.
 *
 * @class Usage_Logger
 */
class Usage_Logger {

	/**
	 * Log a generation.
	 *
	 * @param int    $object_id   Post ID or term ID.
	 * @param string $object_type Object type.
	 * @param array  $data        Generation data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function log(int $object_id, string $object_type, array $data) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';

		$insert_data = [
			'object_id' => $object_id,
			'object_type' => $object_type,
			'tokens_prompt' => $data['tokens_prompt'] ?? 0,
			'tokens_completion' => $data['tokens_completion'] ?? 0,
			'tokens_total' => $data['tokens_total'] ?? 0,
			'model' => $data['model'] ?? 'gpt-4o',
			'generated_title' => $data['generated_title'] ?? '',
			'generated_description' => $data['generated_description'] ?? '',
			'user_id' => get_current_user_id(),
			'created_at' => current_time('mysql'),
		];

		$inserted = $wpdb->insert($table_name, $insert_data);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get usage statistics for a period.
	 *
	 * @param string $period Period (month, week, day, all).
	 * @return array Statistics array.
	 */
	public function get_stats(string $period = 'month'): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';

		// Calculate date range.
		$date_condition = $this->get_date_condition($period);

		// Get totals.
		$totals = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_generations,
				SUM(tokens_total) as total_tokens,
				SUM(tokens_prompt) as total_prompt_tokens,
				SUM(tokens_completion) as total_completion_tokens
			FROM {$table_name}
			WHERE {$date_condition}",
			ARRAY_A
		);

		// Get by type.
		$by_type = $wpdb->get_results(
			"SELECT
				object_type,
				COUNT(*) as count
			FROM {$table_name}
			WHERE {$date_condition}
			GROUP BY object_type",
			ARRAY_A
		);

		// Get by model.
		$by_model = $wpdb->get_results(
			"SELECT
				model,
				COUNT(*) as count,
				SUM(tokens_total) as tokens
			FROM {$table_name}
			WHERE {$date_condition}
			GROUP BY model",
			ARRAY_A
		);

		// Calculate estimated cost.
		$estimated_cost = $this->calculate_total_cost($by_model);

		return [
			'total_generations' => (int) ($totals['total_generations'] ?? 0),
			'total_tokens' => (int) ($totals['total_tokens'] ?? 0),
			'total_prompt_tokens' => (int) ($totals['total_prompt_tokens'] ?? 0),
			'total_completion_tokens' => (int) ($totals['total_completion_tokens'] ?? 0),
			'estimated_cost' => $estimated_cost,
			'by_type' => $this->format_by_type($by_type),
			'by_model' => $by_model,
		];
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Limit.
	 * @return array Logs array.
	 */
	public function get_recent_logs(int $limit = 50): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		// Enhance with object names.
		foreach ($logs as &$log) {
			$log['object_name'] = $this->get_object_name($log['object_id'], $log['object_type']);
			$log['estimated_cost'] = $this->calculate_cost(
				$log['tokens_prompt'],
				$log['tokens_completion'],
				$log['model']
			);
		}

		return $logs;
	}

	/**
	 * Get monthly totals.
	 *
	 * @return array Monthly totals.
	 */
	public function get_monthly_totals(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';

		$results = $wpdb->get_results(
			"SELECT
				DATE_FORMAT(created_at, '%Y-%m') as month,
				COUNT(*) as total_generations,
				SUM(tokens_total) as total_tokens
			FROM {$table_name}
			GROUP BY month
			ORDER BY month DESC
			LIMIT 12",
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Export logs as CSV.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return string CSV content.
	 */
	public function export_csv(string $start_date, string $end_date): string {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				WHERE DATE(created_at) BETWEEN %s AND %s
				ORDER BY created_at DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Build CSV.
		$csv = "Date,Object Type,Object Name,Title,Description,Tokens Prompt,Tokens Completion,Tokens Total,Model,Estimated Cost,User ID\n";

		foreach ($logs as $log) {
			$object_name = $this->get_object_name($log['object_id'], $log['object_type']);
			$cost = $this->calculate_cost(
				$log['tokens_prompt'],
				$log['tokens_completion'],
				$log['model']
			);

			$csv .= sprintf(
				'"%s","%s","%s","%s","%s",%d,%d,%d,"%s","$%.4f",%d' . "\n",
				$log['created_at'],
				$log['object_type'],
				str_replace('"', '""', $object_name),
				str_replace('"', '""', $log['generated_title']),
				str_replace('"', '""', $log['generated_description']),
				$log['tokens_prompt'],
				$log['tokens_completion'],
				$log['tokens_total'],
				$log['model'],
				$cost,
				$log['user_id']
			);
		}

		return $csv;
	}

	/**
	 * Cleanup old logs.
	 *
	 * @param int $days Days to keep.
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_logs(int $days = 90): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rfs_ai_seo_log';

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $deleted;
	}

	/**
	 * Get date condition for query.
	 *
	 * @param string $period Period.
	 * @return string SQL condition.
	 */
	private function get_date_condition(string $period): string {
		switch ($period) {
			case 'day':
				return "DATE(created_at) = CURDATE()";
			case 'week':
				return "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
			case 'month':
				return "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
			case 'all':
			default:
				return "1=1";
		}
	}

	/**
	 * Format by type results.
	 *
	 * @param array $by_type By type results.
	 * @return array Formatted array.
	 */
	private function format_by_type(array $by_type): array {
		$formatted = [
			'product' => 0,
			'product_variation' => 0,
			'product_cat' => 0,
		];

		foreach ($by_type as $type) {
			$formatted[$type['object_type']] = (int) $type['count'];
		}

		return $formatted;
	}

	/**
	 * Calculate total cost.
	 *
	 * @param array $by_model By model results.
	 * @return float Total cost.
	 */
	private function calculate_total_cost(array $by_model): float {
		$total_cost = 0;

		foreach ($by_model as $model_data) {
			// We don't have separate prompt/completion counts in by_model query,
			// so this is an approximation (assuming 70% prompt, 30% completion).
			$tokens = (int) $model_data['tokens'];
			$prompt_tokens = (int) ($tokens * 0.7);
			$completion_tokens = (int) ($tokens * 0.3);

			$total_cost += $this->calculate_cost(
				$prompt_tokens,
				$completion_tokens,
				$model_data['model']
			);
		}

		return $total_cost;
	}

	/**
	 * Calculate cost for tokens.
	 *
	 * @param int    $prompt_tokens     Prompt tokens.
	 * @param int    $completion_tokens Completion tokens.
	 * @param string $model             Model.
	 * @return float Cost in USD (with multiplier applied).
	 */
	private function calculate_cost(int $prompt_tokens, int $completion_tokens, string $model): float {
		// Rates per 1K tokens (as of 2024).
		$rates = [
			'gpt-4o' => [
				'prompt' => 0.005,
				'completion' => 0.015,
			],
			'gpt-4o-mini' => [
				'prompt' => 0.00015,
				'completion' => 0.0006,
			],
		];

		$rate = $rates[$model] ?? $rates['gpt-4o'];

		$cost = (($prompt_tokens / 1000) * $rate['prompt']) +
				(($completion_tokens / 1000) * $rate['completion']);

		// Apply cost multiplier from settings.
		$settings = new Settings();
		$multiplier = $settings->get_cost_multiplier();

		return $cost * $multiplier;
	}

	/**
	 * Check if monthly spending is below cap.
	 *
	 * @return array Status array with 'allowed', 'current_cost', 'cap', 'remaining'.
	 */
	public function check_spending_cap(): array {
		$settings = new Settings();

		// If no cap is defined, always allow.
		if (!$settings->has_spending_cap()) {
			return [
				'allowed' => true,
				'current_cost' => 0,
				'cap' => 0,
				'remaining' => 0,
				'message' => '',
			];
		}

		$cap = $settings->get_spending_cap();
		$stats = $this->get_stats('month');
		$current_cost = $stats['estimated_cost'];
		$remaining = max(0, $cap - $current_cost);

		$allowed = $current_cost < $cap;

		$message = '';
		if (!$allowed) {
			$message = sprintf(
				__('Monthly spending cap of $%.2f has been reached. Current spending: $%.2f', 'rfs-ai-seo'),
				$cap,
				$current_cost
			);
		} elseif ($remaining < ($cap * 0.1)) {
			$message = sprintf(
				__('Warning: You have $%.2f remaining of your $%.2f monthly cap.', 'rfs-ai-seo'),
				$remaining,
				$cap
			);
		}

		return [
			'allowed' => $allowed,
			'current_cost' => $current_cost,
			'cap' => $cap,
			'remaining' => $remaining,
			'message' => $message,
		];
	}

	/**
	 * Get object name.
	 *
	 * @param int    $object_id   Object ID.
	 * @param string $object_type Object type.
	 * @return string Object name.
	 */
	private function get_object_name(int $object_id, string $object_type): string {
		if ($object_type === 'product_cat') {
			$term = get_term($object_id, 'product_cat');
			return $term && !is_wp_error($term) ? $term->name : "Category #{$object_id}";
		}

		$post = get_post($object_id);
		return $post ? $post->post_title : "Post #{$object_id}";
	}
}
