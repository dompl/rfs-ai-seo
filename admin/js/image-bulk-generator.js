/**
 * RFS AI Bulk Image Generator
 *
 * Handles product filtering, image selection, sequential AI generation,
 * comparison panel with replace/skip flow, and completion summary.
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const ImageBulkGenerator = {
		// State.
		items: [],
		selectedProductIds: [],
		imageQueue: [],
		currentIndex: 0,
		generatedAttachmentId: null,
		isRunning: false,
		results: { replaced: 0, skipped: 0, failed: 0 },
		totalCost: 0,
		totalProcessed: 0,
		productResults: {}, // Per-product results keyed by product_id.

		/**
		 * Initialize.
		 */
		init() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			// Filter button.
			$('#rfs_image_bulk_filter_btn').on('click', () => this.loadItems());

			// Select all checkboxes.
			$('#rfs_image_bulk_select_all, #rfs_image_bulk_select_all_header').on('change', (e) => {
				this.selectAll(e.target.checked);
			});

			// Individual checkbox change (delegated).
			$(document).on('change', '.rfs-image-bulk-checkbox', () => this.updateSelection());

			// Generate button.
			$('#rfs_image_bulk_generate_selected').on('click', () => this.startGeneration());

			// Stop button (in progress panel).
			$('#rfs_image_bulk_stop_btn').on('click', () => this.stop());

			// Stop Remaining button (in comparison panel, accessible when progress panel is hidden).
			$('#rfs_image_comparison_stop_btn').on('click', () => this.stop());

			// Close button.
			$('#rfs_image_bulk_close_btn').on('click', () => this.closeProgress());

			// Comparison tab switching.
			$(document).on('click', '.rfs-comparison-tab', function(e) {
				e.preventDefault();
				$('.rfs-comparison-tab').removeClass('active');
				$(this).addClass('active');
				var tab = $(this).data('tab');
				$('.rfs-comparison-img').removeClass('active');
				$('#rfs_image_comparison_' + tab).addClass('active');
			});

			// Replace / Skip.
			$('#rfs_image_replace_btn').on('click', () => this.replaceImage());
			$('#rfs_image_skip_btn').on('click', () => this.skipImage());

			// Enter key in search.
			$('#rfs_image_bulk_search').on('keypress', (e) => {
				if (e.which === 13) {
					this.loadItems();
				}
			});
		},

		/**
		 * Load products with filters.
		 */
		loadItems() {
			const filters = {
				category: $('#rfs_image_bulk_category').val(),
				image_status: $('#rfs_image_bulk_status').val(),
				search: $('#rfs_image_bulk_search').val()
			};

			$('#rfs_image_bulk_items_tbody').html(
				'<tr><td colspan="6" class="rfs-empty-state">Loading...</td></tr>'
			);

			$.ajax({
				url: rfsAiImageBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_get_image_items',
					nonce: rfsAiImageBulk.nonce,
					...filters
				},
				success: (response) => {
					if (response.success) {
						this.items = response.data.items;
						this.renderItems(response.data);
						// Show settings panel only when items are loaded; hide when empty.
						if (response.data.items.length > 0) {
							$('#rfs_image_bulk_settings').show();
						} else {
							$('#rfs_image_bulk_settings').hide();
						}
					} else {
						alert(response.data.message || rfsAiImageBulk.strings.error);
					}
				},
				error: () => {
					alert(rfsAiImageBulk.strings.error);
				}
			});
		},

		/**
		 * Render items table.
		 */
		renderItems(data) {
			const tbody = $('#rfs_image_bulk_items_tbody');
			tbody.empty();

			if (data.items.length === 0) {
				tbody.append(
					'<tr><td colspan="6" class="rfs-empty-state">' +
					(rfsAiImageBulk.strings.noItems || 'No products found with current filters.') +
					'</td></tr>'
				);
				this.updateCounts();
				return;
			}

			data.items.forEach((item) => {
				const thumbnail = item.thumbnail
					? '<img src="' + item.thumbnail + '" alt="' + this.escHtml(item.name) + '">'
					: '<span class="dashicons dashicons-format-image"></span>';

				const imageBreakdown = this.getImageBreakdown(item);
				const statusLabel = item.image_count > 0
					? '<span class="rfs-status-badge rfs-status-has-images">' + item.image_count + ' image' + (item.image_count !== 1 ? 's' : '') + '</span>'
					: '<span class="rfs-status-badge rfs-status-no-images">No images</span>';

				tbody.append(
					'<tr data-product-id="' + item.id + '">' +
						'<th class="check-column">' +
							'<input type="checkbox" class="rfs-image-bulk-checkbox" value="' + item.id + '" data-name="' + this.escHtml(item.name) + '" data-image-count="' + item.image_count + '">' +
						'</th>' +
						'<td class="column-thumbnail">' + thumbnail + '</td>' +
						'<td class="column-name">' +
							'<a href="' + item.edit_url + '" target="_blank">' + this.escHtml(item.name) + '</a>' +
						'</td>' +
						'<td class="column-images">' + imageBreakdown + '</td>' +
						'<td class="column-status">' + statusLabel + '</td>' +
						'<td class="column-result"></td>' +
					'</tr>'
				);
			});

			this.updateCounts(data.total);
			this.selectedProductIds = [];
			this.updateSelection();
		},

		/**
		 * Get image breakdown HTML.
		 */
		getImageBreakdown(item) {
			const parts = [];
			if (item.featured) {
				parts.push('Featured');
			}
			if (item.gallery > 0) {
				parts.push(item.gallery + ' gallery');
			}
			if (item.variations > 0) {
				parts.push(item.variations + ' variation');
			}
			return parts.length > 0 ? parts.join(', ') : '<span style="color: #a7aaad;">None</span>';
		},

		/**
		 * Escape HTML.
		 */
		escHtml(text) {
			const div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		},

		/**
		 * Select/deselect all.
		 */
		selectAll(checked) {
			$('.rfs-image-bulk-checkbox').prop('checked', checked);
			this.updateSelection();
		},

		/**
		 * Update selection state.
		 */
		updateSelection() {
			this.selectedProductIds = $('.rfs-image-bulk-checkbox:checked').map(function() {
				return $(this).val();
			}).get();

			const count = this.selectedProductIds.length;
			$('#rfs_image_bulk_selected_count').text(
				count > 0 ? 'Selected: ' + count : ''
			);

			// Calculate total images for selected products.
			let totalImages = 0;
			$('.rfs-image-bulk-checkbox:checked').each(function() {
				totalImages += parseInt($(this).data('image-count')) || 0;
			});

			// Update cost estimate.
			const sym = rfsAiImageBulk.currencySymbol || '$';
			if (totalImages > 0) {
				const perImage = parseFloat(rfsAiImageBulk.imageCostEstimate) || 0;
				const totalEst = (perImage * totalImages).toFixed(4);
				$('#rfs_image_bulk_cost_estimate').text(
					'Est. cost: ' + sym + totalEst + ' (' + totalImages + ' images)'
				);
			} else {
				$('#rfs_image_bulk_cost_estimate').text('');
			}

			$('#rfs_image_bulk_generate_selected').prop('disabled', count === 0);

			// Update select all checkbox state.
			const totalCheckboxes = $('.rfs-image-bulk-checkbox').length;
			const checkedCheckboxes = $('.rfs-image-bulk-checkbox:checked').length;
			$('#rfs_image_bulk_select_all, #rfs_image_bulk_select_all_header').prop('checked',
				totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes
			);
		},

		/**
		 * Update counts display.
		 */
		updateCounts(total) {
			if (total !== undefined) {
				$('#rfs_image_bulk_total_count').text('Total products: ' + total);
			} else {
				$('#rfs_image_bulk_total_count').text('');
			}
		},

		/**
		 * Get prompt settings.
		 */
		getPromptSettings() {
			return {
				prompt_template: $('#rfs_image_bulk_prompt').val() || '',
				custom_prompt: $('#rfs_image_bulk_custom_prompt').val() || '',
				aspect_ratio: $('#rfs_image_bulk_aspect_ratio').val() || '1:1'
			};
		},

		/**
		 * Start generation.
		 */
		startGeneration() {
			const settings = this.getPromptSettings();

			if (!settings.prompt_template && !settings.custom_prompt) {
				alert('Please select a prompt template or enter a custom prompt.');
				return;
			}

			if (this.selectedProductIds.length === 0) {
				return;
			}

			// Build image queue from selected products.
			this.imageQueue = [];
			this.selectedProductIds.forEach((productId) => {
				const product = this.items.find(i => i.id == productId);
				if (product && product.images) {
					product.images.forEach((image) => {
						this.imageQueue.push({
							...image,
							productName: product.name
						});
					});
				}
			});

			if (this.imageQueue.length === 0) {
				alert('No images found in selected products.');
				return;
			}

			const message = 'Generate AI images for ' + this.imageQueue.length +
				' image(s) across ' + this.selectedProductIds.length + ' product(s)?';

			if (!confirm(message)) {
				return;
			}

			// Reset state.
			this.currentIndex = 0;
			this.isRunning = true;
			this.results = { replaced: 0, skipped: 0, failed: 0 };
			this.totalCost = 0;
			this.totalProcessed = 0;
			this.productResults = {};

			// Show progress panel, hide other panels.
			$('#rfs_image_bulk_progress').show();
			$('#rfs_image_comparison_panel').hide();
			$('#rfs_image_completion_summary').hide();
			$('#rfs_image_progress_log').empty();

			// Reset stats display.
			$('#rfs_image_progress_replaced').text('0');
			$('#rfs_image_progress_skipped').text('0');
			$('#rfs_image_progress_failed').text('0');
			const sym = rfsAiImageBulk.currencySymbol || '$';
			$('#rfs_image_progress_cost').text(sym + '0.0000');
			$('#rfs_image_progress_bar').css('width', '0%');

			// Start processing.
			this.processNext();
		},

		/**
		 * Process next image in queue.
		 */
		processNext() {
			if (!this.isRunning || this.currentIndex >= this.imageQueue.length) {
				this.complete();
				return;
			}

			const item = this.imageQueue[this.currentIndex];
			const total = this.imageQueue.length;
			const current = this.currentIndex + 1;
			const pct = Math.round((this.currentIndex / total) * 100);

			// Update progress.
			$('#rfs_image_progress_bar').css('width', pct + '%');
			$('#rfs_image_progress_current').text(
				item.productName + ' - ' + item.label
			);
			$('#rfs_image_progress_count').text(
				current + ' / ' + total + ' (' + pct + '%)'
			);

			// Show progress panel, hide comparison.
			$('#rfs_image_bulk_progress').show();
			$('#rfs_image_comparison_panel').hide();

			const settings = this.getPromptSettings();

			$.ajax({
				url: rfsAiImageBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_product_image',
					nonce: rfsAiImageBulk.nonce,
					attachment_id: item.id,
					prompt_template: settings.prompt_template,
					custom_prompt: settings.custom_prompt,
					aspect_ratio: settings.aspect_ratio
				},
				success: (response) => {
					if (response.success) {
						const costInfo = response.data.cost_formatted ? ' (' + response.data.cost_formatted + ')' : '';
						this.logEntry(
							item.productName + ' - ' + item.label,
							'success',
							'Generated' + costInfo
						);
						this.generatedAttachmentId = response.data.new_attachment_id;
						this.showComparison(item, response.data);
					} else {
						this.logEntry(
							item.productName + ' - ' + item.label,
							'error',
							response.data.message || 'Generation failed'
						);
						this.results.failed++;
						this.totalProcessed++;
						this.updateStatCards();
						this.recordProductResult(item.product_id, 'failed');
						this.currentIndex++;
						this.processNext();
					}
				},
				error: () => {
					this.logEntry(
						item.productName + ' - ' + item.label,
						'error',
						'Network error'
					);
					this.results.failed++;
					this.totalProcessed++;
					this.updateStatCards();
					this.recordProductResult(item.product_id, 'failed');
					this.currentIndex++;
					this.processNext();
				}
			});
		},

		/**
		 * Show comparison panel.
		 */
		showComparison(item, data) {
			const total = this.imageQueue.length;
			const current = this.currentIndex + 1;

			// Track cost.
			if (data.cost) {
				this.totalCost += parseFloat(data.cost);
			}

			// Hide progress, show comparison.
			$('#rfs_image_bulk_progress').hide();
			$('#rfs_image_comparison_panel').show();

			$('#rfs_image_comparison_product').text(item.productName);
			$('#rfs_image_comparison_label').text(' - ' + item.label);
			const costLabel = data.cost_formatted ? ' \u2014 ' + data.cost_formatted : '';
			$('#rfs_image_comparison_counter').text('(' + current + '/' + total + ')' + costLabel);
			$('#rfs_image_comparison_original').attr('src', data.original_url);
			$('#rfs_image_comparison_generated').attr('src', data.generated_url);

			// Show "Generated" tab by default.
			$('.rfs-comparison-tab').removeClass('active');
			$('.rfs-comparison-tab[data-tab="generated"]').addClass('active');
			$('.rfs-comparison-img').removeClass('active');
			$('#rfs_image_comparison_generated').addClass('active');

			// Store current item data for replace.
			$('#rfs_image_comparison_panel').data('current-item', item);
			$('#rfs_image_comparison_panel').data('new-attachment-id', data.new_attachment_id);
		},

		/**
		 * Replace image.
		 */
		replaceImage() {
			const $panel = $('#rfs_image_comparison_panel');
			const item = $panel.data('current-item');
			const newAttachmentId = $panel.data('new-attachment-id');

			// Disable buttons during AJAX.
			$('#rfs_image_replace_btn, #rfs_image_skip_btn').prop('disabled', true);

			let outcome = 'failed';
			$.ajax({
				url: rfsAiImageBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_replace_product_image',
					nonce: rfsAiImageBulk.nonce,
					image_type: item.type,
					product_id: item.product_id,
					old_attachment_id: item.id,
					new_attachment_id: newAttachmentId,
					variation_id: item.variation_id || 0
				},
				success: (response) => {
					if (response.success) {
						this.logEntry(
							item.productName + ' - ' + item.label,
							'success',
							'Replaced'
						);
						this.results.replaced++;
						outcome = 'replaced';
					} else {
						this.logEntry(
							item.productName + ' - ' + item.label,
							'error',
							'Replace failed: ' + (response.data.message || 'Unknown error')
						);
						this.results.failed++;
					}
				},
				error: () => {
					this.logEntry(
						item.productName + ' - ' + item.label,
						'error',
						'Replace failed: Network error'
					);
					this.results.failed++;
				},
				complete: () => {
					$('#rfs_image_replace_btn, #rfs_image_skip_btn').prop('disabled', false);
					$('#rfs_image_comparison_panel').hide();
					$('#rfs_image_bulk_progress').show();
					this.totalProcessed++;
					this.updateStatCards();
					this.recordProductResult(item.product_id, outcome);
					this.currentIndex++;
					this.processNext();
				}
			});
		},

		/**
		 * Skip image.
		 */
		skipImage() {
			const item = this.imageQueue[this.currentIndex];
			this.logEntry(
				item.productName + ' - ' + item.label,
				'skipped',
				'Skipped'
			);
			this.results.skipped++;
			this.totalProcessed++;
			this.updateStatCards();
			this.recordProductResult(item.product_id, 'skipped');

			$('#rfs_image_comparison_panel').hide();
			$('#rfs_image_bulk_progress').show();
			this.currentIndex++;
			this.processNext();
		},

		/**
		 * Update stat cards in progress panel.
		 */
		updateStatCards() {
			$('#rfs_image_progress_replaced').text(this.results.replaced);
			$('#rfs_image_progress_skipped').text(this.results.skipped);
			$('#rfs_image_progress_failed').text(this.results.failed);
			const sym = rfsAiImageBulk.currencySymbol || '$';
			$('#rfs_image_progress_cost').text(sym + this.totalCost.toFixed(4));
		},

		/**
		 * Stop generation.
		 */
		stop() {
			if (confirm('Stop bulk image generation?')) {
				this.isRunning = false;
				this.logEntry('---', 'skipped', 'Stopped by user');
				this.complete();
			}
		},

		/**
		 * Complete generation.
		 */
		complete() {
			this.isRunning = false;

			// Update progress bar to 100%.
			$('#rfs_image_progress_bar').css('width', '100%');

			// Hide progress and comparison panels.
			$('#rfs_image_bulk_progress').hide();
			$('#rfs_image_comparison_panel').hide();

			// Update summary.
			const sym = rfsAiImageBulk.currencySymbol || '$';
			$('#rfs_image_summary_total').text(this.totalProcessed);
			$('#rfs_image_summary_replaced').text(this.results.replaced);
			$('#rfs_image_summary_skipped').text(this.results.skipped);
			$('#rfs_image_summary_failed').text(this.results.failed);
			$('#rfs_image_summary_cost').text(sym + this.totalCost.toFixed(4));

			// Show summary.
			$('#rfs_image_completion_summary').show();
		},

		/**
		 * Close progress / summary panels.
		 */
		closeProgress() {
			$('#rfs_image_bulk_progress').hide();
			$('#rfs_image_comparison_panel').hide();
			$('#rfs_image_completion_summary').hide();
			$('#rfs_image_bulk_stop_btn').show();
		},

		/**
		 * Record a per-product image result and update the row when all images are done.
		 */
		recordProductResult(productId, outcome) {
			if (!this.productResults[productId]) {
				// Count how many images this product has in the queue.
				const total = this.imageQueue.filter(i => i.product_id == productId).length;
				this.productResults[productId] = { replaced: 0, skipped: 0, failed: 0, total: total, done: 0 };
			}
			const pr = this.productResults[productId];
			pr[outcome]++;
			pr.done++;

			// Update row once all images for this product are processed.
			if (pr.done >= pr.total) {
				this.updateProductRow(productId, pr);
			}
		},

		/**
		 * Update a product's table row with generation results.
		 */
		updateProductRow(productId, pr) {
			const $row = $('tr[data-product-id="' + productId + '"]');
			if (!$row.length) {
				return;
			}

			// Uncheck the checkbox.
			$row.find('.rfs-image-bulk-checkbox').prop('checked', false);

			// Build result badges.
			const parts = [];
			if (pr.replaced > 0) {
				parts.push('<span class="rfs-status-badge rfs-status-has-images">' + pr.replaced + ' replaced</span>');
			}
			if (pr.skipped > 0) {
				parts.push('<span class="rfs-status-badge" style="background:#fff3cd;color:#856404;">' + pr.skipped + ' skipped</span>');
			}
			if (pr.failed > 0) {
				parts.push('<span class="rfs-status-badge rfs-status-no-images">' + pr.failed + ' failed</span>');
			}

			$row.find('.column-result').html(parts.join(' '));

			// Update selection counts since checkbox changed.
			this.updateSelection();
		},

		/**
		 * Log entry.
		 */
		logEntry(label, type, message) {
			const icon = type === 'success' ? '&#10003;' : (type === 'error' ? '&#10007;' : '&#8212;');
			const cls = type === 'success' ? 'rfs-ai-seo-log-entry success'
				: (type === 'error' ? 'rfs-ai-seo-log-entry error' : 'rfs-ai-seo-log-entry');
			$('#rfs_image_progress_log').append(
				'<div class="' + cls + '">' + icon + ' <strong>' + label + '</strong> &mdash; ' + message + '</div>'
			);

			// Auto-scroll.
			var $log = $('#rfs_image_progress_log');
			$log.scrollTop($log[0].scrollHeight);
		}
	};

	// Initialize on document ready.
	$(document).ready(() => ImageBulkGenerator.init());

})(jQuery);
