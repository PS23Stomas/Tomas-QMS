<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

if (!isset($_GET['grupe']) && empty($_SESSION['aktyvus_grupe'])) {
    header('Location: /moduliai.php');
    exit;
}

$filtro_grupe = $_GET['grupe'] ?? ($_SESSION['aktyvus_grupe'] ?? 'MT');

if (isset($_GET['grupe'])) {
    $stmt_mod = $pdo->prepare("SELECT id, pavadinimas FROM gaminiu_rusys WHERE pavadinimas = ? LIMIT 1");
    $stmt_mod->execute([$_GET['grupe']]);
    $mod_info = $stmt_mod->fetch(PDO::FETCH_ASSOC);
    if ($mod_info) {
        $_SESSION['aktyvus_modulis'] = (int)$mod_info['id'];
        $_SESSION['aktyvus_modulis_pav'] = $mod_info['pavadinimas'];
        $_SESSION['aktyvus_grupe'] = $mod_info['pavadinimas'];
    }
}
$page_title = $filtro_grupe . ' Kokybės rodikliai';
$active_tab = $_GET['tab'] ?? '30d';

$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";
$ACTIVE_DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' AND LOWER(COALESCE(fb.isvada,'')) = 'neatitinka')";

// ==================== TAB 1: 30 dienų duomenys ====================
$where_sql_30d = "WHERE gr.pavadinimas = " . $pdo->quote($filtro_grupe) . " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'";

$patikrinti = (int)$pdo->query("
  SELECT COUNT(DISTINCT fb.gaminio_id)
  FROM funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
  $where_sql_30d
")->fetchColumn();

$viso_defektu = (int)$pdo->query("
  SELECT COUNT(*)
  FROM funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
  $where_sql_30d AND $DEFECT_COND
")->fetchColumn();

$viso_punktu = (int)$pdo->query("
  SELECT COUNT(*)
  FROM funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
  $where_sql_30d
")->fetchColumn();

$vid_proc = ($viso_punktu > 0) ? round($viso_defektu / $viso_punktu * 100, 1) : 0.0;

$sql_aktyvus = "
  SELECT u.uzsakymo_numeris AS uzsakymo_nr, g.gaminio_numeris, gt.gaminio_tipas AS gaminio_tipas,
    fb.eil_nr AS punkto_nr, fb.reikalavimas, fb.defektas AS defekto_aprasymas
  FROM funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
  WHERE gr.pavadinimas = " . $pdo->quote($filtro_grupe) . " AND $ACTIVE_DEFECT_COND AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'
  ORDER BY u.uzsakymo_numeris DESC, g.gaminio_numeris, fb.eil_nr LIMIT 50
";
$aktyvus_defektai = $pdo->query($sql_aktyvus)->fetchAll(PDO::FETCH_ASSOC);
$aktyvus_count = count($aktyvus_defektai);

$top_klaidos = $pdo->query("
  SELECT MIN(fb.eil_nr) as eil_nr, fb.reikalavimas, COUNT(*) AS kiekis
  FROM funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
  $where_sql_30d AND $DEFECT_COND AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
  GROUP BY fb.reikalavimas ORDER BY kiekis DESC, eil_nr ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
$max_kiekis = !empty($top_klaidos) ? (int)$top_klaidos[0]['kiekis'] : 1;

$weeks = $pdo->query("
  SELECT TO_CHAR(u.sukurtas::timestamp, 'IYYYIW') AS yw,
    COUNT(DISTINCT fb.gaminio_id) AS patikrinta,
    SUM(CASE WHEN $DEFECT_COND THEN 1 ELSE 0 END) AS klaidu
  FROM funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
  $where_sql_30d
  GROUP BY TO_CHAR(u.sukurtas::timestamp, 'IYYYIW')
  ORDER BY TO_CHAR(u.sukurtas::timestamp, 'IYYYIW')
")->fetchAll(PDO::FETCH_ASSOC);

$wLabels=[]; $wGaminiai=[]; $wKlaidos=[];
foreach ($weeks as $w) {
  $yw=(string)$w['yw']; $wk=substr($yw,-2);
  $wLabels[]="S$wk"; $wGaminiai[]=(int)$w['patikrinta']; $wKlaidos[]=(int)$w['klaidu'];
}

// ==================== TAB 2: Ketvirčių palyginimas ====================
$ketvirciu_sarasas = $pdo->query("
    SELECT DISTINCT
        EXTRACT(YEAR FROM u.sukurtas::timestamp)::int AS metai,
        EXTRACT(QUARTER FROM u.sukurtas::timestamp)::int AS ketvirtis
    FROM uzsakymai u
    JOIN gaminiai g ON g.uzsakymo_id = u.id
    JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
    JOIN funkciniai_bandymai fb ON fb.gaminio_id = g.id
    WHERE u.sukurtas IS NOT NULL AND gr.pavadinimas = " . $pdo->quote($filtro_grupe) . "
    ORDER BY metai DESC, ketvirtis DESC
")->fetchAll(PDO::FETCH_ASSOC);

function gautiKetvircioStatistika_idx($pdo, $metai, $ketvirtis, $DEFECT_COND, $grupe = 'MT') {
    $metai = (int)$metai;
    $ketvirtis = (int)$ketvirtis;
    $nuo_men = ($ketvirtis - 1) * 3 + 1;
    $iki_men = $ketvirtis * 3;
    $nuo = "$metai-" . str_pad($nuo_men, 2, '0', STR_PAD_LEFT) . "-01";
    $iki = "$metai-" . str_pad($iki_men, 2, '0', STR_PAD_LEFT) . "-" . cal_days_in_month(CAL_GREGORIAN, $iki_men, $metai);
    $grupe_q = $pdo->quote($grupe);
    $where = "WHERE u.sukurtas::date BETWEEN '$nuo' AND '$iki'";
    $grupe_join = "JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id";
    $grupe_filter = "AND gr.pavadinimas = $grupe_q";
    $r = [];
    $r['periodas'] = "$metai Q$ketvirtis";
    $r['uzsakymai'] = (int)$pdo->query("SELECT COUNT(DISTINCT u.id) FROM uzsakymai u JOIN gaminiai g ON g.uzsakymo_id = u.id $grupe_join JOIN funkciniai_bandymai fb ON fb.gaminio_id = g.id $where $grupe_filter")->fetchColumn();
    $r['gaminiai'] = (int)$pdo->query("SELECT COUNT(DISTINCT g.id) FROM gaminiai g JOIN uzsakymai u ON u.id = g.uzsakymo_id $grupe_join JOIN funkciniai_bandymai fb ON fb.gaminio_id = g.id $where $grupe_filter")->fetchColumn();
    $r['bandymai'] = (int)$pdo->query("SELECT COUNT(*) FROM funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $grupe_join $where $grupe_filter")->fetchColumn();
    $r['defektai'] = (int)$pdo->query("SELECT COUNT(*) FROM funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $grupe_join $where $grupe_filter AND $DEFECT_COND")->fetchColumn();
    $r['defektu_proc'] = ($r['bandymai'] > 0) ? round($r['defektai'] / $r['bandymai'] * 100, 2) : 0;
    $r['defektu_per_gamini'] = ($r['gaminiai'] > 0) ? round($r['defektai'] / $r['gaminiai'], 2) : 0;
    $r['top_darbuotojai'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas, COUNT(*) AS bandymu,
            COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai
        FROM funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $grupe_join
        $where $grupe_filter AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko ORDER BY be_defektu DESC LIMIT 11
    ")->fetchAll(PDO::FETCH_ASSOC);
    $r['top_klydusieji'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai, COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $grupe_join
        $where $grupe_filter AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC, defektu_proc DESC LIMIT 11
    ")->fetchAll(PDO::FETCH_ASSOC);
    $r['problemines_operacijos'] = $pdo->query("
        SELECT fb.reikalavimas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai, COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id $grupe_join
        $where $grupe_filter AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
        GROUP BY fb.reikalavimas HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC LIMIT 11
    ")->fetchAll(PDO::FETCH_ASSOC);
    return $r;
}

$kp_q1_metai = $_GET['kp_q1_metai'] ?? '';
$kp_q1_ketv  = $_GET['kp_q1_ketvirtis'] ?? '';
$kp_q2_metai = $_GET['kp_q2_metai'] ?? '';
$kp_q2_ketv  = $_GET['kp_q2_ketvirtis'] ?? '';

$kp_q1 = $kp_q2 = null;
$kp_rodyti = false;

if ($kp_q1_metai !== '' && $kp_q1_ketv !== '' && $kp_q2_metai !== '' && $kp_q2_ketv !== '') {
    $kp_q1 = gautiKetvircioStatistika_idx($pdo, $kp_q1_metai, $kp_q1_ketv, $DEFECT_COND, $filtro_grupe);
    $kp_q2 = gautiKetvircioStatistika_idx($pdo, $kp_q2_metai, $kp_q2_ketv, $DEFECT_COND, $filtro_grupe);
    $kp_rodyti = true;
} elseif (count($ketvirciu_sarasas) >= 2) {
    $kp_q2_metai = $ketvirciu_sarasas[0]['metai'];
    $kp_q2_ketv = $ketvirciu_sarasas[0]['ketvirtis'];
    $kp_q1_metai = $ketvirciu_sarasas[1]['metai'];
    $kp_q1_ketv = $ketvirciu_sarasas[1]['ketvirtis'];
    $kp_q2 = gautiKetvircioStatistika_idx($pdo, $kp_q2_metai, $kp_q2_ketv, $DEFECT_COND, $filtro_grupe);
    $kp_q1 = gautiKetvircioStatistika_idx($pdo, $kp_q1_metai, $kp_q1_ketv, $DEFECT_COND, $filtro_grupe);
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

// ==================== TAB 2 mėnesinė darbuotojų statistika ====================
$menesiu_pav = [1=>'Sausis',2=>'Vasaris',3=>'Kovas',4=>'Balandis',5=>'Gegužė',6=>'Birželis',7=>'Liepa',8=>'Rugpjūtis',9=>'Rugsėjis',10=>'Spalis',11=>'Lapkritis',12=>'Gruodis'];
$men_metai = $_GET['men_metai'] ?? date('Y');
$men_menuo = $_GET['men_menuo'] ?? date('n');
$men_metai = (int)$men_metai;
$men_menuo = (int)$men_menuo;

$men_pradzia = sprintf('%04d-%02d-01', $men_metai, $men_menuo);
$men_pabaiga = date('Y-m-t', strtotime($men_pradzia));
$men_where = "WHERE u.sukurtas IS NOT NULL AND u.sukurtas <> '' AND u.sukurtas::timestamp::date BETWEEN '$men_pradzia' AND '$men_pabaiga'";
$men_grupe_q = $pdo->quote($filtro_grupe);

$men_top_darbuotojai = $pdo->query("
    SELECT fb.darba_atliko AS vardas, COUNT(*) AS bandymu,
        COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
        COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai
    FROM funkciniai_bandymai fb
    JOIN gaminiai g ON g.id = fb.gaminio_id
    JOIN uzsakymai u ON u.id = g.uzsakymo_id
    JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
    $men_where AND gr.pavadinimas = $men_grupe_q AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
    GROUP BY fb.darba_atliko
    ORDER BY be_defektu DESC
    LIMIT 11
")->fetchAll(PDO::FETCH_ASSOC);

$men_top_klydusieji = $pdo->query("
    SELECT fb.darba_atliko AS vardas,
        COUNT(*) AS bandymu,
        COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
        COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai,
        ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
    FROM funkciniai_bandymai fb
    JOIN gaminiai g ON g.id = fb.gaminio_id
    JOIN uzsakymai u ON u.id = g.uzsakymo_id
    JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
    $men_where AND gr.pavadinimas = $men_grupe_q AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
    GROUP BY fb.darba_atliko
    HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
    ORDER BY defektai DESC, defektu_proc DESC
    LIMIT 11
")->fetchAll(PDO::FETCH_ASSOC);

$men_turimi_metai = $pdo->query("
    SELECT DISTINCT EXTRACT(YEAR FROM u.sukurtas::timestamp)::int AS metai
    FROM uzsakymai u JOIN gaminiai g ON g.uzsakymo_id = u.id
    JOIN funkciniai_bandymai fb ON fb.gaminio_id = g.id
    WHERE u.sukurtas IS NOT NULL AND u.sukurtas <> ''
    ORDER BY metai DESC
")->fetchAll(PDO::FETCH_COLUMN);
if (empty($men_turimi_metai)) $men_turimi_metai = [(int)date('Y')];

// ==================== TAB 3: Išplėstinė statistika ====================
$ist_uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$ist_periodas         = $_GET['periodas'] ?? 'visi';
$ist_menuo            = $_GET['menuo'] ?? '';
$ist_nuo              = $_GET['nuo'] ?? '';
$ist_iki              = $_GET['iki'] ?? '';

$ist_uzsakymai = $pdo->query("
    SELECT DISTINCT u.uzsakymo_numeris
    FROM uzsakymai u
    JOIN gaminiai g ON g.uzsakymo_id = u.id
    JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
    WHERE gr.pavadinimas = " . $pdo->quote($filtro_grupe) . "
    ORDER BY u.uzsakymo_numeris DESC
")->fetchAll(PDO::FETCH_COLUMN);

$ist_patikrinti = 0; $ist_klaidos = 0;
$ist_top_defektai = []; $ist_defektu_gaminiai = []; $ist_aktyvus_defektai = [];

$ist_where_uzsakymas = ($ist_uzsakymo_numeris !== '') ? "u.uzsakymo_numeris = ?" : "1=1";
$ist_params = [];
if ($ist_uzsakymo_numeris !== '') $ist_params[] = $ist_uzsakymo_numeris;

$ist_where_laikotarpis = '';
if ($ist_menuo !== '') {
    $ist_where_laikotarpis = " AND TO_CHAR(u.sukurtas::timestamp, 'YYYY-MM') = ?";
    $ist_params[] = $ist_menuo;
} elseif ($ist_nuo !== '' && $ist_iki !== '') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) BETWEEN ? AND ?";
    $ist_params[] = $ist_nuo;
    $ist_params[] = $ist_iki;
} elseif ($ist_periodas === '1m') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 month'";
} elseif ($ist_periodas === '6m') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '6 month'";
} elseif ($ist_periodas === '1y') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 year'";
}

$ist_rodyti = !($ist_uzsakymo_numeris === '' && $ist_periodas === 'visi' && $ist_menuo === '' && ($ist_nuo === '' || $ist_iki === ''));
$ist_where_sql = "WHERE $ist_where_uzsakymas $ist_where_laikotarpis";

if ($ist_rodyti) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT fb.gaminio_id)
        FROM funkciniai_bandymai fb JOIN gaminiai g ON fb.gaminio_id = g.id
        JOIN uzsakymai u ON g.uzsakymo_id = u.id
        JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
        $ist_where_sql AND gr.pavadinimas = " . $pdo->quote($filtro_grupe) . "
    ");
    $stmt->execute($ist_params);
    $ist_patikrinti = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.uzsakymo_numeris, fb.reikalavimas, fb.defektas, fb.isvada
        FROM funkciniai_bandymai fb JOIN gaminiai g ON fb.gaminio_id = g.id
        JOIN uzsakymai u ON g.uzsakymo_id = u.id
        JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
        $ist_where_sql AND gr.pavadinimas = " . $pdo->quote($filtro_grupe) . " AND fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> ''
        ORDER BY u.uzsakymo_numeris
    ");
    $stmt->execute($ist_params);
    $ist_defektu_gaminiai = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ist_defektu_gaminiai as $r) {
        if (!empty(trim((string)$r['defektas']))) $ist_klaidos++;
    }

    $stmt = $pdo->prepare("
        SELECT MIN(fb.eil_nr) as eil_nr, fb.reikalavimas, COUNT(*) AS kiekis
        FROM funkciniai_bandymai fb JOIN gaminiai g ON fb.gaminio_id = g.id
        JOIN uzsakymai u ON g.uzsakymo_id = u.id
        JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
        $ist_where_sql AND gr.pavadinimas = " . $pdo->quote($filtro_grupe) . " AND fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> ''
        AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
        GROUP BY fb.reikalavimas ORDER BY kiekis DESC, eil_nr ASC LIMIT 5
    ");
    $stmt->execute($ist_params);
    $ist_top_defektai = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT u.uzsakymo_numeris, f.reikalavimas, f.defektas
        FROM funkciniai_bandymai f JOIN gaminiai g ON f.gaminio_id = g.id
        JOIN uzsakymai u ON g.uzsakymo_id = u.id
        JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
        $ist_where_sql AND gr.pavadinimas = " . $pdo->quote($filtro_grupe) . " AND LOWER(f.isvada) IN ('neatitinka','nepadaryta')
        AND f.defektas IS NOT NULL AND TRIM(f.defektas) <> ''
        ORDER BY u.uzsakymo_numeris
    ");
    $stmt->execute($ist_params);
    $ist_aktyvus_defektai = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="kr-tabs" data-testid="kr-tabs">
  <button class="kr-tab <?= $active_tab === '30d' ? 'kr-tab-active' : '' ?>" data-tab="30d" data-testid="tab-30d" onclick="switchTab('30d')">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    30 dienu rodikliai
  </button>
  <button class="kr-tab <?= $active_tab === 'ketv' ? 'kr-tab-active' : '' ?>" data-tab="ketv" data-testid="tab-ketv" onclick="switchTab('ketv')">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Ketvirciu palyginimas
  </button>
  <button class="kr-tab <?= $active_tab === 'stat' ? 'kr-tab-active' : '' ?>" data-tab="stat" data-testid="tab-stat" onclick="switchTab('stat')">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
    Isplestine statistika
  </button>
</div>

<!-- ==================== TAB 1: 30 dienų rodikliai ==================== -->
<div id="tab-content-30d" class="kr-tab-content" style="<?= $active_tab !== '30d' ? 'display:none;' : '' ?>" data-testid="tab-content-30d">

<div class="dashboard-top-bar" style="display:flex;justify-content:space-between;align-items:center;">
  <div class="dashboard-subtitle" data-testid="text-dashboard-period">Paskutines 30 dienu (1 menuo)</div>
  <a href="/kokybe_30d_pdf.php?grupe=<?= urlencode($filtro_grupe) ?>" target="_blank" class="btn btn-secondary" data-testid="button-30d-pdf" style="display:inline-flex;align-items:center;gap:5px;font-size:13px;padding:5px 14px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Atsisiusti PDF
  </a>
</div>

<div class="kpi-grid" data-testid="kpi-container">
  <div class="kpi-card kpi-green" data-testid="kpi-patikrinta">
    <div class="kpi-value"><?= $patikrinti ?></div>
    <div class="kpi-label">Patikrinta gaminiu</div>
  </div>
  <div class="kpi-card kpi-orange" data-testid="kpi-neatitikimai">
    <div class="kpi-value"><?= $viso_defektu ?></div>
    <div class="kpi-label">Viso neatitikimu</div>
  </div>
  <div class="kpi-card kpi-yellow" data-testid="kpi-procentas">
    <div class="kpi-value"><?= $vid_proc ?>%</div>
    <div class="kpi-label">Neatitikimu %</div>
  </div>
  <div class="kpi-card kpi-red" data-testid="kpi-aktyvus">
    <div class="kpi-value"><?= $aktyvus_count ?></div>
    <div class="kpi-label">Aktyvus nepataisyti</div>
  </div>
</div>

<div class="dashboard-panels" data-testid="panels-container">
  <div class="dashboard-panel" data-testid="panel-chart">
    <div class="dashboard-panel-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
      Menesine Suvestine
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
        Defektu nerasta - puikus darbas!
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

<?php if (!empty($aktyvus_defektai)): ?>
<div class="dashboard-panel aktyvus-panel-wrapper" data-testid="panel-aktyvus-defektai">
  <div class="dashboard-panel-title">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Aktyvus Nepataisyti Defektai
    <span class="aktyvus-badge"><?= $aktyvus_count ?></span>
  </div>
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
</div>
<?php endif; ?>

</div><!-- /tab-content-30d -->

<!-- ==================== TAB 2: Ketvirčių palyginimas ==================== -->
<div id="tab-content-ketv" class="kr-tab-content" style="<?= $active_tab !== 'ketv' ? 'display:none;' : '' ?>" data-testid="tab-content-ketv">

<?php
$kp_metai_sarasas = array_values(array_unique(array_column($ketvirciu_sarasas, 'metai')));
rsort($kp_metai_sarasas);
$kp_ketv_sarasas = [];
foreach ($ketvirciu_sarasas as $ks) {
    $kp_ketv_sarasas[$ks['metai'] . '-' . $ks['ketvirtis']] = true;
}
?>
<div class="filter-bar kp-filter-bar" data-testid="filter-bar-quarters" style="margin-bottom:16px;">
    <form method="GET" class="kp-filter-form">
        <input type="hidden" name="tab" value="ketv">
        <input type="hidden" name="grupe" value="<?= h($filtro_grupe) ?>">
        <div class="form-group">
            <label class="form-label">Senesnis ketvirtis</label>
            <div class="kp-select-pair">
                <select name="kp_q1_metai" class="form-control" data-testid="select-kp-q1-year" style="width:auto;">
                    <option value="">Metai</option>
                    <?php foreach($kp_metai_sarasas as $m): ?>
                    <option value="<?= $m ?>" <?= ($kp_q1_metai==$m)?'selected':'' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="kp_q1_ketvirtis" class="form-control" data-testid="select-kp-q1-quarter" style="width:auto;">
                    <option value="">Q</option>
                    <?php for($i=1;$i<=4;$i++): ?>
                    <option value="<?= $i ?>" <?= ($kp_q1_ketv==$i)?'selected':'' ?>>Q<?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-group kp-vs-label">
            <span style="font-size:20px;color:var(--text-secondary);">vs</span>
        </div>
        <div class="form-group">
            <label class="form-label">Naujesnis ketvirtis</label>
            <div class="kp-select-pair">
                <select name="kp_q2_metai" class="form-control" data-testid="select-kp-q2-year" style="width:auto;">
                    <option value="">Metai</option>
                    <?php foreach($kp_metai_sarasas as $m): ?>
                    <option value="<?= $m ?>" <?= ($kp_q2_metai==$m)?'selected':'' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="kp_q2_ketvirtis" class="form-control" data-testid="select-kp-q2-quarter" style="width:auto;">
                    <option value="">Q</option>
                    <?php for($i=1;$i<=4;$i++): ?>
                    <option value="<?= $i ?>" <?= ($kp_q2_ketv==$i)?'selected':'' ?>>Q<?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-group kp-actions">
            <button type="submit" class="btn btn-primary" data-testid="button-kp-compare">Palyginti</button>
            <?php if ($kp_rodyti): ?>
            <a href="/generuoti_ketvirciu_pdf.php?grupe=<?= urlencode($filtro_grupe) ?>&kp_q1_metai=<?= $kp_q1_metai ?>&kp_q1_ketvirtis=<?= $kp_q1_ketv ?>&kp_q2_metai=<?= $kp_q2_metai ?>&kp_q2_ketvirtis=<?= $kp_q2_ketv ?>&men_metai=<?= $men_metai ?>&men_menuo=<?= $men_menuo ?>" 
               target="_blank" class="btn btn-secondary" data-testid="button-kp-pdf" style="display:inline-flex;align-items:center;gap:5px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                PDF
            </a>
            <?php endif; ?>
        </div>
    </form>
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
  </div>

  <div class="kp-summary" data-testid="kp-summary">
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

  <div class="kp-monthly-bar" data-testid="filter-bar-monthly">
    <span style="font-weight:600;font-size:14px;color:var(--text-primary);">Darbuotojų mėnesinė statistika:</span>
    <form method="GET" class="kp-monthly-form">
      <input type="hidden" name="tab" value="ketv">
      <input type="hidden" name="grupe" value="<?= h($filtro_grupe) ?>">
      <input type="hidden" name="kp_q1_metai" value="<?= h($kp_q1_metai) ?>">
      <input type="hidden" name="kp_q1_ketvirtis" value="<?= h($kp_q1_ketv) ?>">
      <input type="hidden" name="kp_q2_metai" value="<?= h($kp_q2_metai) ?>">
      <input type="hidden" name="kp_q2_ketvirtis" value="<?= h($kp_q2_ketv) ?>">
      <select name="men_menuo" data-testid="select-men-month">
        <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m == $men_menuo ? 'selected' : '' ?>><?= $menesiu_pav[$m] ?></option>
        <?php endfor; ?>
      </select>
      <select name="men_metai" data-testid="select-men-year">
        <?php foreach ($men_turimi_metai as $m): ?>
        <option value="<?= $m ?>" <?= $m == $men_metai ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" style="padding:5px 14px;font-size:13px;" data-testid="button-men-filter">Rodyti</button>
    </form>
  </div>

  <div class="kp-two-col-grid">
    <div data-testid="kp-top-workers">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:var(--text-primary);">TOP 11 darbuotojai (daugiausiai punktu) — <?= $menesiu_pav[$men_menuo] ?> <?= $men_metai ?></div>
      <div class="table-wrapper">
        <table data-testid="table-kp-top-workers">
          <thead><tr><th>#</th><th>Darbuotojas</th><th style="text-align:center;">Bandymų</th><th style="text-align:center;">Be def.</th></tr></thead>
          <tbody>
            <?php $i = 1; foreach ($men_top_darbuotojai as $d): ?>
            <tr>
              <td style="font-weight:600;color:<?= $i<=3?'#f59e0b':'var(--text-secondary)' ?>;"><?= $i++ ?></td>
              <td style="font-weight:500;"><?= h($d['vardas']) ?></td>
              <td style="text-align:center;"><?= $d['bandymu'] ?></td>
              <td style="text-align:center;font-weight:600;color:#16a34a;"><?= $d['be_defektu'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($men_top_darbuotojai)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:12px;">Duomenų nerasta</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div data-testid="kp-top-errors">
      <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:#dc2626;">TOP 11 daugiausiai klydo — <?= $menesiu_pav[$men_menuo] ?> <?= $men_metai ?></div>
      <div class="table-wrapper">
        <table data-testid="table-kp-top-errors">
          <thead><tr><th>#</th><th>Darbuotojas</th><th style="text-align:center;">Bandymų</th><th style="text-align:center;">Be def.</th><th style="text-align:center;">Def.</th><th style="text-align:center;">%</th></tr></thead>
          <tbody>
            <?php $i = 1; foreach ($men_top_klydusieji as $d): ?>
            <tr>
              <td style="font-weight:600;color:#dc2626;"><?= $i++ ?></td>
              <td style="font-weight:500;"><?= h($d['vardas']) ?></td>
              <td style="text-align:center;"><?= $d['bandymu'] ?></td>
              <td style="text-align:center;font-weight:600;color:#16a34a;"><?= $d['be_defektu'] ?></td>
              <td style="text-align:center;font-weight:600;color:#dc2626;"><?= $d['defektai'] ?></td>
              <td style="text-align:center;"><?= $d['defektu_proc'] ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($men_top_klydusieji)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:12px;">Defektų nerasta</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="kp-two-col-grid">
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

<?php else: ?>
<div class="alert alert-info" data-testid="text-no-quarters">
  Nepakanka duomenu ketvirciu palyginimui. Reikia bent 2 ketvirciu su duomenimis.
</div>
<?php endif; ?>

</div><!-- /tab-content-ketv -->

<!-- ==================== TAB 3: Išplėstinė statistika ==================== -->
<div id="tab-content-stat" class="kr-tab-content" style="<?= $active_tab !== 'stat' ? 'display:none;' : '' ?>" data-testid="tab-content-stat">

<div class="filter-bar" data-testid="filter-bar-stat">
    <form method="GET" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%;">
        <input type="hidden" name="tab" value="stat">
        <input type="hidden" name="grupe" value="<?= h($filtro_grupe) ?>">
        <div class="form-group">
            <label class="form-label">Pasirinkti uzsakyma</label>
            <select name="uzsakymo_numeris" class="form-control" data-testid="select-order-stat">
                <option value="">-- Pasirinkti --</option>
                <?php foreach ($ist_uzsakymai as $nr): ?>
                <option value="<?= h($nr) ?>" <?= ($ist_uzsakymo_numeris == $nr) ? 'selected' : '' ?>><?= h($nr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Laikotarpis</label>
            <select name="periodas" class="form-control" data-testid="select-period-stat">
                <option value="visi" <?= ($ist_periodas=='visi')?'selected':'' ?>>Visi</option>
                <option value="1m"  <?= ($ist_periodas=='1m') ?'selected':'' ?>>Paskutinis menuo</option>
                <option value="6m"  <?= ($ist_periodas=='6m') ?'selected':'' ?>>Paskutiniai 6 menesiai</option>
                <option value="1y"  <?= ($ist_periodas=='1y') ?'selected':'' ?>>Paskutiniai 12 menesiu</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Menuo</label>
            <input type="month" name="menuo" value="<?= h($ist_menuo) ?>" class="form-control" data-testid="input-month-stat">
        </div>

        <div class="form-group">
            <label class="form-label">Nuo</label>
            <input type="date" name="nuo" class="form-control" value="<?= h($ist_nuo) ?>" data-testid="input-date-from-stat">
        </div>

        <div class="form-group">
            <label class="form-label">Iki</label>
            <input type="date" name="iki" class="form-control" value="<?= h($ist_iki) ?>" data-testid="input-date-to-stat">
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary" data-testid="button-filter-stat">Rodyti</button>
        </div>

        <?php if ($ist_rodyti): ?>
        <div class="form-group">
            <a href="/kokybe_isplestine_pdf.php?<?= http_build_query(array_filter(['grupe' => $filtro_grupe, 'uzsakymo_numeris' => $ist_uzsakymo_numeris, 'periodas' => $ist_periodas, 'menuo' => $ist_menuo, 'nuo' => $ist_nuo, 'iki' => $ist_iki])) ?>" 
               target="_blank" class="btn btn-secondary" data-testid="button-stat-pdf" style="display:inline-flex;align-items:center;gap:5px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                PDF
            </a>
        </div>
        <?php endif; ?>

        <?php if ($ist_uzsakymo_numeris !== '' || $ist_periodas !== 'visi' || $ist_menuo !== '' || $ist_nuo !== '' || $ist_iki !== ''): ?>
        <div class="form-group">
            <a href="/?tab=stat&grupe=<?= urlencode($filtro_grupe) ?>" class="btn btn-secondary" data-testid="button-clear-filter-stat">Valyti filtrus</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if (!$ist_rodyti): ?>
<div class="alert alert-info" data-testid="text-filter-hint-stat">
    Pasirinkite bent viena filtra (uzsakyma ar laikotarpi), kad butu rodomi duomenys.
</div>
<?php else: ?>

<div class="stats-summary">
    <div class="stat-card" data-testid="stat-patikrinti">
        <div class="stat-header">
            <span class="stat-label">Patikrinti uzsakymai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int)$ist_patikrinti ?></div>
        <div class="stat-change">Gaminiu su funkciniais bandymais</div>
    </div>
    <div class="stat-card" data-testid="stat-klaidos">
        <div class="stat-header">
            <span class="stat-label">Klaidu skaicius</span>
            <div class="stat-icon red">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int)$ist_klaidos ?></div>
        <div class="stat-change">Rasti defektai</div>
    </div>
    <div class="stat-card stat-card-wide" data-testid="stat-top-defektai">
        <div class="stat-header">
            <span class="stat-label">5 dazniausi klaidos punktai</span>
            <div class="stat-icon orange">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
        </div>
        <?php if (empty($ist_top_defektai)): ?>
        <div class="stat-change" style="margin-top: 8px;">Defektu nerasta</div>
        <?php else: ?>
        <ol class="top-defektai-list">
            <?php foreach ($ist_top_defektai as $d): ?>
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

<div class="card" style="margin-bottom: 24px;" data-testid="chart-container-stat">
    <div class="card-header">
        <span class="card-title">Per savaite: patikrinta gaminiu ir rasta klaidu</span>
    </div>
    <div class="card-body">
        <canvas id="grafikas" height="90" data-testid="chart-weekly-stat"></canvas>
    </div>
</div>

<?php if (!empty($ist_defektu_gaminiai)): ?>
<div class="card" style="margin-bottom: 24px;" data-testid="table-defektai-container">
    <div class="card-header">
        <span class="card-title">Uzsakymai ir defektai</span>
        <span class="badge badge-primary"><?= count($ist_defektu_gaminiai) ?> irasu</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table data-testid="table-defektai-stat">
                <thead>
                    <tr><th>Uzsakymo numeris</th><th>Reikalavimas</th><th>Defektas</th><th>Busena</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($ist_defektu_gaminiai as $eil):
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
    <div class="card-header"><span class="card-title">Uzsakymai ir defektai</span></div>
    <div class="card-body">
        <div class="empty-state"><p>Pagal pasirinktus filtrus uzsakymu nerasta.</p></div>
    </div>
</div>
<?php endif; ?>

<div class="card aktyvus-defektai-card" style="margin-bottom: 24px;" data-testid="table-aktyvus-container-stat">
    <div class="card-header aktyvus-defektai-header">
        <span class="card-title"><span class="aktyvus-dot"></span> Aktyvus nepataisyti defektai</span>
        <span class="badge badge-danger"><?= count($ist_aktyvus_defektai) ?></span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table data-testid="table-aktyvus-defektai-stat">
                <thead class="aktyvus-thead">
                    <tr><th style="width: 40px;"></th><th>Uzsakymo numeris</th><th>Reikalavimas</th><th>Defekto aprasymas</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($ist_aktyvus_defektai)): ?>
                    <?php foreach ($ist_aktyvus_defektai as $row): ?>
                    <tr>
                        <td style="text-align: center;"><span class="aktyvus-dot"></span></td>
                        <td><?= h($row['uzsakymo_numeris']) ?></td>
                        <td><?= h($row['reikalavimas']) ?></td>
                        <td><?= h($row['defektas']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 24px; color: var(--text-secondary);">Nera aktyviu nepataisytu defektu</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

</div><!-- /tab-content-stat -->

<div class="dashboard-footer-info" data-testid="text-dashboard-footer">
  Duomenys atnaujinami automatiskai kas 5 minutes
</div>

<script>
function switchTab(tabId) {
  document.querySelectorAll('.kr-tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.kr-tab').forEach(el => el.classList.remove('kr-tab-active'));
  document.getElementById('tab-content-' + tabId).style.display = '';
  document.querySelector('[data-tab="' + tabId + '"]').classList.add('kr-tab-active');
  
  var url = new URL(window.location);
  url.searchParams.set('tab', tabId);
  window.history.replaceState({}, '', url);

  if (tabId === '30d' && !window._weeklyChartInit) initWeeklyChart();
  if (tabId === 'ketv' && !window._compChartInit) initCompChart();
  if (tabId === 'stat' && !window._statChartInit) initStatChart();
}

setTimeout(() => location.reload(), 300000);

var _weeklyChartInit = false;
function initWeeklyChart() {
  var ctx = document.getElementById('weeklyChart');
  if (!ctx) return;
  _weeklyChartInit = true;
  new Chart(ctx.getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($wLabels) ?>,
      datasets: [
        { label: 'Gaminiai', data: <?= json_encode($wGaminiai) ?>, backgroundColor: 'rgba(22, 163, 74, 0.8)', borderColor: 'rgba(22, 163, 74, 1)', borderWidth: 1, borderRadius: 4 },
        { label: 'Neatitikimai', data: <?= json_encode($wKlaidos) ?>, backgroundColor: 'rgba(220, 38, 38, 0.8)', borderColor: 'rgba(220, 38, 38, 1)', borderWidth: 1, borderRadius: 4 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true, font: { size: 12, family: "'Inter', sans-serif" } } } },
      scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
    }
  });
}

var _compChartInit = false;
function initCompChart() {
  <?php if ($kp_rodyti): ?>
  var ctx = document.getElementById('compChart');
  if (!ctx) return;
  _compChartInit = true;
  new Chart(ctx.getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Uzsakymai', 'Gaminiai', 'Bandymu punktai', 'Defektai'],
      datasets: [
        { label: <?= json_encode($kp_q1['periodas']) ?>, data: [<?= $kp_q1['uzsakymai'] ?>, <?= $kp_q1['gaminiai'] ?>, <?= $kp_q1['bandymai'] ?>, <?= $kp_q1['defektai'] ?>], backgroundColor: 'rgba(37, 99, 235, 0.7)', borderColor: 'rgba(37, 99, 235, 1)', borderWidth: 1, borderRadius: 4 },
        { label: <?= json_encode($kp_q2['periodas']) ?>, data: [<?= $kp_q2['uzsakymai'] ?>, <?= $kp_q2['gaminiai'] ?>, <?= $kp_q2['bandymai'] ?>, <?= $kp_q2['defektai'] ?>], backgroundColor: 'rgba(16, 185, 129, 0.7)', borderColor: 'rgba(16, 185, 129, 1)', borderWidth: 1, borderRadius: 4 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true, font: { size: 12, family: "'Inter', sans-serif" } } } },
      scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
    }
  });
  <?php endif; ?>
}

var _statChartInit = false;
function initStatChart() {
  <?php if ($ist_rodyti): ?>
  var ctx = document.getElementById('grafikas');
  if (!ctx || _statChartInit) return;
  _statChartInit = true;
  (async function() {
    var params = new URLSearchParams({
      uzsakymo_numeris: <?= json_encode($ist_uzsakymo_numeris) ?>,
      periodas: <?= json_encode($ist_periodas) ?>,
      menuo: <?= json_encode($ist_menuo) ?>,
      nuo: <?= json_encode($ist_nuo) ?>,
      iki: <?= json_encode($ist_iki) ?>,
      grupe: <?= json_encode($filtro_grupe) ?>
    });
    try {
      var res = await fetch('/grafiko_duomenys.php?' + params.toString());
      var data = await res.json();
      if (!data || data.length === 0) return;
      new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.map(d => 'Savaite ' + d.savaite),
          datasets: [
            { label: 'Patikrinta gaminiu', data: data.map(d => d.patikrinta_gaminiu), backgroundColor: 'rgba(37, 99, 235, 0.7)', borderColor: 'rgba(37, 99, 235, 1)', borderWidth: 1 },
            { label: 'Rasta klaidu', data: data.map(d => d.klaidu), backgroundColor: 'rgba(220, 38, 38, 0.7)', borderColor: 'rgba(220, 38, 38, 1)', borderWidth: 1 }
          ]
        },
        options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true, title: { display: true, text: 'vnt.' } } } }
      });
    } catch (e) { console.log('Chart data not available'); }
  })();
  <?php endif; ?>
}

var activeTab = <?= json_encode($active_tab) ?>;
if (activeTab === '30d') initWeeklyChart();
else if (activeTab === 'ketv') initCompChart();
else if (activeTab === 'stat') initStatChart();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
