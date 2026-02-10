<?php 
require_once 'klases/Database.php';
require_once 'klases/Sesija.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['gaminio_id'], $_POST['isvada'], $_POST['defektas'])) {
    echo "Duomenų nepavyko išsaugoti – netinkamas užklausos metodas arba trūksta duomenų.";
    exit;
}

$gaminio_id       = (int)$_POST['gaminio_id'];
$isvados          = $_POST['isvada'];
$defektai         = $_POST['defektas'];
$reikalavimai     = $_POST['reikalavimas'] ?? [];
$eil_nrs          = $_POST['eil_nr'] ?? [];
$darba_atliko_in  = $_POST['darba_atliko'] ?? [];

$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';

$pilnas_vardas = (isset($_SESSION['vardas'], $_SESSION['pavarde']))
    ? ($_SESSION['vardas'] . ' ' . $_SESSION['pavarde'])
    : '';

$db   = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    // Esami įrašai (įskaitant reikalavimą palyginimui)
    $stmt = $conn->prepare("
        SELECT eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas
        FROM mt_funkciniai_bandymai
        WHERE gaminio_id = ?
    ");
    $stmt->execute([$gaminio_id]);
    $esami = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $esami[(int)$row['eil_nr']] = [
            'reikalavimas'     => trim((string)$row['reikalavimas']),
            'isvada'           => trim((string)$row['isvada']),
            'defektas'         => trim((string)$row['defektas']),
            'darba_atliko'     => trim((string)$row['darba_atliko']),
            'irase_vartotojas' => (string)$row['irase_vartotojas'],
        ];
    }

    $upd = $conn->prepare("
        UPDATE mt_funkciniai_bandymai
           SET reikalavimas     = :reikalavimas,
               isvada           = :isvada,
               defektas         = :defektas,
               darba_atliko     = :darba_atliko
         WHERE gaminio_id       = :gaminio_id AND eil_nr = :eil_nr
    ");

    $ins = $conn->prepare("
        INSERT INTO mt_funkciniai_bandymai
            (gaminio_id, eil_nr, reikalavimas, isvada, defektas, darba_atliko, irase_vartotojas)
        VALUES
            (:gaminio_id, :eil_nr, :reikalavimas, :isvada, :defektas, :darba_atliko, :irase_vartotojas)
    ");

    foreach ($isvados as $i => $isv) {
        $eil_nr       = isset($eil_nrs[$i]) ? (int)$eil_nrs[$i] : ($i + 1);
        $reik         = trim((string)($reikalavimai[$i]    ?? ''));
        $def          = trim((string)($defektai[$i]        ?? ''));
        $darba_atliko = trim((string)($darba_atliko_in[$i] ?? ''));

        $buvo = $esami[$eil_nr] ?? null;
        
        // ❗ Jei forma atsiuntė tuščią "darba_atliko", bet DB jau turi reikšmę - išsaugoti seną
        if ($darba_atliko === '' && $buvo && $buvo['darba_atliko'] !== '') {
            $darba_atliko = $buvo['darba_atliko'];
        }
        
        // ❗ PUNKTAS 14: Vykdytojas niekada nekeičiamas po pirminio įrašymo
        if ($eil_nr === 14 && $buvo && $buvo['darba_atliko'] !== '') {
            $darba_atliko = $buvo['darba_atliko'];
        }

        // ❗ Jei tai nauja eilutė ir ji visiškai nepildyta – praleidžiam (nieko neįrašom)
        if (!$buvo && $isv === 'nepadaryta' && $def === '' && $darba_atliko === '') {
            continue;
        }

        // ❗ Atnaujinam/įterpiam tik jei realiai keitėsi bent vienas laukas
        if ($buvo) {
            $reikia_atnaujinti = (
                $buvo['isvada']       !== (string)$isv  ||
                $buvo['defektas']     !== (string)$def ||
                $buvo['darba_atliko'] !== (string)$darba_atliko ||
                $buvo['reikalavimas'] !== (string)$reik
            );
            
            // DEBUG: Log kas pasikeitė punktui 14
            if ($eil_nr === 14) {
                error_log("=== PUNKTAS 14 DEBUG ===");
                error_log("Reikia atnaujinti: " . ($reikia_atnaujinti ? 'TAIP' : 'NE'));
                if ($buvo['isvada'] !== (string)$isv) {
                    error_log("  isvada: DB='{$buvo['isvada']}' vs Forma='{$isv}'");
                }
                if ($buvo['defektas'] !== (string)$def) {
                    error_log("  defektas: DB='{$buvo['defektas']}' vs Forma='{$def}'");
                }
                if ($buvo['darba_atliko'] !== (string)$darba_atliko) {
                    error_log("  darba_atliko: DB='{$buvo['darba_atliko']}' vs Forma='{$darba_atliko}'");
                }
                if ($buvo['reikalavimas'] !== (string)$reik) {
                    error_log("  reikalavimas: DB='{$buvo['reikalavimas']}' vs Forma='{$reik}'");
                }
            }
            
            if (!$reikia_atnaujinti) {
                continue; // jokio pokyčio – nieko nedarom ir „Įrašė" nekeičiam
            }
        }

        // 'Įrašė' – tik pirmą kartą įrašant naują eilutę
        // Jei eilutė jau egzistuoja, išsaugome pirminį įrašytoją
        if ($buvo && $buvo['irase_vartotojas'] !== '') {
            $irase_vartotojas = $buvo['irase_vartotojas']; // Išsaugome pirminį įrašytoją
        } else {
            $irase_vartotojas = $pilnas_vardas; // Naujai eilutei įrašome dabartinį vartotoją
        }

        if ($buvo) {
            // UPDATE – be irase_vartotojas (nepakeičiame pirminio įrašytojo)
            $params_upd = [
                ':gaminio_id'   => $gaminio_id,
                ':eil_nr'       => $eil_nr,
                ':reikalavimas' => $reik,
                ':isvada'       => $isv,
                ':defektas'     => $def,
                ':darba_atliko' => $darba_atliko,
            ];
            $upd->execute($params_upd);
        } else {
            // INSERT – su irase_vartotojas (nauja eilutė)
            $params_ins = [
                ':gaminio_id'       => $gaminio_id,
                ':eil_nr'           => $eil_nr,
                ':reikalavimas'     => $reik,
                ':isvada'           => $isv,
                ':defektas'         => $def,
                ':darba_atliko'     => $darba_atliko,
                ':irase_vartotojas' => $irase_vartotojas,
            ];
            $ins->execute($params_ins);
        }
    }

    $conn->commit();

    $qs = http_build_query([
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas'        => $uzsakovas,
        'gaminio_id'       => $gaminio_id,
        'issaugota'        => 'taip'
    ]);
    header("Location: mt_funkciniai_bandymai.php?{$qs}");
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Klaida saugant: " . htmlspecialchars($e->getMessage());
}
