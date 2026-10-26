# Publicación de Esforzados Legal en EC2 compartida

Objetivo: **`/var/www/legal`**, con actualizaciones aisladas del repositorio público `jimmybackend/legal` (`main`). No interrumpe los vhosts de `esforzados`, `drive.esforzados.com`, `chat.esforzados.com` ni `mailit.click`.

## 1. Arquitectura

```text
GitHub: jimmybackend/legal (main)
       |
       v
/var/lib/legal/repo/                   <- checkout Git FUERA de la carpeta web
       |
       v
/var/www/legal/releases/sha-.../      <- lista blanca de archivos
       |
       v
/var/www/legal/current/               <- symlink atómico para Nginx
       |
       v
legal.50.17.162.57.sslip.io           <- PREVIEW HTTP, sólo HTML/CSS/JS
```

El usuario `legal-deploy` no tiene shell y es dueño de `/var/lib/legal` y `/var/www/legal`. `legal-update.timer` consulta `main` cada ~5 minutos. El repositorio nunca está en la raíz servida. Los scripts bajados del repositorio **no se ejecutan** como parte de cada publicación; el actualizador instalado es una copia controlada en `/usr/local/libexec/legal-update`.

## 2. Requisitos y revisión antes de instalar

Ejecutar en la EC2 que actualmente atiende la IP **si todavía es la 50.17.162.57**. La dirección IP es la proporcionada por el usuario, no ha sido comprobada desde AWS. El dominio `sslip.io` construye el registro DNS a partir de la IP incrustada; si cambia la IP hay que ajustar el `server_name` en la configuración.

```bash
sudo nginx -t
systemctl is-active nginx
git --version
ls -ld /var/www /var/www/legal 2>/dev/null || true
sudo nginx -T 2>&1 | grep -nE 'server_name .*legal|server_name .*preview' || true
```

No continuar si ya hay un vhost Legal de otro sistema; primero revisar conflictos.

## 3. Instalación inicial por terminal autorizada

```bash
cd /tmp
git clone --depth=1 --branch main https://github.com/jimmybackend/legal.git legal-install
less /tmp/legal-install/deploy/ec2/install-preview.sh
sudo bash /tmp/legal-install/deploy/ec2/install-preview.sh
```

El instalador usa únicamente los nuevos nombres:
- `/var/lib/legal` (checkout),
- `/var/www/legal` (publicación),
- `/etc/nginx/conf.d/legal-preview.conf` (vhost HTTP),
- `/usr/local/libexec/legal-update`,
- `/etc/systemd/system/legal-update.service` y `legal-update.timer`.

Se niega a sobrescribir `/var/www/legal` si tiene contenido ajeno; hace `nginx -t` y revierte el nuevo archivo Nginx en caso de error. No abre puertos ni edita ningún otro vhost.

Prueba local para revisar Nginx sin depender del DNS:

```bash
sudo nginx -t
curl -I -H 'Host: legal.50.17.162.57.sslip.io' http://127.0.0.1/index.html
curl -I -H 'Host: legal.50.17.162.57.sslip.io' http://127.0.0.1/acceso.php
```

La segunda solicitud debe devolver **404**, deliberadamente. El preview HTTP **no ejecuta PHP**. Sólo está pensado para ver diseño responsivo y paquetes documentales. El precio de audio/video en USD no podrá calcularse hasta que esté habilitado el endpoint `tipo-cambio.php` sobre HTTPS. Tampoco será accesible el dashboard de clientes. No compartir datos de expedientes en esta URL.

## 4. Actualizaciones posteriores desde GitHub

Los cambios fusionados a `main` se publican por timer sin tocar los sitios vecinos. Para publicar bajo demanda:

```bash
sudo systemctl start legal-update.service
sudo journalctl -u legal-update.service -n 40 --no-pager
readlink /var/www/legal/current
```

El script aborta si falta un archivo requerido, no ejecuta comandos arbitrarios desde el repo, conserva releases y conmuta el symlink `current` de forma atómica.

Si se modifica el código del publicador en GitHub, **no cambia automáticamente** el ejecutable root ya instalado; primero debe revisarse y actualizarse deliberadamente.

## 5. Siguiente fase: dominio oficial, HTTPS y funcionalidad PHP

Destinado a **`https://legal.esforzados.com/`**, con un vhost HTTPS independiente del preview. Requiere:
- Confirmar registro DNS A `legal.esforzados.com` -> IPv4 pública real de la EC2.
- Emitir certificado TLS válido con Certbot (tras verificar DNS y configuración actual).
- Configurar PHP-FPM (socket real existente), permitir solo `acceso.php`, `dashboard.php`, `salir.php`, `tipo-cambio.php`; negar acceso web directo a `src/` y otros PHP.
- Activar y configurar `php-curl`, `pdo_mysql` y las variables `LEGAL_DB_*` en el entorno privado de PHP-FPM.
- Revisar y aplicar manualmente `sql/001_initial.sql` en la base aprobada, con cuenta DB de mínimos privilegios. El esquema no se carga automáticamente.
- Verificar que no se registren tokens de acceso en logs, que las cookies de sesión sean Secure y que el sitio no permita clientes reales por HTTP.
- Para la consulta del dólar, permitir HTTPS saliente hacia `api.frankfurter.dev` y habilitar `tipo-cambio.php` solo por HTTPS.

La configuración del repositorio `deploy/nginx-legal.conf.example` sirve de punto de partida, pero exige adaptarla al host real, certificado y socket de PHP antes de aplicarla.

**Importante:** el puente OIDC existente en `jimmybackend/s3` solo implementa el informe de estado EC2/SSM. Aunque el rol IAM tenga privilegios amplios, el workflow actual NO implementa despliegues ni ejecución de órdenes en el servidor. Esta instalación se hace por terminal EC2 y requiere autorización operacional.

## 6. Diagnóstico

```bash
systemctl status legal-update.timer --no-pager
sudo journalctl -u legal-update.service -n 60 --no-pager
sudo nginx -t
sudo tail -n 60 /var/log/nginx/legal-preview.error.log
```

El valor de `?v=2` se puede modificar en el navegador para evitar caché visual. No selecciona una revisión Git ni realiza actualizaciones por sí mismo.
