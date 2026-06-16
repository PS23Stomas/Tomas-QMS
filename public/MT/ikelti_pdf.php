<?php
/**
 * Išorinio PDF failo įkėlimo tvarkyklė
 *
 * Priima POST užklausą su PDF failu ir išsaugo jį duomenų bazėje
 * kaip paso, dielektrinių arba funkcinių bandymų PDF dokumentą.
 *
 * POST parametrai:
 *   - gaminio_id   : gaminys ID (sveikasis skaičius)
 *   - pdf_type     : paso | dielektriniu | funkciniu
 *   - redirect_url : nuoroda atgal į puslapį (privalo prasidėti /)
 *   - _csrf        : CSRF apsaugos žetonas
 *   - pdf_failas   : PDF failas (file upload, max 20 MB)
 */
require_once __DIR__ . '/../includes/config.php';

requireLogin();

// Tik admin ir vartotojas gali įkelti PDF (ne skaitytojas)
$user = currentUser();
if (($user['role'] ?? '') === 'skaitytojas') {
    http_response_code(403);
    die('Neturite teisių įkelti PDF failų.');
}

csrfVerify();

$gaminio_id = (int)($_POST['gaminio_id'] ?? 0);
$pdf_type   = $_POST['pdf_type'] ?? '';
$redirect   = $_POST['redirect_url'] ?? '';

// Leistini tipai ir atitinkamos DB kolumnos
$allowed = [
    'paso'         => ['pdf_col' => 'mt_paso_pdf',        'failas_col' => 'mt_paso_failas'],
    'dielektriniu' => ['pdf_col' => 'mt_dielektriniu_pdf', 'failas_col' => 'mt_dielektriniu_failas'],
    'funkciniu'    => ['pdf_col' => 'mt_funkciniu_pdf',    'failas_col' => 'mt_funkciniu_failas'],
    'nustatymu'    => ['pdf_col' => null,                  'failas_col' => null],
];

if ($gaminio_id <= 0 || !isset($allowed[$pdf_type])) {
    http_response_code(400);
    die('Neteisingi parametrai.');
}

// Redirect URL apsauga — privalo prasidėti šlaitu, kad nebūtų open redirect
if (!$redirect || !str_starts_with($redirect, '/')) {
    $redirect = '/uzsakymai.php';
}

// Nustatome, ar URL jau turi ? simbolį
$redir_sep = str_contains($redirect, '?') ? '&' : '?';

// Failo įkėlimo klaidos tikrinimas
$upload_klaida = $_FILES['pdf_failas']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($upload_klaida !== UPLOAD_ERR_OK) {
    $klaida = match($upload_klaida) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Failas per didelis (max 20 MB).',
        UPLOAD_ERR_NO_FILE                        => 'Failas nepasirinktas.',
        default                                   => 'Failo įkėlimo klaida (kodas: ' . $upload_klaida . ').',
    };
    header('Location: ' . $redirect . $redir_sep . 'pdf_klaida=' . urlencode($klaida));
    exit;
}

$failas = $_FILES['pdf_failas'];
$max_size = 20 * 1024 * 1024; // 20 MB

if ($failas['size'] > $max_size) {
    header('Location: ' . $redirect . $redir_sep . 'pdf_klaida=' . urlencode('Failas per didelis (max 20 MB).'));
    exit;
}

// MIME tipo ir plėtinio tikrinimas
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($failas['tmp_name']);
$ext   = strtolower(pathinfo($failas['name'], PATHINFO_EXTENSION));

if ($mime !== 'application/pdf' || $ext !== 'pdf') {
    header('Location: ' . $redirect . $redir_sep . 'pdf_klaida=' . urlencode('Leidžiami tik PDF formato failai.'));
    exit;
}

// Nuskaitome binarinį turinį ir išsaugome naujoje lentelėje
// (kiekvienas įkėlimas sukuria naują eilutę — esami failai neperrašomi)
$pdf_data    = file_get_contents($failas['tmp_name']);
$failas_name = basename($failas['name']);

$conn        = Database::getConnection();
$vartotojas_id = $user['id'] ?? null;

try {
    $stmt = $conn->prepare(
        "INSERT INTO gaminiu_pdf_failai (gaminio_id, pdf_tipas, failas_vardas, turinys, vartotojas_id)
         VALUES (:gaminio_id, :tipas, :vardas, :turinys, :vartotojas_id)"
    );
    $stmt->bindValue(':gaminio_id',    $gaminio_id,   PDO::PARAM_INT);
    $stmt->bindValue(':tipas',         $pdf_type);
    $stmt->bindValue(':vardas',        $failas_name);
    $stmt->bindValue(':turinys',       $pdf_data,     PDO::PARAM_LOB);
    $stmt->bindValue(':vartotojas_id', $vartotojas_id, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    header('Location: ' . $redirect . $redir_sep . 'pdf_klaida=' . urlencode('Duomenų bazės klaida įkeliant PDF.'));
    exit;
}

header('Location: ' . $redirect . $redir_sep . 'pdf_ikeltas=1');
exit;
