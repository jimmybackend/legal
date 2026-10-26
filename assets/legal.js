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
  function quantity(control, whole) {
    const number = Number(control?.value ?? 0);
    if (!Number.isFinite(number) || number < 0 || number > 1000000 || (whole && !Number.isInteger(number))) return null;
    return number;
  }
  function updateQuote() {
    if (!pages || !minutes) return;
    const p = quantity(pages, true);
    const m = quantity(minutes, false);
    const valid = p !== null && m !== null;
    const documentTotal = valid ? Math.round(p * 200) / 100 : 0;
    const recordingTotal = valid ? Math.round(m * 1100) / 100 : 0;
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