<?php
session_set_cookie_params([
    'lifetime' => 28800,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$database_url = getenv('DATABASE_URL');
if ($database_url) {
    $parsed = parse_url($database_url);
    $dsn = "pgsql:host={$parsed['host']};port=" . ($parsed['port'] ?? 5432) . ";dbname=" . ltrim($parsed['path'], '/');
    try {
        $pdo = new PDO($dsn, $parsed['user'], $parsed['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        $session_id = session_id();
        $pdo->prepare("DELETE FROM aktyvus_vartotojai WHERE session_id = ?")->execute([$session_id]);
        
        if (isset($_COOKIE['remember_token'])) {
            $hashed_token = hash('sha256', $_COOKIE['remember_token']);
            $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$hashed_token]);
        }
    } catch (Exception $e) {
    }
}

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

session_destroy();
header('Location: /login.php');
exit;
