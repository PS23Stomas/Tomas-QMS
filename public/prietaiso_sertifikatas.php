<?php
/**
 * Prietaiso kalibravimo sertifikato PDF peržiūra ir atsisiuntimas
 *
 * Nuskaito kalibravimo sertifikato PDF failą iš duomenų bazės ir
 * grąžina jį naršyklei peržiūrai arba atsisiuntimui.
 * Sertifikatas saugomas prietaisai.sertifikato_pdf lauke (BYTEA).
 *
 * Priima GET parametrą:
 *   - id — prietaiso ID iš prietaisai lentelės
 *
 * Veikimas:
 *   Nustato Content-Type: application/pdf ir Content-Disposition: inline
 *   (PDF atidaromas naršyklėje, ne atsiunčiamas). Failo pavadinimas
 *   paimamas iš prietaisai.sertifikato_pdf_pavadinimas lauko.
 *
 * Prieiga: reikalauja prisijungimo.
 * Naudojama: prietaisai.php puslapyje prie kiekvieno prietaiso „PDF" nuorodos.
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(404);
    exit('Prietaisas nerastas');
}

$stmt = $pdo->prepare("SELECT sertifikato_pdf, sertifikato_failas FROM prietaisai WHERE id = :id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row || empty($row['sertifikato_pdf'])) {
    http_response_code(404);
    exit('Sertifikato failas nerastas');
}

$failas = $row['sertifikato_failas'] ?: 'sertifikatas.pdf';
$failas = preg_replace('/[^a-zA-Z0-9._\-\(\) ]/', '', $failas);
if (empty($failas)) $failas = 'sertifikatas.pdf';

$pdf_data = is_resource($row['sertifikato_pdf']) ? stream_get_contents($row['sertifikato_pdf']) : $row['sertifikato_pdf'];

$action = $_GET['action'] ?? 'view';

header('Content-Type: application/pdf');
if ($action === 'download') {
    header('Content-Disposition: attachment; filename="' . $failas . '"');
} else {
    header('Content-Disposition: inline; filename="' . $failas . '"');
}
header('Content-Length: ' . strlen($pdf_data));
echo $pdf_data;
exit;
