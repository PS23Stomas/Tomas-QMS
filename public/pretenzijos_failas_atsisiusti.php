<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Failo ID nenurodytas';
    exit;
}

$stmt = $pdo->prepare("SELECT pavadinimas, tipas, turinys FROM pretenzijos_failai WHERE id = :id");
$stmt->execute([':id' => $id]);
$failas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$failas) {
    http_response_code(404);
    echo 'Failas nerastas';
    exit;
}

$turinys = is_resource($failas['turinys']) ? stream_get_contents($failas['turinys']) : $failas['turinys'];
$tipas = $failas['tipas'] ?: 'application/octet-stream';

header('Content-Type: ' . $tipas);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $failas['pavadinimas']) . '"');
header('Content-Length: ' . strlen($turinys));
echo $turinys;
