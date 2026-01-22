/**
 * RFS AI Short Description Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const ShortDescriptionGenerator = {

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
			// Generate short description button
			$('#rfs_generate_short_description_btn').on('click', () => this.generateShortDescription());

			// Save short description button
			$('#rfs_save_short_description_btn').on('click', () => this.saveShortDescription());

			// Regenerate short description button
			$('#rfs_regenerate_short_description_btn').on('click', () => this.generateShortDescription());

			// Cancel short description button
			$('#rfs_cancel_short_description_btn').on('click', () => this.cancelShortDescription());

			// Restore original short description button
			$('#rfs_restore_short_description_btn').on('click', () => this.restoreOriginal());

			// Update word count on text change
			$('#rfs_short_description_preview_text').on('input', () => this.updateWordCount());
		},

		/**
		 * Generate product short description
		 */
		generateShortDescription() {
			const productId = $('#rfs_ai_seo_object_id').val();

			if (!productId) {
				alert('Product ID not found.');
				return;
			}

			// Show spinner
			$('#rfs_short_description_spinner').css('display', 'inline-block');
			$('#rfs_generate_short_description_btn').prop('disabled', true);

			// AJAX call
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_short_description',
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
					$('#rfs_short_description_spinner').hide();
					$('#rfs_generate_short_description_btn').prop('disabled', false);
				}
			});
		},

		/**
		 * Show short description preview
		 */
		showPreview(data) {
			// Set short description text
			$('#rfs_short_description_preview_text').val(data.description);

			// Show preview panel
			$('#rfs_short_description_preview').slideDown();

			// Update word count
			this.updateWordCount();

			// Show token usage
			if (data.tokens) {
				const tokenInfo = `Tokens used: ${data.tokens.total} (${data.tokens.prompt} prompt + ${data.tokens.completion} completion)`;
				$('#rfs_short_description_token_info').text(tokenInfo);
			}
		},

		/**
		 * Update word count
		 */
		updateWordCount() {
			const text = $('#rfs_short_description_preview_text').val();
			const words = text.trim().split(/\s+/).filter(word => word.length > 0).length;
			const chars = text.length;

			$('#rfs_short_description_word_count').text(`${words} words, ${chars} characters`);
		},

		/**
		 * Save short description
		 */
		saveShortDescription() {
			const productId = $('#rfs_ai_seo_object_id').val();
			const description = $('#rfs_short_description_preview_text').val();

			if (!productId || !description) {
				alert('Missing required data.');
				return;
			}

			// Show spinner
			$('#rfs_save_short_description_spinner').css('display', 'inline-block');
			$('#rfs_save_short_description_btn').prop('disabled', true);

			// AJAX call
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_save_short_description',
					nonce: rfsAiSeo.nonce,
					product_id: productId,
					description: description
				},
				success: (response) => {
					if (response.success) {
						alert('Short description saved successfully! Please refresh the page to see the updated status.');
						this.cancelShortDescription();
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
					$('#rfs_save_short_description_spinner').hide();
					$('#rfs_save_short_description_btn').prop('disabled', false);
				}
			});
		},

		/**
		 * Cancel short description preview
		 */
		cancelShortDescription() {
			$('#rfs_short_description_preview').slideUp();
			$('#rfs_short_description_preview_text').val('');
			$('#rfs_short_description_token_info').text('');
		},

		/**
		 * Restore original short description
		 */
		restoreOriginal() {
			if (!confirm('This will restore the original product short description. The current short description will be replaced. Continue?')) {
				return;
			}

			const productId = $('#rfs_ai_seo_object_id').val();

			if (!productId) {
				alert('Product ID not found.');
				return;
			}

			// Show spinner on restore button
			const $btn = $('#rfs_restore_short_description_btn');
			$btn.prop('disabled', true).text('Restoring...');

			// AJAX call
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_restore_short_description',
					nonce: rfsAiSeo.nonce,
					product_id: productId
				},
				success: (response) => {
					if (response.success) {
						alert('Original short description restored successfully! Please refresh the page.');
						if (confirm('Reload the page now?')) {
							location.reload();
						}
					} else {
						alert(response.data.message || 'Failed to restore original short description.');
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
	$(document).ready(() => ShortDescriptionGenerator.init());

})(jQuery);
