# Esforzados Legal

Portal de **Esforzados Legal**: página pública responsiva, cotizador de transcripciones y módulo inicial de seguimiento privado de expedientes para clientes. Repositorio público [jimmybackend/legal](https://github.com/jimmybackend/legal), independiente de [jimmybackend/esforzados](https://github.com/jimmybackend/esforzados).

## Alcance implementado

- **Portada:** mantiene la primera ilustración jurídica aprobada del carrusel del portal principal. Por ahora se carga desde el archivo público original `esforzados/main/assets/carousel-legal.png`, sin duplicar otros slides. La apariencia usa negro, caoba y dorado; funciona en móvil, tablet y escritorio.
- **Servicios:** estudio documental, recursos legales con supervisión, digitalización, transcripciones, hallazgos y seguimiento.
- **Clientes:** acceso por enlace/token de 256 bits, sin usuario y contraseña. Se almacena exclusivamente SHA-256 del token. Es de un solo uso y expira a los 7 días. Tras la redención se inicia una sesión con cookie Secure/HttpOnly/SameSite Strict, ID regenerado y vencimiento por inactividad de 4 horas.
- **Dashboard:** ficha legal, lista de asuntos, avance porcentual de actividades, próximo paso, documentos visibles, hallazgos e historial. Todos los registros detallados están ocultos al cliente por defecto hasta que un operador habilite `client_visible=1`.
- **Cotizador:** 2 MXN por hoja de captura/transcripción; US$0.6593 por minuto de audio/video a un cambio fijo de 18.50 MXN/USD (12.19705 MXN/min; admite fracciones de minuto); suma y opción de impresión. Entrega prevista en **JSON estructurado**. Es informativo: sin captura de pago ni pedidos.
- **MySQL/MariaDB:** esquema nuevo en `sql/001_initial.sql` para clientes, asuntos, documentos, hallazgos, eventos y tokens. No contiene información personal real.
- **S3:** el dashboard muestra solo **metadatos** de archivos; no implementa todavía descarga autenticada ni expone claves de objetos.

## Implementación en servidor

**No está desplegado automáticamente.** Requiere Nginx con TLS, PHP 8.1+ con PDO MySQL y una base de datos privada. No ejecutar esta app como GitHub Pages porque los controladores PHP no funcionarían.

1. Revisar `deploy/nginx-legal.conf.example` y configurar un virtual host HTTPS para `legal.esforzados.com`. Ajustar el socket real de php-fpm, el directorio raíz y permisos. No permitir servir `src/`, `bin/`, `sql/`, `tests/` o `deploy/`. Las rutas PHP permitidas públicamente son solo `acceso.php`, `dashboard.php` y `salir.php`. Deshabilitar registro de la URL completa de `acceso.php` en Nginx, proxy y CDN.
2. Crear una base o esquema **privado** con un usuario MySQL de permisos mínimos, con acceso exclusivo a las tablas `legal_*`. Aplicar manualmente `sql/001_initial.sql` con un usuario administrador de DB. **No aplicar migraciones a tablas existentes sin revisar conflictos.**
3. Definir fuera del repositorio variables de entorno `LEGAL_DB_HOST`, `LEGAL_DB_PORT`, `LEGAL_DB_NAME`, `LEGAL_DB_USER`, `LEGAL_DB_PASSWORD`, `LEGAL_BASE_URL`. Revisar `.env.example` como lista; la aplicación **no carga .env automáticamente**: inyectarlas mediante el servicio php-fpm con controles de acceso adecuados.
4. Registrar clientes/asuntos exclusivamente por la DB o un futuro panel administrativo. No existe un formulario público de alta y **no se entregan tokens predefinidos**. El campo `client_visible` inicia en 0 para todo asunto, documento, hallazgo o evento.
5. Emitir enlace desde la consola de la EC2 con `php bin/issue-token.php ID_CLIENTE`. Se requiere cliente existente con `status='activo'`. Entregar enlace solamente al cliente autorizado por un canal privado. Tras su primera utilización el enlace no sirve otra vez; emitir otro si es necesario.
6. Publicar el contenido de `legal` y verificar la cabecera HTTPS. El formulario público POST a `acceso.php` admite tokens de 64 caracteres hexadecimales; también funcionan enlaces directos por GET `/acceso.php?token=...`. Hay que considerar el riesgo de consumo por previsualizadores automáticos de enlaces y evitar compartirlos en herramientas con escaneo previo.
7. Ejecutar `python3 tests/check_site.py`, `php -l acceso.php`, `php -l dashboard.php`, `php -l salir.php` y el resto de los archivos PHP antes del despliegue.

## Esquema lógico

| Tabla nueva | Función |
|---|---|
| `legal_clients` | Ficha básica y folio del cliente; notas autorizadas |
| `legal_cases` | Asuntos, autoridad, referencia, estado, avance y siguiente tarea |
| `legal_documents` | Inventario de documentos, tipo, estado y referencia privada opcional |
| `legal_findings` | Hallazgos escritos relacionados con un asunto |
| `legal_case_events` | Cronología de actuaciones y actualizaciones |
| `legal_access_tokens` | Tokens hasheados, expiración, consumo y revocación |

El enlace de descarga de S3 **no** se coloca en la página ni en la DB pública; cuando se implemente, un endpoint autenticado deberá verificar la pertenencia del archivo al cliente y emitir URLs firmadas de corta duración. No dar por completo el módulo de descarga ni el procesamiento RAG/JSON automático: quedan para integración posterior con ArcadeCloud/MiChat.

## Privacidad y seguridad

Aunque el **código es público**, la base de datos y los archivos no deben serlo. Nada de contraseñas, credenciales AWS, tokens emitidos, expedientes reales, datos personales o URLs firmadas debe subirse a GitHub. Las salidas del dashboard usan escape HTML y consultas parametrizadas. Debe revisarse la protección de TLS, cabeceras de seguridad, permisos de BD, backups, logs, caducidad y acceso físico antes de operar con expedientes de producción. El enlace de un solo uso es un **factor de posesión**, no verificación adicional de identidad; entregar el enlace por un medio seguro es imprescindible.

## Tarifas iniciales

| Servicio | Regla | Entrega anunciada |
|---|---|---|
| Captura/transcripción de hojas | 2 MXN × hojas | JSON estructurado |
| Transcripción de audio o video | US$0.6593 × minutos × $18.50 MXN/USD (≈ $12.20 MXN/min) | JSON estructurado |

**Precisión monetaria:** la tarifa de audio/video es US$0.6593/min; la conversión exacta con tipo de cambio de referencia de $18.50 MXN/USD es **$12.19705 MXN/min** (aprox. **$12.20 MXN/min**). El resultado publicado inicialmente como $12.1973 era aproximado y no coincide exactamente con la multiplicación. El cotizador usa el precio USD original y redondea el subtotal en MXN a centavos. Este tipo de cambio es fijo, no en tiempo real.

Precios sujetos a confirmación del servicio; escaneo, OCR especializado, certificaciones y asesoría jurídica profesional no están incluidos automáticamente. La cotización no almacena datos ni constituye contratación.

## Próximas integraciones

Panel privado de operador para altas, asignación de permisos de visualización, sincronización segura de metadatos con ArcadeCloud, descarga autorizada por caso, auditoría de cambios y vinculación con salidas JSON de transcripción.

© Esforzados. Código sujeto a la licencia del repositorio.
