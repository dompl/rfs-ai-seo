<?php
/**
 * Credits Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

$settings = new \RFS_AI_SEO\Settings();
$balance = \RFS_AI_SEO\Credits_Manager::get_balance();
$packages = \RFS_AI_SEO\Credits_Manager::get_packages();
$history = \RFS_AI_SEO\Credits_Manager::get_purchase_history(20);
$stripe_configured = !empty($settings->get_stripe_secret_key());
?>

<div class="wrap">
	<h1><?php esc_html_e('Buy Credits', 'rfs-ai-seo'); ?></h1>

	<?php settings_errors('rfs_ai_seo_messages'); ?>

	<!-- Current Balance -->
	<div class="rfs-credits-balance">
		<div class="rfs-credits-balance-inner">
			<span class="rfs-credits-balance-label"><?php esc_html_e('Current Balance', 'rfs-ai-seo'); ?></span>
			<span class="rfs-credits-balance-amount <?php echo $balance < 1.00 ? 'rfs-credits-low' : ''; ?>">
				<?php echo esc_html("\xC2\xA3" . number_format($balance, 2)); ?>
			</span>
			<?php if ($balance < 1.00): ?>
				<span class="rfs-credits-balance-warning">
					<?php esc_html_e('Low balance! Purchase credits to continue generating.', 'rfs-ai-seo'); ?>
				</span>
			<?php endif; ?>
		</div>
	</div>

	<?php if (!$stripe_configured): ?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						__('Stripe is not configured. Please add your Stripe keys in <a href="%s">Settings</a> to enable credit purchases.', 'rfs-ai-seo'),
						esc_url(admin_url('admin.php?page=rfs-ai-settings&tab=api'))
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Credit Packages -->
	<h2><?php esc_html_e('Credit Packages', 'rfs-ai-seo'); ?></h2>
	<div class="rfs-credits-packages">
		<?php foreach ($packages as $id => $package): ?>
			<div class="rfs-credit-package">
				<h3><?php echo esc_html($package['label']); ?></h3>
				<div class="rfs-credit-package-price">
					<?php echo esc_html("\xC2\xA3" . number_format($package['price_gbp'], 2)); ?>
				</div>
				<div class="rfs-credit-package-credits">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: credit amount */
							__('%s credits', 'rfs-ai-seo'),
							"\xC2\xA3" . number_format($package['credits_gbp'], 2)
						)
					);
					?>
				</div>
				<?php if ($package['bonus_pct'] > 0): ?>
					<span class="rfs-credits-bonus-badge">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: bonus percentage */
								__('%d%% bonus', 'rfs-ai-seo'),
								$package['bonus_pct']
							)
						);
						?>
					</span>
				<?php endif; ?>
				<button type="button"
						class="button button-primary rfs-credits-buy-btn"
						data-package="<?php echo esc_attr($id); ?>"
						<?php echo !$stripe_configured ? 'disabled' : ''; ?>>
					<?php esc_html_e('Buy Now', 'rfs-ai-seo'); ?>
				</button>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Purchase History -->
	<?php if (!empty($history)): ?>
		<h2><?php esc_html_e('Purchase History', 'rfs-ai-seo'); ?></h2>
		<table class="wp-list-table widefat fixed striped rfs-credits-history">
			<thead>
				<tr>
					<th><?php esc_html_e('Date', 'rfs-ai-seo'); ?></th>
					<th><?php esc_html_e('Package', 'rfs-ai-seo'); ?></th>
					<th><?php esc_html_e('Credits', 'rfs-ai-seo'); ?></th>
					<th><?php esc_html_e('Session ID', 'rfs-ai-seo'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($history as $purchase): ?>
					<tr>
						<td><?php echo esc_html($purchase['date']); ?></td>
						<td><?php echo esc_html($purchase['package_id']); ?></td>
						<td><?php echo esc_html("\xC2\xA3" . number_format($purchase['credits_gbp'], 2)); ?></td>
						<td><code><?php echo esc_html(substr($purchase['session_id'], 0, 24) . '...'); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
