<?php
require_once __DIR__ . '/includes/config.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$gaminio_id = isset($_GET['gaminio_id']) ? (int)$_GET['gaminio_id'] : 0;
$eil_nr     = isset($_GET['eil_nr'])     ? (int)$_GET['eil_nr']     : 0;

if ($gaminio_id <= 0 || $eil_nr <= 0) {
    http_response_code(400);
    exit('Trūksta parametrų');
}

$conn = Database::getConnection();

$stmt = $conn->prepare("SELECT defekto_nuotrauka, defekto_nuotraukos_pavadinimas FROM funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr = ?");
$stmt->execute([$gaminio_id, $eil_nr]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['defekto_nuotrauka'])) {
    http_response_code(404);
    exit('Nuotrauka nerasta');
}

$data = $row['defekto_nuotrauka'];
if (is_resource($data)) {
    $data = stream_get_contents($data);
}

$pavadinimas = $row['defekto_nuotraukos_pavadinimas'] ?? 'nuotrauka.jpg';
$ext = strtolower(pathinfo($pavadinimas, PATHINFO_EXTENSION));
$mime_types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
];
$mime = $mime_types[$ext] ?? 'image/jpeg';

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));

if (!isset($_GET['thumb'])) {
    header('Content-Disposition: inline; filename="' . $pavadinimas . '"');
}

echo $data;
