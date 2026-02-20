<?php
/**
 * Dashboard Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Get settings.
$settings = new \RFS_AI_SEO\Settings();
$api_key = $settings->get_api_key();
$has_api_key = !empty($api_key);

// Get usage stats.
$logger = new \RFS_AI_SEO\Usage_Logger();
$stats = $logger->get_stats('month');
?>

<div class="wrap rfs-ai-seo-dashboard">
	<h1><?php esc_html_e('RFS AI', 'rfs-ai-seo'); ?></h1>

	<?php if (!$has_api_key): ?>
		<div class="notice notice-warning">
			<h3><?php esc_html_e('API Key Required', 'rfs-ai-seo'); ?></h3>
			<p><?php esc_html_e('To start using RFS AI, you need to configure your OpenAI API key in Settings.', 'rfs-ai-seo'); ?></p>
			<p>
				<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-settings')); ?>" class="button button-primary">
					<?php esc_html_e('Go to Settings', 'rfs-ai-seo'); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<!-- Credit Balance -->
	<?php
	\RFS_AI_SEO\Credits_Manager::maybe_init_balance();
	$credit_balance = \RFS_AI_SEO\Credits_Manager::get_balance();
	?>
	<div class="rfs-dashboard-credits">
		<div class="rfs-dashboard-credits-inner">
			<span class="rfs-dashboard-credits-label"><?php esc_html_e('Credit Balance:', 'rfs-ai-seo'); ?></span>
			<span class="rfs-dashboard-credits-amount <?php echo $credit_balance < 1.00 ? 'rfs-credits-low' : ''; ?>">
				<?php echo esc_html("\xC2\xA3" . number_format($credit_balance, 2)); ?>
			</span>
			<?php if ($credit_balance < 1.00): ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-credits')); ?>" class="button button-small">
					<?php esc_html_e('Buy Credits', 'rfs-ai-seo'); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Tools Grid -->
	<div class="rfs-dashboard-tools">
		<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-seo-bulk')); ?>" class="rfs-tool-card">
			<div class="rfs-tool-card-header">
				<h3><?php esc_html_e('SEO Meta', 'rfs-ai-seo'); ?></h3>
				<span class="dashicons dashicons-search"></span>
			</div>
			<p><?php esc_html_e('Generate optimized meta titles and descriptions to improve search visibility and click-through rates.', 'rfs-ai-seo'); ?></p>
		</a>

		<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-description-bulk')); ?>" class="rfs-tool-card">
			<div class="rfs-tool-card-header">
				<h3><?php esc_html_e('Descriptions', 'rfs-ai-seo'); ?></h3>
				<span class="dashicons dashicons-editor-alignleft"></span>
			</div>
			<p><?php esc_html_e('Create comprehensive product descriptions that highlight features, benefits, and specifications.', 'rfs-ai-seo'); ?></p>
		</a>

		<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-short-description-bulk')); ?>" class="rfs-tool-card">
			<div class="rfs-tool-card-header">
				<h3><?php esc_html_e('Short Descriptions', 'rfs-ai-seo'); ?></h3>
				<span class="dashicons dashicons-editor-paragraph"></span>
			</div>
			<p><?php esc_html_e('Generate concise 20-50 word product summaries for catalog listings and quick overviews.', 'rfs-ai-seo'); ?></p>
		</a>

		<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-image-bulk')); ?>" class="rfs-tool-card">
			<div class="rfs-tool-card-header">
				<h3><?php esc_html_e('Images', 'rfs-ai-seo'); ?></h3>
				<span class="dashicons dashicons-format-image"></span>
			</div>
			<p><?php esc_html_e('Regenerate product photos in bulk using AI-powered image generation with custom prompts.', 'rfs-ai-seo'); ?></p>
		</a>
	</div>

	<!-- Bottom Row: Stats + Quick Links -->
	<div class="rfs-dashboard-bottom">
		<?php if ($has_api_key && $stats['total_generations'] > 0): ?>
			<div class="rfs-dashboard-stats-card">
				<h3><?php esc_html_e('This Month', 'rfs-ai-seo'); ?></h3>
				<div class="rfs-dashboard-stats-row">
					<div class="rfs-dashboard-stat">
						<span class="rfs-dashboard-stat-value"><?php echo esc_html(number_format($stats['total_generations'])); ?></span>
						<span class="rfs-dashboard-stat-label"><?php esc_html_e('Generations', 'rfs-ai-seo'); ?></span>
					</div>
					<div class="rfs-dashboard-stat">
						<span class="rfs-dashboard-stat-value"><?php echo esc_html(number_format($stats['total_tokens'])); ?></span>
						<span class="rfs-dashboard-stat-label"><?php esc_html_e('Tokens', 'rfs-ai-seo'); ?></span>
					</div>
					<div class="rfs-dashboard-stat">
						<span class="rfs-dashboard-stat-value"><?php echo esc_html($settings->format_cost($stats['estimated_cost'])); ?></span>
						<span class="rfs-dashboard-stat-label"><?php esc_html_e('Est. Cost', 'rfs-ai-seo'); ?></span>
					</div>
				</div>
				<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-stats')); ?>" class="rfs-dashboard-stats-link">
					<?php esc_html_e('View full statistics', 'rfs-ai-seo'); ?> &rarr;
				</a>
			</div>
		<?php endif; ?>

		<div class="rfs-dashboard-quick-links">
			<h3><?php esc_html_e('Configuration', 'rfs-ai-seo'); ?></h3>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-trainer')); ?>" class="rfs-quick-link">
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e('AI Trainer', 'rfs-ai-seo'); ?>
			</a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-settings')); ?>" class="rfs-quick-link">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e('Settings', 'rfs-ai-seo'); ?>
			</a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-stats')); ?>" class="rfs-quick-link">
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e('Usage Statistics', 'rfs-ai-seo'); ?>
			</a>
		</div>
	</div>
</div>
