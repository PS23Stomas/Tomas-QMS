<?php
/**
 * Sesijos valdymo klasė - autentifikacija, veiklos sekimas, neaktyvių vartotojų valymas
 */
class Sesija {

    /** Pradeda sesiją su 8 val. galiojimo laiku, atnaujina veiklą ir kartais išvalo neaktyvius */
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

    /** Grąžina sesijos reikšmę pagal raktą arba tuščią eilutę */
    public static function get($raktas) {
        return $_SESSION[$raktas] ?? '';
    }

    /** Tikrina, ar vartotojas prisijungęs; jei ne - nukreipia į prisijungimo puslapį */
    public static function tikrintiPrisijungima(): void {
        if (!isset($_SESSION['vartotojas_id'])) {
            header('Location: /login.php');
            exit;
        }
    }

    /** Grąžina true, jei vartotojas yra prisijungęs */
    public static function arPrisijunges(): bool {
        return isset($_SESSION['vartotojas_id']);
    }

    /** Grąžina true, jei vartotojo rolė yra „skaitytojas" (tik skaitymo teisės) */
    public static function arSkaitytojas(): bool {
        return ($_SESSION['role'] ?? '') === 'skaitytojas';
    }

    /** Blokuoja skaitytojo veiksmus ir nukreipia į nurodytą puslapį su klaidos pranešimu */
    public static function blokuotiSkaitytojaVeiksma($redirect = '/index.php'): void {
        if (self::arSkaitytojas()) {
            header("Location: $redirect?klaida=skaitytojas");
            exit;
        }
    }

    /** Atnaujina prisijungusio vartotojo paskutinės veiklos laiką duomenų bazėje */
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

    /** Išvalo neaktyvius vartotojus, kurių paskutinė veikla senesnė nei 8 valandos */
    public static function isvalytiNeaktyvius(): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM aktyvus_vartotojai 
                                  WHERE paskutine_veikla < CURRENT_TIMESTAMP - INTERVAL '8 hours'");
            $stmt->execute();
        } catch (Exception $e) {
        }
    }

    /** Gauna šiuo metu aktyvių vartotojų sąrašą (aktyvumas per paskutines 15 min.) */
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

    /** Gauna vartotojų prisijungimo istoriją per paskutines 24 valandas su aktyvumo statusu */
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
