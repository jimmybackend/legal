<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
$clientId = legal_require_client();
try {
    $pdo = legal_db();
    $clientQuery = $pdo->prepare('SELECT id, folio, name, status, notes_public FROM legal_clients WHERE id = ? AND status = ? LIMIT 1');
    $clientQuery->execute([$clientId, 'activo']);
    $client = $clientQuery->fetch();
    if (!$client) {
        unset($_SESSION['legal_client_id'], $_SESSION['legal_last_seen']);
        legal_redirect('/#clientes');
    }
    $casesQuery = $pdo->prepare('SELECT id, case_reference, subject, court, jurisdiction, status, progress_percent, summary_public, next_step, updated_at FROM legal_cases WHERE client_id = ? AND client_visible = 1 ORDER BY updated_at DESC, id DESC');
    $casesQuery->execute([$clientId]);
    $cases = $casesQuery->fetchAll();

    $documentsQuery = $pdo->prepare('SELECT d.case_id, d.display_name, d.document_type, d.status, d.notes_public, d.created_at FROM legal_documents d INNER JOIN legal_cases c ON c.id = d.case_id WHERE c.client_id = ? AND c.client_visible = 1 AND d.client_visible = 1 ORDER BY d.created_at DESC LIMIT 150');
    $documentsQuery->execute([$clientId]);
    $documents = $documentsQuery->fetchAll();

    $findingsQuery = $pdo->prepare('SELECT f.case_id, f.heading, f.summary, f.created_at FROM legal_findings f INNER JOIN legal_cases c ON c.id = f.case_id WHERE c.client_id = ? AND c.client_visible = 1 AND f.client_visible = 1 ORDER BY f.created_at DESC LIMIT 100');
    $findingsQuery->execute([$clientId]);
    $findings = $findingsQuery->fetchAll();

    $eventsQuery = $pdo->prepare('SELECT e.case_id, e.heading, e.details, e.event_date FROM legal_case_events e INNER JOIN legal_cases c ON c.id = e.case_id WHERE c.client_id = ? AND c.client_visible = 1 AND e.client_visible = 1 ORDER BY e.event_date DESC LIMIT 100');
    $eventsQuery->execute([$clientId]);
    $events = $eventsQuery->fetchAll();
} catch (Throwable $exception) {
    error_log('Legal dashboard: ' . get_class($exception));
    http_response_code(503);
    exit('El expediente no está disponible temporalmente. Contacte a soporte.');
}
$caseNumbers = [];
foreach ($cases as $case) {
    $caseNumbers[(int) $case['id']] = (string) $case['case_reference'];
}
function legal_date(?string $raw): string
{
    if (!$raw) return 'Sin fecha';
    try { return (new DateTimeImmutable($raw, new DateTimeZone('UTC')))->format('d/m/Y'); }
    catch (Throwable $e) { return 'Sin fecha'; }
}
?><!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="#17110f">
  <title>Mi expediente | Esforzados Legal</title>
  <link rel="icon" href="/assets/brand.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/legal.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
</head>
<body class="dashboard-body">
  <a class="skip-link" href="#expediente">Saltar al expediente</a>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/" aria-label="Esforzados Legal, portada"><span class="brand-symbol" aria-hidden="true">E<span>✦</span></span><span class="brand-wordmark"><strong>esforzados <em>legal</em></strong><small>Portal privado de clientes</small></span></a>
      <form method="post" action="/salir.php"><input type="hidden" name="csrf" value="<?= legal_escape(legal_csrf_token()) ?>"><button class="button button-gold" type="submit">Cerrar sesión</button></form>
    </div>
  </header>
  <main id="expediente" class="container dashboard-main">
    <p class="eyebrow">EXPEDIENTE PRIVADO · SOLO PARA EL CLIENTE AUTORIZADO</p>
    <h1>Seguimiento de su <em>proceso legal</em></h1>
    <p class="dashboard-lead">Aquí se presenta la información autorizada para consulta del cliente. Los datos se actualizan por el equipo responsable.</p>
    <section class="dashboard-panel" aria-labelledby="profile-heading">
      <div class="panel-heading"><h2 id="profile-heading">Ficha legal del cliente</h2><span class="status-chip"><?= legal_escape((string) $client['status']) ?></span></div>
      <div class="profile-grid">
        <div><small>CLIENTE</small><strong><?= legal_escape((string) $client['name']) ?></strong></div>
        <div><small>FOLIO</small><strong><?= legal_escape((string) $client['folio']) ?></strong></div>
        <div><small>ASUNTOS REGISTRADOS</small><strong><?= count($cases) ?></strong></div>
        <div><small>DOCUMENTOS VISIBLES</small><strong><?= count($documents) ?></strong></div>
      </div>
      <?php if ($client['notes_public']): ?><p class="client-note"><?= nl2br(legal_escape((string) $client['notes_public'])) ?></p><?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="cases-heading">
      <div class="panel-heading"><h2 id="cases-heading">Mis asuntos y avance</h2><span><?= count($cases) ?> registrados</span></div>
      <?php if (!$cases): ?><p class="empty-state">Aún no hay asuntos publicados para consulta. Solicite información a su enlace de Esforzados Legal.</p><?php endif; ?>
      <div class="case-grid">
        <?php foreach ($cases as $case): ?>
        <article class="case-card">
          <div class="case-top"><span class="case-ref"><?= legal_escape((string) $case['case_reference']) ?></span><span class="status-chip"><?= legal_escape((string) $case['status']) ?></span></div>
          <h3><?= legal_escape((string) $case['subject']) ?></h3>
          <dl>
            <div><dt>Autoridad / juzgado</dt><dd><?= legal_escape($case['court'] ?: 'Pendiente') ?></dd></div>
            <div><dt>Jurisdicción</dt><dd><?= legal_escape($case['jurisdiction'] ?: 'Pendiente') ?></dd></div>
            <div><dt>Última actualización</dt><dd><?= legal_escape(legal_date($case['updated_at'])) ?></dd></div>
          </dl>
          <?php if ($case['summary_public']): ?><p><?= nl2br(legal_escape((string) $case['summary_public'])) ?></p><?php endif; ?>
          <div class="progress-title"><strong>Avance documentado</strong><span><?= (int) $case['progress_percent'] ?>%</span></div>
          <progress max="100" value="<?= (int) $case['progress_percent'] ?>"><?= (int) $case['progress_percent'] ?>%</progress>
          <p class="next-step"><strong>Próximo paso:</strong> <?= legal_escape($case['next_step'] ?: 'Pendiente de actualización') ?></p>
        </article>
        <?php endforeach; ?>
      </div>
      <p class="dashboard-disclaimer">El porcentaje indica avance de las tareas registradas por el equipo; no representa probabilidad de éxito ni pronóstico judicial.</p>
    </section>

    <div class="dashboard-columns">
      <section class="dashboard-panel" aria-labelledby="documents-heading">
        <div class="panel-heading"><h2 id="documents-heading">Mis archivos</h2><span><?= count($documents) ?> visibles</span></div>
        <?php if (!$documents): ?><p class="empty-state">Todavía no hay documentos publicados.</p><?php endif; ?>
        <?php foreach ($documents as $doc): ?>
          <article class="record"><strong><?= legal_escape((string) $doc['display_name']) ?></strong><small><?= legal_escape($caseNumbers[(int) $doc['case_id']] ?? '') ?> · <?= legal_escape((string) $doc['document_type']) ?> · <?= legal_escape((string) $doc['status']) ?></small><?php if ($doc['notes_public']): ?><p><?= legal_escape((string) $doc['notes_public']) ?></p><?php endif; ?></article>
        <?php endforeach; ?>
        <p class="dashboard-disclaimer">Esta primera versión muestra el inventario, no enlaces directos a S3. La descarga autenticada se incorporará mediante un controlador seguro.</p>
      </section>
      <section class="dashboard-panel" aria-labelledby="findings-heading">
        <div class="panel-heading"><h2 id="findings-heading">Hallazgos</h2><span><?= count($findings) ?> visibles</span></div>
        <?php if (!$findings): ?><p class="empty-state">Todavía no hay hallazgos publicados.</p><?php endif; ?>
        <?php foreach ($findings as $finding): ?>
          <article class="record"><strong><?= legal_escape((string) $finding['heading']) ?></strong><small><?= legal_escape($caseNumbers[(int) $finding['case_id']] ?? '') ?> · <?= legal_escape(legal_date($finding['created_at'])) ?></small><p><?= nl2br(legal_escape((string) $finding['summary'])) ?></p></article>
        <?php endforeach; ?>
      </section>
    </div>

    <section class="dashboard-panel" aria-labelledby="updates-heading">
      <div class="panel-heading"><h2 id="updates-heading">Historial del proceso</h2><span><?= count($events) ?> actualizaciones</span></div>
      <?php if (!$events): ?><p class="empty-state">No hay actualizaciones publicadas.</p><?php endif; ?>
      <ol class="case-timeline">
        <?php foreach ($events as $event): ?>
          <li><time><?= legal_escape(legal_date($event['event_date'])) ?></time><div><strong><?= legal_escape((string) $event['heading']) ?></strong><small><?= legal_escape($caseNumbers[(int) $event['case_id']] ?? '') ?></small><?php if ($event['details']): ?><p><?= nl2br(legal_escape((string) $event['details'])) ?></p><?php endif; ?></div></li>
        <?php endforeach; ?>
      </ol>
    </section>
  </main>
  <footer class="footer"><div class="container footer-contact"><strong>Esforzados Legal · Servidor privado</strong><p><a href="mailto:soporte@esforzados.com">soporte@esforzados.com</a> · +52 55 8645 8862</p></div><div class="container footer-bottom"><small>Acceso confidencial · Esforzados</small></div></footer>
</body>
</html>
