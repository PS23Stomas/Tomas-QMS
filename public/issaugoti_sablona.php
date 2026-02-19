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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sablonas_funkciniai.php');
    exit;
}

$pavadinimai = $_POST['pavadinimas'] ?? [];

$filtruoti = [];
foreach ($pavadinimai as $pav) {
    $pav = trim($pav);
    if ($pav !== '') $filtruoti[] = $pav;
}

if (empty($filtruoti)) {
    header('Location: /sablonas_funkciniai.php?klaida=' . urlencode('Šablonas negali būti tuščias – turi būti bent vienas punktas'));
    exit;
}

$conn = Database::getConnection();

try {
    $conn->beginTransaction();

    $conn->exec("DELETE FROM mt_funkciniu_sablonas");

    $stmt = $conn->prepare("INSERT INTO mt_funkciniu_sablonas (eil_nr, pavadinimas) VALUES (:eil_nr, :pavadinimas)");

    foreach ($filtruoti as $i => $pav) {
        $stmt->execute([
            ':eil_nr' => $i + 1,
            ':pavadinimas' => $pav
        ]);
    }

    $conn->commit();
    header('Location: /sablonas_funkciniai.php?issaugota=1');
} catch (Exception $e) {
    $conn->rollBack();
    header('Location: /sablonas_funkciniai.php?klaida=' . urlencode('Klaida saugant: ' . $e->getMessage()));
}
exit;
