<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Darbuotojų mėnesinė statistika';
$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' AND LOWER(COALESCE(fb.isvada,'')) <> 'atitinka')";

$sel_metai = $_GET['metai'] ?? date('Y');
$sel_menuo = $_GET['menuo'] ?? date('n');
$sel_metai = (int)$sel_metai;
$sel_menuo = (int)$sel_menuo;

$menesiu_pavadinimai = [
    1 => 'Sausis', 2 => 'Vasaris', 3 => 'Kovas', 4 => 'Balandis',
    5 => 'Gegužė', 6 => 'Birželis', 7 => 'Liepa', 8 => 'Rugpjūtis',
    9 => 'Rugsėjis', 10 => 'Spalis', 11 => 'Lapkritis', 12 => 'Gruodis'
];

$men_pradzia = sprintf('%04d-%02d-01', $sel_metai, $sel_menuo);
$men_pabaiga = date('Y-m-t', strtotime($men_pradzia));

$where = "WHERE gt.grupe = 'MT' AND DATE(u.sukurtas) BETWEEN '$men_pradzia' AND '$men_pabaiga'";

$bendra_stat = $pdo->query("
    SELECT
        COUNT(DISTINCT u.id) AS uzsakymai,
        COUNT(DISTINCT g.id) AS gaminiai,
        COUNT(*) AS bandymai,
        COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai
    FROM mt_funkciniai_bandymai fb
    JOIN gaminiai g ON g.id = fb.gaminio_id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    JOIN uzsakymai u ON u.id = g.uzsakymo_id
    $where
")->fetch(PDO::FETCH_ASSOC);

$defektu_proc = ($bendra_stat['bandymai'] > 0) ? round($bendra_stat['defektai'] / $bendra_stat['bandymai'] * 100, 2) : 0;

$darbuotojai = $pdo->query("
    SELECT
        fb.darba_atliko AS vardas,
        COUNT(*) AS bandymu,
        COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
        COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai,
        COUNT(DISTINCT fb.gaminio_id) AS gaminiu,
        ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
    FROM mt_funkciniai_bandymai fb
    JOIN gaminiai g ON g.id = fb.gaminio_id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    JOIN uzsakymai u ON u.id = g.uzsakymo_id
    $where
    AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
    GROUP BY fb.darba_atliko
    ORDER BY bandymu DESC
")->fetchAll(PDO::FETCH_ASSOC);

$klaidos_pagal_darbuotoja = $pdo->query("
    SELECT
        fb.darba_atliko AS vardas,
        fb.reikalavimas,
        fb.defektas,
        COUNT(*) AS kiekis
    FROM mt_funkciniai_bandymai fb
    JOIN gaminiai g ON g.id = fb.gaminio_id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    JOIN uzsakymai u ON u.id = g.uzsakymo_id
    $where
    AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
    AND $DEFECT_COND
    GROUP BY fb.darba_atliko, fb.reikalavimas, fb.defektas
    ORDER BY fb.darba_atliko, kiekis DESC
")->fetchAll(PDO::FETCH_ASSOC);

$klaidos_map = [];
foreach ($klaidos_pagal_darbuotoja as $k) {
    $klaidos_map[$k['vardas']][] = $k;
}

$turimi_metai = $pdo->query("
    SELECT DISTINCT EXTRACT(YEAR FROM u.sukurtas)::int AS metai
    FROM uzsakymai u
    JOIN gaminiai g ON g.uzsakymo_id = u.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    WHERE gt.grupe = 'MT'
    ORDER BY metai DESC
")->fetchAll(PDO::FETCH_COLUMN);

if (empty($turimi_metai)) {
    $turimi_metai = [(int)date('Y')];
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom:16px;">
    <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;" data-testid="form-month-filter">
        <label style="font-weight:500;color:var(--text-secondary);">Mėnuo:</label>
        <select name="menuo" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;background:var(--card-bg);color:var(--text-primary);font-size:14px;" data-testid="select-month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m == $sel_menuo ? 'selected' : '' ?>><?= $menesiu_pavadinimai[$m] ?></option>
            <?php endfor; ?>
        </select>
        <select name="metai" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;background:var(--card-bg);color:var(--text-primary);font-size:14px;" data-testid="select-year">
            <?php foreach ($turimi_metai as $m): ?>
            <option value="<?= $m ?>" <?= $m == $sel_metai ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" data-testid="button-filter">Rodyti</button>
    </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;margin-bottom:20px;">
    <div class="card" style="padding:16px;text-align:center;" data-testid="stat-orders">
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px;">Užsakymai</div>
        <div style="font-size:24px;font-weight:700;color:var(--text-primary);"><?= $bendra_stat['uzsakymai'] ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;" data-testid="stat-products">
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px;">Gaminiai</div>
        <div style="font-size:24px;font-weight:700;color:var(--text-primary);"><?= $bendra_stat['gaminiai'] ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;" data-testid="stat-tests">
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px;">Bandymų punktai</div>
        <div style="font-size:24px;font-weight:700;color:var(--text-primary);"><?= $bendra_stat['bandymai'] ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;" data-testid="stat-defects">
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px;">Defektai</div>
        <div style="font-size:24px;font-weight:700;color:#dc2626;"><?= $bendra_stat['defektai'] ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;" data-testid="stat-defect-pct">
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px;">Defektų %</div>
        <div style="font-size:24px;font-weight:700;color:<?= $defektu_proc > 3 ? '#dc2626' : '#16a34a' ?>;"><?= $defektu_proc ?>%</div>
    </div>
</div>

<div style="font-weight:600;font-size:16px;margin-bottom:12px;color:var(--text-primary);" data-testid="text-period-title">
    <?= $menesiu_pavadinimai[$sel_menuo] ?> <?= $sel_metai ?> — darbuotojų statistika
</div>

<?php if (empty($darbuotojai)): ?>
<div class="card" style="padding:32px;text-align:center;color:var(--text-secondary);" data-testid="text-no-data">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 8px;display:block;opacity:0.5;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    Šiam laikotarpiui darbuotojų duomenų nerasta
</div>
<?php else: ?>

<div class="card" data-testid="card-workers-table">
    <div class="card-header">
        <span class="card-title">Visi darbuotojai (<?= count($darbuotojai) ?>)</span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table data-testid="table-workers-all">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Darbuotojas</th>
                        <th style="text-align:center;">Gaminių</th>
                        <th style="text-align:center;">Bandymų</th>
                        <th style="text-align:center;">Be defektų</th>
                        <th style="text-align:center;">Defektai</th>
                        <th style="text-align:center;">Def. %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($darbuotojai as $d): ?>
                    <tr data-testid="row-worker-<?= $i ?>">
                        <td style="font-weight:600;color:<?= $i <= 3 ? '#f59e0b' : 'var(--text-secondary)' ?>;"><?= $i++ ?></td>
                        <td style="font-weight:500;"><?= h($d['vardas']) ?></td>
                        <td style="text-align:center;"><?= $d['gaminiu'] ?></td>
                        <td style="text-align:center;"><?= $d['bandymu'] ?></td>
                        <td style="text-align:center;font-weight:600;color:#16a34a;"><?= $d['be_defektu'] ?></td>
                        <td style="text-align:center;font-weight:600;color:<?= $d['defektai'] > 0 ? '#dc2626' : 'var(--text-secondary)' ?>;"><?= $d['defektai'] ?></td>
                        <td style="text-align:center;font-weight:500;color:<?= $d['defektu_proc'] > 5 ? '#dc2626' : ($d['defektu_proc'] > 0 ? '#f59e0b' : '#16a34a') ?>;"><?= $d['defektu_proc'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;background:var(--hover-bg);">
                        <td colspan="2">Viso</td>
                        <td style="text-align:center;"><?= $bendra_stat['gaminiai'] ?></td>
                        <td style="text-align:center;"><?= $bendra_stat['bandymai'] ?></td>
                        <td style="text-align:center;color:#16a34a;"><?= $bendra_stat['bandymai'] - $bendra_stat['defektai'] ?></td>
                        <td style="text-align:center;color:#dc2626;"><?= $bendra_stat['defektai'] ?></td>
                        <td style="text-align:center;"><?= $defektu_proc ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
$darbuotojai_su_klaidomis = array_filter($darbuotojai, fn($d) => (int)$d['defektai'] > 0);
if (!empty($darbuotojai_su_klaidomis)):
?>
<div style="margin-top:20px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:12px;color:#dc2626;" data-testid="text-errors-title">Klaidos pagal darbuotoją</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(380px, 1fr));gap:12px;">
        <?php foreach ($darbuotojai_su_klaidomis as $d):
            $vardas = $d['vardas'];
            $darb_klaidos = $klaidos_map[$vardas] ?? [];
        ?>
        <div class="card" data-testid="card-errors-<?= h($vardas) ?>">
            <div class="card-header" style="border-color:#fecaca;">
                <span class="card-title" style="display:flex;align-items:center;gap:8px;">
                    <span style="color:#dc2626;"><?= h($vardas) ?></span>
                    <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:11px;padding:2px 8px;border-radius:10px;"><?= $d['defektai'] ?> def.</span>
                    <span style="color:var(--text-secondary);font-size:12px;font-weight:400;">(<?= $d['defektu_proc'] ?>%)</span>
                </span>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Operacija</th>
                                <th>Defektas</th>
                                <th style="text-align:center;">Kiekis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($darb_klaidos as $kl): ?>
                            <tr>
                                <td style="font-size:13px;"><?= h($kl['reikalavimas']) ?></td>
                                <td style="font-size:13px;color:#dc2626;"><?= h($kl['defektas']) ?></td>
                                <td style="text-align:center;font-weight:600;"><?= $kl['kiekis'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
