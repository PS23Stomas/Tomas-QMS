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

// Sveikatos patikros endpointas (health check)
if ($path === '/__healthcheck' || $path === '/__replit_health') {
    http_response_code(200);
    echo 'OK';
    return true;
}

// Šakninio kelio nukreipimas į prisijungimo puslapį
if ($path === '/') {
    $path = '/login.php';
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
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Puslapis nerastas | Tomo-QMS</title>
    <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);">
    <div style="text-align:center;max-width:480px;padding:40px 20px;" role="main">
        <div style="font-size:80px;font-weight:700;color:var(--primary);line-height:1;margin-bottom:8px;">404</div>
        <h1 style="font-size:22px;font-weight:600;color:var(--text);margin-bottom:12px;">Puslapis nerastas</h1>
        <p style="color:var(--text-secondary);font-size:15px;margin-bottom:32px;">Atsiprašome, bet puslapis, kurio ieškote, neegzistuoja arba buvo perkeltas.</p>
        <a href="/login.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;font-size:15px;" data-testid="link-back-home">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Grįžti į pradžią
        </a>
    </div>
</body>
</html>
<?php
