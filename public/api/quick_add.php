<?php
/**
 * API: Greitasis užsakovo arba objekto pridėjimas
 *
 * Šis API galinis taškas leidžia darbuotojui sukurti naują užsakovą (klientą)
 * arba objektą (statybos vietą) tiesiai iš užsakymo kūrimo formos,
 * nepalieka puslapio ir nereikia atidaryti atskiro administravimo lango.
 *
 * Pavyzdys: kūriant naują užsakymą, jei reikalingo kliento sąraše nėra,
 * vartotojas įveda jo pavadinimą ir paspaudžia "Pridėti" — šis API
 * jį sukuria ir grąžina naują ID, kurį forma tuoj pat pasirenka.
 *
 * Priima: POST užklausą su JSON arba form duomenimis:
 *   - type  — "uzsakovas" (klientas) arba "objektas" (statybos vieta)
 *   - name  — pavadinimas (pvz. "UAB Statybų centras")
 *
 * Apsauga nuo dublikatų:
 *   Prieš kuriant tikrinama ar toks įrašas jau egzistuoja (neatsižvelgiant
 *   į didžiąsias/mažąsias raides ir tarpus). Jei egzistuoja — grąžinamas
 *   esamo įrašo ID vietoje klaidos, kad forma galėtų jį pasirinkti.
 *
 * Grąžina JSON:
 *   { "success": true, "id": 42, "name": "UAB Statybų centras" }
 *   { "success": false, "error": "...", "existing_id": 7 }  — jei dublikatas
 *   { "success": false, "error": "..." }                    — kitos klaidos
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();
Sesija::blokuotiSkaitytojaVeiksma(); // skaitytojas negali kurti

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Tik POST užklausos leidžiamos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$type = trim($input['type'] ?? '');
$name = trim($input['name'] ?? '');

if (!in_array($type, ['uzsakovas', 'objektas'])) {
    echo json_encode(['success' => false, 'error' => 'Neteisingas tipas']);
    exit;
}

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Pavadinimas negali būti tuščias']);
    exit;
}

try {
    if ($type === 'uzsakovas') {
        $check = $pdo->prepare('SELECT id FROM uzsakovai WHERE LOWER(TRIM(uzsakovas)) = LOWER(TRIM(:name))');
        $check->execute(['name' => $name]);
        if ($existing = $check->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'error' => 'Toks užsakovas jau egzistuoja', 'existing_id' => (int)$existing['id']]);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO uzsakovai (uzsakovas) VALUES (:name) RETURNING id');
        $stmt->execute(['name' => $name]);
        $id = (int)$stmt->fetchColumn();
    } else {
        $check = $pdo->prepare('SELECT id FROM objektai WHERE LOWER(TRIM(pavadinimas)) = LOWER(TRIM(:name))');
        $check->execute(['name' => $name]);
        if ($existing = $check->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'error' => 'Toks objektas jau egzistuoja', 'existing_id' => (int)$existing['id']]);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO objektai (pavadinimas) VALUES (:name) RETURNING id');
        $stmt->execute(['name' => $name]);
        $id = (int)$stmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
} catch (Exception $e) {
    error_log('quick_add.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Įvyko serverio klaida. Bandykite dar kartą.']);
}
