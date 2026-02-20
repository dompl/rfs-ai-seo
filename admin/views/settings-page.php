<?php
/**
 * Settings Page Template
 *
 * @package RFS_AI_SEO
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

$settings               = new \RFS_AI_SEO\Settings();
$api_key                = $settings->get_api_key();
$is_api_key_constant    = $settings->is_api_key_constant();
$model                  = $settings->get_model();
$gemini_key             = $settings->get_gemini_api_key();
$is_gemini_key_constant = $settings->is_gemini_key_constant();
$image_model            = $settings->get_image_model();
$image_aspect_ratio     = $settings->get_image_aspect_ratio();
$title_max              = $settings->get_title_max_length();
$desc_max               = $settings->get_description_max_length();
$acf_fields             = $settings->get_acf_fields();
$prompt_product         = $settings->get_prompt( 'product' );
$prompt_variation       = $settings->get_prompt( 'variation' );
$prompt_category        = $settings->get_prompt( 'category' );
$image_prompts          = $settings->get_image_prompts();
$stripe_secret          = $settings->get_stripe_secret_key();
$stripe_pub_key         = $settings->get_stripe_publishable_key();
$is_stripe_secret_constant = $settings->is_stripe_secret_constant();
$is_stripe_pub_constant    = $settings->is_stripe_publishable_constant();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Plugin Description -->
	<div class="rfs-page-header">
		<h2>
			<?php esc_html_e( 'AI-Powered SEO Meta Generation', 'rfs-ai-seo' ); ?>
		</h2>
		<p>
			<?php esc_html_e( 'Automatically generate compelling SEO meta titles and descriptions for your WooCommerce products and categories using OpenAI. Save time and improve your search engine rankings with AI-powered content optimization.', 'rfs-ai-seo' ); ?>
		</p>
		<div class="rfs-page-header-meta">
			<div>
				<strong><?php esc_html_e( 'Developed by:', 'rfs-ai-seo' ); ?></strong>
				<a href="https://redfrogstudio.co.uk" target="_blank">Red Frog Studio</a>
			</div>
			<div>
				<strong><?php esc_html_e( 'License:', 'rfs-ai-seo' ); ?></strong>
				<?php esc_html_e( 'Proprietary - Licensed to Red Frog Studio', 'rfs-ai-seo' ); ?>
			</div>
			<div>
				<strong><?php esc_html_e( 'Version:', 'rfs-ai-seo' ); ?></strong>
				<?php echo esc_html( RFS_AI_SEO_VERSION ); ?>
			</div>
		</div>
	</div>

	<h2 class="nav-tab-wrapper">
		<a href="?page=rfs-ai-settings&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'API Settings', 'rfs-ai-seo' ); ?>
		</a>
		<a href="?page=rfs-ai-settings&tab=prompts" class="nav-tab <?php echo $active_tab === 'prompts' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Prompt Templates', 'rfs-ai-seo' ); ?>
		</a>
		<a href="?page=rfs-ai-settings&tab=content" class="nav-tab <?php echo $active_tab === 'content' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Content Settings', 'rfs-ai-seo' ); ?>
		</a>
	</h2>

	<form method="post" action="">
		<?php wp_nonce_field( 'rfs_ai_seo_settings', 'rfs_ai_seo_settings_nonce' ); ?>

		<?php if ( $active_tab === 'api' ): ?>
			<!-- API Settings Tab -->
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_openai_api_key">
							<?php esc_html_e( 'OpenAI API Key', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<?php if ( $is_api_key_constant ): ?>
							<input type="text"
								   id="rfs_ai_seo_openai_api_key"
								   value="<?php echo esc_attr( substr( $api_key, 0, 20 ) . '••••••••••••••••••••' ); ?>"
								   class="regular-text"
								   disabled
								   readonly>
							<p class="description" style="color: #00a32a;">
								<strong>✓ <?php esc_html_e( 'API key is defined in wp-config.php', 'rfs-ai-seo' ); ?></strong><br>
								<?php esc_html_e( 'The API key is set via the RFS_AI_SEO_OPEN_AI_KEY constant. To change it, edit your wp-config.php file.', 'rfs-ai-seo' ); ?>
							</p>
						<?php else: ?>
							<input type="password"
								   name="rfs_ai_seo_openai_api_key"
								   id="rfs_ai_seo_openai_api_key"
								   value=""
								   placeholder="<?php echo !empty( $api_key ) ? esc_attr( '••••••••••••••••••••' ) : esc_attr( 'Enter your OpenAI API key' ); ?>"
								   class="regular-text">
							<?php if ( !empty( $api_key ) ): ?>
								<p class="description" style="color: #00a32a;">
									<?php esc_html_e( 'API key is set. Leave blank to keep current key, or enter a new key to replace it.', 'rfs-ai-seo' ); ?>
								</p>
							<?php endif; ?>
							<p class="description">
								<?php
echo wp_kses_post(
    sprintf(
        /* translators: %s: OpenAI platform URL */
        __( 'Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>. For better security, define it in wp-config.php as RFS_AI_SEO_OPEN_AI_KEY constant.', 'rfs-ai-seo' ),
        'https://platform.openai.com/api-keys'
    )
);
?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_openai_model">
							<?php esc_html_e( 'Model', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<select name="rfs_ai_seo_openai_model" id="rfs_ai_seo_openai_model">
							<option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>>
								<?php esc_html_e( 'GPT-4o', 'rfs-ai-seo' ); ?>
							</option>
							<option value="gpt-4o-mini" <?php selected( $model, 'gpt-4o-mini' ); ?>>
								<?php esc_html_e( 'GPT-4o-mini', 'rfs-ai-seo' ); ?>
							</option>
							<option value="nano-banana" <?php selected( $model, 'nano-banana' ); ?>>
								<?php esc_html_e( 'Nano Banana', 'rfs-ai-seo' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'GPT-4o: More capable, higher cost. GPT-4o-mini: Faster, lower cost. Nano Banana: Custom model.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<button type="button" id="rfs_ai_seo_test_connection" class="button button-secondary">
							<?php esc_html_e( 'Test Connection', 'rfs-ai-seo' ); ?>
						</button>
						<span class="spinner" id="rfs_ai_seo_test_spinner" style="float: none; margin: 0 10px;"></span>
						<div id="rfs_ai_seo_test_result" style="margin-top: 10px;"></div>
					</td>
				</tr>
			</table>

			<hr>
			<h2><?php esc_html_e( 'Image Generation (Nano Banana / Gemini)', 'rfs-ai-seo' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_gemini_api_key">
							<?php esc_html_e( 'Gemini API Key', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<?php if ( $is_gemini_key_constant ): ?>
							<input type="text"
								   id="rfs_ai_seo_gemini_api_key"
								   value="<?php echo esc_attr( substr( $gemini_key, 0, 15 ) . '••••••••••••••••••••' ); ?>"
								   class="regular-text"
								   disabled
								   readonly>
							<p class="description" style="color: #00a32a;">
								<strong><?php echo esc_html( "\xE2\x9C\x93" ); ?> <?php esc_html_e( 'Gemini API key is defined in wp-config.php', 'rfs-ai-seo' ); ?></strong><br>
								<?php esc_html_e( 'The key is set via the RFS_AI_SEO_GEMINI_KEY constant. To change it, edit your wp-config.php file.', 'rfs-ai-seo' ); ?>
							</p>
						<?php else: ?>
							<input type="password"
								   name="rfs_ai_seo_gemini_api_key"
								   id="rfs_ai_seo_gemini_api_key"
								   value=""
								   placeholder="<?php echo !empty( $gemini_key ) ? esc_attr( '••••••••••••••••••••' ) : esc_attr( 'Enter your Gemini API key' ); ?>"
								   class="regular-text">
							<?php if ( !empty( $gemini_key ) ): ?>
								<p class="description" style="color: #00a32a;">
									<?php esc_html_e( 'Gemini API key is set. Leave blank to keep current key, or enter a new key to replace it.', 'rfs-ai-seo' ); ?>
								</p>
							<?php endif; ?>
							<p class="description">
								<?php
								echo wp_kses_post(
									sprintf(
										__( 'Get your API key from <a href="%s" target="_blank">Google AI Studio</a>. For better security, define it in wp-config.php as <code>RFS_AI_SEO_GEMINI_KEY</code> constant.', 'rfs-ai-seo' ),
										'https://aistudio.google.com/apikey'
									)
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_image_model">
							<?php esc_html_e( 'Image Model', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<select name="rfs_ai_seo_image_model" id="rfs_ai_seo_image_model">
							<option value="gemini-2.5-flash-image" <?php selected( $image_model, 'gemini-2.5-flash-image' ); ?>>
								<?php esc_html_e( 'Nano Banana (Gemini 2.5 Flash)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="gemini-3-pro-image-preview" <?php selected( $image_model, 'gemini-3-pro-image-preview' ); ?>>
								<?php esc_html_e( 'Nano Banana Pro (Gemini 3 Pro)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="gemini-2.0-flash-exp" <?php selected( $image_model, 'gemini-2.0-flash-exp' ); ?>>
								<?php esc_html_e( 'Gemini 2.0 Flash (Experimental)', 'rfs-ai-seo' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Nano Banana: Fast, efficient. Nano Banana Pro: Higher quality, advanced reasoning, text rendering. Gemini 2.0 Flash: Legacy option.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_image_aspect_ratio">
							<?php esc_html_e( 'Default Aspect Ratio', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<select name="rfs_ai_seo_image_aspect_ratio" id="rfs_ai_seo_image_aspect_ratio">
							<option value="1:1" <?php selected( $image_aspect_ratio, '1:1' ); ?>>
								<?php esc_html_e( '1:1 (Square)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="3:4" <?php selected( $image_aspect_ratio, '3:4' ); ?>>
								<?php esc_html_e( '3:4 (Portrait)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="4:3" <?php selected( $image_aspect_ratio, '4:3' ); ?>>
								<?php esc_html_e( '4:3 (Landscape)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="16:9" <?php selected( $image_aspect_ratio, '16:9' ); ?>>
								<?php esc_html_e( '16:9 (Widescreen)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="9:16" <?php selected( $image_aspect_ratio, '9:16' ); ?>>
								<?php esc_html_e( '9:16 (Tall)', 'rfs-ai-seo' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Default aspect ratio. You can override this per image on the attachment edit screen.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<hr>
			<h2><?php esc_html_e( 'Stripe Payment Settings', 'rfs-ai-seo' ); ?></h2>
			<p class="description" style="margin-bottom: 10px;">
				<?php esc_html_e( 'Configure Stripe keys to enable credit purchases. Credits are required for AI generation.', 'rfs-ai-seo' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_stripe_secret_key">
							<?php esc_html_e( 'Stripe Secret Key', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<?php if ( $is_stripe_secret_constant ): ?>
							<input type="text"
								   id="rfs_ai_seo_stripe_secret_key"
								   value="<?php echo esc_attr( substr( $stripe_secret, 0, 12 ) . '••••••••••••••••••••' ); ?>"
								   class="regular-text"
								   disabled
								   readonly>
							<p class="description" style="color: #00a32a;">
								<strong><?php echo esc_html( "\xE2\x9C\x93" ); ?> <?php esc_html_e( 'Stripe secret key is defined in wp-config.php', 'rfs-ai-seo' ); ?></strong><br>
								<?php esc_html_e( 'The key is set via the RFS_AI_SEO_STRIPE_SECRET_KEY constant.', 'rfs-ai-seo' ); ?>
							</p>
						<?php else: ?>
							<input type="password"
								   name="rfs_ai_seo_stripe_secret_key"
								   id="rfs_ai_seo_stripe_secret_key"
								   value=""
								   placeholder="<?php echo !empty( $stripe_secret ) ? esc_attr( '••••••••••••••••••••' ) : esc_attr( 'sk_live_...' ); ?>"
								   class="regular-text">
							<?php if ( !empty( $stripe_secret ) ): ?>
								<p class="description" style="color: #00a32a;">
									<?php esc_html_e( 'Stripe secret key is set. Leave blank to keep current key, or enter a new key to replace it.', 'rfs-ai-seo' ); ?>
								</p>
							<?php endif; ?>
							<p class="description">
								<?php esc_html_e( 'Your Stripe secret key (sk_live_... or sk_test_...). For better security, define it in wp-config.php as RFS_AI_SEO_STRIPE_SECRET_KEY constant.', 'rfs-ai-seo' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_stripe_publishable_key">
							<?php esc_html_e( 'Stripe Publishable Key', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<?php if ( $is_stripe_pub_constant ): ?>
							<input type="text"
								   id="rfs_ai_seo_stripe_publishable_key"
								   value="<?php echo esc_attr( substr( $stripe_pub_key, 0, 12 ) . '••••••••••••••••••••' ); ?>"
								   class="regular-text"
								   disabled
								   readonly>
							<p class="description" style="color: #00a32a;">
								<strong><?php echo esc_html( "\xE2\x9C\x93" ); ?> <?php esc_html_e( 'Stripe publishable key is defined in wp-config.php', 'rfs-ai-seo' ); ?></strong><br>
								<?php esc_html_e( 'The key is set via the RFS_AI_SEO_STRIPE_PUBLISHABLE_KEY constant.', 'rfs-ai-seo' ); ?>
							</p>
						<?php else: ?>
							<input type="text"
								   name="rfs_ai_seo_stripe_publishable_key"
								   id="rfs_ai_seo_stripe_publishable_key"
								   value="<?php echo esc_attr( $stripe_pub_key ); ?>"
								   placeholder="pk_live_..."
								   class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Your Stripe publishable key (pk_live_... or pk_test_...). This is safe to display as it is a public key.', 'rfs-ai-seo' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<button type="button" id="rfs-test-stripe" class="button button-secondary">
							<?php esc_html_e( 'Verify Stripe Account', 'rfs-ai-seo' ); ?>
						</button>
						<span class="spinner" id="rfs-test-stripe-spinner" style="float: none; margin: 0 10px;"></span>
						<div id="rfs-test-stripe-result" style="margin-top: 10px;"></div>
					</td>
				</tr>
			</table>

			<hr>
			<h2><?php esc_html_e( 'Display Settings', 'rfs-ai-seo' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_display_currency">
							<?php esc_html_e( 'Display Currency', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<?php $display_currency = $settings->get_display_currency(); ?>
						<select name="rfs_ai_seo_display_currency" id="rfs_ai_seo_display_currency">
							<option value="USD" <?php selected( $display_currency, 'USD' ); ?>>
								<?php esc_html_e( 'USD ($)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="GBP" <?php selected( $display_currency, 'GBP' ); ?>>
								<?php esc_html_e( 'GBP (£)', 'rfs-ai-seo' ); ?>
							</option>
							<option value="EUR" <?php selected( $display_currency, 'EUR' ); ?>>
								<?php esc_html_e( 'EUR (€)', 'rfs-ai-seo' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Currency for displaying costs. Rates are fetched daily from the ECB. All API costs are billed in USD by providers; this only affects the display.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
			</table>

		<?php elseif ( $active_tab === 'prompts' ): ?>
			<!-- Prompt Templates Tab -->
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_prompt_product">
							<?php esc_html_e( 'Product Prompt', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<textarea name="rfs_ai_seo_prompt_product"
								  id="rfs_ai_seo_prompt_product"
								  rows="10"
								  class="large-text code"><?php echo esc_textarea( $prompt_product ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Available variables: {product_name}, {product_description}, {short_description}, {price}, {categories}, {attributes}, {acf_all}', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_prompt_variation">
							<?php esc_html_e( 'Variation Prompt', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<textarea name="rfs_ai_seo_prompt_variation"
								  id="rfs_ai_seo_prompt_variation"
								  rows="10"
								  class="large-text code"><?php echo esc_textarea( $prompt_variation ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Available variables: {variation_attributes}, {variation_description}, {variation_price}, {variation_sku}', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_prompt_category">
							<?php esc_html_e( 'Category Prompt', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<textarea name="rfs_ai_seo_prompt_category"
								  id="rfs_ai_seo_prompt_category"
								  rows="10"
								  class="large-text code"><?php echo esc_textarea( $prompt_category ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Available variables: {category_name}, {category_description}, {parent_category}, {product_count}', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<hr>
			<h2><?php esc_html_e( 'Image Prompt Templates', 'rfs-ai-seo' ); ?></h2>
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Define reusable prompt templates for AI image generation. These will appear as a dropdown on each image in the media library.', 'rfs-ai-seo' ); ?>
			</p>

			<div id="rfs-ai-image-prompts-repeater">
				<?php if ( !empty( $image_prompts ) ): ?>
					<?php foreach ( $image_prompts as $index => $prompt ): ?>
						<div class="rfs-ai-image-prompt-row" style="background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; margin-bottom: 10px; position: relative;">
							<button type="button" class="button rfs-ai-remove-image-prompt" style="position: absolute; top: 10px; right: 10px;" title="<?php esc_attr_e( 'Remove', 'rfs-ai-seo' ); ?>">
								<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
							</button>
							<p style="margin-top: 0;">
								<label><strong><?php esc_html_e( 'Title', 'rfs-ai-seo' ); ?></strong></label><br>
								<input type="text"
									   name="rfs_ai_seo_image_prompts_title[]"
									   value="<?php echo esc_attr( $prompt['title'] ?? '' ); ?>"
									   class="regular-text"
									   placeholder="<?php esc_attr_e( 'e.g. Product on white background', 'rfs-ai-seo' ); ?>"
									   style="width: 100%;">
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Prompt Description', 'rfs-ai-seo' ); ?></strong></label><br>
								<textarea name="rfs_ai_seo_image_prompts_description[]"
										  rows="4"
										  class="large-text"
										  placeholder="<?php esc_attr_e( 'Describe the image style, composition, and details...', 'rfs-ai-seo' ); ?>"
										  style="width: 100%;"><?php echo esc_textarea( $prompt['description'] ?? '' ); ?></textarea>
							</p>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<button type="button" class="button" id="rfs-ai-add-image-prompt">
				<?php esc_html_e( '+ Add Image Prompt Template', 'rfs-ai-seo' ); ?>
			</button>

			<script>
			jQuery(document).ready(function($) {
				$('#rfs-ai-add-image-prompt').on('click', function() {
					var html = '<div class="rfs-ai-image-prompt-row" style="background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; margin-bottom: 10px; position: relative;">' +
						'<button type="button" class="button rfs-ai-remove-image-prompt" style="position: absolute; top: 10px; right: 10px;" title="<?php esc_attr_e( 'Remove', 'rfs-ai-seo' ); ?>">' +
						'<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span></button>' +
						'<p style="margin-top: 0;"><label><strong><?php esc_html_e( 'Title', 'rfs-ai-seo' ); ?></strong></label><br>' +
						'<input type="text" name="rfs_ai_seo_image_prompts_title[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Product on white background', 'rfs-ai-seo' ); ?>" style="width: 100%;"></p>' +
						'<p><label><strong><?php esc_html_e( 'Prompt Description', 'rfs-ai-seo' ); ?></strong></label><br>' +
						'<textarea name="rfs_ai_seo_image_prompts_description[]" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Describe the image style, composition, and details...', 'rfs-ai-seo' ); ?>" style="width: 100%;"></textarea></p>' +
						'</div>';
					$('#rfs-ai-image-prompts-repeater').append(html);
				});

				$(document).on('click', '.rfs-ai-remove-image-prompt', function() {
					$(this).closest('.rfs-ai-image-prompt-row').remove();
				});
			});
			</script>

		<?php elseif ( $active_tab === 'content' ): ?>
			<!-- Content Settings Tab -->
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_company_name">
							<?php esc_html_e( 'Company Name', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<input type="text"
							   name="rfs_ai_seo_company_name"
							   id="rfs_ai_seo_company_name"
							   value="<?php echo esc_attr( get_option( 'rfs_ai_seo_company_name', '' ) ); ?>"
							   class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Used in alt text generation to include your brand name.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_title_max_length">
							<?php esc_html_e( 'Maximum Title Length', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
							   name="rfs_ai_seo_title_max_length"
							   id="rfs_ai_seo_title_max_length"
							   value="<?php echo esc_attr( $title_max ); ?>"
							   min="30"
							   max="100"
							   class="small-text">
						<span><?php esc_html_e( 'characters', 'rfs-ai-seo' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Recommended: 50-60 characters for optimal display in search results.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rfs_ai_seo_description_max_length">
							<?php esc_html_e( 'Maximum Description Length', 'rfs-ai-seo' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
							   name="rfs_ai_seo_description_max_length"
							   id="rfs_ai_seo_description_max_length"
							   value="<?php echo esc_attr( $desc_max ); ?>"
							   min="100"
							   max="200"
							   class="small-text">
						<span><?php esc_html_e( 'characters', 'rfs-ai-seo' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Recommended: 150-160 characters for optimal display in search results.', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'ACF Fields to Include', 'rfs-ai-seo' ); ?>
					</th>
					<td>
						<div class="rfs-ai-seo-acf-fields">
							<?php if ( !empty( $acf_fields ) ): ?>
								<?php foreach ( $acf_fields as $index => $field ): ?>
									<div class="rfs-ai-seo-acf-field-row">
										<input type="text"
											   name="rfs_ai_seo_acf_fields[]"
											   value="<?php echo esc_attr( $field ); ?>"
											   class="regular-text"
											   placeholder="<?php esc_attr_e( 'field_name or nested.field', 'rfs-ai-seo' ); ?>">
										<button type="button" class="button rfs-ai-seo-remove-field">
											<?php esc_html_e( 'Remove', 'rfs-ai-seo' ); ?>
										</button>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="button rfs-ai-seo-add-field">
							<?php esc_html_e( '+ Add Field', 'rfs-ai-seo' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Use dot notation for nested fields (e.g., "flex_field.layout.sub_field").', 'rfs-ai-seo' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<script>
			jQuery(document).ready(function($) {
				// Add field button
				$('.rfs-ai-seo-add-field').on('click', function() {
					var html = '<div class="rfs-ai-seo-acf-field-row">' +
						'<input type="text" name="rfs_ai_seo_acf_fields[]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'field_name or nested.field', 'rfs-ai-seo' ); ?>">' +
						'<button type="button" class="button rfs-ai-seo-remove-field"><?php esc_html_e( 'Remove', 'rfs-ai-seo' ); ?></button>' +
						'</div>';
					$('.rfs-ai-seo-acf-fields').append(html);
				});

				// Remove field button
				$(document).on('click', '.rfs-ai-seo-remove-field', function() {
					$(this).closest('.rfs-ai-seo-acf-field-row').remove();
				});
			});
			</script>

		<?php endif; ?>

		<?php submit_button(); ?>
	</form>
</div>
