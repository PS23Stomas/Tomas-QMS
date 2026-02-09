<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'MT Statistika';

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$uzsakymas_filter = $_GET['uzsakymas'] ?? '';
$tipas_filter = $_GET['tipas'] ?? '';

$where_clauses = ["gt.grupe = 'MT'"];
$params = [];

if ($date_from) {
    $where_clauses[] = "u.sukurtas >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $where_clauses[] = "u.sukurtas <= :date_to";
    $params['date_to'] = $date_to;
}
if ($uzsakymas_filter) {
    $where_clauses[] = "u.uzsakymo_numeris = :uzsakymas";
    $params['uzsakymas'] = $uzsakymas_filter;
}
if ($tipas_filter) {
    $where_clauses[] = "gt.id = :tipas";
    $params['tipas'] = $tipas_filter;
}

$where_sql = implode(' AND ', $where_clauses);

$total_mt = $pdo->prepare("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id WHERE $where_sql");
$total_mt->execute($params);
$total_mt_count = $total_mt->fetchColumn();

$with_protocol = $pdo->prepare("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id WHERE $where_sql AND g.protokolo_nr IS NOT NULL AND g.protokolo_nr != ''");
$with_protocol->execute($params);
$protocol_count = $with_protocol->fetchColumn();

$with_code = $pdo->prepare("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id WHERE $where_sql AND g.atitikmuo_kodas IS NOT NULL AND g.atitikmuo_kodas != ''");
$with_code->execute($params);
$code_count = $with_code->fetchColumn();

$protocol_pct = $total_mt_count > 0 ? round(($protocol_count / $total_mt_count) * 100) : 0;
$code_pct = $total_mt_count > 0 ? round(($code_count / $total_mt_count) * 100) : 0;

$orders_count = $pdo->prepare("SELECT COUNT(DISTINCT g.uzsakymo_id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id WHERE $where_sql");
$orders_count->execute($params);
$total_orders = $orders_count->fetchColumn();

$mt_types = $pdo->query("SELECT id, gaminio_tipas FROM gaminio_tipai WHERE grupe = 'MT' ORDER BY gaminio_tipas")->fetchAll();

$mt_orders = $pdo->query("SELECT DISTINCT u.uzsakymo_numeris FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id WHERE gt.grupe = 'MT' AND u.uzsakymo_numeris IS NOT NULL ORDER BY u.uzsakymo_numeris")->fetchAll();

$products_by_type = $pdo->prepare("
    SELECT gt.gaminio_tipas, COUNT(g.id) as kiekis
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id
    WHERE $where_sql
    GROUP BY gt.gaminio_tipas
    ORDER BY kiekis DESC
");
$products_by_type->execute($params);
$type_stats = $products_by_type->fetchAll();

$products_list = $pdo->prepare("
    SELECT g.id, g.gaminio_numeris, g.protokolo_nr, g.atitikmuo_kodas, 
           g.uzsakymo_id, gt.gaminio_tipas, u.uzsakymo_numeris
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id
    WHERE $where_sql
    ORDER BY g.id DESC
    LIMIT 50
");
$products_list->execute($params);
$products = $products_list->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<a href="/index.php" class="back-link" data-testid="link-back-dashboard">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Grįžti į kokybės rodiklius
</a>

<div class="filter-bar" data-testid="filter-bar">
    <form method="GET" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%;">
        <div class="form-group">
            <label class="form-label">Data nuo</label>
            <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>" data-testid="input-date-from">
        </div>
        <div class="form-group">
            <label class="form-label">Data iki</label>
            <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>" data-testid="input-date-to">
        </div>
        <div class="form-group">
            <label class="form-label">Užsakymas</label>
            <select name="uzsakymas" class="form-control" data-testid="select-order">
                <option value="">Visi</option>
                <?php foreach ($mt_orders as $o): ?>
                <option value="<?= h($o['uzsakymo_numeris']) ?>" <?= $uzsakymas_filter === $o['uzsakymo_numeris'] ? 'selected' : '' ?>><?= h($o['uzsakymo_numeris']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Gaminio tipas</label>
            <select name="tipas" class="form-control" data-testid="select-type">
                <option value="">Visi</option>
                <?php foreach ($mt_types as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tipas_filter == $t['id'] ? 'selected' : '' ?>><?= h($t['gaminio_tipas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary" data-testid="button-filter">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Filtruoti
            </button>
        </div>
        <?php if ($date_from || $date_to || $uzsakymas_filter || $tipas_filter): ?>
        <div class="form-group">
            <a href="/mt_statistika.php" class="btn btn-secondary" data-testid="button-clear-filter">Valyti filtrus</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<div class="stats-summary">
    <div class="stat-card" data-testid="stat-mt-total">
        <div class="stat-header">
            <span class="stat-label">MT gaminiai</span>
            <div class="stat-icon blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_mt_count ?></div>
        <div class="stat-change">Viso MT gaminių</div>
    </div>
    <div class="stat-card" data-testid="stat-mt-orders">
        <div class="stat-header">
            <span class="stat-label">Užsakymai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_orders ?></div>
        <div class="stat-change">MT užsakymų</div>
    </div>
    <div class="stat-card" data-testid="stat-mt-protocol">
        <div class="stat-header">
            <span class="stat-label">Su protokolu</span>
            <div class="stat-icon <?= $protocol_pct >= 80 ? 'green' : ($protocol_pct >= 50 ? 'orange' : 'red') ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $protocol_pct ?>%</div>
        <div class="stat-change"><?= $protocol_count ?> iš <?= $total_mt_count ?></div>
    </div>
    <div class="stat-card" data-testid="stat-mt-code">
        <div class="stat-header">
            <span class="stat-label">Su atitikties kodu</span>
            <div class="stat-icon <?= $code_pct >= 80 ? 'green' : ($code_pct >= 50 ? 'orange' : 'red') ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $code_pct ?>%</div>
        <div class="stat-change"><?= $code_count ?> iš <?= $total_mt_count ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Gaminiai pagal tipą</span>
        </div>
        <div class="card-body">
            <?php if (count($type_stats) > 0): ?>
            <ul class="recent-list">
                <?php foreach ($type_stats as $row): ?>
                <li>
                    <span><?= h($row['gaminio_tipas']) ?></span>
                    <span class="badge badge-primary"><?= $row['kiekis'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state"><p>Nėra duomenų pagal pasirinktus filtrus</p></div>
            <?php endif; ?>
        </div>
    </div>

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
                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;"><?= $protocol_count ?> iš <?= $total_mt_count ?> gaminių</div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                    <span style="font-size: 13px;">Gaminiai su atitikties kodu</span>
                    <span class="badge badge-<?= $code_pct >= 80 ? 'success' : ($code_pct >= 50 ? 'warning' : 'danger') ?>"><?= $code_pct ?>%</span>
                </div>
                <div class="progress-bar-wrapper">
                    <div class="progress-bar <?= $code_pct >= 80 ? 'green' : ($code_pct >= 50 ? 'orange' : 'red') ?>" style="width: <?= $code_pct ?>%"></div>
                </div>
                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;"><?= $code_count ?> iš <?= $total_mt_count ?> gaminių</div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 16px;">
    <div class="card-header">
        <span class="card-title">MT gaminių sąrašas</span>
        <span class="badge badge-info"><?= $total_mt_count ?> viso</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table data-testid="table-mt-products">
                <thead>
                    <tr>
                        <th>Gaminio Nr.</th>
                        <th>Tipas</th>
                        <th>Užsakymas</th>
                        <th>Protokolo Nr.</th>
                        <th>Atitikties kodas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= h($p['gaminio_numeris'] ?: '-') ?></td>
                        <td><?= h($p['gaminio_tipas'] ?: '-') ?></td>
                        <td>
                            <?php if ($p['uzsakymo_numeris']): ?>
                            <a href="/uzsakymai.php?id=<?= h($p['uzsakymo_id'] ?? '') ?>" style="color: var(--primary);"><?= h($p['uzsakymo_numeris']) ?></a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['protokolo_nr']): ?>
                            <span class="badge badge-success"><?= h($p['protokolo_nr']) ?></span>
                            <?php else: ?>
                            <span class="badge badge-danger">Nėra</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['atitikmuo_kodas']): ?>
                            <span class="badge badge-info"><?= h($p['atitikmuo_kodas']) ?></span>
                            <?php else: ?>
                            <span class="badge badge-warning">Nėra</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 32px; color: var(--text-secondary);">Nėra duomenų pagal pasirinktus filtrus</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
