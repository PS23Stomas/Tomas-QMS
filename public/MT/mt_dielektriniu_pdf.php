<?php
require_once __DIR__ . '/../includes/config.php';

requireLogin();

$gaminio_id = $_GET['gaminio_id'] ?? null;
$atsisiusti = isset($_GET['atsisiusti']);

if (!$gaminio_id) {
    http_response_code(400);
    echo 'Nenurodytas gaminio ID';
    exit;
}

$conn = Database::getConnection();
$stmt = $conn->prepare("SELECT mt_dielektriniu_pdf, mt_dielektriniu_failas FROM gaminiai WHERE id = :id");
$stmt->execute(['id' => $gaminio_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['mt_dielektriniu_pdf'])) {
    http_response_code(404);
    echo 'PDF nerastas';
    exit;
}

$pdf_data = $row['mt_dielektriniu_pdf'];
if (is_resource($pdf_data)) {
    $pdf_data = stream_get_contents($pdf_data);
}

$failas = $row['mt_dielektriniu_failas'] ?? 'mt_dielektriniai.pdf';

header('Content-Type: application/pdf');
if ($atsisiusti) {
    header('Content-Disposition: attachment; filename="' . $failas . '"');
} else {
    header('Content-Disposition: inline; filename="' . $failas . '"');
}
header('Content-Length: ' . strlen($pdf_data));
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $pdf_data;
exit;
