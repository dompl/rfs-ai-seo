/**
 * RFS AI Bulk Short Description Generator
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	const ShortDescriptionBulkGenerator = {
		items: [],
		selectedIds: [],
		currentFilters: {},
		isRunning: false,
		currentIndex: 0,
		itemsToGenerate: [],
		results: {
			success: 0,
			failed: 0,
			tokens: 0,
			skipped: 0,
			charged: 0
		},
		halted: false,

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
			// Filter button
			$('#rfs_short_desc_bulk_filter_btn').on('click', () => this.loadItems());

			// Select all checkboxes
			$('#rfs_short_desc_bulk_select_all, #rfs_short_desc_bulk_select_all_header').on('change', (e) => this.selectAll(e.target.checked));

			// Individual checkbox change (delegated)
			$(document).on('change', '.rfs-short-desc-bulk-item-checkbox', () => this.updateSelection());

			// Generate buttons
			$('#rfs_short_desc_bulk_generate_selected').on('click', () => this.startGeneration(false));
			$('#rfs_short_desc_bulk_generate_all').on('click', () => this.startGeneration(true));

			// Stop button
			$('#rfs_short_desc_bulk_stop_btn').on('click', () => this.stop());

			// Close button
			$('#rfs_short_desc_bulk_close_btn').on('click', () => this.closeProgress());

			// Resume button (after an out-of-credit or unavailable halt)
			$('#rfs_short_desc_bulk_resume_btn').on('click', () => this.resume());

			// Enter key in search
			$('#rfs_short_desc_bulk_search').on('keypress', (e) => {
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
				category: $('#rfs_short_desc_bulk_category').val(),
				status: $('#rfs_short_desc_bulk_status').val(),
				search: $('#rfs_short_desc_bulk_search').val(),
				per_page: 100,
				page: 1
			};

			this.currentFilters = filters;

			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_get_short_description_items',
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
			const tbody = $('#rfs_short_desc_bulk_items_tbody');
			tbody.empty();

			if (data.items.length === 0) {
				tbody.append(`
					<tr>
						<td colspan="5" style="text-align: center; padding: 40px;">
							${rfsAiSeo.strings.noItems || 'No products found with current filters.'}
						</td>
					</tr>
				`);
				this.updateCounts();
				return;
			}

			data.items.forEach((item) => {
				const thumbnail = item.thumbnail ?
					`<img src="${item.thumbnail}" alt="${item.name}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` :
					'<span class="dashicons dashicons-products"></span>';

				const description = item.current_description ?
					this.truncate(item.current_description, 80) :
					'<span style="color: #dc3232;">Empty</span>';

				const status = item.has_description ?
					'<span style="color: #46b450;">✓ Has</span>' :
					'<span style="color: #dc3232;">✗ Empty</span>';

				tbody.append(`
					<tr>
						<th class="check-column">
							<input type="checkbox" class="rfs-short-desc-bulk-item-checkbox" value="${item.id}" data-name="${item.name}">
						</th>
						<td class="column-thumbnail">${thumbnail}</td>
						<td class="column-name">
							<a href="${item.edit_url}" target="_blank">${item.name}</a>
						</td>
						<td class="column-description">${description}</td>
						<td class="column-status">${status}</td>
					</tr>
				`);
			});

			this.updateCounts(data.total);
			this.selectedIds = [];
			this.updateSelection();
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
			$('.rfs-short-desc-bulk-item-checkbox').prop('checked', checked);
			this.updateSelection();
		},

		/**
		 * Update selection state
		 */
		updateSelection() {
			this.selectedIds = $('.rfs-short-desc-bulk-item-checkbox:checked').map(function() {
				return $(this).val();
			}).get();

			const count = this.selectedIds.length;
			$('#rfs_short_desc_bulk_selected_count').text(
				count > 0 ? `Selected: ${count}` : ''
			);

			$('#rfs_short_desc_bulk_generate_selected').prop('disabled', count === 0);
			$('#rfs_short_desc_bulk_generate_all').prop('disabled', this.items.length === 0);

			// Update select all checkbox state
			const totalCheckboxes = $('.rfs-short-desc-bulk-item-checkbox').length;
			const checkedCheckboxes = $('.rfs-short-desc-bulk-item-checkbox:checked').length;
			$('#rfs_short_desc_bulk_select_all, #rfs_short_desc_bulk_select_all_header').prop('checked',
				totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes
			);
		},

		/**
		 * Update counts display
		 */
		updateCounts(total) {
			if (total !== undefined) {
				$('#rfs_short_desc_bulk_total_count').text(`Total products: ${total}`);
			} else {
				$('#rfs_short_desc_bulk_total_count').text('');
			}
		},

		/**
		 * Start generation
		 */
		startGeneration(allFiltered) {
			let itemsToGenerate;
			let message;

			if (allFiltered) {
				// Generate for all items
				itemsToGenerate = this.items.map(item => item.id);
				message = `Generate short descriptions for all ${itemsToGenerate.length} filtered products?\n\nThis will save the original short descriptions as backups.`;
			} else {
				// Generate for selected
				itemsToGenerate = this.selectedIds;
				message = `Generate short descriptions for ${itemsToGenerate.length} selected products?\n\nThis will save the original short descriptions as backups.`;
			}

			if (itemsToGenerate.length === 0) {
				alert('No products to generate.');
				return;
			}

			if (!confirm(message)) {
				return;
			}

			this.itemsToGenerate = itemsToGenerate;
			this.currentIndex = 0;
			this.isRunning = true;
			this.halted = false;
			this.results = { success: 0, failed: 0, tokens: 0, skipped: 0, charged: 0 };

			// Show progress panel
			$('#rfs_short_desc_bulk_progress').addClass('active').show();
			$('#rfs_short_desc_completion_summary').hide();
			$('#rfs_short_desc_progress_log').empty();
			$('#rfs_short_desc_bulk_out_of_credit, #rfs_short_desc_bulk_paused').hide();
			$('#rfs_short_desc_bulk_stop_btn').show();
			$('#rfs_short_desc_bulk_total').text('');

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

			const productId = this.itemsToGenerate[this.currentIndex];
			const item = this.items.find(i => i.id == productId);
			const itemName = item ? item.name : `ID: ${productId}`;

			// Update progress
			this.updateProgress(this.currentIndex + 1, this.itemsToGenerate.length, itemName);

			// Generate
			$.ajax({
				url: rfsAiSeo.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_bulk_generate_short_description',
					nonce: rfsAiSeo.nonce,
					product_id: productId,
					tone: $('#rfs_short_desc_bulk_tone').val(),
					sentences: $('#rfs_short_desc_bulk_sentences').val(),
					skip_existing: $('#rfs_short_desc_bulk_skip_existing').is(':checked') ? '1' : '0'
				},
				success: (response) => {
					if (response.success && response.data.skipped) {
						this.results.skipped++;
						this.logResult(itemName, true, response.data.message || 'Already has content');
						return;
					}

					if (response.success) {
						this.results.success++;
						this.results.tokens += response.data.tokens.total || 0;
						this.results.charged += response.data.charged_gbp || 0;
						$('#rfs_short_desc_bulk_total').text('Spent: £' + this.results.charged.toFixed(2));
						this.logResult(itemName, true, 'Generated successfully');
						return;
					}

					const code = response.data && response.data.code;

					if (code === 'ats_portal_out_of_credit') {
						this.halted = true;
						this.isRunning = false;
						this.logResult(itemName, false, response.data.message || 'Out of AI credit');
						$('#rfs_short_desc_bulk_out_of_credit').show();
						return;
					}

					if (code === 'ats_portal_unavailable') {
						this.halted = true;
						this.isRunning = false;
						this.logResult(itemName, false, response.data.message || 'AI service unavailable');
						$('#rfs_short_desc_bulk_paused').show();
						return;
					}

					this.results.failed++;
					this.logResult(itemName, false, (response.data && response.data.message) || 'Unknown error');
				},
				error: () => {
					this.results.failed++;
					this.logResult(itemName, false, 'AJAX error');
				},
				complete: () => {
					if (this.halted) {
						return;
					}
					this.currentIndex++;
					// Continue to next item
					setTimeout(() => this.processNext(), 500);
				}
			});
		},

		/**
		 * Resume after an out-of-credit or unavailable halt
		 */
		resume() {
			$('#rfs_short_desc_bulk_out_of_credit, #rfs_short_desc_bulk_paused').hide();
			this.halted = false;
			this.isRunning = true;
			this.processNext();
		},

		/**
		 * Update progress display
		 */
		updateProgress(current, total, currentName) {
			const percentage = Math.round((current / total) * 100);

			$('#rfs_short_desc_progress_bar').css('width', percentage + '%');
			$('#rfs_short_desc_progress_current').text(currentName);
			$('#rfs_short_desc_progress_count').text(`${current} / ${total} (${percentage}%)`);
			$('#rfs_short_desc_progress_success').text(this.results.success);
			$('#rfs_short_desc_progress_failed').text(this.results.failed);
			$('#rfs_short_desc_progress_tokens').text(this.results.tokens);
		},

		/**
		 * Log result
		 */
		logResult(itemName, success, message) {
			const className = success ? 'success' : 'error';
			const icon = success ? '✓' : '✗';
			const log = $('#rfs_short_desc_progress_log');

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
		 * Complete generation
		 */
		complete() {
			this.isRunning = false;

			// Update summary
			$('#rfs_short_desc_summary_total').text(this.currentIndex);
			$('#rfs_short_desc_summary_success').text(this.results.success);
			$('#rfs_short_desc_summary_skipped').text(this.results.skipped);
			$('#rfs_short_desc_summary_failed').text(this.results.failed);
			$('#rfs_short_desc_summary_tokens').text(this.results.tokens);

			// Show summary
			$('#rfs_short_desc_completion_summary').show();
			$('#rfs_short_desc_bulk_stop_btn').hide();

			// Reload items to show updated status
			this.loadItems();
		},

		/**
		 * Close progress panel
		 */
		closeProgress() {
			$('#rfs_short_desc_bulk_progress').removeClass('active').hide();
			$('#rfs_short_desc_bulk_stop_btn').show();
		}
	};

	// Initialize on document ready
	$(document).ready(() => ShortDescriptionBulkGenerator.init());

})(jQuery);
