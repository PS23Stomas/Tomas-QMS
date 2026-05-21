<?php
/**
 * Funkcinių bandymų protokolo PDF peržiūra ir atsisiuntimas
 *
 * Šis failas ištraukia anksčiau sugeneruotą funkcinių bandymų protokolo
 * PDF dokumentą iš duomenų bazės ir grąžina jį naršyklei.
 *
 * Veikimo būdai:
 * - Be "atsisiusti" parametro — PDF atidarytas naršyklėje (inline peržiūra)
 * - Su "?atsisiusti" parametru — PDF atsiunčiamas kaip failas į kompiuterį
 *
 * Jei PDF dar nebuvo sugeneruotas (nėra duomenų bazėje) — grąžina 404 klaidą.
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
$stmt = $conn->prepare("SELECT mt_funkciniu_pdf, mt_funkciniu_failas FROM gaminiai WHERE id = :id");
$stmt->execute(['id' => $gaminio_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['mt_funkciniu_pdf'])) {
    http_response_code(404);
    echo 'PDF nerastas';
    exit;
}

$pdf_data = $row['mt_funkciniu_pdf'];
if (is_resource($pdf_data)) {
    $pdf_data = stream_get_contents($pdf_data);
}

$failas = $row['mt_funkciniu_failas'] ?? 'mt_funkciniai.pdf';

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
