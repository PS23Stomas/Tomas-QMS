<?php
/**
 * MT gaminio paso generavimo puslapis - komponentų susiejimas, teksto korekcijos, spausdinimas
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../klases/MTPasasKomponentai.php';

requireLogin();

$conn = Database::getConnection();
$gaminys_obj = new Gaminys();

$gaminio_id = $_GET['gaminio_id'] ?? $_POST['gaminio_id'] ?? null;
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas = $_GET['uzsakovas'] ?? $_POST['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_GET['gaminio_pavadinimas'] ?? '';
$uzsakymo_id = $_GET['uzsakymo_id'] ?? '';
$lang = $_GET['lang'] ?? 'lt';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issaugoti_atitikmuo'])) {
    $naujas_kodas = $_POST['atitikmuo_kodas'] ?? '15.6.2';
    $stmt = $conn->prepare("UPDATE gaminiai SET atitikmuo_kodas = ? WHERE id = ?");
    $stmt->execute([$naujas_kodas, $gaminio_id]);
    $atitikmuo_kodas = $naujas_kodas;
    $params = http_build_query(['gaminio_id' => $gaminio_id, 'uzsakymo_numeris' => $uzsakymo_numeris, 'uzsakovas' => $uzsakovas, 'gaminio_pavadinimas' => $gaminio_pavadinimas, 'uzsakymo_id' => $uzsakymo_id, 'lang' => $lang, 'issaugota' => 'taip']);
    header("Location: /MT/mt_pasas.php?$params");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issaugoti_protokola'])) {
    $naujas_nr = trim($_POST['protokolo_nr'] ?? '');
    if ($naujas_nr !== '') {
        $stmt = $conn->prepare("UPDATE gaminiai SET protokolo_nr = ? WHERE id = ?");
        $stmt->execute([$naujas_nr, $gaminio_id]);
        $protokolo_nr = $naujas_nr;
    }
    $params = http_build_query(['gaminio_id' => $gaminio_id, 'uzsakymo_numeris' => $uzsakymo_numeris, 'uzsakovas' => $uzsakovas, 'gaminio_pavadinimas' => $gaminio_pavadinimas, 'uzsakymo_id' => $uzsakymo_id, 'lang' => $lang, 'issaugota' => 'taip']);
    header("Location: /MT/mt_pasas.php?$params");
    exit;
}

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

function gautiTeksta($key, $default, &$korekcijos_data) {
    return $korekcijos_data[$key] ?? $default;
}

function turiKorekcija($key, &$korekcijos_data) {
    return isset($korekcijos_data[$key]);
}

function formatuotiKomponenta($komp, $su_kiekiu = true) {
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
$pareigos = '';
$vartotojo_id = $_SESSION['vartotojas_id'] ?? 0;
if ($vartotojo_id) {
    $stmt_par = $conn->prepare("SELECT pareigos FROM vartotojai WHERE id = ?");
    $stmt_par->execute([$vartotojo_id]);
    $par_row = $stmt_par->fetch(PDO::FETCH_ASSOC);
    if ($par_row) $pareigos = $par_row['pareigos'] ?? '';
}
if (empty($pareigos)) $pareigos = 'Kokybės inžinierius';

$nuoroda_atgal = "/uzsakymai.php?view=" . urlencode($uzsakymo_id);

$turi_pdf = !empty($gaminio_info['mt_paso_failas']);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.paso-page {
    max-width: 960px;
    margin: 0 auto;
    background: #fff;
    padding: 25px 35px;
    border: 1px solid #ccc;
    font-family: 'Inter', Arial, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}
.paso-company-header {
    text-align: center;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 2px solid #000;
}
.paso-company-header .company-name {
    font-size: 16px;
    margin-bottom: 2px;
}
.paso-company-header .company-name span {
    font-weight: 700;
}
.paso-company-header .company-details {
    font-size: 11px;
    color: #333;
    line-height: 1.5;
}
.paso-company-header .company-details a {
    color: #0066cc;
    text-decoration: none;
    font-weight: 600;
}
.paso-company-header .paso-title-line {
    font-size: 12px;
    margin-top: 8px;
}
.paso-meta-line {
    font-size: 12px;
    margin: 8px 0;
}
.paso-eso-line {
    font-size: 12px;
    margin: 6px 0 12px 0;
}
.paso-eso-line .eso-code {
    font-weight: 600;
}
.paso-main-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-bottom: 0;
}
.paso-main-table td, .paso-main-table th {
    border: 1px solid #000;
    padding: 4px 6px;
    vertical-align: top;
}
.paso-main-table .section-header {
    background: #c6efce;
    font-weight: 700;
    font-size: 12px;
    padding: 5px 6px;
}
.paso-main-table .nr-col {
    width: 40px;
    text-align: left;
    font-weight: 400;
    white-space: nowrap;
}
.paso-main-table .desc-col {
    width: 52%;
}
.paso-main-table .val-col {
    width: auto;
}
.paso-main-table .highlight {
    background: #fff3cd;
}
.paso-main-table .koreguota {
    background: #d1ecf1;
}
.edit-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 11px;
    padding: 1px 5px;
    border-radius: 3px;
    color: #888;
    float: right;
}
.edit-btn:hover {
    background: #e9ecef;
    color: #333;
}
.saugikliu-sub {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin: 0;
}
.saugikliu-sub td {
    border: 1px solid #000;
    padding: 2px 4px;
    text-align: center;
    vertical-align: middle;
    min-width: 28px;
}
.saug-input {
    width: 100%;
    min-width: 24px;
    max-width: 80px;
    border: 1px solid transparent;
    background: transparent;
    text-align: center;
    font-size: 11px;
    padding: 2px 1px;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
}
.saug-input:hover {
    border-color: #adb5bd;
    background: #f8f9fa;
}
.saug-input:focus {
    border-color: #0d6efd;
    background: #fff;
    box-shadow: 0 0 0 1px #0d6efd33;
}
.saug-save-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 6px;
    background: #f8f9fa;
    border-top: 1px solid #000;
}
.saug-save-status {
    font-size: 11px;
    color: #198754;
    font-weight: 500;
}
.saugikliu-sub .sub-header {
    background: #f0f0f0;
    font-weight: 600;
}
.paso-info-section {
    font-size: 12px;
    line-height: 1.6;
    margin-top: 12px;
    padding: 0 2px;
}
.paso-info-section p {
    margin: 4px 0;
}
.paso-signature-zone {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 35px;
    padding-top: 15px;
}
.paso-sig-left {
    font-size: 12px;
    line-height: 1.6;
}
.paso-sig-left .sig-title {
    font-weight: 600;
}
.paso-sig-left .sig-name {
    font-weight: 600;
}
.paso-sig-left .sig-subtitle {
    font-size: 10px;
    color: #666;
}
.paso-sig-center {
    text-align: center;
    font-size: 12px;
}
.paso-sig-center .sig-date-value {
    font-weight: 600;
    margin-bottom: 2px;
}
.paso-sig-center .sig-date-label {
    font-size: 10px;
    color: #666;
}
.paso-sig-right {
    text-align: center;
}
.paso-sig-right img {
    max-height: 70px;
    margin-bottom: 2px;
}
.paso-sig-right .sig-label {
    font-size: 10px;
    color: #666;
}
.paso-toolbar-area {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.paso-eso-select {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 12px;
    background: #fff;
}
.paso-protokolas input {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 12px;
    width: 200px;
}
.edit-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.edit-modal-overlay.active {
    display: flex;
}
.edit-modal {
    background: #fff;
    border-radius: 8px;
    padding: 24px;
    width: 500px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.edit-modal h4 {
    margin: 0 0 15px 0;
    font-size: 16px;
}
.edit-modal textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
    resize: vertical;
    box-sizing: border-box;
}
.edit-modal .modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 15px;
}

@media print {
    .no-print, .paso-toolbar-area, .sidebar, .main-header, .d-flex.justify-content-between, .edit-btn { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .paso-page { border: none; box-shadow: none; max-width: 100%; padding: 10mm; }
    .print-only { display: block !important; }
    body { background: #fff !important; }
    .saug-input { border: none !important; background: transparent !important; box-shadow: none !important; padding: 0 !important; }
    .saug-save-row { display: none !important; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <h4 class="mb-0">MT Gaminio Pasas</h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= htmlspecialchars($nuoroda_atgal) ?>" class="btn btn-secondary btn-sm">Grįžti į užsakymą</a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">Spausdinti</button>
        <form method="POST" action="/MT/generuoti_mt_paso_pdf.php" style="display:inline;">
            <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
            <input type="hidden" name="gaminio_pavadinimas" value="<?= htmlspecialchars($gaminio_pavadinimas) ?>">
            <input type="hidden" name="uzsakymo_id" value="<?= htmlspecialchars($uzsakymo_id) ?>">
            <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">
            <button type="submit" class="btn btn-success btn-sm" data-testid="button-generuoti-pdf">Generuoti PDF</button>
        </form>
        <?php if ($turi_pdf): ?>
        <a href="/MT/mt_paso_pdf.php?gaminio_id=<?= urlencode($gaminio_id) ?>" target="_blank" class="btn btn-primary btn-sm" data-testid="button-perziureti-pdf">Peržiūrėti PDF</a>
        <a href="/MT/mt_paso_pdf.php?gaminio_id=<?= urlencode($gaminio_id) ?>&atsisiusti=1" class="btn btn-outline-secondary btn-sm" data-testid="button-atsisiusti-pdf">Atsisiųsti PDF</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['pdf_sukurtas'])): ?>
<div class="alert alert-success alert-dismissible fade show no-print" role="alert" style="margin-bottom: 15px;">
    PDF sugeneruotas ir išsaugotas sėkmingai!
    <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['pdf_klaida'])): ?>
<div class="alert alert-danger alert-dismissible fade show no-print" role="alert" style="margin-bottom: 15px;">
    Klaida generuojant PDF: <?= htmlspecialchars(urldecode($_GET['pdf_klaida'])) ?>
    <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['issaugota'])): ?>
<div class="alert alert-success alert-dismissible fade show no-print" role="alert" style="margin-bottom: 15px;">
    Duomenys išsaugoti sėkmingai!
    <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button>
</div>
<?php endif; ?>

<div class="paso-toolbar-area no-print">
    <div style="width: 100%;">
        <label style="font-size: 12px; font-weight: 600; margin-bottom: 4px; display: block;">Gaminys atitinka AB „Energijos skirstymo operatorius":</label>
        <form method="POST" style="display: flex; gap: 8px; align-items: center;">
            <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
            <input type="hidden" name="issaugoti_atitikmuo" value="1">
            <select name="atitikmuo_kodas" class="paso-eso-select" style="flex: 1;" onchange="this.form.submit()">
                <?php foreach ($tipu_aprasai as $kodas => $aprasymas): ?>
                <option value="<?= $kodas ?>" <?= ($kodas === $atitikmuo_kodas) ? 'selected' : '' ?>><?= $kodas ?> - <?= htmlspecialchars($aprasymas) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="paso-protokolas" style="width: 100%; margin-top: 8px;">
        <form method="POST" style="display: flex; gap: 8px; align-items: center; width: 100%;">
            <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
            <input type="hidden" name="issaugoti_protokola" value="1">
            <label style="font-size: 12px; font-weight: 600; white-space: nowrap;">Protokolo Nr.:</label>
            <input type="text" name="protokolo_nr" value="<?= htmlspecialchars($protokolo_nr) ?>" placeholder="T1300">
            <button type="submit" class="btn btn-sm btn-success">Išsaugoti</button>
        </form>
    </div>
</div>

<div class="paso-page" id="paso-print-area">

    <?php $imone = getImonesNustatymai(); ?>
    <div class="paso-company-header">
        <div class="company-name"><?= h($imone['pavadinimas']) ?></div>
        <div class="company-details">
            <?= h($imone['adresas']) ?><br>
            Tel. <?= h($imone['telefonas']) ?>, Faks. <?= h($imone['faksas']) ?><br>
            El. paštas: <?= h($imone['el_pastas']) ?> | Internetas: <a href="http://<?= h($imone['internetas']) ?>"><?= h($imone['internetas']) ?></a><br>
            Gaminio pasas <?= htmlspecialchars($gaminio_pasas) ?>
        </div>
    </div>

    <div class="paso-meta-line">
        Tipas: <strong><?= htmlspecialchars($gaminio_pavadinimas) ?></strong>&nbsp;&nbsp;&nbsp;
        Gam. ser. Nr.: <strong><?= htmlspecialchars($serijos_nr) ?></strong>&nbsp;&nbsp;&nbsp;
        Data: <strong><?= htmlspecialchars($data) ?></strong>
    </div>

    <div class="paso-eso-line">
        Gaminys atitinka AB „Energijos skirstymo operatorius":<br>
        <span class="eso-code"><?= htmlspecialchars($atitikmuo_kodas) ?></span> - <?= htmlspecialchars($tipas_aprasas) ?>
    </div>

    <table class="paso-main-table">
        <colgroup>
            <col style="width: 40px;">
            <col style="width: 52%;">
            <col>
        </colgroup>

        <tr><td colspan="3" class="section-header">1. 12 kV įtampos skyrius:</td></tr>

        <?php
        $orig_11 = '';
        if (!empty($komp_11)) {
            $parts = [];
            foreach ($komp_11 as $i => $e) {
                $nr = $i + 1;
                $parts[] = "Linija$nr " . ($e['kodas'] ?? '') . ' ' . ($e['kiekis'] ?? '') . ', ' . ($e['gamintojas'] ?? '');
            }
            $orig_11 = implode("\n", $parts);
        }
        $text_11 = gautiTeksta('1_1', $orig_11, $korekcijos_data);
        $klase_11 = turiKorekcija('1_1', $korekcijos_data) ? 'koreguota' : (empty($komp_11) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.1</td>
            <td class="desc-col">12 kV kabelių movos:</td>
            <td class="val-col <?= $klase_11 ?>">
                <?= nl2br(htmlspecialchars($text_11 ?: 'Duomenys nesuvesti')) ?>
                <button type="button" class="edit-btn no-print" data-field="1_1" data-label="1.1 - 12 kV kabelių movos" data-text="<?= htmlspecialchars($text_11, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_12 = formatuotiKomponenta($komp_12);
        $text_12 = gautiTeksta('1_2', $orig_12, $korekcijos_data);
        $klase_12 = turiKorekcija('1_2', $korekcijos_data) ? 'koreguota' : (empty($komp_12['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.2</td>
            <td class="desc-col">12 kV viršįtampių ribotuvai (gamintojas, tipas)</td>
            <td class="val-col <?= $klase_12 ?>">
                <?= htmlspecialchars($text_12 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_2" data-label="1.2 - Viršįtampių ribotuvai" data-text="<?= htmlspecialchars($text_12, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_13 = formatuotiKomponenta($komp_13, false);
        $text_13 = gautiTeksta('1_3', $orig_13, $korekcijos_data);
        $klase_13 = turiKorekcija('1_3', $korekcijos_data) ? 'koreguota' : (empty($komp_13['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.3</td>
            <td class="desc-col">12 kV skirstykla (gamintojas, modelis, narvelių konfigūracija)</td>
            <td class="val-col <?= $klase_13 ?>">
                <?= htmlspecialchars($text_13 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_3" data-label="1.3 - Skirstykla" data-text="<?= htmlspecialchars($text_13, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_14 = formatuotiKomponenta($komp_14);
        $text_14 = gautiTeksta('1_4', $orig_14, $korekcijos_data);
        $klase_14 = turiKorekcija('1_4', $korekcijos_data) ? 'koreguota' : (empty($komp_14['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.4</td>
            <td class="desc-col">Galios transformatoriaus narvelio Ts komplektuojami lydymieji įdėklai (tipas, vardinė srovė, gamintojas)</td>
            <td class="val-col <?= $klase_14 ?>">
                <?= htmlspecialchars($text_14 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_4" data-label="1.4 - Lydymieji įdėklai" data-text="<?= htmlspecialchars($text_14, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <tr>
            <td class="nr-col">1.5</td>
            <td class="desc-col">12 kV skirstyklos linijiniai narveliai gamintojo variklinėmis pavaromis su valdymo iš TSPĮ galimybe</td>
            <td class="val-col">Taip</td>
        </tr>

        <?php
        $orig_16 = formatuotiKomponenta($komp_15);
        $text_16 = gautiTeksta('1_6', $orig_16, $korekcijos_data);
        $klase_16 = turiKorekcija('1_6', $korekcijos_data) ? 'koreguota' : (empty($komp_15['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.6</td>
            <td class="desc-col">Trumpo jungimo indikatorius</td>
            <td class="val-col <?= $klase_16 ?>">
                <?= htmlspecialchars($text_16 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_6" data-label="1.6 - Trumpo jungimo indikatorius" data-text="<?= htmlspecialchars($text_16, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_17 = formatuotiKomponenta($komp_16);
        $text_17 = gautiTeksta('1_7', $orig_17, $korekcijos_data);
        $klase_17 = turiKorekcija('1_7', $korekcijos_data) ? 'koreguota' : (empty($komp_16['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.7</td>
            <td class="desc-col">Įtampos indikatorius</td>
            <td class="val-col <?= $klase_17 ?>">
                <?= htmlspecialchars($text_17 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_7" data-label="1.7 - Įtampos indikatorius" data-text="<?= htmlspecialchars($text_17, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $kiek_liniju = !empty($komp_11) ? count($komp_11) : 0;
        $kabelio_tekstas = ($kiek_liniju > 0) ? $kiek_liniju . ' kompl. 3x240 mm2 kabeliui' : 'Duomenys nesuvesti';
        $klase_18 = ($kiek_liniju == 0) ? 'highlight' : '';
        ?>
        <tr>
            <td class="nr-col">1.8</td>
            <td class="desc-col">Kabelių įvedimo per pamatą angų sandarinimo medžiagos</td>
            <td class="val-col <?= $klase_18 ?>"><?= htmlspecialchars($kabelio_tekstas) ?></td>
        </tr>

        <tr><td colspan="3" class="section-header">2. Galios transformatoriaus skyrius:</td></tr>

        <?php
        $orig_21 = formatuotiKomponenta($komp_21, false);
        $text_21 = gautiTeksta('2_1', $orig_21, $korekcijos_data);
        $klase_21 = turiKorekcija('2_1', $korekcijos_data) ? 'koreguota' : (empty($komp_21['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">2.1</td>
            <td class="desc-col">0,4 kV jungtys (galios transformatorius-0,4 kV skirstykla)</td>
            <td class="val-col <?= $klase_21 ?>">
                <?= htmlspecialchars($text_21 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="2_1" data-label="2.1 - 0,4 kV jungtys" data-text="<?= htmlspecialchars($text_21, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_22 = formatuotiKomponenta($komp_22, false);
        $text_22 = gautiTeksta('2_2', $orig_22, $korekcijos_data);
        $klase_22 = turiKorekcija('2_2', $korekcijos_data) ? 'koreguota' : (empty($komp_22['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">2.2</td>
            <td class="desc-col">10 kV jungtys (galios transformatorius-12 kV skirst. įrenginys)</td>
            <td class="val-col <?= $klase_22 ?>">
                <?= htmlspecialchars($text_22 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="2_2" data-label="2.2 - 10 kV jungtys" data-text="<?= htmlspecialchars($text_22, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <tr>
            <td class="nr-col">2.3</td>
            <td class="desc-col">Transformatorių skaičius ir maksimali transformatorių galia kVA</td>
            <td class="val-col <?= empty($transformatoriai_kva) ? 'highlight' : '' ?>">
                <?= htmlspecialchars($transformatoriai_kva ?: 'Nenurodyta') ?><?= !empty($galingumas_kva) ? ' (' . htmlspecialchars($galingumas_kva) . ' kVA)' : '' ?>
            </td>
        </tr>

        <tr><td colspan="3" class="section-header">3. 0,4 kV įtampos skyrius:</td></tr>

        <?php
        $orig_31 = formatuotiKomponenta($komp_31);
        $text_31 = gautiTeksta('3_1', $orig_31, $korekcijos_data);
        $klase_31 = turiKorekcija('3_1', $korekcijos_data) ? 'koreguota' : (empty($komp_31['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.1</td>
            <td class="desc-col">Įvadinis saugiklių-kirtiklių blokas TKS, (tipas, vnt., gamintojas)</td>
            <td class="val-col <?= $klase_31 ?>">
                <?= htmlspecialchars($text_31 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="3_1" data-label="3.1 - TKS blokas" data-text="<?= htmlspecialchars($text_31, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_32 = formatuotiKomponenta($komp_32, false);
        $text_32 = gautiTeksta('3_2', $orig_32, $korekcijos_data);
        $klase_32 = turiKorekcija('3_2', $korekcijos_data) ? 'koreguota' : (empty($komp_32['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.2</td>
            <td class="desc-col">Įvadinis saugiklio lydusis įdėklas (gamintojas, tipas)</td>
            <td class="val-col <?= $klase_32 ?>">
                <?= htmlspecialchars($text_32 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="3_2" data-label="3.2 - Įvadinis lydusis" data-text="<?= htmlspecialchars($text_32, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_33 = formatuotiKomponenta($komp_33);
        $text_33 = gautiTeksta('3_3', $orig_33, $korekcijos_data);
        $klase_33 = turiKorekcija('3_3', $korekcijos_data) ? 'koreguota' : (empty($komp_33['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.3</td>
            <td class="desc-col">Linijinis saugiklių-kirtiklių blokas (gamintojas, tipas, vnt.)</td>
            <td class="val-col <?= $klase_33 ?>">
                <?= htmlspecialchars($text_33 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="3_3" data-label="3.3 - Linijinis blokas" data-text="<?= htmlspecialchars($text_33, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_34 = formatuotiKomponenta($komp_34, false);
        $text_34 = gautiTeksta('3_4', $orig_34, $korekcijos_data);
        $klase_34 = turiKorekcija('3_4', $korekcijos_data) ? 'koreguota' : (empty($komp_34['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.4</td>
            <td class="desc-col">0,4 kV saugiklių lydieji įdėklai</td>
            <td class="val-col <?= $klase_34 ?>">
                <?= htmlspecialchars($text_34 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="3_4" data-label="3.4 - Lydieji įdėklai" data-text="<?= htmlspecialchars($text_34, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $saug_map = [];
        foreach ($mt_saugikliai as $s) {
            $saug_map[(int)$s['pozicijos_numeris']] = $s;
        }
        if ($trafo_kiekis == 1) {
            $poz_35 = range(1, 15);
            $label_35 = 'ŠĮ-0,4 sekcijos komplektuojamų saugiklių-lydžiųjų įdėklų gabaritas, nominalas:';
        } else {
            $poz_35 = array_merge(range(301, 304), range(102, 107));
            $label_35 = 'Š1-0,4 (ir Š3-0,4 pagal schemą) sekcijos komplektuojamų saugiklių-lydžiųjų įdėklų gabaritas, nominalas:';
        }
        ?>
        <tr>
            <td class="nr-col" style="vertical-align: top;">3.5</td>
            <td class="desc-col" style="vertical-align: top;"><?= htmlspecialchars($label_35) ?></td>
            <td class="val-col" style="padding: 0; position: relative;">
                <table class="saugikliu-sub" id="saugikliai-35-table">
                    <tr class="sub-header">
                        <?php foreach ($poz_35 as $p): ?>
                        <td><?= $p ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($poz_35 as $p): ?>
                        <td>
                            <input type="text" class="saug-input" name="gabaritas_35_<?= $p ?>" value="<?= htmlspecialchars($saug_map[$p]['gabaritas'] ?? '') ?>" data-poz="<?= $p ?>" data-field="gabaritas" data-sekcija="3.5" placeholder="-" data-testid="input-gabaritas-35-<?= $p ?>">
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($poz_35 as $p): ?>
                        <td>
                            <input type="text" class="saug-input" name="nominalas_35_<?= $p ?>" value="<?= htmlspecialchars($saug_map[$p]['nominalas'] ?? '') ?>" data-poz="<?= $p ?>" data-field="nominalas" data-sekcija="3.5" placeholder="-" data-testid="input-nominalas-35-<?= $p ?>">
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </table>
                <div class="saug-save-row no-print">
                    <button type="button" class="btn btn-success btn-sm" onclick="issaugotiSaugiklius('3.5')" data-testid="button-save-35">Išsaugoti 3.5</button>
                    <span class="saug-save-status" id="save-status-35"></span>
                </div>
            </td>
        </tr>

        <?php if ($trafo_kiekis >= 2): ?>
        <?php
        $saug_map_36 = [];
        foreach ($mt_saugikliai_36 as $s) {
            $saug_map_36[(int)$s['pozicijos_numeris']] = $s;
        }
        $poz_36 = array_merge(range(202, 206), range(401, 404));
        ?>
        <tr>
            <td class="nr-col" style="vertical-align: top;">3.6</td>
            <td class="desc-col" style="vertical-align: top;">Š2-0,4 (ir Š4-0,4 pagal schemą) sekcijos komplektuojamų saugiklių-lydžiųjų įdėklų gabaritas, nominalas:</td>
            <td class="val-col" style="padding: 0; position: relative;">
                <table class="saugikliu-sub" id="saugikliai-36-table">
                    <tr class="sub-header">
                        <?php foreach ($poz_36 as $p): ?>
                        <td><?= $p ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($poz_36 as $p): ?>
                        <td>
                            <input type="text" class="saug-input" name="gabaritas_36_<?= $p ?>" value="<?= htmlspecialchars($saug_map_36[$p]['gabaritas'] ?? '') ?>" data-poz="<?= $p ?>" data-field="gabaritas" data-sekcija="3.6" placeholder="-" data-testid="input-gabaritas-36-<?= $p ?>">
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($poz_36 as $p): ?>
                        <td>
                            <input type="text" class="saug-input" name="nominalas_36_<?= $p ?>" value="<?= htmlspecialchars($saug_map_36[$p]['nominalas'] ?? '') ?>" data-poz="<?= $p ?>" data-field="nominalas" data-sekcija="3.6" placeholder="-" data-testid="input-nominalas-36-<?= $p ?>">
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </table>
                <div class="saug-save-row no-print">
                    <button type="button" class="btn btn-success btn-sm" onclick="issaugotiSaugiklius('3.6')" data-testid="button-save-36">Išsaugoti 3.6</button>
                    <span class="saug-save-status" id="save-status-36"></span>
                </div>
            </td>
        </tr>
        <?php endif; ?>

        <?php
        $orig_39 = formatuotiKomponenta($komp_39, false);
        $text_39 = gautiTeksta('3_9', $orig_39, $korekcijos_data);
        $klase_39 = turiKorekcija('3_9', $korekcijos_data) ? 'koreguota' : (empty($komp_39['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.9</td>
            <td class="desc-col">0,4 kV sekcinis komutacinis aparatas (gamintojas, tipas, vardinė srovė, gr. Nr.)</td>
            <td class="val-col <?= $klase_39 ?>">
                <?= htmlspecialchars($text_39 ?: 'Nėra, -') ?>
                <button type="button" class="edit-btn no-print" data-field="3_9" data-label="3.9 - Sekcinis aparatas" data-text="<?= htmlspecialchars($text_39, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_310 = formatuotiKomponenta($komp_310);
        $text_310 = gautiTeksta('3_10', $orig_310, $korekcijos_data);
        $klase_310 = turiKorekcija('3_10', $korekcijos_data) ? 'koreguota' : (empty($komp_310['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.10</td>
            <td class="desc-col">Sekcinio saugiklio įdėklas (gamintojas, tipas)</td>
            <td class="val-col <?= $klase_310 ?>">
                <?= htmlspecialchars($text_310 ?: 'Nėra') ?>
                <button type="button" class="edit-btn no-print" data-field="3_10" data-label="3.10 - Sekcinio saugiklio įdėklas" data-text="<?= htmlspecialchars($text_310, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_311 = formatuotiKomponenta($komp_311);
        $text_311 = gautiTeksta('3_11', $orig_311, $korekcijos_data);
        $klase_311 = turiKorekcija('3_11', $korekcijos_data) ? 'koreguota' : (empty($komp_311['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.11</td>
            <td class="desc-col">Komercinė apskaita</td>
            <td class="val-col <?= $klase_311 ?>">
                <?= htmlspecialchars($text_311 ?: 'Nėra') ?>
                <button type="button" class="edit-btn no-print" data-field="3_11" data-label="3.11 - Komercinė apskaita" data-text="<?= htmlspecialchars($text_311, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_312 = formatuotiKomponenta($komp_312);
        $text_312 = gautiTeksta('3_12', $orig_312, $korekcijos_data);
        $klase_312 = turiKorekcija('3_12', $korekcijos_data) ? 'koreguota' : (empty($komp_312['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.12</td>
            <td class="desc-col">Kontrolinė apskaita</td>
            <td class="val-col <?= $klase_312 ?>">
                <?= htmlspecialchars($text_312 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="3_12" data-label="3.12 - Kontrolinė apskaita" data-text="<?= htmlspecialchars($text_312, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>
    </table>

    <table class="paso-main-table" style="margin-top: -1px;">
        <colgroup>
            <col style="width: 40px;">
            <col style="width: 52%;">
            <col>
        </colgroup>
        <tr><td colspan="3" class="section-header">4. Transformatorinės dokumentacija</td></tr>
        <tr>
            <td class="nr-col">4.1</td>
            <td colspan="2">MT vienlinijinė elektrinė schema su pavaizduotais visais pase nurodytais elementais. Teikiama kartu su šiuo pasu</td>
        </tr>
        <tr>
            <td class="nr-col">4.2</td>
            <td>Gaminio pasas</td>
            <td><?= htmlspecialchars($gaminio_pasas) ?></td>
        </tr>
    </table>

    <div class="paso-info-section">
        <p><?= htmlspecialchars($gaminio_pavadinimas) ?> (gaminio serijos Nr. <?= htmlspecialchars($serijos_nr) ?> ) sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271-202 standartą bandymų protokolo Nr. <?= htmlspecialchars($protokolo_nr ?: '437A') ?>.</p>
        <p>Komplektuojamajai skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271 standartą. Komplektuojamajam SI-04R skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal LST EN 61439-1 ir LST EN 61439-2 standartus. Bandymų protokolo Nr <?= htmlspecialchars($protokolo_nr ?: '437A') ?>.</p>
        <p><?= htmlspecialchars($gaminio_pavadinimas) ?> ir visiems komplektuojamiems įrenginiams garantija teikiama pagal gaminio serijos numerį. Gamintojas (<?= h($imone['pavadinimas']) ?>) įsipareigoja vykdyti transformatorinės <?= htmlspecialchars($gaminio_pavadinimas) ?> garantinį aptarnavimą 24 mėn.</p>
    </div>

    <div class="paso-signature-zone">
        <div class="paso-sig-left">
            <div class="sig-title"><?= htmlspecialchars($pareigos) ?></div>
            <div class="sig-name"><?= htmlspecialchars($inzinierius) ?></div>
            <div class="sig-subtitle">Pareigos, Vardas, Pavardė</div>
        </div>
        <div class="paso-sig-center">
            <div class="sig-date-value"><?= htmlspecialchars($data) ?></div>
            <div class="sig-date-label">Data</div>
        </div>
        <div class="paso-sig-right">
            <img src="/img/parasas_elga.jpg" alt="UAB ELGA parašas">
            <div class="sig-label">(parašas)/antspaudas</div>
        </div>
    </div>

</div>

<div class="edit-modal-overlay" id="editModalOverlay">
    <div class="edit-modal">
        <h4 id="editModalLabel">Redaguoti tekstą</h4>
        <textarea id="editModalTextarea"></textarea>
        <input type="hidden" id="editModalField">
        <div class="modal-buttons">
            <button class="btn btn-secondary btn-sm" onclick="closeEditModal()">Atšaukti</button>
            <button class="btn btn-success btn-sm" onclick="saveEditModal()">Išsaugoti</button>
        </div>
    </div>
</div>

<script>
function openEditModal(btn) {
    var field = btn.getAttribute('data-field');
    var label = btn.getAttribute('data-label');
    var text = btn.getAttribute('data-text');
    document.getElementById('editModalLabel').textContent = 'Redaguoti: ' + label;
    document.getElementById('editModalTextarea').value = text;
    document.getElementById('editModalField').value = field;
    document.getElementById('editModalOverlay').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModalOverlay').classList.remove('active');
}

function saveEditModal() {
    var field = document.getElementById('editModalField').value;
    var tekstas = document.getElementById('editModalTextarea').value;
    var formData = new FormData();
    formData.append('gaminio_id', '<?= htmlspecialchars($gaminio_id) ?>');
    formData.append('field_key', field);
    formData.append('lang', '<?= $lang ?>');
    formData.append('tekstas', tekstas);
    fetch('/MT/issaugoti_mt_pasa_teksta.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeEditModal();
            location.reload();
        } else {
            alert('Klaida: ' + data.message);
        }
    })
    .catch(function(e) {
        alert('Klaida saugant: ' + e.message);
    });
}

document.getElementById('editModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function issaugotiSaugiklius(sekcija) {
    var sekcijaKey = sekcija.replace('.', '');
    var inputs = document.querySelectorAll('.saug-input[data-sekcija="' + sekcija + '"]');
    var pozicijosMap = {};

    inputs.forEach(function(input) {
        var poz = input.getAttribute('data-poz');
        var field = input.getAttribute('data-field');
        if (!pozicijosMap[poz]) {
            pozicijosMap[poz] = { pozicijos_numeris: parseInt(poz), gabaritas: '', nominalas: '' };
        }
        pozicijosMap[poz][field] = input.value.trim();
    });

    var pozicijos = Object.values(pozicijosMap);

    var formData = new FormData();
    formData.append('gaminio_id', '<?= htmlspecialchars($gaminio_id) ?>');
    formData.append('sekcija', sekcija);
    formData.append('pozicijos', JSON.stringify(pozicijos));

    var statusEl = document.getElementById('save-status-' + sekcijaKey);
    statusEl.textContent = 'Saugoma...';
    statusEl.style.color = '#6c757d';

    fetch('/MT/issaugoti_mt_paso_saugiklius.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            statusEl.textContent = 'Išsaugota!';
            statusEl.style.color = '#198754';
            setTimeout(function() { statusEl.textContent = ''; }, 3000);
        } else {
            statusEl.textContent = 'Klaida: ' + data.message;
            statusEl.style.color = '#dc3545';
        }
    })
    .catch(function(e) {
        statusEl.textContent = 'Klaida: ' + e.message;
        statusEl.style.color = '#dc3545';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
