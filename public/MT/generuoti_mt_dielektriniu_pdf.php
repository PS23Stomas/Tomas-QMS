<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../klases/TomoQMS.php';

requireLogin();

$conn = Database::getConnection();

$gaminio_id = $_POST['gaminio_id'] ?? $_GET['gaminio_id'] ?? null;
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas = $_POST['uzsakovas'] ?? $_GET['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? $_GET['gaminio_pavadinimas'] ?? '';
$uzsakymo_id = $_POST['uzsakymo_id'] ?? $_GET['uzsakymo_id'] ?? '';
$grupe = $_POST['grupe'] ?? $_GET['grupe'] ?? 'MT';

if (!$gaminio_id) {
    header('Location: /uzsakymai.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, protokolo_nr, dielektriniai_issaugoti, gaminio_numeris, pavadinimas FROM gaminiai WHERE id=?");
$stmt->execute([$gaminio_id]);
$gaminys = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$gaminys) die("Klaida: gaminys nerastas");
$protokolo_nr = $gaminys['protokolo_nr'] ?? '';
$jau_issaugota = !empty($gaminys['dielektriniai_issaugoti']);
$gaminio_numeris_db = $gaminys['gaminio_numeris'] ?? '';
$gaminio_pavadinimas_db = $gaminys['pavadinimas'] ?? $gaminio_pavadinimas;

$vardas = $_SESSION['vardas'] ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';
$pareigos = "Kokybės inžinierius";
$data = date("Y-m-d");

$stmt = $conn->prepare("SELECT * FROM bandymai_prietaisai WHERE gaminys_id=? ORDER BY prietaiso_tipas");
$stmt->execute([$gaminio_id]);
$prietaisai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM mt_dielektriniai_bandymai WHERE gaminys_id=? AND tipas='vidutines_itampos' ORDER BY eiles_nr");
$stmt->execute([$gaminio_id]);
$vid_itampa = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM mt_dielektriniai_bandymai WHERE gaminys_id=? AND (tipas='mazos_itampos' OR tipas IS NULL) ORDER BY eiles_nr");
$stmt->execute([$gaminio_id]);
$maz_itampa = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM mt_izeminimo_tikrinimas WHERE gaminys_id=? ORDER BY eil_nr");
$stmt->execute([$gaminio_id]);
$izem = $stmt->fetchAll(PDO::FETCH_ASSOC);

$parasas_path = __DIR__ . '/../img/parasas_elga.jpg';
$parasas_base64 = '';
if (file_exists($parasas_path)) {
    $parasas_base64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($parasas_path));
}

$prietaisai_html = '';
if (!empty($prietaisai)) {
    $i = 1;
    foreach ($prietaisai as $p) {
        $prietaisai_html .= '<tr>
            <td>' . $i++ . '</td>
            <td>' . htmlspecialchars($p['prietaiso_tipas']) . '</td>
            <td>' . htmlspecialchars($p['prietaiso_nr']) . '</td>
            <td>' . htmlspecialchars($p['patikra_data']) . '</td>
            <td>' . htmlspecialchars($p['galioja_iki']) . '</td>
            <td>' . htmlspecialchars($p['sertifikato_nr']) . '</td>
        </tr>';
    }
} else {
    $prietaisai_html = '<tr><td colspan="6">Prietaisai nepriskirti</td></tr>';
}

$vid_itampa_html = '';
if (!empty($vid_itampa)) {
    $i = 1;
    foreach ($vid_itampa as $row) {
        $vid_itampa_html .= '<tr>
            <td>' . $i++ . '</td>
            <td class="text-left">' . htmlspecialchars($row['grandines_pavadinimas'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['grandines_itampa'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['bandymo_schema'] ?? 'L - (kabelio ekranas + P)') . '</td>
            <td>' . htmlspecialchars($row['bandymo_itampa_kV'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['bandymo_trukme'] ?? '') . '</td>
        </tr>';
    }
} elseif (!$jau_issaugota) {
    $t = (stripos($gaminio_pavadinimas, '2x') !== false) ? 2 : 1;
    for ($i = 1; $i <= $t; $i++) {
        $label = ($t == 1) ? 'transformatorių' : "T-$i";
        $vid_itampa_html .= '<tr>
            <td>' . $i . '</td>
            <td class="text-left">Kabeliai į ' . $label . ', Gyslos L1, L2, L3</td>
            <td>10</td>
            <td>L - (kabelio ekranas + PE)</td>
            <td>13</td>
            <td>60</td>
        </tr>';
    }
} else {
    $vid_itampa_html = '<tr><td colspan="6">Duomenys nesuvesti</td></tr>';
}

$maz_itampa_html = '';
if (!empty($maz_itampa)) {
    $i = 1;
    foreach ($maz_itampa as $row) {
        $maz_itampa_html .= '<tr>
            <td>' . $i++ . '</td>
            <td class="text-left">' . htmlspecialchars($row['aprasymas'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['itampa'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['schema1'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['schema2'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['schema3'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['schema4'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['schema5'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['schema6'] ?? '') . '</td>
        </tr>';
    }
} else {
    $maz_itampa_html = '<tr><td colspan="9">Duomenys nesuvesti</td></tr>';
}

$izem_html = '';
if (!empty($izem)) {
    foreach ($izem as $row) {
        $izem_html .= '<tr>
            <td>' . htmlspecialchars($row['eil_nr']) . '</td>
            <td class="text-left">' . htmlspecialchars($row['tasko_pavadinimas']) . '</td>
            <td>' . htmlspecialchars($row['matavimo_tasku_skaicius']) . '</td>
            <td>' . htmlspecialchars($row['varza_ohm']) . '</td>
            <td>' . htmlspecialchars($row['budas']) . '</td>
            <td>' . htmlspecialchars($row['bukle']) . '</td>
        </tr>';
    }
} else {
    $izem_html = '<tr><td colspan="6">Duomenys nesuvesti</td></tr>';
}

$html = '
<style>
body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 11px;
    line-height: 1.4;
    color: #000;
}
.company-header {
    text-align: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 2px solid #000;
}
.company-name { font-size: 15px; margin-bottom: 2px; }
.company-name span { font-weight: 700; }
.company-details { font-size: 10px; color: #333; line-height: 1.5; }
h2 { font-size: 14px; text-align: center; margin: 15px 0 10px 0; text-transform: uppercase; }
h3 { font-size: 12px; margin: 15px 0 6px 0; text-transform: uppercase; font-weight: 700; }
table.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin-bottom: 10px;
}
table.data-table td, table.data-table th {
    border: 1px solid #000;
    padding: 3px 5px;
    vertical-align: middle;
    text-align: center;
}
table.data-table th {
    background: #e9ecef;
    font-weight: 700;
    font-size: 10px;
}
.text-left { text-align: left !important; }
.meta-line { font-size: 11px; margin: 4px 0; }
.isvada { font-size: 11px; font-weight: 600; margin: 8px 0; }
.pastaba { font-size: 10px; font-style: italic; margin: 8px 0; }
.sig-table { width: 100%; margin-top: 25px; }
.sig-table td { vertical-align: bottom; padding: 5px; }
.sig-title { font-weight: 600; }
.sig-name { font-weight: 600; }
.sig-subtitle { font-size: 9px; color: #666; }
.sig-date-label { font-size: 9px; color: #666; }
.sig-label { font-size: 9px; color: #666; }
</style>

<div class="company-header">
    <div class="company-name">UAB <span>ELGA</span></div>
    <div class="company-details">
        Pramonės g. 12, LT-78150 Šiauliai, Lietuva<br>
        Tel. +370 41 594710, Faks. +370 41 594725<br>
        El. paštas: info@elga.lt | Internetas: www.elga.lt
    </div>
</div>

<h2>MT ATLIKTŲ BANDYMŲ PROTOKOLAS NR. ' . htmlspecialchars($protokolo_nr) . '</h2>

<div class="meta-line"><strong>Užsakymo Nr.:</strong> ' . htmlspecialchars($uzsakymo_numeris) . '</div>
<div class="meta-line"><strong>Užsakovas:</strong> ' . htmlspecialchars($uzsakovas) . '</div>
<div class="meta-line"><strong>Gaminys:</strong> ' . htmlspecialchars($gaminio_numeris_db ?: $gaminio_pavadinimas_db) . '</div>

<h3>Matavimai atlikti prietaisais:</h3>
<table class="data-table">
<thead>
<tr><th>Nr.</th><th>Tipas</th><th>Nr.</th><th>Patikra</th><th>Galioja iki</th><th>Sertifikatas</th></tr>
</thead>
<tbody>' . $prietaisai_html . '</tbody>
</table>

' . (!empty($vid_itampa) || !$jau_issaugota ? '
<h3>Vidutinės įtampos (6–24 kV) kabelių bandymas</h3>
<table class="data-table">
<thead>
<tr><th>Eil. Nr.</th><th>Bandymo elementų aprašymas</th><th>Un (kV)</th><th>Bandymo schema</th><th>Band. įtampa (kV)</th><th>Trukmė (min)</th></tr>
</thead>
<tbody>' . $vid_itampa_html . '</tbody>
</table>
<div class="isvada">Išvada: 10kV kabeliai bandymus išlaikė, izoliacija gera.</div>' : '') . '

' . (!empty($maz_itampa) || !$jau_issaugota ? '
<h3>0,4kV grandinių bandymas paaukštinta įtampa</h3>
<table class="data-table">
<thead>
<tr><th>Eil. Nr.</th><th>El. grandinių aprašymas</th><th>Grandinių įtampa</th><th>L1-PE</th><th>L2-PE</th><th>L3-PE</th><th>L1-L2</th><th>L2-L3</th><th>L1-L3</th></tr>
</thead>
<tbody>' . $maz_itampa_html . '</tbody>
</table>
<div class="isvada">Išvada: 0,4kV kabeliai ir laidai bandymus išlaikė, izoliacija gera.</div>' : '') . '

' . (!empty($izem) || !$jau_issaugota ? '
<h3>Grandinės tarp įžeminimo varžtų ir įžemintinų elementų tikrinimas</h3>
<table class="data-table">
<thead>
<tr><th>Eil. Nr.</th><th>Įžemintinų taškų pavadinimas</th><th>Matavimo taškų skaičius</th><th>Grandinės varža (Ω)</th><th>Būdas</th><th>Būklė</th></tr>
</thead>
<tbody>' . $izem_html . '</tbody>
</table>' : '') . '

<div class="pastaba"><strong>Pastaba:</strong> Matavimai atlikti varžto, skirto įžemiklio (įžeminimo kontūro) prijungimui. Įžeminimo varžos normos ribose. Matavimai atlikti pagal galiojančius standartus ir darbo instrukcijas.</div>

<div class="isvada">Išvada: Gaminys tinka eksploatacijai.</div>

<table class="sig-table">
    <tr>
        <td style="width:33%;text-align:left;">
            <div class="sig-title">Kokybės inžinierius</div>
            <div class="sig-name">' . htmlspecialchars($vardas . ' ' . $pavarde) . '</div>
            <div class="sig-subtitle">Pareigos, Vardas, Pavardė</div>
        </td>
        <td style="width:33%;text-align:center;">
            <div style="font-weight:600;">' . htmlspecialchars($data) . '</div>
            <div class="sig-date-label">Data</div>
        </td>
        <td style="width:33%;text-align:center;">
            ' . ($parasas_base64 ? '<img src="' . $parasas_base64 . '" style="max-height:60px;"><br>' : '') . '
            <div class="sig-label">(parašas)/antspaudas</div>
        </td>
    </tr>
</table>';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'tempDir' => '/tmp/mpdf',
    ]);

    $mpdf->SetTitle('MT Dielektriniai bandymai - ' . $uzsakymo_numeris);
    $mpdf->SetAuthor('UAB ELGA');
    $mpdf->WriteHTML($html);

    $pdf_content = $mpdf->Output('', 'S');

    $failo_pavadinimas = 'MT_Dielektriniai_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $uzsakymo_numeris) . '_' . $gaminio_id . '.pdf';

    $stmt = $conn->prepare("UPDATE gaminiai SET mt_dielektriniu_pdf = :pdf, mt_dielektriniu_failas = :failas WHERE id = :id");
    $stmt->bindParam('pdf', $pdf_content, PDO::PARAM_LOB);
    $stmt->bindParam('failas', $failo_pavadinimas);
    $stmt->bindParam('id', $gaminio_id);
    $stmt->execute();

    $gaminio_numeris = $_POST['gaminio_numeris'] ?? '';
    $params = http_build_query([
        'gaminys_id' => $gaminio_id,
        'gaminio_numeris' => $gaminio_numeris,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'grupe' => $grupe,
        'pdf_sukurtas' => 'taip'
    ]);
    header("Location: /MT/mt_dielektriniai.php?$params");
    exit;

} catch (\Exception $e) {
    $gaminio_numeris = $_POST['gaminio_numeris'] ?? '';
    $params = http_build_query([
        'gaminys_id' => $gaminio_id,
        'gaminio_numeris' => $gaminio_numeris,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'grupe' => $grupe,
        'pdf_klaida' => $e->getMessage()
    ]);
    header("Location: /MT/mt_dielektriniai.php?$params");
    exit;
}
