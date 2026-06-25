<?php
/**
 * Funkcinių bandymų šablono išsaugojimo tvarkyklė (tik admin, tik POST)
 *
 * Šis failas išsaugo funkcinių bandymų reikalavimų šabloną duomenų bazėje.
 * Šablonas — tai sąrašas tikrinimo punktų (pvz. "Varžtų priveržimas",
 * "Izoliacijos patikrinimas"), kurie automatiškai įkeliami kuriant naujus
 * funkcinių bandymų protokolus.
 *
 * Kaip veikia:
 *   1. Priima POST masyvą $_POST['pavadinimas'] — kiekvienas elementas
 *      yra vienas šablono reikalavimas
 *   2. Filtruojami tušti pavadinimai (trim + patikrinimas)
 *   3. Transakcijoje: ištrinama visa senoji šablono versija,
 *      tada įrašomi nauji reikalavimai su eilės numeriais (eil_nr = 1, 2, 3...)
 *   4. Sėkmės atveju → grįžtama į sablonas_funkciniai.php su &issaugota=1
 *   5. Klaidos atveju → rollback ir grįžtama su &klaida=... pranešimu
 *
 * Transakcija naudojama todėl, kad tarpinis būsena (ištrinta, bet dar neįrašyta)
 * niekada nebūtų matoma kitiems vartotojams.
 *
 * Prieiga: tik admin rolė. Tik POST metodas (GET nukreipia atgal į šablono puslapį).
 * Modulio filtras: POST['grupe'] ir POST['gaminiu_rusis_id'] nurodo kuriam moduliui
 * (pvz. MT, USN) priklauso šablonas.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();
Sesija::blokuotiSkaitytojaVeiksma(); // skaitytojas negali rašyti

/* Tik adminai gali keisti šabloną */
$user = currentUser();
if (($user['role'] ?? '') !== 'administratorius') {
    header('Location: /index.php');
    exit;
}

/* Modulio grupė (pvz. 'MT') ir jos DB ID — naudojami filtruojant šabloną */
$filtro_grupe = $_POST['grupe'] ?? $_GET['grupe'] ?? 'MT';
$gaminiu_rusis_id = (int)($_POST['gaminiu_rusis_id'] ?? 2);

/* GET užklausos nukreipiamos atgal — šis failas skirtas tik formos pateikimui */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sablonas_funkciniai.php?grupe=' . urlencode($filtro_grupe));
    exit;
}

/* Nuskaitome reikalavimų pavadinimų masyvą ir filtruojame tuščias eilutes */
$pavadinimai = $_POST['pavadinimas'] ?? [];

$filtruoti = [];
foreach ($pavadinimai as $pav) {
    $pav = trim($pav);
    if ($pav !== '') $filtruoti[] = $pav;
}

$conn = Database::getConnection();

try {
    $conn->beginTransaction();

    /* Ištriname visą senąjį šabloną šiam moduliui */
    $del = $conn->prepare("DELETE FROM funkciniu_sablonas WHERE gaminiu_rusis_id = ?");
    $del->execute([$gaminiu_rusis_id]);

    /* Įrašome naujus reikalavimus su eilės numeriais 1, 2, 3... */
    $stmt = $conn->prepare("INSERT INTO funkciniu_sablonas (eil_nr, pavadinimas, gaminiu_rusis_id) VALUES (:eil_nr, :pavadinimas, :gaminiu_rusis_id)");

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
    /* Klaidos atveju atšaukiame visus pakeitimus ir rodomee klaidos pranešimą */
    $conn->rollBack();
    header('Location: /sablonas_funkciniai.php?grupe=' . urlencode($filtro_grupe) . '&klaida=' . urlencode('Klaida saugant: ' . $e->getMessage()));
}
exit;
