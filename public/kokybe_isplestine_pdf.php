<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
requireLogin();

$filtro_grupe = $_GET['grupe'] ?? 'MT';
$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

$ist_uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$ist_periodas         = $_GET['periodas'] ?? 'visi';
$ist_menuo            = $_GET['menuo'] ?? '';
$ist_nuo              = $_GET['nuo'] ?? '';
$ist_iki              = $_GET['iki'] ?? '';

$ist_where_uzsakymas = ($ist_uzsakymo_numeris !== '') ? "u.uzsakymo_numeris = ?" : "1=1";
$ist_params = [];
if ($ist_uzsakymo_numeris !== '') $ist_params[] = $ist_uzsakymo_numeris;

$ist_where_laikotarpis = '';
$periodo_tekstas = 'Visi duomenys';
if ($ist_menuo !== '') {
    $ist_where_laikotarpis = " AND TO_CHAR(u.sukurtas::timestamp, 'YYYY-MM') = ?";
    $ist_params[] = $ist_menuo;
    $periodo_tekstas = 'Menuo: ' . $ist_menuo;
} elseif ($ist_nuo !== '' && $ist_iki !== '') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) BETWEEN ? AND ?";
    $ist_params[] = $ist_nuo;
    $ist_params[] = $ist_iki;
    $periodo_tekstas = 'Nuo ' . $ist_nuo . ' iki ' . $ist_iki;
} elseif ($ist_periodas === '1m') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 month'";
    $periodo_tekstas = 'Paskutinis menuo';
} elseif ($ist_periodas === '6m') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '6 month'";
    $periodo_tekstas = 'Paskutiniai 6 menesiai';
} elseif ($ist_periodas === '1y') {
    $ist_where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 year'";
    $periodo_tekstas = 'Paskutiniai 12 menesiu';
}

$ist_rodyti = !($ist_uzsakymo_numeris === '' && $ist_periodas === 'visi' && $ist_menuo === '' && ($ist_nuo === '' || $ist_iki === ''));
if (!$ist_rodyti) {
    die('Pasirinkite bent viena filtra');
}

$ist_where_sql = "WHERE $ist_where_uzsakymas $ist_where_laikotarpis";

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT fb.gaminio_id)
    FROM funkciniai_bandymai fb JOIN gaminiai g ON fb.gaminio_id = g.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id JOIN uzsakymai u ON g.uzsakymo_id = u.id
    $ist_where_sql AND gt.grupe = " . $pdo->quote($filtro_grupe) . "
");
$stmt->execute($ist_params);
$ist_patikrinti = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.uzsakymo_numeris, fb.reikalavimas, fb.defektas, fb.isvada
    FROM funkciniai_bandymai fb JOIN gaminiai g ON fb.gaminio_id = g.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id JOIN uzsakymai u ON g.uzsakymo_id = u.id
    $ist_where_sql AND gt.grupe = " . $pdo->quote($filtro_grupe) . " AND fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> ''
    ORDER BY u.uzsakymo_numeris
");
$stmt->execute($ist_params);
$ist_defektu_gaminiai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ist_klaidos = 0;
foreach ($ist_defektu_gaminiai as $r) {
    if (!empty(trim((string)$r['defektas']))) $ist_klaidos++;
}

$stmt = $pdo->prepare("
    SELECT MIN(fb.eil_nr) as eil_nr, fb.reikalavimas, COUNT(*) AS kiekis
    FROM funkciniai_bandymai fb JOIN gaminiai g ON fb.gaminio_id = g.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id JOIN uzsakymai u ON g.uzsakymo_id = u.id
    $ist_where_sql AND gt.grupe = " . $pdo->quote($filtro_grupe) . " AND fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> ''
    AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
    GROUP BY fb.reikalavimas ORDER BY kiekis DESC, eil_nr ASC LIMIT 5
");
$stmt->execute($ist_params);
$ist_top_defektai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT u.uzsakymo_numeris, f.reikalavimas, f.defektas
    FROM funkciniai_bandymai f JOIN gaminiai g ON f.gaminio_id = g.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id JOIN uzsakymai u ON g.uzsakymo_id = u.id
    $ist_where_sql AND gt.grupe = " . $pdo->quote($filtro_grupe) . " AND LOWER(f.isvada) IN ('neatitinka','nepadaryta')
    AND f.defektas IS NOT NULL AND TRIM(f.defektas) <> ''
    ORDER BY u.uzsakymo_numeris
");
$stmt->execute($ist_params);
$ist_aktyvus_defektai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = date('Y-m-d H:i');
$vartotojas = currentUser();
$vart_vardas = htmlspecialchars(($vartotojas['vardas'] ?? '') . ' ' . ($vartotojas['pavarde'] ?? ''));

$filtro_info = $periodo_tekstas;
if ($ist_uzsakymo_numeris !== '') {
    $filtro_info .= ' | Uzsakymas: ' . htmlspecialchars($ist_uzsakymo_numeris);
}

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
    .badge-danger { display: inline-block; background: #fef2f2; color: #dc2626; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    .badge-success { display: inline-block; background: #dcfce7; color: #16a34a; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
</style>

<h1>Isplestine kokybes statistika</h1>
<div class="meta">
    ' . $filtro_info . ' | Sugeneruota: ' . $data . ' | ' . $vart_vardas . '
</div>

<h2>Pagrindiniai rodikliai</h2>
<table class="kpi-table">
    <tr>
        <td>
            <div class="kpi-value" style="color:#16a34a;">' . $ist_patikrinti . '</div>
            <div class="kpi-label">Patikrinti gaminiai</div>
        </td>
        <td>
            <div class="kpi-value" style="color:#dc2626;">' . $ist_klaidos . '</div>
            <div class="kpi-label">Rasti defektai</div>
        </td>
    </tr>
</table>';

if (!empty($ist_top_defektai)) {
    $html .= '<h2>TOP 5 dazniausios klaidos</h2>
    <table>
        <thead><tr><th>#</th><th>Punkto Nr.</th><th>Reikalavimas</th><th class="tc">Kiekis</th></tr></thead>
        <tbody>';
    $i = 1;
    foreach ($ist_top_defektai as $d) {
        $html .= '<tr>
            <td>' . $i++ . '</td>
            <td class="tc">' . (int)$d['eil_nr'] . '</td>
            <td>' . htmlspecialchars($d['reikalavimas'] ?? '') . '</td>
            <td class="tc red">' . (int)$d['kiekis'] . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

if (!empty($ist_defektu_gaminiai)) {
    $html .= '<h2>Uzsakymai ir defektai (' . count($ist_defektu_gaminiai) . ' irasu)</h2>
    <table>
        <thead><tr><th>Uzsakymo numeris</th><th>Reikalavimas</th><th>Defektas</th><th class="tc">Busena</th></tr></thead>
        <tbody>';
    foreach ($ist_defektu_gaminiai as $eil) {
        $busena = (in_array(strtolower((string)($eil['isvada'] ?? '')), ['neatitinka','nepadaryta'])) ? 'Nepataisyta' : 'Pataisyta';
        $busena_class = $busena === 'Nepataisyta' ? 'badge-danger' : 'badge-success';
        $html .= '<tr>
            <td>' . htmlspecialchars($eil['uzsakymo_numeris'] ?? '') . '</td>
            <td>' . htmlspecialchars($eil['reikalavimas'] ?? '-') . '</td>
            <td>' . htmlspecialchars($eil['defektas'] ?? '-') . '</td>
            <td class="tc"><span class="' . $busena_class . '">' . $busena . '</span></td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

if (!empty($ist_aktyvus_defektai)) {
    $html .= '<h2>Aktyvus nepataisyti defektai (' . count($ist_aktyvus_defektai) . ')</h2>
    <table>
        <thead><tr><th>Uzsakymo numeris</th><th>Reikalavimas</th><th>Defekto aprasymas</th></tr></thead>
        <tbody>';
    foreach ($ist_aktyvus_defektai as $row) {
        $html .= '<tr>
            <td>' . htmlspecialchars($row['uzsakymo_numeris'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['reikalavimas'] ?? '') . '</td>
            <td class="red">' . htmlspecialchars($row['defektas'] ?? '') . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<h2>Aktyvus nepataisyti defektai</h2>
    <p style="color:#6b7280;text-align:center;padding:12px;">Nera aktyviu nepataisytu defektu</p>';
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
    $mpdf->SetTitle('Isplestine kokybes statistika');
    $mpdf->SetAuthor('MT Modulis');
    $mpdf->WriteHTML($html);

    $failas = 'Isplestine_statistika_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($failas, 'D');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'PDF generavimo klaida: ' . $e->getMessage();
}
