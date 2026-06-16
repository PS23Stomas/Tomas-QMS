<?php
/**
 * AJAX endpoint: grąžina gaminių sąrašą pagal užsakymo ID
 * Naudojama PDF įkėlimo modalo gaminio parinkimui iš uzsakymai.php
 *
 * GET parametrai:
 *   uzsakymo_id — užsakymo ID (sveikasis skaičius)
 *
 * Grąžina: JSON masyvas [{id, gaminio_numeris, pavadinimas}, ...]
 */
require_once __DIR__ . '/../includes/config.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$uzsakymo_id = (int)($_GET['uzsakymo_id'] ?? 0);
if ($uzsakymo_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenurodytas užsakymo ID']);
    exit;
}

$conn = Database::getConnection();
$stmt = $conn->prepare(
    "SELECT id, gaminio_numeris, pavadinimas FROM gaminiai WHERE uzsakymo_id = ? ORDER BY id ASC"
);
$stmt->execute([$uzsakymo_id]);
$gaminiai = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($gaminiai, JSON_UNESCAPED_UNICODE);
