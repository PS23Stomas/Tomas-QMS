<?php
/**
 * Saugiklių bloko valdymas pagal transformatorių kiekį
 * Pvz.: "MMT 2x630(630)" -> transformatorių kiekis = 2
 * 
 * Logika:
 * - 1x tipas: rodoma tik 3.5 lentelė
 * - 2x tipas: rodomos abi lentelės 3.5 ir 3.6
 */

// Gaminio pavadinimas turi būti perduotas prieš include
if (!isset($gaminio_pavadinimas) || empty($gaminio_pavadinimas)) {
    $gaminio_pavadinimas = '';
}

// Tikriname ar gaminio tipe yra "1x" ar "2x" (pvz. "MT 8k10-1x250(630)" arba "MT 8k10-2x250(630)")
// Regex ieško patrną kur po skaitmens ir "x" eina 3+ skaitmenų skaičius (galios reikšmė)
preg_match('/(\d+)x(\d{3,})/', $gaminio_pavadinimas, $match);
$transformatoriu_kiekis = isset($match[1]) ? intval($match[1]) : 1;

// 1x tipas - tik 3.5 lentelė, 2x tipas - abi lentelės 3.5 ir 3.6
if ($transformatoriu_kiekis == 1) {
    include __DIR__ . '/mt_saugikliai_3_5_vienas.php';
} else {
    include __DIR__ . '/mt_saugikliai_3_5_dviejosek.php';
    include __DIR__ . '/mt_saugikliai_3_6_dviejosek.php';
}
?>
