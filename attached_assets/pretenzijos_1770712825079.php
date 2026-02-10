<?php
/**
 * Pretenzijų ir reklamacijų valdymo modulis
 * 
 * Šis failas atsakingas už pretenzijų (vidinių, klientų ir tiekėjų) registravimą,
 * redagavimą, peržiūrą ir trynimą. Sistema leidžia pridėti nuotraukas prie pretenzijų,
 * generuoti PDF dokumentus, siųsti el. paštu ir sekti pretenzijų statusus.
 * 
 * Pagrindinės funkcijos:
 * - Naujos pretenzijos sukūrimas (PR 28/2 forma)
 * - Pretenzijos statuso keitimas (nauja, tiriama, vykdoma, užbaigta, atmesta)
 * - Pretenzijos informacijos atnaujinimas (priežastis, veiksmai, atsakingas asmuo)
 * - Pretenzijos trynimas
 * - Nuotraukų įkėlimas ir saugojimas duomenų bazėje
 * - Pretenzijų filtravimas pagal tipą ir statusą
 * - Statistikos atvaizdavimas
 * 
 * @package KokybesValdymoSistema
 * @author ELGAK
 */

session_start();
require_once 'db.php';
require_once 'klases/Sesija.php';

/**
 * Atnaujina vartotojo sesijos veiklos laiką
 */
Sesija::atnaujintiVeikla($pdo);

/**
 * Patikrina ar vartotojas prisijungęs
 * 
 * Jei vartotojas neprisijungęs, nukreipia į prisijungimo puslapį.
 */
$prisijunges = (isset($_SESSION['vardas'], $_SESSION['pavarde']))
  ? trim($_SESSION['vardas'].' '.$_SESSION['pavarde'])
  : null;

if (!$prisijunges) {
    header('Location: prisijungimas.php');
    exit;
}

/**
 * Skaitytojas rolė - tik peržiūra, be redagavimo teisių
 */
$arSkaitytojas = Sesija::arSkaitytojas();

/**
 * Pretenzijų tipų sąrašas
 * 
 * Galimi tipai: vidinė (gamybos procese), kliento (iš kliento gauta),
 * tiekėjo (dėl tiekėjo medžiagų/paslaugų).
 */
$tipai = [
    'vidine' => 'Vidinė pretenzija',
    'kliento' => 'Kliento pretenzija',
    'tiekejo' => 'Tiekėjo pretenzija'
];

/**
 * Pretenzijų statusų konfigūracija
 * 
 * Kiekvienas statusas turi pavadinimą (label), teksto spalvą (color)
 * ir fono spalvą (bg) atvaizdavimui vartotojo sąsajoje.
 */
$statusai = [
    'nauja' => ['label' => 'Nauja', 'color' => '#3498db', 'bg' => '#ebf5fb'],
    'tyrimas' => ['label' => 'Tiriama', 'color' => '#f39c12', 'bg' => '#fef9e7'],
    'vykdoma' => ['label' => 'Vykdoma', 'color' => '#9b59b6', 'bg' => '#f5eef8'],
    'uzbaigta' => ['label' => 'Užbaigta', 'color' => '#27ae60', 'bg' => '#eafaf1'],
    'atmesta' => ['label' => 'Atmesta', 'color' => '#95a5a6', 'bg' => '#f4f6f6']
];

/**
 * Kintamieji pranešimams apie klaidas ir sėkmingus veiksmus
 */
$klaida = '';
$sekminga = '';

/**
 * POST užklausų apdorojimas
 * 
 * Apdoroja formų pateikimus: naujos pretenzijos kūrimą, statuso atnaujinimą,
 * pretenzijos redagavimą ir trynimą.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $veiksmas = $_POST['veiksmas'] ?? '';
    
    /**
     * Naujos pretenzijos sukūrimas
     * 
     * Sukuria naują pretenzijos įrašą duomenų bazėje pagal PR 28/2 formą.
     * Įrašomi visi pretenzijos duomenys: tipas, aprašymas, aptikimo vieta,
     * gaminys, atsakingas padalinys, siūlomas sprendimas ir kt.
     * Jei pridėtos nuotraukos, jos taip pat išsaugomos duomenų bazėje.
     */
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
                /**
                 * Pretenzijos įrašymas į duomenų bazę
                 * 
                 * Visi laukai išsaugomi pretenzijos lentelėje, įskaitant
                 * sukūrimo datą ir sukūrusio asmens vardą.
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO pretenzijos (
                        tipas, aprasymas, uzsakymo_id, uzsakymo_numeris_ranka, terminas, gavimo_data, sukure_vardas,
                        aptikimo_vieta, gaminys_info, atsakingas_padalinys, siulomas_sprendimas,
                        uzfiksavo_padalinys, uzfiksavo_asmuo
                    ) VALUES (
                        :tipas, :aprasymas, :uzsakymo_id, :uzsakymo_numeris_ranka, :terminas, :gavimo_data, :sukure,
                        :aptikimo_vieta, :gaminys_info, :atsakingas_padalinys, :siulomas_sprendimas,
                        :uzfiksavo_padalinys, :uzfiksavo_asmuo
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
                    ':uzfiksavo_asmuo' => $uzfiksavo_asmuo ?: null
                ]);
                
                $pretenzijaId = $pdo->lastInsertId();
                
                /**
                 * Nuotraukų įkėlimas ir išsaugojimas
                 * 
                 * Apdoroja įkeltas nuotraukas ir išsaugo jas duomenų bazėje
                 * kaip BLOB duomenis. Kiekviena nuotrauka susiejama su pretenzijos ID.
                 * Saugomi duomenys: failo pavadinimas, MIME tipas ir binarinis turinys.
                 */
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
                
                $sekminga = 'Pretenzija sėkmingai užregistruota';
            } catch (PDOException $e) {
                $klaida = 'Klaida kuriant pretenziją: ' . $e->getMessage();
            }
        }
    }
    
    /**
     * Pretenzijos statuso atnaujinimas
     * 
     * Keičia pretenzijos statusą (nauja, tiriama, vykdoma, užbaigta, atmesta).
     * Jei statusas pakeičiamas į 'užbaigta' arba 'atmesta', automatiškai
     * užpildoma užbaigimo data.
     */
    if ($veiksmas === 'atnaujinti_statusa') {
        $id = (int)($_POST['id'] ?? 0);
        $statusas = $_POST['statusas'] ?? '';
        
        if ($id > 0 && isset($statusai[$statusas])) {
            $uzbaigimo = ($statusas === 'uzbaigta' || $statusas === 'atmesta') ? 'CURRENT_DATE' : 'NULL';
            $stmt = $pdo->prepare("UPDATE pretenzijos SET statusas = :statusas, uzbaigimo_data = $uzbaigimo, atnaujinta = NOW() WHERE id = :id");
            $stmt->execute([':statusas' => $statusas, ':id' => $id]);
            $sekminga = 'Statusas atnaujintas';
        }
    }
    
    /**
     * Pretenzijos informacijos atnaujinimas
     * 
     * Leidžia atnaujinti pretenzijos tyrimo rezultatus: nustatytą priežastį,
     * korekcinius veiksmus ir atsakingą asmenį. Naudojama tyrimo metu
     * dokumentuojant atliktą analizę ir priimtus sprendimus.
     */
    if ($veiksmas === 'atnaujinti') {
        $id = (int)($_POST['id'] ?? 0);
        $priezastis = trim($_POST['priezastis'] ?? '');
        $veiksmai_txt = trim($_POST['veiksmai'] ?? '');
        $atsakingas = trim($_POST['atsakingas_asmuo'] ?? '');
        
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE pretenzijos 
                SET priezastis = :priezastis, veiksmai = :veiksmai, atsakingas_asmuo = :atsakingas, atnaujinta = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':priezastis' => $priezastis ?: null,
                ':veiksmai' => $veiksmai_txt ?: null,
                ':atsakingas' => $atsakingas ?: null,
                ':id' => $id
            ]);
            $sekminga = 'Pretenzija atnaujinta';
        }
    }
    
    /**
     * Pretenzijos ištrynimas
     * 
     * Visiškai pašalina pretenzijos įrašą iš duomenų bazės.
     * Kartu su pretenzija ištrinamos ir visos susietos nuotraukos
     * (per CASCADE ryšį duomenų bazėje).
     */
    if ($veiksmas === 'trinti') {
        if ($arSkaitytojas) {
            $klaida = 'Neturite teisių trinti pretenzijų';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM pretenzijos WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $sekminga = 'Pretenzija ištrinta';
            }
        }
    }
}

/**
 * Pretenzijų sąrašo filtravimas
 * 
 * Nuskaito GET parametrus filtravimui pagal pretenzijos tipą ir statusą.
 * Galima filtruoti pagal abu parametrus vienu metu.
 */
$filtras_tipas = $_GET['tipas'] ?? '';
$filtras_statusas = $_GET['statusas'] ?? '';

/**
 * Filtravimo sąlygų formavimas
 * 
 * Dinamiškai formuojamos WHERE sąlygos pagal pasirinktus filtrus.
 * Naudojami parametrizuoti užklausų kintamieji SQL injekcijos prevencijai.
 */
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

/**
 * Pretenzijų sąrašo gavimas iš duomenų bazės
 * 
 * Gaunamas visų pretenzijų sąrašas su susietais užsakymo numeriais.
 * Rezultatai rikiuojami pagal statusą (prioritetas: nauja > tiriama > vykdoma > kitos)
 * ir pagal sukūrimo datą (naujausi viršuje).
 * 
 * PDF generavimas atliekamas atskirame faile pretenzijos_pdf.php,
 * kuris naudoja mPDF biblioteką dokumentų kūrimui.
 */
$sql = "
    SELECT p.*, 
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

/**
 * Statistikos/suvestinės skaičiavimas
 * 
 * Skaičiuojama bendra pretenzijų statistika:
 * - viso: bendras pretenzijų skaičius (po filtravimo)
 * - naujos: pretenzijos su statusu 'nauja'
 * - aktyvios: pretenzijos, kurios dar nėra užbaigtos (nauja, tiriama, vykdoma)
 * - uzbaigtos: pretenzijos su statusu 'užbaigta'
 * 
 * Ši statistika rodoma puslapio viršuje kaip suvestinės kortelės.
 */
$stats = [
    'viso' => count($pretenzijos),
    'naujos' => 0,
    'aktyvios' => 0,
    'uzbaigtos' => 0
];
foreach ($pretenzijos as $p) {
    if ($p['statusas'] === 'nauja') $stats['naujos']++;
    if (in_array($p['statusas'], ['nauja', 'tyrimas', 'vykdoma'])) $stats['aktyvios']++;
    if ($p['statusas'] === 'uzbaigta') $stats['uzbaigtos']++;
}

/**
 * Užsakymų sąrašas formos pasirinkimui
 * 
 * Gaunami 100 naujausių užsakymų numerių, kurie bus rodomi
 * išskleidžiamajame sąraše kuriant naują pretenziją.
 */
$uzsakymai_opts = $pdo->query("SELECT id, uzsakymo_numeris FROM uzsakymai ORDER BY uzsakymo_numeris DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Nuotraukų susiejimas su pretenzijomis
 * 
 * Sukuriamas nuotraukų žemėlapis (map), kur raktas yra pretenzijos ID,
 * o reikšmė - nuotraukų masyvas. Tai leidžia efektyviai pridėti nuotraukų
 * informaciją prie kiekvienos pretenzijos be papildomų užklausų.
 * Nuotraukų turinys (BLOB) čia nekraunamas - tik ID ir pavadinimai.
 */
$nuotraukos_map = [];
$nuotraukosStmt = $pdo->query("SELECT id, pretenzija_id, pavadinimas FROM pretenzijos_nuotraukos ORDER BY id");
while ($row = $nuotraukosStmt->fetch(PDO::FETCH_ASSOC)) {
    $pid = $row['pretenzija_id'];
    if (!isset($nuotraukos_map[$pid])) $nuotraukos_map[$pid] = [];
    $nuotraukos_map[$pid][] = ['id' => $row['id'], 'pavadinimas' => $row['pavadinimas']];
}

/**
 * Nuotraukų priskyrimas pretenzijoms
 * 
 * Kiekvienai pretenzijai pridedamas nuotraukų masyvas iš sukurto žemėlapio.
 */
foreach ($pretenzijos as &$p) {
    $p['nuotraukos'] = $nuotraukos_map[$p['id']] ?? [];
}
unset($p);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pretenzijos - Kokybės valdymo sistema</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --primary: #e74c3c;
      --primary-dark: #c0392b;
    }
    
    body {
      background: #f5f7fa;
      min-height: 100vh;
    }
    
    .page-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 1.5rem 0;
      margin-bottom: 2rem;
    }
    
    .page-header h1 {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 1.25rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid;
    }
    
    .stat-card.viso { border-color: #3498db; }
    .stat-card.naujos { border-color: #e74c3c; }
    .stat-card.aktyvios { border-color: #f39c12; }
    .stat-card.uzbaigtos { border-color: #27ae60; }
    
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      line-height: 1;
    }
    
    .stat-card.viso .stat-value { color: #3498db; }
    .stat-card.naujos .stat-value { color: #e74c3c; }
    .stat-card.aktyvios .stat-value { color: #f39c12; }
    .stat-card.uzbaigtos .stat-value { color: #27ae60; }
    
    .stat-label {
      font-size: 0.85rem;
      color: #7f8c8d;
      margin-top: 0.5rem;
    }
    
    .card-main {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 15px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    
    .card-header-custom {
      background: #f8f9fa;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
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
      font-size: 0.9rem;
    }
    
    .btn-add {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-add:hover {
      background: var(--primary-dark);
      color: white;
    }
    
    .pretenzija-item {
      padding: 1.25rem 1.5rem;
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
      margin-bottom: 0.75rem;
    }
    
    .pretenzija-id {
      font-weight: 700;
      color: var(--primary);
      font-size: 1rem;
    }
    
    .pretenzija-meta {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    
    .badge-tipas {
      padding: 0.25rem 0.6rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .badge-tipas.vidine { background: #ebf5fb; color: #2980b9; }
    .badge-tipas.kliento { background: #fef9e7; color: #b7950b; }
    .badge-tipas.tiekejo { background: #f5eef8; color: #8e44ad; }
    
    .badge-statusas {
      padding: 0.25rem 0.6rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .pretenzija-desc {
      color: #2c3e50;
      margin-bottom: 0.75rem;
      line-height: 1.5;
    }
    
    .pretenzija-info {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
      font-size: 0.85rem;
      color: #7f8c8d;
    }
    
    .pretenzija-info i {
      margin-right: 0.25rem;
    }
    
    .pretenzija-actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .btn-action {
      padding: 0.35rem 0.6rem;
      border-radius: 6px;
      font-size: 0.8rem;
      border: none;
      cursor: pointer;
    }
    
    .btn-action.view { background: #ebf5fb; color: #2980b9; }
    .btn-action.pdf { background: #fdedec; color: #c0392b; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-action.email { background: #e8f5e9; color: #27ae60; }
    .btn-action.edit { background: #fef9e7; color: #b7950b; }
    .btn-action.delete { background: #fdedec; color: #c0392b; }
    
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: #7f8c8d;
    }
    
    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }
    
    .modal-header-custom {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
</head>
<body>

<div class="page-header">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h1><i class="bi bi-exclamation-triangle-fill me-2"></i>Pretenzijos</h1>
      <a href="pagrindinis.php" class="btn btn-light btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Grįžti
      </a>
    </div>
  </div>
</div>

<div class="container">
  
  <?php if ($klaida): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3">
      <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($klaida) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if ($sekminga): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3">
      <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($sekminga) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card viso">
      <div class="stat-value"><?= $stats['viso'] ?></div>
      <div class="stat-label">Viso pretenzijų</div>
    </div>
    <div class="stat-card naujos">
      <div class="stat-value"><?= $stats['naujos'] ?></div>
      <div class="stat-label">Naujos</div>
    </div>
    <div class="stat-card aktyvios">
      <div class="stat-value"><?= $stats['aktyvios'] ?></div>
      <div class="stat-label">Aktyvios</div>
    </div>
    <div class="stat-card uzbaigtos">
      <div class="stat-value"><?= $stats['uzbaigtos'] ?></div>
      <div class="stat-label">Užbaigtos</div>
    </div>
  </div>

  <div class="card-main">
    <div class="card-header-custom">
      <div class="filters">
        <select onchange="applyFilter('tipas', this.value)">
          <option value="">Visi tipai</option>
          <?php foreach ($tipai as $k => $v): ?>
            <option value="<?= $k ?>" <?= $filtras_tipas === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <select onchange="applyFilter('statusas', this.value)">
          <option value="">Visi statusai</option>
          <?php foreach ($statusai as $k => $s): ?>
            <option value="<?= $k ?>" <?= $filtras_statusas === $k ? 'selected' : '' ?>><?= $s['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn-add" data-bs-toggle="modal" data-bs-target="#modalKurti">
        <i class="bi bi-plus-lg"></i>Nauja pretenzija
      </button>
    </div>
    
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
        <div class="pretenzija-item">
          <div class="pretenzija-header">
            <div>
              <span class="pretenzija-id">#<?= $p['id'] ?></span>
              <span class="badge-tipas <?= $p['tipas'] ?>"><?= $tipai[$p['tipas']] ?? $p['tipas'] ?></span>
              <span class="badge-statusas" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>"><?= $st['label'] ?></span>
            </div>
            <div class="pretenzija-actions">
              <button class="btn-action view" onclick="viewPretenzija(<?= $p['id'] ?>)" title="Peržiūrėti">
                <i class="bi bi-eye"></i>
              </button>
              <a href="pretenzijos_pdf.php?id=<?= $p['id'] ?>" target="_blank" class="btn-action pdf" title="PDF">
                <i class="bi bi-file-pdf"></i>
              </a>
              <button class="btn-action email" onclick="openEmailModal(<?= $p['id'] ?>)" title="Siųsti el. paštu">
                <i class="bi bi-envelope"></i>
              </button>
              <button class="btn-action edit" onclick="editPretenzija(<?= $p['id'] ?>)" title="Redaguoti">
                <i class="bi bi-pencil"></i>
              </button>
              <?php if (!$arSkaitytojas): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Ar tikrai norite ištrinti?')">
                <input type="hidden" name="veiksmas" value="trinti">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn-action delete" title="Trinti">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="pretenzija-desc">
            <?= nl2br(htmlspecialchars(mb_substr($p['aprasymas'], 0, 200))) ?><?= mb_strlen($p['aprasymas']) > 200 ? '...' : '' ?>
            <?php if (!empty($p['nuotraukos'])): ?>
              <span class="badge bg-secondary ms-2"><i class="bi bi-camera-fill me-1"></i><?= count($p['nuotraukos']) ?></span>
            <?php endif; ?>
          </div>
          
          <div class="pretenzija-info">
            <span><i class="bi bi-calendar3"></i><?= date('Y-m-d', strtotime($p['gavimo_data'])) ?></span>
            <?php if (!empty($p['aptikimo_vieta'])): ?>
              <span><i class="bi bi-geo-alt"></i><?= htmlspecialchars($p['aptikimo_vieta']) ?></span>
            <?php endif; ?>
            <?php 
            $uzsakymo_nr_display = $p['uzsakymo_numeris'] ?? $p['uzsakymo_numeris_ranka'] ?? null;
            if ($uzsakymo_nr_display): ?>
              <span><i class="bi bi-box"></i>Užs. <?= htmlspecialchars($uzsakymo_nr_display) ?></span>
            <?php endif; ?>
            <?php if (!empty($p['gaminys_info'])): ?>
              <span><i class="bi bi-cpu"></i><?= htmlspecialchars($p['gaminys_info']) ?></span>
            <?php endif; ?>
            <?php if (!empty($p['atsakingas_padalinys'])): ?>
              <span><i class="bi bi-building"></i><?= htmlspecialchars($p['atsakingas_padalinys']) ?></span>
            <?php endif; ?>
            <?php if ($p['terminas']): ?>
              <span><i class="bi bi-clock"></i>Terminas: <?= date('Y-m-d', strtotime($p['terminas'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="modalKurti" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>PRETENZIJA (PR 28/2 forma)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="veiksmas" value="kurti">
          
          <div class="form-section mb-4">
            <div class="row">
              <div class="col-md-5">
                <label class="form-label fw-bold">Problemos pastebėjimo (aptikimo) vieta</label>
                <select name="aptikimo_vieta_select" class="form-select mb-2" onchange="toggleCustomField(this, 'aptikimo_vieta_custom')">
                  <option value="">-- Pasirinkti --</option>
                  <option value="USN baras">USN baras</option>
                  <option value="Dėžių baras">Dėžių baras</option>
                  <option value="MT baras">MT baras</option>
                  <option value="KAMP baras">KAMP baras</option>
                  <option value="Paruošimas">Paruošimas</option>
                  <option value="Objekte">Objekte</option>
                  <option value="__kita__">Kita (įvesti)...</option>
                </select>
                <input type="text" name="aptikimo_vieta_custom" id="aptikimo_vieta_custom" class="form-control d-none" placeholder="Įveskite kitą vietą...">
                <input type="hidden" name="aptikimo_vieta" id="aptikimo_vieta_final">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Gaminys</label>
                <select name="gaminys_info_select" class="form-select mb-2" onchange="toggleCustomField(this, 'gaminys_info_custom')">
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
                <input type="text" name="gaminys_info_custom" id="gaminys_info_custom" class="form-control d-none" placeholder="Įveskite kitą gaminį...">
                <input type="hidden" name="gaminys_info" id="gaminys_info_final">
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">Užsakymo Nr.</label>
                <select name="uzsakymo_id_select" class="form-select mb-2" onchange="toggleUzsakymoField(this)">
                  <option value="">-- Pasirinkti --</option>
                  <?php foreach ($uzsakymai_opts as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['uzsakymo_numeris']) ?></option>
                  <?php endforeach; ?>
                  <option value="__kita__">Kita (įvesti rankiniu būdu)...</option>
                </select>
                <input type="text" name="uzsakymo_numeris_ranka" id="uzsakymo_numeris_ranka" class="form-control d-none" placeholder="Įveskite užsakymo numerį...">
                <input type="hidden" name="uzsakymo_id" id="uzsakymo_id_final">
              </div>
            </div>
          </div>
          
          <div class="form-section mb-4">
            <label class="form-label fw-bold text-uppercase">Problemos aprašymas <span class="text-danger">*</span></label>
            <textarea name="aprasymas" class="form-control" rows="4" required placeholder="Išsamiai aprašykite nustatytą problemą..."></textarea>
            
            <div class="mt-3">
              <label class="form-label fw-bold"><i class="bi bi-camera me-1"></i>Nuotraukos (neprivaloma)</label>
              <div class="photo-upload-area" id="photoUploadArea">
                <input type="file" name="nuotraukos[]" id="photoInput" multiple accept="image/*" capture="environment" class="d-none">
                <div class="upload-placeholder" onclick="document.getElementById('photoInput').click()">
                  <i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:#6c757d;"></i>
                  <div class="mt-2">Paspauskite norėdami įkelti nuotraukas</div>
                  <small class="text-muted">arba nufotografuokite telefonu</small>
                </div>
                <div id="photoPreview" class="photo-preview-grid"></div>
              </div>
            </div>
          </div>
          
          <div class="form-section mb-4">
            <label class="form-label fw-bold text-uppercase">Padalinys atsakingas už sprendimą</label>
            <input type="text" name="atsakingas_padalinys" class="form-control" placeholder="Pvz.: Gamybos padalinys, Kokybės skyrius...">
          </div>
          
          <div class="form-section mb-4">
            <div class="row">
              <div class="col-md-8">
                <label class="form-label fw-bold text-uppercase">Siūlomas sprendimo būdas (jeigu žinoma)</label>
                <textarea name="siulomas_sprendimas" class="form-control" rows="3" placeholder="Aprašykite siūlomą sprendimą..."></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold text-uppercase">Terminas</label>
                <input type="date" name="terminas" class="form-control">
              </div>
            </div>
          </div>
          
          <div class="form-section mb-3" style="background:#f8f9fa; padding:1rem; border-radius:8px; border:1px solid #dee2e6;">
            <label class="form-label fw-bold text-uppercase mb-3">Problemą užfiksavo</label>
            <div class="row">
              <div class="col-md-4">
                <label class="form-label small text-muted">Padalinys</label>
                <input type="text" name="uzfiksavo_padalinys" class="form-control" placeholder="Padalinio pavadinimas">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Pavardė, vardas</label>
                <input type="text" name="uzfiksavo_asmuo" class="form-control" value="<?= htmlspecialchars($prisijunges ?? '') ?>" placeholder="Vardas Pavardė">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Data</label>
                <input type="date" name="gavimo_data" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Pretenzijos tipas</label>
              <select name="tipas" class="form-select">
                <?php foreach ($tipai as $k => $v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Atšaukti</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-plus-lg me-1"></i>Registruoti pretenziją
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalView" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Pretenzijos peržiūra</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewContent">
      </div>
      <div class="modal-footer">
        <a href="#" id="pdfDownloadBtn" target="_blank" class="btn btn-danger">
          <i class="bi bi-file-earmark-pdf me-1"></i>Atsisiųsti PDF
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Uždaryti</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Redaguoti pretenziją</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body" id="editContent">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Atšaukti</button>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-check-lg me-1"></i>Išsaugoti
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEmail" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg, #27ae60 0%, #1e8449 100%);color:white;">
        <h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Siųsti el. paštu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="emailPretenzijaId">
        <div class="mb-3">
          <label class="form-label fw-bold">El. pašto adresai</label>
          <textarea id="emailRecipients" class="form-control" rows="3" placeholder="email@example.com&#10;email2@example.com&#10;&#10;Galite įvesti kelis adresus (atskirti kableliu, kabliataškiu arba nauja eilute)"></textarea>
          <small class="text-muted">PDF dokumentas bus pridėtas kaip priedas</small>
        </div>
        <div id="emailStatus" class="alert d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Atšaukti</button>
        <button type="button" class="btn btn-success" onclick="sendEmail()" id="btnSendEmail">
          <i class="bi bi-send me-1"></i>Siųsti
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

function viewPretenzija(id) {
  const p = pretenzijosData.find(x => x.id == id);
  if (!p) return;
  
  const st = statusai[p.statusas] || statusai['nauja'];
  
  let html = `
    <div class="mb-3">
      <span class="badge-tipas ${p.tipas}" style="font-size:0.85rem;padding:0.4rem 0.8rem;">${tipai[p.tipas] || p.tipas}</span>
      <span class="badge-statusas" style="background:${st.bg};color:${st.color};font-size:0.85rem;padding:0.4rem 0.8rem;">${st.label}</span>
    </div>
    
    <table class="table table-bordered mb-3">
      <tr>
        <td style="width:40%;"><strong>Problemos pastebėjimo vieta</strong></td>
        <td style="width:30%;"><strong>Gaminys</strong></td>
        <td style="width:30%;"><strong>Užsakymo Nr.</strong></td>
      </tr>
      <tr>
        <td>${p.aptikimo_vieta || '-'}</td>
        <td>${p.gaminys_info || '-'}</td>
        <td>${p.uzsakymo_numeris || p.uzsakymo_numeris_ranka || '-'}</td>
      </tr>
    </table>
    
    <div class="mb-3">
      <strong class="text-uppercase">Problemos aprašymas</strong>
      <div class="bg-light p-3 rounded mt-1">${p.aprasymas ? p.aprasymas.replace(/\n/g, '<br>') : '-'}</div>
    </div>
    
    <div class="mb-3">
      <strong class="text-uppercase">Padalinys atsakingas už sprendimą</strong>
      <div class="bg-light p-3 rounded mt-1">${p.atsakingas_padalinys || '-'}</div>
    </div>
    
    <div class="row mb-3">
      <div class="col-md-8">
        <strong class="text-uppercase">Siūlomas sprendimo būdas</strong>
        <div class="bg-light p-3 rounded mt-1">${p.siulomas_sprendimas ? p.siulomas_sprendimas.replace(/\n/g, '<br>') : '-'}</div>
      </div>
      <div class="col-md-4">
        <strong class="text-uppercase">Terminas</strong>
        <div class="bg-light p-3 rounded mt-1">${p.terminas || '-'}</div>
      </div>
    </div>
    
    <div class="p-3 rounded mb-3" style="background:#f8f9fa; border:1px solid #dee2e6;">
      <strong class="text-uppercase d-block mb-2">Problemą užfiksavo</strong>
      <div class="row text-muted" style="font-size:0.9rem;">
        <div class="col-md-4"><strong>Padalinys:</strong> ${p.uzfiksavo_padalinys || '-'}</div>
        <div class="col-md-4"><strong>Asmuo:</strong> ${p.uzfiksavo_asmuo || '-'}</div>
        <div class="col-md-4"><strong>Data:</strong> ${p.gavimo_data || '-'}</div>
      </div>
    </div>
    
    ${p.priezastis ? `<div class="mb-3"><strong>Nustatyta priežastis</strong><div class="bg-light p-3 rounded mt-1">${p.priezastis.replace(/\n/g, '<br>')}</div></div>` : ''}
    ${p.veiksmai ? `<div class="mb-3"><strong>Korekciniai veiksmai</strong><div class="bg-light p-3 rounded mt-1">${p.veiksmai.replace(/\n/g, '<br>')}</div></div>` : ''}
    
    ${p.nuotraukos && p.nuotraukos.length > 0 ? `
      <div class="mb-3">
        <strong class="text-uppercase"><i class="bi bi-images me-1"></i>Nuotraukos (${p.nuotraukos.length})</strong>
        <div class="photo-gallery mt-2">
          ${p.nuotraukos.map(n => `<img src="pretenzijos_nuotrauka.php?id=${n.id}" alt="${n.pavadinimas || 'Nuotrauka'}" onclick="window.open(this.src, '_blank')">`).join('')}
        </div>
      </div>
    ` : ''}
  `;
  
  document.getElementById('viewContent').innerHTML = html;
  document.getElementById('pdfDownloadBtn').href = 'pretenzijos_pdf.php?id=' + id;
  new bootstrap.Modal(document.getElementById('modalView')).show();
}

function editPretenzija(id) {
  const p = pretenzijosData.find(x => x.id == id);
  if (!p) return;
  
  let statusOptions = '';
  for (const [k, v] of Object.entries(statusai)) {
    statusOptions += `<option value="${k}" ${p.statusas === k ? 'selected' : ''}>${v.label}</option>`;
  }
  
  let html = `
    <input type="hidden" name="veiksmas" value="atnaujinti">
    <input type="hidden" name="id" value="${id}">
    
    <div class="mb-3">
      <label class="form-label">Statusas</label>
      <select name="statusas" class="form-select" onchange="this.form.veiksmas.value='atnaujinti_statusa';this.form.submit();">
        ${statusOptions}
      </select>
      <small class="text-muted">Pakeitus statusą forma bus automatiškai išsaugota</small>
    </div>
    
    <div class="mb-3">
      <label class="form-label">Priežastis</label>
      <textarea name="priezastis" class="form-control" rows="3" placeholder="Nustatyta priežastis...">${p.priezastis || ''}</textarea>
    </div>
    
    <div class="mb-3">
      <label class="form-label">Korekciniai veiksmai</label>
      <textarea name="veiksmai" class="form-control" rows="3" placeholder="Kokie veiksmai bus/buvo atlikti...">${p.veiksmai || ''}</textarea>
    </div>
    
    <div class="mb-3">
      <label class="form-label">Atsakingas asmuo</label>
      <input type="text" name="atsakingas_asmuo" class="form-control" value="${p.atsakingas_asmuo || ''}">
    </div>
  `;
  
  document.getElementById('editContent').innerHTML = html;
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

let compressedFiles = [];

document.getElementById('photoInput').addEventListener('change', async function(e) {
  const preview = document.getElementById('photoPreview');
  const placeholder = document.querySelector('.upload-placeholder');
  
  if (this.files.length > 0) {
    placeholder.style.display = 'none';
    placeholder.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Apdorojama...';
    placeholder.style.display = 'block';
    
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
    
    placeholder.style.display = 'none';
    placeholder.innerHTML = '<i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:#6c757d;"></i><div class="mt-2">Paspauskite norėdami įkelti nuotraukas</div><small class="text-muted">arba nufotografuokite telefonu</small>';
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

function removePhoto(btn, index) {
  const input = document.getElementById('photoInput');
  const dt = new DataTransfer();
  const files = input.files;
  
  for (let i = 0; i < files.length; i++) {
    if (i !== index) dt.items.add(files[i]);
  }
  
  input.files = dt.files;
  btn.parentElement.remove();
  
  if (input.files.length === 0) {
    document.querySelector('.upload-placeholder').style.display = 'block';
    document.getElementById('photoPreview').innerHTML = '';
  }
}

document.getElementById('modalKurti').addEventListener('hidden.bs.modal', function() {
  document.getElementById('photoInput').value = '';
  document.getElementById('photoPreview').innerHTML = '';
  document.querySelector('.upload-placeholder').style.display = 'block';
});

function toggleCustomField(selectEl, customFieldId) {
  const customField = document.getElementById(customFieldId);
  if (selectEl.value === '__kita__') {
    customField.classList.remove('d-none');
    customField.focus();
  } else {
    customField.classList.add('d-none');
    customField.value = '';
  }
}

function toggleUzsakymoField(selectEl) {
  const customField = document.getElementById('uzsakymo_numeris_ranka');
  const hiddenField = document.getElementById('uzsakymo_id_final');
  if (selectEl.value === '__kita__') {
    customField.classList.remove('d-none');
    customField.focus();
    hiddenField.value = '';
  } else {
    customField.classList.add('d-none');
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
  document.getElementById('emailRecipients').value = '';
  document.getElementById('emailStatus').className = 'alert d-none';
  document.getElementById('btnSendEmail').disabled = false;
  new bootstrap.Modal(document.getElementById('modalEmail')).show();
}

async function sendEmail() {
  const pretenzijaId = document.getElementById('emailPretenzijaId').value;
  const emails = document.getElementById('emailRecipients').value.trim();
  const statusDiv = document.getElementById('emailStatus');
  const btn = document.getElementById('btnSendEmail');
  
  if (!emails) {
    statusDiv.className = 'alert alert-warning';
    statusDiv.textContent = 'Įveskite bent vieną el. pašto adresą';
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Siunčiama...';
  statusDiv.className = 'alert alert-info';
  statusDiv.textContent = 'Siunčiamas el. laiškas su PDF priedu...';
  
  try {
    const formData = new FormData();
    formData.append('pretenzija_id', pretenzijaId);
    formData.append('emails', emails);
    
    const response = await fetch('pretenzijos_siusti.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      statusDiv.className = 'alert alert-success';
      statusDiv.innerHTML = '<i class="bi bi-check-circle me-1"></i>' + result.message + (result.with_pdf ? ' (su PDF)' : '');
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Išsiųsta!';
      setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('modalEmail')).hide();
      }, 2000);
    } else {
      statusDiv.className = 'alert alert-danger';
      statusDiv.textContent = result.message || 'Klaida siunčiant';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i>Siųsti';
    }
  } catch (err) {
    statusDiv.className = 'alert alert-danger';
    statusDiv.textContent = 'Ryšio klaida: ' + err.message;
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send me-1"></i>Siųsti';
  }
}
</script>
</body>
</html>
