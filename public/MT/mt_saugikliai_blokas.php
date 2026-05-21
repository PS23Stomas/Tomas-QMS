<?php
/**
 * Saugiklių įdėklų bloko valdymo koordinatorius
 *
 * Šis failas sprendžia, kurią saugiklių lentelę rodyti, atsižvelgiant į
 * gaminyje esančių transformatorių kiekį. Transformatorių kiekis nustatomas
 * automatiškai iš gaminio pavadinimo.
 *
 * Pavadinimo analizės pavyzdžiai:
 * - "MT 630/10"         → 1 transformatorius → rodoma tik 3.5 lentelė (poz. 1–15)
 * - "MT 8x10-2x250(630)" → 2 transformatoriai → rodomos abi lentelės:
 *                           3.5 (poz. 101–106 ir 301–304)
 *                           3.6 (poz. 201–206 ir 401–404)
 *
 * Įtraukiami daliniai failai (HTML šablonai):
 * - mt_saugikliai_3_5_vienas.php   — 1 transformatoriaus sekcija 3.5
 * - mt_saugikliai_3_5_dviejosek.php — 2 transformatorių sekcija 3.5
 * - mt_saugikliai_3_6_dviejosek.php — 2 transformatorių sekcija 3.6
 */

if (!isset($gaminio_pavadinimas) || empty($gaminio_pavadinimas)) {
    $gaminio_pavadinimas = '';
}

preg_match('/-(\d+)x\d{3,}/', $gaminio_pavadinimas, $match);
$transformatoriu_kiekis = isset($match[1]) ? intval($match[1]) : 1;

$stmt_35 = $conn->prepare("SELECT * FROM saugikliu_ideklai WHERE gaminio_id = :gaminio_id AND sekcija = '3.5' ORDER BY pozicijos_numeris ASC");
$stmt_35->execute([':gaminio_id' => $gaminio_id]);
$mt_saugikliai_35 = $stmt_35->fetchAll(PDO::FETCH_ASSOC);

if ($transformatoriu_kiekis == 1) {
    include __DIR__ . '/mt_saugikliai_3_5_vienas.php';
} else {
    include __DIR__ . '/mt_saugikliai_3_5_dviejosek.php';
    include __DIR__ . '/mt_saugikliai_3_6_dviejosek.php';
}
?>
