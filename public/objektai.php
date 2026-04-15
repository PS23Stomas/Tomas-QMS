<?php
/**
 * Objektų (statybos aikštelių) valdymo puslapis - CRUD operacijos.
 * Rodo visus objektus su susijusių užsakymų skaičiumi.
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Objektai';
$message = '';

// POST užklausų apdorojimas: kūrimas, redagavimas, šalinimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Naujo objekto sukūrimas
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO objektai (pavadinimas) VALUES (:pavadinimas)');
        $stmt->execute(['pavadinimas' => $_POST['pavadinimas'] ?? '']);
        $message = 'Objektas sukurtas sėkmingai.';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE objektai SET pavadinimas = :pavadinimas WHERE id = :id');
        $stmt->execute(['pavadinimas' => $_POST['pavadinimas'] ?? '', 'id' => $_POST['id']]);
        $message = 'Objektas atnaujintas.';
    } elseif ($action === 'delete') {
        $user = currentUser();
        if (($user['role'] ?? '') !== 'admin') {
            $error = 'Tik administratorius gali trinti objektus.';
        } else {
            $id = $_POST['id'] ?? null;
            if ($id) {
                $pdo->prepare('DELETE FROM objektai WHERE id = :id')->execute(['id' => $id]);
                $message = 'Objektas ištrintas.';
            }
        }
    }
}

// Objektų sąrašas su susijusių užsakymų skaičiumi
$objects = $pdo->query('
    SELECT o.*, (SELECT COUNT(*) FROM uzsakymai u WHERE u.objektas_id = o.id) as uzsakymu_sk
    FROM objektai o
    ORDER BY o.pavadinimas
')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Visi objektai (<?= count($objects) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createObjectModal')" data-testid="button-new-object">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas objektas
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="generic-card-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pavadinimas</th>
                        <th>Užsakymų</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($objects) > 0): ?>
                        <?php foreach ($objects as $o): ?>
                        <tr data-testid="row-object-<?= $o['id'] ?>">
                            <td data-label="ID"><?= $o['id'] ?></td>
                            <td class="gct-cell-title"><?= h($o['pavadinimas']) ?></td>
                            <td data-label="Užsakymų"><span class="badge badge-info"><?= $o['uzsakymu_sk'] ?></span></td>
                            <td class="gct-cell-actions">
                                <div class="actions">
                                    <button class="btn btn-secondary btn-sm" onclick="editObject(<?= $o['id'] ?>, '<?= h(addslashes($o['pavadinimas'])) ?>')" data-testid="button-edit-object-<?= $o['id'] ?>">Redaguoti</button>
                                    <?php if ((currentUser()['role'] ?? '') === 'admin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-object-<?= $o['id'] ?>">Trinti</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty-state"><p>Nėra objektų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="createObjectModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas objektas</h3>
            <button class="modal-close" onclick="closeModal('createObjectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Objekto pavadinimas</label>
                    <input type="text" class="form-control" name="pavadinimas" required data-testid="input-object-name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createObjectModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-object">Sukurti</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editObjectModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Redaguoti objektą</h3>
            <button class="modal-close" onclick="closeModal('editObjectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editObjectId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Objekto pavadinimas</label>
                    <input type="text" class="form-control" name="pavadinimas" id="editObjectName" required data-testid="input-object-name-edit">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editObjectModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-object">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<script>
function editObject(id, name) {
    document.getElementById('editObjectId').value = id;
    document.getElementById('editObjectName').value = name;
    openModal('editObjectModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
