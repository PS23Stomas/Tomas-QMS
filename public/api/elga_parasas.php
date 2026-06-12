<?php
/**
 * API: UAB ELGA parašo paveikslėlio gavimas
 *
 * Saugo parašo failą už web root ribų (private/img/).
 * Tik prisijungę vartotojai gali gauti šį paveikslėlį.
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$failas = __DIR__ . '/../../private/img/parasas_elga.jpg';
if (!file_exists($failas)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
readfile($failas);
