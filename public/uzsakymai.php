<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Užsakymai';

$clients = $pdo->query('SELECT id, uzsakovas FROM uzsakovai ORDER BY uzsakovas')->fetchAll();
$objects = $pdo->query('SELECT id, pavadinimas FROM objektai ORDER BY pavadinimas')->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO uzsakymai (uzsakymo_numeris, kiekis, uzsakovas_id, objektas_id, vartotojas_id, gaminiu_rusis_id) VALUES (:nr, :kiekis, :uzsakovas_id, :objektas_id, :vartotojas_id, 1)');
        $stmt->execute([
            'nr' => $_POST['uzsakymo_numeris'] ?? '',
            'kiekis' => $_POST['kiekis'] ?: null,
            'uzsakovas_id' => $_POST['uzsakovas_id'] ?: null,
            'objektas_id' => $_POST['objektas_id'] ?: null,
            'vartotojas_id' => $_SESSION['vartotojas_id'],
        ]);
        $message = 'Užsakymas sukurtas sėkmingai.';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE uzsakymai SET uzsakymo_numeris = :nr, kiekis = :kiekis, uzsakovas_id = :uzsakovas_id, objektas_id = :objektas_id WHERE id = :id');
        $stmt->execute([
            'nr' => $_POST['uzsakymo_numeris'] ?? '',
            'kiekis' => $_POST['kiekis'] ?: null,
            'uzsakovas_id' => $_POST['uzsakovas_id'] ?: null,
            'objektas_id' => $_POST['objektas_id'] ?: null,
            'id' => $_POST['id'],
        ]);
        $message = 'Užsakymas atnaujintas.';
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        if ($id) {
            $pdo->prepare('DELETE FROM gaminiai WHERE uzsakymo_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM uzsakymai WHERE id = :id')->execute(['id' => $id]);
            $message = 'Užsakymas ištrintas.';
        }
    }
}

$view_id = $_GET['id'] ?? null;

if ($view_id) {
    $stmt = $pdo->prepare('
        SELECT u.*, uz.uzsakovas, o.pavadinimas as objektas, v.vardas, v.pavarde
        FROM uzsakymai u
        LEFT JOIN uzsakovai uz ON u.uzsakovas_id = uz.id
        LEFT JOIN objektai o ON u.objektas_id = o.id
        LEFT JOIN vartotojai v ON u.vartotojas_id = v.id
        WHERE u.id = :id
    ');
    $stmt->execute(['id' => $view_id]);
    $order = $stmt->fetch();

    $products_stmt = $pdo->prepare('
        SELECT g.*, gt.gaminio_tipas
        FROM gaminiai g
        LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
        WHERE g.uzsakymo_id = :id
        ORDER BY g.id
    ');
    $products_stmt->execute(['id' => $view_id]);
    $order_products = $products_stmt->fetchAll();
}

$orders = $pdo->query('
    SELECT u.*, uz.uzsakovas, o.pavadinimas as objektas, v.vardas, v.pavarde,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id) as gaminiu_sk
    FROM uzsakymai u
    LEFT JOIN uzsakovai uz ON u.uzsakovas_id = uz.id
    LEFT JOIN objektai o ON u.objektas_id = o.id
    LEFT JOIN vartotojai v ON u.vartotojas_id = v.id
    ORDER BY u.id DESC
')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($view_id && $order): ?>
<div style="margin-bottom: 16px;">
    <a href="/uzsakymai.php" class="btn btn-secondary btn-sm" data-testid="button-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Atgal
    </a>
</div>
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <span class="card-title">Užsakymas: <?= h($order['uzsakymo_numeris'] ?: 'Be nr.') ?></span>
        <div class="actions">
            <button class="btn btn-primary btn-sm" onclick="openModal('editOrderModal')" data-testid="button-edit-order">Redaguoti</button>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <p><strong>Užsakymo Nr.:</strong> <?= h($order['uzsakymo_numeris'] ?: '-') ?></p>
                <p><strong>Užsakovas:</strong> <?= h($order['uzsakovas'] ?? '-') ?></p>
                <p><strong>Objektas:</strong> <?= h($order['objektas'] ?? '-') ?></p>
            </div>
            <div>
                <p><strong>Kiekis:</strong> <?= h($order['kiekis'] ?? '-') ?></p>
                <p><strong>Sukūrė:</strong> <?= h(($order['vardas'] ?? '') . ' ' . ($order['pavarde'] ?? '')) ?></p>
                <p><strong>Data:</strong> <?= h($order['sukurtas'] ?? '') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Gaminiai (<?= count($order_products) ?>)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Gaminio Nr.</th>
                        <th>Tipas</th>
                        <th>Protokolo Nr.</th>
                        <th>Atitikties kodas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($order_products) > 0): ?>
                        <?php foreach ($order_products as $p): ?>
                        <tr>
                            <td style="font-weight: 500;"><?= h($p['gaminio_numeris'] ?: '-') ?></td>
                            <td><?= h($p['gaminio_tipas'] ?? '-') ?></td>
                            <td><?= h($p['protokolo_nr'] ?: '-') ?></td>
                            <td><?= h($p['atitikmuo_kodas'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty-state"><p>Šiame užsakyme nėra gaminių</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editOrderModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Redaguoti užsakymą</h3>
            <button class="modal-close" onclick="closeModal('editOrderModal')">&times;</button>
        </div>
        <form method="POST" action="/uzsakymai.php?id=<?= $order['id'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $order['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Užsakymo numeris</label>
                    <input type="text" class="form-control" name="uzsakymo_numeris" value="<?= h($order['uzsakymo_numeris'] ?? '') ?>" data-testid="input-order-number-edit">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakovas</label>
                        <select class="form-control" name="uzsakovas_id" data-testid="select-client-edit">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $order['uzsakovas_id'] ? 'selected' : '' ?>><?= h($c['uzsakovas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Objektas</label>
                        <select class="form-control" name="objektas_id" data-testid="select-object-edit">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($objects as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $o['id'] == $order['objektas_id'] ? 'selected' : '' ?>><?= h($o['pavadinimas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kiekis</label>
                    <input type="number" class="form-control" name="kiekis" value="<?= h($order['kiekis'] ?? '') ?>" data-testid="input-quantity-edit">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editOrderModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-order">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Visi užsakymai (<?= count($orders) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createOrderModal')" data-testid="button-new-order">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas užsakymas
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Užsakovas</th>
                        <th>Objektas</th>
                        <th>Gaminių</th>
                        <th>Sukūrė</th>
                        <th>Data</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $o): ?>
                        <tr data-testid="row-order-<?= $o['id'] ?>">
                            <td><a href="/uzsakymai.php?id=<?= $o['id'] ?>" style="color: var(--primary); font-weight: 500;" data-testid="link-order-<?= $o['id'] ?>"><?= h($o['uzsakymo_numeris'] ?: 'Be nr.') ?></a></td>
                            <td><?= h($o['uzsakovas'] ?? '-') ?></td>
                            <td><?= h($o['objektas'] ?? '-') ?></td>
                            <td><span class="badge badge-info"><?= $o['gaminiu_sk'] ?></span></td>
                            <td><?= h(($o['vardas'] ?? '') . ' ' . ($o['pavarde'] ?? '')) ?></td>
                            <td style="color: var(--text-secondary);"><?= h($o['sukurtas'] ?? '') ?></td>
                            <td>
                                <div class="actions">
                                    <a href="/uzsakymai.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm" data-testid="button-view-order-<?= $o['id'] ?>">Peržiūrėti</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti šį užsakymą?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-order-<?= $o['id'] ?>">Trinti</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-state"><p>Nėra užsakymų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="createOrderModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas užsakymas</h3>
            <button class="modal-close" onclick="closeModal('createOrderModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Užsakymo numeris</label>
                    <input type="text" class="form-control" name="uzsakymo_numeris" required data-testid="input-order-number">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakovas</label>
                        <select class="form-control" name="uzsakovas_id" data-testid="select-client">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= h($c['uzsakovas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Objektas</label>
                        <select class="form-control" name="objektas_id" data-testid="select-object">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($objects as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= h($o['pavadinimas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kiekis</label>
                    <input type="number" class="form-control" name="kiekis" data-testid="input-quantity">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createOrderModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-order">Sukurti</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
