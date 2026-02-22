<?php
/**
 * ==========================================================================
 *  GAMINIŲ VALDYMO PUSLAPIS
 * ==========================================================================
 *
 *  Paskirtis:  Rodyti visų gaminių sąrašą, leisti kurti naujus gaminius
 *              ir šalinti esamus.
 *
 *  Ką šis failas daro:
 *    1. Priima vartotojo veiksmus (naujo gaminio kūrimas arba trynimas)
 *    2. Gauna duomenis iš duomenų bazės (gaminiai, užsakymai, tipai)
 *    3. Atvaizduoja lentelę su visais gaminiais
 *    4. Rodo modalinį langą naujo gaminio kūrimui
 *
 *  Duomenų bazės lentelės, su kuriomis dirba:
 *    - gaminiai         → pagrindinė gaminių lentelė
 *    - gaminio_tipai    → gaminių tipų klasifikatorius
 *    - uzsakymai        → užsakymai, prie kurių priskirti gaminiai
 *    - mt_komponentai   → gaminio komponentai (trinami kartu su gaminiu)
 *
 *  Naudojamos klasės ir funkcijos:
 *    - config.php       → DB prisijungimas ($pdo), sesija, h() funkcija
 *    - Gaminys::gautiVisusTipus() → gaminio tipų sąrašas iš DB
 *    - openModal() / closeModal() → modalinio lango valdymas (app.js)
 * ==========================================================================
 */

// --------------------------------------------------------------------------
//  1 DALIS: PARUOŠIMAS
//  Įkeliame konfigūraciją (DB prisijungimą, sesiją) ir tikriname,
//  ar vartotojas yra prisijungęs. Neprisijungęs bus nukreiptas į login.php
// --------------------------------------------------------------------------
require_once __DIR__ . '/includes/config.php';
requireLogin();

// Puslapio pavadinimas - rodomas naršyklės kortelėje ir header.php antraštėje
$page_title = 'Gaminiai';

// Pranešimo kintamasis - rodomas po sėkmingo veiksmo (sukūrimo/trynimo)
$message = '';


// --------------------------------------------------------------------------
//  2 DALIS: FORMOS DUOMENŲ APDOROJIMAS (POST UŽKLAUSOS)
//  Kai vartotojas paspaudžia "Sukurti" arba "Trinti" mygtuką,
//  naršyklė siunčia POST užklausą su duomenimis. Šioje dalyje
//  apdorojame tuos duomenis ir įrašome/ištriname iš duomenų bazės.
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Nustatome kokį veiksmą vartotojas nori atlikti
    $action = $_POST['action'] ?? '';

    // --- NAUJO GAMINIO SUKŪRIMAS ---
    // Vartotojas užpildė formą modaliniame lange ir paspaudė "Sukurti"
    if ($action === 'create') {

        // Paruošiame SQL užklausą su parametrais (apsauga nuo SQL injekcijų)
        $stmt = $pdo->prepare('
            INSERT INTO gaminiai 
                (uzsakymo_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr, atitikmuo_kodas) 
            VALUES 
                (:uzsakymo_id, :gaminio_numeris, :gaminio_tipas_id, :protokolo_nr, :atitikmuo_kodas)
        ');

        // Vykdome užklausą su formoje įvestais duomenimis
        $stmt->execute([
            'uzsakymo_id'     => $_POST['uzsakymo_id'] ?: null,        // Užsakymo ID (gali būti tuščias)
            'gaminio_numeris' => $_POST['gaminio_numeris'] ?? '',       // Gaminio numeris (privalomas)
            'gaminio_tipas_id'=> $_POST['gaminio_tipas_id'] ?: null,   // Gaminio tipo ID (gali būti tuščias)
            'protokolo_nr'    => $_POST['protokolo_nr'] ?? '',          // Protokolo numeris
            'atitikmuo_kodas' => $_POST['atitikmuo_kodas'] ?? '',       // Atitikties kodas
        ]);

        $message = 'Gaminys sukurtas sėkmingai.';

    // --- GAMINIO ŠALINIMAS ---
    // Vartotojas paspaudė "Trinti" mygtuką prie konkretaus gaminio
    } elseif ($action === 'delete') {

        $id = $_POST['id'] ?? null;
        $user = currentUser();
        if (($user['role'] ?? '') !== 'admin') {
            $error = 'Tik administratorius gali trinti gaminius.';
        } elseif ($id) {
            // Pirmiausia ištriname susijusius komponentus (nes jie susieti per gaminio_id)
            // Jei to nepadarytume - DB mestų klaidą dėl foreign key apribojimo
            $pdo->prepare('DELETE FROM mt_komponentai WHERE gaminio_id = :id')->execute(['id' => $id]);

            // Tada ištriname patį gaminį
            $pdo->prepare('DELETE FROM gaminiai WHERE id = :id')->execute(['id' => $id]);

            $message = 'Gaminys ištrintas.';
        }
    }
}


// --------------------------------------------------------------------------
//  3 DALIS: DUOMENŲ GAVIMAS IŠ DUOMENŲ BAZĖS
//  Čia gauname visus reikalingus duomenis, kurie bus rodomi puslapyje:
//  - Užsakymų sąrašas (formos select elementui)
//  - Gaminių tipų sąrašas (formos select elementui)
//  - Visų gaminių sąrašas su papildoma informacija (lentelei)
// --------------------------------------------------------------------------

// Užsakymų sąrašas - naudojamas naujo gaminio formoje (pasirinkti užsakymą)
$orders = $pdo->query('SELECT id, uzsakymo_numeris FROM uzsakymai ORDER BY id DESC')->fetchAll();

// Gaminių tipų sąrašas - naudojamas naujo gaminio formoje (pasirinkti tipą)
$types = Gaminys::gautiVisusTipus($pdo);

// Visų gaminių sąrašas su sujungtomis lentelėmis:
//   - gaminio_tipai (gt) → kad gautume gaminio tipo pavadinimą ir grupę
//   - uzsakymai (u)      → kad gautume užsakymo numerį
// LEFT JOIN naudojamas tam, kad gaminiai be užsakymo ar tipo irgi būtų rodomi
$products = $pdo->query('
    SELECT 
        g.*,                    -- visi gaminių lentelės stulpeliai
        gt.gaminio_tipas,       -- gaminio tipo pavadinimas (pvz. "MT-630")
        gt.grupe,               -- gaminio grupė (pvz. "MT")
        u.uzsakymo_numeris      -- užsakymo numeris (pvz. "UZS-2026-001")
    FROM gaminiai g
    LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id
    LEFT JOIN uzsakymai u ON g.uzsakymo_id = u.id
    ORDER BY g.id DESC
')->fetchAll();


// --------------------------------------------------------------------------
//  4 DALIS: PUSLAPIO ANTRAŠTĖ
//  Įkeliame bendrą viršutinę dalį (navigacija, šoninė juosta, stiliai)
// --------------------------------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>


<!-- ======================================================================
     5 DALIS: PRANEŠIMAS VARTOTOJUI
     Jei buvo atliktas veiksmas (sukūrimas/trynimas), rodome žalią pranešimą
     ====================================================================== -->
<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>


<!-- ======================================================================
     6 DALIS: GAMINIŲ LENTELĖ
     Rodome visus gaminius lentelės pavidalu su stulpeliais:
     ID | Gaminio Nr. | Užsakymo Nr. | Tipas | Grupė | Protokolo Nr. | Atitikties kodas | Veiksmai
     ====================================================================== -->
<div class="card">
    <div class="card-header">
        <!-- Lentelės antraštė su gaminių skaičiumi ir mygtuku naujam gaminiui -->
        <span class="card-title">Visi gaminiai (<?= count($products) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('createProductModal')" data-testid="button-new-product">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Naujas gaminys
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table>
                <!-- Lentelės stulpelių pavadinimai -->
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gaminio Nr.</th>
                        <th>Užsakymo Nr.</th>
                        <th>Tipas</th>
                        <th>Grupė</th>
                        <th>Protokolo Nr.</th>
                        <th>Atitikties kodas</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>

                <!-- Lentelės duomenys - kiekvienas gaminys = viena eilutė -->
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $p): ?>
                        <tr data-testid="row-product-<?= $p['id'] ?>">
                            <td><?= $p['id'] ?></td>

                            <!-- Gaminio numeris (paryškintas) -->
                            <td style="font-weight: 500;"><?= h($p['gaminio_numeris'] ?: '-') ?></td>

                            <!-- Užsakymo numeris - paspaudus nukreipia į užsakymo detalių puslapį -->
                            <td><a href="/uzsakymai.php?id=<?= $p['uzsakymo_id'] ?>" style="color: var(--primary);"><?= h($p['uzsakymo_numeris'] ?? '-') ?></a></td>

                            <td><?= h($p['gaminio_tipas'] ?? '-') ?></td>
                            <td><?= h($p['grupe'] ?? '-') ?></td>
                            <td><?= h($p['protokolo_nr'] ?: '-') ?></td>
                            <td><?= h($p['atitikmuo_kodas'] ?: '-') ?></td>

                            <!-- Trynimo mygtukas su patvirtinimo dialogu (tik admin) -->
                            <td>
                                <?php if ((currentUser()['role'] ?? '') === 'admin'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Ar tikrai norite ištrinti šį gaminį?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" data-testid="button-delete-product-<?= $p['id'] ?>">Trinti</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Rodoma, kai nėra nei vieno gaminio duomenų bazėje -->
                        <tr><td colspan="8" class="empty-state"><p>Nėra gaminių</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ======================================================================
     7 DALIS: MODALINIS LANGAS - NAUJO GAMINIO KŪRIMAS
     Atsidaro paspaudus "Naujas gaminys" mygtuką.
     Formoje yra šie laukai:
       - Gaminio numeris (privalomas)
       - Užsakymas (pasirinkimas iš sąrašo)
       - Gaminio tipas (pasirinkimas iš sąrašo)
       - Protokolo Nr.
       - Atitikties kodas
     Paspaudus "Sukurti" - forma siunčia POST užklausą į šį patį failą,
     kuri apdorojama 2 DALYJE (viršuje).
     ====================================================================== -->
<div class="modal-overlay" id="createProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Naujas gaminys</h3>
            <button class="modal-close" onclick="closeModal('createProductModal')">&times;</button>
        </div>

        <!-- Forma siunčia duomenis POST metodu į šį patį puslapį (gaminiai.php) -->
        <form method="POST">
            <!-- Paslėptas laukas nurodo, kad tai yra kūrimo veiksmas -->
            <input type="hidden" name="action" value="create">

            <div class="modal-body">
                <!-- Gaminio numeris - privalomas laukas -->
                <div class="form-group">
                    <label class="form-label">Gaminio numeris</label>
                    <input type="text" class="form-control" name="gaminio_numeris" required data-testid="input-product-number">
                </div>

                <!-- Užsakymas ir tipas - du pasirinkimo laukai greta vienas kito -->
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Užsakymas</label>
                        <select class="form-control" name="uzsakymo_id" data-testid="select-order">
                            <option value="">-- Pasirinkite --</option>
                            <!-- Generuojame pasirinkimo sąrašą iš užsakymų duomenų bazėje -->
                            <?php foreach ($orders as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= h($o['uzsakymo_numeris'] ?: 'ID: ' . $o['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gaminio tipas</label>
                        <select class="form-control" name="gaminio_tipas_id" data-testid="select-type">
                            <option value="">-- Pasirinkite --</option>
                            <!-- Generuojame pasirinkimo sąrašą iš gaminio tipų duomenų bazėje -->
                            <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= h($t['gaminio_tipas']) ?> (<?= h($t['grupe']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Protokolo numeris ir atitikties kodas -->
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Protokolo Nr.</label>
                        <input type="text" class="form-control" name="protokolo_nr" data-testid="input-protocol">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atitikties kodas</label>
                        <input type="text" class="form-control" name="atitikmuo_kodas" data-testid="input-code">
                    </div>
                </div>
            </div>

            <!-- Formos apačia su mygtukais -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProductModal')">Atšaukti</button>
                <button type="submit" class="btn btn-primary" data-testid="button-create-product">Sukurti</button>
            </div>
        </form>
    </div>
</div>


<!-- ======================================================================
     8 DALIS: PUSLAPIO APAČIA
     Įkeliame bendrą apatinę dalį (JavaScript failai, uždarymo žymės)
     ====================================================================== -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
