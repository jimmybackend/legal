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
assert re.search(r"\$2\.00", html) and re.search(r"\$11\.00", html)
assert "JSON estructurado" in html
print("OK: estructura responsive, imagen única, costos y acceso por token")
