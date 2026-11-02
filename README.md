
# Cryptoria Bitcoin/Sats Converter

**A free, open-source WordPress plugin for converting Bitcoin (BTC), satoshis (sats), euros (EUR), and US dollars (USD).**

Developed and maintained by [Cryptoria.bg](https://cryptoria.bg), an independent Bulgarian Bitcoin-first media platform.

[WordPress Plugin Directory](https://wordpress.org/plugins/cryptoria-sats-converter/) | [Live Demo & Documentation](https://cryptoria.bg/bitcoin-sats-converter/)

## Features

- Convert Bitcoin (BTC) to satoshis and vice versa.
- Calculate BTC and sats values in EUR or USD.
- Choose between EUR and USD in a single fiat field.
- Enter a BTC exchange rate directly in the calculator.
- Optionally configure exchange rates in the WordPress admin area.
- Display the last update time of administrator-defined rates.
- Get a warning when an administrator-defined rate may be outdated.
- Choose from Neutral Light, Dark, and Custom appearance modes.
- Customize the calculator's colors.
- Embed multiple independent converters on the same page.
- Load plugin assets only when the shortcode is rendered.
- Support WordPress localization and translation packs.

## How Exchange Rates Work

Cryptoria Bitcoin/Sats Converter does **not** automatically retrieve live cryptocurrency prices.

There are two rate modes:

**Visitor-entered rate (default)**

Visitors enter the value of 1 BTC in EUR or USD directly in the calculator.

**Administrator-defined rate**

WordPress administrators can manually configure BTC/EUR and BTC/USD rates under Settings > Bitcoin/Sats Converter.

BTC-to-satoshi conversion works without any fiat exchange rate.

Fiat calculations depend on the manually entered rate and should not be interpreted as live market quotations.

## Installation

1. Open your WordPress dashboard.
2. Navigate to Plugins > Add New.
3. Search for "Cryptoria Bitcoin/Sats Converter".
4. Install and activate the plugin.
5. Add the following shortcode to a page or post:

    [bitcoin_sats_converter]

The default visitor-entered rate mode works immediately.

Optionally, open Settings > Bitcoin/Sats Converter to configure supported fiat currencies, exchange rates, and appearance.

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later
- JavaScript enabled in the visitor's browser

## Privacy

The plugin does not connect to external cryptocurrency price providers.

It does not include telemetry or transmit calculator input to the plugin author.

Visitor-entered exchange rates remain in the individual calculator instance in browser memory.

Administrator-defined rates and appearance settings are stored in the site's WordPress database.

## Support and Contributions

For installation and usage support, please use the official WordPress.org support forum.

For technical bug reports, source code improvements, and feature proposals, use GitHub Issues and Pull Requests.

Please include the relevant WordPress, PHP, and plugin versions when reporting a technical issue.

Do not publish passwords, credentials, API keys, or personal information in public issues.

## License

Cryptoria Bitcoin/Sats Converter is free software licensed under the GNU General Public License, version 2 or any later version (GPL-2.0-or-later).

See the LICENSE file for the full license text.

## Official Links

- Website: https://cryptoria.bg
- Plugin information and demo: https://cryptoria.bg/bitcoin-sats-converter/
- WordPress.org: https://wordpress.org/plugins/cryptoria-sats-converter/
- Source code: https://github.com/cryptoriabg-dev/cryptoria-sats-converter
- Contact: info@cryptoria.bg

---

Maintained by **Cryptoria.bg**.

*This plugin is an informational calculation tool, not a live market price feed or financial advisory service.*
