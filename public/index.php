<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Kokybės rodikliai';

$mt_count = $pdo->query("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'MT'")->fetchColumn();
$gvx_count = $pdo->query("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'GVX'")->fetchColumn();
$kv10_count = $pdo->query("SELECT COUNT(g.id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe LIKE '10kV%'")->fetchColumn();

$mt_orders = $pdo->query("SELECT COUNT(DISTINCT g.uzsakymo_id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'MT'")->fetchColumn();
$gvx_orders = $pdo->query("SELECT COUNT(DISTINCT g.uzsakymo_id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe = 'GVX'")->fetchColumn();
$kv10_orders = $pdo->query("SELECT COUNT(DISTINCT g.uzsakymo_id) FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE gt.grupe LIKE '10kV%'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="group-cards-container">
    <div class="group-card mt-card" onclick="window.location.href='mt_statistika.php'" data-testid="card-mt-stats">
        <div class="group-card-body">
            <div class="group-card-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <h3 class="group-card-title">MT Statistika</h3>
            <p class="group-card-desc">Transformatorių (MT) pastebėtų gedimų statistika su filtrais pagal laikotarpį, užsakymą ir gedimų tipus</p>
            <ul class="group-card-features">
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Filtravimas pagal datą
                </li>
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Detalūs gedimų duomenys
                </li>
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?= $mt_count ?> gaminių, <?= $mt_orders ?> užsakymų
                </li>
            </ul>
        </div>
    </div>

    <div class="group-card gvx-card" onclick="window.location.href='gvx_statistika.php'" data-testid="card-gvx-stats">
        <div class="group-card-body">
            <div class="group-card-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 3 20 16 16 16 16 8"/><line x1="5" y1="19" x2="19" y2="19"/><circle cx="8.5" cy="19" r="2"/><circle cx="15.5" cy="19" r="2"/></svg>
            </div>
            <h3 class="group-card-title">GVX Statistika</h3>
            <p class="group-card-desc">GVX konteinerių kokybės klausimynų statistika su KPI rodikliais ir vizualizacijomis</p>
            <ul class="group-card-features">
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    KPI rodikliai
                </li>
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Diagramos ir vizualizacijos
                </li>
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?= $gvx_count ?> gaminių, <?= $gvx_orders ?> užsakymų
                </li>
            </ul>
        </div>
    </div>

    <div class="group-card kv10-card" onclick="window.location.href='kv10_statistika.php'" data-testid="card-10kv-stats">
        <div class="group-card-body">
            <div class="group-card-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
            <h3 class="group-card-title">10kV Statistika</h3>
            <p class="group-card-desc">10kV skirstomųjų įrenginių (USN) pastebėtų gedimų statistika su filtrais pagal laikotarpį ir užsakymą</p>
            <ul class="group-card-features">
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Filtravimas pagal datą
                </li>
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Detalūs gedimų duomenys
                </li>
                <li>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?= $kv10_count ?> gaminių, <?= $kv10_orders ?> užsakymų
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
