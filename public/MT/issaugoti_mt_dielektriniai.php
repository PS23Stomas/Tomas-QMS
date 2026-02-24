<?php
/**
 * MT dielektrinių bandymų išsaugojimo tvarkyklė - žemos įtampos bandymai ir įžeminimas
 *
 * Apdoroja dielektrinių bandymų formos duomenis iš mt_dielektriniai.php.
 * Naudojamas trynimo + pakartotinio įrašymo šablonas su duomenų bazės transakcija.
 * Išsaugomi: žemos įtampos bandymai ir įžeminimo tikrinimo duomenys.
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';
require_once __DIR__ . '/../klases/TomoQMS.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

// POST duomenų gavimas
$gaminys_id        = (int)($_POST['gaminys_id'] ?? 0);
$gaminio_numeris   = $_POST['gaminio_numeris'] ?? '';
$uzsakymo_numeris  = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas         = $_POST['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? '';
$uzsakymo_id       = $_POST['uzsakymo_id'] ?? '';
$grupe             = $_POST['grupe'] ?? 'MT';

if ($gaminys_id <= 0) die('Klaida: nėra gaminio ID');

try {
    // Transakcijos pradžia – užtikrinamas duomenų vientisumas
    $conn->beginTransaction();

    // Esamų visų dielektrinių bandymų trynimas prieš pakartotinį įrašymą
    $conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id = ?")->execute([$gaminys_id]);

    // Vidutinės įtampos bandymų duomenų įrašymas (tipas = vidutines_itampos)
    if (!empty($_POST['vid_itampa']['aprasymas'])) {
        $stmt_vid = $conn->prepare("INSERT INTO mt_dielektriniai_bandymai 
            (gaminys_id, eiles_nr, aprasymas, itampa, isvada, tipas, grandines_pavadinimas, grandines_itampa, bandymo_schema, bandymo_itampa_kv, bandymo_trukme) 
            VALUES (?, ?, ?, ?, ?, 'vidutines_itampos', ?, ?, ?, ?, ?)");

        foreach ($_POST['vid_itampa']['aprasymas'] as $i => $apras) {
            $stmt_vid->execute([
                $gaminys_id,
                $_POST['vid_itampa']['eiles_nr'][$i] ?? '',
                $apras,
                $_POST['vid_itampa']['itampa'][$i] ?? '',
                $_POST['vid_itampa']['isvada'] ?? '',
                $apras,
                $_POST['vid_itampa']['itampa'][$i] ?? '',
                $_POST['vid_itampa']['schema1'][$i] ?? '',
                $_POST['vid_itampa']['band_itampa'][$i] ?? '',
                $_POST['vid_itampa']['trukme'][$i] ?? ''
            ]);
        }
    }

    // Žemos įtampos bandymų duomenų įrašymas (tipas = mazos_itampos)
    if (!empty($_POST['maz_itampa']['aprasymas'])) {
        $stmt2 = $conn->prepare("INSERT INTO mt_dielektriniai_bandymai 
            (gaminys_id, eiles_nr, aprasymas, itampa, schema1, schema2, schema3, schema4, schema5, schema6, isvada, tipas) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'mazos_itampos')");

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

    // Esamų įžeminimo tikrinimo duomenų trynimas prieš pakartotinį įrašymą
    $conn->prepare("DELETE FROM mt_izeminimo_tikrinimas WHERE gaminys_id = ?")->execute([$gaminys_id]);

    // Įžeminimo tikrinimo duomenų pakartotinis įrašymas
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

    // Transakcijos patvirtinimas – visi duomenys sėkmingai išsaugoti
    $conn->commit();

} catch (Throwable $e) {
    // Klaidos atveju – transakcijos atšaukimas (rollback)
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Klaida saugant dielektrinius: " . htmlspecialchars($e->getMessage());
    exit;
}

header("Location: /MT/mt_dielektriniai.php?" . http_build_query([
    'gaminys_id' => $gaminys_id,
    'gaminio_numeris' => $gaminio_numeris,
    'uzsakymo_numeris' => $uzsakymo_numeris,
    'uzsakovas' => $uzsakovas,
    'gaminio_pavadinimas' => $gaminio_pavadinimas,
    'uzsakymo_id' => $uzsakymo_id,
    'grupe' => $grupe,
    'issaugota' => 'taip',
    't' => time(),
]));
exit;
