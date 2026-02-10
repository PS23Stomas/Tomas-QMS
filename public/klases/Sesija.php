<?php
class Sesija {
    public static function pradzia(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 28800,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            ini_set('session.gc_maxlifetime', 28800);
            session_start();
        }
    }

    public static function tikrintiPrisijungima(): void {
        if (!isset($_SESSION['vartotojas_id'])) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function arPrisijunges(): bool {
        return isset($_SESSION['vartotojas_id']);
    }
}
