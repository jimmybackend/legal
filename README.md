# Esforzados Legal

Portal de **Esforzados Legal**: página pública responsiva, cotizador de transcripciones y módulo inicial de seguimiento privado de expedientes para clientes. Repositorio público [jimmybackend/legal](https://github.com/jimmybackend/legal), independiente de [jimmybackend/esforzados](https://github.com/jimmybackend/esforzados).

## Alcance implementado

- **Portada:** mantiene la primera ilustración jurídica aprobada del carrusel del portal principal. Por ahora se carga desde el archivo público original `esforzados/main/assets/carousel-legal.png`, sin duplicar otros slides. La apariencia usa negro, caoba y dorado; funciona en móvil, tablet y escritorio.
- **Servicios:** estudio documental, recursos legales con supervisión, digitalización, transcripciones, hallazgos y seguimiento.
- **Clientes:** acceso por enlace/token de 256 bits, sin usuario y contraseña. Se almacena exclusivamente SHA-256 del token. Es de un solo uso y expira a los 7 días. Tras la redención se inicia una sesión con cookie Secure/HttpOnly/SameSite Strict, ID regenerado y vencimiento por inactividad de 4 horas.
- **Dashboard:** ficha legal, lista de asuntos, avance porcentual de actividades, próximo paso, documentos visibles, hallazgos e historial. Todos los registros detallados están ocultos al cliente por defecto hasta que un operador habilite `client_visible=1`.
- **Cotizador:** cuatro precios fijos en MXN por hoja (OCR+JSON 2.00; escaneo+imagen 2.50; escaneo+OCR+JSON 3.50; organización 5.00). Audio/video cuesta US$0.6593 por minuto, convertido a MXN según la última tasa USD/MXN publicada por Frankfurter. Se muestran fecha y fuente de la tasa, fecha de emisión, subtotales y total. Permite imprimir y descargar JSON de la cotización con la tasa congelada. No es pedido ni pago.
- **MySQL/MariaDB:** esquema nuevo en `sql/001_initial.sql` para clientes, asuntos, documentos, hallazgos, eventos y tokens. No contiene información personal real.
- **S3:** el dashboard muestra solo **metadatos** de archivos; no implementa todavía descarga autenticada ni expone claves de objetos.

## Implementación en servidor

**No está desplegado automáticamente.** Requiere Nginx con TLS, PHP 8.1+ con PDO MySQL y una base de datos privada. No ejecutar esta app como GitHub Pages porque los controladores PHP no funcionarían.

1. Revisar `deploy/nginx-legal.conf.example` y configurar un virtual host HTTPS para `legal.esforzados.com`. Ajustar el socket real de php-fpm, el directorio raíz y permisos. No permitir servir `src/`, `bin/`, `sql/`, `tests/` o `deploy/`. Las rutas PHP permitidas públicamente son `acceso.php`, `dashboard.php`, `salir.php` y `tipo-cambio.php`. Deshabilitar registro de la URL completa de `acceso.php` en Nginx, proxy y CDN.
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

## Tarifas comerciales para cotización

| Servicio | Regla | Entregable |
|---|---|---|
| OCR de documentos ya digitalizados | $2.00 MXN por hoja | Texto extraído + JSON estructurado |
| Escaneo físico de documentos | $2.50 MXN por hoja | Imagen digital |
| Escaneo físico + OCR | $3.50 MXN por hoja | Imagen + texto + JSON |
| Escaneo + OCR + organización documental | $5.00 MXN por hoja | Imagen + texto + JSON + organización |
| Transcripción de audio o video | **US$0.6593/minuto** × tasa USD/MXN publicada | JSON estructurado |

Los precios documentales están fijados en MXN y NO cambian al variar el dólar. La cotización de audio/video sí utiliza el tipo de cambio USD/MXN consultado a través de `tipo-cambio.php`, basado en el servicio público [Frankfurter v2](https://frankfurter.dev/) (`https://api.frankfurter.dev/v2/rate/USD/MXN`). **Frankfurter ofrece una tasa de referencia, no el FIX de Banxico ni una tasa de compraventa bancaria.** No requiere API key y puede publicar el último dato de día hábil (por ejemplo viernes en domingo). El precio final de audio/video se redondea al centavo del subtotal, no a la tarifa por minuto.

Para evitar errores, la interfaz expone por separado:
- **Fecha de publicación de la tasa** proporcionada por Frankfurter, aunque sea anterior al día actual.
- **Fecha y hora de emisión de la cotización** según el reloj del dispositivo, presentada en horario de Ciudad de México.
- **Valor exacto consultado** expresado en MXN por USD y fuente.

Al imprimir se fija esa referencia de cambio en la pantalla; al pulsar **Descargar cotización JSON**, la fecha, tasa, conceptos y totales quedan guardados en el archivo generado. No existe almacenamiento del historial de cotizaciones en servidor: para conservar una cotización es necesario guardar el JSON/PDF impreso, o implementar posteriormente almacenamiento privado. Si se pulsa **Actualizar tipo de cambio** se consulta otro valor y se recalcula el borrador; un JSON descargado anteriormente no cambia.

**Seguridad del precio:** si el servicio no responde o entrega moneda, tasa o fecha inválida, no se cotiza audio/video; se permite seguir cotizando documentos por hoja. No hay cambio fijo inventado ni reversión a 18.50. Se usa caché temporal, cuando se habilita, máximo una hora. Los precios son orientativos, sujetos a revisión del material; IVA, corrección humana, certificaciones y casos especiales se cotizan aparte. El escaneo básico no anuncia OCR ni JSON de transcripción como si estuvieran incluidos.

### Integración y operación del dólar

- Configurar el virtual host Nginx para permitir `tipo-cambio.php` junto a los tres controladores anteriores. La consulta HTTP al exterior la hace PHP de la EC2, no el navegador del cliente. Activar `php-curl`, verificar certificados TLS y autorizar tráfico saliente HTTPS a `api.frankfurter.dev`.
- Opcionalmente definir `LEGAL_FX_CACHE_DIR` apuntando a un directorio privado de escritura propiedad de php-fpm, **fuera del DocumentRoot**, por ejemplo `/var/lib/esforzados-legal/fx-cache`. No publicar ese directorio. Puede utilizarse APCu como caché alterna; de lo contrario se consulta la fuente en cada petición.
- Verificar `https://legal.esforzados.com/tipo-cambio.php`, que deberá devolver JSON con `available=true`, `base=USD`, `quote=MXN`, `rate`, `rate_date`, `retrieved_at` y `source`. En fin de semana la `rate_date` probablemente será la del último día hábil.
- La tasa es **informativa de mercado**. Si se necesita obligatoriamente el FIX oficial de Banxico para contratos, sustituir o añadir el proveedor oficial tras acordar los requisitos legales. No afirmar que se usa Banxico cuando se usa Frankfurter.

No hay registro de información personal ni pedidos en este cotizador. El archivo JSON solo contiene los importes y parámetros de la cotización.

## Próximas integraciones

Panel privado de operador para altas, asignación de permisos de visualización, sincronización segura de metadatos con ArcadeCloud, descarga autorizada por caso, auditoría de cambios y vinculación con salidas JSON de transcripción.

© Esforzados. Código sujeto a la licencia del repositorio.
