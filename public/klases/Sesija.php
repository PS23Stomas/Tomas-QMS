<?php
class Sesija {

    public static function pradzia(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', 28800);
            ini_set('session.cookie_lifetime', 28800);

            session_set_cookie_params([
                'lifetime' => 28800,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        self::atnaujintiVeikla();

        if (rand(1, 10) === 1) {
            self::isvalytiNeaktyvius();
        }
    }

    public static function get($raktas) {
        return $_SESSION[$raktas] ?? '';
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

    public static function arSkaitytojas(): bool {
        return ($_SESSION['role'] ?? '') === 'skaitytojas';
    }

    public static function blokuotiSkaitytojaVeiksma($redirect = '/index.php'): void {
        if (self::arSkaitytojas()) {
            header("Location: $redirect?klaida=skaitytojas");
            exit;
        }
    }

    public static function atnaujintiVeikla(): void {
        if (isset($_SESSION['vartotojas_id'])) {
            try {
                $pdo = Database::getConnection();
                $session_id = session_id();
                $stmt = $pdo->prepare("UPDATE aktyvus_vartotojai 
                                      SET paskutine_veikla = CURRENT_TIMESTAMP 
                                      WHERE session_id = ?");
                $stmt->execute([$session_id]);
            } catch (Exception $e) {
            }
        }
    }

    public static function isvalytiNeaktyvius(): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM aktyvus_vartotojai 
                                  WHERE paskutine_veikla < CURRENT_TIMESTAMP - INTERVAL '8 hours'");
            $stmt->execute();
        } catch (Exception $e) {
        }
    }

    public static function gautiAktyvius(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT vardas, pavarde, prisijungimo_laikas, paskutine_veikla 
                                FROM aktyvus_vartotojai 
                                WHERE paskutine_veikla > NOW() - INTERVAL '15 minutes'
                                ORDER BY paskutine_veikla DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public static function gautiIstorija24h(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT vardas, pavarde, prisijungimo_laikas, paskutine_veikla,
                                CASE WHEN paskutine_veikla > NOW() - INTERVAL '15 minutes' THEN true ELSE false END as aktyvus
                                FROM aktyvus_vartotojai 
                                WHERE prisijungimo_laikas > NOW() - INTERVAL '24 hours'
                                ORDER BY prisijungimo_laikas DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
