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
$model                  = $settings->get_model();
$title_max              = $settings->get_title_max_length();
$desc_max               = $settings->get_description_max_length();
$acf_fields             = $settings->get_acf_fields();
$prompt_product         = $settings->get_prompt( 'product' );
$prompt_variation       = $settings->get_prompt( 'variation' );
$prompt_category        = $settings->get_prompt( 'category' );
$image_prompts          = $settings->get_image_prompts();
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
						</select>
						<p class="description">
							<?php esc_html_e( 'GPT-4o: More capable, higher cost. GPT-4o-mini: Faster, lower cost.', 'rfs-ai-seo' ); ?>
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
			<h2><?php esc_html_e( 'Image generation', 'rfs-ai-seo' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Product images are generated with OpenAI gpt-image-1 through the Red Frog AI portal. Each image is charged to the AI Credits balance.', 'rfs-ai-seo' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="rfs_ai_seo_image_quality"><?php esc_html_e( 'Quality', 'rfs-ai-seo' ); ?></label></th>
					<td>
						<select name="rfs_ai_seo_image_quality" id="rfs_ai_seo_image_quality">
							<?php foreach ( [ 'low' => __( 'Low (cheapest)', 'rfs-ai-seo' ), 'medium' => __( 'Medium', 'rfs-ai-seo' ), 'high' => __( 'High (most detail)', 'rfs-ai-seo' ) ] as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings->get_image_quality(), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rfs_ai_seo_image_size"><?php esc_html_e( 'Size', 'rfs-ai-seo' ); ?></label></th>
					<td>
						<select name="rfs_ai_seo_image_size" id="rfs_ai_seo_image_size">
							<?php foreach ( [ '1024x1024' => __( 'Square 1024 × 1024', 'rfs-ai-seo' ), '1536x1024' => __( 'Landscape 1536 × 1024', 'rfs-ai-seo' ), '1024x1536' => __( 'Portrait 1024 × 1536', 'rfs-ai-seo' ) ] as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings->get_image_size(), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
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

			<hr>
			<h2><?php esc_html_e( 'Content defaults', 'rfs-ai-seo' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Starting values for the description generators. Each bulk page and product box can override them per run.', 'rfs-ai-seo' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="rfs_ai_seo_company_name"><?php esc_html_e( 'Company name', 'rfs-ai-seo' ); ?></label></th>
					<td><input type="text" class="regular-text" name="rfs_ai_seo_company_name" id="rfs_ai_seo_company_name" value="<?php echo esc_attr( get_option( 'rfs_ai_seo_company_name', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="rfs_ai_seo_content_tone"><?php esc_html_e( 'Tone of voice', 'rfs-ai-seo' ); ?></label></th>
					<td>
						<select name="rfs_ai_seo_content_tone" id="rfs_ai_seo_content_tone">
							<?php foreach ( \RFS_AI_SEO\Settings::tones() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings->get_content_tone(), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rfs_ai_seo_description_words"><?php esc_html_e( 'Description length (words)', 'rfs-ai-seo' ); ?></label></th>
					<td><input type="number" min="50" max="1000" step="10" name="rfs_ai_seo_description_words" id="rfs_ai_seo_description_words" value="<?php echo esc_attr( (string) $settings->get_description_words() ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="rfs_ai_seo_short_sentences"><?php esc_html_e( 'Short description (sentences)', 'rfs-ai-seo' ); ?></label></th>
					<td><input type="number" min="1" max="6" name="rfs_ai_seo_short_sentences" id="rfs_ai_seo_short_sentences" value="<?php echo esc_attr( (string) $settings->get_short_sentences() ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Key features list', 'rfs-ai-seo' ); ?></th>
					<td><label><input type="checkbox" name="rfs_ai_seo_include_features" value="1" <?php checked( $settings->get_include_features() ); ?>> <?php esc_html_e( 'Include a bullet-point list of key features in descriptions', 'rfs-ai-seo' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Business context', 'rfs-ai-seo' ); ?></th>
					<td><p class="description"><?php printf( wp_kses_post( __( 'Set on the <a href="%s">AI Trainer</a> page. It is sent with every description request.', 'rfs-ai-seo' ) ), esc_url( admin_url( 'admin.php?page=rfs-ai-trainer' ) ) ); ?></p></td>
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
