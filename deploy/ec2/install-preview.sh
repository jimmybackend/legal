#!/usr/bin/env bash
# Managed-by: esforzados-legal-preview
# Instala preview aislado. No cambia vhosts de chat, drive, esforzados o mailit.
set -Eeuo pipefail
umask 022
ROOT="/var/www/legal"
STATE="/var/lib/legal"
MARKER="$ROOT/.legal-managed"
CONF="/etc/nginx/conf.d/legal-preview.conf"
UPDATER="/usr/local/libexec/legal-update"
UNIT="/etc/systemd/system/legal-update.service"
TIMER="/etc/systemd/system/legal-update.timer"
BASE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

die(){ echo "[legal-install] ERROR: $*" >&2; exit 1; }
info(){ echo "[legal-install] $*"; }
[[ "$EUID" -eq 0 ]] || die "Ejecuta como root, usando sudo."
for cmd in git nginx systemctl install useradd getent; do
  command -v "$cmd" >/dev/null 2>&1 || die "Falta dependencia: $cmd"
done
for f in legal-update.sh legal-update.service legal-update.timer nginx-legal-preview.conf; do
  [[ -f "$BASE/$f" ]] || die "Archivo requerido ausente: $f"
done

# No invadir instalaciones ajenas.
[[ ! -L "$ROOT" ]] || die "$ROOT no debe ser symlink."
if [[ -e "$ROOT" && ! -f "$MARKER" ]]; then
  [[ -d "$ROOT" && -z "$(ls -A "$ROOT")" ]] ||
    die "$ROOT ya existe y no está vacío ni pertenece a este instalador."
fi
for existing in "$CONF" "$UPDATER" "$UNIT" "$TIMER"; do
  if [[ -e "$existing" ]] && ! grep -q '^# Managed-by: esforzados-legal-preview$' "$existing"; then
    die "Archivo ajeno existente: $existing. Revisa manualmente."
  fi
done
nginx -t || die "Nginx ya tiene errores. No se instalará."
if ! getent passwd legal-deploy >/dev/null; then
  useradd --system --home-dir "$STATE" --create-home --shell /sbin/nologin legal-deploy
fi
install -d -o legal-deploy -g legal-deploy -m 0755 "$STATE" "$ROOT" "$ROOT/releases"
if [[ ! -f "$MARKER" ]]; then
  printf 'Esforzados Legal preview\n' > "$MARKER"
  chown legal-deploy:legal-deploy "$MARKER"
  chmod 0644 "$MARKER"
fi
install -d -o root -g root -m 0755 /usr/local/libexec
install -m 0755 -o root -g root "$BASE/legal-update.sh" "$UPDATER"
install -m 0644 -o root -g root "$BASE/legal-update.service" "$UNIT"
install -m 0644 -o root -g root "$BASE/legal-update.timer" "$TIMER"

backup=""
if [[ -f "$CONF" ]]; then
  backup="$(mktemp /tmp/legal-preview-nginx.XXXXXXXX)"
  cp -- "$CONF" "$backup"
fi
install -m 0644 -o root -g root "$BASE/nginx-legal-preview.conf" "$CONF"
if ! nginx -t; then
  if [[ -n "$backup" ]]; then cp -- "$backup" "$CONF"; else rm -f -- "$CONF"; fi
  [[ -z "$backup" ]] || rm -f "$backup"
  die "Config Nginx inválida; se restauró el vhost anterior."
fi
[[ -z "$backup" ]] || rm -f "$backup"

systemctl daemon-reload
# Primero publicar. Si falla, no recargar Nginx para apuntar a un sitio vacío.
systemctl start legal-update.service ||
  die "Falló publicación inicial. Consulta: journalctl -u legal-update.service -n 60"
[[ -f "$ROOT/current/index.html" ]] || die "No se publicó index.html"
if systemctl is-active --quiet nginx; then
  systemctl reload nginx
else
  info "Nginx está detenido. No arrancamos automáticamente un servicio compartido."
fi
systemctl enable --now legal-update.timer

info "Sitio publicado: $ROOT/current"
info "Prueba local: curl -I -H 'Host: legal.50.17.162.57.sslip.io' http://127.0.0.1/index.html"
info "Prueba pública: http://legal.50.17.162.57.sslip.io/index.html?v=1"
info "El preview HTTP bloquea TODOS los PHP, incluido el token del cliente y tipo-cambio.php."
info "Para cotizador USD/MXN funcional y clientes reales, configurar HTTPS y PHP por separado."
