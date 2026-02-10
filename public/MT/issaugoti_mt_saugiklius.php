<?php
/**
 * MT saugiklių idėklų išsaugojimo tvarkyklė - sekcinis trynimas ir pakartotinis įrašymas
 *
 * Apdoroja saugiklių idėklų formos duomenis.
 * Naudojamas sekcinis trynimo + pakartotinio įrašymo šablonas su transakcija:
 *   1. Trinami visi esami įrašai pagal gaminio ID ir sekciją
 *   2. Įterpiami nauji įrašai iš formos duomenų
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Tik POST užklausos leidžiamos');
}

$conn = Database::getConnection();

// POST duomenų gavimas: gaminio ID, sekcija ir užsakymo parametrai
$gaminio_id       = intval($_POST['gaminio_id'] ?? 0);
$sekcija          = $_POST['sekcija'] ?? '';
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';

// Saugiklių duomenų masyvai iš formos
$poz_numeriai = $_POST['pozicijos_numeris'] ?? [];
$pozicijos    = $_POST['pozicijos'] ?? [];
$gabaritai    = $_POST['gabaritai'] ?? [];
$nominalai    = $_POST['nominalai'] ?? [];

try {
    // Transakcijos pradžia
    $conn->beginTransaction();

    // Esamų saugiklių trynimas pagal gaminio ID ir sekciją
    $stmt_delete = $conn->prepare("DELETE FROM mt_saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = :sekcija");
    $stmt_delete->execute([
        ':gaminio_id' => $gaminio_id,
        ':sekcija' => $sekcija
    ]);

    // Naujų saugiklių įrašymo paruošimas
    $stmt_insert = $conn->prepare("
        INSERT INTO mt_saugikliu_ideklai 
        (gaminio_id, sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris)
        VALUES (:gaminio_id, :sekcija, :pozicija, :gabaritas, :nominalas, :pozicijos_numeris)
    ");

    // Saugiklių įrašymo ciklas – praleidžiamos tuščios eilutės
    for ($i = 0; $i < count($poz_numeriai); $i++) {
        $poz_n = trim($poz_numeriai[$i] ?? '');
        $pozicija = trim($pozicijos[$i] ?? '');
        $gabaritas = trim($gabaritai[$i] ?? '');
        $nominalas = trim($nominalai[$i] ?? '');

        if ($poz_n === '' || $pozicija === '') continue;

        $stmt_insert->execute([
            ':gaminio_id' => $gaminio_id,
            ':sekcija' => $sekcija,
            ':pozicija' => $pozicija,
            ':gabaritas' => $gabaritas,
            ':nominalas' => $nominalas,
            ':pozicijos_numeris' => $poz_n
        ]);
    }

    // Transakcijos patvirtinimas
    $conn->commit();

    // Nukreipimo parametrų paruošimas
    $gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? '';
    $gaminio_numeris     = $_POST['gaminio_numeris'] ?? '';
    $uzsakymo_id_val     = $_POST['uzsakymo_id'] ?? '';

    header("Location: /MT/mt_dielektriniai.php?gaminys_id=" . $gaminio_id .
           "&gaminio_numeris=" . urlencode($gaminio_numeris) .
           "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
           "&uzsakovas=" . urlencode($uzsakovas) .
           "&gaminio_pavadinimas=" . urlencode($gaminio_pavadinimas) .
           "&uzsakymo_id=" . urlencode($uzsakymo_id_val) .
           "&issaugota=taip");
    exit;

} catch (PDOException $e) {
    // Klaidos atveju – transakcijos atšaukimas
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Klaida saugant saugiklius: " . htmlspecialchars($e->getMessage());
}
