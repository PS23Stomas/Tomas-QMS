<?php
require_once __DIR__ . '/../klases/Database.php';
require_once __DIR__ . '/../klases/Sesija.php';
require_once __DIR__ . '/../klases/Komponentas.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

$vardas = $_SESSION['vardas'] ?? '';
$pavarde = $_SESSION['pavarde'] ?? '';

$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas = $_GET['uzsakovas'] ?? '';
$gaminio_id = $_GET['gaminio_id'] ?? '';
$uzsakymo_id = $_GET['uzsakymo_id'] ?? '';

$stmt = $conn->prepare("SELECT gt.gaminio_tipas FROM gaminiai g JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE g.id = :id");
$stmt->execute([':id' => $gaminio_id]);
$gaminio_pavadinimas = $stmt->fetchColumn() ?: '';

$kodai_per_eile = [];
$stmt = $conn->prepare("SELECT eiles_numeris, gamintojo_kodas FROM mt_komponentai WHERE gamintojo_kodas IS NOT NULL AND gamintojo_kodas != ''");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nr = (int)$row['eiles_numeris'];
    $val = $row['gamintojo_kodas'];
    if (!isset($kodai_per_eile[$nr])) $kodai_per_eile[$nr] = [];
    if (!in_array($val, $kodai_per_eile[$nr])) $kodai_per_eile[$nr][] = $val;
}

$visi_gamintojai = [];
$stmt2 = $conn->prepare("SELECT DISTINCT gamintojas FROM mt_komponentai WHERE gamintojas IS NOT NULL AND gamintojas != '' ORDER BY gamintojas ASC");
$stmt2->execute();
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $visi_gamintojai[] = $row['gamintojas'];
}

$stmt = $conn->prepare("SELECT * FROM mt_komponentai WHERE gaminio_id = :gaminio_id ORDER BY eiles_numeris ASC");
$stmt->execute([':gaminio_id' => $gaminio_id]);
$rez = $stmt->fetchAll(PDO::FETCH_ASSOC);

$irasytos_eilutes = [];
$parinktos_eilutes = [];
foreach ($rez as $eil) {
    $nr = (int)$eil['eiles_numeris'];
    $irasytos_eilutes[$nr] = $eil;
    if ((int)$eil['parinkta_projektui'] === 1) {
        $parinktos_eilutes[$nr] = true;
    }
}

$default = [
    ['gTr 400V 630kVA', 3, 'Saugiklis gTr Įvadas ', 'Jean Muller'],
    ['SL3-3x3/910+/2G/HA/V0/black', 2, 'Vertikalus kirtiklis Įvadas ', 'Jean Muller'],
    ['TM3/1250A/ISM N8387610', 3, 'Trumpiklis sekcijinis ', 'Jean Muller'],
    ['SL3-3SR/3x3/910', 1, 'Sekcijinis kirtiklis ', 'Jean Muller'],
    ['WT3 NH3 gL/gG 315A 500V', 9, 'Tirptukas linijos', 'ETI'],
    ['SL3-3x3/3A/V0 ', 3, 'Vertikalus kirtiklis linijos ', 'Jean Muller'],
    ['SL3-3x3/SL/910/V0', 6, 'Sekcijinis kirtiklis ', 'Jean Muller'],
    ['W/RV/10E6I4T ', 2, 'Elektros apskaitos gnybtas', 'Weidmuller'],
    ['TAC051 400/5 A 0,5S', 3, 'Srovės transformatorius (komercine)', 'UTU'],
    ['TAC051 1000/5 A 0,5S', 3, 'Srovės transformatorius (kontroline)', 'UTU'],
    ['8DJH TLSLT', 1, '10kV skirstomasis įrenginys ', 'SIEMENS'],
    ['VVT-D 80A', 3, '12kV  tirptukas', 'ETI'],
    ['CTSKSA 12kV 10kA 341873', 1, '10kV ribotuvas ', 'Cellpack'],
    ['CTS/630A/24kV', 1, 'C tipo kištukinę mova 24kV', 'Cellpack'],
    ['HR', 1, '10kV Įtampos indikatorius', 'siemens'],
    ['MF-L 200-1000A', 2, 'Srovės indikatorius', 'Germany'],
    ['H07V-K 300mm2', 36, '0,4kV kabelis', 'Rohs'],
    ['N2XSY 1x35/16mm2', 26, '10kV kabelis', 'Nexans']
];

$komponentai = [];
for ($i = 1; $i <= 18; $i++) {
    if (isset($irasytos_eilutes[$i])) {
        $eil = $irasytos_eilutes[$i];
        $komponentai[] = new Komponentas([
            'id' => $i,
            'kodas' => $eil['gamintojo_kodas'],
            'kiekis' => $eil['kiekis'],
            'aprasymas' => $eil['aprasymas'],
            'gamintojas' => $eil['gamintojas'],
            'parinkta_projektui' => isset($parinktos_eilutes[$i]) && $parinktos_eilutes[$i],
            'irasyta' => true
        ], $kodai_per_eile[$i] ?? [], $visi_gamintojai);
    } else {
        [$kodas, $kiekis, $aprasymas, $gamintojas] = $default[$i - 1];
        $komponentai[] = new Komponentas([
            'id' => $i,
            'kodas' => $kodas,
            'kiekis' => $kiekis,
            'aprasymas' => $aprasymas,
            'gamintojas' => $gamintojas,
            'parinkta_projektui' => false,
            'irasyta' => false
        ], $kodai_per_eile[$i] ?? [], $visi_gamintojai);
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <meta charset="UTF-8">
    <title>MT sumontuoti komponentai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <?php if (!empty($vardas) && !empty($pavarde)): ?>
        <div class="alert alert-success">Prisijungęs: <strong><?= htmlspecialchars($vardas . ' ' . $pavarde) ?></strong></div>
    <?php endif; ?>

    <h4>Užsakymo numeris: <?= htmlspecialchars($uzsakymo_numeris) ?></h4>
    <h5>Užsakovas: <?= htmlspecialchars($uzsakovas) ?></h5>
    <h5>Gaminio pavadinimas: <?= htmlspecialchars($gaminio_pavadinimas) ?></h5>

    <?php if (isset($_GET['issaugota']) && $_GET['issaugota'] === 'taip'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Komponentai sėkmingai išsaugoti.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Uždaryti"></button>
    </div>
    <?php endif; ?>

    <form method="post" action="/MT/issaugoti_mt_komponentus.php">
        <input type="hidden" name="gaminio_id" value="<?= htmlspecialchars($gaminio_id) ?>">
        <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
        <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
        <input type="hidden" name="uzsakymo_id" value="<?= htmlspecialchars($uzsakymo_id) ?>">

        <table class="table table-bordered" style="table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 45px;">
                <col style="width: 28%;">
                <col style="width: 70px;">
                <col style="width: 22%;">
                <col style="width: 20%;">
                <col style="width: 42px;">
            </colgroup>
            <thead style="background-color: #0f766e; color: white;">
                <tr>
                    <th style="padding: 8px 6px; font-size: 13px;">Nr.</th>
                    <th style="padding: 8px 6px; font-size: 13px;">Gamintojo kodas</th>
                    <th style="padding: 8px 6px; font-size: 13px;">Kiekis</th>
                    <th style="padding: 8px 6px; font-size: 13px;">Aprašymas</th>
                    <th style="padding: 8px 6px; font-size: 13px;">Gamintojas</th>
                    <th style="padding: 8px 4px;"></th>
                </tr>
            </thead>
            <tbody id="komponentai_tbody">
                <?php foreach ($komponentai as $komp) echo $komp->render(); ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-info" onclick="pridetiEilute()">Pridėti eilutę</button>
            <button type="submit" class="btn btn-success">Išsaugoti</button>
        </div>
    </form>

    <a href="/uzsakymai.php?id=<?= htmlspecialchars($uzsakymo_id) ?>" class="btn btn-dark mt-3">← Grįžti</a>
</div>

<script>
function pridetiEilute() {
    const tbody = document.getElementById('komponentai_tbody');
    const index = tbody.children.length + 1;
    const naujaEilute = `
        <tr>
            <td style='padding: 6px 4px; vertical-align: top; text-align: center; font-weight: 600; font-size: 13px;'>
                ${index}
                <input type='hidden' name='eile_id[]' value='${index}'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top;'>
                <input type='text' class='form-control form-control-sm' name='kodas[]' placeholder='Gamintojo kodas' style='font-size: 12px;'>
                <input type='hidden' name='kodas_naujas[]' value=''>
            </td>
            <td style='padding: 5px 4px; vertical-align: top;'>
                <input type='number' class='form-control form-control-sm' name='kiekis[]' style='font-size: 12px;'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top;'>
                <input type='text' class='form-control form-control-sm' name='aprasymas[]' style='font-size: 12px;'>
            </td>
            <td style='padding: 5px 4px; vertical-align: top;'>
                <input type='text' class='form-control form-control-sm' name='gamintojas[]' placeholder='Gamintojas' style='font-size: 12px;'>
                <input type='hidden' name='gamintojas_naujas[]' value=''>
            </td>
            <td style='padding: 5px 2px; vertical-align: middle; text-align: center;'>
                <button type='submit' name='saugoti[]' value='${index}' class='btn btn-outline-secondary btn-sm' title='Išsaugoti eilutę' style='padding: 3px 6px;'>
                    <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z'/><polyline points='17 21 17 13 7 13 7 21'/><polyline points='7 3 7 8 15 8'/></svg>
                </button>
            </td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', naujaEilute);
}
</script>
<script>
setTimeout(() => {
    const alert = document.querySelector('.alert-dismissible');
    if (alert) alert.classList.remove('show');
}, 4000);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
