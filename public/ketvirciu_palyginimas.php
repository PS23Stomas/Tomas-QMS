<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Ketvirčių palyginimas';

$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

$ketvirciu_sarasas = $pdo->query("
    SELECT DISTINCT
        EXTRACT(YEAR FROM u.sukurtas::timestamp)::int AS metai,
        EXTRACT(QUARTER FROM u.sukurtas::timestamp)::int AS ketvirtis
    FROM uzsakymai u
    JOIN gaminiai g ON g.uzsakymo_id = u.id
    JOIN mt_funkciniai_bandymai fb ON fb.gaminio_id = g.id
    WHERE u.sukurtas IS NOT NULL
    ORDER BY metai DESC, ketvirtis DESC
")->fetchAll(PDO::FETCH_ASSOC);

$q1_metai = $_GET['q1_metai'] ?? '';
$q1_ketv  = $_GET['q1_ketvirtis'] ?? '';
$q2_metai = $_GET['q2_metai'] ?? '';
$q2_ketv  = $_GET['q2_ketvirtis'] ?? '';

function gautiKetvircioStatistika($pdo, $metai, $ketvirtis, $DEFECT_COND) {
    $metai = (int)$metai;
    $ketvirtis = (int)$ketvirtis;
    $nuo_men = ($ketvirtis - 1) * 3 + 1;
    $iki_men = $ketvirtis * 3;
    $nuo = "$metai-" . str_pad($nuo_men, 2, '0', STR_PAD_LEFT) . "-01";
    $iki = "$metai-" . str_pad($iki_men, 2, '0', STR_PAD_LEFT) . "-" . cal_days_in_month(CAL_GREGORIAN, $iki_men, $metai);

    $where = "WHERE u.sukurtas::date BETWEEN '$nuo' AND '$iki'";

    $r = [];
    $r['periodas'] = "$metai Q$ketvirtis";
    $r['nuo'] = $nuo;
    $r['iki'] = $iki;

    $r['uzsakymai'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT u.id)
        FROM uzsakymai u
        JOIN gaminiai g ON g.uzsakymo_id = u.id
        JOIN mt_funkciniai_bandymai fb ON fb.gaminio_id = g.id
        $where
    ")->fetchColumn();

    $r['gaminiai'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT g.id)
        FROM gaminiai g
        JOIN uzsakymai u ON u.id = g.uzsakymo_id
        JOIN mt_funkciniai_bandymai fb ON fb.gaminio_id = g.id
        $where
    ")->fetchColumn();

    $r['bandymai'] = (int)$pdo->query("
        SELECT COUNT(*)
        FROM mt_funkciniai_bandymai fb
        JOIN gaminiai g ON g.id = fb.gaminio_id
        JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where
    ")->fetchColumn();

    $r['defektai'] = (int)$pdo->query("
        SELECT COUNT(*)
        FROM mt_funkciniai_bandymai fb
        JOIN gaminiai g ON g.id = fb.gaminio_id
        JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where
        AND $DEFECT_COND
    ")->fetchColumn();

    $r['defektu_proc'] = ($r['bandymai'] > 0) ? round($r['defektai'] / $r['bandymai'] * 100, 2) : 0;
    $r['defektu_per_gamini'] = ($r['gaminiai'] > 0) ? round($r['defektai'] / $r['gaminiai'], 2) : 0;

    $r['top_darbuotojai'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas,
            COUNT(*) AS bandymu,
            COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai
        FROM mt_funkciniai_bandymai fb
        JOIN gaminiai g ON g.id = fb.gaminio_id
        JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where
        AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko
        ORDER BY be_defektu DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $r['top_klydusieji'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai,
            COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM mt_funkciniai_bandymai fb
        JOIN gaminiai g ON g.id = fb.gaminio_id
        JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where
        AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko
        HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC, defektu_proc DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $r['problemines_operacijos'] = $pdo->query("
        SELECT fb.reikalavimas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai,
            COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM mt_funkciniai_bandymai fb
        JOIN gaminiai g ON g.id = fb.gaminio_id
        JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where
        AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
        GROUP BY fb.reikalavimas
        HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    return $r;
}

$rodyti = ($q1_metai !== '' && $q1_ketv !== '' && $q2_metai !== '' && $q2_ketv !== '');
$q1 = $q2 = null;
if ($rodyti) {
    $q1 = gautiKetvircioStatistika($pdo, $q1_metai, $q1_ketv, $DEFECT_COND);
    $q2 = gautiKetvircioStatistika($pdo, $q2_metai, $q2_ketv, $DEFECT_COND);
}

function pokytis($senas, $naujas) {
    if ($senas == 0 && $naujas == 0) return ['reiksme' => 0, 'klase' => '', 'rodykle' => ''];
    if ($senas == 0) return ['reiksme' => 100, 'klase' => 'pokytis-up', 'rodykle' => '+'];
    $p = round(($naujas - $senas) / $senas * 100, 1);
    if ($p > 0) return ['reiksme' => $p, 'klase' => 'pokytis-up', 'rodykle' => '+'];
    if ($p < 0) return ['reiksme' => abs($p), 'klase' => 'pokytis-down', 'rodykle' => '-'];
    return ['reiksme' => 0, 'klase' => '', 'rodykle' => ''];
}

function defPokytis($senas, $naujas) {
    $p = pokytis($senas, $naujas);
    if ($p['klase'] === 'pokytis-up') $p['klase'] = 'pokytis-blogiau';
    elseif ($p['klase'] === 'pokytis-down') $p['klase'] = 'pokytis-geriau';
    return $p;
}

require_once __DIR__ . '/includes/header.php';
?>

<a href="/index.php" class="back-link" data-testid="link-back-dashboard">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Gryzti i kokybes rodiklius
</a>

<div class="filter-bar" data-testid="filter-bar-quarters">
    <form method="GET" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;width:100%;">
        <div class="form-group">
            <label class="form-label">Senesnis ketvirtis</label>
            <select name="q1_metai" class="form-control" data-testid="select-q1-year" style="display:inline;width:auto;">
                <option value="">Metai</option>
                <?php foreach(array_unique(array_column($ketvirciu_sarasas, 'metai')) as $m): ?>
                <option value="<?= $m ?>" <?= ($q1_metai==$m)?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
            <select name="q1_ketvirtis" class="form-control" data-testid="select-q1-quarter" style="display:inline;width:auto;">
                <option value="">Q</option>
                <?php for($i=1;$i<=4;$i++): ?>
                <option value="<?= $i ?>" <?= ($q1_ketv==$i)?'selected':'' ?>>Q<?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="display:flex;align-items:center;">
            <span style="font-size:20px;color:var(--text-secondary);margin:0 4px;">vs</span>
        </div>
        <div class="form-group">
            <label class="form-label">Naujesnis ketvirtis</label>
            <select name="q2_metai" class="form-control" data-testid="select-q2-year" style="display:inline;width:auto;">
                <option value="">Metai</option>
                <?php foreach(array_unique(array_column($ketvirciu_sarasas, 'metai')) as $m): ?>
                <option value="<?= $m ?>" <?= ($q2_metai==$m)?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
            <select name="q2_ketvirtis" class="form-control" data-testid="select-q2-quarter" style="display:inline;width:auto;">
                <option value="">Q</option>
                <?php for($i=1;$i<=4;$i++): ?>
                <option value="<?= $i ?>" <?= ($q2_ketv==$i)?'selected':'' ?>>Q<?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary" data-testid="button-compare">Palyginti</button>
        </div>
    </form>
</div>

<?php if (!$rodyti): ?>
<div class="alert alert-info" data-testid="text-hint">
    Pasirinkite du ketvircius palyginimui.
</div>
<?php else: ?>

<?php
$p_uzs = pokytis($q1['uzsakymai'], $q2['uzsakymai']);
$p_gam = pokytis($q1['gaminiai'], $q2['gaminiai']);
$p_ban = pokytis($q1['bandymai'], $q2['bandymai']);
$p_def = defPokytis($q1['defektai'], $q2['defektai']);
$p_proc = defPokytis($q1['defektu_proc'], $q2['defektu_proc']);
?>

<div class="card" style="margin-bottom:20px;padding:20px;" data-testid="summary-card">
    <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;">Apibendrinimas</h3>
    <p style="font-size:14px;line-height:1.7;color:var(--text-primary);">
        <strong><?= h($q2['periodas']) ?></strong> palyginti su <strong><?= h($q1['periodas']) ?></strong>:
        <?php if ($q2['defektu_proc'] < $q1['defektu_proc']): ?>
            defektu procentas <span style="color:#16a34a;font-weight:600;">sumaz&#279;jo nuo <?= $q1['defektu_proc'] ?>% iki <?= $q2['defektu_proc'] ?>%</span>.
        <?php elseif ($q2['defektu_proc'] > $q1['defektu_proc']): ?>
            defektu procentas <span style="color:#dc2626;font-weight:600;">padid&#279;jo nuo <?= $q1['defektu_proc'] ?>% iki <?= $q2['defektu_proc'] ?>%</span>.
        <?php else: ?>
            defektu procentas <span style="font-weight:600;">nepasikeit&#279; (<?= $q1['defektu_proc'] ?>%)</span>.
        <?php endif; ?>
        Patikrinta gaminiu: <?= $q1['gaminiai'] ?> &rarr; <?= $q2['gaminiai'] ?>
        (<?= $p_gam['rodykle'] ?><?= $p_gam['reiksme'] ?>%).
        Rasta defektu: <?= $q1['defektai'] ?> &rarr; <?= $q2['defektai'] ?>
        (<?= $p_def['rodykle'] ?><?= $p_def['reiksme'] ?>%).
    </p>
</div>

<div class="card" style="margin-bottom:20px;" data-testid="comparison-table-card">
    <div class="card-header"><span class="card-title">Ketvirciu palyginimas</span></div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table data-testid="table-comparison">
                <thead>
                    <tr>
                        <th>Rodiklis</th>
                        <th style="text-align:center;"><?= h($q1['periodas']) ?></th>
                        <th style="text-align:center;"><?= h($q2['periodas']) ?></th>
                        <th style="text-align:center;">Pokytis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $eilutes = [
                        ['Uzsakymai', $q1['uzsakymai'], $q2['uzsakymai'], $p_uzs],
                        ['Patikrinti gaminiai', $q1['gaminiai'], $q2['gaminiai'], $p_gam],
                        ['Bandymu punktai', $q1['bandymai'], $q2['bandymai'], $p_ban],
                        ['Rasti defektai', $q1['defektai'], $q2['defektai'], $p_def],
                        ['Defektu procentas', $q1['defektu_proc'].'%', $q2['defektu_proc'].'%', $p_proc],
                        ['Defektai / gaminys', $q1['defektu_per_gamini'], $q2['defektu_per_gamini'], defPokytis($q1['defektu_per_gamini'], $q2['defektu_per_gamini'])],
                    ];
                    foreach ($eilutes as $e): ?>
                    <tr>
                        <td style="font-weight:500;"><?= $e[0] ?></td>
                        <td style="text-align:center;"><?= $e[1] ?></td>
                        <td style="text-align:center;"><?= $e[2] ?></td>
                        <td style="text-align:center;">
                            <?php if ($e[3]['klase']): ?>
                            <span class="pokytis-badge <?= $e[3]['klase'] ?>"><?= $e[3]['rodykle'] ?><?= $e[3]['reiksme'] ?>%</span>
                            <?php else: ?>
                            <span style="color:var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    <div class="card" data-testid="card-top-workers">
        <div class="card-header"><span class="card-title">TOP 5 darbuotojai (daugiausiai punktu)</span></div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
                <table data-testid="table-top-workers">
                    <thead>
                        <tr><th>#</th><th>Darbuotojas</th><th>Ketvirtis</th><th style="text-align:center;">Bandymu</th><th style="text-align:center;">Be defektu</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $combined = [];
                        foreach ($q2['top_darbuotojai'] as $d) {
                            $combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'bandymu'=>(int)$d['bandymu'], 'be_defektu'=>(int)$d['be_defektu'], 'ketvirtis'=>$q2['periodas']];
                        }
                        foreach ($q1['top_darbuotojai'] as $d) {
                            if (!isset($combined[$d['vardas']]) || (int)$d['be_defektu'] > $combined[$d['vardas']]['be_defektu']) {
                                $combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'bandymu'=>(int)$d['bandymu'], 'be_defektu'=>(int)$d['be_defektu'], 'ketvirtis'=>$q1['periodas']];
                            }
                        }
                        usort($combined, fn($a,$b) => $b['be_defektu'] <=> $a['be_defektu']);
                        $combined = array_slice($combined, 0, 5);
                        $i = 1;
                        foreach ($combined as $d): ?>
                        <tr>
                            <td style="font-weight:600;color:<?= $i<=3?'#f59e0b':'var(--text-secondary)' ?>;"><?= $i++ ?></td>
                            <td style="font-weight:500;"><?= h($d['vardas']) ?></td>
                            <td><span class="badge badge-primary" style="font-size:11px;"><?= h($d['ketvirtis']) ?></span></td>
                            <td style="text-align:center;"><?= $d['bandymu'] ?></td>
                            <td style="text-align:center;font-weight:600;color:#16a34a;"><?= $d['be_defektu'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($combined)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-secondary);padding:16px;">Duomenu nerasta</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" data-testid="card-top-errors">
        <div class="card-header" style="border-color:#fecaca;"><span class="card-title" style="color:#dc2626;">TOP 5 daugiausiai klydo</span></div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
                <table data-testid="table-top-errors">
                    <thead>
                        <tr><th>#</th><th>Darbuotojas</th><th>Ketvirtis</th><th style="text-align:center;">Defektai</th><th style="text-align:center;">Def. %</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $err_combined = [];
                        foreach ($q2['top_klydusieji'] as $d) {
                            $err_combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'defektai'=>(int)$d['defektai'], 'defektu_proc'=>$d['defektu_proc'], 'ketvirtis'=>$q2['periodas']];
                        }
                        foreach ($q1['top_klydusieji'] as $d) {
                            if (!isset($err_combined[$d['vardas']]) || (int)$d['defektai'] > $err_combined[$d['vardas']]['defektai']) {
                                $err_combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'defektai'=>(int)$d['defektai'], 'defektu_proc'=>$d['defektu_proc'], 'ketvirtis'=>$q1['periodas']];
                            }
                        }
                        usort($err_combined, fn($a,$b) => $b['defektai'] <=> $a['defektai']);
                        $err_combined = array_slice($err_combined, 0, 5);
                        $i = 1;
                        foreach ($err_combined as $d): ?>
                        <tr>
                            <td style="font-weight:600;color:#dc2626;"><?= $i++ ?></td>
                            <td style="font-weight:500;"><?= h($d['vardas']) ?></td>
                            <td><span class="badge badge-primary" style="font-size:11px;"><?= h($d['ketvirtis']) ?></span></td>
                            <td style="text-align:center;font-weight:600;color:#dc2626;"><?= $d['defektai'] ?></td>
                            <td style="text-align:center;"><?= $d['defektu_proc'] ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($err_combined)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-secondary);padding:16px;">Defektu nerasta</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
    <?php foreach ([$q1, $q2] as $q): ?>
    <div class="card" data-testid="card-operations-<?= str_replace(' ', '-', $q['periodas']) ?>">
        <div class="card-header"><span class="card-title">Problemingiausios operacijos (<?= h($q['periodas']) ?>)</span></div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>#</th><th>Operacija</th><th style="text-align:center;">Defektai</th><th style="text-align:center;">Def. %</th></tr></thead>
                    <tbody>
                        <?php $i=1; foreach ($q['problemines_operacijos'] as $op): ?>
                        <tr>
                            <td style="font-weight:600;"><?= $i++ ?></td>
                            <td style="font-size:13px;"><?= h($op['reikalavimas']) ?></td>
                            <td style="text-align:center;font-weight:600;color:#dc2626;"><?= $op['defektai'] ?></td>
                            <td style="text-align:center;"><?= $op['defektu_proc'] ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($q['problemines_operacijos'])): ?>
                        <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:16px;">Defektu nerasta</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-bottom:20px;" data-testid="card-chart">
    <div class="card-header"><span class="card-title">Defektu palyginimas</span></div>
    <div class="card-body">
        <canvas id="compChart" height="80" data-testid="chart-comparison"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var ctx = document.getElementById('compChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Uzsakymai', 'Gaminiai', 'Bandymu punktai', 'Defektai'],
        datasets: [
            {
                label: <?= json_encode($q1['periodas']) ?>,
                data: [<?= $q1['uzsakymai'] ?>, <?= $q1['gaminiai'] ?>, <?= $q1['bandymai'] ?>, <?= $q1['defektai'] ?>],
                backgroundColor: 'rgba(37, 99, 235, 0.7)',
                borderColor: 'rgba(37, 99, 235, 1)',
                borderWidth: 1
            },
            {
                label: <?= json_encode($q2['periodas']) ?>,
                data: [<?= $q2['uzsakymai'] ?>, <?= $q2['gaminiai'] ?>, <?= $q2['bandymai'] ?>, <?= $q2['defektai'] ?>],
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'vnt.' } } }
    }
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
