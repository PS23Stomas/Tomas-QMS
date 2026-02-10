<?php
/**
 * Atsijungimo tvarkyklė - sesijos sunaikinimas ir aktyvaus vartotojo pašalinimas
 *
 * Veiksmai:
 * - Ištrinamas aktyvaus vartotojo įrašas iš aktyvus_vartotojai lentelės
 * - Pašalinamas „prisiminti mane" žetonas iš remember_tokens lentelės
 * - Ištrinamas remember_token slapukas iš naršyklės
 * - Sunaikinama PHP sesija
 * - Nukreipiama į prisijungimo puslapį
 */
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

// Paleidžiama sesija, jei dar nepradėta
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = Database::getConnection();

    // Ištrinamas aktyvaus vartotojo įrašas pagal dabartinę sesijos ID
    $session_id = session_id();
    $pdo->prepare("DELETE FROM aktyvus_vartotojai WHERE session_id = ?")->execute([$session_id]);

    // Jei egzistuoja „prisiminti mane" slapukas - ištrinamas žetonas iš duomenų bazės
    if (isset($_COOKIE['remember_token'])) {
        $hashed_token = hash('sha256', $_COOKIE['remember_token']);
        $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$hashed_token]);
    }
} catch (Exception $e) {
}

// Ištrinamas remember_token slapukas iš naršyklės (nustatomas pasibaigęs galiojimas)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Sesijos sunaikinimas ir nukreipimas į prisijungimo puslapį
session_destroy();
header('Location: /login.php');
exit;
