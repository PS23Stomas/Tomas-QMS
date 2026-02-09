<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Vartotojų valdymas';
$user = currentUser();

if (($user['role'] ?? '') !== 'admin') {
    header('Location: /index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $el_pastas = trim($_POST['el_pastas'] ?? '');
        $existing = $pdo->prepare("SELECT id FROM vartotojai WHERE el_pastas = :el");
        $existing->execute(['el' => $el_pastas]);
        if ($existing->fetch()) {
            $error = 'Vartotojas su šiuo el. paštu jau egzistuoja.';
        } else {
            $slaptazodis = password_hash($_POST['slaptazodis'] ?? '', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO vartotojai (vardas, pavarde, el_pastas, slaptazodis, role, patvirtintas, patvirtino_id, patvirtinimo_data) VALUES (:vardas, :pavarde, :el_pastas, :slaptazodis, :role, true, :patvirtino_id, CURRENT_TIMESTAMP)");
            $stmt->execute([
                'vardas' => $_POST['vardas'] ?? '',
                'pavarde' => $_POST['pavarde'] ?? '',
                'el_pastas' => $el_pastas,
                'slaptazodis' => $slaptazodis,
                'role' => $_POST['role'] ?? 'user',
                'patvirtino_id' => $_SESSION['user_id'],
            ]);
            $message = 'Vartotojas sukurtas sėkmingai.';
        }
    } elseif ($action === 'update') {
        $fields = "vardas = :vardas, pavarde = :pavarde, el_pastas = :el_pastas, role = :role";
        $params = [
            'vardas' => $_POST['vardas'] ?? '',
            'pavarde' => $_POST['pavarde'] ?? '',
            'el_pastas' => $_POST['el_pastas'] ?? '',
            'role' => $_POST['role'] ?? 'user',
            'id' => $_POST['id'],
        ];

        if (!empty($_POST['slaptazodis'])) {
            $fields .= ", slaptazodis = :slaptazodis";
            $params['slaptazodis'] = password_hash($_POST['slaptazodis'], PASSWORD_BCRYPT);
        }

        $stmt = $pdo->prepare("UPDATE vartotojai SET $fields WHERE id = :id");
        $stmt->execute($params);
        $message = 'Vartotojas atnaujintas.';
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id && $id != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM vartotojai WHERE id = :id")->execute(['id' => $id]);
            $message = 'Vartotojas ištrintas.';
        } else {
            $error = 'Negalite ištrinti savo paskyros.';
        }
    } elseif ($action === 'toggle_confirm') {
        $id = $_POST['id'] ?? null;
        $confirmed = $_POST['patvirtintas'] === '1' ? true : false;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE vartotojai SET patvirtintas = :patvirtintas, patvirtino_id = :patvirtino_id, patvirtinimo_data = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([
                'patvirtintas' => $confirmed ? 'true' : 'false',
                'patvirtino_id' => $_SESSION['user_id'],
                'id' => $id,
            ]);
            $message = $confirmed ? 'Vartotojas patvirtintas.' : 'Vartotojo patvirtinimas atšauktas.';
        }
    }
}

$users = $pdo->query("SELECT v.*, p.vardas as patvirtino_vardas, p.pavarde as patvirtino_pavarde FROM vartotojai v LEFT JOIN vartotojai p ON v.patvirtino_id = p.id ORDER BY v.id")->fetchAll();

$role_counts = $pdo->query("SELECT role, COUNT(*) as cnt FROM vartotojai GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="stats-summary" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Viso vartotojų</span>
            <div class="stat-icon blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-users-total"><?= count($users) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Administratoriai</span>
            <div class="stat-icon red">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-users-admin"><?= $role_counts['admin'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Vartotojai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-users-regular"><?= $role_counts['user'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Skaitytojai</span>
            <div class="stat-icon cyan">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-users-readers"><?= $role_counts['skaitytojas'] ?? 0 ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Vartotojai (<?= count($users) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createUserModal')" data-testid="button-new-user">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas vartotojas
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Vardas</th>
                        <th>Pavardė</th>
                        <th>El. paštas</th>
                        <th>Rolė</th>
                        <th>Patvirtintas</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <?php
                        $role_badge = 'badge-info';
                        $role_labels = ['admin' => 'Administratorius', 'user' => 'Vartotojas', 'skaitytojas' => 'Skaitytojas'];
                        if ($u['role'] === 'admin') $role_badge = 'badge-danger';
                        elseif ($u['role'] === 'user') $role_badge = 'badge-primary';
                    ?>
                    <tr data-testid="row-user-<?= $u['id'] ?>">
                        <td style="font-weight: 500;"><?= h($u['vardas'] ?? '-') ?></td>
                        <td><?= h($u['pavarde'] ?? '-') ?></td>
                        <td style="color: var(--text-secondary);"><?= h($u['el_pastas'] ?? '-') ?></td>
                        <td><span class="badge <?= $role_badge ?>"><?= h($role_labels[$u['role']] ?? $u['role'] ?? '-') ?></span></td>
                        <td>
                            <?php if ($u['patvirtintas']): ?>
                                <span class="badge badge-success">Taip</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Ne</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-secondary btn-sm" onclick='editUser(<?= json_encode($u) ?>)' data-testid="button-edit-user-<?= $u['id'] ?>">Redaguoti</button>
                                <?php if (!$u['patvirtintas']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_confirm">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="patvirtintas" value="1">
                                    <button type="submit" class="btn btn-primary btn-sm" data-testid="button-confirm-user-<?= $u['id'] ?>">Patvirtinti</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti šį vartotoją?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-user-<?= $u['id'] ?>">Trinti</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas vartotojas</h3>
            <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Vardas *</label>
                        <input type="text" class="form-control" name="vardas" required data-testid="input-user-name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pavardė *</label>
                        <input type="text" class="form-control" name="pavarde" required data-testid="input-user-surname">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">El. paštas *</label>
                    <input type="email" class="form-control" name="el_pastas" required data-testid="input-user-email">
                </div>
                <div class="form-group">
                    <label class="form-label">Slaptažodis *</label>
                    <input type="password" class="form-control" name="slaptazodis" required minlength="6" data-testid="input-user-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Rolė</label>
                    <select class="form-control" name="role" data-testid="select-user-role">
                        <option value="user">Vartotojas</option>
                        <option value="admin">Administratorius</option>
                        <option value="skaitytojas">Skaitytojas</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-user">Sukurti</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Redaguoti vartotoją</h3>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_user_id">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Vardas *</label>
                        <input type="text" class="form-control" name="vardas" id="edit_user_vardas" required data-testid="input-user-name-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pavardė *</label>
                        <input type="text" class="form-control" name="pavarde" id="edit_user_pavarde" required data-testid="input-user-surname-edit">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">El. paštas *</label>
                    <input type="email" class="form-control" name="el_pastas" id="edit_user_el_pastas" required data-testid="input-user-email-edit">
                </div>
                <div class="form-group">
                    <label class="form-label">Naujas slaptažodis (palikite tuščią jei nekeičiate)</label>
                    <input type="password" class="form-control" name="slaptazodis" minlength="6" data-testid="input-user-password-edit">
                </div>
                <div class="form-group">
                    <label class="form-label">Rolė</label>
                    <select class="form-control" name="role" id="edit_user_role" data-testid="select-user-role-edit">
                        <option value="user">Vartotojas</option>
                        <option value="admin">Administratorius</option>
                        <option value="skaitytojas">Skaitytojas</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-user">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('edit_user_id').value = u.id;
    document.getElementById('edit_user_vardas').value = u.vardas || '';
    document.getElementById('edit_user_pavarde').value = u.pavarde || '';
    document.getElementById('edit_user_el_pastas').value = u.el_pastas || '';
    document.getElementById('edit_user_role').value = u.role || 'user';
    openModal('editUserModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
