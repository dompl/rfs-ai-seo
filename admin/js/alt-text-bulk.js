/**
 * RFS AI Bulk Alt Text Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const AltTextBulkGenerator = {
		items: [],
		selectedKeys: [],
		currentFilters: {},
		isRunning: false,
		currentIndex: 0,
		imageQueue: [],
		results: {
			success: 0,
			failed: 0,
			tokens: 0
		},

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
			$('#rfs_alt_bulk_filter_btn').on('click', () => this.loadItems());

			$('#rfs_alt_bulk_select_all, #rfs_alt_bulk_select_all_header').on('change', (e) => this.selectAll(e.target.checked));

			$(document).on('change', '.rfs-alt-bulk-item-checkbox', () => this.updateSelection());

			$('#rfs_alt_bulk_generate_selected').on('click', () => this.startGeneration(false));
			$('#rfs_alt_bulk_generate_all').on('click', () => this.startGeneration(true));

			$('#rfs_alt_bulk_stop_btn').on('click', () => this.stop());

			$('#rfs_alt_bulk_close_btn').on('click', () => this.closeProgress());

			$('#rfs_alt_bulk_search').on('keypress', (e) => {
				if (e.which === 13) {
					this.loadItems();
				}
			});
		},

		/**
		 * Load items with filters
		 */
		loadItems() {
			const filters = {
				category: $('#rfs_alt_bulk_category').val(),
				status: $('#rfs_alt_bulk_status').val(),
				search: $('#rfs_alt_bulk_search').val(),
				per_page: 100,
				page: 1
			};

			this.currentFilters = filters;

			$.ajax({
				url: rfsAiAltText.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_get_alt_text_items',
					nonce: rfsAiAltText.nonce,
					...filters
				},
				success: (response) => {
					if (response.success) {
						this.items = response.data.items;
						this.renderItems(response.data);
					} else {
						alert(response.data.message || rfsAiAltText.strings.error);
					}
				},
				error: () => {
					alert(rfsAiAltText.strings.error);
				}
			});
		},

		/**
		 * Render items table — one row per image
		 */
		renderItems(data) {
			const tbody = $('#rfs_alt_bulk_items_tbody');
			tbody.empty();

			if (data.items.length === 0) {
				tbody.append(`
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px;">
							${rfsAiAltText.strings.noItems || 'No images found with current filters.'}
						</td>
					</tr>
				`);
				this.updateCounts();
				return;
			}

			data.items.forEach((item) => {
				const thumbnail = item.thumbnail ?
					`<img src="${this.escapeHtml(item.thumbnail)}" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` :
					'<span class="dashicons dashicons-format-image"></span>';

				const altText = item.current_alt ?
					this.truncate(item.current_alt, 60) :
					'<span style="color: #a7aaad;">&mdash;</span>';

				const status = item.has_alt ?
					'<span style="color: #46b450;">&#10003; Has Alt</span>' :
					'<span style="color: #dc3232;">&#10005; Missing</span>';

				const typeLabel = item.type.charAt(0).toUpperCase() + item.type.slice(1);

				const rowKey = item.attachment_id + '-' + item.product_id;

				tbody.append(`
					<tr data-row-key="${rowKey}" data-attachment-id="${item.attachment_id}" data-product-id="${item.product_id}">
						<th class="check-column">
							<input type="checkbox" class="rfs-alt-bulk-item-checkbox"
								value="${rowKey}"
								data-attachment-id="${item.attachment_id}"
								data-product-id="${item.product_id}"
								data-product-name="${this.escapeHtml(item.product_name)}"
								data-type="${this.escapeHtml(item.type)}">
						</th>
						<td class="column-thumbnail">${thumbnail}</td>
						<td class="column-name">
							<a href="${item.edit_url}" target="_blank">${this.escapeHtml(item.product_name)}</a>
						</td>
						<td class="column-type">${this.escapeHtml(typeLabel)}</td>
						<td class="column-alt-text">${altText}</td>
						<td class="column-status">${status}</td>
					</tr>
				`);
			});

			this.updateCounts(data.total);
			this.selectedKeys = [];
			this.updateSelection();
		},

		/**
		 * Truncate text
		 */
		truncate(text, length) {
			return text.length > length ? text.substring(0, length) + '...' : text;
		},

		/**
		 * Escape HTML entities.
		 */
		escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Select/deselect all
		 */
		selectAll(checked) {
			$('.rfs-alt-bulk-item-checkbox').prop('checked', checked);
			this.updateSelection();
		},

		/**
		 * Update selection state
		 */
		updateSelection() {
			this.selectedKeys = $('.rfs-alt-bulk-item-checkbox:checked').map(function() {
				return $(this).val();
			}).get();

			const count = this.selectedKeys.length;
			$('#rfs_alt_bulk_selected_count').text(
				count > 0 ? `Selected: ${count}` : ''
			);

			$('#rfs_alt_bulk_generate_selected').prop('disabled', count === 0);
			$('#rfs_alt_bulk_generate_all').prop('disabled', this.items.length === 0);

			const totalCheckboxes = $('.rfs-alt-bulk-item-checkbox').length;
			const checkedCheckboxes = $('.rfs-alt-bulk-item-checkbox:checked').length;
			$('#rfs_alt_bulk_select_all, #rfs_alt_bulk_select_all_header').prop('checked',
				totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes
			);
		},

		/**
		 * Update counts display
		 */
		updateCounts(total) {
			if (total !== undefined) {
				$('#rfs_alt_bulk_total_count').text(`Total images: ${total}`);
			} else {
				$('#rfs_alt_bulk_total_count').text('');
			}
		},

		/**
		 * Start generation — build queue from selected images.
		 */
		startGeneration(allFiltered) {
			this.imageQueue = [];
			const overwrite = $('#rfs_alt_bulk_overwrite').is(':checked');

			if (allFiltered) {
				this.items.forEach(item => {
					if (overwrite || !item.has_alt) {
						this.imageQueue.push({
							attachment_id: item.attachment_id,
							product_id: item.product_id,
							productName: item.product_name,
							imageType: item.type,
							rowKey: item.attachment_id + '-' + item.product_id
						});
					}
				});
			} else {
				$('.rfs-alt-bulk-item-checkbox:checked').each((i, el) => {
					const $el = $(el);
					const attachmentId = parseInt($el.data('attachment-id'), 10);
					const productId = parseInt($el.data('product-id'), 10);
					const item = this.items.find(it =>
						it.attachment_id === attachmentId && it.product_id === productId
					);

					if (item && (overwrite || !item.has_alt)) {
						this.imageQueue.push({
							attachment_id: attachmentId,
							product_id: productId,
							productName: $el.data('product-name'),
							imageType: $el.data('type'),
							rowKey: attachmentId + '-' + productId
						});
					}
				});
			}

			if (this.imageQueue.length === 0) {
				return;
			}

			this.currentIndex = 0;
			this.isRunning = true;
			this.results = { success: 0, failed: 0, tokens: 0 };

			$('#rfs_alt_bulk_progress').addClass('active').show();
			$('#rfs_alt_completion_summary').hide();
			$('#rfs_alt_progress_log').empty();

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

			const task = this.imageQueue[this.currentIndex];
			const label = `${task.productName} (${task.imageType} #${task.attachment_id})`;

			this.updateProgress(this.currentIndex + 1, this.imageQueue.length, label);

			$.ajax({
				url: rfsAiAltText.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_bulk_generate_alt_text',
					nonce: rfsAiAltText.nonce,
					attachment_id: task.attachment_id,
					product_id: task.product_id
				},
				success: (response) => {
					if (response.success) {
						this.results.success++;
						this.results.tokens += response.data.tokens.total || 0;
						this.logResult(label, true, response.data.alt_text || 'Generated successfully');
						this.updateItemRow(task.rowKey, response.data.alt_text);
					} else {
						this.results.failed++;
						this.logResult(label, false, response.data.message || 'Unknown error');
					}
				},
				error: () => {
					this.results.failed++;
					this.logResult(label, false, 'AJAX error');
				},
				complete: () => {
					this.currentIndex++;
					setTimeout(() => this.processNext(), 500);
				}
			});
		},

		/**
		 * Update a table row after successful generation.
		 */
		updateItemRow(rowKey, altText) {
			const $row = $('tr[data-row-key="' + rowKey + '"]');
			if (!$row.length) {
				return;
			}

			// Update alt text column.
			if (altText) {
				$row.find('.column-alt-text').html(this.truncate(altText, 60));
			}

			// Update status to complete.
			$row.find('.column-status').html('<span style="color: #46b450;">&#10003; Has Alt</span>');

			// Uncheck the item.
			$row.find('.rfs-alt-bulk-item-checkbox').prop('checked', false);

			// Update the item in our local data.
			const parts = rowKey.split('-');
			const attachmentId = parseInt(parts[0], 10);
			const productId = parseInt(parts[1], 10);
			const item = this.items.find(it =>
				it.attachment_id === attachmentId && it.product_id === productId
			);
			if (item) {
				item.current_alt = altText;
				item.has_alt = true;
			}

			// Brief highlight.
			$row.css('background-color', '#d4edda');
			setTimeout(() => $row.css('background-color', ''), 2000);

			this.updateSelection();
		},

		/**
		 * Update progress display
		 */
		updateProgress(current, total, currentName) {
			const percentage = Math.round((current / total) * 100);

			$('#rfs_alt_progress_bar').css('width', percentage + '%');
			$('#rfs_alt_progress_current').text(currentName);
			$('#rfs_alt_progress_count').text(`${current} / ${total} (${percentage}%)`);
			$('#rfs_alt_progress_success').text(this.results.success);
			$('#rfs_alt_progress_failed').text(this.results.failed);
			$('#rfs_alt_progress_tokens').text(this.results.tokens);
		},

		/**
		 * Log result
		 */
		logResult(itemName, success, message) {
			const className = success ? 'success' : 'error';
			const icon = success ? '&#10003;' : '&#10005;';
			const log = $('#rfs_alt_progress_log');

			log.append(`
				<div class="rfs-ai-seo-log-entry ${className}">
					${icon} <strong>${this.escapeHtml(itemName)}</strong> &mdash; ${this.escapeHtml(message)}
				</div>
			`);

			log.scrollTop(log[0].scrollHeight);
		},

		/**
		 * Stop generation
		 */
		stop() {
			this.isRunning = false;
			this.logResult('System', false, 'Generation stopped by user');
			this.complete();
		},

		/**
		 * Complete generation
		 */
		complete() {
			this.isRunning = false;

			$('#rfs_alt_summary_total').text(this.currentIndex);
			$('#rfs_alt_summary_success').text(this.results.success);
			$('#rfs_alt_summary_failed').text(this.results.failed);
			$('#rfs_alt_summary_tokens').text(this.results.tokens);

			$('#rfs_alt_completion_summary').show();
			$('#rfs_alt_bulk_stop_btn').hide();
		},

		/**
		 * Close progress panel
		 */
		closeProgress() {
			$('#rfs_alt_bulk_progress').removeClass('active').hide();
			$('#rfs_alt_bulk_stop_btn').show();
		}
	};

	$(document).ready(() => AltTextBulkGenerator.init());

})(jQuery);
