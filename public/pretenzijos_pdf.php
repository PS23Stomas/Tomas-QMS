<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Pretenzijos ID nerastas';
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.uzsakymo_numeris
    FROM pretenzijos p
    LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    http_response_code(404);
    echo 'Pretenzija nerasta';
    exit;
}

$tipai = [
    'vidine' => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjo pretenzija'
];

$statusai = [
    'nauja' => 'Nauja',
    'tyrimas' => 'Tiriama',
    'vykdoma' => 'Vykdoma',
    'uzbaigta' => 'Užbaigta',
    'atmesta' => 'Atmesta'
];

$nuotraukos = $pdo->prepare("SELECT id, pavadinimas, tipas, turinys FROM pretenzijos_nuotraukos WHERE pretenzija_id = :id ORDER BY id");
$nuotraukos->execute([':id' => $id]);
$photos = $nuotraukos->fetchAll(PDO::FETCH_ASSOC);

$uzsakymo_nr = $p['uzsakymo_numeris'] ?? $p['uzsakymo_numeris_ranka'] ?? '-';
$tipas_label = $tipai[$p['tipas']] ?? $p['tipas'];
$statusas_label = $statusai[$p['statusas']] ?? $p['statusas'];
$esc = function($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); };

$imone = getImonesNustatymai();

$html = '
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #333; }
    h1 { font-size: 16px; text-align: center; margin: 0 0 5px 0; }
    h2 { font-size: 12px; text-align: center; margin: 0 0 15px 0; color: #666; font-weight: normal; }
    .header-table { width: 100%; margin-bottom: 15px; }
    .header-table td { vertical-align: top; }
    .section { margin-bottom: 12px; }
    .section-title { font-weight: bold; font-size: 11px; text-transform: uppercase; color: #444; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 6px; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.info td, table.info th { border: 1px solid #999; padding: 5px 8px; font-size: 10px; }
    table.info th { background: #f0f0f0; font-weight: bold; text-align: left; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; }
    .badge-nauja { background: #ebf5fb; color: #2980b9; }
    .badge-tyrimas { background: #fef9e7; color: #b7950b; }
    .badge-vykdoma { background: #f5eef8; color: #8e44ad; }
    .badge-uzbaigta { background: #eafaf1; color: #27ae60; }
    .badge-atmesta { background: #f4f6f6; color: #95a5a6; }
    .text-block { background: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #e9ecef; margin-top: 3px; }
    .photo-grid { margin-top: 5px; }
    .photo-grid img { max-width: 200px; max-height: 150px; margin: 3px; border: 1px solid #ddd; border-radius: 4px; }
    .footer { text-align: center; font-size: 8px; color: #999; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 5px; }
</style>

<table class="header-table">
    <tr>
        <td style="width:50%;">
            <strong style="font-size:14px;">' . htmlspecialchars($imone['pavadinimas']) . '</strong><br>
            Kokybės valdymo sistema
        </td>
        <td style="width:50%;text-align:right;">
            <span class="badge badge-' . $esc($p['statusas']) . '">' . $esc($statusas_label) . '</span><br>
            <strong>Pretenzija #' . $p['id'] . '</strong><br>
            ' . $esc($tipas_label) . '
        </td>
    </tr>
</table>

<h1>PRETENZIJA (PR 28/2)</h1>
<h2>' . $esc($tipas_label) . '</h2>

<div class="section">
    <div class="section-title">Aptikimo informacija</div>
    <table class="info">
        <tr>
            <th style="width:40%;">Problemos pastebėjimo vieta</th>
            <th style="width:30%;">Gaminys</th>
            <th style="width:30%;">Užsakymo Nr.</th>
        </tr>
        <tr>
            <td>' . $esc($p['aptikimo_vieta'] ?: '-') . '</td>
            <td>' . $esc($p['gaminys_info'] ?: '-') . '</td>
            <td>' . $esc($uzsakymo_nr) . '</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Problemos aprašymas</div>
    <div class="text-block">' . nl2br($esc($p['aprasymas'] ?: '-')) . '</div>
</div>

<div class="section">
    <div class="section-title">Padalinys atsakingas už sprendimą</div>
    <div class="text-block">' . $esc($p['atsakingas_padalinys'] ?: '-') . '</div>
</div>

<div class="section">
    <table class="info">
        <tr>
            <th style="width:70%;">Siūlomas sprendimo būdas</th>
            <th style="width:30%;">Terminas</th>
        </tr>
        <tr>
            <td>' . nl2br($esc($p['siulomas_sprendimas'] ?: '-')) . '</td>
            <td>' . $esc($p['terminas'] ?: '-') . '</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Problemą užfiksavo</div>
    <table class="info">
        <tr>
            <th>Padalinys</th>
            <th>Asmuo</th>
            <th>Data</th>
        </tr>
        <tr>
            <td>' . $esc($p['uzfiksavo_padalinys'] ?: '-') . '</td>
            <td>' . $esc($p['uzfiksavo_asmuo'] ?: '-') . '</td>
            <td>' . $esc($p['gavimo_data'] ? date('Y-m-d', strtotime($p['gavimo_data'])) : '-') . '</td>
        </tr>
    </table>
</div>';

if (!empty($p['priezastis']) || !empty($p['veiksmai']) || !empty($p['atsakingas_asmuo'])) {
    $html .= '
<div class="section">
    <div class="section-title">Tyrimo rezultatai</div>
    <table class="info">
        <tr><th>Nustatyta priežastis</th></tr>
        <tr><td>' . nl2br($esc($p['priezastis'] ?: '-')) . '</td></tr>
    </table>
    <table class="info">
        <tr>
            <th style="width:70%;">Korekciniai veiksmai</th>
            <th style="width:30%;">Atsakingas asmuo</th>
        </tr>
        <tr>
            <td>' . nl2br($esc($p['veiksmai'] ?: '-')) . '</td>
            <td>' . $esc($p['atsakingas_asmuo'] ?: '-') . '</td>
        </tr>
    </table>
</div>';
}

if (!empty($photos)) {
    $html .= '<div class="section"><div class="section-title">Nuotraukos (' . count($photos) . ')</div><div class="photo-grid">';
    foreach ($photos as $photo) {
        $data = is_resource($photo['turinys']) ? stream_get_contents($photo['turinys']) : $photo['turinys'];
        $base64 = base64_encode($data);
        $mime = $photo['tipas'] ?: 'image/jpeg';
        $html .= '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="' . $esc($photo['pavadinimas']) . '">';
    }
    $html .= '</div></div>';
}

$html .= '
<div class="section">
    <table class="info">
        <tr>
            <th style="width:33%;">Statusas</th>
            <th style="width:33%;">Sukūrė</th>
            <th style="width:34%;">Data</th>
        </tr>
        <tr>
            <td><span class="badge badge-' . $esc($p['statusas']) . '">' . $esc($statusas_label) . '</span></td>
            <td>' . $esc($p['sukure_vardas'] ?: '-') . '</td>
            <td>' . ($p['sukurta'] ? date('Y-m-d H:i', strtotime($p['sukurta'])) : '-') . '</td>
        </tr>
    </table>
</div>

<div class="footer">
    Sugeneruota: ' . date('Y-m-d H:i') . ' | Kokybės valdymo sistema — ' . htmlspecialchars($imone['pavadinimas']) . '
</div>';

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

    $mpdf->WriteHTML($html);
    $mpdf->Output('Pretenzija_' . $id . '.pdf', 'D');
} catch (Exception $e) {
    http_response_code(500);
    echo 'PDF generavimo klaida: ' . $e->getMessage();
}
