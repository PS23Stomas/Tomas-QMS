<?php
/**
 * MT funkcinių bandymų išsaugojimo tvarkyklė - transakcija, originalaus vartotojo išsaugojimas
 *
 * Šis failas apdoroja funkcinių bandymų formos pateikimą. Naudoja duomenų bazės transakciją,
 * išsaugo originalų vartotoją (irase_vartotojas), atnaujina arba įterpia eilutes,
 * ir pašalina eilutes, kurios nebuvo pateiktos formoje.
 */

require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Sesija.php';
require_once __DIR__ . '/klases/TomoQMS.php';

Sesija::pradzia();
Sesija::tikrintiPrisijungima();

/* Tikrinama, ar užklausa yra POST ir ar yra būtini duomenys */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['gaminio_id'], $_POST['isvada'], $_POST['defektas'])) {
    echo "Duomenų nepavyko išsaugoti – netinkamas užklausos metodas arba trūksta duomenų.";
    exit;
}

/* POST duomenų nuskaitymas iš formos */
$gaminio_id       = (int)$_POST['gaminio_id'];
$isvados          = $_POST['isvada'];
$defektai         = $_POST['defektas'];
$reikalavimai     = $_POST['reikalavimas'] ?? [];
$eil_nrs          = $_POST['eil_nr'] ?? [];
$darba_atliko_in  = $_POST['darba_atliko'] ?? [];

$uzsakymo_numeris = $_POST['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_POST['uzsakovas'] ?? '';
$uzsakymo_id      = $_POST['uzsakymo_id'] ?? '';

/* Dabartinio prisijungusio vartotojo pilnas vardas */
$pilnas_vardas = (isset($_SESSION['vardas'], $_SESSION['pavarde']))
    ? ($_SESSION['vardas'] . ' ' . $_SESSION['pavarde'])
    : '';

$conn = Database::getConnection();

try {
    /* Transakcijos pradžia - visi pakeitimai bus atlikti arba atšaukti kartu */
    $conn->beginTransaction();

    /* --- Esamų duomenų užkrovimas iš duomenų bazės --- */
    /* Užkraunami visi esami bandymų įrašai šiam gaminiui, indeksuoti pagal eilės numerį */
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

    /* Paruošiami SQL sakiniai: atnaujinimui (UPDATE) ir įterpimui (INSERT) */
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

    /* --- Atnaujinimo ir įterpimo logika kiekvienai eilutei --- */
    foreach ($isvados as $i => $isv) {
        $eil_nr       = isset($eil_nrs[$i]) ? (int)$eil_nrs[$i] : ($i + 1);
        $reik         = trim((string)($reikalavimai[$i]    ?? ''));
        $def          = trim((string)($defektai[$i]        ?? ''));
        $darba_atliko = trim((string)($darba_atliko_in[$i] ?? ''));

        $pateikti_eil_nriai[] = $eil_nr;
        $buvo = $esami[$eil_nr] ?? null;

        /* Jei darbuotojas neįvestas, bet buvo anksčiau - išsaugomas ankstesnis */
        if ($darba_atliko === '' && $buvo && $buvo['darba_atliko'] !== '') {
            $darba_atliko = $buvo['darba_atliko'];
        }

        /* 14-asis punktas: darbuotojo vardas nekeičiamas, jei jau buvo įvestas */
        if ($eil_nr === 14 && $buvo && $buvo['darba_atliko'] !== '') {
            $darba_atliko = $buvo['darba_atliko'];
        }

        /* Praleidžiame tuščias naujas eilutes (nepadaryta, be defekto, be darbuotojo) */
        if (!$buvo && $isv === 'nepadaryta' && $def === '' && $darba_atliko === '') {
            continue;
        }

        /* Tikriname ar reikia atnaujinti - praleidžiame, jei duomenys nepasikeitė */
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

        /* --- Originalaus vartotojo išsaugojimas --- */
        /* Jei įrašas jau egzistavo su vartotoju, išsaugomas originalus autorius */
        if ($buvo && $buvo['irase_vartotojas'] !== '') {
            $irase_vartotojas = $buvo['irase_vartotojas'];
        } else {
            $irase_vartotojas = $pilnas_vardas;
        }

        /* Vykdome UPDATE (jei eilutė egzistavo) arba INSERT (jei nauja) */
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

    /* --- Pašalinamų eilučių valymas --- */
    /* Ištrinamos eilutės, kurių eilės numeriai nebuvo pateikti formoje */
    if (!empty($pateikti_eil_nriai)) {
        $placeholders = implode(',', array_fill(0, count($pateikti_eil_nriai), '?'));
        $del = $conn->prepare("DELETE FROM mt_funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr NOT IN ($placeholders)");
        $del->execute(array_merge([$gaminio_id], $pateikti_eil_nriai));
    }

    /* --- Nuotraukų įkėlimo apdorojimas --- */
    $max_foto_dydis = 10 * 1024 * 1024;
    $leistini_tipai = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];

    foreach ($pateikti_eil_nriai as $enr) {
        $file_key = 'nuotrauka_' . $enr;
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK && $_FILES[$file_key]['size'] > 0) {
            if ($_FILES[$file_key]['size'] > $max_foto_dydis) {
                continue;
            }
            $tmp = $_FILES[$file_key]['tmp_name'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $tikras_tipas = $finfo->file($tmp);
            if (!in_array($tikras_tipas, $leistini_tipai)) {
                continue;
            }
            $pavadinimas = $_FILES[$file_key]['name'];
            $turinys = file_get_contents($tmp);
            if ($turinys !== false) {
                $check = $conn->prepare("SELECT 1 FROM mt_funkciniai_bandymai WHERE gaminio_id = ? AND eil_nr = ?");
                $check->execute([$gaminio_id, $enr]);
                if (!$check->fetch()) {
                    $ins = $conn->prepare("INSERT INTO mt_funkciniai_bandymai (gaminio_id, eil_nr, isvada, defekto_nuotrauka, defekto_nuotraukos_pavadinimas) VALUES (:gid, :enr, 'nepadaryta', :foto, :pav)");
                    $ins->bindParam(':foto', $turinys, PDO::PARAM_LOB);
                    $ins->bindParam(':pav', $pavadinimas);
                    $ins->bindParam(':gid', $gaminio_id);
                    $ins->bindParam(':enr', $enr);
                    $ins->execute();
                } else {
                    $upd_photo = $conn->prepare("UPDATE mt_funkciniai_bandymai SET defekto_nuotrauka = :foto, defekto_nuotraukos_pavadinimas = :pav WHERE gaminio_id = :gid AND eil_nr = :enr");
                    $upd_photo->bindParam(':foto', $turinys, PDO::PARAM_LOB);
                    $upd_photo->bindParam(':pav', $pavadinimas);
                    $upd_photo->bindParam(':gid', $gaminio_id);
                    $upd_photo->bindParam(':enr', $enr);
                    $upd_photo->execute();
                }
            }
        }
    }

    /* Transakcijos patvirtinimas - visi pakeitimai įrašomi */
    $conn->commit();

    $qs = http_build_query([
        'uzsakymo_numeris' => $uzsakymo_numeris,
        'uzsakovas'        => $uzsakovas,
        'gaminio_id'       => $gaminio_id,
        'uzsakymo_id'      => $uzsakymo_id,
        'issaugota'        => 'taip'
    ]);
    header("Location: /mt_funkciniai_bandymai.php?{$qs}");
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Klaida saugant: " . htmlspecialchars($e->getMessage());
}
