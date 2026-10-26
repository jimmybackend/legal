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
assert all(s in html for s in ['value="ocr_json"', 'value="scan_image"', 'value="scan_ocr_json"', 'value="managed"'])
assert all(x in html for x in ["$2.50", "$3.50", "$5.00"])
assert 'id="fx-rate-date"' in html and 'id="quote-issued-at"' in html
assert 'id="download-quote"' in html and 'tipo-cambio.php' not in html
assert "$11.00" not in html and "$18.50 MXN/USD" not in html
js=(ROOT/"assets/legal.js").read_text(encoding="utf8")
assert 'fetch("tipo-cambio.php"' in js
assert 'exchange_rate_publication_date' in js and 'issued_at_mexico_city' in js
assert 'exchange_rate_source' in js
assert "JSON estructurado" in html
print("OK: estructura responsive, imagen única, costos y acceso por token")
