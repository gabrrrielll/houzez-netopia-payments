# Houzez Netopia Payments

WordPress plugin for integrating Netopia Payments gateway with Houzez theme. Supports membership packages and per-listing payments.

## Features

- Integration with Netopia Payments API v2.x
- Support for membership package payments
- Support for per-listing payments
- 3D Secure authentication support
- IPN (Instant Payment Notification) handling
- Sandbox and Live mode support
- Admin settings page

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Houzez theme installed and activated
- Netopia Payments merchant account

## Installation

1. Upload the plugin files to `/wp-content/plugins/houzez-netopia-payments/`
2. Install dependencies using Composer:
   ```bash
   cd wp-content/plugins/houzez-netopia-payments
   composer install
   ```
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Netopia Payments settings page and configure your API credentials

## Configuration

1. Log in to your Netopia Payments admin panel
2. Generate an API Key from Profile -> Security
3. Get your Signature ID (POS Signature)
4. Go to WordPress Admin -> Netopia Payments -> Settings
5. Enter your API Key and Signature ID
6. Choose Sandbox or Live mode
7. Save settings

### Testing in Sandbox Mode

When testing in Sandbox mode, you can use any valid card number format. Common test card numbers:
- **Visa:** 4111 1111 1111 1111
- **Mastercard:** 5555 5555 5555 4444
- **Any future date** for expiration (e.g., 12/2028)
- **Any 3-digit CVV** (e.g., 123)

**Important:** Make sure you are using Sandbox API credentials when testing. The API Key and Signature from your Sandbox account are different from Live account credentials.

If you see "Authorization required" error, check:
1. API Key is correct and matches Sandbox/Live mode
2. Signature ID (POS Signature) is correct
3. You are using Sandbox credentials when Sandbox mode is enabled
4. You are using Live credentials when Live mode is enabled

## Usage

### For Membership Packages

1. Users select a membership package
2. Choose Netopia Payments as payment method
3. Enter card details
4. Complete payment (3D Secure if required)
5. Package is activated automatically

### For Per-Listing Payments

1. Users submit a property listing
2. Choose Netopia Payments as payment method
3. Enter card details
4. Complete payment (3D Secure if required)
5. Listing is published automatically

## Support

For Netopia Payments API documentation, visit:
- https://doc.netopia-payments.com/docs/payment-api/v2.x/intro
- https://doc.netopia-payments.com/docs/payment-sdks/php

## License

GPL-2.0-or-later

