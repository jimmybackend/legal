<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
legal_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST' && $method !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método no permitido.');
}
$token = $method === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
$token = is_string($token) ? strtolower(trim($token)) : '';
$validFormat = preg_match('/^[a-f0-9]{64}$/D', $token) === 1;

// Las previsualizaciones automáticas de enlaces no deben consumir un token.
// La redención sólo ocurre en un POST enviado conscientemente por el cliente.
if ($method === 'GET' || !$validFormat) {
    $confirm = $method === 'GET' && $validFormat;
    if (!$confirm) http_response_code(400);
    ?><!doctype html>
    <html lang="es-MX"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer">
      <title>Acceso privado | Esforzados Legal</title>
      <link rel="stylesheet" href="/assets/legal.css"><link rel="icon" href="/assets/brand.svg" type="image/svg+xml">
    </head><body>
      <header class="site-header"><div class="container header-inner"><a class="brand" href="/"><span class="brand-symbol">E<span>✦</span></span><span class="brand-wordmark"><strong>esforzados <em>legal</em></strong><small>Portal privado</small></span></a></div></header>
      <main class="section"><div class="container" style="max-width:620px">
        <p class="eyebrow">ACCESO CONFIDENCIAL</p>
        <h1><?= $confirm ? 'Confirmar acceso a su expediente' : 'Token de acceso inválido' ?></h1>
        <p><?= $confirm ? 'Confirme que desea abrir su expediente privado. Su código quedará inutilizado después del ingreso.' : 'El enlace no tiene el formato esperado. Regrese al portal o solicite uno nuevo a Esforzados Legal.' ?></p>
        <?php if ($confirm): ?><form action="/acceso.php" method="post">
          <input type="hidden" name="token" value="<?= legal_escape($token) ?>">
          <button class="button button-gold" type="submit">Abrir mi expediente →</button>
        </form><?php endif; ?>
        <p><a href="/#clientes">Regresar al portal</a></p>
      </div></main>
    </body></html><?php
    exit;
}
try {
    $pdo = legal_db();
    $pdo->beginTransaction();
    $query = $pdo->prepare(
        'SELECT t.id, t.client_id
           FROM legal_access_tokens t
           JOIN legal_clients c ON c.id = t.client_id
          WHERE t.token_hash = :hash AND t.used_at IS NULL
            AND t.revoked_at IS NULL AND t.expires_at > UTC_TIMESTAMP()
            AND c.status = :status
          LIMIT 1 FOR UPDATE'
    );
    $query->execute(['hash' => hash('sha256', $token), 'status' => 'activo']);
    $record = $query->fetch();
    if (!$record) {
        $pdo->rollBack();
        usleep(200000);
        http_response_code(403);
        exit('El enlace es incorrecto, expiró o ya fue utilizado. Solicite un token nuevo.');
    }
    $update = $pdo->prepare('UPDATE legal_access_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id AND used_at IS NULL');
    $update->execute(['id' => (int) $record['id']]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(403);
        exit('El token ya fue utilizado.');
    }
    $pdo->commit();
    session_regenerate_id(true);
    $_SESSION['legal_client_id'] = (int) $record['client_id'];
    $_SESSION['legal_last_seen'] = time();
    $_SESSION['legal_csrf'] = bin2hex(random_bytes(32));
    legal_redirect('/dashboard.php');
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Legal token: ' . get_class($exception));
    http_response_code(503);
    exit('El acceso no está disponible temporalmente. Contacte a soporte.');
}
