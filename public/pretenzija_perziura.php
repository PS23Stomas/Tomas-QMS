<?php
/**
 * pretenzija_perziura.php
 * Atskiras pretenzijos peržiūros puslapis — naudojamas kaip nuoroda Excel/Word dokumentuose.
 * VIEŠAS puslapis — nereikalauja prisijungimo, nerodo sistemos navigacijos.
 * URL formatas: /pretenzija_perziura.php?id=33
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/includes/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('<p>Neteisingas pretenzijos ID.</p>');
}

$stmt = $pdo->prepare("
    SELECT p.*, u.uzsakymo_numeris
    FROM pretenzijos p
    LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die('<p>Pretenzija nerasta.</p>');
}

$nStmt = $pdo->prepare("SELECT id, pavadinimas FROM pretenzijos_nuotraukos WHERE pretenzija_id = ? ORDER BY id");
$nStmt->execute([$id]);
$nuotraukos = $nStmt->fetchAll(PDO::FETCH_ASSOC);

$hStmt = $pdo->prepare("SELECT * FROM pretenzijos_email_history WHERE pretenzija_id = ? ORDER BY sent_at DESC");
$hStmt->execute([$id]);
$history = $hStmt->fetchAll(PDO::FETCH_ASSOC);

$statusai = [
    'nauja'    => ['label' => 'Nauja',    'bg' => '#e74c3c', 'color' => '#fff'],
    'tyrimas'  => ['label' => 'Tyrimas',  'bg' => '#e67e22', 'color' => '#fff'],
    'vykdoma'  => ['label' => 'Vykdoma',  'bg' => '#f1c40f', 'color' => '#333'],
    'uzbaigta' => ['label' => 'Užbaigta', 'bg' => '#27ae60', 'color' => '#fff'],
    'atmesta'  => ['label' => 'Atmesta',  'bg' => '#95a5a6', 'color' => '#fff'],
];
$tipai = [
    'vidine'  => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjui pretenzija',
];

$imone = getImonesNustatymai();
$imone_pav = htmlspecialchars($imone['pavadinimas'] ?? 'ELGA', ENT_QUOTES, 'UTF-8');

$st    = $statusai[$p['statusas']] ?? $statusai['nauja'];
$tipas = $tipai[$p['tipas']] ?? $p['tipas'];
$esc   = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
$nl    = fn($s) => nl2br($esc($s));
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pretenzija #<?= $id ?> — <?= $imone_pav ?></title>
  <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .card-main { max-width: 860px; margin: 30px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.1); overflow: hidden; }
    .card-header-custom { background: linear-gradient(135deg, #c0392b, #e74c3c); color: #fff; padding: 1.2rem 1.5rem; }
    .card-body-inner { padding: 1.5rem; }
    .section-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; margin-bottom: 4px; }
    .section-value { background: #f8f9fa; border-radius: 6px; padding: 10px 14px; font-size: 0.95rem; }
    .badge-tipas  { border-radius: 20px; padding: 5px 14px; font-weight: 600; font-size: 0.8rem; background: #3498db; color: #fff; }
    .badge-status { border-radius: 20px; padding: 5px 14px; font-weight: 600; font-size: 0.8rem; }
    .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; }
    .photo-grid img { width: 100%; border-radius: 6px; cursor: pointer; border: 1px solid #dee2e6; }
    .email-history-item { font-size: 0.87rem; }
    @media print { body { background: #fff; } .card-main { box-shadow: none; } .no-print { display: none !important; } }
    @media (max-width: 768px) {
      .card-main { margin: 10px; width: auto; }
      .card-body-inner { padding: 1rem; }
      .card-header-custom { padding: 0.9rem 1rem; }
      .perziura-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .perziura-row-flex { flex-direction: column !important; }
      .perziura-row-flex > [class^="col"] { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; margin-bottom: 6px; }
      .row.mb-3 > .col-md-8,
      .row.mb-3 > .col-md-4 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
      .mb-3.d-flex.flex-wrap { flex-wrap: wrap; }
    }
  </style>
</head>
<body>
<div class="card-main">
  <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div style="font-size:1.1rem;font-weight:700;letter-spacing:0.03em;">Tomo-QMS</div>
      <div style="font-size:0.78rem;opacity:.8;margin-top:2px;"><?= $imone_pav ?></div>
    </div>
    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Pretenzija #<?= $id ?></h5>
  </div>

  <div class="card-body-inner" data-testid="pretenzija-perziura-container">
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
      <span class="badge-tipas"><?= $esc($tipas) ?></span>
      <span class="badge-status" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;"><?= $st['label'] ?></span>
      <span class="text-muted ms-auto" style="font-size:0.83rem;"><i class="bi bi-clock me-1"></i><?= $esc(substr($p['sukurta'] ?? '', 0, 16)) ?></span>
    </div>

    <div class="perziura-table-wrap">
      <table class="table table-bordered mb-3">
        <thead class="table-light">
          <tr>
            <th>Problemos pastebėjimo vieta</th>
            <th>Gaminys</th>
            <th>Užsakymo Nr.</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= $esc($p['aptikimo_vieta'] ?? '') ?: '-' ?></td>
            <td><?= $esc($p['gaminys_info'] ?? '') ?: '-' ?></td>
            <td><?= $esc($p['uzsakymo_numeris'] ?? $p['uzsakymo_numeris_ranka'] ?? '') ?: '-' ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mb-3">
      <div class="section-label">Problemos aprašymas</div>
      <div class="section-value"><?= $nl($p['aprasymas'] ?? '') ?: '-' ?></div>
    </div>

    <div class="mb-3">
      <div class="section-label">Padalinys atsakingas už sprendimą</div>
      <div class="section-value"><?= $esc($p['atsakingas_padalinys'] ?? '') ?: '-' ?></div>
    </div>

    <div class="row mb-3">
      <div class="col-md-8">
        <div class="section-label">Siūlomas sprendimo būdas</div>
        <div class="section-value"><?= $nl($p['siulomas_sprendimas'] ?? '') ?: '-' ?></div>
      </div>
      <div class="col-md-4">
        <div class="section-label">Terminas</div>
        <div class="section-value"><?= $esc($p['terminas'] ?? '') ?: '-' ?></div>
      </div>
    </div>

    <div class="p-3 rounded mb-3" style="background:#f8f9fa;border:1px solid #dee2e6;">
      <div class="section-label">Problemą užfiksavo</div>
      <div class="row perziura-row-flex text-muted mt-1" style="font-size:0.9rem;">
        <div class="col-md-4"><strong>Padalinys:</strong> <?= $esc($p['uzfiksavo_padalinys'] ?? '') ?: '-' ?></div>
        <div class="col-md-4"><strong>Asmuo:</strong> <?= $esc($p['uzfiksavo_asmuo'] ?? '') ?: '-' ?></div>
        <div class="col-md-4"><strong>Data:</strong> <?= $esc($p['gavimo_data'] ?? '') ?: '-' ?></div>
      </div>
    </div>

    <?php if (!empty($p['priezastis'])): ?>
    <div class="mb-3">
      <div class="section-label">Nustatyta priežastis</div>
      <div class="section-value"><?= $nl($p['priezastis']) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($p['veiksmai'])): ?>
    <div class="mb-3">
      <div class="section-label">Korekciniai veiksmai</div>
      <div class="section-value"><?= $nl($p['veiksmai']) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($nuotraukos)): ?>
    <div class="mb-3">
      <div class="section-label"><i class="bi bi-images me-1"></i>Nuotraukos (<?= count($nuotraukos) ?>)</div>
      <div class="photo-grid mt-2">
        <?php foreach ($nuotraukos as $n): ?>
          <img src="pretenzijos_nuotrauka.php?id=<?= (int)$n['id'] ?>" alt="<?= $esc($n['pavadinimas'] ?? '') ?>"
               onclick="window.open(this.src,'_blank')" title="Padidinti"
               data-testid="img-nuotrauka-<?= (int)$n['id'] ?>">
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($p['defekto_pdf_pavadinimas'])): ?>
    <div class="mb-3" style="background:#fdedec;padding:0.75rem;border-radius:6px;border:1px solid #f5c6cb;">
      <div class="section-label"><i class="bi bi-file-earmark-pdf me-1"></i>Defekto PDF</div>
      <div style="margin-top:0.3rem;">
        <a href="pretenzija_defekto_pdf.php?id=<?= (int)$p['id'] ?>" target="_blank" style="color:#c0392b;font-weight:500;text-decoration:none;" data-testid="link-perziura-defekto-pdf">
          <i class="bi bi-download me-1"></i><?= $esc($p['defekto_pdf_pavadinimas']) ?>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($history)): ?>
    <hr>
    <div class="section-label mb-2"><i class="bi bi-envelope-check me-1"></i>Siuntimo istorija</div>
    <?php foreach ($history as $h): ?>
      <?php $answered = !empty($h['feedback_text']); ?>
      <div class="border rounded p-2 mb-2 email-history-item <?= $answered ? 'border-success' : '' ?>"
           style="background:<?= $answered ? '#f0fff4' : '#fff' ?>;">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
          <div>
            <strong>Kam:</strong> <?= $esc($h['email_delegated_to'] ?? '') ?>
            <?php if (!empty($h['email_cc'])): ?>
              &nbsp;<span class="text-muted">CC: <?= $esc($h['email_cc']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($answered): ?>
            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Atsakyta</span>
          <?php else: ?>
            <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i>Laukiama</span>
          <?php endif; ?>
        </div>
        <div class="text-muted mt-1">Išsiųsta: <?= $esc(substr($h['sent_at'] ?? '', 0, 16)) ?> &mdash; <?= $esc($h['sent_by'] ?? '') ?></div>
        <?php if ($answered): ?>
          <div class="mt-2 p-2 rounded" style="background:#e8f5e9;border-left:3px solid #27ae60;">
            <div class="fw-bold text-success mb-1" style="font-size:0.78rem;">ATSAKYMAS</div>
            <div><?= $nl($h['feedback_text']) ?></div>
            <div class="text-muted mt-1" style="font-size:0.78rem;"><?= $esc($h['feedback_by'] ?? '') ?> &mdash; <?= $esc(substr($h['feedback_at'] ?? '', 0, 16)) ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center mt-4 mb-2 no-print">
      <a href="pretenzijos_pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-danger btn-lg fw-semibold" data-testid="button-pdf-perziura">
        <i class="bi bi-file-earmark-pdf me-2"></i>Atsisiųsti PDF
      </a>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
