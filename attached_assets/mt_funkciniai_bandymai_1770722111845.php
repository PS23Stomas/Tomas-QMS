<?php
/**
 * MT funkcinių bandymų pildymo forma
 * 
 * Šis failas atsakingas už MT (mažos transformatorinės) gaminio atliktų darbų
 * pildymo formos atvaizdavimą. Leidžia darbuotojams įvesti ir redaguoti
 * bandymų rezultatus, defektus ir išvadas kiekvienam reikalavimui.
 */

require_once 'klases/Database.php';
require_once 'klases/Gamys1.php';
require_once 'klases/Sesija.php';

/**
 * Sesijos inicijavimas ir prisijungimo tikrinimas
 * Jei vartotojas neprisijungęs - nukreipia į prisijungimo puslapį
 */
Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$vardas = htmlspecialchars($_SESSION['vardas']);
$pavarde = htmlspecialchars($_SESSION['pavarde']);
$pilnas_vardas = $vardas . ' ' . $pavarde;

/**
 * MT gaminio tikrinimo reikalavimų sąrašas
 * Kiekvienas punktas atitinka konkretų darbo etapą, kurį reikia atlikti ir patikrinti
 */
$reikalavimai = [
    "MT korpuso surinkimas",
    "MT sienų surinkimas",
    "MT stogo surinkimas",
    "MT stogo tvirtinimas",
    "Pagrindo (pamato) surinkimas įžeminimo ženklų prikniedijimas",
    "10 kV kabelių gaminimas",
    "0,4 kV kabelių gaminimas",
    "10 kV kabelių sumontavimas į MT ir movų komplektacija",
    "0,4 kV kabelių sumontavimas į MT",
    "MT durų surinkimas",
    "MT durų sumontavimas sureguliavimas",
    "10 kV narvelio sumontavimas",
    "10 kV šynų , skardos, laikikliai montavimas",
    "0,4 kV komutacinių aparatų montavimas,šynų montavimas ",
    "Apskaitos ir antrinių grandinių montavimas",
    "Komplektacija",
    "MT sumontavimas ant pamato",
    "Pagalbinių grandinių (apšvietimas, ventiliacija) montavimas",
    "0,4 kV įrenginių izoliacijos varža (atitiktis)",
    "Lipdukai pagal projektą suklijavimas",
    "Išvalymas"
];

/**
 * GET parametrų nuskaitymas
 * Gaunami užsakymo numeris, užsakovas ir gaminio ID iš URL parametrų
 */
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_GET['uzsakovas'] ?? '';
$gaminio_id       = (int)($_GET['gaminio_id'] ?? 0);

/**
 * Duomenų bazės prisijungimas ir gaminio pavadinimo gavimas
 */
$db = new Database();
$conn = $db->getConnection();

$gaminys = new Gamys1($conn);
$gaminio_pavadinimas = $gaminys->gautiPilnaPavadinima($uzsakymo_numeris);

/**
 * SQL užklausa: Esamų bandymų duomenų nuskaitymas
 * Gauna visus įrašytus bandymų rezultatus pagal gaminio ID,
 * įskaitant eilės numerį, išvadą, defektą, kas atliko darbą ir kas įrašė
 */
$stmt = $conn->prepare("
    SELECT eil_nr, isvada, defektas, darba_atliko, irase_vartotojas
    FROM mt_funkciniai_bandymai
    WHERE gaminio_id = ?
");
$stmt->execute([$gaminio_id]);

/**
 * Duomenų transformavimas į asociatyvų masyvą
 * Sukuriamas žemėlapis pagal eilės numerį greitesniam duomenų pasiekimui
 */
$duomenys_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $nr = (int)$r['eil_nr'];
    $duomenys_map[$nr] = [
        'isvada'   => $r['isvada'] ?? 'nepadaryta',
        'defektas' => $r['defektas'] ?? '',
        'atliko'   => $r['darba_atliko'] ?? '',
        'irase'    => $r['irase_vartotojas'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <title>MT atliktų darbų pildymo forma</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; }
        th, td { vertical-align: middle !important; }
        .col-eilnr  { width: 70px; text-align:center; }
        .col-irase  { width: 220px; }
        .col-atliko { width: 220px; }
        .col-isvada { width: 180px; }
        .col-defekt { width: 320px; }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">

    <div class="alert alert-success mb-3">
        Prisijungęs: <strong><?= $vardas . ' ' . $pavarde ?></strong>
    </div>

    <div class="mb-4">
        <h5><strong>Užsakymo numeris:</strong> <?= htmlspecialchars($uzsakymo_numeris) ?></h5>
        <h5><strong>Užsakovas:</strong> <?= htmlspecialchars($uzsakovas) ?></h5>
        <h5><strong>Gaminio pavadinimas:</strong> <?= htmlspecialchars($gaminio_pavadinimas) ?></h5>
    </div>

    <?php 
    /**
     * Sėkmingo išsaugojimo pranešimo atvaizdavimas
     * Rodomas kai duomenys buvo sėkmingai išsaugoti (GET parametras issaugota=taip)
     */
    if (isset($_GET['issaugota']) && $_GET['issaugota'] === 'taip'): ?>
        <div class="alert alert-success">✅ Duomenys sėkmingai išsaugoti.</div>
    <?php endif; ?>

    <h2 class="text-center mb-4">MT gaminio atliktų darbų pildymo forma</h2>

    <form action="issaugoti_mt_bandyma.php" method="post">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th class="col-eilnr">Eil. Nr</th>
                        <th>Reikalavimas</th>
                        <!-- Nauja tvarka: Įrašė → Atliko → Išvada → Defektas -->
                        <th class="col-irase">Įrašė</th>
                        <th class="col-atliko">Atliko</th>
                        <th class="col-isvada">Išvada</th>
                        <th class="col-defekt">Defektas</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                /**
                 * Reikalavimų lentelės generavimas
                 * Kiekvienam reikalavimui sukuriama eilutė su įvesties laukeliais
                 */
                foreach ($reikalavimai as $i => $reik):
                    $eil_nr   = $i + 1;
                    $row      = $duomenys_map[$eil_nr] ?? [];
                    $isvada   = $row['isvada']   ?? 'nepadaryta';
                    $defektas = $row['defektas'] ?? '';
                    $atliko   = $row['atliko']   ?? '';
                    $irase    = $row['irase']    ?? '';
                ?>
                    <tr>
                        <td class="text-center"><?= $eil_nr ?></td>
                        <td><?= htmlspecialchars($reik) ?></td>

                        <!-- Įrašė (tik rodoma) -->
                        <td><?= htmlspecialchars($irase) ?></td>

                        <!-- Atliko (redaguojama, išskyrus punktą 14) -->
                        <td>
                            <?php 
                            /**
                             * Punkto 14 specialus apdorojimas
                             * Jei punktas 14 jau turi vykdytoją, laukelis tampa tik skaitomas
                             */
                            if ($eil_nr === 14 && $atliko !== ''): ?>
                                <input type="text" name="darba_atliko[<?= $i ?>]" class="form-control" 
                                       value="<?= htmlspecialchars($atliko) ?>" readonly 
                                       style="background-color: #e9ecef; cursor: not-allowed;" 
                                       title="Šio punkto vykdytojas nekeičiamas">
                            <?php else: ?>
                                <input type="text" name="darba_atliko[<?= $i ?>]" class="form-control"
                                       placeholder="Kas atliko darbus" value="<?= htmlspecialchars($atliko) ?>">
                            <?php endif; ?>
                        </td>

                        <!-- Išvada -->
                        <td>
                            <select name="isvada[<?= $i ?>]" class="form-select">
                                <option value="atitinka"   <?= $isvada === 'atitinka'   ? 'selected' : '' ?>>Atitinka</option>
                                <option value="nepadaryta" <?= $isvada === 'nepadaryta' ? 'selected' : '' ?>>Nepadaryta</option>
                                <option value="nėra"       <?= $isvada === 'nėra'       ? 'selected' : '' ?>>Šio mazgo daryti nereikia</option>
                            </select>
                        </td>

                        <!-- Defektas + paslėpti laukeliai -->
                        <td>
                            <input type="text" name="defektas[<?= $i ?>]" class="form-control"
                                   placeholder="Įveskite defektą (jei yra)" value="<?= htmlspecialchars($defektas) ?>">
                            <input type="hidden" name="reikalavimas[<?= $i ?>]" value="<?= htmlspecialchars($reik) ?>">
                            <input type="hidden" name="eil_nr[<?= $i ?>]" value="<?= (int)$eil_nr ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="gaminio_id" value="<?= (int)$gaminio_id ?>">
        <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
        <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">

        <div class="d-flex justify-content-between mt-4">
            <a class="btn btn-secondary"
               href="gaminiu_langai_mt.php?uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>">
               ← Grįžti
            </a>
            <div class="d-flex gap-2">
                <a href="MT/issaugoti_mt_bandymo_pdf.php?gaminio_id=<?= (int)$gaminio_id ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>" 
                   class="btn btn-danger">
                   📄 Išsaugoti PDF
                </a>
                <button type="submit" class="btn btn-success">💾 Išsaugoti</button>
            </div>
        </div>
    </form>
</div>
</body>
</html>
