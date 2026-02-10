<?php
session_start();
require_once 'db.php';
require 'vendor/autoload.php';

use Mpdf\Mpdf;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Neteisingas pretenzijos ID');
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
    die('Pretenzija nerasta');
}

$nuotraukosStmt = $pdo->prepare("SELECT id, pavadinimas, tipas, turinys FROM pretenzijos_nuotraukos WHERE pretenzija_id = :id ORDER BY id");
$nuotraukosStmt->execute([':id' => $id]);
$nuotraukos = $nuotraukosStmt->fetchAll(PDO::FETCH_ASSOC);

$tipai = [
    'vidine' => 'Vidinė',
    'tiekejo' => 'Tiekėjo',
    'kliento' => 'Kliento'
];

$statusai = [
    'nauja' => 'Nauja',
    'tyrimas' => 'Tiriama',
    'vykdoma' => 'Vykdoma',
    'uzbaigta' => 'Užbaigta',
    'atmesta' => 'Atmesta'
];

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11pt;
    line-height: 1.4;
}
h1 {
    text-align: center;
    font-size: 16pt;
    margin-bottom: 5px;
    color: #c0392b;
}
.form-code {
    text-align: center;
    font-size: 10pt;
    color: #666;
    margin-bottom: 20px;
}
.section {
    margin-bottom: 15px;
}
.section-title {
    font-weight: bold;
    background: #f5f5f5;
    padding: 5px 8px;
    border-left: 3px solid #c0392b;
    margin-bottom: 8px;
}
.field-row {
    margin-bottom: 5px;
}
.field-label {
    font-weight: bold;
    color: #333;
}
.field-value {
    padding: 5px;
    background: #fafafa;
    border: 1px solid #ddd;
    min-height: 20px;
}
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
.info-table th, .info-table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}
.info-table th {
    background: #f0f0f0;
    font-weight: bold;
    font-size: 10pt;
}
.description-box {
    border: 1px solid #ccc;
    padding: 10px;
    min-height: 60px;
    background: #fff;
}
.footer-section {
    background: #f8f9fa;
    padding: 10px;
    border: 1px solid #ddd;
    margin-top: 15px;
}
.footer-table {
    width: 100%;
}
.footer-table td {
    padding: 5px;
    width: 33%;
}
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 9pt;
    font-weight: bold;
}
.badge-tipas {
    background: #3498db;
    color: white;
}
.badge-status {
    background: #27ae60;
    color: white;
}
.photos-section {
    margin-top: 10px;
    page-break-inside: avoid;
}
.photo-table {
    width: 100%;
    border-collapse: collapse;
}
.photo-table td {
    padding: 5px;
    text-align: center;
    vertical-align: top;
    border: 1px solid #eee;
}
.photo-table img {
    max-width: 170px;
    max-height: 170px;
}
.section {
    page-break-inside: avoid;
}
.footer-section {
    page-break-inside: avoid;
}
.meta-info {
    font-size: 9pt;
    color: #666;
    text-align: right;
    margin-top: 20px;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}
</style>
</head>
<body>

<h1>PRETENZIJA</h1>
<div class="form-code">Forma PR 28/2 &nbsp;&nbsp;|&nbsp;&nbsp; Nr. ' . $p['id'] . '</div>

<table class="info-table">
    <tr>
        <th style="width:40%">Problemos pastebėjimo (aptikimo) vieta</th>
        <th style="width:30%">Gaminys</th>
        <th style="width:30%">Užsakymo Nr.</th>
    </tr>
    <tr>
        <td>' . htmlspecialchars($p['aptikimo_vieta'] ?? '-') . '</td>
        <td>' . htmlspecialchars($p['gaminys_info'] ?? '-') . '</td>
        <td>' . htmlspecialchars($p['uzsakymo_numeris'] ?? '-') . '</td>
    </tr>
</table>

<div class="section">
    <div class="section-title">PROBLEMOS APRAŠYMAS</div>
    <div class="description-box">' . nl2br(htmlspecialchars($p['aprasymas'] ?? '')) . '</div>
</div>

<div class="section">
    <div class="section-title">PADALINYS ATSAKINGAS UŽ SPRENDIMĄ</div>
    <div class="field-value">' . htmlspecialchars($p['atsakingas_padalinys'] ?? '-') . '</div>
</div>

<table class="info-table">
    <tr>
        <th style="width:70%">Siūlomas sprendimo būdas</th>
        <th style="width:30%">Terminas</th>
    </tr>
    <tr>
        <td>' . nl2br(htmlspecialchars($p['siulomas_sprendimas'] ?? '-')) . '</td>
        <td>' . ($p['terminas'] ? date('Y-m-d', strtotime($p['terminas'])) : '-') . '</td>
    </tr>
</table>

<div class="footer-section">
    <div style="font-weight:bold; margin-bottom:8px;">PROBLEMĄ UŽFIKSAVO</div>
    <table class="footer-table">
        <tr>
            <td><strong>Padalinys:</strong><br>' . htmlspecialchars($p['uzfiksavo_padalinys'] ?? '-') . '</td>
            <td><strong>Pavardė, vardas:</strong><br>' . htmlspecialchars($p['uzfiksavo_asmuo'] ?? '-') . '</td>
            <td><strong>Data:</strong><br>' . ($p['gavimo_data'] ? date('Y-m-d', strtotime($p['gavimo_data'])) : '-') . '</td>
        </tr>
    </table>
</div>';

if (!empty($p['priezastis']) || !empty($p['veiksmai'])) {
    $html .= '
<div class="section" style="margin-top:15px;">
    <div class="section-title">TYRIMO REZULTATAI</div>';
    
    if (!empty($p['priezastis'])) {
        $html .= '<div class="field-row"><span class="field-label">Nustatyta priežastis:</span></div>
        <div class="field-value">' . nl2br(htmlspecialchars($p['priezastis'])) . '</div>';
    }
    
    if (!empty($p['veiksmai'])) {
        $html .= '<div class="field-row" style="margin-top:10px;"><span class="field-label">Korekciniai veiksmai:</span></div>
        <div class="field-value">' . nl2br(htmlspecialchars($p['veiksmai'])) . '</div>';
    }
    
    $html .= '</div>';
}

if (!empty($nuotraukos)) {
    $html .= '<div class="photos-section">
        <div class="section-title">NUOTRAUKOS (' . count($nuotraukos) . ')</div>
        <table class="photo-table"><tr>';
    
    $col = 0;
    $maxCols = 3;
    
    foreach ($nuotraukos as $n) {
        $imageData = is_resource($n['turinys']) ? stream_get_contents($n['turinys']) : $n['turinys'];
        $mimeType = $n['tipas'] ?: 'image/jpeg';
        
        if (strpos($mimeType, 'webp') !== false) {
            $img = @imagecreatefromwebp('data://image/webp;base64,' . base64_encode($imageData));
            if ($img) {
                ob_start();
                imagejpeg($img, null, 85);
                $imageData = ob_get_clean();
                imagedestroy($img);
                $mimeType = 'image/jpeg';
            }
        }
        
        $base64 = base64_encode($imageData);
        $html .= '<td><img src="data:' . $mimeType . ';base64,' . $base64 . '"></td>';
        
        $col++;
        if ($col >= $maxCols) {
            $html .= '</tr><tr>';
            $col = 0;
        }
    }
    
    while ($col > 0 && $col < $maxCols) {
        $html .= '<td></td>';
        $col++;
    }
    
    $html .= '</tr></table></div>';
}

$html .= '
<div class="meta-info">
    <span class="badge badge-tipas">' . ($tipai[$p['tipas']] ?? $p['tipas']) . '</span>
    <span class="badge badge-status">' . ($statusai[$p['statusas']] ?? $p['statusas']) . '</span>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    Sukurta: ' . date('Y-m-d H:i', strtotime($p['sukurta'])) . '
    ' . ($p['sukure_vardas'] ? ' | ' . htmlspecialchars($p['sukure_vardas']) : '') . '
</div>

</body>
</html>';

try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);
    
    $mpdf->SetTitle('Pretenzija Nr. ' . $p['id']);
    $mpdf->SetAuthor('Kokybės valdymo sistema');
    
    $mpdf->WriteHTML($html);
    
    $filename = 'Pretenzija_' . $p['id'] . '_' . date('Y-m-d') . '.pdf';
    
    if (isset($_GET['raw']) && $_GET['raw'] === '1') {
        echo $mpdf->Output($filename, 'S');
    } else {
        $mpdf->Output($filename, 'I');
    }
    
} catch (Exception $e) {
    die('PDF generavimo klaida: ' . $e->getMessage());
}
