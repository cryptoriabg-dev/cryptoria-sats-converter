/**
 * Cryptoria Bitcoin/Sats Converter — settings screen progressive enhancement.
 *
 * Toggles the administrator-rate section by selected mode, each rate row by
 * enabled currency, and the Custom colours section by appearance preset. HEX
 * colour fields stay the committed form controls. Swatch buttons open the
 * WordPress core Iris picker against a nameless staging input so dragging does
 * not write the public field until Apply colour. Cancel/Escape restore the
 * value captured on open. Persistence is still the page Save Changes button.
 *
 * jQuery is used only because wp-color-picker requires it. Nothing is attached
 * as a public global. The page remains usable if this script does not run.
 */
( function ( $ ) {
	'use strict';

	var OPT = 'cryptoria_sats_converter';
	var HEX_FULL = /^#[0-9A-Fa-f]{6}$/;
	var HEX_BARE = /^[0-9A-Fa-f]{6}$/;
	var root = document.getElementById( 'csc-settings' );
	if ( ! root ) {
		return;
	}
	if ( root.getAttribute( 'data-csc-ready' ) === '1' ) {
		return;
	}
	root.setAttribute( 'data-csc-ready', '1' );

	function all( selector ) {
		return Array.prototype.slice.call( root.querySelectorAll( selector ) );
	}

	function closestAttr( el, selector ) {
		if ( ! el ) {
			return null;
		}
		if ( el.closest ) {
			return el.closest( selector );
		}
		return null;
	}

	function selectedMode() {
		var checked = root.querySelector( 'input[name="' + OPT + '[mode]"]:checked' );
		return checked ? checked.value : 'visitor';
	}

	function syncMode() {
		var mode = selectedMode();
		all( '[data-csc-mode-section]' ).forEach( function ( section ) {
			section.hidden = section.getAttribute( 'data-csc-mode-section' ) !== mode;
		} );
	}

	function syncCurrencies() {
		all( 'input[name="' + OPT + '[currencies][]"]' ).forEach( function ( checkbox ) {
			all( '[data-csc-currency="' + checkbox.value + '"]' ).forEach( function ( row ) {
				row.hidden = ! checkbox.checked;
			} );
		} );
	}

	function selectedAppearance() {
		var checked = root.querySelector( 'input[name="' + OPT + '[appearance][preset]"]:checked' );
		return checked ? checked.value : 'neutral_light';
	}

	function syncAppearance() {
		var preset = selectedAppearance();
		all( '[data-csc-appearance-section]' ).forEach( function ( section ) {
			section.hidden = section.getAttribute( 'data-csc-appearance-section' ) !== preset;
		} );
		if ( preset !== 'custom' ) {
			cancelPicker( openControl(), false );
		}
	}

	function canonicalHex( raw ) {
		var s = String( raw == null ? '' : raw ).replace( /^[\s\u00A0]+|[\s\u00A0]+$/g, '' );
		if ( HEX_BARE.test( s ) ) {
			s = '#' + s;
		}
		if ( ! HEX_FULL.test( s ) ) {
			return null;
		}
		return s.toUpperCase();
	}

	function hexInputs() {
		return all( '[data-csc-hex-input]' );
	}

	function paintTarget( el, hex ) {
		if ( ! el || ! hex ) {
			return;
		}
		el.style.setProperty( '--csc-hex-swatch', hex );
	}

	function paintSwatch( input ) {
		var control = closestAttr( input, '.csc-hex-control' );
		var swatch = control ? control.querySelector( '[data-csc-hex-swatch]' ) : null;
		var hex = canonicalHex( input.value );
		if ( hex ) {
			paintTarget( swatch, hex );
		}
	}

	function normalizeHexField( input ) {
		var hex = canonicalHex( input.value );
		if ( hex ) {
			input.value = hex;
			paintSwatch( input );
		}
	}

	function hasPicker() {
		return $ && $.fn && typeof $.fn.wpColorPicker === 'function';
	}

	function concealChromeNode( el ) {
		if ( ! el ) {
			return;
		}
		el.setAttribute( 'aria-hidden', 'true' );
		el.setAttribute( 'tabindex', '-1' );
		if ( 'inert' in el ) {
			el.inert = true;
		}
	}

	/*
	 * Conceal only generated WP colour-result / input-wrap chrome for this
	 * staging input. Leave .iris-picker square, hue and palettes usable.
	 */
	function concealGeneratedChrome( stage ) {
		var wrap = closestAttr( stage, '.wp-picker-container' );
		var result;
		var inputWrap;
		var extras;
		if ( ! wrap || wrap.getAttribute( 'data-csc-chrome-hidden' ) === '1' ) {
			return;
		}
		result = wrap.querySelector( '.wp-color-result' );
		inputWrap = wrap.querySelector( '.wp-picker-input-wrap' );
		concealChromeNode( result );
		concealChromeNode( inputWrap );
		extras = wrap.querySelectorAll( '.wp-picker-input-wrap button, .wp-picker-input-wrap input, .wp-picker-clear, .wp-picker-default' );
		Array.prototype.forEach.call( extras, concealChromeNode );
		concealChromeNode( stage );
		wrap.setAttribute( 'data-csc-chrome-hidden', '1' );
	}

	function degradePickers() {
		root.classList.add( 'csc-no-iris' );
		all( '[data-csc-hex-swatch]' ).forEach( function ( swatch ) {
			swatch.hidden = true;
			swatch.disabled = true;
			swatch.setAttribute( 'aria-hidden', 'true' );
			swatch.setAttribute( 'tabindex', '-1' );
		} );
		all( '[data-csc-hex-picker]' ).forEach( function ( picker ) {
			picker.hidden = true;
		} );
	}

	function focusPickerDialog( picker ) {
		var active;
		if ( ! picker ) {
			return;
		}
		if ( ! picker.hasAttribute( 'tabindex' ) ) {
			picker.setAttribute( 'tabindex', '-1' );
		}
		picker.focus();
		active = document.activeElement;
		if ( active && picker.contains( active ) && closestAttr( active, '.wp-color-result, .wp-picker-input-wrap' ) ) {
			picker.focus();
		}
	}

	function openControl() {
		return root.querySelector( '.csc-hex-control[data-csc-picker-open="1"]' );
	}

	function parts( control ) {
		if ( ! control ) {
			return null;
		}
		return {
			control: control,
			input: control.querySelector( '[data-csc-hex-input]' ),
			swatch: control.querySelector( '[data-csc-hex-swatch]' ),
			picker: control.querySelector( '[data-csc-hex-picker]' ),
			stage: control.querySelector( '[data-csc-hex-stage]' ),
			preview: control.querySelector( '[data-csc-hex-preview]' )
		};
	}

	function stagedHex( p ) {
		var hex = canonicalHex( p.stage ? p.stage.value : '' );
		if ( hex ) {
			return hex;
		}
		return canonicalHex( p.preview ? p.preview.style.getPropertyValue( '--csc-hex-swatch' ) : '' );
	}

	function setStage( p, hex ) {
		if ( ! hex ) {
			return;
		}
		if ( p.stage ) {
			p.stage.value = hex;
		}
		paintTarget( p.preview, hex );
		if ( hasPicker() && p.stage && p.stage.getAttribute( 'data-csc-iris-ready' ) === '1' ) {
			$( p.stage ).wpColorPicker( 'color', hex );
		}
	}

	function captureSnapshot( p ) {
		p.control.setAttribute( 'data-csc-snap-field', p.input ? p.input.value : '' );
		p.control.setAttribute(
			'data-csc-snap-swatch',
			p.swatch ? p.swatch.style.getPropertyValue( '--csc-hex-swatch' ) : ''
		);
	}

	function restoreSnapshot( p ) {
		var field = p.control.getAttribute( 'data-csc-snap-field' );
		var swatchHex = canonicalHex( p.control.getAttribute( 'data-csc-snap-swatch' ) );
		if ( p.input ) {
			p.input.value = field == null ? '' : field;
		}
		if ( swatchHex ) {
			paintTarget( p.swatch, swatchHex );
			setStage( p, swatchHex );
		}
	}

	function closePicker( control, restore, moveFocus ) {
		var p = parts( control );
		if ( ! p || p.control.getAttribute( 'data-csc-picker-open' ) !== '1' ) {
			return;
		}
		if ( restore ) {
			restoreSnapshot( p );
		}
		p.control.removeAttribute( 'data-csc-picker-open' );
		if ( p.picker ) {
			p.picker.hidden = true;
		}
		if ( p.swatch ) {
			p.swatch.setAttribute( 'aria-expanded', 'false' );
			if ( moveFocus ) {
				p.swatch.focus();
			}
		}
	}

	function cancelPicker( control, moveFocus ) {
		closePicker( control, true, moveFocus );
	}

	function applyColour( control ) {
		var p = parts( control );
		var hex;
		if ( ! p || p.control.getAttribute( 'data-csc-picker-open' ) !== '1' ) {
			return;
		}
		hex = stagedHex( p );
		if ( ! hex ) {
			return;
		}
		if ( p.input ) {
			p.input.value = hex;
		}
		paintTarget( p.swatch, hex );
		setStage( p, hex );
		closePicker( control, false, true );
	}

	function openPicker( control ) {
		var p = parts( control );
		var current = openControl();
		var start;
		if ( ! hasPicker() ) {
			return;
		}
		if ( ! p || ! p.picker || ! p.swatch ) {
			return;
		}
		if ( current && current !== control ) {
			cancelPicker( current, false );
		}
		if ( p.control.getAttribute( 'data-csc-picker-open' ) === '1' ) {
			cancelPicker( control, true );
			return;
		}
		captureSnapshot( p );
		start = canonicalHex( p.input ? p.input.value : '' ) ||
			canonicalHex( p.swatch.style.getPropertyValue( '--csc-hex-swatch' ) );
		if ( start ) {
			setStage( p, start );
		}
		p.control.setAttribute( 'data-csc-picker-open', '1' );
		p.picker.hidden = false;
		p.swatch.setAttribute( 'aria-expanded', 'true' );
		focusPickerDialog( p.picker );
	}

	function bindPickers() {
		if ( ! hasPicker() ) {
			degradePickers();
			return;
		}
		all( '[data-csc-hex-stage]' ).forEach( function ( stage ) {
			var control;
			var preview;
			if ( stage.getAttribute( 'data-csc-iris-ready' ) === '1' ) {
				return;
			}
			stage.setAttribute( 'data-csc-iris-ready', '1' );
			control = closestAttr( stage, '.csc-hex-control' );
			preview = control ? control.querySelector( '[data-csc-hex-preview]' ) : null;
			$( stage ).wpColorPicker( {
				hide: false,
				palettes: true,
				change: function ( event, ui ) {
					var hex = canonicalHex( ui && ui.color && typeof ui.color.toString === 'function' ? ui.color.toString() : '' );
					if ( hex ) {
						stage.value = hex;
						paintTarget( preview, hex );
					}
				}
			} );
			concealGeneratedChrome( stage );
		} );
	}

	root.addEventListener( 'change', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.name ) {
			return;
		}
		if ( target.name === OPT + '[mode]' ) {
			syncMode();
		} else if ( target.name === OPT + '[currencies][]' ) {
			syncCurrencies();
		} else if ( target.name === OPT + '[appearance][preset]' ) {
			syncAppearance();
		}
	} );

	root.addEventListener( 'input', function ( event ) {
		var target = event.target;
		var control;
		if ( ! target || ! target.hasAttribute( 'data-csc-hex-input' ) ) {
			return;
		}
		control = closestAttr( target, '.csc-hex-control' );
		if ( control && control.getAttribute( 'data-csc-picker-open' ) === '1' ) {
			closePicker( control, false, false );
		}
		normalizeHexField( target );
		paintSwatch( target );
	} );

	root.addEventListener( 'blur', function ( event ) {
		var target = event.target;
		if ( target && target.hasAttribute( 'data-csc-hex-input' ) ) {
			normalizeHexField( target );
		}
	}, true );

	root.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var apply = closestAttr( target, '[data-csc-hex-apply]' );
		var cancel = closestAttr( target, '[data-csc-hex-cancel]' );
		var swatch = closestAttr( target, '[data-csc-hex-swatch]' );
		var picker = closestAttr( target, '[data-csc-hex-picker]' );
		if ( target && target.type === 'submit' ) {
			hexInputs().forEach( normalizeHexField );
			return;
		}
		if ( closestAttr( target, '.csc-hex-picker .wp-color-result, .csc-hex-picker .wp-picker-input-wrap' ) ) {
			event.preventDefault();
			event.stopPropagation();
			return;
		}
		if ( apply ) {
			event.preventDefault();
			applyColour( closestAttr( apply, '.csc-hex-control' ) );
			return;
		}
		if ( cancel ) {
			event.preventDefault();
			cancelPicker( closestAttr( cancel, '.csc-hex-control' ), true );
			return;
		}
		if ( swatch ) {
			event.preventDefault();
			if ( hasPicker() && ! swatch.disabled ) {
				openPicker( closestAttr( swatch, '.csc-hex-control' ) );
			}
			return;
		}
		if ( ! picker ) {
			cancelPicker( openControl(), false );
		}
	} );

	root.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}
		if ( ! openControl() ) {
			return;
		}
		event.preventDefault();
		cancelPicker( openControl(), true );
	} );

	syncMode();
	syncCurrencies();
	syncAppearance();
	hexInputs().forEach( paintSwatch );
	bindPickers();
}( typeof jQuery === 'undefined' ? null : jQuery ) );
