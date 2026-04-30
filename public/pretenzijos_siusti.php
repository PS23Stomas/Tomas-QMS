<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/klases/Emailas.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Tik POST užklausos']);
    exit;
}

$pretenzija_id = (int)($_POST['pretenzija_id'] ?? 0);
$email_to = trim($_POST['email_delegated_to'] ?? '');
$email_cc = trim($_POST['email_cc'] ?? '');
$papildomas_komentaras = trim($_POST['papildomas_komentaras'] ?? '');
$reply_to_history_id = (int)($_POST['reply_to_history_id'] ?? 0);

if ($pretenzija_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Nenurodytas pretenzijos ID']);
    exit;
}

if (empty($email_to) || !filter_var($email_to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Neteisingas gavėjo el. pašto adresas']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.uzsakymo_numeris
    FROM pretenzijos p
    LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $pretenzija_id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    echo json_encode(['success' => false, 'message' => 'Pretenzija nerasta']);
    exit;
}

$tipai = [
    'vidine' => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjo pretenzija'
];

$user = currentUser();
$prisijunges = trim(($user['vardas'] ?? '') . ' ' . ($user['pavarde'] ?? ''));
$tipas_label = $tipai[$p['tipas']] ?? $p['tipas'];
$uzsakymo_nr = $p['uzsakymo_numeris'] ?? $p['uzsakymo_numeris_ranka'] ?? '-';
$subject = "Pretenzija #{$pretenzija_id} — {$tipas_label}";

$baseUrl = getBaseUrl();

$stmtInsert = $pdo->prepare("
    INSERT INTO pretenzijos_email_history (pretenzija_id, email_delegated_to, email_cc, email_subject, sent_by, papildomas_komentaras, outgoing_text, parent_history_id)
    VALUES (:pid, :to, :cc, :subject, :by, :komentaras, :outgoing_text, :parent_history_id)
    RETURNING id
");
$stmtInsert->execute([
    ':pid' => $pretenzija_id,
    ':to' => $email_to,
    ':cc' => $email_cc ?: null,
    ':subject' => $subject,
    ':by' => $prisijunges,
    ':komentaras' => $papildomas_komentaras ?: null,
    ':outgoing_text' => $papildomas_komentaras ?: null,
    ':parent_history_id' => $reply_to_history_id > 0 ? $reply_to_history_id : null
]);
$history_id = $stmtInsert->fetchColumn();

$feedback_url = "{$baseUrl}/pretenzijos_atsakymas.php?id={$history_id}";

$esc = function($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); };

$imone = getImonesNustatymai();

// Jei tęsiamas pokalbis — gauname prieš tai buvusio atsakymo kontekstą
$ankstesnis_atsakymas = '';
if ($reply_to_history_id > 0) {
    $stmtPrev = $pdo->prepare("SELECT feedback_text, feedback_by, feedback_at, sent_by, sent_at FROM pretenzijos_email_history WHERE id = ? AND pretenzija_id = ?");
    $stmtPrev->execute([$reply_to_history_id, $pretenzija_id]);
    $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
    if ($prev && !empty($prev['feedback_text'])) {
        $ankstesnis_atsakymas = $prev['feedback_text'];
        $ankstesnis_autorius = $prev['feedback_by'] ?? 'Nenurodyta';
        $ankstesnis_data = !empty($prev['feedback_at']) ? date('Y-m-d H:i', strtotime($prev['feedback_at'])) : '';
    }
}

$html_body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 15px 20px; border-radius: 8px 8px 0 0; color: white;">
        <h2 style="margin: 0; font-size: 18px;">Pretenzija #' . $pretenzija_id . '</h2>
        <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">' . $esc($tipas_label) . '</p>
    </div>
    <div style="background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <td style="padding: 6px 10px; border: 1px solid #ddd; font-weight: bold; background: #f0f0f0; width: 40%;">Aptikimo vieta</td>
                <td style="padding: 6px 10px; border: 1px solid #ddd;">' . $esc($p['aptikimo_vieta'] ?: '-') . '</td>
            </tr>
            <tr>
                <td style="padding: 6px 10px; border: 1px solid #ddd; font-weight: bold; background: #f0f0f0;">Gaminys</td>
                <td style="padding: 6px 10px; border: 1px solid #ddd;">' . $esc($p['gaminys_info'] ?: '-') . '</td>
            </tr>
            <tr>
                <td style="padding: 6px 10px; border: 1px solid #ddd; font-weight: bold; background: #f0f0f0;">Užsakymo Nr.</td>
                <td style="padding: 6px 10px; border: 1px solid #ddd;">' . $esc($uzsakymo_nr) . '</td>
            </tr>
        </table>

        <div style="margin-bottom: 15px;">
            <strong style="font-size: 13px;">Problemos aprašymas:</strong>
            <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb; margin-top: 5px; font-size: 13px; line-height: 1.5;">
                ' . nl2br($esc($p['aprasymas'] ?: '-')) . '
            </div>
        </div>

        ' . (!empty($p['siulomas_sprendimas']) ? '
        <div style="margin-bottom: 15px;">
            <strong style="font-size: 13px;">Siūlomas sprendimas:</strong>
            <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb; margin-top: 5px; font-size: 13px;">
                ' . nl2br($esc($p['siulomas_sprendimas'])) . '
            </div>
        </div>' : '') . '

        ' . (!empty($p['terminas']) ? '<p style="font-size: 13px;"><strong>Terminas:</strong> ' . $esc($p['terminas']) . '</p>' : '') . '

        ' . (!empty($papildomas_komentaras) ? '
        <div style="margin-bottom: 15px; padding: 12px; background: #fffbeb; border: 1px solid #fbbf24; border-radius: 6px;">
            <strong style="font-size: 13px; color: #92400e;">Papildomas komentaras:</strong>
            <div style="font-size: 13px; line-height: 1.5; margin-top: 5px; color: #1c1917;">
                ' . nl2br($esc($papildomas_komentaras)) . '
            </div>
        </div>' : '') . '

        ' . (!empty($ankstesnis_atsakymas) ? '
        <div style="margin-bottom: 15px; padding: 12px; background: #f0fdf4; border-left: 4px solid #16a34a; border-radius: 0 6px 6px 0;">
            <strong style="font-size: 12px; color: #166534;">Ankstesnis atsakymas (' . $esc($ankstesnis_autorius ?? '') . ($ankstesnis_data ? ', ' . $esc($ankstesnis_data) : '') . '):</strong>
            <div style="font-size: 12px; line-height: 1.5; margin-top: 5px; color: #374151;">
                ' . nl2br($esc($ankstesnis_atsakymas)) . '
            </div>
        </div>' : '') . '

        <div style="text-align: center; margin: 25px 0 15px 0;">
            <a href="' . $esc($feedback_url) . '"
               style="background: #27ae60; color: white; padding: 12px 30px; border-radius: 6px;
                      text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                Pateikti atsakymą
            </a>
        </div>

        <p style="color: #888; font-size: 12px; text-align: center;">
            Siuntė: ' . $esc($prisijunges) . ' | ' . date('Y-m-d H:i') . '
        </p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 15px 0;">
        <p style="color: #aaa; font-size: 11px; text-align: center;">
            Kokybės valdymo sistema — ' . htmlspecialchars($imone['pavadinimas']) . '
        </p>
    </div>
</div>';

try {
    $sent = Emailas::siusti($email_to, $subject, $html_body);

    if ($sent) {
        if ($email_cc) {
            $cc_list = array_map('trim', explode(',', $email_cc));
            foreach ($cc_list as $cc_addr) {
                if (filter_var($cc_addr, FILTER_VALIDATE_EMAIL)) {
                    Emailas::siusti($cc_addr, $subject, $html_body);
                }
            }
        }
        echo json_encode(['success' => true, 'message' => 'Laiškas išsiųstas sėkmingai']);
    } else {
        $pdo->prepare("DELETE FROM pretenzijos_email_history WHERE id = ?")->execute([$history_id]);
        $errDetail = Emailas::getLastError();
        echo json_encode(['success' => false, 'message' => 'Nepavyko išsiųsti laiško' . ($errDetail ? ': ' . $errDetail : '')]);
    }
} catch (Exception $e) {
    $pdo->prepare("DELETE FROM pretenzijos_email_history WHERE id = ?")->execute([$history_id]);
    echo json_encode(['success' => false, 'message' => 'Klaida: ' . $e->getMessage()]);
}
