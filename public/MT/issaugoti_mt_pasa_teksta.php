<?php
/**
 * MT paso teksto korekcijų AJAX išsaugojimo tvarkyklė
 *
 * Šis failas priima teksto pakeitimus iš MT paso puslapio ir juos išsaugo.
 * "Teksto korekcija" — tai kai vartotojas nori pakeisti paso dokumento
 * tekstą tiesiai paso peržiūros ekrane (pvz. ištaisyti klientui skirto
 * dokumento frazę) be viso puslapio perkrovimo.
 *
 * Kiekviena korekcija identifikuojama trimis dalykais:
 * - gaminio_id — kurio gaminio paso tekstas keičiamas
 * - field_key  — kuris laukas keičiamas (pvz. "pastabos_lt")
 * - lang       — kokia kalba ("lt" arba "en")
 *
 * Jei tokia korekcija jau egzistuoja — atnaujinama. Jei ne — sukuriama nauja.
 * Grąžina JSON atsakymą: {"success": true} arba {"success": false, "message": "..."}
 */
require_once __DIR__ . '/../includes/config.php';

// Nustatomas JSON atsakymo tipas
header('Content-Type: application/json; charset=utf-8');

// requireLogin() + Sesija::tikrintiPrisijungima() dabar grąžina JSON
// AJAX užklausoms vietoje HTTP 302 redirect (žr. Sesija::isAjax())
requireLogin();
Sesija::blokuotiSkaitytojaVeiksma(); // skaitytojas negali rašyti

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Tik POST užklausos leidžiamos');
    }

    // POST parametrų gavimas
    $gaminio_id = $_POST['gaminio_id'] ?? null;
    $field_key  = $_POST['field_key'] ?? null;
    $lang       = $_POST['lang'] ?? 'lt';
    $tekstas    = $_POST['tekstas'] ?? '';

    // Privalomų parametrų tikrinimas
    if (!$gaminio_id || !$field_key) {
        throw new Exception('Trūksta privalomų parametrų');
    }

    // Kalbos parametro validavimas (leidžiamos tik 'lt' ir 'en')
    if (!in_array($lang, ['lt', 'en'])) {
        $lang = 'lt';
    }

    $conn = Database::getConnection();

    // UPSERT užklausa: įterpimas arba atnaujinimas pagal unikalų raktą (gaminio_id, field_key, lang)
    $sql = "INSERT INTO paso_teksto_korekcijos (gaminio_id, field_key, lang, tekstas, updated_at)
            VALUES (:gaminio_id, :field_key, :lang, :tekstas, CURRENT_TIMESTAMP)
            ON CONFLICT (gaminio_id, field_key, lang) 
            DO UPDATE SET tekstas = EXCLUDED.tekstas, updated_at = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':gaminio_id' => $gaminio_id,
        ':field_key'  => $field_key,
        ':lang'       => $lang,
        ':tekstas'    => $tekstas
    ]);

    $response['success'] = true;
    $response['message'] = 'Tekstas išsaugotas sėkmingai';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
