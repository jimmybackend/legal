import { DOCUMENT_SERVICES, wholePages, hundredthsOfMinute, fxMicros, calculateQuote } from "./pricing.mjs";

(() => {
  "use strict";
  const toggle = document.querySelector(".menu-toggle");
  const nav = document.getElementById("site-navigation");
  if (toggle && nav) {
    toggle.addEventListener("click", () => {
      const open = !nav.classList.contains("is-open");
      nav.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", String(open));
    });
    nav.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    }));
  }

  const pages = document.getElementById("pages");
  const minutes = document.getElementById("minutes");
  const service = document.getElementById("document-service");
  const print = document.getElementById("print-quote");
  const download = document.getElementById("download-quote");
  const refresh = document.getElementById("refresh-rate");
  const pesos = new Intl.NumberFormat("es-MX", {style:"currency",currency:"MXN"});
  const rateFormat = new Intl.NumberFormat("es-MX",{minimumFractionDigits:4,maximumFractionDigits:6});
  const issueDateFormat = new Intl.DateTimeFormat("es-MX",{
    timeZone:"America/Mexico_City",year:"numeric",month:"2-digit",day:"2-digit",
    hour:"2-digit",minute:"2-digit",second:"2-digit",hour12:false,
  });
  let fx = null;
  let loading = false;

  const set = (id, value) => {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
  };
  const date = iso => iso.split("-").reverse().join("/");
  function input() {
    const n = wholePages(pages?.value ?? "");
    const m = hundredthsOfMinute(minutes?.value ?? "");
    const code = service?.value;
    if (n === null || m === null || !DOCUMENT_SERVICES[code]) return null;
    return {pages:n,minuteHundredths:m,service:code};
  }
  function update() {
    const values = input();
    const selected = DOCUMENT_SERVICES[service?.value];
    if (selected) {
      const el = document.getElementById("document-unit-price");
      if (el?.firstChild) el.firstChild.textContent = pesos.format(selected.centavos/100) + " ";
      set("summary-service-label",selected.label);
    }
    const result = values ? calculateQuote({...values,exchangeRateMicros:fx?.micros ?? null}) : null;
    const waiting = Boolean(values && values.minuteHundredths > 0 && !fx);
    set("pages-total", values ? pesos.format(values.pages*selected.centavos/100) : "Cantidad inválida");
    set("summary-pages", values ? pesos.format(values.pages*selected.centavos/100) : "—");
    set("summary-pages-quantity",values ? values.pages+" hojas × "+pesos.format(selected.centavos/100)+" por hoja":"Cantidad inválida");
    set("summary-minutes-quantity",values ? (values.minuteHundredths/100).toFixed(2)+" minutos × US$0.6593/min":"Cantidad inválida");
    set("minutes-total", result ? pesos.format(result.audioCents/100) : waiting ? "Esperando tipo de cambio" : "Cantidad inválida");
    set("summary-minutes",result ? pesos.format(result.audioCents/100) : "—");
    set("grand-total",result ? pesos.format(result.totalCents/100) : waiting ? "Esperando tipo de cambio" : "Revise las cantidades");
    const enabled=Boolean(result && (values.pages>0 || values.minuteHundredths>0) && !loading);
    if (print) print.disabled=!enabled;
    if (download) download.disabled=!enabled;
    return result ? {values,result} : null;
  }
  function invalidate() {
    set("quote-issued-at","Se asignará al generar la cotización");
  }
  function showFx() {
    if (!fx) {
      set("fx-rate","No disponible");
      set("fx-rate-date","Fecha de publicación: no disponible");
      set("fx-equivalent","Equivalente pendiente: se necesita una tasa verificada.");
      set("quote-fx-snapshot","No disponible; se pueden cotizar documentos sin audio/video.");
      return;
    }
    set("fx-rate",rateFormat.format(fx.rate)+" MXN por USD");
    set("fx-rate-date","Fecha de publicación: "+date(fx.date)+" (último día disponible)");
    set("fx-equivalent","Equivalente aproximado: "+pesos.format(0.6593*fx.rate)+" por minuto.");
    set("quote-fx-snapshot",rateFormat.format(fx.rate)+" MXN/USD · publicado "+date(fx.date)+" · Frankfurter");
  }
  async function updateFx() {
    if (loading) return;
    loading=true;
    fx=null;
    invalidate();
    if (refresh) refresh.disabled=true;
    set("fx-status","Consultando tipo de cambio publicado...");
    showFx();
    update();
    try {
      const response=await fetch("tipo-cambio.php",{
        method:"GET",credentials:"same-origin",cache:"no-store",headers:{"Accept":"application/json"},
      });
      if (!response.ok) throw new Error("Sin servicio");
      const body=await response.json();
      if (body.available!==true || body.base!=="USD" || body.quote!=="MXN" ||
          typeof body.rate!=="number" || !/^\d{4}-\d{2}-\d{2}$/.test(body.rate_date)) {
        throw new Error("Respuesta inválida");
      }
      const micros=fxMicros(body.rate);
      if (micros===null) throw new Error("Tasa inválida");
      fx=Object.freeze({rate:body.rate,micros,date:body.rate_date,
        retrievedAt:body.retrieved_at,source:body.source,sourceUrl:body.source_url});
      set("fx-status","Referencia verificada. Se mantendrá fija hasta pulsar Actualizar.");
    } catch(e) {
      fx=null;
      set("fx-status","Sin tipo de cambio verificable: no se emitirán cotizaciones de audio/video. Intente actualizar.");
    } finally {
      loading=false;
      if (refresh) refresh.disabled=false;
      showFx();
      update();
    }
  }
  function issue() {
    const snapshot=update();
    if (!snapshot) return null;
    const {values,result}=snapshot;
    const issued=new Date();
    const appliedFx=values.minuteHundredths>0 ? fx : null;
    const record={
      schema:"esforzados.legal.quotation.v1",
      issuer:"Esforzados Legal",
      issued_at:issued.toISOString(),
      issued_at_mexico_city:issueDateFormat.format(issued),
      currency:"MXN",
      document_service:{
        code:values.service,description:DOCUMENT_SERVICES[values.service].label,
        pages:values.pages,unit_price_mxn:DOCUMENT_SERVICES[values.service].centavos/100,
        subtotal_mxn:result.documentsCents/100,
      },
      audio_video:{
        minutes:values.minuteHundredths/100,unit_price_usd:0.6593,
        exchange_rate_usd_mxn:appliedFx?.rate ?? null,
        exchange_rate_publication_date:appliedFx?.date ?? null,
        exchange_rate_retrieved_at:appliedFx?.retrievedAt ?? null,
        exchange_rate_source:appliedFx?.source ?? null,
        exchange_rate_source_url:appliedFx?.sourceUrl ?? null,
        subtotal_mxn:result.audioCents/100,
      },
      total_mxn:result.totalCents/100,
      terms:"Importe informativo y sujeto a confirmación. Impuestos, revisión humana y certificaciones se cotizan por separado. No acredita pago.",
    };
    set("quote-issued-at",record.issued_at_mexico_city);
    return record;
  }
  for(const element of [pages,minutes,service]){
    element?.addEventListener("input",()=>{invalidate();update();});
    element?.addEventListener("change",()=>{invalidate();update();});
  }
  refresh?.addEventListener("click",updateFx);
  print?.addEventListener("click",()=>{if(issue()) window.print();});
  download?.addEventListener("click",()=>{
    const record=issue();
    if(!record) return;
    const blob=new Blob([JSON.stringify(record,null,2)+"\n"],{type:"application/json"});
    const url=URL.createObjectURL(blob);
    const link=document.createElement("a");
    link.href=url;
    link.download="cotizacion-esforzados-legal-"+record.issued_at.slice(0,10)+".json";
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  update();
  showFx();
  updateFx();
  const year=document.getElementById("year");
  if(year) year.textContent=String(new Date().getFullYear());
})();
