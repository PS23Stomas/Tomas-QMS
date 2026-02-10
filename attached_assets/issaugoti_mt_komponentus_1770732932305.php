<?php
/**
 * MT komponentų išsaugojimas į duomenų bazę
 * 
 * Šis failas apdoroja POST užklausą ir išsaugo pasirinktą komponentą
 * į mt_komponentai lentelę. Jei komponentas jau egzistuoja su tuo pačiu
 * eilės numeriu, jis ištrinamas ir įrašomas naujas.
 * 
 * Veikimo principas:
 * - Gauna formos duomenis iš POST užklausos
 * - Nustato kurią eilutę reikia išsaugoti
 * - Ištrina esamą įrašą su tuo pačiu eilės numeriu
 * - Įterpia naują komponentą į duomenų bazę
 * - Nukreipia atgal į komponentų sąrašą
 */
require_once '../klases/Database.php';
require_once '../klases/Sesija.php';
session_start();

/**
 * Duomenų bazės prisijungimo inicializavimas
 */
$pdo = (new Database())->getConnection();

/**
 * POST duomenų nuskaitymas
 * Gaunami visi formos laukai: gaminio ID, eilės numeriai, kodai, kiekiai ir kt.
 */
$gaminio_id = $_POST['gaminio_id'] ?? '';
$eiles_numeriai = $_POST['eile_id'] ?? [];
$kodasai = $_POST['kodas'] ?? [];
$nauji_kodai = $_POST['kodas_naujas'] ?? [];
$kiekiai = $_POST['kiekis'] ?? [];
$aprasymai = $_POST['aprasymas'] ?? [];
$gamintojai = $_POST['gamintojas'] ?? [];
$nauji_gamintojai = $_POST['gamintojas_naujas'] ?? [];

$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas = $_POST['uzsakovas'] ?? '';

/**
 * Nustatoma kuri eilutė pažymėta išsaugojimui
 * Jei jokia eilutė nepasirinkta, rodomas klaidos pranešimas
 */
$saugoti_eile_id = $_POST['saugoti'][0] ?? null;
if ($saugoti_eile_id === null) {
    die("Nepasirinkta jokia eilutė.");
}

/**
 * Randamas pasirinktos eilutės indeksas masyve
 */
$indeksas = array_search($saugoti_eile_id, $eiles_numeriai);
if ($indeksas === false) {
    die("Nepavyko rasti pasirinktų duomenų.");
}

/**
 * Ištraukiami vienos pasirinktos eilutės duomenys
 * Jei įvestas naujas kodas arba gamintojas, jis naudojamas vietoj pasirinkto
 */
$eile_id = $eiles_numeriai[$indeksas];
$kodas = trim($kodasai[$indeksas] ?? '');
$kodas_naujas = trim($nauji_kodai[$indeksas] ?? '');
$kiekis = intval($kiekiai[$indeksas] ?? 0);
$aprasymas = trim($aprasymai[$indeksas] ?? '');
$gamintojas = trim($gamintojai[$indeksas] ?? '');
$gamintojas_naujas = trim($nauji_gamintojai[$indeksas] ?? '');

if ($kodas_naujas !== '') $kodas = $kodas_naujas;
if ($gamintojas_naujas !== '') $gamintojas = $gamintojas_naujas;

/**
 * Ištrinamas esamas komponentas su tuo pačiu eilės numeriu
 */
$stmt = $pdo->prepare("DELETE FROM mt_komponentai WHERE gaminio_id = ? AND eiles_numeris = ?");
$stmt->execute([$gaminio_id, $eile_id]);

/**
 * Įterpiamas naujas komponentas į duomenų bazę
 * parinkta_projektui nustatomas į 1 (true)
 */
$stmt = $pdo->prepare("INSERT INTO mt_komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui)
                       VALUES (?, ?, ?, ?, ?, ?, 1)");
$stmt->execute([$gaminio_id, $eile_id, $kodas, $kiekis, $aprasymas, $gamintojas]);

/**
 * Nukreipiama atgal į komponentų sąrašo puslapį su sėkmės pranešimu
 */
header("Location: mt_sumontuoti_komponentai.php?gaminio_id=" . urlencode($gaminio_id) .
       "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
       "&uzsakovas=" . urlencode($uzsakovas) .
       "&issaugota=taip&parinkta_eile=" . urlencode($eile_id));
exit;
