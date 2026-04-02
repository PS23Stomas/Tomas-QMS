<?php
/**
 * MT dielektrinių bandymų puslapis - prietaisai, įtampos bandymai, įžeminimo tikrinimas
 *
 * Šis puslapis apima:
 *   - Bandymų prietaisų CRUD (pridėjimas, redagavimas, šalinimas)
 *   - Vidutinės įtampos (6-24 kV) kabelių bandymo lentelę
 *   - Žemos įtampos (0,4 kV) grandinių bandymo lentelę
 *   - Įžeminimo grandinių tikrinimo lentelę
 */
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';

// Sesijos inicializavimas ir prisijungimo tikrinimas
Sesija::pradzia();
Sesija::tikrintiPrisijungima();

// Prisijungusio vartotojo duomenys
$vardas = $_SESSION['vardas'] ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';
$pareigos = '';
$vartotojo_id = $_SESSION['vartotojas_id'] ?? 0;
if ($vartotojo_id) {
    $conn_tmp = Database::getConnection();
    $stmt_par = $conn_tmp->prepare("SELECT pareigos FROM vartotojai WHERE id = ?");
    $stmt_par->execute([$vartotojo_id]);
    $par_row = $stmt_par->fetch(PDO::FETCH_ASSOC);
    if ($par_row) $pareigos = $par_row['pareigos'] ?? '';
}
if (empty($pareigos)) $pareigos = 'Kokybės inžinierius';
$data = date("Y-m-d");

$conn = Database::getConnection();

// Gaminio ID gavimas (palaikomi abu parametrų pavadinimai: gaminys_id ir gaminio_id)
$gaminys_id = isset($_REQUEST['gaminys_id']) ? (int)$_REQUEST['gaminys_id'] :
             (isset($_REQUEST['gaminio_id']) ? (int)$_REQUEST['gaminio_id'] : 0);

$gaminio_numeris   = $_REQUEST['gaminio_numeris']   ?? '';
$uzsakymo_numeris  = $_REQUEST['uzsakymo_numeris']  ?? '';
$uzsakovas         = $_REQUEST['uzsakovas']         ?? '';
$gaminio_pavadinimas = $_REQUEST['gaminio_pavadinimas'] ?? '';
$uzsakymo_id       = $_REQUEST['uzsakymo_id']       ?? '';
$grupe             = $_REQUEST['grupe']     ?? 'MT';
$issaugota         = $_REQUEST['issaugota'] ?? '';
$istrinta          = $_REQUEST['istrinta']  ?? '';
$pdf_sukurtas      = $_REQUEST['pdf_sukurtas'] ?? '';
$pdf_klaida        = $_REQUEST['pdf_klaida'] ?? '';

if ($gaminys_id <= 0) die("Klaida: nėra gaminio ID");

$stmt = $conn->prepare("SELECT g.id, g.protokolo_nr, g.mt_dielektriniu_failas, g.pavadinimas AS individualus_pav, g.gaminio_numeris AS gam_nr, g.dielektriniai_issaugoti, gt.gaminio_tipas AS gam_pav FROM gaminiai g LEFT JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id WHERE g.id=?");
$stmt->execute([$gaminys_id]);
$gaminys = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$gaminys) die("Klaida: gaminys nerastas");
$protokolo_numeris = $gaminys['protokolo_nr'] ?? '';
$turi_dielektriniu_pdf = !empty($gaminys['mt_dielektriniu_failas']);
$individualus_pavadinimas = $gaminys['individualus_pav'] ?? '';
$jau_issaugota = !empty($gaminys['dielektriniai_issaugoti']);

if (empty($gaminio_pavadinimas) && !empty($gaminys['gam_pav'])) {
    $gaminio_pavadinimas = $gaminys['gam_pav'];
}

function redirectParams($gaminys_id, $gaminio_numeris, $uzsakymo_numeris, $uzsakovas, $gaminio_pavadinimas, $uzsakymo_id, $grupe, $extra = []) {
    return http_build_query(array_merge([
        'gaminys_id' => $gaminys_id,
        'gaminio_numeris' => $gaminio_numeris,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'grupe' => $grupe,
        't' => time(),
    ], $extra));
}

// === Prietaisų CRUD operacijos ===
// Naujo prietaiso pridėjimas
if (isset($_POST['prideti'])) {
    $stmt = $conn->prepare("INSERT INTO bandymai_prietaisai (gaminys_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$gaminys_id, $_POST['prietaiso_tipas'], $_POST['prietaiso_nr'], $_POST['patikra_data'], $_POST['galioja_iki'], $_POST['sertifikato_nr']]);
    header("Location: mt_dielektriniai.php?" . redirectParams($gaminys_id, $gaminio_numeris, $uzsakymo_numeris, $uzsakovas, $gaminio_pavadinimas, $uzsakymo_id, $grupe, ['issaugota' => 'taip']));
    exit;
}

// Esamo prietaiso redagavimas
if (isset($_POST['redaguoti'])) {
    $stmt = $conn->prepare("UPDATE bandymai_prietaisai SET prietaiso_tipas=?, prietaiso_nr=?, patikra_data=?, galioja_iki=?, sertifikato_nr=? WHERE id=? AND gaminys_id=?");
    $stmt->execute([$_POST['prietaiso_tipas'], $_POST['prietaiso_nr'], $_POST['patikra_data'], $_POST['galioja_iki'], $_POST['sertifikato_nr'], $_POST['id'], $gaminys_id]);
    header("Location: mt_dielektriniai.php?" . redirectParams($gaminys_id, $gaminio_numeris, $uzsakymo_numeris, $uzsakovas, $gaminio_pavadinimas, $uzsakymo_id, $grupe, ['issaugota' => 'taip']));
    exit;
}

// Prietaiso šalinimas pagal ID
if (isset($_GET['salinti'])) {
    $id = (int)$_GET['salinti'];
    $gaminio_numeris = $_GET['gaminio_numeris'] ?? $gaminio_numeris;
    $uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? $uzsakymo_numeris;
    $uzsakovas = $_GET['uzsakovas'] ?? $uzsakovas;
    $gaminio_pavadinimas = $_GET['gaminio_pavadinimas'] ?? $gaminio_pavadinimas;

    $stmt = $conn->prepare("DELETE FROM bandymai_prietaisai WHERE id=? AND gaminys_id=?");
    $stmt->execute([$id, $gaminys_id]);
    header("Location: mt_dielektriniai.php?" . redirectParams($gaminys_id, $gaminio_numeris, $uzsakymo_numeris, $uzsakovas, $gaminio_pavadinimas, $uzsakymo_id, $grupe, ['issaugota' => 'taip']));
    exit;
}


// Automatinis protokolo numerio priskyrimas (d0009, d0010, ...)
if (empty($protokolo_numeris)) {
    $max_st = $conn->query("SELECT protokolo_nr FROM gaminiai WHERE protokolo_nr ~ '^d[0-9]+$' ORDER BY CAST(SUBSTRING(protokolo_nr FROM 2) AS INTEGER) DESC LIMIT 1");
    $max_row = $max_st->fetch(PDO::FETCH_ASSOC);
    if ($max_row) {
        $max_num = (int)substr($max_row['protokolo_nr'], 1);
    } else {
        $max_num = 8;
    }
    $naujas_nr = '';
    do {
        $max_num++;
        $naujas_nr = 'd' . str_pad($max_num, 4, '0', STR_PAD_LEFT);
        $exists = $conn->prepare("SELECT 1 FROM gaminiai WHERE protokolo_nr = ?");
        $exists->execute([$naujas_nr]);
    } while ($exists->fetch());
    $conn->prepare("UPDATE gaminiai SET protokolo_nr = ? WHERE id = ?")->execute([$naujas_nr, $gaminys_id]);
    $protokolo_numeris = $naujas_nr;
}

// Lentelės trynimas vykdomas per atskirą failą /MT/istrinti_dielektriniu_lentele.php

// Visų galimų prietaisų sąrašo gavimas iš prietaisų lentelės
$db_prietaisai = $conn->query("SELECT id, pavadinimas, modelis, serijos_nr, kalibravimo_data, galiojimo_pabaiga, kalibracijos_sertifikato_nr FROM prietaisai ORDER BY pavadinimas")->fetchAll(PDO::FETCH_ASSOC);

// Tikrinama ar gaminiui jau priskirti bandymų prietaisai
$stmt = $conn->prepare("SELECT COUNT(*) FROM bandymai_prietaisai WHERE gaminys_id=?");
$stmt->execute([$gaminys_id]);
$prietaisu_sk = $stmt->fetchColumn();

// Numatytųjų prietaisų automatinis įterpimas tik pirmą kartą (kai dar nebuvo išsaugota ir nebuvo ką tik ištrinta)
if ($prietaisu_sk == 0 && !$jau_issaugota && $istrinta !== 'prietaisai' && $istrinta !== 'visi') {
    $default_modeliai = ['AID-70M', 'EUROTEST 61557', 'MI2077'];
    $sql = "INSERT INTO bandymai_prietaisai (gaminys_id, prietaiso_tipas, prietaiso_nr, patikra_data, galioja_iki, sertifikato_nr) VALUES (?, ?, ?, ?, ?, ?)";
    $insert = $conn->prepare($sql);
    foreach ($db_prietaisai as $dp) {
        if (in_array(strtoupper($dp['modelis']), $default_modeliai)) {
            $tipas = $dp['modelis'];
            $insert->execute([$gaminys_id, $tipas, $dp['serijos_nr'], $dp['kalibravimo_data'], $dp['galiojimo_pabaiga'], $dp['kalibracijos_sertifikato_nr']]);
        }
    }
}

// Priskirtų prietaisų sąrašo gavimas atvaizdavimui
$stmt = $conn->prepare("SELECT * FROM bandymai_prietaisai WHERE gaminys_id=? ORDER BY prietaiso_tipas");
$stmt->execute([$gaminys_id]);
$prietaisai = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vidutinės įtampos bandymų duomenys iš dielektriniai_bandymai lentelės (tipas = vidutines_itampos)
$stmt = $conn->prepare("SELECT * FROM dielektriniai_bandymai WHERE gaminys_id=? AND tipas='vidutines_itampos' ORDER BY eiles_nr");
$stmt->execute([$gaminys_id]);
$vid_itampa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Žemos įtampos (0,4 kV) dielektrinių bandymų duomenų gavimas
$stmt = $conn->prepare("SELECT * FROM dielektriniai_bandymai WHERE gaminys_id=? AND (tipas='mazos_itampos' OR tipas IS NULL) ORDER BY eiles_nr");
$stmt->execute([$gaminys_id]);
$maz_itampa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Įžeminimo tikrinimo duomenų gavimas
$stmt = $conn->prepare("SELECT * FROM izeminimo_tikrinimas WHERE gaminys_id=? ORDER BY eil_nr");
$stmt->execute([$gaminys_id]);
$izem = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#1e293b">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>MT Dielektriniai bandymai</title>
<link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
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
.section-header { display: flex; align-items: center; justify-content: space-between; margin-top: 2rem; margin-bottom: 0.5rem; }
.section-header h5 { margin: 0; }
.btn-delete-table { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 3px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; display: inline-block; text-decoration: none; }
.btn-delete-table:hover { background: #fecaca; color: #dc2626; text-decoration: none; }
</style>
</head>
<body>
<div class="container mt-4">

<?php
function deleteTableBtn($lentele, $label = 'Ištrinti') {
    global $gaminys_id, $gaminio_numeris, $uzsakymo_numeris, $uzsakovas, $gaminio_pavadinimas, $uzsakymo_id, $grupe;
    $params = http_build_query([
        'lentele' => $lentele,
        'gaminys_id' => $gaminys_id,
        'gaminio_numeris' => $gaminio_numeris,
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas' => $uzsakovas,
        'gaminio_pavadinimas' => $gaminio_pavadinimas,
        'uzsakymo_id' => $uzsakymo_id,
        'grupe' => $grupe,
    ]);
    $url = '/MT/istrinti_dielektriniu_lentele.php?' . $params;
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" class="btn-delete-table" onclick="return confirm(\'Ar tikrai norite ištrinti: ' . htmlspecialchars($label, ENT_QUOTES) . '?\')">🗑 ' . htmlspecialchars($label) . '</a>';
}
?>
<h4 class="mb-2 text-uppercase fw-bold">ATLIKTŲ BANDYMŲ PROTOKOLAS NR. <?=htmlspecialchars($protokolo_numeris)?></h4>

<div style="background:linear-gradient(135deg,#f0f4ff,#e8edf5);border:1px solid #c7d2e0;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;font-size:14px;">
    <div><span style="color:#64748b;font-weight:500;">Gaminio numeris:</span> <strong><?=htmlspecialchars($gaminys['gam_nr'] ?: $gaminio_numeris)?></strong></div>
    <div><span style="color:#64748b;font-weight:500;">Užsakymo Nr.:</span> <strong><?=htmlspecialchars($uzsakymo_numeris)?></strong></div>
    <div><span style="color:#64748b;font-weight:500;">Gaminio pavadinimas:</span> <strong><?=htmlspecialchars($individualus_pavadinimas ?: $gaminio_pavadinimas)?></strong></div>
    <div><span style="color:#64748b;font-weight:500;">Užsakovas:</span> <strong><?=htmlspecialchars($uzsakovas)?></strong></div>
    <?php if ($grupe !== 'MT'): ?>
    <div><span style="color:#64748b;font-weight:500;">Grupė:</span> <strong><?=htmlspecialchars($grupe)?></strong></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($istrinta): ?>
<div class="alert alert-success" style="padding:10px 14px;font-size:14px;">Lentelės duomenys sėkmingai ištrinti.</div>
<?php endif; ?>
<?php if ($issaugota==='taip'): ?>
<div class="alert alert-success">Duomenys sėkmingai išsaugoti.</div>
<?php endif; ?>
<?php if ($pdf_sukurtas==='taip'): ?>
<div class="alert alert-success">PDF dokumentas sėkmingai sugeneruotas ir išsaugotas.</div>
<?php endif; ?>
<?php if (!empty($pdf_klaida)): ?>
<div class="alert alert-danger">PDF generavimo klaida: <?=htmlspecialchars($pdf_klaida)?></div>
<?php endif; ?>

<form action="mt_dielektriniai.php" method="post">
    <input type="hidden" name="gaminys_id" value="<?=$gaminys_id?>">
    <input type="hidden" name="gaminio_numeris" value="<?=htmlspecialchars($gaminio_numeris)?>">
    <input type="hidden" name="uzsakymo_numeris" value="<?=htmlspecialchars($uzsakymo_numeris)?>">
    <input type="hidden" name="uzsakovas" value="<?=htmlspecialchars($uzsakovas)?>">
    <input type="hidden" name="gaminio_pavadinimas" value="<?=htmlspecialchars($gaminio_pavadinimas)?>">
    <input type="hidden" name="uzsakymo_id" value="<?=htmlspecialchars($uzsakymo_id)?>">
    <input type="hidden" name="grupe" value="<?=htmlspecialchars($grupe)?>">
    <input type="hidden" name="id" id="prietaiso_id">

    <h4 class="mt-4">Matavimai atlikti prietaisais:</h4>
    
    <div class="row g-2 mb-2">
        <div class="col">
            <select id="prietaiso_select" class="form-control" onchange="uzpildytiPrietaisa(this.value)">
                <option value="">-- Pasirinkti prietaisą --</option>
                <?php foreach ($db_prietaisai as $dp): ?>
                <option value="<?=htmlspecialchars(json_encode($dp))?>"><?=htmlspecialchars($dp['pavadinimas'] . ' / ' . $dp['modelis'])?></option>
                <?php endforeach; ?>
                <option value="__kita__">Kita (įvesti rankiniu būdu)...</option>
            </select>
            <input type="text" id="prietaiso_tipas" name="prietaiso_tipas" class="form-control mt-1" placeholder="Tipas" required style="display:none;">
        </div>
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
        <a href="?salinti=<?=$p['id']?>&gaminys_id=<?=$gaminys_id?>&gaminio_numeris=<?=urlencode($gaminio_numeris)?>&uzsakymo_numeris=<?=urlencode($uzsakymo_numeris)?>&uzsakovas=<?=urlencode($uzsakovas)?>&gaminio_pavadinimas=<?=urlencode($gaminio_pavadinimas)?>&uzsakymo_id=<?=urlencode($uzsakymo_id)?>&grupe=<?=urlencode($grupe)?>"
           class="btn btn-sm btn-danger" onclick="return confirm('Šalinti?')">Trint.</a>
    </td>
</tr>
<?php endforeach; else: ?><tr><td colspan="7">Įrašų nėra</td></tr><?php endif; ?>
</tbody>
</table>

<script>
function uzpildytiPrietaisa(val) {
    var tipasInput = document.getElementById('prietaiso_tipas');
    var selectEl = document.getElementById('prietaiso_select');
    if (val === '__kita__') {
        tipasInput.style.display = 'block';
        tipasInput.value = '';
        tipasInput.focus();
        document.getElementById('prietaiso_nr').value = '';
        document.getElementById('patikra_data').value = '';
        document.getElementById('galioja_iki').value = '';
        document.getElementById('sertifikato_nr').value = '';
        return;
    }
    if (!val) {
        tipasInput.style.display = 'none';
        tipasInput.value = '';
        return;
    }
    try {
        var p = JSON.parse(val);
        tipasInput.value = p.modelis || '';
        tipasInput.style.display = 'none';
        document.getElementById('prietaiso_nr').value = p.serijos_nr || '';
        document.getElementById('patikra_data').value = p.kalibravimo_data || '';
        document.getElementById('galioja_iki').value = p.galiojimo_pabaiga || '';
        document.getElementById('sertifikato_nr').value = p.kalibracijos_sertifikato_nr || '';
    } catch(e) {}
}

function redaguotiPrietaisa(id, tipas, nr, patikra, galioja, sert) {
    document.getElementById('prietaiso_id').value = id;
    document.getElementById('prietaiso_tipas').value = tipas;
    document.getElementById('prietaiso_tipas').style.display = 'block';
    document.getElementById('prietaiso_select').value = '';
    document.getElementById('prietaiso_nr').value = nr;
    document.getElementById('patikra_data').value = patikra;
    document.getElementById('galioja_iki').value = galioja;
    document.getElementById('sertifikato_nr').value = sert;
    window.scrollTo({top: 0, behavior: 'smooth'});
}

document.querySelector('form').addEventListener('submit', function(e) {
    var tipasInput = document.getElementById('prietaiso_tipas');
    var selectEl = document.getElementById('prietaiso_select');
    if (tipasInput.style.display === 'none' && selectEl.value && selectEl.value !== '__kita__') {
        try {
            var p = JSON.parse(selectEl.value);
            tipasInput.value = p.modelis || '';
        } catch(ex) {}
    }
    if (!tipasInput.value.trim()) {
        e.preventDefault();
        alert('Pasirinkite arba įveskite prietaiso tipą');
    }
});
</script>

<?php if ($grupe === 'MT'):
    $gaminio_id = $gaminys_id;
    $st_saug = $conn->prepare("SELECT COUNT(*) FROM saugikliu_ideklai WHERE gaminio_id=?");
    $st_saug->execute([$gaminio_id]);
    $saugikliu_cnt = (int)$st_saug->fetchColumn();
    $saugikliai_tusti = ($saugikliu_cnt === 0) && ($jau_issaugota || $istrinta === 'saugikliai' || $istrinta === 'visi');
?>
<?php if (!$saugikliai_tusti): ?>
<!-- Saugikliu ideklu blokas (3.5 / 3.6) - tik MT moduliui -->
<div class="section-header" style="margin-top:2rem;">
    <h5 class="text-uppercase fw-bold" style="margin:0;">SAUGIKLIU IDEKLAI</h5>
    <?= deleteTableBtn('saugikliai', 'Ištrinti') ?>
</div>
<table class="table table-bordered">
<tbody>
<?php
include __DIR__ . '/mt_saugikliai_blokas.php';
?>
</tbody>
</table>
<?php endif; ?>
<?php endif; ?>

<form action="/MT/issaugoti_mt_dielektriniai.php" method="post">
   <input type="hidden" name="gaminys_id" value="<?=$gaminys_id?>">
   <input type="hidden" name="gaminio_numeris" value="<?=htmlspecialchars($gaminio_numeris)?>">
   <input type="hidden" name="uzsakymo_numeris" value="<?=htmlspecialchars($uzsakymo_numeris)?>">
   <input type="hidden" name="uzsakovas" value="<?=htmlspecialchars($uzsakovas)?>">
   <input type="hidden" name="gaminio_pavadinimas" value="<?=htmlspecialchars($gaminio_pavadinimas)?>">
   <input type="hidden" name="uzsakymo_id" value="<?=htmlspecialchars($uzsakymo_id)?>">
   <input type="hidden" name="grupe" value="<?=htmlspecialchars($grupe)?>">

<?php
$vid_itampa_tusti = empty($vid_itampa) && ($jau_issaugota || $istrinta === 'vidutines_itampos' || $istrinta === 'visi');
$maz_itampa_tusti = ((!isset($maz_itampa) || !is_array($maz_itampa) || count($maz_itampa) === 0)) && ($jau_issaugota || $istrinta === 'mazos_itampos' || $istrinta === 'visi');
$izem_tusti = empty($izem) && ($jau_issaugota || $istrinta === 'izeminimas' || $istrinta === 'visi');
?>

<?php if (!$vid_itampa_tusti): ?>
<div class="section-header">
    <h5 class="text-uppercase fw-bold">VIDUTINĖS ĮTAMPOS (6–24 kV) KABELIŲ BANDYMAS</h5>
    <?= deleteTableBtn('vidutines_itampos', 'Ištrinti') ?>
</div>
<table class="table table-bordered">
<thead class="table-secondary">
<tr><th>Eil. Nr.</th><th>Bandymo elementų aprašymas</th><th>Un (kV)</th><th>Bandymo schema</th><th>Band. įtampa (kV)</th><th>Trukmė (min)</th></tr>
</thead>
<tbody>
<?php
if (empty($vid_itampa) && !$jau_issaugota && $istrinta !== 'vidutines_itampos' && $istrinta !== 'visi') {
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
            <td><input type='text' name='vid_itampa[band_itampa][]' class='form-control' value='".htmlspecialchars($row['bandymo_itampa_kv'] ?? '')."'></td>
            <td><input type='text' name='vid_itampa[trukme][]' class='form-control' value='".htmlspecialchars($row['bandymo_trukme'] ?? '')."'></td>
        </tr>";
        $i++;
    }
}
?>
</tbody>
</table>
<input type="hidden" name="vid_itampa[isvada]" value="10kV kabeliai bandymus išlaikė, izoliacija gera.">
<?php endif; ?>

<?php if (!$maz_itampa_tusti): ?>
<div class="section-header">
    <h5 class="text-uppercase fw-bold">0,4kV GRANDINIŲ BANDYMAS PAAUKŠTINTA ĮTAMPA</h5>
    <?= deleteTableBtn('mazos_itampos', 'Ištrinti') ?>
</div>
<table class="table table-bordered" id="mazItampaTable">
<thead class="table-secondary">
<tr><th>Eil. Nr.</th><th>El. grandinių aprašymas</th><th>Grandinių įtampa</th><th>L1-PE</th><th>L2-PE</th><th>L3-PE</th><th>L1-L2</th><th>L2-L3</th><th>L1-L3</th><th>Veiksmas</th></tr>
</thead>
<tbody>
<?php
// Numatytosios žemos įtampos bandymų eilutės (jei dar nėra duomenų)
$default_maz = [
    ['0,4kV skirstomojo įrenginio grandinės(šynos)','400 V'], 
    ['Kontrolinės elektros apskaitos įtampos grandinės','400 V'], 
    ['Komercinės elektros apskaitos įtampos grandinės','400 V'],
    ['T-1 ventiliacijos grandinė','230 V'],
    ['T-2 ventiliacijos grandinė','230 V'],
    ['SRS kištukinio lizdo maitinimas','230 V']
];

if (($jau_issaugota || $istrinta === 'mazos_itampos' || $istrinta === 'visi') && (!isset($maz_itampa) || !is_array($maz_itampa) || count($maz_itampa) === 0)) {
    $eilutes = [];
} elseif (!isset($maz_itampa) || !is_array($maz_itampa) || count($maz_itampa) === 0) {
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
<input type="hidden" name="maz_itampa[isvada]" value="0,4kV įranga bandymus išlaikė, izoliacija gera.">
<?php endif; ?>

<?php if (!$izem_tusti): ?>
<div class="section-header">
    <h5 class="text-uppercase fw-bold">GRANDINĖS TARP ĮŽEMINIMO VARŽTŲ IR ĮŽEMINTINŲ ELEMENTŲ TIKRINIMAS</h5>
    <?= deleteTableBtn('izeminimas', 'Ištrinti') ?>
</div>
<table class="table table-bordered">
  <thead class="table-secondary text-center">
    <tr>
      <th>Eil. Nr.</th>
      <th>Įžemintinų taškų pavadinimas</th>
      <th>Matavimo taškų skaičius</th>
      <th>Grandinės varža (Ω)</th>
      <th>Būdas</th>
      <th>Būklė</th>
      <th>Veiksmas</th>
    </tr>
  </thead>
  <tbody class="text-center" id="izeminimoTableBody">
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
          <td><button type='button' class='btn btn-danger btn-sm' onclick='removeIzemRow(this)'>Šalinti</button></td>
        </tr>";
    }
} elseif ($jau_issaugota || $istrinta === 'izeminimas' || $istrinta === 'visi') {
    // Jau buvo išsaugota arba ką tik ištrinta — nerodyti numatytųjų duomenų
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
          <td><button type='button' class='btn btn-danger btn-sm' onclick='removeIzemRow(this)'>Šalinti</button></td>
        </tr>";
    }
}
?>
  </tbody>
</table>
<button type="button" class="btn btn-primary btn-sm" onclick="addIzemRow()">Pridėti eilutę</button>

<script>
function renumberIzemRows() {
    let tbody = document.getElementById("izeminimoTableBody");
    for (let i = 0; i < tbody.rows.length; i++) {
        let nr = i + 1;
        let cell = tbody.rows[i].cells[0];
        let hidden = cell.querySelector('input[type="hidden"]');
        if (hidden) hidden.value = nr;
        cell.childNodes[0].textContent = nr;
    }
}

function addIzemRow() {
    let tbody = document.getElementById("izeminimoTableBody");
    let rowCount = tbody.rows.length + 1;
    let row = tbody.insertRow();
    row.innerHTML = `<td>${rowCount}<input type="hidden" name="izeminimo[eil_nr][]" value="${rowCount}"></td>
        <td class="text-start"><input type="text" name="izeminimo[taskas][]" class="form-control" value=""></td>
        <td><input type="number" name="izeminimo[tasku_skaicius][]" class="form-control" value="1"></td>
        <td><input type="text" name="izeminimo[varza][]" class="form-control" value="0.01"></td>
        <td><input type="text" name="izeminimo[budas][]" class="form-control" value="Varžtu"></td>
        <td><input type="text" name="izeminimo[bukle][]" class="form-control" value="Gera"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeIzemRow(this)">Šalinti</button></td>`;
}

function removeIzemRow(btn) {
    let row = btn.closest("tr");
    row.parentNode.removeChild(row);
    renumberIzemRows();
}
</script>
<?php endif; ?>

<p class="fw-bold"><em>Pastaba:</em> Matavimai atlikti varžto, skirto įžemiklio (įžeminimo kontūro) prijungimui. Įžeminimo varžos normos ribose.
 Matavimai atlikti pagal galiojančius standartus ir darbo instrukcijas.</p>

<p class="fw-bold">Išvada: Gaminys tinka eksploatacijai.</p>

<div class="d-flex gap-2 mt-3 mb-3 align-items-center">
    <button type="submit" class="btn btn-success">Išsaugoti visus pakeitimus</button>
    <a href="/uzsakymai.php?id=<?= htmlspecialchars($uzsakymo_id) ?>&grupe=<?= urlencode($grupe) ?>" class="btn btn-secondary">← Grįžti</a>
    <div style="margin-left:auto;">
        <?= deleteTableBtn('visi', 'Ištrinti visus duomenis') ?>
    </div>
</div>

</form>


<div class="d-flex gap-2 mb-3 align-items-center">
    <form action="/MT/generuoti_mt_dielektriniu_pdf.php" method="post" style="display:inline;">
        <input type="hidden" name="gaminio_id" value="<?=$gaminys_id?>">
        <input type="hidden" name="gaminio_numeris" value="<?=htmlspecialchars($gaminio_numeris)?>">
        <input type="hidden" name="uzsakymo_numeris" value="<?=htmlspecialchars($uzsakymo_numeris)?>">
        <input type="hidden" name="uzsakovas" value="<?=htmlspecialchars($uzsakovas)?>">
        <input type="hidden" name="gaminio_pavadinimas" value="<?=htmlspecialchars($gaminio_pavadinimas)?>">
        <input type="hidden" name="uzsakymo_id" value="<?=htmlspecialchars($uzsakymo_id)?>">
        <input type="hidden" name="grupe" value="<?=htmlspecialchars($grupe)?>">
        <button type="submit" class="btn btn-primary" data-testid="button-generuoti-dielektriniu-pdf">Generuoti PDF</button>
    </form>
    <?php if ($turi_dielektriniu_pdf): ?>
    <a href="/MT/mt_dielektriniu_pdf.php?gaminio_id=<?=$gaminys_id?>" target="_blank" class="btn btn-outline-primary" data-testid="button-perziureti-dielektriniu-pdf">Peržiūrėti PDF</a>
    <a href="/MT/mt_dielektriniu_pdf.php?gaminio_id=<?=$gaminys_id?>&atsisiusti" class="btn btn-outline-secondary" data-testid="button-atsisiusti-dielektriniu-pdf">Atsisiųsti PDF</a>
    <?php endif; ?>
</div>

<div class="mt-3 p-2 border rounded d-flex align-items-center justify-content-between mb-4" style="background:#f9f9f9;">
    <div style="flex:1; font-size:14px; line-height:1.4;">
        <strong>Data:</strong> <?=$data?> | 
        <strong>Pareigos:</strong> <?=htmlspecialchars($pareigos)?> 
    </div>
    <div style="display:inline-flex; align-items:center; gap:10px;">
        <span style="font-size:14px;">
            <strong>Vardas, Pavardė:</strong> <?=htmlspecialchars($vardas)?> <?=htmlspecialchars($pavarde)?>
        </span>
        <?php if (file_exists(__DIR__ . '/../img/parasas_elga.jpg')): ?>
        <img src="/img/parasas_elga.jpg" alt="Parašas" style="max-height:50px;">
        <?php endif; ?>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
