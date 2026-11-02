=== Cryptoria Bitcoin/Sats Converter ===
Contributors: cryptoria
Tags: bitcoin, satoshi, calculator, converter, cryptocurrency
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert Bitcoin, satoshis, EUR and USD using a visitor-entered or administrator-defined BTC rate. No external price API required.

== Description ==

Cryptoria Bitcoin/Sats Converter is a lightweight calculator for converting between Bitcoin (BTC), satoshis (sats), euros (EUR) and US dollars (USD).

The plugin does not fetch or claim to provide a live market price. A BTC rate is entered either by the visitor directly in the calculator or by a site administrator in the plugin settings. Bitcoin-to-satoshi conversion continues to work when no fiat rate is available.

= Features =

* Convert BTC to satoshis and satoshis to BTC.
* Calculate BTC and satoshi amounts from EUR or USD.
* Calculate EUR or USD values from BTC or satoshis.
* Choose between EUR and USD in one fiat field.
* Use a visitor-entered BTC rate without setup.
* Optionally use BTC rates defined by a site administrator.
* Show when an administrator-defined rate was last updated and warn when it may be outdated.
* Choose Neutral Light, Dark or Custom appearance settings.
* Customize calculator colours from the WordPress administration area.
* Add the calculator with the `[bitcoin_sats_converter]` shortcode.
* Place multiple independent calculator instances on the same page.
* Load calculator assets only on pages where the shortcode is rendered.
* Use internationalized English source strings and support WordPress.org language packs.

= Rate modes =

**Visitor-entered rate (default)**

The visitor enters the price of 1 BTC in EUR or USD. The rate is kept only in that calculator instance in the visitor's browser memory. It is not saved by WordPress and is not sent to an external service.

**Administrator-defined rate**

A site administrator enters the BTC/EUR and/or BTC/USD rate in **Settings > Bitcoin/Sats Converter**. The calculator displays the saved rate and its update time. The rate does not update automatically.

= Important rate information =

Fiat results depend entirely on the rate entered by the visitor or administrator. A manually entered rate may be inaccurate or outdated. The calculator is an informational tool and should not be treated as a live market quotation, trading service or financial advice.

= Privacy and external services =

This version does not connect to external price providers or other third-party services. It does not send calculator entries to the plugin author, does not include telemetry and does not store visitor-entered rates in cookies, local storage or the WordPress database.

Administrator settings, including administrator-defined rates and appearance choices, are stored in the site's WordPress database.

For documentation and a live demonstration, visit the [plugin page](https://cryptoria.bg/bitcoin-sats-converter/).

== Installation ==

1. Upload the `cryptoria-sats-converter` folder to `/wp-content/plugins/`, or install the plugin ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate **Cryptoria Bitcoin/Sats Converter** through the **Plugins** screen.
3. Add the `[bitcoin_sats_converter]` shortcode to a post, page, shortcode block or compatible page-builder module.
4. The default visitor-entered rate mode works immediately.
5. Optionally open **Settings > Bitcoin/Sats Converter** to select currencies, enter administrator-defined rates or change the appearance.

== Frequently Asked Questions ==

= Does the plugin fetch a live Bitcoin price? =

No. This version makes no requests to a market-data provider. The BTC rate is entered by the visitor or set manually by a site administrator.

= Does BTC-to-satoshi conversion work without a fiat rate? =

Yes. BTC and satoshi conversion works without an EUR or USD rate.

= Where is a visitor-entered rate stored? =

It is kept only in the individual calculator instance in browser memory while the page is open. It is not saved to the WordPress database, cookies or local storage, and it is not transmitted to an external service by this plugin.

= Which fiat currencies are supported? =

EUR and USD. A site administrator can enable one or both and choose the default currency.

= How do I display the calculator? =

Use the `[bitcoin_sats_converter]` shortcode. The legacy `[cryptoria_sats_converter]` shortcode is also supported for existing installations.

= Can I change the calculator design? =

Yes. Choose Neutral Light, Dark or Custom under **Settings > Bitcoin/Sats Converter**. Custom mode allows the site administrator to choose the calculator colours. When using custom colours, the administrator is responsible for maintaining readable contrast.

= Can I place more than one calculator on a page? =

Yes. Each calculator instance operates independently, while the plugin loads its CSS and JavaScript only once per page.

= Does the plugin work without JavaScript? =

No. JavaScript is required for the calculator. If JavaScript or the calculator module cannot load, the controls remain disabled rather than displaying misleading results.

= Does the plugin provide financial advice? =

No. The calculator provides arithmetic conversions based on a manually entered rate. It is not a trading tool, price feed or financial advisory service.

== Screenshots ==

1. Visitor-entered rate mode with EUR and USD selection.
2. Administrator-defined rate settings.
3. Dark appearance preset.
4. Custom colour settings.

== Support ==

For support, use the plugin's support forum on WordPress.org. Support is provided on a best-effort basis.

Before reporting an issue, please include the WordPress version, PHP version, active theme and the steps needed to reproduce the problem. Do not include passwords, API keys or other private information.

== Changelog ==

= 1.2.6 =

* Raised the EUR/USD control minimum height to 44 CSS pixels and added defensive min-width so the converter shrinks safely in narrow and mobile containers.

= 1.2.5 =

* Initial public release.
* Added BTC and satoshi conversion with exact integer-based arithmetic.
* Added EUR and USD calculations using visitor-entered or administrator-defined BTC rates.
* Added Neutral Light, Dark and Custom appearance options.
* Custom appearance includes a visual colour picker with manual HEX entry.
* Corrected Custom-mode contrast across mixed dark and light surfaces.
* Improved the Currency label layout so it sits below the EUR/USD control.
* Added internationalized English source strings with support for WordPress translation packs.
* Added privacy-first operation without external price services or telemetry.
