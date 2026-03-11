<?php
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
