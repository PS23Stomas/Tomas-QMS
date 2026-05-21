<?php
/**
 * API: Vartotojo parašo paveikslėlio gavimas
 *
 * Šis API galinis taškas grąžina vartotojo parašo paveikslėlį tiesiai
 * iš duomenų bazės. Parašas saugomas kaip binariniai duomenys (BYTEA)
 * lentelėje vartotojai, lauke "parasas".
 *
 * Parašas naudojamas PDF dokumentuose (funkcinių bandymų protokolas,
 * dielektrinių bandymų protokolas, MT pasas) — po dokumentu automatiškai
 * įterpiamas atsakingo darbuotojo parašas.
 *
 * Priima: GET užklausą su parametru:
 *   - id  — vartotojo ID iš lentelės vartotojai
 *
 * Kaip veikia:
 *   1. Nuskaito parašo binarynius duomenis ir jų MIME tipą iš DB
 *   2. Nustato teisingą Content-Type antraštę (pvz. image/jpeg arba image/png)
 *   3. Siunčia paveikslėlio duomenis tiesiai į naršyklę
 *   4. Nustato talpyklą 1 valandai (Cache-Control: max-age=3600) —
 *      toks pat parašas nereikia kaskart siųsti iš naujo
 *
 * Grąžina:
 *   Paveikslėlio duomenis (image/jpeg arba image/png) — jei parašas rastas
 *   HTTP 404 — jei vartotojas neturi parašo arba ID neteisingas
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare("SELECT parasas, parasas_tipas FROM vartotojai WHERE id = ? AND parasas IS NOT NULL");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['parasas'])) {
    http_response_code(404);
    exit;
}

$mime = $row['parasas_tipas'] ?: 'image/jpeg';
$data = $row['parasas'];
if (is_resource($data)) {
    $data = stream_get_contents($data);
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
echo $data;
