/**
 * RFS AI Bulk Image Generator
 *
 * Individual image rows with sequential generation, comparison panel
 * with replace/regenerate/skip flow, and inline status updates.
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const ImageBulkGenerator = {
		// State.
		items: [],
		selectedKeys: [],
		imageQueue: [],
		currentIndex: 0,
		generatedAttachmentId: null,
		isRunning: false,
		results: { replaced: 0, skipped: 0, failed: 0 },
		totalCost: 0,
		totalProcessed: 0,

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
			$('#rfs_image_bulk_filter_btn').on('click', () => this.loadItems());

			$('#rfs_image_bulk_select_all, #rfs_image_bulk_select_all_header').on('change', (e) => {
				this.selectAll(e.target.checked);
			});

			$(document).on('change', '.rfs-image-bulk-checkbox', () => this.updateSelection());

			$('#rfs_image_bulk_generate_selected').on('click', () => this.startGeneration());

			$('#rfs_image_bulk_stop_btn').on('click', () => this.stop());
			$('#rfs_image_comparison_stop_btn').on('click', () => this.stop());

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

			// Replace / Regenerate / Skip.
			$('#rfs_image_replace_btn').on('click', () => this.replaceImage());
			$('#rfs_image_regenerate_btn').on('click', () => this.regenerateImage());
			$('#rfs_image_skip_btn').on('click', () => this.skipImage());

			$('#rfs_image_bulk_search').on('keypress', (e) => {
				if (e.which === 13) {
					this.loadItems();
				}
			});
		},

		/**
		 * Load images with filters.
		 */
		loadItems() {
			const filters = {
				category: $('#rfs_image_bulk_category').val(),
				image_status: $('#rfs_image_bulk_status').val(),
				search: $('#rfs_image_bulk_search').val()
			};

			$('#rfs_image_bulk_items_tbody').html(
				'<tr><td colspan="5" class="rfs-empty-state">Loading...</td></tr>'
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
		 * Render items table — one row per image.
		 */
		renderItems(data) {
			const tbody = $('#rfs_image_bulk_items_tbody');
			tbody.empty();

			if (data.items.length === 0) {
				tbody.append(
					'<tr><td colspan="5" class="rfs-empty-state">' +
					(rfsAiImageBulk.strings.noItems || 'No images found with current filters.') +
					'</td></tr>'
				);
				this.updateCounts();
				return;
			}

			data.items.forEach((item) => {
				const thumbnail = item.thumbnail
					? '<img src="' + this.escHtml(item.thumbnail) + '" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">'
					: '<span class="dashicons dashicons-format-image"></span>';

				const typeLabel = item.type.charAt(0).toUpperCase() + item.type.slice(1);
				const rowKey = item.attachment_id + '-' + item.product_id;

				tbody.append(
					'<tr data-row-key="' + rowKey + '" data-attachment-id="' + item.attachment_id + '" data-product-id="' + item.product_id + '">' +
						'<th class="check-column">' +
							'<input type="checkbox" class="rfs-image-bulk-checkbox"' +
								' value="' + rowKey + '"' +
								' data-attachment-id="' + item.attachment_id + '"' +
								' data-product-id="' + item.product_id + '"' +
								' data-product-name="' + this.escHtml(item.product_name) + '"' +
								' data-type="' + this.escHtml(item.type) + '"' +
								' data-label="' + this.escHtml(item.label) + '"' +
								' data-variation-id="' + (item.variation_id || 0) + '">' +
						'</th>' +
						'<td class="column-thumbnail">' + thumbnail + '</td>' +
						'<td class="column-name">' +
							'<a href="' + item.edit_url + '" target="_blank">' + this.escHtml(item.product_name) + '</a>' +
						'</td>' +
						'<td class="column-type">' + this.escHtml(typeLabel) + '</td>' +
						'<td class="column-status"></td>' +
					'</tr>'
				);
			});

			this.updateCounts(data.total);
			this.selectedKeys = [];
			this.updateSelection();
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
			this.selectedKeys = $('.rfs-image-bulk-checkbox:checked').map(function() {
				return $(this).val();
			}).get();

			const count = this.selectedKeys.length;
			$('#rfs_image_bulk_selected_count').text(
				count > 0 ? 'Selected: ' + count : ''
			);

			// Update cost estimate.
			const sym = rfsAiImageBulk.currencySymbol || '$';
			if (count > 0) {
				const perImage = parseFloat(rfsAiImageBulk.imageCostEstimate) || 0;
				const totalEst = (perImage * count).toFixed(4);
				$('#rfs_image_bulk_cost_estimate').text(
					'Est. cost: ' + sym + totalEst + ' (' + count + ' images)'
				);
			} else {
				$('#rfs_image_bulk_cost_estimate').text('');
			}

			$('#rfs_image_bulk_generate_selected').prop('disabled', count === 0);

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
				$('#rfs_image_bulk_total_count').text('Total images: ' + total);
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
		 * Show an inline notice (replaces alert() for better visibility).
		 */
		showNotice(message, type) {
			$('.rfs-bulk-notice').remove();
			const cls = type === 'error' ? 'notice-error' : 'notice-warning';
			const $notice = $(
				'<div class="notice ' + cls + ' is-dismissible rfs-bulk-notice" style="margin: 10px 0;">' +
					'<p>' + this.escHtml(message) + '</p>' +
					'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>' +
				'</div>'
			);
			$notice.find('.notice-dismiss').on('click', function() { $notice.remove(); });
			$('#rfs_image_bulk_settings').before($notice);
			$notice[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		},

		/**
		 * Start generation — build queue from selected images.
		 */
		startGeneration() {
			$('.rfs-bulk-notice').remove();

			const settings = this.getPromptSettings();

			if (!settings.prompt_template && !settings.custom_prompt) {
				this.showNotice('Please select a prompt template or enter a custom prompt.', 'error');
				$('#rfs_image_bulk_prompt').focus();
				return;
			}

			if (this.selectedKeys.length === 0) {
				return;
			}

			// Build image queue from selected checkboxes.
			this.imageQueue = [];
			$('.rfs-image-bulk-checkbox:checked').each((i, el) => {
				const $el = $(el);
				this.imageQueue.push({
					attachment_id: parseInt($el.data('attachment-id'), 10),
					product_id: parseInt($el.data('product-id'), 10),
					product_name: $el.data('product-name'),
					type: $el.data('type'),
					label: $el.data('label'),
					variation_id: parseInt($el.data('variation-id'), 10) || 0,
					rowKey: $el.val()
				});
			});

			if (this.imageQueue.length === 0) {
				return;
			}

			// Reset state.
			this.currentIndex = 0;
			this.isRunning = true;
			this.results = { replaced: 0, skipped: 0, failed: 0 };
			this.totalCost = 0;
			this.totalProcessed = 0;

			// Disable the generate button and show loading state.
			$('#rfs_image_bulk_generate_selected')
				.prop('disabled', true)
				.text(rfsAiImageBulk.strings.generating || 'Generating...');

			// Mark all queued rows as pending with spinner.
			this.imageQueue.forEach((item) => {
				const $row = $('tr[data-row-key="' + item.rowKey + '"]');
				$row.find('.column-status').html(
					'<span class="spinner is-active" style="float: none; margin: 0; visibility: visible;"></span>'
				);
				$row.addClass('rfs-row-queued');
			});

			// Show progress panel, hide other panels.
			$('#rfs_image_bulk_progress').addClass('active').show();
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

			// Scroll progress panel into view.
			$('#rfs_image_bulk_progress')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });

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
			const itemLabel = item.product_name + ' (' + item.label + ')';

			// Update progress.
			$('#rfs_image_progress_bar').css('width', pct + '%');
			$('#rfs_image_progress_current').text(itemLabel);
			$('#rfs_image_progress_count').text(current + ' / ' + total + ' (' + pct + '%)');

			// Show progress panel, hide comparison.
			$('#rfs_image_bulk_progress').show();
			$('#rfs_image_comparison_panel').hide();

			// Highlight the current row being processed.
			$('.rfs-row-processing').removeClass('rfs-row-processing');
			const $currentRow = $('tr[data-row-key="' + item.rowKey + '"]');
			$currentRow.addClass('rfs-row-processing');
			$currentRow.find('.column-status').html(
				'<span class="spinner is-active" style="float: none; margin: 0; visibility: visible;"></span>' +
				' <span style="color: var(--rfs-primary); font-size: 12px;">Generating...</span>'
			);

			const settings = this.getPromptSettings();

			$.ajax({
				url: rfsAiImageBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_product_image',
					nonce: rfsAiImageBulk.nonce,
					attachment_id: item.attachment_id,
					prompt_template: settings.prompt_template,
					custom_prompt: settings.custom_prompt,
					aspect_ratio: settings.aspect_ratio
				},
				success: (response) => {
					if (response.success) {
						const costInfo = response.data.cost_formatted ? ' (' + response.data.cost_formatted + ')' : '';
						this.logEntry(itemLabel, 'success', 'Generated' + costInfo);
						this.generatedAttachmentId = response.data.new_attachment_id;
						this.showComparison(item, response.data);
					} else {
						this.logEntry(itemLabel, 'error', response.data.message || 'Generation failed');
						this.results.failed++;
						this.totalProcessed++;
						this.updateStatCards();
						this.updateItemRow(item.rowKey, 'failed');
						this.currentIndex++;
						this.processNext();
					}
				},
				error: () => {
					this.logEntry(itemLabel, 'error', 'Network error');
					this.results.failed++;
					this.totalProcessed++;
					this.updateStatCards();
					this.updateItemRow(item.rowKey, 'failed');
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

			$('#rfs_image_comparison_product').text(item.product_name);
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

			// Store current item data for replace/regenerate.
			$('#rfs_image_comparison_panel').data('current-item', item);
			$('#rfs_image_comparison_panel').data('new-attachment-id', data.new_attachment_id);

			// Update stat cards with current cost.
			this.updateStatCards();
		},

		/**
		 * Replace image.
		 */
		replaceImage() {
			const $panel = $('#rfs_image_comparison_panel');
			const item = $panel.data('current-item');
			const newAttachmentId = $panel.data('new-attachment-id');

			// Disable buttons during AJAX.
			$('#rfs_image_replace_btn, #rfs_image_skip_btn, #rfs_image_regenerate_btn').prop('disabled', true);

			let outcome = 'failed';
			$.ajax({
				url: rfsAiImageBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_replace_product_image',
					nonce: rfsAiImageBulk.nonce,
					image_type: item.type,
					product_id: item.product_id,
					old_attachment_id: item.attachment_id,
					new_attachment_id: newAttachmentId,
					variation_id: item.variation_id || 0
				},
				success: (response) => {
					if (response.success) {
						const itemLabel = item.product_name + ' (' + item.label + ')';
						this.logEntry(itemLabel, 'success', 'Replaced');
						this.results.replaced++;
						outcome = 'replaced';
					} else {
						const itemLabel = item.product_name + ' (' + item.label + ')';
						this.logEntry(itemLabel, 'error', 'Replace failed: ' + (response.data.message || 'Unknown error'));
						this.results.failed++;
					}
				},
				error: () => {
					const itemLabel = item.product_name + ' (' + item.label + ')';
					this.logEntry(itemLabel, 'error', 'Replace failed: Network error');
					this.results.failed++;
				},
				complete: () => {
					$('#rfs_image_replace_btn, #rfs_image_skip_btn, #rfs_image_regenerate_btn').prop('disabled', false);
					$('#rfs_image_comparison_panel').hide();
					$('#rfs_image_bulk_progress').show();
					this.totalProcessed++;
					this.updateStatCards();
					this.updateItemRow(item.rowKey, outcome);
					this.currentIndex++;
					this.processNext();
				}
			});
		},

		/**
		 * Regenerate image — call API again for a different version.
		 */
		regenerateImage() {
			const $panel = $('#rfs_image_comparison_panel');
			const item = $panel.data('current-item');
			const itemLabel = item.product_name + ' (' + item.label + ')';

			// Disable buttons during regeneration.
			$('#rfs_image_replace_btn, #rfs_image_skip_btn, #rfs_image_regenerate_btn').prop('disabled', true);
			$('#rfs_image_regenerate_btn').text('Regenerating...');

			const settings = this.getPromptSettings();

			$.ajax({
				url: rfsAiImageBulk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_generate_product_image',
					nonce: rfsAiImageBulk.nonce,
					attachment_id: item.attachment_id,
					prompt_template: settings.prompt_template,
					custom_prompt: settings.custom_prompt,
					aspect_ratio: settings.aspect_ratio
				},
				success: (response) => {
					if (response.success) {
						const costInfo = response.data.cost_formatted ? ' (' + response.data.cost_formatted + ')' : '';
						this.logEntry(itemLabel, 'success', 'Regenerated' + costInfo);

						// Track additional cost.
						if (response.data.cost) {
							this.totalCost += parseFloat(response.data.cost);
						}

						// Update comparison panel with new generated image.
						$('#rfs_image_comparison_generated').attr('src', response.data.generated_url);
						$panel.data('new-attachment-id', response.data.new_attachment_id);

						// Update cost in counter.
						const total = this.imageQueue.length;
						const current = this.currentIndex + 1;
						const costLabel = response.data.cost_formatted ? ' \u2014 ' + response.data.cost_formatted : '';
						$('#rfs_image_comparison_counter').text('(' + current + '/' + total + ')' + costLabel);

						// Switch to Generated tab.
						$('.rfs-comparison-tab').removeClass('active');
						$('.rfs-comparison-tab[data-tab="generated"]').addClass('active');
						$('.rfs-comparison-img').removeClass('active');
						$('#rfs_image_comparison_generated').addClass('active');

						this.updateStatCards();
					} else {
						this.logEntry(itemLabel, 'error', 'Regeneration failed: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: () => {
					this.logEntry(itemLabel, 'error', 'Regeneration failed: Network error');
				},
				complete: () => {
					$('#rfs_image_replace_btn, #rfs_image_skip_btn, #rfs_image_regenerate_btn').prop('disabled', false);
					$('#rfs_image_regenerate_btn').text('Regenerate');
				}
			});
		},

		/**
		 * Skip image.
		 */
		skipImage() {
			const item = this.imageQueue[this.currentIndex];
			const itemLabel = item.product_name + ' (' + item.label + ')';
			this.logEntry(itemLabel, 'skipped', 'Skipped');
			this.results.skipped++;
			this.totalProcessed++;
			this.updateStatCards();
			this.updateItemRow(item.rowKey, 'skipped');

			$('#rfs_image_comparison_panel').hide();
			$('#rfs_image_bulk_progress').show();
			this.currentIndex++;
			this.processNext();
		},

		/**
		 * Update a table row after generation result.
		 */
		updateItemRow(rowKey, outcome) {
			const $row = $('tr[data-row-key="' + rowKey + '"]');
			if (!$row.length) {
				return;
			}

			// Remove processing/queued classes.
			$row.removeClass('rfs-row-processing rfs-row-queued');

			// Uncheck the checkbox.
			$row.find('.rfs-image-bulk-checkbox').prop('checked', false);

			// Build status badge.
			let statusHtml = '';
			if (outcome === 'replaced') {
				statusHtml = '<span class="rfs-status-badge rfs-status-has-images">Replaced</span>';
			} else if (outcome === 'skipped') {
				statusHtml = '<span class="rfs-status-badge" style="background:#fff3cd;color:#856404;">Skipped</span>';
			} else if (outcome === 'failed') {
				statusHtml = '<span class="rfs-status-badge rfs-status-no-images">Failed</span>';
			}

			$row.find('.column-status').html(statusHtml);

			// Brief highlight.
			const highlightColor = outcome === 'replaced' ? '#d4edda' : (outcome === 'failed' ? '#f8d7da' : '#fff3cd');
			$row.css('background-color', highlightColor);
			setTimeout(() => $row.css('background-color', ''), 2000);

			this.updateSelection();
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
			this.isRunning = false;
			this.logEntry('---', 'skipped', 'Stopped by user');
			this.complete();
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

			// Reset generate button.
			$('#rfs_image_bulk_generate_selected').text('Generate for Selected');
			this.updateSelection();

			// Clear any remaining queued/processing row states.
			$('.rfs-row-queued, .rfs-row-processing').each(function() {
				$(this).removeClass('rfs-row-queued rfs-row-processing');
				const $status = $(this).find('.column-status');
				// Only clear spinner if no outcome badge was set.
				if ($status.find('.rfs-status-badge').length === 0) {
					$status.html('');
				}
			});

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
		 * Log entry.
		 */
		logEntry(label, type, message) {
			const icon = type === 'success' ? '&#10003;' : (type === 'error' ? '&#10007;' : '&#8212;');
			const cls = type === 'success' ? 'rfs-ai-seo-log-entry success'
				: (type === 'error' ? 'rfs-ai-seo-log-entry error' : 'rfs-ai-seo-log-entry skipped');
			$('#rfs_image_progress_log').append(
				'<div class="' + cls + '">' + icon + ' <strong>' + this.escHtml(label) + '</strong> &mdash; ' + message + '</div>'
			);

			var $log = $('#rfs_image_progress_log');
			$log.scrollTop($log[0].scrollHeight);
		}
	};

	$(document).ready(() => ImageBulkGenerator.init());

})(jQuery);
