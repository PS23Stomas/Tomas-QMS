<?php
/**
 * Sesijos valdymo klasė - autentifikacija, veiklos sekimas, neaktyvių vartotojų valymas
 */
class Sesija {

    const SESIJOS_GALIOJIMAS = 1800; // 30 minučių sekundėmis

    public static function pradzia(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', self::SESIJOS_GALIOJIMAS);
            ini_set('session.cookie_lifetime', 0);

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        if (isset($_SESSION['vartotojas_id']) && isset($_SESSION['paskutine_veikla'])) {
            $neaktyvumo_laikas = time() - $_SESSION['paskutine_veikla'];
            if ($neaktyvumo_laikas > self::SESIJOS_GALIOJIMAS) {
                $session_id = session_id();
                try {
                    $pdo = Database::getConnection();
                    $pdo->prepare("DELETE FROM aktyvus_vartotojai WHERE session_id = ?")->execute([$session_id]);
                } catch (Exception $e) {}
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['sesija_pasibaige'] = true;
                header('Location: /login.php?sesija_pasibaige=1');
                exit;
            }
        }

        if (isset($_SESSION['vartotojas_id'])) {
            $_SESSION['paskutine_veikla'] = time();
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

    public static function tikrintiPrisijungima(): void {
        if (!isset($_SESSION['vartotojas_id'])) {
            $pasibaige = isset($_SESSION['sesija_pasibaige']) && $_SESSION['sesija_pasibaige'];
            if ($pasibaige) {
                unset($_SESSION['sesija_pasibaige']);
            }
            header('Location: /login.php' . ($pasibaige ? '?sesija_pasibaige=1' : ''));
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

    public static function isvalytiNeaktyvius(): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM aktyvus_vartotojai 
                                  WHERE paskutine_veikla < CURRENT_TIMESTAMP - INTERVAL '30 minutes'");
            $stmt->execute();
        } catch (Exception $e) {
        }
    }

    /** Gauna šiuo metu aktyvių vartotojų sąrašą (aktyvumas per paskutines 15 min.) */
    public static function gautiAktyvius(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT vardas, pavarde, 
                                    MAX(prisijungimo_laikas) AS prisijungimo_laikas, 
                                    MAX(paskutine_veikla) AS paskutine_veikla 
                                FROM aktyvus_vartotojai 
                                WHERE paskutine_veikla > NOW() - INTERVAL '15 minutes'
                                GROUP BY vartotojas_id, vardas, pavarde
                                ORDER BY MAX(paskutine_veikla) DESC");
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
