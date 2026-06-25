<?php
/**
 * ==========================================================================
 *  INCLUDES/CONFIG.PHP — Pagrindinė konfigūracijos rinkmena
 * ==========================================================================
 *
 *  Paskirtis:  Įkelti visas reikalingas klases, inicializuoti sesiją,
 *              paleisti duomenų bazės migracijas ir apibrėžti pagalbines
 *              funkcijas, naudojamas visuose sistemos puslapiuose.
 *
 *  Ką šis failas daro:
 *    1. Įkelia pagrindines klases (Database, Sesija, DBMigracija ir kt.)
 *    2. Inicializuoja vartotojo sesiją
 *    3. Užmezga ryšį su duomenų baze per Singleton šabloną
 *    4. Paleidžia DB migracijas (tik kai DBMigracija.php pasikeitė)
 *    5. Apibrėžia globalias pagalbines funkcijas: h(), requireLogin(),
 *       currentUser(), getBaseUrl(), getImonesNustatymai()
 *
 *  Įtraukiamas visų puslapių pradžioje:
 *    require_once __DIR__ . '/includes/config.php';
 *
 *  Suteikiami globalūs kintamieji:
 *    - $pdo  → PDO duomenų bazės prisijungimo objektas
 * ==========================================================================
 */


// --------------------------------------------------------------------------
//  0 DALIS: KLAIDŲ APDOROJIMAS (SAUGUMAS)
//  Produkcijoje klaidų NEVAIZDUOJAME vartotojui (gali nutekėti slaptažodžiai,
//  SQL, keliai) — jas rašome į serverio žurnalą. Derinimui galima įjungti
//  rodymą nustačius aplinkos kintamąjį APP_DEBUG=1.
// --------------------------------------------------------------------------
$app_debug = getenv('APP_DEBUG') === '1';
ini_set('display_errors', $app_debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);


// --------------------------------------------------------------------------
//  1 DALIS: KLASIŲ ĮKĖLIMAS
//  Visos sistemos klasės yra /klases/ direktorijoje. Įkeliame jas vieną
//  kartą (require_once) — taip išvengiame dvigubo įkėlimo klaidų.
// --------------------------------------------------------------------------
$klases_dir = __DIR__ . '/../klases/';

require_once $klases_dir . 'Database.php';    // DB prisijungimas (Singleton)
require_once $klases_dir . 'Sesija.php';      // Sesijų ir autentifikacijos valdymas
require_once $klases_dir . 'DBMigracija.php'; // Automatinė DB schemos migracija
require_once $klases_dir . 'Gaminys.php';     // Gaminio CRUD operacijos
require_once $klases_dir . 'Komponentas.php'; // Komponentų valdymas
require_once $klases_dir . 'Emailas.php';     // El. pašto siuntimas (Resend API)
require_once $klases_dir . 'TomoQMS.php';     // Sinchronizacija su išorine sistema


// --------------------------------------------------------------------------
//  2 DALIS: SESIJOS INICIJAVIMAS
//  Sesija leidžia sistemai „prisiminti" prisijungusį vartotoją tarp
//  atskirų puslapių užklausų. Sesija::pradzia() užtikrina, kad sesija
//  bus pradėta tik vieną kartą, net jei config.php įtraukiamas kelis kartus.
// --------------------------------------------------------------------------
Sesija::pradzia();


// --------------------------------------------------------------------------
//  3 DALIS: DUOMENŲ BAZĖS PRISIJUNGIMAS
//  Database::getConnection() naudoja Singleton šabloną — tai reiškia,
//  kad per visą užklausą sukuriamas tik VIENAS $pdo objektas,
//  o ne naujas kiekvienai SQL užklausai. Tai taupo serverio resursus.
// --------------------------------------------------------------------------
$pdo = Database::getConnection();


// --------------------------------------------------------------------------
//  4 DALIS: DB MIGRACIJA
//  Migracija NEVYKDOMA automatiškai užklausos metu — tai užtikrina
//  saugų diegimo modelį produkcinėje aplinkoje.
//
//  Migraciją paleisti vienu iš dviejų būdų:
//    a) Komandinė eilutė: php migracija.php
//    b) Admino sąsaja:    /migracija_admin.php (tik admin rolė)
//
//  Paleisti būtina po kiekvieno sistemos atnaujinimo.
// --------------------------------------------------------------------------


// ==========================================================================
//  5 DALIS: PAGALBINĖS FUNKCIJOS
//  Šios funkcijos prieinamos visuose puslapiuose, kurie įtraukia config.php
// ==========================================================================

/**
 * Tikrina, ar vartotojas yra prisijungęs prie sistemos.
 *
 * @return bool  true — prisijungęs, false — neprisijungęs
 */
function isLoggedIn(): bool {
    return Sesija::arPrisijunges();
}

/**
 * Reikalauja, kad vartotojas būtų prisijungęs.
 * Jei vartotojas NEPRISIJUNGĘS — automatiškai nukreipia į /login.php.
 * Naudojama kiekvieno apsaugoto puslapio pradžioje po require config.php.
 *
 * Naudojimas: requireLogin();
 */
function requireLogin(): void {
    Sesija::tikrintiPrisijungima();
}

/**
 * Grąžina prisijungusio vartotojo duomenis iš aktyvios sesijos.
 *
 * @return array{
 *   id:      int|null,
 *   vardas:  string|null,
 *   pavarde: string|null,
 *   role:    string|null   (galimos reikšmės: 'administratorius', 'vartotojas', 'skaitytojas')
 * }
 */
function currentUser(): array {
    return [
        'id'      => Sesija::get('vartotojas_id'),
        'vardas'  => Sesija::get('vardas'),
        'pavarde' => Sesija::get('pavarde'),
        'role'    => Sesija::get('role'),
    ];
}

/**
 * Apsaugo tekstą nuo XSS (Cross-Site Scripting) atakų.
 * PRIVALOMA naudoti visur, kur rodomi vartotojo įvesti ar DB gauti duomenys.
 *
 * Pavyzdys: echo h($vartotojo_tekstas);
 *           Netinkama: echo $vartotojo_tekstas; ← XSS pavojus!
 *
 * @param  string|null $str  Tekstas, kurį reikia apsaugoti
 * @return string            HTML-saugus tekstas
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Grąžina sistemos bazinį URL (protokolas + domenas).
 * Naudojama el. laiškuose generuojant nuorodas (pvz. slaptažodžio atstatymui).
 *
 * Pirmenybė: BASE_URL aplinkos kintamasis → dinamiškas iš HTTP_HOST
 * Pavyzdys:  https://nkokybe.elga.tech
 *
 * @return string  Bazinis URL be pabaigos pasvirojo brūkšnio
 */
function getBaseUrl(): string {
    $env = getenv('BASE_URL');
    if ($env) return rtrim($env, '/');

    // Nustatome protokolą pagal HTTPS serverio kintamąjį
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:5000';
    return "{$protocol}://{$host}";
}

/**
 * Grąžina įmonės nustatymų duomenis iš DB lentelės imones_nustatymai.
 * Naudojami PDF dokumentuose: antraštėse, kolofonuose, sertifikatuose.
 *
 * Optimizavimas: duomenys saugomi statiniame kintamajame ($cache),
 * todėl per vieną užklausą DB kreipimasis vyksta tik VIENĄ kartą.
 *
 * Jei DB lentelė tuščia arba nepasiekiama — grąžinamos UAB „ELGA"
 * numatytosios reikšmės, kad PDF generavimas nesugestų.
 *
 * @return array{
 *   pavadinimas:    string,
 *   adresas:        string,
 *   telefonas:      string,
 *   faksas:         string,
 *   el_pastas:      string,
 *   internetas:     string,
 *   logotipas:      string|null,
 *   logotipo_tipas: string|null
 * }
 */
function getImonesNustatymai(): array {
    static $cache = null; // Statinis kintamasis išlieka per visą užklausą

    if ($cache !== null) return $cache; // Grąžiname iš talpyklos (jau buvo kreiptasi)

    // Numatytosios reikšmės — naudojamos kai DB nepasiekiama arba lentelė tuščia
    $numatytosios = [
        'pavadinimas'    => 'UAB "ELGA"',
        'adresas'        => 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva',
        'telefonas'      => '+370 41 594710',
        'faksas'         => '+370 41 594725',
        'el_pastas'      => 'info@elga.lt',
        'internetas'     => 'www.elga.lt',
        'logotipas'      => null,
        'logotipo_tipas' => null,
    ];

    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM imones_nustatymai LIMIT 1');
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache = $row ?: $numatytosios; // Jei eilutė rasta — naudojame DB duomenis
    } catch (PDOException $e) {
        // DB klaidos atveju tyliai grąžiname numatytąsias reikšmes,
        // kad PDF generavimas ir kiti puslapiai galėtų veikti toliau
        $cache = $numatytosios;
    }

    return $cache;
}

/**
 * Grąžina užsakymui priskirtus įmonės duomenis.
 * Jei užsakymas turi savo įmonės duomenis (pvz. skirtingas klientas) —
 * jie perrašo globalius nustatymus; kitu atveju naudojami globalūs.
 *
 * Naudojama PDF generatoriuose, kur ant dokumento turi atsirasti
 * konkrečiam užsakymui priskirtos įmonės rekvizitai.
 *
 * @param  int   $uzsakymo_id  Užsakymo ID iš DB lentelės uzsakymai
 * @return array               Įmonės duomenų masyvas (tas pats formatas kaip getImonesNustatymai)
 */
function getUzsakymoImone(int $uzsakymo_id): array {
    $global = getImonesNustatymai(); // Pradedame nuo globalių nustatymų

    if ($uzsakymo_id <= 0) return $global; // Nėra užsakymo — grąžiname globalius

    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT imone_pavadinimas, imone_adresas, imone_telefonas,
                   imone_faksas, imone_el_pastas, imone_internetas
            FROM uzsakymai
            WHERE id = ?
        ');
        $stmt->execute([$uzsakymo_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Žemėlapis: DB stulpelio pavadinimas → masyvo raktas
            $map = [
                'imone_pavadinimas' => 'pavadinimas',
                'imone_adresas'     => 'adresas',
                'imone_telefonas'   => 'telefonas',
                'imone_faksas'      => 'faksas',
                'imone_el_pastas'   => 'el_pastas',
                'imone_internetas'  => 'internetas',
            ];
            // Perrašome tik tuos laukus, kurie užsakyme yra užpildyti (ne null)
            foreach ($map as $db_stulpelis => $masyvo_raktas) {
                if ($row[$db_stulpelis] !== null) {
                    $global[$masyvo_raktas] = $row[$db_stulpelis];
                }
            }
        }
    } catch (PDOException $e) {
        // Klaidos atveju grąžiname globalius nustatymus be pakeitimų
    }

    return $global;
}

/**
 * Grąžina (arba sukuria) CSRF apsaugos žetoną, saugomą sesijoje.
 * Naudojamas kaip paslėptas laukas HTML formose ir meta žyme.
 *
 * @return string  32 baitų atsitiktinis šešioliktainis žetonas
 */
function csrfToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Tikrina, ar POST užklausa turi galiojantį CSRF žetoną.
 * Žetonas gali būti: POST lauke '_csrf' ARBA HTTP antraštėje 'X-CSRF-Token'.
 * Nesutapus — grąžinama 403 klaida ir vykdymas sustabdomas.
 */
function csrfVerify(): void {
    $pateiktas = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $sesijoje  = $_SESSION['_csrf_token'] ?? '';
    if (!$sesijoje || !hash_equals($sesijoje, $pateiktas)) {
        http_response_code(403);
        die('CSRF patikrinimas nepavyko. Atnaujinkite puslapį ir bandykite dar kartą.');
    }
}

/**
 * Bendras apsaugos vartų patikrinimas KIEKVIENAM rašymo veiksmui (POST).
 * Sujungia dvi būtinas patikras:
 *   1. csrfVerify()                      — apsauga nuo CSRF (svetimų užklausų)
 *   2. Sesija::blokuotiSkaitytojaVeiksma() — „skaitytojas" rolė negali rašyti
 *
 * Naudojimas formų valdikliuose, prieš bet kokį INSERT/UPDATE/DELETE:
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') { requireWrite(); ... }
 */
function requireWrite(): void {
    csrfVerify();
    Sesija::blokuotiSkaitytojaVeiksma();
}