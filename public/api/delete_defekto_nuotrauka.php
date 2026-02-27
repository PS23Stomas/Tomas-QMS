<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Tik POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$gaminio_id = (int)($input['gaminio_id'] ?? 0);
$eil_nr = (int)($input['eil_nr'] ?? 0);

if ($gaminio_id <= 0 || $eil_nr <= 0) {
    echo json_encode(['success' => false, 'error' => 'Trūksta parametrų']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE mt_funkciniai_bandymai SET defekto_nuotrauka = NULL, defekto_nuotraukos_pavadinimas = NULL WHERE gaminio_id = :gid AND eil_nr = :enr AND defekto_nuotrauka IS NOT NULL");
    $stmt->execute(['gid' => $gaminio_id, 'enr' => $eil_nr]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Nuotrauka nerasta']);
    }
} catch (Exception $e) {
    error_log('delete_defekto_nuotrauka error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverio klaida']);
}
