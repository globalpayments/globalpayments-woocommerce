=== GlobalPayments WooCommerce ===
Contributors: globalpayments
Tags: woocommerce, woo, unified, commerce, platform, global, payments, ucp, heartland, payment, systems, tsys, genius, 3DS, gateway, token, tokenize, save cards
Requires at least: 5.4
Tested up to: 5.8
Stable tag: 1.1.3
License: MIT
License URI: https://github.com/globalpayments/globalpayments-woocommerce/blob/main/LICENSE

== Description ==
This extension allows WooCommerce to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.

= Features =
- Heartland Portico gateway
- TSYS Genius gateway
- TSYS TransIT gateway with TSEP
- Unified Commerce Platform (UCP)
- Credit Cards
- Integrates with Woocommerce
- Sale transactions (automatic capture or separate capture action later)
- Refund transactions from a previous Sale
- Stored payment methods
- 3D Secure 2 & SCA
- 3D Secure 1

= Support =
For more information or questions, please email <a href="mailto:developers@globalpay.com">developers@globalpay.com </a>.

= Developer Docs =
Discover our developer portal powered by Heartland, a Global Payments Company (https://developer.heartlandpaymentsystems.com/) or our portal for companies located outside the US (https://developer.globalpay.com/).

== Installation ==
After you have installed and configured the main WooCommerce plugin use the following steps to install the GlobalPayments WooCommerce:
1. In your WordPress admin, go to Plugins > Add New and search for "GlobalPayments WooCommerce"
2. Click Install, once installed click Activate
3. Configure and Enable gateways in WooCommerce by adding your public and secret Api Keys

== UCP Sandbox credentials ==
Access to our Unified Commerce Platform (UCP) requires sandbox credentials which you can retrieve yourself via our <a href="https://developer.globalpay.com/" target="_blank">Developer Portal</a>:

1. First go to the Developer Portal and make sure the green GP-API button is selected in the top right corner.
2. Click on the icon in the top right and click Register.
3. Once registered, click on the same icon in top right corner and click MyApps. Here we are going to create an app which is a set of GP API credentials used to access the API and generate access tokens.
4. Click ‘Create

== Changelog ==

= 1.1.3=
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
