<?php
/**
 * Usage Statistics Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap rfs-ai-seo-stats-page">
	<h1><?php esc_html_e('Usage Statistics', 'rfs-ai-seo'); ?></h1>

	<?php
	// Get settings for currency formatting.
	$settings = new \RFS_AI_SEO\Settings();

	// Check spending cap status.
	$cap_status = $logger->check_spending_cap();
	if ($cap_status['cap'] > 0) :
		$percentage = ($cap_status['current_cost'] / $cap_status['cap']) * 100;
		$bar_class = $percentage >= 100 ? 'danger' : ($percentage >= 80 ? 'warning' : 'success');
		?>
		<!-- Spending Cap Notice -->
		<div class="notice notice-<?php echo $percentage >= 100 ? 'error' : ($percentage >= 80 ? 'warning' : 'info'); ?> inline">
			<p>
				<strong><?php esc_html_e('Monthly Spending Cap:', 'rfs-ai-seo'); ?></strong>
				<?php
				printf(
					/* translators: 1: Current spending, 2: Cap limit, 3: Remaining */
					esc_html__('%1$s of %2$s used this month (%3$s remaining)', 'rfs-ai-seo'),
					esc_html($settings->format_cost($cap_status['current_cost'])),
					esc_html($settings->format_cost($cap_status['cap'])),
					esc_html($settings->format_cost($cap_status['remaining']))
				);
				?>
			</p>
			<div class="rfs-spending-bar">
				<div class="rfs-spending-bar-fill <?php echo esc_attr($bar_class); ?>" style="width: <?php echo min(100, $percentage); ?>%"></div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Summary Cards -->
	<div class="rfs-ai-seo-stats-cards">
		<div class="rfs-ai-seo-stat-card">
			<div class="number"><?php echo esc_html(number_format($stats['total_generations'])); ?></div>
			<div class="label"><?php esc_html_e('Generations this month', 'rfs-ai-seo'); ?></div>
		</div>

		<div class="rfs-ai-seo-stat-card">
			<div class="number"><?php echo esc_html(number_format($stats['total_tokens'])); ?></div>
			<div class="label"><?php esc_html_e('Tokens this month', 'rfs-ai-seo'); ?></div>
		</div>

		<div class="rfs-ai-seo-stat-card">
			<div class="number"><?php echo esc_html($settings->format_cost($stats['estimated_cost'])); ?></div>
			<div class="label"><?php esc_html_e('Estimated cost this month', 'rfs-ai-seo'); ?></div>
		</div>

		<div class="rfs-ai-seo-stat-card">
			<div class="number"><?php echo esc_html(number_format($stats['by_type']['product'])); ?></div>
			<div class="label"><?php esc_html_e('Products generated', 'rfs-ai-seo'); ?></div>
		</div>

		<div class="rfs-ai-seo-stat-card">
			<div class="number"><?php echo esc_html(number_format($stats['by_type']['product_cat'])); ?></div>
			<div class="label"><?php esc_html_e('Categories generated', 'rfs-ai-seo'); ?></div>
		</div>

		<div class="rfs-ai-seo-stat-card">
			<div class="number"><?php echo esc_html(number_format($stats['by_type']['image_generation'])); ?></div>
			<div class="label"><?php esc_html_e('Images generated', 'rfs-ai-seo'); ?></div>
		</div>
	</div>

	<!-- Export Button -->
	<div class="rfs-export-form">
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="rfs_ai_seo_export_csv">
			<?php wp_nonce_field('rfs_ai_seo_export_csv', 'rfs_ai_seo_export_nonce'); ?>

			<label for="export_start_date"><?php esc_html_e('Start Date:', 'rfs-ai-seo'); ?></label>
			<input type="date" id="export_start_date" name="start_date" value="<?php echo esc_attr(date('Y-m-01')); ?>" required>

			<label for="export_end_date"><?php esc_html_e('End Date:', 'rfs-ai-seo'); ?></label>
			<input type="date" id="export_end_date" name="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>

			<button type="submit" class="button button-secondary">
				<?php esc_html_e('Export CSV', 'rfs-ai-seo'); ?>
			</button>
		</form>
	</div>

	<!-- Recent Generations Table -->
	<h2><?php esc_html_e('Recent Generations', 'rfs-ai-seo'); ?></h2>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e('Date', 'rfs-ai-seo'); ?></th>
				<th><?php esc_html_e('Type', 'rfs-ai-seo'); ?></th>
				<th><?php esc_html_e('Item', 'rfs-ai-seo'); ?></th>
				<th><?php esc_html_e('Title', 'rfs-ai-seo'); ?></th>
				<th><?php esc_html_e('Tokens', 'rfs-ai-seo'); ?></th>
				<th><?php esc_html_e('Model', 'rfs-ai-seo'); ?></th>
				<th><?php esc_html_e('Cost', 'rfs-ai-seo'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($recent_logs)) : ?>
				<tr>
					<td colspan="7" class="rfs-empty-state">
						<?php esc_html_e('No generation logs found.', 'rfs-ai-seo'); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ($recent_logs as $log) : ?>
					<tr>
						<td><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($log['created_at']))); ?></td>
						<td>
							<?php
							$type_labels = [
								'product' => __('Product', 'rfs-ai-seo'),
								'product_variation' => __('Variation', 'rfs-ai-seo'),
								'product_cat' => __('Category', 'rfs-ai-seo'),
								'image_generation' => __('Image', 'rfs-ai-seo'),
							];
							echo esc_html($type_labels[$log['object_type']] ?? $log['object_type']);
							?>
						</td>
						<td>
							<?php
							$edit_url = $log['object_type'] === 'product_cat'
								? get_edit_term_link($log['object_id'], 'product_cat')
								: get_edit_post_link($log['object_id']);
							?>
							<a href="<?php echo esc_url($edit_url); ?>" target="_blank">
								<?php echo esc_html($log['object_name']); ?>
							</a>
						</td>
						<td>
							<span title="<?php echo esc_attr($log['generated_title']); ?>">
								<?php echo esc_html(mb_substr($log['generated_title'], 0, 50)); ?>
								<?php if (mb_strlen($log['generated_title']) > 50) echo '...'; ?>
							</span>
						</td>
						<td><?php echo esc_html(number_format($log['tokens_total'])); ?></td>
						<td>
							<span class="rfs-model-badge rfs-model-<?php echo esc_attr($log['model']); ?>">
								<?php echo esc_html($log['model']); ?>
							</span>
						</td>
						<td><?php echo esc_html($settings->format_cost($log['estimated_cost'], 4)); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
