<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/klases/TomoQMS.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Tik POST metodas leidžiamas']);
    exit;
}

$masinis = ($_POST['masinis'] ?? '') === '1';
$masinis_sarasas = ($_POST['masinis_sarasas'] ?? '') === '1';
$importas = ($_POST['importas'] ?? '') === '1';
$conn = $pdo;

$importas_stream = ($_POST['importas_stream'] ?? '') === '1';

if ($importas_stream) {
    ignore_user_abort(true);
    set_time_limit(300);

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) apache_setenv('no-gzip', '1');
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    if (function_exists('ob_implicit_flush')) ob_implicit_flush(true);
    while (ob_get_level()) ob_end_flush();

    $lastProc = -1;
    $msgCount = 0;
    $progressCallback = function(int $proc, int $viso, string $zinute) use (&$lastProc, &$msgCount) {
        $msgCount++;
        if ($proc === $lastProc && $proc < 100 && $msgCount % 5 !== 0) return;
        $lastProc = $proc;
        echo "data: " . json_encode(['proc' => $proc, 'viso' => $viso, 'zinute' => $zinute]) . "\n\n";
        flush();
    };

    try {
        $rezultatas = TomoQMS::importuotiILocalDB($conn, $progressCallback);
        $success = empty($rezultatas['klaidos']);
        echo "data: " . json_encode([
            'proc' => 100,
            'baigta' => true,
            'success' => $success,
            'rezultatas' => $rezultatas
        ]) . "\n\n";
        flush();
    } catch (Throwable $e) {
        echo "data: " . json_encode(['proc' => 100, 'baigta' => true, 'success' => false, 'klaida' => $e->getMessage()]) . "\n\n";
        flush();
    }
    exit;
}

if ($importas) {
    ignore_user_abort(true);
    set_time_limit(300);
    try {
        $rezultatas = TomoQMS::importuotiILocalDB($conn);
        $success = empty($rezultatas['klaidos']);
        echo json_encode([
            'success' => $success,
            'message' => $success
                ? 'Importas sėkmingas! Nauji: ' . $rezultatas['nauji'] . ', Atnaujinti: ' . $rezultatas['atnaujinti'] . ', Gaminiai: ' . $rezultatas['gaminiai'] . ', Bandymai: ' . $rezultatas['bandymai'] . ', Komponentai: ' . $rezultatas['komponentai']
                : 'Importas baigtas su klaidomis.',
            'rezultatas' => $rezultatas
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Importo klaida: ' . $e->getMessage()]);
    }
    exit;
}

if ($masinis_sarasas) {
    $uzsakymai = $conn->query("
        SELECT u.id, u.uzsakymo_numeris
        FROM uzsakymai u
        ORDER BY u.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'uzsakymai' => $uzsakymai]);
    exit;
}

if ($masinis) {
    $rezultatai = [];
    $klaidos = [];
    $uzsakymu_viso = 0;

    $uzsakymai = $conn->query("
        SELECT u.id, u.uzsakymo_numeris, u.kiekis, u.gaminiu_rusis_id, u.sukurtas, u.vartotojas_id,
               uz.uzsakovas, o.pavadinimas as objektas
        FROM uzsakymai u
        LEFT JOIN uzsakovai uz ON uz.id = u.uzsakovas_id
        LEFT JOIN objektai o ON o.id = u.objektas_id
        ORDER BY u.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($uzsakymai as $uzsakymas) {
        $uzsakymu_viso++;
        $uzs_nr = $uzsakymas['uzsakymo_numeris'] ?? '';
        $uzsakymo_id = (int)$uzsakymas['id'];

        try {
            TomoQMS::sinchronizuotiUzsakyma(
                $uzs_nr,
                $uzsakymas['uzsakovas'] ?? null,
                $uzsakymas['objektas'] ?? null,
                (int)($uzsakymas['kiekis'] ?? 1),
                (int)($uzsakymas['vartotojas_id'] ?? 1),
                $uzsakymas['gaminiu_rusis_id'] ? (int)$uzsakymas['gaminiu_rusis_id'] : null,
                $uzsakymas['sukurtas'] ?? null
            );
            $rezultatai[] = 'Užs. ' . $uzs_nr;
        } catch (Throwable $e) {
            $klaidos[] = 'Užs. ' . $uzs_nr . ': ' . $e->getMessage();
        }

        $stmt = $conn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id = ? ORDER BY id ASC");
        $stmt->execute([$uzsakymo_id]);
        $gaminiu_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($gaminiu_ids as $gid) {
            sinchronizuotiGamini($conn, (int)$gid, $uzs_nr, $rezultatai, $klaidos);
        }
    }

    $success = empty($klaidos);
    echo json_encode([
        'success' => $success,
        'message' => $success
            ? 'Masinė sinchronizacija sėkminga!'
            : 'Sinchronizacija baigta su klaidomis.',
        'uzsakymu_viso' => $uzsakymu_viso,
        'rezultatai' => $rezultatai,
        'klaidos' => $klaidos,
        'sinchronizuota_viso' => count($rezultatai)
    ]);
    exit;
}

$uzsakymo_id = (int)($_POST['uzsakymo_id'] ?? 0);
$gaminio_id = (int)($_POST['gaminio_id'] ?? 0);

if ($uzsakymo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Nenurodytas užsakymo ID']);
    exit;
}

$rezultatai = [];
$klaidos = [];

try {
    $stmt = $conn->prepare("
        SELECT u.uzsakymo_numeris, u.kiekis, u.gaminiu_rusis_id, u.sukurtas, u.vartotojas_id,
               uz.uzsakovas, o.pavadinimas as objektas
        FROM uzsakymai u
        LEFT JOIN uzsakovai uz ON uz.id = u.uzsakovas_id
        LEFT JOIN objektai o ON o.id = u.objektas_id
        WHERE u.id = ?
    ");
    $stmt->execute([$uzsakymo_id]);
    $uzsakymas = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$uzsakymas) {
        echo json_encode(['success' => false, 'message' => 'Užsakymas nerastas']);
        exit;
    }

    $uzs_nr = $uzsakymas['uzsakymo_numeris'] ?? '';

    try {
        TomoQMS::sinchronizuotiUzsakyma(
            $uzs_nr,
            $uzsakymas['uzsakovas'] ?? null,
            $uzsakymas['objektas'] ?? null,
            (int)($uzsakymas['kiekis'] ?? 1),
            (int)($uzsakymas['vartotojas_id'] ?? 1),
            $uzsakymas['gaminiu_rusis_id'] ? (int)$uzsakymas['gaminiu_rusis_id'] : null,
            $uzsakymas['sukurtas'] ?? null
        );
        $rezultatai[] = 'Užsakymas sinchronizuotas';
    } catch (Throwable $e) {
        $klaidos[] = 'Užsakymo sinch.: ' . $e->getMessage();
    }

    if ($gaminio_id <= 0) {
        $stmt = $conn->prepare("SELECT id FROM gaminiai WHERE uzsakymo_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$uzsakymo_id]);
        $gaminio_id = (int)$stmt->fetchColumn();
    }

    if ($gaminio_id > 0) {
        sinchronizuotiGamini($conn, $gaminio_id, $uzs_nr, $rezultatai, $klaidos);
    }

    $success = empty($klaidos);
    $message = $success
        ? 'Sinchronizacija sėkminga! Sinchronizuota: ' . implode(', ', $rezultatai)
        : 'Sinchronizacija baigta su klaidomis. Pavyko: ' . implode(', ', $rezultatai) . '. Klaidos: ' . implode('; ', $klaidos);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'rezultatai' => $rezultatai,
        'klaidos' => $klaidos,
        'sinchronizuota_viso' => count($rezultatai)
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Sinchronizacijos klaida: ' . $e->getMessage()]);
}

function sinchronizuotiGamini($conn, $gaminio_id, $uzs_nr, &$rezultatai, &$klaidos) {
    try {
        TomoQMS::sinchFunkciniai($conn, $gaminio_id);
        $rezultatai[] = 'Funkciniai (' . $uzs_nr . ')';
    } catch (Throwable $e) {
        $klaidos[] = 'Funkciniai (' . $uzs_nr . '): ' . $e->getMessage();
    }

    try {
        TomoQMS::sinchKomponentai($conn, $gaminio_id);
        $rezultatai[] = 'Komponentai (' . $uzs_nr . ')';
    } catch (Throwable $e) {
        $klaidos[] = 'Komponentai (' . $uzs_nr . '): ' . $e->getMessage();
    }

    try {
        TomoQMS::sinchDielektriniai($conn, $gaminio_id);
        $rezultatai[] = 'Dielektriniai (' . $uzs_nr . ')';
    } catch (Throwable $e) {
        $klaidos[] = 'Dielektriniai (' . $uzs_nr . '): ' . $e->getMessage();
    }

    try {
        TomoQMS::sinchSaugiklius($conn, $gaminio_id);
        $rezultatai[] = 'Saugikliai (' . $uzs_nr . ')';
    } catch (Throwable $e) {
        $klaidos[] = 'Saugikliai (' . $uzs_nr . '): ' . $e->getMessage();
    }

    try {
        TomoQMS::sinchPrietaisus($conn, $gaminio_id);
        $rezultatai[] = 'Prietaisai (' . $uzs_nr . ')';
    } catch (Throwable $e) {
        $klaidos[] = 'Prietaisai (' . $uzs_nr . '): ' . $e->getMessage();
    }

    $stmt = $conn->prepare("SELECT protokolo_nr FROM gaminiai WHERE id = ?");
    $stmt->execute([$gaminio_id]);
    $protokolo_nr = $stmt->fetchColumn();
    if ($protokolo_nr) {
        try {
            TomoQMS::sinchProtokoloNr($conn, $gaminio_id, $protokolo_nr);
            $rezultatai[] = 'Protokolo Nr. (' . $uzs_nr . ')';
        } catch (Throwable $e) {
            $klaidos[] = 'Protokolo Nr. (' . $uzs_nr . '): ' . $e->getMessage();
        }
    }

    $stmt = $conn->prepare("SELECT field_key, lang, tekstas FROM mt_paso_teksto_korekcijos WHERE gaminio_id = ?");
    $stmt->execute([$gaminio_id]);
    $korekcijos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($korekcijos as $kor) {
        try {
            TomoQMS::sinchPasoTeksta($conn, $gaminio_id, $kor['field_key'], $kor['lang'], $kor['tekstas']);
        } catch (Throwable $e) {
            $klaidos[] = 'Paso tekstas (' . $uzs_nr . '): ' . $e->getMessage();
        }
    }
    if (!empty($korekcijos)) {
        $rezultatai[] = 'Paso korekcijos (' . $uzs_nr . ', ' . count($korekcijos) . ')';
    }

    $pdf_stulpeliai = [
        ['mt_paso_pdf', 'mt_paso_failas'],
        ['mt_dielektriniu_pdf', 'mt_dielektriniu_failas'],
        ['mt_funkciniu_pdf', 'mt_funkciniu_failas'],
    ];
    foreach ($pdf_stulpeliai as [$pdf_col, $failas_col]) {
        $stmt = $conn->prepare("SELECT $failas_col FROM gaminiai WHERE id = ? AND $failas_col IS NOT NULL");
        $stmt->execute([$gaminio_id]);
        if ($stmt->fetchColumn()) {
            try {
                TomoQMS::sinchPDF($conn, $gaminio_id, $pdf_col, $failas_col);
                $rezultatai[] = 'PDF ' . $failas_col . ' (' . $uzs_nr . ')';
            } catch (Throwable $e) {
                $klaidos[] = 'PDF ' . $failas_col . ' (' . $uzs_nr . '): ' . $e->getMessage();
            }
        }
    }
}
