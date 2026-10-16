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
			'charged_gbp' => round((float) ($data['charged_gbp'] ?? 0), 4),
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

		return [
			'total_generations' => (int) ($totals['total_generations'] ?? 0),
			'total_tokens' => (int) ($totals['total_tokens'] ?? 0),
			'total_prompt_tokens' => (int) ($totals['total_prompt_tokens'] ?? 0),
			'total_completion_tokens' => (int) ($totals['total_completion_tokens'] ?? 0),
			'charged_gbp' => (float) $wpdb->get_var("SELECT COALESCE(SUM(charged_gbp), 0) FROM {$table_name} WHERE {$date_condition}"),
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
		$csv = "Date,Object Type,Object Name,Title,Description,Tokens Prompt,Tokens Completion,Tokens Total,Model,Charged (GBP),User ID\n";

		foreach ($logs as $log) {
			$object_name = $this->get_object_name($log['object_id'], $log['object_type']);

			$csv .= sprintf(
				'"%s","%s","%s","%s","%s",%d,%d,%d,"%s","%s",%d' . "\n",
				$log['created_at'],
				$log['object_type'],
				str_replace('"', '""', $object_name),
				str_replace('"', '""', $log['generated_title']),
				str_replace('"', '""', $log['generated_description']),
				$log['tokens_prompt'],
				$log['tokens_completion'],
				$log['tokens_total'],
				$log['model'],
				number_format((float) $log['charged_gbp'], 4, '.', ''),
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
			'image_generation' => 0,
			'alt_text' => 0,
		];

		foreach ($by_type as $type) {
			$formatted[$type['object_type']] = (int) $type['count'];
		}

		return $formatted;
	}

	/**
	 * Average gateway charge for the last N charged rows of one type.
	 * Used for the "about £x per product" estimate on bulk pages.
	 */
	public function average_charge(string $object_type, int $last = 20): ?float {
		global $wpdb;
		$table = $wpdb->prefix . 'rfs_ai_seo_log';
		$avg = $wpdb->get_var($wpdb->prepare(
			"SELECT AVG(charged_gbp) FROM (SELECT charged_gbp FROM {$table} WHERE object_type = %s AND charged_gbp > 0 ORDER BY id DESC LIMIT %d) t",
			$object_type,
			max(1, $last)
		));
		return $avg === null ? null : (float) $avg;
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

		if ($object_type === 'image_generation') {
			$post = get_post($object_id);
			return $post ? $post->post_title : "Image #{$object_id}";
		}

		if ($object_type === 'alt_text') {
			$post = get_post($object_id);
			return $post ? $post->post_title : "Image #{$object_id}";
		}

		$post = get_post($object_id);
		return $post ? $post->post_title : "Post #{$object_id}";
	}
}
