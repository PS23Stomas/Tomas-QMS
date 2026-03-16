<?php
/**
 * Pagrindinė konfigūracijos rinkmena - klasių įkėlimas, sesijos pradžia, migracijos paleidimas
 *
 * Ši rinkmena įkelia visas reikalingas klases, inicializuoja sesiją,
 * paleidžia duomenų bazės migracijas ir apibrėžia pagalbines funkcijas.
 */

// Klasių direktorijos nustatymas ir pagrindinių klasių įkėlimas
$klases_dir = __DIR__ . '/../klases/';
require_once $klases_dir . 'Database.php';
require_once $klases_dir . 'Sesija.php';

// Sesijos pradžia - inicializuojama vartotojo sesija
Sesija::pradzia();

// Papildomų klasių įkėlimas
require_once $klases_dir . 'DBMigracija.php';
require_once $klases_dir . 'Gaminys.php';
require_once $klases_dir . 'Emailas.php';

// Duomenų bazės prisijungimo gavimas
$pdo = Database::getConnection();

// Migracijos paleidimas - tik kai pasikeitė migracijos failas arba nauja sesija
$migr_failas = $klases_dir . 'DBMigracija.php';
$migr_hash = md5_file($migr_failas);
if (empty($_SESSION['migracijos_hash']) || $_SESSION['migracijos_hash'] !== $migr_hash) {
    $migracija = new DBMigracija($pdo);
    $migracija->paleisti();
    $_SESSION['migracijos_hash'] = $migr_hash;
}

/**
 * Tikrina, ar vartotojas yra prisijungęs prie sistemos
 * @return bool Grąžina true, jei vartotojas prisijungęs
 */
function isLoggedIn() {
    return Sesija::arPrisijunges();
}

/**
 * Reikalauja, kad vartotojas būtų prisijungęs.
 * Jei neprisijungęs - nukreipia į prisijungimo puslapį.
 */
function requireLogin() {
    Sesija::tikrintiPrisijungima();
}

/**
 * Grąžina dabartinio prisijungusio vartotojo duomenis iš sesijos
 * @return array Vartotojo duomenų masyvas (id, vardas, pavardė, rolė)
 */
function currentUser() {
    return [
        'id' => Sesija::get('vartotojas_id'),
        'vardas' => Sesija::get('vardas'),
        'pavarde' => Sesija::get('pavarde'),
        'role' => Sesija::get('role'),
    ];
}

/**
 * HTML specialiųjų simbolių apsaugos funkcija (XSS prevencija)
 * @param string|null $str Tekstas, kurį reikia apsaugoti
 * @return string Apsaugotas tekstas, saugus naudoti HTML išvedime
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getBaseUrl(): string {
    $env = getenv('APP_BASE_URL');
    if ($env) return rtrim($env, '/');
    return 'https://nkokybe.elga.tech';
}

function getImonesNustatymai(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM imones_nustatymai LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $cache = $row;
        } else {
            $cache = [
                'pavadinimas' => 'UAB "ELGA"',
                'adresas' => 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva',
                'telefonas' => '+370 41 594710',
                'faksas' => '+370 41 594725',
                'el_pastas' => 'info@elga.lt',
                'internetas' => 'www.elga.lt',
                'logotipas' => null,
                'logotipo_tipas' => null,
            ];
        }
    } catch (PDOException $e) {
        $cache = [
            'pavadinimas' => 'UAB "ELGA"',
            'adresas' => 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva',
            'telefonas' => '+370 41 594710',
            'faksas' => '+370 41 594725',
            'el_pastas' => 'info@elga.lt',
            'internetas' => 'www.elga.lt',
            'logotipas' => null,
            'logotipo_tipas' => null,
        ];
    }
    return $cache;
}

function getUzsakymoImone(int $uzsakymo_id): array {
    $global = getImonesNustatymai();
    if ($uzsakymo_id <= 0) return $global;
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT imone_pavadinimas, imone_adresas, imone_telefonas, imone_faksas, imone_el_pastas, imone_internetas FROM uzsakymai WHERE id = ?");
        $stmt->execute([$uzsakymo_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $map = [
                'imone_pavadinimas' => 'pavadinimas',
                'imone_adresas' => 'adresas',
                'imone_telefonas' => 'telefonas',
                'imone_faksas' => 'faksas',
                'imone_el_pastas' => 'el_pastas',
                'imone_internetas' => 'internetas',
            ];
            foreach ($map as $col => $key) {
                if ($row[$col] !== null) {
                    $global[$key] = $row[$col];
                }
            }
        }
    } catch (PDOException $e) {
    }
    return $global;
}
