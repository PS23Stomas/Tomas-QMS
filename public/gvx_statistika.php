<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'GVX Statistika';

$total_gvx = $pdo->query("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'GVX'")->fetchColumn();
$gvx_orders = $pdo->query("SELECT COUNT(DISTINCT g.uzsakymo_id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'GVX'")->fetchColumn();

$with_protocol = $pdo->query("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'GVX' AND g.protokolo_nr IS NOT NULL AND g.protokolo_nr != ''")->fetchColumn();
$with_code = $pdo->query("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'GVX' AND g.atitikmuo_kodas IS NOT NULL AND g.atitikmuo_kodas != ''")->fetchColumn();

$protocol_pct = $total_gvx > 0 ? round(($with_protocol / $total_gvx) * 100) : 0;
$code_pct = $total_gvx > 0 ? round(($with_code / $total_gvx) * 100) : 0;

$type_stats = $pdo->query("
    SELECT gt.gaminio_tipas, COUNT(g.id) as kiekis
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    WHERE gt.grupe = 'GVX'
    GROUP BY gt.gaminio_tipas
    ORDER BY kiekis DESC
")->fetchAll();

$products = $pdo->query("
    SELECT g.id, g.gaminio_numeris, g.protokolo_nr, g.atitikmuo_kodas,
           gt.gaminio_tipas, u.uzsakymo_numeris
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id
    WHERE gt.grupe = 'GVX'
    ORDER BY g.id DESC
    LIMIT 50
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<a href="/index.php" class="back-link" data-testid="link-back-dashboard">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Grįžti į kokybės rodiklius
</a>

<div class="stats-summary">
    <div class="stat-card" data-testid="stat-gvx-total">
        <div class="stat-header">
            <span class="stat-label">GVX gaminiai</span>
            <div class="stat-icon blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 3 20 16 16 16 16 8"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $total_gvx ?></div>
        <div class="stat-change">Viso GVX gaminių</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Užsakymai</span>
            <div class="stat-icon green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $gvx_orders ?></div>
        <div class="stat-change">GVX užsakymų</div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Su protokolu</span>
            <div class="stat-icon <?= $protocol_pct >= 80 ? 'green' : ($protocol_pct >= 50 ? 'orange' : 'red') ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $protocol_pct ?>%</div>
        <div class="stat-change"><?= $with_protocol ?> iš <?= $total_gvx ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Su atitikties kodu</span>
            <div class="stat-icon <?= $code_pct >= 80 ? 'green' : ($code_pct >= 50 ? 'orange' : 'red') ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= $code_pct ?>%</div>
        <div class="stat-change"><?= $with_code ?> iš <?= $total_gvx ?></div>
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
            <div class="empty-state"><p>Nėra duomenų</p></div>
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
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                    <span style="font-size: 13px;">Gaminiai su atitikties kodu</span>
                    <span class="badge badge-<?= $code_pct >= 80 ? 'success' : ($code_pct >= 50 ? 'warning' : 'danger') ?>"><?= $code_pct ?>%</span>
                </div>
                <div class="progress-bar-wrapper">
                    <div class="progress-bar <?= $code_pct >= 80 ? 'green' : ($code_pct >= 50 ? 'orange' : 'red') ?>" style="width: <?= $code_pct ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 16px;">
    <div class="card-header">
        <span class="card-title">GVX gaminių sąrašas</span>
        <span class="badge badge-info"><?= $total_gvx ?> viso</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
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
                        <td><?= h($p['uzsakymo_numeris'] ?: '-') ?></td>
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
                    <tr><td colspan="5" style="text-align: center; padding: 32px; color: var(--text-secondary);">Nėra duomenų</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
