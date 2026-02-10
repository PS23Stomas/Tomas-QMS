<?php
/**
 * MT funkcinių bandymų PDF generavimas ir išsaugojimas
 * 
 * Šis failas generuoja PDF dokumentą su MT gaminio funkcinių bandymų rezultatais
 * ir išsaugo jį į gvx_dokumentai lentelę duomenų bazėje.
 * 
 * Pagrindinės funkcijos:
 * - Gauna bandymų duomenis iš mt_funkciniai_bandymai lentelės
 * - Generuoja HTML šabloną su bandymų rezultatais
 * - Konvertuoja HTML į PDF naudojant mPDF biblioteką
 * - Įrašo arba atnaujina PDF dokumentą gvx_dokumentai lentelėje
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

/**
 * Sesijos pradžia ir vartotojo duomenų gavimas
 */
session_start();
$vardas  = $_SESSION['vardas']  ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';
$sukurejasVardas = trim($vardas . ' ' . $pavarde);

/**
 * GET parametrų nuskaitymas
 * - gaminio_id: gaminio identifikatorius (privalomas)
 * - uzsakymo_numeris: užsakymo numeris
 * - uzsakovas: užsakovo pavadinimas
 */
$gaminio_id       = (int)($_GET['gaminio_id'] ?? 0);
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_GET['uzsakovas'] ?? '';

/**
 * Tikrinama ar pateiktas gaminio ID
 */
if ($gaminio_id <= 0) { http_response_code(400); echo 'Trūksta gaminio_id'; exit; }

/**
 * Duomenų bazės prisijungimo inicializavimas
 */
$db  = new Database();
$pdo = $db->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/**
 * Užsakymo informacijos gavimas pagal gaminio ID
 * Sujungiamos gaminiai, uzsakymai ir uzsakovai lentelės
 */
$inf = $pdo->prepare("
  SELECT u.id AS uzsakymo_id, u.uzsakymo_numeris, z.uzsakovas, g.id AS gaminys_id
    FROM gaminiai g
    JOIN uzsakymai u ON u.id = g.uzsakymo_id
    LEFT JOIN uzsakovai z ON z.id = u.uzsakovas_id
   WHERE g.id = ?
   LIMIT 1");
$inf->execute([$gaminio_id]);
$hdr = $inf->fetch();
if (!$hdr) { echo 'Nerastas gaminys'; exit; }

$uzsakymo_id_db   = (int)$hdr['uzsakymo_id'];
$uzsakymo_num_db  = $hdr['uzsakymo_numeris'];
$uzsakovas_db     = $hdr['uzsakovas'] ?? '';

/* Pagrindinės lentelės duomenys */
$st = $pdo->prepare("
  SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas
    FROM mt_funkciniai_bandymai
   WHERE gaminio_id = ?
ORDER BY eil_nr ASC");
$st->execute([$gaminio_id]);
$rows = $st->fetchAll();

/**
 * HTML šablono generavimas PDF dokumentui
 * Pradedamas išvesties buferis, kuris vėliau bus konvertuotas į PDF
 */
ob_start();
?>
<!doctype html>
<html lang="lt">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
<meta charset="utf-8">
<title>MT funkcinių bandymų PDF</title>
<style>
  body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ border:0.8px solid #9aa5ad; padding:4px; vertical-align:top; }
  thead th{ background:#eef4f8; text-align:center; }
  .ok   td{ background:#e5f4e5; }
  .bad  td{ background:#ffecec; }
  .none td{ background:#eef6ff; }
  .muted{ color:#6c757d; font-size:.9em; }
  h3{ margin:0 0 6px 0; }
  .head{ margin-bottom:8px; }
</style>
</head>
<body>
  <div class="head">
    <h3>MT gaminio funkcinių bandymų forma</h3>
    Užsakymas: <strong><?=htmlspecialchars($uzsakymo_num_db)?></strong> •
    Užsakovas: <strong><?=htmlspecialchars($uzsakovas ?: $uzsakovas_db)?></strong> •
    Sugeneruota: <strong><?=date('Y-m-d H:i')?></strong>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:45px">Eil. Nr</th>
        <th>Reikalavimas</th>
        <th style="width:140px">Įrašė</th>
        <th style="width:160px">Atliko</th>
        <th style="width:100px">Išvada</th>
        <th style="width:240px">Defektas</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $map = ['atitinka'=>'ok','nepadaryta'=>'bad','nėra'=>'none','nera'=>'none'];
      foreach ($rows as $r):
        $cls = $map[$r['isvada'] ?? ''] ?? '';
    ?>
      <tr class="<?=$cls?>">
        <td style="text-align:center"><?= (int)$r['eil_nr'] ?></td>
        <td><?= nl2br(htmlspecialchars($r['reikalavimas'])) ?></td>
        <td><?= htmlspecialchars($r['irase_vartotojas'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['darba_atliko'] ?? '—') ?></td>
        <td style="text-align:center"><?= htmlspecialchars($r['isvada'] ?? '—') ?></td>
        <td><?= nl2br(htmlspecialchars($r['defektas'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <p class="muted">Parengė: <?=htmlspecialchars($sukurejasVardas ?: '—')?></p>
</body>
</html>
<?php
$html = ob_get_clean();

/**
 * mPDF bibliotekos autoload failo paieška
 * Bandoma rasti vendor/autoload.php įvairiuose keliuose
 */
$autoloadCandidates = [
  __DIR__ . '/../../vendor/autoload.php',
  __DIR__ . '/../vendor/autoload.php',
  dirname(__DIR__, 2) . '/vendor/autoload.php',
  ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/praktika1/vendor/autoload.php',
  ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/vendor/autoload.php',
];
$autoloadFound = false;
foreach ($autoloadCandidates as $p) {
  if ($p && is_file($p)) { require_once $p; $autoloadFound = true; break; }
}
if (!$autoloadFound) { echo $html; exit; }

/**
 * mPDF objekto inicializavimas
 * Nustatomi paraštės, formatas ir UTF-8 koduotė
 */
$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0777, true); }
$mpdf = new \Mpdf\Mpdf([
  'mode' => 'utf-8',
  'format' => 'A4',
  'margin_top' => 10,
  'margin_bottom' => 12,
  'margin_left' => 10,
  'margin_right' => 10,
  'tempDir' => $tmpDir,
  'default_font' => 'DejaVu Sans',
]);
$mpdf->autoLangToFont = true;
$mpdf->WriteHTML($html);
$pdfBytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

/**
 * PDF failo pavadinimo ir dydžio nustatymas
 * Failo pavadinimas sudarytas iš užsakymo numerio, gaminio ID ir datos
 */
$ts = date('Ymd_His');
$file = 'MT_Bandymai_' . $uzsakymo_num_db . '_g' . $gaminio_id . '_' . $ts . '.pdf';
$sizeB = strlen($pdfBytes);

echo "<div style='padding:14px;font-size:16px;'>✅ PDF sugeneruotas: <strong>".htmlspecialchars($file)."</strong></div>";


$sizeB   = strlen($pdfBytes);

/**
 * PDF dokumento įrašymas į gvx_dokumentai lentelę
 * Jei dokumentas jau egzistuoja - atnaujinamas, jei ne - sukuriamas naujas
 */
$title = 'MT funkcinių bandymų PDF';

/**
 * Kūrėjo stulpelio pavadinimo nustatymas
 * Tikrinama ar stulpelis vadinasi 'sukurejas' ar 'sukūrėjas'
 */
$creatorCol = null;
try {
  if ($pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'gvx_dokumentai' AND column_name = 'sukurejas' LIMIT 1")->fetch()) {
    $creatorCol = 'sukurejas';
  } elseif ($pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'gvx_dokumentai' AND column_name = 'sukūrėjas' LIMIT 1")->fetch()) {
    $creatorCol = 'sukūrėjas';
  }
} catch (Throwable $e) {}

/**
 * Tikrinama ar lentelėje yra BLOB tipo stulpelis PDF turiniui saugoti
 */
$hasBlob = false;
try { $hasBlob = (bool)$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'gvx_dokumentai' AND column_name = 'turinys_lob' LIMIT 1")->fetch(); } catch (Throwable $e) {}

/**
 * Ieškoma ar jau egzistuoja PDF dokumentas šiam gaminiui
 * Jei rastas - bus atnaujintas, jei ne - bus sukurtas naujas
 */
$ex = $pdo->prepare("
  SELECT id FROM gvx_dokumentai
   WHERE uzsakymo_id=? AND gaminys_id=? AND tipas='mt_bandymai_pdf'
   ORDER BY sukurta DESC LIMIT 1
");
$ex->execute([$uzsakymo_id_db, $gaminio_id]);
$rowId = (int)($ex->fetchColumn() ?: 0);

if ($rowId > 0) {
  /**
   * Atnaujinamas esamas dokumentas duomenų bazėje
   */
  if ($hasBlob) {
    $sql = "UPDATE gvx_dokumentai
               SET pavadinimas=?, failas=?, dydis_b=?, "
               . ($creatorCol && $sukurejasVardas ? "\"$creatorCol\"=?," : "")
               . " turinys_lob=?, sukurta=NOW()
             WHERE id=?";
    $st = $pdo->prepare($sql);
    $i=1;
    $st->bindValue($i++, $title);
    $st->bindValue($i++, $file);
    $st->bindValue($i++, $sizeB, PDO::PARAM_INT);
    if ($creatorCol && $sukurejasVardas) $st->bindValue($i++, $sukurejasVardas);
    $st->bindValue($i++, $pdfBytes, PDO::PARAM_LOB);
    $st->bindValue($i++, $rowId, PDO::PARAM_INT);
    $st->execute();
  } else {
    $sql = "UPDATE gvx_dokumentai
               SET pavadinimas=?, failas=?, dydis_b=?, "
               . ($creatorCol && $sukurejasVardas ? "\"$creatorCol\"=?," : "")
               . " sukurta=NOW()
             WHERE id=?";
    $st = $pdo->prepare($sql);
    $i=1;
    $st->bindValue($i++, $title);
    $st->bindValue($i++, $file);
    $st->bindValue($i++, $sizeB, PDO::PARAM_INT);
    if ($creatorCol && $sukurejasVardas) $st->bindValue($i++, $sukurejasVardas);
    $st->bindValue($i++, $rowId, PDO::PARAM_INT);
    $st->execute();
  }
} else {
  /**
   * Įterpiamas naujas dokumentas į duomenų bazę
   */
  if ($hasBlob) {
    $sql = "INSERT INTO gvx_dokumentai
              (uzsakymo_id, gaminys_id, tipas, pavadinimas, failas, dydis_b, "
              . ($creatorCol && $sukurejasVardas ? "\"$creatorCol\", " : "")
              . "turinys_lob, sukurta)
            VALUES (?, ?, 'mt_bandymai_pdf', ?, ?, ?, "
              . ($creatorCol && $sukurejasVardas ? "?, " : "")
              . "?, NOW())";
    $st = $pdo->prepare($sql);
    $i=1;
    $st->bindValue($i++, $uzsakymo_id_db, PDO::PARAM_INT);
    $st->bindValue($i++, $gaminio_id, PDO::PARAM_INT);
    $st->bindValue($i++, $title);
    $st->bindValue($i++, $file);
    $st->bindValue($i++, $sizeB, PDO::PARAM_INT);
    if ($creatorCol && $sukurejasVardas) $st->bindValue($i++, $sukurejasVardas);
    $st->bindValue($i++, $pdfBytes, PDO::PARAM_LOB);
    $st->execute();
  } else {
    $sql = "INSERT INTO gvx_dokumentai
              (uzsakymo_id, gaminys_id, tipas, pavadinimas, failas, dydis_b, "
              . ($creatorCol && $sukurejasVardas ? "\"$creatorCol\", " : "")
              . "sukurta)
            VALUES (?, ?, 'mt_bandymai_pdf', ?, ?, ?, "
              . ($creatorCol && $sukurejasVardas ? "?, " : "")
              . "NOW())";
    $st = $pdo->prepare($sql);
    $i=1;
    $st->bindValue($i++, $uzsakymo_id_db, PDO::PARAM_INT);
    $st->bindValue($i++, $gaminio_id, PDO::PARAM_INT);
    $st->bindValue($i++, $title);
    $st->bindValue($i++, $file);
    $st->bindValue($i++, $sizeB, PDO::PARAM_INT);
    if ($creatorCol && $sukurejasVardas) $st->bindValue($i++, $sukurejasVardas);
    $st->execute();
  }
}

/**
 * Patvirtinimo puslapio HTML
 * Rodomas pranešimas apie sėkmingą PDF sugeneravimą ir mygtukas grįžti atgal
 */
?>
<!doctype html>
<html lang="lt"><head><meta charset="utf-8"><title>PDF įrašytas</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-3">
  <div class="alert alert-success">
    ✅ MT bandymų PDF sugeneruotas ir įrašytas į <code>gvx_dokumentai</code>.
  </div>
  </div>
  <a class="btn btn-secondary" href="../gaminiu_langai_mt.php?uzsakymo_numeris=<?=urlencode($uzsakymo_numeris ?: $uzsakymo_num_db)?>&uzsakovas=<?=urlencode($uzsakovas ?: $uzsakovas_db)?>&gaminio_id=<?=$gaminio_id?>">Grįžti</a>
</body></html>
