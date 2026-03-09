<?php
/**
 * MT statistikos puslapis su filtrais - defektų analizė pagal užsakymą, laikotarpį, mėnesį
 *
 * Šis failas atvaizduoja MT statistikos puslapį su filtrais pagal užsakymo numerį,
 * laikotarpį (1 mėn./6 mėn./1 metai/pasirinktinis) ir mėnesį. Rodomi defektų duomenys
 * su diagramomis (Chart.js), TOP 5 dažniausi defektų punktai ir aktyvūs nepataisyti defektai.
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Pastebėtų gedimų statistika (MT)';

/* --- Filtrų parametrų nuskaitymas iš GET užklausos --- */
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$periodas         = $_GET['periodas'] ?? 'visi';
$menuo            = $_GET['menuo'] ?? '';
$nuo              = $_GET['nuo'] ?? '';
$iki              = $_GET['iki'] ?? '';

/* Visų MT užsakymų numerių sąrašo gavimas filtrų išskleidžiamajam meniu */
$uzsakymai = $pdo->query("
    SELECT DISTINCT u.uzsakymo_numeris
    FROM uzsakymai u
    JOIN gaminiai g       ON g.uzsakymo_id = u.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    WHERE gt.grupe = 'MT'
    ORDER BY u.uzsakymo_numeris DESC
")->fetchAll(PDO::FETCH_COLUMN);

/* Pradinės statistikos kintamųjų reikšmės */
$patikrinti = 0;
$klaidos = 0;
$top_defektai = [];
$defektu_gaminiai = [];
$aktyvus_defektai = [];

/* --- WHERE sąlygos sudarymas pagal pasirinktus filtrus --- */
$where_uzsakymas = '';
$where_laikotarpis = '';
$params = [];

/* Užsakymo numerio filtras */
if ($uzsakymo_numeris !== '') {
    $where_uzsakymas = "u.uzsakymo_numeris = ?";
    $params[] = $uzsakymo_numeris;
} else {
    $where_uzsakymas = "1=1";
}

/* Laikotarpio filtras: pagal mėnesį, datų intervalą arba iš anksto nustatytą periodą */
if ($menuo !== '') {
    $where_laikotarpis = " AND TO_CHAR(u.sukurtas::timestamp, 'YYYY-MM') = ?";
    $params[] = $menuo;
} elseif ($nuo !== '' && $iki !== '') {
    $where_laikotarpis = " AND DATE(u.sukurtas) BETWEEN ? AND ?";
    $params[] = $nuo;
    $params[] = $iki;
} elseif ($periodas === '1m') {
    $where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 month'";
} elseif ($periodas === '6m') {
    $where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '6 month'";
} elseif ($periodas === '1y') {
    $where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 year'";
}

$rodyti_duomenis = !(
    $uzsakymo_numeris === '' &&
    $periodas === 'visi' &&
    $menuo === '' &&
    ($nuo === '' || $iki === '')
);

$where_sql = "WHERE $where_uzsakymas $where_laikotarpis";

if ($rodyti_duomenis) {

    /* --- Defektų agregavimo užklausos --- */

    /* Patikrintų gaminių skaičiaus gavimas (unikalūs gaminio ID su funkciniais bandymais) */
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT fb.gaminio_id)
        FROM funkciniai_bandymai fb
        JOIN gaminiai g       ON fb.gaminio_id = g.id
        JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
        JOIN uzsakymai u      ON g.uzsakymo_id = u.id
        $where_sql
          AND gt.grupe = 'MT'
    ");
    $stmt->execute($params);
    $patikrinti = (int)$stmt->fetchColumn();

    /* Užsakymų su defektais ir be defektų sąrašo gavimas (UNION ALL) */
    $stmt = $pdo->prepare("
        SELECT u.uzsakymo_numeris, fb.reikalavimas, fb.defektas, fb.isvada
        FROM funkciniai_bandymai fb
        JOIN gaminiai g       ON fb.gaminio_id = g.id
        JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
        JOIN uzsakymai u      ON g.uzsakymo_id = u.id
        $where_sql
          AND gt.grupe = 'MT'
          AND fb.defektas IS NOT NULL
          AND TRIM(fb.defektas) <> ''

        UNION ALL

        SELECT u.uzsakymo_numeris,
               NULL AS reikalavimas,
               NULL AS defektas,
               'atitinka' AS isvada
        FROM uzsakymai u
        JOIN gaminiai g       ON g.uzsakymo_id = u.id
        JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
        LEFT JOIN funkciniai_bandymai fb ON fb.gaminio_id = g.id
        $where_sql
          AND gt.grupe = 'MT'
        GROUP BY u.uzsakymo_numeris
        HAVING SUM(CASE WHEN fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' THEN 1 ELSE 0 END) = 0

        ORDER BY uzsakymo_numeris
    ");
    $stmt->execute(array_merge($params, $params));
    $defektu_gaminiai = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($defektu_gaminiai as $r) {
        if (!empty(trim((string)$r['defektas']))) {
            $klaidos++;
        }
    }

    /* TOP 5 dažniausių defektų punktų gavimas pagal pasikartojimų skaičių */
    $stmt = $pdo->prepare("
        SELECT 
            MIN(fb.eil_nr) as eil_nr,
            fb.reikalavimas, 
            COUNT(*) AS kiekis
        FROM funkciniai_bandymai fb
        JOIN gaminiai g       ON fb.gaminio_id = g.id
        JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
        JOIN uzsakymai u      ON g.uzsakymo_id = u.id
        $where_sql
          AND gt.grupe = 'MT'
          AND fb.defektas IS NOT NULL
          AND TRIM(fb.defektas) <> ''
          AND fb.reikalavimas IS NOT NULL
          AND TRIM(fb.reikalavimas) <> ''
        GROUP BY fb.reikalavimas
        ORDER BY kiekis DESC, eil_nr ASC
        LIMIT 5
    ");
    $stmt->execute($params);
    $top_defektai = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Aktyvių nepataisytų defektų sąrašo gavimas (isvada = neatitinka/nepadaryta) */
    $stmt = $pdo->prepare("
        SELECT u.uzsakymo_numeris, f.reikalavimas, f.defektas
        FROM funkciniai_bandymai f
        JOIN gaminiai g       ON f.gaminio_id = g.id
        JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
        JOIN uzsakymai u      ON g.uzsakymo_id = u.id
        $where_sql
          AND gt.grupe = 'MT'
          AND LOWER(f.isvada) IN ('neatitinka','nepadaryta')
          AND f.defektas IS NOT NULL
          AND TRIM(f.defektas) <> ''
        ORDER BY u.uzsakymo_numeris
    ");
    $stmt->execute($params);
    $aktyvus_defektai = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$export_qs = http_build_query([
    'uzsakymo_numeris' => $uzsakymo_numeris,
    'periodas'         => $periodas,
    'menuo'            => $menuo,
    'nuo'              => $nuo,
    'iki'              => $iki,
    'tik_mt'           => 1,
]);

require_once __DIR__ . '/includes/header.php';
?>

<a href="/index.php" class="back-link" data-testid="link-back-dashboard">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Grįžti į kokybės rodiklius
</a>

<div class="filter-bar" data-testid="filter-bar">
    <form method="GET" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%;">
        <div class="form-group">
            <label class="form-label">Pasirinkti užsakymą</label>
            <select name="uzsakymo_numeris" class="form-control" data-testid="select-order">
                <option value="">-- Pasirinkti --</option>
                <?php foreach ($uzsakymai as $nr): ?>
                <option value="<?= h($nr) ?>" <?= ($uzsakymo_numeris == $nr) ? 'selected' : '' ?>><?= h($nr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Laikotarpis</label>
            <select name="periodas" class="form-control" data-testid="select-period">
                <option value="visi" <?= ($periodas=='visi')?'selected':'' ?>>Visi</option>
                <option value="1m"  <?= ($periodas=='1m') ?'selected':'' ?>>Paskutinis mėnuo</option>
                <option value="6m"  <?= ($periodas=='6m') ?'selected':'' ?>>Paskutiniai 6 mėnesiai</option>
                <option value="1y"  <?= ($periodas=='1y') ?'selected':'' ?>>Paskutiniai 12 mėnesių</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Mėnuo</label>
            <input type="month" name="menuo" value="<?= h($menuo) ?>" class="form-control" data-testid="input-month">
        </div>

        <div class="form-group">
            <label class="form-label">Nuo</label>
            <input type="date" name="nuo" class="form-control" value="<?= h($nuo) ?>" data-testid="input-date-from">
        </div>

        <div class="form-group">
            <label class="form-label">Iki</label>
            <input type="date" name="iki" class="form-control" value="<?= h($iki) ?>" data-testid="input-date-to">
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary" data-testid="button-filter">Rodyti</button>
        </div>

        <?php if ($uzsakymo_numeris !== '' || $periodas !== 'visi' || $menuo !== '' || $nuo !== '' || $iki !== ''): ?>
        <div class="form-group">
            <a href="/mt_statistika.php" class="btn btn-secondary" data-testid="button-clear-filter">Valyti filtrus</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if (!$rodyti_duomenis): ?>
<div class="alert alert-info" data-testid="text-filter-hint">
    Pasirinkite bent vieną filtrą (užsakymą ar laikotarpį), kad būtų rodomi duomenys.
</div>
<?php else: ?>

<div class="stats-summary">
    <div class="stat-card" data-testid="stat-patikrinti">
        <div class="stat-header">
            <span class="stat-label">Patikrinti užsakymai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int)$patikrinti ?></div>
        <div class="stat-change">Gaminių su funkciniais bandymais</div>
    </div>
    <div class="stat-card" data-testid="stat-klaidos">
        <div class="stat-header">
            <span class="stat-label">Klaidų skaičius</span>
            <div class="stat-icon red">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int)$klaidos ?></div>
        <div class="stat-change">Rasti defektai</div>
    </div>
    <div class="stat-card stat-card-wide" data-testid="stat-top-defektai">
        <div class="stat-header">
            <span class="stat-label">5 dažniausiai klaidos punktai</span>
            <div class="stat-icon orange">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
        </div>
        <?php if (empty($top_defektai)): ?>
        <div class="stat-change" style="margin-top: 8px;">Defektų nerasta</div>
        <?php else: ?>
        <ol class="top-defektai-list">
            <?php foreach ($top_defektai as $d): ?>
            <li>
                <span class="defektas-punktas">Punktas <?= (int)$d['eil_nr'] ?>:</span>
                <span class="defektas-tekstas"><?= h($d['reikalavimas']) ?></span>
                <span class="badge badge-danger"><?= (int)$d['kiekis'] ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;" data-testid="chart-container">
    <div class="card-header">
        <span class="card-title">Per savaitę: patikrinta gaminių ir rasta klaidų</span>
    </div>
    <div class="card-body">
        <canvas id="grafikas" height="90" data-testid="chart-weekly"></canvas>
    </div>
</div>

<?php if (!empty($defektu_gaminiai)): ?>
<div class="card" style="margin-bottom: 24px;" data-testid="table-defektai-container">
    <div class="card-header">
        <span class="card-title">Užsakymai ir defektai</span>
        <span class="badge badge-primary"><?= count($defektu_gaminiai) ?> įrašų</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table data-testid="table-defektai">
                <thead>
                    <tr>
                        <th>Užsakymo numeris</th>
                        <th>Reikalavimas</th>
                        <th>Defektas</th>
                        <th>Būsena</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($defektu_gaminiai as $eil):
                        $busena = (in_array(strtolower((string)($eil['isvada'] ?? '')), ['neatitinka','nepadaryta'])) ? 'Nepataisyta' : 'Pataisyta';
                        $busena_class = $busena === 'Nepataisyta' ? 'badge-danger' : 'badge-success';
                    ?>
                    <tr>
                        <td><?= h($eil['uzsakymo_numeris']) ?></td>
                        <td><?= h($eil['reikalavimas'] ?? '-') ?></td>
                        <td><?= h($eil['defektas'] ?? '-') ?></td>
                        <td><span class="badge <?= $busena_class ?>"><?= h($busena) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <span class="card-title">Užsakymai ir defektai</span>
    </div>
    <div class="card-body">
        <div class="empty-state"><p>Pagal pasirinktus filtrus užsakymų nerasta.</p></div>
    </div>
</div>
<?php endif; ?>

<div class="card aktyvus-defektai-card" style="margin-bottom: 24px;" data-testid="table-aktyvus-container">
    <div class="card-header aktyvus-defektai-header">
        <span class="card-title">
            <span class="aktyvus-dot"></span>
            Aktyvūs nepataisyti defektai
        </span>
        <span class="badge badge-danger"><?= count($aktyvus_defektai) ?></span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table data-testid="table-aktyvus-defektai">
                <thead class="aktyvus-thead">
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Užsakymo numeris</th>
                        <th>Reikalavimas</th>
                        <th>Defekto aprašymas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($aktyvus_defektai)): ?>
                    <?php foreach ($aktyvus_defektai as $row): ?>
                    <tr>
                        <td style="text-align: center;"><span class="aktyvus-dot"></span></td>
                        <td><?= h($row['uzsakymo_numeris']) ?></td>
                        <td><?= h($row['reikalavimas']) ?></td>
                        <td><?= h($row['defektas']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 24px; color: var(--text-secondary);">Nėra aktyvių nepataisytų defektų</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php if ($rodyti_duomenis): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(async function() {
    const params = new URLSearchParams({
        uzsakymo_numeris: <?= json_encode($uzsakymo_numeris) ?>,
        periodas: <?= json_encode($periodas) ?>,
        menuo: <?= json_encode($menuo) ?>,
        nuo: <?= json_encode($nuo) ?>,
        iki: <?= json_encode($iki) ?>
    });
    try {
        const res = await fetch('/grafiko_duomenys.php?' + params.toString());
        const data = await res.json();

        if (!data || data.length === 0) return;

        const labels = data.map(d => 'Savaite ' + d.savaite);
        const patikrinta = data.map(d => d.patikrinta_gaminiu);
        const klaidos = data.map(d => d.klaidu);

        const ctx = document.getElementById('grafikas').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Patikrinta gaminių',
                        data: patikrinta,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Rasta klaidų',
                        data: klaidos,
                        backgroundColor: 'rgba(220, 38, 38, 0.7)',
                        borderColor: 'rgba(220, 38, 38, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'vnt.' }
                    }
                }
            }
        });
    } catch (e) {
        console.log('Chart data not available');
    }
})();
</script>
<?php endif; ?>
