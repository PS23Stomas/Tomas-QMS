<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Duomenų bazės diagrama';

$tables_sql = "
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
ORDER BY table_name
";
$tables = $pdo->query($tables_sql)->fetchAll(PDO::FETCH_COLUMN);

$schema = [];
foreach ($tables as $tbl) {
    $cols_sql = "
    SELECT column_name, data_type, is_nullable, column_default,
        (SELECT EXISTS(
            SELECT 1 FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = c.table_name AND kcu.column_name = c.column_name AND tc.constraint_type = 'PRIMARY KEY'
        )) AS is_pk
    FROM information_schema.columns c
    WHERE c.table_schema = 'public' AND c.table_name = :tbl
    ORDER BY c.ordinal_position
    ";
    $stmt = $pdo->prepare($cols_sql);
    $stmt->execute([':tbl' => $tbl]);
    $schema[$tbl] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$fk_sql = "
SELECT
    tc.table_name, kcu.column_name,
    ccu.table_name AS foreign_table_name, ccu.column_name AS foreign_column_name
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public'
";
$fk_explicit = $pdo->query($fk_sql)->fetchAll(PDO::FETCH_ASSOC);

$fk_implicit = [
    ['table_name' => 'gaminiai', 'column_name' => 'uzsakymo_id', 'foreign_table_name' => 'uzsakymai', 'foreign_column_name' => 'id'],
    ['table_name' => 'gaminiai', 'column_name' => 'gaminio_tipas_id', 'foreign_table_name' => 'gaminio_tipai', 'foreign_column_name' => 'id'],
    ['table_name' => 'uzsakymai', 'column_name' => 'uzsakovas_id', 'foreign_table_name' => 'uzsakovai', 'foreign_column_name' => 'id'],
    ['table_name' => 'uzsakymai', 'column_name' => 'vartotojas_id', 'foreign_table_name' => 'vartotojai', 'foreign_column_name' => 'id'],
    ['table_name' => 'uzsakymai', 'column_name' => 'objektas_id', 'foreign_table_name' => 'objektai', 'foreign_column_name' => 'id'],
    ['table_name' => 'uzsakymai', 'column_name' => 'gaminiu_rusis_id', 'foreign_table_name' => 'gaminiu_rusys', 'foreign_column_name' => 'id'],
    ['table_name' => 'mt_funkciniai_bandymai', 'column_name' => 'gaminio_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'mt_komponentai', 'column_name' => 'gaminio_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'mt_dielektriniai_bandymai', 'column_name' => 'gaminys_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'mt_izeminimo_tikrinimas', 'column_name' => 'gaminys_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'mt_saugikliu_ideklai', 'column_name' => 'gaminio_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'mt_paso_teksto_korekcijos', 'column_name' => 'gaminio_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'antriniu_grandiniu_bandymai', 'column_name' => 'gaminys_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'bandymai_prietaisai', 'column_name' => 'gaminys_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'pretenzijos', 'column_name' => 'uzsakymo_id', 'foreign_table_name' => 'uzsakymai', 'foreign_column_name' => 'id'],
    ['table_name' => 'pretenzijos', 'column_name' => 'gaminio_id', 'foreign_table_name' => 'gaminiai', 'foreign_column_name' => 'id'],
    ['table_name' => 'pretenzijos', 'column_name' => 'sukure_id', 'foreign_table_name' => 'vartotojai', 'foreign_column_name' => 'id'],
    ['table_name' => 'aktyvus_vartotojai', 'column_name' => 'vartotojas_id', 'foreign_table_name' => 'vartotojai', 'foreign_column_name' => 'id'],
];

$all_fk = array_merge($fk_explicit, $fk_implicit);
$unique_fk = [];
foreach ($all_fk as $fk) {
    $key = $fk['table_name'] . '.' . $fk['column_name'] . '->' . $fk['foreign_table_name'] . '.' . $fk['foreign_column_name'];
    $unique_fk[$key] = $fk;
}
$all_fk = array_values($unique_fk);

$fk_by_table = [];
foreach ($all_fk as $fk) {
    $fk_by_table[$fk['table_name']][$fk['column_name']] = $fk['foreign_table_name'] . '.' . $fk['foreign_column_name'];
}

$table_groups = [
    'Pagrindinės lentelės' => ['uzsakymai', 'gaminiai', 'uzsakovai', 'objektai', 'gaminio_tipai', 'gaminiu_rusys'],
    'MT Bandymai ir komponentai' => ['mt_funkciniai_bandymai', 'mt_komponentai', 'mt_dielektriniai_bandymai', 'mt_izeminimo_tikrinimas', 'mt_saugikliu_ideklai', 'mt_paso_teksto_korekcijos', 'antriniu_grandiniu_bandymai', 'bandymai_prietaisai'],
    'Vartotojai ir sesijos' => ['vartotojai', 'aktyvus_vartotojai'],
    'Pretenzijos' => ['pretenzijos', 'pretenzijos_nuotraukos'],
    'Kita' => ['prietaisai'],
];

$grouped_tables = [];
foreach ($table_groups as $tables_list) {
    foreach ($tables_list as $t) $grouped_tables[] = $t;
}
foreach ($tables as $t) {
    if (!in_array($t, $grouped_tables)) {
        $table_groups['Kita'][] = $t;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.db-diagram-page { padding: 20px; }
.db-diagram-page h2 { margin-bottom: 20px; font-size: 20px; font-weight: 700; color: var(--text-primary); }
.db-group { margin-bottom: 32px; }
.db-group-title {
    font-size: 15px; font-weight: 700; color: #fff;
    background: var(--primary, #2563eb); padding: 8px 16px; border-radius: 6px;
    margin-bottom: 14px; display: inline-block;
}
.db-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}
.db-table-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.db-table-name {
    background: #1e293b; color: #fff; padding: 8px 14px;
    font-weight: 700; font-size: 13px; letter-spacing: 0.3px;
    display: flex; align-items: center; gap: 8px;
}
.db-table-name .tbl-icon { opacity: 0.7; }
.db-col-list { padding: 0; margin: 0; list-style: none; }
.db-col-item {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 14px; font-size: 12px; border-bottom: 1px solid #f1f5f9;
}
.db-col-item:last-child { border-bottom: none; }
.db-col-name { font-weight: 600; color: #1e293b; min-width: 160px; }
.db-col-type { color: #64748b; font-size: 11px; font-family: monospace; }
.db-col-badge {
    font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 3px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.badge-pk { background: #fbbf24; color: #78350f; }
.badge-fk { background: #60a5fa; color: #1e3a5f; }
.badge-null { background: #e2e8f0; color: #64748b; }
.db-fk-ref {
    font-size: 10px; color: #3b82f6; cursor: pointer;
    text-decoration: underline; margin-left: auto;
}
.db-fk-ref:hover { color: #1d4ed8; }

.db-relations-section { margin-top: 32px; }
.db-relations-title { font-size: 16px; font-weight: 700; margin-bottom: 14px; color: var(--text-primary); }
.db-rel-table { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
.db-rel-table th { background: #f8fafc; padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
.db-rel-table td { padding: 6px 12px; border-bottom: 1px solid #f1f5f9; }
.db-rel-table tr:hover td { background: #f0f9ff; }
.rel-arrow { color: #3b82f6; font-weight: 700; }
.db-stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.db-stat-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 14px 20px; min-width: 140px; text-align: center;
}
.db-stat-value { font-size: 24px; font-weight: 700; color: var(--primary, #2563eb); }
.db-stat-label { font-size: 12px; color: #64748b; margin-top: 2px; }

@media print {
    .sidebar, .topbar, .sidebar-toggle, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 10px !important; }
    .db-tables-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .db-table-card { break-inside: avoid; }
}
</style>

<div class="db-diagram-page">
    <h2>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
            <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
        </svg>
        Duomenų bazės ryšių diagrama
    </h2>

    <div class="db-stats">
        <div class="db-stat-card">
            <div class="db-stat-value"><?= count($tables) ?></div>
            <div class="db-stat-label">Lentelės</div>
        </div>
        <div class="db-stat-card">
            <div class="db-stat-value"><?= array_sum(array_map('count', $schema)) ?></div>
            <div class="db-stat-label">Stulpeliai</div>
        </div>
        <div class="db-stat-card">
            <div class="db-stat-value"><?= count($all_fk) ?></div>
            <div class="db-stat-label">Ryšiai (FK)</div>
        </div>
    </div>

    <?php foreach ($table_groups as $group_name => $group_tables): ?>
    <div class="db-group">
        <div class="db-group-title"><?= htmlspecialchars($group_name) ?></div>
        <div class="db-tables-grid">
            <?php foreach ($group_tables as $tbl): ?>
            <?php if (!isset($schema[$tbl])) continue; ?>
            <div class="db-table-card" id="tbl-<?= $tbl ?>">
                <div class="db-table-name">
                    <span class="tbl-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    </span>
                    <?= htmlspecialchars($tbl) ?>
                    <span style="margin-left:auto; opacity:0.6; font-size:11px; font-weight:400;"><?= count($schema[$tbl]) ?> stulp.</span>
                </div>
                <ul class="db-col-list">
                    <?php foreach ($schema[$tbl] as $col): ?>
                    <li class="db-col-item">
                        <span class="db-col-name"><?= htmlspecialchars($col['column_name']) ?></span>
                        <?php if ($col['is_pk']): ?>
                            <span class="db-col-badge badge-pk">PK</span>
                        <?php endif; ?>
                        <?php if (isset($fk_by_table[$tbl][$col['column_name']])): ?>
                            <span class="db-col-badge badge-fk">FK</span>
                        <?php endif; ?>
                        <span class="db-col-type"><?= htmlspecialchars($col['data_type']) ?></span>
                        <?php if (isset($fk_by_table[$tbl][$col['column_name']])): ?>
                            <a class="db-fk-ref" href="#tbl-<?= explode('.', $fk_by_table[$tbl][$col['column_name']])[0] ?>"
                               title="Ryšys su <?= htmlspecialchars($fk_by_table[$tbl][$col['column_name']]) ?>">
                                &rarr; <?= htmlspecialchars($fk_by_table[$tbl][$col['column_name']]) ?>
                            </a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="db-relations-section">
        <div class="db-relations-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
            </svg>
            Visi ryšiai tarp lentelių
        </div>
        <table class="db-rel-table">
            <thead>
                <tr>
                    <th>Lentelė</th>
                    <th>Stulpelis</th>
                    <th></th>
                    <th>Susijusi lentelė</th>
                    <th>Susijęs stulpelis</th>
                    <th>Tipas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_fk as $fk): ?>
                <tr>
                    <td><a href="#tbl-<?= $fk['table_name'] ?>" style="color:#1e293b; font-weight:600; text-decoration:none;"><?= htmlspecialchars($fk['table_name']) ?></a></td>
                    <td><?= htmlspecialchars($fk['column_name']) ?></td>
                    <td class="rel-arrow">&rarr;</td>
                    <td><a href="#tbl-<?= $fk['foreign_table_name'] ?>" style="color:#1e293b; font-weight:600; text-decoration:none;"><?= htmlspecialchars($fk['foreign_table_name']) ?></a></td>
                    <td><?= htmlspecialchars($fk['foreign_column_name']) ?></td>
                    <td><span class="db-col-badge badge-fk">FK</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
