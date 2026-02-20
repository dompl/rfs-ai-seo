/**
 * RFS AI Alt Text Generator (Meta Box)
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const AltTextGenerator = {
		productId: 0,
		isGeneratingAll: false,
		pendingQueue: [],
		currentIndex: 0,

		init() {
			this.productId = $('#rfs_ai_seo_object_id').val();
			if (!this.productId) {
				return;
			}
			this.bindEvents();
		},

		bindEvents() {
			// Single image generate button.
			$(document).on('click', '.rfs-alt-text-generate-btn', (e) => {
				const $row = $(e.target).closest('.rfs-alt-text-image-row');
				this.generateForRow($row);
			});

			// Generate all missing button.
			$('#rfs_generate_all_alt_text_btn').on('click', () => this.generateAllMissing());

			// Save button.
			$(document).on('click', '.rfs-alt-text-save-btn', (e) => {
				const $row = $(e.target).closest('.rfs-alt-text-image-row');
				this.saveAltText($row);
			});

			// Cancel button.
			$(document).on('click', '.rfs-alt-text-cancel-btn', (e) => {
				const $row = $(e.target).closest('.rfs-alt-text-image-row');
				this.cancelEdit($row);
			});
		},

		/**
		 * Generate alt text for a single image row.
		 */
		generateForRow($row) {
			const attachmentId = $row.data('attachment-id');
			const $btn = $row.find('.rfs-alt-text-generate-btn');
			const $spinner = $row.find('.rfs-alt-text-spinner');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_alt_text',
					nonce: rfsAiSeo.nonce,
					attachment_id: attachmentId,
					product_id: this.productId
				},
				success: (response) => {
					if (response.success) {
						this.showEditMode($row, response.data.alt_text);
					} else {
						alert(response.data.message || rfsAiSeo.strings.error);
					}
				},
				error: () => {
					alert(rfsAiSeo.strings.error);
				},
				complete: () => {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Show inline edit mode with generated alt text.
		 */
		showEditMode($row, altText) {
			$row.find('.rfs-alt-text-current').hide();
			$row.find('.rfs-alt-text-edit').show();
			$row.find('.rfs-alt-text-input').val(altText).focus();
		},

		/**
		 * Cancel inline edit.
		 */
		cancelEdit($row) {
			$row.find('.rfs-alt-text-edit').hide();
			$row.find('.rfs-alt-text-current').show();
		},

		/**
		 * Save alt text for a single image.
		 */
		saveAltText($row) {
			const attachmentId = $row.data('attachment-id');
			const altText = $row.find('.rfs-alt-text-input').val().trim();
			const $saveBtn = $row.find('.rfs-alt-text-save-btn');
			const $spinner = $row.find('.rfs-alt-text-save-spinner');

			if (!altText) {
				alert('Alt text cannot be empty.');
				return;
			}

			$saveBtn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_save_alt_text',
					nonce: rfsAiSeo.nonce,
					attachment_id: attachmentId,
					alt_text: altText
				},
				success: (response) => {
					if (response.success) {
						// Update the display.
						$row.find('.rfs-alt-text-current')
							.html('<span class="rfs-alt-text-value">' + $('<span>').text(altText).html() + '</span>');
						$row.find('.rfs-alt-text-edit').hide();
						$row.find('.rfs-alt-text-current').show();
						this.updateSummary();
					} else {
						alert(response.data.message || rfsAiSeo.strings.error);
					}
				},
				error: () => {
					alert(rfsAiSeo.strings.error);
				},
				complete: () => {
					$saveBtn.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Generate alt text for all images missing alt text.
		 */
		generateAllMissing() {
			const $rows = $('.rfs-alt-text-image-row').filter(function() {
				return $(this).find('.rfs-status-empty').length > 0;
			});

			if ($rows.length === 0) {
				return;
			}

			this.isGeneratingAll = true;
			this.pendingQueue = $rows.toArray();
			this.currentIndex = 0;

			$('#rfs_generate_all_alt_text_btn').prop('disabled', true);
			$('#rfs_all_alt_text_spinner').addClass('is-active');

			this.processNextInQueue();
		},

		/**
		 * Process next image in the "generate all" queue.
		 */
		processNextInQueue() {
			if (!this.isGeneratingAll || this.currentIndex >= this.pendingQueue.length) {
				this.isGeneratingAll = false;
				$('#rfs_generate_all_alt_text_btn').prop('disabled', false);
				$('#rfs_all_alt_text_spinner').removeClass('is-active');
				return;
			}

			const $row = $(this.pendingQueue[this.currentIndex]);
			const attachmentId = $row.data('attachment-id');
			const $spinner = $row.find('.rfs-alt-text-spinner');

			$spinner.addClass('is-active');

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_alt_text',
					nonce: rfsAiSeo.nonce,
					attachment_id: attachmentId,
					product_id: this.productId
				},
				success: (response) => {
					if (response.success) {
						// Auto-save immediately.
						this.autoSaveAltText($row, attachmentId, response.data.alt_text);
					}
				},
				complete: () => {
					$spinner.removeClass('is-active');
					this.currentIndex++;
					setTimeout(() => this.processNextInQueue(), 500);
				}
			});
		},

		/**
		 * Auto-save alt text (for generate all flow).
		 */
		autoSaveAltText($row, attachmentId, altText) {
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_save_alt_text',
					nonce: rfsAiSeo.nonce,
					attachment_id: attachmentId,
					alt_text: altText
				},
				success: (response) => {
					if (response.success) {
						$row.find('.rfs-alt-text-current')
							.html('<span class="rfs-alt-text-value">' + $('<span>').text(altText).html() + '</span>');
						$row.find('.rfs-alt-text-input').val(altText);
						this.updateSummary();
					}
				}
			});
		},

		/**
		 * Update the summary count after saving.
		 */
		updateSummary() {
			const total = $('.rfs-alt-text-image-row').length;
			const withAlt = $('.rfs-alt-text-image-row').filter(function() {
				return $(this).find('.rfs-alt-text-value').length > 0;
			}).length;

			$('.rfs-alt-text-summary p strong').text(withAlt + ' of ' + total + ' images have alt text');

			// Hide the generate all button if all done.
			if (withAlt >= total) {
				$('#rfs_generate_all_alt_text_btn').hide();
			}
		}
	};

	$(document).ready(() => AltTextGenerator.init());

})(jQuery);
