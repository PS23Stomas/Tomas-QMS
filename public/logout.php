<?php
/**
 * Atsijungimo tvarkyklė - sesijos sunaikinimas
 *
 * Veiksmai:
 * - Pašalinamas „prisiminti mane" žetonas iš remember_tokens lentelės
 * - Ištrinamas remember_token slapukas iš naršyklės
 * - Sunaikinama PHP sesija
 * - Nukreipiama į prisijungimo puslapį
 */
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = Database::getConnection();

    if (isset($_COOKIE['remember_token'])) {
        $hashed_token = hash('sha256', $_COOKIE['remember_token']);
        $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$hashed_token]);
    }
} catch (Exception $e) {
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
