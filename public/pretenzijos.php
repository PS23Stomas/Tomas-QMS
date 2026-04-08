<?php
/**
 * Pretenzijų valdymo puslapis - vidinių, kliento ir tiekėjo pretenzijų CRUD
 *
 * Šis puslapis leidžia kurti, peržiūrėti, redaguoti ir šalinti pretenzijas.
 * Palaikomi tipai: vidinė, kliento, tiekėjo.
 * Palaikomi statusai: nauja, tyrimas (tiriama), vykdoma, užbaigta, atmesta.
 */

require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Pretenzijos';
$user = currentUser();
$prisijunges = trim(($user['vardas'] ?? '') . ' ' . ($user['pavarde'] ?? ''));
$arSkaitytojas = ($user['role'] === 'skaitytojas');

// Pretenzijų tipų apibrėžimai
$tipai = [
    'vidine' => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjui pretenzija'
];

// Pretenzijų statusų apibrėžimai su spalvomis atvaizdavimui
$statusai = [
    'nauja' => ['label' => 'Nauja', 'color' => '#3498db', 'bg' => '#ebf5fb'],
    'tyrimas' => ['label' => 'Tiriama', 'color' => '#f39c12', 'bg' => '#fef9e7'],
    'vykdoma' => ['label' => 'Vykdoma', 'color' => '#9b59b6', 'bg' => '#f5eef8'],
    'uzbaigta' => ['label' => 'Užbaigta', 'color' => '#27ae60', 'bg' => '#eafaf1'],
    'atmesta' => ['label' => 'Atmesta', 'color' => '#95a5a6', 'bg' => '#f4f6f6']
];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'email_history') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_GET['pretenzija_id'] ?? 0);
    if (!$pid) { echo json_encode([]); exit; }
    $st = $pdo->prepare("SELECT id, email_delegated_to, email_cc, email_subject, sent_by, sent_at,
        feedback_text, feedback_at, feedback_by
        FROM pretenzijos_email_history WHERE pretenzija_id = ? ORDER BY sent_at DESC");
    $st->execute([$pid]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if (isset($_GET['view']) && ctype_digit($_GET['view'])) {
    header('Location: /pretenzija_perziura.php?id=' . (int)$_GET['view']);
    exit;
}

$klaida = '';
$sekminga = '';

$msg_map = ['sukurta' => 'Pretenzija sėkmingai užregistruota', 'atnaujinta' => 'Pretenzija atnaujinta', 'statusas' => 'Statusas atnaujintas', 'istrinta' => 'Pretenzija ištrinta'];
if (isset($_GET['msg']) && isset($msg_map[$_GET['msg']])) {
    $sekminga = $msg_map[$_GET['msg']];
}

function pretenzijosRedirect($msg) {
    $params = ['msg' => $msg];
    if (!empty($_GET['tipas'])) $params['tipas'] = $_GET['tipas'];
    if (!empty($_GET['statusas'])) $params['statusas'] = $_GET['statusas'];
    header('Location: /pretenzijos.php?' . http_build_query($params));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $veiksmas = $_POST['veiksmas'] ?? '';

    // Naujos pretenzijos kūrimas
    if ($veiksmas === 'kurti') {
        $tipas = $_POST['tipas'] ?? 'vidine';
        $aprasymas = trim($_POST['aprasymas'] ?? '');
        $uzsakymo_id = !empty($_POST['uzsakymo_id']) ? (int)$_POST['uzsakymo_id'] : null;
        $uzsakymo_numeris_ranka = trim($_POST['uzsakymo_numeris_ranka'] ?? '');
        $terminas = !empty($_POST['terminas']) ? $_POST['terminas'] : null;
        $gavimo_data = !empty($_POST['gavimo_data']) ? $_POST['gavimo_data'] : date('Y-m-d');

        $aptikimo_vieta = trim($_POST['aptikimo_vieta'] ?? '');
        $gaminys_info = trim($_POST['gaminys_info'] ?? '');
        $atsakingas_padalinys = trim($_POST['atsakingas_padalinys'] ?? '');
        $siulomas_sprendimas = trim($_POST['siulomas_sprendimas'] ?? '');
        $uzfiksavo_padalinys = trim($_POST['uzfiksavo_padalinys'] ?? '');
        $uzfiksavo_asmuo = trim($_POST['uzfiksavo_asmuo'] ?? '');

        if (empty($aprasymas)) {
            $klaida = 'Problemos aprašymas privalomas';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO pretenzijos (
                        tipas, aprasymas, uzsakymo_id, uzsakymo_numeris_ranka, terminas, gavimo_data, sukure_vardas,
                        aptikimo_vieta, gaminys_info, atsakingas_padalinys, siulomas_sprendimas,
                        uzfiksavo_padalinys, uzfiksavo_asmuo, sukure_id
                    ) VALUES (
                        :tipas, :aprasymas, :uzsakymo_id, :uzsakymo_numeris_ranka, :terminas, :gavimo_data, :sukure,
                        :aptikimo_vieta, :gaminys_info, :atsakingas_padalinys, :siulomas_sprendimas,
                        :uzfiksavo_padalinys, :uzfiksavo_asmuo, :sukure_id
                    )
                ");
                $stmt->execute([
                    ':tipas' => $tipas,
                    ':aprasymas' => $aprasymas,
                    ':uzsakymo_id' => $uzsakymo_id,
                    ':uzsakymo_numeris_ranka' => $uzsakymo_numeris_ranka ?: null,
                    ':terminas' => $terminas,
                    ':gavimo_data' => $gavimo_data,
                    ':sukure' => $prisijunges,
                    ':aptikimo_vieta' => $aptikimo_vieta ?: null,
                    ':gaminys_info' => $gaminys_info ?: null,
                    ':atsakingas_padalinys' => $atsakingas_padalinys ?: null,
                    ':siulomas_sprendimas' => $siulomas_sprendimas ?: null,
                    ':uzfiksavo_padalinys' => $uzfiksavo_padalinys ?: null,
                    ':uzfiksavo_asmuo' => $uzfiksavo_asmuo ?: null,
                    ':sukure_id' => $_SESSION['vartotojas_id']
                ]);

                $pretenzijaId = $pdo->lastInsertId();

                // Nuotraukų įkėlimas ir išsaugojimas duomenų bazėje
                if (!empty($_FILES['nuotraukos']['name'][0])) {
                    $stmtPhoto = $pdo->prepare("
                        INSERT INTO pretenzijos_nuotraukos (pretenzija_id, pavadinimas, tipas, turinys)
                        VALUES (:pretenzija_id, :pavadinimas, :tipas, :turinys)
                    ");

                    foreach ($_FILES['nuotraukos']['tmp_name'] as $i => $tmpName) {
                        if (!empty($tmpName) && is_uploaded_file($tmpName)) {
                            $fileName = $_FILES['nuotraukos']['name'][$i];
                            $fileType = $_FILES['nuotraukos']['type'][$i];
                            $fileContent = file_get_contents($tmpName);

                            $stmtPhoto->bindValue(':pretenzija_id', $pretenzijaId, PDO::PARAM_INT);
                            $stmtPhoto->bindValue(':pavadinimas', $fileName, PDO::PARAM_STR);
                            $stmtPhoto->bindValue(':tipas', $fileType, PDO::PARAM_STR);
                            $stmtPhoto->bindValue(':turinys', $fileContent, PDO::PARAM_LOB);
                            $stmtPhoto->execute();
                        }
                    }
                }

                if (!empty($_FILES['defekto_pdf']['tmp_name']) && is_uploaded_file($_FILES['defekto_pdf']['tmp_name'])) {
                    $pdfTmp = $_FILES['defekto_pdf']['tmp_name'];
                    $pdfName = $_FILES['defekto_pdf']['name'];
                    $pdfMime = mime_content_type($pdfTmp);
                    $pdfExt = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));
                    if ($pdfMime === 'application/pdf' && $pdfExt === 'pdf') {
                        $pdfContent = file_get_contents($pdfTmp);
                        $stmtPdf = $pdo->prepare("UPDATE pretenzijos SET defekto_pdf_pavadinimas = :pav, defekto_pdf_turinys = :tur WHERE id = :id");
                        $stmtPdf->bindValue(':pav', $pdfName, PDO::PARAM_STR);
                        $stmtPdf->bindValue(':tur', $pdfContent, PDO::PARAM_LOB);
                        $stmtPdf->bindValue(':id', $pretenzijaId, PDO::PARAM_INT);
                        $stmtPdf->execute();
                    }
                }

                pretenzijosRedirect('sukurta');
            } catch (PDOException $e) {
                $klaida = 'Klaida kuriant pretenziją: ' . $e->getMessage();
            }
        }
    }

    if ($veiksmas === 'atnaujinti_statusa') {
        $id = (int)($_POST['id'] ?? 0);
        $statusas = $_POST['statusas'] ?? '';

        if ($id > 0 && isset($statusai[$statusas])) {
            $uzbaigimo = ($statusas === 'uzbaigta' || $statusas === 'atmesta') ? 'CURRENT_DATE' : 'NULL';
            $stmt = $pdo->prepare("UPDATE pretenzijos SET statusas = :statusas, uzbaigimo_data = $uzbaigimo, atnaujinta = NOW() WHERE id = :id");
            $stmt->execute([':statusas' => $statusas, ':id' => $id]);
            pretenzijosRedirect('statusas');
        }
    }

    if ($veiksmas === 'atnaujinti') {
        $id = (int)($_POST['id'] ?? 0);
        $tipas_upd            = $_POST['tipas'] ?? 'vidine';
        $aprasymas_upd        = trim($_POST['aprasymas'] ?? '');
        $gavimo_data_upd      = !empty($_POST['gavimo_data']) ? $_POST['gavimo_data'] : null;
        $terminas_upd         = !empty($_POST['terminas']) ? $_POST['terminas'] : null;
        $aptikimo_vieta_upd   = trim($_POST['aptikimo_vieta'] ?? '');
        $gaminys_info_upd     = trim($_POST['gaminys_info'] ?? '');
        $uzsakymo_nr_upd      = trim($_POST['uzsakymo_numeris_ranka'] ?? '');
        $atsakingas_pad_upd   = trim($_POST['atsakingas_padalinys'] ?? '');
        $siulomas_spr_upd     = trim($_POST['siulomas_sprendimas'] ?? '');
        $uzfiksavo_pad_upd    = trim($_POST['uzfiksavo_padalinys'] ?? '');
        $uzfiksavo_asm_upd    = trim($_POST['uzfiksavo_asmuo'] ?? '');
        $priezastis           = trim($_POST['priezastis'] ?? '');
        $veiksmai_txt         = trim($_POST['veiksmai'] ?? '');
        $atsakingas           = trim($_POST['atsakingas_asmuo'] ?? '');

        if ($id > 0 && !empty($aprasymas_upd)) {
            $stmt = $pdo->prepare("
                UPDATE pretenzijos SET
                    tipas = :tipas,
                    aprasymas = :aprasymas,
                    gavimo_data = :gavimo_data,
                    terminas = :terminas,
                    aptikimo_vieta = :aptikimo_vieta,
                    gaminys_info = :gaminys_info,
                    uzsakymo_numeris_ranka = :uzsakymo_numeris_ranka,
                    atsakingas_padalinys = :atsakingas_padalinys,
                    siulomas_sprendimas = :siulomas_sprendimas,
                    uzfiksavo_padalinys = :uzfiksavo_padalinys,
                    uzfiksavo_asmuo = :uzfiksavo_asmuo,
                    priezastis = :priezastis,
                    veiksmai = :veiksmai,
                    atsakingas_asmuo = :atsakingas_asmuo,
                    atnaujinta = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':tipas'                  => $tipas_upd,
                ':aprasymas'              => $aprasymas_upd ?: null,
                ':gavimo_data'            => $gavimo_data_upd,
                ':terminas'               => $terminas_upd,
                ':aptikimo_vieta'         => $aptikimo_vieta_upd ?: null,
                ':gaminys_info'           => $gaminys_info_upd ?: null,
                ':uzsakymo_numeris_ranka' => $uzsakymo_nr_upd ?: null,
                ':atsakingas_padalinys'   => $atsakingas_pad_upd ?: null,
                ':siulomas_sprendimas'    => $siulomas_spr_upd ?: null,
                ':uzfiksavo_padalinys'    => $uzfiksavo_pad_upd ?: null,
                ':uzfiksavo_asmuo'        => $uzfiksavo_asm_upd ?: null,
                ':priezastis'             => $priezastis ?: null,
                ':veiksmai'               => $veiksmai_txt ?: null,
                ':atsakingas_asmuo'       => $atsakingas ?: null,
                ':id'                     => $id
            ]);

            if (!empty($_FILES['defekto_pdf']['tmp_name']) && is_uploaded_file($_FILES['defekto_pdf']['tmp_name'])) {
                $pdfTmp = $_FILES['defekto_pdf']['tmp_name'];
                $pdfName = $_FILES['defekto_pdf']['name'];
                $pdfMime = mime_content_type($pdfTmp);
                $pdfExt = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));
                if ($pdfMime === 'application/pdf' && $pdfExt === 'pdf') {
                    $pdfContent = file_get_contents($pdfTmp);
                    $stmtPdf = $pdo->prepare("UPDATE pretenzijos SET defekto_pdf_pavadinimas = :pav, defekto_pdf_turinys = :tur WHERE id = :id");
                    $stmtPdf->bindValue(':pav', $pdfName, PDO::PARAM_STR);
                    $stmtPdf->bindValue(':tur', $pdfContent, PDO::PARAM_LOB);
                    $stmtPdf->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtPdf->execute();
                }
            }

            pretenzijosRedirect('atnaujinta');
        }
    }

    if ($veiksmas === 'trinti') {
        $user = currentUser();
        if ($user['role'] !== 'admin') {
            $klaida = 'Tik administratorius gali trinti pretenzijas.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $patvirtinta = ($_POST['trynimo_patvirtinimas'] ?? '') === 'TAIP';
            if (!$patvirtinta) {
                $klaida = 'Trynimas nepatvirtintas.';
            } elseif ($id > 0) {
                $pdo->prepare("DELETE FROM pretenzijos_nuotraukos WHERE pretenzija_id = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM pretenzijos WHERE id = :id")->execute([':id' => $id]);
                pretenzijosRedirect('istrinta');
            }
        }
    }
}

// Filtravimo parametrai iš GET užklausos
$filtras_tipas = $_GET['tipas'] ?? '';
$filtras_statusas = $_GET['statusas'] ?? '';

$where = [];
$params = [];

if ($filtras_tipas && isset($tipai[$filtras_tipas])) {
    $where[] = "p.tipas = :tipas";
    $params[':tipas'] = $filtras_tipas;
}
if ($filtras_statusas && isset($statusai[$filtras_statusas])) {
    $where[] = "p.statusas = :statusas";
    $params[':statusas'] = $filtras_statusas;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pretenzijų sąrašo užklausa su rikiavimo pagal statusą ir sukūrimo datą
$sql = "
    SELECT p.id, p.uzsakymo_id, p.gaminio_id, p.pretenzijos_nr, p.data, p.tipas, p.aprasymas,
           p.statusas, p.prioritetas, p.atsakingas_asmuo, p.sprendimas, p.uzdaryta_data,
           p.sukure_id, p.sukurta, p.atnaujinta, p.aptikimo_vieta, p.gaminys_info,
           p.atsakingas_padalinys, p.siulomas_sprendimas, p.uzfiksavo_padalinys,
           p.uzfiksavo_asmuo, p.priezastis, p.veiksmai, p.terminas, p.gavimo_data,
           p.uzbaigimo_data, p.sukure_vardas, p.uzsakymo_numeris_ranka,
           p.defekto_pdf_pavadinimas,
           u.uzsakymo_numeris
    FROM pretenzijos p
    LEFT JOIN uzsakymai u ON u.id = p.uzsakymo_id
    $where_sql
    ORDER BY 
        CASE p.statusas 
            WHEN 'nauja' THEN 1 
            WHEN 'tyrimas' THEN 2 
            WHEN 'vykdoma' THEN 3 
            ELSE 4 
        END,
        p.sukurta DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pretenzijos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistikos skaičiavimas atvaizdavimui
$stats = ['viso' => count($pretenzijos), 'naujos' => 0, 'aktyvios' => 0, 'uzbaigtos' => 0];
foreach ($pretenzijos as $p) {
    if ($p['statusas'] === 'nauja') $stats['naujos']++;
    if (in_array($p['statusas'], ['nauja', 'tyrimas', 'vykdoma'])) $stats['aktyvios']++;
    if ($p['statusas'] === 'uzbaigta') $stats['uzbaigtos']++;
}

// Užsakymų sąrašas pasirinkimui formoje
$uzsakymai_opts = $pdo->query("SELECT id, uzsakymo_numeris FROM uzsakymai ORDER BY uzsakymo_numeris DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

// Nuotraukų susiejimas su pretenzijomis atvaizdavimui sąraše
$nuotraukos_map = [];
$nuotraukosStmt = $pdo->query("SELECT id, pretenzija_id, pavadinimas FROM pretenzijos_nuotraukos ORDER BY id");
while ($row = $nuotraukosStmt->fetch(PDO::FETCH_ASSOC)) {
    $pid = $row['pretenzija_id'];
    if (!isset($nuotraukos_map[$pid])) $nuotraukos_map[$pid] = [];
    $nuotraukos_map[$pid][] = ['id' => $row['id'], 'pavadinimas' => $row['pavadinimas']];
}

$email_stats_map = [];
$emailStatsStmt = $pdo->query("
    SELECT pretenzija_id,
        COUNT(*) as total_sent,
        COUNT(feedback_text) as total_answered
    FROM pretenzijos_email_history
    GROUP BY pretenzija_id
");
while ($row = $emailStatsStmt->fetch(PDO::FETCH_ASSOC)) {
    $email_stats_map[(int)$row['pretenzija_id']] = [
        'sent' => (int)$row['total_sent'],
        'answered' => (int)$row['total_answered']
    ];
}

foreach ($pretenzijos as &$p) {
    $p['nuotraukos'] = $nuotraukos_map[$p['id']] ?? [];
    $p['email_stats'] = $email_stats_map[$p['id']] ?? ['sent' => 0, 'answered' => 0];
}
unset($p);

include __DIR__ . '/includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-shrink: 0;
  }
  
  .stat-card {
    background: white;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-left: 4px solid;
  }
  
  .stat-card.viso { border-color: #3498db; }
  .stat-card.naujos { border-color: #e74c3c; }
  .stat-card.aktyvios { border-color: #f39c12; }
  .stat-card.uzbaigtos { border-color: #27ae60; }
  
  .stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
  }
  
  .stat-card.viso .stat-value { color: #3498db; }
  .stat-card.naujos .stat-value { color: #e74c3c; }
  .stat-card.aktyvios .stat-value { color: #f39c12; }
  .stat-card.uzbaigtos .stat-value { color: #27ae60; }
  
  .stat-label {
    font-size: 0.82rem;
    color: #7f8c8d;
    margin-top: 0.4rem;
  }
  
  .card-main {
    background: white;
    border-radius: 10px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  
  .card-header-custom {
    background: #f8f9fa;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    flex-shrink: 0;
  }
  
  .content-area {
    overflow: hidden;
  }

  .pretenzijos-list {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 0 0.25rem;
  }

  .filters {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
  }
  
  .filters select {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    padding: 0.4rem 0.75rem;
    font-size: 0.88rem;
  }
  
  .btn-add {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    font-size: 0.9rem;
  }
  
  .btn-add:hover {
    background: #c0392b;
    color: white;
  }
  
  .pretenzija-item {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
  }
  
  .pretenzija-item:hover {
    background: #fafbfc;
  }
  
  .pretenzija-item:last-child {
    border-bottom: none;
  }
  
  .pretenzija-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0.5rem;
  }
  
  .pretenzija-id {
    font-weight: 700;
    color: #e74c3c;
    font-size: 0.95rem;
  }
  
  .pretenzija-meta {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
  }
  
  .badge-tipas {
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
  }
  
  .badge-tipas.vidine { background: #ebf5fb; color: #2980b9; }
  .badge-tipas.kliento { background: #fef9e7; color: #b7950b; }
  .badge-tipas.tiekejo { background: #f5eef8; color: #8e44ad; }
  
  .badge-statusas {
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
  }
  
  .pretenzija-desc {
    color: #2c3e50;
    margin-bottom: 0.5rem;
    line-height: 1.5;
    font-size: 0.92rem;
  }
  
  .pretenzija-info {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
    font-size: 0.82rem;
    color: #7f8c8d;
  }
  
  .pretenzija-info i {
    margin-right: 0.2rem;
  }
  
  .pretenzija-actions {
    display: flex;
    gap: 0.4rem;
  }
  
  .btn-action {
    padding: 0.3rem 0.5rem;
    border-radius: 6px;
    font-size: 0.78rem;
    border: none;
    cursor: pointer;
  }
  
  .btn-action.view { background: #ebf5fb; color: #2980b9; }
  .btn-action.pdf { background: #fdedec; color: #c0392b; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
  .btn-action.email { background: #eafaf1; color: #27ae60; }
  .btn-action.edit { background: #fef9e7; color: #b7950b; }
  .btn-action.delete { background: #fdedec; color: #c0392b; }
  
  .empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #7f8c8d;
  }
  
  .empty-state i {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    opacity: 0.5;
  }
  
  .modal-header-custom {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
  }
  
  .modal-header-custom .btn-close {
    filter: brightness(0) invert(1);
  }
  
  .photo-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    background: #fafbfc;
  }
  
  .upload-placeholder {
    text-align: center;
    padding: 1.5rem;
    cursor: pointer;
    transition: background 0.2s;
  }
  
  .upload-placeholder:hover {
    background: #f0f4f8;
  }
  
  .photo-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
  }
  
  .photo-preview-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 1;
  }
  
  .photo-preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  
  .photo-preview-item .remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    background: rgba(220,53,69,0.9);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .photo-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.5rem;
  }
  
  .photo-gallery img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s;
  }
  
  .photo-gallery img:hover {
    transform: scale(1.05);
  }
  
  @media (max-width: 768px) {
    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
    }
    .pretenzija-header {
      flex-direction: column;
    }
    .card-header-custom {
      flex-direction: column;
      align-items: stretch;
    }
  }
  
  @media (max-width: 480px) {
    .stats-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<?php if ($klaida): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" style="background:#fdedec;border-color:#f5c6cb;color:#721c24;padding:0.75rem 1rem;border-radius:8px;font-size:0.9rem;" data-testid="alert-error">
    <i class="bi bi-exclamation-circle me-2"></i><?= h($klaida) ?>
    <button type="button" style="float:right;background:none;border:none;font-size:1.2rem;cursor:pointer;color:#721c24;" onclick="this.parentElement.remove()" aria-label="Uždaryti pranešimą">&times;</button>
  </div>
<?php endif; ?>

<?php if ($sekminga): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="background:#d4edda;border-color:#c3e6cb;color:#155724;padding:0.75rem 1rem;border-radius:8px;font-size:0.9rem;" data-testid="alert-success">
    <i class="bi bi-check-circle me-2"></i><?= h($sekminga) ?>
    <button type="button" style="float:right;background:none;border:none;font-size:1.2rem;cursor:pointer;color:#155724;" onclick="this.parentElement.remove()" aria-label="Uždaryti pranešimą">&times;</button>
  </div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card viso">
    <div class="stat-value" data-testid="text-stats-total"><?= $stats['viso'] ?></div>
    <div class="stat-label">Viso pretenzijų</div>
  </div>
  <div class="stat-card naujos">
    <div class="stat-value" data-testid="text-stats-new"><?= $stats['naujos'] ?></div>
    <div class="stat-label">Naujos</div>
  </div>
  <div class="stat-card aktyvios">
    <div class="stat-value" data-testid="text-stats-active"><?= $stats['aktyvios'] ?></div>
    <div class="stat-label">Aktyvios</div>
  </div>
  <div class="stat-card uzbaigtos">
    <div class="stat-value" data-testid="text-stats-completed"><?= $stats['uzbaigtos'] ?></div>
    <div class="stat-label">Užbaigtos</div>
  </div>
</div>

<div class="card-main">
  <div class="card-header-custom">
    <div class="filters">
      <select onchange="applyFilter('tipas', this.value)" data-testid="select-filter-type">
        <option value="">Visi tipai</option>
        <?php foreach ($tipai as $k => $v): ?>
          <option value="<?= $k ?>" <?= $filtras_tipas === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <select onchange="applyFilter('statusas', this.value)" data-testid="select-filter-status">
        <option value="">Visi statusai</option>
        <?php foreach ($statusai as $k => $s): ?>
          <option value="<?= $k ?>" <?= $filtras_statusas === $k ? 'selected' : '' ?>><?= $s['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!$arSkaitytojas): ?>
    <button class="btn-add" onclick="document.getElementById('modalKurti').style.display='flex'" data-testid="button-new-claim">
      <i class="bi bi-plus-lg"></i>Nauja pretenzija
    </button>
    <?php endif; ?>
  </div>
  
  <div class="pretenzijos-list">
  <?php if (empty($pretenzijos)): ?>
    <div class="empty-state">
      <i class="bi bi-inbox"></i>
      <h5>Pretenzijų nerasta</h5>
      <p>Sukurkite naują pretenziją paspaudę mygtuką viršuje</p>
    </div>
  <?php else: ?>
    <?php foreach ($pretenzijos as $p): 
      $st = $statusai[$p['statusas']] ?? $statusai['nauja'];
    ?>
      <div class="pretenzija-item" data-testid="row-claim-<?= $p['id'] ?>">
        <div class="pretenzija-header">
          <div>
            <span class="pretenzija-id">#<?= $p['id'] ?></span>
            <span class="badge-tipas <?= h($p['tipas']) ?>"><?= $tipai[$p['tipas']] ?? h($p['tipas']) ?></span>
            <span class="badge-statusas" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>"><?= $st['label'] ?></span>
          </div>
          <div class="pretenzija-actions">
            <button class="btn-action view" onclick="viewPretenzija(<?= $p['id'] ?>)" title="Peržiūrėti" aria-label="Peržiūrėti pretenziją #<?= $p['id'] ?>" data-testid="button-view-<?= $p['id'] ?>">
              <i class="bi bi-eye"></i>
            </button>
            <a class="btn-action pdf" href="pretenzijos_pdf.php?id=<?= $p['id'] ?>" target="_blank" title="PDF" aria-label="Atsisiųsti PDF pretenzijai #<?= $p['id'] ?>" data-testid="button-pdf-<?= $p['id'] ?>">
              <i class="bi bi-file-earmark-pdf"></i>
            </a>
            <?php if (!$arSkaitytojas): ?>
            <button class="btn-action email" onclick="openEmailModal(<?= $p['id'] ?>)" title="Siųsti el. paštu" aria-label="Siųsti el. laišką pretenzijai #<?= $p['id'] ?>" data-testid="button-email-<?= $p['id'] ?>">
              <i class="bi bi-envelope"></i>
            </button>
            <?php endif; ?>
            <button class="btn-action edit" onclick="editPretenzija(<?= $p['id'] ?>)" title="Redaguoti" aria-label="Redaguoti pretenziją #<?= $p['id'] ?>" data-testid="button-edit-<?= $p['id'] ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <?php if (currentUser()['role'] === 'admin'): ?>
            <button class="btn-action delete" title="Šis veiksmas negrįžtamas – pretenzija bus ištrinta" aria-label="Trinti pretenziją #<?= $p['id'] ?>" data-testid="button-delete-<?= $p['id'] ?>"
                onclick="atidarytiPretenzijosTrinyma(<?= $p['id'] ?>, '<?= h($p['nr'] ?? $p['id']) ?>')">
              <i class="bi bi-trash"></i>
            </button>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="pretenzija-desc">
          <?= nl2br(h(mb_substr($p['aprasymas'], 0, 200))) ?><?= mb_strlen($p['aprasymas'] ?? '') > 200 ? '...' : '' ?>
          <?php if (!empty($p['nuotraukos'])): ?>
            <span style="display:inline-block;background:#6c757d;color:white;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;margin-left:0.5rem;"><i class="bi bi-camera-fill me-1"></i><?= count($p['nuotraukos']) ?></span>
          <?php endif; ?>
        </div>
        
        <div class="pretenzija-info">
          <span><i class="bi bi-calendar3"></i><?= $p['gavimo_data'] ? date('Y-m-d', strtotime($p['gavimo_data'])) : ($p['data'] ? date('Y-m-d', strtotime($p['data'])) : '-') ?></span>
          <?php if (!empty($p['aptikimo_vieta'])): ?>
            <span><i class="bi bi-geo-alt"></i><?= h($p['aptikimo_vieta']) ?></span>
          <?php endif; ?>
          <?php 
          $uzsakymo_nr_display = $p['uzsakymo_numeris'] ?? $p['uzsakymo_numeris_ranka'] ?? null;
          if ($uzsakymo_nr_display): ?>
            <span><i class="bi bi-box"></i>Užs. <?= h($uzsakymo_nr_display) ?></span>
          <?php endif; ?>
          <?php if (!empty($p['gaminys_info'])): ?>
            <span><i class="bi bi-cpu"></i><?= h($p['gaminys_info']) ?></span>
          <?php endif; ?>
          <?php if (!empty($p['atsakingas_padalinys'])): ?>
            <span><i class="bi bi-building"></i><?= h($p['atsakingas_padalinys']) ?></span>
          <?php endif; ?>
          <?php if ($p['terminas']): ?>
            <span><i class="bi bi-clock"></i>Terminas: <?= date('Y-m-d', strtotime($p['terminas'])) ?></span>
          <?php endif; ?>
          <?php 
          $es = $p['email_stats'];
          if ($es['sent'] > 0):
            $waiting = $es['sent'] - $es['answered'];
            if ($es['answered'] === $es['sent']): ?>
              <span style="background:#d4edda;color:#155724;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;"><i class="bi bi-check-circle-fill me-1"></i><?= $es['answered'] ?>/<?= $es['sent'] ?> atsakyta</span>
            <?php elseif ($es['answered'] > 0): ?>
              <span style="background:#fff3cd;color:#856404;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;"><i class="bi bi-hourglass-split me-1"></i><?= $es['answered'] ?>/<?= $es['sent'] ?> atsakyta</span>
            <?php else: ?>
              <span style="background:#fef9e7;color:#b7950b;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;"><i class="bi bi-envelope me-1"></i>Laukiama <?= $es['sent'] ?></span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</div>

<div id="modalKurti" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:flex-start;padding-top:2rem;overflow-y:auto;">
  <div style="background:white;border-radius:12px;width:95%;max-width:900px;margin-bottom:2rem;">
    <div style="background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%);color:white;padding:1rem 1.5rem;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:1.1rem;"><i class="bi bi-file-earmark-text me-2"></i>PRETENZIJA (PR 28/2 forma)</h5>
      <button type="button" onclick="document.getElementById('modalKurti').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <div style="padding:1.5rem;">
        <input type="hidden" name="veiksmas" value="kurti">
        
        <div style="margin-bottom:1.5rem;">
          <div style="display:grid;grid-template-columns:5fr 4fr 3fr;gap:1rem;">
            <div>
              <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;">Problemos pastebėjimo (aptikimo) vieta</label>
              <select name="aptikimo_vieta_select" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;margin-bottom:0.4rem;" onchange="toggleCustomField(this, 'aptikimo_vieta_custom')" data-testid="select-aptikimo-vieta">
                <option value="">-- Pasirinkti --</option>
                <option value="USN baras">USN baras</option>
                <option value="Dėžių baras">Dėžių baras</option>
                <option value="MT baras">MT baras</option>
                <option value="KAMP baras">KAMP baras</option>
                <option value="Paruošimas">Paruošimas</option>
                <option value="Objekte">Objekte</option>
                <option value="__kita__">Kita (įvesti)...</option>
              </select>
              <input type="text" name="aptikimo_vieta_custom" id="aptikimo_vieta_custom" style="display:none;width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="Įveskite kitą vietą..." data-testid="input-aptikimo-vieta-custom">
              <input type="hidden" name="aptikimo_vieta" id="aptikimo_vieta_final">
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;">Gaminys</label>
              <select name="gaminys_info_select" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;margin-bottom:0.4rem;" onchange="toggleCustomField(this, 'gaminys_info_custom')" data-testid="select-gaminys-info">
                <option value="">-- Pasirinkti --</option>
                <option value="USN">USN</option>
                <option value="GVX">GVX</option>
                <option value="MT">MT</option>
                <option value="SI-04">SI-04</option>
                <option value="KAMP">KAMP</option>
                <option value="LPS">LPS</option>
                <option value="Dėžės">Dėžės</option>
                <option value="__kita__">Kita (įvesti)...</option>
              </select>
              <input type="text" name="gaminys_info_custom" id="gaminys_info_custom" style="display:none;width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="Įveskite kitą gaminį..." data-testid="input-gaminys-info-custom">
              <input type="hidden" name="gaminys_info" id="gaminys_info_final">
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;">Užsakymo Nr.</label>
              <select name="uzsakymo_id_select" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;margin-bottom:0.4rem;" onchange="toggleUzsakymoField(this)" data-testid="select-uzsakymo-id">
                <option value="">-- Pasirinkti --</option>
                <?php foreach ($uzsakymai_opts as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= h($u['uzsakymo_numeris']) ?></option>
                <?php endforeach; ?>
                <option value="__kita__">Kita (įvesti rankiniu būdu)...</option>
              </select>
              <input type="text" name="uzsakymo_numeris_ranka" id="uzsakymo_numeris_ranka" style="display:none;width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="Įveskite užsakymo numerį..." data-testid="input-uzsakymo-nr-ranka">
              <input type="hidden" name="uzsakymo_id" id="uzsakymo_id_final">
            </div>
          </div>
        </div>
        
        <div style="margin-bottom:1.5rem;">
          <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;text-transform:uppercase;">Problemos aprašymas <span style="color:#e74c3c;">*</span></label>
          <textarea name="aprasymas" style="width:100%;padding:0.5rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;resize:vertical;" rows="4" required placeholder="Išsamiai aprašykite nustatytą problemą..." data-testid="input-aprasymas"></textarea>
          
          <div style="margin-top:0.75rem;">
            <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;"><i class="bi bi-camera me-1"></i>Nuotraukos (neprivaloma)</label>
            <div class="photo-upload-area" id="photoUploadArea">
              <input type="file" name="nuotraukos[]" id="photoInput" multiple accept="image/*" capture="environment" style="display:none;" data-testid="input-photos">
              <div class="upload-placeholder" onclick="document.getElementById('photoInput').click()">
                <i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:#6c757d;"></i>
                <div style="margin-top:0.5rem;">Paspauskite norėdami įkelti nuotraukas</div>
                <small style="color:#6c757d;">arba nufotografuokite telefonu</small>
              </div>
              <div id="photoPreview" class="photo-preview-grid"></div>
            </div>
          </div>
          <div style="margin-top:0.75rem;">
            <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;"><i class="bi bi-file-earmark-pdf me-1"></i>Defekto PDF (neprivaloma)</label>
            <input type="file" name="defekto_pdf" accept="application/pdf,.pdf" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;background:white;" data-testid="input-defekto-pdf">
            <small style="color:#6c757d;">Tik PDF failai priimami</small>
          </div>
        </div>
        
        <div style="margin-bottom:1.5rem;">
          <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;text-transform:uppercase;">Padalinys atsakingas už sprendimą</label>
          <input type="text" name="atsakingas_padalinys" style="width:100%;padding:0.4rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="Pvz.: Gamybos padalinys, Kokybės skyrius..." data-testid="input-atsakingas-padalinys">
        </div>
        
        <div style="margin-bottom:1.5rem;">
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
            <div>
              <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;text-transform:uppercase;">Siūlomas sprendimo būdas (jeigu žinoma)</label>
              <textarea name="siulomas_sprendimas" style="width:100%;padding:0.5rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;resize:vertical;" rows="3" placeholder="Aprašykite siūlomą sprendimą..." data-testid="input-siulomas-sprendimas"></textarea>
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:0.4rem;font-size:0.88rem;text-transform:uppercase;">Terminas</label>
              <input type="date" name="terminas" style="width:100%;padding:0.4rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" data-testid="input-terminas">
            </div>
          </div>
        </div>
        
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;border:1px solid #dee2e6;margin-bottom:1rem;">
          <label style="font-weight:600;display:block;margin-bottom:0.75rem;font-size:0.88rem;text-transform:uppercase;">Problemą užfiksavo</label>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
              <label style="display:block;font-size:0.8rem;color:#6c757d;margin-bottom:0.25rem;">Padalinys</label>
              <input type="text" name="uzfiksavo_padalinys" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="Padalinio pavadinimas" data-testid="input-uzfiksavo-padalinys">
            </div>
            <div>
              <label style="display:block;font-size:0.8rem;color:#6c757d;margin-bottom:0.25rem;">Pavardė, vardas</label>
              <input type="text" name="uzfiksavo_asmuo" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" value="<?= h($prisijunges) ?>" placeholder="Vardas Pavardė" data-testid="input-uzfiksavo-asmuo">
            </div>
            <div>
              <label style="display:block;font-size:0.8rem;color:#6c757d;margin-bottom:0.25rem;">Data</label>
              <input type="date" name="gavimo_data" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" value="<?= date('Y-m-d') ?>" data-testid="input-gavimo-data">
            </div>
          </div>
        </div>
        
        <div style="margin-bottom:1rem;">
          <label style="display:block;margin-bottom:0.4rem;font-size:0.88rem;">Pretenzijos tipas</label>
          <select name="tipas" style="width:50%;padding:0.4rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" data-testid="select-tipas">
            <?php foreach ($tipai as $k => $v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="padding:1rem 1.5rem;border-top:1px solid #dee2e6;display:flex;justify-content:flex-end;gap:0.5rem;">
        <button type="button" onclick="document.getElementById('modalKurti').style.display='none'" style="padding:0.5rem 1rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.88rem;">Atšaukti</button>
        <button type="submit" style="padding:0.5rem 1rem;border:none;border-radius:6px;background:#e74c3c;color:white;cursor:pointer;font-weight:500;font-size:0.88rem;" data-testid="button-submit-claim">
          <i class="bi bi-plus-lg me-1"></i>Registruoti pretenziją
        </button>
      </div>
    </form>
  </div>
</div>

<div id="modalView" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:flex-start;padding-top:2rem;overflow-y:auto;">
  <div style="background:white;border-radius:12px;width:95%;max-width:800px;margin-bottom:2rem;">
    <div style="background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%);color:white;padding:1rem 1.5rem;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:1.1rem;"><i class="bi bi-eye me-2"></i>Pretenzijos peržiūra</h5>
      <button type="button" onclick="document.getElementById('modalView').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <div id="viewContent" style="padding:1.5rem;"></div>
    <div style="padding:0.75rem 1.5rem;border-top:1px solid #dee2e6;display:flex;justify-content:space-between;align-items:center;">
      <div style="display:flex;gap:0.5rem;">
        <button type="button" id="btnCopyLink" onclick="copyPretLink()" style="padding:0.4rem 1rem;border:1px solid #2980b9;border-radius:6px;background:#ebf5fb;color:#2980b9;cursor:pointer;font-size:0.88rem;font-weight:500;display:flex;align-items:center;gap:0.3rem;transition:all 0.3s;" data-testid="button-copy-link">
          <i class="bi bi-link-45deg"></i><span id="copyLinkText">Kopijuoti nuorodą</span>
        </button>
        <a id="btnViewPdf" href="#" target="_blank" style="padding:0.4rem 1rem;border:1px solid #c0392b;border-radius:6px;background:#fdedec;color:#c0392b;cursor:pointer;font-size:0.88rem;font-weight:500;display:flex;align-items:center;gap:0.3rem;text-decoration:none;" data-testid="button-modal-pdf">
          <i class="bi bi-file-earmark-pdf"></i>Atsisiųsti PDF
        </a>
      </div>
      <button type="button" onclick="document.getElementById('modalView').style.display='none'" style="padding:0.4rem 1rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.88rem;">Uždaryti</button>
    </div>
  </div>
</div>

<div id="modalEmail" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center;overflow-y:auto;">
  <div style="background:white;border-radius:12px;width:95%;max-width:500px;">
    <div style="background:linear-gradient(135deg,#27ae60 0%,#1e8449 100%);color:white;padding:1rem 1.5rem;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:1.1rem;"><i class="bi bi-envelope me-2"></i>Siųsti pretenziją el. paštu</h5>
      <button type="button" onclick="document.getElementById('modalEmail').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <form onsubmit="event.preventDefault(); sendEmail();">
      <div style="padding:1.5rem;">
        <input type="hidden" id="emailPretenzijaId">
        <div style="margin-bottom:1rem;">
          <label style="font-weight:600;display:block;margin-bottom:0.3rem;font-size:0.88rem;">Kam (el. paštas) <span style="color:#e74c3c;">*</span></label>
          <input type="email" id="emailTo" style="width:100%;padding:0.4rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="gavėjas@imone.lt" required data-testid="input-email-to">
        </div>
        <div style="margin-bottom:1rem;">
          <label style="font-weight:600;display:block;margin-bottom:0.3rem;font-size:0.88rem;">CC (kopija, neprivaloma)</label>
          <input type="text" id="emailCc" style="width:100%;padding:0.4rem 0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.88rem;" placeholder="kopija1@imone.lt, kopija2@imone.lt" data-testid="input-email-cc">
        </div>
        <div id="emailStatus" style="display:none;margin-bottom:1rem;padding:0.6rem 0.8rem;border-radius:6px;font-size:0.85rem;"></div>
      </div>
      <div style="padding:0.75rem 1.5rem;border-top:1px solid #dee2e6;display:flex;justify-content:flex-end;gap:0.5rem;">
        <button type="button" onclick="document.getElementById('modalEmail').style.display='none'" style="padding:0.4rem 1rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.88rem;">Atšaukti</button>
        <button type="submit" id="btnSendEmail" style="padding:0.4rem 1rem;border:none;border-radius:6px;background:#27ae60;color:white;cursor:pointer;font-weight:500;font-size:0.88rem;" data-testid="button-send-email">
          <i class="bi bi-send me-1"></i>Siųsti
        </button>
      </div>
    </form>
  </div>
</div>

<div id="modalEdit" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:flex-start;padding-top:2rem;overflow-y:auto;">
  <div style="background:white;border-radius:12px;width:95%;max-width:800px;margin-bottom:2rem;">
    <div style="background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%);color:white;padding:1rem 1.5rem;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:1.1rem;"><i class="bi bi-pencil me-2"></i>Redaguoti pretenziją</h5>
      <button type="button" onclick="document.getElementById('modalEdit').style.display='none'" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <div id="editContent" style="padding:1.5rem;"></div>
      <div style="padding:0.75rem 1.5rem;border-top:1px solid #dee2e6;display:flex;justify-content:flex-end;gap:0.5rem;">
        <button type="button" onclick="document.getElementById('modalEdit').style.display='none'" style="padding:0.4rem 1rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.88rem;">Atšaukti</button>
        <button type="submit" style="padding:0.4rem 1rem;border:none;border-radius:6px;background:#f39c12;color:white;cursor:pointer;font-weight:500;font-size:0.88rem;" data-testid="button-save-edit">
          <i class="bi bi-check-lg me-1"></i>Išsaugoti
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const pretenzijosData = <?= json_encode($pretenzijos) ?>;
const statusai = <?= json_encode($statusai) ?>;
const tipai = <?= json_encode($tipai) ?>;

function applyFilter(name, value) {
  const url = new URL(window.location);
  if (value) {
    url.searchParams.set(name, value);
  } else {
    url.searchParams.delete(name);
  }
  window.location = url;
}

function escH(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function escNl(s) { return s ? escH(s).replace(/\n/g, '<br>') : '-'; }

let currentViewId = null;

function copyPretLink() {
  if (!currentViewId) return;
  const url = window.location.origin + '/pretenzija_perziura.php?id=' + currentViewId;
  const btn = document.getElementById('btnCopyLink');
  const txt = document.getElementById('copyLinkText');
  
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(function() {
      btn.style.background = '#d4edda';
      btn.style.borderColor = '#27ae60';
      btn.style.color = '#27ae60';
      txt.textContent = 'Nukopijuota ✓';
      setTimeout(function() {
        btn.style.background = '#ebf5fb';
        btn.style.borderColor = '#2980b9';
        btn.style.color = '#2980b9';
        txt.textContent = 'Kopijuoti nuorodą';
      }, 2000);
    }).catch(function() {
      prompt('Nukopijuokite šią nuorodą:', url);
    });
  } else {
    prompt('Nukopijuokite šią nuorodą:', url);
  }
}

function viewPretenzija(id) {
  const p = pretenzijosData.find(x => x.id == id);
  if (!p) return;
  
  currentViewId = id;
  document.getElementById('btnViewPdf').href = 'pretenzijos_pdf.php?id=' + id;
  
  const st = statusai[p.statusas] || statusai['nauja'];
  
  let html = `
    <div style="margin-bottom:1rem;">
      <span class="badge-tipas ${p.tipas}" style="font-size:0.85rem;padding:0.4rem 0.8rem;">${tipai[p.tipas] || p.tipas}</span>
      <span class="badge-statusas" style="background:${st.bg};color:${st.color};font-size:0.85rem;padding:0.4rem 0.8rem;">${st.label}</span>
    </div>
    
    <table style="width:100%;border-collapse:collapse;margin-bottom:1rem;">
      <tr>
        <td style="width:40%;padding:0.5rem;border:1px solid #dee2e6;font-weight:600;">Problemos pastebėjimo vieta</td>
        <td style="width:30%;padding:0.5rem;border:1px solid #dee2e6;font-weight:600;">Gaminys</td>
        <td style="width:30%;padding:0.5rem;border:1px solid #dee2e6;font-weight:600;">Užsakymo Nr.</td>
      </tr>
      <tr>
        <td style="padding:0.5rem;border:1px solid #dee2e6;">${escH(p.aptikimo_vieta) || '-'}</td>
        <td style="padding:0.5rem;border:1px solid #dee2e6;">${escH(p.gaminys_info) || '-'}</td>
        <td style="padding:0.5rem;border:1px solid #dee2e6;">${escH(p.uzsakymo_numeris || p.uzsakymo_numeris_ranka) || '-'}</td>
      </tr>
    </table>
    
    <div style="margin-bottom:1rem;">
      <strong style="text-transform:uppercase;font-size:0.88rem;">Problemos aprašymas</strong>
      <div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;margin-top:0.3rem;">${escNl(p.aprasymas)}</div>
    </div>
    
    <div style="margin-bottom:1rem;">
      <strong style="text-transform:uppercase;font-size:0.88rem;">Padalinys atsakingas už sprendimą</strong>
      <div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;margin-top:0.3rem;">${escH(p.atsakingas_padalinys) || '-'}</div>
    </div>
    
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem;">
      <div>
        <strong style="text-transform:uppercase;font-size:0.88rem;">Siūlomas sprendimo būdas</strong>
        <div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;margin-top:0.3rem;">${escNl(p.siulomas_sprendimas)}</div>
      </div>
      <div>
        <strong style="text-transform:uppercase;font-size:0.88rem;">Terminas</strong>
        <div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;margin-top:0.3rem;">${escH(p.terminas) || '-'}</div>
      </div>
    </div>
    
    <div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;border:1px solid #dee2e6;margin-bottom:1rem;">
      <strong style="text-transform:uppercase;display:block;margin-bottom:0.5rem;font-size:0.88rem;">Problemą užfiksavo</strong>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;font-size:0.85rem;color:#6c757d;">
        <div><strong>Padalinys:</strong> ${escH(p.uzfiksavo_padalinys) || '-'}</div>
        <div><strong>Asmuo:</strong> ${escH(p.uzfiksavo_asmuo) || '-'}</div>
        <div><strong>Data:</strong> ${escH(p.gavimo_data) || '-'}</div>
      </div>
    </div>
    
    ${p.priezastis ? `<div style="margin-bottom:1rem;"><strong>Nustatyta priežastis</strong><div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;margin-top:0.3rem;">${escNl(p.priezastis)}</div></div>` : ''}
    ${p.veiksmai ? `<div style="margin-bottom:1rem;"><strong>Korekciniai veiksmai</strong><div style="background:#f8f9fa;padding:0.75rem;border-radius:6px;margin-top:0.3rem;">${escNl(p.veiksmai)}</div></div>` : ''}
    
    ${p.nuotraukos && p.nuotraukos.length > 0 ? `
      <div style="margin-bottom:1rem;">
        <strong style="text-transform:uppercase;"><i class="bi bi-images me-1"></i>Nuotraukos (${p.nuotraukos.length})</strong>
        <div class="photo-gallery" style="margin-top:0.5rem;">
          ${p.nuotraukos.map(n => `<img src="pretenzijos_nuotrauka.php?id=${parseInt(n.id)}" alt="${escH(n.pavadinimas) || 'Nuotrauka'}" onclick="window.open(this.src, '_blank')">`).join('')}
        </div>
      </div>
    ` : ''}

    ${p.defekto_pdf_pavadinimas ? `
      <div style="margin-bottom:1rem;background:#fdedec;padding:0.75rem;border-radius:6px;border:1px solid #f5c6cb;">
        <strong style="text-transform:uppercase;font-size:0.88rem;"><i class="bi bi-file-earmark-pdf me-1"></i>Defekto PDF</strong>
        <div style="margin-top:0.3rem;">
          <a href="pretenzija_defekto_pdf.php?id=${id}" target="_blank" style="color:#c0392b;font-weight:500;text-decoration:none;" data-testid="link-view-defekto-pdf">
            <i class="bi bi-download me-1"></i>${escH(p.defekto_pdf_pavadinimas)}
          </a>
        </div>
      </div>
    ` : ''}
    
    ${p.email_stats && p.email_stats.sent > 0 ? `
    <div style="border-top:1px solid #dee2e6;margin-top:1rem;padding-top:1rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
        <strong style="text-transform:uppercase;font-size:0.88rem;"><i class="bi bi-envelope-paper me-1"></i>El. pašto istorija</strong>
        <div style="font-size:0.8rem;">
          <span style="background:#ebf5fb;color:#2980b9;padding:0.2rem 0.5rem;border-radius:8px;margin-right:0.3rem;">Išsiųsta: ${p.email_stats.sent}</span>
          <span style="background:#d4edda;color:#155724;padding:0.2rem 0.5rem;border-radius:8px;margin-right:0.3rem;">Atsakyta: ${p.email_stats.answered}</span>
          ${p.email_stats.sent - p.email_stats.answered > 0 ? `<span style="background:#fff3cd;color:#856404;padding:0.2rem 0.5rem;border-radius:8px;">Laukiama: ${p.email_stats.sent - p.email_stats.answered}</span>` : ''}
        </div>
      </div>
      <div id="emailHistorySection">
        <div style="text-align:center;color:#6c757d;font-size:0.85rem;padding:0.5rem;"><div class="spinner-border spinner-border-sm me-2"></div>Kraunama istorija...</div>
      </div>
    </div>
    ` : '<div id="emailHistorySection"></div>'}
  `;
  
  document.getElementById('viewContent').innerHTML = html;
  document.getElementById('modalView').style.display = 'flex';
  
  fetch('pretenzijos.php?ajax=email_history&pretenzija_id=' + id)
    .then(r => r.json())
    .then(history => {
      const section = document.getElementById('emailHistorySection');
      if (!history || history.length === 0) {
        section.innerHTML = '';
        return;
      }
      let hhtml = '';
      history.forEach(h => {
        const hasF = !!h.feedback_text;
        const badge = hasF 
          ? '<span style="background:#d4edda;color:#155724;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;">Atsakyta</span>'
          : '<span style="background:#fff3cd;color:#856404;padding:0.15rem 0.5rem;border-radius:10px;font-size:0.72rem;font-weight:600;">Laukiama</span>';
        hhtml += '<div style="background:#f8f9fa;padding:0.6rem 0.8rem;border-radius:6px;margin-top:0.5rem;border:1px solid #e9ecef;font-size:0.85rem;">';
        hhtml += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem;">';
        hhtml += '<span><i class="bi bi-send me-1"></i><strong>' + escH(h.email_delegated_to) + '</strong></span>';
        hhtml += badge;
        hhtml += '</div>';
        hhtml += '<div style="color:#6c757d;font-size:0.8rem;">';
        hhtml += 'Siuntė: ' + escH(h.sent_by || '-') + ' | ' + (h.sent_at ? new Date(h.sent_at).toLocaleString('lt-LT') : '-');
        if (h.email_cc) hhtml += ' | CC: ' + escH(h.email_cc);
        hhtml += '</div>';
        if (hasF) {
          hhtml += '<div style="background:#e8f5e9;padding:0.5rem 0.6rem;border-radius:4px;margin-top:0.4rem;border-left:3px solid #27ae60;">';
          hhtml += '<div style="font-size:0.78rem;color:#6c757d;margin-bottom:0.2rem;">' + escH(h.feedback_by || 'Anonim.') + ' — ' + (h.feedback_at ? new Date(h.feedback_at).toLocaleString('lt-LT') : '') + '</div>';
          hhtml += '<div>' + escH(h.feedback_text).replace(/\n/g, '<br>') + '</div>';
          hhtml += '</div>';
        }
        hhtml += '</div>';
      });
      section.innerHTML = hhtml;
    })
    .catch(() => {
      const section = document.getElementById('emailHistorySection');
      if (section) section.innerHTML = '';
    });
}

function editPretenzija(id) {
  const p = pretenzijosData.find(x => x.id == id);
  if (!p) return;

  let statusOptions = '';
  for (const [k, v] of Object.entries(statusai)) {
    statusOptions += `<option value="${k}" ${p.statusas === k ? 'selected' : ''}>${v.label}</option>`;
  }

  const tipaiOpts = [
    ['vidine', 'Vidinė'],
    ['kliento', 'Kliento'],
    ['tiekejo', 'Tiekėjui']
  ].map(([k, l]) => `<option value="${k}" ${p.tipas === k ? 'selected' : ''}>${l}</option>`).join('');

  const esc = s => (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  let html = `
    <input type="hidden" name="veiksmas" value="atnaujinti">
    <input type="hidden" name="id" value="${id}">

    <div style="margin-bottom:1rem;padding:0.75rem;border-radius:8px;background:#f8f9fa;border-left:4px solid #0d6efd;">
      <div style="font-weight:700;text-transform:uppercase;font-size:0.78rem;color:#0d6efd;margin-bottom:0.75rem;"><i class="bi bi-info-circle me-1"></i>Pagrindinė informacija</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;">
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Tipas</label>
          <select name="tipas" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" data-testid="select-edit-tipas">${tipaiOpts}</select>
        </div>
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Gavimo data</label>
          <input type="date" name="gavimo_data" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.gavimo_data ? p.gavimo_data.substring(0,10) : '')}" data-testid="input-edit-gavimo-data">
        </div>
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Terminas</label>
          <input type="date" name="terminas" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.terminas ? p.terminas.substring(0,10) : '')}" data-testid="input-edit-terminas">
        </div>
      </div>
      <div style="margin-top:0.75rem;">
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Problemos aprašymas <span style="color:#e74c3c;">*</span></label>
        <textarea name="aprasymas" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;resize:vertical;" rows="3" placeholder="Aprašykite problemą..." required data-testid="input-edit-aprasymas">${esc(p.aprasymas)}</textarea>
      </div>
    </div>

    <div style="margin-bottom:1rem;padding:0.75rem;border-radius:8px;background:#f8f9fa;border-left:4px solid #6c757d;">
      <div style="font-weight:700;text-transform:uppercase;font-size:0.78rem;color:#6c757d;margin-bottom:0.75rem;"><i class="bi bi-geo-alt me-1"></i>Aptikimo ir gaminio informacija</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Aptikimo vieta</label>
          <input type="text" name="aptikimo_vieta" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.aptikimo_vieta)}" placeholder="Kur aptikta problema..." data-testid="input-edit-aptikimo-vieta">
        </div>
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Gaminys / informacija</label>
          <input type="text" name="gaminys_info" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.gaminys_info)}" placeholder="Gaminys arba detalė..." data-testid="input-edit-gaminys-info">
        </div>
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Užsakymo nr. (rankinis)</label>
          <input type="text" name="uzsakymo_numeris_ranka" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.uzsakymo_numeris_ranka)}" placeholder="pvz. UŽ-2024-001" data-testid="input-edit-uzsakymo-nr">
        </div>
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Atsakingas padalinys</label>
          <input type="text" name="atsakingas_padalinys" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.atsakingas_padalinys)}" placeholder="Padalinys..." data-testid="input-edit-atsakingas-padalinys">
        </div>
      </div>
      <div style="margin-top:0.75rem;">
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Siūlomas sprendimas</label>
        <textarea name="siulomas_sprendimas" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;resize:vertical;" rows="2" placeholder="Siūlomas sprendimas..." data-testid="input-edit-siulomas-sprendimas">${esc(p.siulomas_sprendimas)}</textarea>
      </div>
    </div>

    <div style="margin-bottom:1rem;padding:0.75rem;border-radius:8px;background:#f8f9fa;border-left:4px solid #198754;">
      <div style="font-weight:700;text-transform:uppercase;font-size:0.78rem;color:#198754;margin-bottom:0.75rem;"><i class="bi bi-person-check me-1"></i>Registravimo duomenys</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Užfiksavo padalinys</label>
          <input type="text" name="uzfiksavo_padalinys" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.uzfiksavo_padalinys)}" placeholder="Padalinys..." data-testid="input-edit-uzfiksavo-padalinys">
        </div>
        <div>
          <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Užfiksavo asmuo</label>
          <input type="text" name="uzfiksavo_asmuo" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.uzfiksavo_asmuo)}" placeholder="Vardas Pavardė..." data-testid="input-edit-uzfiksavo-asmuo">
        </div>
      </div>
    </div>

    <div style="margin-bottom:1rem;padding:0.75rem;border-radius:8px;background:#f8f9fa;border-left:4px solid #dc3545;">
      <div style="font-weight:700;text-transform:uppercase;font-size:0.78rem;color:#dc3545;margin-bottom:0.75rem;"><i class="bi bi-search me-1"></i>Tyrimas ir sprendimas</div>
      <div style="margin-bottom:0.5rem;">
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Statusas</label>
        <select name="statusas" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" onchange="this.form.querySelector('[name=veiksmas]').value='atnaujinti_statusa';this.form.submit();" data-testid="select-edit-status">
          ${statusOptions}
        </select>
        <small style="color:#6c757d;font-size:0.78rem;">Pakeitus statusą forma bus automatiškai išsaugota</small>
      </div>
      <div style="margin-bottom:0.5rem;">
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Nustatyta priežastis</label>
        <textarea name="priezastis" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;resize:vertical;" rows="3" placeholder="Nustatyta priežastis..." data-testid="input-edit-priezastis">${esc(p.priezastis)}</textarea>
      </div>
      <div style="margin-bottom:0.5rem;">
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Korekciniai veiksmai</label>
        <textarea name="veiksmai" style="width:100%;padding:0.4rem 0.6rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;resize:vertical;" rows="3" placeholder="Kokie veiksmai bus/buvo atlikti..." data-testid="input-edit-veiksmai">${esc(p.veiksmai)}</textarea>
      </div>
      <div>
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">Atsakingas asmuo</label>
        <input type="text" name="atsakingas_asmuo" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;" value="${esc(p.atsakingas_asmuo)}" placeholder="Vardas Pavardė..." data-testid="input-edit-atsakingas">
      </div>
    </div>

    <div style="margin-bottom:1rem;padding:0.75rem;border-radius:8px;background:#f8f9fa;border-left:4px solid #e74c3c;">
      <div style="font-weight:700;text-transform:uppercase;font-size:0.78rem;color:#e74c3c;margin-bottom:0.75rem;"><i class="bi bi-file-earmark-pdf me-1"></i>Defekto PDF</div>
      ${p.defekto_pdf_pavadinimas ? `<div style="margin-bottom:0.5rem;"><a href="pretenzija_defekto_pdf.php?id=${id}" target="_blank" style="color:#e74c3c;font-weight:500;text-decoration:none;" data-testid="link-edit-pdf-download"><i class="bi bi-file-earmark-pdf me-1"></i>${esc(p.defekto_pdf_pavadinimas)}</a></div>` : ''}
      <div>
        <label style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.82rem;">${p.defekto_pdf_pavadinimas ? 'Pakeisti PDF failą' : 'Įkelti PDF failą'}</label>
        <input type="file" name="defekto_pdf" accept="application/pdf,.pdf" style="width:100%;padding:0.35rem 0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.85rem;background:white;" data-testid="input-edit-defekto-pdf">
        <small style="color:#6c757d;font-size:0.78rem;">Tik PDF failai priimami</small>
      </div>
    </div>
  `;

  document.getElementById('editContent').innerHTML = html;
  document.getElementById('modalEdit').style.display = 'flex';
}

let compressedFiles = [];

document.getElementById('photoInput').addEventListener('change', async function(e) {
  const preview = document.getElementById('photoPreview');
  const placeholder = document.querySelector('.upload-placeholder');
  const originalPlaceholderHTML = placeholder.innerHTML;
  
  if (this.files.length > 0) {
    placeholder.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div><span style="font-size:0.95rem;">Apdorojama...</span>';
    placeholder.style.display = 'flex';
    placeholder.style.alignItems = 'center';
    placeholder.style.justifyContent = 'center';
    
    compressedFiles = [];
    preview.innerHTML = '';
    
    for (let i = 0; i < this.files.length; i++) {
      const file = this.files[i];
      if (!file.type.startsWith('image/')) continue;
      
      try {
        const compressed = await compressImage(file, 1200, 0.8);
        compressedFiles.push(compressed);
        
        const item = document.createElement('div');
        item.className = 'photo-preview-item';
        item.innerHTML = `
          <img src="${URL.createObjectURL(compressed)}" alt="Nuotrauka">
          <button type="button" class="remove-btn" onclick="removeCompressedPhoto(${compressedFiles.length - 1})">&times;</button>
        `;
        preview.appendChild(item);
      } catch (err) {
        console.error('Klaida suspaudžiant:', err);
      }
    }
    
    placeholder.innerHTML = originalPlaceholderHTML;
    placeholder.style.display = 'none';
    placeholder.style.alignItems = '';
    placeholder.style.justifyContent = '';
    
    updateFileInput();
  }
});

function compressImage(file, maxSize, quality) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = new Image();
      img.onload = function() {
        const canvas = document.createElement('canvas');
        let width = img.width;
        let height = img.height;
        
        if (width > maxSize || height > maxSize) {
          if (width > height) {
            height = Math.round(height * maxSize / width);
            width = maxSize;
          } else {
            width = Math.round(width * maxSize / height);
            height = maxSize;
          }
        }
        
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        
        canvas.toBlob(blob => {
          resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
        }, 'image/jpeg', quality);
      };
      img.onerror = reject;
      img.src = e.target.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

function removeCompressedPhoto(index) {
  compressedFiles.splice(index, 1);
  updateFileInput();
  renderPreviews();
}

function renderPreviews() {
  const preview = document.getElementById('photoPreview');
  preview.innerHTML = '';
  
  if (compressedFiles.length === 0) {
    document.querySelector('.upload-placeholder').style.display = 'block';
    return;
  }
  
  compressedFiles.forEach((file, i) => {
    const item = document.createElement('div');
    item.className = 'photo-preview-item';
    item.innerHTML = `
      <img src="${URL.createObjectURL(file)}" alt="Nuotrauka">
      <button type="button" class="remove-btn" onclick="removeCompressedPhoto(${i})">&times;</button>
    `;
    preview.appendChild(item);
  });
}

function updateFileInput() {
  const dt = new DataTransfer();
  compressedFiles.forEach(f => dt.items.add(f));
  document.getElementById('photoInput').files = dt.files;
}

function toggleCustomField(selectEl, customFieldId) {
  const customField = document.getElementById(customFieldId);
  if (selectEl.value === '__kita__') {
    customField.style.display = 'block';
    customField.focus();
  } else {
    customField.style.display = 'none';
    customField.value = '';
  }
}

function toggleUzsakymoField(selectEl) {
  const customField = document.getElementById('uzsakymo_numeris_ranka');
  const hiddenField = document.getElementById('uzsakymo_id_final');
  if (selectEl.value === '__kita__') {
    customField.style.display = 'block';
    customField.focus();
    hiddenField.value = '';
  } else {
    customField.style.display = 'none';
    customField.value = '';
    hiddenField.value = selectEl.value;
  }
}

document.querySelector('#modalKurti form').addEventListener('submit', function(e) {
  const aptikimoSelect = this.querySelector('[name="aptikimo_vieta_select"]');
  const aptikimoCustom = this.querySelector('[name="aptikimo_vieta_custom"]');
  const aptikimoFinal = this.querySelector('[name="aptikimo_vieta"]');
  
  if (aptikimoSelect.value === '__kita__') {
    aptikimoFinal.value = aptikimoCustom.value;
  } else {
    aptikimoFinal.value = aptikimoSelect.value;
  }
  
  const gaminysSelect = this.querySelector('[name="gaminys_info_select"]');
  const gaminysCustom = this.querySelector('[name="gaminys_info_custom"]');
  const gaminysFinal = this.querySelector('[name="gaminys_info"]');
  
  if (gaminysSelect.value === '__kita__') {
    gaminysFinal.value = gaminysCustom.value;
  } else {
    gaminysFinal.value = gaminysSelect.value;
  }
  
  const uzsakymoSelect = this.querySelector('[name="uzsakymo_id_select"]');
  const uzsakymoFinal = this.querySelector('[name="uzsakymo_id"]');
  
  if (uzsakymoSelect.value && uzsakymoSelect.value !== '__kita__') {
    uzsakymoFinal.value = uzsakymoSelect.value;
  } else {
    uzsakymoFinal.value = '';
  }
});

function openEmailModal(id) {
  document.getElementById('emailPretenzijaId').value = id;
  document.getElementById('emailTo').value = '';
  document.getElementById('emailCc').value = '';
  const status = document.getElementById('emailStatus');
  status.style.display = 'none';
  status.innerHTML = '';
  document.getElementById('btnSendEmail').disabled = false;
  document.getElementById('modalEmail').style.display = 'flex';
}

function sendEmail() {
  const pid = document.getElementById('emailPretenzijaId').value;
  const emailTo = document.getElementById('emailTo').value.trim();
  const emailCc = document.getElementById('emailCc').value.trim();
  const status = document.getElementById('emailStatus');
  const btn = document.getElementById('btnSendEmail');
  
  if (!emailTo) {
    status.style.display = 'block';
    status.style.background = '#fdedec';
    status.style.color = '#721c24';
    status.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Įveskite gavėjo el. pašto adresą';
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Siunčiama...';
  status.style.display = 'none';
  
  const fd = new FormData();
  fd.append('pretenzija_id', pid);
  fd.append('email_delegated_to', emailTo);
  fd.append('email_cc', emailCc);
  
  fetch('pretenzijos_siusti.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      status.style.display = 'block';
      if (data.success) {
        status.style.background = '#d4edda';
        status.style.color = '#155724';
        status.innerHTML = '<i class="bi bi-check-circle me-1"></i>' + data.message;
        setTimeout(() => { document.getElementById('modalEmail').style.display = 'none'; }, 2000);
      } else {
        status.style.background = '#fdedec';
        status.style.color = '#721c24';
        status.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + data.message;
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i>Siųsti';
    })
    .catch(err => {
      status.style.display = 'block';
      status.style.background = '#fdedec';
      status.style.color = '#721c24';
      status.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Klaida siunčiant';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i>Siųsti';
    });
}

document.querySelectorAll('#modalKurti, #modalView, #modalEdit, #modalEmail').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
  });
});

</script>

<?php if (currentUser()['role'] === 'admin'): ?>
<div class="modal-overlay" id="deletePretModal" data-testid="modal-delete-pretenzija">
    <div class="modal" style="max-width: 420px;">
        <div class="modal-header" style="background: #fef2f2; border-bottom: 2px solid #fecaca;">
            <h3 style="color: #dc2626;">Pretenzijos trynimas</h3>
            <button class="modal-close" onclick="closeModal('deletePretModal')" aria-label="Uždaryti">&times;</button>
        </div>
        <form method="POST" id="deletePretForm">
            <input type="hidden" name="veiksmas" value="trinti">
            <input type="hidden" name="id" id="deletePretId">
            <input type="hidden" name="trynimo_patvirtinimas" id="deletePretConfirmVal" value="">
            <div class="modal-body">
                <div class="delete-warning">
                    <div class="delete-warning-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <p style="font-weight: 600; font-size: 15px; margin-bottom: 8px;">Šis veiksmas negrįžtamas!</p>
                    <p style="color: var(--text-secondary); font-size: 13px;">
                        Ar tikrai norite ištrinti pretenziją <strong id="deletePretNrDisplay"></strong>?
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deletePretModal')">Atšaukti</button>
                <button type="submit" class="btn btn-danger" data-testid="button-confirm-delete-pret">Ištrinti</button>
            </div>
        </form>
    </div>
</div>
<script>
function atidarytiPretenzijosTrinyma(id, nr) {
    document.getElementById('deletePretId').value = id;
    document.getElementById('deletePretNrDisplay').textContent = 'Nr. ' + nr;
    document.getElementById('deletePretConfirmVal').value = 'TAIP';
    openModal('deletePretModal');
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
