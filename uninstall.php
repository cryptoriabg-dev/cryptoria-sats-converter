<?php
/**
 * Uninstall cleanup.
 *
 * Deletes only this plugin's own option. No custom tables, no post data, no
 * shortcode content and no unrelated data are touched. Deactivation preserves
 * settings; only a full uninstall runs this file.
 *
 * The plugin is a single-site plugin (not network-activated), so a plain
 * delete_option is correct.
 *
 * @package Cryptoria_Sats_Converter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cryptoria_sats_converter' );
