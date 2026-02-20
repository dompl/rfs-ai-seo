/**
 * RFS AI SEO Credits Page JavaScript
 *
 * @package RFS_AI_SEO
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		$('.rfs-credits-buy-btn').on('click', function() {
			var $button = $(this);
			var packageId = $button.data('package');

			// Disable button and show loading state.
			$button.prop('disabled', true).text(rfsAiCredits.strings.processing);

			$.ajax({
				url: rfsAiCredits.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rfs_ai_seo_create_checkout',
					nonce: rfsAiCredits.nonce,
					package_id: packageId
				},
				success: function(response) {
					if (response.success && response.data.checkout_url) {
						window.location.href = response.data.checkout_url;
					} else {
						alert(response.data.message || rfsAiCredits.strings.error);
						$button.prop('disabled', false).text(rfsAiCredits.strings.buyNow);
					}
				},
				error: function() {
					alert(rfsAiCredits.strings.error);
					$button.prop('disabled', false).text(rfsAiCredits.strings.buyNow);
				}
			});
		});
	});

})(jQuery);
