=== OmniSignal - Server-Side Conversions API for WooCommerce ===
Contributors: electrictomcat
Tags: conversions api, capi, meta capi, offline conversions, woocommerce
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover WooCommerce ad conversions lost to ITP and ad-blockers by sending them server-side to Meta, TikTok, Microsoft and LinkedIn.

== Description ==

Browser-based conversion pixels miss a large share of purchases to Safari ITP, ad-blockers and consent tooling. OmniSignal captures the ad click identifier when the visitor lands, stores it on the order, and reports the conversion to your ad platforms from your server after the payment completes.

= Supported channels =

* **Meta (Facebook & Instagram)** — Conversions API v20.0
* **TikTok** — Events API v1.3
* **Microsoft Advertising (Bing)** — Offline Conversions, Campaign Management v13
* **LinkedIn** — Conversions API

* **Google Ads** — connected at omnisignal.dev and uploaded on your behalf

Google Ads is the one channel not configured inside WordPress. It requires an OAuth client secret and a Google Ads developer token, and a plugin's source code is public, so neither can ship here. You authorise your Google Ads account once at omnisignal.dev/portal and this plugin forwards those conversions to us to upload. The credential the plugin stores is scoped to this domain alone — it cannot read your licence key or affect your other sites, and deactivating the domain revokes it.

= What it tracks =

* Purchase (on `woocommerce_payment_complete` and `woocommerce_thankyou`, de-duplicated)
* Add to cart, initiate checkout, and refunds
* Form leads from Contact Form 7, WPForms, Gravity Forms and Elementor Pro

= How attribution survives the gateway =

Click identifiers (`gclid`, `gbraid`, `wbraid`, `fbclid`, `ttclid`, `msclkid`, `li_fat_id`) are written onto the order at checkout, while the customer's cookies are still available. Gateways such as Stripe and PayPal complete payment through a server-to-server callback that carries no cookies at all, so reading them at that moment would attribute nothing.

= Privacy =

* Email addresses and phone numbers are normalised and SHA-256 hashed before they leave your server. Raw values are never transmitted.
* A phone number that cannot be resolved to E.164 is dropped rather than hashed under a guessed country code — a wrong hash matches nobody and looks exactly like success.
* All assets are served from your own site. The plugin loads nothing from a third-party CDN and sends no visitor data anywhere except the ad platforms you configure.
* Deleting the plugin removes every option it stored, including API tokens.

= High-Performance Order Storage =

Fully compatible with WooCommerce HPOS. Order data is read and written through the WooCommerce order API, never through post meta.

== Installation ==

1. Upload the `wp-omnisignal` folder to `/wp-content/plugins/`, or install the zip through Plugins > Add New.
2. Activate the plugin through the Plugins menu.
3. Go to WooCommerce > OmniSignal and enter the credentials for each channel you want to use.
4. Enter your licence key. It is verified against omnisignal.dev and activates this domain.

== Frequently Asked Questions ==

= Do I need a licence key? =

The plugin validates your key against omnisignal.dev and activates the domain it is running on. You can see and manage your activated domains at omnisignal.dev/portal.

= Will conversions be double counted? =

No. Purchases are de-duplicated per order, and every event carries a stable `event_id` so the ad platforms discard a repeat if one ever reaches them.

= Does this slow down checkout? =

No. Conversions are queued through Action Scheduler (or WP-Cron when Action Scheduler is unavailable) and delivered in the background, so an unresponsive ad platform never delays the thank-you page.

= How do I know it is working? =

The settings screen lists recent delivery failures with the message the platform returned. An empty list means everything sent successfully.

= My store is behind Cloudflare and the visitor IP looks wrong =

Forwarded IP headers are only trusted when you opt in, because they can be spoofed on a server that is not actually behind a proxy. Add `add_filter( 'omnisignal_trust_proxy_headers', '__return_true' );` once you have confirmed your site sits behind one.

== Changelog ==

= 2.2.0 =
* Added: Google Ads support, via a one-time account connection at omnisignal.dev. The plugin forwards those conversions rather than holding credentials it cannot safely store.
* Added: the settings screen shows whether Google Ads is connected.
* Changed: the site now authenticates to omnisignal.dev with a per-domain token issued at activation, instead of sending its licence key.

= 2.1.0 =
* Fixed: licence keys are now verified against omnisignal.dev. Any non-empty value previously displayed as an active Pro licence.
* Fixed: click identifiers are stored on the order at checkout, so conversions completed through a gateway callback are attributed correctly.
* Fixed: order de-duplication uses the WooCommerce order API. Under HPOS the previous post-meta guard never matched, so both purchase hooks fired.
* Fixed: the tracking script is bundled with the plugin instead of being loaded from omnisignal.dev.
* Fixed: API responses are checked, and failures are recorded and shown in the settings screen. They were previously discarded.
* Fixed: conversions are delivered in the background rather than blocking the thank-you page for up to 20 seconds.
* Fixed: form leads use the store currency and a configurable value instead of a hardcoded 25 USD.
* Added: Microsoft Advertising and LinkedIn channels, which the plugin previously advertised without implementing.
* Added: shared email and phone normalisation, including Gmail dot and +suffix handling.
* Added: settings are sanitised against an allow-list, and `uninstall.php` removes all stored options including API tokens.

= 2.0.0 =
* WooCommerce funnel tracking, form lead capture, HPOS declaration.

== Upgrade Notice ==

= 2.1.0 =
Licence keys are now actually verified, and conversions completed through a payment gateway callback are attributed correctly for the first time. Re-save your settings after upgrading.
