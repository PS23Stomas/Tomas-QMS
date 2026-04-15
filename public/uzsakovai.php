<?php
/**
 * Užsakovų (klientų) valdymo puslapis - CRUD operacijos.
 * Rodo visus užsakovus su susijusių užsakymų skaičiumi.
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Užsakovai';
$message = '';

// POST užklausų apdorojimas: kūrimas, redagavimas, šalinimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Naujo užsakovo sukūrimas
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO uzsakovai (uzsakovas) VALUES (:uzsakovas)');
        $stmt->execute(['uzsakovas' => $_POST['uzsakovas'] ?? '']);
        $message = 'Užsakovas sukurtas sėkmingai.';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE uzsakovai SET uzsakovas = :uzsakovas WHERE id = :id');
        $stmt->execute(['uzsakovas' => $_POST['uzsakovas'] ?? '', 'id' => $_POST['id']]);
        $message = 'Užsakovas atnaujintas.';
    } elseif ($action === 'delete') {
        $user = currentUser();
        if (($user['role'] ?? '') !== 'admin') {
            $error = 'Tik administratorius gali trinti užsakovus.';
        } else {
            $id = $_POST['id'] ?? null;
            if ($id) {
                $pdo->prepare('DELETE FROM uzsakovai WHERE id = :id')->execute(['id' => $id]);
                $message = 'Užsakovas ištrintas.';
            }
        }
    }
}

// Užsakovų sąrašas su susijusių užsakymų skaičiumi
$clients = $pdo->query('
    SELECT uz.*, (SELECT COUNT(*) FROM uzsakymai u WHERE u.uzsakovas_id = uz.id) as uzsakymu_sk
    FROM uzsakovai uz
    ORDER BY uz.uzsakovas
')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Visi užsakovai (<?= count($clients) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createClientModal')" data-testid="button-new-client">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas užsakovas
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="generic-card-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Užsakovas</th>
                        <th>Užsakymų</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients) > 0): ?>
                        <?php foreach ($clients as $c): ?>
                        <tr data-testid="row-client-<?= $c['id'] ?>">
                            <td data-label="ID"><?= $c['id'] ?></td>
                            <td class="gct-cell-title"><?= h($c['uzsakovas']) ?></td>
                            <td data-label="Užsakymų"><span class="badge badge-info"><?= $c['uzsakymu_sk'] ?></span></td>
                            <td class="gct-cell-actions">
                                <div class="actions">
                                    <button class="btn btn-secondary btn-sm" onclick="editClient(<?= $c['id'] ?>, '<?= h(addslashes($c['uzsakovas'])) ?>')" data-testid="button-edit-client-<?= $c['id'] ?>">Redaguoti</button>
                                    <?php if ((currentUser()['role'] ?? '') === 'admin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-client-<?= $c['id'] ?>">Trinti</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty-state"><p>Nėra užsakovų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="createClientModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas užsakovas</h3>
            <button class="modal-close" onclick="closeModal('createClientModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Užsakovo pavadinimas</label>
                    <input type="text" class="form-control" name="uzsakovas" required data-testid="input-client-name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createClientModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-client">Sukurti</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editClientModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Redaguoti užsakovą</h3>
            <button class="modal-close" onclick="closeModal('editClientModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editClientId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Užsakovo pavadinimas</label>
                    <input type="text" class="form-control" name="uzsakovas" id="editClientName" required data-testid="input-client-name-edit">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editClientModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-client">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<script>
function editClient(id, name) {
    document.getElementById('editClientId').value = id;
    document.getElementById('editClientName').value = name;
    openModal('editClientModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
