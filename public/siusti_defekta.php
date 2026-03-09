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
$gavejo_id = (int)($_POST['gavejo_id'] ?? 0);
$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$gaminio_pavadinimas = $_POST['gaminio_pavadinimas'] ?? '';

$eil_nr_arr = $_POST['eil_nr'] ?? [];
if (!is_array($eil_nr_arr)) {
    $eil_nr_arr = [(int)$eil_nr_arr];
} else {
    $eil_nr_arr = array_map('intval', $eil_nr_arr);
}
$eil_nr_arr = array_filter($eil_nr_arr, function($v) { return $v > 0; });

if (!$gaminio_id || empty($eil_nr_arr) || !$gavejo_id) {
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

$siuntejas = ($_SESSION['vardas'] ?? '') . ' ' . ($_SESSION['pavarde'] ?? '');
$gavejo_vardas = $gavejas['vardas'] . ' ' . $gavejas['pavarde'];
$gavejo_el = $gavejas['el_pastas'];
$data = date('Y-m-d H:i');

$placeholders = implode(',', array_fill(0, count($eil_nr_arr), '?'));
$params = array_merge([$gaminio_id], $eil_nr_arr);
$stmt = $conn->prepare("SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko FROM funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr IN ($placeholders)");
$stmt->execute($params);
$bandymai_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $bandymai_map[(int)$b['eil_nr']] = $b;
}

$punktu_html = '';
foreach ($eil_nr_arr as $nr) {
    $b = $bandymai_map[$nr] ?? null;
    if (!$b) continue;
    $bg = ($punktu_html === '') ? '' : ' style="background: #f3f4f6;"';
    $punktu_html .= '
        <tr' . $bg . '>
            <td style="padding: 10px; border: 1px solid #e5e7eb; text-align:center; font-weight:600;">' . (int)$b['eil_nr'] . '</td>
            <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($b['reikalavimas'] ?? '') . '</td>
            <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($b['isvada'] ?? '') . '</td>
            <td style="padding: 10px; border: 1px solid #e5e7eb; color: #c0392b; font-weight: 500;">' . htmlspecialchars($b['defektas'] ?? '-') . '</td>
            <td style="padding: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($b['darba_atliko'] ?? '-') . '</td>
        </tr>';
}

if (empty($punktu_html)) {
    echo json_encode(['success' => false, 'message' => 'Bandymų punktai nerasti. Pirmiausia išsaugokite formą.']);
    exit;
}

$html = '
<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 20px;">MT Modulis - Defektų pranešimas</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
        <p style="color: #333; font-size: 16px;">Sveiki, <strong>' . htmlspecialchars($gavejo_vardas) . '</strong></p>
        <p style="color: #555; font-size: 14px; line-height: 1.6;">
            Jums siunčiamas pranešimas apie funkcinių bandymų punktus (' . count($eil_nr_arr) . ' vnt.):
        </p>
        <table style="width: 100%; border-collapse: collapse; margin: 10px 0 5px;">
            <tr style="background: #e2e8f0;">
                <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600; width: 35%;">Užsakymo Nr.</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb;">' . htmlspecialchars($uzsakymo_numeris) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600;">Gaminys</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb;">' . htmlspecialchars($gaminio_pavadinimas) . '</td>
            </tr>
        </table>
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="background: #334155; color: white;">
                <th style="padding: 8px; border: 1px solid #e5e7eb; width:50px;">Nr.</th>
                <th style="padding: 8px; border: 1px solid #e5e7eb;">Reikalavimas</th>
                <th style="padding: 8px; border: 1px solid #e5e7eb; width:90px;">Išvada</th>
                <th style="padding: 8px; border: 1px solid #e5e7eb;">Defektas</th>
                <th style="padding: 8px; border: 1px solid #e5e7eb; width:100px;">Atliko</th>
            </tr>
            ' . $punktu_html . '
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

$reik_pavad = [];
foreach ($eil_nr_arr as $nr) {
    if (isset($bandymai_map[$nr])) {
        $reik_pavad[] = $nr;
    }
}
$tema = 'MT defektai: Užs. ' . $uzsakymo_numeris . ' - Punktai: ' . implode(', ', $reik_pavad);

try {
    $result = Emailas::siusti($gavejo_el, $tema, $html);

    if ($result) {
        $issiusta_info_new = $gavejo_vardas . ' (' . $data . ')';
        $rezultatai = [];

        foreach ($eil_nr_arr as $nr) {
            if (!isset($bandymai_map[$nr])) {
                $rezultatai[] = ['eil_nr' => $nr, 'ok' => false, 'message' => 'Nerastas'];
                continue;
            }

            $stmt = $conn->prepare("SELECT issiusta_kam FROM funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr = ?");
            $stmt->execute([$gaminio_id, $nr]);
            $esama = $stmt->fetchColumn();

            $naujas = !empty($esama) ? $esama . "\n" . $issiusta_info_new : $issiusta_info_new;

            $upd = $conn->prepare("UPDATE funkciniai_bandymai SET issiusta_kam = ? WHERE gaminio_id = ? AND eil_nr = ?");
            $upd->execute([$naujas, $gaminio_id, $nr]);

            $rezultatai[] = ['eil_nr' => $nr, 'ok' => true, 'issiusta_kam' => $naujas];
        }

        echo json_encode([
            'success' => true,
            'message' => 'Išsiųsta: ' . count($eil_nr_arr) . ' punktai',
            'rezultatai' => $rezultatai
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepavyko išsiųsti el. laiško. Patikrinkite Resend API nustatymus.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Klaida: ' . $e->getMessage()]);
}
