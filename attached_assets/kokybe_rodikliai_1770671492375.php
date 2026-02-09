<?php
/**
 * Kokybės rodiklių pagrindinis puslapis
 * 
 * Šis failas yra navigacinis puslapis, leidžiantis pasirinkti
 * tarp skirtingų produktų grupių statistikos puslapių:
 * - MT (Transformatorių) statistika
 * - GVX (Konteinerių) statistika
 * - 10kV (Skirstomųjų įrenginių) statistika
 */

require_once 'klases/Sesija.php';

/**
 * Sesijos inicijavimas ir prisijungimo tikrinimas
 * Jei vartotojas neprisijungęs - nukreipia į prisijungimo puslapį
 */
Sesija::pradzia();
Sesija::tikrintiPrisijungima();

/**
 * Puslapio pavadinimo nustatymas
 */
$page_title = 'Kokybės rodikliai';
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - ELGA QMS</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .stats-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        .stats-card.mt-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card.gvx-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .stats-card.usn-10kv-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 50%, #d35400 100%);
            color: white;
        }
        .stats-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .container-main {
            max-width: 1200px;
        }
        .card-body {
            padding: 3rem 2rem;
        }
    </style>
</head>
<body>

    <div class="container container-main">
        <div class="text-center mb-5">
            <h1 class="text-white mb-3">
                <i class="bi bi-graph-up-arrow"></i> Kokybės rodikliai
            </h1>
            <p class="text-white-50">Pasirinkite produktų grupę statistinei analizei</p>
        </div>

        <div class="row g-4">
            <!-- MT Statistika -->
            <div class="col-md-4">
                <div class="card stats-card mt-card" onclick="window.location.href='mt_statistika.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-lightning-charge-fill stats-icon"></i>
                        <h3 class="card-title mb-3">MT Statistika</h3>
                        <p class="card-text mb-4">
                            Transformatorių (MT) pastebėtų gedimų statistika su filtrais pagal laikotarpį, užsakymą ir gedimų tipus
                        </p>
                        <ul class="list-unstyled text-start mb-0">
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Filtravimas pagal datą</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Excel eksportavimas</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Detalūs gedimų duomenys</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- GVX Statistika -->
            <div class="col-md-4">
                <div class="card stats-card gvx-card" onclick="window.location.href='GVX/gvx_statistika.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-box-seam-fill stats-icon"></i>
                        <h3 class="card-title mb-3">GVX Statistika</h3>
                        <p class="card-text mb-4">
                            GVX konteinerių kokybės klausimynų statistika su KPI rodikliais ir vizualizacijomis
                        </p>
                        <ul class="list-unstyled text-start mb-0">
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> KPI rodikliai</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Pareto ir stulpelinės diagramos</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Excel eksportavimas</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 10kV USN Statistika -->
            <div class="col-md-4">
                <div class="card stats-card usn-10kv-card" onclick="window.location.href='10kv_statistika.php'">
                    <div class="card-body text-center">
                        <i class="bi bi-battery-charging stats-icon"></i>
                        <h3 class="card-title mb-3">10kV Statistika</h3>
                        <p class="card-text mb-4">
                            10kV skirstomųjų įrenginių (USN) pastebėtų gedimų statistika su filtrais pagal laikotarpį ir užsakymą
                        </p>
                        <ul class="list-unstyled text-start mb-0">
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Filtravimas pagal datą</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Excel eksportavimas</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i> Detalūs gedimų duomenys</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="pagrindinis.php" class="btn btn-outline-light btn-lg">
                <i class="bi bi-arrow-left"></i> Grįžti į peržiūrą
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
