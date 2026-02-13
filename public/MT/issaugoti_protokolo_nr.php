<?php
/**
 * Protokolo numerio išsaugojimo tvarkyklė - gaminio protokolo Nr. atnaujinimas
 *
 * Atnaujina gaminiai lentelės protokolo_nr lauką pagal gaminio ID.
 * Po sėkmingo išsaugojimo nukreipia atgal į gaminio langų puslapį.
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';
require_once __DIR__ . '/../klases/TomoQMS.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

// Leidžiamos tik POST užklausos
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Neleistinas metodas.');
}

// POST duomenų gavimas ir validavimas
$gaminio_id   = (int)($_POST['gaminio_id'] ?? 0);
$protokolo_nr = trim($_POST['protokolo_nr'] ?? '');

if ($gaminio_id <= 0 || $protokolo_nr === '') {
    die('Trūksta duomenų.');
}

$conn = Database::getConnection();

try {
    // Protokolo numerio atnaujinimas gaminiai lentelėje
    $sql = "UPDATE gaminiai SET protokolo_nr = :protokolo_nr WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':protokolo_nr' => $protokolo_nr,
        ':id' => $gaminio_id
    ]);

    $params = http_build_query([
        'gaminio_id'       => $gaminio_id,
        'uzsakymo_numeris' => $_POST['uzsakymo_numeris'] ?? '',
        'uzsakovas'        => $_POST['uzsakovas'] ?? '',
        'issaugota'        => 'taip'
    ]);

    header("Location: /gaminiu_langai_mt.php?$params");
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Klaida saugant protokolo Nr.: " . htmlspecialchars($e->getMessage());
}
