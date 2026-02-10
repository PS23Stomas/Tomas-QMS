<?php
/**
 * Pretenzijos nuotraukos atvaizdavimo tvarkyklė.
 * Grąžina nuotraukos binarinį turinį pagal ID iš pretenzijos_nuotraukos lentelės.
 * Naudojamas kaip <img src="/pretenzijos_nuotrauka.php?id=X"> šaltinis.
 */
require_once __DIR__ . '/includes/config.php';

// Nuotraukos ID gavimas iš GET parametro
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

try {
    $stmt = $pdo->prepare("SELECT tipas, turinys FROM pretenzijos_nuotraukos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $photo = $stmt->fetch();

    if (!$photo || empty($photo['turinys'])) {
        http_response_code(404);
        exit('Photo not found');
    }

    // Nustatyti turinio tipą (numatytasis: image/jpeg)
    $contentType = $photo['tipas'] ?: 'image/jpeg';

    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');

    // Išvesti nuotraukos turinį (resursas arba eilutė)
    if (is_resource($photo['turinys'])) {
        echo stream_get_contents($photo['turinys']);
    } else {
        echo $photo['turinys'];
    }

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}
