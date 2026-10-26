from pathlib import Path
from re import search
root = Path(__file__).resolve().parents[1] / "deploy" / "ec2"
preview = (root / "nginx-legal-preview.conf").read_text()
install = (root / "install-preview.sh").read_text()
update = (root / "legal-update.sh").read_text()
service = (root / "legal-update.service").read_text()
assert "server_name legal.50.17.162.57.sslip.io;" in preview
assert "root /var/www/legal/current;" in preview
assert "location ~* \\.php(?:$|/) { return 404; }" in preview
assert "location ~ /\\.(?!well-known/) { return 404; }" in preview
assert "sudo" not in update and "git clone" in update and "git -C" in update
assert 'GIT_URL="https://github.com/jimmybackend/legal.git"' in update
assert "User=legal-deploy" in service
assert 'ROOT="/var/www/legal"' in update
assert 'ROOT="/var/www/legal"' in install
assert "nginx -t" in install
assert "/etc/nginx/conf.d/legal-preview.conf" in install
assert "drive.esforzados.com" not in preview
assert "server_name preview.50.17.162.57.sslip.io;" not in preview
assert "esforzados-update" not in service + install + update
print("OK: publicador Legal aislado de los vhosts existentes")
