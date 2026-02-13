<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
requireLogin();

$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

$q1_metai = (int)($_GET['kp_q1_metai'] ?? 0);
$q1_ketv  = (int)($_GET['kp_q1_ketvirtis'] ?? 0);
$q2_metai = (int)($_GET['kp_q2_metai'] ?? 0);
$q2_ketv  = (int)($_GET['kp_q2_ketvirtis'] ?? 0);

if ($q1_metai <= 0 || $q1_ketv <= 0 || $q2_metai <= 0 || $q2_ketv <= 0) {
    die('Nenurodyti ketvirčiai');
}

function gautiKetvircioStat($pdo, $metai, $ketvirtis, $DEFECT_COND) {
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
        GROUP BY fb.darba_atliko ORDER BY be_defektu DESC LIMIT 11
    ")->fetchAll(PDO::FETCH_ASSOC);
    $r['top_klydusieji'] = $pdo->query("
        SELECT fb.darba_atliko AS vardas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai, COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
        GROUP BY fb.darba_atliko HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC, defektu_proc DESC LIMIT 11
    ")->fetchAll(PDO::FETCH_ASSOC);
    $r['problemines_operacijos'] = $pdo->query("
        SELECT fb.reikalavimas,
            COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai, COUNT(*) AS bandymu,
            ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
        FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
        $where AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
        GROUP BY fb.reikalavimas HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
        ORDER BY defektai DESC LIMIT 11
    ")->fetchAll(PDO::FETCH_ASSOC);
    return $r;
}

function pdf_pokytis($senas, $naujas) {
    if ($senas == 0 && $naujas == 0) return ['reiksme' => 0, 'tekstas' => '-', 'spalva' => '#666'];
    if ($senas == 0) return ['reiksme' => 100, 'tekstas' => '+100%', 'spalva' => '#2563eb'];
    $p = round(($naujas - $senas) / $senas * 100, 1);
    if ($p > 0) return ['reiksme' => $p, 'tekstas' => '+' . $p . '%', 'spalva' => '#2563eb'];
    if ($p < 0) return ['reiksme' => abs($p), 'tekstas' => '-' . abs($p) . '%', 'spalva' => '#16a34a'];
    return ['reiksme' => 0, 'tekstas' => '0%', 'spalva' => '#666'];
}

function pdf_defPokytis($senas, $naujas) {
    $p = pdf_pokytis($senas, $naujas);
    if ($p['spalva'] === '#2563eb') $p['spalva'] = '#dc2626';
    elseif ($p['spalva'] === '#16a34a') $p['spalva'] = '#16a34a';
    return $p;
}

$q1 = gautiKetvircioStat($pdo, $q1_metai, $q1_ketv, $DEFECT_COND);
$q2 = gautiKetvircioStat($pdo, $q2_metai, $q2_ketv, $DEFECT_COND);

$p_uzs = pdf_pokytis($q1['uzsakymai'], $q2['uzsakymai']);
$p_gam = pdf_pokytis($q1['gaminiai'], $q2['gaminiai']);
$p_ban = pdf_pokytis($q1['bandymai'], $q2['bandymai']);
$p_def = pdf_defPokytis($q1['defektai'], $q2['defektai']);
$p_proc = pdf_defPokytis($q1['defektu_proc'], $q2['defektu_proc']);
$p_pg = pdf_defPokytis($q1['defektu_per_gamini'], $q2['defektu_per_gamini']);

$data = date('Y-m-d H:i');
$vartotojas = currentUser();
$vart_vardas = htmlspecialchars(($vartotojas['vardas'] ?? '') . ' ' . ($vartotojas['pavarde'] ?? ''));

$apibendrinimas = '';
if ($q2['defektu_proc'] < $q1['defektu_proc']) {
    $apibendrinimas = "defektu procentas <b style='color:#16a34a;'>sumazejo nuo {$q1['defektu_proc']}% iki {$q2['defektu_proc']}%</b>";
} elseif ($q2['defektu_proc'] > $q1['defektu_proc']) {
    $apibendrinimas = "defektu procentas <b style='color:#dc2626;'>padidejo nuo {$q1['defektu_proc']}% iki {$q2['defektu_proc']}%</b>";
} else {
    $apibendrinimas = "defektu procentas <b>nepasikete ({$q1['defektu_proc']}%)</b>";
}

$menesiu_pav = [1=>'Sausis',2=>'Vasaris',3=>'Kovas',4=>'Balandis',5=>'Gegužė',6=>'Birželis',7=>'Liepa',8=>'Rugpjūtis',9=>'Rugsėjis',10=>'Spalis',11=>'Lapkritis',12=>'Gruodis'];
$men_metai = (int)($_GET['men_metai'] ?? date('Y'));
$men_menuo = (int)($_GET['men_menuo'] ?? date('n'));
$men_pradzia = sprintf('%04d-%02d-01', $men_metai, $men_menuo);
$men_pabaiga = date('Y-m-t', strtotime($men_pradzia));
$men_where = "WHERE u.sukurtas IS NOT NULL AND u.sukurtas <> '' AND u.sukurtas::timestamp::date BETWEEN '$men_pradzia' AND '$men_pabaiga'";
$men_periodas = $menesiu_pav[$men_menuo] . ' ' . $men_metai;

$combined_workers = $pdo->query("
    SELECT fb.darba_atliko AS vardas, COUNT(*) AS bandymu,
        COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
        COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai
    FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
    $men_where AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
    GROUP BY fb.darba_atliko ORDER BY be_defektu DESC LIMIT 11
")->fetchAll(PDO::FETCH_ASSOC);

$combined_errors = $pdo->query("
    SELECT fb.darba_atliko AS vardas,
        COUNT(*) AS bandymu,
        COUNT(CASE WHEN NOT $DEFECT_COND THEN 1 END) AS be_defektu,
        COUNT(CASE WHEN $DEFECT_COND THEN 1 END) AS defektai,
        ROUND(COUNT(CASE WHEN $DEFECT_COND THEN 1 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS defektu_proc
    FROM mt_funkciniai_bandymai fb JOIN gaminiai g ON g.id = fb.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id
    $men_where AND fb.darba_atliko IS NOT NULL AND TRIM(fb.darba_atliko) <> ''
    GROUP BY fb.darba_atliko HAVING COUNT(CASE WHEN $DEFECT_COND THEN 1 END) > 0
    ORDER BY defektai DESC, defektu_proc DESC LIMIT 11
")->fetchAll(PDO::FETCH_ASSOC);

$html = '
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #333; }
    h1 { font-size: 18px; margin-bottom: 4px; color: #1e3a5f; }
    h2 { font-size: 14px; margin: 16px 0 8px; color: #1e3a5f; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; }
    h3 { font-size: 12px; margin: 12px 0 6px; color: #374151; }
    .meta { font-size: 10px; color: #6b7280; margin-bottom: 12px; }
    .summary { background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; border-bottom: 2px solid #d1d5db; }
    td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
    .tc { text-align: center; }
    .green { color: #16a34a; font-weight: 600; }
    .red { color: #dc2626; font-weight: 600; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    .badge-green { background: #dcfce7; color: #16a34a; }
    .badge-red { background: #fef2f2; color: #dc2626; }
    .two-col { width: 100%; }
    .two-col td { vertical-align: top; width: 50%; padding: 0 6px; }
</style>

<h1>Ketvirciu kokybes palyginimas</h1>
<div class="meta">
    ' . htmlspecialchars($q1['periodas']) . ' vs ' . htmlspecialchars($q2['periodas']) . ' | Sugeneruota: ' . $data . ' | ' . $vart_vardas . '
</div>

<div class="summary">
    <b>' . htmlspecialchars($q2['periodas']) . '</b> palyginti su <b>' . htmlspecialchars($q1['periodas']) . '</b>: ' . $apibendrinimas . '.
    Gaminiu: ' . $q1['gaminiai'] . ' &rarr; ' . $q2['gaminiai'] . '.
    Defektu: ' . $q1['defektai'] . ' &rarr; ' . $q2['defektai'] . '.
</div>

<h2>Pagrindiniai rodikliai</h2>
<table>
    <thead>
        <tr><th>Rodiklis</th><th class="tc">' . htmlspecialchars($q1['periodas']) . '</th><th class="tc">' . htmlspecialchars($q2['periodas']) . '</th><th class="tc">Pokytis</th></tr>
    </thead>
    <tbody>
        <tr><td>Uzsakymai</td><td class="tc">' . $q1['uzsakymai'] . '</td><td class="tc">' . $q2['uzsakymai'] . '</td><td class="tc" style="color:' . $p_uzs['spalva'] . ';">' . $p_uzs['tekstas'] . '</td></tr>
        <tr><td>Patikrinti gaminiai</td><td class="tc">' . $q1['gaminiai'] . '</td><td class="tc">' . $q2['gaminiai'] . '</td><td class="tc" style="color:' . $p_gam['spalva'] . ';">' . $p_gam['tekstas'] . '</td></tr>
        <tr><td>Bandymu punktai</td><td class="tc">' . $q1['bandymai'] . '</td><td class="tc">' . $q2['bandymai'] . '</td><td class="tc" style="color:' . $p_ban['spalva'] . ';">' . $p_ban['tekstas'] . '</td></tr>
        <tr><td>Rasti defektai</td><td class="tc">' . $q1['defektai'] . '</td><td class="tc">' . $q2['defektai'] . '</td><td class="tc" style="color:' . $p_def['spalva'] . ';">' . $p_def['tekstas'] . '</td></tr>
        <tr><td>Defektu procentas</td><td class="tc">' . $q1['defektu_proc'] . '%</td><td class="tc">' . $q2['defektu_proc'] . '%</td><td class="tc" style="color:' . $p_proc['spalva'] . ';">' . $p_proc['tekstas'] . '</td></tr>
        <tr><td>Defektai / gaminys</td><td class="tc">' . $q1['defektu_per_gamini'] . '</td><td class="tc">' . $q2['defektu_per_gamini'] . '</td><td class="tc" style="color:' . $p_pg['spalva'] . ';">' . $p_pg['tekstas'] . '</td></tr>
    </tbody>
</table>

<table class="two-col"><tr><td>

<h3>TOP 11 darbuotojai (daugiausiai punktu) &mdash; ' . htmlspecialchars($men_periodas) . '</h3>
<table>
    <thead><tr><th>#</th><th>Darbuotojas</th><th class="tc">Bandymu</th><th class="tc">Be def.</th></tr></thead>
    <tbody>';
$i = 1;
foreach ($combined_workers as $d) {
    $html .= '<tr>
        <td>' . $i++ . '</td>
        <td>' . htmlspecialchars($d['vardas']) . '</td>
        <td class="tc">' . $d['bandymu'] . '</td>
        <td class="tc green">' . $d['be_defektu'] . '</td>
    </tr>';
}
if (empty($combined_workers)) {
    $html .= '<tr><td colspan="4" class="tc" style="color:#999;padding:10px;">Duomenu nerasta</td></tr>';
}
$html .= '</tbody></table>

</td><td>

<h3 style="color:#dc2626;">TOP 11 daugiausiai klydo &mdash; ' . htmlspecialchars($men_periodas) . '</h3>
<table>
    <thead><tr><th>#</th><th>Darbuotojas</th><th class="tc">Bandymu</th><th class="tc">Be def.</th><th class="tc">Def.</th><th class="tc">%</th></tr></thead>
    <tbody>';
$i = 1;
foreach ($combined_errors as $d) {
    $html .= '<tr>
        <td style="color:#dc2626;">' . $i++ . '</td>
        <td>' . htmlspecialchars($d['vardas']) . '</td>
        <td class="tc">' . $d['bandymu'] . '</td>
        <td class="tc green">' . $d['be_defektu'] . '</td>
        <td class="tc red">' . $d['defektai'] . '</td>
        <td class="tc">' . $d['defektu_proc'] . '%</td>
    </tr>';
}
if (empty($combined_errors)) {
    $html .= '<tr><td colspan="6" class="tc" style="color:#999;padding:10px;">Defektu nerasta</td></tr>';
}
$html .= '</tbody></table>

</td></tr></table>';

foreach ([$q1, $q2] as $idx => $q) {
    $html .= '<h3>Problemingiausios operacijos (' . htmlspecialchars($q['periodas']) . ')</h3>';
    $html .= '<table>
        <thead><tr><th>#</th><th>Operacija</th><th class="tc">Defektai</th><th class="tc">Def. %</th></tr></thead>
        <tbody>';
    $i = 1;
    foreach ($q['problemines_operacijos'] as $op) {
        $html .= '<tr>
            <td>' . $i++ . '</td>
            <td style="font-size:10px;">' . htmlspecialchars($op['reikalavimas']) . '</td>
            <td class="tc red">' . $op['defektai'] . '</td>
            <td class="tc">' . $op['defektu_proc'] . '%</td>
        </tr>';
    }
    if (empty($q['problemines_operacijos'])) {
        $html .= '<tr><td colspan="4" class="tc" style="color:#999;padding:10px;">Defektu nerasta</td></tr>';
    }
    $html .= '</tbody></table>';
}

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 12,
        'margin_bottom' => 12,
        'tempDir' => '/tmp/mpdf',
    ]);
    $mpdf->SetTitle('Ketvirciu palyginimas - ' . $q1['periodas'] . ' vs ' . $q2['periodas']);
    $mpdf->SetAuthor('MT Modulis');
    $mpdf->WriteHTML($html);

    $failas = 'Ketvirciu_palyginimas_' . $q1_metai . 'Q' . $q1_ketv . '_vs_' . $q2_metai . 'Q' . $q2_ketv . '.pdf';
    $mpdf->Output($failas, 'D');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'PDF generavimo klaida: ' . $e->getMessage();
}
