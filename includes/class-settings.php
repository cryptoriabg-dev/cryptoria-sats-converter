<?php
/**
 * Settings page, option schema, sanitization and runtime config.
 *
 * One namespaced option array owns all plugin settings. Defaults are applied in
 * code (never written to the database on activation or page view). All rate math
 * uses canonical decimal strings — never floatval/binary floats.
 *
 * @package Cryptoria_Sats_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings under Settings -> Bitcoin/Sats Converter.
 */
final class Cryptoria_Sats_Converter_Settings {

	const OPTION       = 'cryptoria_sats_converter';
	const GROUP        = 'cryptoria_sats_converter_group';
	const PAGE_SLUG    = 'cryptoria-sats-converter';
	const HANDLE_ADMIN = 'cryptoria-sats-converter-admin';
	const MODES        = array( 'visitor', 'admin_manual' );
	const CURRENCIES   = array( 'EUR', 'USD' );
	const PRESETS      = array( 'neutral_light', 'cryptoria_dark', 'custom' );

	/**
	 * Allowlisted colour tokens: option key => Neutral Light hex and CSS variable.
	 *
	 * @return array<string, array{hex:string, var:string}>
	 */
	public static function color_tokens() {
		return array(
			'surface'       => array( 'hex' => '#faf7f0', 'var' => '--csc-surface' ),
			'text'          => array( 'hex' => '#161920', 'var' => '--csc-text' ),
			'muted'         => array( 'hex' => '#4a453c', 'var' => '--csc-muted' ),
			'input_bg'      => array( 'hex' => '#ffffff', 'var' => '--csc-input-bg' ),
			'input_text'    => array( 'hex' => '#161920', 'var' => '--csc-input-text' ),
			'border'        => array( 'hex' => '#8a7d63', 'var' => '--csc-border' ),
			'disabled_bg'   => array( 'hex' => '#ebe4d6', 'var' => '--csc-disabled-bg' ),
			'disabled_text' => array( 'hex' => '#5c5548', 'var' => '--csc-disabled-text' ),
			'focus'         => array( 'hex' => '#c2410c', 'var' => '--csc-focus' ),
			'error'         => array( 'hex' => '#b91c1c', 'var' => '--csc-error' ),
			'selected'      => array( 'hex' => '#f3e0c4', 'var' => '--csc-selected' ),
			'accent'        => array( 'hex' => '#c2410c', 'var' => '--csc-accent' ),
		);
	}

	/** Dark preset hex values keyed like color_tokens(). */
	public static function cryptoria_dark_colors() {
		return array(
			'surface'       => '#26272a',
			'text'          => '#ffffff',
			'muted'         => '#c7c7c7',
			'input_bg'      => '#1e1e20',
			'input_text'    => '#ffffff',
			'border'        => '#8a8248',
			'disabled_bg'   => '#2d2e32',
			'disabled_text' => '#c7c7c7',
			'focus'         => '#d4af37',
			'error'         => '#ff7b7b',
			'selected'      => '#2a2722',
			'accent'        => '#f5931e',
		);
	}

	/** Settings page hook suffix, for scoped admin asset loading. */
	private static $hook_suffix = '';

	/**
	 * Wire admin hooks. No frontend side effects.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( CRYPTORIA_SATS_FILE ),
			array( __CLASS__, 'settings_link' )
		);
	}

	/**
	 * Allowlist a mode. Any stored value outside the allowlist, including a
	 * retired mode from an older development install, becomes visitor.
	 * Read-only: this never writes the option.
	 *
	 * @param mixed $mode Candidate mode.
	 * @return string
	 */
	public static function normalize_mode( $mode ) {
		if ( ! is_string( $mode ) || ! in_array( $mode, self::MODES, true ) ) {
			return 'visitor';
		}
		return $mode;
	}

	/**
	 * Code defaults. Applied without any database write.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'             => 'visitor',
			'currencies'       => array( 'EUR', 'USD' ),
			'default_currency' => 'EUR',
			'rates'            => array(
				'EUR' => array( 'value' => '', 'updated_at' => 0 ),
				'USD' => array( 'value' => '', 'updated_at' => 0 ),
			),
			'appearance'       => array(
				'preset'        => 'neutral_light',
				'custom_colors' => self::neutral_light_colors(),
			),
		);
	}

	/**
	 * Merge saved option over defaults. No write. Unknown stored modes become visitor.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved    = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings                     = array();
		$settings['mode']             = self::normalize_mode( isset( $saved['mode'] ) ? $saved['mode'] : '' );
		$settings['currencies']       = self::normalize_currencies( isset( $saved['currencies'] ) ? $saved['currencies'] : $defaults['currencies'] );
		$settings['default_currency'] = ( isset( $saved['default_currency'] ) && is_string( $saved['default_currency'] ) && in_array( $saved['default_currency'], $settings['currencies'], true ) )
			? $saved['default_currency']
			: $settings['currencies'][0];

		$settings['rates'] = array();
		foreach ( self::CURRENCIES as $code ) {
			$row                        = isset( $saved['rates'][ $code ] ) && is_array( $saved['rates'][ $code ] ) ? $saved['rates'][ $code ] : array();
			$settings['rates'][ $code ] = array(
				'value'      => isset( $row['value'] ) && is_string( $row['value'] ) ? $row['value'] : '',
				'updated_at' => isset( $row['updated_at'] ) ? (int) $row['updated_at'] : 0,
			);
		}

		$settings['appearance'] = self::normalize_appearance( isset( $saved['appearance'] ) ? $saved['appearance'] : null );

		return $settings;
	}

	/**
	 * Coerce a value to a string only when it is a genuine scalar string/number.
	 * Arrays, objects, bool and null return null (rejected without warnings).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function to_scalar_string( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		return null;
	}

	/**
	 * @param mixed $input Raw currencies.
	 * @return array Non-empty subset of allowed currencies (order preserved, unique).
	 */
	private static function normalize_currencies( $input ) {
		if ( ! is_array( $input ) ) {
			return array( 'EUR' );
		}
		$curr = array();
		foreach ( $input as $value ) {
			$code = self::to_scalar_string( $value );
			if ( null !== $code && in_array( $code, self::CURRENCIES, true ) && ! in_array( $code, $curr, true ) ) {
				$curr[] = $code;
			}
		}
		return empty( $curr ) ? array( 'EUR' ) : $curr;
	}

	/** Neutral Light hex map (current reviewed palette). */
	public static function neutral_light_colors() {
		$out = array();
		foreach ( self::color_tokens() as $key => $token ) {
			$out[ $key ] = $token['hex'];
		}
		return $out;
	}

	/**
	 * Accept only a full six-digit hex colour. Case-insensitive. A bare six-digit
	 * value is prefixed with #. Canonical result is uppercase #RRGGBB. Never uses
	 * binary floats. Three-digit, empty and other malformed values return null.
	 *
	 * @param mixed $raw Raw value.
	 * @return string|null Canonical #RRGGBB or null.
	 */
	public static function sanitize_hex( $raw ) {
		$s = self::to_scalar_string( $raw );
		if ( null === $s ) {
			return null;
		}
		$s = trim( $s );
		if ( preg_match( '/^[0-9A-Fa-f]{6}$/', $s ) ) {
			$s = '#' . $s;
		}
		if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $s ) ) {
			return null;
		}
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$wp = sanitize_hex_color( $s );
			if ( ! is_string( $wp ) || ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $wp ) ) {
				return null;
			}
			return strtoupper( $wp );
		}
		return strtoupper( $s );
	}

	/**
	 * @param mixed $raw Saved or submitted appearance blob.
	 * @return array{preset:string, custom_colors: array<string,string>}
	 */
	public static function normalize_appearance( $raw ) {
		$defaults = array(
			'preset'        => 'neutral_light',
			'custom_colors' => self::neutral_light_colors(),
		);
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		$preset = self::to_scalar_string( isset( $raw['preset'] ) ? $raw['preset'] : null );
		if ( null === $preset || ! in_array( $preset, self::PRESETS, true ) ) {
			$preset = 'neutral_light';
		}
		$saved_colors = ( isset( $raw['custom_colors'] ) && is_array( $raw['custom_colors'] ) ) ? $raw['custom_colors'] : array();
		$colors       = array();
		foreach ( self::color_tokens() as $key => $token ) {
			$hex = self::sanitize_hex( isset( $saved_colors[ $key ] ) ? $saved_colors[ $key ] : null );
			$colors[ $key ] = null !== $hex ? $hex : $token['hex'];
		}
		return array(
			'preset'        => $preset,
			'custom_colors' => $colors,
		);
	}

	/**
	 * Merge a submitted appearance payload over previously valid values.
	 * Switching preset never drops saved custom colours.
	 *
	 * @param mixed $input    Submitted appearance.
	 * @param array $existing Previously normalized appearance.
	 * @return array
	 */
	public static function sanitize_appearance( $input, $existing ) {
		$existing = self::normalize_appearance( $existing );
		if ( ! is_array( $input ) ) {
			return $existing;
		}
		$preset = self::to_scalar_string( isset( $input['preset'] ) ? $input['preset'] : null );
		if ( null === $preset || ! in_array( $preset, self::PRESETS, true ) ) {
			$preset = $existing['preset'];
		}
		$submitted = ( isset( $input['custom_colors'] ) && is_array( $input['custom_colors'] ) ) ? $input['custom_colors'] : null;
		$colors    = $existing['custom_colors'];
		if ( is_array( $submitted ) ) {
			$invalid = false;
			foreach ( array_keys( self::color_tokens() ) as $key ) {
				if ( ! array_key_exists( $key, $submitted ) ) {
					continue;
				}
				$hex = self::sanitize_hex( $submitted[ $key ] );
				if ( null !== $hex ) {
					$colors[ $key ] = $hex;
				} else {
					$invalid = true;
					// Invalid or empty: keep the previously valid colour (or Neutral Light via existing).
				}
			}
			if ( $invalid ) {
				add_settings_error(
					self::OPTION,
					'custom_colors',
					__( 'Enter a valid six-digit hexadecimal colour.', 'cryptoria-sats-converter' )
				);
			}
		}
		return array(
			'preset'        => $preset,
			'custom_colors' => $colors,
		);
	}

	/**
	 * Public appearance for the shortcode renderer.
	 *
	 * @return array{preset:string, custom_colors: array<string,string>}
	 */
	public static function get_appearance() {
		$s = self::get_settings();
		return $s['appearance'];
	}

	/**
	 * Build a style attribute body of allowlisted CSS variables only.
	 *
	 * @param array $colors Sanitized hex map.
	 * @return string
	 */
	public static function custom_style_attribute( $colors ) {
		$parts = array();
		foreach ( self::color_tokens() as $key => $token ) {
			$hex = self::sanitize_hex( isset( $colors[ $key ] ) ? $colors[ $key ] : null );
			if ( null === $hex ) {
				$hex = $token['hex'];
			}
			$parts[] = $token['var'] . ':' . $hex;
		}
		return implode( ';', $parts );
	}

	/* ------------------------------------------------------------------ menu */

	public static function add_menu() {
		self::$hook_suffix = add_options_page(
			__( 'Cryptoria Sats Converter', 'cryptoria-sats-converter' ),
			__( 'Bitcoin/Sats Converter', 'cryptoria-sats-converter' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	/**
	 * Enqueue settings CSS/JS only on this plugin's screen.
	 * Colour picking uses WordPress core wp-color-picker (Iris), never globally.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_admin( $hook ) {
		if ( $hook !== self::$hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			self::HANDLE_ADMIN,
			CRYPTORIA_SATS_URL . 'assets/css/admin-settings.css',
			array( 'wp-color-picker' ),
			CRYPTORIA_SATS_VERSION
		);
		wp_enqueue_script(
			self::HANDLE_ADMIN,
			CRYPTORIA_SATS_URL . 'assets/js/admin-settings.js',
			array( 'wp-color-picker' ),
			CRYPTORIA_SATS_VERSION,
			true
		);
	}

	/**
	 * Add a Settings link on the Plugins list.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function settings_link( $links ) {
		$url  = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cryptoria-sats-converter' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/* -------------------------------------------------------------- sanitize */

	/**
	 * Strict sanitization for the whole option array. Rejects malformed nested
	 * shapes without PHP warnings and without overwriting valid stored data.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$existing = self::get_settings();
		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$out = array();

		// Mode. Values outside the allowlist, including a retired stored mode, become visitor.
		$mode_raw    = self::to_scalar_string( isset( $input['mode'] ) ? $input['mode'] : null );
		$out['mode'] = self::normalize_mode( $mode_raw );

		// Currencies + default currency.
		$out['currencies'] = self::normalize_currencies( isset( $input['currencies'] ) ? $input['currencies'] : array() );
		$def               = self::to_scalar_string( isset( $input['default_currency'] ) ? $input['default_currency'] : null );
		$out['default_currency'] = ( null !== $def && in_array( $def, $out['currencies'], true ) ) ? $def : $out['currencies'][0];

		// Rates.
		$rates_in     = ( isset( $input['rates'] ) && is_array( $input['rates'] ) ) ? $input['rates'] : array();
		$out['rates'] = array();
		foreach ( self::CURRENCIES as $code ) {
			$prev       = isset( $existing['rates'][ $code ] ) ? $existing['rates'][ $code ] : array( 'value' => '', 'updated_at' => 0 );
			$prev_value = (string) $prev['value'];
			$prev_ts    = (int) $prev['updated_at'];

			// Absent submission (e.g. field not rendered for the current mode or a
			// disabled currency): keep the stored rate untouched.
			if ( ! array_key_exists( $code, $rates_in ) || ! is_array( $rates_in[ $code ] ) || ! array_key_exists( 'value', $rates_in[ $code ] ) ) {
				$out['rates'][ $code ] = array( 'value' => $prev_value, 'updated_at' => $prev_ts );
				continue;
			}

			$raw_val = self::to_scalar_string( $rates_in[ $code ]['value'] );
			if ( null === $raw_val ) {
				// Malformed shape (nested array/object): reject silently, keep valid data.
				$out['rates'][ $code ] = array( 'value' => $prev_value, 'updated_at' => $prev_ts );
				continue;
			}

			$raw = trim( $raw_val );
			if ( '' === $raw ) {
				// Intentional clear: drop value and reset its timestamp.
				$out['rates'][ $code ] = array( 'value' => '', 'updated_at' => 0 );
				continue;
			}

			$canonical = self::parse_rate_string( $raw );
			if ( null === $canonical ) {
				add_settings_error(
					self::OPTION,
					'rate_' . $code,
					sprintf(
						/* translators: %s: currency code. */
						__( 'Enter a valid price for 1 BTC in %s.', 'cryptoria-sats-converter' ),
						$code
					)
				);
				$out['rates'][ $code ] = array( 'value' => $prev_value, 'updated_at' => $prev_ts );
				continue;
			}

			if ( $canonical === $prev_value ) {
				// Unchanged canonical value keeps its original timestamp.
				$out['rates'][ $code ] = array( 'value' => $prev_value, 'updated_at' => $prev_ts );
			} else {
				$out['rates'][ $code ] = array( 'value' => $canonical, 'updated_at' => time() );
			}
		}

		$out['appearance'] = self::sanitize_appearance(
			isset( $input['appearance'] ) ? $input['appearance'] : null,
			$existing['appearance']
		);

		return $out;
	}

	/**
	 * Validate and canonicalize a positive decimal rate string.
	 *
	 * Accepts an optional leading '+', comma or dot decimal separator, up to 16
	 * integer and 8 fractional digits. Rejects zero, negatives, exponent,
	 * grouping/mixed separators, NaN/Infinity, arrays and malformed input.
	 * Never uses floatval.
	 *
	 * @param mixed $raw Raw value.
	 * @return string|null Canonical dot-decimal string, or null if invalid.
	 */
	public static function parse_rate_string( $raw ) {
		if ( ! is_string( $raw ) && ! is_int( $raw ) && ! is_float( $raw ) ) {
			return null;
		}
		$s = trim( (string) $raw );
		if ( '' === $s ) {
			return null;
		}
		if ( ! preg_match( '/^\+?(\d+)(?:[.,](\d+))?$/', $s, $m ) ) {
			return null;
		}
		$int  = $m[1];
		$frac = isset( $m[2] ) ? $m[2] : '';
		if ( strlen( $int ) > 16 || strlen( $frac ) > 8 ) {
			return null;
		}

		$int = ltrim( $int, '0' );
		if ( '' === $int ) {
			$int = '0';
		}
		$frac = rtrim( $frac, '0' );

		$canonical = '' !== $frac ? $int . '.' . $frac : $int;

		// Must be strictly positive.
		if ( '0' === $canonical ) {
			return null;
		}

		return $canonical;
	}

	/* --------------------------------------------------------------- runtime */

	/**
	 * @return int Stale threshold in seconds (filterable, default 24h).
	 */
	public static function get_stale_seconds() {
		return (int) apply_filters( 'cryptoria_sats_stale_seconds', DAY_IN_SECONDS );
	}

	/**
	 * @param int $updated_at Unix timestamp.
	 * @return bool
	 */
	public static function is_stale( $updated_at ) {
		$updated_at = (int) $updated_at;
		if ( $updated_at <= 0 ) {
			return false;
		}
		return ( time() - $updated_at ) > self::get_stale_seconds();
	}

	/**
	 * Usable admin rate for a currency, or null.
	 *
	 * @param string $currency Currency code.
	 * @return array{value:string,updated_at:int}|null
	 */
	public static function get_usable_rate( $currency ) {
		$s = self::get_settings();
		if ( ! in_array( $currency, $s['currencies'], true ) ) {
			return null;
		}
		$row = isset( $s['rates'][ $currency ] ) ? $s['rates'][ $currency ] : null;
		if ( ! is_array( $row ) || '' === (string) $row['value'] ) {
			return null;
		}
		return array( 'value' => (string) $row['value'], 'updated_at' => (int) $row['updated_at'] );
	}

	/**
	 * Non-secret per-instance config for the frontend. get_settings() already
	 * maps any unknown stored mode to visitor, so only visitor and admin_manual
	 * reach the page.
	 *
	 * @return array
	 */
	public static function get_runtime_config() {
		$s          = self::get_settings();
		$currencies = $s['currencies'];

		$rates = array();
		if ( 'admin_manual' === $s['mode'] ) {
			foreach ( $currencies as $code ) {
				$usable = self::get_usable_rate( $code );
				if ( $usable ) {
					$rates[ $code ] = array(
						'value'      => $usable['value'],
						'updated_at' => $usable['updated_at'],
						'stale'      => self::is_stale( $usable['updated_at'] ),
						'message'    => sprintf(
							/* translators: %s: human-readable date/time. */
							__( 'Rate set by the site administrator · updated %s', 'cryptoria-sats-converter' ),
							self::human_time( $usable['updated_at'] )
						),
					);
				}
			}
		}

		return array(
			'mode'            => $s['mode'],
			'currencies'      => $currencies,
			'defaultCurrency' => $s['default_currency'],
			'rates'           => $rates,
			'strings'         => self::frontend_strings(),
		);
	}

	/**
	 * @return array Translatable frontend strings.
	 */
	private static function frontend_strings() {
		$fiat_label = array();
		$sub_cent   = array();
		foreach ( self::CURRENCIES as $code ) {
			$fiat_label[ $code ] = sprintf(
				/* translators: %s: currency code. */
				__( 'Amount in %s', 'cryptoria-sats-converter' ),
				$code
			);
			$sub_cent[ $code ] = sprintf(
				/* translators: %s: currency code. */
				__( 'Less than 0.01 %s', 'cryptoria-sats-converter' ),
				$code
			);
		}

		return array(
			'rateLabel'           => __( 'Price for 1 BTC', 'cryptoria-sats-converter' ),
			'fiatLabel'           => $fiat_label,
			'visitorNote'         => __( 'Manual rate — it does not update automatically.', 'cryptoria-sats-converter' ),
			'noRate'              => __( 'Enter the price of 1 BTC.', 'cryptoria-sats-converter' ),
			'staleWarning'        => __( 'This rate may be outdated.', 'cryptoria-sats-converter' ),
			'subCent'             => $sub_cent,
		);
	}

	/**
	 * Timezone-correct display of a stored Unix timestamp using the site's
	 * configured formats and timezone. Storage stays a raw timestamp.
	 *
	 * @param int $ts Unix timestamp.
	 * @return string
	 */
	private static function human_time( $ts ) {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/* ------------------------------------------------------------- rendering */

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = self::get_settings();
		$opt = self::OPTION;

		$modes = array(
			'visitor'      => __( 'Visitor enters the rate (default)', 'cryptoria-sats-converter' ),
			'admin_manual' => __( 'Administrator-defined rate', 'cryptoria-sats-converter' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Cryptoria Sats Converter', 'cryptoria-sats-converter' ); ?></h1>
			<?php settings_errors( self::OPTION ); ?>

			<form method="post" action="options.php" id="csc-settings">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php echo esc_html__( 'Exchange-rate mode', 'cryptoria-sats-converter' ); ?></h2>
				<fieldset>
					<?php foreach ( $modes as $value => $label ) : ?>
						<label style="display:block;margin:.25rem 0;">
							<input type="radio" name="<?php echo esc_attr( $opt . '[mode]' ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								<?php checked( $s['mode'], $value ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<h2><?php echo esc_html__( 'Enabled fiat currencies', 'cryptoria-sats-converter' ); ?></h2>
				<fieldset>
					<?php foreach ( self::CURRENCIES as $code ) : ?>
						<label style="display:inline-block;margin-right:1rem;">
							<input type="checkbox" name="<?php echo esc_attr( $opt . '[currencies][]' ); ?>" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $s['currencies'], true ) ); ?> />
							<?php echo esc_html( $code ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php echo esc_html__( 'At least one currency must remain enabled.', 'cryptoria-sats-converter' ); ?></p>
				</fieldset>

				<h2><?php echo esc_html__( 'Default currency', 'cryptoria-sats-converter' ); ?></h2>
				<select name="<?php echo esc_attr( $opt . '[default_currency]' ); ?>">
					<?php foreach ( self::CURRENCIES as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['default_currency'], $code ); ?>><?php echo esc_html( $code ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html__( 'Must be one of the enabled currencies.', 'cryptoria-sats-converter' ); ?></p>

				<div class="csc-section" data-csc-mode-section="admin_manual" <?php echo ( 'admin_manual' === $s['mode'] ) ? '' : 'hidden'; ?>>
					<h2><?php echo esc_html__( 'Administrator-defined rates', 'cryptoria-sats-converter' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
						<?php foreach ( self::CURRENCIES as $code ) : ?>
							<tr data-csc-currency="<?php echo esc_attr( $code ); ?>" <?php echo in_array( $code, $s['currencies'], true ) ? '' : 'hidden'; ?>>
								<th scope="row">
									<label for="<?php echo esc_attr( 'csc-rate-' . $code ); ?>">
										<?php echo esc_html( sprintf( /* translators: %s: currency code. */ __( 'BTC/%s rate', 'cryptoria-sats-converter' ), $code ) ); ?>
									</label>
								</th>
								<td>
									<input type="text" id="<?php echo esc_attr( 'csc-rate-' . $code ); ?>"
										name="<?php echo esc_attr( $opt . '[rates][' . $code . '][value]' ); ?>"
										value="<?php echo esc_attr( $s['rates'][ $code ]['value'] ); ?>"
										inputmode="decimal" autocomplete="off" class="regular-text" />
									<p class="description">
										<?php
										$ts = (int) $s['rates'][ $code ]['updated_at'];
										if ( $ts > 0 && '' !== $s['rates'][ $code ]['value'] ) {
											echo esc_html(
												sprintf(
													/* translators: %s: human-readable date/time. */
													__( 'Last updated: %s', 'cryptoria-sats-converter' ),
													self::human_time( $ts )
												)
											);
											if ( self::is_stale( $ts ) ) {
												echo ' — ' . esc_html__( 'possibly outdated', 'cryptoria-sats-converter' );
											}
										} else {
											echo esc_html__( 'No saved rate.', 'cryptoria-sats-converter' );
										}
										?>
									</p>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<h2><?php echo esc_html__( 'Appearance', 'cryptoria-sats-converter' ); ?></h2>
				<fieldset>
					<legend class="screen-reader-text"><?php echo esc_html__( 'Appearance', 'cryptoria-sats-converter' ); ?></legend>
					<?php
					$appearance_presets = array(
						'neutral_light'  => __( 'Neutral Light', 'cryptoria-sats-converter' ),
						'cryptoria_dark' => __( 'Dark', 'cryptoria-sats-converter' ),
						'custom'         => __( 'Custom', 'cryptoria-sats-converter' ),
					);
					foreach ( $appearance_presets as $value => $label ) :
						?>
						<label style="display:block;margin:.25rem 0;">
							<input type="radio" name="<?php echo esc_attr( $opt . '[appearance][preset]' ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								<?php checked( $s['appearance']['preset'], $value ); ?>
								aria-describedby="csc-appearance-desc" />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<p id="csc-appearance-desc" class="description">
						<?php echo esc_html__( 'The Neutral Light and Dark presets use accessible default contrast. After choosing custom colours, check that text, borders, focus and error states remain readable. You are responsible for contrast in custom combinations.', 'cryptoria-sats-converter' ); ?>
					</p>
				</fieldset>

				<div class="csc-section" data-csc-appearance-section="custom" <?php echo ( 'custom' === $s['appearance']['preset'] ) ? '' : 'hidden'; ?>>
					<fieldset>
						<legend><?php echo esc_html__( 'Custom colours', 'cryptoria-sats-converter' ); ?></legend>
						<p id="csc-hex-help" class="description">
							<?php echo esc_html__( 'Six-digit hexadecimal colour, for example #2B1720.', 'cryptoria-sats-converter' ); ?>
						</p>
						<?php
						$color_labels = array(
							'surface'       => __( 'Background', 'cryptoria-sats-converter' ),
							'text'          => __( 'Primary text', 'cryptoria-sats-converter' ),
							'muted'         => __( 'Secondary text', 'cryptoria-sats-converter' ),
							'input_bg'      => __( 'Input background', 'cryptoria-sats-converter' ),
							'input_text'    => __( 'Input text', 'cryptoria-sats-converter' ),
							'border'        => __( 'Border', 'cryptoria-sats-converter' ),
							'disabled_bg'   => __( 'Disabled background', 'cryptoria-sats-converter' ),
							'disabled_text' => __( 'Disabled text', 'cryptoria-sats-converter' ),
							'focus'         => __( 'Focus', 'cryptoria-sats-converter' ),
							'error'         => __( 'Error', 'cryptoria-sats-converter' ),
							'selected'      => __( 'Selected currency', 'cryptoria-sats-converter' ),
							'accent'        => __( 'Accent', 'cryptoria-sats-converter' ),
						);
						foreach ( self::color_tokens() as $key => $token ) :
							$hex = isset( $s['appearance']['custom_colors'][ $key ] ) ? $s['appearance']['custom_colors'][ $key ] : $token['hex'];
							$hex = self::sanitize_hex( $hex );
							if ( null === $hex ) {
								$hex = strtoupper( $token['hex'] );
							}
							$cid    = 'csc-color-' . $key;
							$pid    = 'csc-picker-' . $key;
							$choose = sprintf(
								/* translators: %s: colour setting name, e.g. Background */
								__( 'Choose %s colour', 'cryptoria-sats-converter' ),
								$color_labels[ $key ]
							);
							?>
							<div class="csc-hex-row">
								<label for="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $color_labels[ $key ] ); ?></label>
								<div class="csc-hex-control">
									<input type="text" class="csc-hex-input" id="<?php echo esc_attr( $cid ); ?>"
										name="<?php echo esc_attr( $opt . '[appearance][custom_colors][' . $key . ']' ); ?>"
										value="<?php echo esc_attr( $hex ); ?>"
										maxlength="7" size="8" spellcheck="false" autocomplete="off" autocapitalize="off"
										pattern="<?php echo esc_attr( '#[0-9A-Fa-f]{6}' ); ?>"
										aria-describedby="csc-hex-help"
										data-csc-hex-input />
									<button type="button" class="csc-hex-swatch" data-csc-hex-swatch
										aria-expanded="false"
										aria-haspopup="dialog"
										aria-controls="<?php echo esc_attr( $pid ); ?>"
										style="<?php echo esc_attr( '--csc-hex-swatch:' . $hex ); ?>">
										<span class="screen-reader-text"><?php echo esc_html( $choose ); ?></span>
									</button>
									<div id="<?php echo esc_attr( $pid ); ?>" class="csc-hex-picker" hidden
										role="dialog"
										aria-label="<?php echo esc_attr( $choose ); ?>"
										data-csc-hex-picker>
										<input type="text" class="csc-hex-stage" data-csc-hex-stage
											value="<?php echo esc_attr( $hex ); ?>"
											maxlength="7" autocomplete="off" spellcheck="false"
											tabindex="-1" aria-hidden="true" />
										<span class="csc-hex-picker-preview" data-csc-hex-preview aria-hidden="true"
											style="<?php echo esc_attr( '--csc-hex-swatch:' . $hex ); ?>"></span>
										<div class="csc-hex-picker-actions">
											<button type="button" class="button button-primary" data-csc-hex-apply>
												<?php echo esc_html__( 'Apply colour', 'cryptoria-sats-converter' ); ?>
											</button>
											<button type="button" class="button" data-csc-hex-cancel>
												<?php echo esc_html__( 'Cancel', 'cryptoria-sats-converter' ); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</fieldset>
				</div>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Usage', 'cryptoria-sats-converter' ); ?></h2>
			<p><?php echo esc_html__( 'Place this shortcode in content, a widget, or a template:', 'cryptoria-sats-converter' ); ?></p>
			<input type="text" class="large-text code" readonly value="[bitcoin_sats_converter]" onclick="this.select();" />

			<h2><?php echo esc_html__( 'Status', 'cryptoria-sats-converter' ); ?></h2>
			<table class="widefat striped" style="max-width:640px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Current mode', 'cryptoria-sats-converter' ); ?></th>
						<td><?php echo esc_html( isset( $modes[ $s['mode'] ] ) ? $modes[ $s['mode'] ] : $s['mode'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enabled currencies', 'cryptoria-sats-converter' ); ?></th>
						<td><?php echo esc_html( implode( ', ', $s['currencies'] ) ); ?></td>
					</tr>
					<?php foreach ( $s['currencies'] as $code ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( sprintf( /* translators: %s: currency code. */ __( '%s rate status', 'cryptoria-sats-converter' ), $code ) ); ?></th>
							<td>
								<?php
								if ( 'admin_manual' === $s['mode'] ) {
									$usable = self::get_usable_rate( $code );
									echo esc_html( $usable ? __( 'available', 'cryptoria-sats-converter' ) : __( 'missing', 'cryptoria-sats-converter' ) );
								} else {
									echo esc_html__( 'entered by the visitor', 'cryptoria-sats-converter' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
