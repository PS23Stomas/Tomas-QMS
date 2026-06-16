<?php
/**
 * Perkelti PDF iš quality_tomas į Tomo_QMS
 * - MT paso PDF (mt_deklaracija + mt_deklaracija_pdf) → Tomo_QMS.gaminiai.mt_paso_pdf
 * - Nustatymų protokolai (nustatymu_protokolas)      → Tomo_QMS.gaminiu_pdf_failai
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
 * BYTEA iš PostgreSQL PDO grąžinamas kaip resource (stream).
 * Konvertuojame į PostgreSQL hex-escaped formatą (\xAABB...) kurį PDO::PARAM_STR priima.
 */
function byteaHex(mixed $val): ?string {
    if ($val === null) return null;
    $bin = is_resource($val) ? stream_get_contents($val) : (string)$val;
    if ($bin === '' || $bin === false) return null;
    return '\\x' . bin2hex($bin);
}

/** Ištraukia pirmą skaičių iš failo vardo */
function nrIsFailo(string $failas): string {
    preg_match('/^(\d+)/', $failas, $m);
    return $m[1] ?? '';
}

/** Sukuria gaminiu_pdf_failai Tomo_QMS jei neegzistuoja */
function uztikriniGaminPdfFailai(PDO $tq): void {
    $tq->exec("
        CREATE TABLE IF NOT EXISTS gaminiu_pdf_failai (
            id           SERIAL PRIMARY KEY,
            gaminio_id   INTEGER NOT NULL,
            pdf_tipas    VARCHAR(50) NOT NULL,
            failas_vardas VARCHAR(255),
            turinys      BYTEA,
            ikelta       TIMESTAMP DEFAULT NOW(),
            vartotojas_id INTEGER
        )
    ");
}

/** Surinkia Tomo_QMS gaminiai: ID → [id, gaminio_numeris, turi_paso] */
function tqGaminiai(): array {
    $tq = tqConn();
    $rows = $tq->query(
        "SELECT id, gaminio_numeris, mt_paso_pdf IS NOT NULL AS turi_paso FROM gaminiai"
    )->fetchAll(PDO::FETCH_ASSOC);
    $byId = $byNr = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
        $byNr[$r['gaminio_numeris']] = $r;
    }

    // Patikrinti ar egzistuoja lentelė ir jau perkelta nustatymu
    $exists = $tq->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name='gaminiu_pdf_failai' AND table_schema='public'")->fetchColumn();
    $jau_nust = [];
    if ($exists) {
        $rows2 = $tq->query("SELECT gaminio_id, failas_vardas FROM gaminiu_pdf_failai WHERE pdf_tipas='nustatymu'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows2 as $r) $jau_nust[$r['gaminio_id'] . '|' . $r['failas_vardas']] = true;
    }

    return [$byId, $byNr, $jau_nust];
}

/**
 * Papildomas sutapimo būdas kai paprastas nrIsFailo() nerado.
 * Bando: B1) skaičius-su-brūkšniu modelis (pvz. "23001-1"), B2) QT gaminio_numeris
 * kaip failo vardo prefiksas, B3) per QT uzsakymo_numeris → Tomo_QMS gaminiai.
 * Grąžina ['row' => [...], 'saltinis' => '...'] arba null jei neranda.
 */
function suraskPagalFaila(
    string $failas,
    array $tqByNr,
    array $qtByGamNr,
    array $qtUzsNrByGamId,
    array $tqByUzsNr
): ?array {
    // B1: skaičius su brūkšniais iš failo vardo (pvz. "23001-1 pasas.pdf" → "23001-1")
    preg_match('/^(\d[\d\-]+\d)(?=[^\d]|$)/', $failas, $m);
    $nr2 = $m[1] ?? '';
    if ($nr2 && isset($tqByNr[$nr2])) {
        return ['row' => $tqByNr[$nr2], 'saltinis' => 'failas nr. (su brūkšniu "' . $nr2 . '")'];
    }

    // B2 + B3: ieškome QT gaminio, kurio gaminio_numeris yra failo vardo prefiksas
    foreach ($qtByGamNr as $qtGamNr => $qtGamId) {
        if (!str_starts_with($failas, (string)$qtGamNr)) continue;

        // B2: tas pats gaminio_numeris Tomo_QMS
        if (isset($tqByNr[$qtGamNr])) {
            return ['row' => $tqByNr[$qtGamNr], 'saltinis' => 'QT gam. nr. "' . $qtGamNr . '"'];
        }

        // B3: per QT uzsakymo_numeris → Tomo_QMS gaminiai
        if (!empty($qtUzsNrByGamId[$qtGamId])) {
            $uzsNr = $qtUzsNrByGamId[$qtGamId];
            if (!empty($tqByUzsNr[$uzsNr])) {
                $candidates = $tqByUzsNr[$uzsNr];
                // Bandyti suderinti pagal gaminio_numeris failo varde
                foreach ($candidates as $c) {
                    if (str_contains($failas, (string)$c['gaminio_numeris'])) {
                        return ['row' => $c, 'saltinis' => 'QT uzs. nr. "' . $uzsNr . '" → gam. "' . $c['gaminio_numeris'] . '"'];
                    }
                }
                // Jei tik vienas gaminys tame užsakyme
                if (count($candidates) === 1) {
                    return ['row' => $candidates[0], 'saltinis' => 'QT uzs. nr. "' . $uzsNr . '" (1 gaminys)'];
                }
            }
        }
    }

    return null;
}

// ── Duomenų rinkinys peržiūrai / vykdymui ─────────────────────────────────────
function surinktDarbus(): array {
    $qt = qtConn();
    [$tqById, $tqByNr, $jau_nust] = tqGaminiai();

    // ─ QT žemėlapiai papildomam sutapimui ─────────────────────────────────────
    $qtGamNrByGamId = [];  // QT gaminys_id → gaminio_numeris
    $qtByGamNr      = [];  // QT gaminio_numeris → gaminys_id
    $qtUzsNrByGamId = [];  // QT gaminys_id → uzsakymo_numeris

    try {
        $qt_gam_rows = $qt->query("SELECT id, gaminio_numeris FROM gaminiai WHERE gaminio_numeris IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($qt_gam_rows as $qg) {
            $qtGamNrByGamId[(int)$qg['id']] = $qg['gaminio_numeris'];
            $qtByGamNr[$qg['gaminio_numeris']] = (int)$qg['id'];
        }
        // Pabandyti prisijungti su uzsakymai
        $qt_uzs_rows = $qt->query("
            SELECT g.id AS gaminys_id, u.uzsakymo_numeris
            FROM gaminiai g
            JOIN uzsakymai u ON u.id = g.uzsakymo_id
            WHERE u.uzsakymo_numeris IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($qt_uzs_rows as $qur) {
            $qtUzsNrByGamId[(int)$qur['gaminys_id']] = $qur['uzsakymo_numeris'];
        }
    } catch (Exception $e) { /* lentelė gali neegzistuoti — praleisti */ }

    // Tomo_QMS: uzsakymo_numeris → gaminiai masyvai (B3 sutapimui)
    $tqByUzsNr = [];
    try {
        $tq_uzs_rows = tqConn()->query("
            SELECT g.id, g.gaminio_numeris, u.uzsakymo_numeris,
                   (g.mt_paso_pdf IS NOT NULL) AS turi_paso
            FROM gaminiai g
            JOIN uzsakymai u ON u.id = g.uzsakymo_id
            WHERE u.uzsakymo_numeris IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tq_uzs_rows as $tur) {
            $tqByUzsNr[$tur['uzsakymo_numeris']][] = $tur;
        }
    } catch (Exception $e) { /* Tomo_QMS gali neturėti uzsakymai lentelės — praleisti */ }

    $paso  = [];  // bus rašoma į Tomo_QMS.gaminiai.mt_paso_pdf
    $nust  = [];  // bus rašoma į Tomo_QMS.gaminiu_pdf_failai (tipas=nustatymu)
    $praleista = [];

    // 1. mt_deklaracija — sutapimas pagal gaminys_id
    $rows = $qt->query(
        "SELECT id, gaminys_id, failas, length(turinys_lob) AS dydis
         FROM gvx_dokumentai WHERE tipas = 'mt_deklaracija' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $gid = (int)$r['gaminys_id'];
        if ($gid && isset($tqById[$gid])) {
            // 1a. Tiesioginis sutapimas pagal ID
            $tqRow = $tqById[$gid];
            $paso[] = [
                'qt_dok_id' => $r['id'],
                'tq_gam_id' => $tqRow['id'],
                'failas'    => $r['failas'],
                'dydis'     => $r['dydis'],
                'jau_turi'  => (bool)$tqRow['turi_paso'],
                'šaltinis'  => 'mt_deklaracija (ID)',
            ];
        } elseif ($gid && isset($qtGamNrByGamId[$gid]) && isset($tqByNr[$qtGamNrByGamId[$gid]])) {
            // 1b. ID žinomas, bet Tomo_QMS neturi — bandyti per QT gaminio_numeris
            $qtNr  = $qtGamNrByGamId[$gid];
            $tqRow = $tqByNr[$qtNr];
            $paso[] = [
                'qt_dok_id' => $r['id'],
                'tq_gam_id' => $tqRow['id'],
                'failas'    => $r['failas'],
                'dydis'     => $r['dydis'],
                'jau_turi'  => (bool)$tqRow['turi_paso'],
                'šaltinis'  => 'mt_deklaracija (QT gam. nr. "' . h($qtNr) . '")',
            ];
        } else {
            // 1c. Bandyti pagal numerį iš failo vardo
            $nr = nrIsFailo($r['failas']);
            if ($nr && isset($tqByNr[$nr])) {
                $paso[] = [
                    'qt_dok_id' => $r['id'],
                    'tq_gam_id' => $tqByNr[$nr]['id'],
                    'failas'    => $r['failas'],
                    'dydis'     => $r['dydis'],
                    'jau_turi'  => (bool)$tqByNr[$nr]['turi_paso'],
                    'šaltinis'  => 'mt_deklaracija (failas nr.)',
                ];
            } else {
                // 1d. Papildomi atsarginiai metodai (brūkšninis nr., QT gaminio_numeris, uzsakymo_numeris)
                $ext = suraskPagalFaila($r['failas'], $tqByNr, $qtByGamNr, $qtUzsNrByGamId, $tqByUzsNr);
                if ($ext) {
                    $paso[] = [
                        'qt_dok_id' => $r['id'],
                        'tq_gam_id' => (int)$ext['row']['id'],
                        'failas'    => $r['failas'],
                        'dydis'     => $r['dydis'],
                        'jau_turi'  => (bool)$ext['row']['turi_paso'],
                        'šaltinis'  => 'mt_deklaracija (' . $ext['saltinis'] . ')',
                    ];
                } else {
                    $praleista[] = ['tipas' => 'mt_deklaracija', 'failas' => $r['failas'], 'priežastis' => 'Gaminys nerastas Tomo_QMS'];
                }
            }
        }
    }

    // 2. mt_deklaracija_pdf — gaminys_id yra NULL, sutapimas pagal failo vardą
    $rows2 = $qt->query(
        "SELECT id, failas, length(turinys_lob) AS dydis
         FROM gvx_dokumentai WHERE tipas = 'mt_deklaracija_pdf' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows2 as $r) {
        $nr = nrIsFailo($r['failas']);
        if ($nr && isset($tqByNr[$nr])) {
            $tqRow = $tqByNr[$nr];
            $paso[] = [
                'qt_dok_id' => $r['id'],
                'tq_gam_id' => $tqRow['id'],
                'failas'    => $r['failas'],
                'dydis'     => $r['dydis'],
                'jau_turi'  => (bool)$tqRow['turi_paso'],
                'šaltinis'  => 'mt_deklaracija_pdf (failas nr.)',
            ];
        } else {
            // Papildomi atsarginiai metodai
            $ext = suraskPagalFaila($r['failas'], $tqByNr, $qtByGamNr, $qtUzsNrByGamId, $tqByUzsNr);
            if ($ext) {
                $paso[] = [
                    'qt_dok_id' => $r['id'],
                    'tq_gam_id' => (int)$ext['row']['id'],
                    'failas'    => $r['failas'],
                    'dydis'     => $r['dydis'],
                    'jau_turi'  => (bool)$ext['row']['turi_paso'],
                    'šaltinis'  => 'mt_deklaracija_pdf (' . $ext['saltinis'] . ')',
                ];
            } else {
                $praleista[] = ['tipas' => 'mt_deklaracija_pdf', 'failas' => $r['failas'], 'priežastis' => 'Gaminys nerastas Tomo_QMS'];
            }
        }
    }

    // 3. nustatymu_protokolas — gaminys_id yra NULL, sutapimas pagal failo vardą
    $rows3 = $qt->query(
        "SELECT id, failas, length(turinys_lob) AS dydis
         FROM gvx_dokumentai WHERE tipas = 'nustatymu_protokolas' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows3 as $r) {
        $nr = nrIsFailo($r['failas']);
        if ($nr && isset($tqByNr[$nr])) {
            $tq_gam_id = $tqByNr[$nr]['id'];
            $key = $tq_gam_id . '|' . $r['failas'];
            $nust[] = [
                'qt_dok_id'     => $r['id'],
                'tq_gam_id'     => $tq_gam_id,
                'failas'        => $r['failas'],
                'dydis'         => $r['dydis'],
                'jau_perkeltas' => isset($jau_nust[$key]),
            ];
        } else {
            $praleista[] = ['tipas' => 'nustatymu_protokolas', 'failas' => $r['failas'], 'priežastis' => 'Gaminys nerastas Tomo_QMS'];
        }
    }

    return [$paso, $nust, $praleista];
}

// ── Vykdymas POST ──────────────────────────────────────────────────────────────
$rezultatai = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['vykdyti'] ?? '') === '1') {
    ignore_user_abort(true);
    set_time_limit(300);

    [$paso, $nust, $praleista] = surinktDarbus();
    $tq = tqConn();
    $qt = qtConn();

    $paso_ok = $paso_pral = $nust_ok = $nust_pral = 0;
    $klaidos = [];

    // ─ Paso PDFs ─
    $upd_paso = $tq->prepare(
        "UPDATE gaminiai SET mt_paso_pdf = :pdf, mt_paso_failas = :failas WHERE id = :id AND mt_paso_pdf IS NULL"
    );
    $sel_turinys = $qt->prepare("SELECT turinys_lob, failas FROM gvx_dokumentai WHERE id = ?");

    foreach ($paso as $d) {
        if ($d['jau_turi']) { $paso_pral++; continue; }
        try {
            $sel_turinys->execute([$d['qt_dok_id']]);
            $row = $sel_turinys->fetch(PDO::FETCH_ASSOC);
            $turinys = byteaHex($row['turinys_lob'] ?? null);
            if (!$row || !$turinys) { $paso_pral++; continue; }

            $upd_paso->bindValue(':pdf', $turinys, PDO::PARAM_STR);
            $upd_paso->bindValue(':failas', $row['failas']);
            $upd_paso->bindValue(':id', $d['tq_gam_id']);
            $upd_paso->execute();
            $paso_ok++;
        } catch (Exception $e) {
            $klaidos[] = 'Paso [' . $d['failas'] . ']: ' . $e->getMessage();
            $paso_pral++;
        }
    }

    // ─ Nustatymų protokolai ─
    uztikriniGaminPdfFailai($tq);

    // Patikrinti kas jau įkelta
    $jau_nust = $tq->query(
        "SELECT gaminio_id, failas_vardas FROM gaminiu_pdf_failai WHERE pdf_tipas = 'nustatymu'"
    )->fetchAll(PDO::FETCH_ASSOC);
    $jau_set = [];
    foreach ($jau_nust as $j) $jau_set[$j['gaminio_id'] . '|' . $j['failas_vardas']] = true;

    $ins_nust = $tq->prepare(
        "INSERT INTO gaminiu_pdf_failai (gaminio_id, pdf_tipas, failas_vardas, turinys, ikelta)
         VALUES (:gam_id, 'nustatymu', :failas, :turinys, NOW())"
    );

    foreach ($nust as $d) {
        if ($d['jau_perkeltas']) { $nust_pral++; continue; }
        $key = $d['tq_gam_id'] . '|' . $d['failas'];
        if (isset($jau_set[$key])) { $nust_pral++; continue; }
        try {
            $sel_turinys->execute([$d['qt_dok_id']]);
            $row = $sel_turinys->fetch(PDO::FETCH_ASSOC);
            $turinys = byteaHex($row['turinys_lob'] ?? null);
            if (!$row || !$turinys) { $nust_pral++; continue; }

            $ins_nust->bindValue(':gam_id', $d['tq_gam_id']);
            $ins_nust->bindValue(':failas', $row['failas']);
            $ins_nust->bindValue(':turinys', $turinys, PDO::PARAM_STR);
            $ins_nust->execute();
            $nust_ok++;
        } catch (Exception $e) {
            $klaidos[] = 'Nust. [' . $d['failas'] . ']: ' . $e->getMessage();
            $nust_pral++;
        }
    }

    $rezultatai = compact('paso_ok', 'paso_pral', 'nust_ok', 'nust_pral', 'klaidos', 'praleista');
}

// ── Peržiūra GET ───────────────────────────────────────────────────────────────
$prazv_duomenys = null;
$conn_klaida    = null;
try {
    [$paso, $nust, $praleista] = surinktDarbus();
    $paso_nauji   = array_filter($paso, fn($d) => !$d['jau_turi']);
    $paso_jau     = array_filter($paso, fn($d) => $d['jau_turi']);
    $nust_nauji   = array_filter($nust, fn($d) => !$d['jau_perkeltas']);
    $nust_jau     = array_filter($nust, fn($d) => $d['jau_perkeltas']);
    $prazv_duomenys = compact('paso', 'paso_nauji', 'paso_jau', 'nust', 'nust_nauji', 'nust_jau', 'praleista');
} catch (Exception $e) {
    $conn_klaida = $e->getMessage();
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:980px;margin:0 auto;padding:24px 16px;">

<nav aria-label="breadcrumb" style="margin-bottom:16px;">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/index.php">Pagrindinis</a></li>
        <li class="breadcrumb-item active">PDF perkėlimas iš quality_tomas</li>
    </ol>
</nav>

<h2 style="margin-bottom:4px;">PDF perkėlimas: quality_tomas → Tomo_QMS</h2>
<p style="color:var(--text-secondary);margin-bottom:24px;">
    Perkeliami <strong>MT paso PDF</strong> ir <strong>Nustatymų protokolai</strong> iš quality_tomas į Tomo_QMS duomenų bazę.
</p>

<?php if ($conn_klaida): ?>
    <div class="alert alert-danger">Prisijungimo klaida: <?= h($conn_klaida) ?></div>
<?php elseif ($rezultatai): ?>
    <!-- ── Rezultatai ── -->
    <div class="alert <?= empty($rezultatai['klaidos']) ? 'alert-success' : 'alert-warning' ?>">
        <strong>Baigta!</strong><br>
        ✅ MT paso PDF perkelti: <strong><?= $rezultatai['paso_ok'] ?></strong> vnt.<br>
        ✅ Nustatymų protokolai perkelti: <strong><?= $rezultatai['nust_ok'] ?></strong> vnt.<br>
        ⏭️ Praleista (jau turėjo arba nerasta): <?= $rezultatai['paso_pral'] + $rezultatai['nust_pral'] ?> vnt.
        <?php if (!empty($rezultatai['klaidos'])): ?>
            <hr>
            <strong>Klaidos (<?= count($rezultatai['klaidos']) ?>):</strong><br>
            <?php foreach ($rezultatai['klaidos'] as $kl): ?>
                <code style="font-size:12px;display:block;"><?= h($kl) ?></code>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <a href="/perkelti_pdf_is_qt.php" class="btn btn-secondary">← Grįžti į peržiūrą</a>

<?php else: ?>
    <!-- ── Peržiūra ── -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;">
        <div class="card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= count($prazv_duomenys['paso_nauji']) ?></div>
            <div style="font-size:13px;color:var(--text-secondary);">MT paso PDF bus perkelti</div>
        </div>
        <div class="card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#2e7d32;"><?= count($prazv_duomenys['nust_nauji']) ?></div>
            <div style="font-size:13px;color:var(--text-secondary);">Nustatymų protokolai bus perkelti</div>
        </div>
        <div class="card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#1565c0;"><?= count($prazv_duomenys['paso_jau']) + count($prazv_duomenys['nust_jau']) ?></div>
            <div style="font-size:13px;color:var(--text-secondary);">Jau perkelti anksčiau</div>
        </div>
        <div class="card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:var(--text-secondary);"><?= count($prazv_duomenys['praleista']) ?></div>
            <div style="font-size:13px;color:var(--text-secondary);">Nerasta Tomo_QMS</div>
        </div>
    </div>

    <!-- Paso PDF -->
    <div class="card" style="margin-bottom:20px;">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;">
            📄 MT paso PDF — bus perkelti (<?= count($prazv_duomenys['paso_nauji']) ?> vnt.)
        </div>
        <?php if (empty($prazv_duomenys['paso_nauji'])): ?>
            <div style="padding:14px 16px;color:var(--text-secondary);">Visi paso PDF jau perkelti arba nerasta sutampančių gaminių.</div>
        <?php else: ?>
        <table class="table" style="margin:0;">
            <thead><tr><th>Failo vardas</th><th>Dydis</th><th>Šaltinis</th><th>Tomo_QMS ID</th></tr></thead>
            <tbody>
            <?php foreach ($prazv_duomenys['paso_nauji'] as $d): ?>
                <tr>
                    <td style="font-size:13px;"><?= h($d['failas']) ?></td>
                    <td style="font-size:12px;white-space:nowrap;"><?= round($d['dydis']/1024) ?> KB</td>
                    <td style="font-size:11px;color:var(--text-secondary);"><?= h($d['šaltinis']) ?></td>
                    <td style="font-size:12px;"><?= $d['tq_gam_id'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php if (!empty($prazv_duomenys['paso_jau'])): ?>
            <div style="padding:8px 16px;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);">
                ⏭️ Jau turi paso PDF Tomo_QMS: <?= count($prazv_duomenys['paso_jau']) ?> vnt. — bus praleisti.
            </div>
        <?php endif; ?>
    </div>

    <!-- Nustatymų protokolai -->
    <div class="card" style="margin-bottom:20px;">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;">
            📋 Nustatymų protokolai — bus perkelti (<?= count($prazv_duomenys['nust_nauji']) ?> vnt.)
        </div>
        <?php if (empty($prazv_duomenys['nust_nauji'])): ?>
            <div style="padding:14px 16px;color:var(--text-secondary);">Nerasta naujų nustatymų protokolų — visi jau perkelti arba nėra sutampančių gaminių.</div>
        <?php else: ?>
        <table class="table" style="margin:0;">
            <thead><tr><th>Failo vardas</th><th>Dydis</th><th>Tomo_QMS gaminio ID</th></tr></thead>
            <tbody>
            <?php foreach ($prazv_duomenys['nust_nauji'] as $d): ?>
                <tr>
                    <td style="font-size:13px;"><?= h($d['failas']) ?></td>
                    <td style="font-size:12px;white-space:nowrap;"><?= round($d['dydis']/1024) ?> KB</td>
                    <td style="font-size:12px;"><?= $d['tq_gam_id'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php if (!empty($prazv_duomenys['nust_jau'])): ?>
            <div style="padding:8px 16px;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);">
                ✅ Jau perkelti Tomo_QMS: <?= count($prazv_duomenys['nust_jau']) ?> vnt.
            </div>
        <?php endif; ?>
    </div>

    <!-- Praleista -->
    <?php if (!empty($prazv_duomenys['praleista'])): ?>
    <div class="card" style="margin-bottom:20px;">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--text-secondary);">
            ⏭️ Bus praleista — nerasta Tomo_QMS (<?= count($prazv_duomenys['praleista']) ?> vnt.)
        </div>
        <table class="table" style="margin:0;">
            <thead><tr><th>Tipas</th><th>Failo vardas</th><th>Priežastis</th></tr></thead>
            <tbody>
            <?php foreach ($prazv_duomenys['praleista'] as $d): ?>
                <tr style="opacity:0.6;">
                    <td style="font-size:12px;"><?= h($d['tipas']) ?></td>
                    <td style="font-size:13px;"><?= h($d['failas']) ?></td>
                    <td style="font-size:12px;"><?= h($d['priežastis']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Vykdymo mygtukas -->
    <?php if (count($prazv_duomenys['paso_nauji']) > 0 || count($prazv_duomenys['nust_nauji']) > 0): ?>
    <div class="card" style="padding:20px;background:var(--bg-secondary);">
        <p style="margin:0 0 12px;">
            <strong>Pasiruošta perkelti:</strong>
            <?= count($prazv_duomenys['paso_nauji']) ?> paso PDF ir
            <?= count($prazv_duomenys['nust_nauji']) ?> nustatymų protokolų
            į Tomo_QMS duomenų bazę.
            Jau turintys paso PDF gaminiai <strong>nebus perrašyti</strong>.
        </p>
        <form method="post">
            <input type="hidden" name="vykdyti" value="1">
            <button type="submit" class="btn btn-primary" data-testid="button-vykdyti-perkela"
                onclick="return confirm('Pradėti perkėlimą? Gali užtrukti kelias minutes.')">
                ▶ Pradėti perkėlimą
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        Visi PDF failai jau perkelti arba nerasta sutampančių gaminių — nieko daryti nereikia.
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
