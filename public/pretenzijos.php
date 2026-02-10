<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Pretenzijos';
$user = currentUser();

$message = '';
$error = '';

$orders = $pdo->query("SELECT u.id, u.uzsakymo_numeris FROM uzsakymai u WHERE u.gaminiu_rusis_id = 2 ORDER BY u.uzsakymo_numeris")->fetchAll();

$products_by_order = [];
foreach ($orders as $ord) {
    $stmt = $pdo->prepare("SELECT id, gaminio_numeris FROM gaminiai WHERE uzsakymo_id = :oid ORDER BY gaminio_numeris");
    $stmt->execute(['oid' => $ord['id']]);
    $products_by_order[$ord['id']] = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO pretenzijos (uzsakymo_id, gaminio_id, pretenzijos_nr, data, tipas, aprasymas, statusas, prioritetas, atsakingas_asmuo, sukure_id) VALUES (:uzsakymo_id, :gaminio_id, :pretenzijos_nr, :data, :tipas, :aprasymas, :statusas, :prioritetas, :atsakingas_asmuo, :sukure_id)");
        $stmt->execute([
            'uzsakymo_id' => $_POST['uzsakymo_id'] ?: null,
            'gaminio_id' => $_POST['gaminio_id'] ?: null,
            'pretenzijos_nr' => $_POST['pretenzijos_nr'] ?? '',
            'data' => $_POST['data'] ?: date('Y-m-d'),
            'tipas' => $_POST['tipas'] ?? '',
            'aprasymas' => $_POST['aprasymas'] ?? '',
            'statusas' => $_POST['statusas'] ?? 'nauja',
            'prioritetas' => $_POST['prioritetas'] ?? 'vidutinis',
            'atsakingas_asmuo' => $_POST['atsakingas_asmuo'] ?? '',
            'sukure_id' => $_SESSION['vartotojas_id'],
        ]);
        $message = 'Pretenzija sukurta sėkmingai.';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE pretenzijos SET uzsakymo_id = :uzsakymo_id, gaminio_id = :gaminio_id, pretenzijos_nr = :pretenzijos_nr, data = :data, tipas = :tipas, aprasymas = :aprasymas, statusas = :statusas, prioritetas = :prioritetas, atsakingas_asmuo = :atsakingas_asmuo, sprendimas = :sprendimas, uzdaryta_data = :uzdaryta_data, atnaujinta = CURRENT_TIMESTAMP WHERE id = :id");
        $uzdaryta = null;
        if ($_POST['statusas'] === 'uzdaryta' && !empty($_POST['uzdaryta_data'])) {
            $uzdaryta = $_POST['uzdaryta_data'];
        } elseif ($_POST['statusas'] === 'uzdaryta') {
            $uzdaryta = date('Y-m-d');
        }
        $stmt->execute([
            'uzsakymo_id' => $_POST['uzsakymo_id'] ?: null,
            'gaminio_id' => $_POST['gaminio_id'] ?: null,
            'pretenzijos_nr' => $_POST['pretenzijos_nr'] ?? '',
            'data' => $_POST['data'] ?: date('Y-m-d'),
            'tipas' => $_POST['tipas'] ?? '',
            'aprasymas' => $_POST['aprasymas'] ?? '',
            'statusas' => $_POST['statusas'] ?? 'nauja',
            'prioritetas' => $_POST['prioritetas'] ?? 'vidutinis',
            'atsakingas_asmuo' => $_POST['atsakingas_asmuo'] ?? '',
            'sprendimas' => $_POST['sprendimas'] ?? '',
            'uzdaryta_data' => $uzdaryta,
            'id' => $_POST['id'],
        ]);
        $message = 'Pretenzija atnaujinta.';
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $pdo->prepare("DELETE FROM pretenzijos WHERE id = :id")->execute(['id' => $id]);
            $message = 'Pretenzija ištrinta.';
        }
    }
}

$view_id = $_GET['id'] ?? null;
$view_claim = null;

if ($view_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.uzsakymo_numeris, g.gaminio_numeris, v.vardas, v.pavarde
        FROM pretenzijos p
        LEFT JOIN uzsakymai u ON p.uzsakymo_id = u.id
        LEFT JOIN gaminiai g ON p.gaminio_id = g.id
        LEFT JOIN vartotojai v ON p.sukure_id = v.id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $view_id]);
    $view_claim = $stmt->fetch();
}

$filter_status = $_GET['statusas'] ?? '';
$where = '';
$params = [];
if ($filter_status) {
    $where = ' WHERE p.statusas = :statusas';
    $params['statusas'] = $filter_status;
}

$claims = $pdo->prepare("
    SELECT p.*, u.uzsakymo_numeris, g.gaminio_numeris, v.vardas, v.pavarde
    FROM pretenzijos p
    LEFT JOIN uzsakymai u ON p.uzsakymo_id = u.id
    LEFT JOIN gaminiai g ON p.gaminio_id = g.id
    LEFT JOIN vartotojai v ON p.sukure_id = v.id
    $where
    ORDER BY p.id DESC
");
$claims->execute($params);
$claims = $claims->fetchAll();

$status_counts = $pdo->query("SELECT statusas, COUNT(*) as cnt FROM pretenzijos GROUP BY statusas")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<?php if ($view_id && $view_claim): ?>
<div style="margin-bottom: 16px;">
    <a href="/pretenzijos.php" class="btn btn-secondary btn-sm" data-testid="button-back-claims">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Atgal
    </a>
</div>

<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <span class="card-title">Pretenzija: <?= h($view_claim['pretenzijos_nr'] ?: '#'.$view_claim['id']) ?></span>
        <div class="actions">
            <?php
                $sc = $view_claim['statusas'];
                $badge_class = $sc === 'nauja' ? 'badge-warning' : ($sc === 'tiriama' ? 'badge-info' : ($sc === 'uzdaryta' ? 'badge-success' : 'badge-primary'));
                $status_labels = ['nauja' => 'Nauja', 'tiriama' => 'Tiriama', 'sprendziama' => 'Sprendžiama', 'uzdaryta' => 'Uždaryta'];
            ?>
            <span class="badge <?= $badge_class ?>"><?= h($status_labels[$sc] ?? $sc) ?></span>
            <button class="btn btn-primary btn-sm" onclick="openModal('editClaimModal')" data-testid="button-edit-claim">Redaguoti</button>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <p><strong>Pretenzijos Nr.:</strong> <?= h($view_claim['pretenzijos_nr'] ?: '-') ?></p>
                <p><strong>Data:</strong> <?= h($view_claim['data'] ?: '-') ?></p>
                <p><strong>Užsakymas:</strong> <?= h($view_claim['uzsakymo_numeris'] ?? '-') ?></p>
                <p><strong>Gaminys:</strong> <?= h($view_claim['gaminio_numeris'] ?? '-') ?></p>
                <p><strong>Tipas:</strong> <?= h($view_claim['tipas'] ?: '-') ?></p>
            </div>
            <div>
                <p><strong>Prioritetas:</strong>
                    <?php
                        $pr = $view_claim['prioritetas'];
                        $pr_badge = $pr === 'aukštas' ? 'badge-danger' : ($pr === 'vidutinis' ? 'badge-warning' : 'badge-info');
                        $pr_labels = ['žemas' => 'Žemas', 'vidutinis' => 'Vidutinis', 'aukštas' => 'Aukštas'];
                    ?>
                    <span class="badge <?= $pr_badge ?>"><?= h($pr_labels[$pr] ?? $pr) ?></span>
                </p>
                <p><strong>Atsakingas:</strong> <?= h($view_claim['atsakingas_asmuo'] ?: '-') ?></p>
                <p><strong>Sukūrė:</strong> <?= h(($view_claim['vardas'] ?? '') . ' ' . ($view_claim['pavarde'] ?? '')) ?></p>
                <p><strong>Uždaryta:</strong> <?= h($view_claim['uzdaryta_data'] ?: '-') ?></p>
            </div>
        </div>
        <div style="margin-top: 16px;">
            <p><strong>Aprašymas:</strong></p>
            <p style="color: var(--text-secondary); white-space: pre-wrap;"><?= h($view_claim['aprasymas'] ?: 'Nėra aprašymo') ?></p>
        </div>
        <?php if ($view_claim['sprendimas']): ?>
        <div style="margin-top: 16px;">
            <p><strong>Sprendimas:</strong></p>
            <p style="color: var(--text-secondary); white-space: pre-wrap;"><?= h($view_claim['sprendimas']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="editClaimModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Redaguoti pretenziją</h3>
            <button class="modal-close" onclick="closeModal('editClaimModal')">&times;</button>
        </div>
        <form method="POST" action="/pretenzijos.php?id=<?= $view_claim['id'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $view_claim['id'] ?>">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Pretenzijos Nr.</label>
                        <input type="text" class="form-control" name="pretenzijos_nr" value="<?= h($view_claim['pretenzijos_nr'] ?? '') ?>" data-testid="input-claim-nr-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data</label>
                        <input type="date" class="form-control" name="data" value="<?= h($view_claim['data'] ?? '') ?>" data-testid="input-claim-date-edit">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakymas</label>
                        <select class="form-control" name="uzsakymo_id" data-testid="select-claim-order-edit" onchange="updateProducts(this.value, 'edit')">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($orders as $ord): ?>
                            <option value="<?= $ord['id'] ?>" <?= $ord['id'] == $view_claim['uzsakymo_id'] ? 'selected' : '' ?>><?= h($ord['uzsakymo_numeris']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gaminys</label>
                        <select class="form-control" name="gaminio_id" id="gaminio_id_edit" data-testid="select-claim-product-edit">
                            <option value="">-- Pasirinkite --</option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tipas</label>
                        <select class="form-control" name="tipas" data-testid="select-claim-type-edit">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach (['Gamybos defektas', 'Medžiagų defektas', 'Transportavimo pažeidimas', 'Montavimo klaida', 'Kita'] as $t): ?>
                            <option value="<?= $t ?>" <?= $view_claim['tipas'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioritetas</label>
                        <select class="form-control" name="prioritetas" data-testid="select-claim-priority-edit">
                            <?php foreach (['žemas' => 'Žemas', 'vidutinis' => 'Vidutinis', 'aukštas' => 'Aukštas'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $view_claim['prioritetas'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Statusas</label>
                        <select class="form-control" name="statusas" data-testid="select-claim-status-edit">
                            <?php foreach (['nauja' => 'Nauja', 'tiriama' => 'Tiriama', 'sprendziama' => 'Sprendžiama', 'uzdaryta' => 'Uždaryta'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $view_claim['statusas'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atsakingas asmuo</label>
                        <input type="text" class="form-control" name="atsakingas_asmuo" value="<?= h($view_claim['atsakingas_asmuo'] ?? '') ?>" data-testid="input-claim-responsible-edit">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Aprašymas</label>
                    <textarea class="form-control" name="aprasymas" rows="3" data-testid="input-claim-description-edit"><?= h($view_claim['aprasymas'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Sprendimas</label>
                    <textarea class="form-control" name="sprendimas" rows="3" data-testid="input-claim-solution-edit"><?= h($view_claim['sprendimas'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Uždarymo data</label>
                    <input type="date" class="form-control" name="uzdaryta_data" value="<?= h($view_claim['uzdaryta_data'] ?? '') ?>" data-testid="input-claim-closed-edit">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editClaimModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-claim">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<script>
var productsByOrder = <?= json_encode($products_by_order) ?>;
var currentGaminioId = <?= $view_claim['gaminio_id'] ?? 'null' ?>;
function updateProducts(orderId, mode) {
    var sel = document.getElementById('gaminio_id_' + mode);
    sel.innerHTML = '<option value="">-- Pasirinkite --</option>';
    if (orderId && productsByOrder[orderId]) {
        productsByOrder[orderId].forEach(function(p) {
            var opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.gaminio_numeris;
            if (mode === 'edit' && p.id == currentGaminioId) opt.selected = true;
            sel.appendChild(opt);
        });
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var ordSel = document.querySelector('[data-testid="select-claim-order-edit"]');
    if (ordSel && ordSel.value) updateProducts(ordSel.value, 'edit');
});
</script>

<?php else: ?>

<div class="stats-summary" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Visos</span>
            <div class="stat-icon blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-claims-total"><?= count($claims) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Naujos</span>
            <div class="stat-icon orange">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-claims-new"><?= $status_counts['nauja'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Tiriamos</span>
            <div class="stat-icon cyan">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-claims-investigating"><?= ($status_counts['tiriama'] ?? 0) + ($status_counts['sprendziama'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Uždarytos</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-claims-closed"><?= $status_counts['uzdaryta'] ?? 0 ?></div>
    </div>
</div>

<div class="filter-bar">
    <div class="form-group">
        <label class="form-label">Filtruoti pagal statusą</label>
        <select class="form-control" onchange="window.location.href='/pretenzijos.php' + (this.value ? '?statusas=' + this.value : '')" data-testid="select-filter-status">
            <option value="">Visos</option>
            <option value="nauja" <?= $filter_status === 'nauja' ? 'selected' : '' ?>>Naujos</option>
            <option value="tiriama" <?= $filter_status === 'tiriama' ? 'selected' : '' ?>>Tiriamos</option>
            <option value="sprendziama" <?= $filter_status === 'sprendziama' ? 'selected' : '' ?>>Sprendžiamos</option>
            <option value="uzdaryta" <?= $filter_status === 'uzdaryta' ? 'selected' : '' ?>>Uždarytos</option>
        </select>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Pretenzijos (<?= count($claims) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createClaimModal')" data-testid="button-new-claim">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nauja pretenzija
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Data</th>
                        <th>Užsakymas</th>
                        <th>Gaminys</th>
                        <th>Tipas</th>
                        <th>Prioritetas</th>
                        <th>Statusas</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($claims) > 0): ?>
                        <?php foreach ($claims as $c): ?>
                        <?php
                            $sc = $c['statusas'];
                            $badge_class = $sc === 'nauja' ? 'badge-warning' : ($sc === 'tiriama' ? 'badge-info' : ($sc === 'uzdaryta' ? 'badge-success' : 'badge-primary'));
                            $status_labels = ['nauja' => 'Nauja', 'tiriama' => 'Tiriama', 'sprendziama' => 'Sprendžiama', 'uzdaryta' => 'Uždaryta'];
                            $pr = $c['prioritetas'];
                            $pr_badge = $pr === 'aukštas' ? 'badge-danger' : ($pr === 'vidutinis' ? 'badge-warning' : 'badge-info');
                            $pr_labels = ['žemas' => 'Žemas', 'vidutinis' => 'Vidutinis', 'aukštas' => 'Aukštas'];
                        ?>
                        <tr data-testid="row-claim-<?= $c['id'] ?>">
                            <td><a href="/pretenzijos.php?id=<?= $c['id'] ?>" style="color: var(--primary); font-weight: 500;" data-testid="link-claim-<?= $c['id'] ?>"><?= h($c['pretenzijos_nr'] ?: '#'.$c['id']) ?></a></td>
                            <td style="color: var(--text-secondary);"><?= h($c['data'] ?? '') ?></td>
                            <td><?= h($c['uzsakymo_numeris'] ?? '-') ?></td>
                            <td><?= h($c['gaminio_numeris'] ?? '-') ?></td>
                            <td><?= h($c['tipas'] ?: '-') ?></td>
                            <td><span class="badge <?= $pr_badge ?>"><?= h($pr_labels[$pr] ?? $pr) ?></span></td>
                            <td><span class="badge <?= $badge_class ?>"><?= h($status_labels[$sc] ?? $sc) ?></span></td>
                            <td>
                                <div class="actions">
                                    <a href="/pretenzijos.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" data-testid="button-view-claim-<?= $c['id'] ?>">Peržiūrėti</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti šią pretenziją?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-claim-<?= $c['id'] ?>">Trinti</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state"><p>Nėra pretenzijų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="createClaimModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nauja pretenzija</h3>
            <button class="modal-close" onclick="closeModal('createClaimModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Pretenzijos Nr.</label>
                        <input type="text" class="form-control" name="pretenzijos_nr" data-testid="input-claim-nr">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data</label>
                        <input type="date" class="form-control" name="data" value="<?= date('Y-m-d') ?>" data-testid="input-claim-date">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakymas</label>
                        <select class="form-control" name="uzsakymo_id" data-testid="select-claim-order" onchange="updateProducts(this.value, 'create')">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($orders as $ord): ?>
                            <option value="<?= $ord['id'] ?>"><?= h($ord['uzsakymo_numeris']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gaminys</label>
                        <select class="form-control" name="gaminio_id" id="gaminio_id_create" data-testid="select-claim-product">
                            <option value="">-- Pasirinkite užsakymą --</option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tipas</label>
                        <select class="form-control" name="tipas" data-testid="select-claim-type">
                            <option value="">-- Pasirinkite --</option>
                            <option value="Gamybos defektas">Gamybos defektas</option>
                            <option value="Medžiagų defektas">Medžiagų defektas</option>
                            <option value="Transportavimo pažeidimas">Transportavimo pažeidimas</option>
                            <option value="Montavimo klaida">Montavimo klaida</option>
                            <option value="Kita">Kita</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioritetas</label>
                        <select class="form-control" name="prioritetas" data-testid="select-claim-priority">
                            <option value="žemas">Žemas</option>
                            <option value="vidutinis" selected>Vidutinis</option>
                            <option value="aukštas">Aukštas</option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Statusas</label>
                        <select class="form-control" name="statusas" data-testid="select-claim-status">
                            <option value="nauja" selected>Nauja</option>
                            <option value="tiriama">Tiriama</option>
                            <option value="sprendziama">Sprendžiama</option>
                            <option value="uzdaryta">Uždaryta</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atsakingas asmuo</label>
                        <input type="text" class="form-control" name="atsakingas_asmuo" data-testid="input-claim-responsible">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Aprašymas</label>
                    <textarea class="form-control" name="aprasymas" rows="3" data-testid="input-claim-description"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createClaimModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-claim">Sukurti</button>
            </div>
        </form>
    </div>
</div>

<script>
var productsByOrder = <?= json_encode($products_by_order) ?>;
function updateProducts(orderId, mode) {
    var sel = document.getElementById('gaminio_id_' + mode);
    sel.innerHTML = '<option value="">-- Pasirinkite --</option>';
    if (orderId && productsByOrder[orderId]) {
        productsByOrder[orderId].forEach(function(p) {
            var opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.gaminio_numeris;
            sel.appendChild(opt);
        });
    }
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
