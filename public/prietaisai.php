<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Prietaisų patikra';
$user = currentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO prietaisai (vidinis_kodas, pavadinimas, gamintojas, modelis, serijos_nr, matavimo_tipas, matavimo_ribos, tikslumo_klase, busena, vieta, atsakingas_asmuo, kalibracijos_sertifikato_nr, kalibravimo_istaiga, kalibravimo_data, galiojimo_pabaiga, kita_kalibracija, standartas_metodika, pastabos, sukurta) VALUES (:vidinis_kodas, :pavadinimas, :gamintojas, :modelis, :serijos_nr, :matavimo_tipas, :matavimo_ribos, :tikslumo_klase, :busena, :vieta, :atsakingas_asmuo, :kalibracijos_sertifikato_nr, :kalibravimo_istaiga, :kalibravimo_data, :galiojimo_pabaiga, :kita_kalibracija, :standartas_metodika, :pastabos, CURRENT_TIMESTAMP)");
        $stmt->execute([
            'vidinis_kodas' => $_POST['vidinis_kodas'] ?? '',
            'pavadinimas' => $_POST['pavadinimas'] ?? '',
            'gamintojas' => $_POST['gamintojas'] ?? '',
            'modelis' => $_POST['modelis'] ?? '',
            'serijos_nr' => $_POST['serijos_nr'] ?? '',
            'matavimo_tipas' => $_POST['matavimo_tipas'] ?? '',
            'matavimo_ribos' => $_POST['matavimo_ribos'] ?? '',
            'tikslumo_klase' => $_POST['tikslumo_klase'] ?? '',
            'busena' => $_POST['busena'] ?? 'naudojamas',
            'vieta' => $_POST['vieta'] ?? '',
            'atsakingas_asmuo' => $_POST['atsakingas_asmuo'] ?? '',
            'kalibracijos_sertifikato_nr' => $_POST['kalibracijos_sertifikato_nr'] ?? '',
            'kalibravimo_istaiga' => $_POST['kalibravimo_istaiga'] ?? '',
            'kalibravimo_data' => $_POST['kalibravimo_data'] ?: null,
            'galiojimo_pabaiga' => $_POST['galiojimo_pabaiga'] ?: null,
            'kita_kalibracija' => $_POST['kita_kalibracija'] ?: null,
            'standartas_metodika' => $_POST['standartas_metodika'] ?? '',
            'pastabos' => $_POST['pastabos'] ?? '',
        ]);
        $message = 'Prietaisas pridėtas sėkmingai.';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE prietaisai SET vidinis_kodas = :vidinis_kodas, pavadinimas = :pavadinimas, gamintojas = :gamintojas, modelis = :modelis, serijos_nr = :serijos_nr, matavimo_tipas = :matavimo_tipas, matavimo_ribos = :matavimo_ribos, tikslumo_klase = :tikslumo_klase, busena = :busena, vieta = :vieta, atsakingas_asmuo = :atsakingas_asmuo, kalibracijos_sertifikato_nr = :kalibracijos_sertifikato_nr, kalibravimo_istaiga = :kalibravimo_istaiga, kalibravimo_data = :kalibravimo_data, galiojimo_pabaiga = :galiojimo_pabaiga, kita_kalibracija = :kita_kalibracija, standartas_metodika = :standartas_metodika, pastabos = :pastabos, atnaujinta = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([
            'vidinis_kodas' => $_POST['vidinis_kodas'] ?? '',
            'pavadinimas' => $_POST['pavadinimas'] ?? '',
            'gamintojas' => $_POST['gamintojas'] ?? '',
            'modelis' => $_POST['modelis'] ?? '',
            'serijos_nr' => $_POST['serijos_nr'] ?? '',
            'matavimo_tipas' => $_POST['matavimo_tipas'] ?? '',
            'matavimo_ribos' => $_POST['matavimo_ribos'] ?? '',
            'tikslumo_klase' => $_POST['tikslumo_klase'] ?? '',
            'busena' => $_POST['busena'] ?? 'naudojamas',
            'vieta' => $_POST['vieta'] ?? '',
            'atsakingas_asmuo' => $_POST['atsakingas_asmuo'] ?? '',
            'kalibracijos_sertifikato_nr' => $_POST['kalibracijos_sertifikato_nr'] ?? '',
            'kalibravimo_istaiga' => $_POST['kalibravimo_istaiga'] ?? '',
            'kalibravimo_data' => $_POST['kalibravimo_data'] ?: null,
            'galiojimo_pabaiga' => $_POST['galiojimo_pabaiga'] ?: null,
            'kita_kalibracija' => $_POST['kita_kalibracija'] ?: null,
            'standartas_metodika' => $_POST['standartas_metodika'] ?? '',
            'pastabos' => $_POST['pastabos'] ?? '',
            'id' => $_POST['id'],
        ]);
        $message = 'Prietaisas atnaujintas.';
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $pdo->prepare("DELETE FROM prietaisai WHERE id = :id")->execute(['id' => $id]);
            $message = 'Prietaisas ištrintas.';
        }
    }
}

$view_id = $_GET['id'] ?? null;
$view_device = null;

if ($view_id) {
    $stmt = $pdo->prepare("SELECT * FROM prietaisai WHERE id = :id");
    $stmt->execute(['id' => $view_id]);
    $view_device = $stmt->fetch();
}

$filter_busena = $_GET['busena'] ?? '';
$where = '';
$params = [];
if ($filter_busena) {
    $where = ' WHERE busena = :busena';
    $params['busena'] = $filter_busena;
}

$devices_stmt = $pdo->prepare("SELECT * FROM prietaisai $where ORDER BY vidinis_kodas");
$devices_stmt->execute($params);
$devices = $devices_stmt->fetchAll();

$today = date('Y-m-d');
$soon_date = date('Y-m-d', strtotime('+30 days'));
$expired_count = 0;
$expiring_count = 0;
$valid_count = 0;
foreach ($devices as $d) {
    if ($d['galiojimo_pabaiga']) {
        if ($d['galiojimo_pabaiga'] < $today) $expired_count++;
        elseif ($d['galiojimo_pabaiga'] <= $soon_date) $expiring_count++;
        else $valid_count++;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<?php if ($view_id && $view_device): ?>
<div style="margin-bottom: 16px;">
    <a href="/prietaisai.php" class="btn btn-secondary btn-sm" data-testid="button-back-devices">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Atgal
    </a>
</div>

<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <span class="card-title"><?= h($view_device['pavadinimas']) ?> (<?= h($view_device['vidinis_kodas']) ?>)</span>
        <div class="actions">
            <?php
                $cal_status = '';
                $cal_badge = 'badge-info';
                if ($view_device['galiojimo_pabaiga']) {
                    if ($view_device['galiojimo_pabaiga'] < $today) {
                        $cal_status = 'Pasibaigęs';
                        $cal_badge = 'badge-danger';
                    } elseif ($view_device['galiojimo_pabaiga'] <= $soon_date) {
                        $cal_status = 'Baigiasi greitai';
                        $cal_badge = 'badge-warning';
                    } else {
                        $cal_status = 'Galioja';
                        $cal_badge = 'badge-success';
                    }
                }
            ?>
            <?php if ($cal_status): ?>
            <span class="badge <?= $cal_badge ?>"><?= $cal_status ?></span>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" onclick="openModal('editDeviceModal')" data-testid="button-edit-device">Redaguoti</button>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <p><strong>Vidinis kodas:</strong> <?= h($view_device['vidinis_kodas']) ?></p>
                <p><strong>Pavadinimas:</strong> <?= h($view_device['pavadinimas']) ?></p>
                <p><strong>Gamintojas:</strong> <?= h($view_device['gamintojas'] ?: '-') ?></p>
                <p><strong>Modelis:</strong> <?= h($view_device['modelis'] ?: '-') ?></p>
                <p><strong>Serijos Nr.:</strong> <?= h($view_device['serijos_nr'] ?: '-') ?></p>
                <p><strong>Matavimo tipas:</strong> <?= h($view_device['matavimo_tipas'] ?: '-') ?></p>
                <p><strong>Matavimo ribos:</strong> <?= h($view_device['matavimo_ribos'] ?: '-') ?></p>
                <p><strong>Tikslumo klasė:</strong> <?= h($view_device['tikslumo_klase'] ?: '-') ?></p>
            </div>
            <div>
                <p><strong>Būsena:</strong> <?= h($view_device['busena'] ?: '-') ?></p>
                <p><strong>Vieta:</strong> <?= h($view_device['vieta'] ?: '-') ?></p>
                <p><strong>Atsakingas asmuo:</strong> <?= h($view_device['atsakingas_asmuo'] ?: '-') ?></p>
                <p><strong>Kalibravimo sertifikato Nr.:</strong> <?= h($view_device['kalibracijos_sertifikato_nr'] ?: '-') ?></p>
                <p><strong>Kalibravimo įstaiga:</strong> <?= h($view_device['kalibravimo_istaiga'] ?: '-') ?></p>
                <p><strong>Kalibravimo data:</strong> <?= h($view_device['kalibravimo_data'] ?: '-') ?></p>
                <p><strong>Galiojimo pabaiga:</strong> <?= h($view_device['galiojimo_pabaiga'] ?: '-') ?></p>
                <p><strong>Kita kalibracija:</strong> <?= h($view_device['kita_kalibracija'] ?: '-') ?></p>
            </div>
        </div>
        <?php if ($view_device['standartas_metodika']): ?>
        <div style="margin-top: 16px;">
            <p><strong>Standartas / Metodika:</strong></p>
            <p style="color: var(--text-secondary);"><?= h($view_device['standartas_metodika']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($view_device['pastabos']): ?>
        <div style="margin-top: 16px;">
            <p><strong>Pastabos:</strong></p>
            <p style="color: var(--text-secondary);"><?= h($view_device['pastabos']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="editDeviceModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Redaguoti prietaisą</h3>
            <button class="modal-close" onclick="closeModal('editDeviceModal')">&times;</button>
        </div>
        <form method="POST" action="/prietaisai.php?id=<?= $view_device['id'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $view_device['id'] ?>">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Vidinis kodas *</label>
                        <input type="text" class="form-control" name="vidinis_kodas" value="<?= h($view_device['vidinis_kodas']) ?>" required data-testid="input-device-code-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pavadinimas *</label>
                        <input type="text" class="form-control" name="pavadinimas" value="<?= h($view_device['pavadinimas']) ?>" required data-testid="input-device-name-edit">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Gamintojas</label>
                        <input type="text" class="form-control" name="gamintojas" value="<?= h($view_device['gamintojas'] ?? '') ?>" data-testid="input-device-manufacturer-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modelis</label>
                        <input type="text" class="form-control" name="modelis" value="<?= h($view_device['modelis'] ?? '') ?>" data-testid="input-device-model-edit">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Serijos Nr.</label>
                        <input type="text" class="form-control" name="serijos_nr" value="<?= h($view_device['serijos_nr'] ?? '') ?>" data-testid="input-device-serial-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Būsena</label>
                        <select class="form-control" name="busena" data-testid="select-device-status-edit">
                            <?php foreach (['naudojamas' => 'Naudojamas', 'remontuojamas' => 'Remontuojamas', 'nenaudojamas' => 'Nenaudojamas', 'nurašytas' => 'Nurašytas'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $view_device['busena'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Matavimo tipas</label>
                        <input type="text" class="form-control" name="matavimo_tipas" value="<?= h($view_device['matavimo_tipas'] ?? '') ?>" data-testid="input-device-measurement-type-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tikslumo klasė</label>
                        <input type="text" class="form-control" name="tikslumo_klase" value="<?= h($view_device['tikslumo_klase'] ?? '') ?>" data-testid="input-device-accuracy-edit">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Matavimo ribos</label>
                    <input type="text" class="form-control" name="matavimo_ribos" value="<?= h($view_device['matavimo_ribos'] ?? '') ?>" data-testid="input-device-limits-edit">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Vieta</label>
                        <input type="text" class="form-control" name="vieta" value="<?= h($view_device['vieta'] ?? '') ?>" data-testid="input-device-location-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atsakingas asmuo</label>
                        <input type="text" class="form-control" name="atsakingas_asmuo" value="<?= h($view_device['atsakingas_asmuo'] ?? '') ?>" data-testid="input-device-responsible-edit">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kalibravimo sert. Nr.</label>
                        <input type="text" class="form-control" name="kalibracijos_sertifikato_nr" value="<?= h($view_device['kalibracijos_sertifikato_nr'] ?? '') ?>" data-testid="input-device-cert-nr-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kalibravimo įstaiga</label>
                        <input type="text" class="form-control" name="kalibravimo_istaiga" value="<?= h($view_device['kalibravimo_istaiga'] ?? '') ?>" data-testid="input-device-cert-org-edit">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kalibravimo data</label>
                        <input type="date" class="form-control" name="kalibravimo_data" value="<?= h($view_device['kalibravimo_data'] ?? '') ?>" data-testid="input-device-cal-date-edit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Galiojimo pabaiga</label>
                        <input type="date" class="form-control" name="galiojimo_pabaiga" value="<?= h($view_device['galiojimo_pabaiga'] ?? '') ?>" data-testid="input-device-expiry-edit">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kita kalibracija</label>
                    <input type="date" class="form-control" name="kita_kalibracija" value="<?= h($view_device['kita_kalibracija'] ?? '') ?>" data-testid="input-device-next-cal-edit">
                </div>
                <div class="form-group">
                    <label class="form-label">Standartas / Metodika</label>
                    <textarea class="form-control" name="standartas_metodika" rows="2" data-testid="input-device-standard-edit"><?= h($view_device['standartas_metodika'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Pastabos</label>
                    <textarea class="form-control" name="pastabos" rows="2" data-testid="input-device-notes-edit"><?= h($view_device['pastabos'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editDeviceModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-device">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="stats-summary" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Viso prietaisų</span>
            <div class="stat-icon blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-devices-total"><?= count($devices) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Galioja</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-devices-valid"><?= $valid_count ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Baigiasi greitai</span>
            <div class="stat-icon orange">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-devices-expiring"><?= $expiring_count ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Pasibaigę</span>
            <div class="stat-icon red">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
        </div>
        <div class="stat-value" data-testid="text-devices-expired"><?= $expired_count ?></div>
    </div>
</div>

<div class="filter-bar">
    <div class="form-group">
        <label class="form-label">Filtruoti pagal būseną</label>
        <select class="form-control" onchange="window.location.href='/prietaisai.php' + (this.value ? '?busena=' + this.value : '')" data-testid="select-filter-device-status">
            <option value="">Visi</option>
            <option value="naudojamas" <?= $filter_busena === 'naudojamas' ? 'selected' : '' ?>>Naudojamas</option>
            <option value="remontuojamas" <?= $filter_busena === 'remontuojamas' ? 'selected' : '' ?>>Remontuojamas</option>
            <option value="nenaudojamas" <?= $filter_busena === 'nenaudojamas' ? 'selected' : '' ?>>Nenaudojamas</option>
            <option value="nurašytas" <?= $filter_busena === 'nurašytas' ? 'selected' : '' ?>>Nurašytas</option>
        </select>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Prietaisai (<?= count($devices) ?>)</span>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <button class="btn btn-primary btn-sm" onclick="openModal('createDeviceModal')" data-testid="button-new-device">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas prietaisas
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Kodas</th>
                        <th>Pavadinimas</th>
                        <th>Gamintojas</th>
                        <th>Serijos Nr.</th>
                        <th>Būsena</th>
                        <th>Kalibravimas</th>
                        <th>Galioja iki</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($devices) > 0): ?>
                        <?php foreach ($devices as $d): ?>
                        <?php
                            $busena_badge = 'badge-success';
                            $busena_labels = ['naudojamas' => 'Naudojamas', 'remontuojamas' => 'Remontuojamas', 'nenaudojamas' => 'Nenaudojamas', 'nurašytas' => 'Nurašytas'];
                            if ($d['busena'] === 'remontuojamas') $busena_badge = 'badge-warning';
                            elseif ($d['busena'] === 'nenaudojamas' || $d['busena'] === 'nurašytas') $busena_badge = 'badge-danger';

                            $cal_badge = '';
                            $cal_text = '-';
                            if ($d['galiojimo_pabaiga']) {
                                if ($d['galiojimo_pabaiga'] < $today) {
                                    $cal_badge = 'badge-danger';
                                    $cal_text = 'Pasibaigęs';
                                } elseif ($d['galiojimo_pabaiga'] <= $soon_date) {
                                    $cal_badge = 'badge-warning';
                                    $cal_text = 'Baigiasi';
                                } else {
                                    $cal_badge = 'badge-success';
                                    $cal_text = 'Galioja';
                                }
                            }
                        ?>
                        <tr data-testid="row-device-<?= $d['id'] ?>">
                            <td style="font-weight: 500;"><a href="/prietaisai.php?id=<?= $d['id'] ?>" style="color: var(--primary);" data-testid="link-device-<?= $d['id'] ?>"><?= h($d['vidinis_kodas']) ?></a></td>
                            <td><?= h($d['pavadinimas']) ?></td>
                            <td><?= h($d['gamintojas'] ?: '-') ?></td>
                            <td style="color: var(--text-secondary);"><?= h($d['serijos_nr'] ?: '-') ?></td>
                            <td><span class="badge <?= $busena_badge ?>"><?= h($busena_labels[$d['busena']] ?? $d['busena'] ?? '-') ?></span></td>
                            <td><?php if ($cal_badge): ?><span class="badge <?= $cal_badge ?>"><?= $cal_text ?></span><?php else: ?>-<?php endif; ?></td>
                            <td style="color: var(--text-secondary);"><?= h($d['galiojimo_pabaiga'] ?: '-') ?></td>
                            <td>
                                <div class="actions">
                                    <a href="/prietaisai.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm" data-testid="button-view-device-<?= $d['id'] ?>">Peržiūrėti</a>
                                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti šį prietaisą?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-device-<?= $d['id'] ?>">Trinti</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state"><p>Nėra prietaisų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (($user['role'] ?? '') === 'admin'): ?>
<div class="modal-overlay" id="createDeviceModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Naujas prietaisas</h3>
            <button class="modal-close" onclick="closeModal('createDeviceModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Vidinis kodas *</label>
                        <input type="text" class="form-control" name="vidinis_kodas" required data-testid="input-device-code">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pavadinimas *</label>
                        <input type="text" class="form-control" name="pavadinimas" required data-testid="input-device-name">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Gamintojas</label>
                        <input type="text" class="form-control" name="gamintojas" data-testid="input-device-manufacturer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modelis</label>
                        <input type="text" class="form-control" name="modelis" data-testid="input-device-model">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Serijos Nr.</label>
                        <input type="text" class="form-control" name="serijos_nr" data-testid="input-device-serial">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Būsena</label>
                        <select class="form-control" name="busena" data-testid="select-device-status">
                            <option value="naudojamas" selected>Naudojamas</option>
                            <option value="remontuojamas">Remontuojamas</option>
                            <option value="nenaudojamas">Nenaudojamas</option>
                            <option value="nurašytas">Nurašytas</option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Matavimo tipas</label>
                        <input type="text" class="form-control" name="matavimo_tipas" data-testid="input-device-measurement-type">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tikslumo klasė</label>
                        <input type="text" class="form-control" name="tikslumo_klase" data-testid="input-device-accuracy">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Matavimo ribos</label>
                    <input type="text" class="form-control" name="matavimo_ribos" data-testid="input-device-limits">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Vieta</label>
                        <input type="text" class="form-control" name="vieta" data-testid="input-device-location">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atsakingas asmuo</label>
                        <input type="text" class="form-control" name="atsakingas_asmuo" data-testid="input-device-responsible">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kalibravimo sert. Nr.</label>
                        <input type="text" class="form-control" name="kalibracijos_sertifikato_nr" data-testid="input-device-cert-nr">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kalibravimo įstaiga</label>
                        <input type="text" class="form-control" name="kalibravimo_istaiga" data-testid="input-device-cert-org">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kalibravimo data</label>
                        <input type="date" class="form-control" name="kalibravimo_data" data-testid="input-device-cal-date">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Galiojimo pabaiga</label>
                        <input type="date" class="form-control" name="galiojimo_pabaiga" data-testid="input-device-expiry">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kita kalibracija</label>
                    <input type="date" class="form-control" name="kita_kalibracija" data-testid="input-device-next-cal">
                </div>
                <div class="form-group">
                    <label class="form-label">Standartas / Metodika</label>
                    <textarea class="form-control" name="standartas_metodika" rows="2" data-testid="input-device-standard"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Pastabos</label>
                    <textarea class="form-control" name="pastabos" rows="2" data-testid="input-device-notes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createDeviceModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-device">Sukurti</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
