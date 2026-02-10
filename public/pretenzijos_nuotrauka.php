<?php
require_once __DIR__ . '/includes/config.php';

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

    $contentType = $photo['tipas'] ?: 'image/jpeg';

    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');

    if (is_resource($photo['turinys'])) {
        echo stream_get_contents($photo['turinys']);
    } else {
        echo $photo['turinys'];
    }

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}
