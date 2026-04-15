<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

$conn = Database::getConnection();

$user = currentUser();
if (($user['role'] ?? '') !== 'admin') {
    header('Location: /moduliai.php');
    exit;
}

if (!isset($_GET['grupe']) && empty($_SESSION['aktyvus_grupe'])) {
    header('Location: /moduliai.php');
    exit;
}

$filtro_grupe = $_GET['grupe'] ?? ($_SESSION['aktyvus_grupe'] ?? 'MT');
$grupe_id_stmt = $conn->prepare("SELECT id FROM gaminiu_rusys WHERE pavadinimas = ? LIMIT 1");
$grupe_id_stmt->execute([$filtro_grupe]);
$gaminiu_rusis_id = (int)($grupe_id_stmt->fetchColumn() ?: 2);

$stmt = $conn->prepare("SELECT id, eil_nr, pavadinimas FROM funkciniu_sablonas WHERE gaminiu_rusis_id = ? ORDER BY eil_nr ASC");
$stmt->execute([$gaminiu_rusis_id]);
$sablonas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$issaugota = $_GET['issaugota'] ?? '';
$klaida = $_GET['klaida'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<div class="page-header sablonas-ph" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <h2 data-testid="text-page-title">Funkcinių bandymų šablonas</h2>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button class="btn btn-sm" onclick="pridetiEilute()" data-testid="button-add-row" style="background:var(--success);color:#fff;border:none;display:inline-flex;align-items:center;gap:4px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Pridėti punktą
        </button>
    </div>
</div>

<?php if ($issaugota === '1'): ?>
<div class="alert" style="background:var(--success-light);color:var(--success);padding:10px 16px;border-radius:var(--radius);margin-bottom:12px;font-size:13px;" data-testid="alert-success">
    Šablonas sėkmingai išsaugotas!
</div>
<?php endif; ?>
<?php if ($klaida): ?>
<div class="alert" style="background:var(--danger-light);color:var(--danger);padding:10px 16px;border-radius:var(--radius);margin-bottom:12px;font-size:13px;" data-testid="alert-error">
    <?= htmlspecialchars($klaida) ?>
</div>
<?php endif; ?>

<div class="card" style="padding:0;">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);background:var(--bg);border-radius:var(--radius) var(--radius) 0 0;">
        <p style="font-size:13px;color:var(--text-secondary);margin:0;">
            Čia galite redaguoti funkcinių bandymų šabloną. Pakeitimai bus taikomi visiems naujiems gaminiams. Esami gaminiai nebus pakeisti.
        </p>
    </div>
    <form method="POST" action="/issaugoti_sablona.php?grupe=<?= urlencode($filtro_grupe) ?>" id="sablonasForm" data-testid="form-template">
        <input type="hidden" name="grupe" value="<?= htmlspecialchars($filtro_grupe) ?>">
        <input type="hidden" name="gaminiu_rusis_id" value="<?= $gaminiu_rusis_id ?>">
        <table class="data-table" id="sablonasTable" data-testid="table-template">
            <thead>
                <tr>
                    <th style="width:60px;text-align:center;">Eil. Nr</th>
                    <th>Reikalavimo pavadinimas</th>
                    <th style="width:120px;text-align:center;">Veiksmai</th>
                </tr>
            </thead>
            <tbody id="sablonasBody">
                <?php foreach ($sablonas as $i => $r): ?>
                <tr data-id="<?= $r['id'] ?>" data-testid="row-template-<?= $r['eil_nr'] ?>">
                    <td style="text-align:center;font-weight:600;color:var(--text-secondary);" class="eilNrCell"><?= $r['eil_nr'] ?></td>
                    <td>
                        <input type="hidden" name="id[]" value="<?= $r['id'] ?>">
                        <input type="text" name="pavadinimas[]" value="<?= htmlspecialchars($r['pavadinimas']) ?>"
                               style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:var(--radius);font-size:13px;"
                               data-testid="input-name-<?= $r['eil_nr'] ?>" required>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:inline-flex;gap:4px;align-items:center;">
                            <button type="button" class="btn-icon" onclick="perkeltiAukstyn(this)" title="Perkelti aukštyn" data-testid="button-up-<?= $r['eil_nr'] ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                            </button>
                            <button type="button" class="btn-icon" onclick="perkeltiZemyn(this)" title="Perkelti žemyn" data-testid="button-down-<?= $r['eil_nr'] ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" onclick="pasalintiEilute(this)" title="Pašalinti" data-testid="button-delete-<?= $r['eil_nr'] ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;color:var(--text-secondary);" id="punktuSkaicius" data-testid="text-count">Iš viso: <?= count($sablonas) ?> punktų</span>
            <button type="submit" class="btn btn-primary btn-sm" data-testid="button-save-template">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Išsaugoti šabloną
            </button>
        </div>
    </form>
</div>

<style>
.btn-icon {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg-card);
    cursor: pointer;
    color: var(--text-secondary);
    transition: all 0.15s ease;
}
.btn-icon:hover {
    background: var(--bg);
    color: var(--text);
    border-color: var(--text-secondary);
}
.btn-icon-danger:hover {
    background: var(--danger-light);
    color: var(--danger);
    border-color: var(--danger);
}
#sablonasBody tr {
    transition: background 0.15s ease;
}
#sablonasBody tr:hover {
    background: var(--bg);
}
#sablonasBody tr.dragging {
    opacity: 0.5;
    background: var(--primary-light);
}
</style>

<script>
function atnaujintiNumerius() {
    var rows = document.querySelectorAll('#sablonasBody tr');
    rows.forEach(function(row, i) {
        row.querySelector('.eilNrCell').textContent = i + 1;
    });
    document.getElementById('punktuSkaicius').textContent = 'Iš viso: ' + rows.length + ' punktų';
}

function pridetiEilute() {
    var tbody = document.getElementById('sablonasBody');
    var nr = tbody.querySelectorAll('tr').length + 1;
    var tr = document.createElement('tr');
    tr.setAttribute('data-id', 'new');
    tr.setAttribute('data-testid', 'row-template-new-' + nr);
    tr.innerHTML = '<td style="text-align:center;font-weight:600;color:var(--text-secondary);" class="eilNrCell">' + nr + '</td>' +
        '<td>' +
        '<input type="hidden" name="id[]" value="new">' +
        '<input type="text" name="pavadinimas[]" value="" style="width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:var(--radius);font-size:13px;" placeholder="Įveskite reikalavimo pavadinimą..." required>' +
        '</td>' +
        '<td style="text-align:center;">' +
        '<div style="display:inline-flex;gap:4px;align-items:center;">' +
        '<button type="button" class="btn-icon" onclick="perkeltiAukstyn(this)" title="Perkelti aukštyn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg></button>' +
        '<button type="button" class="btn-icon" onclick="perkeltiZemyn(this)" title="Perkelti žemyn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></button>' +
        '<button type="button" class="btn-icon btn-icon-danger" onclick="pasalintiEilute(this)" title="Pašalinti"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>' +
        '</div>' +
        '</td>';
    tbody.appendChild(tr);
    atnaujintiNumerius();
    tr.querySelector('input[name="pavadinimas[]"]').focus();
}

function pasalintiEilute(btn) {
    var tr = btn.closest('tr');
    if (!confirm('Ar tikrai norite pašalinti šį punktą?')) return;
    tr.remove();
    atnaujintiNumerius();
}

function perkeltiAukstyn(btn) {
    var tr = btn.closest('tr');
    var prev = tr.previousElementSibling;
    if (prev) {
        tr.parentNode.insertBefore(tr, prev);
        atnaujintiNumerius();
    }
}

function perkeltiZemyn(btn) {
    var tr = btn.closest('tr');
    var next = tr.nextElementSibling;
    if (next) {
        tr.parentNode.insertBefore(next, tr);
        atnaujintiNumerius();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
