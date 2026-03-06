<?php
session_start();

$database_url = getenv('DATABASE_URL');
$parsed = parse_url($database_url);
$dsn = "pgsql:host={$parsed['host']};port=" . ($parsed['port'] ?? 5432) . ";dbname=" . ltrim($parsed['path'], '/');
$pdo = new PDO($dsn, $parsed['user'], $parsed['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$history_id = (int)($_GET['id'] ?? 0);
if ($history_id <= 0) {
    http_response_code(400);
    echo 'Neteisingas nuorodos ID';
    exit;
}

$stmt = $pdo->prepare("
    SELECT eh.*, p.tipas, p.aprasymas, p.aptikimo_vieta, p.gaminys_info, p.atsakingas_padalinys,
           p.siulomas_sprendimas, p.terminas, p.gavimo_data,
           u.uzsakymo_numeris
    FROM pretenzijos_email_history eh
    JOIN pretenzijos p ON p.id = eh.pretenzija_id
    LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
    WHERE eh.id = :id
");
$stmt->execute([':id' => $history_id]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    echo 'Įrašas nerastas';
    exit;
}

$tipai = [
    'vidine' => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjo pretenzija'
];

$sekminga = '';
$klaida = '';
$jau_atsakyta = !empty($record['feedback_text']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$jau_atsakyta) {
    $feedback = trim($_POST['feedback_text'] ?? '');
    $feedback_by = trim($_POST['feedback_by'] ?? '');

    if (empty($feedback)) {
        $klaida = 'Atsakymo tekstas privalomas';
    } else {
        $upd = $pdo->prepare("
            UPDATE pretenzijos_email_history
            SET feedback_text = :text, feedback_at = NOW(), feedback_by = :by
            WHERE id = :id
        ");
        $upd->execute([':text' => $feedback, ':by' => $feedback_by ?: null, ':id' => $history_id]);
        $sekminga = 'Atsakymas pateiktas sėkmingai. Ačiū!';
        $jau_atsakyta = true;
        $record['feedback_text'] = $feedback;
        $record['feedback_by'] = $feedback_by;

        try {
            require_once __DIR__ . '/klases/Emailas.php';
            $notify_subject = "Atsakymas į pretenziją #{$record['pretenzija_id']}";
            $notify_html = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <div style="background:#27ae60;padding:15px 20px;border-radius:8px 8px 0 0;color:white;">
                    <h2 style="margin:0;font-size:16px;">Gautas atsakymas į pretenziją #' . $record['pretenzija_id'] . '</h2>
                </div>
                <div style="background:#f9fafb;padding:20px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;">
                    <p><strong>Atsakė:</strong> ' . htmlspecialchars($feedback_by ?: 'Nenurodyta') . '</p>
                    <div style="background:#e8f5e9;padding:12px;border-radius:6px;border-left:4px solid #27ae60;margin:10px 0;">
                        ' . nl2br(htmlspecialchars($feedback)) . '
                    </div>
                    <hr style="border:none;border-top:1px solid #eee;margin:15px 0;">
                    <p style="color:#aaa;font-size:11px;text-align:center;">Kokybės valdymo sistema — UAB "ELGA"</p>
                </div>
            </div>';

            if (!empty($record['sent_by'])) {
            }

            if (!empty($record['email_delegated_to'])) {
                Emailas::siusti($record['email_delegated_to'], $notify_subject, $notify_html);
            }
            if (!empty($record['email_cc'])) {
                foreach (array_map('trim', explode(',', $record['email_cc'])) as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                        Emailas::siusti($cc, $notify_subject, $notify_html);
                    }
                }
            }
        } catch (Exception $e) {
        }
    }
}

$esc = function($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); };
$uzsakymo_nr = $record['uzsakymo_numeris'] ?? '-';
$tipas_label = $tipai[$record['tipas']] ?? $record['tipas'];
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atsakymas į pretenziją #<?= $record['pretenzija_id'] ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; justify-content: center; padding: 2rem 1rem; }
        .container { width: 100%; max-width: 700px; }
        .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; }
        .header h1 { font-size: 1.3rem; margin-bottom: 0.3rem; }
        .header p { opacity: 0.85; font-size: 0.9rem; }
        .card { background: white; border-radius: 0 0 12px 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); padding: 1.5rem; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 1.25rem; }
        .info-table td { padding: 0.5rem 0.75rem; border: 1px solid #e9ecef; font-size: 0.88rem; }
        .info-table td:first-child { font-weight: 600; background: #f8f9fa; width: 35%; }
        .section-title { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: #555; margin-bottom: 0.5rem; }
        .text-block { background: #f8f9fa; padding: 0.75rem; border-radius: 6px; border: 1px solid #e9ecef; font-size: 0.9rem; line-height: 1.6; margin-bottom: 1.25rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
        textarea { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.9rem; resize: vertical; font-family: inherit; }
        textarea:focus { outline: none; border-color: #27ae60; box-shadow: 0 0 0 3px rgba(39,174,96,0.15); }
        input[type="text"] { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.9rem; }
        input[type="text"]:focus { outline: none; border-color: #27ae60; box-shadow: 0 0 0 3px rgba(39,174,96,0.15); }
        label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.88rem; }
        .btn { padding: 0.6rem 1.5rem; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #1e8449; }
        .form-group { margin-bottom: 1rem; }
        .footer { text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Pretenzija #<?= $record['pretenzija_id'] ?></h1>
        <p><?= $esc($tipas_label) ?></p>
    </div>
    <div class="card">
        <?php if ($sekminga): ?>
            <div class="alert alert-success"><?= $esc($sekminga) ?></div>
        <?php endif; ?>
        <?php if ($klaida): ?>
            <div class="alert alert-danger"><?= $esc($klaida) ?></div>
        <?php endif; ?>

        <table class="info-table">
            <tr><td>Aptikimo vieta</td><td><?= $esc($record['aptikimo_vieta'] ?: '-') ?></td></tr>
            <tr><td>Gaminys</td><td><?= $esc($record['gaminys_info'] ?: '-') ?></td></tr>
            <tr><td>Užsakymo Nr.</td><td><?= $esc($uzsakymo_nr) ?></td></tr>
            <?php if (!empty($record['terminas'])): ?>
            <tr><td>Terminas</td><td><?= $esc($record['terminas']) ?></td></tr>
            <?php endif; ?>
        </table>

        <div class="section-title">Problemos aprašymas</div>
        <div class="text-block"><?= nl2br($esc($record['aprasymas'] ?: '-')) ?></div>

        <?php if (!empty($record['siulomas_sprendimas'])): ?>
        <div class="section-title">Siūlomas sprendimas</div>
        <div class="text-block"><?= nl2br($esc($record['siulomas_sprendimas'])) ?></div>
        <?php endif; ?>

        <?php if ($jau_atsakyta && empty($sekminga)): ?>
            <div class="alert alert-info">
                <strong>Atsakymas jau pateiktas</strong>
                <?php if (!empty($record['feedback_by'])): ?>
                    — <?= $esc($record['feedback_by']) ?>
                <?php endif; ?>
            </div>
            <div class="text-block" style="background:#e8f5e9;border-left:4px solid #27ae60;">
                <?= nl2br($esc($record['feedback_text'])) ?>
            </div>
        <?php elseif (!$jau_atsakyta): ?>
            <hr style="border:none;border-top:1px solid #e9ecef;margin:1.5rem 0;">
            <div class="section-title">Jūsų atsakymas</div>
            <form method="post">
                <div class="form-group">
                    <label for="feedback_by">Jūsų vardas, pavardė</label>
                    <input type="text" name="feedback_by" id="feedback_by" placeholder="Vardas Pavardė">
                </div>
                <div class="form-group">
                    <label for="feedback_text">Atsakymas <span style="color:#e74c3c;">*</span></label>
                    <textarea name="feedback_text" id="feedback_text" rows="5" required placeholder="Įveskite atsakymą..."></textarea>
                </div>
                <button type="submit" class="btn btn-success">Pateikti atsakymą</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="footer">Kokybės valdymo sistema — UAB "ELGA"</div>
</div>
</body>
</html>
