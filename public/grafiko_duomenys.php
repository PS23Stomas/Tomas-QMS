<?php
/**
 * Grafiko duomenų API - savaitiniai defektų/gaminių skaičiai JSON formatu
 *
 * Šis failas yra API galinis taškas (endpoint), grąžinantis JSON duomenis
 * su savaitiniais defektų ir patikrintų gaminių skaičiais Chart.js diagramai.
 * Palaiko tuos pačius filtrus kaip ir mt_statistika.php puslapis.
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();

header('Content-Type: application/json');

/* --- Filtrų parametrų nuskaitymas iš GET užklausos --- */
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$periodas         = $_GET['periodas'] ?? 'visi';
$menuo            = $_GET['menuo'] ?? '';
$nuo              = $_GET['nuo'] ?? '';
$iki              = $_GET['iki'] ?? '';
$grupe            = $_GET['grupe'] ?? 'MT';

/* --- WHERE sąlygos sudarymas (analogiškai kaip mt_statistika.php) --- */
$where_uzsakymas = '';
$where_laikotarpis = '';
$params = [];

/* Užsakymo numerio filtras */
if ($uzsakymo_numeris !== '') {
    $where_uzsakymas = "u.uzsakymo_numeris = ?";
    $params[] = $uzsakymo_numeris;
} else {
    $where_uzsakymas = "1=1";
}

/* Laikotarpio filtras: pagal mėnesį, datų intervalą arba periodą */
if ($menuo !== '') {
    $where_laikotarpis = " AND TO_CHAR(u.sukurtas::timestamp, 'YYYY-MM') = ?";
    $params[] = $menuo;
} elseif ($nuo !== '' && $iki !== '') {
    $where_laikotarpis = " AND DATE(u.sukurtas) BETWEEN ? AND ?";
    $params[] = $nuo;
    $params[] = $iki;
} elseif ($periodas === '1m') {
    $where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 month'";
} elseif ($periodas === '6m') {
    $where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '6 month'";
} elseif ($periodas === '1y') {
    $where_laikotarpis = " AND DATE(u.sukurtas) >= CURRENT_DATE - INTERVAL '1 year'";
}

$where_sql = "WHERE $where_uzsakymas $where_laikotarpis";

/* --- Savaitinė agregavimo SQL užklausa --- */
/* Grupuojame pagal savaitės numerį, skaičiuojame unikalius gaminius ir defektus */
$stmt = $pdo->prepare("
    SELECT 
        EXTRACT(WEEK FROM u.sukurtas::timestamp) AS savaite,
        COUNT(DISTINCT fb.gaminio_id) AS patikrinta_gaminiu,
        COUNT(CASE WHEN fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' THEN 1 END) AS klaidu
    FROM funkciniai_bandymai fb
    JOIN gaminiai g          ON fb.gaminio_id = g.id
    JOIN uzsakymai u         ON g.uzsakymo_id = u.id
    JOIN gaminiu_rusys gr    ON u.gaminiu_rusis_id = gr.id
    $where_sql
      AND gr.pavadinimas = ?
    GROUP BY EXTRACT(WEEK FROM u.sukurtas::timestamp)
    ORDER BY savaite
");
$params[] = $grupe;
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* --- JSON išvesties formavimas --- */
/* Kiekviena eilutė konvertuojama į sveikuosius skaičius ir grąžinama kaip JSON masyvas */
$result = [];
foreach ($data as $row) {
    $result[] = [
        'savaite' => (int)$row['savaite'],
        'patikrinta_gaminiu' => (int)$row['patikrinta_gaminiu'],
        'klaidu' => (int)$row['klaidu'],
    ];
}

echo json_encode($result);
