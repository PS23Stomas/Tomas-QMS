<?php
/**
 * Sesijos atnaujinimo (keepalive) AJAX endpoint
 *
 * Šis failas iškviečiamas iš app.js kas 30 sekundžių, kai vartotojas
 * aktyviai naudoja sistemą — taip išvengiama automatinio atsijungimo
 * dėl neaktyvumo (sesija gyvuoja 30 min nuo paskutinės veiklos).
 *
 * Kaip veikia:
 *   1. Patikrina ar sesija aktyvi (ar yra $_SESSION['vartotojas_id'])
 *   2. Atnaujina $_SESSION['paskutine_veikla'] = time()
 *   3. Grąžina { "ok": true }
 *
 * Jei sesija pasibaigusi:
 *   HTTP 401 + { "ok": false, "expired": true }
 *   app.js tuomet nukreipia vartotoją į /login.php?sesija_pasibaige=1
 */
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
ini_set('session.gc_maxlifetime', 1800);
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['vartotojas_id'])) {
    $_SESSION['paskutine_veikla'] = time();
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true]);
}
