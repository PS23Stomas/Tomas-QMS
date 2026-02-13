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
    if (!in_array($fk['table_name'], $tables) || !in_array($fk['foreign_table_name'], $tables)) continue;
    $key = $fk['table_name'] . '.' . $fk['column_name'] . '->' . $fk['foreign_table_name'] . '.' . $fk['foreign_column_name'];
    $unique_fk[$key] = $fk;
}
$all_fk = array_values($unique_fk);

$fk_by_table = [];
foreach ($all_fk as $fk) {
    $fk_by_table[$fk['table_name']][$fk['column_name']] = $fk['foreign_table_name'] . '.' . $fk['foreign_column_name'];
}

$table_group_map = [];
$group_defs = [
    ['name' => 'Pagrindinės lentelės', 'tables' => ['uzsakymai', 'gaminiai', 'uzsakovai', 'objektai', 'gaminio_tipai', 'gaminiu_rusys'], 'color' => '#1e293b'],
    ['name' => 'MT Bandymai ir komponentai', 'tables' => ['mt_funkciniai_bandymai', 'mt_komponentai', 'mt_dielektriniai_bandymai', 'mt_izeminimo_tikrinimas', 'mt_saugikliu_ideklai', 'mt_paso_teksto_korekcijos', 'antriniu_grandiniu_bandymai', 'bandymai_prietaisai'], 'color' => '#059669'],
    ['name' => 'Vartotojai', 'tables' => ['vartotojai', 'aktyvus_vartotojai'], 'color' => '#7c3aed'],
    ['name' => 'Pretenzijos', 'tables' => ['pretenzijos', 'pretenzijos_nuotraukos'], 'color' => '#dc2626'],
    ['name' => 'Kita', 'tables' => [], 'color' => '#d97706'],
];
$assigned = [];
foreach ($group_defs as $gi => $gd) {
    foreach ($gd['tables'] as $t) {
        if (in_array($t, $tables)) {
            $table_group_map[$t] = $gi;
            $assigned[] = $t;
        }
    }
}
foreach ($tables as $t) {
    if (!in_array($t, $assigned)) {
        $table_group_map[$t] = 4;
        $group_defs[4]['tables'][] = $t;
    }
}

$schema_json = json_encode($schema);
$fk_json = json_encode($all_fk);
$fk_by_table_json = json_encode($fk_by_table);
$group_map_json = json_encode($table_group_map);
$group_defs_json = json_encode($group_defs);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.er-page { padding: 16px; overflow-y: auto; }
.er-toolbar {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; background: #fff; border: 1px solid #e2e8f0;
    border-radius: 8px; margin-bottom: 12px; flex-wrap: wrap;
}
.er-toolbar h2 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px; }
.er-toolbar .sep { width: 1px; height: 24px; background: #e2e8f0; }
.er-btn {
    padding: 6px 14px; font-size: 12px; font-weight: 600;
    border: 1px solid #e2e8f0; border-radius: 6px; background: #fff;
    color: #475569; cursor: pointer; display: flex; align-items: center; gap: 5px;
}
.er-btn:hover { background: #f1f5f9; }
.er-zoom { font-size: 12px; color: #64748b; font-weight: 600; min-width: 40px; text-align: center; }

.er-canvas-wrap {
    position: relative; overflow: hidden; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 8px; cursor: grab;
    height: 700px;
}
.er-canvas-wrap:active { cursor: grabbing; }
.er-canvas { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
.er-grid {
    position: absolute; top: 0; left: 0; width: 10000px; height: 10000px;
    background-image: linear-gradient(rgba(0,0,0,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.03) 1px, transparent 1px);
    background-size: 40px 40px; pointer-events: none;
}
svg.er-lines { position: absolute; top: 0; left: 0; width: 10000px; height: 10000px; pointer-events: none; overflow: visible; }
svg.er-lines path { fill: none; stroke-width: 2; }
svg.er-lines text { font-size: 10px; font-family: 'Inter', sans-serif; fill: #64748b; pointer-events: none; }

.er-table {
    position: absolute; background: #fff; border: 2px solid #cbd5e1;
    border-radius: 8px; min-width: 190px; max-width: 260px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08); cursor: move; user-select: none; z-index: 2;
}
.er-table:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.14); z-index: 10; }
.er-table.dragging { box-shadow: 0 8px 24px rgba(0,0,0,0.2); z-index: 100; opacity: 0.92; }
.er-table.highlight { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }

.er-tbl-header {
    padding: 7px 10px; font-size: 11px; font-weight: 700; color: #fff;
    border-radius: 6px 6px 0 0; display: flex; align-items: center; gap: 6px;
}
.er-tbl-cols { padding: 0; margin: 0; list-style: none; }
.er-tbl-col {
    display: flex; align-items: center; gap: 5px;
    padding: 3px 8px; font-size: 10px; border-bottom: 1px solid #f1f5f9;
}
.er-tbl-col:last-child { border-bottom: none; }
.col-icon.pk { color: #f59e0b; }
.col-icon.fk { color: #3b82f6; }
.col-icon.normal { color: #cbd5e1; }
.er-tbl-col .col-name { font-weight: 500; color: #1e293b; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.er-tbl-col .col-type { font-size: 9px; color: #94a3b8; font-family: monospace; white-space: nowrap; }

.er-legend {
    position: absolute; bottom: 12px; left: 12px; background: #fff;
    border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px;
    font-size: 11px; color: #475569; z-index: 50; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    max-width: 300px;
}
.er-legend h4 { margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #1e293b; }
.er-legend-item { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
.er-legend-item:last-child { margin-bottom: 0; }
.er-legend-swatch { width: 24px; height: 4px; border-radius: 2px; flex-shrink: 0; }

.er-fallback { margin-top: 20px; }
.er-fallback summary { font-size: 14px; font-weight: 700; color: #1e293b; cursor: pointer; padding: 10px 0; }
.er-fb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; margin-top: 12px; }
.er-fb-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.er-fb-card-header { padding: 6px 12px; font-size: 12px; font-weight: 700; color: #fff; }
.er-fb-card ul { padding: 0; margin: 0; list-style: none; }
.er-fb-card li { padding: 3px 12px; font-size: 11px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 6px; }
.er-fb-card li:last-child { border-bottom: none; }
.er-fb-card .fb-pk { color: #f59e0b; font-weight: 700; font-size: 9px; }
.er-fb-card .fb-fk { color: #3b82f6; font-weight: 700; font-size: 9px; }
.er-fb-card .fb-type { color: #94a3b8; font-size: 10px; font-family: monospace; margin-left: auto; }
.er-fb-card .fb-ref { color: #3b82f6; font-size: 10px; }

.er-rel-table { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 12px; }
.er-rel-table th { background: #f8fafc; padding: 6px 10px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
.er-rel-table td { padding: 5px 10px; border-bottom: 1px solid #f1f5f9; }
.er-rel-table tr:hover td { background: #f0f9ff; }
.rel-arrow { color: #3b82f6; font-weight: 700; }
</style>

<div class="er-page">
    <div class="er-toolbar">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </svg>
            DB ryšių diagrama
        </h2>
        <div class="sep"></div>
        <button class="er-btn" onclick="erZoomIn()" data-testid="btn-zoom-in">+ Priartinti</button>
        <span class="er-zoom" id="zoomLevel">75%</span>
        <button class="er-btn" onclick="erZoomOut()" data-testid="btn-zoom-out">- Nutolinti</button>
        <button class="er-btn" onclick="erResetView()" data-testid="btn-reset-view">Pradinis vaizdas</button>
        <div class="sep"></div>
        <span style="font-size:11px; color:#94a3b8;"><?= count($tables) ?> lentelių | <?= count($all_fk) ?> ryšių | Vilkite lenteles pele</span>
    </div>

    <div class="er-canvas-wrap" id="canvasWrap">
        <div class="er-canvas" id="erCanvas">
            <div class="er-grid"></div>
            <svg class="er-lines" id="erLines"></svg>
        </div>
        <div class="er-legend" id="erLegend">
            <h4>Legenda</h4>
            <div class="er-legend-item">
                <svg width="14" height="14" viewBox="0 0 16 16"><circle cx="8" cy="8" r="5" fill="#f59e0b" stroke="#b45309" stroke-width="1"/></svg>
                <span><b>PK</b> - Pirminis raktas (unikalus ID)</span>
            </div>
            <div class="er-legend-item">
                <svg width="14" height="14" viewBox="0 0 16 16"><circle cx="8" cy="8" r="5" fill="#3b82f6" stroke="#1d4ed8" stroke-width="1"/></svg>
                <span><b>FK</b> - Išorinis raktas (nuoroda į kitą lentelę)</span>
            </div>
            <div class="er-legend-item">
                <div class="er-legend-swatch" style="background:#64748b;"></div>
                <span><b>Linija su rodykle</b> - FK ryšys (rodyklė rodo į tėvinę lentelę)</span>
            </div>
            <div class="er-legend-item">
                <svg width="20" height="14" viewBox="0 0 20 14"><line x1="2" y1="7" x2="18" y2="7" stroke="#64748b" stroke-width="2"/><polygon points="16,3 20,7 16,11" fill="#64748b"/></svg>
                <span><b>Kryptis</b> - iš vaikinės lentelės (FK) į tėvinę (PK)</span>
            </div>
            <div class="er-legend-item" style="margin-top:4px; padding-top:4px; border-top:1px solid #e2e8f0;">
                <span style="font-size:10px; color:#94a3b8;">Vilkite lenteles pele | Slinkite rateliu priartinti</span>
            </div>
            <?php foreach ($group_defs as $gi => $gd): ?>
            <?php if (count($gd['tables']) > 0): ?>
            <div class="er-legend-item">
                <div class="er-legend-swatch" style="background:<?= $gd['color'] ?>;"></div>
                <span><?= htmlspecialchars($gd['name']) ?></span>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="er-fallback">
        <details open>
            <summary>Lentelių ir ryšių sąrašas (tekstinis vaizdas)</summary>
            <div class="er-fb-grid">
                <?php foreach ($tables as $tbl): ?>
                <?php $gi = $table_group_map[$tbl] ?? 4; $color = $group_defs[$gi]['color']; ?>
                <div class="er-fb-card">
                    <div class="er-fb-card-header" style="background:<?= $color ?>;"><?= htmlspecialchars($tbl) ?> (<?= count($schema[$tbl]) ?>)</div>
                    <ul>
                        <?php foreach ($schema[$tbl] as $col): ?>
                        <li>
                            <?php if ($col['is_pk'] === true || $col['is_pk'] === 't' || $col['is_pk'] === '1'): ?>
                                <span class="fb-pk">PK</span>
                            <?php endif; ?>
                            <?php if (isset($fk_by_table[$tbl][$col['column_name']])): ?>
                                <span class="fb-fk">FK</span>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($col['column_name']) ?></span>
                            <span class="fb-type"><?= htmlspecialchars($col['data_type']) ?></span>
                            <?php if (isset($fk_by_table[$tbl][$col['column_name']])): ?>
                                <span class="fb-ref">&rarr; <?= htmlspecialchars($fk_by_table[$tbl][$col['column_name']]) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>

            <h4 style="margin-top:16px; font-size:14px;">Visi ryšiai</h4>
            <table class="er-rel-table">
                <thead><tr><th>Lentelė</th><th>Stulpelis (FK)</th><th></th><th>Susijusi lentelė</th><th>Stulpelis</th></tr></thead>
                <tbody>
                    <?php foreach ($all_fk as $fk): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($fk['table_name']) ?></td>
                        <td><?= htmlspecialchars($fk['column_name']) ?></td>
                        <td class="rel-arrow">&rarr;</td>
                        <td style="font-weight:600;"><?= htmlspecialchars($fk['foreign_table_name']) ?></td>
                        <td><?= htmlspecialchars($fk['foreign_column_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    </div>
</div>

<script>
(function() {
    var schema = <?= $schema_json ?>;
    var allFk = <?= $fk_json ?>;
    var fkByTable = <?= $fk_by_table_json ?>;
    var groupMap = <?= $group_map_json ?>;
    var groupDefs = <?= $group_defs_json ?>;

    var canvas = document.getElementById('erCanvas');
    var wrap = document.getElementById('canvasWrap');
    var svgLines = document.getElementById('erLines');

    var scale = 0.75, panX = 30, panY = 30;
    var tableEls = {};

    var tableNames = Object.keys(schema);
    var positions = {};

    function layoutTables() {
        var groups = {};
        tableNames.forEach(function(t) {
            var g = groupMap[t] !== undefined ? groupMap[t] : 4;
            if (!groups[g]) groups[g] = [];
            groups[g].push(t);
        });

        var groupOrder = [0, 2, 1, 3, 4];
        var curY = 40;
        var colWidth = 260;
        var maxCols = 4;
        var gapX = 30;
        var gapY = 40;

        groupOrder.forEach(function(gi) {
            if (!groups[gi]) return;
            var tbls = groups[gi];

            var rows = [];
            var currentRow = [];
            tbls.forEach(function(t) {
                currentRow.push(t);
                if (currentRow.length >= maxCols) {
                    rows.push(currentRow);
                    currentRow = [];
                }
            });
            if (currentRow.length > 0) rows.push(currentRow);

            rows.forEach(function(row) {
                var maxH = 0;
                row.forEach(function(t, col) {
                    var numCols = (schema[t] || []).length;
                    var shown = Math.min(numCols, 15);
                    var h = 28 + shown * 18 + (numCols > 15 ? 18 : 0);

                    positions[t] = {
                        x: 40 + col * (colWidth + gapX),
                        y: curY
                    };
                    if (h > maxH) maxH = h;
                });
                curY += maxH + gapY;
            });

            curY += 30;
        });
    }

    layoutTables();

    function getHeaderColor(tbl) {
        var g = groupMap[tbl] !== undefined ? groupMap[tbl] : 4;
        return groupDefs[g] ? groupDefs[g].color : '#64748b';
    }

    function renderTables() {
        tableNames.forEach(function(tbl) {
            var cols = schema[tbl];
            var fks = fkByTable[tbl] || {};
            var pos = positions[tbl];

            var el = document.createElement('div');
            el.className = 'er-table';
            el.id = 'ertbl-' + tbl;
            el.style.left = pos.x + 'px';
            el.style.top = pos.y + 'px';

            var html = '<div class="er-tbl-header" style="background:' + getHeaderColor(tbl) + ';">' +
                '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.8"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>' +
                '<span>' + tbl + '</span></div>';

            html += '<ul class="er-tbl-cols">';
            var maxShow = 15;
            var shown = 0;
            cols.forEach(function(col) {
                if (shown >= maxShow) return;
                shown++;
                var isPk = col.is_pk === true || col.is_pk === 't' || col.is_pk === '1';
                var isFk = fks[col.column_name] !== undefined;
                var iconSvg;
                if (isPk) {
                    iconSvg = '<svg width="10" height="10" viewBox="0 0 16 16"><circle cx="8" cy="8" r="5" fill="#f59e0b" stroke="#b45309" stroke-width="1"/></svg>';
                } else if (isFk) {
                    iconSvg = '<svg width="10" height="10" viewBox="0 0 16 16"><circle cx="8" cy="8" r="5" fill="#3b82f6" stroke="#1d4ed8" stroke-width="1"/></svg>';
                } else {
                    iconSvg = '<svg width="10" height="10" viewBox="0 0 16 16"><circle cx="8" cy="8" r="3" fill="#cbd5e1"/></svg>';
                }
                html += '<li class="er-tbl-col">' +
                    '<span class="col-icon">' + iconSvg + '</span>' +
                    '<span class="col-name">' + col.column_name + '</span>' +
                    '<span class="col-type">' + col.data_type + '</span>' +
                    '</li>';
            });
            if (cols.length > maxShow) {
                html += '<li class="er-tbl-col" style="color:#94a3b8; font-style:italic;">...ir dar ' + (cols.length - maxShow) + ' stulp.</li>';
            }
            html += '</ul>';

            el.innerHTML = html;
            canvas.appendChild(el);
            tableEls[tbl] = el;
            makeDraggable(el, tbl);
        });
    }

    function makeDraggable(el, tbl) {
        var startX, startY, origLeft, origTop;
        var header = el.querySelector('.er-tbl-header');

        function onDown(e) {
            e.preventDefault();
            el.classList.add('dragging');
            var ev = e.touches ? e.touches[0] : e;
            startX = ev.clientX; startY = ev.clientY;
            origLeft = parseInt(el.style.left) || 0;
            origTop = parseInt(el.style.top) || 0;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onUp);
        }
        function onMove(e) {
            e.preventDefault();
            var ev = e.touches ? e.touches[0] : e;
            var dx = (ev.clientX - startX) / scale;
            var dy = (ev.clientY - startY) / scale;
            el.style.left = (origLeft + dx) + 'px';
            el.style.top = (origTop + dy) + 'px';
            positions[tbl] = { x: origLeft + dx, y: origTop + dy };
            drawLines();
        }
        function onUp() {
            el.classList.remove('dragging');
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onUp);
        }
        header.addEventListener('mousedown', onDown);
        header.addEventListener('touchstart', onDown, { passive: false });
    }

    function getTableRect(tbl) {
        var el = tableEls[tbl];
        if (!el) return null;
        var x = parseInt(el.style.left) || 0;
        var y = parseInt(el.style.top) || 0;
        var w = el.offsetWidth;
        var h = el.offsetHeight;
        return { x: x, y: y, w: w, h: h, cx: x + w/2, cy: y + h/2 };
    }

    function drawLines() {
        while (svgLines.firstChild) svgLines.removeChild(svgLines.firstChild);

        var colors = ['#94a3b8', '#059669', '#7c3aed', '#dc2626', '#d97706', '#0284c7'];

        var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        colors.forEach(function(color, i) {
            var marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
            marker.setAttribute('id', 'arrow-' + i);
            marker.setAttribute('viewBox', '0 0 10 10');
            marker.setAttribute('refX', '9'); marker.setAttribute('refY', '5');
            marker.setAttribute('markerWidth', '7'); marker.setAttribute('markerHeight', '7');
            marker.setAttribute('orient', 'auto-start-reverse');
            var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p.setAttribute('d', 'M 0 0 L 10 5 L 0 10 z');
            p.setAttribute('fill', color);
            marker.appendChild(p);
            defs.appendChild(marker);
        });
        svgLines.appendChild(defs);

        var pairOffsets = {};
        allFk.forEach(function(fk) {
            var fromTbl = fk.table_name;
            var toTbl = fk.foreign_table_name;
            var rectFrom = getTableRect(fromTbl);
            var rectTo = getTableRect(toTbl);
            if (!rectFrom || !rectTo) return;

            var pairKey = [fromTbl, toTbl].sort().join('|');
            if (!pairOffsets[pairKey]) pairOffsets[pairKey] = 0;
            var offset = pairOffsets[pairKey]++;

            var dx = rectTo.cx - rectFrom.cx;
            var dy = rectTo.cy - rectFrom.cy;
            var fx, fy, tx, ty;
            if (Math.abs(dx) > Math.abs(dy)) {
                if (dx > 0) {
                    fx = rectFrom.x + rectFrom.w; fy = rectFrom.cy + offset * 10;
                    tx = rectTo.x; ty = rectTo.cy + offset * 10;
                } else {
                    fx = rectFrom.x; fy = rectFrom.cy + offset * 10;
                    tx = rectTo.x + rectTo.w; ty = rectTo.cy + offset * 10;
                }
            } else {
                if (dy > 0) {
                    fx = rectFrom.cx + offset * 20; fy = rectFrom.y + rectFrom.h;
                    tx = rectTo.cx + offset * 20; ty = rectTo.y;
                } else {
                    fx = rectFrom.cx + offset * 20; fy = rectFrom.y;
                    tx = rectTo.cx + offset * 20; ty = rectTo.y + rectTo.h;
                }
            }

            var isHoriz = Math.abs(fx - tx) > Math.abs(fy - ty);
            var cp1x, cp1y, cp2x, cp2y;
            if (isHoriz) {
                cp1x = fx + (tx - fx) * 0.4; cp1y = fy;
                cp2x = fx + (tx - fx) * 0.6; cp2y = ty;
            } else {
                cp1x = fx; cp1y = fy + (ty - fy) * 0.4;
                cp2x = tx; cp2y = fy + (ty - fy) * 0.6;
            }

            var gFrom = groupMap[fromTbl] !== undefined ? groupMap[fromTbl] : 4;
            var colorIdx = gFrom < colors.length ? gFrom : 0;

            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'M' + fx + ',' + fy + ' C' + cp1x + ',' + cp1y + ' ' + cp2x + ',' + cp2y + ' ' + tx + ',' + ty);
            path.setAttribute('stroke', colors[colorIdx]);
            path.setAttribute('marker-end', 'url(#arrow-' + colorIdx + ')');
            svgLines.appendChild(path);

            var midX = (fx + tx) / 2;
            var midY = (fy + ty) / 2;
            var labelText = fk.column_name;

            var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            var tw = labelText.length * 5 + 10;
            var bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            bg.setAttribute('x', midX - tw/2); bg.setAttribute('y', midY - 7);
            bg.setAttribute('width', tw); bg.setAttribute('height', 13);
            bg.setAttribute('rx', '3'); bg.setAttribute('fill', '#fff');
            bg.setAttribute('stroke', '#e2e8f0'); bg.setAttribute('stroke-width', '1');
            g.appendChild(bg);

            var txt = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            txt.setAttribute('x', midX); txt.setAttribute('y', midY + 3);
            txt.setAttribute('text-anchor', 'middle'); txt.setAttribute('font-size', '8');
            txt.setAttribute('fill', '#475569'); txt.textContent = labelText;
            g.appendChild(txt);
            svgLines.appendChild(g);
        });
    }

    function updateTransform() {
        canvas.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + scale + ')';
        document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
    }

    var isPanning = false, panStartX, panStartY, panOrigX, panOrigY;
    wrap.addEventListener('mousedown', function(e) {
        if (e.target.closest('.er-table') || e.target.closest('.er-legend')) return;
        isPanning = true;
        panStartX = e.clientX; panStartY = e.clientY;
        panOrigX = panX; panOrigY = panY;
    });
    document.addEventListener('mousemove', function(e) {
        if (!isPanning) return;
        panX = panOrigX + (e.clientX - panStartX);
        panY = panOrigY + (e.clientY - panStartY);
        updateTransform();
    });
    document.addEventListener('mouseup', function() { isPanning = false; });

    wrap.addEventListener('wheel', function(e) {
        e.preventDefault();
        scale = Math.max(0.2, Math.min(2, scale + (e.deltaY > 0 ? -0.08 : 0.08)));
        updateTransform();
    }, { passive: false });

    window.erZoomIn = function() { scale = Math.min(2, scale + 0.15); updateTransform(); };
    window.erZoomOut = function() { scale = Math.max(0.2, scale - 0.15); updateTransform(); };
    window.erResetView = function() { scale = 0.75; panX = 30; panY = 30; updateTransform(); };

    renderTables();
    setTimeout(function() { updateTransform(); drawLines(); }, 100);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
