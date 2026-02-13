<?php
/**
 * Pagrindinis puslapis - kokybės rodiklių skydelis (paskutinės 30 dienų)
 *
 * Šis puslapis rodo MT gaminių kokybės rodiklius (KPI):
 * - Patikrintų gaminių skaičius
 * - Bendras neatitikimų kiekis
 * - Neatitikimų procentas
 * - Aktyvūs nepataisyti defektai
 * - Savaitinė suvestinė (grafikas)
 * - TOP 5 dažniausios klaidos
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Kokybės rodikliai';

// Filtravimo sąlyga: tik MT grupės gaminiai per paskutines 30 dienų
$where_sql = "WHERE gt.grupe = 'MT'
  AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'";

// Defekto buvimo sąlyga (naudojama keliuose užklausose)
$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

// SQL užklausa: unikalių patikrintų gaminių skaičius
$sql = "
  SELECT COUNT(DISTINCT fb.gaminio_id)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
";
$patikrinti = (int)$pdo->query($sql)->fetchColumn();

// SQL užklausa: bendras neatitikimų (defektų) skaičius
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

// SQL užklausa: visų bandymo punktų skaičius (neatitikimų procento skaičiavimui)
$sql = "
  SELECT COUNT(*)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql
";
$viso_punktu = (int)$pdo->query($sql)->fetchColumn();

// Neatitikimų procentas: defektai / visi punktai * 100
$vid_proc = ($viso_punktu > 0) ? round($viso_defektu / $viso_punktu * 100, 1) : 0.0;

// SQL užklausa: aktyvūs nepataisyti defektai (iki 50 įrašų)
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

// SQL užklausa: TOP 5 dažniausiai pasikartojančios klaidos pagal reikalavimą
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

// SQL užklausa: savaitiniai duomenys grafikui (patikrinti gaminiai ir klaidos pagal savaitę)
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

// Paruošiami masyvai Chart.js savaitiniam grafikui
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

function gautiKetvircioStatistika_idx($pdo, $metai, $ketvirtis, $DEFECT_COND) {
    $metai = (int)$metai;
    $ketvirtis = (int)$ketvirtis;
    $nuo_men = ($ketvirtis - 1) * 3 + 1;
    $iki_men = $ketvirtis * 3;
    $nuo = "$metai-" . str_pad($nuo_men, 2, '0', STR_PAD_LEFT) . "-01";
    $iki = "$metai-" . str_pad($iki_men, 2, '0', STR_PAD_LEFT) . "-" . cal_days_in_month(CAL_GREGORIAN, $iki_men, $metai);
    $where = "WHERE u.sukurtas::date BETWEEN '$nuo' AND '$iki'";
    $r = [];
    $r['periodas'] = "$metai Q$ketvirtis";
    $r['uzsakymai'] = (int)$pdo->query("SELECT COUNT(DISTINCT u.id) FROM uzsakymai u JOIN gaminiai g ON g.uzsakymo_id = u.id JOIN mt_funkciniai_bandymai fb ON fb.gaminio_id = g.id $where")->fetchColumn();
    $r['gaminiai'] = (int)$pdo->query("SELECT COUNT(DISTINCT g.id) FROM gaminiai g JOIN uzsakymai u ON u.id = g.uzsakymo_id JOIN mt_funkciniai_bandymai fb ON fb.gaminio_id = g.id $where")->fetchColumn();
    $r['bandymai'] = (int)$pdo->query("SELECT COUNT(*) FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $where")->fetchColumn();
    $r['defektai'] = (int)$pdo->query("SELECT COUNT(*) FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $where AND $DEFECT_COND")->fetchColumn();
    $r['defektu_proc'] = ($r['bandymai'] > 0) ? round($r['defektai'] / $r['bandymai'] * 100, 2) : 0;
    $r['defektu_per_gamini'] = ($r['gaminiai'] > 0) ? round($r['defektai'] / $r['gaminiai'], 2) : 0;
    $r['top_darbuotojai'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas, COUNT(*) AS bandymu,
            COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai
        FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko ORDER BY be_defektu DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    $r['top_klydusieji'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai, COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC, defektu_proc DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    $r['problemines_operacijos'] = $pdo->query("
        SELECT fb.reikalavimas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai, COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
        GROUP BY fb.reikalavimas HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    return $r;
}

$kp_q1 = $kp_q2 = null;
$kp_rodyti = false;
if (count($ketvirciu_sarasas) >= 2) {
    $kp_q2 = gautiKetvircioStatistika_idx($pdo, $ketvirciu_sarasas[0]['metai'], $ketvirciu_sarasas[0]['ketvirtis'], $DEFECT_COND);
    $kp_q1 = gautiKetvircioStatistika_idx($pdo, $ketvirciu_sarasas[1]['metai'], $ketvirciu_sarasas[1]['ketvirtis'], $DEFECT_COND);
    $kp_rodyti = true;
}

function kp_pokytis($senas, $naujas) {
    if ($senas == 0 && $naujas == 0) return ['reiksme' => 0, 'klase' => '', 'rodykle' => ''];
    if ($senas == 0) return ['reiksme' => 100, 'klase' => 'pokytis-up', 'rodykle' => '+'];
    $p = round(($naujas - $senas) / $senas * 100, 1);
    if ($p > 0) return ['reiksme' => $p, 'klase' => 'pokytis-up', 'rodykle' => '+'];
    if ($p < 0) return ['reiksme' => abs($p), 'klase' => 'pokytis-down', 'rodykle' => '-'];
    return ['reiksme' => 0, 'klase' => '', 'rodykle' => ''];
}
function kp_defPokytis($senas, $naujas) {
    $p = kp_pokytis($senas, $naujas);
    if ($p['klase'] === 'pokytis-up') $p['klase'] = 'pokytis-blogiau';
    elseif ($p['klase'] === 'pokytis-down') $p['klase'] = 'pokytis-geriau';
    return $p;
}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-top-bar">
  <div class="dashboard-subtitle" data-testid="text-dashboard-period">Paskutinės 30 dienų (1 mėnuo)</div>
  <a href="/mt_statistika.php" class="btn btn-primary btn-sm" data-testid="link-mt-statistika">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
    Išplėstinė statistika su filtrais
  </a>
</div>

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

<?php if ($kp_rodyti): ?>
<?php
$p_uzs = kp_pokytis($kp_q1['uzsakymai'], $kp_q2['uzsakymai']);
$p_gam = kp_pokytis($kp_q1['gaminiai'], $kp_q2['gaminiai']);
$p_ban = kp_pokytis($kp_q1['bandymai'], $kp_q2['bandymai']);
$p_def = kp_defPokytis($kp_q1['defektai'], $kp_q2['defektai']);
$p_proc = kp_defPokytis($kp_q1['defektu_proc'], $kp_q2['defektu_proc']);
?>

<div class="dashboard-panel kp-section" data-testid="panel-ketvirciu-palyginimas">
  <div class="dashboard-panel-title">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Ketvirciu palyginimas: <?= h($kp_q1['periodas']) ?> vs <?= h($kp_q2['periodas']) ?>
    <a href="/ketvirciu_palyginimas.php" class="btn btn-secondary btn-sm" style="margin-left:auto;font-size:12px;" data-testid="link-full-comparison">Pilnas palyginimas</a>
  </div>

  <div class="kp-summary" data-testid="kp-summary" style="padding:12px 16px;font-size:14px;line-height:1.7;color:var(--text-primary);background:var(--bg-light);border-radius:8px;margin-bottom:16px;">
    <strong><?= h($kp_q2['periodas']) ?></strong> palyginti su <strong><?= h($kp_q1['periodas']) ?></strong>:
    <?php if ($kp_q2['defektu_proc'] < $kp_q1['defektu_proc']): ?>
      defektu procentas <span style="color:#16a34a;font-weight:600;">sumazejo nuo <?= $kp_q1['defektu_proc'] ?>% iki <?= $kp_q2['defektu_proc'] ?>%</span>.
    <?php elseif ($kp_q2['defektu_proc'] > $kp_q1['defektu_proc']): ?>
      defektu procentas <span style="color:#dc2626;font-weight:600;">padidejo nuo <?= $kp_q1['defektu_proc'] ?>% iki <?= $kp_q2['defektu_proc'] ?>%</span>.
    <?php else: ?>
      defektu procentas <span style="font-weight:600;">nepasikete (<?= $kp_q1['defektu_proc'] ?>%)</span>.
    <?php endif; ?>
    Gaminiu: <?= $kp_q1['gaminiai'] ?> &rarr; <?= $kp_q2['gaminiai'] ?>.
    Defektu: <?= $kp_q1['defektai'] ?> &rarr; <?= $kp_q2['defektai'] ?>.
  </div>

  <div class="table-wrapper" style="margin-bottom:16px;">
    <table data-testid="table-kp-comparison">
      <thead>
        <tr><th>Rodiklis</th><th style="text-align:center;"><?= h($kp_q1['periodas']) ?></th><th style="text-align:center;"><?= h($kp_q2['periodas']) ?></th><th style="text-align:center;">Pokytis</th></tr>
      </thead>
      <tbody>
        <?php
        $kp_eilutes = [
            ['Uzsakymai', $kp_q1['uzsakymai'], $kp_q2['uzsakymai'], $p_uzs],
            ['Patikrinti gaminiai', $kp_q1['gaminiai'], $kp_q2['gaminiai'], $p_gam],
            ['Bandymu punktai', $kp_q1['bandymai'], $kp_q2['bandymai'], $p_ban],
            ['Rasti defektai', $kp_q1['defektai'], $kp_q2['defektai'], $p_def],
            ['Defektu procentas', $kp_q1['defektu_proc'].'%', $kp_q2['defektu_proc'].'%', $p_proc],
            ['Defektai / gaminys', $kp_q1['defektu_per_gamini'], $kp_q2['defektu_per_gamini'], kp_defPokytis($kp_q1['defektu_per_gamini'], $kp_q2['defektu_per_gamini'])],
        ];
        foreach ($kp_eilutes as $e): ?>
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

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    <div data-testid="kp-top-workers">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:var(--text-primary);">TOP 5 darbuotojai (daugiausiai punktu)</div>
      <div class="table-wrapper">
        <table data-testid="table-kp-top-workers">
          <thead><tr><th>#</th><th>Darbuotojas</th><th>Ketv.</th><th style="text-align:center;">Be def.</th></tr></thead>
          <tbody>
            <?php
            $combined = [];
            foreach ($kp_q2['top_darbuotojai'] as $d) $combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'be_defektu'=>(int)$d['be_defektu'], 'ketvirtis'=>$kp_q2['periodas']];
            foreach ($kp_q1['top_darbuotojai'] as $d) { if (!isset($combined[$d['vardas']]) || (int)$d['be_defektu'] > $combined[$d['vardas']]['be_defektu']) $combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'be_defektu'=>(int)$d['be_defektu'], 'ketvirtis'=>$kp_q1['periodas']]; }
            usort($combined, fn($a,$b) => $b['be_defektu'] <=> $a['be_defektu']);
            $combined = array_slice($combined, 0, 5);
            $i = 1;
            foreach ($combined as $d): ?>
            <tr>
              <td style="font-weight:600;color:<?= $i<=3?'#f59e0b':'var(--text-secondary)' ?>;"><?= $i++ ?></td>
              <td style="font-weight:500;"><?= h($d['vardas']) ?></td>
              <td><span class="badge badge-primary" style="font-size:11px;"><?= h($d['ketvirtis']) ?></span></td>
              <td style="text-align:center;font-weight:600;color:#16a34a;"><?= $d['be_defektu'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($combined)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:12px;">Duomenu nerasta</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div data-testid="kp-top-errors">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:#dc2626;">TOP 5 daugiausiai klydo</div>
      <div class="table-wrapper">
        <table data-testid="table-kp-top-errors">
          <thead><tr><th>#</th><th>Darbuotojas</th><th>Ketv.</th><th style="text-align:center;">Def.</th><th style="text-align:center;">%</th></tr></thead>
          <tbody>
            <?php
            $err_combined = [];
            foreach ($kp_q2['top_klydusieji'] as $d) $err_combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'defektai'=>(int)$d['defektai'], 'defektu_proc'=>$d['defektu_proc'], 'ketvirtis'=>$kp_q2['periodas']];
            foreach ($kp_q1['top_klydusieji'] as $d) { if (!isset($err_combined[$d['vardas']]) || (int)$d['defektai'] > $err_combined[$d['vardas']]['defektai']) $err_combined[$d['vardas']] = ['vardas'=>$d['vardas'], 'defektai'=>(int)$d['defektai'], 'defektu_proc'=>$d['defektu_proc'], 'ketvirtis'=>$kp_q1['periodas']]; }
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
            <tr><td colspan="5" style="text-align:center;color:var(--text-secondary);padding:12px;">Defektu nerasta</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    <?php foreach ([$kp_q1, $kp_q2] as $q): ?>
    <div>
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;">Problemingiausios operacijos (<?= h($q['periodas']) ?>)</div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Operacija</th><th style="text-align:center;">Def.</th><th style="text-align:center;">%</th></tr></thead>
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
            <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:12px;">Defektu nerasta</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="chart-container" style="margin-bottom:8px;">
    <canvas id="compChart"></canvas>
  </div>
</div>
<?php endif; ?>

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
  Duomenys atnaujinami automatiskai kas 5 minutes
</div>

<!-- Chart.js savaitinio grafiko atvaizdavimas -->
<script>
// Automatinis puslapio atnaujinimas kas 5 minutes (300000 ms)
setTimeout(() => location.reload(), 300000);

// Savaitinio stulpelinio grafiko inicializavimas su Chart.js biblioteka
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

<?php if ($kp_rodyti): ?>
var compCtx = document.getElementById('compChart');
if (compCtx) {
  new Chart(compCtx.getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Uzsakymai', 'Gaminiai', 'Bandymu punktai', 'Defektai'],
      datasets: [
        {
          label: <?= json_encode($kp_q1['periodas']) ?>,
          data: [<?= $kp_q1['uzsakymai'] ?>, <?= $kp_q1['gaminiai'] ?>, <?= $kp_q1['bandymai'] ?>, <?= $kp_q1['defektai'] ?>],
          backgroundColor: 'rgba(37, 99, 235, 0.7)',
          borderColor: 'rgba(37, 99, 235, 1)',
          borderWidth: 1,
          borderRadius: 4
        },
        {
          label: <?= json_encode($kp_q2['periodas']) ?>,
          data: [<?= $kp_q2['uzsakymai'] ?>, <?= $kp_q2['gaminiai'] ?>, <?= $kp_q2['bandymai'] ?>, <?= $kp_q2['defektai'] ?>],
          backgroundColor: 'rgba(16, 185, 129, 0.7)',
          borderColor: 'rgba(16, 185, 129, 1)',
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
          labels: { padding: 15, usePointStyle: true, font: { size: 12, family: "'Inter', sans-serif" } }
        }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { family: "'Inter', sans-serif" } } },
        x: { grid: { display: false }, ticks: { font: { family: "'Inter', sans-serif" } } }
      }
    }
  });
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
