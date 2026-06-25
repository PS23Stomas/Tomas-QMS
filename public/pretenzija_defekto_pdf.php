<?php
/**
 * Pretenzijos defekto PDF peržiūra ir atsisiuntimas (VIEŠAS)
 *
 * Grąžina defekto PDF dokumentą tiesiai naršyklei iš duomenų bazės.
 * Defekto PDF — tai prie pretenzijos pridėtas išorinis PDF failas
 * (pvz. nuskaitytas defekto aktas, gamintojo sertifikatas ir pan.),
 * saugomas pretenzijos lentelės laukuose:
 *   - defekto_pdf_turinys   — BYTEA, binarinis PDF turinys
 *   - defekto_pdf_pavadinimas — failo pavadinimas atsisiuntimui
 *
 * Priima GET parametrą:
 *   - id — pretenzijos ID iš pretenzijos lentelės
 *
 * Veikimas:
 *   Nuskaito PDF iš DB ir siunčia su Content-Type: application/pdf.
 *   Inline peržiūra naršyklėje (ne atsisiuntimas) — vartotojas mato PDF.
 *
 * Prieiga: VIEŠAS puslapis (nereikalauja prisijungimo) —
 *   naudojamas kaip nuoroda el. laiškuose gavėjams.
 */
require_once __DIR__ . '/includes/config.php';

$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Nenurodytas pretenzijos ID';
    exit;
}

$stmt = $pdo->prepare("SELECT defekto_pdf_pavadinimas, defekto_pdf_turinys, perziuros_token FROM pretenzijos WHERE id = ? AND defekto_pdf_turinys IS NOT NULL");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['defekto_pdf_turinys'])) {
    http_response_code(404);
    echo 'PDF nerastas';
    exit;
}

// Prieiga: prisijungęs vartotojas ARBA galiojantis pretenzijos peržiūros token
if (!isLoggedIn()) {
    $token = trim($_GET['token'] ?? '');
    if ($token === '' || !hash_equals((string)($row['perziuros_token'] ?? ''), $token)) {
        http_response_code(403);
        echo 'Prieiga negalima';
        exit;
    }
}

$pavadinimas = $row['defekto_pdf_pavadinimas'] ?: 'defektas.pdf';
$turinys = is_resource($row['defekto_pdf_turinys']) ? stream_get_contents($row['defekto_pdf_turinys']) : $row['defekto_pdf_turinys'];

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($pavadinimas) . '"');
header('Content-Length: ' . strlen($turinys));
echo $turinys;
exit;
