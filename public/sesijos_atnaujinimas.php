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
 *   3. Atnaujina aktyvus_vartotojai.paskutine_veikla stulpelį DB
 *   4. Grąžina { "ok": true }
 *
 * Jei sesija pasibaigusi:
 *   HTTP 401 + { "ok": false, "expired": true }
 *   app.js tuomet nukreipia vartotoją į /login.php?sesija_pasibaige=1
 *
 * Svarbu: šis failas nenaudoja config.php ar requireLogin() —
 *   jis pats konfigūruoja sesiją ir tiesiogiai jungiasi prie DB,
 *   nes turi veikti net kai sesija jau pasibaigusi (grąžinti 401).
 */
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
ini_set('session.gc_maxlifetime', 1800);
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['vartotojas_id'])) {
    $_SESSION['paskutine_veikla'] = time();
    try {
        $database_url = getenv('DATABASE_URL');
        $parsed = parse_url($database_url);
        $dsn = "pgsql:host={$parsed['host']};port=" . ($parsed['port'] ?? 5432) . ";dbname=" . ltrim($parsed['path'], '/');
        $pdo = new PDO($dsn, $parsed['user'], $parsed['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->prepare("UPDATE aktyvus_vartotojai SET paskutine_veikla = CURRENT_TIMESTAMP WHERE session_id = ?")
            ->execute([session_id()]);
    } catch (Exception $e) {}
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true]);
}
