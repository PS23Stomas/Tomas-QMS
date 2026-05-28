<?php
/**
 * ==========================================================================
 *  MIGRACIJA_ADMIN.PHP — Duomenų bazės migracijos valdymas (Web sąsaja)
 * ==========================================================================
 *
 *  Paskirtis: Suteikti administratoriui galimybę paleisti DB migracijas
 *             per naršyklę, be prieigos prie komandinės eilutės.
 *
 *  Prieiga:  Tik admin rolės vartotojai.
 *  URL:      /migracija_admin.php
 * ==========================================================================
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();

// Tik adminai gali naudotis šiuo puslapiu
if (currentUser()['role'] !== 'administratorius') {
    http_response_code(403);
    die('Prieiga uždrausta. Šis puslapis skirtas tik administratoriams.');
}

$rezultatas  = null;
$klaida      = null;
$ar_vykdyta  = false;

// Apdorojame formos pateikimą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vykdyti_migracija'])) {
    csrfVerify();
    $ar_vykdyta = true;
    $pradzia    = microtime(true);

    try {
        $migracija = new DBMigracija($pdo);
        $migracija->paleisti();
        $trukme    = round(microtime(true) - $pradzia, 3);
        $rezultatas = "Migracijos įvykdytos sėkmingai. Trukmė: {$trukme} sek.";
    } catch (Exception $e) {
        $klaida = 'Klaida vykdant migracijas: ' . $e->getMessage();
    }
}

// Migracijų failo informacija
$migr_failas    = __DIR__ . '/klases/DBMigracija.php';
$migr_hash      = md5_file($migr_failas);
$migr_data      = date('Y-m-d H:i:s', filemtime($migr_failas));

$page_title      = 'DB Migracija';
$aktyvus_modulis = false;
require_once __DIR__ . '/includes/header.php';
?>

<style>
.migracija-box {
    max-width: 680px;
    margin: 0 auto;
}
.info-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 8px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}
.info-label {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}
.info-value {
    font-size: 13px;
    font-family: monospace;
    color: var(--text-primary);
    word-break: break-all;
}
.pastaba-blokas {
    background: #fffbeb;
    border: 1px solid #f59e0b;
    border-left: 4px solid #f59e0b;
    border-radius: 6px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.pastaba-blokas h4 {
    margin: 0 0 8px;
    color: #92400e;
    font-size: 14px;
}
.pastaba-blokas ul {
    margin: 0;
    padding-left: 20px;
    color: #78350f;
    font-size: 13px;
    line-height: 1.7;
}
.rezultatas-ok {
    background: #f0fdf4;
    border: 1px solid #86efac;
    border-left: 4px solid #22c55e;
    border-radius: 6px;
    padding: 16px 20px;
    margin-bottom: 24px;
    color: #166534;
    font-size: 14px;
}
.rezultatas-klaida {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-left: 4px solid #ef4444;
    border-radius: 6px;
    padding: 16px 20px;
    margin-bottom: 24px;
    color: #991b1b;
    font-size: 14px;
}
.vykdyti-btn {
    background: var(--accent-color);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}
.vykdyti-btn:hover { opacity: 0.85; }
.cli-blokas {
    background: #1e1e2e;
    color: #cdd6f4;
    border-radius: 8px;
    padding: 16px 20px;
    font-family: monospace;
    font-size: 13px;
    margin-top: 24px;
}
.cli-blokas .komentaras { color: #6c7086; }
.cli-blokas .komanda    { color: #a6e3a1; }
</style>

<div class="migracija-box">

    <div class="pastaba-blokas">
        <h4>Kada vykdyti migraciją?</h4>
        <ul>
            <li>Po kiekvieno sistemos atnaujinimo</li>
            <li>Diegiant sistemą pirmą kartą naujame serveryje</li>
            <li>Kai sistema praneša apie trūkstamus stulpelius ar lenteles</li>
        </ul>
    </div>

    <?php if ($rezultatas): ?>
    <div class="rezultatas-ok">
        ✓ <?= h($rezultatas) ?>
    </div>
    <?php endif; ?>

    <?php if ($klaida): ?>
    <div class="rezultatas-klaida">
        ✗ <?= h($klaida) ?>
    </div>
    <?php endif; ?>

    <div class="info-grid">
        <span class="info-label">Migracijos failas:</span>
        <span class="info-value">klases/DBMigracija.php</span>

        <span class="info-label">Paskutinis keitimas:</span>
        <span class="info-value"><?= h($migr_data) ?></span>

        <span class="info-label">Failo kontrolinė suma:</span>
        <span class="info-value"><?= h($migr_hash) ?></span>
    </div>

    <form method="POST" onsubmit="return confirm('Vykdyti DB migracijas? Tai atnaujins duomenų bazės schemą.')">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <button type="submit" name="vykdyti_migracija" class="btn btn-primary" style="padding:12px 28px; font-size:15px; font-weight:600;">
            Vykdyti migracijas
        </button>
    </form>

    <div class="cli-blokas">
        <div class="komentaras"># Alternatyvus būdas — komandinė eilutė:</div>
        <div class="komanda">php migracija.php</div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
