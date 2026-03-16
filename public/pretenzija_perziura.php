<?php
/**
 * Pretenzijos peržiūros puslapis — atskiras švarus puslapis vienai pretenzijai peržiūrėti.
 * Naudojamas kaip nuoroda, kurią galima kopijuoti ir siųsti kitiems.
 * URL formatas: /pretenzija_perziura.php?id=33
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Pretenzijos peržiūra';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $klaida = 'Nenurodytas pretenzijos ID.';
    $p = null;
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.uzsakymo_numeris
        FROM pretenzijos p
        LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        $klaida = 'Pretenzija #' . $id . ' nerasta.';
    }
}

$tipai = [
    'vidine' => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjo pretenzija'
];

$statusai = [
    'nauja' => ['label' => 'Nauja', 'color' => '#3498db', 'bg' => '#ebf5fb'],
    'tyrimas' => ['label' => 'Tiriama', 'color' => '#f39c12', 'bg' => '#fef9e7'],
    'vykdoma' => ['label' => 'Vykdoma', 'color' => '#9b59b6', 'bg' => '#f5eef8'],
    'uzbaigta' => ['label' => 'Užbaigta', 'color' => '#27ae60', 'bg' => '#eafaf1'],
    'atmesta' => ['label' => 'Atmesta', 'color' => '#95a5a6', 'bg' => '#f4f6f6']
];

$nuotraukos = [];
$email_history = [];

if ($p) {
    $nStmt = $pdo->prepare("SELECT id, pavadinimas FROM pretenzijos_nuotraukos WHERE pretenzija_id = :id ORDER BY id");
    $nStmt->execute([':id' => $id]);
    $nuotraukos = $nStmt->fetchAll(PDO::FETCH_ASSOC);

    $eStmt = $pdo->prepare("SELECT id, email_delegated_to, email_cc, email_subject, sent_by, sent_at, feedback_text, feedback_at, feedback_by FROM pretenzijos_email_history WHERE pretenzija_id = :id ORDER BY sent_at DESC");
    $eStmt->execute([':id' => $id]);
    $email_history = $eStmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';

function esc($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
function escNl($val) {
    if (empty($val)) return '-';
    return nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .perziura-container {
        max-width: 850px;
        margin: 0 auto;
        padding: 1.5rem;
    }
    .perziura-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .perziura-header {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .perziura-header h2 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
    }
    .perziura-body {
        padding: 1.5rem;
    }
    .perziura-footer {
        padding: 0.75rem 1.5rem;
        border-top: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .badge-tipas {
        padding: 0.3rem 0.7rem;
        border-radius: 6px;
        font-size: 0.82rem;
        font-weight: 600;
    }
    .badge-tipas.vidine { background: #ebf5fb; color: #2980b9; }
    .badge-tipas.kliento { background: #fef9e7; color: #b7950b; }
    .badge-tipas.tiekejo { background: #f5eef8; color: #8e44ad; }
    .badge-statusas {
        padding: 0.3rem 0.7rem;
        border-radius: 6px;
        font-size: 0.82rem;
        font-weight: 600;
    }
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1rem;
    }
    .info-table td {
        padding: 0.5rem;
        border: 1px solid #dee2e6;
    }
    .info-table td.label {
        font-weight: 600;
        background: #f8f9fa;
    }
    .section-title {
        text-transform: uppercase;
        font-size: 0.88rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
    }
    .section-content {
        background: #f8f9fa;
        padding: 0.75rem;
        border-radius: 6px;
        margin-bottom: 1rem;
    }
    .photo-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    .photo-gallery img {
        width: 100%;
        border-radius: 6px;
        cursor: pointer;
        border: 1px solid #dee2e6;
        transition: transform 0.2s;
    }
    .photo-gallery img:hover {
        transform: scale(1.03);
    }
    .email-entry {
        background: #f8f9fa;
        padding: 0.6rem 0.8rem;
        border-radius: 6px;
        margin-top: 0.5rem;
        border: 1px solid #e9ecef;
        font-size: 0.85rem;
    }
    .btn-footer {
        padding: 0.45rem 1rem;
        border-radius: 6px;
        font-size: 0.88rem;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        text-decoration: none;
    }
    .btn-pdf {
        border: 1px solid #c0392b;
        background: #fdedec;
        color: #c0392b;
    }
    .btn-back {
        border: 1px solid #dee2e6;
        background: white;
        color: #333;
    }
    .error-box {
        max-width: 600px;
        margin: 3rem auto;
        text-align: center;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    .error-box i {
        font-size: 3rem;
        color: #e74c3c;
        margin-bottom: 1rem;
        display: block;
    }
    .uzfiksavo-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: #6c757d;
    }
</style>

<?php if (!$p): ?>
    <div class="error-box" data-testid="error-pretenzija-not-found">
        <i class="bi bi-exclamation-triangle"></i>
        <h3><?= esc($klaida) ?></h3>
        <a href="pretenzijos.php" class="btn-footer btn-back" style="margin-top:1rem;display:inline-flex;">
            <i class="bi bi-arrow-left"></i> Grįžti į sąrašą
        </a>
    </div>
<?php else: ?>
    <?php
        $st = $statusai[$p['statusas']] ?? $statusai['nauja'];
        $tipas_label = $tipai[$p['tipas']] ?? $p['tipas'];
    ?>
    <div class="perziura-container" data-testid="pretenzija-perziura-container">
        <div class="perziura-card">
            <div class="perziura-header">
                <h2><i class="bi bi-eye me-2"></i>Pretenzija #<?= (int)$p['id'] ?></h2>
                <a href="pretenzijos.php" style="color:white;text-decoration:none;font-size:0.85rem;">
                    <i class="bi bi-arrow-left me-1"></i>Grįžti
                </a>
            </div>

            <div class="perziura-body">
                <div style="margin-bottom:1rem;">
                    <span class="badge-tipas <?= esc($p['tipas']) ?>" style="font-size:0.85rem;padding:0.4rem 0.8rem;"><?= esc($tipas_label) ?></span>
                    <span class="badge-statusas" style="background:<?= esc($st['bg']) ?>;color:<?= esc($st['color']) ?>;font-size:0.85rem;padding:0.4rem 0.8rem;"><?= esc($st['label']) ?></span>
                </div>

                <table class="info-table">
                    <tr>
                        <td class="label" style="width:40%;">Problemos pastebėjimo vieta</td>
                        <td class="label" style="width:30%;">Gaminys</td>
                        <td class="label" style="width:30%;">Užsakymo Nr.</td>
                    </tr>
                    <tr>
                        <td><?= esc($p['aptikimo_vieta']) ?: '-' ?></td>
                        <td><?= esc($p['gaminys_info']) ?: '-' ?></td>
                        <td><?= esc($p['uzsakymo_numeris'] ?? $p['uzsakymo_numeris_ranka']) ?: '-' ?></td>
                    </tr>
                </table>

                <div style="margin-bottom:1rem;">
                    <div class="section-title">Problemos aprašymas</div>
                    <div class="section-content"><?= escNl($p['aprasymas']) ?></div>
                </div>

                <div style="margin-bottom:1rem;">
                    <div class="section-title">Padalinys atsakingas už sprendimą</div>
                    <div class="section-content"><?= esc($p['atsakingas_padalinys']) ?: '-' ?></div>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div>
                        <div class="section-title">Siūlomas sprendimo būdas</div>
                        <div class="section-content"><?= escNl($p['siulomas_sprendimas']) ?></div>
                    </div>
                    <div>
                        <div class="section-title">Terminas</div>
                        <div class="section-content"><?= esc($p['terminas']) ?: '-' ?></div>
                    </div>
                </div>

                <div class="section-content" style="border:1px solid #dee2e6;margin-bottom:1rem;">
                    <div class="section-title" style="margin-bottom:0.5rem;">Problemą užfiksavo</div>
                    <div class="uzfiksavo-grid">
                        <div><strong>Padalinys:</strong> <?= esc($p['uzfiksavo_padalinys']) ?: '-' ?></div>
                        <div><strong>Asmuo:</strong> <?= esc($p['uzfiksavo_asmuo']) ?: '-' ?></div>
                        <div><strong>Data:</strong> <?= esc($p['gavimo_data']) ?: '-' ?></div>
                    </div>
                </div>

                <?php if (!empty($p['priezastis'])): ?>
                    <div style="margin-bottom:1rem;">
                        <div class="section-title">Nustatyta priežastis</div>
                        <div class="section-content"><?= escNl($p['priezastis']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($p['veiksmai'])): ?>
                    <div style="margin-bottom:1rem;">
                        <div class="section-title">Korekciniai veiksmai</div>
                        <div class="section-content"><?= escNl($p['veiksmai']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (count($nuotraukos) > 0): ?>
                    <div style="margin-bottom:1rem;">
                        <div class="section-title"><i class="bi bi-images me-1"></i>Nuotraukos (<?= count($nuotraukos) ?>)</div>
                        <div class="photo-gallery">
                            <?php foreach ($nuotraukos as $n): ?>
                                <img src="pretenzijos_nuotrauka.php?id=<?= (int)$n['id'] ?>"
                                     alt="<?= esc($n['pavadinimas']) ?: 'Nuotrauka' ?>"
                                     onclick="window.open(this.src, '_blank')"
                                     data-testid="img-nuotrauka-<?= (int)$n['id'] ?>">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (count($email_history) > 0): ?>
                    <div style="border-top:1px solid #dee2e6;margin-top:1rem;padding-top:1rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                            <div class="section-title"><i class="bi bi-envelope-paper me-1"></i>El. pašto istorija</div>
                            <div style="font-size:0.8rem;">
                                <?php
                                    $sent = count($email_history);
                                    $answered = count(array_filter($email_history, fn($h) => !empty($h['feedback_text'])));
                                    $waiting = $sent - $answered;
                                ?>
                                <span style="background:#ebf5fb;color:#2980b9;padding:0.2rem 0.5rem;border-radius:8px;margin-right:0.3rem;">Išsiųsta: <?= $sent ?></span>
                                <span style="background:#d4edda;color:#155724;padding:0.2rem 0.5rem;border-radius:8px;margin-right:0.3rem;">Atsakyta: <?= $answered ?></span>
                                <?php if ($waiting > 0): ?>
                                    <span style="background:#fff3cd;color:#856404;padding:0.2rem 0.5rem;border-radius:8px;">Laukiama: <?= $waiting ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php foreach ($email_history as $h): ?>
                            <?php $hasF = !empty($h['feedback_text']); ?>
                            <div class="email-entry">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem;">
                                    <div>
                                        <i class="bi bi-envelope me-1"></i>
                                        <strong><?= esc($h['email_delegated_to']) ?></strong>
                                        <?php if (!empty($h['email_cc'])): ?>
                                            <span style="color:#6c757d;font-size:0.8rem;"> (CC: <?= esc($h['email_cc']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($hasF): ?>
                                        <span style="background:#d4edda;color:#155724;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;">Atsakyta</span>
                                    <?php else: ?>
                                        <span style="background:#fff3cd;color:#856404;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;">Laukiama</span>
                                    <?php endif; ?>
                                </div>
                                <div style="color:#6c757d;font-size:0.8rem;">
                                    Siuntė: <?= esc($h['sent_by']) ?> | <?= esc($h['sent_at']) ?>
                                </div>
                                <?php if ($hasF): ?>
                                    <div style="margin-top:0.4rem;padding:0.4rem 0.6rem;background:#d4edda;border-radius:4px;font-size:0.82rem;">
                                        <strong>Atsakymas:</strong> <?= escNl($h['feedback_text']) ?>
                                        <div style="color:#6c757d;font-size:0.75rem;margin-top:0.2rem;">
                                            <?= esc($h['feedback_by']) ?> | <?= esc($h['feedback_at']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="perziura-footer">
                <div style="display:flex;gap:0.5rem;">
                    <a href="pretenzijos_pdf.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn-footer btn-pdf" data-testid="button-pdf-perziura">
                        <i class="bi bi-file-earmark-pdf"></i>Atsisiųsti PDF
                    </a>
                </div>
                <a href="pretenzijos.php" class="btn-footer btn-back" data-testid="button-back-list">
                    <i class="bi bi-arrow-left"></i> Grįžti į sąrašą
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
