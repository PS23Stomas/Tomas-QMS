<?php
/**
 * Išplėstinės statistikos ataskaitos PDF generatorius
 *
 * Generuoja PDF ataskaitą su išsamia kokybės statistika pagal
 * vartotojo pasirinktus filtrus. Skirtingai nuo 30 dienų ataskaitos,
 * ši leidžia laisvai pasirinkti laikotarpį ir filtruoti pagal užsakymą.
 *
 * Ataskaitos turinys:
 *   - Bendra statistika (gaminiai, bandymų taškai, defektai, defektų %)
 *   - Defektų sąrašas su gaminio numeriu, reikalavimu ir aprašymu
 *   - Darbuotojų veiklos rodikliai pasirinktu laikotarpiu
 *
 * Filtravimo GET parametrai:
 *   - grupe           — modulio filtras (pvz. 'MT'), numatyta 'MT'
 *   - uzsakymo_numeris — filtruoti pagal konkretų užsakymą (pvz. '16467')
 *   - periodas        — 'visi' | '1m' | '3m' | '6m' | '1y' arba laisvas
 *   - menuo           — konkretus mėnuo formatu 'YYYY-MM' (pvz. '2024-06')
 *   - nuo, iki        — datos intervalas formatu 'YYYY-MM-DD'
 *
 * PDF generavimas: mPDF biblioteka.
 * Duomenų šaltinis: funkciniai_bandymai + gaminiai + gaminio_tipai + uzsakymai.
 *
 * Naudojama: mt_statistika.php puslapyje paspaudus „Eksportuoti PDF"
 */
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

$stmt = $pdo->prepare("
    SELECT
        EXTRACT(WEEK FROM u.sukurtas::timestamp) AS savaite,
        MIN(DATE(u.sukurtas)) AS savaites_data,
        COUNT(DISTINCT fb.gaminio_id) AS patikrinta_gaminiu,
        COUNT(CASE WHEN fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' THEN 1 END) AS klaidu
    FROM funkciniai_bandymai fb
    JOIN gaminiai g ON fb.gaminio_id = g.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    JOIN uzsakymai u ON g.uzsakymo_id = u.id
    $ist_where_sql AND gt.grupe = " . $pdo->quote($filtro_grupe) . "
    GROUP BY EXTRACT(WEEK FROM u.sukurtas::timestamp)
    ORDER BY savaite
");
$stmt->execute($ist_params);
$ist_savaiciu_duomenys = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

if (!empty($ist_savaiciu_duomenys)) {
    $max_val = 1;
    foreach ($ist_savaiciu_duomenys as $s) {
        $max_val = max($max_val, (int)$s['patikrinta_gaminiu'], (int)$s['klaidu']);
    }

    $svg_w      = 530;
    $svg_h      = 170;
    $ml         = 28;
    $mb         = 32;
    $mt         = 12;
    $chart_h    = $svg_h - $mb - $mt;
    $chart_w    = $svg_w - $ml;
    $n          = count($ist_savaiciu_duomenys);
    $slot_w     = $n > 0 ? $chart_w / $n : $chart_w;
    $bar_w      = max(4, min(16, $slot_w * 0.32));
    $baseline_y = $svg_h - $mb;

    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svg_w . '" height="' . $svg_h . '">';
    $svg .= '<line x1="' . $ml . '" y1="' . $baseline_y . '" x2="' . $svg_w . '" y2="' . $baseline_y . '" stroke="#d1d5db" stroke-width="1"/>';

    for ($yi = 1; $yi <= 4; $yi++) {
        $gy = $baseline_y - round($yi / 4 * $chart_h);
        $svg .= '<line x1="' . $ml . '" y1="' . $gy . '" x2="' . $svg_w . '" y2="' . $gy . '" stroke="#f3f4f6" stroke-width="1"/>';
        $svg .= '<text x="' . ($ml - 3) . '" y="' . ($gy + 3) . '" text-anchor="end" font-size="7" fill="#9ca3af">' . round($max_val * $yi / 4) . '</text>';
    }

    foreach ($ist_savaiciu_duomenys as $i => $s) {
        $g  = (int)$s['patikrinta_gaminiu'];
        $k  = (int)$s['klaidu'];
        $xc = $ml + ($i + 0.5) * $slot_w;

        $bh_g = $max_val > 0 ? round(($g / $max_val) * $chart_h) : 0;
        $xg   = round($xc - $bar_w - 1);
        $yg   = $baseline_y - $bh_g;
        if ($bh_g > 0) {
            $svg .= '<rect x="' . $xg . '" y="' . $yg . '" width="' . $bar_w . '" height="' . $bh_g . '" fill="#3b82f6" rx="1"/>';
        }
        if ($g > 0) {
            $svg .= '<text x="' . round($xg + $bar_w / 2) . '" y="' . ($yg - 2) . '" text-anchor="middle" font-size="7" fill="#1d4ed8">' . $g . '</text>';
        }

        $bh_k = $max_val > 0 ? round(($k / $max_val) * $chart_h) : 0;
        $xk   = round($xc + 1);
        $yk   = $baseline_y - $bh_k;
        if ($bh_k > 0) {
            $svg .= '<rect x="' . $xk . '" y="' . $yk . '" width="' . $bar_w . '" height="' . $bh_k . '" fill="#ef4444" rx="1"/>';
        }
        if ($k > 0) {
            $svg .= '<text x="' . round($xk + $bar_w / 2) . '" y="' . ($yk - 2) . '" text-anchor="middle" font-size="7" fill="#b91c1c">' . $k . '</text>';
        }

        $savaite  = (int)$s['savaite'];
        $data_txt = $s['savaites_data'] ? date('d.m', strtotime($s['savaites_data'])) : '';
        $svg .= '<text x="' . round($xc) . '" y="' . ($baseline_y + 11) . '" text-anchor="middle" font-size="8" fill="#374151">' . $savaite . '</text>';
        $svg .= '<text x="' . round($xc) . '" y="' . ($baseline_y + 21) . '" text-anchor="middle" font-size="7" fill="#9ca3af">' . $data_txt . '</text>';
    }

    $svg .= '</svg>';

    $html .= '<h2>Per savaite: patikrinta gaminiu ir rasta klaidu</h2>'
           . $svg
           . '<p style="font-size:9px;color:#6b7280;margin:4px 0 12px 0;">'
           . '<span style="color:#3b82f6;">&#9632;</span> Patikrinta gaminiu &nbsp;&nbsp;'
           . '<span style="color:#ef4444;">&#9632;</span> Rasta klaidu</p>';
}

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
