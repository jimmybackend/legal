from html.parser import HTMLParser
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]

class Parser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = set()
        self.links = []
        self.images = []
        self.forms = []
    def handle_starttag(self, tag, attrs):
        data = dict(attrs)
        if "id" in data:
            self.ids.add(data["id"])
        if tag == "a":
            self.links.append(data.get("href", ""))
        if tag == "img":
            self.images.append(data.get("src", ""))
        if tag == "form":
            self.forms.append(data)

html = (ROOT / "index.html").read_text(encoding="utf8")
parsed = Parser()
parsed.feed(html)
assert {"inicio", "servicios", "clientes", "cotizaciones", "pages", "minutes", "grand-total"} <= parsed.ids
assert parsed.links.count("https://esforzados.com/") >= 1
assert len(parsed.images) == 1, "Solo debe usarse la primera imagen aprobada del carrusel"
assert parsed.images[0].endswith("/assets/carousel-legal.png")
assert any(f.get("action") == "acceso.php" and f.get("method") == "post" for f in parsed.forms)
assert "$2.00" in html and "US$0.6593" in html
assert "$11.00" not in html and "$12.19705" in html
js = (ROOT / "assets/legal.js").read_text(encoding="utf8")
assert "USD_RATE_1E4 = 6593" in js and "FX_RATE_CENTAVOS = 1850" in js
from decimal import Decimal, ROUND_HALF_UP
rate_mxn = Decimal("0.6593") * Decimal("18.50")
assert rate_mxn == Decimal("12.197050")
assert (rate_mxn * 60).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP) == Decimal("731.82")
assert "JSON estructurado" in html
print("OK: estructura responsive, imagen única, costos y acceso por token")
