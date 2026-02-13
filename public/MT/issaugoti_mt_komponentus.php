<?php
/**
 * MT komponentų išsaugojimo tvarkyklė - vienos eilutės ir masinis išsaugojimas
 *
 * Apdoroja komponentų formos duomenis iš mt_sumontuoti_komponentai.php.
 * Palaiko du režimus:
 *   1. Vienos eilutės išsaugojimas (kai paspaustas konkretus eilutės mygtukas)
 *   2. Masinis visų eilučių išsaugojimas (trynimas + pakartotinis įrašymas su transakcija)
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';
require_once __DIR__ . '/../klases/TomoQMS.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

// POST duomenų gavimas: gaminio identifikatoriai ir užsakymo parametrai
$gaminio_id       = $_POST['gaminio_id'] ?? '';
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';
$uzsakymo_id      = $_POST['uzsakymo_id'] ?? '';

// Formos masyvų gavimas: kodai, kiekiai, aprašymai, gamintojai
$eile_ids     = $_POST['eile_id'] ?? [];
$kodai        = $_POST['kodas'] ?? [];
$kodai_nauji  = $_POST['kodas_naujas'] ?? [];
$kiekiai      = $_POST['kiekis'] ?? [];
$aprasymai    = $_POST['aprasymas'] ?? [];
$gamintojai   = $_POST['gamintojas'] ?? [];
$gamintojai_n = $_POST['gamintojas_naujas'] ?? [];

// Tikrinama ar buvo paspausta konkrečios eilutės išsaugojimo mygtukas
$saugoti_eile_id = $_POST['saugoti'][0] ?? null;

// === Vienos eilutės išsaugojimo logika ===
if ($saugoti_eile_id !== null) {
    $indeksas = array_search($saugoti_eile_id, $eile_ids);
    if ($indeksas === false) {
        die("Nepavyko rasti pasirinktų duomenų.");
    }

    $eile_id = $eile_ids[$indeksas];
    $kodas = trim($kodai[$indeksas] ?? '');
    $kodas_naujas = trim($kodai_nauji[$indeksas] ?? '');
    $kiekis = intval($kiekiai[$indeksas] ?? 0);
    $aprasymas = trim($aprasymai[$indeksas] ?? '');
    $gamintojas = trim($gamintojai[$indeksas] ?? '');
    $gamintojas_naujas = trim($gamintojai_n[$indeksas] ?? '');

    // Jei įvestas naujas kodas/gamintojas – naudojamas vietoj pasirinkto iš sąrašo
    if ($kodas_naujas !== '') $kodas = $kodas_naujas;
    if ($gamintojas_naujas !== '') $gamintojas = $gamintojas_naujas;

    // Transakcijos pradžia vienos eilutės išsaugojimui
    $conn->beginTransaction();
    try {
        // Tikrinama ar jau egzistuoja įrašas su šiuo gaminio ID ir eilės numeriu
        $stmt = $conn->prepare("SELECT id FROM mt_komponentai WHERE gaminio_id = ? AND eiles_numeris = ?");
        $stmt->execute([$gaminio_id, $eile_id]);
        $esamas = $stmt->fetchColumn();

        if ($esamas) {
            // Esamo įrašo atnaujinimas (UPDATE)
            $stmt = $conn->prepare("UPDATE mt_komponentai SET gamintojo_kodas = ?, kiekis = ?, aprasymas = ?, gamintojas = ?, parinkta_projektui = 1 WHERE gaminio_id = ? AND eiles_numeris = ?");
            $stmt->execute([$kodas, $kiekis, $aprasymas, $gamintojas, $gaminio_id, $eile_id]);
        } else {
            // Naujo įrašo sukūrimas (INSERT)
            $stmt = $conn->prepare("INSERT INTO mt_komponentai (gaminio_id, eiles_numeris, gamintojo_kodas, kiekis, aprasymas, gamintojas, parinkta_projektui) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$gaminio_id, $eile_id, $kodas, $kiekis, $aprasymas, $gamintojas]);
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Klaida išsaugant: " . htmlspecialchars($e->getMessage()));
    }
    header("Location: /MT/mt_sumontuoti_komponentai.php?gaminio_id=" . urlencode($gaminio_id) .
           "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
           "&uzsakovas=" . urlencode($uzsakovas) .
           "&uzsakymo_id=" . urlencode($uzsakymo_id) .
           "&issaugota=taip&parinkta_eile=" . urlencode($eile_id));
    exit;
} else {
    // === Masinis visų eilučių išsaugojimas (trynimas + pakartotinis įrašymas) ===
    $conn->beginTransaction();
    try {
        // Visų esamų komponentų trynimas pagal gaminio ID
        $conn->prepare("DELETE FROM mt_komponentai WHERE gaminio_id = ?")->execute([$gaminio_id]);

        // Visų eilučių pakartotinis įrašymas su naujais duomenimis
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
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Klaida išsaugant: " . htmlspecialchars($e->getMessage()));
    }
    header("Location: /MT/mt_sumontuoti_komponentai.php?gaminio_id=" . urlencode($gaminio_id) .
           "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
           "&uzsakovas=" . urlencode($uzsakovas) .
           "&uzsakymo_id=" . urlencode($uzsakymo_id) .
           "&issaugota=taip");
    exit;
}
