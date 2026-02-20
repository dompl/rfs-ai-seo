/**
 * RFS AI SEO Bulk Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const BulkGenerator = {
		items: [],
		selectedIds: [],
		currentFilters: {},
		isRunning: false,
		currentIndex: 0,
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
			this.updateCategoryFilter();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Filter button
			$('#rfs_bulk_filter_btn').on('click', () => this.loadItems());

			// Object type change
			$('#rfs_bulk_object_type').on('change', () => this.updateCategoryFilter());

			// Select all checkboxes
			$('#rfs_bulk_select_all, #rfs_bulk_select_all_header').on('change', (e) => this.selectAll(e.target.checked));

			// Individual checkbox change (delegated)
			$(document).on('change', '.rfs-bulk-item-checkbox', (e) => this.updateSelection());

			// Generate buttons
			$('#rfs_bulk_generate_selected').on('click', () => this.startGeneration(false));
			$('#rfs_bulk_generate_all').on('click', () => this.startGeneration(true));

			// Stop button
			$('#rfs_bulk_stop_btn').on('click', () => this.stop());

			// Close button
			$('#rfs_bulk_close_btn').on('click', () => this.closeProgress());

			// Enter key in search
			$('#rfs_bulk_search').on('keypress', (e) => {
				if (e.which === 13) {
					this.loadItems();
				}
			});
		},

		/**
		 * Update category filter visibility
		 */
		updateCategoryFilter() {
			const objectType = $('#rfs_bulk_object_type').val();
			if (objectType === 'product') {
				$('#rfs_category_filter').show();
			} else {
				$('#rfs_category_filter').hide();
			}
		},

		/**
		 * Load items with filters
		 */
		loadItems() {
			const filters = {
				object_type: $('#rfs_bulk_object_type').val(),
				category: $('#rfs_bulk_category').val(),
				meta_status: $('#rfs_bulk_meta_status').val(),
				search: $('#rfs_bulk_search').val(),
				per_page: 100,
				page: 1
			};

			this.currentFilters = filters;

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_seo_get_items',
					nonce: rfsAiSeo.nonce,
					...filters
				},
				success: (response) => {
					if (response.success) {
						this.items = response.data.items;
						this.renderItems(response.data);
					} else {
						alert(response.data.message || rfsAiSeo.strings.error);
					}
				},
				error: () => {
					alert(rfsAiSeo.strings.error);
				}
			});
		},

		/**
		 * Render items table
		 */
		renderItems(data) {
			const tbody = $('#rfs_bulk_items_tbody');
			tbody.empty();

			if (data.items.length === 0) {
				tbody.append(`
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px;">
							${rfsAiSeo.strings.noItems || 'No items found with current filters.'}
						</td>
					</tr>
				`);
				this.updateCounts();
				return;
			}

			data.items.forEach((item) => {
				const statusDots = this.getStatusDots(item.status);
				const thumbnail = item.thumbnail ?
					`<img src="${item.thumbnail}" alt="${item.name}">` :
					'<span class="dashicons dashicons-category"></span>';

				const title = item.current_title ?
					this.truncate(item.current_title, 50) :
					'<span style="color: #a7aaad;">—</span>';

				const description = item.current_description ?
					this.truncate(item.current_description, 60) :
					'<span style="color: #a7aaad;">—</span>';

				tbody.append(`
					<tr data-item-id="${item.id}">
						<th class="check-column">
							<input type="checkbox" class="rfs-bulk-item-checkbox" value="${item.id}" data-name="${item.name}">
						</th>
						<td class="column-thumbnail">${thumbnail}</td>
						<td class="column-name">
							<a href="${item.edit_url}" target="_blank">${item.name}</a>
						</td>
						<td class="column-title">${title}</td>
						<td class="column-description">${description}</td>
						<td class="column-status">${statusDots}</td>
					</tr>
				`);
			});

			this.updateCounts(data.total);
			this.selectedIds = [];
			this.updateSelection();
		},

		/**
		 * Get status dots HTML
		 */
		getStatusDots(status) {
			const filled = status === 'complete' ? 2 : (status === 'partial' ? 1 : 0);
			return `
				<div class="rfs-ai-seo-status">
					<span class="rfs-ai-seo-status-dot ${filled >= 1 ? 'filled' : ''}"></span>
					<span class="rfs-ai-seo-status-dot ${filled >= 2 ? 'filled' : ''}"></span>
				</div>
			`;
		},

		/**
		 * Truncate text
		 */
		truncate(text, length) {
			return text.length > length ? text.substring(0, length) + '...' : text;
		},

		/**
		 * Select/deselect all
		 */
		selectAll(checked) {
			$('.rfs-bulk-item-checkbox').prop('checked', checked);
			this.updateSelection();
		},

		/**
		 * Update selection state
		 */
		updateSelection() {
			this.selectedIds = $('.rfs-bulk-item-checkbox:checked').map(function() {
				return $(this).val();
			}).get();

			const count = this.selectedIds.length;
			$('#rfs_bulk_selected_count').text(
				count > 0 ? `Selected: ${count}` : ''
			);

			$('#rfs_bulk_generate_selected').prop('disabled', count === 0);
			$('#rfs_bulk_generate_all').prop('disabled', this.items.length === 0);

			// Update select all checkbox state
			const totalCheckboxes = $('.rfs-bulk-item-checkbox').length;
			const checkedCheckboxes = $('.rfs-bulk-item-checkbox:checked').length;
			$('#rfs_bulk_select_all, #rfs_bulk_select_all_header').prop('checked',
				totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes
			);
		},

		/**
		 * Update counts display
		 */
		updateCounts(total) {
			if (total !== undefined) {
				$('#rfs_bulk_total_count').text(`Total items: ${total}`);
			} else {
				$('#rfs_bulk_total_count').text('');
			}
		},

		/**
		 * Start generation
		 */
		startGeneration(allFiltered) {
			let itemsToGenerate;

			if (allFiltered) {
				itemsToGenerate = this.items.map(item => item.id);
			} else {
				itemsToGenerate = this.selectedIds;
			}

			if (itemsToGenerate.length === 0) {
				return;
			}

			this.itemsToGenerate = itemsToGenerate;
			this.currentIndex = 0;
			this.isRunning = true;
			this.results = { success: 0, failed: 0, tokens: 0 };

			// Show progress panel
			$('#rfs_bulk_progress').addClass('active');
			$('#rfs_completion_summary').hide();
			$('#rfs_progress_log').empty();

			// Start processing
			this.processNext();
		},

		/**
		 * Process next item
		 */
		processNext() {
			if (!this.isRunning || this.currentIndex >= this.itemsToGenerate.length) {
				this.complete();
				return;
			}

			const itemId = this.itemsToGenerate[this.currentIndex];
			const item = this.items.find(i => i.id == itemId);
			const itemName = item ? item.name : `ID: ${itemId}`;

			// Update progress
			this.updateProgress(this.currentIndex + 1, this.itemsToGenerate.length, itemName);

			// Generate
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_seo_bulk_generate',
					nonce: rfsAiSeo.nonce,
					object_id: itemId,
					object_type: this.currentFilters.object_type
				},
				success: (response) => {
					if (response.success) {
						this.results.success++;
						this.results.tokens += response.data.tokens.total || 0;
						this.logResult(itemName, true, 'Generated successfully');
						this.updateItemRow(itemId, response.data);
					} else {
						this.results.failed++;
						this.logResult(itemName, false, response.data.message || 'Unknown error');
					}
				},
				error: () => {
					this.results.failed++;
					this.logResult(itemName, false, 'AJAX error');
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

			$('#rfs_progress_bar').css('width', percentage + '%');
			$('#rfs_progress_current').text(currentName);
			$('#rfs_progress_count').text(`${current} / ${total} (${percentage}%)`);
			$('#rfs_progress_success').text(this.results.success);
			$('#rfs_progress_failed').text(this.results.failed);
			$('#rfs_progress_tokens').text(this.results.tokens);
		},

		/**
		 * Log result
		 */
		logResult(itemName, success, message) {
			const className = success ? 'success' : 'error';
			const icon = success ? '✓' : '✗';
			const log = $('#rfs_progress_log');

			log.append(`
				<div class="rfs-ai-seo-log-entry ${className}">
					${icon} <strong>${itemName}</strong> — ${message}
				</div>
			`);

			// Scroll to bottom
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
		 * Update a table row after successful generation.
		 */
		updateItemRow(itemId, data) {
			const $row = $('tr[data-item-id="' + itemId + '"]');
			if (!$row.length) {
				return;
			}

			// Update title column.
			if (data.title) {
				$row.find('.column-title').html(this.truncate(data.title, 50));
			}

			// Update description column.
			if (data.description) {
				$row.find('.column-description').html(this.truncate(data.description, 60));
			}

			// Update status dots to "complete".
			$row.find('.column-status').html(this.getStatusDots('complete'));

			// Uncheck the item.
			$row.find('.rfs-bulk-item-checkbox').prop('checked', false);

			// Brief highlight to draw attention.
			$row.css('background-color', '#d4edda');
			setTimeout(() => $row.css('background-color', ''), 2000);

			this.updateSelection();
		},

		/**
		 * Complete generation
		 */
		complete() {
			this.isRunning = false;

			// Update summary
			$('#rfs_summary_total').text(this.currentIndex);
			$('#rfs_summary_success').text(this.results.success);
			$('#rfs_summary_failed').text(this.results.failed);
			$('#rfs_summary_tokens').text(this.results.tokens);

			// Show summary
			$('#rfs_completion_summary').show();
			$('#rfs_bulk_stop_btn').hide();
		},

		/**
		 * Close progress panel
		 */
		closeProgress() {
			$('#rfs_bulk_progress').removeClass('active');
			$('#rfs_bulk_stop_btn').show();
		}
	};

	// Initialize on document ready
	$(document).ready(() => BulkGenerator.init());

})(jQuery);
