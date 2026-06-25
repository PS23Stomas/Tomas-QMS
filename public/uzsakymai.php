<?php
/**
 * Užsakymų valdymo puslapis - kūrimas, peržiūra, redagavimas, šalinimas
 *
 * Funkcionalumas:
 * - Užsakymų sąrašo atvaizdavimas su paieška
 * - Naujo užsakymo kūrimas (create)
 * - Užsakymo redagavimas ir MT gaminio pavadinimo atnaujinimas (update)
 * - Užsakymo trynimas kartu su susijusiais gaminiais (delete)
 * - Detalus užsakymo peržiūros rodinys su MT gaminių navigacijos kortelėmis
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

if (!isset($_GET['grupe']) && !isset($_POST['grupe']) && empty($_SESSION['aktyvus_grupe'])) {
    header('Location: /moduliai.php');
    exit;
}

$filtro_grupe = $_GET['grupe'] ?? $_POST['grupe'] ?? ($_SESSION['aktyvus_grupe'] ?? 'MT');

if (isset($_GET['grupe'])) {
    $stmt_mod = $pdo->prepare("SELECT id, pavadinimas FROM gaminiu_rusys WHERE pavadinimas = ? LIMIT 1");
    $stmt_mod->execute([$_GET['grupe']]);
    $mod_info = $stmt_mod->fetch(PDO::FETCH_ASSOC);
    if ($mod_info) {
        $_SESSION['aktyvus_modulis'] = (int)$mod_info['id'];
        $_SESSION['aktyvus_modulis_pav'] = $mod_info['pavadinimas'];
        $_SESSION['aktyvus_grupe'] = $mod_info['pavadinimas'];
    }
}
$rusis_row = $pdo->prepare("SELECT id, pavadinimas FROM gaminiu_rusys WHERE pavadinimas = ?");
$rusis_row->execute([$filtro_grupe]);
$rusis_info = $rusis_row->fetch(PDO::FETCH_ASSOC);
$filtro_rusis_id = $rusis_info ? (int)$rusis_info['id'] : 2;

$page_title = $filtro_grupe . ' Užsakymai';

$clients = $pdo->query('SELECT id, uzsakovas FROM uzsakovai ORDER BY uzsakovas')->fetchAll();
$objects = $pdo->query('SELECT id, pavadinimas FROM objektai ORDER BY pavadinimas')->fetchAll();

$user = currentUser();
$is_admin = (($user['role'] ?? '') === 'administratorius');
$gali_ikelti = in_array($user['role'] ?? '', ['administratorius', 'vartotojas']);
$imones_nust = null;
$imones_logo_src = '';
$imones_has_logo = false;
if ($is_admin) {
    $imones_nust = getImonesNustatymai();
    if (!empty($imones_nust['logotipas']) && !empty($imones_nust['logotipo_tipas'])) {
        $imones_has_logo = true;
        $logo_d = $imones_nust['logotipas'];
        if (is_resource($logo_d)) $logo_d = stream_get_contents($logo_d);
        $imones_logo_src = 'data:' . $imones_nust['logotipo_tipas'] . ';base64,' . base64_encode($logo_d);
    }
}

$message = $_GET['msg'] ?? '';
$error = $_GET['klaida'] ?? '';

// POST veiksmų apdorojimas: kūrimas, atnaujinimas arba trynimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireWrite();
    $action = $_POST['action'] ?? '';

    // Naujo užsakymo kūrimas
    if ($action === 'create') {
        $kiekis_val = max(1, (int)($_POST['kiekis'] ?? 1));
        $uzsakymo_nr = trim($_POST['uzsakymo_numeris'] ?? '');
        
        $stmt = $pdo->prepare('INSERT INTO uzsakymai (uzsakymo_numeris, kiekis, uzsakovas_id, objektas_id, vartotojas_id, gaminiu_rusis_id) VALUES (:nr, :kiekis, :uzsakovas_id, :objektas_id, :vartotojas_id, :rusis_id)');
        $stmt->execute([
            'nr' => $uzsakymo_nr,
            'kiekis' => $kiekis_val,
            'uzsakovas_id' => $_POST['uzsakovas_id'] ?: null,
            'objektas_id' => $_POST['objektas_id'] ?: null,
            'vartotojas_id' => $_SESSION['vartotojas_id'],
            'rusis_id' => $filtro_rusis_id,
        ]);
        $new_order_id = $pdo->lastInsertId();
        
        $stmt_gaminys = $pdo->prepare('INSERT INTO gaminiai (uzsakymo_id, gaminio_numeris) VALUES (:uid, :gnr)');
        for ($i = 1; $i <= $kiekis_val; $i++) {
            $gaminio_nr = $uzsakymo_nr . '-' . $i;
            $stmt_gaminys->execute(['uid' => $new_order_id, 'gnr' => $gaminio_nr]);
        }
        $uzs_nr_val = $_POST['uzsakymo_numeris'] ?? '';
        $uzs_pav = '';
        $obj_pav = '';
        if ($_POST['uzsakovas_id'] ?? null) {
            $st = $pdo->prepare('SELECT uzsakovas FROM uzsakovai WHERE id = ?');
            $st->execute([$_POST['uzsakovas_id']]);
            $uzs_pav = $st->fetchColumn() ?: '';
        }
        if ($_POST['objektas_id'] ?? null) {
            $st = $pdo->prepare('SELECT pavadinimas FROM objektai WHERE id = ?');
            $st->execute([$_POST['objektas_id']]);
            $obj_pav = $st->fetchColumn() ?: '';
        }
        $message = 'Užsakymas sukurtas sėkmingai.';
    // Užsakymo duomenų atnaujinimas ir MT gaminio pavadinimo įrašymas
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE uzsakymai SET uzsakymo_numeris = :nr, uzsakovas_id = :uzsakovas_id, objektas_id = :objektas_id WHERE id = :id');
        $stmt->execute([
            'nr' => $_POST['uzsakymo_numeris'] ?? '',
            'uzsakovas_id' => $_POST['uzsakovas_id'] ?: null,
            'objektas_id' => $_POST['objektas_id'] ?: null,
            'id' => $_POST['id'],
        ]);
        $mt_pav = trim($_POST['pilnas_pavadinimas'] ?? '');
        $uzs_nr = trim($_POST['uzsakymo_numeris'] ?? '');
        if ($mt_pav !== '' && $uzs_nr !== '') {
            $gh = new Gaminys($pdo);
            $gh->irasytiPilnaPavadinima($uzs_nr, $mt_pav);
        }
        $gam_id = (int)($_POST['gaminio_id'] ?? 0);
        $gam_nr = trim($_POST['gaminio_numeris'] ?? '');
        $gam_pav = trim($_POST['gaminio_pavadinimas'] ?? '');
        if ($gam_id > 0 && $gam_nr !== '') {
            $stmt_g = $pdo->prepare('UPDATE gaminiai SET gaminio_numeris = :nr, pavadinimas = :pav WHERE id = :id');
            $stmt_g->execute(['nr' => $gam_nr, 'pav' => $gam_pav !== '' ? $gam_pav : null, 'id' => $gam_id]);
        }
        $uzs_pav_upd = '';
        $obj_pav_upd = '';
        if ($_POST['uzsakovas_id'] ?? null) {
            $st = $pdo->prepare('SELECT uzsakovas FROM uzsakovai WHERE id = ?');
            $st->execute([$_POST['uzsakovas_id']]);
            $uzs_pav_upd = $st->fetchColumn() ?: '';
        }
        if ($_POST['objektas_id'] ?? null) {
            $st = $pdo->prepare('SELECT pavadinimas FROM objektai WHERE id = ?');
            $st->execute([$_POST['objektas_id']]);
            $obj_pav_upd = $st->fetchColumn() ?: '';
        }
        $st_r = $pdo->prepare('SELECT gaminiu_rusis_id, sukurtas FROM uzsakymai WHERE id = ?');
        $st_r->execute([$_POST['id']]);
        $upd_row = $st_r->fetch(PDO::FETCH_ASSOC);
        $rusis_id_upd = $upd_row['gaminiu_rusis_id'] ?? null;
        $sukurtas_upd = $upd_row['sukurtas'] ?? null;
        $message = 'Užsakymas atnaujintas.';
    } elseif ($action === 'update_gaminys') {
        $gam_id = (int)($_POST['gaminio_id'] ?? 0);
        $gam_nr = trim($_POST['gaminio_numeris'] ?? '');
        $gam_pav = trim($_POST['gaminio_pavadinimas'] ?? '');
        if ($gam_id > 0 && $gam_nr !== '') {
            $stmt = $pdo->prepare('UPDATE gaminiai SET gaminio_numeris = :nr, pavadinimas = :pav WHERE id = :id');
            $stmt->execute([
                'nr' => $gam_nr,
                'pav' => $gam_pav !== '' ? $gam_pav : null,
                'id' => $gam_id,
            ]);
            $message = 'Gaminio duomenys atnaujinti.';
        }
        $grupe_p = $_POST['grupe'] ?? ($filtro_grupe ?? '');
        $uzs_id_p = $_POST['uzsakymo_id'] ?? '';
        header('Location: /uzsakymai.php?grupe=' . urlencode($grupe_p) . '&id=' . urlencode($uzs_id_p) . '&gaminys=' . $gam_id . '&msg=' . urlencode($message));
        exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        $patvirtinimas = trim($_POST['patvirtinimo_nr'] ?? '');
        $user = currentUser();
        if ($user['role'] !== 'administratorius') {
            $error = 'Tik administratorius gali trinti užsakymus.';
        } elseif (!$id) {
            $error = 'Nenurodytas užsakymo ID.';
        } else {
            $chk = $pdo->prepare('SELECT uzsakymo_numeris FROM uzsakymai WHERE id = ?');
            $chk->execute([$id]);
            $tikras_nr = trim($chk->fetchColumn() ?: '');
            if ($tikras_nr === '') {
                $error = 'Užsakymas nerastas.';
            } elseif ($patvirtinimas !== $tikras_nr) {
                $error = 'Įvestas neteisingas užsakymo numeris. Trynimas atšauktas.';
            } else {
                $pdo->beginTransaction();
                try {
                    $gam_ids = $pdo->prepare('SELECT id FROM gaminiai WHERE uzsakymo_id = ?');
                    $gam_ids->execute([$id]);
                    foreach ($gam_ids->fetchAll(PDO::FETCH_COLUMN) as $gid) {
                        $pdo->prepare('DELETE FROM funkciniai_bandymai WHERE gaminio_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM komponentai WHERE gaminio_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM dielektriniai_bandymai WHERE gaminys_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM saugikliu_ideklai WHERE gaminio_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM izeminimo_tikrinimas WHERE gaminys_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM paso_teksto_korekcijos WHERE gaminio_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM bandymai_prietaisai WHERE gaminys_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM gaminiu_pdf_failai WHERE gaminio_id = ?')->execute([$gid]);
                        $pret_ids = $pdo->prepare('SELECT id FROM pretenzijos WHERE gaminio_id = ?');
                        $pret_ids->execute([$gid]);
                        foreach ($pret_ids->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                            $pdo->prepare('DELETE FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?')->execute([$pid]);
                        }
                        $pdo->prepare('DELETE FROM pretenzijos WHERE gaminio_id = ?')->execute([$gid]);
                    }
                    $uzs_pret_ids = $pdo->prepare('SELECT id FROM pretenzijos WHERE uzsakymo_id = ?');
                    $uzs_pret_ids->execute([$id]);
                    foreach ($uzs_pret_ids->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                        $pdo->prepare('DELETE FROM pretenzijos_nuotraukos WHERE pretenzija_id = ?')->execute([$pid]);
                    }
                    $pdo->prepare('DELETE FROM pretenzijos WHERE uzsakymo_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM gvx_dokumentai WHERE uzsakymo_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM gaminiai WHERE uzsakymo_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM uzsakymai WHERE id = ?')->execute([$id]);
                    $pdo->commit();
                    $message = 'Užsakymas Nr. ' . h($tikras_nr) . ' ištrintas su visais susijusiais duomenimis.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if ($e->getCode() === '23503') {
                        $error = 'Negalima ištrinti — užsakymas turi susijusių įrašų, kurių nepavyko pašalinti.';
                    } else {
                        error_log('Užsakymo trynimo klaida: ' . $e->getMessage());
                        $error = 'Klaida trinant užsakymą.';
                    }
                }
            }
        }
    } elseif ($action === 'delete_pdf') {
        $user = currentUser();
        if (($user['role'] ?? '') !== 'administratorius') {
            $error = 'Tik administratorius gali trinti PDF failus.';
        } else {
            $gam_id = (int)($_POST['gaminio_id'] ?? 0);
            $pdf_type = $_POST['pdf_type'] ?? '';
            $allowed = ['paso' => 'mt_paso_failas', 'dielektriniu' => 'mt_dielektriniu_failas', 'funkciniu' => 'mt_funkciniu_failas'];
            if ($gam_id > 0 && isset($allowed[$pdf_type])) {
                $col = $allowed[$pdf_type];
                $stmt = $pdo->prepare("UPDATE gaminiai SET $col = NULL WHERE id = ?");
                $stmt->execute([$gam_id]);
                $message = 'PDF failas ištrintas.';
            } else {
                $error = 'Neteisingi parametrai.';
            }
        }
        $redirect = '/uzsakymai.php?grupe=' . urlencode($filtro_grupe);
        $redir_id = $_GET['id'] ?? '';
        if ($redir_id !== '') $redirect .= '&id=' . urlencode($redir_id);
        if (!empty($message)) $redirect .= '&msg=' . urlencode($message);
        if (!empty($error)) $redirect .= '&klaida=' . urlencode($error);
        header('Location: ' . $redirect);
        exit;
    } elseif ($action === 'delete_ikeltas_pdf') {
        $user = currentUser();
        if (($user['role'] ?? '') !== 'administratorius') {
            $error = 'Tik administratorius gali trinti PDF failus.';
        } else {
            $failas_id = (int)($_POST['failas_id'] ?? 0);
            if ($failas_id > 0) {
                $pdo->prepare("DELETE FROM gaminiu_pdf_failai WHERE id = ?")->execute([$failas_id]);
                $message = 'PDF failas ištrintas.';
            } else {
                $error = 'Neteisingi parametrai.';
            }
        }
        $redirect = '/uzsakymai.php?grupe=' . urlencode($filtro_grupe);
        if (!empty($message)) $redirect .= '&msg=' . urlencode($message);
        if (!empty($error)) $redirect .= '&klaida=' . urlencode($error);
        header('Location: ' . $redirect);
        exit;
    }
}

// Užsakymo detalios peržiūros režimas (kai perduodamas ?id= parametras)
$view_id = $_GET['id'] ?? null;

if ($view_id) {
    // Užklausa: užsakymo informacija su užsakovu, objektu ir kūrėju
    $stmt = $pdo->prepare('
        SELECT u.*, uz.uzsakovas, o.pavadinimas as objektas, v.vardas, v.pavarde
        FROM uzsakymai u
        LEFT JOIN uzsakovai uz ON u.uzsakovas_id = uz.id
        LEFT JOIN objektai o ON u.objektas_id = o.id
        LEFT JOIN vartotojai v ON u.vartotojas_id = v.id
        WHERE u.id = :id
    ');
    $stmt->execute(['id' => $view_id]);
    $order = $stmt->fetch();

    // Gaunami visi gaminiai, priklausantys šiam užsakymui
    $order_products = Gaminys::gautiPagalUzsakyma($pdo, (int)$view_id);

    if ($order) {
        $gaminys_helper = new Gaminys($pdo);
        $uzsakymo_nr = $order['uzsakymo_numeris'] ?? '';
        $uzsakovas_name = $order['uzsakovas'] ?? '';

        $esamas_pavadinimas = $gaminys_helper->gautiPilnaPavadinima($uzsakymo_nr);

        $pasirinktas_gaminys_id = isset($_GET['gaminys']) ? (int)$_GET['gaminys'] : 0;

        $gaminio_id_mt = 0;
        if ($pasirinktas_gaminys_id > 0) {
            $chk = $pdo->prepare("SELECT id FROM gaminiai WHERE id = ? AND uzsakymo_id = ?");
            $chk->execute([$pasirinktas_gaminys_id, $view_id]);
            if ($chk->fetch()) {
                $gaminio_id_mt = $pasirinktas_gaminys_id;
            }
        }
        if ($gaminio_id_mt === 0 && !empty($order_products)) {
            $gaminio_id_mt = (int)$order_products[0]['id'];
        }

        if ($filtro_grupe === 'MT') {
            $uzbaigtumo_zingsniai = ['funkciniai' => false, 'komponentai' => false, 'dielektriniai' => false, 'pasas' => false];
        } else {
            $uzbaigtumo_zingsniai = ['funkciniai' => false, 'dielektriniai' => false];
        }
        $funkciniu_klaidu_sk = 0;
        if ($gaminio_id_mt > 0) {
            $st = $pdo->prepare("SELECT COUNT(*) as cnt FROM funkciniai_bandymai WHERE gaminio_id = ?");
            $st->execute([$gaminio_id_mt]);
            $uzbaigtumo_zingsniai['funkciniai'] = ((int)$st->fetchColumn()) > 0;

            $st = $pdo->prepare("SELECT COUNT(*) FROM funkciniai_bandymai WHERE gaminio_id = ? AND (isvada = 'neatitinka' OR isvada = 'nepadaryta')");
            $st->execute([$gaminio_id_mt]);
            $funkciniu_klaidu_sk = (int)$st->fetchColumn();

            if ($filtro_grupe === 'MT') {
                $st = $pdo->prepare("SELECT COUNT(*) as cnt FROM komponentai WHERE gaminio_id = ?");
                $st->execute([$gaminio_id_mt]);
                $uzbaigtumo_zingsniai['komponentai'] = ((int)$st->fetchColumn()) > 0;
            }

            $st = $pdo->prepare("SELECT COUNT(*) as cnt FROM dielektriniai_bandymai WHERE gaminys_id = ?");
            $st->execute([$gaminio_id_mt]);
            $diel_cnt = (int)$st->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) as cnt FROM izeminimo_tikrinimas WHERE gaminys_id = ?");
            $st->execute([$gaminio_id_mt]);
            $izem_cnt = (int)$st->fetchColumn();
            $uzbaigtumo_zingsniai['dielektriniai'] = ($diel_cnt + $izem_cnt) > 0;

            if ($filtro_grupe === 'MT') {
                $st = $pdo->prepare("SELECT mt_paso_failas, (mt_paso_pdf IS NOT NULL) AS has_paso_pdf FROM gaminiai WHERE id = ?");
                $st->execute([$gaminio_id_mt]);
                $paso_r = $st->fetch(PDO::FETCH_ASSOC);
                $uzbaigtumo_zingsniai['pasas'] = !empty($paso_r['mt_paso_failas']) || !empty($paso_r['has_paso_pdf']);
            }
        }
        $uzbaigtumo_atlikta = array_sum($uzbaigtumo_zingsniai);
        $uzbaigtumo_viso = count($uzbaigtumo_zingsniai);
        $uzbaigtumo_procentai = round(($uzbaigtumo_atlikta / $uzbaigtumo_viso) * 100);

        $aktyvaus_gaminio_nr = '';
        $aktyvaus_gaminio_pav = '';
        foreach ($order_products as $p) {
            if ((int)$p['id'] === $gaminio_id_mt) {
                $aktyvaus_gaminio_nr = $p['gaminio_numeris'] ?? '';
                $aktyvaus_gaminio_pav = $p['pavadinimas'] ?? '';
                break;
            }
        }
    }
}

$gvx_lentele_yra = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name='gvx_dokumentai' AND table_schema='public'")->fetchColumn();
$gvx_subquery = $gvx_lentele_yra
    ? "(SELECT COUNT(*) FROM gvx_dokumentai gd WHERE gd.uzsakymo_id = u.id AND gd.tipas IN ('mt_deklaracija','mt_deklaracija_pdf')) as gvx_paso_sk,
           (SELECT COUNT(*) FROM gvx_dokumentai gd WHERE gd.uzsakymo_id = u.id AND gd.tipas = 'nustatymu_protokolas') as gvx_nust_sk,"
    : "0 as gvx_paso_sk, 0 as gvx_nust_sk,";

$stmt_orders = $pdo->prepare("
    SELECT u.*, uz.uzsakovas, o.pavadinimas as objektas, v.vardas, v.pavarde,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id) as gaminiu_sk,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id AND (g.mt_paso_failas IS NOT NULL OR g.mt_paso_pdf IS NOT NULL)) as paso_pdf_sk,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id AND g.mt_dielektriniu_failas IS NOT NULL) as dielektriniu_pdf_sk,
           (SELECT COUNT(*) FROM gaminiai g WHERE g.uzsakymo_id = u.id AND g.mt_funkciniu_failas IS NOT NULL) as funkciniu_pdf_sk,
           (SELECT COUNT(*) FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = u.id AND pf.pdf_tipas = 'paso') as paso_ikeltu_sk,
           (SELECT COUNT(*) FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = u.id AND pf.pdf_tipas = 'dielektriniu') as dielektriniu_ikeltu_sk,
           (SELECT COUNT(*) FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = u.id AND pf.pdf_tipas = 'funkciniu') as funkciniu_ikeltu_sk,
           (SELECT COUNT(*) FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = u.id AND pf.pdf_tipas = 'nustatymu') as nustatymu_ikeltu_sk,
           {$gvx_subquery}
           (SELECT g2.id FROM gaminiai g2 WHERE g2.uzsakymo_id = u.id ORDER BY g2.id DESC LIMIT 1) as pirmasis_gaminio_id
    FROM uzsakymai u
    LEFT JOIN uzsakovai uz ON u.uzsakovas_id = uz.id
    LEFT JOIN objektai o ON u.objektas_id = o.id
    LEFT JOIN vartotojai v ON u.vartotojas_id = v.id
    WHERE u.gaminiu_rusis_id = ?
    ORDER BY u.sukurtas DESC, u.id DESC
");
$stmt_orders->execute([$filtro_rusis_id]);
$orders = $stmt_orders->fetchAll();

$uzbaigtumo_cache = [];
$detail_gaminio_ids = [];
if ($view_id && !empty($order_products)) {
    $detail_gaminio_ids = array_map(function($p) { return (int)$p['id']; }, $order_products);
}
$all_gaminio_ids = array_unique(array_merge(
    array_filter(array_column($orders, 'pirmasis_gaminio_id')),
    $detail_gaminio_ids
));
if (!empty($all_gaminio_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_gaminio_ids), '?'));

    $funk_st = $pdo->prepare("SELECT gaminio_id, COUNT(*) as cnt FROM funkciniai_bandymai WHERE gaminio_id IN ($placeholders) GROUP BY gaminio_id");
    $funk_st->execute(array_values($all_gaminio_ids));
    $funk_map = [];
    while ($r = $funk_st->fetch(PDO::FETCH_ASSOC)) { $funk_map[(int)$r['gaminio_id']] = (int)$r['cnt']; }

    $funk_err_st = $pdo->prepare("SELECT gaminio_id, COUNT(*) as cnt FROM funkciniai_bandymai WHERE gaminio_id IN ($placeholders) AND (isvada = 'neatitinka' OR isvada = 'nepadaryta') GROUP BY gaminio_id");
    $funk_err_st->execute(array_values($all_gaminio_ids));
    $funk_err_map = [];
    while ($r = $funk_err_st->fetch(PDO::FETCH_ASSOC)) { $funk_err_map[(int)$r['gaminio_id']] = (int)$r['cnt']; }

    $komp_st = $pdo->prepare("SELECT gaminio_id, COUNT(*) as cnt FROM komponentai WHERE gaminio_id IN ($placeholders) GROUP BY gaminio_id");
    $komp_st->execute(array_values($all_gaminio_ids));
    $komp_map = [];
    while ($r = $komp_st->fetch(PDO::FETCH_ASSOC)) { $komp_map[(int)$r['gaminio_id']] = (int)$r['cnt']; }

    $diel_st = $pdo->prepare("SELECT gaminys_id, COUNT(*) as cnt FROM dielektriniai_bandymai WHERE gaminys_id IN ($placeholders) GROUP BY gaminys_id");
    $diel_st->execute(array_values($all_gaminio_ids));
    $diel_map = [];
    while ($r = $diel_st->fetch(PDO::FETCH_ASSOC)) { $diel_map[(int)$r['gaminys_id']] = (int)$r['cnt']; }

    $izem_st = $pdo->prepare("SELECT gaminys_id, COUNT(*) as cnt FROM izeminimo_tikrinimas WHERE gaminys_id IN ($placeholders) GROUP BY gaminys_id");
    $izem_st->execute(array_values($all_gaminio_ids));
    $izem_map = [];
    while ($r = $izem_st->fetch(PDO::FETCH_ASSOC)) { $izem_map[(int)$r['gaminys_id']] = (int)$r['cnt']; }

    $paso_st = $pdo->prepare("SELECT id, mt_paso_failas, (mt_paso_pdf IS NOT NULL) AS has_paso_pdf FROM gaminiai WHERE id IN ($placeholders)");
    $paso_st->execute(array_values($all_gaminio_ids));
    $paso_map = [];
    while ($r = $paso_st->fetch(PDO::FETCH_ASSOC)) { $paso_map[(int)$r['id']] = !empty($r['mt_paso_failas']) || (bool)$r['has_paso_pdf']; }

    $total_steps = ($filtro_grupe === 'MT') ? 4 : 2;
    foreach ($all_gaminio_ids as $gid) {
        $gid = (int)$gid;
        $steps = 0;
        if (($funk_map[$gid] ?? 0) > 0) $steps++;
        if ($filtro_grupe === 'MT' && ($komp_map[$gid] ?? 0) > 0) $steps++;
        if ((($diel_map[$gid] ?? 0) + ($izem_map[$gid] ?? 0)) > 0) $steps++;
        if ($filtro_grupe === 'MT' && ($paso_map[$gid] ?? false)) $steps++;
        $uzbaigtumo_cache[$gid] = ['steps' => $steps, 'total' => $total_steps, 'funk_errors' => $funk_err_map[$gid] ?? 0];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success" role="alert"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" role="alert"><?= h($error) ?></div>
<?php endif; ?>
<?php if (isset($_GET['pdf_ikeltas'])): ?>
<div class="alert alert-success" role="alert">PDF failas įkeltas sėkmingai!</div>
<?php endif; ?>
<?php if (isset($_GET['pdf_klaida']) && $_GET['pdf_klaida'] !== ''): ?>
<div class="alert alert-danger" role="alert">PDF įkėlimo klaida: <?= h($_GET['pdf_klaida']) ?></div>
<?php endif; ?>

<?php if ($view_id && $order): ?>
<div style="margin-bottom: 16px;">
    <a href="/uzsakymai.php?grupe=<?= urlencode($filtro_grupe) ?>" class="btn btn-secondary btn-sm" data-testid="button-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Atgal
    </a>
</div>

<?php
    $first_product = $order_products[0] ?? null;
    $aktyvus_gaminys = null;
    foreach ($order_products as $p) {
        if ((int)$p['id'] === $gaminio_id_mt) { $aktyvus_gaminys = $p; break; }
    }
    if (!$aktyvus_gaminys) $aktyvus_gaminys = $first_product;
?>
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <span class="card-title">Užsakymas: <?= h($order['uzsakymo_numeris'] ?: 'Be nr.') ?></span>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <p><strong>Užsakymo Nr.:</strong> <?= h($order['uzsakymo_numeris'] ?: '-') ?></p>
                <p><strong>Užsakovas:</strong> <?= h($order['uzsakovas'] ?? '-') ?></p>
                <p><strong>Objektas:</strong> <?= h($order['objektas'] ?? '-') ?></p>
            </div>
            <div>
                <p><strong>Pavadinimas:</strong> <?= h($esamas_pavadinimas ?: ($aktyvus_gaminys['gaminio_tipas'] ?? '-')) ?></p>
                <p><strong>Kiekis:</strong> <?= count($order_products) ?></p>
                <p><strong>Sukūrė:</strong> <?= h(($order['vardas'] ?? '') . ' ' . ($order['pavarde'] ?? '')) ?></p>
                <p><strong>Data:</strong> <?= !empty($order['sukurtas']) ? date('Y-m-d H:i:s', strtotime($order['sukurtas'])) : '' ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (count($order_products) > 1): ?>
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <span class="card-title">Gaminiai (<?= count($order_products) ?>)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="gaminiu-tabs" data-testid="product-tabs">
            <?php foreach ($order_products as $idx => $p): 
                $is_active = ((int)$p['id'] === $gaminio_id_mt);
                $g_url = '/uzsakymai.php?grupe=' . urlencode($filtro_grupe) . '&id=' . $view_id . '&gaminys=' . $p['id'];
            ?>
            <a href="<?= $g_url ?>" class="gaminiu-tab <?= $is_active ? 'gaminiu-tab-active' : '' ?>" data-testid="product-tab-<?= ($idx + 1) ?>" <?= !empty($p['pavadinimas']) ? 'title="' . h($p['pavadinimas']) . '"' : '' ?>>
                <span class="gaminiu-tab-nr"><?= h($p['gaminio_numeris'] ?: ($idx + 1)) ?></span>
                <?php if (!empty($p['pavadinimas'])): ?>
                <span class="gaminiu-tab-pav"><?= h($p['pavadinimas']) ?></span>
                <?php endif; ?>
                <?php
                    $g_cache = $uzbaigtumo_cache[(int)$p['id']] ?? null;
                    if ($g_cache && $g_cache['steps'] > 0):
                ?>
                <span class="gaminiu-tab-status <?= $g_cache['funk_errors'] > 0 ? 'status-warn' : 'status-done' ?>">
                    <?= $g_cache['funk_errors'] > 0 ? $g_cache['funk_errors'] . ' kl.' : $g_cache['steps'] . '/' . ($g_cache['total'] ?? (($filtro_grupe === 'MT') ? 4 : 2)) ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 16px;" data-testid="card-mt-langas">
    <div class="card-header uzs-detail-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <?= h($filtro_grupe) ?> Gaminių Langas<?php if ($aktyvaus_gaminio_nr): ?> — <?= h($aktyvaus_gaminio_nr) ?><?php endif; ?><?php if (!empty($aktyvaus_gaminio_pav)): ?> <span style="font-weight:400;color:var(--text-secondary);font-size:0.85em;">(<?= h($aktyvaus_gaminio_pav) ?>)</span><?php endif; ?>
        </span>
        <?php if (isset($uzbaigtumo_procentai)): ?>
        <div class="uzbaigtumo-rodiklis" data-testid="text-completion-indicator">
            <div class="uzbaigtumo-bar">
                <div class="uzbaigtumo-bar-fill <?= $uzbaigtumo_procentai == 100 ? 'uzbaigtumo-bar-done' : '' ?>" style="width: <?= $uzbaigtumo_procentai ?>%;"></div>
            </div>
            <span class="uzbaigtumo-tekstas"><?= $uzbaigtumo_atlikta ?>/<?= $uzbaigtumo_viso ?> (<?= $uzbaigtumo_procentai ?>%)</span>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($gaminio_id_mt === 0): ?>
        <div class="alert alert-warning" style="margin-bottom: 12px;" data-testid="text-no-product-warning">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Nerastas gaminys šiam užsakymui. Pirmiausia įveskite gaminio pavadinimą aukščiau.
        </div>
        <?php endif; ?>

        <div class="mt-tiles-grid" data-testid="mt-tiles-grid">
            <?php if ($gaminio_id_mt > 0): ?>
            <a href="/mt_funkciniai_bandymai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&uzsakymo_id=<?= $view_id ?>&grupe=<?= urlencode($filtro_grupe) ?>" 
               class="mt-tile" data-testid="tile-funkciniai">
                <div class="mt-tile-icon mt-tile-icon-teal">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Gaminio pildymo forma</div>
                    <div class="mt-tile-desc"><?php
                        if ($uzbaigtumo_zingsniai['funkciniai'] && $funkciniu_klaidu_sk > 0) {
                            echo '<span class="uzbaigtumo-badge uzbaigtumo-warn-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;margin-right:3px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' . $funkciniu_klaidu_sk . ' neatit./nepad.</span>';
                        } elseif ($uzbaigtumo_zingsniai['funkciniai']) {
                            echo '<span class="uzbaigtumo-badge uzbaigtumo-done">Užpildyta</span>';
                        } else {
                            echo '<span class="uzbaigtumo-badge uzbaigtumo-pending">Neužpildyta</span>';
                        }
                    ?></div>
                </div>
            </a>
            <a href="/MT/mt_dielektriniai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&gaminio_pavadinimas=<?= urlencode($esamas_pavadinimas) ?>&uzsakymo_id=<?= $view_id ?>&grupe=<?= urlencode($filtro_grupe) ?>" 
               class="mt-tile" data-testid="tile-dielektriniai">
                <div class="mt-tile-icon mt-tile-icon-indigo">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Dielektriniai bandymai</div>
                    <div class="mt-tile-desc"><?= $uzbaigtumo_zingsniai['dielektriniai'] ? '<span class="uzbaigtumo-badge uzbaigtumo-done">Užpildyta</span>' : '<span class="uzbaigtumo-badge uzbaigtumo-pending">Neužpildyta</span>' ?></div>
                </div>
            </a>
            <?php if ($filtro_grupe === 'MT'): ?>
            <a href="/MT/mt_sumontuoti_komponentai.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&pavadinimas=<?= urlencode($esamas_pavadinimas) ?>&uzsakymo_id=<?= $view_id ?>" 
               class="mt-tile" data-testid="tile-komponentai">
                <div class="mt-tile-icon mt-tile-icon-cyan">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Panaudoti komponentai</div>
                    <div class="mt-tile-desc"><?= $uzbaigtumo_zingsniai['komponentai'] ? '<span class="uzbaigtumo-badge uzbaigtumo-done">Užpildyta</span>' : '<span class="uzbaigtumo-badge uzbaigtumo-pending">Neužpildyta</span>' ?></div>
                </div>
            </a>
            <a href="/MT/mt_pasas.php?gaminio_id=<?= $gaminio_id_mt ?>&uzsakymo_numeris=<?= urlencode($uzsakymo_nr) ?>&uzsakovas=<?= urlencode($uzsakovas_name) ?>&gaminio_pavadinimas=<?= urlencode($esamas_pavadinimas) ?>&uzsakymo_id=<?= $view_id ?>" 
               class="mt-tile" data-testid="tile-pasas">
                <div class="mt-tile-icon mt-tile-icon-emerald">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">MT Pasas</div>
                    <div class="mt-tile-desc"><?= $uzbaigtumo_zingsniai['pasas'] ? '<span class="uzbaigtumo-badge uzbaigtumo-done">Sugeneruotas</span>' : '<span class="uzbaigtumo-badge uzbaigtumo-pending">Nesugeneruotas</span>' ?></div>
                </div>
            </a>
            <?php endif; ?>
            <div class="mt-tile" onclick="openModal('editOrderModal')" data-testid="tile-redaguoti" style="cursor: pointer;">
                <div class="mt-tile-icon mt-tile-icon-slate">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Redaguoti</div>
                    <div class="mt-tile-desc"><?= $is_admin ? 'Užsakymo, gaminio ir įmonės duomenys' : 'Gaminio ir užsakymo duomenys' ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Gaminio pildymo forma</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Dielektriniai bandymai</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <?php if ($filtro_grupe === 'MT'): ?>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Panaudoti komponentai</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <div class="mt-tile mt-tile-disabled">
                <div class="mt-tile-icon mt-tile-icon-muted">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">MT Pasas</div>
                    <div class="mt-tile-desc">Nėra gaminio</div>
                </div>
            </div>
            <?php endif; ?>
            <div class="mt-tile" onclick="openModal('editOrderModal')" data-testid="tile-redaguoti" style="cursor: pointer;">
                <div class="mt-tile-icon mt-tile-icon-slate">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="mt-tile-text">
                    <div class="mt-tile-title">Redaguoti</div>
                    <div class="mt-tile-desc"><?= $is_admin ? 'Užsakymo ir įmonės duomenys' : 'Užsakymo duomenys' ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editOrderModal">
    <div class="modal" style="<?= $is_admin ? 'max-width:640px;' : '' ?>">
        <div class="modal-header">
            <h3>Redaguoti</h3>
            <button class="modal-close" onclick="closeModal('editOrderModal')" aria-label="Uždaryti">&times;</button>
        </div>
        <?php if ($is_admin): ?>
        <div class="edit-modal-tabs" style="display:flex;border-bottom:1px solid var(--border,#e5e7eb);padding:0 16px;" data-testid="edit-modal-tabs">
            <button type="button" class="edit-tab active" onclick="switchEditTab('uzsakymas')" data-testid="tab-uzsakymas" style="padding:10px 16px;border:none;background:none;font-size:14px;font-weight:600;cursor:pointer;border-bottom:2px solid var(--primary,#3b82f6);color:var(--primary,#3b82f6);">Užsakymo duomenys</button>
            <button type="button" class="edit-tab" onclick="switchEditTab('imone')" data-testid="tab-imone" style="padding:10px 16px;border:none;background:none;font-size:14px;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;color:var(--text-secondary,#6b7280);">Įmonės nustatymai</button>
        </div>
        <?php endif; ?>
        <div id="editTabUzsakymas">
        <form method="POST" action="/uzsakymai.php?id=<?= $order['id'] ?>&grupe=<?= urlencode($filtro_grupe) ?><?= $gaminio_id_mt > 0 ? '&gaminys=' . $gaminio_id_mt : '' ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $order['id'] ?>">
            <input type="hidden" name="grupe" value="<?= h($filtro_grupe) ?>">
            <input type="hidden" name="gaminio_id" value="<?= $gaminio_id_mt ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Pavadinimas</label>
                    <input type="text" class="form-control" name="pilnas_pavadinimas" value="<?= h($esamas_pavadinimas ?? '') ?>" placeholder="pvz. MT 8x10-1x100(630)" data-testid="input-mt-pavadinimas">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakymo numeris</label>
                        <input type="text" class="form-control" name="uzsakymo_numeris" value="<?= h($order['uzsakymo_numeris'] ?? '') ?>" data-testid="input-order-number-edit">
                    </div>
                    <?php if ($gaminio_id_mt > 0): ?>
                    <div class="form-group">
                        <label class="form-label">Gaminio numeris</label>
                        <input type="text" class="form-control" name="gaminio_numeris" value="<?= h($aktyvaus_gaminio_nr) ?>" required data-testid="input-gaminio-numeris">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakovas</label>
                        <div class="select-with-add">
                            <select class="form-control" name="uzsakovas_id" data-testid="select-client-edit" data-qa-select="uzsakovas">
                                <option value="">-- Pasirinkite --</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $order['uzsakovas_id'] ? 'selected' : '' ?>><?= h($c['uzsakovas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-quick-add" onclick="toggleQuickAdd('uzsakovas','edit')" title="Pridėti naują užsakovą" data-testid="button-add-client-edit">+</button>
                        </div>
                        <div class="quick-add-row" id="qa-uzsakovas-edit" style="display:none;">
                            <input type="text" class="form-control" placeholder="Naujo užsakovo pavadinimas" data-testid="input-new-client-edit">
                            <button type="button" class="btn btn-sm btn-primary" onclick="saveQuickAdd('uzsakovas','edit')" data-testid="button-save-new-client-edit">Pridėti</button>
                            <span class="quick-add-error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Objektas</label>
                        <div class="select-with-add">
                            <select class="form-control" name="objektas_id" data-testid="select-object-edit" data-qa-select="objektas">
                                <option value="">-- Pasirinkite --</option>
                                <?php foreach ($objects as $o): ?>
                                <option value="<?= $o['id'] ?>" <?= $o['id'] == $order['objektas_id'] ? 'selected' : '' ?>><?= h($o['pavadinimas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-quick-add" onclick="toggleQuickAdd('objektas','edit')" title="Pridėti naują objektą" data-testid="button-add-object-edit">+</button>
                        </div>
                        <div class="quick-add-row" id="qa-objektas-edit" style="display:none;">
                            <input type="text" class="form-control" placeholder="Naujo objekto pavadinimas" data-testid="input-new-object-edit">
                            <button type="button" class="btn btn-sm btn-primary" onclick="saveQuickAdd('objektas','edit')" data-testid="button-save-new-object-edit">Pridėti</button>
                            <span class="quick-add-error"></span>
                        </div>
                    </div>
                </div>
                <?php if ($gaminio_id_mt > 0): ?>
                <div class="form-group">
                    <label class="form-label">Gaminio pavadinimas <small style="color:var(--text-secondary);font-weight:400;">(individualus, neprivaloma)</small></label>
                    <input type="text" class="form-control" name="gaminio_pavadinimas" value="<?= h($aktyvaus_gaminio_pav ?? '') ?>" placeholder="pvz. Skydas Nr.1" data-testid="input-gaminio-pavadinimas">
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editOrderModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-save-order">Išsaugoti</button>
            </div>
        </form>
        </div>
        <?php if ($is_admin): ?>
        <?php
            $uzs_imone_pav = $order['imone_pavadinimas'] ?? $imones_nust['pavadinimas'] ?? '';
            $uzs_imone_adr = $order['imone_adresas'] ?? $imones_nust['adresas'] ?? '';
            $uzs_imone_tel = $order['imone_telefonas'] ?? $imones_nust['telefonas'] ?? '';
            $uzs_imone_fax = $order['imone_faksas'] ?? $imones_nust['faksas'] ?? '';
            $uzs_imone_el = $order['imone_el_pastas'] ?? $imones_nust['el_pastas'] ?? '';
            $uzs_imone_web = $order['imone_internetas'] ?? $imones_nust['internetas'] ?? '';
        ?>
        <div id="editTabImone" style="display:none;">
            <form id="imones-form" enctype="multipart/form-data" data-testid="form-company-settings-modal">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="uzsakymo_id" value="<?= $order['id'] ?>">
                <div class="modal-body">
                    <p style="color:var(--text-secondary,#6b7280);font-size:13px;margin-bottom:12px;">Šie duomenys rodomi šio užsakymo PDF dokumentų antraštėse</p>
                    <div id="imones-msg" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:6px;font-size:14px;" data-testid="text-imones-msg"></div>
                    <div class="form-group">
                        <label class="form-label">Pavadinimas <span style="color:var(--danger,#dc3545);">*</span></label>
                        <input type="text" class="form-control" name="pavadinimas" value="<?= h($uzs_imone_pav) ?>" required data-testid="input-company-name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adresas</label>
                        <textarea class="form-control" name="adresas" rows="2" data-testid="input-company-address"><?= h($uzs_imone_adr) ?></textarea>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Telefonas</label>
                            <input type="text" class="form-control" name="telefonas" value="<?= h($uzs_imone_tel) ?>" data-testid="input-company-phone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Faksas</label>
                            <input type="text" class="form-control" name="faksas" value="<?= h($uzs_imone_fax) ?>" data-testid="input-company-fax">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">El. paštas</label>
                            <input type="email" class="form-control" name="el_pastas" value="<?= h($uzs_imone_el) ?>" data-testid="input-company-email">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interneto svetainė</label>
                            <input type="text" class="form-control" name="internetas" value="<?= h($uzs_imone_web) ?>" data-testid="input-company-website">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Logotipas</label>
                        <div id="imones-logo-preview" style="<?= $imones_has_logo ? '' : 'display:none;' ?>margin-bottom:10px;padding:12px;background:var(--bg-secondary,#f8f9fa);border-radius:8px;display:<?= $imones_has_logo ? 'flex' : 'none' ?>;align-items:center;gap:12px;">
                            <img id="imones-logo-img" src="<?= $imones_logo_src ?>" alt="Logotipas" style="max-height:60px;max-width:160px;border-radius:4px;" data-testid="img-company-logo">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--danger,#dc3545);font-size:13px;">
                                <input type="checkbox" name="remove_logo" value="1" data-testid="input-remove-logo"> Pašalinti
                            </label>
                        </div>
                        <input type="file" name="logotipas" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp" class="form-control" data-testid="input-company-logo" style="font-size:13px;">
                        <small style="color:var(--text-secondary,#6b7280);margin-top:4px;display:block;">JPEG, PNG, GIF, SVG, WebP. Maks. 5 MB.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editOrderModal')">Atšaukti</button>
                    <button type="button" class="btn btn-primary" onclick="issaugotiImonesNustatymus()" data-testid="button-save-company">Išsaugoti</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="card" id="ordersCard">
    <div class="card-header" id="ordersCardHeader">
        <span class="card-title">Visi užsakymai (<?= count($orders) ?>)</span>
        <div class="uzs-header-actions" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <div class="uzs-search-wrap" style="position:relative;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--text-secondary);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="orderSearch" placeholder="Ieškoti pagal užsakymo Nr..." style="padding:0.4rem 0.6rem 0.4rem 2rem;border:1px solid var(--border);border-radius:6px;font-size:0.85rem;width:220px;" data-testid="input-order-search" oninput="filterOrders()">
            </div>
            <button class="btn btn-primary btn-sm btn-new-order" onclick="openModal('createOrderModal')" data-testid="button-new-order">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Naujas užsakymas
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table id="ordersTable">
                <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Užsakovas</th>
                        <th>Sukūrė</th>
                        <th>Data</th>
                        <th>Užbaigtumas</th>
                        <th>gaminio Pasas</th>
                        <th>Bandymų protokolas</th>
                        <th>Atliktų darbų aprašas</th>
                        <th>Nust. prot.</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $o): ?>
                        <tr data-testid="row-order-<?= $o['id'] ?>" data-order-nr="<?= h(mb_strtolower($o['uzsakymo_numeris'] ?? '')) ?>">
                            <td class="uzs-cell-nr"><a href="/uzsakymai.php?id=<?= $o['id'] ?>" style="color: var(--primary); font-weight: 500;" data-testid="link-order-<?= $o['id'] ?>"><?= h($o['uzsakymo_numeris'] ?: 'Be nr.') ?></a></td>
                            <td data-label="Užsakovas"><?= h($o['uzsakovas'] ?? '-') ?></td>
                            <td data-label="Sukūrė"><?= h(($o['vardas'] ?? '') . ' ' . ($o['pavarde'] ?? '')) ?></td>
                            <td data-label="Data" style="color: var(--text-secondary);"><?= !empty($o['sukurtas']) ? date('Y-m-d H:i:s', strtotime($o['sukurtas'])) : '' ?></td>
                            <td data-label="Užbaigtumas" style="text-align: center;">
                                <?php
                                    $gid = (int)($o['pirmasis_gaminio_id'] ?? 0);
                                    $uzb_total = ($filtro_grupe === 'MT') ? 4 : 2;
                                    $uzb_data = $uzbaigtumo_cache[$gid] ?? ['steps' => 0, 'total' => $uzb_total, 'funk_errors' => 0];
                                    $uzb_steps = $uzb_data['steps'];
                                    $uzb_total = $uzb_data['total'] ?? $uzb_total;
                                    $uzb_funk_err = $uzb_data['funk_errors'];
                                    $uzb_pct = round(($uzb_steps / $uzb_total) * 100);
                                    $uzb_color = $uzb_pct == 100 ? 'var(--success)' : ($uzb_pct >= 50 ? 'var(--warning)' : 'var(--text-light)');
                                ?>
                                <div class="uzbaigtumo-mini" data-testid="text-completion-<?= $o['id'] ?>">
                                    <div class="uzbaigtumo-bar-mini">
                                        <div class="uzbaigtumo-bar-fill-mini" style="width: <?= $uzb_pct ?>%; background: <?= $uzb_color ?>;"></div>
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-secondary);"><?= $uzb_steps ?>/<?= $uzb_total ?></span>
                                    <?php if ($uzb_funk_err > 0): ?>
                                    <span class="uzbaigtumo-warn" title="<?= $uzb_funk_err ?> neatitikimų/nepadarytų bandymų">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Pasas" class="uzs-cell-pdfs" style="text-align: center;">
                                <?php
                                $paso_ikelti_all = [];
                                if (($o['paso_ikeltu_sk'] ?? 0) > 0) {
                                    $stmt_pi = $pdo->prepare("SELECT pf.id, pf.failas_vardas FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = ? AND pf.pdf_tipas = 'paso' ORDER BY pf.ikelta DESC");
                                    $stmt_pi->execute([$o['id']]);
                                    $paso_ikelti_all = $stmt_pi->fetchAll(PDO::FETCH_ASSOC);
                                }
                                $pdf_g_paso = null;
                                if (($o['paso_pdf_sk'] ?? 0) > 0) {
                                    $stmt_gen = $pdo->prepare("SELECT id, mt_paso_failas FROM gaminiai WHERE uzsakymo_id = ? AND (mt_paso_failas IS NOT NULL OR mt_paso_pdf IS NOT NULL) LIMIT 1");
                                    $stmt_gen->execute([$o['id']]);
                                    $pdf_g_paso = $stmt_gen->fetch();
                                }
                                $gvx_paso_all = [];
                                if (($o['gvx_paso_sk'] ?? 0) > 0) {
                                    $stmt_gp = $pdo->prepare("SELECT id, failas, pavadinimas FROM gvx_dokumentai WHERE uzsakymo_id = ? AND tipas IN ('mt_deklaracija','mt_deklaracija_pdf') ORDER BY id ASC");
                                    $stmt_gp->execute([$o['id']]);
                                    $gvx_paso_all = $stmt_gp->fetchAll(PDO::FETCH_ASSOC);
                                }
                                // Sujungti visus šaltinius į vieną sąrašą
                                $paso_merged = [];
                                if (!empty($pdf_g_paso)) {
                                    $paso_merged[] = ['url' => '/MT/mt_paso_pdf.php?gaminio_id=' . $pdf_g_paso['id'], 'title' => 'Generuotas paso PDF', 'del' => "deletePdf({$pdf_g_paso['id']},'paso')", 'gvx_id' => null];
                                }
                                foreach ($paso_ikelti_all as $pf) {
                                    $paso_merged[] = ['url' => '/MT/ikeltu_pdf_rodyti.php?id=' . $pf['id'], 'title' => $pf['failas_vardas'], 'del' => "deleteIkeltasPdf({$pf['id']})", 'gvx_id' => null];
                                }
                                foreach ($gvx_paso_all as $gd) {
                                    $paso_merged[] = ['url' => '/gvx_dokumentai_rodyti.php?id=' . $gd['id'], 'title' => $gd['failas'] ?: $gd['pavadinimas'], 'del' => null, 'gvx_id' => $gd['id']];
                                }
                                $paso_total = count($paso_merged);
                                ?>
                                <span style="display:inline-flex;align-items:center;gap:3px;flex-wrap:wrap;justify-content:center;">
                                <?php foreach ($paso_merged as $n => $p): ?>
                                    <a href="<?= h($p['url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size:11px;padding:2px 8px;" title="<?= h($p['title']) ?>" data-testid="button-paso-pdf-<?= $o['id'] ?>-<?= $n+1 ?>"><?= $paso_total > 1 ? ($n + 1) . ' ' : '' ?>PDF</a>
                                    <?php if ($is_admin): ?>
                                        <?php if ($p['gvx_id']): ?>
                                        <button type="button" class="pdf-del-btn" onclick="deleteGvxDok(<?= $p['gvx_id'] ?>, this)" title="Ištrinti">&times;</button>
                                        <?php elseif ($p['del']): ?>
                                        <button type="button" class="pdf-del-btn" onclick="<?= $p['del'] ?>" title="Ištrinti">&times;</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($paso_merged) && !$gali_ikelti): ?>
                                    <span style="color:var(--text-secondary);font-size:11px;">-</span>
                                <?php endif; ?>
                                <?php if ($gali_ikelti): ?>
                                    <button type="button" class="btn-ikelti-pdf" onclick="atidartiIkelimoPdf(<?= $o['id'] ?>, 'paso')" title="Įkelti paso PDF" data-testid="button-ikelti-paso-<?= $o['id'] ?>">↑</button>
                                <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Dielektr." class="uzs-cell-pdfs" style="text-align: center;">
                                <?php
                                $diel_ikelti_all = [];
                                if (($o['dielektriniu_ikeltu_sk'] ?? 0) > 0) {
                                    $stmt_di = $pdo->prepare("SELECT pf.id, pf.failas_vardas, g.gaminio_numeris, g.pavadinimas FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = ? AND pf.pdf_tipas = 'dielektriniu' ORDER BY pf.ikelta DESC");
                                    $stmt_di->execute([$o['id']]);
                                    $diel_ikelti_all = $stmt_di->fetchAll(PDO::FETCH_ASSOC);
                                }
                                $diel_all = [];
                                if (($o['dielektriniu_pdf_sk'] ?? 0) > 0) {
                                    $stmt_dg = $pdo->prepare("SELECT id, gaminio_numeris, pavadinimas FROM gaminiai WHERE uzsakymo_id = ? AND mt_dielektriniu_failas IS NOT NULL ORDER BY id");
                                    $stmt_dg->execute([$o['id']]);
                                    $diel_all = $stmt_dg->fetchAll(PDO::FETCH_ASSOC);
                                }
                                $diel_any = !empty($diel_all) || !empty($diel_ikelti_all);
                                ?>
                                <span style="display:inline-flex;align-items:center;gap:3px;flex-wrap:wrap;justify-content:center;">
                                <?php if (!empty($diel_all)): ?>
                                    <?php if (count($diel_all) === 1): ?>
                                    <a href="/MT/mt_dielektriniu_pdf.php?gaminio_id=<?= $diel_all[0]['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size:11px;padding:2px 8px;" data-testid="button-dielektriniu-pdf-<?= $o['id'] ?>">PDF</a>
                                    <?php if ($is_admin): ?>
                                    <button type="button" class="pdf-del-btn" onclick="deletePdf(<?= $diel_all[0]['id'] ?>, 'dielektriniu')" title="Ištrinti generuotą PDF" data-testid="button-delete-dielektriniu-pdf-<?= $o['id'] ?>">&times;</button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="pdf-dropdown" data-testid="dropdown-dielektriniu-pdf-<?= $o['id'] ?>">
                                        <button type="button" class="btn btn-outline-primary btn-sm pdf-dropdown-btn" style="font-size:11px;padding:2px 8px;" onclick="togglePdfDropdown(this)">PDF ▾</button>
                                        <div class="pdf-dropdown-list">
                                        <?php foreach ($diel_all as $dg): ?>
                                            <span class="pdf-dropdown-item-wrap">
                                                <a href="/MT/mt_dielektriniu_pdf.php?gaminio_id=<?= $dg['id'] ?>" target="_blank"><?= h($dg['gaminio_numeris'] ?: '—') ?> — <?= h($dg['pavadinimas'] ?: '—') ?></a>
                                                <?php if ($is_admin): ?>
                                                <button type="button" class="pdf-del-btn-sm" onclick="event.stopPropagation(); deletePdf(<?= $dg['id'] ?>, 'dielektriniu')" title="Ištrinti">&times;</button>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($diel_ikelti_all)): ?>
                                    <?php if (count($diel_ikelti_all) === 1): ?>
                                    <a href="/MT/ikeltu_pdf_rodyti.php?id=<?= $diel_ikelti_all[0]['id'] ?>" target="_blank" class="btn btn-outline-success btn-sm" style="font-size:11px;padding:2px 8px;" title="<?= h($diel_ikelti_all[0]['failas_vardas']) ?>">↑PDF</a>
                                    <?php if ($is_admin): ?>
                                    <button type="button" class="pdf-del-btn" onclick="deleteIkeltasPdf(<?= $diel_ikelti_all[0]['id'] ?>)" title="Ištrinti įkeltą PDF">&times;</button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="pdf-dropdown">
                                        <button type="button" class="btn btn-outline-success btn-sm pdf-dropdown-btn" style="font-size:11px;padding:2px 8px;" onclick="togglePdfDropdown(this)">↑<?= count($diel_ikelti_all) ?> ▾</button>
                                        <div class="pdf-dropdown-list">
                                        <?php foreach ($diel_ikelti_all as $pf): ?>
                                            <span class="pdf-dropdown-item-wrap">
                                                <a href="/MT/ikeltu_pdf_rodyti.php?id=<?= $pf['id'] ?>" target="_blank"><?= h($pf['failas_vardas']) ?></a>
                                                <?php if ($is_admin): ?>
                                                <button type="button" class="pdf-del-btn-sm" onclick="event.stopPropagation(); deleteIkeltasPdf(<?= $pf['id'] ?>)" title="Ištrinti">&times;</button>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$diel_any && !$gali_ikelti): ?>
                                    <span style="color:var(--text-secondary);font-size:11px;">-</span>
                                <?php endif; ?>
                                <?php if ($gali_ikelti): ?>
                                    <button type="button" class="btn-ikelti-pdf" onclick="atidartiIkelimoPdf(<?= $o['id'] ?>, 'dielektriniu')" title="Įkelti dielektrinių bandymų PDF" data-testid="button-ikelti-dielektriniu-<?= $o['id'] ?>">↑</button>
                                <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Funkc." class="uzs-cell-pdfs" style="text-align: center;">
                                <?php
                                $funk_ikelti_all = [];
                                if (($o['funkciniu_ikeltu_sk'] ?? 0) > 0) {
                                    $stmt_fi = $pdo->prepare("SELECT pf.id, pf.failas_vardas, g.gaminio_numeris, g.pavadinimas FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = ? AND pf.pdf_tipas = 'funkciniu' ORDER BY pf.ikelta DESC");
                                    $stmt_fi->execute([$o['id']]);
                                    $funk_ikelti_all = $stmt_fi->fetchAll(PDO::FETCH_ASSOC);
                                }
                                $funk_all = [];
                                if (($o['funkciniu_pdf_sk'] ?? 0) > 0) {
                                    $stmt_fg = $pdo->prepare("SELECT id, gaminio_numeris, pavadinimas FROM gaminiai WHERE uzsakymo_id = ? AND mt_funkciniu_failas IS NOT NULL ORDER BY id");
                                    $stmt_fg->execute([$o['id']]);
                                    $funk_all = $stmt_fg->fetchAll(PDO::FETCH_ASSOC);
                                }
                                $funk_any = !empty($funk_all) || !empty($funk_ikelti_all);
                                ?>
                                <span style="display:inline-flex;align-items:center;gap:3px;flex-wrap:wrap;justify-content:center;">
                                <?php if (!empty($funk_all)): ?>
                                    <?php if (count($funk_all) === 1): ?>
                                    <a href="/MT/mt_funkciniu_pdf.php?gaminio_id=<?= $funk_all[0]['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size:11px;padding:2px 8px;" data-testid="button-funkciniu-pdf-<?= $o['id'] ?>">PDF</a>
                                    <?php if ($is_admin): ?>
                                    <button type="button" class="pdf-del-btn" onclick="deletePdf(<?= $funk_all[0]['id'] ?>, 'funkciniu')" title="Ištrinti generuotą PDF" data-testid="button-delete-funkciniu-pdf-<?= $o['id'] ?>">&times;</button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="pdf-dropdown" data-testid="dropdown-funkciniu-pdf-<?= $o['id'] ?>">
                                        <button type="button" class="btn btn-outline-primary btn-sm pdf-dropdown-btn" style="font-size:11px;padding:2px 8px;" onclick="togglePdfDropdown(this)">PDF ▾</button>
                                        <div class="pdf-dropdown-list">
                                        <?php foreach ($funk_all as $fg): ?>
                                            <span class="pdf-dropdown-item-wrap">
                                                <a href="/MT/mt_funkciniu_pdf.php?gaminio_id=<?= $fg['id'] ?>" target="_blank"><?= h($fg['gaminio_numeris'] ?: '—') ?> — <?= h($fg['pavadinimas'] ?: '—') ?></a>
                                                <?php if ($is_admin): ?>
                                                <button type="button" class="pdf-del-btn-sm" onclick="event.stopPropagation(); deletePdf(<?= $fg['id'] ?>, 'funkciniu')" title="Ištrinti">&times;</button>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($funk_ikelti_all)): ?>
                                    <?php if (count($funk_ikelti_all) === 1): ?>
                                    <a href="/MT/ikeltu_pdf_rodyti.php?id=<?= $funk_ikelti_all[0]['id'] ?>" target="_blank" class="btn btn-outline-success btn-sm" style="font-size:11px;padding:2px 8px;" title="<?= h($funk_ikelti_all[0]['failas_vardas']) ?>">↑PDF</a>
                                    <?php if ($is_admin): ?>
                                    <button type="button" class="pdf-del-btn" onclick="deleteIkeltasPdf(<?= $funk_ikelti_all[0]['id'] ?>)" title="Ištrinti įkeltą PDF">&times;</button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="pdf-dropdown">
                                        <button type="button" class="btn btn-outline-success btn-sm pdf-dropdown-btn" style="font-size:11px;padding:2px 8px;" onclick="togglePdfDropdown(this)">↑<?= count($funk_ikelti_all) ?> ▾</button>
                                        <div class="pdf-dropdown-list">
                                        <?php foreach ($funk_ikelti_all as $pf): ?>
                                            <span class="pdf-dropdown-item-wrap">
                                                <a href="/MT/ikeltu_pdf_rodyti.php?id=<?= $pf['id'] ?>" target="_blank"><?= h($pf['failas_vardas']) ?></a>
                                                <?php if ($is_admin): ?>
                                                <button type="button" class="pdf-del-btn-sm" onclick="event.stopPropagation(); deleteIkeltasPdf(<?= $pf['id'] ?>)" title="Ištrinti">&times;</button>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($uzb_funk_err > 0): ?>
                                    <span class="uzbaigtumo-warn" title="<?= $uzb_funk_err ?> neatitikimų/nepadarytų">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$funk_any && $uzb_funk_err === 0 && !$gali_ikelti): ?>
                                    <span style="color:var(--text-secondary);font-size:11px;">-</span>
                                <?php endif; ?>
                                <?php if ($gali_ikelti): ?>
                                    <button type="button" class="btn-ikelti-pdf" onclick="atidartiIkelimoPdf(<?= $o['id'] ?>, 'funkciniu')" title="Įkelti funkcinių bandymų PDF" data-testid="button-ikelti-funkciniu-<?= $o['id'] ?>">↑</button>
                                <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Nust. prot." class="uzs-cell-pdfs" style="text-align: center;">
                                <?php
                                $nust_ikelti_all = [];
                                if (($o['nustatymu_ikeltu_sk'] ?? 0) > 0) {
                                    $stmt_ni = $pdo->prepare("SELECT pf.id, pf.failas_vardas FROM gaminiu_pdf_failai pf JOIN gaminiai g ON pf.gaminio_id = g.id WHERE g.uzsakymo_id = ? AND pf.pdf_tipas = 'nustatymu' ORDER BY pf.ikelta DESC");
                                    $stmt_ni->execute([$o['id']]);
                                    $nust_ikelti_all = $stmt_ni->fetchAll(PDO::FETCH_ASSOC);
                                }
                                $gvx_nust_all = [];
                                if (($o['gvx_nust_sk'] ?? 0) > 0) {
                                    $stmt_gn = $pdo->prepare("SELECT id, failas, pavadinimas FROM gvx_dokumentai WHERE uzsakymo_id = ? AND tipas = 'nustatymu_protokolas' ORDER BY id ASC");
                                    $stmt_gn->execute([$o['id']]);
                                    $gvx_nust_all = $stmt_gn->fetchAll(PDO::FETCH_ASSOC);
                                }
                                // Sujungti visus šaltinius į vieną sąrašą
                                $nust_merged = [];
                                foreach ($nust_ikelti_all as $pf) {
                                    $nust_merged[] = ['url' => '/MT/ikeltu_pdf_rodyti.php?id=' . $pf['id'], 'title' => $pf['failas_vardas'], 'del' => "deleteIkeltasPdf({$pf['id']})", 'gvx_id' => null];
                                }
                                foreach ($gvx_nust_all as $gd) {
                                    $nust_merged[] = ['url' => '/gvx_dokumentai_rodyti.php?id=' . $gd['id'], 'title' => $gd['failas'] ?: $gd['pavadinimas'], 'del' => null, 'gvx_id' => $gd['id']];
                                }
                                $nust_total = count($nust_merged);
                                ?>
                                <span style="display:inline-flex;align-items:center;gap:3px;flex-wrap:wrap;justify-content:center;">
                                <?php foreach ($nust_merged as $n => $p): ?>
                                    <a href="<?= h($p['url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size:11px;padding:2px 8px;" title="<?= h($p['title']) ?>" data-testid="button-nust-pdf-<?= $o['id'] ?>-<?= $n+1 ?>"><?= $nust_total > 1 ? ($n + 1) . ' ' : '' ?>PDF</a>
                                    <?php if ($is_admin): ?>
                                        <?php if ($p['gvx_id']): ?>
                                        <button type="button" class="pdf-del-btn" onclick="deleteGvxDok(<?= $p['gvx_id'] ?>, this)" title="Ištrinti">&times;</button>
                                        <?php elseif ($p['del']): ?>
                                        <button type="button" class="pdf-del-btn" onclick="<?= $p['del'] ?>" title="Ištrinti">&times;</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($nust_merged) && !$gali_ikelti): ?>
                                    <span style="color:var(--text-secondary);font-size:11px;">-</span>
                                <?php endif; ?>
                                <?php if ($gali_ikelti): ?>
                                    <button type="button" class="btn-ikelti-pdf" onclick="atidartiIkelimoPdf(<?= $o['id'] ?>, 'nustatymu')" title="Įkelti nustatymų protokolą" data-testid="button-ikelti-nustatymu-<?= $o['id'] ?>">↑</button>
                                <?php endif; ?>
                                </span>
                            </td>
                            <td class="uzs-cell-actions">
                                <div class="actions">
                                    <?php if (currentUser()['role'] === 'administratorius'): ?>
                                    <button type="button" class="btn btn-danger btn-sm" data-testid="button-delete-order-<?= $o['id'] ?>"
                                        onclick="atidarytiTrynima(<?= $o['id'] ?>, '<?= h($o['uzsakymo_numeris']) ?>')">Trinti</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="empty-state"><p>Nėra užsakymų</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
function filterOrders() {
    const search = document.getElementById('orderSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#ordersTable tbody tr[data-order-nr]');
    let visible = 0;
    rows.forEach(row => {
        const nr = row.getAttribute('data-order-nr') || '';
        if (!search || nr.includes(search)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
}


function togglePdfDropdown(btn) {
    var dropdown = btn.closest('.pdf-dropdown');
    var wasOpen = dropdown.classList.contains('open');
    document.querySelectorAll('.pdf-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
    if (!wasOpen) {
        dropdown.classList.add('open');
        var list = dropdown.querySelector('.pdf-dropdown-list');
        var rect = btn.getBoundingClientRect();
        list.style.top = (rect.bottom + 4) + 'px';
        list.style.left = Math.max(8, rect.right - list.offsetWidth) + 'px';
    }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.pdf-dropdown')) {
        document.querySelectorAll('.pdf-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
    }
});

</script>

<div class="modal-overlay" id="createOrderModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas užsakymas</h3>
            <button class="modal-close" onclick="closeModal('createOrderModal')" aria-label="Uždaryti">&times;</button>
        </div>
        <form method="POST" action="/uzsakymai.php?grupe=<?= urlencode($filtro_grupe) ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="grupe" value="<?= h($filtro_grupe) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Užsakymo numeris</label>
                    <input type="text" class="form-control" name="uzsakymo_numeris" required data-testid="input-order-number">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakovas</label>
                        <div class="select-with-add">
                            <select class="form-control" name="uzsakovas_id" data-testid="select-client" data-qa-select="uzsakovas">
                                <option value="">-- Pasirinkite --</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['uzsakovas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-quick-add" onclick="toggleQuickAdd('uzsakovas','create')" title="Pridėti naują užsakovą" data-testid="button-add-client-create">+</button>
                        </div>
                        <div class="quick-add-row" id="qa-uzsakovas-create" style="display:none;">
                            <input type="text" class="form-control" placeholder="Naujo užsakovo pavadinimas" data-testid="input-new-client-create">
                            <button type="button" class="btn btn-sm btn-primary" onclick="saveQuickAdd('uzsakovas','create')" data-testid="button-save-new-client-create">Pridėti</button>
                            <span class="quick-add-error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Objektas</label>
                        <div class="select-with-add">
                            <select class="form-control" name="objektas_id" data-testid="select-object" data-qa-select="objektas">
                                <option value="">-- Pasirinkite --</option>
                                <?php foreach ($objects as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= h($o['pavadinimas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-quick-add" onclick="toggleQuickAdd('objektas','create')" title="Pridėti naują objektą" data-testid="button-add-object-create">+</button>
                        </div>
                        <div class="quick-add-row" id="qa-objektas-create" style="display:none;">
                            <input type="text" class="form-control" placeholder="Naujo objekto pavadinimas" data-testid="input-new-object-create">
                            <button type="button" class="btn btn-sm btn-primary" onclick="saveQuickAdd('objektas','create')" data-testid="button-save-new-object-create">Pridėti</button>
                            <span class="quick-add-error"></span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kiekis</label>
                    <input type="number" class="form-control" name="kiekis" min="1" value="1" required data-testid="input-quantity">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createOrderModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-order">Sukurti</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (currentUser()['role'] === 'administratorius'): ?>
<div class="modal-overlay" id="deleteOrderModal" data-testid="modal-delete-order">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header" style="background: #fef2f2; border-bottom: 2px solid #fecaca;">
            <h3 style="color: #dc2626;">Užsakymo trynimas</h3>
            <button class="modal-close" onclick="closeModal('deleteOrderModal')" aria-label="Uždaryti" data-testid="button-close-delete-modal">&times;</button>
        </div>
        <form method="POST" id="deleteOrderForm" data-testid="form-delete-order">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteOrderId">
            <div class="modal-body">
                <div class="delete-warning" data-testid="delete-warning">
                    <div class="delete-warning-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <p style="font-weight: 600; font-size: 15px; margin-bottom: 8px;">Šis veiksmas negrįžtamas!</p>
                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">
                        Bus ištrintas užsakymas <strong id="deleteOrderNrDisplay" style="color: var(--text-primary);"></strong> ir visi susiję duomenys:
                        gaminiai, funkciniai bandymai, komponentai, dielektriniai bandymai, saugiklių įdėklai ir kiti įrašai.
                    </p>
                    <div class="form-group" style="margin-top: 16px;">
                        <label style="font-size: 13px; font-weight: 600;">Patvirtinimui įveskite užsakymo numerį:</label>
                        <input type="text" name="patvirtinimo_nr" id="deleteConfirmInput" class="form-control"
                            placeholder="Įveskite užsakymo Nr." autocomplete="off"
                            data-testid="input-delete-confirm" style="margin-top: 6px; border-color: #fecaca;">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteOrderModal')" data-testid="button-cancel-delete">Atšaukti</button>
                <button type="submit" class="btn btn-danger" id="deleteConfirmBtn" disabled data-testid="button-confirm-delete">Ištrinti užsakymą</button>
            </div>
        </form>
    </div>
</div>
<script>
var _deleteNr = '';
function atidarytiTrynima(id, nr) {
    _deleteNr = nr;
    document.getElementById('deleteOrderId').value = id;
    document.getElementById('deleteOrderNrDisplay').textContent = 'Nr. ' + nr;
    document.getElementById('deleteConfirmInput').value = '';
    document.getElementById('deleteConfirmBtn').disabled = true;
    openModal('deleteOrderModal');
    setTimeout(function(){ document.getElementById('deleteConfirmInput').focus(); }, 100);
}
document.getElementById('deleteConfirmInput').addEventListener('input', function() {
    document.getElementById('deleteConfirmBtn').disabled = (this.value.trim() !== _deleteNr.trim());
});
</script>
<?php endif; ?>

<style>
.select-with-add { display: flex; gap: 6px; align-items: center; }
.select-with-add select { flex: 1; min-width: 0; }
.btn-quick-add {
    width: 34px; height: 34px; min-width: 34px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--bg-card, #fff); color: var(--primary);
    font-size: 1.2rem; font-weight: 700; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .15s, color .15s;
}
.btn-quick-add:hover { background: var(--primary); color: #fff; }
.quick-add-row {
    display: flex; gap: 6px; align-items: center; margin-top: 6px;
}
.quick-add-row input { flex: 1; min-width: 0; }
.quick-add-row .btn { white-space: nowrap; }
.quick-add-error { color: #dc2626; font-size: 0.8rem; }
.btn-ikelti-pdf {
    background: none;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    padding: 1px 6px;
    color: var(--text-secondary, #6b7280);
    line-height: 1.4;
    transition: background .15s, color .15s, border-color .15s;
}
.btn-ikelti-pdf:hover {
    background: var(--bg-hover, #f3f4f6);
    color: var(--primary, #2563eb);
    border-color: var(--primary, #2563eb);
}
#ikeltiPdfGaminioSelect {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 6px;
    font-size: 13px;
    background: var(--bg-card, #fff);
    color: var(--text-primary, #111827);
}
</style>
<script>
function toggleQuickAdd(type, prefix) {
    var row = document.getElementById('qa-' + type + '-' + prefix);
    if (!row) return;
    var visible = row.style.display !== 'none';
    row.style.display = visible ? 'none' : 'flex';
    if (!visible) {
        var inp = row.querySelector('input');
        if (inp) { inp.value = ''; inp.focus(); }
        row.querySelector('.quick-add-error').textContent = '';
    }
}

function addOptionToAllSelects(type, id, name) {
    document.querySelectorAll('select[data-qa-select="' + type + '"]').forEach(function(sel) {
        var exists = Array.from(sel.options).some(function(o) { return o.value == id; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name;
            sel.appendChild(opt);
        }
        var options = Array.from(sel.options).slice(1);
        options.sort(function(a, b) { return a.textContent.localeCompare(b.textContent, 'lt'); });
        while (sel.options.length > 1) sel.remove(1);
        options.forEach(function(o) { sel.appendChild(o); });
    });
}

function ensureOptionExists(type, id, name) {
    document.querySelectorAll('select[data-qa-select="' + type + '"]').forEach(function(sel) {
        var exists = Array.from(sel.options).some(function(o) { return o.value == id; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name;
            sel.appendChild(opt);
            var options = Array.from(sel.options).slice(1);
            options.sort(function(a, b) { return a.textContent.localeCompare(b.textContent, 'lt'); });
            while (sel.options.length > 1) sel.remove(1);
            options.forEach(function(o) { sel.appendChild(o); });
        }
    });
}

async function saveQuickAdd(type, prefix) {
    var row = document.getElementById('qa-' + type + '-' + prefix);
    if (!row) return;
    var inp = row.querySelector('input');
    var errSpan = row.querySelector('.quick-add-error');
    var btn = row.querySelector('button');
    var name = (inp.value || '').trim();
    errSpan.textContent = '';

    if (!name) {
        errSpan.textContent = 'Įveskite pavadinimą';
        inp.focus();
        return;
    }

    btn.disabled = true;
    btn.textContent = '...';

    try {
        var resp = await fetch('/api/quick_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, name: name })
        });
        if (!resp.ok) { errSpan.textContent = 'Serverio klaida (' + resp.status + ')'; btn.disabled = false; btn.textContent = 'Pridėti'; return; }
        var data;
        try { data = await resp.json(); } catch(pe) { errSpan.textContent = 'Neteisingas atsakymas'; btn.disabled = false; btn.textContent = 'Pridėti'; return; }

        if (data.success) {
            addOptionToAllSelects(type, data.id, data.name);
            var thisSelect = row.closest('.form-group').querySelector('select[data-qa-select="' + type + '"]');
            if (thisSelect) thisSelect.value = data.id;
            row.style.display = 'none';
            inp.value = '';
        } else {
            if (data.existing_id) {
                ensureOptionExists(type, data.existing_id, name);
                var thisSelect = row.closest('.form-group').querySelector('select[data-qa-select="' + type + '"]');
                if (thisSelect) thisSelect.value = data.existing_id;
                row.style.display = 'none';
                inp.value = '';
            } else {
                errSpan.textContent = data.error || 'Klaida';
            }
        }
    } catch (e) {
        errSpan.textContent = 'Tinklo klaida';
    }

    btn.disabled = false;
    btn.textContent = 'Pridėti';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.closest('.quick-add-row')) {
        e.preventDefault();
        var row = e.target.closest('.quick-add-row');
        var btn = row.querySelector('button');
        if (btn) btn.click();
    }
});

function switchEditTab(tab) {
    var uzsakymasTab = document.getElementById('editTabUzsakymas');
    var imoneTab = document.getElementById('editTabImone');
    var tabs = document.querySelectorAll('.edit-modal-tabs .edit-tab');
    if (!uzsakymasTab || !imoneTab) return;

    if (tab === 'imone') {
        uzsakymasTab.style.display = 'none';
        imoneTab.style.display = '';
        tabs[0].style.borderBottomColor = 'transparent';
        tabs[0].style.color = 'var(--text-secondary,#6b7280)';
        tabs[0].style.fontWeight = '500';
        tabs[1].style.borderBottomColor = 'var(--primary,#3b82f6)';
        tabs[1].style.color = 'var(--primary,#3b82f6)';
        tabs[1].style.fontWeight = '600';
    } else {
        uzsakymasTab.style.display = '';
        imoneTab.style.display = 'none';
        tabs[0].style.borderBottomColor = 'var(--primary,#3b82f6)';
        tabs[0].style.color = 'var(--primary,#3b82f6)';
        tabs[0].style.fontWeight = '600';
        tabs[1].style.borderBottomColor = 'transparent';
        tabs[1].style.color = 'var(--text-secondary,#6b7280)';
        tabs[1].style.fontWeight = '500';
    }
}

async function issaugotiImonesNustatymus() {
    var form = document.getElementById('imones-form');
    if (!form) return;
    var msgDiv = document.getElementById('imones-msg');
    var btn = form.querySelector('[data-testid="button-save-company"]');
    btn.disabled = true;
    btn.textContent = 'Saugoma...';
    msgDiv.style.display = 'none';

    try {
        var fd = new FormData(form);
        var resp = await fetch('/imones_nustatymai.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        if (!resp.ok) throw new Error('Server error');
        var data = await resp.json();
        msgDiv.style.display = 'block';
        if (data.ok) {
            msgDiv.style.background = '#d1fae5';
            msgDiv.style.color = '#065f46';
            msgDiv.textContent = (data.message || 'Išsaugota') + ' Puslapio atnaujinimas...';
            setTimeout(function(){ location.reload(); }, 800);
            return;
        } else {
            msgDiv.style.background = '#fee2e2';
            msgDiv.style.color = '#991b1b';
            msgDiv.textContent = data.message || 'Klaida';
        }
    } catch (e) {
        msgDiv.style.display = 'block';
        msgDiv.style.background = '#fee2e2';
        msgDiv.style.color = '#991b1b';
        msgDiv.textContent = 'Tinklo klaida';
    }
    btn.disabled = false;
    btn.textContent = 'Išsaugoti';
}

function deleteIkeltasPdf(failasId) {
    if (!confirm('Ar tikrai norite ištrinti šį įkeltą PDF failą?')) return;
    var f = document.getElementById('delete-ikeltas-pdf-form');
    f.querySelector('[name="failas_id"]').value = failasId;
    f.submit();
}

function deletePdf(gaminioId, pdfType) {
    var labels = {paso: 'MT paso', dielektriniu: 'Dielektrinių bandymų', funkciniu: 'Funkcinių bandymų'};
    if (!confirm('Ar tikrai norite ištrinti ' + (labels[pdfType] || '') + ' PDF failą?')) return;
    var f = document.getElementById('delete-pdf-form');
    f.querySelector('[name="gaminio_id"]').value = gaminioId;
    f.querySelector('[name="pdf_type"]').value = pdfType;
    f.submit();
}

function deleteGvxDok(id, btn) {
    if (!confirm('Ar tikrai norite ištrinti šį PDF dokumentą?')) return;
    var csrf = document.querySelector('meta[name="csrf"]') ? document.querySelector('meta[name="csrf"]').content
             : (document.getElementById('csrf-token') ? document.getElementById('csrf-token').value : '');
    // fallback: read from any hidden csrf field on page
    if (!csrf) { var cf = document.querySelector('[name="_csrf"]'); if (cf) csrf = cf.value; }
    var row = btn ? btn.closest('span') : null;
    fetch('/gvx_dok_trinti.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf) + '&id=' + encodeURIComponent(id)
    }).then(function(r){ return r.json(); }).then(function(d){
        if (d.ok) {
            if (row) { location.reload(); } else { location.reload(); }
        } else { alert('Klaida: ' + (d.klaida || 'Nežinoma klaida')); }
    }).catch(function(){ alert('Ryšio klaida'); });
}

function atidartiIkelimoPdf(uzsakymoId, pdfType) {
    var tipai = {paso: 'paso PDF', dielektriniu: 'Dielektrinių bandymų PDF', funkciniu: 'Funkcinių bandymų PDF', nustatymu: 'nustatymų protokolą'};
    document.getElementById('ikeltiPdfUzsModalTitle').textContent = 'Įkelti ' + (tipai[pdfType] || 'PDF');
    document.getElementById('ikeltiPdfType').value = pdfType;
    document.getElementById('ikeltiPdfFailasInput').value = '';

    var grupe = <?= json_encode($filtro_grupe) ?>;
    document.getElementById('ikeltiPdfRedirect').value = '/uzsakymai.php?grupe=' + encodeURIComponent(grupe);

    var gaminioWrap   = document.getElementById('ikeltiPdfGaminioWrap');
    var gaminioSelect = document.getElementById('ikeltiPdfGaminioSelect');
    var gaminioIdInput = document.getElementById('ikeltiPdfGaminioId');
    var submitBtn     = document.getElementById('ikeltiPdfSubmitBtn');
    var loadingEl     = document.getElementById('ikeltiPdfLoading');

    gaminioSelect.innerHTML = '';
    gaminioWrap.style.display = 'block';
    if (loadingEl) loadingEl.style.display = 'block';
    submitBtn.disabled = true;

    fetch('/MT/gaminiai_uzsakymui.php?uzsakymo_id=' + encodeURIComponent(uzsakymoId))
        .then(function(r) { return r.json(); })
        .then(function(gaminiai) {
            if (loadingEl) loadingEl.style.display = 'none';
            submitBtn.disabled = false;
            if (!Array.isArray(gaminiai) || gaminiai.length === 0) {
                gaminioSelect.innerHTML = '<option value="">— nėra gaminių —</option>';
                gaminioSelect.style.display = 'block';
                gaminioIdInput.value = '';
                gaminioWrap.style.display = 'block';
                submitBtn.disabled = true;
                return;
            }
            if (gaminiai.length === 1) {
                gaminioIdInput.value = gaminiai[0].id;
                gaminioWrap.style.display = 'none';
            } else {
                gaminiai.forEach(function(g) {
                    var opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = (g.gaminio_numeris || '—') + (g.pavadinimas ? ' — ' + g.pavadinimas : '');
                    gaminioSelect.appendChild(opt);
                });
                gaminioIdInput.value = gaminiai[0].id;
                gaminioSelect.style.display = 'block';
                gaminioSelect.onchange = function() {
                    gaminioIdInput.value = this.value;
                };
                gaminioWrap.style.display = 'block';
            }
        })
        .catch(function() {
            if (loadingEl) loadingEl.style.display = 'none';
            gaminioSelect.innerHTML = '<option value="">Klaida kraunant gaminius</option>';
            gaminioSelect.style.display = 'block';
            gaminioIdInput.value = '';
            gaminioWrap.style.display = 'block';
            submitBtn.disabled = true;
        });

    openModal('ikeltiPdfUzsModal');
}
</script>
<form id="delete-pdf-form" method="POST" action="/uzsakymai.php?grupe=<?= urlencode($filtro_grupe) ?>" style="display:none;">
    <input type="hidden" name="action" value="delete_pdf">
    <input type="hidden" name="gaminio_id" value="">
    <input type="hidden" name="pdf_type" value="">
</form>
<form id="delete-ikeltas-pdf-form" method="POST" action="/uzsakymai.php?grupe=<?= urlencode($filtro_grupe) ?>" style="display:none;">
    <input type="hidden" name="action" value="delete_ikeltas_pdf">
    <input type="hidden" name="failas_id" value="">
</form>

<div class="modal-overlay" id="ikeltiPdfUzsModal">
    <div class="modal" style="max-width: 460px;">
        <div class="modal-header">
            <h3 id="ikeltiPdfUzsModalTitle">Įkelti PDF</h3>
            <button class="modal-close" onclick="closeModal('ikeltiPdfUzsModal')" aria-label="Uždaryti">&times;</button>
        </div>
        <form method="POST" action="/MT/ikelti_pdf.php" enctype="multipart/form-data">
            <input type="hidden" name="gaminio_id" id="ikeltiPdfGaminioId" value="">
            <input type="hidden" name="pdf_type" id="ikeltiPdfType" value="">
            <input type="hidden" name="redirect_url" id="ikeltiPdfRedirect" value="">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="modal-body" style="display:flex; flex-direction:column; gap:14px;">
                <div id="ikeltiPdfGaminioWrap">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Gaminys</label>
                    <div id="ikeltiPdfLoading" style="font-size:12px; color:var(--text-secondary);">Kraunama...</div>
                    <select id="ikeltiPdfGaminioSelect" style="display:none; width:100%; padding:6px 10px; border:1px solid var(--border,#e5e7eb); border-radius:6px; font-size:13px; background:var(--bg-card,#fff); color:var(--text-primary,#111827);"></select>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">PDF failas</label>
                    <input type="file" id="ikeltiPdfFailasInput" name="pdf_failas" accept=".pdf" required
                           style="width:100%; padding:6px; border:1px solid var(--border,#e5e7eb); border-radius:6px; font-size:13px; box-sizing:border-box;">
                    <div style="font-size:11px; color:var(--text-secondary); margin-top:4px;">Tik .pdf formato failai, max 20 MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('ikeltiPdfUzsModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" id="ikeltiPdfSubmitBtn">Įkelti</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
