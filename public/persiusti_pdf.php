<?php
/**
 * Greitas PDF persiuntimas į Tomo_QMS
 *
 * Persiunta tik PDF failus (paso, dielektrinių, funkcinių) iš vietinės DB
 * į Tomo_QMS duomenų bazę. Praleidžia gaminius, kurie Tomo_QMS jau turi PDF.
 * Greičiau nei pilnas sinchronizavimas — nesisinchronizuoja kitų duomenų.
 *
 * Prieiga: tik administratorius
 * URL: /persiusti_pdf.php
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

if (currentUser()['role'] !== 'administratorius') {
    http_response_code(403);
    die('Prieiga uždrausta.');
}

$page_title = 'PDF persiuntimas į Tomo_QMS';

$tomo_url_set = (bool)getenv('TOMO_QMS_DATABASE_URL');
$tomo_conn_ok = $tomo_url_set && TomoQMS::getConnection() !== null;

$rezultatas = null;
$klaida     = null;
$skirtumas  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['vykdyti'] ?? '') === '1') {
    csrfVerify();
    ignore_user_abort(true);
    set_time_limit(300);

    $rez = TomoQMS::sinchVisusPDF($pdo);
    if (isset($rez['klaida'])) {
        $klaida = $rez['klaida'];
    } else {
        $rezultatas = $rez;
    }
} else {
    if ($tomo_conn_ok) {
        $sk = TomoQMS::gautiPDFSkirtuma($pdo);
        if (!isset($sk['klaida'])) {
            $skirtumas = $sk;
        } else {
            $klaida = $sk['klaida'];
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="page-header">
        <h1 data-testid="text-page-title">PDF persiuntimas į Tomo_QMS</h1>
    </div>

    <div style="max-width: 760px;">

        <!-- Aplinkos kintamieji -->
        <div class="card" style="margin-bottom: 20px; padding: 16px;">
            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 4px;">TOMO_QMS_DATABASE_URL</div>
                    <div style="font-weight: 600; color: <?= $tomo_conn_ok ? '#16a34a' : '#dc2626' ?>;">
                        <?= $tomo_conn_ok ? '✓ Prisijungta' : ($tomo_url_set ? '✗ Klaida jungiantis' : '✗ Nenustatytas') ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 4px;">Vietinė DB</div>
                    <div style="font-weight: 600; color: #16a34a;">✓ Prisijungta</div>
                </div>
            </div>
        </div>

        <?php if (!$tomo_conn_ok): ?>
        <div class="alert alert-danger" data-testid="alert-no-connection">
            Nepavyko prisijungti prie Tomo_QMS duomenų bazės. Patikrinkite aplinkos kintamąjį <code>TOMO_QMS_DATABASE_URL</code>.
        </div>
        <?php endif; ?>

        <?php if ($klaida): ?>
        <div class="alert alert-danger" data-testid="alert-error"><?= h($klaida) ?></div>
        <?php endif; ?>

        <?php if ($rezultatas !== null): ?>
        <!-- Rezultatai po vykdymo -->
        <div class="card" style="margin-bottom: 20px; padding: 20px; border-left: 4px solid <?= empty($rezultatas['klaidos']) ? '#16a34a' : '#f59e0b' ?>;">
            <h2 style="margin: 0 0 16px; font-size: 1.1rem;">
                <?= empty($rezultatas['klaidos']) ? '✅ Perkėlimas baigtas sėkmingai' : '⚠️ Perkėlimas baigtas su klaidomis' ?>
            </h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #16a34a;" data-testid="stat-perkelti"><?= $rezultatas['perkelti'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">PDF perkelta</div>
                </div>
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--text-secondary);" data-testid="stat-praleisti"><?= $rezultatas['praleisti'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">Praleista (jau turėjo)</div>
                </div>
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--text-secondary);" data-testid="stat-trukme"><?= $rezultatas['trukme'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">sekundžių</div>
                </div>
            </div>
            <?php if (!empty($rezultatas['klaidos'])): ?>
            <details style="margin-top: 8px;">
                <summary style="cursor: pointer; color: #dc2626; font-size: 0.88rem; font-weight: 600;"
                         data-testid="details-errors">
                    <?= count($rezultatas['klaidos']) ?> klaida(-os)
                </summary>
                <ul style="margin: 8px 0 0; padding-left: 20px;">
                    <?php foreach ($rezultatas['klaidos'] as $kl): ?>
                    <li style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 4px;"><?= h($kl) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
        <a href="/persiusti_pdf.php" class="btn btn-secondary" data-testid="link-back">← Grįžti į peržiūrą</a>

        <?php elseif ($tomo_conn_ok && $skirtumas !== null): ?>
        <!-- Peržiūra prieš vykdymą (tikslus skirtumas: vietinė DB vs Tomo_QMS) -->
        <div class="card" style="margin-bottom: 20px; padding: 16px 20px;">
            <h2 style="margin: 0 0 8px; font-size: 1rem; font-weight: 600; color: var(--text-secondary);">
                PDF, kurių dar nėra Tomo_QMS
            </h2>
            <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0 0 16px;">
                Tiksliai palyginta su Tomo_QMS — rodomi tik tie, kurie bus realiai persiųsti.
                Jau esantys Tomo_QMS bus praleisti.
            </p>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 8px;">
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary);" data-testid="stat-paso"><?= $skirtumas['paso'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">📄 Paso PDF laukia</div>
                </div>
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary);" data-testid="stat-dielektriniu"><?= $skirtumas['dielektriniu'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">⚡ Dielektrinių PDF laukia</div>
                </div>
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary);" data-testid="stat-funkciniu"><?= $skirtumas['funkciniu'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">🔧 Funkcinių PDF laukia</div>
                </div>
                <div style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #64748b;" data-testid="stat-jau-turi"><?= $skirtumas['viso_jau_turi'] ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-secondary);">✅ Jau Tomo_QMS — bus praleisti</div>
                </div>
            </div>
        </div>

        <?php if ($skirtumas['viso_laukia'] > 0): ?>
        <div class="card" style="padding: 20px; background: var(--bg-secondary); margin-bottom: 20px;">
            <p style="margin: 0 0 8px; font-size: 0.9rem;">
                Bus persiųsti <strong><?= $skirtumas['viso_laukia'] ?> PDF</strong> į Tomo_QMS duomenų bazę.
                Perkėlimas greičiau nei pilnas sinchronizavimas — kopijuojami tik PDF duomenys.
            </p>
            <p style="margin: 0 0 16px; font-size: 0.82rem; color: var(--text-secondary);">
                ⚠️ Jei užsakymo dar nėra Tomo_QMS — jis bus sukurtas automatiškai prieš perkeliant PDF.
            </p>
            <form method="post" data-testid="form-vykdyti">
                <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="vykdyti" value="1">
                <button type="submit" class="btn btn-primary" data-testid="button-persiusti"
                        onclick="return confirm('Pradėti PDF perkėlimą į Tomo_QMS?\n\nBus persiųsti <?= $skirtumas['viso_laukia'] ?> PDF.\nGali užtrukti kelias minutes.')">
                    📤 Persiųsti <?= $skirtumas['viso_laukia'] ?> PDF į Tomo_QMS
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="alert alert-success" data-testid="alert-no-pdfs">
            ✅ Visi PDF jau yra Tomo_QMS — nieko persiųsti nereikia.
        </div>
        <?php endif; ?>

        <?php elseif (!$tomo_conn_ok): ?>
        <!-- Nėra prisijungimo - nieko nerodome -->
        <?php endif; ?>

        <div style="margin-top: 8px;">
            <a href="/index.php" class="btn btn-secondary btn-sm" data-testid="link-home">← Grįžti į pradžią</a>
        </div>

    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
