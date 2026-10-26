<?php
declare(strict_types=1);
require_once __DIR__ . '/src/fx_rate.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: https://legal.esforzados.com');
header('Vary: Origin');
header('Cache-Control: public, max-age=900');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['available' => false, 'error' => 'Método no permitido']);
    exit;
}
try {
    $rate = legal_fx_get();
    if ($rate === null) {
        http_response_code(503);
        header('Cache-Control: no-store');
        echo json_encode([
            'available' => false,
            'error' => 'El tipo de cambio no está disponible. No se emitirá una cotización en USD con una tasa desconocida.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['available' => true] + $rate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Legal exchange rate: ' . get_class($e));
    http_response_code(503);
    header('Cache-Control: no-store');
    echo json_encode(['available' => false, 'error' => 'No es posible consultar el tipo de cambio en este momento.'], JSON_UNESCAPED_UNICODE);
}
