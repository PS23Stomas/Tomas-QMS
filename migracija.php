<?php
/**
 * ==========================================================================
 *  MIGRACIJA.PHP — Duomenų bazės migracijos CLI skriptas
 * ==========================================================================
 *
 *  Paskirtis: Atnaujinti duomenų bazės schemą rankiniu būdu.
 *             Paleidžiamas komandinėje eilutėje po sistemos atnaujinimo.
 *
 *  Naudojimas:
 *    php migracija.php
 *
 *  Kada paleisti:
 *    - Po kiekvieno sistemos atnaujinimo (git pull, failų kopijavimo)
 *    - Diegiant sistemą pirmą kartą
 *    - Kai administratorius matosi pranešimą apie trūkstamas lenteles
 *
 *  SVARBU: Šis skriptas skirtas tik komandinei eilutei (CLI).
 *          Naršyklė negali jo paleisti tiesiogiai.
 *          Web sąsajai naudokite: /migracija_admin.php
 * ==========================================================================
 */

// Leidžiame paleisti TIK iš komandinės eilutės
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Klaida: Šis skriptas skirtas tik komandinei eilutei.\nWeb sąsajai naudokite /migracija_admin.php\n");
}

$pradzia = microtime(true);

echo "==========================================================\n";
echo "  Tomo-QMS — Duomenų bazės migracijos skriptas\n";
echo "==========================================================\n";
echo "Pradedama: " . date('Y-m-d H:i:s') . "\n\n";

// Įkeliame reikalingas klases
$klases_dir = __DIR__ . '/public/klases/';
require_once $klases_dir . 'Database.php';
require_once $klases_dir . 'DBMigracija.php';

// Jungiamės prie duomenų bazės
echo "Jungiamasi prie duomenų bazės...\n";
try {
    $pdo = Database::getConnection();
    echo "Prisijungta sėkmingai.\n\n";
} catch (Exception $e) {
    echo "KLAIDA: Nepavyko prisijungti prie DB:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

// Vykdome migracijas
echo "Vykdomos migracijos...\n";
echo "----------------------------------------------------------\n";

try {
    $migracija = new DBMigracija($pdo);
    $migracija->paleisti();
    echo "----------------------------------------------------------\n";
    echo "Migracijos baigtos sėkmingai.\n";
} catch (Exception $e) {
    echo "----------------------------------------------------------\n";
    echo "KLAIDA vykdant migracijas:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

$trukme = round(microtime(true) - $pradzia, 3);
echo "\nBaigta: " . date('Y-m-d H:i:s') . "\n";
echo "Trukmė: {$trukme} sek.\n";
echo "==========================================================\n";
exit(0);
