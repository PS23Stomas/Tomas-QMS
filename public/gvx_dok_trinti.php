<?php
/**
 * AJAX: ištrina gvx_dokumentai įrašą (paso/protokolo PDF).
 * POST: id, _csrf
 * Prieiga: tik administratorius
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
header('Content-Type: application/json');

if (currentUser()['role'] !== 'administratorius') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'klaida' => 'Prieiga uždrausta']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'klaida' => 'Tik POST']);
    exit;
}

try { csrfVerify(); } catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'klaida' => 'CSRF klaida']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'klaida' => 'Neteisingas ID']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM gvx_dokumentai WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'klaida' => 'Įrašas nerastas']);
}
