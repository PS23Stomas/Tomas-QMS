<?php
/**
 * MT saugiklių įdėklų išsaugojimas į duomenų bazę
 * 
 * Šis failas apdoroja POST užklausą ir išsaugo saugiklių įdėklų duomenis
 * į mt_saugikliu_ideklai lentelę. Naudojama transakcija užtikrinant
 * duomenų vientisumą.
 * 
 * Veikimo principas:
 * - Gauna formos duomenis iš POST užklausos
 * - Pradeda duomenų bazės transakciją
 * - Ištrina esamus įrašus pagal gaminio ID ir sekciją
 * - Įterpia naujus saugiklių įdėklų įrašus
 * - Patvirtina transakciją arba atšaukia esant klaidai
 */
session_start();
require_once '../db.php';

/**
 * Tikrinama ar užklausa yra POST tipo
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /**
     * POST duomenų nuskaitymas
     * - gaminio_id: gaminio identifikatorius
     * - sekcija: sekcijos pavadinimas (pvz., "3.5" arba "3.6")
     * - pozicijos, gabaritai, nominalai: saugiklių duomenų masyvai
     */
    $gaminio_id = intval($_POST['gaminio_id'] ?? 0);
    $sekcija = $_POST['sekcija'] ?? '';
    $uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
    $uzsakovas = $_POST['uzsakovas'] ?? '';

    $poz_numeriai = $_POST['pozicijos_numeris'] ?? [];
    $pozicijos = $_POST['pozicijos'] ?? [];
    $gabaritai = $_POST['gabaritai'] ?? [];
    $nominalai = $_POST['nominalai'] ?? [];

    try {
        /**
         * Pradedama duomenų bazės transakcija
         * Užtikrina, kad visi pakeitimai bus atlikti arba atmesti kartu
         */
        $pdo->beginTransaction();

        /**
         * Ištrinami esami saugiklių įdėklų įrašai pagal gaminio ID ir sekciją
         */
        $stmt_delete = $pdo->prepare("DELETE FROM mt_saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = :sekcija");
        $stmt_delete->execute([
            ':gaminio_id' => $gaminio_id,
            ':sekcija' => $sekcija
        ]);

        /**
         * Paruošiamas SQL sakinys naujų saugiklių įterpimui
         */
        $stmt_insert = $pdo->prepare("
            INSERT INTO mt_saugikliu_ideklai 
            (gaminio_id, sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris)
            VALUES (:gaminio_id, :sekcija, :pozicija, :gabaritas, :nominalas, :pozicijos_numeris)
        ");

        /**
         * Ciklas per visus pateiktus saugiklius
         * Praleidžiami tušti įrašai
         */
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

        /**
         * Patvirtinama transakcija - visi pakeitimai išsaugomi
         */
        $pdo->commit();

        /**
         * Nukreipiama atgal į MT paso puslapį su sėkmės pranešimu
         */
        header("Location: MTpasas.php?uzsakymo_numeris=" . urlencode($uzsakymo_numeris) . "&uzsakovas=" . urlencode($uzsakovas) . "&gaminio_id=" . $gaminio_id . "&issaugota=taip");
        exit();
    } catch (PDOException $e) {
        /**
         * Klaidos atveju transakcija atšaukiama ir rodomas klaidos pranešimas
         */
        $pdo->rollBack();
        echo "Klaida: " . $e->getMessage();
    }
}
?>