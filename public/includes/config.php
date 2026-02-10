<?php
session_set_cookie_params([
    'lifetime' => 28800,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', 28800);

session_start();

$database_url = getenv('DATABASE_URL');
if (!$database_url) {
    die('DATABASE_URL not set');
}

$parsed = parse_url($database_url);
$host = $parsed['host'];
$port = $parsed['port'] ?? 5432;
$dbname = ltrim($parsed['path'], '/');
$user = $parsed['user'];
$pass = $parsed['pass'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['vartotojas_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function currentUser() {
    return [
        'id' => $_SESSION['vartotojas_id'] ?? null,
        'vardas' => $_SESSION['vardas'] ?? null,
        'pavarde' => $_SESSION['pavarde'] ?? null,
        'role' => $_SESSION['role'] ?? null,
    ];
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
