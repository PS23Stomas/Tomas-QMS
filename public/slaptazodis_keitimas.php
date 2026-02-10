<?php
session_set_cookie_params([
    'lifetime' => 28800, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', 28800);
session_start();

require __DIR__ . '/klases/Database.php';
$pdo = Database::getConnection();

$pranesimas = '';
$klaida = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$galiojantis = false;

if (empty($token)) {
    $klaida = 'Netinkama arba trūkstama atstatymo nuoroda.';
} else {
    $stmt = $pdo->prepare("SELECT id, vardas, pavarde FROM vartotojai WHERE login_token = ? AND token_galiojimas > NOW()");
    $stmt->execute([$token]);
    $vartotojas = $stmt->fetch();

    if ($vartotojas) {
        $galiojantis = true;
    } else {
        $stmt2 = $pdo->prepare("SELECT id FROM vartotojai WHERE login_token = ?");
        $stmt2->execute([$token]);
        if ($stmt2->fetch()) {
            $klaida = 'Atstatymo nuoroda nebegalioja. Prašome sukurti naują užklausą.';
        } else {
            $klaida = 'Netinkama atstatymo nuoroda.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $galiojantis) {
    $naujas = $_POST['slaptazodis'] ?? '';
    $pakartoti = $_POST['slaptazodis2'] ?? '';

    if (empty($naujas)) {
        $klaida = 'Įveskite naują slaptažodį.';
    } elseif (mb_strlen($naujas) < 4) {
        $klaida = 'Slaptažodis turi būti bent 4 simbolių.';
    } elseif ($naujas !== $pakartoti) {
        $klaida = 'Slaptažodžiai nesutampa.';
    } else {
        $hash = password_hash($naujas, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE vartotojai SET slaptazodis = ?, login_token = NULL, token_galiojimas = NULL WHERE id = ?");
        $stmt->execute([$hash, $vartotojas['id']]);

        $pranesimas = 'Slaptažodis sėkmingai pakeistas! Galite prisijungti su nauju slaptažodžiu.';
        $galiojantis = false;
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Naujas slaptažodis - MT Modulis</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
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
        .btn-save {
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
        .btn-save:hover { opacity: 0.9; }
        .btn-save:active { transform: scale(0.98); }
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
        .icon-key {
            text-align: center;
            margin-bottom: 1rem;
        }
        .icon-key svg {
            width: 48px;
            height: 48px;
            color: #667eea;
        }
        .user-info {
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f0f0ff;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #555;
        }
        .user-info strong { color: #333; }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.4rem;
            transition: all 0.3s;
            background: #e0e0e0;
        }
        .password-strength.weak { background: #ef4444; width: 33%; }
        .password-strength.medium { background: #f59e0b; width: 66%; }
        .password-strength.strong { background: #22c55e; width: 100%; }
        @media (max-width: 480px) {
            .reset-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="icon-key">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
            </svg>
        </div>
        <h2>Naujas slaptažodis</h2>

        <?php if ($klaida): ?>
            <div class="alert alert-danger" data-testid="text-change-error"><?= htmlspecialchars($klaida) ?></div>
        <?php endif; ?>
        <?php if ($pranesimas): ?>
            <div class="alert alert-success" data-testid="text-change-success"><?= htmlspecialchars($pranesimas) ?></div>
        <?php endif; ?>

        <?php if ($galiojantis && $vartotojas): ?>
            <div class="user-info">
                Keičiamas slaptažodis: <strong><?= htmlspecialchars($vartotojas['vardas'] . ' ' . $vartotojas['pavarde']) ?></strong>
            </div>
            <form method="POST" action="/slaptazodis_keitimas.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label class="form-label" for="slaptazodis">Naujas slaptažodis</label>
                    <input type="password" class="form-control" id="slaptazodis" name="slaptazodis" 
                           required autofocus minlength="4"
                           data-testid="input-new-password">
                    <div class="password-strength" id="strengthBar"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="slaptazodis2">Pakartokite slaptažodį</label>
                    <input type="password" class="form-control" id="slaptazodis2" name="slaptazodis2" 
                           required minlength="4"
                           data-testid="input-confirm-password">
                </div>
                <button type="submit" class="btn-save" data-testid="button-save-password">Išsaugoti slaptažodį</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <?php if ($pranesimas): ?>
                <a href="/login.php" data-testid="link-go-login">Eiti į prisijungimą</a>
            <?php else: ?>
                <a href="/slaptazodis_atstatymas.php" data-testid="link-new-request">Sukurti naują užklausą</a>
                &nbsp;&middot;&nbsp;
                <a href="/login.php" data-testid="link-back-login">Prisijungti</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var pw = document.getElementById('slaptazodis');
        var bar = document.getElementById('strengthBar');
        if (!pw || !bar) return;
        pw.addEventListener('input', function() {
            var v = pw.value;
            var score = 0;
            if (v.length >= 4) score++;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            bar.className = 'password-strength';
            if (score <= 1) bar.classList.add('weak');
            else if (score <= 3) bar.classList.add('medium');
            else bar.classList.add('strong');
        });
    })();
    </script>
</body>
</html>
