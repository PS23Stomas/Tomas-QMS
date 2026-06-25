<?php
/**
 * Bandymams naudotų matavimo prietaisų išsaugojimo tvarkyklė
 *
 * Šis failas priima matavimo prietaiso duomenis ir juos išsaugo duomenų bazėje.
 * Matavimo prietaisai — tai įrenginiai, naudojami atliekant dielektrinius bandymus
 * (pvz. megaohmmetrai, aukštos įtampos šaltiniai). Kiekvienam prietaisui saugoma:
 * tipas, serijos numeris, kalibravo data, galiojimo data ir sertifikato numeris.
 *
 * Išsaugojimo logika:
 * - Jei prietaiso ID pateiktas — ATNAUJINAMAS esamas prietaiso įrašas
 * - Jei prietaiso ID nėra — SUKURIAMAS naujas prietaiso įrašas
 *
 * Po išsaugojimo nukreipia atgal į dielektrinių bandymų puslapį.
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';
require_once __DIR__ . '/../klases/TomoQMS.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();
Sesija::blokuotiSkaitytojaVeiksma(); // skaitytojas negali rašyti

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Tik POST užklausos leidžiamos');
}

$conn = Database::getConnection();

// POST duomenų gavimas
$gaminio_id       = (int)($_POST['gaminio_id'] ?? 0);
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';
$uzsakymo_id_val  = $_POST['uzsakymo_id'] ?? '';
$prietaiso_id     = (int)($_POST['prietaiso_id'] ?? 0);

if ($gaminio_id <= 0) {
    die('Klaida: nėra gaminio ID');
}

try {
    if ($prietaiso_id > 0) {
        // Esamo prietaiso atnaujinimas pagal ID
        $stmt = $conn->prepare("UPDATE bandymai_prietaisai 
            SET prietaiso_tipas = ?, prietaiso_nr = ?, patikra_data = ?, galioja_iki = ?, sertifikato_nr = ?
            WHERE id = ? AND gaminys_id = ?");
        $stmt->execute([
            $_POST['prietaiso_tipas'] ?? '',
            $_POST['prietaiso_nr'] ?? '',
            $_POST['patikra_data'] ?: null,
            $_POST['galioja_iki'] ?: null,
            $_POST['sertifikato_nr'] ?? '',
            $prietaiso_id,
            $gaminio_id
        ]);
    } else {
        // Naujo prietaiso įterpimas
        $stmt = $conn->prepare("INSERT INTO bandymai_prietaisai 
            (gaminys_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $gaminio_id,
            $_POST['prietaiso_tipas'] ?? '',
            $_POST['prietaiso_nr'] ?? '',
            $_POST['patikra_data'] ?: null,
            $_POST['galioja_iki'] ?: null,
            $_POST['sertifikato_nr'] ?? ''
        ]);
    }

    header("Location: /MT/mt_dielektriniai.php?gaminys_id=" . $gaminio_id .
           "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
           "&uzsakovas=" . urlencode($uzsakovas) .
           "&uzsakymo_id=" . urlencode($uzsakymo_id_val) .
           "&issaugota=taip");
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Klaida saugant prietaisą: " . htmlspecialchars($e->getMessage());
}
