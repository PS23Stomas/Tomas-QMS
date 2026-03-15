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

if (!$gaminio_id) {
    header('Location: /uzsakymai.php');
    exit;
}

$grupes_pavadinimas = 'MT';
$uzsakymo_id_db = 0;
try {
    $stmtGr = $conn->prepare("SELECT gr.pavadinimas, u.id as uzs_id FROM gaminiai g JOIN uzsakymai u ON g.uzsakymo_id = u.id JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id WHERE g.id = :gid");
    $stmtGr->execute([':gid' => $gaminio_id]);
    $grRow = $stmtGr->fetch(PDO::FETCH_ASSOC);
    if ($grRow) {
        $grupes_pavadinimas = $grRow['pavadinimas'] ?: 'MT';
        $uzsakymo_id_db = (int)($grRow['uzs_id'] ?? 0);
    }
} catch (PDOException $e) {}

$stmt = $conn->prepare("SELECT id, protokolo_nr, pavadinimas, gaminio_numeris FROM gaminiai WHERE id=?");
$stmt->execute([$gaminio_id]);
$gaminys = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$gaminys) die("Klaida: gaminys nerastas");
$gaminio_pavadinimas_db = $gaminys['pavadinimas'] ?? '';
if (!empty($gaminio_pavadinimas_db)) {
    $gaminio_pavadinimas = $gaminio_pavadinimas_db;
}

$vardas = $_SESSION['vardas'] ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';
$data = date("Y-m-d");

$stmt = $conn->prepare("SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas FROM funkciniai_bandymai WHERE gaminio_id = ? ORDER BY eil_nr");
$stmt->execute([$gaminio_id]);
$bandymai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$parasas_base64 = '';
$pareigos = '';
$vartotojo_id = $_SESSION['vartotojas_id'] ?? 0;
if ($vartotojo_id) {
    $stmt_par = $conn->prepare("SELECT parasas, parasas_tipas, pareigos FROM vartotojai WHERE id = ?");
    $stmt_par->execute([$vartotojo_id]);
    $par_row = $stmt_par->fetch(PDO::FETCH_ASSOC);
    if ($par_row) {
        $pareigos = $par_row['pareigos'] ?? '';
        if (!empty($par_row['parasas'])) {
            $par_mime = $par_row['parasas_tipas'] ?: 'image/jpeg';
            $par_data = $par_row['parasas'];
            if (is_resource($par_data)) $par_data = stream_get_contents($par_data);
            $parasas_base64 = 'data:' . $par_mime . ';base64,' . base64_encode($par_data);
        }
    }
}
if (empty($pareigos)) $pareigos = 'Kokybės inžinierius';

$eilutes_html = '';
$atitinka_sk = 0;
$neatitinka_sk = 0;
$nepadaryta_sk = 0;
$nera_sk = 0;

if (!empty($bandymai)) {
    foreach ($bandymai as $row) {
        $isvada_txt = '';
        $isvada_spalva = '';
        switch ($row['isvada']) {
            case 'atitinka':
                $isvada_txt = 'Atitinka';
                $isvada_spalva = 'color: #28a745;';
                $atitinka_sk++;
                break;
            case 'neatitinka':
                $isvada_txt = 'Neatitinka';
                $isvada_spalva = 'color: #dc3545; font-weight: 700;';
                $neatitinka_sk++;
                break;
            case 'nėra':
                $isvada_txt = 'Nereikia';
                $isvada_spalva = 'color: #6c757d;';
                $nera_sk++;
                break;
            default:
                $isvada_txt = 'Nepadaryta';
                $isvada_spalva = 'color: #ffc107;';
                $nepadaryta_sk++;
                break;
        }

        $defektas = htmlspecialchars($row['defektas'] ?? '');
        $defekto_td = !empty($defektas) ? '<span style="color: #dc3545;">' . $defektas . '</span>' : '-';

        $eilutes_html .= '<tr>
            <td>' . htmlspecialchars($row['eil_nr']) . '</td>
            <td class="text-left">' . htmlspecialchars($row['reikalavimas'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['irase_vartotojas'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['darba_atliko'] ?? '') . '</td>
            <td style="' . $isvada_spalva . '">' . $isvada_txt . '</td>
            <td class="text-left">' . $defekto_td . '</td>
        </tr>';
    }
} else {
    $eilutes_html = '<tr><td colspan="6">Duomenys nesuvesti</td></tr>';
}

$bendra_isvada = ($neatitinka_sk === 0 && $nepadaryta_sk === 0) ? 'Visi darbai atlikti, gaminys atitinka reikalavimus.' : 'Yra neatitikimų arba neatliktų darbų.';
$bendra_spalva = ($neatitinka_sk === 0 && $nepadaryta_sk === 0) ? 'color: #28a745;' : 'color: #dc3545;';

$imone = getUzsakymoImone($uzsakymo_id_db);

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
.stats-box { margin: 10px 0; font-size: 11px; }
.sig-table { width: 100%; margin-top: 25px; }
.sig-table td { vertical-align: bottom; padding: 5px; }
.sig-title { font-weight: 600; }
.sig-name { font-weight: 600; }
.sig-subtitle { font-size: 9px; color: #666; }
.sig-date-label { font-size: 9px; color: #666; }
.sig-label { font-size: 9px; color: #666; }
</style>

<div class="company-header">
    <div class="company-name">' . htmlspecialchars($imone['pavadinimas']) . '</div>
    <div class="company-details">
        ' . htmlspecialchars($imone['adresas']) . '<br>
        Tel. ' . htmlspecialchars($imone['telefonas']) . ', Faks. ' . htmlspecialchars($imone['faksas']) . '<br>
        El. paštas: ' . htmlspecialchars($imone['el_pastas']) . ' | Internetas: ' . htmlspecialchars($imone['internetas']) . '
    </div>
</div>

<h2>' . htmlspecialchars(mb_strtoupper($grupes_pavadinimas)) . ' ATLIKTŲ DARBŲ PILDYMO FORMA</h2>

<div class="meta-line"><strong>Užsakymo Nr.:</strong> ' . htmlspecialchars($uzsakymo_numeris) . '</div>
<div class="meta-line"><strong>Užsakovas:</strong> ' . htmlspecialchars($uzsakovas) . '</div>
<div class="meta-line"><strong>Gaminys:</strong> ' . htmlspecialchars($gaminio_pavadinimas) . '</div>
<div class="meta-line"><strong>Data:</strong> ' . htmlspecialchars($data) . '</div>

<h3>Gamybos reikalavimai ir atliktų darbų rezultatai</h3>
<table class="data-table">
<thead>
<tr>
    <th style="width:40px;">Nr.</th>
    <th>Reikalavimas</th>
    <th style="width:100px;">Įrašė</th>
    <th style="width:100px;">Atliko</th>
    <th style="width:80px;">Išvada</th>
    <th style="width:150px;">Defektas</th>
</tr>
</thead>
<tbody>' . $eilutes_html . '</tbody>
</table>

<div class="stats-box">
    <strong>Statistika:</strong>
    Atitinka: <span style="color: #28a745; font-weight: 600;">' . $atitinka_sk . '</span> |
    Neatitinka: <span style="color: #dc3545; font-weight: 600;">' . $neatitinka_sk . '</span> |
    Nepadaryta: <span style="color: #ffc107; font-weight: 600;">' . $nepadaryta_sk . '</span> |
    Nereikia: <span style="color: #6c757d;">' . $nera_sk . '</span>
</div>

<div class="isvada" style="' . $bendra_spalva . '">Išvada: ' . $bendra_isvada . '</div>

<table class="sig-table">
    <tr>
        <td style="width:33%;text-align:left;">
            <div class="sig-title">' . htmlspecialchars($pareigos) . '</div>
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

    $mpdf->SetTitle($grupes_pavadinimas . ' Funkciniai bandymai - ' . $uzsakymo_numeris);
    $mpdf->SetAuthor($imone['pavadinimas']);
    $mpdf->WriteHTML($html);

    $pdf_content = $mpdf->Output('', 'S');

    $failo_pavadinimas = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $grupes_pavadinimas) . '_Funkciniai_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $uzsakymo_numeris) . '_' . $gaminio_id . '.pdf';

    $stmt = $conn->prepare("UPDATE gaminiai SET mt_funkciniu_pdf = :pdf, mt_funkciniu_failas = :failas WHERE id = :id");
    $stmt->bindParam('pdf', $pdf_content, PDO::PARAM_LOB);
    $stmt->bindParam('failas', $failo_pavadinimas);
    $stmt->bindParam('id', $gaminio_id);
    $stmt->execute();

    $params = http_build_query([
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_id' => $gaminio_id,
        'uzsakymo_id' => $uzsakymo_id,
        'pdf_sukurtas' => 'taip'
    ]);
    header("Location: /mt_funkciniai_bandymai.php?$params");
    exit;

} catch (\Exception $e) {
    $params = http_build_query([
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_id' => $gaminio_id,
        'uzsakymo_id' => $uzsakymo_id,
        'pdf_klaida' => 'Nepavyko sugeneruoti PDF dokumento'
    ]);
    header("Location: /mt_funkciniai_bandymai.php?$params");
    exit;
}
