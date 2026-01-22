/**
 * RFS AI Description Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const DescriptionGenerator = {

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
			// Generate description button
			$('#rfs_generate_description_btn').on('click', () => this.generateDescription());

			// Save description button
			$('#rfs_save_description_btn').on('click', () => this.saveDescription());

			// Regenerate description button
			$('#rfs_regenerate_description_btn').on('click', () => this.generateDescription());

			// Cancel description button
			$('#rfs_cancel_description_btn').on('click', () => this.cancelDescription());

			// Restore original description button
			$('#rfs_restore_description_btn').on('click', () => this.restoreOriginal());

			// Update word count on text change
			$('#rfs_description_preview_text').on('input', () => this.updateWordCount());
		},

		/**
		 * Generate product description
		 */
		generateDescription() {
			const productId = $('#rfs_ai_seo_object_id').val();

			if (!productId) {
				alert('Product ID not found.');
				return;
			}

			// Show spinner
			$('#rfs_description_spinner').css('display', 'inline-block');
			$('#rfs_generate_description_btn').prop('disabled', true);

			// AJAX call
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_description',
					nonce: rfsAiSeo.nonce,
					product_id: productId
				},
				success: (response) => {
					if (response.success) {
						this.showPreview(response.data);
					} else {
						alert(response.data.message || rfsAiSeo.strings.error);
					}
				},
				error: () => {
					alert(rfsAiSeo.strings.error);
				},
				complete: () => {
					$('#rfs_description_spinner').hide();
					$('#rfs_generate_description_btn').prop('disabled', false);
				}
			});
		},

		/**
		 * Show description preview
		 */
		showPreview(data) {
			// Set description text
			$('#rfs_description_preview_text').val(data.description);

			// Show preview panel
			$('#rfs_description_preview').slideDown();

			// Update word count
			this.updateWordCount();

			// Show token usage
			if (data.tokens) {
				const tokenInfo = `Tokens used: ${data.tokens.total} (${data.tokens.prompt} prompt + ${data.tokens.completion} completion)`;
				$('#rfs_description_token_info').text(tokenInfo);
			}
		},

		/**
		 * Update word count
		 */
		updateWordCount() {
			const text = $('#rfs_description_preview_text').val();
			const words = text.trim().split(/\s+/).filter(word => word.length > 0).length;
			const chars = text.length;

			$('#rfs_description_word_count').text(`${words} words, ${chars} characters`);
		},

		/**
		 * Save description
		 */
		saveDescription() {
			const productId = $('#rfs_ai_seo_object_id').val();
			const description = $('#rfs_description_preview_text').val();

			if (!productId || !description) {
				alert('Missing required data.');
				return;
			}

			// Confirm if current description exists
			const currentDesc = $('#rfs_description_preview').data('current-description');
			if (currentDesc && currentDesc.length > 0) {
				if (!confirm('This will replace the current product description. The original will be saved. Continue?')) {
					return;
				}
			}

			// Show spinner
			$('#rfs_save_description_spinner').css('display', 'inline-block');
			$('#rfs_save_description_btn').prop('disabled', true);

			// AJAX call
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_save_description',
					nonce: rfsAiSeo.nonce,
					product_id: productId,
					description: description
				},
				success: (response) => {
					if (response.success) {
						alert('Description saved successfully! Please refresh the page to see the updated status.');
						this.cancelDescription();
						// Suggest page reload
						if (confirm('Reload the page to see updated status?')) {
							location.reload();
						}
					} else {
						alert(response.data.message || rfsAiSeo.strings.error);
					}
				},
				error: () => {
					alert(rfsAiSeo.strings.error);
				},
				complete: () => {
					$('#rfs_save_description_spinner').hide();
					$('#rfs_save_description_btn').prop('disabled', false);
				}
			});
		},

		/**
		 * Cancel description preview
		 */
		cancelDescription() {
			$('#rfs_description_preview').slideUp();
			$('#rfs_description_preview_text').val('');
			$('#rfs_description_token_info').text('');
		},

		/**
		 * Restore original description
		 */
		restoreOriginal() {
			if (!confirm('This will restore the original product description. The current description will be replaced. Continue?')) {
				return;
			}

			const productId = $('#rfs_ai_seo_object_id').val();

			if (!productId) {
				alert('Product ID not found.');
				return;
			}

			// Show spinner on restore button
			const $btn = $('#rfs_restore_description_btn');
			$btn.prop('disabled', true).text('Restoring...');

			// AJAX call
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_restore_description',
					nonce: rfsAiSeo.nonce,
					product_id: productId
				},
				success: (response) => {
					if (response.success) {
						alert('Original description restored successfully! Please refresh the page.');
						if (confirm('Reload the page now?')) {
							location.reload();
						}
					} else {
						alert(response.data.message || 'Failed to restore original description.');
					}
				},
				error: () => {
					alert(rfsAiSeo.strings.error);
				},
				complete: () => {
					$btn.prop('disabled', false).text('Restore Original');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(() => DescriptionGenerator.init());

})(jQuery);
