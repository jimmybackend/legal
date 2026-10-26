<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
legal_session();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}
$given = $_POST['csrf'] ?? '';
if (!is_string($given) || !hash_equals((string) ($_SESSION['legal_csrf'] ?? ''), $given) || $given === '') {
    http_response_code(403);
    exit('Solicitud no autorizada.');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'], 'domain' => $params['domain'],
        'secure' => $params['secure'], 'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
}
session_destroy();
legal_redirect('/#clientes');
