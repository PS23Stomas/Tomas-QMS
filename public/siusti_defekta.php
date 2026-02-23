<?php
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Emailas.php';
require_once __DIR__ . '/klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Tik POST metodas leidžiamas']);
    exit;
}

$gaminio_id = (int)($_POST['gaminio_id'] ?? 0);
$eil_nr = (int)($_POST['eil_nr'] ?? 0);
$gavejo_id = (int)($_POST['gavejo_id'] ?? 0);
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? '';

if (!$gaminio_id || !$eil_nr || !$gavejo_id) {
    echo json_encode(['success' => false, 'message' => 'Trūksta privalomų duomenų']);
    exit;
}

$conn = Database::getConnection();

$stmt = $conn->prepare("SELECT el_pastas, vardas, pavarde FROM vartotojai WHERE id = ?");
$stmt->execute([$gavejo_id]);
$gavejas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gavejas || empty($gavejas['el_pastas'])) {
    echo json_encode(['success' => false, 'message' => 'Gavėjas neturi el. pašto adreso']);
    exit;
}

$stmt = $conn->prepare("SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko FROM mt_funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr = ?");
$stmt->execute([$gaminio_id, $eil_nr]);
$bandymas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bandymas) {
    echo json_encode(['success' => false, 'message' => 'Bandymo punktas nerastas. Pirmiausia išsaugokite formą.']);
    exit;
}

$siuntejas = ($_SESSION['vardas'] ?? '') . ' ' . ($_SESSION['pavarde'] ?? '');
$gavejo_vardas = $gavejas['vardas'] . ' ' . $gavejas['pavarde'];
$gavejo_el = $gavejas['el_pastas'];
$data = date('Y-m-d H:i');

$html = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 20px;">MT Modulis - Defekto pranešimas</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
        <p style="color: #333; font-size: 16px;">Sveiki, <strong>' . htmlspecialchars($gavejo_vardas) . '</strong></p>
        <p style="color: #555; font-size: 14px; line-height: 1.6;">
            Jums siunčiamas pranešimas apie funkcinių bandymų punktą:
        </p>
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="background: #f3f4f6;">
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600; width: 40%;">Užsakymo Nr.</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($uzsakymo_numeris) . '</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600;">Gaminys</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($gaminio_pavadinimas) . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600;">Punkto Nr.</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">' . (int)$bandymas['eil_nr'] . '</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600;">Reikalavimas</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($bandymas['reikalavimas'] ?? '') . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600;">Išvada</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($bandymas['isvada'] ?? '') . '</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600;">Defektas</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb; color: #c0392b; font-weight: 500;">' . htmlspecialchars($bandymas['defektas'] ?? '-') . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <td style="padding: 10px; border: 1px solid #e5e7eb; font-weight: 600;">Darbą atliko</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($bandymas['darba_atliko'] ?? '-') . '</td>
            </tr>
        </table>
        <p style="color: #888; font-size: 13px; line-height: 1.5;">
            Pranešimą išsiuntė: <strong>' . htmlspecialchars($siuntejas) . '</strong><br>
            Data: ' . $data . '
        </p>
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
        <p style="color: #aaa; font-size: 11px; text-align: center;">
            MT Modulis - Gamybos valdymo sistema
        </p>
    </div>
</div>';

$tema = 'MT defektas: Užs. ' . $uzsakymo_numeris . ' - ' . ($bandymas['reikalavimas'] ?? 'Punktas ' . $eil_nr);

try {
    $result = Emailas::siusti($gavejo_el, $tema, $html);

    if ($result) {
        $issiusta_info = $gavejo_vardas . ' (' . $data . ')';

        $stmt = $conn->prepare("SELECT issiusta_kam FROM mt_funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr = ?");
        $stmt->execute([$gaminio_id, $eil_nr]);
        $esama = $stmt->fetchColumn();

        if (!empty($esama)) {
            $issiusta_info = $esama . "\n" . $issiusta_info;
        }

        $upd = $conn->prepare("UPDATE mt_funkciniai_bandymai SET issiusta_kam = ? WHERE gaminio_id = ? AND eil_nr = ?");
        $upd->execute([$issiusta_info, $gaminio_id, $eil_nr]);

        echo json_encode([
            'success' => true,
            'message' => 'El. laiškas išsiųstas: ' . $gavejo_vardas,
            'issiusta_kam' => $issiusta_info
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepavyko išsiųsti el. laiško. Patikrinkite Resend API nustatymus.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Klaida: ' . $e->getMessage()]);
}
