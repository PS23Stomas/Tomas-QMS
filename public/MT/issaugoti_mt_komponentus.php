<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

$gaminio_id       = $_POST['gaminio_id'] ?? '';
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';

$eile_ids     = $_POST['eile_id'] ?? [];
$kodai        = $_POST['kodas'] ?? [];
$kodai_nauji  = $_POST['kodas_naujas'] ?? [];
$kiekiai      = $_POST['kiekis'] ?? [];
$aprasymai    = $_POST['aprasymas'] ?? [];
$gamintojai   = $_POST['gamintojas'] ?? [];
$gamintojai_n = $_POST['gamintojas_naujas'] ?? [];

$conn->prepare("DELETE FROM mt_komponentai WHERE gaminio_id = ?")->execute([$gaminio_id]);

$sql = "INSERT INTO mt_komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?, ?, ?, ?, ?, ?, 1)";
$stmt = $conn->prepare($sql);

for ($i = 0; $i < count($eile_ids); $i++) {
    $kodas = !empty($kodai_nauji[$i]) ? $kodai_nauji[$i] : ($kodai[$i] ?? '');
    $gamintojas = !empty($gamintojai_n[$i]) ? $gamintojai_n[$i] : ($gamintojai[$i] ?? '');
    
    $stmt->execute([
        $gaminio_id,
        (int)$eile_ids[$i],
        $kodas,
        (int)($kiekiai[$i] ?? 0),
        $aprasymai[$i] ?? '',
        $gamintojas
    ]);
}

header("Location: /MT/mt_sumontuoti_komponentai.php?gaminio_id=" . urlencode($gaminio_id) . 
       "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) . 
       "&uzsakovas=" . urlencode($uzsakovas) . 
       "&issaugota=taip");
exit;
