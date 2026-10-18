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
$has_api_key = \RedFrogStudio\AtsAiPortal\Portal::has_key('content');

// Get usage stats.
$logger = new \RFS_AI_SEO\Usage_Logger();
$stats = $logger->get_stats('month');
?>

<?php do_action('ats_ai_portal_header', \RFS_AI_SEO\Admin::header_args('rfs-ai')); ?>
<hr class="wp-header-end" style="display:none">
<div class="ats-ai-wrap rfs-ai-seo-dashboard">
	<h1><?php esc_html_e('AI SEO', 'rfs-ai-seo'); ?></h1>

	<?php if (!$has_api_key): ?>
		<div class="notice notice-warning">
			<h3><?php esc_html_e('AI Not Connected', 'rfs-ai-seo'); ?></h3>
			<p><?php esc_html_e('AI is not connected. Run: wp ats secret set gateway_key_content to add the gateway key.', 'rfs-ai-seo'); ?></p>
			<p>
				<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-settings')); ?>" class="button button-primary">
					<?php esc_html_e('Go to Settings', 'rfs-ai-seo'); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

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
						<span class="rfs-dashboard-stat-value"><?php echo esc_html($settings->format_gbp((float) $stats['charged_gbp'])); ?></span>
						<span class="rfs-dashboard-stat-label"><?php esc_html_e('Spent', 'rfs-ai-seo'); ?></span>
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
