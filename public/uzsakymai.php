<?php
/**
 * Užsakymų valdymo puslapis - kūrimas, peržiūra, redagavimas, šalinimas
 *
 * Funkcionalumas:
 * - Užsakymų sąrašo atvaizdavimas su paieška
 * - Naujo užsakymo kūrimas (create)
 * - Užsakymo redagavimas ir MT gaminio pavadinimo atnaujinimas (update)
 * - Užsakymo trynimas kartu su susijusiais gaminiais (delete)
 * - Detalus užsakymo peržiūros rodinys su MT gaminių navigacijos kortelėmis
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Užsakymai';

// Gaunami užsakovų ir objektų sąrašai formų išskleidžiamiesiems meniu
$clients = $pdo->query('SELECT id, uzsakovas FROM uzsakovai ORDER BY uzsakovas')->fetchAll();
$objects = $pdo->query('SELECT id, pavadinimas FROM objektai ORDER BY pavadinimas')->fetchAll();

$message = '';
$error = '';

// POST veiksmų apdorojimas: kūrimas, atnaujinimas arba trynimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Naujo užsakymo kūrimas
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
    // Užsakymo duomenų atnaujinimas ir MT gaminio pavadinimo įrašymas
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE uzsakymai SET uzsakymo_numeris = :nr, kiekis = :kiekis, uzsakovas_id = :uzsakovas_id, objektas_id = :objektas_id WHERE id = :id');
        $stmt->execute([
            'nr' => $_POST['uzsakymo_numeris'] ?? '',
            'kiekis' => $_POST['kiekis'] ?: null,
            'uzsakovas_id' => $_POST['uzsakovas_id'] ?: null,
            'objektas_id' => $_POST['objektas_id'] ?: null,
            'id' => $_POST['id'],
        ]);
        // MT gaminio pilno pavadinimo atnaujinimas (jei nurodytas)
        $mt_pav = trim($_POST['pilnas_pavadinimas'] ?? '');
        $uzs_nr = trim($_POST['uzsakymo_numeris'] ?? '');
        if ($mt_pav !== '' && $uzs_nr !== '') {
            $gh = new Gaminys($pdo);
            $gh->irasytiPilnaPavadinima($uzs_nr, $mt_pav);
        }
        $message = 'Užsakymas atnaujintas.';
    // Užsakymo trynimas kartu su visais susijusiais gaminiais
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        if ($id) {
            $pdo->prepare('DELETE FROM gaminiai WHERE uzsakymo_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM uzsakymai WHERE id = :id')->execute(['id' => $id]);
            $message = 'Užsakymas ištrintas.';
        }
    }
}

// Užsakymo detalios peržiūros režimas (kai perduodamas ?id= parametras)
$view_id = $_GET['id'] ?? null;

if ($view_id) {
    // Užklausa: užsakymo informacija su užsakovu, objektu ir kūrėju
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

    // Gaunami visi gaminiai, priklausantys šiam užsakymui
    $order_products = Gaminys::gautiPagalUzsakyma($pdo, (int)$view_id);

    if ($order) {
        $gaminys_helper = new Gaminys($pdo);
        $uzsakymo_nr = $order['uzsakymo_numeris'] ?? '';
        $uzsakovas_name = $order['uzsakovas'] ?? '';

        // Gaunamas esamas MT gaminio pilnas pavadinimas
        $esamas_pavadinimas = $gaminys_helper->gautiPilnaPavadinima($uzsakymo_nr);

        // MT gaminio ID nustatymas navigacijos kortelėms (pirmiausia ieškoma gaminiai lentelėje)
        $gaminio_id_mt = 0;
        if ($uzsakymo_nr !== '') {
            $st = $pdo->prepare("SELECT g.id FROM gaminiai g JOIN uzsakymai u ON u.id = g.uzsakymo_id WHERE TRIM(u.uzsakymo_numeris) = TRIM(:nr) ORDER BY g.id DESC LIMIT 1");
            $st->execute([':nr' => $uzsakymo_nr]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $gaminio_id_mt = (int)$row['id'];
            }
        }
        // Antrinis bandymas: ieškoma per mt_funkciniai_bandymai lentelę
        if ($gaminio_id_mt === 0 && $uzsakymo_nr !== '') {
            $st = $pdo->prepare("SELECT m.gaminio_id FROM mt_funkciniai_bandymai m JOIN gaminiai g ON g.id = m.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id WHERE TRIM(u.uzsakymo_numeris) = TRIM(:nr) ORDER BY m.id DESC LIMIT 1");
            $st->execute([':nr' => $uzsakymo_nr]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $gaminio_id_mt = (int)$row['gaminio_id'];
            }
        }
    }
}

// Visų užsakymų sąrašo užklausa (su gaminių skaičiumi)
$orders = $pdo->query('
    SELECT u.*, uz.uzsakovas, o.pavadinimas as objektas, v.vardas, v.pavarde,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id) as gaminiu_sk,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id AND g.mt_paso_failas IS NOT NULL) as paso_pdf_sk
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

<?php
    $first_product = $order_products[0] ?? null;
?>
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <span class="card-title">Užsakymas: <?= h($order['uzsakymo_numeris'] ?: 'Be nr.') ?></span>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <p><strong>Užsakymo Nr.:</strong> <?= h($order['uzsakymo_numeris'] ?: '-') ?></p>
                <p><strong>Užsakovas:</strong> <?= h($order['uzsakovas'] ?? '-') ?></p>
                <p><strong>Objektas:</strong> <?= h($order['objektas'] ?? '-') ?></p>
                <p><strong>Kiekis:</strong> <?= h($order['kiekis'] ?? '-') ?></p>
            </div>
            <div>
                <p><strong>Pavadinimas:</strong> <?= h($esamas_pavadinimas ?: ($first_product['gaminio_tipas'] ?? '-')) ?></p>
                <p><strong>Gaminio Nr.:</strong> <?= h($first_product['gaminio_numeris'] ?? '-') ?></p>
                <p><strong>Protokolo Nr.:</strong> <?= h($first_product['protokolo_nr'] ?? '-') ?></p>
                <p><strong>Atitikties kodas:</strong> <?= h($first_product['atitikmuo_kodas'] ?? '-') ?></p>
                <p><strong>Sukūrė:</strong> <?= h(($order['vardas'] ?? '') . ' ' . ($order['pavarde'] ?? '')) ?></p>
                <p><strong>Data:</strong> <?= h($order['sukurtas'] ?? '') ?></p>
            </div>
        </div>
        <?php if (count($order_products) > 1): ?>
        <div style="border-top: 1px solid var(--border); padding-top: 10px; margin-top: 10px;">
            <p style="color: var(--text-secondary); font-size: 0.82rem; margin-bottom: 6px;"><strong>Visi gaminiai (<?= count($order_products) ?>):</strong></p>
            <?php foreach ($order_products as $idx => $p): ?>
            <p style="font-size: 0.82rem; color: var(--text-secondary);">
                <?= ($idx + 1) ?>. Nr. <?= h($p['gaminio_numeris'] ?: '-') ?> &mdash; <?= h($p['gaminio_tipas'] ?? '-') ?> &mdash; Prot. <?= h($p['protokolo_nr'] ?: '-') ?> &mdash; Atit. <?= h($p['atitikmuo_kodas'] ?: '-') ?>
            </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom: 16px;" data-testid="card-mt-langas">
    <div class="card-header">
        <span class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            MT Gaminių Langas
        </span>
    </div>
    <div class="card-body">
        <?php if ($gaminio_id_mt === 0): ?>
        <div class="alert alert-warning" style="margin-bottom: 12px;" data-testid="text-no-product-warning">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Nerastas gaminys šiam užsakymui. Pirmiausia įveskite gaminio pavadinimą aukščiau.
        </div>
        <?php endif; ?>

        <div class="mt-tiles-grid" data-testid="mt-tiles-grid">
            <?php if ($gaminio_id_mt > 0): ?>
            <a href="/mt_funkciniai_bandymai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&uzsakymo_id=<?= $view_id ?>" 
               class="mt-tile" data-testid="tile-funkciniai">
                <div class="mt-tile-icon mt-tile-icon-teal">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Gaminio pildymo forma</div>
                    <div class="mt-tile-desc">Funkciniai bandymai</div>
                </div>
            </a>
            <a href="/MT/mt_sumontuoti_komponentai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&pavadinimas=<?= urlencode($esamas_pavadinimas) ?>&uzsakymo_id=<?= $view_id ?>" 
               class="mt-tile" data-testid="tile-komponentai">
                <div class="mt-tile-icon mt-tile-icon-cyan">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Panaudoti komponentai</div>
                    <div class="mt-tile-desc">Sumontuotos dalys</div>
                </div>
            </a>
            <a href="/MT/mt_dielektriniai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&gaminio_pavadinimas=<?= urlencode($esamas_pavadinimas) ?>&uzsakymo_id=<?= $view_id ?>" 
               class="mt-tile" data-testid="tile-dielektriniai">
                <div class="mt-tile-icon mt-tile-icon-indigo">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Dielektriniai bandymai</div>
                    <div class="mt-tile-desc">Įtampos testai</div>
                </div>
            </a>
            <a href="/MT/mt_pasas.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&gaminio_pavadinimas=<?= urlencode($esamas_pavadinimas) ?>&uzsakymo_id=<?= $view_id ?>" 
               class="mt-tile" data-testid="tile-pasas">
                <div class="mt-tile-icon mt-tile-icon-emerald">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">MT Pasas</div>
                    <div class="mt-tile-desc">Gaminio pasas</div>
                </div>
            </a>
            <div class="mt-tile" onclick="openModal('editOrderModal')" data-testid="tile-redaguoti" style="cursor: pointer;">
                <div class="mt-tile-icon mt-tile-icon-slate">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Redaguoti</div>
                    <div class="mt-tile-desc">Užsakymo duomenys</div>
                </div>
            </div>
            <?php else: ?>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Gaminio pildymo forma</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Panaudoti komponentai</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Dielektriniai bandymai</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">MT Pasas</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <div class="mt-tile" onclick="openModal('editOrderModal')" data-testid="tile-redaguoti" style="cursor: pointer;">
                <div class="mt-tile-icon mt-tile-icon-slate">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Redaguoti</div>
                    <div class="mt-tile-desc">Užsakymo duomenys</div>
                </div>
            </div>
            <?php endif; ?>
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
                    <label class="form-label">MT Gaminio pavadinimas</label>
                    <input type="text" class="form-control" name="pilnas_pavadinimas" value="<?= h($esamas_pavadinimas ?? '') ?>" placeholder="pvz. MT 8x10-1x100(630)" data-testid="input-mt-pavadinimas">
                </div>
                <div style="border-top: 1px solid var(--border); padding-top: 14px; margin-top: 6px;">
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
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <div style="position:relative;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--text-secondary);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="orderSearch" placeholder="Ieškoti pagal užsakymo Nr..." style="padding:0.4rem 0.6rem 0.4rem 2rem;border:1px solid var(--border);border-radius:6px;font-size:0.85rem;width:220px;" data-testid="input-order-search" oninput="filterOrders()">
            </div>
            <button class="btn btn-primary btn-sm" onclick="openModal('createOrderModal')" data-testid="button-new-order">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Naujas užsakymas
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table id="ordersTable">
                <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Užsakovas</th>
                        <th>Sukūrė</th>
                        <th>Data</th>
                        <th>Pasas</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $o): ?>
                        <tr data-testid="row-order-<?= $o['id'] ?>" data-order-nr="<?= h(mb_strtolower($o['uzsakymo_numeris'] ?? '')) ?>">
                            <td><a href="/uzsakymai.php?id=<?= $o['id'] ?>" style="color: var(--primary); font-weight: 500;" data-testid="link-order-<?= $o['id'] ?>"><?= h($o['uzsakymo_numeris'] ?: 'Be nr.') ?></a></td>
                            <td><?= h($o['uzsakovas'] ?? '-') ?></td>
                            <td><?= h(($o['vardas'] ?? '') . ' ' . ($o['pavarde'] ?? '')) ?></td>
                            <td style="color: var(--text-secondary);"><?= h($o['sukurtas'] ?? '') ?></td>
                            <td style="text-align: center;">
                                <?php if (($o['paso_pdf_sk'] ?? 0) > 0): ?>
                                    <?php
                                    $pdf_gaminys = $pdo->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id = ? AND mt_paso_failas IS NOT NULL LIMIT 1");
                                    $pdf_gaminys->execute([$o['id']]);
                                    $pdf_g = $pdf_gaminys->fetch();
                                    ?>
                                    <?php if ($pdf_g): ?>
                                    <a href="/MT/mt_paso_pdf.php?gaminio_id=<?= $pdf_g['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size: 11px; padding: 2px 8px;" data-testid="button-paso-pdf-<?= $o['id'] ?>">PDF</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary); font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>
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
                        <tr><td colspan="6" class="empty-state"><p>Nėra užsakymų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterOrders() {
    const search = document.getElementById('orderSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#ordersTable tbody tr[data-order-nr]');
    let visible = 0;
    rows.forEach(row => {
        const nr = row.getAttribute('data-order-nr') || '';
        if (!search || nr.includes(search)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

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
