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

/* --- Gamybos reikalavimų sąrašas iš šablono lentelės (pagal grupę) --- */
$grupe_sab = 'MT';
if ($gaminio_id > 0) {
    $stmt_gr = $conn->prepare("SELECT COALESCE(gt.grupe, 'MT') FROM gaminiai g JOIN gaminio_tipai gt ON gt.id = g.gaminio_tipas_id WHERE g.id = ?");
    $stmt_gr->execute([$gaminio_id]);
    $grupe_sab = $stmt_gr->fetchColumn() ?: 'MT';
}
$stmt_rusis = $conn->prepare("SELECT id FROM gaminiu_rusys WHERE pavadinimas = ? LIMIT 1");
$stmt_rusis->execute([$grupe_sab]);
$rusis_id_sab = (int)($stmt_rusis->fetchColumn() ?: 2);

$stmt_sab = $conn->prepare("SELECT pavadinimas FROM mt_funkciniu_sablonas WHERE gaminiu_rusis_id = ? ORDER BY eil_nr ASC");
$stmt_sab->execute([$rusis_id_sab]);
$reikalavimai = $stmt_sab->fetchAll(PDO::FETCH_COLUMN);
if (empty($reikalavimai)) {
    $stmt_sab_all = $conn->query("SELECT pavadinimas FROM mt_funkciniu_sablonas WHERE gaminiu_rusis_id = 2 ORDER BY eil_nr ASC");
    $reikalavimai = $stmt_sab_all->fetchAll(PDO::FETCH_COLUMN);
}
if (empty($reikalavimai)) {
    $reikalavimai = ["Korpuso surinkimas"];
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
        <div class="d-flex justify-content-end mb-2">
            <button type="button" class="btn btn-primary btn-sm" onclick="atidarytiMasiniSiuntima()" data-testid="button-siusti-pazymetus">✉ Siųsti pažymėtus</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="pasirinkti-visus" onclick="perjungtiVisus(this)" data-testid="checkbox-select-all" title="Pažymėti visus"></th>
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
                    <tr data-eil-nr="<?= $eil_nr ?>" data-reikalavimas="<?= htmlspecialchars($reik) ?>">
                        <td class="text-center"><input type="checkbox" class="siuntimo-cb" value="<?= $eil_nr ?>" data-testid="checkbox-eilute-<?= $eil_nr ?>"></td>
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
        <h5>Siųsti el. pranešimus</h5>
        <div id="modal-punktu-sarasas" style="font-size: 13px; color: #555; margin-bottom: 10px;"></div>
        <label for="modal-gavejas" style="font-size: 13px; font-weight: 500; margin-bottom: 4px; display: block;">Pasirinkite gavėją:</label>
        <select id="modal-gavejas" class="form-select" data-testid="select-gavejas">
            <option value="">-- Pasirinkite --</option>
            <?php foreach ($vartotojai_su_el as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vardas'] . ' ' . $v['pavarde']) ?> (<?= htmlspecialchars($v['el_pastas']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <div id="modal-siuntimo-progr" style="display:none; margin-bottom:10px;">
            <div style="font-size:13px; color:#555; margin-bottom:4px;">Siunčiama: <span id="modal-progr-tekstas"></span></div>
            <div style="background:#e5e7eb; border-radius:4px; height:6px; overflow:hidden;">
                <div id="modal-progr-bar" style="background:#3498db; height:100%; width:0%; transition: width 0.3s;"></div>
            </div>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary btn-sm" onclick="uzdarytiSiuntima()" data-testid="button-atsaukti-siuntima">Atšaukti</button>
            <button type="button" class="btn btn-primary btn-sm" id="modal-siusti-btn" onclick="siustiMasiniai()" data-testid="button-patvirtinti-siuntima">Siųsti visus</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var _gaminio_id = <?= (int)$gaminio_id ?>;
var _uzsakymo_numeris = <?= json_encode($uzsakymo_numeris) ?>;
var _gaminio_pavadinimas = <?= json_encode($gaminio_pavadinimas) ?>;
var _pasirinkti_eilnr = [];

function perjungtiVisus(cb) {
    var visi = document.querySelectorAll('.siuntimo-cb');
    for (var i = 0; i < visi.length; i++) {
        visi[i].checked = cb.checked;
    }
}

function gautiPasirinktus() {
    var pasirinkti = [];
    var visi = document.querySelectorAll('.siuntimo-cb:checked');
    for (var i = 0; i < visi.length; i++) {
        pasirinkti.push(parseInt(visi[i].value));
    }
    return pasirinkti;
}

function atidarytiMasiniSiuntima() {
    _pasirinkti_eilnr = gautiPasirinktus();
    if (_pasirinkti_eilnr.length === 0) {
        alert('Pažymėkite bent vieną eilutę');
        return;
    }
    var sarasas = document.getElementById('modal-punktu-sarasas');
    var html = '<strong>Pažymėti punktai (' + _pasirinkti_eilnr.length + '):</strong><ul style="margin:5px 0 0 0; padding-left:20px;">';
    for (var i = 0; i < _pasirinkti_eilnr.length; i++) {
        var nr = _pasirinkti_eilnr[i];
        var rowEl = document.querySelector('tr[data-eil-nr="' + nr + '"]');
        var pav = rowEl ? rowEl.getAttribute('data-reikalavimas') : '';
        html += '<li>' + nr + '. ' + pav + '</li>';
    }
    html += '</ul>';
    sarasas.innerHTML = html;
    document.getElementById('modal-gavejas').value = '';
    document.getElementById('modal-siuntimo-progr').style.display = 'none';
    document.getElementById('modal-siusti-btn').disabled = false;
    document.getElementById('modal-siusti-btn').textContent = 'Siųsti visus';
    document.getElementById('siuntimo-modal').classList.add('active');
}

function uzdarytiSiuntima() {
    document.getElementById('siuntimo-modal').classList.remove('active');
}

function siustiMasiniai() {
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
    formData.append('gavejo_id', gavejo_id);
    formData.append('uzsakymo_numeris', _uzsakymo_numeris);
    formData.append('gaminio_pavadinimas', _gaminio_pavadinimas);
    for (var i = 0; i < _pasirinkti_eilnr.length; i++) {
        formData.append('eil_nr[]', _pasirinkti_eilnr[i]);
    }

    var progrDiv = document.getElementById('modal-siuntimo-progr');
    progrDiv.style.display = 'block';
    document.getElementById('modal-progr-tekstas').textContent = '0 / ' + _pasirinkti_eilnr.length;
    document.getElementById('modal-progr-bar').style.width = '0%';

    fetch('/siusti_defekta.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var rezultatai = data.rezultatai || [];
                var sekmingai = 0;
                for (var i = 0; i < rezultatai.length; i++) {
                    var rez = rezultatai[i];
                    var rezDiv = document.getElementById('siuntimo-rez-' + rez.eil_nr);
                    if (rez.ok) {
                        sekmingai++;
                        if (rezDiv) {
                            rezDiv.className = 'siuntimo-rezultatas ok';
                            rezDiv.textContent = '✓ Išsiųsta';
                        }
                        var infoDiv = document.getElementById('issiusta-info-' + rez.eil_nr);
                        if (infoDiv && rez.issiusta_kam) {
                            infoDiv.textContent = rez.issiusta_kam;
                        }
                    } else {
                        if (rezDiv) {
                            rezDiv.className = 'siuntimo-rezultatas klaida';
                            rezDiv.textContent = '✗ ' + (rez.message || 'Klaida');
                        }
                    }
                    var proc = Math.round(((i + 1) / rezultatai.length) * 100);
                    document.getElementById('modal-progr-bar').style.width = proc + '%';
                    document.getElementById('modal-progr-tekstas').textContent = (i + 1) + ' / ' + rezultatai.length;
                }
                document.getElementById('modal-progr-tekstas').textContent = 'Baigta! Išsiųsta: ' + sekmingai + ' / ' + rezultatai.length;

                var visi_cb = document.querySelectorAll('.siuntimo-cb:checked');
                for (var j = 0; j < visi_cb.length; j++) { visi_cb[j].checked = false; }
                document.getElementById('pasirinkti-visus').checked = false;
            } else {
                alert(data.message || 'Klaida siunčiant');
            }
            btn.disabled = false;
            btn.textContent = 'Siųsti visus';
            setTimeout(function() { uzdarytiSiuntima(); }, 2000);
        })
        .catch(function(err) {
            alert('Klaida siunčiant: ' + err);
            btn.disabled = false;
            btn.textContent = 'Siųsti visus';
        });
}

document.getElementById('siuntimo-modal').addEventListener('click', function(e) {
    if (e.target === this) uzdarytiSiuntima();
});
</script>
</body>
</html>
