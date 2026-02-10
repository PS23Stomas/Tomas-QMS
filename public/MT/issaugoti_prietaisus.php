<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Tik POST užklausos leidžiamos');
}

$conn = Database::getConnection();

$gaminio_id       = (int)($_POST['gaminio_id'] ?? 0);
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';
$prietaiso_id     = (int)($_POST['prietaiso_id'] ?? 0);

if ($gaminio_id <= 0) {
    die('Klaida: nėra gaminio ID');
}

try {
    if ($prietaiso_id > 0) {
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
           "&issaugota=taip");
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Klaida saugant prietaisą: " . htmlspecialchars($e->getMessage());
}
