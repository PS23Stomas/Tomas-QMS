<?php
/**
 * Saugikliu bloko valdymas pagal transformatoriu kieki
 * Pvz.: "MT 8x10-2x250(630)" -> transformatoriu kiekis = 2
 *
 * Logika:
 * - 1x tipas: rodoma tik 3.5 lentele (pozicijos 1-15)
 * - 2x tipas: rodomos abi lenteles 3.5 (poz. 101-106, 301-304) ir 3.6 (poz. 201-206, 401-404)
 */

if (!isset($gaminio_pavadinimas) || empty($gaminio_pavadinimas)) {
    $gaminio_pavadinimas = '';
}

preg_match('/-(\d+)x\d{3,}/', $gaminio_pavadinimas, $match);
$transformatoriu_kiekis = isset($match[1]) ? intval($match[1]) : 1;

$stmt_35 = $conn->prepare("SELECT * FROM mt_saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = '3.5' ORDER BY pozicijos_numeris ASC");
$stmt_35->execute([':gaminio_id' => $gaminio_id]);
$mt_saugikliai_35 = $stmt_35->fetchAll(PDO::FETCH_ASSOC);

if ($transformatoriu_kiekis == 1) {
    include __DIR__ . '/mt_saugikliai_3_5_vienas.php';
} else {
    include __DIR__ . '/mt_saugikliai_3_5_dviejosek.php';
    include __DIR__ . '/mt_saugikliai_3_6_dviejosek.php';
}
?>
