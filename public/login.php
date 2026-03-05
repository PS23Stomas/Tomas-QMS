<?php
/**
 * Prisijungimo puslapis - autentifikacija pagal vardą, pavardę ir slaptažodį
 *
 * Funkcionalumas:
 * - Automatinis prisijungimas per „prisiminti mane" (remember_token) slapuką
 * - Sesijos tikrinimas ir nukreipimas jei jau prisijungta
 * - POST autentifikacija: vardo, pavardės ir slaptažodžio patikra
 * - Aktyvių vartotojų (aktyvus_vartotojai) sekimas
 */

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', 1800);
session_start();

// Duomenų bazės prisijungimas per DATABASE_URL aplinkos kintamąjį
$database_url = getenv('DATABASE_URL');
$parsed = parse_url($database_url);
$dsn = "pgsql:host={$parsed['host']};port=" . ($parsed['port'] ?? 5432) . ";dbname=" . ltrim($parsed['path'], '/');
$pdo = new PDO($dsn, $parsed['user'], $parsed['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$klaida = '';

// Automatinio prisijungimo tikrinimas per „prisiminti mane" slapuką (remember_token)
// Pastaba: GET užklausoms nenukreipiame - tiesiog nustatome sesiją ir rodome formą
if (!isset($_SESSION['vartotojas_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $hashed_token = hash('sha256', $token);
    
    try {
        $stmt = $pdo->prepare("
            SELECT v.id, v.vardas, v.pavarde, v.role 
            FROM remember_tokens rt
            JOIN vartotojai v ON rt.vartotojas_id = v.id
            WHERE rt.token = ? AND rt.expires_at > CURRENT_TIMESTAMP
        ");
        $stmt->execute([$hashed_token]);
        $user = $stmt->fetch();
        
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['vartotojas_id'] = $user['id'];
            $_SESSION['vardas'] = $user['vardas'];
            $_SESSION['pavarde'] = $user['pavarde'];
            $_SESSION['role'] = $user['role'] ?? '';
            $_SESSION['paskutine_veikla'] = time();
            
            $session_id = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt_ins = $pdo->prepare("INSERT INTO aktyvus_vartotojai 
                (vartotojas_id, session_id, vardas, pavarde, ip_adresas, naršykle) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (session_id) DO UPDATE SET 
                    paskutine_veikla = CURRENT_TIMESTAMP");
            $stmt_ins->execute([$user['id'], $session_id, $user['vardas'], $user['pavarde'], $ip, $user_agent]);
        } else {
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    } catch (Exception $e) {
    }
}

// Jei vartotojas jau prisijungęs ir tai GET užklausa - rodome nukreipimo nuorodą formoje (ne 302)
$jau_prisijunges = isset($_SESSION['vartotojas_id']);

// POST užklausos apdorojimas: prisijungimo formos duomenų tikrinimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vardas = trim($_POST['vardas'] ?? '');
    $pavarde = trim($_POST['pavarde'] ?? '');
    $slaptazodis = $_POST['slaptazodis'] ?? '';

    if ($vardas && $pavarde && $slaptazodis) {
        // Vartotojo paieška duomenų bazėje pagal vardą ir pavardę
        $stmt = $pdo->prepare("SELECT id, vardas, pavarde, slaptazodis, role FROM vartotojai WHERE vardas = :vardas AND pavarde = :pavarde");
        $stmt->execute([
            'vardas' => $vardas,
            'pavarde' => $pavarde
        ]);
        $naudotojas = $stmt->fetch();

        // Slaptažodžio tikrinimas su password_verify (bcrypt)
        if ($naudotojas && password_verify($slaptazodis, $naudotojas['slaptazodis'])) {
            // Sėkmingas prisijungimas: sesijos kintamųjų nustatymas
            session_regenerate_id(true);
            
            $_SESSION['vartotojas_id'] = $naudotojas['id'];
            $_SESSION['vardas'] = $naudotojas['vardas'];
            $_SESSION['pavarde'] = $naudotojas['pavarde'];
            $_SESSION['role'] = $naudotojas['role'] ?? '';
            $_SESSION['paskutine_veikla'] = time();
            
            // Aktyvaus vartotojo įrašymas (sesijos sekimas)
            $session_id = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt_ins = $pdo->prepare("INSERT INTO aktyvus_vartotojai 
                (vartotojas_id, session_id, vardas, pavarde, ip_adresas, naršykle) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (session_id) DO UPDATE SET 
                    paskutine_veikla = CURRENT_TIMESTAMP");
            $stmt_ins->execute([$naudotojas['id'], $session_id, $naudotojas['vardas'], $naudotojas['pavarde'], $ip, $user_agent]);
            
            // „Prisiminti mane" slapuko kūrimas (30 dienų galiojimas)
            if (isset($_POST['remember']) && $_POST['remember'] == '1') {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60);
                
                // Užšifruoto žetono įrašymas į remember_tokens lentelę
                $stmt_token = $pdo->prepare("INSERT INTO remember_tokens 
                    (vartotojas_id, token, expires_at) 
                    VALUES (?, ?, TO_TIMESTAMP(?))");
                $stmt_token->execute([$naudotojas['id'], hash('sha256', $token), $expires]);
                
                // Slapuko nustatymas naršyklėje
                setcookie('remember_token', $token, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            http_response_code(200);
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/moduliai.php"></head><body><p>Nukreipiama...</p><script>window.location.replace("/moduliai.php")</script></body></html>';
            exit;
        } else {
            $klaida = "Neteisingi prisijungimo duomenys.";
        }
    } else {
        $klaida = "Visi laukai yra privalomi.";
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Prisijungimas - Tomo-QMS</title>
    <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 440px;
        }
        .login-card h2 {
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
            text-align: center;
            font-size: 1.5rem;
        }
        .login-subtitle {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        .form-row {
            display: flex;
            gap: 0.75rem;
        }
        .form-row .form-group {
            flex: 1;
        }
        .remember-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            margin-top: 0.25rem;
        }
        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #667eea;
        }
        .remember-row label {
            font-size: 0.9rem;
            color: #555;
            cursor: pointer;
        }
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            font-size: 1.05rem;
            font-weight: 600;
            border: none;
            border-radius: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            font-family: 'Inter', sans-serif;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
        .btn-login:active {
            transform: scale(0.98);
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .forgot-link {
            text-align: center;
            margin-top: 1.25rem;
        }
        .forgot-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Tomo_QMS sistema </h2>
        <p class="login-subtitle">Kokybės valdymo sistema</p>
        <?php if ($jau_prisijunges): ?>
            <div data-testid="text-already-logged-in" style="text-align:center;padding:1.5rem 0;">
                <p style="color:#333;margin-bottom:1rem;">Jūs jau esate prisijungęs kaip <strong><?= htmlspecialchars($_SESSION['vardas'] ?? '') ?> <?= htmlspecialchars($_SESSION['pavarde'] ?? '') ?></strong></p>
                <a href="/moduliai.php" class="btn-login" style="display:inline-block;text-decoration:none;text-align:center;padding:0.85rem 2rem;" data-testid="link-go-to-modules">Tęsti į sistemą</a>
            </div>
        <?php else: ?>
        <?php if (isset($_GET['sesija_pasibaige']) && $_GET['sesija_pasibaige'] == '1'): ?>
            <div class="alert alert-warning" data-testid="text-session-expired" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1rem;font-size:0.9rem;">
                Jūsų sesija baigėsi dėl neaktyvumo. Prašome prisijungti iš naujo.
            </div>
        <?php endif; ?>
        <?php if ($klaida): ?>
            <div class="alert alert-danger" data-testid="text-login-error"><?= htmlspecialchars($klaida) ?></div>
        <?php endif; ?>
        <form method="POST" action="/login.php">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="vardas">Vardas</label>
                    <input type="text" class="form-control" id="vardas" name="vardas" 
                           value="<?= htmlspecialchars($_POST['vardas'] ?? '') ?>" required autofocus
                           data-testid="input-vardas">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pavarde">Pavardė</label>
                    <input type="text" class="form-control" id="pavarde" name="pavarde" 
                           value="<?= htmlspecialchars($_POST['pavarde'] ?? '') ?>" required
                           data-testid="input-pavarde">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="slaptazodis">Slaptažodis</label>
                <input type="password" class="form-control" id="slaptazodis" name="slaptazodis" required
                       data-testid="input-password">
            </div>
            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember" value="1" data-testid="checkbox-remember">
                <label for="remember">Prisiminti mane</label>
            </div>
            <button type="submit" class="btn-login" data-testid="button-login">Prisijungti</button>
        </form>
        <div class="forgot-link">
            <a href="/slaptazodis_atstatymas.php" data-testid="link-forgot-password">Pamiršau slaptažodį</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
