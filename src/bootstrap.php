<?php
declare(strict_types=1);

/** Configuración privada por variables de entorno. Nunca guardar secretos en Git. */
function legal_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $keys = ['LEGAL_DB_HOST', 'LEGAL_DB_NAME', 'LEGAL_DB_USER', 'LEGAL_DB_PASSWORD'];
    foreach ($keys as $key) {
        if (getenv($key) === false || getenv($key) === '') {
            throw new RuntimeException('Configuración de base de datos incompleta');
        }
    }
    $host = (string) getenv('LEGAL_DB_HOST');
    $name = (string) getenv('LEGAL_DB_NAME');
    $port = (int) (getenv('LEGAL_DB_PORT') ?: 3306);
    if ($port < 1 || $port > 65535 || str_contains($host, ';') || str_contains($name, ';')) {
        throw new RuntimeException('Configuración de base de datos inválida');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $connection = new PDO($dsn, (string) getenv('LEGAL_DB_USER'), (string) getenv('LEGAL_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '+00:00'");
    return $connection;
}

function legal_escape(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function legal_security_headers(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
}

function legal_session(): void
{
    if (PHP_SAPI === 'cli') {
        throw new RuntimeException('La sesión del portal sólo funciona por HTTPS');
    }
    legal_security_headers();
    if (($_SERVER['HTTPS'] ?? '') !== 'on') {
        http_response_code(403);
        exit('Se requiere una conexión HTTPS.');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('ESFORZADOS_LEGAL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function legal_redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}
