/**
 * RFS AI Bulk Alt Text Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const AltTextBulkGenerator = {
		items: [],
		selectedIds: [],
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
		 * Render items table
		 */
		renderItems(data) {
			const tbody = $('#rfs_alt_bulk_items_tbody');
			tbody.empty();

			if (data.items.length === 0) {
				tbody.append(`
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px;">
							${rfsAiAltText.strings.noItems || 'No products found with current filters.'}
						</td>
					</tr>
				`);
				this.updateCounts();
				return;
			}

			data.items.forEach((item) => {
				const thumbnail = item.thumbnail ?
					`<img src="${item.thumbnail}" alt="${this.escapeHtml(item.name)}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` :
					'<span class="dashicons dashicons-products"></span>';

				let status;
				if (item.missing_alt === 0) {
					status = '<span style="color: #46b450;">&#10003; Complete</span>';
				} else if (item.missing_alt === item.total_images) {
					status = '<span style="color: #dc3232;">&#10005; All Missing</span>';
				} else {
					status = '<span style="color: #dba617;">&#9888; Partial</span>';
				}

				tbody.append(`
					<tr>
						<th class="check-column">
							<input type="checkbox" class="rfs-alt-bulk-item-checkbox" value="${item.id}" data-name="${this.escapeHtml(item.name)}">
						</th>
						<td class="column-thumbnail">${thumbnail}</td>
						<td class="column-name">
							<a href="${item.edit_url}" target="_blank">${this.escapeHtml(item.name)}</a>
						</td>
						<td class="column-total-images">${item.total_images}</td>
						<td class="column-missing-alt">${item.missing_alt}</td>
						<td class="column-status">${status}</td>
					</tr>
				`);
			});

			this.updateCounts(data.total);
			this.selectedIds = [];
			this.updateSelection();
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
			this.selectedIds = $('.rfs-alt-bulk-item-checkbox:checked').map(function() {
				return $(this).val();
			}).get();

			const count = this.selectedIds.length;
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
				$('#rfs_alt_bulk_total_count').text(`Total products: ${total}`);
			} else {
				$('#rfs_alt_bulk_total_count').text('');
			}
		},

		/**
		 * Start generation — flatten products into individual image tasks.
		 */
		startGeneration(allFiltered) {
			let productIds;

			if (allFiltered) {
				productIds = this.items.map(item => item.id);
			} else {
				productIds = this.selectedIds.map(id => parseInt(id, 10));
			}

			if (productIds.length === 0) {
				alert('No products selected.');
				return;
			}

			// Flatten into image queue: each entry = { attachment_id, product_id, productName }.
			this.imageQueue = [];
			productIds.forEach(pid => {
				const item = this.items.find(i => i.id === pid || i.id === String(pid));
				if (!item || !item.images) return;

				item.images.forEach(img => {
					if (!img.has_alt) {
						this.imageQueue.push({
							attachment_id: img.attachment_id,
							product_id: item.id,
							productName: item.name,
							imageType: img.type
						});
					}
				});
			});

			if (this.imageQueue.length === 0) {
				alert('No images missing alt text in the selected products.');
				return;
			}

			const message = `Generate alt text for ${this.imageQueue.length} images across ${productIds.length} products?`;
			if (!confirm(message)) {
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
						this.logResult(label, true, 'Generated successfully');
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
			if (confirm('Stop bulk generation?')) {
				this.isRunning = false;
				this.logResult('System', false, 'Generation stopped by user');
				this.complete();
			}
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

			this.loadItems();
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
