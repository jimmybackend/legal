(() => {
  "use strict";
  const toggle = document.querySelector(".menu-toggle");
  const nav = document.getElementById("site-navigation");
  if (toggle && nav) {
    toggle.addEventListener("click", () => {
      const open = !nav.classList.contains("is-open");
      nav.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
    });
    nav.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    }));
  }
  const pages = document.getElementById("pages");
  const minutes = document.getElementById("minutes");
  const currency = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
  // Tarifa promedio: USD 0.6593/min × 18.50 MXN/USD = 12.19705 MXN/min.
  // Cálculo entero: tarifa en diezmilésimas de USD y tipo de cambio en centavos.
  const USD_RATE_1E4 = 6593;
  const FX_RATE_CENTAVOS = 1850;
  function quantity(control, whole) {
    const number = Number(control?.value ?? 0);
    if (!Number.isFinite(number) || number < 0 || number > 1000000 || (whole && !Number.isInteger(number)) || (!whole && Math.abs(number * 100 - Math.round(number * 100)) > 1e-7)) return null;
    return number;
  }
  function updateQuote() {
    if (!pages || !minutes) return;
    const p = quantity(pages, true);
    const m = quantity(minutes, false);
    const valid = p !== null && m !== null;
    const documentTotal = valid ? Math.round(p * 200) / 100 : 0;
    // Convertir a centavos de MXN y redondear únicamente el subtotal final.
    // Evita pérdidas por redondear prematuramente la tarifa por minuto.
    const recordingTotal = valid
      ? Math.round(Math.round(m * 100) * USD_RATE_1E4 * FX_RATE_CENTAVOS / 1000000) / 100
      : 0;
    const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
    const format = amount => currency.format(amount);
    set("pages-total", valid ? format(documentTotal) : "Cantidad inválida");
    set("minutes-total", valid ? format(recordingTotal) : "Cantidad inválida");
    set("summary-pages", valid ? format(documentTotal) : "—");
    set("summary-minutes", valid ? format(recordingTotal) : "—");
    set("grand-total", valid ? format(documentTotal + recordingTotal) : "Revise las cantidades");
    document.getElementById("print-quote")?.toggleAttribute("disabled", !valid);
  }
  pages?.addEventListener("input", updateQuote);
  minutes?.addEventListener("input", updateQuote);
  document.getElementById("print-quote")?.addEventListener("click", () => window.print());
  updateQuote();
  const year = document.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());
})();