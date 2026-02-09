<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

header('Content-Type: application/json');

$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$periodas         = $_GET['periodas'] ?? 'visi';
$menuo            = $_GET['menuo'] ?? '';
$nuo              = $_GET['nuo'] ?? '';
$iki              = $_GET['iki'] ?? '';

$where_uzsakymas = '';
$where_laikotarpis = '';
$params = [];

if ($uzsakymo_numeris !== '') {
    $where_uzsakymas = "u.uzsakymo_numeris = ?";
    $params[] = $uzsakymo_numeris;
} else {
    $where_uzsakymas = "1=1";
}

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

$stmt = $pdo->prepare("
    SELECT 
        EXTRACT(WEEK FROM u.sukurtas::timestamp) AS savaite,
        COUNT(DISTINCT fb.gaminio_id) AS patikrinta_gaminiu,
        COUNT(CASE WHEN fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '' THEN 1 END) AS klaidu
    FROM mt_funkciniai_bandymai fb
    JOIN gaminiai g       ON fb.gaminio_id = g.id
    JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id
    JOIN uzsakymai u      ON g.uzsakymo_id = u.id
    $where_sql
      AND gt.grupe = 'MT'
    GROUP BY EXTRACT(WEEK FROM u.sukurtas::timestamp)
    ORDER BY savaite
");
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($data as $row) {
    $result[] = [
        'savaite' => (int)$row['savaite'],
        'patikrinta_gaminiu' => (int)$row['patikrinta_gaminiu'],
        'klaidu' => (int)$row['klaidu'],
    ];
}

echo json_encode($result);
