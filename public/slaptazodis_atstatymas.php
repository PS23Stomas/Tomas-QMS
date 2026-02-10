<?php
/**
 * Slaptažodžio atstatymo užklausos puslapis - el. laiško siuntimas su atstatymo nuoroda
 *
 * Šis puslapis leidžia vartotojui įvesti el. pašto adresą ir gauti
 * slaptažodžio atstatymo nuorodą el. paštu naudojant Emailas klasę.
 */

// Sesijos konfigūracija su saugumo parametrais
session_set_cookie_params([
    'lifetime' => 28800, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', 28800);
session_start();

require __DIR__ . '/klases/Database.php';
require __DIR__ . '/klases/Emailas.php';
$pdo = Database::getConnection();

$pranesimas = '';
$klaida = '';

// POST užklausos apdorojimas - atstatymo nuorodos siuntimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $el_pastas = trim($_POST['el_pastas'] ?? '');

    // El. pašto adreso validacija
    if (empty($el_pastas)) {
        $klaida = 'Įveskite savo el. pašto adresą.';
    } elseif (!filter_var($el_pastas, FILTER_VALIDATE_EMAIL)) {
        $klaida = 'Neteisingas el. pašto formatas.';
    } else {
        $stmt = $pdo->prepare("SELECT id, vardas, pavarde, el_pastas FROM vartotojai WHERE LOWER(el_pastas) = LOWER(?)");
        $stmt->execute([$el_pastas]);
        $vartotojas = $stmt->fetch();

        if ($vartotojas) {
            // Atsitiktinio žetono generavimas ir galiojimo laiko nustatymas (1 valanda)
            $token = bin2hex(random_bytes(32));
            $galiojimas = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Žetono išsaugojimas duomenų bazėje
            $stmt = $pdo->prepare("UPDATE vartotojai SET login_token = ?, token_galiojimas = ? WHERE id = ?");
            $stmt->execute([$token, $galiojimas, $vartotojas['id']]);

            // El. laiško siuntimas per Emailas klasę (naudoja Resend API)
            try {
                $vardas = $vartotojas['vardas'] . ' ' . $vartotojas['pavarde'];
                $issiusta = Emailas::siustiAtstatymoNuoroda($vartotojas['el_pastas'], $vardas, $token);

                if ($issiusta) {
                    $pranesimas = 'Slaptažodžio atstatymo nuoroda išsiųsta į jūsų el. paštą.';
                } else {
                    $klaida = 'Nepavyko išsiųsti el. laiško. Bandykite vėliau arba kreipkitės į administratorių.';
                }
            } catch (Exception $e) {
                $klaida = 'El. pašto siuntimo klaida. Patikrinkite, ar sistema sukonfigūruota teisingai.';
            }
        } else {
            $pranesimas = 'Jei šis el. pašto adresas yra registruotas, atstatymo nuoroda bus išsiųsta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slaptažodžio atstatymas - MT Modulis</title>
    <link rel="shortcut icon" type="image/png" href="/favicon-32.png?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .reset-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 440px;
        }
        .reset-card h2 {
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
            text-align: center;
            font-size: 1.5rem;
        }
        .reset-subtitle {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        .form-group { margin-bottom: 1rem; }
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
        .btn-reset {
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
            margin-top: 0.5rem;
        }
        .btn-reset:hover { opacity: 0.9; }
        .btn-reset:active { transform: scale(0.98); }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .back-link {
            text-align: center;
            margin-top: 1.25rem;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .back-link a:hover { text-decoration: underline; }
        .icon-lock {
            text-align: center;
            margin-bottom: 1rem;
        }
        .icon-lock svg {
            width: 48px;
            height: 48px;
            color: #667eea;
        }
        @media (max-width: 480px) {
            .reset-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="icon-lock">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </div>
        <h2>Slaptažodžio atstatymas</h2>
        <p class="reset-subtitle">Įveskite savo el. pašto adresą ir mes atsiųsime nuorodą slaptažodžiui pakeisti</p>

        <?php if ($klaida): ?>
            <div class="alert alert-danger" data-testid="text-reset-error"><?= htmlspecialchars($klaida) ?></div>
        <?php endif; ?>
        <?php if ($pranesimas): ?>
            <div class="alert alert-success" data-testid="text-reset-success"><?= htmlspecialchars($pranesimas) ?></div>
        <?php endif; ?>

        <?php if (!$pranesimas): ?>
        <form method="POST" action="/slaptazodis_atstatymas.php">
            <div class="form-group">
                <label class="form-label" for="el_pastas">El. pašto adresas</label>
                <input type="email" class="form-control" id="el_pastas" name="el_pastas" 
                       value="<?= htmlspecialchars($_POST['el_pastas'] ?? '') ?>" 
                       required autofocus placeholder="jusu@pastas.lt"
                       data-testid="input-email">
            </div>
            <button type="submit" class="btn-reset" data-testid="button-send-reset">Siųsti atstatymo nuorodą</button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="/login.php" data-testid="link-back-login">Grįžti į prisijungimą</a>
        </div>
    </div>
</body>
</html>
