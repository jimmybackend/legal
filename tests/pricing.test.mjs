import test from "node:test";
import assert from "node:assert/strict";
import {
  DOCUMENT_SERVICES, AUDIO_USD_RATE_1E4, wholePages, hundredthsOfMinute,
  fxMicros, calculateQuote,
} from "../assets/pricing.mjs";

test("los cuatro precios pactados son invariables en MXN", () => {
  assert.deepEqual(Object.values(DOCUMENT_SERVICES).map(x => x.centavos), [200, 250, 350, 500]);
});
test("precios de 7000 hojas según servicio", () => {
  for (const [code, expected] of Object.entries({
    ocr_json: 1_400_000, scan_image: 1_750_000,
    scan_ocr_json: 2_450_000, managed: 3_500_000,
  })) {
    assert.equal(calculateQuote({pages:7000, service:code, minuteHundredths:0, exchangeRateMicros:null}).totalCents,expected);
  }
});
test("USD 0.6593 x 18.5, 60 minutos, redondeo final: MXN 731.82", () => {
  assert.equal(AUDIO_USD_RATE_1E4,6593);
  assert.equal(fxMicros(18.5),18_500_000);
  const result=calculateQuote({pages:100,service:"ocr_json",minuteHundredths:6000,exchangeRateMicros:fxMicros(18.5)});
  assert.equal(result.audioCents,73182);
  assert.equal(result.totalCents,93182);
});
test("conversión de minutos con centésimas",()=>{
  assert.equal(hundredthsOfMinute("2.5"),250);
  assert.equal(hundredthsOfMinute("2.555"),null);
  assert.equal(wholePages("1.5"),null);
  assert.equal(wholePages("-1"),null);
  assert.equal(calculateQuote({pages:0,service:"ocr_json",minuteHundredths:250,exchangeRateMicros:fxMicros(18.5)}).audioCents,3049);
});
test("sin tasa de cambio bloquea cualquier cotización de audio",()=>{
  assert.equal(calculateQuote({pages:4,service:"ocr_json",minuteHundredths:100,exchangeRateMicros:null}),null);
  assert.equal(calculateQuote({pages:4,service:"ocr_json",minuteHundredths:0,exchangeRateMicros:null}).totalCents,800);
});
