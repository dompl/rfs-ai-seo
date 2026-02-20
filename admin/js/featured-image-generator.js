/**
 * RFS AI Featured Image Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const FeaturedImageGenerator = {

		generatedAttachmentId: null,

		init() {
			this.bindEvents();
		},

		bindEvents() {
			$(document).on('click', '#rfs-ai-featured-generate-btn', (e) => {
				e.preventDefault();
				this.generate();
			});

			$(document).on('click', '#rfs-ai-featured-set-btn', (e) => {
				e.preventDefault();
				this.setAsFeatured();
			});

			$(document).on('click', '#rfs-ai-featured-discard-btn', (e) => {
				e.preventDefault();
				this.discard();
			});
		},

		generate() {
			const $container = $('#rfs-ai-featured-generator');
			const postId = $container.data('post-id');
			const promptTemplate = $('#rfs-ai-featured-prompt').val() || '';
			const customPrompt = $('#rfs-ai-featured-custom-prompt').val() || '';
			const aspectRatio = $('#rfs-ai-featured-ratio').val() || '1:1';

			if (!promptTemplate && !customPrompt) {
				this.showStatus('Please select a prompt template or enter a custom prompt.', 'error');
				return;
			}

			// Show progress, hide result and status.
			$('#rfs-ai-featured-generate-btn').prop('disabled', true);
			$('#rfs-ai-featured-progress').show();
			$('#rfs-ai-featured-result').hide();
			$('#rfs-ai-featured-status').hide();
			$('#rfs-ai-featured-cost').hide();

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_featured_image',
					nonce: rfsAiSeo.nonce,
					post_id: postId,
					prompt_template: promptTemplate,
					custom_prompt: customPrompt,
					aspect_ratio: aspectRatio
				},
				success: (response) => {
					if (response.success) {
						this.showResult(response.data);
					} else {
						this.showStatus(response.data.message || 'An error occurred.', 'error');
					}
				},
				error: () => {
					this.showStatus('An error occurred while generating the image.', 'error');
				},
				complete: () => {
					$('#rfs-ai-featured-generate-btn').prop('disabled', false);
					$('#rfs-ai-featured-progress').hide();
				}
			});
		},

		showResult(data) {
			this.generatedAttachmentId = data.attachment_id;
			$('#rfs-ai-featured-preview-img').attr('src', data.thumbnail_url);
			$('#rfs-ai-featured-result').show();
			$('#rfs-ai-featured-status').hide();

			// Show actual cost.
			if (data.cost_formatted) {
				$('#rfs-ai-featured-cost').html('Cost: <strong>' + data.cost_formatted + '</strong>').show();
			}
		},

		setAsFeatured() {
			if (!this.generatedAttachmentId) {
				return;
			}

			const $container = $('#rfs-ai-featured-generator');
			const postId = $container.data('post-id');

			$('#rfs-ai-featured-set-btn').prop('disabled', true);

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_set_featured_image',
					nonce: rfsAiSeo.nonce,
					post_id: postId,
					attachment_id: this.generatedAttachmentId
				},
				success: (response) => {
					if (response.success) {
						// Update the WordPress featured image box.
						if (response.data.thumbnail_html) {
							$('#postimagediv .inside').html(response.data.thumbnail_html);
						}

						// Also try WooCommerce product image box.
						if ($('#set-post-thumbnail').length === 0 && response.data.thumbnail_html) {
							$('#postimagediv .inside').html(response.data.thumbnail_html);
						}

						this.showStatus('Featured image set successfully.', 'success');
						$('#rfs-ai-featured-result').hide();
						this.generatedAttachmentId = null;
					} else {
						this.showStatus(response.data.message || 'Failed to set featured image.', 'error');
					}
				},
				error: () => {
					this.showStatus('An error occurred while setting the featured image.', 'error');
				},
				complete: () => {
					$('#rfs-ai-featured-set-btn').prop('disabled', false);
				}
			});
		},

		discard() {
			$('#rfs-ai-featured-result').hide();
			$('#rfs-ai-featured-status').hide();
			this.generatedAttachmentId = null;
		},

		showStatus(message, type) {
			const $status = $('#rfs-ai-featured-status');
			const color = type === 'error' ? '#d63638' : '#00a32a';
			$status.html('<p style="color: ' + color + '; margin: 0;">' + message + '</p>');
			$status.show();

			// Auto-hide success messages after 5 seconds.
			if (type === 'success') {
				setTimeout(() => {
					$status.fadeOut();
				}, 5000);
			}
		}
	};

	$(document).ready(() => FeaturedImageGenerator.init());

})(jQuery);
