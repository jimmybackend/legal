<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/fx_rate.php';

$now = new DateTimeImmutable('2026-10-25 20:00:00', new DateTimeZone('UTC'));
$valid = legal_fx_validate(['date'=>'2026-10-23','base'=>'USD','quote'=>'MXN','rate'=>18.5],$now);
assert($valid !== null && $valid['rate_date']==='2026-10-23');
if ($valid === null || $valid['rate'] !== 18.5) exit(1);
foreach ([
    ['date'=>'2026-10-23','base'=>'MXN','quote'=>'USD','rate'=>18.5],
    ['date'=>'2026-10-23','base'=>'USD','quote'=>'MXN','rate'=>-2],
    ['date'=>'2026-10-23','base'=>'USD','quote'=>'MXN','rate'=>1000],
    ['date'=>'2026-12-01','base'=>'USD','quote'=>'MXN','rate'=>18.5],
    ['date'=>'2026-09-01','base'=>'USD','quote'=>'MXN','rate'=>18.5],
] as $invalid) {
    if (legal_fx_validate($invalid,$now) !== null) exit(1);
}
echo "OK: validación de par, tasa y fecha USD/MXN\n";
