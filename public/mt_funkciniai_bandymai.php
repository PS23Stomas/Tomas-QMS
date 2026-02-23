<?php
/**
 * MT funkcinių bandymų forma - 21 gamybos reikalavimas su defektų sekimu
 *
 * Šis failas atvaizduoja MT funkcinių bandymų pildymo formą su 21 gamybos reikalavimu.
 * Kiekvienas reikalavimas turi išvadą (atitinka/nepadaryta/nėra), defekto aprašymą
 * ir darbuotojo, kuris atliko darbus, vardą.
 */

require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Gaminys.php';
require_once __DIR__ . '/klases/Sesija.php';

/* Sesijos pradžia ir prisijungimo tikrinimas */
Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$vardas = htmlspecialchars($_SESSION['vardas']);
$pavarde = htmlspecialchars($_SESSION['pavarde']);
$pilnas_vardas = $vardas . ' ' . $pavarde;

/* GET parametrų nuskaitymas */
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_GET['uzsakovas'] ?? '';
$gaminio_id       = (int)($_GET['gaminio_id'] ?? 0);
$uzsakymo_id      = $_GET['uzsakymo_id'] ?? '';

$conn = Database::getConnection();

/* --- Gamybos reikalavimų sąrašas iš šablono lentelės --- */
$stmt_sab = $conn->query("SELECT pavadinimas FROM mt_funkciniu_sablonas ORDER BY eil_nr ASC");
$reikalavimai = $stmt_sab->fetchAll(PDO::FETCH_COLUMN);
if (empty($reikalavimai)) {
    $reikalavimai = ["MT korpuso surinkimas"];
}
$gaminys = new Gaminys($conn);
$gaminio_pavadinimas = $gaminys->gautiPilnaPavadinima($uzsakymo_numeris);

$gaminio_info = $gaminys->gautiPagalId($gaminio_id);
$turi_funkciniu_pdf = !empty($gaminio_info['mt_funkciniu_failas']);
$pdf_sukurtas = $_GET['pdf_sukurtas'] ?? '';
$pdf_klaida = $_GET['pdf_klaida'] ?? '';

/* --- Esamų bandymų duomenų užkrovimas iš duomenų bazės į žemėlapį (map) --- */
/* Rezultatas: $duomenys_map[eilės_nr] = ['isvada', 'defektas', 'atliko', 'irase'] */
$stmt = $conn->prepare("SELECT eil_nr, isvada, defektas, darba_atliko, irase_vartotojas, defekto_nuotraukos_pavadinimas, pataisyta, issiusta_kam FROM mt_funkciniai_bandymai WHERE gaminio_id = ?");
$stmt->execute([$gaminio_id]);

$duomenys_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $nr = (int)$r['eil_nr'];
    $duomenys_map[$nr] = [
        'isvada'       => $r['isvada'] ?? 'nepadaryta',
        'defektas'     => $r['defektas'] ?? '',
        'atliko'       => $r['darba_atliko'] ?? '',
        'irase'        => $r['irase_vartotojas'] ?? '',
        'nuotrauka'    => $r['defekto_nuotraukos_pavadinimas'] ?? '',
        'pataisyta'    => $r['pataisyta'] ?? '',
        'issiusta_kam' => $r['issiusta_kam'] ?? ''
    ];
}

$vartotojai_su_el = $conn->query("SELECT id, vardas, pavarde, el_pastas FROM vartotojai WHERE el_pastas IS NOT NULL AND el_pastas != '' ORDER BY vardas, pavarde")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <title>MT atliktų darbų pildymo forma</title>
    <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; }
        th, td { vertical-align: middle !important; }
        .col-eilnr  { width: 70px; text-align:center; }
        .col-irase  { width: 110px; }
        .col-atliko { width: 160px; }
        .col-isvada { width: 100px; }
        .col-defekt { width: 260px; }
        .col-nuotr  { width: 220px; }
        .col-patais { width: 160px; }
        .col-issiusta { width: 180px; }
        .nuotr-preview { max-width: 60px; max-height: 40px; cursor: pointer; border: 1px solid #ccc; border-radius: 3px; }
        .nuotr-input { font-size: 11px; }
        .btn-siusti { background: #3498db; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; white-space: nowrap; }
        .btn-siusti:hover { background: #2980b9; }
        .btn-siusti:disabled { background: #95a5a6; cursor: not-allowed; }
        .issiusta-info { font-size: 11px; color: #555; white-space: pre-line; margin-top: 4px; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 8px; padding: 24px; max-width: 450px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-box h5 { margin: 0 0 15px; font-size: 18px; }
        .modal-box .form-select { margin-bottom: 12px; }
        .modal-box .btn-group { display: flex; gap: 8px; justify-content: flex-end; }
        .siuntimo-rezultatas { margin-top: 4px; font-size: 11px; font-weight: 500; }
        .siuntimo-rezultatas.ok { color: #27ae60; }
        .siuntimo-rezultatas.klaida { color: #c0392b; }
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

    <?php if (isset($_GET['issaugota']) && $_GET['issaugota'] === 'taip'): ?>
        <div class="alert alert-success">Duomenys sėkmingai išsaugoti.</div>
    <?php endif; ?>
    <?php if ($pdf_sukurtas === 'taip'): ?>
        <div class="alert alert-success">PDF dokumentas sėkmingai sugeneruotas ir išsaugotas.</div>
    <?php endif; ?>
    <?php if (!empty($pdf_klaida)): ?>
        <div class="alert alert-danger">PDF generavimo klaida: <?= htmlspecialchars($pdf_klaida) ?></div>
    <?php endif; ?>

    <h2 class="text-center mb-4">MT gaminio atliktų darbų pildymo forma</h2>

    <form action="/issaugoti_mt_bandyma.php" method="post" enctype="multipart/form-data">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th class="col-eilnr">Eil. Nr</th>
                        <th>Reikalavimas</th>
                        <th class="col-irase">Įrašė</th>
                        <th class="col-atliko">Atliko</th>
                        <th class="col-isvada">Išvada</th>
                        <th class="col-defekt">Defektas/Trukumas</th>
                        <th class="col-nuotr">Nuotrauka</th>
                        <th class="col-patais">Pataisyta</th>
                        <th class="col-issiusta">Išsiųsta</th>
                    </tr>
                </thead>
                <tbody>
                <!-- Formos atvaizdavimas: kiekvienas reikalavimas su pasirinkimu (select) ir įvesties laukais -->
                <?php foreach ($reikalavimai as $i => $reik):
                    $eil_nr   = $i + 1;
                    $row      = $duomenys_map[$eil_nr] ?? [];
                    $isvada    = $row['isvada']    ?? 'nepadaryta';
                    $defektas  = $row['defektas']  ?? '';
                    $atliko    = $row['atliko']    ?? '';
                    $irase       = $row['irase']       ?? '';
                    $nuotrauka   = $row['nuotrauka']   ?? '';
                    $pataisyta   = $row['pataisyta']   ?? '';
                    $issiusta_kam = $row['issiusta_kam'] ?? '';
                ?>
                    <tr>
                        <td class="text-center"><?= $eil_nr ?></td>
                        <td><?= htmlspecialchars($reik) ?></td>
                        <td><?= htmlspecialchars($irase) ?></td>
                        <td>
                            <?php if ($eil_nr === 14 && $atliko !== ''): ?>
                                <input type="text" name="darba_atliko[<?= $i ?>]" class="form-control" 
                                       value="<?= htmlspecialchars($atliko) ?>" readonly 
                                       style="background-color: #e9ecef; cursor: not-allowed;" 
                                       title="Šio punkto vykdytojas nekeičiamas">
                            <?php else: ?>
                                <input type="text" name="darba_atliko[<?= $i ?>]" class="form-control"
                                       placeholder="Kas atliko darbus" value="<?= htmlspecialchars($atliko) ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="isvada[<?= $i ?>]" class="form-select">
                                <option value="atitinka"   <?= $isvada === 'atitinka'   ? 'selected' : '' ?>>Atitinka</option>
                                <option value="nepadaryta" <?= $isvada === 'nepadaryta' ? 'selected' : '' ?>>Nepadaryta</option>
                                <option value="nėra"       <?= $isvada === 'nėra'       ? 'selected' : '' ?>>Šio mazgo daryti nereikia</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="defektas[<?= $i ?>]" class="form-control"
                                   placeholder="Įveskite defektą (jei yra)" value="<?= htmlspecialchars($defektas) ?>">
                            <input type="hidden" name="reikalavimas[<?= $i ?>]" value="<?= htmlspecialchars($reik) ?>">
                            <input type="hidden" name="eil_nr[<?= $i ?>]" value="<?= (int)$eil_nr ?>">
                        </td>
                        <td class="text-center">
                            <?php if (!empty($nuotrauka)): ?>
                                <a href="/defekto_nuotrauka.php?gaminio_id=<?= $gaminio_id ?>&eil_nr=<?= $eil_nr ?>" target="_blank">
                                    <img src="/defekto_nuotrauka.php?gaminio_id=<?= $gaminio_id ?>&eil_nr=<?= $eil_nr ?>&thumb=1" class="nuotr-preview" alt="Nuotrauka" title="<?= htmlspecialchars($nuotrauka) ?>">
                                </a>
                            <?php endif; ?>
                            <input type="file" name="nuotrauka_<?= $eil_nr ?>" accept="image/*" capture="environment" class="form-control nuotr-input mt-1" data-testid="input-photo-<?= $eil_nr ?>">
                        </td>
                        <td>
                            <input type="text" name="pataisyta[<?= $i ?>]" class="form-control"
                                   placeholder="" value="<?= htmlspecialchars($pataisyta) ?>" data-testid="input-pataisyta-<?= $eil_nr ?>">
                        </td>
                        <td>
                            <button type="button" class="btn-siusti" onclick="atidarytiSiuntima(<?= $eil_nr ?>)" data-testid="button-siusti-<?= $eil_nr ?>">✉ Siųsti</button>
                            <div id="issiusta-info-<?= $eil_nr ?>" class="issiusta-info"><?= !empty($issiusta_kam) ? htmlspecialchars($issiusta_kam) : '' ?></div>
                            <div id="siuntimo-rez-<?= $eil_nr ?>" class="siuntimo-rezultatas"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="gaminio_id" value="<?= (int)$gaminio_id ?>">
        <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
        <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">

        <input type="hidden" name="uzsakymo_id" value="<?= htmlspecialchars($uzsakymo_id) ?>">
        <div class="d-flex justify-content-between mt-4">
            <a class="btn btn-secondary"
               href="/uzsakymai.php?id=<?= htmlspecialchars($uzsakymo_id) ?>">
               ← Grįžti
            </a>
            <button type="submit" class="btn btn-success">Išsaugoti</button>
        </div>
    </form>

    <div class="d-flex gap-2 mb-3 mt-4 align-items-center">
        <form action="/MT/generuoti_mt_funkciniu_pdf.php" method="post" style="display:inline;">
            <input type="hidden" name="gaminio_id" value="<?= (int)$gaminio_id ?>">
            <input type="hidden" name="uzsakymo_numeris" value="<?= htmlspecialchars($uzsakymo_numeris) ?>">
            <input type="hidden" name="uzsakovas" value="<?= htmlspecialchars($uzsakovas) ?>">
            <input type="hidden" name="gaminio_pavadinimas" value="<?= htmlspecialchars($gaminio_pavadinimas) ?>">
            <input type="hidden" name="uzsakymo_id" value="<?= htmlspecialchars($uzsakymo_id) ?>">
            <button type="submit" class="btn btn-primary" data-testid="button-generuoti-funkciniu-pdf">Generuoti PDF</button>
        </form>
        <?php if ($turi_funkciniu_pdf): ?>
        <a href="/MT/mt_funkciniu_pdf.php?gaminio_id=<?= $gaminio_id ?>" target="_blank" class="btn btn-outline-primary" data-testid="button-perziureti-funkciniu-pdf">Peržiūrėti PDF</a>
        <a href="/MT/mt_funkciniu_pdf.php?gaminio_id=<?= $gaminio_id ?>&atsisiusti" class="btn btn-outline-secondary" data-testid="button-atsisiusti-funkciniu-pdf">Atsisiųsti PDF</a>
        <?php endif; ?>
    </div>
</div>
<div class="modal-overlay" id="siuntimo-modal">
    <div class="modal-box">
        <h5>Siųsti el. pranešimą</h5>
        <p style="font-size: 13px; color: #555; margin-bottom: 10px;">
            Punktas: <strong id="modal-punktas-nr"></strong> – <span id="modal-punktas-pav"></span>
        </p>
        <label for="modal-gavejas" style="font-size: 13px; font-weight: 500; margin-bottom: 4px; display: block;">Pasirinkite gavėją:</label>
        <select id="modal-gavejas" class="form-select" data-testid="select-gavejas">
            <option value="">-- Pasirinkite --</option>
            <?php foreach ($vartotojai_su_el as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vardas'] . ' ' . $v['pavarde']) ?> (<?= htmlspecialchars($v['el_pastas']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary btn-sm" onclick="uzdarytiSiuntima()" data-testid="button-atsaukti-siuntima">Atšaukti</button>
            <button type="button" class="btn btn-primary btn-sm" id="modal-siusti-btn" onclick="siustiElPasta()" data-testid="button-patvirtinti-siuntima">Siųsti</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var _siuntimo_eil_nr = 0;
var _gaminio_id = <?= (int)$gaminio_id ?>;
var _uzsakymo_numeris = <?= json_encode($uzsakymo_numeris) ?>;
var _gaminio_pavadinimas = <?= json_encode($gaminio_pavadinimas) ?>;

function atidarytiSiuntima(eilNr) {
    _siuntimo_eil_nr = eilNr;
    document.getElementById('modal-punktas-nr').textContent = eilNr;
    var row = document.querySelector('tr:nth-child(' + eilNr + ') td:nth-child(2)');
    document.getElementById('modal-punktas-pav').textContent = row ? row.textContent.trim() : '';
    document.getElementById('modal-gavejas').value = '';
    document.getElementById('siuntimo-modal').classList.add('active');
}

function uzdarytiSiuntima() {
    document.getElementById('siuntimo-modal').classList.remove('active');
}

function siustiElPasta() {
    var gavejo_id = document.getElementById('modal-gavejas').value;
    if (!gavejo_id) {
        alert('Pasirinkite gavėją');
        return;
    }

    var btn = document.getElementById('modal-siusti-btn');
    btn.disabled = true;
    btn.textContent = 'Siunčiama...';

    var formData = new FormData();
    formData.append('gaminio_id', _gaminio_id);
    formData.append('eil_nr', _siuntimo_eil_nr);
    formData.append('gavejo_id', gavejo_id);
    formData.append('uzsakymo_numeris', _uzsakymo_numeris);
    formData.append('gaminio_pavadinimas', _gaminio_pavadinimas);

    fetch('/siusti_defekta.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var rezDiv = document.getElementById('siuntimo-rez-' + _siuntimo_eil_nr);
            if (data.success) {
                rezDiv.className = 'siuntimo-rezultatas ok';
                rezDiv.textContent = '✓ ' + data.message;
                document.getElementById('issiusta-info-' + _siuntimo_eil_nr).textContent = data.issiusta_kam || '';
            } else {
                rezDiv.className = 'siuntimo-rezultatas klaida';
                rezDiv.textContent = '✗ ' + data.message;
            }
            uzdarytiSiuntima();
            btn.disabled = false;
            btn.textContent = 'Siųsti';
        })
        .catch(function(err) {
            var rezDiv = document.getElementById('siuntimo-rez-' + _siuntimo_eil_nr);
            rezDiv.className = 'siuntimo-rezultatas klaida';
            rezDiv.textContent = '✗ Klaida siunčiant';
            uzdarytiSiuntima();
            btn.disabled = false;
            btn.textContent = 'Siųsti';
        });
}

document.getElementById('siuntimo-modal').addEventListener('click', function(e) {
    if (e.target === this) uzdarytiSiuntima();
});
</script>
</body>
</html>
