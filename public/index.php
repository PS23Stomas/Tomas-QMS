<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Kokybės rodikliai';

$where_sql = "WHERE gt.grupe = 'MT'
  AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'";

$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

$sql = "
  SELECT COUNT(DISTINCT fb.gaminio_id)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
";
$patikrinti = (int)$pdo->query($sql)->fetchColumn();

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

$sql = "
  SELECT COUNT(*)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
";
$viso_punktu = (int)$pdo->query($sql)->fetchColumn();

$vid_proc = ($viso_punktu > 0) ? round($viso_defektu / $viso_punktu * 100, 1) : 0.0;

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
$max_kiekis = !empty($top_klaidos) ? (int)$top_klaidos[0]['kiekis'] : 1;

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

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-subtitle" data-testid="text-dashboard-period">Paskutinės 30 dienų (1 mėnuo)</div>

<div class="kpi-grid" data-testid="kpi-container">
  <div class="kpi-card kpi-green" data-testid="kpi-patikrinta">
    <div class="kpi-value"><?= $patikrinti ?></div>
    <div class="kpi-label">Patikrinta gaminių</div>
  </div>
  <div class="kpi-card kpi-orange" data-testid="kpi-neatitikimai">
    <div class="kpi-value"><?= $viso_defektu ?></div>
    <div class="kpi-label">Viso neatitikimų</div>
  </div>
  <div class="kpi-card kpi-yellow" data-testid="kpi-procentas">
    <div class="kpi-value"><?= $vid_proc ?>%</div>
    <div class="kpi-label">Neatitikimų %</div>
  </div>
  <div class="kpi-card kpi-red" data-testid="kpi-aktyvus">
    <div class="kpi-value"><?= $aktyvus_count ?></div>
    <div class="kpi-label">Aktyvūs nepataisyti</div>
  </div>
</div>

<div class="dashboard-panels" data-testid="panels-container">
  <div class="dashboard-panel" data-testid="panel-chart">
    <div class="dashboard-panel-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
      Mėnesinė Suvestinė
    </div>
    <div class="chart-container">
      <canvas id="weeklyChart"></canvas>
    </div>
  </div>
  
  <div class="dashboard-panel" data-testid="panel-top-klaidos">
    <div class="dashboard-panel-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      TOP 5 Klaidos
    </div>
    <?php if (empty($top_klaidos)): ?>
      <div class="klaidos-empty" data-testid="text-no-defects">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Defektų nerasta - puikus darbas!
      </div>
    <?php else: ?>
      <div class="klaidos-list">
        <?php foreach ($top_klaidos as $idx => $t): 
          $pct = $max_kiekis > 0 ? round((int)$t['kiekis'] / $max_kiekis * 100) : 0;
        ?>
          <div class="klaidos-item" data-testid="klaida-<?= $idx ?>">
            <div class="klaidos-header">
              <span class="klaidos-text">
                <strong>#<?= $idx+1 ?></strong> Kl. <?= (int)$t['eil_nr'] ?>: <?= h(mb_substr($t['reikalavimas'] ?? '', 0, 60)) ?><?= mb_strlen($t['reikalavimas'] ?? '') > 60 ? '...' : '' ?>
              </span>
              <span class="klaidos-count-badge"><?= (int)$t['kiekis'] ?></span>
            </div>
            <div class="klaidos-bar-bg">
              <div class="klaidos-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="dashboard-panel aktyvus-panel-wrapper" data-testid="panel-aktyvus-defektai">
  <div class="dashboard-panel-title">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Aktyvūs Nepataisyti Defektai
    <?php if ($aktyvus_count > 0): ?>
      <span class="aktyvus-badge"><?= $aktyvus_count ?></span>
    <?php endif; ?>
  </div>
  
  <?php if (empty($aktyvus_defektai)): ?>
    <div class="klaidos-empty" data-testid="text-no-active-defects">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Puiku! Visi defektai pataisyti.
    </div>
  <?php else: ?>
    <div class="defekt-grid" data-testid="defekt-grid">
      <?php foreach ($aktyvus_defektai as $i => $d): ?>
        <div class="defekt-card" data-testid="defekt-<?= $i ?>">
          <div class="defekt-dot-indicator"></div>
          <div class="defekt-content">
            <div class="defekt-meta-row">
              <span class="defekt-order-nr"><?= h($d['uzsakymo_nr'] ?? '') ?></span>
              <span class="defekt-type-badge"><?= h($d['gaminio_tipas'] ?? '') ?></span>
              <span class="defekt-punkt-nr">Pkt. <?= (int)($d['punkto_nr'] ?? 0) ?></span>
            </div>
            <div class="defekt-description"><?= h(mb_substr($d['defekto_aprasymas'] ?? '', 0, 100)) ?><?= mb_strlen($d['defekto_aprasymas'] ?? '') > 100 ? '...' : '' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="dashboard-footer-info" data-testid="text-dashboard-footer">
  Duomenys atnaujinami automatiškai kas 5 minutes
</div>

<script>
setTimeout(() => location.reload(), 300000);

const ctx = document.getElementById('weeklyChart');
if (ctx) {
  new Chart(ctx.getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($wLabels) ?>,
      datasets: [
        {
          label: 'Gaminiai',
          data: <?= json_encode($wGaminiai) ?>,
          backgroundColor: 'rgba(22, 163, 74, 0.8)',
          borderColor: 'rgba(22, 163, 74, 1)',
          borderWidth: 1,
          borderRadius: 4
        },
        {
          label: 'Neatitikimai',
          data: <?= json_encode($wKlaidos) ?>,
          backgroundColor: 'rgba(220, 38, 38, 0.8)',
          borderColor: 'rgba(220, 38, 38, 1)',
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
            font: { size: 12, family: "'Inter', sans-serif" }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: { font: { family: "'Inter', sans-serif" } }
        },
        x: {
          grid: { display: false },
          ticks: { font: { family: "'Inter', sans-serif" } }
        }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
