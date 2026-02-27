<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

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
