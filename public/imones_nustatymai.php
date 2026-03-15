<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$page_title = 'Įmonės nustatymai';
$current_page = 'imones_nustatymai';
$user = currentUser();

if (($user['role'] ?? '') !== 'admin') {
    header('Location: /uzsakymai.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $pavadinimas = trim($_POST['pavadinimas'] ?? '');
        $adresas = trim($_POST['adresas'] ?? '');
        $telefonas = trim($_POST['telefonas'] ?? '');
        $faksas = trim($_POST['faksas'] ?? '');
        $el_pastas = trim($_POST['el_pastas'] ?? '');
        $internetas = trim($_POST['internetas'] ?? '');
        $uzsakymo_id = (int)($_POST['uzsakymo_id'] ?? 0);

        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (empty($pavadinimas)) {
            $error = 'Įmonės pavadinimas yra privalomas.';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => $error]);
                exit;
            }
        } else {
            try {
                if ($uzsakymo_id > 0) {
                    $stmt = $pdo->prepare("UPDATE uzsakymai SET 
                        imone_pavadinimas = :pavadinimas,
                        imone_adresas = :adresas,
                        imone_telefonas = :telefonas,
                        imone_faksas = :faksas,
                        imone_el_pastas = :el_pastas,
                        imone_internetas = :internetas
                        WHERE id = :id");
                    $stmt->execute([
                        ':pavadinimas' => $pavadinimas,
                        ':adresas' => $adresas,
                        ':telefonas' => $telefonas,
                        ':faksas' => $faksas,
                        ':el_pastas' => $el_pastas,
                        ':internetas' => $internetas,
                        ':id' => $uzsakymo_id,
                    ]);
                    if ($stmt->rowCount() === 0) {
                        $error = 'Užsakymas nerastas.';
                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode(['ok' => false, 'message' => $error]);
                            exit;
                        }
                    }

                    $logo_sql = '';
                    $logo_params = [];
                    if (!empty($_POST['remove_logo'])) {
                        $logo_sql = ', logotipas = NULL, logotipo_tipas = NULL';
                    } elseif (!empty($_FILES['logotipas']['tmp_name']) && $_FILES['logotipas']['error'] === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['logotipas']['tmp_name'];
                        $dydis = $_FILES['logotipas']['size'];
                        if ($dydis > 5 * 1024 * 1024) {
                            $error = 'Logotipo failas per didelis. Maksimalus dydis: 5 MB.';
                        } else {
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->file($tmp);
                            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
                            if (!in_array($mime, $allowed)) {
                                $error = 'Netinkamas failo formatas. Leidžiami: JPEG, PNG, GIF, SVG, WebP.';
                            } else {
                                $logo_data = file_get_contents($tmp);
                                $logo_sql = ', logotipas = :logotipas, logotipo_tipas = :logotipo_tipas';
                                $logo_params[':logotipas'] = $logo_data;
                                $logo_params[':logotipo_tipas'] = $mime;
                            }
                        }
                    }
                    if (empty($error) && $logo_sql !== '') {
                        $sql2 = "UPDATE imones_nustatymai SET pavadinimas = pavadinimas $logo_sql WHERE id = (SELECT id FROM imones_nustatymai LIMIT 1)";
                        $stmt2 = $pdo->prepare($sql2);
                        if (isset($logo_params[':logotipas'])) {
                            $stmt2->bindParam(':logotipas', $logo_params[':logotipas'], PDO::PARAM_LOB);
                            if (isset($logo_params[':logotipo_tipas'])) {
                                $stmt2->bindValue(':logotipo_tipas', $logo_params[':logotipo_tipas']);
                            }
                        }
                        $stmt2->execute();
                    }

                    if (empty($error)) {
                        $message = 'Užsakymo įmonės nustatymai sėkmingai atnaujinti.';
                    }
                } else {
                    $logo_sql = '';
                    $params = [
                        ':pavadinimas' => $pavadinimas,
                        ':adresas' => $adresas,
                        ':telefonas' => $telefonas,
                        ':faksas' => $faksas,
                        ':el_pastas' => $el_pastas,
                        ':internetas' => $internetas,
                    ];

                    if (!empty($_POST['remove_logo'])) {
                        $logo_sql = ', logotipas = NULL, logotipo_tipas = NULL';
                    } elseif (!empty($_FILES['logotipas']['tmp_name']) && $_FILES['logotipas']['error'] === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['logotipas']['tmp_name'];
                        $dydis = $_FILES['logotipas']['size'];
                        if ($dydis > 5 * 1024 * 1024) {
                            $error = 'Logotipo failas per didelis. Maksimalus dydis: 5 MB.';
                        } else {
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->file($tmp);
                            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
                            if (!in_array($mime, $allowed)) {
                                $error = 'Netinkamas failo formatas. Leidžiami: JPEG, PNG, GIF, SVG, WebP.';
                            } else {
                                $logo_data = file_get_contents($tmp);
                                $logo_sql = ', logotipas = :logotipas, logotipo_tipas = :logotipo_tipas';
                                $params[':logotipas'] = $logo_data;
                                $params[':logotipo_tipas'] = $mime;
                            }
                        }
                    }

                    if (empty($error)) {
                        $sql = "UPDATE imones_nustatymai SET 
                            pavadinimas = :pavadinimas, 
                            adresas = :adresas, 
                            telefonas = :telefonas, 
                            faksas = :faksas, 
                            el_pastas = :el_pastas, 
                            internetas = :internetas
                            $logo_sql
                            WHERE id = (SELECT id FROM imones_nustatymai LIMIT 1)";
                        $stmt = $pdo->prepare($sql);
                        if (isset($params[':logotipas'])) {
                            $stmt->bindParam(':logotipas', $params[':logotipas'], PDO::PARAM_LOB);
                            unset($params[':logotipas']);
                        }
                        foreach ($params as $key => $val) {
                            $stmt->bindValue($key, $val);
                        }
                        $stmt->execute();
                        $message = 'Įmonės nustatymai sėkmingai atnaujinti.';
                    }
                }

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => empty($error), 'message' => $message ?: $error]);
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Klaida saugant nustatymus: ' . $e->getMessage();
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'message' => $error]);
                    exit;
                }
            }
        }
    }
}

$nustatymai = getImonesNustatymai();

$has_logo = false;
$logo_src = '';
if (!empty($nustatymai['logotipas']) && !empty($nustatymai['logotipo_tipas'])) {
    $has_logo = true;
    $logo_data = $nustatymai['logotipas'];
    if (is_resource($logo_data)) {
        $logo_data = stream_get_contents($logo_data);
    }
    $logo_src = 'data:' . $nustatymai['logotipo_tipas'] . ';base64,' . base64_encode($logo_data);
}

include __DIR__ . '/includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1 data-testid="text-page-title">Įmonės nustatymai</h1>
        <p style="color: var(--text-secondary); margin-top: 4px;">Šie duomenys rodomi PDF dokumentų antraštėse</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success" data-testid="text-success-message"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger" data-testid="text-error-message"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 720px;">
        <div class="card-header">
            <h3>Redaguoti įmonės duomenis</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-testid="form-company-settings">
                <input type="hidden" name="action" value="update">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="pavadinimas" class="form-label">Pavadinimas <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="pavadinimas" name="pavadinimas" class="form-control" required
                           value="<?= h($nustatymai['pavadinimas'] ?? '') ?>" data-testid="input-company-name">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="adresas" class="form-label">Adresas</label>
                    <textarea id="adresas" name="adresas" class="form-control" rows="2"
                              data-testid="input-company-address"><?= h($nustatymai['adresas'] ?? '') ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label for="telefonas" class="form-label">Telefonas</label>
                        <input type="text" id="telefonas" name="telefonas" class="form-control"
                               value="<?= h($nustatymai['telefonas'] ?? '') ?>" data-testid="input-company-phone">
                    </div>
                    <div class="form-group">
                        <label for="faksas" class="form-label">Faksas</label>
                        <input type="text" id="faksas" name="faksas" class="form-control"
                               value="<?= h($nustatymai['faksas'] ?? '') ?>" data-testid="input-company-fax">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label for="el_pastas" class="form-label">El. paštas</label>
                        <input type="email" id="el_pastas" name="el_pastas" class="form-control"
                               value="<?= h($nustatymai['el_pastas'] ?? '') ?>" data-testid="input-company-email">
                    </div>
                    <div class="form-group">
                        <label for="internetas" class="form-label">Interneto svetainė</label>
                        <input type="text" id="internetas" name="internetas" class="form-control"
                               value="<?= h($nustatymai['internetas'] ?? '') ?>" data-testid="input-company-website">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Logotipas</label>
                    <?php if ($has_logo): ?>
                    <div style="margin-bottom: 12px; padding: 16px; background: var(--bg-secondary); border-radius: 8px; display: flex; align-items: center; gap: 16px;">
                        <img src="<?= $logo_src ?>" alt="Įmonės logotipas" style="max-height: 80px; max-width: 200px; border-radius: 4px;" data-testid="img-company-logo">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--danger); font-size: 14px;">
                            <input type="checkbox" name="remove_logo" value="1" data-testid="input-remove-logo"> Pašalinti logotipą
                        </label>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="logotipas" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp"
                           class="form-control" data-testid="input-company-logo">
                    <small style="color: var(--text-secondary); margin-top: 4px; display: block;">Leistini formatai: JPEG, PNG, GIF, SVG, WebP. Maks. 5 MB.</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" data-testid="button-save-settings">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Išsaugoti
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="max-width: 720px; margin-top: 24px;">
        <div class="card-header">
            <h3>PDF antraštės peržiūra</h3>
        </div>
        <div class="card-body">
            <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 24px; background: #fff; text-align: center;">
                <?php if ($has_logo): ?>
                <img src="<?= $logo_src ?>" alt="Logotipas" style="max-height: 60px; margin-bottom: 8px;" data-testid="img-preview-logo">
                <br>
                <?php endif; ?>
                <div style="font-size: 18px; font-weight: 700; color: #1a1a2e;" data-testid="text-preview-name"><?= h($nustatymai['pavadinimas'] ?? '') ?></div>
                <div style="font-size: 13px; color: #666; margin-top: 4px;" data-testid="text-preview-details">
                    <?= h($nustatymai['adresas'] ?? '') ?><br>
                    <?php if (!empty($nustatymai['telefonas'])): ?>Tel. <?= h($nustatymai['telefonas']) ?><?php endif; ?>
                    <?php if (!empty($nustatymai['faksas'])): ?>, Faks. <?= h($nustatymai['faksas']) ?><?php endif; ?><br>
                    <?php if (!empty($nustatymai['el_pastas'])): ?>El. paštas: <?= h($nustatymai['el_pastas']) ?><?php endif; ?>
                    <?php if (!empty($nustatymai['internetas'])): ?> | Internetas: <?= h($nustatymai['internetas']) ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
