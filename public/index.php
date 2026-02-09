<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Kokybiniai rodikliai';

$total_orders = $pdo->query('SELECT COUNT(*) FROM uzsakymai')->fetchColumn();
$total_products = $pdo->query('SELECT COUNT(*) FROM gaminiai')->fetchColumn();
$total_clients = $pdo->query('SELECT COUNT(*) FROM uzsakovai')->fetchColumn();
$total_objects = $pdo->query('SELECT COUNT(*) FROM objektai')->fetchColumn();
$total_types = $pdo->query('SELECT COUNT(*) FROM gaminio_tipai')->fetchColumn();
$total_components = $pdo->query('SELECT COUNT(*) FROM mt_komponentai')->fetchColumn();

$products_with_protocol = $pdo->query("SELECT COUNT(*) FROM gaminiai WHERE protokolo_nr IS NOT NULL AND protokolo_nr != ''")->fetchColumn();
$protocol_pct = $total_products > 0 ? round(($products_with_protocol / $total_products) * 100) : 0;

$products_with_code = $pdo->query("SELECT COUNT(*) FROM gaminiai WHERE atitikmuo_kodas IS NOT NULL AND atitikmuo_kodas != ''")->fetchColumn();
$code_pct = $total_products > 0 ? round(($products_with_code / $total_products) * 100) : 0;

$recent_orders = $pdo->query('
    SELECT u.id, u.uzsakymo_numeris, u.sukurtas, uz.uzsakovas, o.pavadinimas as objektas
    FROM uzsakymai u
    LEFT JOIN uzsakovai uz ON u.uzsakovas_id = uz.id
    LEFT JOIN objektai o ON u.objektas_id = o.id
    ORDER BY u.id DESC LIMIT 5
')->fetchAll();

$orders_per_client = $pdo->query('
    SELECT uz.uzsakovas, COUNT(u.id) as kiekis
    FROM uzsakymai u
    LEFT JOIN uzsakovai uz ON u.uzsakovas_id = uz.id
    WHERE uz.uzsakovas IS NOT NULL
    GROUP BY uz.uzsakovas
    ORDER BY kiekis DESC
    LIMIT 5
')->fetchAll();

$products_per_type = $pdo->query('
    SELECT gt.gaminio_tipas, COUNT(g.id) as kiekis
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    WHERE gt.gaminio_tipas IS NOT NULL
    GROUP BY gt.gaminio_tipas
    ORDER BY kiekis DESC
    LIMIT 5
')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card" data-testid="stat-orders">
        <div class="stat-header">
            <span class="stat-label">Užsakymai</span>
            <div class="stat-icon blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_orders ?></div>
        <div class="stat-change">Viso užsakymų sistemoje</div>
    </div>
    <div class="stat-card" data-testid="stat-products">
        <div class="stat-header">
            <span class="stat-label">Gaminiai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_products ?></div>
        <div class="stat-change">Viso gaminių sistemoje</div>
    </div>
    <div class="stat-card" data-testid="stat-clients">
        <div class="stat-header">
            <span class="stat-label">Užsakovai</span>
            <div class="stat-icon orange">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_clients ?></div>
        <div class="stat-change">Viso klientų</div>
    </div>
    <div class="stat-card" data-testid="stat-objects">
        <div class="stat-header">
            <span class="stat-label">Objektai</span>
            <div class="stat-icon cyan">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_objects ?></div>
        <div class="stat-change">Statybos objektų</div>
    </div>
    <div class="stat-card" data-testid="stat-types">
        <div class="stat-header">
            <span class="stat-label">Gaminių tipai</span>
            <div class="stat-icon red">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_types ?></div>
        <div class="stat-change">Tipų klasifikacija</div>
    </div>
    <div class="stat-card" data-testid="stat-components">
        <div class="stat-header">
            <span class="stat-label">Komponentai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_components ?></div>
        <div class="stat-change">MT komponentų</div>
    </div>
</div>

<div class="grid-2">
    <div class="quality-section">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Kokybės rodikliai</span>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                        <span style="font-size: 13px;">Gaminiai su protokolų Nr.</span>
                        <span class="badge badge-<?= $protocol_pct >= 80 ? 'success' : ($protocol_pct >= 50 ? 'warning' : 'danger') ?>"><?= $protocol_pct ?>%</span>
                    </div>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar <?= $protocol_pct >= 80 ? 'green' : ($protocol_pct >= 50 ? 'orange' : 'red') ?>" style="width: <?= $protocol_pct ?>%"></div>
                    </div>
                    <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;"><?= $products_with_protocol ?> iš <?= $total_products ?> gaminių</div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                        <span style="font-size: 13px;">Gaminiai su atitikties kodu</span>
                        <span class="badge badge-<?= $code_pct >= 80 ? 'success' : ($code_pct >= 50 ? 'warning' : 'danger') ?>"><?= $code_pct ?>%</span>
                    </div>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar <?= $code_pct >= 80 ? 'green' : ($code_pct >= 50 ? 'orange' : 'red') ?>" style="width: <?= $code_pct ?>%"></div>
                    </div>
                    <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;"><?= $products_with_code ?> iš <?= $total_products ?> gaminių</div>
                </div>
            </div>
        </div>
    </div>

    <div class="quality-section">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Užsakymai pagal užsakovą</span>
            </div>
            <div class="card-body">
                <?php if (count($orders_per_client) > 0): ?>
                <ul class="recent-list">
                    <?php foreach ($orders_per_client as $row): ?>
                    <li>
                        <span><?= h($row['uzsakovas']) ?></span>
                        <span class="badge badge-primary"><?= $row['kiekis'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state"><p>Nėra duomenų</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top: 0;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Paskutiniai užsakymai</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nr.</th>
                            <th>Užsakovas</th>
                            <th>Objektas</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><a href="/uzsakymai.php?id=<?= $order['id'] ?>" style="color: var(--primary); font-weight: 500;"><?= h($order['uzsakymo_numeris'] ?: 'Be nr.') ?></a></td>
                            <td><?= h($order['uzsakovas'] ?? '-') ?></td>
                            <td><?= h($order['objektas'] ?? '-') ?></td>
                            <td style="color: var(--text-secondary);"><?= h($order['sukurtas'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Gaminiai pagal tipą</span>
        </div>
        <div class="card-body">
            <?php if (count($products_per_type) > 0): ?>
            <ul class="recent-list">
                <?php foreach ($products_per_type as $row): ?>
                <li>
                    <span><?= h($row['gaminio_tipas']) ?></span>
                    <span class="badge badge-info"><?= $row['kiekis'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state"><p>Nėra duomenų</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
