<?php
declare(strict_types=1);
// Solo CLI del servidor: nunca exponer como controlador web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/src/bootstrap.php';
$rawId = $argv[1] ?? '';
if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
    fwrite(STDERR, "Uso: php bin/issue-token.php ID_CLIENTE\n");
    exit(2);
}
try {
    $pdo = legal_db();
    $lookup = $pdo->prepare('SELECT id FROM legal_clients WHERE id = ? AND status = ?');
    $lookup->execute([(int) $rawId, 'activo']);
    if (!$lookup->fetch()) {
        fwrite(STDERR, "Cliente no encontrado o no activo.\n");
        exit(1);
    }
    $secret = bin2hex(random_bytes(32));
    $hash = hash('sha256', $secret);
    $insert = $pdo->prepare('INSERT INTO legal_access_tokens (client_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY))');
    $insert->execute([(int) $rawId, $hash]);
    $baseUrl = rtrim((string) (getenv('LEGAL_BASE_URL') ?: 'https://legal.esforzados.com'), '/');
    if (!str_starts_with($baseUrl, 'https://')) {
        fwrite(STDERR, "LEGAL_BASE_URL debe utilizar HTTPS.\n");
        exit(1);
    }
    fwrite(STDOUT, $baseUrl . '/acceso.php?token=' . $secret . "\n");
    fwrite(STDERR, "Enlace confidencial, válido 7 días y de un solo uso. Compártalo solo por canal seguro.\n");
} catch (Throwable $exception) {
    error_log('Legal issue-token: ' . get_class($exception));
    fwrite(STDERR, "No fue posible emitir el token. Revise configuración y tablas.\n");
    exit(1);
}
