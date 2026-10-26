// Precios al público, en centavos MXN. No dependen del tipo de cambio.
export const DOCUMENT_SERVICES = Object.freeze({
  ocr_json: Object.freeze({ label: "OCR de hojas ya digitalizadas + JSON", centavos: 200 }),
  scan_image: Object.freeze({ label: "Escaneo físico + imagen digital", centavos: 250 }),
  scan_ocr_json: Object.freeze({ label: "Escaneo físico + OCR + JSON", centavos: 350 }),
  managed: Object.freeze({ label: "Escaneo + OCR + JSON + organización", centavos: 500 }),
});
export const AUDIO_USD_RATE_1E4 = 6593; // US$0.6593/min

export function wholePages(value) {
  const input = String(value);
  if (!/^\d+$/.test(input)) return null;
  const qty = Number(input);
  return Number.isSafeInteger(qty) && qty <= 1_000_000 ? qty : null;
}

export function hundredthsOfMinute(value) {
  const input = String(value);
  if (!/^\d+(?:\.\d{1,2})?$/.test(input)) return null;
  const [integer, decimals = ""] = input.split(".");
  const units = Number(integer) * 100 + Number(decimals.padEnd(2, "0"));
  return Number.isSafeInteger(units) && units <= 100_000_000 ? units : null;
}

export function fxMicros(value) {
  if (typeof value !== "number" || !Number.isFinite(value) || value <= 0 || value >= 200) return null;
  return Math.round(value * 1_000_000);
}

export function calculateQuote({ pages, service, minuteHundredths, exchangeRateMicros }) {
  const selected = DOCUMENT_SERVICES[service];
  if (!selected || !Number.isInteger(pages) || pages < 0 || pages > 1_000_000
      || !Number.isInteger(minuteHundredths) || minuteHundredths < 0 || minuteHundredths > 100_000_000) {
    return null;
  }
  const documentsCents = pages * selected.centavos;
  let audioCents = 0;
  if (minuteHundredths > 0) {
    if (!Number.isSafeInteger(exchangeRateMicros) || exchangeRateMicros <= 0) return null;
    // USD rate = 6593/10000; minuteHundredths = minutes*100;
    // FX micro = MXN/USD * 1,000,000. Resultado exacto en centavos:
    // (minuteHundredths * 6593 * fxMicros) / 10,000,000,000.
    const numerator = BigInt(minuteHundredths) * BigInt(AUDIO_USD_RATE_1E4) * BigInt(exchangeRateMicros);
    audioCents = Number((numerator + 5_000_000_000n) / 10_000_000_000n);
  }
  return { documentsCents, audioCents, totalCents: documentsCents + audioCents, service: selected };
}
