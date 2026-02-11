<?php
/**
 * MT gaminio paso generavimo puslapis - komponentų susiejimas, teksto korekcijos, spausdinimas
 *
 * Šis puslapis generuoja MT gaminio pasą, kuriame:
 *   - Komponentai susiejami su paso sekcijomis (1.1-3.11)
 *   - Rodomi redaguojami teksto laukai su korekcijų palaikymu
 *   - ESO atitikties tipo pasirinkimas ir protokolo numerio išsaugojimas
 *   - Spausdinimo palaikymas su atskiru spausdinimo stiliumi
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../klases/MTPasasKomponentai.php';

requireLogin();

$conn = Database::getConnection();
$gaminys_obj = new Gaminys();

// Puslapio parametrų gavimas iš GET/POST užklausų
$gaminio_id = $_GET['gaminio_id'] ?? $_POST['gaminio_id'] ?? null;
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas = $_GET['uzsakovas'] ?? $_POST['uzsakovas'] ?? '';
$gaminio_pavadinimas = $_GET['gaminio_pavadinimas'] ?? '';
$uzsakymo_id = $_GET['uzsakymo_id'] ?? '';
$lang = $_GET['lang'] ?? 'lt';

if (!$gaminio_id) {
    die('Trūksta gaminio ID');
}

if (empty($gaminio_pavadinimas)) {
    $gaminio_pavadinimas = $gaminys_obj->gautiPavadinimaPagalGaminioId($gaminio_id);
}

$gaminio_info = $gaminys_obj->gautiPagalId($gaminio_id);
$protokolo_nr = $gaminio_info['protokolo_nr'] ?? '';
$atitikmuo_kodas = $gaminio_info['atitikmuo_kodas'] ?? '15.6.2';

// === ESO atitikties tipo pasirinkimo išsaugojimas ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issaugoti_atitikmuo'])) {
    $naujas_kodas = $_POST['atitikmuo_kodas'] ?? '15.6.2';
    $stmt = $conn->prepare("UPDATE gaminiai SET atitikmuo_kodas = ? WHERE id = ?");
    $stmt->execute([$naujas_kodas, $gaminio_id]);
    $atitikmuo_kodas = $naujas_kodas;

    $params = http_build_query([
        'gaminio_id' => $gaminio_id,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'lang' => $lang,
        'issaugota' => 'taip'
    ]);
    header("Location: /MT/mt_pasas.php?$params");
    exit;
}

// === Protokolo numerio išsaugojimas ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issaugoti_protokola'])) {
    $naujas_nr = trim($_POST['protokolo_nr'] ?? '');
    if ($naujas_nr !== '') {
        $stmt = $conn->prepare("UPDATE gaminiai SET protokolo_nr = ? WHERE id = ?");
        $stmt->execute([$naujas_nr, $gaminio_id]);
        $protokolo_nr = $naujas_nr;
    }
    $params = http_build_query([
        'gaminio_id' => $gaminio_id,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'lang' => $lang,
        'issaugota' => 'taip'
    ]);
    header("Location: /MT/mt_pasas.php?$params");
    exit;
}

// === Komponentų susiejimas su paso sekcijomis ===
// MTPasasKomponentai klasė grąžina komponentus pagal paso punktų numerius
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

// Transformatorių skaičiaus ir galingumo ištraukimas iš gaminio pavadinimo
preg_match('/(\d+)x(\d+)/', $gaminio_pavadinimas, $match_kva);
$transformatoriai_kva = $match_kva[0] ?? '';

preg_match('/(\d+x\d+)\((\d+)\)/', $gaminio_pavadinimas, $match_full);
$transformatoriu_aprasas = $match_full[1] ?? $transformatoriai_kva;
$galingumas_kva = $match_full[2] ?? ($match_kva[2] ?? '');

// Saugiklių idėklų duomenų gavimas 3.5 sekcijai
$stmt = $conn->prepare("SELECT * FROM mt_saugikliu_ideklai WHERE gaminio_id = ? AND sekcija = '3.5' ORDER BY pozicijos_numeris ASC");
$stmt->execute([$gaminio_id]);
$mt_saugikliai = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Teksto korekcijų krovimas iš duomenų bazės ===
$korekcijos_data = [];
$stmt = $conn->prepare("SELECT field_key, tekstas FROM mt_paso_teksto_korekcijos WHERE gaminio_id = ? AND lang = ?");
$stmt->execute([$gaminio_id, $lang]);
$kor_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($kor_rows as $kr) {
    $korekcijos_data[$kr['field_key']] = $kr['tekstas'];
}

// Pagalbinė funkcija: grąžina koreguotą tekstą arba numatytąjį
function gautiTeksta($key, $default, &$korekcijos_data) {
    return $korekcijos_data[$key] ?? $default;
}

// Pagalbinė funkcija: tikrina ar yra teksto korekcija
function turiKorekcija($key, &$korekcijos_data) {
    return isset($korekcijos_data[$key]);
}

// Pagalbinė funkcija: formatuoja komponento tekstą (kodas, gamintojas, kiekis)
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

// ESO atitikties tipų aprašymai pagal kodus
$tipu_aprasai = [
    '15.6.1' => '10/0,4 kV įtampos mažo gabarito modulinės tranzitinės transformatorinės su vienu iki 160 kVA galios transformatoriumi techninius reikalavimus',
    '15.6.2' => '10/0,4 kV įtampos mažo gabarito modulinės transformatorinės su vienu iki 630 kVA galios transformatoriumi techninius reikalavimus',
    '15.6.3' => '10/0,4 kV įtampos mažo gabarito modulinės transformatorinės su dviem iki 630 kVA galios transformatoriais techninius reikalavimus',
    '15.2.5' => '10/0,4 kV modulinė transformatorinė su vienu iki 1000 kVA galios transformatoriumi (neigilinta) techninius reikalavimus',
    '15.2.9' => '10/0,4 kV modulinė transformatorinė su dviem 800 - 1600 kVA galios transformatoriais (neigilinta) techninius reikalavimus',
    '15.2.11' => '10/0,4 kV modulinė galinė transformatorinė su vienu iki 160 kVA galios transformatoriumi (neigilinta) techninius reikalavimus',
];

$tipas_aprasas = $tipu_aprasai[$atitikmuo_kodas] ?? 'Nenurodytas';

// Paso metaduomenys: serijos numeris, data, inžinierius
$serijos_nr = $uzsakymo_numeris;
$data = date("Y-m-d");
$gaminio_pasas = "MT/" . $uzsakymo_numeris;
$inzinierius = ($_SESSION['vardas'] ?? '') . ' ' . ($_SESSION['pavarde'] ?? '');

$nuoroda_atgal = "/uzsakymai.php?view=" . urlencode($uzsakymo_id);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.paso-container {
    max-width: 900px;
    margin: 0 auto;
    background: #fff;
    padding: 30px;
    border: 1px solid var(--border);
    border-radius: 8px;
}
.paso-header {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #333;
}
.paso-header .company-name {
    font-weight: 700;
    font-size: 16px;
    margin-bottom: 2px;
}
.paso-header .company-info {
    font-size: 12px;
    color: #555;
}
.paso-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
}
.paso-title .left {
    font-size: 16px;
    font-weight: 700;
}
.paso-title .right {
    font-size: 16px;
    font-weight: 700;
    color: #2563eb;
}
.paso-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 15px;
    font-size: 13px;
}
.paso-meta span {
    display: inline-flex;
    gap: 6px;
}
.paso-meta strong {
    color: #333;
}
.paso-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-bottom: 20px;
}
.paso-table td, .paso-table th {
    border: 1px solid #999;
    padding: 6px 10px;
    vertical-align: middle;
}
.paso-table .section-header {
    background: #d4edda;
    font-weight: 700;
    font-size: 14px;
}
.paso-table .nr-col {
    width: 50px;
    text-align: center;
    font-weight: 600;
}
.paso-table .desc-col {
    width: 55%;
}
.paso-table .val-col {
    width: auto;
}
.paso-table .highlight {
    background: #fff3cd;
}
.paso-table .koreguota {
    background: #d1ecf1;
}
.edit-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    padding: 2px 6px;
    border-radius: 4px;
    color: #6c757d;
    float: right;
}
.edit-btn:hover {
    background: #e9ecef;
    color: #333;
}
.paso-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
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
    font-size: 13px;
    background: #fff;
}
.paso-protokolas {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.paso-protokolas input {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
    width: 200px;
}
.paso-paraso-zona {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
.paso-paraso-zona .info-line {
    font-size: 13px;
    margin-bottom: 8px;
    line-height: 1.6;
}
.paso-paraso-zona .parasas-eilute {
    display: flex;
    gap: 40px;
    align-items: flex-end;
    margin-top: 20px;
    padding-top: 10px;
}
.paso-paraso-zona .parasas-eilute .parasas-label {
    font-size: 12px;
    color: #666;
}
.paso-paraso-zona .parasas-eilute .parasas-linija {
    border-bottom: 1px solid #333;
    min-width: 200px;
    text-align: center;
    font-size: 13px;
    padding-bottom: 2px;
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
.saugikliu-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin: 0;
}
.saugikliu-table td, .saugikliu-table th {
    border: 1px solid #999;
    padding: 3px 5px;
    text-align: center;
    vertical-align: middle;
}
.saugikliu-table .header-row {
    background: #e9ecef;
    font-weight: 600;
}

<?php if (isset($_GET['issaugota'])): ?>
.paso-saved-msg {
    display: block;
}
<?php endif; ?>
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">MT Gaminio Pasas</h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= htmlspecialchars($nuoroda_atgal) ?>" class="btn btn-secondary btn-sm">Grįžti į užsakymą</a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">Spausdinti</button>
    </div>
</div>

<?php if (isset($_GET['issaugota'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 15px;">
    Duomenys išsaugoti sėkmingai!
    <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="this.parentElement.style.display='none'"></button>
</div>
<?php endif; ?>

<div class="paso-container" id="paso-print-area">
    <div class="paso-header">
        <div class="company-name">UAB „ELGA"</div>
        <div class="company-info">Pramonės g. 12, LT-78150 Šiauliai, Lietuva</div>
        <div class="company-info">Tel. +370 41 594710; Faks. +370 41 594725</div>
        <div class="company-info">El. paštas: info@elga.lt; Internetas: www.elga.lt</div>
    </div>

    <div class="paso-title">
        <div class="left">Modulinė transformatorinė <?= htmlspecialchars($gaminio_pavadinimas) ?></div>
        <div class="right">GAMINIO PASAS</div>
    </div>
    <div class="paso-title" style="margin-top: 0;">
        <div></div>
        <div class="right" style="font-size: 14px;"><?= htmlspecialchars($gaminio_pasas) ?></div>
    </div>

    <div class="paso-meta">
        <span><strong>Tipas:</strong> <?= htmlspecialchars($gaminio_pavadinimas) ?></span>
        <span><strong>Gaminio ser. Nr.</strong> <?= htmlspecialchars($serijos_nr) ?></span>
        <span><strong>Pagaminimo data</strong> <?= htmlspecialchars($data) ?></span>
    </div>

    <div class="paso-toolbar no-print">
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

    <div class="print-only" style="display: none; font-size: 12px; margin-bottom: 10px;">
        <strong>Gaminys atitinka AB „Energijos skirstymo operatorius":</strong> <?= htmlspecialchars($atitikmuo_kodas) ?> - <?= htmlspecialchars($tipas_aprasas) ?>
    </div>

    <table class="paso-table">
        <colgroup>
            <col style="width: 50px;">
            <col style="width: 55%;">
            <col>
        </colgroup>

        <tr>
            <td colspan="3" class="section-header">1. 10 kV įtampos skyrius:</td>
        </tr>

        <?php
        $orig_11 = '';
        if (!empty($komp_11)) {
            $parts = [];
            foreach ($komp_11 as $i => $e) {
                $parts[] = 'Nr. ' . ($i+1) . ': ' . $e['kodas'] . ', ' . $e['gamintojas'];
            }
            $orig_11 = implode('; ', $parts);
        }
        $text_11 = gautiTeksta('1_1', $orig_11, $korekcijos_data);
        $klase_11 = turiKorekcija('1_1', $korekcijos_data) ? 'koreguota' : (empty($komp_11) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.1</td>
            <td class="desc-col">10 kV kabelių movos</td>
            <td class="val-col <?= $klase_11 ?>">
                <?= htmlspecialchars($text_11 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_1" data-label="1.1 - 10 kV kabelių movos" data-text="<?= htmlspecialchars($text_11, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_12 = formatuotiKomponenta($komp_12);
        $text_12 = gautiTeksta('1_2', $orig_12, $korekcijos_data);
        $klase_12 = turiKorekcija('1_2', $korekcijos_data) ? 'koreguota' : (empty($komp_12['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.2</td>
            <td class="desc-col">10 kV viršįtampių ribotuvai (gamintojas, tipas)</td>
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
            <td class="desc-col">10 kV skirstykla (gamintojas, modelis, narvelių konfigūracija)</td>
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
            <td class="desc-col">Galios transformatoriaus narvelio lydymieji įdėklai (tipas, vardinė srovė, gamintojas)</td>
            <td class="val-col <?= $klase_14 ?>">
                <?= htmlspecialchars($text_14 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_4" data-label="1.4 - Lydymieji įdėklai" data-text="<?= htmlspecialchars($text_14, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <tr>
            <td class="nr-col">1.5</td>
            <td class="desc-col">10 kV skirstyklos linijiniai narveliai gamintojo variklinėmis pavaromis su valdymo iš TSPĮ galimybe</td>
            <td class="val-col">Taip</td>
        </tr>

        <?php
        $orig_15 = formatuotiKomponenta($komp_15);
        $text_15 = gautiTeksta('1_6', $orig_15, $korekcijos_data);
        $klase_15 = turiKorekcija('1_6', $korekcijos_data) ? 'koreguota' : (empty($komp_15['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.6</td>
            <td class="desc-col">Trumpo jungimo indikatorius</td>
            <td class="val-col <?= $klase_15 ?>">
                <?= htmlspecialchars($text_15 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_6" data-label="1.6 - Trumpo jungimo indikatorius" data-text="<?= htmlspecialchars($text_15, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_16 = formatuotiKomponenta($komp_16);
        $text_16 = gautiTeksta('1_7', $orig_16, $korekcijos_data);
        $klase_16 = turiKorekcija('1_7', $korekcijos_data) ? 'koreguota' : (empty($komp_16['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">1.7</td>
            <td class="desc-col">Įtampos indikatorius</td>
            <td class="val-col <?= $klase_16 ?>">
                <?= htmlspecialchars($text_16 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="1_7" data-label="1.7 - Įtampos indikatorius" data-text="<?= htmlspecialchars($text_16, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
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
            <td class="val-col <?= $klase_18 ?>">
                <?= htmlspecialchars($kabelio_tekstas) ?>
            </td>
        </tr>

        <tr>
            <td colspan="3" class="section-header">2. Galios transformatoriaus skyrius:</td>
        </tr>

        <?php
        $orig_21 = formatuotiKomponenta($komp_21, false);
        $text_21 = gautiTeksta('2_1', $orig_21, $korekcijos_data);
        $klase_21 = turiKorekcija('2_1', $korekcijos_data) ? 'koreguota' : (empty($komp_21['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">2.1</td>
            <td class="desc-col">0,4 kV jungtys (galios transformatorius–0,4 kV skirstykla)</td>
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
            <td class="desc-col">10 kV jungtys (galios transformatorius–10 kV skirst. įrenginys)</td>
            <td class="val-col <?= $klase_22 ?>">
                <?= htmlspecialchars($text_22 ?: 'Duomenys nesuvesti') ?>
                <button type="button" class="edit-btn no-print" data-field="2_2" data-label="2.2 - 10 kV jungtys" data-text="<?= htmlspecialchars($text_22, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <tr>
            <td class="nr-col">2.3</td>
            <td class="desc-col">Transformatorių skaičius ir maksimali transformatorių galia kVA</td>
            <td class="val-col <?= empty($transformatoriai_kva) ? 'highlight' : '' ?>">
                <?= htmlspecialchars($transformatoriai_kva ?: 'Nenurodyta') ?> <?= !empty($galingumas_kva) ? '(' . htmlspecialchars($galingumas_kva) . ' kVA)' : '' ?>
            </td>
        </tr>

        <tr>
            <td colspan="3" class="section-header">3. 0,4 kV įtampos skyrius:</td>
        </tr>

        <?php
        $orig_31 = formatuotiKomponenta($komp_31);
        $text_31 = gautiTeksta('3_1', $orig_31, $korekcijos_data);
        $klase_31 = turiKorekcija('3_1', $korekcijos_data) ? 'koreguota' : (empty($komp_31['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.1</td>
            <td class="desc-col">Įvadinis saugiklių-kirtiklių blokas TKS (tipas, vnt., gamintojas)</td>
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

        <?php if (!empty($mt_saugikliai)): ?>
        <tr>
            <td class="nr-col">3.5</td>
            <td class="desc-col">Š1-0,4 sekcijos komplektuojamų saugiklių lydžiųjų įdėklų gabaritas, grupės numeris, nominalas:</td>
            <td class="val-col" style="padding: 0;">
                <table class="saugikliu-table">
                    <tr class="header-row">
                        <?php foreach ($mt_saugikliai as $s): ?>
                        <td><?= htmlspecialchars($s['pozicijos_numeris'] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($mt_saugikliai as $s): ?>
                        <td><?= htmlspecialchars($s['gabaritas'] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($mt_saugikliai as $s): ?>
                        <td><?= htmlspecialchars($s['nominalas'] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                </table>
            </td>
        </tr>
        <?php else: ?>
        <tr>
            <td class="nr-col">3.5</td>
            <td class="desc-col">Š1-0,4 sekcijos komplektuojamų saugiklių lydžiųjų įdėklų gabaritas, grupės numeris, nominalas:</td>
            <td class="val-col highlight">Duomenys nesuvesti</td>
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
                <?= htmlspecialchars($text_39 ?: 'Nėra') ?>
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
            <td class="desc-col">Komercinė apskaita</td>
            <td class="val-col <?= $klase_310 ?>">
                <?= htmlspecialchars($text_310 ?: 'Nėra') ?>
                <button type="button" class="edit-btn no-print" data-field="3_10" data-label="3.10 - Komercinė apskaita" data-text="<?= htmlspecialchars($text_310, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>

        <?php
        $orig_311 = formatuotiKomponenta($komp_311);
        $text_311 = gautiTeksta('3_11', $orig_311, $korekcijos_data);
        $klase_311 = turiKorekcija('3_11', $korekcijos_data) ? 'koreguota' : (empty($komp_311['gamintojo_kodas']) ? 'highlight' : '');
        ?>
        <tr>
            <td class="nr-col">3.11</td>
            <td class="desc-col">Kontrolinė apskaita</td>
            <td class="val-col <?= $klase_311 ?>">
                <?= htmlspecialchars($text_311 ?: 'Nėra') ?>
                <button type="button" class="edit-btn no-print" data-field="3_11" data-label="3.11 - Kontrolinė apskaita" data-text="<?= htmlspecialchars($text_311, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">Red.</button>
            </td>
        </tr>
    </table>

    <table class="paso-table">
        <tr>
            <td colspan="3" class="section-header">4. Transformatorinės dokumentacija</td>
        </tr>
        <tr>
            <td class="nr-col">4.1</td>
            <td colspan="2">MTT vienlinijinė elektrinė schema su pavaizduotais visais pase nurodytais elementais. Teikiama kartu su šiuo pasu.</td>
        </tr>
        <tr>
            <td class="nr-col">4.2</td>
            <td colspan="2">Gaminio pasas <?= htmlspecialchars($gaminio_pasas) ?></td>
        </tr>
    </table>

    <div class="paso-paraso-zona">
        <div class="info-line">
            <strong><?= htmlspecialchars($gaminio_pavadinimas) ?></strong> (gaminio serijos Nr. <?= htmlspecialchars($serijos_nr) ?>) sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271-202 standartą,
            bandymų protokolo Nr. <strong><?= htmlspecialchars($protokolo_nr ?: '___') ?></strong>
        </div>
        <div class="info-line">
            10 kV komplektuojamai (1.3) skirstyklai sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271 standartą.
        </div>
        <div class="info-line">
            Komplektuojamajam ŠI-0,4R skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal LST EN 61439-1 ir LST EN 61439-2 standartus.
            Bandymų protokolo Nr. <strong><?= htmlspecialchars($protokolo_nr ?: '___') ?></strong>
        </div>
        <div class="info-line" style="margin-top: 10px;">
            <strong><?= htmlspecialchars($gaminio_pavadinimas) ?></strong> ir visiems komplektuojamiems įrenginiams garantija teikiama pagal gaminio serijos numerį.
        </div>
        <div class="info-line">
            Gamintojas (UAB „ELGA") įsipareigoja vykdyti transformatorinės <strong><?= htmlspecialchars($gaminio_pavadinimas) ?></strong> garantinį aptarnavimą 24 mėn.
        </div>
        <div class="info-line" style="font-size: 11px; margin-top: 5px;">
            Visos garantinės sąlygos, įrengimo, eksploatavimo, transportavimo ir komplektuojamų įrenginių instrukcijos, pamato specifikacija, MT gabaritiniai brėžiniai suderinti su AB „Energijos skirstymo operatorius" dokumente MT-NI-17.1-LT.
        </div>

        <div class="parasas-eilute" style="margin-top: 30px;">
            <div>
                <div class="parasas-label">Kokybės inžinierius</div>
            </div>
            <div>
                <div class="parasas-linija"><?= htmlspecialchars($inzinierius) ?></div>
                <div class="parasas-label">Vardas, Pavardė</div>
            </div>
            <div>
                <div class="parasas-linija"><?= htmlspecialchars($data) ?></div>
                <div class="parasas-label">Data</div>
            </div>
            <div>
                <div class="parasas-linija" style="min-width: 150px;">&nbsp;</div>
                <div class="parasas-label">(parašas) / antspaudas</div>
            </div>
        </div>
    </div>
</div>

<!-- Teksto redagavimo modalinis langas -->
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

<!-- JavaScript funkcijos modaliniam teksto redagavimui ir AJAX išsaugojimui -->
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
</script>

<style>
@media print {
    .no-print, .paso-toolbar, .sidebar, .main-header, .d-flex.justify-content-between { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .paso-container { border: none; box-shadow: none; max-width: 100%; padding: 10mm; }
    .print-only { display: block !important; }
    .edit-btn { display: none !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
