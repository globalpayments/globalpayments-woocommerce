=== GlobalPayments Gateway Provider for WooCommerce ===
Contributors: globalpayments
Tags: woocommerce, woo, commerce, payments, payments
Requires at least: 6.0
Tested up to: 6.8.2
Stable tag: trunk
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
- Digital Wallets - Google Pay
- Digital Wallets - Apple Pay
- Digital Wallets - Click To Pay
- Payments over the phone
- Buy Now Pay Later - Affirm
- Buy Now Pay Later - Clearpay
- Buy Now Pay Later - Klarna
- Bank Payment
- PayPal

= Support =
For more information or questions, please email <a href="mailto:developers@globalpay.com">developers@globalpay.com </a>.

= Developer Docs =
Discover our developer portal powered by Heartland, a Global Payments Company (https://developer.heartlandpaymentsystems.com/) or our portal for companies located outside the US (https://developer.globalpay.com/).

== Installation ==
After you have installed and configured the main WooCommerce plugin use the following steps to install the GlobalPayments Gateway Provider for WooCommerce:
1. In your WordPress admin, go to Plugins > Add New and search for "GlobalPayments Gateway Provider for WooCommerce"
2. Click Install, once installed click Activate
3. Configure and Enable gateways in WooCommerce by adding your public and secret Api Keys

== Unified Payments Sandbox credentials ==
Access to our Unified Payments requires sandbox credentials which you can retrieve yourself via our <a href="https://developer.globalpay.com/" target="_blank">Developer Portal</a>:

1. First go to the Developer Portal.
2. Click on the person icon in the top-right corner and select Log In or Register.
3. Once registered, click on the person icon again and select Unified Payments Apps.
4. Click  ‘Create a New App’. An app is a set of credentials used to access the API and generate access tokens.

== Changelog ==
= 1.15.0 (10/28/25) =
* GPAPI gateway: Add HPP checkout option

= 1.14.9 (10/09/25) =
* Fixed bug causing transaction failures when using TransIT gateway

= 1.14.8 (10/02/25) =
* Fixed No COF flag in authorization message while card is being saved
* Fixed single card was being saved multiple times during checkout

= 1.14.7 (09/25/25) =
* GPAPI gateway: enhancements to Polish APM validations
* Fixed sandbox 3ds error issue
* fixed the plugin update issue

= 1.14.6 (09/18/25) =
* Gift card script is visible on checkout page issue
* Fixed - Payment fields not loading issue
* Fixed - Google Pay content issue, which was displaying HTML tags

= 1.14.5 (09/11/25) =
* Various security remediations

= 1.14.4 (09/09/25) =
* Google Pay misaligned button issue

= 1.14.3 (09/04/25) =
* Updates to order status handling for Blik transactions
* Minor corrections to file formatting

= 1.14.1 (08/26/25) =
* Enhanced functionality for determining optimal configuration for 3DS
* Fixed Admin Panel "Pay for Order" button display issue
* Minor labeling and description improvements
* GPAPI gateway: fixed admin-panel configuration screen display issue

= 1.14.0 (08/12/25) =
* Added DiUI-based APMs: Blik & Bank Select (Poland-only APMs)

= 1.13.8 (07/24/25) =
* Various security remediations
* Fixed HPS gift card display issue

= 1.13.7 (06/10/25) =
* Added account name mandetory with dropdown to select account
* GPAPI gateway: switched to Drop-in UI card fields
* Added PHP configuration checks

= 1.13.4 (05/01/25) =
* Fixed gateway settings display bug

= 1.13.3 (04/15/25) =
* Add support for admin panel refunds w/TSYS Genius gateway
* Added support for Spanish Translations

= 1.13.2 (03/27/25) =
* Minor WordPress marketplace enhancements

= 1.13.1 (03/27/25) =
* Add support for Spanish translations

= 1.13.0 (03/24/25) =
* Add subscriptions support for GP API gateway

= 1.12.1 (09/12/24) =
* Add the possibility to configure the Transaction Processing Account Name

= 1.12.0 (07/30/24) =
* Block checkout compatibility for Digital Wallets, Buy Now Pay Later, Bank Payment and PayPal

= 1.11.0 (07/16/24) =
* Block checkout compatibility for Unified Payments

= 1.10.7 (04/10/24) =
* Added Google Pay and Apple Pay compatible with the Heartland Portico gateway
* Added French Translation

= 1.10.6 (03/28/24) =
* Updated Hosted Fields JS Library version to 3.0.11

= 1.10.5 (03/19/24) =
* Fixed a bug where the TransIT gateway would not reverse the transaction if the AVS/CVN checks failed

= 1.10.4 (03/13/24) =
* Paypal

= 1.10.3 (02/20/24) =
* Open Banking

= 1.10.2 (01/10/24) =
* Bug Fix - Provider missing on initiated message for open banking

= 1.10.1 (12/05/23) =
* Hotfix for the 1.10.0 release

= 1.10.0 (12/05/23) =
* Open banking

= 1.9.5 (11/09/23) =
* Unified payments - High-Performance Order Storage (HPOS) compatibility

= 1.9.4 (10/10/23) =
* GooglePay - configurable Allowed Card Auth Methods
* Bug fix - Accepted cards field is not mandatory on Apple Pay config

= 1.9.3 (09/19/23) =
* Added the option to enable/disable the 3DS flow

= 1.9.2 (08/01/23) =
* Replaced OrderId function with OrderNumber function

= 1.9.1 (07/20/23) =
* Added the Card Holder Name in the Google Pay and Apple Pay requests

= 1.9.0 (07/06/23) =
* Unified Payments - Added Credential Check button
* Fixed a bug where the Card Number iframe would not be 100% expanded on Mozilla Firefox

= 1.8.0 (05/16/23) =
* Unified Payments - Added Click To Pay

= 1.7.0 (05/10/23) =
* Added GPI Transactions support

= 1.6.0 (04/11/23) =
* Unified Payments - Added Buy Now Pay Later (BNPL) - Affirm, Clearpay, Klarna

= 1.5.6 (03/30/23) =
* Unified Payments - fixed a bug where certain browser extensions would stop the 3DS Challenge process

= 1.5.5 (03/01/23) =
* Update to v6.1.5 of GP PHP SDK
* Genius MerchantWare bug fix - fixes service URL problem

= 1.5.4 (02/09/23) =
* Unified Payments bug fix - `Pay for Order` 3DS

= 1.5.3 (02/02/23) =
* Added GPI Transactions : Transaction API - added support for credit, ach & reporting transactions

= 1.5.2 (01/10/23) =
* Unified Payments improvement - 3ds notification endpoints work with defer mode
* Unified Payments improvement - `Pay for Order` button improved selector
* Hosted fields bug fix - checkout loading

= 1.5.1 (11/17/22) =
* Unified Payments - Added transaction descriptor
* Unified Payments settings - Increased `Merchant contact url` length
* Unified Payments bug fix - Added `Pay for Order` 3DS
* Unified Payments bug fix - `Pay for Order` amount

= 1.5.0 (10/26/22) =
* Unified Payments - remove 3DS1

= 1.4.2 (10/19/22) =
* Update PHP-SDK to v6.0.0

= 1.4.1 (08/24/22) =
* Bug fix - fixed for payment failed issue (transIt)

= 1.4.0 (08/23/22) =
* Unified Payments - added Card Holder Name for Hosted Fields
* Add Merchant Name option for the Google Pay gateway

= 1.3.0 (08/01/22) =
* Unified Payments - Added Admin Pay for Order (process payments over the phone)
* Added Admin option for Apple Pay button color
* Bug fix - Refund issue when create_refund is called programmatically
* Bug fix - Digital Wallets pay buttons on `Pay for Order`
* Update PHP-SDK to v4.0.4

= 1.2.2 (06/14/22) =
* Bug fix - 3DS/Digital Wallets amount not updated when a customer added/removed a coupon

= 1.2.1 (05/05/22) =
* Renamed Unified Commerce Platform (UCP) to Unified Payments
* Bug fix - Live mode toggle in Digital Wallets javascript
* Update PHP-SDK to v3.0.5

= 1.2.0 (04/14/22) =
* Added Digital Wallets - Google Pay
* Added Digital Wallets - Apple Pay

= 1.1.6 (03/03/22) =
* Split-tender - added functionality

= 1.1.5 (02/22/22) =
* Bug fix - Heartland gift card error

= 1.1.4 (02/15/22) =
* Add dependency for WC checkout frontend scripts.

= 1.1.3 (12/07/21) =
* Added composer
* Bug fix - globalpayments_gpapi-checkout_validated displayed although it should be hidden

= 1.1.2 (09/21/21) =
* UCP Bug fix - admin sandbox credentials error when live mode enabled
* UCP Bug fix - 3DS InitiateAuthentication order.shipping_address.state
* TransIT Bug fix - Generate transaction key when credentials updated
* UCP, Heartland, TransIT - Add logging
* Bug fix -  'Place order' button appears twice on checkout flow
* Update PHP-SDK to v2.3.12

= 1.1.1 (08/17/21) =
* Added gateway credentials toggle for live/sandbox in admin gateway configuration
* Added UCP dynamic headers for platform and extension
* Update PHP-SDK to v2.3.9

= 1.1.0 (07/29/21) =
* Renamed GP-API to Unified Commerce Platform (UCP)
* Added checkout environment indicator for test/sandbox
* Update PHP-SDK to v2.3.7
* Update UCP 3DS

= 1.0.3 (07/29/21) =
* Added AVS/CVV result based reversal conditions in admin and store.

= 1.0.2 (07/13/21) =
* Update PHP-SDK to v2.3.6
* Fix GP-API 3DS Challenge for Live Mode
* Fix GP-API Capture for Live Mode

= 1.0.1 (06/24/21) =
* Fix TransIT credential handling

= 1.0.0 (06/17/21) =
* Add GP-API instructions for sandbox credentials
* Handle new errors for card expiry_year
* Move Heartland hooks to gateway class
* Card saving admin note
* Validate refund amount
* Add missing invalid card icons

= 1.0.0-b.2 (05/20/21) =
* Fix toggleSubmitButton
* Fix 3DS events
* Remove Verify payment action
* Add filters for hosted fields styling
* Internet Explorer compatibility
* Update PHP-SDK version to 2.2.14

= 1.0.0-b.1 (04/09/21) =
* Initial release.
