/**
 * RFS AI SEO Admin JavaScript
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin functionality
	 */
	$(document).ready(function() {
		// Test Connection Button
		$('#rfs_ai_seo_test_connection').on('click', function(e) {
			e.preventDefault();
			testConnection();
		});

		// Meta Box functionality
		initMetaBox();
	});

	/**
	 * Test OpenAI API connection
	 */
	function testConnection() {
		var $button = $('#rfs_ai_seo_test_connection');
		var $spinner = $('#rfs_ai_seo_test_spinner');
		var $result = $('#rfs_ai_seo_test_result');

		// Clear previous results
		$result.html('');

		// Show spinner, disable button
		$spinner.addClass('is-active');
		$button.prop('disabled', true);

		// Make AJAX request
		$.ajax({
			url: rfsAiSeo.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rfs_ai_seo_test_connection',
				nonce: rfsAiSeo.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error inline"><p>' + rfsAiSeo.strings.error + '</p></div>');
			},
			complete: function() {
				// Hide spinner, enable button
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
			}
		});
	}

	/**
	 * Test Stripe Connection Button
	 */
	$('#rfs-test-stripe').on('click', function(e) {
		e.preventDefault();
		var $button = $(this);
		var $spinner = $('#rfs-test-stripe-spinner');
		var $result = $('#rfs-test-stripe-result');

		$result.html('');
		$spinner.addClass('is-active');
		$button.prop('disabled', true);

		$.ajax({
			url: rfsAiSeo.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rfs_ai_seo_test_stripe',
				nonce: rfsAiSeo.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error inline"><p>' + rfsAiSeo.strings.error + '</p></div>');
			},
			complete: function() {
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
			}
		});
	});

	/**
	 * Initialize Meta Box functionality
	 */
	function initMetaBox() {
		if (!$('#rfs_ai_seo_generate_btn').length) {
			return; // Meta box not present
		}

		// Generate button
		$('#rfs_ai_seo_generate_btn').on('click', generateMeta);

		// Save button
		$('#rfs_ai_seo_save_btn').on('click', saveMeta);

		// Regenerate button
		$('#rfs_ai_seo_regenerate_btn').on('click', generateMeta);

		// Cancel button
		$('#rfs_ai_seo_cancel_btn').on('click', cancelPreview);

		// Character counters
		$('#rfs_ai_seo_preview_title').on('input', function() {
			updateCharCounter($(this), '#rfs_ai_seo_title_counter', '#rfs_ai_seo_title_max');
		});

		$('#rfs_ai_seo_preview_description').on('input', function() {
			updateCharCounter($(this), '#rfs_ai_seo_desc_counter', '#rfs_ai_seo_desc_max');
		});
	}

	/**
	 * Generate meta with AI
	 */
	function generateMeta() {
		var $button = $('#rfs_ai_seo_generate_btn');
		var $spinner = $('#rfs_ai_seo_generate_spinner');
		var objectId = $('#rfs_ai_seo_object_id').val();
		var objectType = $('#rfs_ai_seo_object_type').val();

		// Show spinner, disable button
		$spinner.addClass('is-active');
		$button.prop('disabled', true);

		// Make AJAX request
		$.ajax({
			url: rfsAiSeo.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rfs_ai_seo_generate',
				nonce: rfsAiSeo.nonce,
				object_id: objectId,
				object_type: objectType
			},
			success: function(response) {
				if (response.success) {
					showPreview(response.data);
				} else {
					alert(response.data.message || rfsAiSeo.strings.error);
				}
			},
			error: function() {
				alert(rfsAiSeo.strings.error);
			},
			complete: function() {
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
			}
		});
	}

	/**
	 * Show preview panel with generated meta
	 */
	function showPreview(data) {
		// Populate fields
		$('#rfs_ai_seo_preview_title').val(data.title);
		$('#rfs_ai_seo_preview_description').val(data.description);

		// Update character counters
		$('#rfs_ai_seo_preview_title').trigger('input');
		$('#rfs_ai_seo_preview_description').trigger('input');

		// Show token info
		if (data.tokens) {
			$('#rfs_ai_seo_token_info').html(
				'Tokens used: ' + data.tokens.total + ' (prompt: ' + data.tokens.prompt + ', completion: ' + data.tokens.completion + ')'
			);
		}

		// Show preview panel
		$('#rfs_ai_seo_preview').addClass('active');
	}

	/**
	 * Save meta to Yoast
	 */
	function saveMeta() {
		var $button = $('#rfs_ai_seo_save_btn');
		var $spinner = $('#rfs_ai_seo_save_spinner');
		var objectId = $('#rfs_ai_seo_object_id').val();
		var objectType = $('#rfs_ai_seo_object_type').val();
		var title = $('#rfs_ai_seo_preview_title').val();
		var description = $('#rfs_ai_seo_preview_description').val();

		if (!title || !description) {
			alert('Please fill in both title and description.');
			return;
		}

		// Show spinner, disable button
		$spinner.addClass('is-active');
		$button.prop('disabled', true);

		// Make AJAX request
		$.ajax({
			url: rfsAiSeo.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rfs_ai_seo_save_meta',
				nonce: rfsAiSeo.nonce,
				object_id: objectId,
				object_type: objectType,
				title: title,
				description: description
			},
			success: function(response) {
				if (response.success) {
					// Update current meta display
					$('.rfs-ai-seo-current-meta .value').first().removeClass('not-set').text(title);
					$('.rfs-ai-seo-current-meta .value').last().removeClass('not-set').text(description);

					// Hide preview
					cancelPreview();

					alert(response.data.message);
				} else {
					alert(response.data.message || rfsAiSeo.strings.error);
				}
			},
			error: function() {
				alert(rfsAiSeo.strings.error);
			},
			complete: function() {
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
			}
		});
	}

	/**
	 * Cancel preview and hide panel
	 */
	function cancelPreview() {
		$('#rfs_ai_seo_preview').removeClass('active');
		$('#rfs_ai_seo_preview_title').val('');
		$('#rfs_ai_seo_preview_description').val('');
		$('#rfs_ai_seo_token_info').html('');
	}

	/**
	 * Update character counter
	 */
	function updateCharCounter($input, counterSelector, maxSelector) {
		var length = $input.val().length;
		var max = parseInt($(maxSelector).val());
		var $counter = $(counterSelector);

		// Update count text
		$counter.find('.count').text(length + '/' + max);

		// Update color class
		$counter.removeClass('green yellow red');

		if (length <= Math.floor(max * 0.9)) {
			$counter.addClass('green');
		} else if (length <= max) {
			$counter.addClass('yellow');
		} else {
			$counter.addClass('red');
		}
	}

})(jQuery);
