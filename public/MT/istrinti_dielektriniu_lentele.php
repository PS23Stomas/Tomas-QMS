<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../klases/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800);
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

if (!isset($_SESSION['vartotojas_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Neprisijungta']);
    exit;
}

$_SESSION['paskutine_veikla'] = time();

$conn = Database::getConnection();
$gaminys_id = (int)($_POST['gaminys_id'] ?? 0);
$lentele = $_POST['istrinti_lentele'] ?? '';

if ($gaminys_id <= 0 || empty($lentele)) {
    echo json_encode(['ok' => false, 'error' => 'Trūksta parametrų']);
    exit;
}

try {
    if ($lentele === 'saugikliai') {
        $conn->prepare("DELETE FROM mt_saugikliu_ideklai WHERE gaminio_id=?")->execute([$gaminys_id]);
    } elseif ($lentele === 'vidutines_itampos') {
        $conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id=? AND tipas='vidutines_itampos'")->execute([$gaminys_id]);
    } elseif ($lentele === 'mazos_itampos') {
        $conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id=? AND (tipas='mazos_itampos' OR tipas IS NULL)")->execute([$gaminys_id]);
    } elseif ($lentele === 'izeminimas') {
        $conn->prepare("DELETE FROM mt_izeminimo_tikrinimas WHERE gaminys_id=?")->execute([$gaminys_id]);
    } elseif ($lentele === 'prietaisai') {
        $conn->prepare("DELETE FROM bandymai_prietaisai WHERE gaminys_id=?")->execute([$gaminys_id]);
    } elseif ($lentele === 'visi') {
        $conn->beginTransaction();
        $conn->prepare("DELETE FROM mt_dielektriniai_bandymai WHERE gaminys_id=?")->execute([$gaminys_id]);
        $conn->prepare("DELETE FROM mt_izeminimo_tikrinimas WHERE gaminys_id=?")->execute([$gaminys_id]);
        $conn->prepare("DELETE FROM bandymai_prietaisai WHERE gaminys_id=?")->execute([$gaminys_id]);
        $conn->commit();
    } else {
        echo json_encode(['ok' => false, 'error' => 'Nežinoma lentelė: ' . $lentele]);
        exit;
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
