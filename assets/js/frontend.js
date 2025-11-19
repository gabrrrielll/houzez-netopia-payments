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

			// Handle membership payment
			$(document).on('click', '#houzez_complete_membership', function(e) {
				e.preventDefault();
				var paymentType = $('input[name="houzez_payment_type"]:checked').val();
				
				if (paymentType === 'netopia') {
					HouzezNetopia.processMembershipPayment();
				}
			});

			// Handle listing payment
			$(document).on('click', '#houzez_complete_order', function(e) {
				e.preventDefault();
				var paymentType = $('input[name="houzez_payment_type"]:checked').val();
				
				if (paymentType === 'netopia') {
					HouzezNetopia.processListingPayment();
				}
			});

			// Format card number input
			$(document).on('input', '#netopia_card_number, #netopia_card_number_listing', function() {
				var value = $(this).val().replace(/\s/g, '');
				var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
				$(this).val(formattedValue);
			});
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
					if (response.success) {
						if (response.data.requires_3ds) {
							// Redirect to 3D Secure
							HouzezNetopia.handle3DSecure(response.data);
						} else {
							// Payment completed
							window.location.href = houzez_netopia.success_url;
						}
					} else {
						HouzezNetopia.showError(response.data.message || 'Payment failed. Please try again.');
						$button.prop('disabled', false).html('Complete Membership');
					}
				},
				error: function() {
					HouzezNetopia.showError('An error occurred. Please try again.');
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
					if (response.success) {
						if (response.data.requires_3ds) {
							// Redirect to 3D Secure
							HouzezNetopia.handle3DSecure(response.data);
						} else {
							// Payment completed
							window.location.href = houzez_netopia.success_url;
						}
					} else {
						HouzezNetopia.showError(response.data.message || 'Payment failed. Please try again.');
						$button.prop('disabled', false).html('Complete Payment');
					}
				},
				error: function() {
					HouzezNetopia.showError('An error occurred. Please try again.');
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
			if (typeof houzez !== 'undefined' && houzez.Core && houzez.Core.util) {
				houzez.Core.util.showMessage(message, 'error');
			} else {
				alert(message);
			}
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		HouzezNetopia.init();
	});

})(jQuery);

