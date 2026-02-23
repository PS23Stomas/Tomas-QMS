<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
requireLogin();

$filtro_grupe = $_GET['grupe'] ?? 'MT';
$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";
$ACTIVE_DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' AND LOWER(COALESCE(fb.isvada,'')) = 'neatitinka')";

$where_sql_30d = "WHERE gt.grupe = " . $pdo->quote($filtro_grupe) . " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'";

$patikrinti = (int)$pdo->query("
  SELECT COUNT(DISTINCT fb.gaminio_id)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql_30d
")->fetchColumn();

$viso_defektu = (int)$pdo->query("
  SELECT COUNT(*)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql_30d AND $DEFECT_COND
")->fetchColumn();

$viso_punktu = (int)$pdo->query("
  SELECT COUNT(*)
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql_30d
")->fetchColumn();

$vid_proc = ($viso_punktu > 0) ? round($viso_defektu / $viso_punktu * 100, 1) : 0.0;

$aktyvus_defektai = $pdo->query("
  SELECT u.uzsakymo_numeris AS uzsakymo_nr, g.gaminio_numeris, gt.gaminio_tipas AS gaminio_tipas,
    fb.eil_nr AS punkto_nr, fb.reikalavimas, fb.defektas AS defekto_aprasymas
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  WHERE gt.grupe = " . $pdo->quote($filtro_grupe) . " AND $ACTIVE_DEFECT_COND AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '30 days'
  ORDER BY u.uzsakymo_numeris DESC, g.gaminio_numeris, fb.eil_nr LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
$aktyvus_count = count($aktyvus_defektai);

$top_klaidos = $pdo->query("
  SELECT MIN(fb.eil_nr) as eil_nr, fb.reikalavimas, COUNT(*) AS kiekis
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql_30d AND $DEFECT_COND AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
  GROUP BY fb.reikalavimas ORDER BY kiekis DESC, eil_nr ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$weeks = $pdo->query("
  SELECT TO_CHAR(u.sukurtas::timestamp, 'IYYYIW') AS yw,
    COUNT(DISTINCT fb.gaminio_id) AS patikrinta,
    SUM(CASE WHEN $DEFECT_COND THEN 1 ELSE 0 END) AS klaidu
  FROM mt_funkciniai_bandymai fb
  JOIN gaminiai g ON fb.gaminio_id = g.id
  JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
  JOIN uzsakymai u ON g.uzsakymo_id = u.id
  $where_sql_30d
  GROUP BY TO_CHAR(u.sukurtas::timestamp, 'IYYYIW')
  ORDER BY TO_CHAR(u.sukurtas::timestamp, 'IYYYIW')
")->fetchAll(PDO::FETCH_ASSOC);

$data = date('Y-m-d H:i');
$vartotojas = currentUser();
$vart_vardas = htmlspecialchars(($vartotojas['vardas'] ?? '') . ' ' . ($vartotojas['pavarde'] ?? ''));

$html = '
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #333; }
    h1 { font-size: 18px; margin-bottom: 4px; color: #1e3a5f; }
    h2 { font-size: 14px; margin: 16px 0 8px; color: #1e3a5f; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; }
    .meta { font-size: 10px; color: #6b7280; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; border-bottom: 2px solid #d1d5db; }
    td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
    .tc { text-align: center; }
    .green { color: #16a34a; font-weight: 600; }
    .red { color: #dc2626; font-weight: 600; }
    .kpi-table td { text-align: center; padding: 12px 8px; }
    .kpi-value { font-size: 24px; font-weight: 700; }
    .kpi-label { font-size: 10px; color: #6b7280; margin-top: 4px; }
    .kpi-green .kpi-value { color: #16a34a; }
    .kpi-orange .kpi-value { color: #f59e0b; }
    .kpi-yellow .kpi-value { color: #ca8a04; }
    .kpi-red .kpi-value { color: #dc2626; }
    .aktyvus-dot { display: inline-block; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; }
</style>

<h1>30 dienu kokybes rodikliai</h1>
<div class="meta">
    Periodas: paskutines 30 dienu | Sugeneruota: ' . $data . ' | ' . $vart_vardas . '
</div>

<h2>Pagrindiniai rodikliai</h2>
<table class="kpi-table">
    <tr>
        <td class="kpi-green">
            <div class="kpi-value">' . $patikrinti . '</div>
            <div class="kpi-label">Patikrinta gaminiu</div>
        </td>
        <td class="kpi-orange">
            <div class="kpi-value">' . $viso_defektu . '</div>
            <div class="kpi-label">Viso neatitikimu</div>
        </td>
        <td class="kpi-yellow">
            <div class="kpi-value">' . $vid_proc . '%</div>
            <div class="kpi-label">Neatitikimu %</div>
        </td>
        <td class="kpi-red">
            <div class="kpi-value">' . $aktyvus_count . '</div>
            <div class="kpi-label">Aktyvus nepataisyti</div>
        </td>
    </tr>
</table>';

if (!empty($weeks)) {
    $html .= '<h2>Menesine suvestine pagal savaites</h2>
    <table>
        <thead><tr><th>Savaite</th><th class="tc">Patikrinta gaminiu</th><th class="tc">Rasta neatitikimu</th></tr></thead>
        <tbody>';
    foreach ($weeks as $w) {
        $yw = (string)$w['yw'];
        $wk = substr($yw, -2);
        $html .= '<tr>
            <td>S' . $wk . '</td>
            <td class="tc">' . (int)$w['patikrinta'] . '</td>
            <td class="tc' . ((int)$w['klaidu'] > 0 ? ' red' : '') . '">' . (int)$w['klaidu'] . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

if (!empty($top_klaidos)) {
    $html .= '<h2>TOP 5 dazniausios klaidos</h2>
    <table>
        <thead><tr><th>#</th><th>Punkto Nr.</th><th>Reikalavimas</th><th class="tc">Kiekis</th></tr></thead>
        <tbody>';
    $i = 1;
    foreach ($top_klaidos as $t) {
        $html .= '<tr>
            <td>' . $i++ . '</td>
            <td class="tc">' . (int)$t['eil_nr'] . '</td>
            <td>' . htmlspecialchars($t['reikalavimas'] ?? '') . '</td>
            <td class="tc red">' . (int)$t['kiekis'] . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

if (!empty($aktyvus_defektai)) {
    $html .= '<h2>Aktyvus nepataisyti defektai (' . $aktyvus_count . ')</h2>
    <table>
        <thead><tr><th>Uzsakymo Nr.</th><th>Gaminio Nr.</th><th>Tipas</th><th>Pkt.</th><th>Reikalavimas</th><th>Defekto aprasymas</th></tr></thead>
        <tbody>';
    foreach ($aktyvus_defektai as $d) {
        $html .= '<tr>
            <td>' . htmlspecialchars($d['uzsakymo_nr'] ?? '') . '</td>
            <td class="tc">' . htmlspecialchars($d['gaminio_numeris'] ?? '') . '</td>
            <td>' . htmlspecialchars($d['gaminio_tipas'] ?? '') . '</td>
            <td class="tc">' . (int)($d['punkto_nr'] ?? 0) . '</td>
            <td style="font-size:10px;">' . htmlspecialchars($d['reikalavimas'] ?? '') . '</td>
            <td style="font-size:10px;" class="red">' . htmlspecialchars($d['defekto_aprasymas'] ?? '') . '</td>
        </tr>';
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
    $mpdf->SetTitle('30 dienu kokybes rodikliai');
    $mpdf->SetAuthor('MT Modulis');
    $mpdf->WriteHTML($html);

    $failas = '30d_kokybes_rodikliai_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($failas, 'D');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'PDF generavimo klaida: ' . $e->getMessage();
}
