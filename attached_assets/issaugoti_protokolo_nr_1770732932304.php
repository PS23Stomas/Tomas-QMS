<?php
require_once '../klases/Database.php';
require_once '../klases/Gamys1.php';

// Patikrinam ar formos duomenys buvo pateikti
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gaminio_id = $_POST['gaminio_id'] ?? null;
    $protokolo_nr = trim($_POST['protokolo_nr'] ?? '');

    if ($gaminio_id && $protokolo_nr !== '') {
        $db = new Database();
        $conn = $db->getConnection();

        // Atnaujinti protokolo numerį duomenų bazėje
        $sql = "UPDATE gaminiai SET protokolo_nr = :protokolo_nr WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':protokolo_nr' => $protokolo_nr,
            ':id' => $gaminio_id
        ]);

        // Grąžinti GET parametrus, įskaitant serijos numerį
        $params = http_build_query([
            'gaminio_id' => $_POST['gaminio_id'],
            'uzsakymo_numeris' => $_POST['uzsakymo_numeris'],
            'uzsakovas' => $_POST['uzsakovas'],
            'tipas_kodas' => $_POST['tipas_kodas'],
            'serijos_nr' => $_POST['serijos_nr'], // <- nauja eilutė
            'issaugota' => 'taip'
        ]);

        header("Location: MTpasas.php?$params");
        exit;
    } else {
        echo "Trūksta duomenų.";
    }
} else {
    echo "Neleistinas metodas.";
}
?>
