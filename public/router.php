<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if ($path === '/') {
    $path = '/index.php';
}

$file = __DIR__ . $path;

if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf)$/i', $path)) {
    if (file_exists($file)) {
        return false;
    }
}

if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    return true;
}

if (file_exists($file . '.php')) {
    require $file . '.php';
    return true;
}

$clean = ltrim($path, '/');
$phpFile = __DIR__ . '/' . $clean . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    return true;
}

if (file_exists($file)) {
    return false;
}

http_response_code(404);
echo '<h1>404 - Puslapis nerastas</h1>';
