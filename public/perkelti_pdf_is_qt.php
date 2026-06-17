<?php
/**
 * Perkelti PDF iš quality_tomas į Tomo_QMS
 *
 * Kopijuoja quality_tomas.gvx_dokumentai įrašus (tipas IN mt_deklaracija,
 * mt_deklaracija_pdf, nustatymu_protokolas) → tomas_qms.gvx_dokumentai.
 *
 * Užsakymo siejimas: quality_tomas.uzsakymai.uzsakymo_numeris
 *                  → tomas_qms.uzsakymai.uzsakymo_numeris
 * (ID reikšmės skiriasi tarp sistemų)
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
if (currentUser()['role'] !== 'administratorius') {
    http_response_code(403);
    echo '<p>Tik administratoriams.</p>';
    exit;
}

// ── Prisijungimai ──────────────────────────────────────────────────────────────

function qtConn(): PDO {
    static $conn = null;
    if ($conn) return $conn;
    $url   = getenv('QUALITY_TOMAS_DATABASE_URL');
    $parts = parse_url($url);
    $dsn   = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432)
           . ';dbname=' . ltrim($parts['path'], '/');
    if (str_contains($url, 'sslmode=require')) $dsn .= ';sslmode=require';
    $conn = new PDO($dsn, $parts['user'], $parts['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $conn;
}

function tqConn(): PDO {
    static $conn = null;
    if ($conn) return $conn;
    $url   = getenv('TOMO_QMS_DATABASE_URL');
    $parts = parse_url($url);
    $dsn   = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432)
           . ';dbname=' . ltrim($parts['path'], '/');
    if (str_contains($url, 'sslmode=require')) $dsn .= ';sslmode=require';
    $conn = new PDO($dsn, $parts['user'], $parts['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $conn;
}

// ── Pagalbinės funkcijos ───────────────────────────────────────────────────────

/**
 * Nustato gvx_dokumentai.turinys_lob stulpelio tipą quality_tomas DB.
 * Grąžina 'oid', 'bytea' arba null.
 */
function qtLobTipas(): ?string {
    static $tipas = false;
    if ($tipas !== false) return $tipas;
    try {
        $t = qtConn()->query("
            SELECT data_type FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name   = 'gvx_dokumentai'
              AND column_name  = 'turinys_lob'
            LIMIT 1
        ")->fetchColumn();
        $tipas = $t ?: null;
    } catch (Exception $e) {
        $tipas = null;
    }
    return $tipas;
}

/**
 * SQL dydžio išraiška pagal turinys_lob tipą.
 */
function dydisSQL(): string {
    if (qtLobTipas() === 'oid') {
        return "COALESCE((SELECT SUM(octet_length(data)) FROM pg_largeobject WHERE loid = turinys_lob::oid), 0)";
    }
    return "COALESCE(octet_length(turinys_lob), 0)";
}

/**
 * BYTEA → PostgreSQL hex-escaped formatas (\xAABB...).
 * Tvarko: resource (stream), \x... eilutė, gryna binarinė eilutė.
 */
function byteaHex(mixed $val): ?string {
    if ($val === null) return null;
    if (is_resource($val)) {
        $bin = stream_get_contents($val);
        if ($bin === '' || $bin === false) return null;
        return '\\x' . bin2hex($bin);
    }
    $s = (string)$val;
    if ($s === '') return null;
    if (str_starts_with($s, '\\x') || str_starts_with($s, "\x5cx")) {
        return $s;
    }
    return '\\x' . bin2hex($s);
}

/**
 * Sukuria tomas_qms.gvx_dokumentai lentelę jei jos dar nėra.
 */
function uztikriniGvxDokumentai(PDO $tq): void {
    $tq->exec("
        CREATE TABLE IF NOT EXISTS gvx_dokumentai (
            id          SERIAL PRIMARY KEY,
            uzsakymo_id INTEGER,
            tipas       VARCHAR(100),
            pavadinimas VARCHAR(500),
            failas      VARCHAR(500),
            dydis_b     INTEGER,
            turinys_lob BYTEA,
            sukurta     TIMESTAMP DEFAULT NOW(),
            sukurejas   VARCHAR(255)
        )
    ");
}

/**
 * Surinkia žemėlapius siejimui:
 *   qt_uzs_id_to_nr  : quality_tomas uzsakymai.id → uzsakymo_numeris
 *   tq_nr_to_uzs_id  : tomas_qms uzsakymo_numeris → uzsakymai.id
 *   tq_jau           : jau perkelti įrašai (uzsakymo_id|tipas|failas → true)
 */
function surinktZemelapius(): array {
    $qt = qtConn();
    $tq = tqConn();

    // quality_tomas: uzsakymai.id → uzsakymo_numeris
    $qt_uzs_id_to_nr = [];
    try {
        $rows = $qt->query("SELECT id, uzsakymo_numeris FROM uzsakymai WHERE uzsakymo_numeris IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $qt_uzs_id_to_nr[(int)$r['id']] = $r['uzsakymo_numeris'];
    } catch (Exception $e) { /* gali neegzistuoti */ }

    // tomas_qms: uzsakymo_numeris → uzsakymai.id
    $tq_nr_to_uzs_id = [];
    try {
        $rows = $tq->query("SELECT id, uzsakymo_numeris FROM uzsakymai WHERE uzsakymo_numeris IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $tq_nr_to_uzs_id[$r['uzsakymo_numeris']] = (int)$r['id'];
    } catch (Exception $e) { /* gali neegzistuoti */ }

    // Jau perkelti į tomas_qms.gvx_dokumentai — tikrinama pagal uzsakymo_id + failas
    $tq_jau = [];
    try {
        $exists = $tq->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name='gvx_dokumentai' AND table_schema='public'")->fetchColumn();
        if ($exists) {
            $rows = $tq->query("SELECT uzsakymo_id, failas FROM gvx_dokumentai")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $tq_jau[$r['uzsakymo_id'] . '|' . $r['failas']] = true;
            }
        }
    } catch (Exception $e) { /* praleisti */ }

    return [$qt_uzs_id_to_nr, $tq_nr_to_uzs_id, $tq_jau];
}

/**
 * Surinkia visus darbus (peržiūrai ir vykdymui).
 * Grąžina [perkelti[], praleista[]].
 * perkelti[]: qt_dok_id, tq_uzs_id, qt_uzs_nr, tipas, pavadinimas, failas, dydis, jau_perkeltas
 * praleista[]: tipas, failas, priežastis
 */
function surinktDarbus(): array {
    $qt = qtConn();
    [$qt_uzs_id_to_nr, $tq_nr_to_uzs_id, $tq_jau] = surinktZemelapius();

    $dydis_sql = dydisSQL();
    $tipai = "'mt_deklaracija','mt_deklaracija_pdf','nustatymu_protokolas'";

    // Bandyti su uzsakymo_id stulpeliu
    $has_uzs_id = false;
    try {
        $qt->query("SELECT uzsakymo_id FROM gvx_dokumentai LIMIT 0");
        $has_uzs_id = true;
    } catch (Exception $e) {}

    // Bandyti su gaminys_id stulpeliu (senas variantas)
    $has_gam_id = false;
    try {
        $qt->query("SELECT gaminys_id FROM gvx_dokumentai LIMIT 0");
        $has_gam_id = true;
    } catch (Exception $e) {}

    // Nuskaityti dokumentus
    if ($has_uzs_id) {
        $rows = $qt->query("
            SELECT id, uzsakymo_id, tipas, pavadinimas, failas, {$dydis_sql} AS dydis
            FROM gvx_dokumentai
            WHERE tipas IN ({$tipai})
            ORDER BY tipas, id
        ")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($has_gam_id) {
        // Sena struktūra: gaminys_id → bandyti per gaminiai.uzsakymo_id → uzsakymai
        $rows = $qt->query("
            SELECT d.id, u.id AS uzsakymo_id, d.tipas, d.pavadinimas, d.failas, {$dydis_sql} AS dydis
            FROM gvx_dokumentai d
            LEFT JOIN gaminiai g ON g.id = d.gaminys_id
            LEFT JOIN uzsakymai u ON u.id = g.uzsakymo_id
            WHERE d.tipas IN ({$tipai})
            ORDER BY d.tipas, d.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = [];
    }

    $perkelti  = [];
    $praleista = [];

    foreach ($rows as $r) {
        $qt_uzs_id = (int)($r['uzsakymo_id'] ?? 0);
        // Siejimas tik per uzsakymo_numeris — jokio spekuliatyvaus atspėjimo pagal failo vardą
        $uzs_nr = $qt_uzs_id ? ($qt_uzs_id_to_nr[$qt_uzs_id] ?? null) : null;

        if (!$uzs_nr || !isset($tq_nr_to_uzs_id[$uzs_nr])) {
            $praleista[] = [
                'tipas'     => $r['tipas'],
                'failas'    => $r['failas'] ?? '?',
                'priežastis' => $uzs_nr
                    ? "Užsakymas \"{$uzs_nr}\" nerastas tomas_qms"
                    : 'Uzsakymo numeris nerastas quality_tomas',
            ];
            continue;
        }

        $tq_uzs_id = $tq_nr_to_uzs_id[$uzs_nr];
        $jau_key   = $tq_uzs_id . '|' . ($r['failas'] ?? '');

        $perkelti[] = [
            'qt_dok_id'    => $r['id'],
            'tq_uzs_id'    => $tq_uzs_id,
            'qt_uzs_nr'    => $uzs_nr,
            'tipas'        => $r['tipas'],
            'pavadinimas'  => $r['pavadinimas'] ?? '',
            'failas'       => $r['failas'] ?? '',
            'dydis'        => $r['dydis'],
            'jau_perkeltas' => isset($tq_jau[$jau_key]),
        ];
    }

    return [$perkelti, $praleista];
}

// ── Vykdymas POST ──────────────────────────────────────────────────────────────
$rezultatai  = null;
$conn_klaida = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['vykdyti'] ?? '') === '1') {
    ignore_user_abort(true);
    set_time_limit(600);
    ini_set('memory_limit', '512M');

    try {
        [$perkelti, $praleista] = surinktDarbus();
        $tq = tqConn();
        $qt = qtConn();

        uztikriniGvxDokumentai($tq);

        $isOid = qtLobTipas() === 'oid';
        $turinys_select = $isOid
            ? "SELECT lo_get(turinys_lob) AS turinys_lob, failas, pavadinimas FROM gvx_dokumentai WHERE id = ?"
            : "SELECT turinys_lob, failas, pavadinimas FROM gvx_dokumentai WHERE id = ?";
        $sel = $qt->prepare($turinys_select);

        $ins = $tq->prepare("
            INSERT INTO gvx_dokumentai
                (uzsakymo_id, tipas, pavadinimas, failas, dydis_b, turinys_lob, sukurta)
            VALUES
                (:uzs_id, :tipas, :pavadinimas, :failas, :dydis_b, :turinys, NOW())
        ");

        $ok = $pral = 0;
        $klaidos = [];

        foreach ($perkelti as $d) {
            if ($d['jau_perkeltas']) { $pral++; continue; }
            try {
                if ($isOid) $qt->beginTransaction();
                $sel->execute([$d['qt_dok_id']]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if ($isOid) $qt->commit();

                if (!$row) {
                    $klaidos[] = '[' . $d['failas'] . ']: įrašas nerastas gvx_dokumentai';
                    $pral++; continue;
                }
                $turinys = byteaHex($row['turinys_lob'] ?? null);
                if (!$turinys) {
                    $klaidos[] = '[' . $d['failas'] . ']: turinys tuščias';
                    $pral++; continue;
                }

                $ins->bindValue(':uzs_id',     $d['tq_uzs_id']);
                $ins->bindValue(':tipas',       $d['tipas']);
                $ins->bindValue(':pavadinimas', $row['pavadinimas'] ?? $d['pavadinimas']);
                $ins->bindValue(':failas',      $row['failas'] ?? $d['failas']);
                $ins->bindValue(':dydis_b',     (int)$d['dydis']);
                $ins->bindValue(':turinys',     $turinys, PDO::PARAM_STR);
                $ins->execute();
                $ok++;
            } catch (Exception $e) {
                if ($isOid && $qt->inTransaction()) $qt->rollBack();
                $klaidos[] = '[' . $d['failas'] . ']: ' . $e->getMessage();
                $pral++;
            }
        }

        $rezultatai = compact('ok', 'pral', 'klaidos', 'praleista');
    } catch (Exception $e) {
        $conn_klaida = $e->getMessage();
    }
}

// ── Peržiūra GET ───────────────────────────────────────────────────────────────
$prazv      = null;
$qt_lob_tipas = null;
if (!$rezultatai && !$conn_klaida) {
    try {
        $qt_lob_tipas = qtLobTipas();
        [$perkelti, $praleista] = surinktDarbus();
        $nauji = array_filter($perkelti, fn($d) => !$d['jau_perkeltas']);
        $jau   = array_filter($perkelti, fn($d) => $d['jau_perkeltas']);
        $prazv = compact('perkelti', 'nauji', 'jau', 'praleista');
    } catch (Exception $e) {
        $conn_klaida = $e->getMessage();
    }
}

// ── HTML ───────────────────────────────────────────────────────────────────────
include __DIR__ . '/includes/header.php';

function fmt_b(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024, 0)    . ' KB';
    return $b . ' B';
}
?>

<div class="container" style="max-width:980px;margin:0 auto;padding:24px 16px;">

<nav aria-label="breadcrumb" style="margin-bottom:16px;">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/index.php">Pagrindinis</a></li>
        <li class="breadcrumb-item"><a href="/qt_import_admin.php">quality_tomas importas</a></li>
        <li class="breadcrumb-item active">PDF perkėlimas</li>
    </ol>
</nav>

<h2 style="margin-bottom:4px;">PDF perkėlimas: quality_tomas → Tomo_QMS</h2>
<p style="color:var(--text-secondary);margin-bottom:16px;">
    Perkeliami <strong>MT paso PDF</strong> ir <strong>Nustatymų protokolai</strong>
    iš <code>quality_tomas.gvx_dokumentai</code> į <code>tomas_qms.gvx_dokumentai</code>.
    Siejama pagal <strong>užsakymo numerį</strong>.
</p>

<?php if ($qt_lob_tipas !== null): ?>
<div style="margin-bottom:14px;padding:9px 14px;border-radius:6px;border:1px solid var(--border);background:var(--bg-secondary);font-size:13px;">
    🔍 <strong>quality_tomas turinys_lob tipas:</strong>
    <?php if ($qt_lob_tipas === 'oid'): ?>
        <code style="background:#fff3cd;padding:2px 5px;border-radius:3px;">oid</code> — lo_get() bus naudojamas ✅
    <?php elseif ($qt_lob_tipas === 'bytea'): ?>
        <code style="background:#d4edda;padding:2px 5px;border-radius:3px;">bytea</code> — tiesioginis skaitymas ✅
    <?php else: ?>
        <code><?= h((string)$qt_lob_tipas) ?></code>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($conn_klaida): ?>
<div class="alert alert-danger" role="alert">
    <strong>Prisijungimo klaida:</strong> <?= h($conn_klaida) ?>
</div>

<?php elseif ($rezultatai): ?>
<!-- ── Rezultatai ── -->
<div class="alert <?= empty($rezultatai['klaidos']) ? 'alert-success' : 'alert-warning' ?>" role="alert">
    <strong>Baigta!</strong><br>
    ✅ Perkelti: <strong><?= $rezultatai['ok'] ?></strong> dokumentų<br>
    ⏭️ Praleista (jau buvo arba klaida): <strong><?= $rezultatai['pral'] ?></strong>
    <?php if (!empty($rezultatai['klaidos'])): ?>
    <hr style="margin:10px 0;">
    <strong>Klaidos (<?= count($rezultatai['klaidos']) ?>):</strong><br>
    <?php foreach (array_slice($rezultatai['klaidos'], 0, 30) as $kl): ?>
        <code style="font-size:12px;display:block;margin-top:2px;"><?= h($kl) ?></code>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($rezultatai['praleista'])): ?>
    <hr style="margin:10px 0;">
    <strong>Nesusieti (<?= count($rezultatai['praleista']) ?>):</strong><br>
    <?php foreach (array_slice($rezultatai['praleista'], 0, 20) as $p): ?>
        <code style="font-size:12px;display:block;margin-top:2px;"><?= h($p['tipas']) ?> / <?= h($p['failas']) ?> — <?= h($p['priežastis']) ?></code>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<p><a href="/perkelti_pdf_is_qt.php" class="btn btn-secondary btn-sm">← Atnaujinti peržiūrą</a></p>

<?php elseif ($prazv !== null): ?>
<!-- ── Peržiūra ── -->
<?php
    $naujiC   = count($prazv['nauji']);
    $jauC     = count($prazv['jau']);
    $pral_c   = count($prazv['praleista']);
    $viso     = count($prazv['perkelti']);
?>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
    <div style="flex:1;min-width:140px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px 18px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:#16a34a;"><?= $naujiC ?></div>
        <div style="font-size:0.84rem;color:#166534;">Bus perkelti</div>
    </div>
    <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:14px 18px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:#64748b;"><?= $jauC ?></div>
        <div style="font-size:0.84rem;color:#64748b;">Jau perkelti</div>
    </div>
    <div style="flex:1;min-width:140px;background:<?= $pral_c ? '#fef9c3' : '#f8fafc' ?>;border:1px solid <?= $pral_c ? '#fde047' : 'var(--border)' ?>;border-radius:8px;padding:14px 18px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:700;color:<?= $pral_c ? '#a16207' : '#64748b' ?>;"><?= $pral_c ?></div>
        <div style="font-size:0.84rem;color:<?= $pral_c ? '#854d0e' : '#64748b' ?>;">Nesusieti</div>
    </div>
</div>

<?php if ($naujiC > 0): ?>
<form method="POST" onsubmit="return confirm('Perkelti <?= $naujiC ?> dokumentą(-ų) į tomas_qms.gvx_dokumentai?')">
    <input type="hidden" name="vykdyti" value="1">
    <button type="submit" class="btn btn-primary" style="margin-bottom:20px;" data-testid="button-perkelti">
        📤 Perkelti <?= $naujiC ?> dokumentą(-ų) →
    </button>
</form>
<?php else: ?>
<div class="alert alert-success" role="alert">✅ Visi rasti dokumentai jau perkelti į tomas_qms.</div>
<?php endif; ?>

<?php if (!empty($prazv['nauji'])): ?>
<details open style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;padding:10px 0;font-size:0.95rem;">
        📋 Bus perkelti (<?= $naujiC ?> vnt.)
    </summary>
    <div style="overflow-x:auto;margin-top:8px;">
    <table class="table table-sm" style="font-size:0.83rem;">
        <thead><tr>
            <th>Tipas</th>
            <th>Failo vardas</th>
            <th>Pavadinimas</th>
            <th>Užsakymas</th>
            <th style="text-align:right;">Dydis</th>
        </tr></thead>
        <tbody>
        <?php foreach ($prazv['nauji'] as $d): ?>
        <tr>
            <td><code style="font-size:11px;"><?= h($d['tipas']) ?></code></td>
            <td><?= h($d['failas']) ?></td>
            <td style="color:var(--text-secondary);max-width:240px;overflow:hidden;text-overflow:ellipsis;"><?= h($d['pavadinimas']) ?></td>
            <td><?= h($d['qt_uzs_nr']) ?></td>
            <td style="text-align:right;white-space:nowrap;"><?= fmt_b((int)$d['dydis']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</details>
<?php endif; ?>

<?php if (!empty($prazv['jau'])): ?>
<details style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;padding:10px 0;font-size:0.95rem;color:var(--text-secondary);">
        ✅ Jau perkelti (<?= $jauC ?> vnt.) — bus praleisti
    </summary>
    <div style="overflow-x:auto;margin-top:8px;">
    <table class="table table-sm" style="font-size:0.83rem;opacity:.75;">
        <thead><tr><th>Tipas</th><th>Failas</th><th>Užsakymas</th><th style="text-align:right;">Dydis</th></tr></thead>
        <tbody>
        <?php foreach ($prazv['jau'] as $d): ?>
        <tr>
            <td><code style="font-size:11px;"><?= h($d['tipas']) ?></code></td>
            <td><?= h($d['failas']) ?></td>
            <td><?= h($d['qt_uzs_nr']) ?></td>
            <td style="text-align:right;"><?= fmt_b((int)$d['dydis']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</details>
<?php endif; ?>

<?php if (!empty($prazv['praleista'])): ?>
<details style="margin-bottom:16px;">
    <summary style="cursor:pointer;font-weight:600;padding:10px 0;font-size:0.95rem;color:#a16207;">
        ⚠️ Nesusieti (<?= $pral_c ?> vnt.) — nebus perkelti
    </summary>
    <p style="font-size:0.84rem;color:var(--text-secondary);margin:8px 0;">
        Šie dokumentai nerasti jokio atitikimo tomas_qms užsakymuose.
    </p>
    <div style="overflow-x:auto;">
    <table class="table table-sm" style="font-size:0.83rem;">
        <thead><tr><th>Tipas</th><th>Failas</th><th>Priežastis</th></tr></thead>
        <tbody>
        <?php foreach ($prazv['praleista'] as $d): ?>
        <tr>
            <td><code style="font-size:11px;"><?= h($d['tipas']) ?></code></td>
            <td><?= h($d['failas']) ?></td>
            <td style="color:#92400e;"><?= h($d['priežastis']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</details>
<?php endif; ?>

<?php endif; ?>

<div style="margin-top:20px;">
    <a href="/qt_import_admin.php" class="btn btn-secondary btn-sm">← Grįžti į importo valdymą</a>
</div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
