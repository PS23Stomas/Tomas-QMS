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
            // AJAX užklausoms grąžiname JSON klaidą, o ne nukreipimą
            if (self::isAjax()) {
                http_response_code(403);
                self::ajaxKlaida('Skaitytojo rolė negali atlikti šio veiksmo');
            }
            header("Location: $redirect?klaida=skaitytojas");
            exit;
        }
    }
}
