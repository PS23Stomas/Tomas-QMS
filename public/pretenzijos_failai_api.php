<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$veiksmas = $_GET['veiksmas'] ?? $_POST['veiksmas'] ?? '';
$pretenzija_id = (int)($_GET['pretenzija_id'] ?? $_POST['pretenzija_id'] ?? 0);

if ($pretenzija_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Nenurodytas pretenzijos ID']);
    exit;
}

if ($veiksmas === 'sarasas') {
    $stmt = $pdo->prepare("SELECT id, pavadinimas, tipas, ikelta FROM pretenzijos_failai WHERE pretenzija_id = :pid ORDER BY id");
    $stmt->execute([':pid' => $pretenzija_id]);
    $failai = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'failai' => $failai]);
    exit;
}

if ($veiksmas === 'trinti' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $failo_id = (int)($_POST['failo_id'] ?? 0);
    if ($failo_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM pretenzijos_failai WHERE id = :id AND pretenzija_id = :pid");
        $stmt->execute([':id' => $failo_id, ':pid' => $pretenzija_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenurodytas failo ID']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Nežinomas veiksmas']);
