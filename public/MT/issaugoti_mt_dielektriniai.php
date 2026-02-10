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

try {
    $conn->beginTransaction();

    $conn->prepare("DELETE FROM antriniu_grandiniu_bandymai WHERE gaminys_id = ?")->execute([$gaminys_id]);

    if (!empty($_POST['vid_itampa']['aprasymas'])) {
        $stmt = $conn->prepare("INSERT INTO antriniu_grandiniu_bandymai 
            (gaminys_id, eiles_nr, grandines_pavadinimas, grandines_itampa, \"bandymo_itampa_kV\", bandymo_trukme, isvada, rezultatas) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $vid_isvada = $_POST['vid_itampa']['isvada'] ?? '';

        foreach ($_POST['vid_itampa']['aprasymas'] as $i => $apras) {
            $stmt->execute([
                $gaminys_id,
                $_POST['vid_itampa']['eiles_nr'][$i] ?? ($i + 1),
                $apras,
                $_POST['vid_itampa']['itampa'][$i] ?? '',
                $_POST['vid_itampa']['band_itampa'][$i] ?? '',
                $_POST['vid_itampa']['trukme'][$i] ?? '',
                $vid_isvada,
                'bandymą išlaikė'
            ]);
        }
    }

    $conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id = ?")->execute([$gaminys_id]);

    if (!empty($_POST['maz_itampa']['aprasymas'])) {
        $stmt2 = $conn->prepare("INSERT INTO mt_dielektriniai_bandymai 
            (gaminys_id, eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['maz_itampa']['aprasymas'] as $i => $apras) {
            $stmt2->execute([
                $gaminys_id,
                $_POST['maz_itampa']['eiles_nr'][$i] ?? '',
                $apras,
                $_POST['maz_itampa']['itampa'][$i] ?? '',
                $_POST['maz_itampa']['schema1'][$i] ?? '',
                $_POST['maz_itampa']['schema2'][$i] ?? '',
                $_POST['maz_itampa']['schema3'][$i] ?? '',
                $_POST['maz_itampa']['schema4'][$i] ?? '',
                $_POST['maz_itampa']['schema5'][$i] ?? '',
                $_POST['maz_itampa']['schema6'][$i] ?? '',
                $_POST['maz_itampa']['isvada'] ?? ''
            ]);
        }
    }

    $conn->prepare("DELETE FROM mt_izeminimo_tikrinimas WHERE gaminys_id = ?")->execute([$gaminys_id]);

    if (!empty($_POST['izeminimo']['taskas'])) {
        $stmt3 = $conn->prepare("INSERT INTO mt_izeminimo_tikrinimas 
            (gaminys_id, eil_nr, tasko_pavadinimas, matavimo_tasku_skaicius, varza_ohm, budas, bukle) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['izeminimo']['taskas'] as $i => $taskas) {
            $stmt3->execute([
                $gaminys_id,
                $_POST['izeminimo']['eil_nr'][$i] ?? '',
                $taskas,
                $_POST['izeminimo']['tasku_skaicius'][$i] ?? '',
                $_POST['izeminimo']['varza'][$i] ?? '',
                $_POST['izeminimo']['budas'][$i] ?? '',
                $_POST['izeminimo']['bukle'][$i] ?? ''
            ]);
        }
    }

    $conn->commit();

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Klaida saugant dielektrinius: " . htmlspecialchars($e->getMessage());
    exit;
}

header("Location: /MT/mt_dielektriniai.php?gaminys_id=$gaminys_id" .
    "&gaminio_numeris=" . urlencode($gaminio_numeris) .
    "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
    "&uzsakovas=" . urlencode($uzsakovas) .
    "&gaminio_pavadinimas=" . urlencode($gaminio_pavadinimas) .
    "&issaugota=taip");
exit;
