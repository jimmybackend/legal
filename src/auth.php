<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function legal_client_id(): ?int
{
    legal_session();
    $id = $_SESSION['legal_client_id'] ?? null;
    $last = $_SESSION['legal_last_seen'] ?? null;
    if (!is_int($id) || $id <= 0 || !is_int($last) || time() - $last > 14400) {
        unset($_SESSION['legal_client_id'], $_SESSION['legal_last_seen'], $_SESSION['legal_csrf']);
        return null;
    }
    $_SESSION['legal_last_seen'] = time();
    return $id;
}

function legal_require_client(): int
{
    $id = legal_client_id();
    if ($id === null) {
        legal_redirect('/#clientes');
    }
    return $id;
}

function legal_csrf_token(): string
{
    if (!isset($_SESSION['legal_csrf']) || !is_string($_SESSION['legal_csrf'])) {
        $_SESSION['legal_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['legal_csrf'];
}
