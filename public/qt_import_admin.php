<?php
/**
 * quality_tomas → Tomo QMS importo valdymas
 *
 * Kopijuoja MT užsakymus, gaminius, bandymus, komponentus
 * ir pretenzijas iš quality_tomas DB į Tomo QMS production DB.
 *
 * Prieiga: tik administratorius
 * URL: /qt_import_admin.php
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();

if (currentUser()['role'] !== 'administratorius') {
    http_response_code(403);
    die('Prieiga uždrausta.');
}

set_time_limit(600);
ignore_user_abort(true);

$qt_url   = getenv('QUALITY_TOMAS_DATABASE_URL') ? 'nustatytas' : 'NENUSTATYTAS';
$tomo_url = getenv('TOMO_QMS_DATABASE_URL')      ? 'nustatytas' : 'NENUSTATYTAS';

$veiksmas  = $_POST['veiksmas'] ?? '';
$rezultatas = null;
$klaida     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if ($veiksmas === 'importuoti_uzsakymus') {
        $pradzia = microtime(true);
        $rez = TomoQMS::importuotiIsQualityTomas();
        $trukme = round(microtime(true) - $pradzia, 2);
        if (isset($rez['klaida'])) {
            $klaida = $rez['klaida'];
        } else {
            $rezultatas = [
                'tipas'   => 'uzsakymai',
                'trukme'  => $trukme,
                'duomenys' => $rez,
            ];
        }

    } elseif ($veiksmas === 'importuoti_pretenzijas') {
        $pradzia = microtime(true);
        $rez = TomoQMS::importuotiPretenzijasSiQualityTomas();
        $trukme = round(microtime(true) - $pradzia, 2);
        if (isset($rez['klaida'])) {
            $klaida = $rez['klaida'];
        } else {
            $rezultatas = [
                'tipas'   => 'pretenzijos',
                'trukme'  => $trukme,
                'duomenys' => $rez,
            ];
        }

    } elseif ($veiksmas === 'importuoti_viska') {
        $pradzia = microtime(true);
        $rez1 = TomoQMS::importuotiIsQualityTomas();
        $rez2 = isset($rez1['klaida']) ? [] : TomoQMS::importuotiPretenzijasSiQualityTomas();
        $trukme = round(microtime(true) - $pradzia, 2);
        if (isset($rez1['klaida'])) {
            $klaida = 'Užsakymų importas: ' . $rez1['klaida'];
        } elseif (isset($rez2['klaida'])) {
            $klaida = 'Pretenzijų importas: ' . $rez2['klaida'];
        } else {
            $rezultatas = [
                'tipas'   => 'viskas',
                'trukme'  => $trukme,
                'duomenys' => ['uzsakymai' => $rez1, 'pretenzijos' => $rez2],
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>quality_tomas → Tomo QMS importas</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body { padding: 24px; max-width: 860px; margin: 0 auto; }
        .page-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 28px; }
        .env-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .env-card { flex: 1; min-width: 200px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 18px; }
        .env-label { font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 4px; }
        .env-val { font-weight: 600; font-size: 0.9rem; }
        .env-ok   { color: #22c55e; }
        .env-err  { color: #ef4444; }
        .import-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .import-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; }
        .import-card h3 { font-size: 1rem; font-weight: 600; margin: 0 0 8px; }
        .import-card p { font-size: 0.84rem; color: var(--text-secondary); margin: 0 0 16px; line-height: 1.5; }
        .import-card.main { border-color: var(--primary); }
        .result-box { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 22px; margin-bottom: 20px; }
        .result-box h3 { margin: 0 0 16px; font-size: 1rem; font-weight: 600; }
        .stat-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .stat-pill { background: var(--bg-secondary,#f3f4f6); border-radius: 6px; padding: 8px 14px; font-size: 0.85rem; }
        .stat-pill strong { display: block; font-size: 1.3rem; font-weight: 700; }
        .err-list { margin: 12px 0 0; }
        .err-list summary { cursor: pointer; color: #ef4444; font-size: 0.85rem; font-weight: 600; }
        .err-list ul { margin: 8px 0 0; padding-left: 20px; }
        .err-list li { font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 4px; }
        .alert-danger  { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; border-radius:8px; padding:14px 18px; margin-bottom:20px; }
        .warning-box { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px 18px; margin-bottom:20px; font-size:0.88rem; color:#92400e; }
        .btn-import { width:100%; }
    </style>
</head>
<body>

<div class="page-title">quality_tomas → Tomo QMS importas</div>
<div class="page-sub">Duomenų perkėlimas iš išorinės quality_tomas DB į Replit production Tomo QMS DB</div>

<div class="env-row">
    <div class="env-card">
        <div class="env-label">QUALITY_TOMAS_DATABASE_URL (šaltinis)</div>
        <div class="env-val <?= $qt_url === 'nustatytas' ? 'env-ok' : 'env-err' ?>">
            <?= $qt_url === 'nustatytas' ? '✓ Nustatytas' : '✗ Nenustatytas' ?>
        </div>
    </div>
    <div class="env-card">
        <div class="env-label">TOMO_QMS_DATABASE_URL (tikslas)</div>
        <div class="env-val <?= $tomo_url === 'nustatytas' ? 'env-ok' : 'env-err' ?>">
            <?= $tomo_url === 'nustatytas' ? '✓ Nustatytas' : '✗ Nenustatytas' ?>
        </div>
    </div>
</div>

<?php if ($qt_url !== 'nustatytas' || $tomo_url !== 'nustatytas'): ?>
<div class="alert-danger">Trūksta aplinkos kintamųjų. Importas neveiks.</div>
<?php endif; ?>

<?php if ($klaida): ?>
<div class="alert-danger"><strong>Klaida:</strong> <?= h($klaida) ?></div>
<?php endif; ?>

<?php if ($rezultatas): ?>
<div class="result-box">
    <?php if ($rezultatas['tipas'] === 'uzsakymai' || $rezultatas['tipas'] === 'viskas'): ?>
    <?php $r = $rezultatas['tipas'] === 'viskas' ? $rezultatas['duomenys']['uzsakymai'] : $rezultatas['duomenys']; ?>
    <h3>✓ Užsakymai importuoti</h3>
    <div class="stat-row">
        <div class="stat-pill"><strong><?= $r['nauji'] ?? 0 ?></strong>Nauji užsakymai</div>
        <div class="stat-pill"><strong><?= $r['atnaujinti'] ?? 0 ?></strong>Atnaujinti</div>
        <div class="stat-pill"><strong><?= $r['gaminiai'] ?? 0 ?></strong>Gaminiai</div>
        <div class="stat-pill"><strong><?= $r['bandymai'] ?? 0 ?></strong>Bandymai</div>
        <div class="stat-pill"><strong><?= $r['komponentai'] ?? 0 ?></strong>Komponentai</div>
    </div>
    <?php if (!empty($r['klaidos'])): ?>
    <details class="err-list">
        <summary><?= count($r['klaidos']) ?> klaida(-os)</summary>
        <ul><?php foreach (array_slice($r['klaidos'], 0, 20) as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($rezultatas['tipas'] === 'pretenzijos' || $rezultatas['tipas'] === 'viskas'): ?>
    <?php $p = $rezultatas['tipas'] === 'viskas' ? $rezultatas['duomenys']['pretenzijos'] : $rezultatas['duomenys']; ?>
    <h3 style="margin-top:<?= $rezultatas['tipas'] === 'viskas' ? '20px' : '0' ?>">✓ Pretenzijos importuotos</h3>
    <div class="stat-row">
        <div class="stat-pill"><strong><?= $p['pretenzijos'] ?? 0 ?></strong>Pretenzijos</div>
        <div class="stat-pill"><strong><?= $p['nuotraukos'] ?? 0 ?></strong>Nuotraukos</div>
        <div class="stat-pill"><strong><?= $p['email'] ?? 0 ?></strong>El. pašto istorija</div>
    </div>
    <?php if (!empty($p['klaidos'])): ?>
    <details class="err-list">
        <summary><?= count($p['klaidos']) ?> klaida(-os)</summary>
        <ul><?php foreach (array_slice($p['klaidos'], 0, 20) as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:14px;font-size:0.83rem;color:var(--text-secondary);">Trukmė: <?= $rezultatas['trukme'] ?> sek.</div>
</div>
<?php endif; ?>

<div class="warning-box">
    ⚠️ <strong>Dėmesio:</strong> šis importas rašo į <strong>Tomo QMS production DB</strong>. Esami įrašai bus atnaujinti pagal užsakymo numerį. Operacija negrįžtama.
</div>

<div class="import-grid">
    <div class="import-card main">
        <h3>🔄 Viskas iš karto</h3>
        <p>Užsakymai + gaminiai + bandymai + komponentai + pretenzijos + nuotraukos + el. pašto istorija.</p>
        <form method="POST" onsubmit="return confirm('Importuoti VISKĄ iš quality_tomas į Tomo QMS production DB?')">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="veiksmas" value="importuoti_viska">
            <button type="submit" class="btn btn-primary btn-import" data-testid="button-import-all">Importuoti viską</button>
        </form>
    </div>

    <div class="import-card">
        <h3>📦 Tik užsakymai</h3>
        <p>Užsakymai, gaminiai, funkciniai bandymai, komponentai. Be pretenzijų.</p>
        <form method="POST" onsubmit="return confirm('Importuoti užsakymus ir gaminius?')">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="veiksmas" value="importuoti_uzsakymus">
            <button type="submit" class="btn btn-secondary btn-import" data-testid="button-import-orders">Importuoti užsakymus</button>
        </form>
    </div>

    <div class="import-card">
        <h3>📋 Tik pretenzijos</h3>
        <p>Pretenzijos, nuotraukos, el. pašto istorija. Užsakymai turi jau būti importuoti.</p>
        <form method="POST" onsubmit="return confirm('Importuoti pretenzijas?')">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="veiksmas" value="importuoti_pretenzijas">
            <button type="submit" class="btn btn-secondary btn-import" data-testid="button-import-claims">Importuoti pretenzijas</button>
        </form>
    </div>
</div>

<div style="margin-top:8px;">
    <a href="/index.php" class="btn btn-secondary btn-sm">← Grįžti į pradžią</a>
</div>

</body>
</html>
