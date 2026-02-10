<?php
/**
 * Vartotojo prisijungimo puslapis
 * 
 * Šis failas atsakingas už vartotojų autentifikaciją sistemoje.
 * Palaiko tiek įprastą prisijungimą su vardu, pavarde ir slaptažodžiu,
 * tiek automatinį prisijungimą naudojant "Remember Me" funkciją su saugiu token.
 * 
 * Saugumo priemonės:
 * - Sesijos ID regeneravimas prisijungus (apsauga nuo session fixation atakų)
 * - Slaptažodžio tikrinimas naudojant password_verify() (bcrypt)
 * - Remember token saugomas užhašuotas SHA-256 algoritmu
 * - Saugūs cookie parametrai (httponly, secure, samesite)
 * 
 * @package ELGAK
 * @subpackage Autentifikacija
 */

/**
 * Sesijos konfigūracija
 * 
 * Nustatomi saugūs sesijos cookie parametrai prieš pradedant sesiją.
 * Sesijos gyvavimo laikas - 8 valandos (28800 sekundžių).
 */
session_set_cookie_params([
    'lifetime' => 28800,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', 28800);

session_start();
require 'db.php';

$klaida = '';

/**
 * Automatinis prisijungimas su "Remember Me" token
 * 
 * Tikrina ar vartotojas turi galiojantį remember_token cookie.
 * Jei token rastas ir galioja, vartotojas automatiškai prijungiamas
 * be slaptažodžio įvedimo.
 * 
 * Procesas:
 * 1. Patikrinama ar vartotojas dar neprisijungęs ir ar yra remember_token cookie
 * 2. Token užhašuojamas SHA-256 ir ieškomas duomenų bazėje
 * 3. Tikrinama ar token dar negalioja (expires_at > dabartinis laikas)
 * 4. Jei viskas gerai - sukuriama sesija ir vartotojas nukreipiamas į pagrindinį puslapį
 * 5. Jei token negalioja - cookie ištrinamas
 * 
 * @global PDO $pdo Duomenų bazės prisijungimo objektas
 * @global array $_SESSION Sesijos kintamieji
 * @global array $_COOKIE Cookie kintamieji
 */
if (!isset($_SESSION['vartotojas_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $hashed_token = hash('sha256', $token);
    
    try {
        /**
         * Užklausa ieško vartotojo pagal remember token
         * JOIN su vartotojai lentele grąžina vartotojo duomenis
         * Tikrinama ar token dar galioja (expires_at > CURRENT_TIMESTAMP)
         */
        $stmt = $pdo->prepare("
            SELECT v.id, v.vardas, v.pavarde, v.role 
            FROM remember_tokens rt
            JOIN vartotojai v ON rt.vartotojas_id = v.id
            WHERE rt.token = ? AND rt.expires_at > CURRENT_TIMESTAMP
        ");
        $stmt->execute([$hashed_token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            /**
             * Sėkmingas automatinis prisijungimas
             * Regeneruojamas sesijos ID apsaugai nuo session fixation atakų
             */
            session_regenerate_id(true);
            
            $_SESSION['vartotojas_id'] = $user['id'];
            $_SESSION['vardas'] = $user['vardas'];
            $_SESSION['pavarde'] = $user['pavarde'];
            $_SESSION['role'] = $user['role'] ?? '';
            unset($_SESSION['usn_darbuotojas']); // Išvalyti USN darbuotojo flag'ą
            unset($_SESSION['gvx_darbuotojas']); // Išvalyti GVX darbuotojo flag'ą
            
            /**
             * Įrašomas aktyvus vartotojas į aktyvus_vartotojai lentelę
             * Saugoma: vartotojo ID, sesijos ID, vardas, pavardė, IP adresas, naršyklė
             */
            $session_id = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt_ins = $pdo->prepare("INSERT INTO aktyvus_vartotojai 
                (vartotojas_id, session_id, vardas, pavarde, ip_adresas, naršykle) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (session_id) DO UPDATE SET 
                    paskutine_veikla = CURRENT_TIMESTAMP");
            $stmt_ins->execute([$user['id'], $session_id, $user['vardas'], 
                          $user['pavarde'], $ip, $user_agent]);
            
            header("Location: pagrindinis.php");
            exit;
        } else {
            /**
             * Token negalioja arba nerastas
             * Ištrinamas remember_token cookie nustatant galiojimo laiką į praeitį
             * Naudojami tie patys saugumo parametrai kaip ir nustatant cookie
             */
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    } catch (Exception $e) {
        // Ignoruoti klaidas - vartotojas tiesiog turės prisijungti rankiniu būdu
    }
}

/**
 * POST užklausos apdorojimas - vartotojo prisijungimas
 * 
 * Apdoroja prisijungimo formos duomenis, tikrina vartotojo kredencialus
 * ir sukuria sesiją sėkmingo prisijungimo atveju.
 * 
 * Procesas:
 * 1. Gaunami ir išvalomi formos duomenys (vardas, pavardė, slaptažodis)
 * 2. Tikrinama ar visi laukai užpildyti
 * 3. Ieškomas vartotojas duomenų bazėje pagal vardą ir pavardę
 * 4. Tikrinamas slaptažodis naudojant password_verify()
 * 5. Sukuriama sesija ir įrašomas aktyvus vartotojas
 * 6. Jei pasirinkta "Remember Me" - sukuriamas ilgalaikis token
 * 
 * @global PDO $conn Duomenų bazės prisijungimo objektas
 * @global PDO $pdo Duomenų bazės prisijungimo objektas (remember tokens)
 * @global array $_POST POST užklausos duomenys
 * @global array $_SESSION Sesijos kintamieji
 * @global string $klaida Klaidos pranešimas rodomas vartotojui
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /**
     * Formos duomenų gavimas ir valymas
     * trim() pašalina tarpus iš pradžios ir pabaigos
     */
    $vardas = trim($_POST['vardas']);
    $pavarde = trim($_POST['pavarde']);
    $slaptazodis = $_POST['slaptazodis'];

    if ($vardas && $pavarde && $slaptazodis) {
        /**
         * Vartotojo paieška duomenų bazėje
         * Naudojami named placeholders apsaugai nuo SQL injection
         */
        $stmt = $conn->prepare("SELECT id, vardas, pavarde, slaptazodis, role FROM vartotojai WHERE vardas = :vardas AND pavarde = :pavarde");
        $stmt->execute([
            'vardas' => $vardas,
            'pavarde' => $pavarde
        ]);
        $naudotojas = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * Slaptažodžio tikrinimas
         * password_verify() palygina įvestą slaptažodį su užhašuotu slaptažodžiu iš DB
         */
        if ($naudotojas && password_verify($slaptazodis, $naudotojas['slaptazodis'])) {
            /**
             * Sėkmingas prisijungimas
             * Regeneruojamas sesijos ID apsaugai nuo session fixation atakų
             */
            session_regenerate_id(true);
            
            $_SESSION['vartotojas_id'] = $naudotojas['id'];
            $_SESSION['vardas'] = $naudotojas['vardas'];
            $_SESSION['pavarde'] = $naudotojas['pavarde'];
            $_SESSION['role'] = $naudotojas['role'] ?? '';
            unset($_SESSION['usn_darbuotojas']); // Išvalyti USN darbuotojo flag'ą
            unset($_SESSION['gvx_darbuotojas']); // Išvalyti GVX darbuotojo flag'ą
            
            /**
             * Aktyvaus vartotojo įrašymas
             * Leidžiamos kelios sesijos tam pačiam vartotojui (skirtinguose įrenginiuose)
             * ON CONFLICT - jei sesija jau egzistuoja, atnaujinamas paskutinės veiklos laikas
             */
            $session_id = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt_ins = $pdo->prepare("INSERT INTO aktyvus_vartotojai 
                (vartotojas_id, session_id, vardas, pavarde, ip_adresas, naršykle) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (session_id) DO UPDATE SET 
                    paskutine_veikla = CURRENT_TIMESTAMP");
            $stmt_ins->execute([$naudotojas['id'], $session_id, $naudotojas['vardas'], 
                          $naudotojas['pavarde'], $ip, $user_agent]);
            
            /**
             * "Remember Me" funkcionalumas
             * 
             * Jei vartotojas pažymėjo "Prisiminti mane" checkbox, sukuriamas
             * ilgalaikis token, kuris leidžia automatinį prisijungimą 30 dienų.
             * 
             * Procesas:
             * 1. Generuojamas kriptografiškai saugus 32 baitų (64 hex simbolių) token
             * 2. Token užhašuojamas SHA-256 prieš saugant į DB (saugumo sumetimais)
             * 3. Nustatomas galiojimo laikas - 30 dienų nuo dabar
             * 4. Token įrašomas į remember_tokens lentelę
             * 5. Originalus (neužhašuotas) token saugomas cookie
             * 
             * @var string $token Kriptografiškai saugus atsitiktinis token
             * @var int $expires Token galiojimo laikas (Unix timestamp)
             */
            if (isset($_POST['remember']) && $_POST['remember'] == '1') {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 dienų
                
                /**
                 * Token saugojimas duomenų bazėje
                 * Saugomas SHA-256 hash, ne originalus token
                 * TO_TIMESTAMP() konvertuoja Unix timestamp į PostgreSQL timestamp
                 */
                $stmt_token = $pdo->prepare("INSERT INTO remember_tokens 
                    (vartotojas_id, token, expires_at) 
                    VALUES (?, ?, TO_TIMESTAMP(?))");
                $stmt_token->execute([$naudotojas['id'], hash('sha256', $token), $expires]);
                
                /**
                 * Cookie nustatymas su saugiais parametrais
                 * - secure: tik per HTTPS
                 * - httponly: nepasiekiamas per JavaScript (XSS apsauga)
                 * - samesite: Lax apsaugo nuo CSRF atakų
                 */
                setcookie('remember_token', $token, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            header("Location: pagrindinis.php");
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
    <title>Prisijungimas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        body {
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
            padding: 2rem;
            width: 100%;
            max-width: 440px;
        }
        .login-card h2 {
            font-weight: 700;
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .login-card .form-label {
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }
        .login-card .form-control {
            padding: 0.75rem;
            font-size: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
        }
        .login-card .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .login-card .btn-primary {
            padding: 0.85rem;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 0.5rem;
            width: 100%;
        }
        .login-card .alert {
            border-radius: 0.5rem;
            font-size: 0.95rem;
        }
        .forgot-link {
            text-align: center;
            margin-top: 1rem;
        }
        .forgot-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 576px) {
            .login-card {
                padding: 1.5rem;
            }
            .login-card h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<div class="login-card">
    <h2>🔐 Prisijungimas</h2>

    <?php if ($klaida): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($klaida) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'slaptazodis_atnaujintas'): ?>
        <div class="alert alert-success">
            Slaptažodis sėkmingai atnaujintas. Prisijunkite iš naujo.
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="vardas" class="form-label">Vardas</label>
            <input type="text" name="vardas" id="vardas" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label for="pavarde" class="form-label">Pavardė</label>
            <input type="text" name="pavarde" id="pavarde" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="slaptazodis" class="form-label">Slaptažodis</label>
            <input type="password" name="slaptazodis" id="slaptazodis" class="form-control" required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
            <label for="remember" class="form-check-label">Prisiminti mane (30 dienų)</label>
        </div>
        <button type="submit" class="btn btn-primary">Prisijungti</button>
    </form>

    <div class="forgot-link">
        <a href="siusti_nuoroda.php">Pamiršote slaptažodį?</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
