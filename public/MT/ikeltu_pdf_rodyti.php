<?php
/**
 * Įkelto PDF failo rodymas naršyklėje
 *
 * Ištraukia rankiniu būdu įkeltą PDF iš gaminiu_pdf_failai lentelės
 * ir grąžina jį naršyklei kaip inline dokumentą.
 *
 * GET parametrai:
 *   id         — failo ID gaminiu_pdf_failai lentelėje
 *   atsisiusti — jei nurodytas, faile atsisiunčiamas (o ne rodomas)
 */
require_once __DIR__ . '/../includes/config.php';

requireLogin();

$id         = (int)($_GET['id'] ?? 0);
$atsisiusti = isset($_GET['atsisiusti']);

if ($id <= 0) {
    http_response_code(400);
    echo 'Nenurodytas failo ID';
    exit;
}

$conn = Database::getConnection();
$stmt = $conn->prepare("SELECT turinys, failas_vardas FROM gaminiu_pdf_failai WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo 'PDF nerastas';
    exit;
}

$pdf_data = $row['turinys'];
if (is_resource($pdf_data)) {
    $pdf_data = stream_get_contents($pdf_data);
}

$failas = $row['failas_vardas'] ?: 'dokumentas.pdf';

header('Content-Type: application/pdf');
if ($atsisiusti) {
    header('Content-Disposition: attachment; filename="' . rawurlencode($failas) . '"');
} else {
    header('Content-Disposition: inline; filename="' . rawurlencode($failas) . '"');
}
header('Content-Length: ' . strlen($pdf_data));
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $pdf_data;
exit;
