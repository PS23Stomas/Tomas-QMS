<?php
/**
 * Pareto analizė — defektų pasiskirstymas pagal reikalavimą (80/20 taisyklė)
 *
 * Rodo kombinuotą stulpelinį/linijinį grafiką:
 *   - Stulpeliai: defektų kiekis pagal reikalavimą (surikiuota nuo dažniausio)
 *   - Linija: kaupimosi procentas (kumuliatyvus %)
 *   - 80% horizontali riba
 *
 * Filtrai: laikotarpis, defekto sunkumas
 * Grupė: iš sesijos (aktyvus modulis)
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

if (!isset($_GET['grupe']) && empty($_SESSION['aktyvus_grupe'])) {
    header('Location: /moduliai.php');
    exit;
}

$filtro_grupe = $_GET['grupe'] ?? ($_SESSION['aktyvus_grupe'] ?? 'MT');

if (isset($_GET['grupe'])) {
    $stmt_mod = $pdo->prepare("SELECT id, pavadinimas FROM gaminiu_rusys WHERE pavadinimas = ? LIMIT 1");
    $stmt_mod->execute([$_GET['grupe']]);
    $mod_info = $stmt_mod->fetch(PDO::FETCH_ASSOC);
    if ($mod_info) {
        $_SESSION['aktyvus_modulis']     = (int)$mod_info['id'];
        $_SESSION['aktyvus_modulis_pav'] = $mod_info['pavadinimas'];
        $_SESSION['aktyvus_grupe']       = $mod_info['pavadinimas'];
    }
}

// ---------- Filtrai ----------
$periodas  = $_GET['periodas'] ?? '90d';
$sunkumas  = $_GET['sunkumas'] ?? 'visi';
$nuo_param = $_GET['nuo'] ?? '';
$iki_param = $_GET['iki'] ?? '';

// Datos pagal periodą
if ($nuo_param && $iki_param) {
    $nuo_date = $nuo_param;
    $iki_date = $iki_param;
    $periodas = 'custom';
} else {
    $iki_date = date('Y-m-d');
    switch ($periodas) {
        case '30d':  $nuo_date = date('Y-m-d', strtotime('-30 days')); break;
        case '90d':  $nuo_date = date('Y-m-d', strtotime('-90 days')); break;
        case '180d': $nuo_date = date('Y-m-d', strtotime('-180 days')); break;
        case '1y':   $nuo_date = date('Y-m-d', strtotime('-1 year')); break;
        case 'visi': $nuo_date = '2000-01-01'; break;
        default:     $nuo_date = date('Y-m-d', strtotime('-90 days')); $periodas = '90d';
    }
}

// ---------- DB užklausa ----------
$DEFECT_COND = "(fb.defektas IS NOT NULL AND TRIM(fb.defektas) <> '')";

$sunkumas_sql = '';
if ($sunkumas === 'critical') $sunkumas_sql = "AND fb.defekto_sunkumas = 'critical'";
elseif ($sunkumas === 'major') $sunkumas_sql = "AND fb.defekto_sunkumas = 'major'";
elseif ($sunkumas === 'minor') $sunkumas_sql = "AND fb.defekto_sunkumas = 'minor'";

$grupe_q = $pdo->quote($filtro_grupe);

$duomenys = $pdo->query("
    SELECT
        COALESCE(NULLIF(TRIM(fb.reikalavimas), ''), '(be reikalavimo)') AS reikalavimas,
        MIN(fb.eil_nr) AS eil_nr,
        COUNT(*) AS kiekis,
        COUNT(CASE WHEN fb.defekto_sunkumas = 'critical' THEN 1 END) AS critical_sk,
        COUNT(CASE WHEN fb.defekto_sunkumas = 'major'    THEN 1 END) AS major_sk,
        COUNT(CASE WHEN fb.defekto_sunkumas = 'minor'    THEN 1 END) AS minor_sk
    FROM funkciniai_bandymai fb
    JOIN gaminiai g ON fb.gaminio_id = g.id
    JOIN uzsakymai u ON g.uzsakymo_id = u.id
    JOIN gaminiu_rusys gr ON u.gaminiu_rusis_id = gr.id
    WHERE gr.pavadinimas = $grupe_q
      AND DATE(u.sukurtas) BETWEEN '$nuo_date' AND '$iki_date'
      AND $DEFECT_COND
      $sunkumas_sql
      AND fb.reikalavimas IS NOT NULL AND TRIM(fb.reikalavimas) <> ''
    GROUP BY COALESCE(NULLIF(TRIM(fb.reikalavimas), ''), '(be reikalavimo)')
    ORDER BY kiekis DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ---------- Skaičiavimai ----------
$viso_defektu = array_sum(array_column($duomenys, 'kiekis'));
$kaupiamasis = 0;
$pareto_items = [];
$riba_80_idx  = null;

foreach ($duomenys as $i => $row) {
    $kaupiamasis += (int)$row['kiekis'];
    $proc = $viso_defektu > 0 ? round($kaupiamasis / $viso_defektu * 100, 1) : 0;
    $pareto_items[] = [
        'reikalavimas' => $row['reikalavimas'],
        'eil_nr'       => (int)$row['eil_nr'],
        'kiekis'       => (int)$row['kiekis'],
        'kaupiamasis'  => $kaupiamasis,
        'proc'         => $proc,
        'critical_sk'  => (int)$row['critical_sk'],
        'major_sk'     => (int)$row['major_sk'],
        'minor_sk'     => (int)$row['minor_sk'],
    ];
    if ($riba_80_idx === null && $proc >= 80) {
        $riba_80_idx = $i;
    }
}

// Trumpinti ilgus reikalavimų pavadinimus grafikui
function trumpinti(string $s, int $max = 40): string {
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
}

$labels    = array_map(fn($r) => trumpinti($r['reikalavimas'], 35), $pareto_items);
$kiekiai   = array_column($pareto_items, 'kiekis');
$kumuliat  = array_column($pareto_items, 'proc');

$page_title = $filtro_grupe . ' Pareto analizė';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.pareto-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 1.25rem;
}
.period-btns { display: flex; gap: 4px; flex-wrap: wrap; }
.period-btn {
    padding: 5px 14px;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 13px;
    cursor: pointer;
    transition: all .15s;
    text-decoration: none;
}
.period-btn:hover { border-color: var(--accent-color); color: var(--accent-color); }
.period-btn.active { background: var(--accent-color); color: #fff; border-color: var(--accent-color); }
.custom-range-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.custom-range-form input[type=date] {
    padding: 5px 10px; border: 1px solid var(--border-color); border-radius: 6px;
    font-size: 13px; background: var(--bg-card); color: var(--text-primary);
}
.sunkumas-sel {
    padding: 5px 10px; border: 1px solid var(--border-color); border-radius: 6px;
    font-size: 13px; background: var(--bg-card); color: var(--text-primary); cursor: pointer;
}
.pareto-stats {
    display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 1.25rem;
}
.pareto-stat {
    flex: 1; min-width: 120px;
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 14px 16px;
    text-align: center;
}
.pareto-stat .val { font-size: 1.6rem; font-weight: 700; color: var(--accent-color); }
.pareto-stat .lbl { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.chart-wrap {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 10px; padding: 20px; margin-bottom: 1.25rem;
    position: relative;
}
.chart-wrap canvas { max-height: 420px; }
.pareto-table-wrap {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 10px; overflow-x: auto;
}
.pareto-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.pareto-table th {
    padding: 10px 14px; text-align: left;
    background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);
    font-size: 12px; font-weight: 600; color: var(--text-muted);
    white-space: nowrap;
}
.pareto-table td { padding: 9px 14px; border-bottom: 1px solid var(--border-color-light, #f1f5f9); }
.pareto-table tr:last-child td { border-bottom: none; }
.pareto-table tr.row-80 td { background: #fffbeb; }
.pareto-table tr.row-80-after td { opacity: .65; }
.bar-bg { height: 8px; border-radius: 4px; background: #e2e8f0; min-width: 60px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 4px; background: var(--accent-color); }
.badge-c { background:#fee2e2; color:#991b1b; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }
.badge-m { background:#ffedd5; color:#9a3412; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }
.badge-s { background:#fef9c3; color:#713f12; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }
.empty-state { text-align:center; padding:3rem; color:var(--text-muted); }
</style>

<div style="max-width:1200px;">

<?php
// Breadcrumbs
$bc_grupe = h($filtro_grupe);
$bc_url   = "/index.php?grupe=" . urlencode($filtro_grupe);
?>
<nav aria-label="Duonos trupiniai" style="margin-bottom:.75rem;font-size:13px;color:var(--text-muted);">
    <a href="<?= $bc_url ?>" style="color:var(--text-muted);text-decoration:none;">Kokybiniai rodikliai</a>
    <span style="margin:0 6px;">›</span>
    <span>Pareto analizė</span>
</nav>

<h1 style="font-size:1.4rem;font-weight:700;margin:0 0 1.25rem;">
    <?= h($filtro_grupe) ?> — Pareto defektų analizė
</h1>

<!-- ── Toolbar ────────────────────────────── -->
<form method="GET" class="pareto-toolbar" id="filterForm">
    <input type="hidden" name="grupe" value="<?= h($filtro_grupe) ?>">
    <input type="hidden" name="periodas" value="<?= h($periodas) ?>" id="periodasInput">

    <div class="period-btns">
        <?php
        $presets = ['30d'=>'30 d.','90d'=>'90 d.','180d'=>'180 d.','1y'=>'1 metai','visi'=>'Viskas'];
        foreach ($presets as $k => $lbl):
        ?>
        <button type="button" class="period-btn <?= $periodas === $k ? 'active' : '' ?>"
                onclick="setPreset('<?= $k ?>')"><?= $lbl ?></button>
        <?php endforeach; ?>
    </div>

    <div class="custom-range-form">
        <input type="date" name="nuo" id="nuoInput" value="<?= h($nuo_param ?: $nuo_date) ?>"
               onchange="setPreset('custom')" max="<?= date('Y-m-d') ?>">
        <span style="color:var(--text-muted);font-size:13px;">—</span>
        <input type="date" name="iki" id="ikiInput" value="<?= h($iki_param ?: $iki_date) ?>"
               onchange="setPreset('custom')" max="<?= date('Y-m-d') ?>">
    </div>

    <select name="sunkumas" class="sunkumas-sel" onchange="this.form.submit()">
        <option value="visi"     <?= $sunkumas==='visi'     ?'selected':'' ?>>Visi sunkumai</option>
        <option value="critical" <?= $sunkumas==='critical' ?'selected':'' ?>>Tik kritiniai</option>
        <option value="major"    <?= $sunkumas==='major'    ?'selected':'' ?>>Tik dideli</option>
        <option value="minor"    <?= $sunkumas==='minor'    ?'selected':'' ?>>Tik maži</option>
    </select>
</form>

<!-- ── Statistikos plytelės ───────────────── -->
<div class="pareto-stats">
    <div class="pareto-stat">
        <div class="val"><?= $viso_defektu ?></div>
        <div class="lbl">Iš viso defektų</div>
    </div>
    <div class="pareto-stat">
        <div class="val"><?= count($pareto_items) ?></div>
        <div class="lbl">Operacijų su defektais</div>
    </div>
    <div class="pareto-stat">
        <div class="val"><?= $riba_80_idx !== null ? ($riba_80_idx + 1) : '—' ?></div>
        <div class="lbl">Operacijų 80% problemoms</div>
    </div>
    <div class="pareto-stat">
        <div class="val">
            <?php
            if ($riba_80_idx !== null && count($pareto_items) > 0) {
                echo round(($riba_80_idx + 1) / count($pareto_items) * 100) . '%';
            } else { echo '—'; }
            ?>
        </div>
        <div class="lbl">Dalis operacijų (80/20)</div>
    </div>
</div>

<?php if (empty($pareto_items)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:.4;">
        <path d="M18 20V10M12 20V4M6 20v-6"/>
    </svg>
    <p>Nėra defektų duomenų pasirinktam laikotarpiui.</p>
</div>
<?php else: ?>

<!-- ── Pareto grafikas ────────────────────── -->
<div class="chart-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div>
            <span style="font-size:.85rem;font-weight:600;">Defektai pagal operaciją</span>
            <span style="font-size:.75rem;color:var(--text-muted);margin-left:8px;">
                <?= date('Y-m-d', strtotime($nuo_date)) ?> – <?= date('Y-m-d', strtotime($iki_date)) ?>
            </span>
        </div>
        <div style="display:flex;gap:16px;font-size:12px;color:var(--text-muted);">
            <span><span style="display:inline-block;width:12px;height:12px;background:#3b82f6;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Defektų kiekis</span>
            <span><span style="display:inline-block;width:24px;height:2px;background:#ef4444;margin-right:4px;vertical-align:middle;"></span>Kumuliatyvus %</span>
        </div>
    </div>
    <canvas id="paretoChart"></canvas>
</div>

<!-- ── Detalė lentelė ─────────────────────── -->
<div class="pareto-table-wrap">
    <table class="pareto-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Operacija / Reikalavimas</th>
                <th style="text-align:right;">Defektai</th>
                <th style="min-width:100px;">Dalis</th>
                <th style="text-align:right;">Kaupiamasis</th>
                <th style="text-align:right;">Kum. %</th>
                <th style="text-align:center;">Sunkumas</th>
            </tr>
        </thead>
        <tbody>
        <?php $max_kiekis = $pareto_items[0]['kiekis'] ?? 1; ?>
        <?php foreach ($pareto_items as $i => $row): ?>
            <?php
            $is80    = ($i === $riba_80_idx);
            $after80 = ($riba_80_idx !== null && $i > $riba_80_idx);
            $cls     = $is80 ? 'row-80' : ($after80 ? 'row-80-after' : '');
            $fill_w  = round($row['kiekis'] / $max_kiekis * 100);
            ?>
            <tr class="<?= $cls ?>">
                <td style="color:var(--text-muted);font-size:12px;"><?= $i + 1 ?><?= $is80 ? ' <span title="80% riba" style="color:#f59e0b;">★</span>' : '' ?></td>
                <td>
                    <?php if ($row['eil_nr']): ?>
                        <span style="color:var(--text-muted);font-size:11px;margin-right:4px;"><?= $row['eil_nr'] ?>.</span>
                    <?php endif; ?>
                    <?= h($row['reikalavimas']) ?>
                </td>
                <td style="text-align:right;font-weight:600;"><?= $row['kiekis'] ?></td>
                <td>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width:<?= $fill_w ?>%;"></div>
                    </div>
                </td>
                <td style="text-align:right;color:var(--text-muted);"><?= $row['kaupiamasis'] ?></td>
                <td style="text-align:right;font-weight:600;<?= $row['proc'] >= 80 ? 'color:#16a34a;' : '' ?>">
                    <?= $row['proc'] ?>%
                </td>
                <td style="text-align:center;">
                    <?php if ($row['critical_sk'] > 0): ?><span class="badge-c" title="Kritiniai"><?= $row['critical_sk'] ?></span> <?php endif; ?>
                    <?php if ($row['major_sk']    > 0): ?><span class="badge-m" title="Dideli"><?= $row['major_sk'] ?></span> <?php endif; ?>
                    <?php if ($row['minor_sk']    > 0): ?><span class="badge-s" title="Maži"><?= $row['minor_sk'] ?></span> <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700;background:var(--bg-secondary);">
                <td colspan="2" style="padding:9px 14px;">Iš viso</td>
                <td style="text-align:right;padding:9px 14px;"><?= $viso_defektu ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Filtro mygtukai ──────────────────────────────────────────
function setPreset(val) {
    document.getElementById('periodasInput').value = val;
    if (val !== 'custom') {
        document.getElementById('nuoInput').value = '';
        document.getElementById('ikiInput').value = '';
    }
    document.getElementById('filterForm').submit();
}

<?php if (!empty($pareto_items)): ?>
// ── Pareto grafikas ──────────────────────────────────────────
(function() {
    const labels   = <?= json_encode($labels) ?>;
    const kiekiai  = <?= json_encode($kiekiai) ?>;
    const kumuliat = <?= json_encode($kumuliat) ?>;
    const riba80   = <?= $riba_80_idx !== null ? ($riba_80_idx + 1) : 'null' ?>;

    // Stulpelių spalvos: iki 80% — mėlyna, po — pilka
    const barColors = kiekiai.map((_, i) =>
        riba80 === null || i < riba80 ? 'rgba(59,130,246,0.85)' : 'rgba(148,163,184,0.55)'
    );

    const ctx = document.getElementById('paretoChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Defektų kiekis',
                    data: kiekiai,
                    backgroundColor: barColors,
                    borderColor: barColors.map(c => c.replace('0.85','1').replace('0.55','0.8')),
                    borderWidth: 1,
                    borderRadius: 3,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Kumuliatyvus %',
                    data: kumuliat,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.08)',
                    borderWidth: 2.5,
                    pointRadius: labels.length > 20 ? 2 : 4,
                    pointBackgroundColor: '#ef4444',
                    fill: false,
                    tension: 0.15,
                    yAxisID: 'y2',
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        afterBody: function(ctx) {
                            const i = ctx[0].dataIndex;
                            return `Kumuliatyvus: ${kumuliat[i]}%`;
                        }
                    }
                },
                annotation: undefined
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 45,
                        callback: function(val, i) {
                            const s = labels[i] || '';
                            return s.length > 28 ? s.slice(0, 27) + '…' : s;
                        }
                    },
                    grid: { display: false }
                },
                y: {
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Defektų sk.', font: { size: 11 } },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y2: {
                    position: 'right',
                    min: 0, max: 100,
                    title: { display: true, text: 'Kumuliatyvus %', font: { size: 11 } },
                    ticks: {
                        callback: v => v + '%',
                        stepSize: 20
                    },
                    grid: { drawOnChartArea: false }
                }
            }
        },
        plugins: [{
            // 80% horizontali riba
            id: 'line80',
            afterDraw(chart) {
                const y2 = chart.scales.y2;
                if (!y2) return;
                const y = y2.getPixelForValue(80);
                const ctx = chart.ctx;
                const left = chart.chartArea.left;
                const right = chart.chartArea.right;
                ctx.save();
                ctx.setLineDash([6, 4]);
                ctx.strokeStyle = '#f59e0b';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.moveTo(left, y);
                ctx.lineTo(right, y);
                ctx.stroke();
                ctx.fillStyle = '#f59e0b';
                ctx.font = 'bold 11px Inter, sans-serif';
                ctx.fillText('80%', right + 4, y + 4);
                ctx.restore();
            }
        }]
    });
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
