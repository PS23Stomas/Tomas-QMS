<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Gaminiai';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO gaminiai (uzsakymo_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr, atitikmuo_kodas) VALUES (:uzsakymo_id, :gaminio_numeris, :gaminio_tipas_id, :protokolo_nr, :atitikmuo_kodas)');
        $stmt->execute([
            'uzsakymo_id' => $_POST['uzsakymo_id'] ?: null,
            'gaminio_numeris' => $_POST['gaminio_numeris'] ?? '',
            'gaminio_tipas_id' => $_POST['gaminio_tipas_id'] ?: null,
            'protokolo_nr' => $_POST['protokolo_nr'] ?? '',
            'atitikmuo_kodas' => $_POST['atitikmuo_kodas'] ?? '',
        ]);
        $message = 'Gaminys sukurtas sėkmingai.';
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $pdo->prepare('DELETE FROM mt_komponentai WHERE gaminio_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM gaminiai WHERE id = :id')->execute(['id' => $id]);
            $message = 'Gaminys ištrintas.';
        }
    }
}

$orders = $pdo->query('SELECT id, uzsakymo_numeris FROM uzsakymai ORDER BY id DESC')->fetchAll();
$types = Gaminys::gautiVisusTipus($pdo);

$products = $pdo->query('
    SELECT g.*, gt.gaminio_tipas, gt.grupe, u.uzsakymo_numeris
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id
    ORDER BY g.id DESC
')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Visi gaminiai (<?= count($products) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createProductModal')" data-testid="button-new-product">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas gaminys
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gaminio Nr.</th>
                        <th>Užsakymo Nr.</th>
                        <th>Tipas</th>
                        <th>Grupė</th>
                        <th>Protokolo Nr.</th>
                        <th>Atitikties kodas</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $p): ?>
                        <tr data-testid="row-product-<?= $p['id'] ?>">
                            <td><?= $p['id'] ?></td>
                            <td style="font-weight: 500;"><?= h($p['gaminio_numeris'] ?: '-') ?></td>
                            <td><a href="/uzsakymai.php?id=<?= $p['uzsakymo_id'] ?>" style="color: var(--primary);"><?= h($p['uzsakymo_numeris'] ?? '-') ?></a></td>
                            <td><?= h($p['gaminio_tipas'] ?? '-') ?></td>
                            <td><?= h($p['grupe'] ?? '-') ?></td>
                            <td><?= h($p['protokolo_nr'] ?: '-') ?></td>
                            <td><?= h($p['atitikmuo_kodas'] ?: '-') ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti šį gaminį?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-product-<?= $p['id'] ?>">Trinti</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state"><p>Nėra gaminių</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="createProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas gaminys</h3>
            <button class="modal-close" onclick="closeModal('createProductModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Gaminio numeris</label>
                    <input type="text" class="form-control" name="gaminio_numeris" required data-testid="input-product-number">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakymas</label>
                        <select class="form-control" name="uzsakymo_id" data-testid="select-order">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($orders as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= h($o['uzsakymo_numeris'] ?: 'ID: ' . $o['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gaminio tipas</label>
                        <select class="form-control" name="gaminio_tipas_id" data-testid="select-type">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= h($t['gaminio_tipas']) ?> (<?= h($t['grupe']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Protokolo Nr.</label>
                        <input type="text" class="form-control" name="protokolo_nr" data-testid="input-protocol">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atitikties kodas</label>
                        <input type="text" class="form-control" name="atitikmuo_kodas" data-testid="input-code">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProductModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-product">Sukurti</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
