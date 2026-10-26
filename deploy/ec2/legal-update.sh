#!/usr/bin/env bash
# Managed-by: esforzados-legal-preview
# Publica únicamente archivos aprobados; no ejecuta código descargado de GitHub.
set -Eeuo pipefail
umask 022

readonly GIT_URL="https://github.com/jimmybackend/legal.git"
readonly REPO_DIR="/var/lib/legal/repo"
readonly ROOT="/var/www/legal"
readonly RELEASES="$ROOT/releases"
readonly MARKER="$ROOT/.legal-managed"

fail(){ echo "[legal-update] ERROR: $*" >&2; exit 1; }
info(){ echo "[legal-update] $*"; }

[[ -f "$MARKER" && -d "$RELEASES" ]] || fail "No está inicializado el destino. Usa install-preview.sh."
[[ ! -L "$REPO_DIR" ]] || fail "El checkout no puede ser un enlace simbólico."

if [[ ! -d "$REPO_DIR/.git" ]]; then
  [[ ! -e "$REPO_DIR" ]] || fail "Hay un directorio de código que no es Git: $REPO_DIR"
  git clone --quiet --depth=1 --single-branch --no-tags --branch main "$GIT_URL" "$REPO_DIR"
else
  [[ "$(git -C "$REPO_DIR" remote get-url origin)" == "$GIT_URL" ]] ||
    fail "Remote Git inesperado: no se actualizará."
  git -C "$REPO_DIR" fetch --quiet --depth=1 --no-tags origin refs/heads/main
  git -C "$REPO_DIR" checkout --quiet --force -B main FETCH_HEAD
fi

sha="$(git -C "$REPO_DIR" rev-parse HEAD)"
[[ "$sha" =~ ^[0-9a-f]{40}$ ]] || fail "Commit SHA inválido."
release="$RELEASES/sha-$sha"
target="releases/sha-$sha"

# Lista blanca: código PHP sólo se ejecutará cuando exista vhost HTTPS aprobado.
# El preview HTTP bloquea completamente PHP; no se sirve .git ni SQL.
files=(
  "index.html"
  "assets/brand.svg"
  "assets/legal.css"
  "assets/legal.js"
  "assets/pricing.mjs"
  "assets/dashboard.css"
  "dashboard.php"
  "acceso.php"
  "salir.php"
  "tipo-cambio.php"
  "src/bootstrap.php"
  "src/auth.php"
  "src/fx_rate.php"
)
for file in "${files[@]}"; do
  [[ -f "$REPO_DIR/$file" && ! -L "$REPO_DIR/$file" ]] ||
    fail "Archivo requerido ausente o enlace simbólico: $file"
done
[[ ! -L "$release" ]] || fail "El release no puede ser un enlace simbólico."

if [[ ! -d "$release" ]]; then
  stage="$(mktemp -d "$RELEASES/.stage.XXXXXXXX")"
  trap 'rm -rf -- "${stage:-}"' EXIT
  install -d -m 0755 "$stage/assets" "$stage/src"
  for file in "${files[@]}"; do
    install -m 0644 "$REPO_DIR/$file" "$stage/$file"
  done
  chmod 0755 "$stage" "$stage/assets" "$stage/src"
  mv -- "$stage" "$release"
  trap - EXIT
fi
[[ -d "$release" && -f "$release/index.html" && -f "$release/assets/pricing.mjs" ]] ||
  fail "Release incompleto."

if [[ -L "$ROOT/current" ]] && [[ "$(readlink -- "$ROOT/current")" == "$target" ]]; then
  info "Sin cambios: revisión $sha publicada."
  exit 0
fi
[[ ! -e "$ROOT/current" || -L "$ROOT/current" ]] ||
  fail "Existe $ROOT/current sin ser enlace; no se sobrescribirá."
next="$ROOT/.current.next.$$"
trap 'rm -f -- "$next"' EXIT
ln -s -- "$target" "$next"
mv -Tf -- "$next" "$ROOT/current"
trap - EXIT
info "Publicado $sha en $ROOT/current"
