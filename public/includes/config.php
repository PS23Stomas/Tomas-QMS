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

$klases_dir = __DIR__ . '/../klases/';
require_once $klases_dir . 'Database.php';
require_once $klases_dir . 'DBMigracija.php';
require_once $klases_dir . 'GaminioTipas.php';
require_once $klases_dir . 'Gaminys.php';
require_once $klases_dir . 'Gamys1.php';

$pdo = Database::getConnection();

$migracija = new DBMigracija($pdo);
$migracija->paleisti();

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
