<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

$gaminys_id        = (int)($_POST['gaminys_id'] ?? 0);
$gaminio_numeris   = $_POST['gaminio_numeris'] ?? '';
$uzsakymo_numeris  = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas         = $_POST['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? '';

if ($gaminys_id <= 0) die('Klaida: nėra gaminio ID');

$conn->prepare("DELETE FROM antriniu_grandiniu_bandymai WHERE gaminys_id = ?")->execute([$gaminys_id]);

if (isset($_POST['vid_itampa']['eiles_nr'])) {
    $sql = "INSERT INTO antriniu_grandiniu_bandymai (gaminys_id, eiles_nr, grandines_pavadinimas, grandines_itampa, bandymo_itampa_kV, bandymo_trukme, isvada) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $isvada = $_POST['vid_itampa']['isvada'] ?? '';
    
    for ($i = 0; $i < count($_POST['vid_itampa']['eiles_nr']); $i++) {
        $stmt->execute([
            $gaminys_id,
            (int)$_POST['vid_itampa']['eiles_nr'][$i],
            $_POST['vid_itampa']['aprasymas'][$i] ?? '',
            $_POST['vid_itampa']['itampa'][$i] ?? '',
            $_POST['vid_itampa']['band_itampa'][$i] ?? '',
            $_POST['vid_itampa']['trukme'][$i] ?? '',
            $isvada
        ]);
    }
}

$conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id = ?")->execute([$gaminys_id]);

if (isset($_POST['maz_itampa']['eiles_nr'])) {
    $sql = "INSERT INTO mt_dielektriniai_bandymai (gaminys_id, eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $isvada = $_POST['maz_itampa']['isvada'] ?? '';
    
    for ($i = 0; $i < count($_POST['maz_itampa']['eiles_nr']); $i++) {
        $stmt->execute([
            $gaminys_id,
            (int)$_POST['maz_itampa']['eiles_nr'][$i],
            $_POST['maz_itampa']['aprasymas'][$i] ?? '',
            $_POST['maz_itampa']['itampa'][$i] ?? '',
            $_POST['maz_itampa']['schema1'][$i] ?? '',
            $_POST['maz_itampa']['schema2'][$i] ?? '',
            $_POST['maz_itampa']['schema3'][$i] ?? '',
            $_POST['maz_itampa']['schema4'][$i] ?? '',
            $_POST['maz_itampa']['schema5'][$i] ?? '',
            $_POST['maz_itampa']['schema6'][$i] ?? '',
            $isvada
        ]);
    }
}

$conn->prepare("DELETE FROM mt_izeminimo_tikrinimas WHERE gaminys_id = ?")->execute([$gaminys_id]);

if (isset($_POST['izeminimo']['eil_nr'])) {
    $sql = "INSERT INTO mt_izeminimo_tikrinimas (gaminys_id, eil_nr, tasko_pavadinimas, matavimo_tasku_skaicius, varza_ohm, budas, bukle) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    for ($i = 0; $i < count($_POST['izeminimo']['eil_nr']); $i++) {
        $stmt->execute([
            $gaminys_id,
            $_POST['izeminimo']['eil_nr'][$i],
            $_POST['izeminimo']['taskas'][$i] ?? '',
            (int)($_POST['izeminimo']['tasku_skaicius'][$i] ?? 0),
            $_POST['izeminimo']['varza'][$i] ?? '0.01',
            $_POST['izeminimo']['budas'][$i] ?? '',
            $_POST['izeminimo']['bukle'][$i] ?? ''
        ]);
    }
}

header("Location: /MT/mt_dielektriniai.php?gaminys_id=$gaminys_id" .
    "&gaminio_numeris=" . urlencode($gaminio_numeris) .
    "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
    "&uzsakovas=" . urlencode($uzsakovas) .
    "&gaminio_pavadinimas=" . urlencode($gaminio_pavadinimas) .
    "&issaugota=taip");
exit;
