<?php
/**
 * Vartotojo profilio puslapis - el. pašto atnaujinimas ir slaptažodžio keitimas
 *
 * Funkcionalumas:
 * - El. pašto adreso peržiūra ir atnaujinimas
 * - Slaptažodžio keitimas su dabartinio slaptažodžio patvirtinimu
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user = currentUser();
$pranesimas = '';
$klaida = '';

// Gaunamas dabartinis vartotojo el. pašto adresas
$stmt = $pdo->prepare("SELECT el_pastas FROM vartotojai WHERE id = ?");
$stmt->execute([$user['id']]);
$vartotojas = $stmt->fetch();

// POST užklausos apdorojimas: el. pašto arba slaptažodžio keitimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $veiksmas = $_POST['veiksmas'] ?? '';

    // El. pašto adreso atnaujinimo logika
    if ($veiksmas === 'el_pastas') {
        $naujas_el = trim($_POST['el_pastas'] ?? '');
        if (empty($naujas_el)) {
            $klaida = 'Įveskite el. pašto adresą.';
        } elseif (!filter_var($naujas_el, FILTER_VALIDATE_EMAIL)) {
            $klaida = 'Neteisingas el. pašto formatas.';
        } else {
            // Atnaujinamas el. paštas duomenų bazėje
            $stmt = $pdo->prepare("UPDATE vartotojai SET el_pastas = ? WHERE id = ?");
            $stmt->execute([$naujas_el, $user['id']]);
            $vartotojas['el_pastas'] = $naujas_el;
            $pranesimas = 'El. pašto adresas atnaujintas.';
        }
    }

    // Slaptažodžio keitimo logika su dabartiniu slaptažodžiu patvirtinimu
    if ($veiksmas === 'slaptazodis') {
        $dabartinis = $_POST['dabartinis_slaptazodis'] ?? '';
        $naujas = $_POST['naujas_slaptazodis'] ?? '';
        $pakartoti = $_POST['pakartoti_slaptazodis'] ?? '';

        if (empty($dabartinis) || empty($naujas) || empty($pakartoti)) {
            $klaida = 'Visi slaptažodžio laukai privalomi.';
        } elseif (mb_strlen($naujas) < 8) {
            $klaida = 'Naujas slaptažodis turi būti bent 8 simbolių.';
        } elseif (!preg_match('/[0-9]/', $naujas)) {
            $klaida = 'Naujas slaptažodis turi turėti bent vieną skaičių.';
        } elseif ($naujas !== $pakartoti) {
            $klaida = 'Nauji slaptažodžiai nesutampa.';
        } else {
            // Tikrinamas dabartinis slaptažodis prieš leidžiant keisti
            $stmt = $pdo->prepare("SELECT slaptazodis FROM vartotojai WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();

            if (!password_verify($dabartinis, $row['slaptazodis'])) {
                $klaida = 'Neteisingas dabartinis slaptažodis.';
            } else {
                // Naujo slaptažodžio užšifravimas ir įrašymas
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
                    <input type="password" class="form-control" id="naujas_slaptazodis" name="naujas_slaptazodis" required minlength="8"
                           data-testid="input-new-password">
                    <div id="profilis_strengthBar" style="height:4px;border-radius:2px;margin-top:0.4rem;transition:all 0.3s;background:#e0e0e0;" data-testid="password-strength-bar"></div>
                    <small id="naujas_slaptazodis_hint" style="display:block; color:#6b7280; font-size:0.82rem; margin-top:4px;" data-testid="text-new-password-hint">Bent 8 simboliai ir vienas skaičius</small>
                    <div id="naujas_slaptazodis_error" style="display:none; color:#dc2626; font-size:0.82rem; margin-top:4px;" data-testid="text-new-password-error"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pakartoti_slaptazodis">Pakartokite naują slaptažodį</label>
                    <input type="password" class="form-control" id="pakartoti_slaptazodis" name="pakartoti_slaptazodis" required minlength="8"
                           data-testid="input-repeat-password">
                    <div id="pakartoti_slaptazodis_hint" style="display:none; font-size:0.82rem; margin-top:4px;" data-testid="text-repeat-password-hint"></div>
                </div>
                <button type="submit" class="btn btn-primary" data-testid="button-change-password">Pakeisti slaptažodį</button>
            </form>
            <script>
            (function() {
                var input = document.getElementById('naujas_slaptazodis');
                var errorDiv = document.getElementById('naujas_slaptazodis_error');
                var hintEl = document.getElementById('naujas_slaptazodis_hint');
                var repeatInput = document.getElementById('pakartoti_slaptazodis');
                var repeatHint = document.getElementById('pakartoti_slaptazodis_hint');
                var strengthBar = document.getElementById('profilis_strengthBar');

                function validate() {
                    var val = input.value;
                    if (val.length === 0) {
                        errorDiv.style.display = 'none';
                        if (hintEl) hintEl.style.color = '#6b7280';
                        return true;
                    }
                    if (val.length < 8) {
                        errorDiv.textContent = 'Slaptažodis turi būti bent 8 simbolių.';
                        errorDiv.style.display = 'block';
                        if (hintEl) hintEl.style.color = '#dc2626';
                        return false;
                    }
                    if (!/[0-9]/.test(val)) {
                        errorDiv.textContent = 'Slaptažodis turi turėti bent vieną skaičių.';
                        errorDiv.style.display = 'block';
                        if (hintEl) hintEl.style.color = '#dc2626';
                        return false;
                    }
                    errorDiv.style.display = 'none';
                    if (hintEl) hintEl.style.color = '#16a34a';
                    return true;
                }

                function validateRepeat() {
                    if (!repeatInput || !repeatHint) return true;
                    var val = repeatInput.value;
                    if (val.length === 0) {
                        repeatHint.style.display = 'none';
                        return true;
                    }
                    if (val !== input.value) {
                        repeatHint.textContent = 'Slaptažodžiai nesutampa.';
                        repeatHint.style.color = '#dc2626';
                        repeatHint.style.display = 'block';
                        return false;
                    }
                    repeatHint.textContent = 'Slaptažodžiai sutampa.';
                    repeatHint.style.color = '#16a34a';
                    repeatHint.style.display = 'block';
                    return true;
                }

                function updateStrengthBar() {
                    if (!strengthBar) return;
                    var v = input.value;
                    var score = 0;
                    if (v.length >= 8) score++;
                    if (v.length >= 12) score++;
                    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
                    if (/[0-9]/.test(v)) score++;
                    if (/[^A-Za-z0-9]/.test(v)) score++;
                    if (score <= 1) {
                        strengthBar.style.background = '#ef4444';
                        strengthBar.style.width = '33%';
                    } else if (score <= 3) {
                        strengthBar.style.background = '#f59e0b';
                        strengthBar.style.width = '66%';
                    } else {
                        strengthBar.style.background = '#22c55e';
                        strengthBar.style.width = '100%';
                    }
                }

                input.addEventListener('input', function() {
                    validate();
                    updateStrengthBar();
                    if (repeatInput && repeatInput.value.length > 0) validateRepeat();
                });
                repeatInput && repeatInput.addEventListener('input', validateRepeat);

                input.closest('form').addEventListener('submit', function(e) {
                    if (!validate() || !validateRepeat()) e.preventDefault();
                });
            })();
            </script>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
