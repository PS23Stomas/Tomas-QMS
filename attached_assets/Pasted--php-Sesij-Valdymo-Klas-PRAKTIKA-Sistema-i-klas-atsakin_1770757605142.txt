<?php
/**
 * Sesijų Valdymo Klasė - PRAKTIKA Sistema
 * 
 * Ši klasė atsakinga už vartotojų sesijų valdymą, įskaitant:
 * - Sesijos inicijavimą su saugiais nustatymais
 * - Prisijungimo tikrinimą
 * - Aktyvių vartotojų sekimą
 * - Neaktyvių sesijų valymą
 * 
 * @package    Praktika
 * @subpackage Classes
 * @author     UAB ELGA
 * @version    2.0
 */
class Sesija {
    
    /**
     * Inicijuoja PHP sesiją su saugiais nustatymais
     * 
     * Ši funkcija turi būti iškviesta kiekvieno puslapio pradžioje.
     * Nustato sesijos gyvavimo laiką (8 valandos) ir saugius cookie parametrus.
     * 
     * Saugumo nustatymai:
     * - secure: true - cookie siunčiamas tik per HTTPS
     * - httponly: true - JavaScript negali pasiekti cookie
     * - samesite: Lax - apsauga nuo CSRF atakų
     * 
     * @return void
     * 
     * @example
     * require_once 'klases/Sesija.php';
     * Sesija::pradzia();
     */
    public static function pradzia() {
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

    /**
     * Gauna reikšmę iš sesijos pagal raktą
     * 
     * Saugus būdas gauti sesijos duomenis - grąžina tuščią stringą
     * jei raktas neegzistuoja (vietoj PHP klaidos).
     * 
     * @param string $raktas Sesijos kintamojo pavadinimas
     * @return mixed Sesijos reikšmė arba tuščias stringas jei neegzistuoja
     * 
     * @example
     * $vardas = Sesija::get('vardas');
     * $role = Sesija::get('role');
     */
    public static function get($raktas) {
        return $_SESSION[$raktas] ?? '';
    }

    /**
     * Tikrina ar vartotojas yra prisijungęs
     * 
     * Jei vartotojas neprisijungęs, nukreipia į prisijungimo puslapį
     * ir sustabdo tolimesnį kodo vykdymą.
     * 
     * Tikrina ar sesijoje yra nustatyti 'vardas' ir 'pavarde' kintamieji.
     * 
     * @return void (nukreipia į prisijungimas.php jei neprisijungęs)
     * 
     * @example
     * Sesija::tikrintiPrisijungima();
     * // Kodas žemiau vykdomas tik jei vartotojas prisijungęs
     */
    public static function tikrintiPrisijungima() {
        if (!isset($_SESSION['vardas']) || !isset($_SESSION['pavarde'])) {
            header("Location: prisijungimas.php");
            exit;
        }
    }
    
    /**
     * Tikrina ar vartotojas turi "skaitytojas" rolę (tik peržiūra)
     * 
     * Skaitytojas gali tik žiūrėti duomenis, bet negali nieko redaguoti.
     * 
     * @return bool true jei vartotojas yra skaitytojas
     */
    public static function arSkaitytojas() {
        return ($_SESSION['role'] ?? '') === 'skaitytojas';
    }
    
    /**
     * Blokuoja veiksmą jei vartotojas yra skaitytojas
     * 
     * Naudojama POST užklausų apsaugai - skaitytojas negali siųsti formų.
     * 
     * @param string $redirect Nukreipimo URL (default: perziura.php)
     * @return void
     */
    public static function blokuotiSkaitytojaVeiksma($redirect = 'perziura.php') {
        if (self::arSkaitytojas()) {
            header("Location: $redirect?klaida=skaitytojas");
            exit;
        }
    }
    
    /**
     * Atnaujina vartotojo paskutinės veiklos laiką duomenų bazėje
     * 
     * Kviečiama automatiškai kiekvieno puslapio užkrovimo metu.
     * Leidžia sekti aktyvius vartotojus ir rodyti juos dashboarde.
     * 
     * Klaidos tyliai ignoruojamos, kad nesugadintų puslapio veikimo.
     * 
     * @return void
     */
    public static function atnaujintiVeikla() {
        if (isset($_SESSION['vartotojas_id'])) {
            try {
                global $pdo;
                if (!isset($pdo)) {
                    require __DIR__ . '/../db.php';
                }
                $session_id = session_id();
                $stmt = $pdo->prepare("UPDATE aktyvus_vartotojai 
                                      SET paskutine_veikla = CURRENT_TIMESTAMP 
                                      WHERE session_id = ?");
                $stmt->execute([$session_id]);
            } catch (Exception $e) {
                // Tyliai ignoruoti klaidas - neturi trukdyti puslapio veikimui
            }
        }
    }
    
    /**
     * Išvalo neaktyvias sesijas iš duomenų bazės
     * 
     * Ištrina įrašus iš aktyvus_vartotojai lentelės, kur vartotojas
     * nebuvo aktyvus ilgiau nei 8 valandas.
     * 
     * Kviečiama automatiškai su 10% tikimybe kiekvieno puslapio užkrovimo metu,
     * kad neapkrautų serverio kiekvieną kartą.
     * 
     * @return void
     */
    public static function isvalytiNeaktyvius() {
        try {
            global $pdo;
            if (!isset($pdo)) {
                require __DIR__ . '/../db.php';
            }
            $stmt = $pdo->prepare("DELETE FROM aktyvus_vartotojai 
                                  WHERE paskutine_veikla < CURRENT_TIMESTAMP - INTERVAL '8 hours'");
            $stmt->execute();
        } catch (Exception $e) {
            // Tyliai ignoruoti klaidas
        }
    }
    
    /**
     * Gauna šiuo metu aktyvių vartotojų sąrašą
     * 
     * Grąžina vartotojus, kurie buvo aktyvūs per paskutines 15 minučių.
     * Naudojama dashboarde rodyti kas šiuo metu dirba sistemoje.
     * 
     * @return array Aktyvių vartotojų masyvas su laukais:
     *               - vardas: Vartotojo vardas
     *               - pavarde: Vartotojo pavardė
     *               - prisijungimo_laikas: Kada prisijungė
     *               - paskutine_veikla: Paskutinis aktyvumas
     * 
     * @example
     * $aktyvus = Sesija::gautiAktyvius();
     * foreach ($aktyvus as $v) {
     *     echo $v['vardas'] . ' ' . $v['pavarde'];
     * }
     */
    public static function gautiAktyvius() {
        try {
            global $pdo;
            if (!isset($pdo)) {
                require __DIR__ . '/../db.php';
            }
            $stmt = $pdo->query("SELECT vardas, pavarde, prisijungimo_laikas, paskutine_veikla 
                                FROM aktyvus_vartotojai 
                                WHERE paskutine_veikla > NOW() - INTERVAL '15 minutes'
                                ORDER BY paskutine_veikla DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Gauna visų vartotojų prisijungimo istoriją per paskutines 24 valandas
     * 
     * Grąžina visus vartotojus, kurie prisijungė per paskutinę parą,
     * su žyme ar jie vis dar aktyvūs (veikė per 15 min).
     * 
     * Naudojama administratoriaus skydelyje sesijų peržiūrai.
     * 
     * @return array Vartotojų masyvas su laukais:
     *               - vardas: Vartotojo vardas
     *               - pavarde: Vartotojo pavardė
     *               - prisijungimo_laikas: Kada prisijungė
     *               - paskutine_veikla: Paskutinis aktyvumas
     *               - aktyvus: Boolean - ar aktyvus dabar
     * 
     * @example
     * $istorija = Sesija::gautiIstorija24h();
     * $aktyvusSk = count(array_filter($istorija, fn($v) => $v['aktyvus']));
     */
    public static function gautiIstorija24h() {
        try {
            global $pdo;
            if (!isset($pdo)) {
                require __DIR__ . '/../db.php';
            }
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
