<?php
/**
 * Paso saugiklių įdėklų duomenų AJAX išsaugojimas
 *
 * Šis failas priima saugiklių įdėklų duomenis iš MT paso puslapio
 * ir juos išsaugo duomenų bazėje be viso puslapio perkrovimo (AJAX).
 *
 * Saugikliai — tai elektros grandinės apsaugos elementai (lyginamieji
 * įdėklai). Kiekvienai pozicijai nurodomas gabaritas (dydis) ir nominalas
 * (srovės riba, pvz. "25A").
 *
 * Veikimo principas: pirma ištrinami visi seni tos sekcijos duomenys,
 * tada įrašomi nauji — taip užtikrinama, kad nebebus senų likučių.
 *
 * Grąžina JSON: {"success": true} arba {"success": false, "message": "..."}
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Tik POST užklausos leidžiamos');
    }

    $gaminio_id = intval($_POST['gaminio_id'] ?? 0);
    $sekcija = $_POST['sekcija'] ?? '';

    if (!$gaminio_id || !$sekcija) {
        throw new Exception('Trūksta privalomų parametrų (gaminio_id, sekcija)');
    }

    if (!in_array($sekcija, ['3.5', '3.6'])) {
        throw new Exception('Neteisinga sekcija: ' . $sekcija);
    }

    $pozicijos_json = $_POST['pozicijos'] ?? '[]';
    $pozicijos = json_decode($pozicijos_json, true);

    if (!is_array($pozicijos)) {
        throw new Exception('Neteisingas pozicijų formatas');
    }

    $conn = Database::getConnection();
    $conn->beginTransaction();

    $stmt_delete = $conn->prepare("DELETE FROM saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = :sekcija");
    $stmt_delete->execute([':gaminio_id' => $gaminio_id, ':sekcija' => $sekcija]);

    $stmt_insert = $conn->prepare("
        INSERT INTO saugikliu_ideklai 
        (gaminio_id, sekcija, pozicija, gabaritas, nominalas, pozicijos_numeris)
        VALUES (:gaminio_id, :sekcija, :pozicija, :gabaritas, :nominalas, :pozicijos_numeris)
    ");

    $irasyta = 0;
    foreach ($pozicijos as $poz) {
        $poz_nr = intval($poz['pozicijos_numeris'] ?? 0);
        $gabaritas = trim($poz['gabaritas'] ?? '');
        $nominalas = trim($poz['nominalas'] ?? '');

        if ($poz_nr === 0) continue;
        if ($gabaritas === '' && $nominalas === '') continue;

        $stmt_insert->execute([
            ':gaminio_id' => $gaminio_id,
            ':sekcija' => $sekcija,
            ':pozicija' => $poz_nr,
            ':gabaritas' => $gabaritas,
            ':nominalas' => $nominalas,
            ':pozicijos_numeris' => $poz_nr
        ]);
        $irasyta++;
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Išsaugota sėkmingai ($irasyta pozicijų)";

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
