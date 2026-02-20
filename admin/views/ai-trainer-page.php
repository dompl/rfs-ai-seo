<?php
/**
 * AI Trainer Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Get current business context.
$business_context = get_option('rfs_ai_trainer_business_context', '');
?>

<div class="wrap">
	<h1><?php esc_html_e('AI Trainer', 'rfs-ai-seo'); ?></h1>

	<div class="rfs-ai-trainer-container">
		<div class="rfs-trainer-intro">
			<p><?php esc_html_e('Train the AI by providing context about your business and products. This information will be used to generate more accurate and relevant product descriptions.', 'rfs-ai-seo'); ?></p>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field('rfs_ai_trainer_save', 'rfs_ai_trainer_nonce'); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="rfs_ai_trainer_business_context">
								<?php esc_html_e('Business Context', 'rfs-ai-seo'); ?>
							</label>
						</th>
						<td>
							<textarea
								name="rfs_ai_trainer_business_context"
								id="rfs_ai_trainer_business_context"
								rows="15"
								class="large-text code"
								placeholder="<?php esc_attr_e('Describe your business, products, target audience, brand voice, and any other relevant information...', 'rfs-ai-seo'); ?>"
							><?php echo esc_textarea($business_context); ?></textarea>
							<p class="description">
								<?php esc_html_e('This context will be included when generating product descriptions to ensure they align with your brand and business goals.', 'rfs-ai-seo'); ?>
							</p>
							<p class="description">
								<?php esc_html_e('Example: "We are a premium diamond tools manufacturer specializing in high-quality products for professionals. Our brand focuses on durability, precision, and innovation. Target audience: Professional contractors and industrial users. Tone: Professional, technical, and trustworthy."', 'rfs-ai-seo'); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(__('Save AI Trainer', 'rfs-ai-seo')); ?>
		</form>
	</div>
</div>
