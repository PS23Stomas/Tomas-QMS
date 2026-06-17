<?php
/**
 * quality_tomas importo valdymas
 * Prieiga: tik administratorius
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

if (currentUser()['role'] !== 'administratorius') {
    http_response_code(403);
    die('Prieiga uždrausta.');
}

set_time_limit(600);
ignore_user_abort(true);

$qt_ok   = (bool)getenv('QUALITY_TOMAS_DATABASE_URL');
$veiksmas   = $_POST['veiksmas'] ?? '';
$rezultatas = null;
$klaida     = null;

$local_uzs_count = (int)$pdo->query("SELECT COUNT(*) FROM uzsakymai WHERE gaminiu_rusis_id=2")->fetchColumn();
$local_gam_count = (int)$pdo->query("SELECT COUNT(*) FROM gaminiai g JOIN uzsakymai u ON u.id=g.uzsakymo_id WHERE u.gaminiu_rusis_id=2")->fetchColumn();

// ── PDF importo pagalbinės funkcijos ─────────────────────────────────────────

function qtPdfConn(): PDO {
    static $c = null;
    if ($c) return $c;
    $url   = getenv('QUALITY_TOMAS_DATABASE_URL');
    $parts = parse_url($url);
    $dsn   = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432)
           . ';dbname=' . ltrim($parts['path'], '/');
    if (str_contains($url, 'sslmode=require')) $dsn .= ';sslmode=require';
    $c = new PDO($dsn, $parts['user'], $parts['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $c;
}

function qtRawBytes($v): string {
    if (is_resource($v)) $v = stream_get_contents($v);
    if (is_string($v) && str_starts_with($v, '\\x')) return hex2bin(substr($v, 2));
    return (string)$v;
}

function importuotiGvxPdf(PDO $local): array {
    $qt = qtPdfConn();

    // quality_tomas: uzsakymai.id → uzsakymo_numeris
    $qt_id_to_nr = [];
    try {
        foreach ($qt->query("SELECT id, uzsakymo_numeris FROM uzsakymai WHERE uzsakymo_numeris IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $qt_id_to_nr[(int)$r['id']] = $r['uzsakymo_numeris'];
        }
    } catch (Exception $e) {}

    // local: uzsakymo_numeris → uzsakymai.id
    $local_nr_to_id = [];
    foreach ($local->query("SELECT id, uzsakymo_numeris FROM uzsakymai WHERE uzsakymo_numeris IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $local_nr_to_id[$r['uzsakymo_numeris']] = (int)$r['id'];
    }

    // sukurti lentelę jei nėra
    $local->exec("CREATE TABLE IF NOT EXISTS gvx_dokumentai (
        id SERIAL PRIMARY KEY, uzsakymo_id INTEGER NOT NULL,
        tipas VARCHAR(50) NOT NULL, pavadinimas VARCHAR(500), failas VARCHAR(500),
        dydis_b INTEGER, turinys_lob BYTEA,
        sukurta TIMESTAMP DEFAULT CURRENT_TIMESTAMP, sukurejas VARCHAR(200)
    )");
    $local->exec("CREATE INDEX IF NOT EXISTS idx_gvx_dokumentai_uzs ON gvx_dokumentai(uzsakymo_id, tipas)");

    // jau esantys (pagal uzsakymo_id + failas)
    $jau = [];
    foreach ($local->query("SELECT uzsakymo_id, failas FROM gvx_dokumentai")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $jau[$r['uzsakymo_id'] . '|' . $r['failas']] = true;
    }

    // aptikti quality_tomas schemą
    $has_uzs = false; try { $qt->query("SELECT uzsakymo_id FROM gvx_dokumentai LIMIT 0"); $has_uzs = true; } catch (Exception $e) {}
    $has_gam = false; try { $qt->query("SELECT gaminys_id FROM gvx_dokumentai LIMIT 0"); $has_gam = true; } catch (Exception $e) {}
    $oid_type = false;
    try {
        $tr = $qt->query("SELECT data_type FROM information_schema.columns WHERE table_name='gvx_dokumentai' AND column_name='turinys_lob' AND table_schema='public'")->fetch();
        $oid_type = $tr && strtolower($tr['data_type']) === 'oid';
    } catch (Exception $e) {}

    $tipai = "'mt_deklaracija','mt_deklaracija_pdf','nustatymu_protokolas'";
    if ($has_uzs) {
        $rows = $qt->query("SELECT id, uzsakymo_id, tipas, pavadinimas, failas FROM gvx_dokumentai WHERE tipas IN ({$tipai}) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($has_gam) {
        $rows = $qt->query("SELECT d.id, u.id AS uzsakymo_id, d.tipas, d.pavadinimas, d.failas FROM gvx_dokumentai d LEFT JOIN gaminiai g ON g.id=d.gaminys_id LEFT JOIN uzsakymai u ON u.id=g.uzsakymo_id WHERE d.tipas IN ({$tipai}) ORDER BY d.id")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return ['perkelti' => 0, 'praleista' => 0, 'nesusieta' => 0, 'klaidos' => ['quality_tomas gvx_dokumentai schemos nerasta']];
    }

    $ins = $local->prepare("INSERT INTO gvx_dokumentai (uzsakymo_id,tipas,pavadinimas,failas,dydis_b,turinys_lob,sukurejas) VALUES (?,?,?,?,?,?,?)");
    $perkelti = $praleista = $nesusieta = 0;
    $klaidos = [];

    foreach ($rows as $r) {
        $qt_uzs_id = (int)($r['uzsakymo_id'] ?? 0);
        $uzs_nr    = $qt_uzs_id ? ($qt_id_to_nr[$qt_uzs_id] ?? null) : null;
        if (!$uzs_nr || !isset($local_nr_to_id[$uzs_nr])) { $nesusieta++; continue; }

        $local_uzs_id = $local_nr_to_id[$uzs_nr];
        $failas = $r['failas'] ?? '';
        $key    = $local_uzs_id . '|' . $failas;
        if (isset($jau[$key])) { $praleista++; continue; }

        try {
            if ($oid_type) {
                $qt->beginTransaction();
                $oid  = (int)$qt->query("SELECT turinys_lob FROM gvx_dokumentai WHERE id=" . (int)$r['id'])->fetchColumn();
                $blob = $qt->query("SELECT lo_get({$oid})")->fetchColumn();
                $qt->commit();
            } else {
                $blob = $qt->query("SELECT turinys_lob FROM gvx_dokumentai WHERE id=" . (int)$r['id'])->fetchColumn();
            }
            $content = qtRawBytes($blob);
        } catch (Exception $e) {
            if ($oid_type && $qt->inTransaction()) $qt->rollBack();
            $klaidos[] = h($failas) . ': ' . h($e->getMessage());
            continue;
        }

        if (empty($content)) { $klaidos[] = h($failas) . ': turinys tuščias'; continue; }

        try {
            $ins->execute([$local_uzs_id, $r['tipas'], $r['pavadinimas'] ?? '', $failas, strlen($content), $content, 'qt_import']);
            $jau[$key] = true;
            $perkelti++;
        } catch (Exception $e) {
            $klaidos[] = h($failas) . ': ' . h($e->getMessage());
        }
    }

    return compact('perkelti', 'praleista', 'nesusieta', 'klaidos');
}

// ── POST veiksmai ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if ($veiksmas === 'importuoti_i_local') {
        $pradzia = microtime(true);
        $rez = TomoQMS::importuotiILocalDB($pdo);
        $trukme = round(microtime(true) - $pradzia, 2);
        isset($rez['klaida']) ? $klaida = $rez['klaida']
            : $rezultatas = ['tipas' => 'local', 'trukme' => $trukme, 'duomenys' => $rez];

    } elseif ($veiksmas === 'importuoti_pdf') {
        $pradzia = microtime(true);
        try {
            $rez = importuotiGvxPdf($pdo);
            $trukme = round(microtime(true) - $pradzia, 2);
            $rezultatas = ['tipas' => 'pdf', 'trukme' => $trukme, 'duomenys' => $rez];
        } catch (Exception $e) {
            $klaida = $e->getMessage();
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="max-width:760px;margin:32px auto;">

<nav aria-label="Breadcrumb" class="breadcrumb-nav">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/index.php">Pradžia</a></li>
        <li class="breadcrumb-item active">QT Importas</li>
    </ol>
</nav>

<h2 style="margin-bottom:4px;">quality_tomas importas</h2>
<p style="color:var(--text-secondary);margin-bottom:24px;font-size:.9rem;">
    Duomenų ir PDF perkėlimas iš išorinės quality_tomas DB į šią sistemą
</p>

<!-- Aplinkos statusas -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <div style="flex:1;min-width:180px;background:var(--card-bg);border:1px solid var(--border-color);border-radius:8px;padding:12px 16px;">
        <div style="font-size:.78rem;color:var(--text-secondary);margin-bottom:4px;">QUALITY_TOMAS_DATABASE_URL</div>
        <div style="font-weight:600;color:<?= $qt_ok ? '#22c55e' : '#ef4444' ?>;">
            <?= $qt_ok ? '✓ Prisijungta' : '✗ Nenustatyta' ?>
        </div>
    </div>
    <div style="flex:1;min-width:180px;background:var(--card-bg);border:1px solid var(--border-color);border-radius:8px;padding:12px 16px;">
        <div style="font-size:.78rem;color:var(--text-secondary);margin-bottom:4px;">Vietinė DB (ši sistema)</div>
        <div style="font-weight:600;color:#22c55e;">✓ Prisijungta</div>
    </div>
</div>

<?php if (!$qt_ok): ?>
<div style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
    Trūksta QUALITY_TOMAS_DATABASE_URL aplinkos kintamojo. Importas neveiks.
</div>
<?php endif; ?>

<?php if ($klaida): ?>
<div style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
    <strong>Klaida:</strong> <?= h($klaida) ?>
</div>
<?php endif; ?>

<!-- Rezultatas -->
<?php if ($rezultatas): ?>
<div style="background:var(--card-bg);border:1px solid #22c55e;border-radius:10px;padding:22px;margin-bottom:24px;">
    <?php if ($rezultatas['tipas'] === 'local'): $r = $rezultatas['duomenys']; ?>
    <h3 style="margin:0 0 14px;font-size:1rem;">✓ Duomenys importuoti</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <?php foreach ([
            'Nauji užsakymai' => $r['nauji'] ?? 0,
            'Atnaujinti' => $r['atnaujinti'] ?? 0,
            'Gaminiai' => $r['gaminiai'] ?? 0,
            'Bandymai' => $r['bandymai'] ?? 0,
            'Komponentai' => $r['komponentai'] ?? 0,
            'Dielektriniai' => $r['dielektriniai'] ?? 0,
        ] as $label => $val): ?>
        <div style="background:var(--bg-secondary,#f3f4f6);border-radius:6px;padding:7px 13px;font-size:.84rem;">
            <strong style="display:block;font-size:1.15rem;font-weight:700;"><?= $val ?></strong><?= $label ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($r['klaidos'])): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;color:#ef4444;font-size:.85rem;font-weight:600;"><?= count($r['klaidos']) ?> klaida(-os)</summary>
        <ul style="margin:8px 0 0;padding-left:20px;"><?php foreach (array_slice($r['klaidos'], 0, 20) as $e): ?><li style="font-size:.82rem;"><?= h($e) ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>

    <?php elseif ($rezultatas['tipas'] === 'pdf'): $r = $rezultatas['duomenys']; ?>
    <h3 style="margin:0 0 14px;font-size:1rem;">✓ PDF importuoti</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <div style="background:var(--bg-secondary,#f3f4f6);border-radius:6px;padding:7px 13px;font-size:.84rem;">
            <strong style="display:block;font-size:1.4rem;font-weight:700;color:#16a34a;"><?= $r['perkelti'] ?></strong>Perkelta
        </div>
        <div style="background:var(--bg-secondary,#f3f4f6);border-radius:6px;padding:7px 13px;font-size:.84rem;">
            <strong style="display:block;font-size:1.4rem;font-weight:700;color:var(--text-secondary);"><?= $r['praleista'] ?></strong>Jau buvo
        </div>
        <div style="background:var(--bg-secondary,#f3f4f6);border-radius:6px;padding:7px 13px;font-size:.84rem;">
            <strong style="display:block;font-size:1.4rem;font-weight:700;color:#f59e0b;"><?= $r['nesusieta'] ?></strong>Nesusieta
        </div>
    </div>
    <?php if (!empty($r['klaidos'])): ?>
    <details style="margin-top:10px;">
        <summary style="cursor:pointer;color:#ef4444;font-size:.85rem;font-weight:600;"><?= count($r['klaidos']) ?> klaida(-os)</summary>
        <ul style="margin:8px 0 0;padding-left:20px;"><?php foreach ($r['klaidos'] as $e): ?><li style="font-size:.82rem;"><?= $e ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:12px;font-size:.83rem;color:var(--text-secondary);">Trukmė: <?= $rezultatas['trukme'] ?> sek.</div>
</div>
<?php endif; ?>

<!-- Vietinės DB statusas -->
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:.87rem;color:#1e40af;">
    📊 <strong>Ši sistema:</strong>
    MT užsakymai: <strong><?= $local_uzs_count ?></strong> &nbsp;|&nbsp;
    MT gaminiai: <strong><?= $local_gam_count ?></strong>
    <?php
    $gvx_sk = 0;
    try { $gvx_sk = (int)$pdo->query("SELECT COUNT(*) FROM gvx_dokumentai")->fetchColumn(); } catch (Exception $e) {}
    ?>
    &nbsp;|&nbsp; PDF dokumentai (gvx): <strong><?= $gvx_sk ?></strong>
</div>

<!-- Du veiksmai -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    <div style="background:var(--card-bg);border:1px solid #22c55e;border-radius:10px;padding:22px;display:flex;flex-direction:column;">
        <h3 style="font-size:1rem;font-weight:600;margin:0 0 8px;">📥 Importuoti duomenis</h3>
        <p style="font-size:.84rem;color:var(--text-secondary);margin:0 0 auto;line-height:1.5;padding-bottom:16px;">
            Užsakymai, gaminiai, bandymai, komponentai, pretenzijos iš quality_tomas į šią sistemą.
            Gali užtrukti 2–5 min.
        </p>
        <form method="POST" onsubmit="return confirm('Importuoti duomenis iš quality_tomas? Gali užtrukti kelias minutes.')">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="veiksmas" value="importuoti_i_local">
            <button type="submit" class="btn btn-primary" style="width:100%;background:#16a34a;" data-testid="button-import-data" <?= !$qt_ok ? 'disabled' : '' ?>>
                Importuoti duomenis
            </button>
        </form>
    </div>

    <div style="background:var(--card-bg);border:1px solid var(--primary);border-radius:10px;padding:22px;display:flex;flex-direction:column;">
        <h3 style="font-size:1rem;font-weight:600;margin:0 0 8px;">📄 Importuoti PDF</h3>
        <p style="font-size:.84rem;color:var(--text-secondary);margin:0 0 auto;line-height:1.5;padding-bottom:16px;">
            Paso ir nustatymų protokolų PDF iš quality_tomas <code>gvx_dokumentai</code> į šią sistemą.
            Dublikatai praleist. Gali užtrukti kelias minutes.
        </p>
        <form method="POST" onsubmit="return confirm('Importuoti PDF iš quality_tomas? Tai kopijuoja paso ir protokolų failus.')">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="veiksmas" value="importuoti_pdf">
            <button type="submit" class="btn btn-primary" style="width:100%;" data-testid="button-import-pdf" <?= !$qt_ok ? 'disabled' : '' ?>>
                Importuoti PDF
            </button>
        </form>
    </div>

</div>

<div style="font-size:.83rem;color:var(--text-secondary);margin-bottom:24px;">
    ⚠️ Importas atnaujina esamus įrašus pagal užsakymo numerį (ne dubliuoja). PDF importas gali būti paleidžiamas kelis kartus — dublikatai praleidžiami.
</div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
