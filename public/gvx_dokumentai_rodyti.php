<?php
/**
 * Grąžina gvx_dokumentai įrašo PDF turinį naršyklei (inline peržiūrai).
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Neteisingas ID');
}

$pdo = getDbConnection();

$r = $pdo->prepare("SELECT failas, pavadinimas, turinys_lob FROM gvx_dokumentai WHERE id = ?");
$r->execute([$id]);
$dok = $r->fetch(PDO::FETCH_ASSOC);

if (!$dok) {
    http_response_code(404);
    exit('Dokumentas nerastas');
}

if (empty($dok['turinys_lob'])) {
    http_response_code(404);
    exit('Dokumento turinys tuščias');
}

// turinys_lob gali būti: PHP resource (stream), '\x...' hex string, arba raw binary
$raw = $dok['turinys_lob'];
if (is_resource($raw)) {
    $raw = stream_get_contents($raw);
}
// PostgreSQL BYTEA hex format: \x followed by hex digits
if (is_string($raw) && str_starts_with($raw, '\\x')) {
    $raw = hex2bin(substr($raw, 2));
} elseif (is_string($raw) && str_starts_with($raw, "\x00") === false && ctype_xdigit(ltrim($raw, '\\x'))) {
    // leave as-is if it's actually raw binary
}

$failas = $dok['failas'] ?? ($dok['pavadinimas'] ?? 'dokumentas.pdf');
// Ensure .pdf extension
if (!str_ends_with(strtolower($failas), '.pdf')) {
    $failas .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . addslashes(basename($failas)) . '"');
header('Content-Length: ' . strlen($raw));
header('Cache-Control: private, max-age=3600');

echo $raw;
exit;
