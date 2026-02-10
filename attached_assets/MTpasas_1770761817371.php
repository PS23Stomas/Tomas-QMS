<?php
/**
 * MT Transformatoriaus paso generavimas
 * 
 * Šis failas generuoja MT (mažos transformatorinės) gaminio pasą.
 * Palaiko lietuvių ir anglų kalbas, leidžia redaguoti ir išsaugoti
 * paso duomenis, generuoti PDF dokumentą.
 * 
 * Pagrindinės funkcijos:
 * - Gaminio paso peržiūra ir redagavimas
 * - Linijų numerių ir sekcinio numerio išsaugojimas
 * - Tipo kodo išsaugojimas
 * - Komponentų duomenų rodymas iš duomenų bazės
 * - PDF generavimo paleidimas
 */

session_start();

/**
 * Nustatome PDF režimą pagal GET parametrą
 * Jei pdf=taip, puslapis generuojamas be formų elementų
 */
$pdf_rezimas = isset($_GET['pdf']) && $_GET['pdf'] === 'taip';

/**
 * Parašo paveikslėlio parinkimas pagal prisijungusį vartotoją
 * Tomas Atkočiūnas naudoja kitą parašą nei Tomas Viržintas
 */
$vartotojoPavarde = $_SESSION['pavarde'] ?? '';
if (stripos($vartotojoPavarde, 'Atkočiūnas') !== false || stripos($vartotojoPavarde, 'Atkociunas') !== false) {
    $parasas_failas = 'parasas_atkociunas.png';
} else {
    $parasas_failas = 'parasas1.jpg';
}

require_once '../klases/Database.php';
require_once '../klases/Gamys1.php';
require_once '../klases/MTPasasKomponentai.php';
require_once '../klases/MTPasoKorekcijos.php';
require_once '../klases/DBMigracija.php';

/**
 * Kalbos palaikymas
 * Galimos kalbos: lt (lietuvių), en (anglų)
 */
$lang = $_GET['lang'] ?? 'lt';
if (!in_array($lang, ['lt', 'en'])) {
    $lang = 'lt';
}
$translations = require_once 'mt_translations.php';
$t = $translations[$lang];



/**
 * Duomenų bazės prisijungimas
 * Sukuriame Database ir Gamys1 objektus darbui su DB
 */
$db = new Database();
$conn = $db->getConnection();

/**
 * Automatinė duomenų bazės migracija
 * Patikrina ir pataiso per mažus varchar laukus
 */
$migracija = new DBMigracija($conn);
$migracija->paleisti();

$gamynys = new Gamys1($conn);

/**
 * GET arba POST parametrų nuskaitymas
 * Šie parametrai perduodami iš gaminių sąrašo puslapio
 */
$gaminio_id = $_GET['gaminio_id'] ?? $_POST['gaminio_id'] ?? null;
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? $_POST['uzsakymo_numeris'] ?? 'Neivestas';
$uzsakovas = $_GET['uzsakovas'] ?? $_POST['uzsakovas'] ?? 'Nenurodytas';
$tipas_kodas = $_GET['tipas_kodas'] ?? $_POST['tipas_kodas'] ?? '15.6.2';

$debug_message = '';

/**
 * ========== POST UŽKLAUSŲ APDOROJIMAS ==========
 * Šie blokai apdoroja formos pateikimo duomenis prieš bet kokį HTML išvedimą.
 * Po sėkmingo išsaugojimo atliekamas peradresavimas (redirect).
 */

/**
 * 1. Linijų numerių išsaugojimas
 * Išsaugo 10kV linijų numerius į gaminio_kirtikliai lentelę.
 * Pirma bando atnaujinti (UPDATE), jei nėra įrašo - įterpia naują (INSERT).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issaugoti_linijas'])) {
    $liniju_masyvas = $_POST['liniju_numeriai'] ?? [];
    $linijos = array_values(array_filter(array_map('trim', $liniju_masyvas), fn($v) => $v !== ''));
    $liniju_string = implode('; ', $linijos);

    if (empty($liniju_string)) {
        $debug_message = "KLAIDA: Nėra linijų numerių";
    } else {
        // Pirmiausia pabandome UPDATE - paprasčiau ir patikimiau
        $stmt = $conn->prepare("UPDATE gaminio_kirtikliai SET linijos_10kv_nr = ? WHERE gaminio_id = ?");
        $stmt->execute([$liniju_string, $gaminio_id]);
        $updated_rows = $stmt->rowCount();

        if ($updated_rows > 0) {
            // UPDATE pavyko
            $stmt2 = $conn->prepare("SELECT id FROM gaminio_kirtikliai WHERE gaminio_id = ? LIMIT 1");
            $stmt2->execute([$gaminio_id]);
            $existing_id = $stmt2->fetchColumn();
            $debug_message = "UPDATE: Atnaujinta {$updated_rows} įrašas(-ai), ID={$existing_id}, linijos: {$liniju_string}";
        } else {
            // Nėra įrašo - darome INSERT
            try {
                // Auto-fix sequence prieš INSERT
                $conn->exec("SELECT setval('gaminio_kirtikliai_id_seq', (SELECT COALESCE(MAX(id), 1) FROM gaminio_kirtikliai))");
                
                $stmt = $conn->prepare("INSERT INTO gaminio_kirtikliai (gaminio_id, linijos_10kv_nr) VALUES (?, ?)");
                $stmt->execute([$gaminio_id, $liniju_string]);
                $new_id = $conn->lastInsertId();
                $debug_message = "INSERT: Naujas įrašas ID={$new_id}, gaminio_id={$gaminio_id}, linijos: {$liniju_string}";
            } catch (PDOException $e) {
                $debug_message = "KLAIDA INSERT: " . $e->getMessage();
            }
        }
    }

    header("Location: MTpasas.php?gaminio_id={$gaminio_id}&uzsakymo_numeris={$uzsakymo_numeris}&uzsakovas={$uzsakovas}&issaugota=taip&debug=" . urlencode($debug_message));
    exit;
}

/**
 * 2. Sekcinio numerio išsaugojimas
 * Išsaugo 0,4kV sekcijinį numerį į gaminio_kirtikliai lentelę.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issaugoti_sekcini'])) {
    $sekcinis_nr = trim($_POST['sekcinis_nr'] ?? '');

    $stmt = $conn->prepare("SELECT id FROM gaminio_kirtikliai WHERE gaminio_id = ? LIMIT 1");
    $stmt->execute([$gaminio_id]);
    $existing_id = $stmt->fetchColumn();

    if ($existing_id) {
        $stmt = $conn->prepare("UPDATE gaminio_kirtikliai SET sekcijinis_04kv_nr = ? WHERE id = ?");
        $stmt->execute([$sekcinis_nr, $existing_id]);
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO gaminio_kirtikliai (gaminio_id, sekcijinis_04kv_nr) VALUES (?, ?)");
            $stmt->execute([$gaminio_id, $sekcinis_nr]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate key') !== false) {
                $stmt = $conn->prepare("UPDATE gaminio_kirtikliai SET sekcijinis_04kv_nr = ? WHERE gaminio_id = ?");
                $stmt->execute([$sekcinis_nr, $gaminio_id]);
            } else {
                throw $e;
            }
        }
    }

    header("Location: MTpasas.php?gaminio_id={$gaminio_id}&uzsakymo_numeris={$uzsakymo_numeris}&uzsakovas={$uzsakovas}&issaugota=taip");
    exit;
}

/**
 * 3. Tipo kodo išsaugojimas
 * Atnaujina gaminio atitikmuo_kodas lauką pagal pasirinktą ESO tipą.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pdf_rezimas && $gaminio_id && isset($_POST['tipas_kodas'])) {
    $naujas_kodas = $_POST['tipas_kodas'];

    $stmt = $conn->prepare("SELECT gaminio_tipas_id FROM gaminiai WHERE id = ?");
    $stmt->execute([$gaminio_id]);
    $gaminio_tipas_id = $stmt->fetchColumn();

    if ($gaminio_id) {
        $stmt = $conn->prepare("UPDATE gaminiai SET atitikmuo_kodas = :kodas WHERE id = :id");
        $stmt->execute([
            ':kodas' => $naujas_kodas,
            ':id' => $gaminio_id
        ]);
    }
}

/**
 * ========== PABAIGA POST APDOROJIMO ==========
 */


/**
 * Gaminio informacijos gavimas iš duomenų bazės
 * Nuskaitome gaminio duomenis, protokolo numerį, pavadinimą
 */
$gaminio_info = $gamynys->gautiPagalId($gaminio_id);
if (empty($tipas_kodas) && isset($gaminio_info['tipas_kodas'])) {
    $tipas_kodas = $gaminio_info['tipas_kodas'];
}
$atitikmuo_kodas = $gaminio_info['atitikmuo_kodas'] ?? $tipas_kodas;
$gaminio_pavadinimas = $gamynys->gautiPavadinimaPagalGaminioId($gaminio_id); // naudoti tikrą pavadinimą

$protokolo_nr = $gaminio_info['protokolo_nr'] ?? 'Neivestas';

/**
 * Išvesties parametrų paruošimas
 * Šie parametrai rodomi paso antraštėje
 */
$serijos_nr = $uzsakymo_numeris;
$data = date("Y-m-d");
$gaminio_pasas = "MT/" . $uzsakymo_numeris;
$inzinierius = ($_SESSION['vardas'] ?? '') . ' ' . ($_SESSION['pavarde'] ?? '');

/**
 * ESO tipų aprašai
 * Šie kodai atitinka ESO (Energijos skirstymo operatoriaus) reikalavimus
 */
$tipu_aprasai = [
    '15.6.1' => $t['type_15_6_1'],
    '15.6.2' => $t['type_15_6_2'],
    '15.6.3' => $t['type_15_6_3'],
    '15.2.5' => $t['type_15_2_5'],
    '15.2.9' => $t['type_15_2_9'],
    '15.2.11' => $t['type_15_2_11'],
];
$tipas_aprasas = $tipu_aprasai[$tipas_kodas] ?? ($lang === 'en' ? 'Not specified' : 'Nenurodytas');

/**
 * Numatytieji komponentų duomenys
 * Šie duomenys naudojami kaip pradinės reikšmės, jei DB nėra įrašų
 */
$linija1 = 'rezervas';
$linija2 = 'RSTI-5854 95-120mm2(20kV kab)';
$virsitampiu_ribotuvai = 'RSTI-CC-68SA1210';
$virsitampiu_gamintojas = 'Raychem';
$skirstykla = 'Safering CCF';
$skirstykla_gamintojas = 'ABB';
$lydej_ideklai = 'VVT-D';
$srove = '32A';
$lydej_gamintojas = 'ETI';
$indikatoriai = 'MF-L (2vnt.), HR (2 vnt.)';
$ivedimo_medziaga = '1 kompl. 3x240 mm2 kabeliui';
$jungtys_04kv = 'Cu H07V–K–4x(2x 300mm²)';
$jungtys_10kv = 'Cu N2XSY 3x(1x35/16)';
$transformatoriai_kva = '1x630';
$tks_blokas = "SL3-3x3/910VO 1 vnt. Jean Muller";
$ivadinis_lydusis = "WT3 NH3 g/Tr 250kVA 3vnt. ETI";
$linijinis_blokas = "SL3H-3x3/3A/VO Jean Muller 1 vnt.";
$lydusieji_ideklai = "WT3 NH3 gL/gG ETI";
$si04r_ideklai = "2 3 4 5 6 7 8 NH3 100A";
$sekcinis_aparatas = "Nėra";
$sekcinio_ideklas = "NZ";
$komercine_apskaita = "";
$kontroline_apskaita = "400 /5 A/A 0,5“S“ (3 vnt.); + BG + skaitiklio tvirtinimas";

/**
 * Transformatorių galingumo ištraukimas iš gaminio pavadinimo
 * Pavyzdys: "MTT 2x630" -> "2x630"
 */
preg_match('/\d+x\d+/', $gaminio_pavadinimas, $match);
$transformatoriai_kva = $match[0] ?? 'Nenurodyta';


/**
 * Paso komponentų duomenų gavimas
 * MTPasasKomponentai klasė skaito komponentų duomenis iš DB
 */
$mt_pasas = new MTPasasKomponentai($conn, $gaminio_id);

/**
 * Paso teksto korekcijų gavimas
 * MTPasoKorekcijos klasė skaito išsaugotus teksto pakeitimus
 */
$korekcijos = new MTPasoKorekcijos($conn, $gaminio_id, $lang);

/**
 * 1.1 punktas - 12kV kabelių movos
 */
$komp_11 = $mt_pasas->punktas1_1();

/**
 * DEBUG: Patikrinti, ar duomenys teisingai nuskaityti iš DB
 * Šis blokas rodomas tik kai URL yra ?debug_db parametras
 */
$stmt_debug = $conn->prepare("SELECT linijos_10kv_nr FROM gaminio_kirtikliai WHERE gaminio_id = ?");
$stmt_debug->execute([$gaminio_id]);
$debug_linijos = $stmt_debug->fetchColumn();
if (isset($_GET['debug_db'])) {
    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
    echo "<strong>DEBUG:</strong> gaminio_id={$gaminio_id}<br>";
    echo "<strong>DB linijos_10kv_nr:</strong> " . ($debug_linijos ? htmlspecialchars($debug_linijos) : "NULL") . "<br>";
    echo "<strong>komp_11 count:</strong> " . (is_array($komp_11) ? count($komp_11) : "NOT ARRAY") . "<br>";
    if (is_array($komp_11)) {
        echo "<strong>komp_11 data:</strong> " . htmlspecialchars(json_encode($komp_11, JSON_UNESCAPED_UNICODE)) . "<br>";
    }
    echo "</div>";
}
/**
 * Paso sekcijų komponentų nuskaitymas iš DB
 * Kiekvienas punktas atitinka paso eilutę
 */
$komp_12 = $mt_pasas->punktas1_2();  // 1.2 - Viršįtampių ribotuvai
$komp_13 = $mt_pasas->punktas1_3();  // 1.3 - 12kV skirstykla
$komp_14 = $mt_pasas->punktas1_4();  // 1.4 - Lydymieji įdėklai
$komp_16 = $mt_pasas->punktas1_6();  // 1.6 - Trumpo jungimo indikatorius
$komp_18 = $mt_pasas->punktas1_8();  // 1.8 - Papildomi komponentai
$komp_15 = $mt_pasas->punktas1_7();  // 1.7 - Įtampos indikatorius
$komp_21 = $mt_pasas->punktas2_1();  // 2.1 - Galios transformatorius
$komp_22 = $mt_pasas->punktas2_2();  // 2.2 - Įvedimo medžiaga
$komp_31 = $mt_pasas->punktas3_1();  // 3.1 - TKS blokas
$komp_32 = $mt_pasas->punktas3_2();  // 3.2 - Įvadinis lydysis
$komp_33 = $mt_pasas->punktas3_3();  // 3.3 - Linijinis blokas
$komp_34 = $mt_pasas->punktas3_4();  // 3.4 - Lydymieji įdėklai 0,4kV
$komp_39 = $mt_pasas->punktas3_9();  // 3.9 - Komercinė apskaita
$komp_310 = $mt_pasas->punktas3_10(); // 3.10 - Kontrolinė apskaita
$komp_311 = $mt_pasas->punktas3_11(); // 3.11 - Sekcinis aparatas
$komp_312 = $mt_pasas->punktas3_12(); // 3.12 - Sekcinio įdėklas

/**
 * Automatinis visų komponentų patikrinimas
 * Jei komponentas neegzistuoja, priskiriama numatytoji reikšmė
 */
$visi_komponentai = [
    'komp_12', 'komp_13', 'komp_14', 'komp_15', 'komp_16', 'komp_18',
    'komp_21', 'komp_22', 'komp_31', 'komp_32', 'komp_33', 'komp_34',
    'komp_39', 'komp_310', 'komp_311', 'komp_312'
];

$neivesti_duomenys = [
    'gamintojo_kodas' => $t['no_data'],
    'gamintojas' => '',
    'kiekis' => '',
    'gnybtas' => ''
];

foreach ($visi_komponentai as $kintamasis) {
    if (!isset($$kintamasis) || !is_array($$kintamasis) || empty($$kintamasis)) {
        $$kintamasis = $neivesti_duomenys;
    }
}
if (!is_array($komp_39)) {
    $komp_39 = [];
}

/**
 * Kabelių movų masyvo patikrinimas
 * 1.1 punktas gali turėti kelias eilutes (kelios linijos)
 */
if (!is_array($komp_11)) {
    $komp_11 = [];
}

if (isset($_GET['issaugota']) && $_GET['issaugota'] === 'taip') {
    echo '<div style="padding: 10px; margin: 10px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">';
    echo '<strong>' . $t['data_saved'] . '</strong>';
    if (isset($_GET['debug'])) {
        echo '<br><small>Debug: ' . htmlspecialchars($_GET['debug']) . '</small>';
    }
    echo '</div>';
}
/**
 * Saugiklių įdėklų nuskaitymas (3.5 sekcija)
 */
$stmt = $conn->prepare("SELECT * FROM mt_saugikliu_ideklai WHERE gaminio_id = :gid AND sekcija = '3.5' ORDER BY pozicijos_numeris ASC");
$stmt->execute([':gid' => $gaminio_id]);
$mt_saugikliai = $stmt->fetchAll(PDO::FETCH_ASSOC);



/**
 * Transformatorių aprašymo ir galingumo ištraukimas
 * Pvz.: "MTT 2x400(630)" -> transformatoriu_aprasas="2x400", galingumas_kva="630"
 */
preg_match('/(\d+x\d+)\((\d+)\)/', $gaminio_pavadinimas, $match);
$transformatoriu_aprasas = $match[1] ?? 'Nenurodyta';  // 2x400
$galingumas_kva = $match[2] ?? 'Nenurodyta';           // 630

/**
 * Transformatorių kiekio ištraukimas iš pavadinimo
 * Pvz.: "2x250kVA" -> transformatoriu_kiekis = 2
 */
preg_match('/(\d+)x(\d+)/', $gaminio_pavadinimas, $match);
$transformatoriu_kiekis = isset($match[1]) ? intval($match[1]) : 1;




?>





<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <title><?= $t['product_passport'] ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <style>
  body {
    font-family: times, serif;
    font-size: 9pt;
    margin: 0;
    padding: 0;
    background-color: #fff;
    color: black;
    text-align: left;
  }

  .page {
    width: 100%;
    max-width: 1400px;
    min-height: 297mm;
    margin: 5mm auto;
    padding: 5mm;
    background: white;
    box-shadow: none;
    page-break-inside: avoid;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
    text-align: left;
    table-layout: fixed;
    margin: 0;
  }

  td, th {
    border: 1px solid black;
    padding: 2px 5px;
    text-align: left;
  }

  .pasas-lentele td {
    text-align: left;
    vertical-align: middle;
    padding: 2px 4px;
    border: 1px solid black;
  }

  .vienodi-stulpeliai td {
    width: 12.5% !important;
    text-align: center;
    vertical-align: middle;
  }

  .header {
    text-align: center;
    margin-bottom: 10px;
  }

  .title {
    font-size: 11pt;
    font-weight: bold;
    text-align: center;
    margin-top: 8px;
  }

  .section-title {
   background-color: #c8f5c8;
S
    font-weight: bold;
    font-size: 10pt;
    padding: 3px;
  }

  .skyrius {
    background-color: #dff0d8;
    font-weight: bold;
    font-size: 10pt;
    padding: 5px;
  }

  .signature-box {
    border-top: 1px solid black;
    width: 300px;
    margin-top: 30px;
    text-align: center;
    padding-top: 10px;
  }

  .no-border {
    border: none !important;
  }

  .paraso-eilute td {
    height: 80px;
    vertical-align: middle;
    text-align: left;
  }

  .input-data {
    font-style: normal;
    font-weight: normal;
  }

  
  /* A4 format styles for screen display */
  @media screen {
    body {
      background-color: #e0e0e0;
      padding: 20px;
    }
    
    .page {
      width: 210mm !important; /* A4 width */
      max-width: 210mm !important;
      min-height: 297mm !important; /* A4 height */
      margin: 20px auto !important;
      padding: 15mm 20mm !important;
      background: white;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08) !important;
      border: 1px solid #ddd !important;
      box-sizing: border-box;
    }
  }
  
  /* Print styles remain compact */
  @media print {
    body {
      background-color: white;
      padding: 0;
    }
    
    .page {
      width: 100%;
      max-width: none;
      margin: 0;
      padding: 10mm;
      box-shadow: none;
      border: none;
      page-break-after: always;
    }
  }
</style>




</head>

<body>

<div class="page">

<?php if (!isset($pdf_rezimas) || !$pdf_rezimas): ?>
  <!-- Language Toggle -->
  <div style="margin-top: 10px; margin-bottom: 10px;">
    <a href="?gaminio_id=<?= urlencode($gaminio_id) ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>&tipas_kodas=<?= urlencode($tipas_kodas) ?>&lang=<?= $lang === 'lt' ? 'en' : 'lt' ?>" 
       style="display: inline-block; padding: 8px 16px; background-color: #27ae60; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
      <?= $lang === 'lt' ? '🌐 EN' : '🌐 LT' ?>
    </a>
  </div>
  
  <form action="mt_generuoti_pdf.php" method="get" target="_blank" style="margin-top: 10px;">
    <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
    <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
    <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
    <input type="hidden" name="tipas_kodas" value="<?= htmlspecialchars($tipas_kodas) ?>">
    <input type="hidden" name="lang" value="<?= $lang ?>">
    <button type="submit"><?= $t['generate_pdf'] ?></button>
  </form>
  <form action="mt_generuoti_pdf.php" method="get" target="_blank" style="margin-top: 5px;">
    <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
    <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
    <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
    <input type="hidden" name="tipas_kodas" value="<?= htmlspecialchars($tipas_kodas) ?>">
    <input type="hidden" name="lang" value="<?= $lang === 'lt' ? 'en' : 'lt' ?>">
    <button type="submit" style="background-color: #3498db; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;"><?= $t['generate_pdf_en'] ?></button>
  </form>
<?php endif; ?>

<div class="container">
  <div class="header">
    <div><strong><?= $t['company'] ?></strong></div>
    <div><?= $t['address'] ?></div>
    <div><?= $t['phone'] ?></div>
    <div><?= $t['email_web'] ?> <a href="http://www.elga.lt">www.elga.lt</a></div>

    <?php if (empty($gaminio_pasas) || $gaminio_pasas === 'MT/Neivestas'): ?>
      <div class="highlight">
        <a><?= $t['product_passport'] ?></a> <?= htmlspecialchars($gaminio_pasas) ?>
      </div>
    <?php else: ?>
      <div>
        <a><?= $t['product_passport'] ?></a> <?= htmlspecialchars($gaminio_pasas) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!isset($_GET['pdf']) || $_GET['pdf'] !== 'taip'): ?>
  <!-- Protokolo numerio įvedimas -->
  <form action="issaugoti_protokolo_nr.php" method="post">
    <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
    <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
    <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
    <input type="hidden" name="tipas_kodas" value="<?= htmlspecialchars($tipas_kodas) ?>">
    <input type="hidden" name="serijos_nr" value="<?= htmlspecialchars($serijos_nr) ?>">

    <label for="protokolo_nr"><strong><?= $t['protocol_number'] ?></strong></label>
    <input type="text" name="protokolo_nr" id="protokolo_nr" value="<?= htmlspecialchars($protokolo_nr) ?>" required>
    <button type="submit"><?= $t['save'] ?></button>
</form>

<?php endif; ?>


<?php
  $klase_tipas = empty($gaminio_pavadinimas) ? 'highlight' : '';
  $klase_serija = empty($serijos_nr) ? 'highlight' : '';
  $klase_data = empty($data) ? 'highlight' : '';
?>
<div>
  <?= $t['type'] ?> <span class="<?= $klase_tipas ?>"><?= htmlspecialchars($gaminio_pavadinimas) ?></span>
  &nbsp;&nbsp;
  <?= $t['serial_no'] ?> <span class="<?= $klase_serija ?>"><?= htmlspecialchars($serijos_nr) ?></span>
  &nbsp;&nbsp;
  <?= $t['date'] ?> <span class="<?= $klase_data ?>"><?= htmlspecialchars($data) ?></span>
</div>
 <div style="display: block; max-width: 100%;">
    <div style="font-size: 10pt;">
        <?= $t['product_complies'] ?>
    </div>

    <?php if (!$pdf_rezimas): ?>
        <form method="POST">
            <input type="hidden" name="gaminio_id" value="<?= $gaminio_id ?>">
            <select name="tipas_kodas" onchange="this.form.submit()" 
                    style="width: 100%; font-size: 10pt; word-wrap: break-word; height: auto; min-height: 3.2em; padding: 5px;">
                <?php foreach ($tipu_aprasai as $kodas => $aprasymas): ?>
                    <option value="<?= $kodas ?>" <?= ($kodas === $atitikmuo_kodas) ? 'selected' : '' ?>>
                        <?= $kodas ?> - <?= $aprasymas ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

    <?php else: ?>
        <!-- ✅ PDF režime rodomas tik tekstas -->
        <div style="padding:2px; border:1px solid #ccc; font-size: 10pt;">
            <?= $atitikmuo_kodas ?> - <?= $tipu_aprasai[$atitikmuo_kodas] ?? 'Nepasirinkta' ?>
        </div>
    <?php endif; ?>
</div>



<?php
$gaminys_id = $_GET['gaminys_id'] ?? '';
$gaminio_numeris = $_GET['gaminio_numeris'] ?? '';
$gaminio_pavadinimas = $_GET['gaminio_pavadinimas'] ?? '';
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas = $_GET['uzsakovas'] ?? '';
?>



<!-- Toliau eina tavo lentelė -->
<table class="pasas-lentele">
  <colgroup>
    <col style="width: 5%;">   <!-- Eilutės numeris -->
    <col style="width: 55%;">  <!-- Aprašymas -->
    <col style="width: 40%;">  <!-- Reikšmės / duomenys -->
  </colgroup>
  <tr class="section-title">
    <td colspan="3"><strong><?= $t['section_1'] ?></strong></td>
  </tr>




<?php
// Nustatom klasę, jei nėra duomenų
$klase_11 = (empty($komp_11) || count($komp_11) === 0) ? 'highlight' : '';
?>

<tr>
  <td>1.1</td>
  <td>12 kV kabelių movos:</td>
  <td colspan="2" class="<?= $klase_11 ?>">
    <?php if (!empty($komp_11)): ?>
      <form method="POST" action="">
        <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
        <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
        <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
        <input type="hidden" name="issaugoti_linijas" value="1">

        <?php foreach ($komp_11 as $i => $eilute): ?>
          <div class="mb-1">
            Nr. <?= $i+1 ?>:
            <input type="text" name="liniju_numeriai[]" value="<?= htmlspecialchars($eilute['linija']) ?>" class="form-control form-control-sm" />
            <span class="input-data"><?= htmlspecialchars($eilute['kodas']) ?>,</span>
            <span class="input-data"><?= htmlspecialchars($eilute['gamintojas']) ?></span>
          </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-sm btn-success mt-2">💾 Išsaugoti linijų numerius</button>
      </form>
    <?php else: ?>
      Duomenys nesuvesti
    <?php endif; ?>
  </td>
</tr>



<?php
  $orig_12 = htmlspecialchars(($komp_12['gamintojo_kodas'] ?? '') . ', ' . ($komp_12['gamintojas'] ?? '') . ' (' . ($komp_12['kiekis'] ?? '') . ' vnt.)');
  $text_12 = $korekcijos->gautiTeksta('1_2', $orig_12);
  $klase_12 = $korekcijos->turiKorekcija('1_2') ? 'koreguota' : ((empty($komp_12['gamintojo_kodas']) || empty($komp_12['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>1.2</td>
  <td>12 kV viršįtampių ribotuvai (gamintojas, tipas)</td>
  <td colspan="2" class="<?= $klase_12 ?>">
    <?= htmlspecialchars($text_12) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="1_2" data-label="1.2 - Viršįtampių ribotuvai" data-text="<?= htmlspecialchars($text_12, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<?php
  $orig_13 = htmlspecialchars(($komp_13['gamintojo_kodas'] ?? '') . ', ' . ($komp_13['gamintojas'] ?? ''));
  $text_13 = $korekcijos->gautiTeksta('1_3', $orig_13);
  $klase_13 = $korekcijos->turiKorekcija('1_3') ? 'koreguota' : ((empty($komp_13['gamintojo_kodas']) || empty($komp_13['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>1.3</td>
  <td>12 kV skirstykla (gamintojas, modelis, narvelių konfigūracija)</td>
  <td colspan="2" class="<?= $klase_13 ?>">
    <?= htmlspecialchars($text_13) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="1_3" data-label="1.3 - Skirstykla" data-text="<?= htmlspecialchars($text_13, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<?php
  $orig_14 = htmlspecialchars(($komp_14['gamintojo_kodas'] ?? '') . ', ' . ($komp_14['gamintojas'] ?? '') . ' (' . ($komp_14['kiekis'] ?? '') . ' vnt.)');
  $text_14 = $korekcijos->gautiTeksta('1_4', $orig_14);
  $klase_14 = $korekcijos->turiKorekcija('1_4') ? 'koreguota' : ((empty($komp_14['gamintojo_kodas']) || empty($komp_14['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>1.4</td>
  <td>Galios transformatoriaus narvelio Ts komplektuojami lydymieji įdėklai (tipas, vardinė srovė, gamintojas)</td>
  <td colspan="2" class="<?= $klase_14 ?>">
    <?= htmlspecialchars($text_14) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="1_4" data-label="1.4 - Lydymieji įdėklai" data-text="<?= htmlspecialchars($text_14, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<tr>
  <td>1.5</td>
  <td>12 kV skirstyklos linijiniai narveliai gamintojo variklinėmis pavaromis su valdymo iš TSPI galimybe</td>
  <td colspan="2" class="">Taip</td>
</tr>

<?php
  $orig_16 = htmlspecialchars(($komp_16['gamintojo_kodas'] ?? '') . ', ' . ($komp_16['gamintojas'] ?? '') . ' (' . ($komp_16['kiekis'] ?? '') . ' vnt.)');
  $text_16 = $korekcijos->gautiTeksta('1_6', $orig_16);
  $klase_16 = $korekcijos->turiKorekcija('1_6') ? 'koreguota' : ((empty($komp_16['gamintojo_kodas']) || empty($komp_16['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>1.6</td>
  <td>Trumpo jungimo indikatorius</td>
  <td colspan="2" class="<?= $klase_16 ?>">
    <?= htmlspecialchars($text_16) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="1_6" data-label="1.6 - Trumpo jungimo indikatorius" data-text="<?= htmlspecialchars($text_16, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<?php
  $orig_17 = htmlspecialchars(($komp_15['gamintojo_kodas'] ?? '') . ', ' . ($komp_15['gamintojas'] ?? '') . ' (' . ($komp_15['kiekis'] ?? '') . ' vnt.)');
  $text_17 = $korekcijos->gautiTeksta('1_7', $orig_17);
  $klase_17 = $korekcijos->turiKorekcija('1_7') ? 'koreguota' : ((empty($komp_15['gamintojo_kodas']) || empty($komp_15['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>1.7</td>
  <td>Įtampos indikatorius</td>
  <td colspan="2" class="<?= $klase_17 ?>">
    <?= htmlspecialchars($text_17) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="1_7" data-label="1.7 - Įtampos indikatorius" data-text="<?= htmlspecialchars($text_17, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<tr>
    <td>1.8</td>
    <td>Kabelių įvedimo per pamatą angų sandarinimo medžiagos</td>
    <?php
    // Klasė paryškinimui, jei duomenys tušti
    $klase_18 = '';

    // Prisijungimas prie DB
    // Using existing PDO connection $conn from Database class
    // $mysqli removed - replaced with PDO
    // gaminio_id is already defined at the top of file

    $stmt = $conn->prepare("SELECT linijos_10kv_nr FROM gaminio_kirtikliai WHERE gaminio_id = :gaminio_id");
    $stmt->execute([':gaminio_id' => $gaminio_id]);
    $kiek_liniju = 0;

    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty(trim($row['linijos_10kv_nr']))) {
                // Skaičiuojam kiek 10kV linijų (skiriamasis simbolis ";")
                $kiek_liniju += count(array_filter(array_map('trim', explode(';', $row['linijos_10kv_nr']))));
            }
        }
    }

    $kabelio_tekstas = ($kiek_liniju > 0)
        ? $kiek_liniju . ' kompl. 3x240 mm2 kabeliui'
        : $t['no_data'];

    if ($kiek_liniju == 0) $klase_18 = 'highlight';
    ?>
    <td colspan="2" class="<?= $klase_18 ?>">
        <?= htmlspecialchars($kabelio_tekstas) ?>
    </td>
</tr>




<tr class="section-title">
  <td colspan="4"><strong>2. Galios transformatoriaus skyrius:</strong></td>
</tr>

<!-- 2.1 -->
<?php
  $orig_21 = !empty($komp_21['gamintojo_kodas']) ? ($komp_21['gamintojo_kodas'] . ', ' . ($komp_21['gamintojas'] ?? '')) : $t['no_data'];
  $text_21 = $korekcijos->gautiTeksta('2_1', $orig_21);
  $klase_21 = $korekcijos->turiKorekcija('2_1') ? 'koreguota' : ((empty($komp_21['gamintojo_kodas']) || empty($komp_21['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>2.1</td>
  <td>0,4 kV jungtys (galios transformatorius–0,4 kV skirstykla)</td>
  <td class="<?= $klase_21 ?>" colspan="2">
    <?= htmlspecialchars($text_21) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="2_1" data-label="2.1 - 0,4 kV jungtys" data-text="<?= htmlspecialchars($text_21, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<!-- 2.2 -->
<?php
  $orig_22 = !empty($komp_22['gamintojo_kodas']) ? ($komp_22['gamintojo_kodas'] . ', ' . ($komp_22['gamintojas'] ?? '')) : $t['no_data'];
  $text_22 = $korekcijos->gautiTeksta('2_2', $orig_22);
  $klase_22 = $korekcijos->turiKorekcija('2_2') ? 'koreguota' : ((empty($komp_22['gamintojo_kodas']) || empty($komp_22['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>2.2</td>
  <td>10 kV jungtys (galios transformatorius–12 kV skirst. įrenginys)</td>
  <td class="<?= $klase_22 ?>" colspan="2">
    <?= htmlspecialchars($text_22) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="2_2" data-label="2.2 - 10 kV jungtys" data-text="<?= htmlspecialchars($text_22, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<!-- 2.3 -->
<?php
  $klase_23 = (empty($transformatoriu_aprasas) || empty($galingumas_kva)) ? 'highlight' : '';
?>
<tr>
  <td>2.3</td>
  <td>Transformatorių skaičius ir maksimali transformatorių galia kVA</td>
  <td colspan="2" class="<?= $klase_23 ?>">
    <?= htmlspecialchars($transformatoriu_aprasas ?? '') ?> (<?= htmlspecialchars($galingumas_kva ?? '') ?> kVA)
  </td>
</tr>

<tr class="section-title">
  <td colspan="4">3. 0,4 kV įtampos skyrius:</td>
</tr>

<!-- 3.1 -->
<?php
  $orig_31 = ($komp_31['gamintojo_kodas'] ?? '') . ' ' . ($komp_31['gamintojas'] ?? '') . ' (' . ($komp_31['kiekis'] ?? '') . ' vnt.)';
  $text_31 = $korekcijos->gautiTeksta('3_1', $orig_31);
  $klase_31 = $korekcijos->turiKorekcija('3_1') ? 'koreguota' : ((empty($komp_31['gamintojo_kodas']) || empty($komp_31['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>3.1</td>
  <td>Įvadinis saugiklių–kirtiklių blokas TKS, (tipas, vnt., gamintojas)</td>
  <td colspan="2" class="<?= $klase_31 ?>">
    <?= htmlspecialchars($text_31) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="3_1" data-label="3.1 - TKS blokas" data-text="<?= htmlspecialchars($text_31, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<!-- 3.2 -->
<?php
  $orig_32 = ($komp_32['gamintojo_kodas'] ?? '') . ' ' . ($komp_32['gamintojas'] ?? '');
  $text_32 = $korekcijos->gautiTeksta('3_2', $orig_32);
  $klase_32 = $korekcijos->turiKorekcija('3_2') ? 'koreguota' : ((empty($komp_32['gamintojo_kodas']) || empty($komp_32['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>3.2</td>
  <td>Įvadinis saugiklio lydusis įdėklas (gamintojas, tipas)</td>
  <td colspan="2" class="<?= $klase_32 ?>">
    <?= htmlspecialchars($text_32) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="3_2" data-label="3.2 - Įvadinis lydusis" data-text="<?= htmlspecialchars($text_32, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<!-- 3.3 -->
<?php
  $orig_33 = ($komp_33['gamintojo_kodas'] ?? '') . ' ' . ($komp_33['gamintojas'] ?? '') . ' (' . ($komp_33['kiekis'] ?? '') . ' vnt.)';
  $text_33 = $korekcijos->gautiTeksta('3_3', $orig_33);
  $klase_33 = $korekcijos->turiKorekcija('3_3') ? 'koreguota' : ((empty($komp_33['gamintojo_kodas']) || empty($komp_33['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>3.3</td>
  <td>Linijinis saugiklių–kirtiklių blokas (gamintojas, tipas, vnt.)</td>
  <td colspan="2" class="<?= $klase_33 ?>">
    <?= htmlspecialchars($text_33) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="3_3" data-label="3.3 - Linijinis blokas" data-text="<?= htmlspecialchars($text_33, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>

<!-- 3.4 -->
<?php
  $orig_34 = ($komp_34['gamintojo_kodas'] ?? '') . ' ' . ($komp_34['gamintojas'] ?? '');
  $text_34 = $korekcijos->gautiTeksta('3_4', $orig_34);
  $klase_34 = $korekcijos->turiKorekcija('3_4') ? 'koreguota' : ((empty($komp_34['gamintojo_kodas']) || empty($komp_34['gamintojas'])) ? 'highlight' : '');
?>
<tr>
  <td>3.4</td>
  <td>0,4 kV saugiklių lydieji įdėklai</td>
  <td colspan="2" class="<?= $klase_34 ?>">
    <?= htmlspecialchars($text_34) ?>
    <?php if (!$pdf_rezimas): ?>
      <button type="button" class="edit-btn" data-field="3_4" data-label="3.4 - Lydieji įdėklai" data-text="<?= htmlspecialchars($text_34, ENT_QUOTES, 'UTF-8') ?>" onclick="openEditModal(this)">✏️</button>
    <?php endif; ?>
  </td>
</tr>


<!-- 3.5– -->
<?php
$gaminio_pavadinimas = $gamynys->gautiPavadinimaPagalGaminioId($gaminio_id);
include '../blokai/mt_saugikliai_blokas.php';
?>



<!-- 3.9 -->
<?php
$stmt = $conn->prepare("SELECT * FROM gaminio_kirtikliai WHERE gaminio_id = ?");
$stmt->execute([$gaminio_id]);
$kirtiklis = $stmt->fetch(PDO::FETCH_ASSOC);
$klase_39 = (empty($komp_39['gamintojo_kodas']) || empty($komp_39['gamintojas'])) ? 'highlight' : '';
?>

<tr>
  <td>3.9</td>
  <td>0,4 kV sekcinis komutacinis aparatas (gamintojas, tipas, vardinė srovė, gr. Nr.)</td>
  <td colspan="2" class="<?= $klase_39 ?>">
    <?php
      $aparatas = !empty($komp_39['gamintojo_kodas']) 
          ? htmlspecialchars($komp_39['gamintojo_kodas']) . ', ' . htmlspecialchars($komp_39['gamintojas']) 
          : $t['no_data'];

      $sekcinis = !empty($kirtiklis['sekcijinis_04kv_nr']) 
          ? 'Nr. ' . htmlspecialchars($kirtiklis['sekcijinis_04kv_nr']) 
          : '';

      echo $aparatas . ' ' . $sekcinis;
    ?>
  </td>
</tr>






<!-- 3.10 -->
<?php
  $klase_310 = (empty($komp_310['gamintojo_kodas']) || empty($komp_310['gamintojas'])) ? 'highlight' : '';
?>
<tr>
  <td>3.10</td>
  <td>Sekcinio saugiklio įdėklas (gamintojas, tipas)</td>
  <td colspan="2" class="<?= $klase_310 ?>">
    <?= !empty($komp_310['gamintojo_kodas'])
        ? htmlspecialchars($komp_310['gamintojo_kodas']) . ', ' . htmlspecialchars($komp_310['gamintojas']) . ''
        : $t['no_data'] ?>
  </td>
</tr>

<!-- 3.11 -->
<?php
  $klase_311 = (empty($komp_311['gamintojo_kodas']) || empty($komp_311['kiekis']) || empty($komp_311['gnybtas'])) ? 'highlight' : '';
?>
<tr>
  <td>3.11</td>
  <td>Komercinė apskaita</td>
  <td colspan="2" class="<?= $klase_311 ?>">
    <?php
      if (empty($komp_311['gamintojo_kodas']) || empty($komp_311['kiekis']) || empty($komp_311['gnybtas'])) {
        echo 'Nėra';
      } else {
        echo htmlspecialchars($komp_311['gamintojo_kodas']) . ', kiekis: ' . htmlspecialchars($komp_311['kiekis']) . ', gnybtas: ' . htmlspecialchars($komp_311['gnybtas']);
      }
    ?>
  </td>
</tr>


<!-- 3.12 -->
<?php
  $klase_312 = (empty($komp_312['gamintojo_kodas']) || empty($komp_312['kiekis']) || empty($komp_312['gnybtas'])) ? 'highlight' : '';
?>
<tr>
  <td>3.12</td>
  <td>Kontrolinė apskaita</td>
  <td colspan="2" class="<?= $klase_312 ?>">
    <?= !empty($komp_312['gamintojo_kodas'])
        ? htmlspecialchars($komp_312['gamintojo_kodas']) . ', kiekis: ' . htmlspecialchars($komp_312['kiekis']) . ', gnybtas: ' . htmlspecialchars($komp_312['gnybtas'])
        : $t['no_data'] ?>
  </td>
</tr>

        <tr class="section-title">
  <td colspan="3">4. Transformatorinės dokumentacija</td>
</tr>
<tr>
  <td>4.1</td>
  <td colspan="2">MT vienlinijinė elektrinė schema su pavaizduotais visais pase nurodytais elementais. Teikiama kartu su šiuo pasu</td>
</tr>



      <!-- 4.2 eilutė -->
<tr>
  <td>4.2</td>
  <td>Gaminio pasas</td>
  <td class="<?= empty($gaminio_pasas) ? 'highlight' : '' ?>">
    <?= htmlspecialchars($gaminio_pasas) ?>
  </td>
</tr>

<!-- Tekstas su dinaminiais laukais -->
<tr>
  <td colspan="3">
    <span class="<?= empty($gaminio_pavadinimas) ? 'highlight' : '' ?>">
      <?= htmlspecialchars($gaminio_pavadinimas) ?>
    </span>
    (gaminio serijos Nr.
    <span class="<?= empty($serijos_nr) ? 'highlight' : '' ?>">
      <?= htmlspecialchars($serijos_nr) ?>
    </span>)
    sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271-202 standartą bandymų protokolo Nr.
    <span class="<?= empty($protokolo_nr) ? 'highlight' : '' ?>">
      <?= htmlspecialchars($protokolo_nr) ?>
    </span>.
    Komplektuojamajam skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal LST EN 62271 standartą.
    Komplektuojamajam SI-04R skirstomajam įrenginiui sėkmingai atlikti gamykliniai bandymai pagal
    LST EN 61439-1 ir LST EN 61439-2 standartus. Bandymų protokolo Nr
    <span class="<?= empty($protokolo_nr) ? 'highlight' : '' ?>">
      <?= htmlspecialchars($protokolo_nr) ?>
    </span>.
  </td>
</tr>

<tr>
  <td colspan="3">
    <span class="<?= empty($gaminio_pavadinimas) ? 'highlight' : '' ?>">
      <?= htmlspecialchars($gaminio_pavadinimas) ?>
    </span>
    ir visiems komplektuojamiems įrenginiams garantija teikiama pagal gaminio serijos numerį.
    Gamintojas (UAB ELGA) įsipareigoja vykdyti transformatorinės
    <span class="<?= empty($gaminio_pavadinimas) ? 'highlight' : '' ?>">
      <?= htmlspecialchars($gaminio_pavadinimas) ?>
    </span>
    garantinį aptarnavimą 24 mėn.
  </td>
</tr>

<!-- Parašo blokas -->
<!-- Parašo blokas -->
<!-- Parašo blokas -->
<tr class="paraso-eilute">
  <td colspan="3">
    <table class="no-border" style="width: 100%;">
      <tr>
        <!-- Kairysis stulpelis -->
        <td class="no-border">
          <strong><?= $t['quality_engineer'] ?></strong><br>
          <strong class="<?= empty($inzinierius) ? 'highlight' : '' ?>">
            <?= htmlspecialchars($inzinierius) ?>
          </strong><br>
          <small><?= $t['position'] ?></small>
        </td>

        <!-- Vidurinis stulpelis -->
        <td class="no-border">
          <strong class="<?= empty($data) ? 'highlight' : '' ?>">
            <?= htmlspecialchars($data) ?>
          </strong><br>
          <small><?= $t['date'] ?></small>
        </td>

        <!-- Dešinysis stulpelis -->
        <td class="no-border" style="text-align: center;">
          <img src="<?= htmlspecialchars($parasas_failas) ?>" alt="Parašas" style="max-height: 120px; max-width: 240px;"><br>
          <small><?= $t['signature'] ?></small>
        </td>
      </tr>
    </table>
  </td>
</tr>



    </table>
  </td>
</tr>

 <?php if (empty($_GET['pdf']) || $_GET['pdf'] != 'taip'): ?>
    <div class="mt-3">
        <a href="../gaminiu_langai_mt.php?gaminys_id=<?= urlencode($gaminys_id) ?>&gaminio_numeris=<?= urlencode($gaminio_numeris) ?>&gaminio_pavadinimas=<?= urlencode($gaminio_pavadinimas) ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>" class="btn btn-secondary ms-2">← Grįžti</a>
    </div>
<?php endif; ?>

</div>

<?php if (!$pdf_rezimas): ?>
<!-- Redagavimo modalas -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
  <div style="background:white; max-width:500px; margin:50px auto; padding:20px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
      <h4 style="margin:0;">✏️ Koreguoti tekstą</h4>
      <button onclick="closeEditModal()" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
    </div>
    <form id="editForm">
      <input type="hidden" id="edit_gaminio_id" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
      <input type="hidden" id="edit_field_key" name="field_key" value="">
      <input type="hidden" id="edit_lang" name="lang" value="<?= $lang ?>">
      
      <div style="margin-bottom:15px;">
        <label style="display:block; font-weight:bold; margin-bottom:5px;">Laukas:</label>
        <span id="edit_field_label" style="color:#666;"></span>
      </div>
      
      <div style="margin-bottom:15px;">
        <label style="display:block; font-weight:bold; margin-bottom:5px;">Tekstas (<?= strtoupper($lang) ?>):</label>
        <textarea id="edit_tekstas" name="tekstas" rows="4" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px;"></textarea>
      </div>
      
      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" onclick="closeEditModal()" style="padding:8px 16px; background:#6c757d; color:white; border:none; border-radius:4px; cursor:pointer;">Atšaukti</button>
        <button type="submit" style="padding:8px 16px; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;">💾 Išsaugoti</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(btn) {
  var fieldKey = btn.getAttribute('data-field');
  var fieldLabel = btn.getAttribute('data-label');
  var currentText = btn.getAttribute('data-text');
  
  document.getElementById('edit_field_key').value = fieldKey;
  document.getElementById('edit_field_label').textContent = fieldLabel;
  document.getElementById('edit_tekstas').value = currentText || '';
  document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  
  fetch('issaugoti_mt_pasa_teksta.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Tekstas išsaugotas!');
      location.reload();
    } else {
      alert('Klaida: ' + data.message);
    }
  })
  .catch(error => {
    alert('Klaida siunčiant duomenis');
    console.error(error);
  });
});

document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>

<style>
.edit-btn {
  background: #ffc107;
  color: #212529;
  border: none;
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 11px;
  cursor: pointer;
  margin-left: 5px;
  vertical-align: middle;
}
.edit-btn:hover {
  background: #e0a800;
}
.koreguota {
  background-color: #d4edda !important;
  border-left: 3px solid #28a745;
}
@media print {
  .edit-btn { display: none !important; }
  #editModal { display: none !important; }
}
</style>
<?php endif; ?>

</body>
</html>
