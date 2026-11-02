<?php
/**
 * Shortcode registration, conditional assets and markup.
 *
 * Registers the canonical [bitcoin_sats_converter] and the backwards-compatible
 * alias [cryptoria_sats_converter]; both share one implementation and asset path.
 *
 * All controls are rendered disabled. The controller enables only the permitted
 * ones after the ES module and math module load successfully, so a no-JS or
 * failed-load page never shows controls that silently do nothing.
 *
 * Visible order: currency, price for 1 BTC, amount, Bitcoin (BTC), Satoshis.
 *
 * @package Cryptoria_Sats_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the converter and manages its conditional assets.
 */
final class Cryptoria_Sats_Converter_Shortcode {

	/** Shared style + script handle. */
	const HANDLE = 'cryptoria-sats-converter';

	/** Per-request instance counter for unique DOM ids. */
	private static $instances = 0;

	/**
	 * Wire hooks. Assets are registered (not enqueued) here; the shortcode
	 * callback enqueues them only when it actually renders.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'module_type_tag' ), 10, 3 );
		add_shortcode( 'bitcoin_sats_converter', array( __CLASS__, 'render' ) );
		add_shortcode( 'cryptoria_sats_converter', array( __CLASS__, 'render' ) );
	}

	/**
	 * Register assets without enqueuing them. A page with no shortcode keeps
	 * zero converter CSS/JS on the front end.
	 */
	public static function register_assets() {
		wp_register_style(
			self::HANDLE,
			CRYPTORIA_SATS_URL . 'assets/css/converter.css',
			array(),
			CRYPTORIA_SATS_VERSION
		);

		wp_register_script(
			self::HANDLE,
			CRYPTORIA_SATS_URL . 'assets/js/converter.js',
			array(),
			CRYPTORIA_SATS_VERSION,
			true
		);
	}

	/**
	 * Output the entry script as a native ES module. Scoped to this handle only.
	 *
	 * @param string $tag    Full <script> tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script src (unused; kept for signature).
	 * @return string
	 */
	public static function module_type_tag( $tag, $handle, $src ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		if ( false !== strpos( $tag, 'type="module"' ) || false !== strpos( $tag, "type='module'" ) ) {
			return $tag;
		}
		$tag = preg_replace( '/\stype=(["\'])[^"\']*\1/', '', $tag );
		return preg_replace( '/<script\s/', '<script type="module" ', $tag, 1 );
	}

	/**
	 * Enqueue assets on demand. WordPress deduplicates by handle, so multiple
	 * instances (including a mix of both shortcode names) on one page never
	 * duplicate asset tags.
	 */
	private static function ensure_assets() {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) || ! wp_script_is( self::HANDLE, 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Render the converter.
	 *
	 * @param array|string $atts    Shortcode attributes (unused in this version).
	 * @param string|null  $content Enclosed content (unused).
	 * @param string       $tag     Shortcode tag (unused).
	 * @return string
	 */
	public static function render( $atts = array(), $content = '', $tag = '' ) {
		self::ensure_assets();

		self::$instances++;
		$uid = 'csc-' . self::$instances . '-' . wp_rand( 1000, 9999 );

		$config           = Cryptoria_Sats_Converter_Settings::get_runtime_config();
		$appearance       = Cryptoria_Sats_Converter_Settings::get_appearance();
		$currencies       = $config['currencies'];
		$mode             = $config['mode'];
		$default_currency = $config['defaultCurrency'];

		$status_id = $uid . '-status';

		$msg_invalid      = __( 'Invalid value.', 'cryptoria-sats-converter' );
		$msg_invalid_rate = __( 'Enter a valid price for 1 BTC.', 'cryptoria-sats-converter' );
		$msg_unsupported  = __( 'Your browser does not support automatic conversion.', 'cryptoria-sats-converter' );
		$msg_load_error   = __( 'The converter could not load. Refresh the page to try again.', 'cryptoria-sats-converter' );

		$mod = 'cryptoria-sats-converter--' . str_replace( '_', '-', $appearance['preset'] );

		$out  = '<div class="cryptoria-sats-converter ' . esc_attr( $mod ) . '" data-cryptoria-sats-converter role="group"';
		$out .= ' aria-labelledby="' . esc_attr( $uid . '-title' ) . '"';
		$out .= ' data-msg-invalid="' . esc_attr( $msg_invalid ) . '"';
		$out .= ' data-msg-invalid-rate="' . esc_attr( $msg_invalid_rate ) . '"';
		$out .= ' data-msg-unsupported="' . esc_attr( $msg_unsupported ) . '"';
		$out .= ' data-msg-load-error="' . esc_attr( $msg_load_error ) . '"';
		$out .= ' data-config="' . esc_attr( wp_json_encode( $config ) ) . '"';
		if ( 'custom' === $appearance['preset'] ) {
			$out .= ' style="' . esc_attr( Cryptoria_Sats_Converter_Settings::custom_style_attribute( $appearance['custom_colors'] ) ) . '"';
		}
		$out .= '>';

		$out .= '<h2 class="cryptoria-sats-converter__title" id="' . esc_attr( $uid . '-title' ) . '">'
			. esc_html__( 'Bitcoin calculator: price, BTC and satoshis', 'cryptoria-sats-converter' )
			. '</h2>';

		$out .= '<p class="cryptoria-sats-converter__jsnote">'
			. esc_html__( 'Conversion requires JavaScript enabled in a modern browser.', 'cryptoria-sats-converter' )
			. '</p>';
		$out .= '<noscript><p class="cryptoria-sats-converter__jsnote">'
			. esc_html__( 'JavaScript is disabled, so the converter is not available.', 'cryptoria-sats-converter' )
			. '</p></noscript>';

		if ( count( $currencies ) > 1 ) {
			$out .= '<fieldset class="cryptoria-sats-converter__currency">';
			$out .= '<legend class="cryptoria-sats-converter__legend">' . esc_html__( 'Currency', 'cryptoria-sats-converter' ) . '</legend>';
			$out .= '<div class="cryptoria-sats-converter__currency-options">';
			foreach ( $currencies as $code ) {
				$rid       = $uid . '-cur-' . $code;
				$opt_class = 'cryptoria-sats-converter__currency-option';
				if ( $code === $default_currency ) {
					$opt_class .= ' is-selected';
				}
				$out .= '<label class="' . esc_attr( $opt_class ) . '" for="' . esc_attr( $rid ) . '">';
				$out .= '<input type="radio" id="' . esc_attr( $rid ) . '"'
					. ' name="' . esc_attr( $uid . '-currency' ) . '" data-field="currency"'
					. ' value="' . esc_attr( $code ) . '"' . checked( $code, $default_currency, false )
					. ' disabled aria-disabled="true"> ';
				$out .= esc_html( $code ) . '</label>';
			}
			$out .= '</div>';
			$out .= '<span class="cryptoria-sats-converter__note" aria-hidden="true">'
				. esc_html__( 'Currency', 'cryptoria-sats-converter' ) . '</span>';
			$out .= '</fieldset>';
		} else {
			$out .= '<p class="cryptoria-sats-converter__currency-single">'
				. esc_html( sprintf( /* translators: %s: currency code. */ __( 'Currency: %s', 'cryptoria-sats-converter' ), $currencies[0] ) )
				. '</p>';
		}

		$rate_id   = $uid . '-rate';
		$rate_err  = $rate_id . '-err';
		$rate_note = $rate_id . '-note';
		$out      .= '<div class="cryptoria-sats-converter__row">';
		$out      .= '<label class="cryptoria-sats-converter__label" data-role="rate-label" for="' . esc_attr( $rate_id ) . '">'
			. esc_html( $config['strings']['rateLabel'] ) . '</label>';
		$out      .= '<div class="cryptoria-sats-converter__input-wrap">';
		$out      .= '<input class="cryptoria-sats-converter__input" id="' . esc_attr( $rate_id ) . '"'
			. ' data-field="rate" type="text" inputmode="decimal" autocomplete="off" spellcheck="false" value=""'
			. ' disabled aria-disabled="true"'
			. ( 'visitor' !== $mode ? ' readonly' : '' )
			. ' aria-describedby="' . esc_attr( $rate_note . ' ' . $rate_err ) . '">';
		$out      .= '<span class="cryptoria-sats-converter__unit" data-role="rate-unit">' . esc_html( $default_currency ) . '</span>';
		$out      .= '</div>';
		$out      .= '<span class="cryptoria-sats-converter__field-error" id="' . esc_attr( $rate_err ) . '" data-role="err" aria-live="polite"></span>';
		$out      .= '<span class="cryptoria-sats-converter__note" id="' . esc_attr( $rate_note ) . '" data-role="rate-note" aria-live="polite"></span>';
		$out      .= '</div>';

		$fiat_id   = $uid . '-fiat';
		$fiat_err  = $fiat_id . '-err';
		$fiat_note = $fiat_id . '-note';
		$out      .= '<div class="cryptoria-sats-converter__row">';
		$out      .= '<label class="cryptoria-sats-converter__label" data-role="fiat-label" for="' . esc_attr( $fiat_id ) . '">'
			. esc_html( $config['strings']['fiatLabel'][ $default_currency ] ) . '</label>';
		$out      .= '<input class="cryptoria-sats-converter__input" id="' . esc_attr( $fiat_id ) . '"'
			. ' data-field="fiat" type="text" inputmode="decimal" autocomplete="off" spellcheck="false" value=""'
			. ' disabled aria-disabled="true"'
			. ' aria-describedby="' . esc_attr( $fiat_note . ' ' . $fiat_err ) . '">';
		$out      .= '<span class="cryptoria-sats-converter__field-error" id="' . esc_attr( $fiat_err ) . '" data-role="err" aria-live="polite"></span>';
		$out      .= '<span class="cryptoria-sats-converter__note" id="' . esc_attr( $fiat_note ) . '" data-role="fiat-note" aria-live="polite"></span>';
		$out      .= '</div>';

		$out .= self::field_row( $uid . '-btc', 'btc', __( 'Bitcoin (BTC)', 'cryptoria-sats-converter' ), 'decimal' );
		$out .= self::field_row( $uid . '-sats', 'sats', __( 'Satoshis (sats)', 'cryptoria-sats-converter' ), 'numeric' );

		$out .= '<div class="cryptoria-sats-converter__status" id="' . esc_attr( $status_id ) . '"'
			. ' data-role="status" role="status" aria-live="polite"></div>';

		$out .= '</div>';

		return $out;
	}

	/**
	 * Build one labelled field row, rendered disabled until the controller enables it.
	 *
	 * @param string $id        Element id.
	 * @param string $field     data-field value.
	 * @param string $label     Visible label.
	 * @param string $inputmode inputmode attribute value.
	 * @return string
	 */
	private static function field_row( $id, $field, $label, $inputmode ) {
		$err_id = $id . '-err';

		$row  = '<div class="cryptoria-sats-converter__row">';
		$row .= '<label class="cryptoria-sats-converter__label" for="' . esc_attr( $id ) . '">'
			. esc_html( $label ) . '</label>';
		$row .= '<input class="cryptoria-sats-converter__input" id="' . esc_attr( $id ) . '"'
			. ' data-field="' . esc_attr( $field ) . '" type="text"'
			. ' inputmode="' . esc_attr( $inputmode ) . '"'
			. ' autocomplete="off" spellcheck="false" value=""'
			. ' disabled aria-disabled="true"'
			. ' aria-describedby="' . esc_attr( $err_id ) . '">';
		$row .= '<span class="cryptoria-sats-converter__field-error" id="' . esc_attr( $err_id ) . '"'
			. ' data-role="err" aria-live="polite"></span>';
		$row .= '</div>';

		return $row;
	}
}
