=== GlobalPayments WooCommerce ===
Contributors: globalpayments
Tags: woocommerce, woo, unified, commerce, platform, global, payments, heartland, payment, systems, tsys, genius, 3DS, gateway, token, tokenize, save cards
Requires at least: 5.4
Tested up to: 6.0.4
Stable tag: 1.5.0
License: MIT
License URI: https://github.com/globalpayments/globalpayments-woocommerce/blob/main/LICENSE

== Description ==
This extension allows WooCommerce to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.

= Features =
- Heartland Portico gateway
- TSYS Genius gateway
- TSYS TransIT gateway with TSEP
- Unified Payments
- Credit Cards
- Integrates with Woocommerce
- Sale transactions (automatic capture or separate capture action later)
- Refund transactions from a previous Sale
- Stored payment methods
- 3D Secure 2 & SCA
- 3D Secure 1
- Digital Wallets - Google Pay
- Digital Wallets - Apple Pay
- Payments over the phone

= Support =
For more information or questions, please email <a href="mailto:developers@globalpay.com">developers@globalpay.com </a>.

= Developer Docs =
Discover our developer portal powered by Heartland, a Global Payments Company (https://developer.heartlandpaymentsystems.com/) or our portal for companies located outside the US (https://developer.globalpay.com/).

== Installation ==
After you have installed and configured the main WooCommerce plugin use the following steps to install the GlobalPayments WooCommerce:
1. In your WordPress admin, go to Plugins > Add New and search for "GlobalPayments WooCommerce"
2. Click Install, once installed click Activate
3. Configure and Enable gateways in WooCommerce by adding your public and secret Api Keys

== Unified Payments Sandbox credentials ==
Access to our Unified Payments requires sandbox credentials which you can retrieve yourself via our <a href="https://developer.globalpay.com/" target="_blank">Developer Portal</a>:

1. First go to the Developer Portal.
2. Click on the person icon in the top-right corner and select Log In or Register.
3. Once registered, click on the person icon again and select Unified Payments Apps.
4. Click  ‘Create a New App’. An app is a set of credentials used to access the API and generate access tokens.

== Changelog ==

= 1.5.0 =
* Unified Payments - remove 3DS1

= 1.4.2 =
* Update PHP-SDK to v6.0.0

= 1.4.1 =
* Bug fix - fixed for payment failed issue (transIt)

= 1.4.0 =
* Unified Payments - added Card Holder Name for Hosted Fields
* Add Merchant Name option for the Google Pay gateway

= 1.3.0 =
* Unified Payments - Added Admin Pay for Order (process payments over the phone)
* Added Admin option for Apple Pay button color
* Bug fix - Refund issue when create_refund is called programmatically
* Bug fix - Digital Wallets pay buttons on `Pay for Order`
* Update PHP-SDK to v4.0.4

= 1.2.2 =
* Bug fix - 3DS/Digital Wallets amount not updated when a customer added/removed a coupon

= 1.2.1 =
* Renamed Unified Commerce Platform (UCP) to Unified Payments
* Bug fix - Live mode toggle in Digital Wallets javascript
* Update PHP-SDK to v3.0.5

= 1.2.0 =
* Added Digital Wallets - Google Pay
* Added Digital Wallets - Apple Pay

= 1.1.6 =
* Split-tender - added functionality

= 1.1.5 =
* Bug fix - Heartland gift card error

= 1.1.4 =
* Add dependency for WC checkout frontend scripts.

= 1.1.3 =
* Added composer
* Bug fix - globalpayments_gpapi-checkout_validated displayed although it should be hidden

= 1.1.2 =
* UCP Bug fix - admin sandbox credentials error when live mode enabled
* UCP Bug fix - 3DS InitiateAuthentication order.shipping_address.state
* TransIT Bug fix - Generate transaction key when credentials updated
* UCP, Heartland, TransIT - Add logging
* Bug fix -  'Place order' button appears twice on checkout flow
* Update PHP-SDK to v2.3.12

= 1.1.1 =
* Added gateway credentials toggle for live/sandbox in admin gateway configuration
* Added UCP dynamic headers for platform and extension
* Update PHP-SDK to v2.3.9

= 1.1.0 =
* Renamed GP-API to Unified Commerce Platform (UCP)
* Added checkout environment indicator for test/sandbox
* Update PHP-SDK to v2.3.7
* Update UCP 3DS

= 1.0.3 =
* Added AVS/CVV result based reversal conditions in admin and store.

= 1.0.2 =
* Update PHP-SDK to v2.3.6
* Fix GP-API 3DS Challenge for Live Mode
* Fix GP-API Capture for Live Mode

= 1.0.1 =
* Fix TransIT credential handling

= 1.0.0 =
* Add GP-API instructions for sandbox credentials
* Handle new errors for card expiry_year
* Move Heartland hooks to gateway class
* Card saving admin note
* Validate refund amount
* Add missing invalid card icons

= 1.0.0-b.2 =
* Fix toggleSubmitButton
* Fix 3DS events
* Remove Verify payment action
* Add filters for hosted fields styling
* Internet Explorer compatibility
* Update PHP-SDK version to 2.2.14

= 1.0.0-b.1 =
* Initial release.
