<?php
/**
 * MT paso PDF peržiūra ir atsisiuntimas
 *
 * Šis failas ištraukia anksčiau sugeneruotą MT paso PDF dokumentą
 * iš duomenų bazės ir grąžina jį naršyklei.
 *
 * Veikimo būdai:
 * - Be "atsisiusti" parametro — paso PDF atidarytas naršyklėje (inline peržiūra)
 * - Su "?atsisiusti" parametru — paso PDF atsiunčiamas kaip failas į kompiuterį
 *
 * Jei paso PDF dar nebuvo sugeneruotas — grąžina 404 klaidą.
 * Jei gaminio ID nenurodytas — grąžina 400 klaidą.
 */
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
$stmt = $conn->prepare("SELECT mt_paso_pdf, mt_paso_failas FROM gaminiai WHERE id = :id");
$stmt->execute(['id' => $gaminio_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['mt_paso_pdf'])) {
    http_response_code(404);
    echo 'PDF nerastas';
    exit;
}

$pdf_data = $row['mt_paso_pdf'];
if (is_resource($pdf_data)) {
    $pdf_data = stream_get_contents($pdf_data);
}

$failas = $row['mt_paso_failas'] ?? 'mt_pasas.pdf';

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
