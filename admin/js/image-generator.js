/**
 * RFS AI Image Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const ImageGenerator = {

		/**
		 * Initialize
		 */
		init() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Handle regenerate button click.
			$(document).on('click', '.rfs-ai-regenerate-image-btn', (e) => {
				e.preventDefault();
				const $btn = $(e.currentTarget);
				const attachmentId = $btn.data('attachment-id');
				this.regenerateImage(attachmentId, $btn);
			});
		},

		/**
		 * Regenerate image with AI
		 */
		regenerateImage(attachmentId, $btn) {
			if (!attachmentId) {
				alert('Invalid attachment ID.');
				return;
			}

			// Confirm action.
			if (!confirm('This will generate a new AI version of this image. The original will be preserved. Continue?')) {
				return;
			}

			// Get the aspect ratio from the per-image dropdown.
			const $ratioSelect = $('.rfs-ai-aspect-ratio-select[data-attachment-id="' + attachmentId + '"]');
			const aspectRatio = $ratioSelect.length ? $ratioSelect.val() : '1:1';

			// Get the prompt template selection.
			const $promptSelect = $('.rfs-ai-prompt-template-select[data-attachment-id="' + attachmentId + '"]');
			const promptTemplate = $promptSelect.length ? $promptSelect.val() : '';

			// Show spinner.
			const $container = $btn.closest('.rfs-ai-image-generator');
			const $spinner = $container.find('.spinner');
			const $result = $container.find('.rfs-ai-image-result');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.hide().html('');

			// AJAX call.
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_image',
					nonce: rfsAiSeo.nonce,
					attachment_id: attachmentId,
					aspect_ratio: aspectRatio,
					prompt_template: promptTemplate
				},
				success: (response) => {
					if (response.success) {
						this.showSuccess(response.data, $container);
					} else {
						alert(response.data.message || 'An error occurred. Please try again.');
					}
				},
				error: (xhr, status, error) => {
					console.error('Image generation error:', error);
					alert('An error occurred while generating the image. Please try again.');
				},
				complete: () => {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Show success message with image preview
		 */
		showSuccess(data, $container) {
			const $result = $container.find('.rfs-ai-image-result');
			const $preview = $container.find('.rfs-ai-image-preview');

			const costHtml = data.cost_formatted
				? '<p style="margin-top: 8px; font-size: 11px; color: #50575e;">Cost: <strong>' + data.cost_formatted + '</strong></p>'
				: '';

			const html = `
				<div style="margin-bottom: 10px;">
					<strong style="color: #46b450;">Image generated successfully!</strong>
				</div>
				<div style="margin-bottom: 10px;">
					<img src="${data.thumbnail_url}" alt="Generated Image" style="max-width: 100%; height: auto; border-radius: 4px;">
				</div>
				<div style="font-size: 12px; color: #666;">
					<p>
						<a href="post.php?post=${data.new_attachment_id}&action=edit" class="button button-small">
							View New Image
						</a>
						<a href="${data.new_attachment_url}" target="_blank" class="button button-small">
							Open Full Size
						</a>
					</p>
					${costHtml}
				</div>
			`;

			$preview.html(html);
			$result.slideDown();

			// Suggest page reload.
			setTimeout(() => {
				if (confirm('The image has been generated. Reload the page to see it in the media library?')) {
					location.reload();
				}
			}, 1000);
		}
	};

	// Initialize on document ready.
	$(document).ready(() => ImageGenerator.init());

})(jQuery);
