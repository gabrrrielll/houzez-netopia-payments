/**
 * Frontend JavaScript for Netopia Payments.
 *
 * @package    Houzez_Netopia
 * @subpackage Houzez_Netopia/assets/js
 */

(function($) {
	'use strict';

	/**
	 * Netopia Payments handler.
	 */
	var HouzezNetopia = {
		
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.formatCardNumber();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Show/hide card form when Netopia is selected
			$(document).on('change', '.payment-netopia, input[name="houzez_payment_type"][value="netopia"]', function() {
				if ($(this).is(':checked')) {
					$('.netopia-card-form').slideDown();
				} else {
					$('.netopia-card-form').slideUp();
				}
			});

			// Handle membership payment using event delegation with capture phase
			// This ensures our handler runs before theme's handler
			$(document).on('click', '#houzez_complete_membership', function(e) {
				var paymentType = $('input[name="houzez_payment_type"]:checked').val();
				
				if (paymentType === 'netopia') {
					e.preventDefault();
					e.stopImmediatePropagation();
					HouzezNetopia.processMembershipPayment();
					return false;
				}
			});

			// Handle listing payment - intercept click before theme's handler processes it
			// Use native addEventListener with capture:true to intercept in capture phase
			var attachOrderHandler = function() {
				var orderBtn = document.getElementById('houzez_complete_order');
				if (orderBtn) {
					// Remove any existing Netopia handlers
					orderBtn.removeEventListener('click', HouzezNetopia.handleOrderClick, true);
					
					// Add our handler in capture phase (runs before bubble phase handlers)
					orderBtn.addEventListener('click', HouzezNetopia.handleOrderClick, true);
					return true;
				}
				return false;
			};
			
			// Try to attach immediately
			if (!attachOrderHandler()) {
				// If button doesn't exist yet, wait for it and try again
				var checkInterval = setInterval(function() {
					if (attachOrderHandler()) {
						clearInterval(checkInterval);
					}
				}, 100);
				
				// Stop checking after 5 seconds
				setTimeout(function() {
					clearInterval(checkInterval);
				}, 5000);
			}

			// Format card number input
			$(document).on('input', '#netopia_card_number, #netopia_card_number_listing', function() {
				var value = $(this).val().replace(/\s/g, '');
				var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
				$(this).val(formattedValue);
			});
		},

		/**
		 * Handle order button click (for listing payments).
		 */
		handleOrderClick: function(e) {
			var paymentType = $('input[name="houzez_payment_type"]:checked').val();
			
			if (paymentType === 'netopia') {
				e.preventDefault();
				e.stopImmediatePropagation();
				HouzezNetopia.processListingPayment();
				return false;
			}
		},

		/**
		 * Format card number input.
		 */
		formatCardNumber: function() {
			$('#netopia_card_number, #netopia_card_number_listing').on('input', function() {
				var value = $(this).val().replace(/\s/g, '');
				var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
				$(this).val(formattedValue);
			});
		},

		/**
		 * Process membership payment.
		 */
		processMembershipPayment: function() {
			var $button = $('#houzez_complete_membership');
			var packageId = $('input[name="houzez_package_id"]').val();
			
			// Get card data
			var cardData = {
				card_number: $('#netopia_card_number').val().replace(/\s/g, ''),
				exp_month: $('#netopia_exp_month').val(),
				exp_year: $('#netopia_exp_year').val(),
				cvv: $('#netopia_cvv').val()
			};

			// Validate card data
			if (!HouzezNetopia.validateCardData(cardData)) {
				return;
			}

			// Show loading
			$button.prop('disabled', true).html('<span class="spinner"></span> Processing...');

			// Send AJAX request
			$.ajax({
				url: houzez_netopia.ajax_url,
				type: 'POST',
				data: {
					action: 'houzez_netopia_package_payment',
					package_id: packageId,
					card_number: cardData.card_number,
					exp_month: cardData.exp_month,
					exp_year: cardData.exp_year,
					cvv: cardData.cvv,
					houzez_register_security2: houzez_netopia.nonce
				},
				success: function(response) {
					// Reset button state
					$button.prop('disabled', false).html('Complete Membership');
					
					// Check if response is valid
					if (!response) {
						HouzezNetopia.showError('Invalid response from server. Please try again.');
						return;
					}
					
					if (response.success && response.data) {
						if (response.data.requires_3ds) {
							// Redirect to 3D Secure
							HouzezNetopia.handle3DSecure(response.data);
						} else {
							// Payment completed - use redirect_url from response or fallback to success_url
							var redirectUrl = response.data.redirect_url || houzez_netopia.success_url;
							window.location.href = redirectUrl;
						}
					} else {
						// Handle error response
						var errorMessage = 'Payment failed. Please try again.';
						if (response && response.data && response.data.message) {
							errorMessage = response.data.message;
						} else if (response && response.message) {
							errorMessage = response.message;
						}
						HouzezNetopia.showError(errorMessage);
					}
				},
				error: function(xhr, status, error) {
					var errorMessage = 'An error occurred. Please try again.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMessage = xhr.responseJSON.data.message;
					} else if (xhr.responseText) {
						try {
							var errorResponse = JSON.parse(xhr.responseText);
							if (errorResponse.data && errorResponse.data.message) {
								errorMessage = errorResponse.data.message;
							}
						} catch (e) {
							// Keep default error message
						}
					}
					HouzezNetopia.showError(errorMessage);
					$button.prop('disabled', false).html('Complete Membership');
				}
			});
		},

		/**
		 * Process listing payment.
		 */
		processListingPayment: function() {
			var $button = $('#houzez_complete_order');
			var propertyId = $('#houzez_property_id').val();
			var isFeatured = $('#featured_pay').val() === '1' || $('.prop_featured').is(':checked');
			var isUpgrade = $('#is_upgrade').val() === '1';
			
			// Get card data
			var cardData = {
				card_number: $('#netopia_card_number_listing').val().replace(/\s/g, ''),
				exp_month: $('#netopia_exp_month_listing').val(),
				exp_year: $('#netopia_exp_year_listing').val(),
				cvv: $('#netopia_cvv_listing').val()
			};

			// Validate card data
			if (!HouzezNetopia.validateCardData(cardData)) {
				return;
			}

			// Show loading
			$button.prop('disabled', true).html('<span class="spinner"></span> Processing...');

			// Send AJAX request
			$.ajax({
				url: houzez_netopia.ajax_url,
				type: 'POST',
				data: {
					action: 'houzez_netopia_listing_payment',
					property_id: propertyId,
					is_featured: isFeatured ? '1' : '0',
					is_upgrade: isUpgrade ? '1' : '0',
					card_number: cardData.card_number,
					exp_month: cardData.exp_month,
					exp_year: cardData.exp_year,
					cvv: cardData.cvv,
					houzez_register_security2: houzez_netopia.nonce
				},
				success: function(response) {
					// Reset button state
					$button.prop('disabled', false).html('Complete Payment');
					
					// Check if response is valid
					if (!response) {
						HouzezNetopia.showError('Invalid response from server. Please try again.');
						return;
					}
					
					if (response.success && response.data) {
						if (response.data.requires_3ds) {
							// Redirect to 3D Secure
							HouzezNetopia.handle3DSecure(response.data);
						} else {
							// Payment completed - use redirect_url from response or fallback to success_url
							var redirectUrl = response.data.redirect_url || houzez_netopia.success_url;
							window.location.href = redirectUrl;
						}
					} else {
						// Handle error response
						var errorMessage = 'Payment failed. Please try again.';
						if (response && response.data && response.data.message) {
							errorMessage = response.data.message;
						} else if (response && response.message) {
							errorMessage = response.message;
						}
						HouzezNetopia.showError(errorMessage);
					}
				},
				error: function(xhr, status, error) {
					var errorMessage = 'An error occurred. Please try again.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMessage = xhr.responseJSON.data.message;
					} else if (xhr.responseText) {
						try {
							var errorResponse = JSON.parse(xhr.responseText);
							if (errorResponse.data && errorResponse.data.message) {
								errorMessage = errorResponse.data.message;
							}
						} catch (e) {
							// Keep default error message
						}
					}
					HouzezNetopia.showError(errorMessage);
					$button.prop('disabled', false).html('Complete Payment');
				}
			});
		},

		/**
		 * Handle 3D Secure redirect.
		 */
		handle3DSecure: function(data) {
			// Create form for 3D Secure
			var form = $('<form>', {
				method: 'POST',
				action: data.auth_url
			});

			// Add form fields
			$.each(data.form_data, function(key, value) {
				form.append($('<input>', {
					type: 'hidden',
					name: key,
					value: value
				}));
			});

			// Add order ID
			form.append($('<input>', {
				type: 'hidden',
				name: 'order_id',
				value: data.order_id
			}));

			// Submit form
			$('body').append(form);
			form.submit();
		},

		/**
		 * Validate card data.
		 */
		validateCardData: function(cardData) {
			if (!cardData.card_number || cardData.card_number.length < 13) {
				this.showError('Please enter a valid card number.');
				return false;
			}

			if (!cardData.exp_month || !cardData.exp_year) {
				this.showError('Please select card expiration date.');
				return false;
			}

			if (!cardData.cvv || cardData.cvv.length < 3) {
				this.showError('Please enter a valid CVV.');
				return false;
			}

			return true;
		},

		/**
		 * Show error message.
		 */
		showError: function(message) {
			// Try to use Houzez notification system if available
			if (typeof houzez !== 'undefined' && houzez.Core && houzez.Core.util && typeof houzez.Core.util.showMessage === 'function') {
				houzez.Core.util.showMessage(message, 'error');
			} else {
				// Fallback: Create custom error message
				HouzezNetopia.showCustomError(message);
			}
		},

		/**
		 * Show custom error message.
		 */
		showCustomError: function(message) {
			// Remove any existing error messages
			$('.netopia-error-message').remove();

			// Create error message element
			var errorHtml = '<div class="netopia-error-message alert alert-danger" role="alert" style="margin-top: 15px; padding: 12px; border-radius: 4px; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">' +
				'<strong>Error:</strong> ' + message +
				'</div>';

			// Insert error message before the payment form or button
			var $form = $('.netopia-card-form');
			if ($form.length) {
				$form.before(errorHtml);
			} else {
				// Fallback: insert before Complete Payment button
				var $button = $('#houzez_complete_order, #houzez_complete_membership');
				if ($button.length) {
					$button.before(errorHtml);
				} else {
					// Last resort: use alert
					alert(message);
					return;
				}
			}

			// Scroll to error message
			$('html, body').animate({
				scrollTop: $('.netopia-error-message').offset().top - 100
			}, 500);

			// Auto-hide after 10 seconds
			setTimeout(function() {
				$('.netopia-error-message').fadeOut(function() {
					$(this).remove();
				});
			}, 10000);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		HouzezNetopia.init();
	});

})(jQuery);

