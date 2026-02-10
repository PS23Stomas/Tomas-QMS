<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$vardas = $_SESSION['vardas'] ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';
$pareigos = "Kokybės inžinierius";
$data = date("Y-m-d");

$conn = Database::getConnection();

$gaminys_id = isset($_REQUEST['gaminys_id']) ? (int)$_REQUEST['gaminys_id'] :
             (isset($_REQUEST['gaminio_id']) ? (int)$_REQUEST['gaminio_id'] : 0);

$gaminio_numeris   = $_REQUEST['gaminio_numeris']   ?? '';
$uzsakymo_numeris  = $_REQUEST['uzsakymo_numeris']  ?? '';
$uzsakovas         = $_REQUEST['uzsakovas']         ?? '';
$gaminio_pavadinimas = $_REQUEST['gaminio_pavadinimas'] ?? '';
$uzsakymo_id       = $_REQUEST['uzsakymo_id']       ?? '';
$issaugota         = $_REQUEST['issaugota'] ?? '';

if ($gaminys_id <= 0) die("Klaida: nėra gaminio ID");

$stmt = $conn->prepare("SELECT id, protokolo_nr FROM gaminiai WHERE id=?");
$stmt->execute([$gaminys_id]);
$gaminys = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$gaminys) die("Klaida: gaminys nerastas");
$protokolo_numeris = $gaminys['protokolo_nr'] ?? '';

if (isset($_POST['prideti'])) {
    $stmt = $conn->prepare("INSERT INTO bandymai_prietaisai (gaminys_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$gaminys_id, $_POST['prietaiso_tipas'], $_POST['prietaiso_nr'], $_POST['patikra_data'], $_POST['galioja_iki'], $_POST['sertifikato_nr']]);
    header("Location: mt_dielektriniai.php?gaminys_id=$gaminys_id&gaminio_numeris=" . urlencode($gaminio_numeris) . "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) . "&uzsakovas=" . urlencode($uzsakovas) . "&gaminio_pavadinimas=" . urlencode($gaminio_pavadinimas) . "&uzsakymo_id=" . urlencode($uzsakymo_id) . "&issaugota=taip&t=" . time());
    exit;
}

if (isset($_POST['redaguoti'])) {
    $stmt = $conn->prepare("UPDATE bandymai_prietaisai SET prietaiso_tipas=?, prietaiso_nr=?, patikra_data=?, galioja_iki=?, sertifikato_nr=? WHERE id=? AND gaminys_id=?");
    $stmt->execute([$_POST['prietaiso_tipas'], $_POST['prietaiso_nr'], $_POST['patikra_data'], $_POST['galioja_iki'], $_POST['sertifikato_nr'], $_POST['id'], $gaminys_id]);
    header("Location: mt_dielektriniai.php?gaminys_id=$gaminys_id&gaminio_numeris=" . urlencode($gaminio_numeris) . "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) . "&uzsakovas=" . urlencode($uzsakovas) . "&gaminio_pavadinimas=" . urlencode($gaminio_pavadinimas) . "&uzsakymo_id=" . urlencode($uzsakymo_id) . "&issaugota=taip&t=" . time());
    exit;
}

if (isset($_GET['salinti'])) {
    $id = (int)$_GET['salinti'];
    $gaminio_numeris = $_GET['gaminio_numeris'] ?? $gaminio_numeris;
    $uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? $uzsakymo_numeris;
    $uzsakovas = $_GET['uzsakovas'] ?? $uzsakovas;
    $gaminio_pavadinimas = $_GET['gaminio_pavadinimas'] ?? $gaminio_pavadinimas;

    $stmt = $conn->prepare("DELETE FROM bandymai_prietaisai WHERE id=? AND gaminys_id=?");
    $stmt->execute([$id, $gaminys_id]);
    header("Location: mt_dielektriniai.php?gaminys_id=$gaminys_id&gaminio_numeris=" . urlencode($gaminio_numeris) . "&uzsakymo_numeris=" . urlencode($uzsakymo_numeris) . "&uzsakovas=" . urlencode($uzsakovas) . "&gaminio_pavadinimas=" . urlencode($gaminio_pavadinimas) . "&uzsakymo_id=" . urlencode($uzsakymo_id) . "&issaugota=taip&t=" . time());
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM bandymai_prietaisai WHERE gaminys_id=?");
$stmt->execute([$gaminys_id]);
$prietaisu_sk = $stmt->fetchColumn();

if ($prietaisu_sk == 0) {
    $default_prietaisai = [
        ['Eurotest 61554', '11350310', '2025-02-07', '2026-02-06', '2233650'],
        ['Metrel 2077', '07180456', '2025-02-25', '2026-02-24', '2233717'],
        ['AID-70M', '1800', '2025-05-26', '2026-05-24', 'K-0043409']
    ];
    $sql = "INSERT INTO bandymai_prietaisai (gaminys_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) VALUES (?, ?, ?, ?, ?, ?)";
    $insert = $conn->prepare($sql);
    foreach ($default_prietaisai as $p) {
        $insert->execute([$gaminys_id, $p[0], $p[1], $p[2], $p[3], $p[4]]);
    }
}

$stmt = $conn->prepare("SELECT * FROM bandymai_prietaisai WHERE gaminys_id=? ORDER BY prietaiso_tipas");
$stmt->execute([$gaminys_id]);
$prietaisai = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM antriniu_grandiniu_bandymai WHERE gaminys_id=?");
$stmt->execute([$gaminys_id]);
$vid_itampa = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM mt_dielektriniai_bandymai WHERE gaminys_id=? ORDER BY eiles_nr");
$stmt->execute([$gaminys_id]);
$maz_itampa = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM mt_izeminimo_tikrinimas WHERE gaminys_id=? ORDER BY eil_nr");
$stmt->execute([$gaminys_id]);
$izem = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>MT Dielektriniai bandymai</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
th, td { text-align: center; vertical-align: middle !important; }
.text-start { text-align: left !important; }
table.prietaisu-lentele { font-size: 13px; width: 100%; table-layout: fixed; border-collapse: collapse; }
table.prietaisu-lentele th, table.prietaisu-lentele td { padding: 4px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
table.prietaisu-lentele th:nth-child(1), table.prietaisu-lentele td:nth-child(1) { width: 7%; }
table.prietaisu-lentele th:nth-child(2), table.prietaisu-lentele td:nth-child(2) { width: 30%; }
table.prietaisu-lentele th:nth-child(3), table.prietaisu-lentele td:nth-child(3) { width: 15%; }
table.prietaisu-lentele th:nth-child(4), table.prietaisu-lentele td:nth-child(4) { width: 12%; }
table.prietaisu-lentele th:nth-child(5), table.prietaisu-lentele td:nth-child(5) { width: 12%; }
table.prietaisu-lentele th:nth-child(6), table.prietaisu-lentele td:nth-child(6) { width: 10%; }
table.prietaisu-lentele th:nth-child(7), table.prietaisu-lentele td:nth-child(7) { width: 7%; text-align:center; }
.btn-sm { padding: 2px 6px; font-size: 12px; }
</style>
</head>
<body>
<div class="container mt-4">

<h4 class="mb-4 text-uppercase fw-bold">
    MT ATLIKTŲ BANDYMŲ PROTOKOLAS NR. <?=htmlspecialchars($protokolo_numeris)?>
</h4>

<p><strong>Užsakymo Nr.:</strong> <?=htmlspecialchars($uzsakymo_numeris)?> |
<strong>Užsakovas:</strong> <?=htmlspecialchars($uzsakovas)?> |
<strong>Pavadinimas:</strong> <?=htmlspecialchars($gaminio_pavadinimas)?></p>

<?php if ($issaugota==='taip'): ?>
<div class="alert alert-success">Duomenys sėkmingai išsaugoti.</div>
<?php endif; ?>

<form action="mt_dielektriniai.php" method="post">
    <input type="hidden" name="gaminys_id" value="<?=$gaminys_id?>">
    <input type="hidden" name="gaminio_numeris" value="<?=htmlspecialchars($gaminio_numeris)?>">
    <input type="hidden" name="uzsakymo_numeris" value="<?=htmlspecialchars($uzsakymo_numeris)?>">
    <input type="hidden" name="uzsakovas" value="<?=htmlspecialchars($uzsakovas)?>">
    <input type="hidden" name="gaminio_pavadinimas" value="<?=htmlspecialchars($gaminio_pavadinimas)?>">
    <input type="hidden" name="uzsakymo_id" value="<?=htmlspecialchars($uzsakymo_id)?>">
    <input type="hidden" name="id" id="prietaiso_id">

    <h4 class="mt-4">Matavimai atlikti prietaisais:</h4>
    
    <div class="row g-2 mb-2">
        <div class="col"><input type="text" id="prietaiso_tipas" name="prietaiso_tipas" class="form-control" placeholder="Tipas" required></div>
        <div class="col"><input type="text" id="prietaiso_nr" name="prietaiso_nr" class="form-control" placeholder="Nr." required></div>
        <div class="col"><input type="date" id="patikra_data" name="patikra_data" class="form-control" required></div>
        <div class="col"><input type="date" id="galioja_iki" name="galioja_iki" class="form-control" required></div>
        <div class="col"><input type="text" id="sertifikato_nr" name="sertifikato_nr" class="form-control" placeholder="Sertifikato Nr." required></div>
    </div>

    <button type="submit" name="prideti" class="btn btn-success">Pridėti</button>
    <button type="submit" name="redaguoti" class="btn btn-warning">Atnaujinti</button>
</form>

<table class="table table-bordered prietaisu-lentele mt-3">
<thead class="table-secondary">
<tr><th>Nr.</th><th>Tipas</th><th>Nr.</th><th>Patikra</th><th>Galioja iki</th><th>Sertifikatas</th><th>Veiksmai</th></tr>
</thead>
<tbody>
<?php if ($prietaisai): $i=1; foreach($prietaisai as $p): ?>
<tr>
    <td><?=$i++?></td>
    <td><?=htmlspecialchars($p['prietaiso_tipas'])?></td>
    <td><?=htmlspecialchars($p['prietaiso_nr'])?></td>
    <td><?=htmlspecialchars($p['patikra_data'])?></td>
    <td><?=htmlspecialchars($p['galioja_iki'])?></td>
    <td><?=htmlspecialchars($p['sertifikato_nr'])?></td>
    <td>
        <button type="button" class="btn btn-sm btn-primary"
            onclick="redaguotiPrietaisa(<?=$p['id']?>,'<?=htmlspecialchars($p['prietaiso_tipas'])?>','<?=htmlspecialchars($p['prietaiso_nr'])?>','<?=$p['patikra_data']?>','<?=$p['galioja_iki']?>','<?=htmlspecialchars($p['sertifikato_nr'])?>')">Red.</button>
        <a href="?salinti=<?=$p['id']?>&gaminys_id=<?=$gaminys_id?>&gaminio_numeris=<?=urlencode($gaminio_numeris)?>&uzsakymo_numeris=<?=urlencode($uzsakymo_numeris)?>&uzsakovas=<?=urlencode($uzsakovas)?>&gaminio_pavadinimas=<?=urlencode($gaminio_pavadinimas)?>"
           class="btn btn-sm btn-danger" onclick="return confirm('Šalinti?')">Trint.</a>
    </td>
</tr>
<?php endforeach; else: ?><tr><td colspan="7">Įrašų nėra</td></tr><?php endif; ?>
</tbody>
</table>

<script>
function redaguotiPrietaisa(id, tipas, nr, patikra, galioja, sert) {
    document.getElementById('prietaiso_id').value=id;
    document.getElementById('prietaiso_tipas').value=tipas;
    document.getElementById('prietaiso_nr').value=nr;
    document.getElementById('patikra_data').value=patikra;
    document.getElementById('galioja_iki').value=galioja;
    document.getElementById('sertifikato_nr').value=sert;
}
</script>

<form action="/MT/issaugoti_mt_dielektriniai.php" method="post">
   <input type="hidden" name="gaminys_id" value="<?=$gaminys_id?>">
   <input type="hidden" name="gaminio_numeris" value="<?=htmlspecialchars($gaminio_numeris)?>">
   <input type="hidden" name="uzsakymo_numeris" value="<?=htmlspecialchars($uzsakymo_numeris)?>">
   <input type="hidden" name="uzsakovas" value="<?=htmlspecialchars($uzsakovas)?>">
   <input type="hidden" name="gaminio_pavadinimas" value="<?=htmlspecialchars($gaminio_pavadinimas)?>">
   <input type="hidden" name="uzsakymo_id" value="<?=htmlspecialchars($uzsakymo_id)?>">

<h5 class="mt-5 text-uppercase fw-bold">VIDUTINĖS ĮTAMPOS (6–24 kV) KABELIŲ BANDYMAS</h5>
<table class="table table-bordered">
<thead class="table-secondary">
<tr><th>Eil. Nr.</th><th>Bandymo elementų aprašymas</th><th>Un (kV)</th><th>Bandymo schema</th><th>Band. įtampa (kV)</th><th>Trukmė (min)</th></tr>
</thead>
<tbody>
<?php
if (empty($vid_itampa)) {
    $t = (stripos($gaminio_pavadinimas, '2x') !== false) ? 2 : 1;
    for ($i = 1; $i <= $t; $i++) {
        $label = ($t==1) ? 'transformatorių' : "T-$i";
        echo "<tr>
            <td>$i<input type='hidden' name='vid_itampa[eiles_nr][]' value='$i'></td>
            <td><input type='text' name='vid_itampa[aprasymas][]' class='form-control' value='Kabeliai į {$label}, Gyslos L1, L2, L3'></td>
            <td><input type='text' name='vid_itampa[itampa][]' class='form-control' value='10'></td>
            <td><input type='text' name='vid_itampa[schema1][]' class='form-control' value='L - (kabelio ekranas + PE)'></td>
            <td><input type='text' name='vid_itampa[band_itampa][]' class='form-control' value='13'></td>
            <td><input type='text' name='vid_itampa[trukme][]' class='form-control' value='60'></td>
        </tr>";
    }
} else {
    $i = 1;
    foreach ($vid_itampa as $row) {
        echo "<tr>
            <td>$i<input type='hidden' name='vid_itampa[eiles_nr][]' value='$i'></td>
            <td><input type='text' name='vid_itampa[aprasymas][]' class='form-control' value='".htmlspecialchars($row['grandines_pavadinimas'])."'></td>
            <td><input type='text' name='vid_itampa[itampa][]' class='form-control' value='".htmlspecialchars($row['grandines_itampa'])."'></td>
            <td><input type='text' name='vid_itampa[schema1][]' class='form-control' value='L - (kabelio ekranas + PE)'></td>
            <td><input type='text' name='vid_itampa[band_itampa][]' class='form-control' value='".htmlspecialchars($row['bandymo_itampa_kV'])."'></td>
            <td><input type='text' name='vid_itampa[trukme][]' class='form-control' value='".htmlspecialchars($row['bandymo_trukme'])."'></td>
        </tr>";
        $i++;
    }
}
?>
</tbody>
</table>
<input type="hidden" name="vid_itampa[isvada]" value="10kV kabeliai bandymus išlaikė, izoliacija gera.">

<h5 class="mt-5 text-uppercase fw-bold">0,4kV GRANDINIŲ BANDYMAS PAAUKŠTINTA ĮTAMPA</h5>
<table class="table table-bordered" id="mazItampaTable">
<thead class="table-secondary">
<tr><th>Eil. Nr.</th><th>El. grandinių aprašymas</th><th>Grandinių įtampa</th><th>L1-PE</th><th>L2-PE</th><th>L3-PE</th><th>L1-L2</th><th>L2-L3</th><th>L1-L3</th><th>Veiksmas</th></tr>
</thead>
<tbody>
<?php
$default_maz = [
    ['0,4kV skirstomojo įrenginio grandinės(šynos)','400 V'], 
    ['Kontrolinės elektros apskaitos įtampos grandinės','400 V'], 
    ['Komercinės elektros apskaitos įtampos grandinės','400 V'],
    ['T-1 ventiliacijos grandinė','230 V'],
    ['T-2 ventiliacijos grandinė','230 V'],
    ['SRS kištukinio lizdo maitinimas','230 V']
];

if (!isset($maz_itampa) || !is_array($maz_itampa) || count($maz_itampa) === 0) {
    $eilutes = $default_maz;
} else {
    $eilutes = $maz_itampa;
}

$i = 1;
foreach ($eilutes as $r) {
    if (isset($r['aprasymas'])) {
        $aprasymas = $r['aprasymas'];
        $itampa    = $r['itampa'];
    } else {
        $aprasymas = $r[0];
        $itampa    = $r[1];
    }

    echo "<tr>
        <td>$i<input type='hidden' name='maz_itampa[eiles_nr][]' value='$i'></td>
        <td><input type='text' name='maz_itampa[aprasymas][]' class='form-control' value='".htmlspecialchars($aprasymas)."'></td>
        <td><input type='text' name='maz_itampa[itampa][]' class='form-control' value='".htmlspecialchars($itampa)."'></td>";

    for ($j = 1; $j <= 6; $j++) {
        $schema = (isset($r["schema$j"])) ? $r["schema$j"] : "2.5kV×1min";
        echo "<td><input type='text' name='maz_itampa[schema$j][]' class='form-control' value='".htmlspecialchars($schema)."'></td>";
    }

    echo "<td><button type='button' class='btn btn-danger btn-sm' onclick='removeRow(this)'>Šalinti</button></td></tr>";
    $i++;
}
?>
</tbody>
</table>
<button type="button" class="btn btn-primary btn-sm" onclick="addRow()">Pridėti eilutę</button>

<script>
function addRow() {
    let table = document.getElementById("mazItampaTable").getElementsByTagName('tbody')[0];
    let rowCount = table.rows.length + 1;
    let row = table.insertRow();
    row.innerHTML = `<td>${rowCount}<input type="hidden" name="maz_itampa[eiles_nr][]" value="${rowCount}"></td>
        <td><input type="text" name="maz_itampa[aprasymas][]" class="form-control"></td>
        <td><input type="text" name="maz_itampa[itampa][]" class="form-control" value="230 V"></td>
        <td><input type="text" name="maz_itampa[schema1][]" class="form-control" value="2.5kV×1min"></td>
        <td><input type="text" name="maz_itampa[schema2][]" class="form-control" value="2.5kV×1min"></td>
        <td><input type="text" name="maz_itampa[schema3][]" class="form-control" value="2.5kV×1min"></td>
        <td><input type="text" name="maz_itampa[schema4][]" class="form-control" value="2.5kV×1min"></td>
        <td><input type="text" name="maz_itampa[schema5][]" class="form-control" value="2.5kV×1min"></td>
        <td><input type="text" name="maz_itampa[schema6][]" class="form-control" value="2.5kV×1min"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Šalinti</button></td>`;
}

function removeRow(btn) {
    let row = btn.closest("tr");
    row.parentNode.removeChild(row);
}
</script>
<input type="hidden" name="maz_itampa[isvada]" value="0,4kV kabeliai ir laidai bandymus išlaikė, izoliacija gera.">

<h5 class="mt-5 text-uppercase fw-bold">GRANDINĖS TARP ĮŽEMINIMO VARŽTŲ IR ĮŽEMINTINŲ ELEMENTŲ TIKRINIMAS</h5>
<table class="table table-bordered">
  <thead class="table-secondary text-center">
    <tr>
      <th>Eil. Nr.</th>
      <th>Įžemintinų taškų pavadinimas</th>
      <th>Matavimo taškų skaičius</th>
      <th>Grandinės varža (Ω)</th>
      <th>Būdas</th>
      <th>Būklė</th>
    </tr>
  </thead>
  <tbody class="text-center">
<?php 
if (!empty($izem)) {
    foreach ($izem as $row) {
        echo "<tr>
          <td>{$row['eil_nr']}<input type='hidden' name='izeminimo[eil_nr][]' value='{$row['eil_nr']}'></td>
          <td class='text-start'><input type='text' name='izeminimo[taskas][]' class='form-control' value='".htmlspecialchars($row['tasko_pavadinimas'])."'></td>
          <td><input type='number' name='izeminimo[tasku_skaicius][]' class='form-control' value='{$row['matavimo_tasku_skaicius']}'></td>
          <td><input type='text' name='izeminimo[varza][]' class='form-control' value='{$row['varza_ohm']}'></td>
          <td><input type='text' name='izeminimo[budas][]' class='form-control' value='".htmlspecialchars($row['budas'])."'></td>
          <td><input type='text' name='izeminimo[bukle][]' class='form-control' value='".htmlspecialchars($row['bukle'])."'></td>
        </tr>";
    }
} else {
    $izem_data = [
        ['1.1','Įžeminimo šyna PE',1],
        ['1.2','Komutacinių aparatų korpusai',1],
        ['1.3','Šynelė PE kabelių ekranų prijungimui',1],
        ['1.4','MMT 10kV skyriaus durys',2],
        ['1.5','10kV kabelio ekranas prijungtas prie PE',1],
        ['2.1','Neutralės šyna PEN',1],
        ['2.2','Šynelė PE kabelių 10kV ekranų prijungimui',1],
        ['2.3','Kilnojamų įžemiklių prijungimo varžtai',1],
        ['3.1','Įžeminimo šyna PE',1],
        ['3.2','0,4kV skydo korpusas',1],
        ['3.3','Pagrindinė įžeminimo šyna',1],
        ['3.4','Apsauginio gaubto korpusas',1],
        ['3.5','Apskaitos skydelio korpusas',1]
    ];
    foreach ($izem_data as $row) {
        echo "<tr>
          <td>{$row[0]}<input type='hidden' name='izeminimo[eil_nr][]' value='{$row[0]}'></td>
          <td class='text-start'><input type='text' name='izeminimo[taskas][]' class='form-control' value='{$row[1]}'></td>
          <td><input type='number' name='izeminimo[tasku_skaicius][]' class='form-control' value='{$row[2]}'></td>
          <td><input type='text' name='izeminimo[varza][]' class='form-control' value='0.01'></td>
          <td><input type='text' name='izeminimo[budas][]' class='form-control' value='Varžtu'></td>
          <td><input type='text' name='izeminimo[bukle][]' class='form-control' value='Gera'></td>
        </tr>";
    }
}
?>
  </tbody>
</table>

<p class="fw-bold"><em>Pastaba:</em> Matavimai atlikti varžto, skirto įžemiklio (įžeminimo kontūro) prijungimui. Įžeminimo varžos normos ribose.
 Matavimai atlikti pagal galiojančius standartus ir darbo instrukcijas.</p>

<p class="fw-bold">Išvada: Gaminys tinka eksploatacijai.</p>

<div class="d-flex gap-2 mt-3 mb-3">
    <button type="submit" class="btn btn-success">Išsaugoti visus pakeitimus</button>
    <a href="/uzsakymai.php?id=<?= htmlspecialchars($uzsakymo_id) ?>" class="btn btn-secondary">← Grįžti</a>
</div>

</form>

<div class="mt-3 p-2 border rounded d-flex align-items-center justify-content-between mb-4" style="background:#f9f9f9;">
    <div style="flex:1; font-size:14px; line-height:1.4;">
        <strong>Data:</strong> <?=$data?> | 
        <strong>Pareigos:</strong> <?=$pareigos?> 
    </div>
    <div style="display:inline-flex; align-items:center; gap:10px;">
        <span style="font-size:14px;">
            <strong>Vardas, Pavardė:</strong> <?=htmlspecialchars($vardas)?> <?=htmlspecialchars($pavarde)?>
        </span>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
