/**
 * Cryptoria Bitcoin/Sats Converter — front-end controller.
 *
 * Module scope only: nothing is attached as a public script global. Exported
 * functions exist for automated DOM tests; ES module exports are not globals.
 * Auto-boot runs only when a real document is present.
 *
 * The math module is loaded with a dynamic import that carries the same ?ver
 * query as this file's own URL, so a plugin update busts the cached math module
 * instead of leaving it stale.
 *
 * Controls are rendered disabled by PHP. They are enabled here only after the
 * math module loads, and fiat is enabled only when the active mode/rate allows a
 * real conversion. A disabled fiat field can never mutate BTC/sats.
 */

const SELF_URL = import.meta.url;

function versionQuery() {
  try {
    const v = new URL(SELF_URL).searchParams.get('ver');
    return v ? '?ver=' + encodeURIComponent(v) : '';
  } catch (e) {
    return '';
  }
}

function roots() {
  return Array.prototype.slice.call(
    document.querySelectorAll(
      '.cryptoria-sats-converter[data-cryptoria-sats-converter]:not(.is-ready):not(.is-unsupported):not(.is-load-error)'
    )
  );
}

function setStatus(root, text) {
  const status = root.querySelector('[data-role="status"]');
  if (status) {
    status.textContent = text || '';
  }
}

function disableInputs(root) {
  root.querySelectorAll('input').forEach(function (input) {
    input.setAttribute('disabled', 'disabled');
    input.setAttribute('aria-disabled', 'true');
  });
}

/** Hard failure: this browser lacks BigInt. Controls stay disabled. */
export function markUnsupported(root) {
  root.classList.add('is-unsupported');
  setStatus(root, root.getAttribute('data-msg-unsupported') || '');
  disableInputs(root);
}

/** Hard failure: the math module could not be loaded. Controls stay disabled. */
export function markLoadError(root) {
  root.classList.add('is-load-error');
  setStatus(root, root.getAttribute('data-msg-load-error') || '');
  disableInputs(root);
}

function readConfig(root) {
  const def = {
    mode: 'visitor',
    currencies: ['EUR', 'USD'],
    defaultCurrency: 'EUR',
    rates: {},
    strings: {},
  };
  const raw = root.getAttribute('data-config');
  if (!raw) {
    return def;
  }
  try {
    const parsed = JSON.parse(raw);
    return Object.assign(def, parsed);
  } catch (e) {
    return def;
  }
}

/**
 * Wire one converter root. Idempotent per root.
 *
 * @param {Element} root
 * @param {object}  M    The production math module.
 */
export function initInstance(root, M) {
  if (
    root.classList.contains('is-ready') ||
    root.classList.contains('is-unsupported') ||
    root.classList.contains('is-load-error')
  ) {
    return;
  }

  const btc = root.querySelector('[data-field="btc"]');
  const sats = root.querySelector('[data-field="sats"]');
  if (!btc || !sats) {
    return;
  }

  root.classList.add('is-ready');

  const config = readConfig(root);
  const strings = Object.assign(
    {
      rateLabel: '',
      fiatLabel: {},
      visitorNote: '',
      noRate: '',
      staleWarning: '',
      subCent: '',
    },
    config.strings || {}
  );

  const fiat = root.querySelector('[data-field="fiat"]');
  const rate = root.querySelector('[data-field="rate"]');
  const rateLabel = root.querySelector('[data-role="rate-label"]');
  const rateUnit = root.querySelector('[data-role="rate-unit"]');
  const rateNote = root.querySelector('[data-role="rate-note"]');
  const fiatLabel = root.querySelector('[data-role="fiat-label"]');
  const fiatNote = root.querySelector('[data-role="fiat-note"]');
  const currencyRadios = Array.prototype.slice.call(
    root.querySelectorAll('[data-field="currency"]')
  );

  const invalidMsg = root.getAttribute('data-msg-invalid') || '';
  const invalidRateMsg = root.getAttribute('data-msg-invalid-rate') || invalidMsg;

  const editable = [ btc, sats ];
  if (fiat) {
    editable.push( fiat );
  }
  if (rate) {
    editable.push( rate );
  }

  // Per-instance, in-memory visitor rates. Never persisted or transmitted.
  const visitorRates = {};
  ( config.currencies || [] ).forEach( function ( c ) {
    visitorRates[ c ] = null;
  } );

  let currency = config.defaultCurrency;
  if ( ( config.currencies || [] ).indexOf( currency ) === -1 ) {
    currency = ( config.currencies || [ 'EUR' ] )[ 0 ];
  }
  let satsBig = null; // canonical amount hub (BigInt | null)
  let source = null; // 'btcsats' | 'fiat'
  let programmatic = false;

  /* ---------------------------------------------------------- helpers */

  function enable( el ) {
    if ( ! el ) {
      return;
    }
    el.removeAttribute( 'disabled' );
    el.removeAttribute( 'aria-disabled' );
  }

  function disable( el ) {
    if ( ! el ) {
      return;
    }
    el.setAttribute( 'disabled', 'disabled' );
    el.setAttribute( 'aria-disabled', 'true' );
  }

  function errorSpan( el ) {
    const row = el.closest( '.cryptoria-sats-converter__row' );
    return row ? row.querySelector( '[data-role="err"]' ) : null;
  }

  function rateWrap( el ) {
    if ( ! el || el.getAttribute( 'data-field' ) !== 'rate' ) {
      return null;
    }
    return el.closest( '.cryptoria-sats-converter__input-wrap' );
  }

  function clearFieldError( el ) {
    el.removeAttribute( 'aria-invalid' );
    const span = errorSpan( el );
    if ( span ) {
      span.textContent = '';
    }
    const wrap = rateWrap( el );
    if ( wrap ) {
      wrap.classList.remove( 'is-invalid' );
    }
  }

  function setFieldError( el, message ) {
    el.setAttribute( 'aria-invalid', 'true' );
    const span = errorSpan( el );
    if ( span ) {
      span.textContent = message || invalidMsg;
    }
    const wrap = rateWrap( el );
    if ( wrap ) {
      wrap.classList.add( 'is-invalid' );
    }
  }

  function clearErrorsExcept( keep ) {
    editable.forEach( function ( el ) {
      if ( el !== keep ) {
        clearFieldError( el );
      }
    } );
  }

  function write( el, value ) {
    if ( ! el ) {
      return;
    }
    programmatic = true;
    el.value = value;
    clearFieldError( el );
    programmatic = false;
  }

  function setRateNote( text ) {
    if ( rateNote ) {
      rateNote.textContent = text || '';
    }
  }

  function setFiatNote( text ) {
    if ( fiatNote ) {
      fiatNote.textContent = text || '';
    }
  }

  function subCentMessage( cur ) {
    const s = strings.subCent;
    if ( s && typeof s === 'object' ) {
      return s[ cur ] || '';
    }
    return typeof s === 'string' ? s : '';
  }

  function updateSubCentNote( rs ) {
    if ( source === 'fiat' || satsBig === null || rs === null || ! M.isPositiveSubCent( satsBig, rs ) ) {
      setFiatNote( '' );
      return;
    }
    setFiatNote( subCentMessage( currency ) );
  }

  function rateStringFor( cur ) {
    if ( config.mode === 'visitor' ) {
      return visitorRates[ cur ] || '';
    }
    const r = config.rates && config.rates[ cur ];
    return r && r.value ? r.value : '';
  }

  function rateScaledFor( cur ) {
    const s = rateStringFor( cur );
    if ( ! s ) {
      return null;
    }
    try {
      return M.rateScaledFromString( s );
    } catch ( e ) {
      return null;
    }
  }

  function fiatNoteFor( cur ) {
    if ( config.mode === 'visitor' ) {
      return rateStringFor( cur ) ? strings.visitorNote : strings.noRate;
    }
    if ( config.mode === 'admin_manual' ) {
      const r = config.rates && config.rates[ cur ];
      if ( r && r.value ) {
        let msg = r.message || '';
        if ( r.stale ) {
          msg = msg ? msg + ' — ' + strings.staleWarning : strings.staleWarning;
        }
        return msg;
      }
      return strings.noRate;
    }
    return strings.noRate;
  }

  function updateRateLabel() {
    if ( rateLabel ) {
      const lbl = strings.rateLabel;
      rateLabel.textContent = typeof lbl === 'string' ? lbl : ( lbl && lbl[ currency ] ) || '';
    }
    if ( rateUnit ) {
      rateUnit.textContent = currency || '';
    }
  }

  function updateFiatLabel() {
    if ( fiatLabel ) {
      fiatLabel.textContent = ( strings.fiatLabel && strings.fiatLabel[ currency ] ) || '';
    }
  }

  function writeBtcSats( s ) {
    write( btc, M.satsToBtcString( s ) );
    write( sats, s.toString() );
  }

  function clearBtcSats() {
    write( btc, '' );
    write( sats, '' );
  }

  // Whether the fiat field may accept input in the current mode/currency.
  function fiatAvailable() {
    if ( config.mode === 'visitor' ) {
      return true; // visitor may type fiat first, then supply the rate
    }
    if ( config.mode === 'admin_manual' ) {
      return rateScaledFor( currency ) !== null;
    }
    return false;
  }

  function updateFiatDerived() {
    if ( ! fiat ) {
      return;
    }
    const rs = rateScaledFor( currency );
    if ( satsBig === null || rs === null ) {
      write( fiat, '' );
      setFiatNote( '' );
      return;
    }
    const minor = M.satsToFiatMinor( satsBig, rs );
    write( fiat, M.minorToFiatString( minor ) );
    updateSubCentNote( rs );
  }

  // Enable/disable fiat per availability and refresh its derived value. Never
  // touches canonical sats or the BTC/sats fields. Rate status lives on the
  // rate field, not under the amount.
  function refreshFiat() {
    setRateNote( fiatNoteFor( currency ) );
    if ( ! fiat ) {
      return;
    }
    if ( fiatAvailable() ) {
      enable( fiat );
      updateFiatDerived();
    } else {
      disable( fiat );
      write( fiat, '' );
      setFiatNote( '' );
    }
  }

  /* ---------------------------------------------------------- handlers */

  function onBtcSats( el, kind ) {
    if ( programmatic ) {
      return;
    }
    const c = M.classify( el.value, kind );
    clearErrorsExcept( el );

    if ( c.status === 'valid' ) {
      const p = kind === 'btc' ? M.btcToSats( el.value ) : M.parseSats( el.value );
      satsBig = p.sats;
      source = 'btcsats';
      if ( kind === 'btc' ) {
        write( sats, satsBig.toString() );
      } else {
        write( btc, M.satsToBtcString( satsBig ) );
      }
      clearFieldError( el );
    } else {
      satsBig = null;
      if ( kind === 'btc' ) {
        write( sats, '' );
      } else {
        write( btc, '' );
      }
      if ( c.status === 'invalid' ) {
        setFieldError( el );
      } else {
        clearFieldError( el );
      }
    }

    refreshFiat();
  }

  function onFiat( el ) {
    if ( programmatic ) {
      return;
    }
    // Defensive: a disabled fiat field must never mutate BTC/sats state.
    if ( el.hasAttribute( 'disabled' ) ) {
      return;
    }
    const c = M.classify( el.value, 'fiat' );
    clearErrorsExcept( el );
    const rs = rateScaledFor( currency );

    if ( c.status === 'valid' ) {
      source = 'fiat';
      clearFieldError( el );
      setFiatNote( '' );
      if ( rs === null ) {
        // Visitor entered a fiat amount before a rate. Do not fabricate or
        // destroy BTC/sats; wait for the rate.
        setRateNote( fiatNoteFor( currency ) );
        return;
      }
      satsBig = M.fiatMinorToSats( M.parseFiatMinor( el.value ).minor, rs );
      writeBtcSats( satsBig );
      setRateNote( fiatNoteFor( currency ) );
      return;
    }

    // empty | incomplete | invalid
    if ( c.status === 'invalid' ) {
      setFieldError( el );
    } else {
      clearFieldError( el );
    }
    setFiatNote( '' );
    // Only clear BTC/sats when fiat is genuinely the active source; a stray
    // edit of a derived fiat must not damage a BTC/sats-driven value.
    if ( source === 'fiat' ) {
      satsBig = null;
      clearBtcSats();
    }
  }

  function onRate( el ) {
    if ( programmatic ) {
      return;
    }
    if ( el.hasAttribute( 'readonly' ) || el.hasAttribute( 'disabled' ) ) {
      return;
    }
    const c = M.classify( el.value, 'rate' );
    clearErrorsExcept( el );

    if ( c.status === 'valid' ) {
      visitorRates[ currency ] = el.value.trim().replace( /^\+/, '' ).replace( ',', '.' );
      clearFieldError( el );
      if ( source === 'fiat' && fiat ) {
        const fc = M.classify( fiat.value, 'fiat' );
        const rs = rateScaledFor( currency );
        if ( fc.status === 'valid' && rs !== null ) {
          satsBig = M.fiatMinorToSats( M.parseFiatMinor( fiat.value ).minor, rs );
          writeBtcSats( satsBig );
        } else {
          satsBig = null;
          clearBtcSats();
        }
        setRateNote( fiatNoteFor( currency ) );
      } else {
        refreshFiat();
      }
      return;
    }

    visitorRates[ currency ] = null;
    if ( c.status === 'invalid' ) {
      setFieldError( el, invalidRateMsg );
      setRateNote( '' );
    } else {
      clearFieldError( el );
      setRateNote( fiatNoteFor( currency ) );
    }
    if ( source === 'fiat' ) {
      satsBig = null;
      clearBtcSats();
    } else {
      write( fiat, '' );
      setFiatNote( '' );
    }
  }

  function syncSelectedCurrency() {
    currencyRadios.forEach( function ( radio ) {
      const label = radio.closest( '.cryptoria-sats-converter__currency-option' );
      if ( ! label ) {
        return;
      }
      if ( radio.value === currency ) {
        label.classList.add( 'is-selected' );
      } else {
        label.classList.remove( 'is-selected' );
      }
    } );
  }

  function onCurrency( newCur ) {
    if ( newCur === currency || ( config.currencies || [] ).indexOf( newCur ) === -1 ) {
      return;
    }
    currency = newCur;
    syncSelectedCurrency();
    if ( rate ) {
      write( rate, rateStringFor( currency ) );
    }
    updateRateLabel();
    updateFiatLabel();
    // BTC/sats are preserved. The single fiat field is recomputed from the
    // canonical sats using the NEW currency's rate; never cross-convert.
    if ( source === 'fiat' ) {
      source = 'btcsats';
    }
    refreshFiat();
  }

  /* ---------------------------------------------------------- wiring */

  btc.addEventListener( 'input', function () {
    onBtcSats( btc, 'btc' );
  } );
  sats.addEventListener( 'input', function () {
    onBtcSats( sats, 'sats' );
  } );
  if ( fiat ) {
    fiat.addEventListener( 'input', function () {
      onFiat( fiat );
    } );
  }
  if ( rate ) {
    rate.addEventListener( 'input', function () {
      onRate( rate );
    } );
  }
  currencyRadios.forEach( function ( radio ) {
    radio.addEventListener( 'change', function () {
      onCurrency( radio.value );
    } );
  } );

  /* ---------------------------------------------------------- enable + paint */

  enable( btc );
  enable( sats );
  currencyRadios.forEach( enable );

  if ( rate ) {
    if ( config.mode === 'visitor' ) {
      enable( rate );
      rate.removeAttribute( 'readonly' );
    } else {
      // Readonly display: not disabled (so the value is readable), not editable.
      enable( rate );
      rate.setAttribute( 'readonly', 'readonly' );
    }
    write( rate, rateStringFor( currency ) );
  }
  updateRateLabel();
  updateFiatLabel();
  syncSelectedCurrency();
  refreshFiat();
}

function boot() {
  const list = roots();
  if ( ! list.length ) {
    return;
  }

  if ( typeof BigInt !== 'function' ) {
    list.forEach( markUnsupported );
    return;
  }

  const mathUrl = new URL( './math.js' + versionQuery(), SELF_URL ).href;
  import( mathUrl )
    .then( function ( M ) {
      list.forEach( function ( root ) {
        initInstance( root, M );
      } );
    } )
    .catch( function () {
      // Distinct from unsupported: the browser is capable, the module failed
      // to load (MIME type, cache, or minifier interference).
      list.forEach( markLoadError );
    } );
}

if ( typeof document !== 'undefined' ) {
  if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', boot );
  } else {
    boot();
  }
}
