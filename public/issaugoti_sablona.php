<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$user = currentUser();
if (($user['role'] ?? '') !== 'admin') {
    header('Location: /index.php');
    exit;
}

$filtro_grupe = $_POST['grupe'] ?? $_GET['grupe'] ?? 'MT';
$gaminiu_rusis_id = (int)($_POST['gaminiu_rusis_id'] ?? 2);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sablonas_funkciniai.php?grupe=' . urlencode($filtro_grupe));
    exit;
}

$pavadinimai = $_POST['pavadinimas'] ?? [];

$filtruoti = [];
foreach ($pavadinimai as $pav) {
    $pav = trim($pav);
    if ($pav !== '') $filtruoti[] = $pav;
}

$conn = Database::getConnection();

try {
    $conn->beginTransaction();

    $del = $conn->prepare("DELETE FROM mt_funkciniu_sablonas WHERE gaminiu_rusis_id = ?");
    $del->execute([$gaminiu_rusis_id]);

    $stmt = $conn->prepare("INSERT INTO mt_funkciniu_sablonas (eil_nr, pavadinimas, gaminiu_rusis_id) VALUES (:eil_nr, :pavadinimas, :gaminiu_rusis_id)");

    foreach ($filtruoti as $i => $pav) {
        $stmt->execute([
            ':eil_nr' => $i + 1,
            ':pavadinimas' => $pav,
            ':gaminiu_rusis_id' => $gaminiu_rusis_id
        ]);
    }

    $conn->commit();
    header('Location: /sablonas_funkciniai.php?grupe=' . urlencode($filtro_grupe) . '&issaugota=1');
} catch (Exception $e) {
    $conn->rollBack();
    header('Location: /sablonas_funkciniai.php?grupe=' . urlencode($filtro_grupe) . '&klaida=' . urlencode('Klaida saugant: ' . $e->getMessage()));
}
exit;
