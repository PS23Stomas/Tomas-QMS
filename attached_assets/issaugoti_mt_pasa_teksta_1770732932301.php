<?php
/**
 * MT paso teksto korekcijų išsaugojimas
 * 
 * Šis failas apdoroja POST užklausas ir išsaugo paso teksto pakeitimus
 * į mt_paso_teksto_korekcijos lentelę.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../klases/Database.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Tik POST užklausos leidžiamos');
    }
    
    $gaminio_id = $_POST['gaminio_id'] ?? null;
    $field_key = $_POST['field_key'] ?? null;
    $lang = $_POST['lang'] ?? 'lt';
    $tekstas = $_POST['tekstas'] ?? '';
    
    if (!$gaminio_id || !$field_key) {
        throw new Exception('Trūksta privalomų parametrų');
    }
    
    if (!in_array($lang, ['lt', 'en'])) {
        $lang = 'lt';
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "INSERT INTO mt_paso_teksto_korekcijos (gaminio_id, field_key, lang, tekstas, updated_at)
            VALUES (:gaminio_id, :field_key, :lang, :tekstas, CURRENT_TIMESTAMP)
            ON CONFLICT (gaminio_id, field_key, lang) 
            DO UPDATE SET tekstas = EXCLUDED.tekstas, updated_at = CURRENT_TIMESTAMP";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':gaminio_id' => $gaminio_id,
        ':field_key' => $field_key,
        ':lang' => $lang,
        ':tekstas' => $tekstas
    ]);
    
    $response['success'] = true;
    $response['message'] = 'Tekstas išsaugotas sėkmingai';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
