<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Moduliai';
$user = currentUser();
$isAdmin = (($user['role'] ?? '') === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    if ($action === 'sukurti') {
        $pavadinimas = trim($_POST['pavadinimas'] ?? '');
        if ($pavadinimas !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gaminiu_rusys WHERE pavadinimas = ?");
            $stmt->execute([$pavadinimas]);
            if ((int)$stmt->fetchColumn() === 0) {
                $stmt = $pdo->prepare("INSERT INTO gaminiu_rusys (pavadinimas) VALUES (?)");
                $stmt->execute([$pavadinimas]);
                header('Location: /moduliai.php?sukurta=1');
                exit;
            } else {
                header('Location: /moduliai.php?klaida=' . urlencode('Modulis su tokiu pavadinimu jau egzistuoja'));
                exit;
            }
        }
    }

    if ($action === 'istrinti') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT pavadinimas FROM gaminiu_rusys WHERE id = ?");
            $stmt->execute([$id]);
            $modulis = $stmt->fetchColumn();
            
            if (!$modulis) {
                header('Location: /moduliai.php?klaida=' . urlencode('Modulis nerastas'));
                exit;
            }

            $kliutys = [];

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gaminio_tipai WHERE grupe = ?");
            $stmt->execute([$modulis]);
            if ((int)$stmt->fetchColumn() > 0) $kliutys[] = 'gaminių tipų';

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzsakymai u JOIN gaminiu_rusys gr ON gr.id = u.gaminiu_rusis_id WHERE gr.pavadinimas = ?");
            $stmt->execute([$modulis]);
            if ((int)$stmt->fetchColumn() > 0) $kliutys[] = 'užsakymų';

            if (!empty($kliutys)) {
                $msg = 'Negalima ištrinti modulio, nes jame yra: ' . implode(', ', $kliutys);
                header('Location: /moduliai.php?klaida=' . urlencode($msg));
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM funkciniu_sablonas WHERE gaminiu_rusis_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM gaminiu_rusys WHERE id = ?");
            $stmt->execute([$id]);
            
            if (isset($_SESSION['aktyvus_modulis']) && (int)$_SESSION['aktyvus_modulis'] === $id) {
                unset($_SESSION['aktyvus_modulis']);
                unset($_SESSION['aktyvus_modulis_pav']);
                unset($_SESSION['aktyvus_grupe']);
            }
            header('Location: /moduliai.php?istrinta=1');
            exit;
        }
    }
}

if (isset($_GET['pasirinkti'])) {
    $modulio_id = (int)$_GET['pasirinkti'];
    $stmt = $pdo->prepare("SELECT id, pavadinimas FROM gaminiu_rusys WHERE id = ?");
    $stmt->execute([$modulio_id]);
    $modulis = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($modulis) {
        $_SESSION['aktyvus_modulis'] = (int)$modulis['id'];
        $_SESSION['aktyvus_modulis_pav'] = $modulis['pavadinimas'];
        $_SESSION['aktyvus_grupe'] = $modulis['pavadinimas'];
        header('Location: /index.php?grupe=' . urlencode($modulis['pavadinimas']));
        exit;
    }
}

$moduliai = $pdo->query("SELECT gr.id, gr.pavadinimas, 
    (SELECT COUNT(*) FROM gaminio_tipai gt WHERE gt.grupe = gr.pavadinimas) AS tipu_kiekis,
    (SELECT COUNT(*) FROM uzsakymai u WHERE u.gaminiu_rusis_id = gr.id) AS uzsakymu_kiekis,
    (SELECT COUNT(*) FROM funkciniu_sablonas s WHERE s.gaminiu_rusis_id = gr.id) AS sablonu_kiekis
    FROM gaminiu_rusys gr ORDER BY gr.id")->fetchAll(PDO::FETCH_ASSOC);

$sukurta = $_GET['sukurta'] ?? '';
$istrinta = $_GET['istrinta'] ?? '';
$klaida = $_GET['klaida'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<?php if ($sukurta === '1'): ?>
<div class="alert" style="background:var(--success-light);color:var(--success);padding:10px 16px;border-radius:var(--radius);margin-bottom:12px;font-size:13px;" data-testid="alert-success">
    Naujas modulis sėkmingai sukurtas!
</div>
<?php endif; ?>
<?php if ($istrinta === '1'): ?>
<div class="alert" style="background:var(--success-light);color:var(--success);padding:10px 16px;border-radius:var(--radius);margin-bottom:12px;font-size:13px;" data-testid="alert-deleted">
    Modulis ištrintas.
</div>
<?php endif; ?>
<?php if ($klaida): ?>
<div class="alert" style="background:#fef2f2;color:#dc2626;padding:10px 16px;border-radius:var(--radius);margin-bottom:12px;font-size:13px;" data-testid="alert-error">
    <?= h($klaida) ?>
</div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <h2 data-testid="text-page-title-modules">Gamybos moduliai</h2>
    <?php if ($isAdmin): ?>
    <button class="btn btn-primary" onclick="openModal('createModuleModal')" data-testid="button-create-module" style="display:inline-flex;align-items:center;gap:5px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Naujas modulis
    </button>
    <?php endif; ?>
</div>

<p style="color:var(--text-secondary);font-size:14px;margin-bottom:20px;">
    Pasirinkite modulį, kad matytumėte jo užsakymus, kokybės rodiklius ir tikrinimo šabloną.
</p>

<div class="modules-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:16px;">
    <?php foreach ($moduliai as $m): ?>
    <?php $is_aktyvus = (isset($_SESSION['aktyvus_modulis']) && (int)$_SESSION['aktyvus_modulis'] === (int)$m['id']); ?>
    <div class="module-card" style="background:var(--bg-card);border:2px solid <?= $is_aktyvus ? 'var(--primary)' : 'var(--border)' ?>;border-radius:var(--radius-lg);padding:20px;cursor:pointer;transition:all 0.2s;position:relative;" 
         onclick="window.location='/moduliai.php?pasirinkti=<?= $m['id'] ?>'" data-testid="module-card-<?= h($m['pavadinimas']) ?>">
        
        <?php if ($is_aktyvus): ?>
        <div style="position:absolute;top:10px;right:10px;background:var(--primary);color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600;">AKTYVUS</div>
        <?php endif; ?>
        
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:48px;height:48px;border-radius:var(--radius);background:<?= $is_aktyvus ? 'var(--primary)' : 'var(--bg-secondary)' ?>;display:flex;align-items:center;justify-content:center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?= $is_aktyvus ? '#fff' : 'var(--text-secondary)' ?>" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary);"><?= h($m['pavadinimas']) ?></div>
                <div style="font-size:12px;color:var(--text-secondary);">Gaminių tipų: <?= (int)$m['tipu_kiekis'] ?></div>
            </div>
        </div>
        
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px;">
            <div style="display:flex;gap:16px;">
                <div><span style="font-weight:600;color:var(--text-primary);"><?= (int)$m['uzsakymu_kiekis'] ?></span> užsakymų</div>
                <div><span style="font-weight:600;color:var(--text-primary);"><?= (int)$m['sablonu_kiekis'] ?></span> šablonų</div>
            </div>
            <?php 
            $tusti = ((int)$m['tipu_kiekis'] === 0 && (int)$m['uzsakymu_kiekis'] === 0 && (int)$m['sablonu_kiekis'] === 0);
            if ($isAdmin && $tusti): ?>
            <button class="btn-delete-module" onclick="event.stopPropagation(); deleteModule(<?= (int)$m['id'] ?>, '<?= h($m['pavadinimas']) ?>')" 
                    data-testid="button-delete-module-<?= h($m['pavadinimas']) ?>"
                    title="Ištrinti modulį">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($isAdmin): ?>
<div class="modal-overlay" id="createModuleModal" data-testid="modal-create-module">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3>Naujas modulis</h3>
            <button class="modal-close" onclick="closeModal('createModuleModal')" aria-label="Uždaryti">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="sukurti">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Modulio pavadinimas</label>
                    <input type="text" class="form-control" name="pavadinimas" required placeholder="pvz. USN, SI-04, GVX..." data-testid="input-module-name" autofocus>
                </div>
                <p style="font-size:12px;color:var(--text-secondary);margin-top:8px;">
                    Sukūrus naują modulį, jis atsiras modulių sąraše. Galėsite jį pasirinkti ir valdyti jo užsakymus bei kokybės rodiklius.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModuleModal')" data-testid="button-cancel-module">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-module">Sukurti</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<form id="deleteModuleForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="istrinti">
    <input type="hidden" name="id" id="deleteModuleId" value="">
</form>

<style>
.module-card:hover {
    border-color: var(--primary) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.btn-delete-module {
    background: none;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 6px;
    cursor: pointer;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    transition: all 0.15s;
}
.btn-delete-module:hover {
    color: #dc2626;
    border-color: #dc2626;
    background: #fef2f2;
}
</style>

<script>
function deleteModule(id, name) {
    if (confirm('Ar tikrai norite ištrinti modulį "' + name + '"?\n\nŠis veiksmas negrįžtamas.')) {
        document.getElementById('deleteModuleId').value = id;
        document.getElementById('deleteModuleForm').submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
