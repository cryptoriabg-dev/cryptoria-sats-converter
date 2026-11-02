<?php
/**
 * Plugin Name:       Cryptoria Bitcoin/Sats Converter
 * Plugin URI:        https://cryptoria.bg/bitcoin-sats-converter/
 * Description:       Convert Bitcoin and satoshis, with optional EUR/USD calculations using visitor or administrator rates. No external API requests.
 * Version:           1.2.6
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Cryptoria
 * Author URI:        https://cryptoria.bg
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cryptoria-sats-converter
 * Domain Path:       /languages
 *
 * @package Cryptoria_Sats_Converter
 */

defined( 'ABSPATH' ) || exit;

define( 'CRYPTORIA_SATS_VERSION', '1.2.6' );
define( 'CRYPTORIA_SATS_FILE', __FILE__ );
define( 'CRYPTORIA_SATS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRYPTORIA_SATS_URL', plugin_dir_url( __FILE__ ) );

require_once CRYPTORIA_SATS_PATH . 'includes/class-settings.php';
require_once CRYPTORIA_SATS_PATH . 'includes/class-shortcode.php';

Cryptoria_Sats_Converter_Shortcode::init();

if ( is_admin() ) {
	Cryptoria_Sats_Converter_Settings::init();
}
