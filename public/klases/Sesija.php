<?php
/**
 * Sesijos valdymo klasė
 *
 * Sesija — tai tarsi "ženklelis", kurį vartotojas gauna prisijungęs prie sistemos.
 * Kol ženklelis galioja, sistema žino KAS yra prisijungęs ir leidžia jam dirbti.
 * Jei vartotojas 30 minučių nieko nedaro — ženklelis panaikinamas ir reikia prisijungti iš naujo.
 *
 * Ši klasė atsakinga už:
 * - Sesijos pradžią ir jos galiojimo tikrinimą
 * - Vartotojo prisijungimo patikrinimą kiekviename puslapyje
 * - Aktyvių vartotojų sąrašo tvarkymą (kas dabar dirba sistemoje)
 * - Neaktyvių vartotojų automatinį ištrynimą iš aktyvių sąrašo
 */
class Sesija {

    /** Sesijos galiojimo laikas sekundėmis (1800 = 30 minučių) */
    const SESIJOS_GALIOJIMAS = 1800;

    /**
     * Patikrina ar užklausa atėjo per AJAX (t.y. iš JavaScript kodo fone,
     * o ne paprastas naršyklės puslapio atidarymas).
     * Tai svarbu norint grąžinti tinkamą atsakymą — JSON arba nukreipimą.
     */
    private static function isAjax(): bool {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        return str_contains($ct, 'application/json');
    }

    /**
     * Grąžina JSON klaidos atsakymą ir iš karto sustabdo puslapio vykdymą.
     * Naudojama kai AJAX užklausa nesėkminga (pvz. sesija pasibaigė).
     */
    private static function ajaxKlaida(string $priezastis): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $priezastis], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Paleidžia sesiją ir atlieka visus reikiamus patikrinimus.
     *
     * Ši funkcija kviečiama kiekvieno puslapio pradžioje. Ji:
     * 1. Pradeda sesiją (jei dar nepradėta)
     * 2. Patikrina ar sesija nepasibaigė (30 min. neveiklumo)
     * 3. Atnaujina vartotojo paskutinės veiklos laiką
     * 4. Kartais (atsitiktinai) išvalo senus neaktyvius vartotojus
     */
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
                if (self::isAjax()) {
                    self::ajaxKlaida('Sesija pasibaigė – prisijunkite iš naujo');
                }
                header('Location: /login.php?sesija_pasibaige=1');
                exit;
            }
        }

        if (isset($_SESSION['vartotojas_id'])) {
            $_SESSION['paskutine_veikla'] = time();
        }

        self::atnaujintiVeikla();

        // 10% tikimybė išvalyti neaktyvius vartotojus — daroma retai, kad negrąžintų viską kiekvieną kartą
        if (rand(1, 10) === 1) {
            self::isvalytiNeaktyvius();
        }
    }

    /**
     * Grąžina sesijos reikšmę pagal raktą.
     * Jei tokio rakto sesijoje nėra — grąžina tuščią eilutę, o ne klaidą.
     * Pavyzdys: Sesija::get('vardas') → "Jonas"
     */
    public static function get($raktas) {
        return $_SESSION[$raktas] ?? '';
    }

    /**
     * Tikrina ar vartotojas yra prisijungęs.
     * Jei NE — nukreipia į prisijungimo puslapį ir sustabdo vykdymą.
     * Jei užklausa buvo AJAX — grąžina JSON klaidą vietoj nukreipimo.
     * Ši funkcija kviečiama kiekvieno apsaugoto puslapio pradžioje.
     */
    public static function tikrintiPrisijungima(): void {
        if (!isset($_SESSION['vartotojas_id'])) {
            if (self::isAjax()) {
                self::ajaxKlaida('Sesija pasibaigė – prisijunkite iš naujo');
            }
            $pasibaige = isset($_SESSION['sesija_pasibaige']) && $_SESSION['sesija_pasibaige'];
            if ($pasibaige) {
                unset($_SESSION['sesija_pasibaige']);
            }
            header('Location: /login.php' . ($pasibaige ? '?sesija_pasibaige=1' : ''));
            exit;
        }
    }

    /**
     * Grąžina true jei vartotojas yra prisijungęs, false jei ne.
     * Skirtumas nuo tikrintiPrisijungima() — ši NENUKREIPIA, tik grąžina atsakymą.
     */
    public static function arPrisijunges(): bool {
        return isset($_SESSION['vartotojas_id']);
    }

    /**
     * Grąžina true jei prisijungęs vartotojas turi "skaitytojas" rolę.
     * Skaitytojas gali tik žiūrėti duomenis — negali jų keisti ar trinti.
     */
    public static function arSkaitytojas(): bool {
        return ($_SESSION['role'] ?? '') === 'skaitytojas';
    }

    /**
     * Blokuoja skaitytojo veiksmus — jei vartotojas yra skaitytojas,
     * nukreipia jį atgal su klaidos pranešimu ir sustabdo vykdymą.
     * Naudojama prieš kiekvieną veiksmą, kurį skaitytojas neturi teisės atlikti
     * (pvz. išsaugoti, ištrinti, redaguoti).
     *
     * @param string $redirect Puslapis į kurį nukreipti (numatyta: /index.php)
     */
    public static function blokuotiSkaitytojaVeiksma($redirect = '/index.php'): void {
        if (self::arSkaitytojas()) {
            header("Location: $redirect?klaida=skaitytojas");
            exit;
        }
    }

    /**
     * Atnaujina prisijungusio vartotojo paskutinės veiklos laiką duomenų bazėje.
     * Tai reikalinga, kad aktyvių vartotojų sąraše matytųsi kada vartotojas
     * paskutinį kartą kažką darė sistemoje.
     */
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

    /**
     * Ištrina iš aktyvių vartotojų sąrašo tuos, kurie nebuvo aktyvūs ilgiau nei 30 minučių.
     * Tai reikalinga, kad sąraše nebūtų rodomi žmonės, kurie jau seniai išjungė naršyklę.
     */
    public static function isvalytiNeaktyvius(): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM aktyvus_vartotojai 
                                  WHERE paskutine_veikla < CURRENT_TIMESTAMP - INTERVAL '30 minutes'");
            $stmt->execute();
        } catch (Exception $e) {
        }
    }

    /**
     * Grąžina sąrašą vartotojų, kurie aktyviai dirba sistemoje šiuo metu
     * (buvo aktyvūs per paskutines 15 minučių).
     * Naudojama rodyti "Kas dabar prisijungęs" skyriuje.
     */
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

    /**
     * Grąžina vartotojų prisijungimų istoriją per paskutines 24 valandas.
     * Kiekvienas įrašas rodo: kas prisijungė, kada ir ar dar aktyvus.
     * Naudojama administratoriaus stebėjimo skyriuje.
     */
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
