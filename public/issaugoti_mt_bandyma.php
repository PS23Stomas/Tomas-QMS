<?php
require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

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

$conn = Database::getConnection();

try {
    $conn->beginTransaction();

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

    $pateikti_eil_nriai = [];

    foreach ($isvados as $i => $isv) {
        $eil_nr       = isset($eil_nrs[$i]) ? (int)$eil_nrs[$i] : ($i + 1);
        $reik         = trim((string)($reikalavimai[$i]    ?? ''));
        $def          = trim((string)($defektai[$i]        ?? ''));
        $darba_atliko = trim((string)($darba_atliko_in[$i] ?? ''));

        $pateikti_eil_nriai[] = $eil_nr;
        $buvo = $esami[$eil_nr] ?? null;

        if ($darba_atliko === '' && $buvo && $buvo['darba_atliko'] !== '') {
            $darba_atliko = $buvo['darba_atliko'];
        }

        if ($eil_nr === 14 && $buvo && $buvo['darba_atliko'] !== '') {
            $darba_atliko = $buvo['darba_atliko'];
        }

        if (!$buvo && $isv === 'nepadaryta' && $def === '' && $darba_atliko === '') {
            continue;
        }

        if ($buvo) {
            $reikia_atnaujinti = (
                $buvo['isvada']       !== (string)$isv  ||
                $buvo['defektas']     !== (string)$def ||
                $buvo['darba_atliko'] !== (string)$darba_atliko ||
                $buvo['reikalavimas'] !== (string)$reik
            );

            if (!$reikia_atnaujinti) {
                continue;
            }
        }

        if ($buvo && $buvo['irase_vartotojas'] !== '') {
            $irase_vartotojas = $buvo['irase_vartotojas'];
        } else {
            $irase_vartotojas = $pilnas_vardas;
        }

        if ($buvo) {
            $upd->execute([
                ':gaminio_id'   => $gaminio_id,
                ':eil_nr'       => $eil_nr,
                ':reikalavimas' => $reik,
                ':isvada'       => $isv,
                ':defektas'     => $def,
                ':darba_atliko' => $darba_atliko,
            ]);
        } else {
            $ins->execute([
                ':gaminio_id'       => $gaminio_id,
                ':eil_nr'           => $eil_nr,
                ':reikalavimas'     => $reik,
                ':isvada'           => $isv,
                ':defektas'         => $def,
                ':darba_atliko'     => $darba_atliko,
                ':irase_vartotojas' => $irase_vartotojas,
            ]);
        }
    }

    if (!empty($pateikti_eil_nriai)) {
        $placeholders = implode(',', array_fill(0, count($pateikti_eil_nriai), '?'));
        $del = $conn->prepare("DELETE FROM mt_funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr NOT IN ($placeholders)");
        $del->execute(array_merge([$gaminio_id], $pateikti_eil_nriai));
    }

    $conn->commit();

    $qs = http_build_query([
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas'        => $uzsakovas,
        'gaminio_id'       => $gaminio_id,
        'issaugota'        => 'taip'
    ]);
    header("Location: /mt_funkciniai_bandymai.php?{$qs}");
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Klaida saugant: " . htmlspecialchars($e->getMessage());
}
