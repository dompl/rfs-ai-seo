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
	<h1><?php esc_html_e('RFS AI - AI-Powered Content Generation', 'rfs-ai-seo'); ?></h1>

	<?php if (!$has_api_key): ?>
		<div class="notice notice-warning" style="padding: 15px; margin: 20px 0;">
			<h3 style="margin-top: 0;"><?php esc_html_e('⚠️ API Key Required', 'rfs-ai-seo'); ?></h3>
			<p><?php esc_html_e('To start using RFS AI, you need to configure your OpenAI API key in Settings.', 'rfs-ai-seo'); ?></p>
			<p>
				<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-settings')); ?>" class="button button-primary">
					<?php esc_html_e('Go to Settings', 'rfs-ai-seo'); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<!-- Welcome Section -->
	<div class="rfs-welcome-section" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 30px; margin: 20px 0;">
		<h2 style="margin-top: 0;"><?php esc_html_e('Welcome to RFS AI', 'rfs-ai-seo'); ?></h2>
		<p style="font-size: 16px; line-height: 1.6; color: #50575e;">
			<?php esc_html_e('RFS AI helps you generate high-quality SEO metadata and product descriptions using artificial intelligence. Save time and improve your content with AI-powered suggestions.', 'rfs-ai-seo'); ?>
		</p>
	</div>

	<!-- Usage Statistics (if API key exists) -->
	<?php if ($has_api_key && $stats['total_generations'] > 0): ?>
		<h2><?php esc_html_e('Usage This Month', 'rfs-ai-seo'); ?></h2>
		<div class="rfs-stats-cards" style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
			<div class="rfs-stat-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 200px;">
				<div class="rfs-stat-icon" style="font-size: 32px; margin-bottom: 10px;">📊</div>
				<div class="rfs-stat-number" style="font-size: 36px; font-weight: 600; color: #1d2327; margin-bottom: 5px;">
					<?php echo esc_html(number_format($stats['total_generations'])); ?>
				</div>
				<div class="rfs-stat-label" style="color: #50575e; font-size: 14px;">
					<?php esc_html_e('Generations', 'rfs-ai-seo'); ?>
				</div>
			</div>

			<div class="rfs-stat-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 200px;">
				<div class="rfs-stat-icon" style="font-size: 32px; margin-bottom: 10px;">🔤</div>
				<div class="rfs-stat-number" style="font-size: 36px; font-weight: 600; color: #1d2327; margin-bottom: 5px;">
					<?php echo esc_html(number_format($stats['total_tokens'])); ?>
				</div>
				<div class="rfs-stat-label" style="color: #50575e; font-size: 14px;">
					<?php esc_html_e('Tokens Used', 'rfs-ai-seo'); ?>
				</div>
			</div>

			<div class="rfs-stat-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 200px;">
				<div class="rfs-stat-icon" style="font-size: 32px; margin-bottom: 10px;">💰</div>
				<div class="rfs-stat-number" style="font-size: 36px; font-weight: 600; color: #1d2327; margin-bottom: 5px;">
					$<?php echo esc_html(number_format($stats['estimated_cost'], 2)); ?>
				</div>
				<div class="rfs-stat-label" style="color: #50575e; font-size: 14px;">
					<?php esc_html_e('Est. Cost', 'rfs-ai-seo'); ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Features Grid -->
	<h2><?php esc_html_e('Available Tools', 'rfs-ai-seo'); ?></h2>
	<div class="rfs-features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">

		<!-- SEO Meta Generator -->
		<div class="rfs-feature-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px;">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 24px;">🔍</span>
				<?php esc_html_e('SEO Meta Generator', 'rfs-ai-seo'); ?>
			</h3>
			<p style="color: #50575e; line-height: 1.6;">
				<?php esc_html_e('Generate optimized meta titles and descriptions for your products. Improve your search engine visibility and click-through rates with AI-powered SEO content.', 'rfs-ai-seo'); ?>
			</p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-seo-bulk')); ?>" class="button button-primary">
				<?php esc_html_e('Open SEO Generator', 'rfs-ai-seo'); ?>
			</a>
		</div>

		<!-- Full Description Generator -->
		<div class="rfs-feature-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px;">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 24px;">📝</span>
				<?php esc_html_e('Description Generator', 'rfs-ai-seo'); ?>
			</h3>
			<p style="color: #50575e; line-height: 1.6;">
				<?php esc_html_e('Create comprehensive product descriptions that highlight features, benefits, and specifications. Perfect for detailed product pages that convert visitors into customers.', 'rfs-ai-seo'); ?>
			</p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-description-bulk')); ?>" class="button button-primary">
				<?php esc_html_e('Open Description Generator', 'rfs-ai-seo'); ?>
			</a>
		</div>

		<!-- Short Description Generator -->
		<div class="rfs-feature-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px;">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 24px;">✏️</span>
				<?php esc_html_e('Short Description Generator', 'rfs-ai-seo'); ?>
			</h3>
			<p style="color: #50575e; line-height: 1.6;">
				<?php esc_html_e('Generate concise 20-50 word product summaries for catalog listings and quick overviews. Capture attention with punchy, compelling short descriptions.', 'rfs-ai-seo'); ?>
			</p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-short-description-bulk')); ?>" class="button button-primary">
				<?php esc_html_e('Open Short Description Generator', 'rfs-ai-seo'); ?>
			</a>
		</div>

		<!-- AI Trainer -->
		<div class="rfs-feature-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px;">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 24px;">🎓</span>
				<?php esc_html_e('AI Trainer', 'rfs-ai-seo'); ?>
			</h3>
			<p style="color: #50575e; line-height: 1.6;">
				<?php esc_html_e('Teach the AI about your business, brand voice, target audience, and unique selling points. The more context you provide, the better your AI-generated content will be.', 'rfs-ai-seo'); ?>
			</p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-trainer')); ?>" class="button button-secondary">
				<?php esc_html_e('Open AI Trainer', 'rfs-ai-seo'); ?>
			</a>
		</div>

		<!-- Usage Statistics -->
		<div class="rfs-feature-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px;">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 24px;">📈</span>
				<?php esc_html_e('Usage Statistics', 'rfs-ai-seo'); ?>
			</h3>
			<p style="color: #50575e; line-height: 1.6;">
				<?php esc_html_e('Track your AI usage, monitor token consumption, and view estimated costs. Export detailed reports for analysis and budgeting.', 'rfs-ai-seo'); ?>
			</p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-stats')); ?>" class="button button-secondary">
				<?php esc_html_e('View Statistics', 'rfs-ai-seo'); ?>
			</a>
		</div>

		<!-- Settings -->
		<div class="rfs-feature-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 25px;">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 24px;">⚙️</span>
				<?php esc_html_e('Settings', 'rfs-ai-seo'); ?>
			</h3>
			<p style="color: #50575e; line-height: 1.6;">
				<?php esc_html_e('Configure your OpenAI API key, select AI models, customize prompt templates, and manage content generation settings for optimal results.', 'rfs-ai-seo'); ?>
			</p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=rfs-ai-settings')); ?>" class="button button-secondary">
				<?php esc_html_e('Open Settings', 'rfs-ai-seo'); ?>
			</a>
		</div>

	</div>

	<!-- Quick Start Guide -->
	<div class="rfs-quick-start" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 20px; margin: 20px 0; border-radius: 3px;">
		<h3 style="margin-top: 0;"><?php esc_html_e('🚀 Quick Start Guide', 'rfs-ai-seo'); ?></h3>
		<ol style="line-height: 1.8;">
			<li>
				<strong><?php esc_html_e('Configure API Key:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Go to Settings and enter your OpenAI API key', 'rfs-ai-seo'); ?>
			</li>
			<li>
				<strong><?php esc_html_e('Train the AI:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Use AI Trainer to teach the AI about your business and brand', 'rfs-ai-seo'); ?>
			</li>
			<li>
				<strong><?php esc_html_e('Generate Content:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Use any of the generators to create SEO meta, descriptions, or short summaries', 'rfs-ai-seo'); ?>
			</li>
			<li>
				<strong><?php esc_html_e('Monitor Usage:', 'rfs-ai-seo'); ?></strong>
				<?php esc_html_e('Check Usage Statistics to track your AI consumption and costs', 'rfs-ai-seo'); ?>
			</li>
		</ol>
	</div>
</div>
