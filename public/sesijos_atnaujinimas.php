<?php
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
