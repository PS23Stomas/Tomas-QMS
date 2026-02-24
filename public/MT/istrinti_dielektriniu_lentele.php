<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

header('Content-Type: application/json');

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
        echo json_encode(['ok' => false, 'error' => 'Nežinoma lentelė']);
        exit;
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
