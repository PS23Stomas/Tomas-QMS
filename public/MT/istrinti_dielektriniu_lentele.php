<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();
$gaminys_id = (int)($_REQUEST['gaminys_id'] ?? 0);
$lentele = $_REQUEST['lentele'] ?? '';
$gaminio_numeris = $_REQUEST['gaminio_numeris'] ?? '';
$uzsakymo_numeris = $_REQUEST['uzsakymo_numeris'] ?? '';
$uzsakovas = $_REQUEST['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_REQUEST['gaminio_pavadinimas'] ?? '';
$uzsakymo_id = $_REQUEST['uzsakymo_id'] ?? '';
$grupe = $_REQUEST['grupe'] ?? 'MT';

if ($gaminys_id <= 0 || empty($lentele)) {
    die('Klaida: trūksta parametrų');
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
        die('Klaida: nežinoma lentelė');
    }
    $conn->prepare("UPDATE gaminiai SET dielektriniai_issaugoti = TRUE WHERE id = ?")->execute([$gaminys_id]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die('Klaida trinant: ' . $e->getMessage());
}

$redirect = '/MT/mt_dielektriniai.php?' . http_build_query([
    'gaminys_id' => $gaminys_id,
    'gaminio_numeris' => $gaminio_numeris,
    'uzsakymo_numeris' => $uzsakymo_numeris,
    'uzsakovas' => $uzsakovas,
    'gaminio_pavadinimas' => $gaminio_pavadinimas,
    'uzsakymo_id' => $uzsakymo_id,
    'grupe' => $grupe,
    'istrinta' => $lentele,
    't' => time(),
]);

header("Location: $redirect");
exit;
