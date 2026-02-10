<?php
/**
 * MT Cecho Kokybės Ekranas
 * 
 * Šis failas generuoja kokybės rodiklių prietaisų skydelį (dashboard)
 * MT (matavimo transformatorių) cecho ekranui.
 * Rodo paskutinių 30 dienų (1 mėnesio) statistiką: patikrintus gaminius,
 * neatitikimus, aktyvius nepataisytus defektus ir mėnesinę dinamiką.
 * 
 * Atnaujinamas automatiškai kas 5 minutes. Šviesi tema.
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db.php';

/**
 * Bazinis SQL filtras
 * Filtruoja tik MT grupės gaminius ir paskutinių 30 dienų duomenis.
 */
$where_sql = "WHERE gt.grupe = 'MT'
  AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'";
$params = [];

/**
 * Defekto sąlygos apibrėžimas
 */
$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

/* ========== KPI RODIKLIAI ========== */

/**
 * SQL užklausa: Patikrintų gaminių skaičius
 */
$sql = "
  SELECT COUNT(DISTINCT fb.gaminio_id)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
";
$patikrinti = (int)$pdo->query($sql)->fetchColumn();

/**
 * SQL užklausa: Viso neatitikimų skaičius
 */
$sql = "
  SELECT COUNT(*)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
  AND $DEFECT_COND
";
$viso_defektu = (int)$pdo->query($sql)->fetchColumn();

/**
 * Testų punktų skaičius (naudojama procentų skaičiavimui)
 */
$sql = "
  SELECT COUNT(*)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
";
$viso_punktu = (int)$pdo->query($sql)->fetchColumn();

/**
 * Vidutinio neatitikimų procento apskaičiavimas
 */
$vid_proc = ($viso_punktu > 0) ? round($viso_defektu / $viso_punktu * 100, 1) : 0.0;

/* ========== AKTYVŪS NEPATAISYTI DEFEKTAI ========== */

/**
 * SQL užklausa: Aktyvūs nepataisyti defektai
 * Gauna defektų sąrašą, kurie dar nepataisyti (istaisyta IS NULL arba istaisyta = 0)
 */
$sql_aktyvus = "
  SELECT 
    u.uzsakymo_numeris AS uzsakymo_nr,
    g.gaminio_numeris,
    gt.gaminio_tipas AS gaminio_tipas,
    fb.eil_nr AS punkto_nr,
    fb.reikalavimas,
    fb.defektas AS defekto_aprasymas
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  WHERE gt.grupe = 'MT'
  AND $DEFECT_COND
  AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'
  ORDER BY u.uzsakymo_numeris DESC, g.gaminio_numeris, fb.eil_nr
  LIMIT 50
";
$aktyvus_defektai = $pdo->query($sql_aktyvus)->fetchAll(PDO::FETCH_ASSOC);
$aktyvus_count = count($aktyvus_defektai);

/* ========== TOP 5 DAŽNIAUSIOS KLAIDOS ========== */

/**
 * SQL užklausa: TOP 5 dažniausiai pasitaikančios klaidos
 */
$sql = "
  SELECT 
    MIN(fb.eil_nr) as eil_nr,
    fb.reikalavimas, 
    COUNT(*) AS kiekis
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
  AND $DEFECT_COND
  AND fb.reikalavimas IS NOT NULL
  AND TRIM(fb.reikalavimas) <> ''
  GROUP BY fb.reikalavimas
  ORDER BY kiekis DESC, eil_nr ASC
  LIMIT 5
";
$top_klaidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/**
 * Maksimalus klaidų kiekis (naudojamas progress bar procentams)
 */
$max_kiekis = !empty($top_klaidos) ? (int)$top_klaidos[0]['kiekis'] : 1;

/* ========== MĖNESINĖ DINAMIKA (SAVAITĖMIS) ========== */

/**
 * SQL užklausa: Mėnesinė dinamika suskirstyta pagal savaites
 */
$sql = "
  SELECT 
    TO_CHAR(u.sukurtas::timestamp, 'IYYYIW') AS yw,
    COUNT(DISTINCT fb.gaminio_id) AS patikrinta,
    SUM(CASE WHEN $DEFECT_COND THEN 1 ELSE 0 END) AS klaidu
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
  GROUP BY TO_CHAR(u.sukurtas::timestamp, 'IYYYIW')
  ORDER BY TO_CHAR(u.sukurtas::timestamp, 'IYYYIW')
";
$weeks = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/**
 * Duomenų paruošimas JavaScript grafikui
 */
$wLabels=[]; $wGaminiai=[]; $wKlaidos=[];
foreach ($weeks as $w) {
  $yw=(string)$w['yw']; 
  $wk=substr($yw,-2);
  $pat=(int)$w['patikrinta']; 
  $kl=(int)$w['klaidu'];
  $wLabels[]="S$wk"; 
  $wGaminiai[]=$pat; 
  $wKlaidos[]=$kl;
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MT Kokybės Rodikliai - Cecho Ekranas</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
  margin: 0;
  padding: 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  min-height: 100vh;
}

.header {
  text-align: center;
  margin-bottom: 25px;
  padding: 20px;
}

.header h1 {
  color: #0d6efd;
  font-size: 2.5rem;
  margin: 0;
  font-weight: 700;
}

.subtitle {
  color: #6c757d;
  font-size: 1rem;
  margin-top: 8px;
}

.time {
  color: #495057;
  font-size: 0.9rem;
  margin-top: 5px;
}

.kpi-container {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  max-width: 1600px;
  margin: 0 auto 25px auto;
}

@media (max-width: 900px) {
  .kpi-container { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
  .kpi-container { grid-template-columns: 1fr; }
}

.kpi-card {
  background: white;
  border-radius: 16px;
  padding: 25px;
  text-align: center;
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  border-top: 4px solid;
}

.kpi-card.green { border-color: #198754; }
.kpi-card.orange { border-color: #fd7e14; }
.kpi-card.yellow { border-color: #ffc107; }
.kpi-card.red { border-color: #dc3545; }

.kpi-value {
  font-size: 3rem;
  font-weight: 700;
  line-height: 1.2;
}

.kpi-card.green .kpi-value { color: #198754; }
.kpi-card.orange .kpi-value { color: #fd7e14; }
.kpi-card.yellow .kpi-value { color: #ffc107; }
.kpi-card.red .kpi-value { color: #dc3545; }

.kpi-label {
  color: #6c757d;
  font-size: 0.95rem;
  margin-top: 8px;
  font-weight: 600;
}

.panels-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  max-width: 1600px;
  margin: 0 auto 25px auto;
}

@media (max-width: 1100px) {
  .panels-container { grid-template-columns: 1fr; }
}

.panel {
  background: white;
  border-radius: 16px;
  padding: 25px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  min-height: 350px;
}

.chart-wrapper {
  position: relative;
  height: 280px;
  width: 100%;
}

.panel-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #212529;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.panel-title .icon { font-size: 1.3rem; }

.klaidos-item {
  margin-bottom: 15px;
}

.klaidos-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.klaidos-text {
  color: #495057;
  font-size: 0.9rem;
  flex: 1;
  padding-right: 10px;
}

.klaidos-count {
  background: linear-gradient(90deg, #dc3545, #fd7e14);
  color: white;
  padding: 4px 12px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.85rem;
}

.klaidos-bar {
  height: 8px;
  background: #e9ecef;
  border-radius: 4px;
  overflow: hidden;
}

.klaidos-fill {
  height: 100%;
  background: linear-gradient(90deg, #dc3545, #fd7e14);
  border-radius: 4px;
  transition: width 0.5s ease;
}

.aktyvus-section {
  max-width: 1600px;
  margin: 0 auto 25px auto;
}

.aktyvus-panel {
  background: white;
  border-radius: 16px;
  padding: 25px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  border-top: 4px solid #dc3545;
}

.defekt-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 15px;
}

.defekt-item {
  background: #fff8f8;
  border: 1px solid #f8d7da;
  border-radius: 12px;
  padding: 15px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.defekt-dot {
  width: 10px;
  height: 10px;
  background: #dc3545;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}

.defekt-info { flex: 1; }

.defekt-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
  align-items: center;
}

.defekt-order {
  font-weight: 600;
  color: #495057;
}

.defekt-gaminys {
  background: #6f42c1;
  color: white;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.defekt-punktas {
  color: #6c757d;
  font-size: 0.8rem;
}

.defekt-text {
  color: #666;
  font-size: 0.85rem;
  line-height: 1.4;
}

.defekt-empty {
  text-align: center;
  color: #198754;
  font-size: 1.2rem;
  padding: 40px;
}

.footer {
  text-align: center;
  color: #6c757d;
  font-size: 0.85rem;
  margin-top: 30px;
  padding-bottom: 20px;
}
</style>
</head>
<body>

<div class="header">
  <h1>MT KOKYBĖS RODIKLIAI</h1>
  <div class="subtitle">Paskutinės 30 dienų (1 mėnuo)</div>
  <div class="time" id="currentTime"></div>
</div>

<!-- KPI Cards -->
<div class="kpi-container">
  <div class="kpi-card green">
    <div class="kpi-value"><?= $patikrinti ?></div>
    <div class="kpi-label">Patikrinta gaminių</div>
  </div>
  <div class="kpi-card orange">
    <div class="kpi-value"><?= $viso_defektu ?></div>
    <div class="kpi-label">Viso neatitikimų</div>
  </div>
  <div class="kpi-card yellow">
    <div class="kpi-value"><?= $vid_proc ?>%</div>
    <div class="kpi-label">Neatitikimų %</div>
  </div>
  <div class="kpi-card red">
    <div class="kpi-value"><?= $aktyvus_count ?></div>
    <div class="kpi-label">Aktyvūs nepataisyti</div>
  </div>
</div>

<!-- Mėnesinė Suvestinė + TOP 5 Klaidos -->
<div class="panels-container">
  <!-- Mėnesinė Suvestinė -->
  <div class="panel">
    <div class="panel-title">
      <span class="icon">📊</span> Mėnesinė Suvestinė
    </div>
    <div class="chart-wrapper">
      <canvas id="weeklyChart"></canvas>
    </div>
  </div>
  
  <!-- TOP 5 Klaidos -->
  <div class="panel">
    <div class="panel-title">
      <span class="icon">🔥</span> TOP 5 Klaidos
    </div>
    <?php if (empty($top_klaidos)): ?>
      <div style="text-align: center; color: #198754; padding: 40px; font-size: 1.1rem;">
        ✅ Defektų nerasta - puikus darbas!
      </div>
    <?php else: ?>
      <div class="klaidos-list">
        <?php foreach ($top_klaidos as $idx => $t): 
          $pct = $max_kiekis > 0 ? round((int)$t['kiekis'] / $max_kiekis * 100) : 0;
        ?>
          <div class="klaidos-item">
            <div class="klaidos-header">
              <span class="klaidos-text">
                <strong>#<?= $idx+1 ?></strong> Kl. <?= (int)$t['eil_nr'] ?>: <?= htmlspecialchars(mb_substr($t['reikalavimas'] ?? '', 0, 60)) ?><?= mb_strlen($t['reikalavimas'] ?? '') > 60 ? '...' : '' ?>
              </span>
              <span class="klaidos-count"><?= (int)$t['kiekis'] ?></span>
            </div>
            <div class="klaidos-bar">
              <div class="klaidos-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Aktyvūs Nepataisyti Defektai -->
<div class="aktyvus-section">
  <div class="aktyvus-panel">
    <div class="panel-title">
      <span class="icon">🔴</span> 
      Aktyvūs Nepataisyti Defektai
      <?php if ($aktyvus_count > 0): ?>
        <span style="background:#dc3545; color:white; padding:2px 10px; border-radius:10px; font-size:0.8rem; margin-left:8px;"><?= $aktyvus_count ?></span>
      <?php endif; ?>
    </div>
    
    <?php if (empty($aktyvus_defektai)): ?>
      <div class="defekt-empty">✅ Puiku! Visi defektai pataisyti.</div>
    <?php else: ?>
      <div class="defekt-grid">
        <?php foreach ($aktyvus_defektai as $d): ?>
          <div class="defekt-item">
            <div class="defekt-dot"></div>
            <div class="defekt-info">
              <div class="defekt-meta">
                <span class="defekt-order"><?= htmlspecialchars($d['uzsakymo_nr'] ?? '') ?></span>
                <span class="defekt-gaminys"><?= htmlspecialchars($d['gaminio_tipas'] ?? '') ?></span>
                <span class="defekt-punktas">• Pkt. <?= (int)($d['punkto_nr'] ?? 0) ?></span>
              </div>
              <div class="defekt-text"><?= htmlspecialchars(mb_substr($d['defekto_aprasymas'] ?? '', 0, 100)) ?><?= mb_strlen($d['defekto_aprasymas'] ?? '') > 100 ? '...' : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="footer">
  Duomenys atnaujinami automatiškai kas 5 minutes | <span>Praktika QMS</span>
</div>

<script>
function updateTime() {
  const now = new Date();
  const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  document.getElementById('currentTime').textContent = now.toLocaleDateString('lt-LT', opts);
}
updateTime();
setInterval(updateTime, 60000);

setTimeout(() => location.reload(), 300000);

const ctx = document.getElementById('weeklyChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($wLabels) ?>,
    datasets: [
      {
        label: 'Gaminiai',
        data: <?= json_encode($wGaminiai) ?>,
        backgroundColor: 'rgba(25, 135, 84, 0.8)',
        borderColor: 'rgba(25, 135, 84, 1)',
        borderWidth: 1,
        borderRadius: 4
      },
      {
        label: 'Neatitikimai',
        data: <?= json_encode($wKlaidos) ?>,
        backgroundColor: 'rgba(220, 53, 69, 0.8)',
        borderColor: 'rgba(220, 53, 69, 1)',
        borderWidth: 1,
        borderRadius: 4
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { 
          padding: 15,
          usePointStyle: true,
          font: { size: 12 }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,0.05)' }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});
</script>

</body>
</html>
