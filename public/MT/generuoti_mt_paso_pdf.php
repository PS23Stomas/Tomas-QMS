<?php
/**
 * MT gaminio paso PDF generavimas ir išsaugojimas į duomenų bazę
 * Naudoja mPDF biblioteką HTML konvertavimui į PDF formatą
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../klases/MTPasasKomponentai.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../klases/TomoQMS.php';

requireLogin();

$conn = Database::getConnection();
$gaminys_obj = new Gaminys();

$gaminio_id = $_POST['gaminio_id'] ?? $_GET['gaminio_id'] ?? null;
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas = $_POST['uzsakovas'] ?? $_GET['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? $_GET['gaminio_pavadinimas'] ?? '';
$uzsakymo_id = $_POST['uzsakymo_id'] ?? $_GET['uzsakymo_id'] ?? '';

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
$lang = $_POST['lang'] ?? $_GET['lang'] ?? 'lt';

if (!$gaminio_id) {
    header('Location: /uzsakymai.php');
    exit;
}

if (empty($gaminio_pavadinimas)) {
    $gaminio_pavadinimas = $gaminys_obj->gautiPavadinimaPagalGaminioId($gaminio_id);
}

$gaminio_info = $gaminys_obj->gautiPagalId($gaminio_id);
$protokolo_nr = $gaminio_info['protokolo_nr'] ?? '';
$atitikmuo_kodas = !empty($gaminio_info['atitikmuo_kodas']) ? $gaminio_info['atitikmuo_kodas'] : '15.6.2';

$mt_pasas = new MTPasasKomponentai($conn, $gaminio_id);

$komp_11 = $mt_pasas->punktas1_1();
$komp_12 = $mt_pasas->punktas1_2();
$komp_13 = $mt_pasas->punktas1_3();
$komp_14 = $mt_pasas->punktas1_4();
$komp_15 = $mt_pasas->punktas1_5();
$komp_16 = $mt_pasas->punktas1_6();
$komp_21 = $mt_pasas->punktas2_1();
$komp_22 = $mt_pasas->punktas2_2();
$komp_31 = $mt_pasas->punktas3_1();
$komp_32 = $mt_pasas->punktas3_2();
$komp_33 = $mt_pasas->punktas3_3();
$komp_34 = $mt_pasas->punktas3_4();
$komp_39 = $mt_pasas->punktas3_9();
$komp_310 = $mt_pasas->punktas3_10();
$komp_311 = $mt_pasas->punktas3_11();
$komp_312 = $mt_pasas->punktas3_12();

preg_match('/(\d+)x(\d+)/', $gaminio_pavadinimas, $match_kva);
$transformatoriai_kva = $match_kva[0] ?? '';

preg_match('/(\d+x\d+)\((\d+)\)/', $gaminio_pavadinimas, $match_full);
$transformatoriu_aprasas = $match_full[1] ?? $transformatoriai_kva;
$galingumas_kva = $match_full[2] ?? ($match_kva[2] ?? '');

$trafo_kiekis = 1;
if (preg_match_all('/(\d+)x(\d+)/', $gaminio_pavadinimas, $all_matches, PREG_SET_ORDER)) {
    foreach ($all_matches as $m) {
        if (intval($m[2]) >= 100) {
            $trafo_kiekis = intval($m[1]);
            break;
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM saugikliu_ideklai WHERE gaminio_id = ? AND sekcija = '3.5' ORDER BY pozicijos_numeris ASC");
$stmt->execute([$gaminio_id]);
$mt_saugikliai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mt_saugikliai_36 = [];
if ($trafo_kiekis >= 2) {
    $stmt = $conn->prepare("SELECT * FROM saugikliu_ideklai WHERE gaminio_id = ? AND sekcija = '3.6' ORDER BY pozicijos_numeris ASC");
    $stmt->execute([$gaminio_id]);
    $mt_saugikliai_36 = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$korekcijos_data = [];
$stmt = $conn->prepare("SELECT field_key, tekstas FROM paso_teksto_korekcijos WHERE gaminio_id = ? AND lang = ?");
$stmt->execute([$gaminio_id, $lang]);
$kor_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($kor_rows as $kr) {
    $korekcijos_data[$kr['field_key']] = $kr['tekstas'];
}

function gautiTekstaPdf($key, $default, &$korekcijos_data) {
    return $korekcijos_data[$key] ?? $default;
}

function formatuotiKomponentaPdf($komp, $su_kiekiu = true) {
    $kodas = $komp['gamintojo_kodas'] ?? '';
    $gamintojas = $komp['gamintojas'] ?? '';
    $kiekis = $komp['kiekis'] ?? '';
    if (empty($kodas) && empty($gamintojas)) return '';
    $tekstas = $kodas;
    if (!empty($gamintojas)) $tekstas .= ', ' . $gamintojas;
    if ($su_kiekiu && !empty($kiekis)) $tekstas .= ' (' . $kiekis . ' vnt.)';
    return $tekstas;
}

$tipu_aprasai = [
    '15.6.1' => '10/0,4 kV įtampos mažo gabarito modulinės tranzitinės transformatorinės su vienu iki 160 kVA galios transformatoriumi techninius reikalavimus',
    '15.6.2' => '10/0,4 kV įtampos mažo gabarito modulinės transformatorinės su vienu iki 630 kVA galios transformatoriumi techninius reikalavimus',
    '15.6.3' => '10/0,4 kV įtampos mažo gabarito modulinės transformatorinės su dviem iki 630 kVA galios transformatoriais techninius reikalavimus',
    '15.2.5' => '10/0,4 kV modulinė transformatorinė su vienu iki 1000 kVA galios transformatoriumi (neigilinta) techninius reikalavimus',
    '15.2.9' => '10/0,4 kV modulinė transformatorinė su dviem 800 - 1600 kVA galios transformatoriais (neigilinta) techninius reikalavimus',
    '15.2.11' => '10/0,4 kV modulinė galinė transformatorinė su vienu iki 160 kVA galios transformatoriumi (neigilinta) techninius reikalavimus',
];

$tipas_aprasas = $tipu_aprasai[$atitikmuo_kodas] ?? 'Nenurodytas';
$serijos_nr = $uzsakymo_numeris;
$data = date("Y-m-d");
$gaminio_pasas = "MT/" . $uzsakymo_numeris;
$inzinierius = ($_SESSION['vardas'] ?? '') . ' ' . ($_SESSION['pavarde'] ?? '');

$orig_11 = '';
if (!empty($komp_11)) {
    $parts = [];
    foreach ($komp_11 as $i => $e) {
        $nr = $i + 1;
        $parts[] = "Linija$nr " . ($e['kodas'] ?? '') . ' ' . ($e['kiekis'] ?? '') . ', ' . ($e['gamintojas'] ?? '');
    }
    $orig_11 = implode("\n", $parts);
}
$text_11 = gautiTekstaPdf('1_1', $orig_11, $korekcijos_data);
$text_12 = gautiTekstaPdf('1_2', formatuotiKomponentaPdf($komp_12), $korekcijos_data);
$text_13 = gautiTekstaPdf('1_3', formatuotiKomponentaPdf($komp_13, false), $korekcijos_data);
$text_14 = gautiTekstaPdf('1_4', formatuotiKomponentaPdf($komp_14), $korekcijos_data);
$text_16 = gautiTekstaPdf('1_6', formatuotiKomponentaPdf($komp_15), $korekcijos_data);
$text_17 = gautiTekstaPdf('1_7', formatuotiKomponentaPdf($komp_16), $korekcijos_data);

$kiek_liniju = !empty($komp_11) ? count($komp_11) : 0;
$kabelio_tekstas = ($kiek_liniju > 0) ? $kiek_liniju . ' kompl. 3x240 mm2 kabeliui' : '';

$text_21 = gautiTekstaPdf('2_1', formatuotiKomponentaPdf($komp_21, false), $korekcijos_data);
$text_22 = gautiTekstaPdf('2_2', formatuotiKomponentaPdf($komp_22, false), $korekcijos_data);

$text_31 = gautiTekstaPdf('3_1', formatuotiKomponentaPdf($komp_31), $korekcijos_data);
$text_32 = gautiTekstaPdf('3_2', formatuotiKomponentaPdf($komp_32, false), $korekcijos_data);
$text_33 = gautiTekstaPdf('3_3', formatuotiKomponentaPdf($komp_33), $korekcijos_data);
$text_34 = gautiTekstaPdf('3_4', formatuotiKomponentaPdf($komp_34, false), $korekcijos_data);
$text_39 = gautiTekstaPdf('3_9', formatuotiKomponentaPdf($komp_39, false), $korekcijos_data);
$text_310 = gautiTekstaPdf('3_10', formatuotiKomponentaPdf($komp_310), $korekcijos_data);
$text_311 = gautiTekstaPdf('3_11', formatuotiKomponentaPdf($komp_311), $korekcijos_data);
$text_312 = gautiTekstaPdf('3_12', formatuotiKomponentaPdf($komp_312), $korekcijos_data);

$transformatoriaus_eilute = htmlspecialchars($transformatoriai_kva ?: 'Nenurodyta');
if (!empty($galingumas_kva)) {
    $transformatoriaus_eilute .= ' (' . htmlspecialchars($galingumas_kva) . ' kVA)';
}

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

function generuotiSaugikliuHtml($duomenys, $pozicijos) {
    $saug_map = [];
    foreach ($duomenys as $s) {
        $saug_map[(int)$s['pozicijos_numeris']] = $s;
    }
    $html = '<table class="saugikliu-sub"><tr class="sub-header">';
    foreach ($pozicijos as $p) { $html .= '<td>' . $p . '</td>'; }
    $html .= '</tr><tr>';
    foreach ($pozicijos as $p) {
        $html .= '<td>' . htmlspecialchars($saug_map[$p]['gabaritas'] ?? '') . '</td>';
    }
    $html .= '</tr><tr>';
    foreach ($pozicijos as $p) {
        $html .= '<td>' . htmlspecialchars($saug_map[$p]['nominalas'] ?? '') . '</td>';
    }
    $html .= '</tr></table>';
    return $html;
}

if ($trafo_kiekis == 1) {
    $poz_35 = range(1, 15);
    $label_35 = 'ŠĮ-0,4 sekcijos komplektuojamų saugiklių-lydžiųjų įdėklų gabaritas, nominalas:';
} else {
    $poz_35 = array_merge(range(101, 106), range(301, 304));
    $label_35 = 'Š1-0,4 (ir Š3-0,4 pagal schemą) sekcijos komplektuojamų saugiklių-lydžiųjų įdėklų gabaritas, nominalas:';
}
$saugikliu_html = generuotiSaugikliuHtml($mt_saugikliai, $poz_35);

$saugikliu_36_html = '';
if ($trafo_kiekis >= 2) {
    $poz_36 = array_merge(range(201, 206), range(401, 404));
    $saugikliu_36_html = generuotiSaugikliuHtml($mt_saugikliai_36, $poz_36);
}

$imone = getUzsakymoImone($uzsakymo_id_db);

$html = '
<style>
body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 11px;
    line-height: 1.4;
    color: #000;
}
.paso-company-header {
    text-align: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 2px solid #000;
}
.company-name { font-size: 15px; margin-bottom: 2px; }
.company-name span { font-weight: 700; }
.company-details { font-size: 10px; color: #333; line-height: 1.5; }
.paso-meta-line { font-size: 11px; margin: 6px 0; }
.paso-eso-line { font-size: 11px; margin: 5px 0 10px 0; }
.eso-code { font-weight: 600; }
.paso-main-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-bottom: 0;
}
.paso-main-table td, .paso-main-table th {
    border: 1px solid #000;
    padding: 3px 5px;
    vertical-align: top;
}
.section-header {
    background: #c6efce;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 5px;
}
.nr-col { width: 35px; text-align: left; font-weight: 400; white-space: nowrap; }
.desc-col { width: 52%; }
.saugikliu-sub {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin: 0;
}
.saugikliu-sub td {
    border: 1px solid #000;
    padding: 2px 3px;
    text-align: center;
    vertical-align: middle;
    min-width: 20px;
}
.sub-header { background: #f0f0f0; font-weight: 600; }
.paso-info-section { font-size: 11px; line-height: 1.5; margin-top: 10px; }
.paso-info-section p { margin: 3px 0; }
.sig-table { width: 100%; margin-top: 30px; }
.sig-table td { vertical-align: bottom; padding: 5px; }
.sig-title { font-weight: 600; }
.sig-name { font-weight: 600; }
.sig-subtitle { font-size: 9px; color: #666; }
.sig-date-label { font-size: 9px; color: #666; }
.sig-label { font-size: 9px; color: #666; }
</style>

<div class="paso-company-header">
    <div class="company-name">' . htmlspecialchars($imone['pavadinimas']) . '</div>
    <div class="company-details">
        ' . htmlspecialchars($imone['adresas']) . '<br>
        Tel. ' . htmlspecialchars($imone['telefonas']) . ', Faks. ' . htmlspecialchars($imone['faksas']) . '<br>
        El. paštas: ' . htmlspecialchars($imone['el_pastas']) . ' | Internetas: ' . htmlspecialchars($imone['internetas']) . '<br>
        Gaminio pasas ' . htmlspecialchars($gaminio_pasas) . '
    </div>
</div>

<div class="paso-meta-line">
    Tipas: <strong>' . htmlspecialchars($gaminio_pavadinimas) . '</strong>&nbsp;&nbsp;&nbsp;
    Gam. ser. Nr.: <strong>' . htmlspecialchars($serijos_nr) . '</strong>&nbsp;&nbsp;&nbsp;
    Data: <strong>' . htmlspecialchars($data) . '</strong>
</div>

<div class="paso-eso-line">
    Gaminys atitinka AB „Energijos skirstymo operatorius":<br>
    <span class="eso-code">' . htmlspecialchars($atitikmuo_kodas) . '</span> - ' . htmlspecialchars($tipas_aprasas) . '
</div>

<table class="paso-main-table">
    <colgroup><col style="width:35px;"><col style="width:52%;"><col></colgroup>
    <tr><td colspan="3" class="section-header">1. 12 kV įtampos skyrius:</td></tr>
    <tr><td class="nr-col">1.1</td><td class="desc-col">12 kV kabelių movos:</td><td>' . nl2br(htmlspecialchars($text_11 ?: 'Duomenys nesuvesti')) . '</td></tr>
    <tr><td class="nr-col">1.2</td><td class="desc-col">12 kV viršįtampių ribotuvai (gamintojas, tipas)</td><td>' . htmlspecialchars($text_12 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">1.3</td><td class="desc-col">12 kV skirstykla (gamintojas, modelis, narvelių konfigūracija)</td><td>' . htmlspecialchars($text_13 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">1.4</td><td class="desc-col">Galios transformatoriaus narvelio Ts komplektuojami lydymieji įdėklai (tipas, vardinė srovė, gamintojas)</td><td>' . htmlspecialchars($text_14 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">1.5</td><td class="desc-col">12 kV skirstyklos linijiniai narveliai gamintojo variklinėmis pavaromis su valdymo iš TSPĮ galimybe</td><td>Taip</td></tr>
    <tr><td class="nr-col">1.6</td><td class="desc-col">Trumpo jungimo indikatorius</td><td>' . htmlspecialchars($text_16 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">1.7</td><td class="desc-col">Įtampos indikatorius</td><td>' . htmlspecialchars($text_17 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">1.8</td><td class="desc-col">Kabelių įvedimo per pamatą angų sandarinimo medžiagos</td><td>' . htmlspecialchars($kabelio_tekstas ?: 'Duomenys nesuvesti') . '</td></tr>

    <tr><td colspan="3" class="section-header">2. Galios transformatoriaus skyrius:</td></tr>
    <tr><td class="nr-col">2.1</td><td class="desc-col">0,4 kV jungtys (galios transformatorius-0,4 kV skirstykla)</td><td>' . htmlspecialchars($text_21 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">2.2</td><td class="desc-col">10 kV jungtys (galios transformatorius-12 kV skirst. įrenginys)</td><td>' . htmlspecialchars($text_22 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">2.3</td><td class="desc-col">Transformatorių skaičius ir maksimali transformatorių galia kVA</td><td>' . $transformatoriaus_eilute . '</td></tr>

    <tr><td colspan="3" class="section-header">3. 0,4 kV įtampos skyrius:</td></tr>
    <tr><td class="nr-col">3.1</td><td class="desc-col">Įvadinis saugiklių-kirtiklių blokas TKS, (tipas, vnt., gamintojas)</td><td>' . htmlspecialchars($text_31 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">3.2</td><td class="desc-col">Įvadinis saugiklio lydusis įdėklas (gamintojas, tipas)</td><td>' . htmlspecialchars($text_32 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">3.3</td><td class="desc-col">Linijinis saugiklių-kirtiklių blokas (gamintojas, tipas, vnt.)</td><td>' . htmlspecialchars($text_33 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">3.4</td><td class="desc-col">0,4 kV saugiklių lydieji įdėklai</td><td>' . htmlspecialchars($text_34 ?: 'Duomenys nesuvesti') . '</td></tr>
    <tr><td class="nr-col">3.5</td><td class="desc-col">' . htmlspecialchars($label_35) . '</td><td style="padding:0;">' . ($saugikliu_html ?: 'Duomenys nesuvesti') . '</td></tr>
    ' . ($trafo_kiekis >= 2 ? '<tr><td class="nr-col">3.6</td><td class="desc-col">Š2-0,4 (ir Š4-0,4 pagal schemą) sekcijos komplektuojamų saugiklių-lydžiųjų įdėklų gabaritas, nominalas:</td><td style="padding:0;">' . ($saugikliu_36_html ?: 'Duomenys nesuvesti') . '</td></tr>' : '') . '
    <tr><td class="nr-col">3.9</td><td class="desc-col">0,4 kV sekcinis komutacinis aparatas (gamintojas, tipas, vardinė srovė, gr. Nr.)</td><td>' . htmlspecialchars($text_39 ?: 'Nėra, -') . '</td></tr>
    <tr><td class="nr-col">3.10</td><td class="desc-col">Sekcinio saugiklio įdėklas (gamintojas, tipas)</td><td>' . htmlspecialchars($text_310 ?: 'Nėra') . '</td></tr>
    <tr><td class="nr-col">3.11</td><td class="desc-col">Komercinė apskaita</td><td>' . htmlspecialchars($text_311 ?: 'Nėra') . '</td></tr>
    <tr><td class="nr-col">3.12</td><td class="desc-col">Kontrolinė apskaita</td><td>' . htmlspecialchars($text_312 ?: 'Duomenys nesuvesti') . '</td></tr>
</table>

<table class="paso-main-table" style="margin-top:-1px;">
    <colgroup><col style="width:35px;"><col style="width:52%;"><col></colgroup>
    <tr><td colspan="3" class="section-header">4. Transformatorinės dokumentacija</td></tr>
    <tr><td class="nr-col">4.1</td><td colspan="2">MT vienlinijinė elektrinė schema su pavaizduotais visais pase nurodytais elementais. Teikiama kartu su šiuo pasu</td></tr>
    <tr><td class="nr-col">4.2</td><td>Gaminio pasas</td><td>' . htmlspecialchars($gaminio_pasas) . '</td></tr>
</table>

<div class="paso-info-section">
    <p>' . htmlspecialchars($gaminio_pavadinimas) . ' (gaminio serijos Nr. ' . htmlspecialchars($serijos_nr) . ' ) sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271-202 standartą bandymų protokolo Nr. ' . htmlspecialchars($protokolo_nr ?: '437A') . '.</p>
    <p>Komplektuojamajai skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271 standartą. Komplektuojamajam SI-04R skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal LST EN 61439-1 ir LST EN 61439-2 standartus. Bandymų protokolo Nr ' . htmlspecialchars($protokolo_nr ?: '437A') . '.</p>
    <p>' . htmlspecialchars($gaminio_pavadinimas) . ' ir visiems komplektuojamiems įrenginiams garantija teikiama pagal gaminio serijos numerį. Gamintojas (' . htmlspecialchars($imone['pavadinimas']) . ') įsipareigoja vykdyti transformatorinės ' . htmlspecialchars($gaminio_pavadinimas) . ' garantinį aptarnavimą 24 mėn.</p>
</div>

<table class="sig-table">
    <tr>
        <td style="width:33%;text-align:left;">
            <div class="sig-title">' . htmlspecialchars($pareigos) . '</div>
            <div class="sig-name">' . htmlspecialchars($inzinierius) . '</div>
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
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 12,
        'margin_bottom' => 12,
        'tempDir' => '/tmp/mpdf',
    ]);

    $mpdf->SetTitle($grupes_pavadinimas . ' Pasas - ' . $gaminio_pasas);
    $mpdf->SetAuthor($imone['pavadinimas']);
    $mpdf->WriteHTML($html);

    $pdf_content = $mpdf->Output('', 'S');

    $failo_pavadinimas = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $uzsakymo_numeris) . '_Pasas.pdf';

    $stmt = $conn->prepare("UPDATE gaminiai SET mt_paso_pdf = :pdf, mt_paso_failas = :failas WHERE id = :id");
    $stmt->bindParam('pdf', $pdf_content, PDO::PARAM_LOB);
    $stmt->bindParam('failas', $failo_pavadinimas);
    $stmt->bindParam('id', $gaminio_id);
    $stmt->execute();

    $params = http_build_query([
        'gaminio_id' => $gaminio_id,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'lang' => $lang,
        'pdf_sukurtas' => 'taip'
    ]);
    header("Location: /MT/mt_pasas.php?$params");
    exit;

} catch (\Exception $e) {
    $params = http_build_query([
        'gaminio_id' => $gaminio_id,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'lang' => $lang,
        'pdf_klaida' => urlencode($e->getMessage())
    ]);
    header("Location: /MT/mt_pasas.php?$params");
    exit;
}
