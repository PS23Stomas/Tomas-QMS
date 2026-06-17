<?php
/**
 * Vienkartinis: kopijuoja quality_tomas.gvx_dokumentai → vietinė DB.
 * Skirta development aplinkai — sinchronizuoja paso ir nustatymų
 * protokolų PDF iš quality_tomas į vietinę gvx_dokumentai lentelę.
 * Gali būti paleista kelis kartus — dublikatus praleidžia.
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
if (!isAdmin()) { http_response_code(403); exit('Tik administratoriui'); }

set_time_limit(300);
ignore_user_abort(true);

// ── Jungtys ───────────────────────────────────────────────────────────────────

function qtConn2(): PDO {
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

$local = getDbConnection();
$qt    = qtConn2();

// ── Pagalba: BYTEA hex → raw binary ───────────────────────────────────────────

function rawBytes($v): string {
    if (is_resource($v)) $v = stream_get_contents($v);
    if (is_string($v) && str_starts_with($v, '\\x')) return hex2bin(substr($v, 2));
    return (string)$v;
}

// ── Žemėlapiai ────────────────────────────────────────────────────────────────

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

// jau esantys vietinėje DB (pagal uzsakymo_id + failas)
$jau   = [];
foreach ($local->query("SELECT uzsakymo_id, failas FROM gvx_dokumentai")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $jau[$r['uzsakymo_id'] . '|' . $r['failas']] = true;
}

// ── Nuskaityti iš quality_tomas ───────────────────────────────────────────────

$tipai = "'mt_deklaracija','mt_deklaracija_pdf','nustatymu_protokolas'";

// Detect schema
$has_uzs_id = false;
try { $qt->query("SELECT uzsakymo_id FROM gvx_dokumentai LIMIT 0"); $has_uzs_id = true; } catch (Exception $e) {}
$has_gam_id = false;
try { $qt->query("SELECT gaminys_id FROM gvx_dokumentai LIMIT 0"); $has_gam_id = true; } catch (Exception $e) {}

// Detect turinys column type (OID vs BYTEA)
$oid_type = false;
try {
    $r = $qt->query("SELECT data_type FROM information_schema.columns WHERE table_name='gvx_dokumentai' AND column_name='turinys_lob' AND table_schema='public'")->fetch();
    $oid_type = $r && strtolower($r['data_type']) === 'oid';
} catch (Exception $e) {}

if ($has_uzs_id) {
    $rows = $qt->query("SELECT id, uzsakymo_id, tipas, pavadinimas, failas, octet_length(turinys_lob) AS dydis FROM gvx_dokumentai WHERE tipas IN ({$tipai}) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($has_gam_id) {
    $rows = $qt->query("SELECT d.id, u.id AS uzsakymo_id, d.tipas, d.pavadinimas, d.failas, octet_length(d.turinys_lob) AS dydis FROM gvx_dokumentai d LEFT JOIN gaminiai g ON g.id = d.gaminys_id LEFT JOIN uzsakymai u ON u.id = g.uzsakymo_id WHERE d.tipas IN ({$tipai}) ORDER BY d.id")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rows = [];
}

// ── Vykdymas ──────────────────────────────────────────────────────────────────

$insert = $local->prepare("INSERT INTO gvx_dokumentai (uzsakymo_id, tipas, pavadinimas, failas, dydis_b, turinys_lob, sukurejas) VALUES (?,?,?,?,?,?,?)");

$perkelti = 0;
$praleista_dup = 0;
$praleista_nesusieta = 0;
$klaidos = [];

foreach ($rows as $r) {
    $qt_uzs_id = (int)($r['uzsakymo_id'] ?? 0);
    $uzs_nr    = $qt_uzs_id ? ($qt_id_to_nr[$qt_uzs_id] ?? null) : null;

    if (!$uzs_nr || !isset($local_nr_to_id[$uzs_nr])) {
        $praleista_nesusieta++;
        continue;
    }

    $local_uzs_id = $local_nr_to_id[$uzs_nr];
    $failas       = $r['failas'] ?? '';
    $key          = $local_uzs_id . '|' . $failas;

    if (isset($jau[$key])) {
        $praleista_dup++;
        continue;
    }

    // Nuskaityti turinį
    try {
        if ($oid_type) {
            $qt->beginTransaction();
            $blob = $qt->query("SELECT lo_get(" . (int)$qt->query("SELECT turinys_lob FROM gvx_dokumentai WHERE id = " . (int)$r['id'])->fetchColumn() . ")")->fetchColumn();
            $qt->commit();
            $content = rawBytes($blob);
        } else {
            $blob    = $qt->query("SELECT turinys_lob FROM gvx_dokumentai WHERE id = " . (int)$r['id'])->fetchColumn();
            $content = rawBytes($blob);
        }
    } catch (Exception $e) {
        $klaidos[] = h($failas) . ': ' . h($e->getMessage());
        continue;
    }

    if (empty($content)) {
        $klaidos[] = h($failas) . ': turinys tuščias';
        continue;
    }

    try {
        $insert->execute([
            $local_uzs_id,
            $r['tipas'],
            $r['pavadinimas'] ?? '',
            $failas,
            strlen($content),
            $content,
            'qt_import',
        ]);
        $jau[$key] = true;
        $perkelti++;
    } catch (Exception $e) {
        $klaidos[] = h($failas) . ': ' . h($e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:860px;margin:32px auto;">
    <h2>quality_tomas → vietinė DB: gvx_dokumentai importas</h2>
    <div style="background:var(--bg-secondary);border-radius:8px;padding:20px 28px;margin-top:20px;">
        <p><strong>✅ Perkelta:</strong> <?= $perkelti ?> dokumentų</p>
        <p><strong>⏭ Praleista (jau buvo):</strong> <?= $praleista_dup ?></p>
        <p><strong>⚠️ Nesusieta (nerastas užsakymas):</strong> <?= $praleista_nesusieta ?></p>
        <?php if ($klaidos): ?>
        <p><strong>❌ Klaidos (<?= count($klaidos) ?>):</strong></p>
        <ul><?php foreach ($klaidos as $k): ?><li><?= $k ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
    <p style="margin-top:16px;color:var(--text-secondary);font-size:13px;">
        Šį puslapį galima ištrinti po sėkmingo importo arba paleisti vėl — dublikatai bus praleisti.
    </p>
    <a href="/uzsakymai.php?grupe=MT" class="btn btn-primary" style="margin-top:8px;">← Grįžti į MT užsakymus</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
