<?php
/**
 * URL maršrutizatorius PHP integruotam serveriui
 *
 * Šis failas apdoroja visas HTTP užklausas, kai naudojamas PHP
 * integruotas kūrimo serveris. Nukreipia užklausas į atitinkamus
 * PHP failus arba aptarnauja statinius resursus.
 */

// Gauti užklausos URI ir išanalizuoti kelią
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Šakninio kelio nukreipimas į pagrindinį puslapį
if ($path === '/') {
    $path = '/index.php';
}

// Pilno failo kelio sudarymas
$file = __DIR__ . $path;

// Statinių failų aptarnavimas (CSS, JS, paveikslėliai, šriftai ir kt.)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf)$/i', $path)) {
    if (file_exists($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf'])) {
            header('Cache-Control: public, max-age=2592000, immutable');
        } else {
            header('Cache-Control: public, max-age=3600');
        }
        return false;
    }
}

// PHP failų vykdymas - jei failas egzistuoja ir turi .php plėtinį
if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    return true;
}

// Bandymas pridėti .php plėtinį prie nurodyto kelio
if (file_exists($file . '.php')) {
    require $file . '.php';
    return true;
}

// Bandymas rasti PHP failą pagal švarų kelią be pradinio brūkšnio
$clean = ltrim($path, '/');
$phpFile = __DIR__ . '/' . $clean . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    return true;
}

// Kiti egzistuojantys failai - grąžinami tiesiogiai
if (file_exists($file)) {
    return false;
}

// 404 klaida - puslapis nerastas
http_response_code(404);
echo '<h1>404 - Puslapis nerastas</h1>';
