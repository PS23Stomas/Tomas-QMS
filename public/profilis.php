<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user = currentUser();
$pranesimas = '';
$klaida = '';

$stmt = $pdo->prepare("SELECT el_pastas FROM vartotojai WHERE id = ?");
$stmt->execute([$user['id']]);
$vartotojas = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $veiksmas = $_POST['veiksmas'] ?? '';

    if ($veiksmas === 'el_pastas') {
        $naujas_el = trim($_POST['el_pastas'] ?? '');
        if (empty($naujas_el)) {
            $klaida = 'Įveskite el. pašto adresą.';
        } elseif (!filter_var($naujas_el, FILTER_VALIDATE_EMAIL)) {
            $klaida = 'Neteisingas el. pašto formatas.';
        } else {
            $stmt = $pdo->prepare("UPDATE vartotojai SET el_pastas = ? WHERE id = ?");
            $stmt->execute([$naujas_el, $user['id']]);
            $vartotojas['el_pastas'] = $naujas_el;
            $pranesimas = 'El. pašto adresas atnaujintas.';
        }
    }

    if ($veiksmas === 'slaptazodis') {
        $dabartinis = $_POST['dabartinis_slaptazodis'] ?? '';
        $naujas = $_POST['naujas_slaptazodis'] ?? '';
        $pakartoti = $_POST['pakartoti_slaptazodis'] ?? '';

        if (empty($dabartinis) || empty($naujas) || empty($pakartoti)) {
            $klaida = 'Visi slaptažodžio laukai privalomi.';
        } elseif (mb_strlen($naujas) < 4) {
            $klaida = 'Naujas slaptažodis turi būti bent 4 simbolių.';
        } elseif ($naujas !== $pakartoti) {
            $klaida = 'Nauji slaptažodžiai nesutampa.';
        } else {
            $stmt = $pdo->prepare("SELECT slaptazodis FROM vartotojai WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();

            if (!password_verify($dabartinis, $row['slaptazodis'])) {
                $klaida = 'Neteisingas dabartinis slaptažodis.';
            } else {
                $hash = password_hash($naujas, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE vartotojai SET slaptazodis = ? WHERE id = ?");
                $stmt->execute([$hash, $user['id']]);
                $pranesimas = 'Slaptažodis sėkmingai pakeistas.';
            }
        }
    }
}

$page_title = 'Profilis';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <?php if ($klaida): ?>
        <div class="alert alert-danger" data-testid="text-profile-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 6px;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= h($klaida) ?>
        </div>
    <?php endif; ?>
    <?php if ($pranesimas): ?>
        <div class="alert alert-success" data-testid="text-profile-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?= h($pranesimas) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title" style="font-size: 1.1rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px; margin-right: 6px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                El. pašto adresas
            </h3>
        </div>
        <div class="card-body">
            <p style="color: #666; font-size: 0.85rem; margin-bottom: 1rem;">
                El. paštas naudojamas slaptažodžio atstatymui. Įsitikinkite, kad adresas teisingas.
            </p>
            <form method="POST">
                <input type="hidden" name="veiksmas" value="el_pastas">
                <div class="form-group">
                    <label class="form-label" for="el_pastas">El. paštas</label>
                    <input type="email" class="form-control" id="el_pastas" name="el_pastas" 
                           value="<?= h($vartotojas['el_pastas'] ?? '') ?>"
                           placeholder="jusu@pastas.lt"
                           data-testid="input-profile-email">
                </div>
                <button type="submit" class="btn btn-primary" data-testid="button-save-email">Išsaugoti el. paštą</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="font-size: 1.1rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px; margin-right: 6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Slaptažodžio keitimas
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="veiksmas" value="slaptazodis">
                <div class="form-group">
                    <label class="form-label" for="dabartinis_slaptazodis">Dabartinis slaptažodis</label>
                    <input type="password" class="form-control" id="dabartinis_slaptazodis" name="dabartinis_slaptazodis" required
                           data-testid="input-current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="naujas_slaptazodis">Naujas slaptažodis</label>
                    <input type="password" class="form-control" id="naujas_slaptazodis" name="naujas_slaptazodis" required minlength="4"
                           data-testid="input-new-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pakartoti_slaptazodis">Pakartokite naują slaptažodį</label>
                    <input type="password" class="form-control" id="pakartoti_slaptazodis" name="pakartoti_slaptazodis" required minlength="4"
                           data-testid="input-repeat-password">
                </div>
                <button type="submit" class="btn btn-primary" data-testid="button-change-password">Pakeisti slaptažodį</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
