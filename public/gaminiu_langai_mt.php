<?php
/**
 * MT gaminių navigacijos langas - plytelės funkcinių bandymų, komponentų, dielektrinių bandymų prieigai
 *
 * Šis failas atvaizduoja MT gaminio navigacijos puslapį su plytelėmis (tiles),
 * leidžiančiomis pasiekti funkcinių bandymų formą, komponentų langą ir dielektrinių
 * bandymų langą. Taip pat rodo gaminio informaciją ir leidžia nustatyti pilną gaminio pavadinimą.
 */

require_once __DIR__ . '/klases/Database.php';
require_once __DIR__ . '/klases/Gaminys.php';
require_once __DIR__ . '/klases/Sesija.php';

/* Sesijos pradžia ir prisijungimo tikrinimas */
Sesija::pradzia();
Sesija::tikrintiPrisijungima();
$vardas  = htmlspecialchars($_SESSION['vardas']);
$pavarde = htmlspecialchars($_SESSION['pavarde']);

/* GET parametrų nuskaitymas: užsakymo numeris, užsakovas, gaminio ID */
$uzsakymo_numeris = $_GET['uzsakymo_numeris'] ?? '';
$uzsakovas        = $_GET['uzsakovas']        ?? '';
$gaminio_id_get   = isset($_GET['gaminio_id']) && ctype_digit((string)$_GET['gaminio_id'])
                    ? (int)$_GET['gaminio_id'] : 0;

$conn    = Database::getConnection();
$gaminys = new Gaminys($conn);

/* --- Gaminio pavadinimo atnaujinimo POST apdorojimas --- */
/* Jei forma pateikta su pilnu pavadinimu, įrašomas naujas pavadinimas ir nukreipiama atgal */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pilnas_pavadinimas'])) {
    $pavadinimas = trim($_POST['pilnas_pavadinimas']);
    if ($pavadinimas !== '' && $uzsakymo_numeris !== '') {
        $gaminys->irasytiPilnaPavadinima($uzsakymo_numeris, $pavadinimas);
    }
    header("Location: gaminiu_langai_mt.php?uzsakymo_numeris=" . urlencode($uzsakymo_numeris) .
           "&uzsakovas=" . urlencode($uzsakovas));
    exit;
}

/* Esamo gaminio pavadinimo gavimas iš duomenų bazės */
$esamas_pavadinimas = $gaminys->gautiPilnaPavadinima($uzsakymo_numeris);

/* --- Gaminio ID nustatymas iš skirtingų lentelių --- */
$gaminio_id_mt = $gaminio_id_get;

/* 1 bandymas: ieškome gaminio ID iš uzsakymai/gaminiai lentelių pagal užsakymo numerį */
if ($gaminio_id_mt === 0 && $uzsakymo_numeris !== '') {
    $sql = "SELECT g.id FROM gaminiai g JOIN uzsakymai u ON u.id = g.uzsakymo_id WHERE TRIM(u.uzsakymo_numeris) = TRIM(:nr) ORDER BY g.id DESC LIMIT 1";
    $st = $conn->prepare($sql);
    $st->execute([':nr' => $uzsakymo_numeris]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $gaminio_id_mt = (int)$row['id'];
    }
}

/* 2 bandymas: ieškome gaminio ID iš mt_funkciniai_bandymai lentelės, jei pirmasis nerado */
if ($gaminio_id_mt === 0 && $uzsakymo_numeris !== '') {
    $sql = "SELECT m.gaminio_id FROM mt_funkciniai_bandymai m JOIN gaminiai g ON g.id = m.gaminio_id JOIN uzsakymai u ON u.id = g.uzsakymo_id WHERE TRIM(u.uzsakymo_numeris) = TRIM(:nr) ORDER BY m.id DESC LIMIT 1";
    $st = $conn->prepare($sql);
    $st->execute([':nr' => $uzsakymo_numeris]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $gaminio_id_mt = (int)$row['gaminio_id'];
    }
}

/* PDF generavimo rezultato vėliavėlės (sėkmė/nesėkmė) */
$pdfOk   = isset($_GET['mt_pdf_ok'])   && $_GET['mt_pdf_ok'] === '1';
$pdfFail = isset($_GET['mt_pdf_fail']) && $_GET['mt_pdf_fail'] === '1';
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e293b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>MT Gaminių Langas</title>
    <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      :root {
        --tile-radius: 12px;
        --tile-shadow: 0 4px 12px rgba(0,0,0,0.08);
      }
      body { background: #ecf0f1; min-height: 100vh; }
      .dashboard-header {
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        color: white; padding: 1rem 0 1.25rem 0;
        position: sticky; top: 0; z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      }
      .dashboard-header h4 { font-size: 1.1rem; font-weight: 600; margin-bottom: 0; }
      .dashboard-header h5 { font-size: 0.95rem; opacity: 0.9; margin-bottom: 0; }
      .user-badge {
        background: rgba(255,255,255,0.2); padding: 0.5rem 1rem;
        border-radius: 50px; font-size: 0.9rem; backdrop-filter: blur(10px);
      }
      .tiles-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
      .tile {
        background: white; border-radius: var(--tile-radius); box-shadow: var(--tile-shadow);
        padding: 1rem; text-decoration: none; color: inherit; transition: all 0.3s ease;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-align: center; cursor: pointer; border: none; min-height: 90px;
      }
      .tile:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); text-decoration: none; }
      .tile-icon { font-size: 1.75rem; margin-bottom: 0.5rem; }
      .tile-title { font-size: 0.85rem; font-weight: 600; line-height: 1.2; }
      .tile-full-color { color: white !important; }
      .tile-back { background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
      .tile-form { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); }
      .tile-pasas { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); }
      .tile-komponentai { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
      .tile-dielektriniai { background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%); }
      .tile-disabled { background: linear-gradient(135deg, #bdc3c7 0%, #95a5a6 100%); opacity: 0.6; cursor: not-allowed; pointer-events: none; }
      .product-name-card {
        background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        padding: 1.5rem; max-width: 500px; margin: 0 auto;
      }
      .product-name-card .card-header-custom {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white; padding: 1rem 1.5rem; margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        border-radius: 16px 16px 0 0; font-weight: 600; font-size: 1.1rem;
        display: flex; align-items: center; gap: 0.5rem;
      }
      .product-name-card .form-control {
        border-radius: 10px; padding: 0.75rem 1rem;
        border: 2px solid #e9ecef; transition: border-color 0.3s ease;
      }
      .product-name-card .form-control:focus { border-color: #e74c3c; box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1); }
      .product-name-card .btn-save {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        border: none; border-radius: 10px; padding: 0.75rem 2rem; font-weight: 600; transition: all 0.3s ease;
      }
      .product-name-card .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4); }
      @media (max-width: 992px) { .tiles-grid { grid-template-columns: repeat(3, 1fr); } }
      @media (max-width: 576px) {
        .tiles-grid { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
        .tile { min-height: 80px; padding: 0.75rem; }
        .tile-icon { font-size: 1.5rem; }
        .tile-title { font-size: 0.75rem; }
        .dashboard-header h4 { font-size: 1rem; }
        .dashboard-header h5 { font-size: 0.85rem; }
        .user-badge { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
        .product-name-card { margin: 0 0.5rem; }
      }
    </style>
</head>
<body>

<header class="dashboard-header">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-1"><i class="bi bi-box-seam me-2"></i>MT Gaminių Langas</h4>
        <h5 class="mb-0">Užsakymas: <?= htmlspecialchars($uzsakymo_numeris) ?> | <?= htmlspecialchars($uzsakovas) ?></h5>
      </div>
      <span class="user-badge">
        <i class="bi bi-person-fill me-1"></i><?= $vardas ?> <?= $pavarde ?>
      </span>
    </div>
    
    <!-- Navigacijos plytelių tinklelis: grįžimo, funkcinių bandymų, komponentų, dielektrinių bandymų prieiga -->
    <div class="tiles-grid">
      <a href="/uzsakymai.php" class="tile tile-full-color tile-back">
        <div class="tile-icon"><i class="bi bi-arrow-left"></i></div>
        <span class="tile-title">Grįžti</span>
      </a>
      
      <!-- Jei gaminio ID rastas, rodome aktyvias plyteles; kitaip - išjungtas (disabled) plyteles -->
      <?php if ($gaminio_id_mt > 0): ?>
        <a href="/mt_funkciniai_bandymai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>" 
           class="tile tile-full-color tile-form">
          <div class="tile-icon"><i class="bi bi-clipboard-check"></i></div>
          <span class="tile-title">Gaminio pildymo Forma</span>
        </a>
        
        <a href="/MT/mt_sumontuoti_komponentai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>&pavadinimas=<?= urlencode($esamas_pavadinimas) ?>" 
           class="tile tile-full-color tile-komponentai">
          <div class="tile-icon"><i class="bi bi-puzzle"></i></div>
          <span class="tile-title">Panaudoti komponentai</span>
        </a>
        
        <a href="/MT/mt_dielektriniai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_numeris) ?>&uzsakovas=<?= urlencode($uzsakovas) ?>&gaminio_pavadinimas=<?= urlencode($esamas_pavadinimas) ?>" 
           class="tile tile-full-color tile-dielektriniai">
          <div class="tile-icon"><i class="bi bi-lightning-charge"></i></div>
          <span class="tile-title">Dielektriniai</span>
        </a>
      <?php else: ?>
        <div class="tile tile-full-color tile-disabled">
          <div class="tile-icon"><i class="bi bi-clipboard-check"></i></div>
          <span class="tile-title">Funkciniai bandymai</span>
        </div>
        <div class="tile tile-full-color tile-disabled">
          <div class="tile-icon"><i class="bi bi-puzzle"></i></div>
          <span class="tile-title">Komponentai</span>
        </div>
        <div class="tile tile-full-color tile-disabled">
          <div class="tile-icon"><i class="bi bi-lightning-charge"></i></div>
          <span class="tile-title">Dielektriniai</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="container mt-4">
  
  <?php if ($pdfOk): ?>
    <div class="alert alert-success d-flex align-items-center mb-4">
      <i class="bi bi-check-circle-fill me-2 fs-5"></i>
      MT bandymų PDF sėkmingai sugeneruotas.
    </div>
  <?php elseif ($pdfFail): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4">
      <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
      Nepavyko sugeneruoti MT bandymų PDF.
    </div>
  <?php endif; ?>
  
  <?php if ($gaminio_id_mt === 0): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4">
      <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
      Nerastas paskutinis gaminys – negalima atidaryti formos ar išsaugoti PDF (gaminio ID = 0).
    </div>
  <?php endif; ?>

  <div class="product-name-card">
    <div class="card-header-custom">
      <i class="bi bi-box-seam"></i>
      MT Gaminio pavadinimas
    </div>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label fw-semibold text-muted">Pilnas gaminio pavadinimas:</label>
        <input type="text" name="pilnas_pavadinimas" class="form-control" 
               value="<?= htmlspecialchars($esamas_pavadinimas ?? '') ?>" 
               placeholder="pvz. MT 8x10">
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-save btn-primary">
          <i class="bi bi-check-lg me-2"></i>Išsaugoti pavadinimą
        </button>
      </div>
    </form>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
