<?php
/**
 * MT statistikos puslapis
 * 
 * Šis failas atsakingas už MT (mažos transformatorinės) gaminių kokybės
 * statistikos atvaizdavimą. Rodo patikrintų užsakymų skaičių, rastų klaidų
 * kiekį, dažniausius defektus ir nepataisytus gedimus. Duomenys gali būti
 * filtruojami pagal užsakymo numerį, laikotarpį arba datų intervalą.
 */

include 'db.php';
require_once 'klases/Sesija.php';

/**
 * Sesijos inicijavimas ir prisijungimo tikrinimas
 * Jei vartotojas neprisijungęs - nukreipia į prisijungimo puslapį
 */
Sesija::pradzia();
Sesija::tikrintiPrisijungima();

/**
 * GET parametrų nuskaitymas
 * Gaunami filtravimo parametrai: užsakymo numeris, periodas, mėnuo, datos nuo/iki
 */
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$periodas         = $_GET['periodas'] ?? 'visi';
$menuo            = $_GET['menuo'] ?? '';
$nuo              = $_GET['nuo'] ?? '';
$iki              = $_GET['iki'] ?? '';

/**
 * SQL užklausa: Užsakymų sąrašo gavimas
 * Gauna visus unikalius užsakymo numerius, kurie turi MT tipo gaminių
 * Naudojama filtravimo išskleidžiamajame sąraše
 */
$uzsakymai = $pdo->query("
    SELECT DISTINCT u.uzsakymo_numeris
    FROM uzsakymai u
    JOIN gaminiai g       ON g.uzsakymo_id = u.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    WHERE gt.grupe = 'MT'
    ORDER BY u.uzsakymo_numeris DESC
")->fetchAll(PDO::FETCH_COLUMN);

/**
 * Statistikos kintamųjų inicializavimas
 */
$patikrinti = 0;
$klaidos = 0;
$top_defektai = [];
$defektu_gaminiai = [];
$aktyvus_defektai = [];

/**
 * Filtrų konstravimas
 * Sukuriami SQL WHERE sąlygų fragmentai pagal pasirinktus filtrus
 */
$where_uzsakymas = '';
$where_laikotarpis = '';
$params = [];

/**
 * Užsakymo filtro apdorojimas
 * Jei pasirinktas konkretus užsakymas, pridedama atitinkama sąlyga
 */
if ($uzsakymo_numeris !== '') {
    $where_uzsakymas = "u.uzsakymo_numeris = ?";
    $params[] = $uzsakymo_numeris;
} else {
    $where_uzsakymas = "1=1";
}

/**
 * Laikotarpio filtro apdorojimas
 * Prioritetas: mėnuo > datų intervalas (nuo/iki) > periodas
 */
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

/**
 * Duomenų rodymo sąlygos tikrinimas
 * Duomenys rodomi tik kai pasirinktas bent vienas filtras
 */
$rodyti_duomenis = !(
    $uzsakymo_numeris === '' &&
    $periodas === 'visi' &&
    $menuo === '' &&
    ($nuo === '' || $iki === '')
);

/**
 * Bendro WHERE fragmento sukūrimas
 */
$where_sql = "WHERE $where_uzsakymas $where_laikotarpis";

/**
 * Duomenų skaičiavimo blokas (tik MT grupės gaminiams)
 * Vykdomas tik kai pasirinkti filtrai
 */
if ($rodyti_duomenis) {

    /**
     * SQL užklausa: Patikrintų gaminių skaičiaus gavimas
     * Skaičiuoja unikalių gaminių, kuriems atlikti funkciniai bandymai, kiekį
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT fb.gaminio_id)
        FROM mt_funkciniai_bandymai fb
        JOIN gaminiai g       ON fb.gaminio_id = g.id
        JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
        JOIN uzsakymai u      ON g.uzsakymo_id = u.id
        $where_sql
          AND gt.grupe = 'MT'
    ");
    $stmt->execute($params);
    $patikrinti = (int)$stmt->fetchColumn();

    /**
     * SQL užklausa: Užsakymų ir defektų sąrašo gavimas
     * Gauna visus užsakymus su defektais ir be defektų (UNION ALL)
     * Pirmoji dalis - užsakymai su defektais
     * Antroji dalis - užsakymai be defektų (išvada 'atitinka')
     */
    $stmt = $pdo->prepare("
        SELECT u.uzsakymo_numeris, fb.reikalavimas, fb.defektas, fb.isvada
        FROM mt_funkciniai_bandymai fb
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
        LEFT JOIN mt_funkciniai_bandymai fb ON fb.gaminio_id = g.id
        $where_sql
          AND gt.grupe = 'MT'
        GROUP BY u.uzsakymo_numeris
        HAVING SUM(CASE WHEN fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' THEN 1 ELSE 0 END) = 0

        ORDER BY uzsakymo_numeris
    ");
    $stmt->execute(array_merge($params, $params));
    $defektu_gaminiai = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /**
     * Klaidų skaičiaus skaičiavimas
     * Skaičiuojami tik realūs defektai (ne tušti)
     */
    foreach ($defektu_gaminiai as $r) {
        if (!empty(trim((string)$r['defektas']))) {
            $klaidos++;
        }
    }

    /**
     * SQL užklausa: TOP 5 dažniausių defektų pagal tikrinimo punktus
     * Grupuoja defektus pagal reikalavimą ir skaičiuoja kiekį
     */
    $stmt = $pdo->prepare("
        SELECT 
            MIN(fb.eil_nr) as eil_nr,
            fb.reikalavimas, 
            COUNT(*) AS kiekis
        FROM mt_funkciniai_bandymai fb
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

    /**
     * SQL užklausa: Aktyvių nepataisytų defektų gavimas
     * Gauna defektus, kurių išvada yra 'neatitinka' arba 'nepadaryta'
     */
    $stmt = $pdo->prepare("
        SELECT u.uzsakymo_numeris, f.reikalavimas, f.defektas
        FROM mt_funkciniai_bandymai f
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

/**
 * URL parametrų paruošimas nuorodoms
 * Sukuriami query string'ai GVX ir eksporto nuorodoms
 */
$gvx_qs = http_build_query([
    'uzsakymo_numeris' => $uzsakymo_numeris,
    'periodas'         => $periodas,
    'menuo'            => $menuo,
    'nuo'              => $nuo,
    'iki'              => $iki,
]);

/**
 * Eksporto URL parametrų paruošimas
 * Pridedamas tik_mt=1 parametras, kad eksportuotų tik MT duomenis
 */
$export_qs = http_build_query([
    'uzsakymo_numeris' => $uzsakymo_numeris,
    'periodas'         => $periodas,
    'menuo'            => $menuo,
    'nuo'              => $nuo,
    'iki'              => $iki,
    'tik_mt'           => 1,
]);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Išplėstinė statistika</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="container mt-5">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Pastebėtų gedimų statistika (MT)</h3>
    <div class="d-flex gap-2">
      <a href="cecho_ekranas_mt.php" class="btn btn-primary" target="_blank" title="Atidaryti cecho ekraną">
        📺 Vaizdo ekrane
      </a>
      <a href="kokybe_rodikliai.php" class="btn btn-secondary">🔙 Grįžti į kokybės rodiklius</a>
    </div>
  </div>

  <a class="btn btn-success mb-3" href="eksportas_excel.php?<?= $export_qs ?>">
    ⬇️ Eksportuoti į Excel
  </a>

  <!-- Filtravimo forma -->
  <form method="GET" class="row g-2 mb-4" id="filtras">
    <div class="col-md-3">
      <label class="form-label">Pasirinkti užsakymą:</label>
      <select name="uzsakymo_numeris" class="form-select">
        <option value="">-- Pasirinkti --</option>
        <?php foreach ($uzsakymai as $nr): ?>
          <option value="<?= htmlspecialchars($nr) ?>" <?= ($uzsakymo_numeris == $nr) ? 'selected' : '' ?>>
            <?= htmlspecialchars($nr) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Pasirinkti laikotarpį:</label>
      <select name="periodas" class="form-select">
        <option value="visi" <?= ($periodas=='visi')?'selected':'' ?>>Visi</option>
        <option value="1m"  <?= ($periodas=='1m') ?'selected':'' ?>>Paskutinis mėnuo</option>
        <option value="6m"  <?= ($periodas=='6m') ?'selected':'' ?>>Paskutiniai 6 mėnesiai</option>
        <option value="1y"  <?= ($periodas=='1y') ?'selected':'' ?>>Paskutiniai 12 mėnesių</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Pasirinkti mėnesį:</label>
      <input type="month" name="menuo" id="menuo" value="<?= htmlspecialchars($menuo) ?>" class="form-control">
    </div>

    <div class="col-md-3">
      <label class="form-label">Pasirinkti datų intervalą:</label>
      <div class="d-flex gap-2">
        <input type="date" name="nuo" id="nuo" class="form-control" value="<?= htmlspecialchars($nuo) ?>">
        <input type="date" name="iki" id="iki" class="form-control" value="<?= htmlspecialchars($iki) ?>">
      </div>
    </div>

    <div class="col-12 col-md-2 mt-2">
      <button type="submit" class="btn btn-primary w-100">Rodyti</button>
    </div>
  </form>

<?php 
/**
 * Pranešimas kai nepasirinkti filtrai
 * Rodomas informacinis pranešimas, kad reikia pasirinkti bent vieną filtrą
 */
if (!$rodyti_duomenis): ?>
  <div class="alert alert-info">
    ❗ Pasirinkite bent vieną filtrą (užsakymą ar laikotarpį), kad būtų rodomi duomenys.
  </div>
<?php else: ?>

  <!-- KPI (Key Performance Indicators) plytelės -->
  <div class="row text-center mb-4">
    <div class="col-md-4">
      <div class="card p-3 shadow-sm">
        <h6>Patikrinti užsakymai</h6>
        <p class="fs-4 text-success mb-0"><?= (int)$patikrinti ?></p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 shadow-sm">
        <h6>Klaidų skaičius</h6>
        <p class="fs-4 text-danger mb-0"><?= (int)$klaidos ?></p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 shadow-sm">
        <h6>5 dažniausiai klaidos punktai</h6>
        <ol class="text-start mb-0" style="font-size: 0.9rem; line-height: 1.6;">
          <?php if (empty($top_defektai)): ?>
            <li class="text-muted">Defektų nerasta</li>
          <?php else: ?>
            <?php foreach ($top_defektai as $d): ?>
              <li>
                <strong>Punktas <?= (int)$d['eil_nr'] ?>:</strong> 
                <?= htmlspecialchars($d['reikalavimas']) ?> 
                <span class="badge bg-danger"><?= (int)$d['kiekis'] ?></span>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ol>
      </div>
    </div>
  </div>

  <!-- Stulpelinė diagrama - savaitinė statistika -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Per savaitę: patikrinta gaminių ir rasta klaidų</h5>
      <canvas id="grafikas" height="90"></canvas>
    </div>
  </div>

  <!-- Užsakymų ir defektų lentelė -->
  <?php if (!empty($defektu_gaminiai)): ?>
  <div class="card mb-4">
    <div class="card-body">
      <h5>Užsakymai ir defektai</h5>
      <table class="table table-bordered">
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
            $busena = (in_array(strtolower((string)$eil['isvada']), ['neatitinka','nepadaryta'])) ? 'Nepataisyta' : 'Pataisyta'; ?>
            <tr>
              <td><?= htmlspecialchars($eil['uzsakymo_numeris']) ?></td>
              <td><?= htmlspecialchars($eil['reikalavimas'] ?? '') ?></td>
              <td><?= htmlspecialchars($eil['defektas'] ?? '') ?></td>
              <td><strong><?= htmlspecialchars($busena) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="card mb-4">
    <div class="card-body">
      <h5>Užsakymai ir defektai</h5>
      <p class="mb-0">Pagal pasirinktus filtrus užsakymų nerasta.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Aktyvių nepataisytų defektų lentelė -->
  <h5 class="text-danger"><span style="font-size:1.5em;">🔴</span> Aktyvūs nepataisyti defektai</h5>
  <table class="table table-sm table-bordered table-striped" style="font-size:0.85em;background-color:#f8d7da;">
    <thead class="table-danger text-center">
      <tr><th></th><th>Užsakymo numeris</th><th>Reikalavimas</th><th>Defekto aprašymas</th></tr>
    </thead>
    <?php if (!empty($aktyvus_defektai)): ?>
    <tbody>
      <?php foreach ($aktyvus_defektai as $row): ?>
        <tr>
          <td class="text-center">🔴</td>
          <td><?= htmlspecialchars($row['uzsakymo_numeris']) ?></td>
          <td><?= htmlspecialchars($row['reikalavimas']) ?></td>
          <td><?= htmlspecialchars($row['defektas']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <?php else: ?>
    <tbody><tr><td colspan="4" class="text-center">Nėra aktyvių nepataisytų defektų</td></tr></tbody>
    <?php endif; ?>
  </table>

<?php endif; ?>
</div>

<?php 
/**
 * JavaScript blokas - stulpelinės diagramos generavimas
 * Naudoja Chart.js biblioteką duomenų vizualizacijai
 * Gauna duomenis iš grafiko_duomenys.php API endpoint'o
 */
if ($rodyti_duomenis): ?>
<script>
(async function() {
  const params = new URLSearchParams({
    uzsakymo_numeris: "<?= htmlspecialchars($uzsakymo_numeris) ?>",
    periodas: "<?= htmlspecialchars($periodas) ?>",
    menuo: "<?= htmlspecialchars($menuo) ?>",
    nuo: "<?= htmlspecialchars($nuo) ?>",
    iki: "<?= htmlspecialchars($iki) ?>"
  });
  const res = await fetch('grafiko_duomenys.php?' + params.toString());
  const data = await res.json();

  const labels = data.map(d => 'Savaitė ' + d.savaite);
  const patikrinta = data.map(d => d.patikrinta_gaminiu);
  const klaidos = data.map(d => d.klaidu);

  const ctx = document.getElementById('grafikas').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Patikrinta gaminių', data: patikrinta, borderWidth: 1 },
        { label: 'Rasta klaidų', data: klaidos, borderWidth: 1 }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      scales: { y: { beginAtZero: true, title: { display: true, text: 'vnt.' } } }
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
