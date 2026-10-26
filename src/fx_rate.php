<?php
declare(strict_types=1);

/**
 * Frankfurter v2 USD/MXN: tasa de referencia, NO FIX de Banxico.
 * Se valida la respuesta del proveedor para evitar tipos de cambio falsos.
 */
function legal_fx_validate(array $value, ?DateTimeImmutable $now = null): ?array
{
    if (strtoupper((string) ($value['base'] ?? '')) !== 'USD'
        || strtoupper((string) ($value['quote'] ?? '')) !== 'MXN') {
        return null;
    }
    $rate = $value['rate'] ?? null;
    if (!is_int($rate) && !is_float($rate)) {
        return null;
    }
    $rate = (float) $rate;
    if (!is_finite($rate) || $rate <= 0 || $rate >= 200) {
        return null;
    }
    $rateDate = $value['date'] ?? null;
    if (!is_string($rateDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $rateDate)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rateDate, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $rateDate) {
        return null;
    }
    $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $today = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0, 0);
    // Fin de semana y festivos: último dato publicado; máximo 10 días.
    if ($date > $today || $date < $today->modify('-10 days')) {
        return null;
    }
    return [
        'base' => 'USD',
        'quote' => 'MXN',
        'rate' => round($rate, 6),
        'rate_date' => $rateDate,
        'source' => 'Frankfurter v2 (referencia de mercado; no FIX Banxico)',
        'source_url' => 'https://frankfurter.dev/',
        'retrieved_at' => $now->format(DateTimeInterface::ATOM),
    ];
}

function legal_fx_download(): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $url = 'https://api.frankfurter.dev/v2/rate/USD/MXN';
    $curl = curl_init($url);
    if ($curl === false) {
        return null;
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Esforzados-Legal/1.0 (+https://legal.esforzados.com/)',
    ]);
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($status !== 200 || !is_string($raw) || strlen($raw) > 8192) {
        return null;
    }
    try {
        $body = json_decode($raw, true, 12, JSON_THROW_ON_ERROR);
        return is_array($body) ? legal_fx_validate($body) : null;
    } catch (JsonException $e) {
        return null;
    }
}

function legal_fx_get(): ?array
{
    // Con APCu, muchas peticiones no alcanzan al servidor externo.
    $cacheKey = 'esforzados_legal_fx_usd_mxn_v1';
    if (function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
        $cached = apcu_fetch($cacheKey, $ok);
        if ($ok && is_array($cached) && !empty($cached['rate_date'])) {
            return $cached;
        }
    }

    // Caché opcional, configurada FUERA de la raíz web. Nunca escribir bajo /assets.
    $dir = getenv('LEGAL_FX_CACHE_DIR') ?: '';
    $path = '';
    if ($dir !== '' && is_dir($dir) && is_writable($dir)) {
        $real = realpath($dir);
        if ($real !== false) {
            $path = $real . DIRECTORY_SEPARATOR . 'usd-mxn-v1.json';
            if (is_file($path) && !is_link($path) && (time() - filemtime($path) < 3600)) {
                $cached = json_decode((string) file_get_contents($path), true);
                if (is_array($cached)
                    && isset($cached['expires_at'], $cached['quote'])
                    && is_int($cached['expires_at']) && $cached['expires_at'] > time()
                    && is_array($cached['quote'])) {
                    $quote = $cached['quote'];
                    // No devolver una tasa cuya fecha ya no cumple los límites.
                    $again = legal_fx_validate([
                        'date' => $quote['rate_date'] ?? null,
                        'base' => 'USD', 'quote' => 'MXN', 'rate' => $quote['rate'] ?? null,
                    ]);
                    if ($again) return $quote;
                }
            }
        }
    }
    $quote = legal_fx_download();
    if ($quote === null) {
        // Sin conexión no inventamos ni utilizamos un cambio de 18.50.
        return null;
    }
    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $quote, 3600);
    }
    if ($path !== '') {
        $blob = json_encode(['expires_at' => time() + 3600, 'quote' => $quote], JSON_UNESCAPED_SLASHES);
        if ($blob !== false) {
            $tmp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($tmp, $blob, LOCK_EX) !== false) {
                chmod($tmp, 0600);
                rename($tmp, $path);
            } else {
                @unlink($tmp);
            }
        }
    }
    return $quote;
}
