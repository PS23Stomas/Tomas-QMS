<?php
$klases_dir = __DIR__ . '/../klases/';
require_once $klases_dir . 'Database.php';
require_once $klases_dir . 'Sesija.php';

Sesija::pradzia();

require_once $klases_dir . 'DBMigracija.php';
require_once $klases_dir . 'Gaminys.php';
require_once $klases_dir . 'Emailas.php';

$pdo = Database::getConnection();

$migracija = new DBMigracija($pdo);
$migracija->paleisti();

function isLoggedIn() {
    return Sesija::arPrisijunges();
}

function requireLogin() {
    Sesija::tikrintiPrisijungima();
}

function currentUser() {
    return [
        'id' => Sesija::get('vartotojas_id'),
        'vardas' => Sesija::get('vardas'),
        'pavarde' => Sesija::get('pavarde'),
        'role' => Sesija::get('role'),
    ];
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
