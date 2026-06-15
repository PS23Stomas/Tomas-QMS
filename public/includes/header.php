                <?php
                /**
                 * ==========================================================================
                 *  INCLUDES/HEADER.PHP — Bendras puslapio antraštės šablonas
                 * ==========================================================================
                 *
                 *  Paskirtis:  Generuoti vieningą HTML antraštę, šoninę navigacijos
                 *              juostą ir pagrindinę turinio sritį kiekvienam sistemos
                 *              puslapiui. Įtraukiamas per require_once kiekviename
                 *              puslapyje prieš rodant turinį.
                 *
                 *  Ką šis failas daro:
                 *    1. Nustato aktyvų puslapį (paryškinimui navigacijoje)
                 *    2. Generuoja HTML <head> su stiliais, šriftais ir favicon
                 *    3. Sukuria šoninę navigacijos juostą su rolių patikrinimais
                 *    4. Generuoja viršutinę antraštę su duonos trupiniais ir puslapio pavadinimu
                 *    5. Atidaro pagrindinę turinio sritį (uždaroma footer.php)
                 *
                 *  Reikalauja iš iškviečiančio puslapio:
                 *    - $page_title  (string) — rodomas naršyklės kortelėje ir antraštėje
                 *
                 *  Naudoja sesijos kintamuosius:
                 *    - $_SESSION['aktyvus_modulis']     → aktyvaus modulio ID
                 *    - $_SESSION['aktyvus_modulis_pav'] → modulio pavadinimas
                 *    - $_SESSION['aktyvus_grupe']       → modulio grupė (pvz. "MT")
                 *
                 *  Susijusios funkcijos:
                 *    - currentUser()  → grąžina prisijungusio vartotojo duomenis
                 *    - h()            → HTML specialiųjų simbolių kodavimas (XSS apsauga)
                 * ==========================================================================
                 */

                // --------------------------------------------------------------------------
                //  1 DALIS: KINTAMŲJŲ PARUOŠIMAS
                //  Nustatome, kuris puslapis šiuo metu aktyvus (be .php plėtinio),
                //  gauname prisijungusio vartotojo duomenis ir aktyvaus modulio informaciją
                // --------------------------------------------------------------------------
                $current_page = basename($_SERVER['PHP_SELF'], '.php'); // Pvz.: "gaminiai"
                $user         = currentUser();                          // Prisijungęs vartotojas

                // Aktyvaus modulio duomenys iš sesijos (nustato moduliai.php pasirinkus modulį)
                $aktyvus_modulis     = $_SESSION['aktyvus_modulis']     ?? null;
                $aktyvus_modulis_pav = $_SESSION['aktyvus_modulis_pav'] ?? '';
                $aktyvus_grupe       = $_SESSION['aktyvus_grupe']       ?? '';
                ?>
                <!DOCTYPE html>
                <html lang="lt">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">

                    <!-- Mobiliesiems įrenginiams: naršyklės būsenos juostos spalva -->
                    <meta name="theme-color" content="#1e293b">
                    <meta name="apple-mobile-web-app-capable" content="yes">
                    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
                    <meta name="csrf-token" content="<?= csrfToken() ?>">

                    <!-- Puslapio pavadinimas naršyklės kortelėje (nustato kiekvienas puslapis per $page_title) -->
                    <title><?= h($page_title ?? 'Tomo-QMS') ?> — Kokybės valdymo sistema</title>

                    <!-- Favicon įvairių dydžių (v=2 parametras priverčia naršyklę atnaujinti iš talpyklos) -->
                    <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
                    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
                    <link rel="icon" type="image/png" sizes="64x64" href="/favicon-64.png?v=2">

                    <!-- Išankstinis DNS/ryšio užmezgimas išoriniams šaltiniams (pagreitina įkėlimą) -->
                    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

                    <!-- Pagrindinis stilių failas su visais CSS kintamaisiais ir komponentais -->
                    <link rel="stylesheet" href="/css/style.css">

                    <!-- Inter šriftas — įkeliamas asinchroniškai, kad nesulėtintų puslapio rodymą -->
                    <!-- onload triukas: pakeičia rel="preload" į rel="stylesheet" po įkėlimo -->
                    <link rel="preload" as="style"
                          href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
                          onload="this.onload=null;this.rel='stylesheet'">
                    <!-- JavaScript išjungusiems vartotojams: šriftas įkeliamas įprastai -->
                    <noscript>
                        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
                              rel="stylesheet">
                    </noscript>
                </head>
                <body>

                    <!-- Prieinamumo nuoroda: leidžia klaviatūros vartotojams praleisti navigaciją -->
                    <a href="#main-content" class="skip-to-content" data-testid="link-skip-content">
                        Pereiti prie turinio
                    </a>

                    <div class="app-layout">

                        <!-- ==============================================================
                             2 DALIS: ŠONINĖ NAVIGACIJOS JUOSTA (SIDEBAR)
                             Visada matoma kairėje. Mobiliuosiuose įrenginiuose
                             atidaroma/uždaroma per meniu mygtuką (app.js).
                             ============================================================== -->
                        <aside class="sidebar" id="sidebar">

                            <!-- Logotipas ir uždarymo mygtukas (mobiliesiems) -->
                            <div class="sidebar-header">
                                <div class="sidebar-logo">Tomo-QMS</div>
                                <button class="sidebar-close"
                                        id="sidebarClose"
                                        data-testid="button-sidebar-close"
                                        aria-label="Uždaryti navigaciją">&times;</button>
                            </div>

                            <nav class="sidebar-nav">

                                <!-- ── MODULIŲ SEKCIJA ─────────────────────────────── -->
                                <div class="nav-section-label">Moduliai</div>
                                <a href="/moduliai.php"
                                   class="nav-item <?= $current_page === 'moduliai' ? 'active' : '' ?>"
                                   data-testid="link-modules">
                                    <!-- Tinklelio ikona -->
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"/>
                                        <rect x="14" y="3" width="7" height="7"/>
                                        <rect x="14" y="14" width="7" height="7"/>
                                        <rect x="3" y="14" width="7" height="7"/>
                                    </svg>
                                    <span>Visi moduliai</span>
                                </a>

                                <?php if ($aktyvus_modulis): ?>
                                <!-- ── AKTYVAUS MODULIO SEKCIJA ────────────────────────
                                     Rodoma tik tada, kai vartotojas pasirinko modulį.
                                     Grupės pavadinimas (pvz. "MT") rodomas kaip skyrelio antraštė.
                                     ─────────────────────────────────────────────────── -->
                                <div class="nav-section-label"><?= h($aktyvus_grupe) ?></div>

                                <!-- Užsakymų nuoroda — filtruoja pagal aktyvią grupę -->
                                <a href="/uzsakymai.php?grupe=<?= urlencode($aktyvus_grupe) ?>"
                                   class="nav-item <?= $current_page === 'uzsakymai' ? 'active' : '' ?>"
                                   data-testid="link-orders">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                    <span>Užsakymai</span>
                                </a>

                                <!-- Kokybinių rodiklių skydelis — filtruojamas pagal grupę -->
                                <a href="/index.php?grupe=<?= urlencode($aktyvus_grupe) ?>"
                                   class="nav-item <?= $current_page === 'index' ? 'active' : '' ?>"
                                   data-testid="link-dashboard">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                    <span>Kokybiniai rodikliai</span>
                                </a>

                                <?php $isAdmin = (($user['role'] ?? '') === 'administratorius'); ?>
                                <?php if ($isAdmin): ?>
                                <!-- Tikrinimo šablonas — tik administratoriams -->
                                <a href="/sablonas_funkciniai.php?grupe=<?= urlencode($aktyvus_grupe) ?>"
                                   class="nav-item <?= $current_page === 'sablonas_funkciniai' ? 'active' : '' ?>"
                                   data-testid="link-template">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="12" y1="18" x2="12" y2="12"/>
                                        <line x1="9" y1="15" x2="15" y2="15"/>
                                    </svg>
                                    <span>Tikrinimo šablonas</span>
                                </a>
                                <?php endif; ?>
                                <?php endif; // aktyvus_modulis ?>


                                <!-- ── BENDROS NUORODOS ────────────────────────────── -->
                                <div class="nav-section-label">Bendra</div>
                                <a href="/pretenzijos.php"
                                   class="nav-item <?= $current_page === 'pretenzijos' ? 'active' : '' ?>"
                                   data-testid="link-claims">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>
                                    <span>Pretenzijos</span>
                                </a>


                                <!-- ── ADMINISTRAVIMAS ────────────────────────────────
                                     Prietaisų patikra — visiems vartotojams
                                     Vartotojų valdymas — tik administratoriams
                                     ─────────────────────────────────────────────────── -->
                                <?php if (!isset($isAdmin)) $isAdmin = (($user['role'] ?? '') === 'administratorius'); ?>
                                <div class="nav-section-label">Administravimas</div>

                                <a href="/prietaisai.php"
                                   class="nav-item <?= $current_page === 'prietaisai' ? 'active' : '' ?>"
                                   data-testid="link-devices">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                                        <line x1="8" y1="21" x2="16" y2="21"/>
                                        <line x1="12" y1="17" x2="12" y2="21"/>
                                    </svg>
                                    <span>Prietaisų patikra</span>
                                </a>

                                <?php if ($isAdmin): ?>
                                <!-- Vartotojų valdymas — rodomas tik admin rolei -->
                                <a href="/vartotojai.php"
                                   class="nav-item <?= $current_page === 'vartotojai' ? 'active' : '' ?>"
                                   data-testid="link-users">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <span>Vartotojų valdymas</span>
                                </a>

                                <!-- quality_tomas importas — tik admin rolei -->
                                <a href="/qt_import_admin.php"
                                   class="nav-item <?= $current_page === 'qt_import_admin' ? 'active' : '' ?>"
                                   data-testid="link-qt-import">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    <span>QT Importas</span>
                                </a>

                                <?php /* DB Migracija — laikinai paslėpta; norint atkomentuoti, išimti PHP komentarą
                                <a href="/migracija_admin.php"
                                   class="nav-item <?= $current_page === 'migracija_admin' ? 'active' : '' ?>"
                                   data-testid="link-migration">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <ellipse cx="12" cy="5" rx="9" ry="3"/>
                                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                                    </svg>
                                    <span>DB Migracija</span>
                                </a>
                                */ ?>
                                <?php endif; ?>

                            </nav>


                            <!-- ── APATINĖ ŠONINĖS JUOSTOS DALIS ─────────────────────
                                 Vartotojo informacija, profilio ir atsijungimo nuorodos
                                 ─────────────────────────────────────────────────────── -->
                            <div class="sidebar-footer">

                                <!-- Vartotojo avataras (pirma vardo raidė), vardas ir rolė -->
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= h(mb_substr($user['vardas'] ?? 'V', 0, 1)) ?>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name">
                                            <?= h(($user['vardas'] ?? '') . ' ' . ($user['pavarde'] ?? '')) ?>
                                        </div>
                                        <div class="user-role"><?php
                                            $roleLabels = ['administratorius' => 'Administratorius', 'vartotojas' => 'Vartotojas',                                                     'skaitytojas' => 'Skaitytojas'];
                                            echo h($roleLabels[$user['role'] ?? 'vartotojas'] ?? $user['role'] ??                                                             'Vartotojas');
                                        ?></div>
                                    </div>
                                </div>

                                <!-- Profilio nuoroda -->
                                <a href="/profilis.php"
                                   class="nav-item <?= $current_page === 'profilis' ? 'active' : '' ?>"
                                   data-testid="link-profile">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    <span>Profilis</span>
                                </a>

                                <!-- Atsijungimo nuoroda — sunaikina sesiją ir nukreipia į login.php -->
                                <a href="/logout.php" class="nav-item logout-link" data-testid="link-logout">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16 17 21 12 16 7"/>
                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                    </svg>
                                    <span>Atsijungti</span>
                                </a>

                            </div>
                        </aside>


                        <!-- ==============================================================
                             3 DALIS: PAGRINDINIS TURINIO BLOKAS
                             Apima viršutinę antraštę ir pagrindinę turinio sritį.
                             Ši div.main-content uždaroma footer.php faile.
                             ============================================================== -->
                        <div class="main-content">

                            <!-- Viršutinė antraštė: meniu mygtukas + duonos trupiniai + puslapio pavadinimas -->
                            <header class="top-header">

                                <!-- Meniu mygtukas (hamburger) — matomas tik mobiliuosiuose įrenginiuose -->
                                <button class="menu-toggle"
                                        id="menuToggle"
                                        data-testid="button-menu-toggle"
                                        aria-label="Atidaryti navigacijos meniu">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <line x1="3" y1="12" x2="21" y2="12"/>
                                        <line x1="3" y1="6" x2="21" y2="6"/>
                                        <line x1="3" y1="18" x2="21" y2="18"/>
                                    </svg>
                                </button>

                                <div class="page-header-info">

                                    <!-- Duonos trupiniai (breadcrumb): Moduliai → Grupė → Puslapis
                                         Rodomi tik esant aktyviam moduliui ir ne pagrindiniuose puslapiuose -->
                                    <nav class="breadcrumb"
                                         aria-label="Navigacijos kelias"
                                         data-testid="nav-breadcrumb">
                                        <?php
                                        $bc = [];
                                        // Duonos trupiniai aktyvūs tik modulio puslapiuose
                                        if ($aktyvus_modulis && !in_array($current_page,
                                            ['moduliai','pretenzijos','prietaisai','vartotojai','profilis'])) {
                                            $bc[] = '<a href="/moduliai.php">Moduliai</a>';
                                            $bc[] = '<a href="/index.php?grupe=' . urlencode($aktyvus_grupe) . '">'
                                                  . h($aktyvus_grupe) . '</a>';
                                        }
                                        if (!empty($bc)) {
                                            $sep = '<span class="breadcrumb-sep" aria-hidden="true">/</span>';
                                            echo implode($sep, $bc) . $sep;
                                            echo '<span class="breadcrumb-current">'
                                               . h($page_title ?? 'Puslapis') . '</span>';
                                        }
                                        ?>
                                    </nav>

                                    <!-- Pagrindinis puslapio pavadinimas (H1) -->
                                    <h1 class="page-title"
                                        data-testid="text-page-title">
                                        <?= h($page_title ?? 'Tomo-QMS') ?>
                                    </h1>

                                </div>
                            </header>

                            <!-- Pagrindinė turinio sritis — čia įterpiamas kiekvieno puslapio turinys.
                                 id="main-content" naudojamas "pereiti prie turinio" nuorodai (prieinamumas) -->
                            <main class="content-area" id="main-content">