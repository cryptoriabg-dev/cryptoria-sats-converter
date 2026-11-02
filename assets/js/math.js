/**
 * Cryptoria Bitcoin Sats Converter — canonical parser + arithmetic.
 *
 * Pure ES module. Decimal-string parsing + BigInt only.
 *
 * Parse-safety: this file contains no BigInt literals (e.g. 100n) and makes
 * no BigInt() call at module-evaluation time, so importing it never throws a
 * SyntaxError or ReferenceError on a browser without BigInt. Callers must
 * gate real use behind isBigIntSupported().
 *
 * The exact same module is imported by the browser (converter.js) and by
 * the automated tests. No second Number-based engine.
 */

// Grammar-derived limits (UX/abuse guards, NOT a 21M-BTC supply cap).
// `eur` is kept as a backwards-compatible alias of the currency-neutral `fiat`.
export const GRAMMAR = {
  btc:  { maxIntDigits: 16, maxFracDigits: 8, allowFraction: true,  maxRawLen: 26 },
  sats: { maxIntDigits: 16, maxFracDigits: 0, allowFraction: false, maxRawLen: 17 },
  fiat: { maxIntDigits: 16, maxFracDigits: 2, allowFraction: true,  maxRawLen: 20 },
  eur:  { maxIntDigits: 16, maxFracDigits: 2, allowFraction: true,  maxRawLen: 20 },
  rate: { maxIntDigits: 16, maxFracDigits: 8, allowFraction: true,  maxRawLen: 26 },
};

// Lazily-built BigInt constants. Never executed at import time.
let _K = null;
function K() {
  if (_K === null) {
    _K = {
      TWO: BigInt(2),
      HUNDRED: BigInt(100),
      ONE: BigInt(1),
      ZERO: BigInt(0),
      SATS_PER_BTC: BigInt(100000000), // 1e8
      SCALE_1E14: BigInt('100000000000000'), // 1e14
    };
  }
  return _K;
}

export function isBigIntSupported() {
  return typeof BigInt === 'function';
}

/** Strip leading/trailing ASCII whitespace and NBSP; keep the interior intact. */
function trimEdges(raw) {
  if (typeof raw !== 'string') {
    return '';
  }
  return raw.replace(/^[\s\u00A0]+/, '').replace(/[\s\u00A0]+$/, '');
}

/**
 * Classify a raw field value against its grammar.
 *
 * @param {string} raw
 * @param {'btc'|'sats'|'eur'} kind
 * @returns {{status:'empty'|'incomplete'|'invalid'|'valid', normalized:string, intDigits?:string, fracDigits?:string}}
 */
export function classify(raw, kind) {
  const g = GRAMMAR[kind];
  if (!g) {
    return { status: 'invalid', normalized: '' };
  }

  const s = trimEdges(raw);

  if (s === '') {
    return { status: 'empty', normalized: '' };
  }
  if (s.length > g.maxRawLen) {
    return { status: 'invalid', normalized: s };
  }

  // Incomplete: a lone plus.
  if (s === '+') {
    return { status: 'incomplete', normalized: s };
  }

  // Incomplete: trailing/bare decimal separator, only where a fraction is allowed.
  if (g.allowFraction && /^\+?\d*[.,]$/.test(s)) {
    const intPart = s.replace(/^\+/, '').slice(0, -1);
    if (intPart.length <= g.maxIntDigits) {
      return { status: 'incomplete', normalized: s };
    }
    return { status: 'invalid', normalized: s };
  }

  const re = g.allowFraction ? /^\+?(\d+)(?:[.,](\d+))?$/ : /^\+?(\d+)$/;
  const m = re.exec(s);
  if (!m) {
    return { status: 'invalid', normalized: s };
  }

  const intDigits = m[1];
  const fracDigits = m[2] || '';
  if (intDigits.length > g.maxIntDigits) {
    return { status: 'invalid', normalized: s };
  }
  if (fracDigits.length > g.maxFracDigits) {
    return { status: 'invalid', normalized: s };
  }

  return { status: 'valid', normalized: s, intDigits, fracDigits };
}

/** Positive-only half-up integer division: floor((2N + D) / (2D)). */
export function halfUpDiv(N, D) {
  const two = K().TWO;
  return (N * two + D) / (D * two);
}

/**
 * BTC decimal string -> integer sats (BigInt). Exact; >8 fractional digits is invalid.
 * @returns {{ok:true, sats:bigint}|{ok:false, status:string}}
 */
export function btcToSats(raw) {
  const c = classify(raw, 'btc');
  if (c.status !== 'valid') {
    return { ok: false, status: c.status };
  }
  const frac = (c.fracDigits + '00000000').slice(0, 8);
  return { ok: true, sats: BigInt(c.intDigits + frac) };
}

/**
 * sats string -> integer sats (BigInt). Non-negative integers only.
 * @returns {{ok:true, sats:bigint}|{ok:false, status:string}}
 */
export function parseSats(raw) {
  const c = classify(raw, 'sats');
  if (c.status !== 'valid') {
    return { ok: false, status: c.status };
  }
  return { ok: true, sats: BigInt(c.intDigits) };
}

/** integer sats (BigInt) -> exact BTC decimal string, trailing zeros trimmed. */
export function satsToBtcString(sats) {
  const k = K();
  const whole = sats / k.SATS_PER_BTC;
  const rem = sats % k.SATS_PER_BTC;
  let frac = rem.toString().padStart(8, '0').replace(/0+$/, '');
  return frac.length ? whole.toString() + '.' + frac : whole.toString();
}

/**
 * Fiat string (EUR or USD) -> canonical integer minor units/cents (BigInt).
 * Max 2 fractional digits.
 * @returns {{ok:true, minor:bigint}|{ok:false, status:string}}
 */
export function parseFiatMinor(raw) {
  const c = classify(raw, 'fiat');
  if (c.status !== 'valid') {
    return { ok: false, status: c.status };
  }
  const frac = (c.fracDigits + '00').slice(0, 2);
  return { ok: true, minor: BigInt(c.intDigits + frac) };
}

/** integer minor units (BigInt) -> fiat decimal string with exactly 2 places. */
export function minorToFiatString(minor) {
  const k = K();
  const whole = minor / k.HUNDRED;
  const rem = minor % k.HUNDRED;
  return whole.toString() + '.' + rem.toString().padStart(2, '0');
}

/**
 * fiat-per-BTC (integer or decimal string/number) -> rateScaled = round(rate * 1e8)
 * (BigInt). Currency-neutral: identical for EUR and USD. Exact for <=8 decimals;
 * half-up beyond that.
 *
 * Throws on malformed input and on a zero/non-positive rate, so downstream
 * division can never divide by zero. No network here.
 */
export function rateScaledFromString(rate) {
  const s = String(rate).trim();
  const m = /^\+?(\d+)(?:[.,](\d+))?$/.exec(s);
  if (!m) {
    throw new Error('invalid rate');
  }
  const intD = m[1];
  const frac = m[2] || '';
  let scaled;
  if (frac.length <= 8) {
    scaled = BigInt(intD + (frac + '00000000').slice(0, 8));
  } else {
    scaled = BigInt(intD + frac.slice(0, 8));
    if (frac.charCodeAt(8) - 48 >= 5) {
      scaled = scaled + K().ONE;
    }
  }
  if (scaled <= K().ZERO) {
    throw new Error('non-positive rate');
  }
  return scaled;
}

/** sats (BigInt) + rateScaled (BigInt) -> fiat minor units (BigInt), half-up once. */
export function satsToFiatMinor(sats, rateScaled) {
  return halfUpDiv(sats * rateScaled, K().SCALE_1E14);
}

/** fiat minor units (BigInt) + rateScaled (BigInt) -> sats (BigInt), half-up once. */
export function fiatMinorToSats(minor, rateScaled) {
  return halfUpDiv(minor * K().SCALE_1E14, rateScaled);
}

/* ------------------------------------------------------------------ *
 * Backwards-compatible EUR-named wrappers (Phase 2 API). Delegate to  *
 * the currency-neutral functions above; no duplicated arithmetic.     *
 * ------------------------------------------------------------------ */

/** @deprecated Use parseFiatMinor. */
export function parseEurCents(raw) {
  const r = parseFiatMinor(raw);
  return r.ok ? { ok: true, cents: r.minor } : r;
}

/** @deprecated Use minorToFiatString. */
export function centsToEurString(cents) {
  return minorToFiatString(cents);
}

/** @deprecated Use rateScaledFromString. */
export function rateScaledFromEurPerBtc(rate) {
  return rateScaledFromString(rate);
}

/** @deprecated Use satsToFiatMinor. */
export function satsToEurCents(sats, rateScaled) {
  return satsToFiatMinor(sats, rateScaled);
}

/** @deprecated Use fiatMinorToSats. */
export function eurCentsToSats(cents, rateScaled) {
  return fiatMinorToSats(cents, rateScaled);
}

/**
 * True when a strictly positive sats amount converts to 0 canonical cents,
 * i.e. the UI must show "< 0.01 €" instead of "0.01 €" (Erratum 1). Also the
 * sats-side analogue: a positive EUR that rounds below 1 sat ("< 1 sat").
 */
export function isPositiveSubCent(sats, rateScaled) {
  const k = K();
  return sats > k.ZERO && satsToEurCents(sats, rateScaled) === k.ZERO;
}
