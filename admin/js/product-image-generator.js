/**
 * RFS AI Product Image Generator
 *
 * Handles image grid selection, sequential AI generation,
 * comparison panel, replace/skip flow, and completion summary.
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const ProductImageGenerator = {

		// State.
		selectedImages: [],
		currentQueue: [],
		currentIndex: 0,
		generatedAttachmentId: null,
		isRunning: false,
		results: { replaced: 0, skipped: 0, failed: 0 },
		totalCost: 0,

		init() {
			if ($('#rfs-product-image-generator').length === 0) {
				return;
			}
			this.bindEvents();
		},

		bindEvents() {
			// Image selection — click thumbnail to toggle.
			$(document).on('click', '.rfs-image-item', function(e) {
				e.preventDefault();
				$(this).toggleClass('selected');
				ProductImageGenerator.updateSelection();
			});
			$(document).on('click', '#rfs-select-all', (e) => {
				e.preventDefault();
				$('.rfs-image-item').addClass('selected');
				this.updateSelection();
			});
			$(document).on('click', '#rfs-deselect-all', (e) => {
				e.preventDefault();
				$('.rfs-image-item').removeClass('selected');
				this.updateSelection();
			});

			// Generate.
			$(document).on('click', '#rfs-product-generate-btn', (e) => {
				e.preventDefault();
				this.startGeneration();
			});

			// Stop.
			$(document).on('click', '#rfs-stop-btn', (e) => {
				e.preventDefault();
				this.stop();
			});

			// Replace / Skip.
			$(document).on('click', '#rfs-replace-btn', (e) => {
				e.preventDefault();
				this.replaceImage();
			});
			$(document).on('click', '#rfs-skip-btn', (e) => {
				e.preventDefault();
				this.skipImage();
			});

			// Generate More.
			$(document).on('click', '#rfs-generate-more-btn', (e) => {
				e.preventDefault();
				this.reset();
			});

			// Comparison tab switching.
			$(document).on('click', '.rfs-comparison-tab', function(e) {
				e.preventDefault();
				$('.rfs-comparison-tab').removeClass('active');
				$(this).addClass('active');
				var tab = $(this).data('tab');
				$('.rfs-comparison-img').removeClass('active');
				$('#rfs-comparison-' + tab).addClass('active');
			});
		},

		updateSelection() {
			this.selectedImages = [];
			$('.rfs-image-item.selected').each((_, el) => {
				const imageData = $(el).data('image');
				if (imageData) {
					this.selectedImages.push(imageData);
				}
			});

			// Update count and button state.
			const count = this.selectedImages.length;
			const label = count === 1 ? '1 image selected' : count + ' images selected';
			$('#rfs-selected-count').text(label);
			$('#rfs-product-generate-btn').prop('disabled', count === 0);

			// Update cost estimate.
			const $estimate = $('#rfs-cost-estimate');
			if ($estimate.length && count > 0) {
				const perImage = parseFloat($estimate.data('per-image')) || 0;
				const totalEstimate = (perImage * count).toFixed(4);
				var sym = (typeof rfsAiSeo !== 'undefined' && rfsAiSeo.currencySymbol) ? rfsAiSeo.currencySymbol : '$';
				$estimate.html('Est. cost: <strong>' + sym + totalEstimate + '</strong> (~' + sym + perImage.toFixed(4) + '/image)').show();
			} else {
				$estimate.hide();
			}
		},

		getPromptSettings() {
			return {
				prompt_template: $('#rfs-product-prompt').val() || '',
				custom_prompt: $('#rfs-product-custom-prompt').val() || '',
				aspect_ratio: $('#rfs-product-ratio').val() || '1:1'
			};
		},

		startGeneration() {
			const settings = this.getPromptSettings();

			if (!settings.prompt_template && !settings.custom_prompt) {
				alert('Please select a prompt template or enter a custom prompt.');
				return;
			}

			if (this.selectedImages.length === 0) {
				return;
			}

			// Copy selected images to queue.
			this.currentQueue = this.selectedImages.slice();
			this.currentIndex = 0;
			this.isRunning = true;
			this.results = { replaced: 0, skipped: 0, failed: 0 };
			this.totalCost = 0;

			// Show progress, hide grid and controls.
			$('.rfs-image-grid-header, .rfs-image-grid-container, #rfs-image-controls').hide();
			$('#rfs-progress-panel').show();
			$('#rfs-progress-log').empty();
			$('#rfs-comparison-panel').hide();
			$('#rfs-completion-panel').hide();

			this.processNext();
		},

		processNext() {
			if (!this.isRunning || this.currentIndex >= this.currentQueue.length) {
				this.complete();
				return;
			}

			const item = this.currentQueue[this.currentIndex];
			const total = this.currentQueue.length;
			const current = this.currentIndex + 1;
			const pct = Math.round((this.currentIndex / total) * 100);

			// Update progress.
			$('.rfs-product-progress-bar-fill').css('width', pct + '%');
			$('#rfs-progress-current').text('Processing ' + current + ' of ' + total + ': ' + item.label);

			// Show progress panel, hide comparison.
			$('#rfs-progress-panel').show();
			$('#rfs-comparison-panel').hide();

			const settings = this.getPromptSettings();

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_product_image',
					nonce: rfsAiSeo.nonce,
					attachment_id: item.id,
					prompt_template: settings.prompt_template,
					custom_prompt: settings.custom_prompt,
					aspect_ratio: settings.aspect_ratio
				},
				success: (response) => {
					if (response.success) {
						const costInfo = response.data.cost_formatted ? ' (' + response.data.cost_formatted + ')' : '';
						this.logEntry(item.label, 'success', 'Generated' + costInfo);
						this.generatedAttachmentId = response.data.new_attachment_id;
						this.showComparison(item, response.data);
					} else {
						this.logEntry(item.label, 'error', response.data.message || 'Generation failed');
						this.results.failed++;
						this.currentIndex++;
						this.processNext();
					}
				},
				error: () => {
					this.logEntry(item.label, 'error', 'Network error');
					this.results.failed++;
					this.currentIndex++;
					this.processNext();
				}
			});
		},

		showComparison(item, data) {
			const total = this.currentQueue.length;
			const current = this.currentIndex + 1;

			// Track cost.
			if (data.cost) {
				this.totalCost += parseFloat(data.cost);
			}

			// Hide progress, show comparison.
			$('#rfs-progress-panel').hide();
			$('#rfs-comparison-panel').show();

			$('#rfs-comparison-label').text(item.label);
			const costLabel = data.cost_formatted ? ' \u2014 ' + data.cost_formatted : '';
			$('#rfs-comparison-counter').text('(' + current + '/' + total + ')' + costLabel);
			$('#rfs-comparison-original').attr('src', data.original_url);
			$('#rfs-comparison-generated').attr('src', data.generated_url);

			// Show "Generated" tab by default.
			$('.rfs-comparison-tab').removeClass('active');
			$('.rfs-comparison-tab[data-tab="generated"]').addClass('active');
			$('.rfs-comparison-img').removeClass('active');
			$('#rfs-comparison-generated').addClass('active');

			// Store current item data for replace.
			$('#rfs-comparison-panel').data('current-item', item);
			$('#rfs-comparison-panel').data('new-attachment-id', data.new_attachment_id);
		},

		replaceImage() {
			const $panel = $('#rfs-comparison-panel');
			const item = $panel.data('current-item');
			const newAttachmentId = $panel.data('new-attachment-id');

			// Disable buttons during AJAX.
			$('#rfs-replace-btn, #rfs-skip-btn').prop('disabled', true);

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_replace_product_image',
					nonce: rfsAiSeo.nonce,
					image_type: item.type,
					product_id: item.product_id,
					old_attachment_id: item.id,
					new_attachment_id: newAttachmentId,
					variation_id: item.variation_id || 0
				},
				success: (response) => {
					if (response.success) {
						this.updateWooCommerceUI(item, newAttachmentId, response.data);
						this.logEntry(item.label, 'success', 'Replaced');
						this.results.replaced++;
					} else {
						this.logEntry(item.label, 'error', 'Replace failed: ' + (response.data.message || 'Unknown error'));
						this.results.failed++;
					}
				},
				error: () => {
					this.logEntry(item.label, 'error', 'Replace failed: Network error');
					this.results.failed++;
				},
				complete: () => {
					$('#rfs-replace-btn, #rfs-skip-btn').prop('disabled', false);
					$('#rfs-comparison-panel').hide();
					$('#rfs-progress-panel').show();
					this.currentIndex++;
					this.processNext();
				}
			});
		},

		skipImage() {
			const item = this.currentQueue[this.currentIndex];
			this.logEntry(item.label, 'skipped', 'Skipped');
			this.results.skipped++;

			$('#rfs-comparison-panel').hide();
			$('#rfs-progress-panel').show();
			this.currentIndex++;
			this.processNext();
		},

		updateWooCommerceUI(item, newAttachmentId, data) {
			switch (item.type) {
				case 'product':
					if (data.thumbnail_html) {
						$('#postimagediv .inside').html(data.thumbnail_html);
					}
					break;

				case 'gallery':
					if (data.gallery_html) {
						var $old = $('li.image[data-attachment_id="' + item.id + '"]');
						if ($old.length) {
							$old.replaceWith(data.gallery_html);
						}
					}
					if (data.gallery_ids) {
						$('#product_image_gallery').val(data.gallery_ids);
					}
					break;

				case 'variation':
					if (data.thumbnail_url && item.variation_id) {
						var $varPanel = $('.woocommerce_variation[data-variation_id="' + item.variation_id + '"]');
						if ($varPanel.length === 0) {
							$varPanel = $('[rel="' + item.variation_id + '"]');
						}
						if ($varPanel.length) {
							$varPanel.find('.upload_image_button img').attr('src', data.thumbnail_url);
						}
					}
					break;
			}

			// Also update the grid thumbnail for visual feedback.
			var $gridItem = $('.rfs-image-item').filter(function() {
				var imgData = $(this).data('image');
				return imgData && imgData.id === item.id && imgData.type === item.type;
			});
			if ($gridItem.length && data.thumbnail_url) {
				$gridItem.find('img').attr('src', data.thumbnail_url);
			}
		},

		stop() {
			this.isRunning = false;
			this.logEntry('---', 'skipped', 'Stopped by user');
			this.complete();
		},

		complete() {
			this.isRunning = false;

			// Update progress bar to 100%.
			$('.rfs-product-progress-bar-fill').css('width', '100%');

			// Hide progress and comparison, show completion.
			$('#rfs-progress-panel').hide();
			$('#rfs-comparison-panel').hide();
			$('#rfs-completion-panel').show();

			// Build stats.
			const total = this.results.replaced + this.results.skipped + this.results.failed;
			let html = '<p><strong>Processed:</strong> ' + total + ' image' + (total !== 1 ? 's' : '') + '</p>';
			if (this.results.replaced > 0) {
				html += '<p style="color: #00a32a;">Replaced: ' + this.results.replaced + '</p>';
			}
			if (this.results.skipped > 0) {
				html += '<p style="color: #dba617;">Skipped: ' + this.results.skipped + '</p>';
			}
			if (this.results.failed > 0) {
				html += '<p style="color: #d63638;">Failed: ' + this.results.failed + '</p>';
			}
			if (this.totalCost > 0) {
				var costSym = (typeof rfsAiSeo !== 'undefined' && rfsAiSeo.currencySymbol) ? rfsAiSeo.currencySymbol : '$';
				html += '<p style="margin-top: 6px; color: #50575e;">Total cost: <strong>' + costSym + this.totalCost.toFixed(4) + '</strong></p>';
			}

			$('#rfs-completion-stats').html(html);
		},

		reset() {
			// Reset state.
			this.selectedImages = [];
			this.currentQueue = [];
			this.currentIndex = 0;
			this.generatedAttachmentId = null;
			this.isRunning = false;
			this.results = { replaced: 0, skipped: 0, failed: 0 };
			this.totalCost = 0;

			// Deselect all.
			$('.rfs-image-item').removeClass('selected');

			// Show grid and controls, hide panels.
			$('.rfs-image-grid-header, .rfs-image-grid-container, #rfs-image-controls').show();
			$('#rfs-progress-panel, #rfs-comparison-panel, #rfs-completion-panel').hide();

			// Reset progress bar.
			$('.rfs-product-progress-bar-fill').css('width', '0%');
			$('#rfs-progress-log').empty();

			this.updateSelection();
		},

		logEntry(label, type, message) {
			const icon = type === 'success' ? '&#10003;' : (type === 'error' ? '&#10007;' : '&#8212;');
			const cls = type === 'success' ? 'rfs-log-success' : (type === 'error' ? 'rfs-log-error' : 'rfs-log-skipped');
			$('#rfs-progress-log').append(
				'<div class="rfs-log-entry ' + cls + '">' + icon + ' ' + label + ' &mdash; ' + message + '</div>'
			);

			// Auto-scroll.
			var $log = $('#rfs-progress-log');
			$log.scrollTop($log[0].scrollHeight);
		}
	};

	$(document).ready(() => ProductImageGenerator.init());

})(jQuery);
