<?php
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

$gaminio_id       = (int)($_POST['gaminio_id'] ?? 0);
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';

$vardas  = $_SESSION['vardas'] ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';
$pilnas_vardas = $vardas . ' ' . $pavarde;

if ($gaminio_id <= 0) {
    die('Klaida: nėra gaminio ID');
}

$reikalavimai = $_POST['reikalavimas'] ?? [];
$eil_nriai    = $_POST['eil_nr'] ?? [];
$isvados      = $_POST['isvada'] ?? [];
$defektai     = $_POST['defektas'] ?? [];
$atliko       = $_POST['darba_atliko'] ?? [];

$conn->prepare("DELETE FROM mt_funkciniai_bandymai WHERE gaminio_id = ?")->execute([$gaminio_id]);

$sql = "INSERT INTO mt_funkciniai_bandymai (gaminio_id, eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

foreach ($reikalavimai as $i => $reik) {
    $stmt->execute([
        $gaminio_id,
        (int)($eil_nriai[$i] ?? ($i + 1)),
        $reik,
        $isvados[$i] ?? 'nepadaryta',
        $defektai[$i] ?? '',
        $atliko[$i] ?? '',
        $pilnas_vardas
    ]);
}

header("Location: /mt_funkciniai_bandymai.php?gaminio_id={$gaminio_id}&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) . "&uzsakovas=" . urlencode($uzsakovas) . "&issaugota=taip");
exit;
