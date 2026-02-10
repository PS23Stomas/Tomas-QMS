<?php
session_start();
require_once 'db.php';
require_once 'klases/Sesija.php';

Sesija::atnaujintiVeikla($pdo);

header('Content-Type: application/json; charset=utf-8');

function generatePretenzijaPdf($pdo, $id, $p, $photos) {
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
    margin-top: 15px;
}
.photo-grid {
    display: block;
}
.photo-item {
    display: inline-block;
    margin: 5px;
    border: 1px solid #ddd;
    padding: 3px;
}
.photo-item img {
    max-width: 150px;
    max-height: 150px;
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

    if (!empty($photos)) {
        $html .= '<div class="photos-section">
            <div class="section-title">NUOTRAUKOS (' . count($photos) . ')</div>
            <div class="photo-grid">';
        
        foreach ($photos as $n) {
            $imageData = $n['turinys'];
            if (is_resource($imageData)) {
                $imageData = stream_get_contents($imageData);
            }
            if (!$imageData) continue;
            
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
            $html .= '<div class="photo-item"><img src="data:' . $mimeType . ';base64,' . $base64 . '"></div>';
        }
        
        $html .= '</div></div>';
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
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);
        
        $mpdf->SetTitle('Pretenzija Nr. ' . $id);
        $mpdf->SetAuthor('Kokybės valdymo sistema');
        
        $mpdf->WriteHTML($html);
        
        return $mpdf->Output('', 'S');
    } catch (Exception $e) {
        error_log("PDF generavimo klaida: " . $e->getMessage());
        return null;
    }
}

$prisijunges = isset($_SESSION['vardas']) ? $_SESSION['vardas'] : null;
if (!$prisijunges) {
    echo json_encode(['success' => false, 'message' => 'Neprisijungęs']);
    exit;
}

const FROM_EMAIL = 'pretenzijos@updates.elga.tech';
const FROM_NAME = 'Pretenzijų Sistema';

$pretenzija_id = (int)($_POST['pretenzija_id'] ?? 0);
$emails_raw = trim($_POST['emails'] ?? '');

if (!$pretenzija_id) {
    echo json_encode(['success' => false, 'message' => 'Nepasirinkta pretenzija']);
    exit;
}

$emails = array_filter(array_map('trim', preg_split('/[,;\s]+/', $emails_raw)));
$validEmails = [];
foreach ($emails as $e) {
    if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
        $validEmails[] = $e;
    }
}

if (empty($validEmails)) {
    echo json_encode(['success' => false, 'message' => 'Neteisingas el. pašto adresas']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.uzsakymo_numeris
    FROM pretenzijos p
    LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
    WHERE p.id = ?
");
$stmt->execute([$pretenzija_id]);
$pretenzija = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pretenzija) {
    echo json_encode(['success' => false, 'message' => 'Pretenzija nerasta']);
    exit;
}

$stmtPhotos = $pdo->prepare("SELECT pavadinimas, tipas, turinys FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?");
$stmtPhotos->execute([$pretenzija_id]);
$photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

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

$tipasText = $tipai[$pretenzija['tipas']] ?? $pretenzija['tipas'];
$statusasText = $statusai[$pretenzija['statusas']] ?? $pretenzija['statusas'];
$uzsakymas = $pretenzija['uzsakymo_numeris'] ?? '-';
$data = date('Y-m-d', strtotime($pretenzija['gavimo_data'] ?? $pretenzija['sukurta']));

$subject = "Pretenzija PR-" . str_pad($pretenzija_id, 4, '0', STR_PAD_LEFT);
if ($uzsakymas !== '-') {
    $subject .= " (Užsakymas: $uzsakymas)";
}

$photosNote = '';
if (!empty($photos)) {
    $photosNote = '<div style="margin-top:20px;padding:12px;background:#e8f4fd;border-left:3px solid #3498db;border-radius:4px;">
        <p style="margin:0;color:#2c3e50;font-size:13px;">📎 <strong>Nuotraukos (' . count($photos) . ')</strong> - žiūrėkite pridėtame PDF faile</p>
    </div>';
}

$bodyHtml = '
<div style="font-family:Segoe UI,Arial,sans-serif;max-width:700px;margin:0 auto;">
    <div style="background:#f8f9fa;padding:20px;border-radius:8px 8px 0 0;border-bottom:3px solid #3498db;">
        <h2 style="margin:0;color:#2c3e50;">' . htmlspecialchars($subject) . '</h2>
        <p style="margin:8px 0 0;color:#7f8c8d;">Data: ' . $data . ' | Tipas: ' . $tipasText . ' | Statusas: ' . $statusasText . '</p>
    </div>
    
    <div style="padding:20px;background:#fff;border:1px solid #e9ecef;border-top:none;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="padding:8px 0;color:#7f8c8d;width:180px;">Aptikimo vieta:</td>
                <td style="padding:8px 0;color:#2c3e50;">' . htmlspecialchars($pretenzija['aptikimo_vieta'] ?? '-') . '</td>
            </tr>
            <tr>
                <td style="padding:8px 0;color:#7f8c8d;">Gaminys:</td>
                <td style="padding:8px 0;color:#2c3e50;">' . htmlspecialchars($pretenzija['gaminys_info'] ?? '-') . '</td>
            </tr>
            <tr>
                <td style="padding:8px 0;color:#7f8c8d;">Užsakymo Nr.:</td>
                <td style="padding:8px 0;color:#2c3e50;">' . htmlspecialchars($uzsakymas) . '</td>
            </tr>
        </table>
        
        <div style="margin-top:20px;">
            <h3 style="color:#2c3e50;border-bottom:1px solid #ddd;padding-bottom:8px;">Problemos aprašymas</h3>
            <p style="color:#2c3e50;line-height:1.6;">' . nl2br(htmlspecialchars($pretenzija['aprasymas'] ?? '-')) . '</p>
        </div>
        
        ' . ($pretenzija['atsakingas_padalinys'] ? '<div style="margin-top:20px;">
            <h3 style="color:#2c3e50;border-bottom:1px solid #ddd;padding-bottom:8px;">Atsakingas padalinys</h3>
            <p style="color:#2c3e50;">' . htmlspecialchars($pretenzija['atsakingas_padalinys']) . '</p>
        </div>' : '') . '
        
        ' . ($pretenzija['siulomas_sprendimas'] ? '<div style="margin-top:20px;">
            <h3 style="color:#2c3e50;border-bottom:1px solid #ddd;padding-bottom:8px;">Siūlomas sprendimas</h3>
            <p style="color:#2c3e50;line-height:1.6;">' . nl2br(htmlspecialchars($pretenzija['siulomas_sprendimas'])) . '</p>
            ' . ($pretenzija['terminas'] ? '<p style="color:#e74c3c;"><strong>Terminas:</strong> ' . date('Y-m-d', strtotime($pretenzija['terminas'])) . '</p>' : '') . '
        </div>' : '') . '
        
        <div style="margin-top:20px;padding-top:15px;border-top:1px solid #e9ecef;">
            <p style="color:#7f8c8d;font-size:13px;margin:0;">
                <strong>Užfiksavo:</strong> ' . htmlspecialchars($pretenzija['uzfiksavo_asmuo'] ?? $pretenzija['sukure_vardas'] ?? '-') . 
                ($pretenzija['uzfiksavo_padalinys'] ? ' (' . htmlspecialchars($pretenzija['uzfiksavo_padalinys']) . ')' : '') . '
            </p>
        </div>
        
        ' . $photosNote . '
    </div>
    
    <div style="background:#f8f9fa;padding:15px 20px;border-radius:0 0 8px 8px;border:1px solid #e9ecef;border-top:none;">
        <p style="margin:0;color:#7f8c8d;font-size:12px;">Automatinis pranešimas iš Pretenzijų valdymo sistemos</p>
    </div>
</div>';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'message' => 'Resend SDK neįdiegtas']);
    exit;
}
require_once $autoload;

$apiKey = getenv('RESEND_API_KEY');
if (!$apiKey) {
    echo json_encode(['success' => false, 'message' => 'RESEND_API_KEY nenustatytas']);
    exit;
}

try {
    $resend = Resend::client($apiKey);
    
    $pdfFilename = 'Pretenzija_PR-' . str_pad($pretenzija_id, 4, '0', STR_PAD_LEFT) . '.pdf';
    
    $pdfContent = generatePretenzijaPdf($pdo, $pretenzija_id, $pretenzija, $photos);
    
    $attachments = [];
    if ($pdfContent && strlen($pdfContent) > 500) {
        $attachments[] = [
            'filename' => $pdfFilename,
            'content' => base64_encode($pdfContent)
        ];
    }
    
    $emailData = [
        'from' => FROM_NAME . ' <' . FROM_EMAIL . '>',
        'to' => $validEmails,
        'subject' => $subject,
        'html' => $bodyHtml
    ];
    
    if (!empty($attachments)) {
        $emailData['attachments'] = $attachments;
    }
    
    $result = $resend->emails->send($emailData);
    
    if (isset($result['error'])) {
        throw new Exception($result['error']['message'] ?? 'Nežinoma klaida');
    }
    
    if (isset($result['id'])) {
        try {
            $sqlHist = "INSERT INTO pretenzijos_email_history (pretenzija_id, email_to, email_subject, sent_by) VALUES (?, ?, ?, ?)";
            $stHist = $pdo->prepare($sqlHist);
            $stHist->execute([$pretenzija_id, implode(', ', $validEmails), $subject, $prisijunges]);
        } catch (Throwable $e) {
            error_log("Nepavyko išsaugoti siuntimo istorijos: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'El. laiškas išsiųstas: ' . implode(', ', $validEmails),
            'with_pdf' => !empty($attachments)
        ]);
    } else {
        throw new Exception('Nepavyko išsiųsti');
    }
    
} catch (Throwable $e) {
    error_log("Pretenzijos el. pašto klaida: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Klaida: ' . $e->getMessage()]);
}
