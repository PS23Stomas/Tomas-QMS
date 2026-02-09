<?php
require_once __DIR__ . '/includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['el_pastas'] ?? '');
    $password = $_POST['slaptazodis'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Prašome užpildyti visus laukus.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM vartotojai WHERE el_pastas = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['slaptazodis'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                header('Location: /index.php');
                exit;
            } else {
                $error = 'Neteisingas slaptažodis.';
            }
        } else {
            $error = 'Vartotojas su šiuo el. paštu nerastas.';
        }
    }
}

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisijungimas - MT Modulis</title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <h1>MT Modulis</h1>
                <p>Gamybos valdymo sistema</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger" data-testid="text-login-error"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="/login.php">
                <div class="form-group">
                    <label class="form-label" for="el_pastas">El. paštas</label>
                    <input type="email" class="form-control" id="el_pastas" name="el_pastas" 
                           value="<?= h($_POST['el_pastas'] ?? '') ?>" required autofocus
                           data-testid="input-email">
                </div>
                <div class="form-group">
                    <label class="form-label" for="slaptazodis">Slaptažodis</label>
                    <input type="password" class="form-control" id="slaptazodis" name="slaptazodis" required
                           data-testid="input-password">
                </div>
                <button type="submit" class="btn btn-primary" data-testid="button-login">Prisijungti</button>
            </form>
        </div>
    </div>
</body>
</html>
