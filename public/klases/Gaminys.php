<?php
/**
 * Gaminio valdymo klasė
 *
 * Gaminys — tai vienas pagamintas produktas (pvz. viena MT transformatorinė).
 * Kiekvienas gaminys priklauso užsakymui ir turi: numerį, tipą, protokolo numerį
 * bei PDF dokumentų kopijas (pasas, dielektrinių bandymų protokolas, funkcinių bandymų protokolas).
 *
 * Ši klasė atsakinga už:
 * - Gaminių kūrimą, paiešką, atnaujinimą ir trynimą
 * - Gaminio tipų valdymą (pvz. "MT 630/10", "USN-250")
 * - Ryšio tarp gaminio ir jo užsakymo palaikymą
 *
 * Naudojama: uzsakymai.php, MT/*.php, sinchronizuoti.php ir kitur.
 */
class Gaminys {
    /** Duomenų bazės ryšys */
    private $conn;

    /**
     * Sukuria gaminio objektą.
     * Jei duomenų bazės ryšys nepateiktas — naudojamas globalus ryšys.
     */
    public function __construct($db = null) {
        $this->conn = $db ?? Database::getConnection();
    }

    /**
     * Grąžina užsakymo ID pagal užsakymo numerį (pvz. "2024-001").
     * Jei užsakymas nerastas — grąžina 0.
     */
    public static function gautiUzsakymoId(PDO $pdo, string $numeris): int {
        $stmt = $pdo->prepare("SELECT id FROM uzsakymai WHERE uzsakymo_numeris = ? LIMIT 1");
        $stmt->execute([$numeris]);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    /**
     * Grąžina gaminio tipų sąrašą (ID ir pavadinimas), surikiuotą pagal abėcėlę.
     * Naudojama formuose kai reikia pasirinkti gaminio tipą iš sąrašo.
     */
    public static function gautiGaminioTipus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, gaminio_tipas FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll();
    }

    /**
     * Grąžina visų gaminio tipų pilną informaciją (ID, pavadinimas, grupė, atitikmenų kodas).
     * Skiriasi nuo gautiGaminioTipus() tuo, kad grąžina VISUS stulpelius.
     */
    public static function gautiVisusTipus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT * FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prideda naują gaminio tipą su nurodytu pavadinimu ir grupe.
     * Grąžina null jei viskas gerai, arba klaidos pranešimą jei nepavyko.
     *
     * @param string $tipas  Gaminio tipo pavadinimas (pvz. "MT 630/10 kV")
     * @param string $grupe  Gaminio grupė (pvz. "MT", "USN", "SI-04")
     * @return string|null   null = sėkmė, tekstas = klaidos pranešimas
     */
    public static function pridetiTipa(PDO $pdo, string $tipas, string $grupe): ?string {
        try {
            $stmt = $pdo->prepare("INSERT INTO gaminio_tipai (gaminio_tipas, grupe) VALUES (?, ?)");
            $stmt->execute([$tipas, $grupe]);
            return null;
        } catch (PDOException $e) {
            return "Klaida pridedant gaminio tipą: " . $e->getMessage();
        }
    }

    /**
     * Tikrina ar visi užsakymo gaminiai turi priskirtus gaminio numerius.
     * Gaminio numeris — tai unikalus serijos numeris (pvz. "MT-2024-001-001").
     * Grąžina true jei VISI gaminiai turi numerius, false jei bent vienas neturi.
     */
    public static function tikrintiNumerius(PDO $pdo, int $uzsakymo_id): bool {
        $stmt = $pdo->prepare("SELECT gaminio_numeris FROM gaminiai WHERE uzsakymo_id = ?");
        $stmt->execute([$uzsakymo_id]);
        $gaminiai = $stmt->fetchAll();
        foreach ($gaminiai as $g) {
            if (empty($g['gaminio_numeris'])) return false;
        }
        return true;
    }

    /**
     * Grąžina visus gaminius pagal užsakymo ID kartu su gaminio tipo pavadinimu.
     * Gaminiai surikiuoti pagal gaminio numerį didėjančia tvarka.
     */
    public static function gautiPagalUzsakyma(PDO $pdo, int $uzsakymo_id): array {
        $stmt = $pdo->prepare("
            SELECT g.*, gt.gaminio_tipas 
            FROM gaminiai g 
            LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id 
            WHERE g.uzsakymo_id = ?
            ORDER BY g.gaminio_numeris ASC
        ");
        $stmt->execute([$uzsakymo_id]);
        return $stmt->fetchAll();
    }

    /**
     * Įrašo arba atnaujina gaminio tipo pavadinimą pagal užsakymo numerį.
     *
     * Kaip veikia:
     * 1. Suranda užsakymą pagal numerį
     * 2. Jei užsakymas neturi gaminio — sukuria naują tuščią gaminį
     * 3. Atnaujina gaminio tipo pavadinimą (arba sukuria naują tipą jei tokio nėra)
     *
     * @return bool true jei pavyko, false jei užsakymas nerastas
     */
    public function irasytiPilnaPavadinima(string $uzsakymo_numeris, string $pavadinimas): bool {
        $sqlUzsak = "SELECT id FROM uzsakymai WHERE TRIM(uzsakymo_numeris) = TRIM(?)";
        $stmtUzsak = $this->conn->prepare($sqlUzsak);
        $stmtUzsak->execute([$uzsakymo_numeris]);
        $uzsakymas = $stmtUzsak->fetch();
        if (!$uzsakymas) return false;

        $uzsakymo_id = $uzsakymas['id'];

        $sqlGaminys = "SELECT g.id, g.gaminio_tipas_id FROM gaminiai g WHERE g.uzsakymo_id = ? ORDER BY g.id DESC LIMIT 1";
        $stmtGaminys = $this->conn->prepare($sqlGaminys);
        $stmtGaminys->execute([$uzsakymo_id]);
        $gaminys = $stmtGaminys->fetch();

        if (!$gaminys) {
            $stmtCreate = $this->conn->prepare("INSERT INTO gaminiai (uzsakymo_id) VALUES (?) RETURNING id");
            $stmtCreate->execute([$uzsakymo_id]);
            $new_gid = $stmtCreate->fetchColumn();
            $gaminys = ['id' => $new_gid, 'gaminio_tipas_id' => null];
        }

        if ($gaminys && $gaminys['gaminio_tipas_id']) {
            $sqlExists = "SELECT id FROM gaminio_tipai WHERE id = ?";
            $stmtExists = $this->conn->prepare($sqlExists);
            $stmtExists->execute([$gaminys['gaminio_tipas_id']]);
            if ($stmtExists->fetch()) {
                $sqlUpd = "UPDATE gaminio_tipai SET gaminio_tipas = ?, grupe = COALESCE(NULLIF(grupe, ''), 'MT') WHERE id = ?";
                $stmtUpd = $this->conn->prepare($sqlUpd);
                $stmtUpd->execute([$pavadinimas, $gaminys['gaminio_tipas_id']]);
                return true;
            }
        }

        $sqlCheck = "SELECT id FROM gaminio_tipai WHERE gaminio_tipas = ? AND gaminio_tipas != ''";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$pavadinimas]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $tipas_id = $existing['id'];
        } else {
            $sqlInsert = "INSERT INTO gaminio_tipai (gaminio_tipas, grupe) VALUES (?, 'MT')";
            $stmtInsert = $this->conn->prepare($sqlInsert);
            $stmtInsert->execute([$pavadinimas]);
            $tipas_id = $this->conn->lastInsertId();
        }

        $sqlUpdate = "UPDATE gaminiai SET gaminio_tipas_id = ? WHERE uzsakymo_id = ?";
        $stmtUpdate = $this->conn->prepare($sqlUpdate);
        return $stmtUpdate->execute([$tipas_id, $uzsakymo_id]);
    }

    /**
     * Grąžina gaminio tipo pavadinimą pagal gaminio ID.
     * Jei gaminio tipas nerastas — grąžina "Nežinomas".
     */
    public function gautiPavadinimaPagalGaminioId($gaminio_id) {
        $sql = "SELECT gt.gaminio_tipas 
                FROM gaminiai g 
                JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id 
                WHERE g.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$gaminio_id]);
        $rez = $stmt->fetch();
        return $rez['gaminio_tipas'] ?? 'Nežinomas';
    }

    /**
     * Grąžina gaminio tipo pavadinimą pagal užsakymo numerį.
     * Ieško naujausiame to užsakymo gaminyje.
     * Jei nerastas — grąžina tuščią eilutę.
     */
    public function gautiPilnaPavadinima($uzsakymo_numeris) {
        $sql = "SELECT id FROM uzsakymai WHERE TRIM(uzsakymo_numeris) = TRIM(?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$uzsakymo_numeris]);
        $uzsakymas = $stmt->fetch();
        if (!$uzsakymas) return '';

        $uzsakymo_id = $uzsakymas['id'];

        $sql = "SELECT gt.gaminio_tipas 
                FROM gaminiai g 
                JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id 
                WHERE g.uzsakymo_id = ?
                ORDER BY g.id DESC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$uzsakymo_id]);
        $rez = $stmt->fetch();

        $pav = $rez['gaminio_tipas'] ?? '';
        return trim($pav);
    }

    /**
     * Grąžina paskutinį (naujausią pagal ID) gaminį iš nurodyto užsakymo.
     * Jei užsakymas nerastas arba neturi gaminių — grąžina null.
     */
    public function gautiPaskutiniGamini($uzsakymo_numeris) {
        $sql = "SELECT id FROM uzsakymai WHERE uzsakymo_numeris = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$uzsakymo_numeris]);
        $uzsakymas = $stmt->fetch();

        if (!$uzsakymas) return null;
        $uzsakymo_id = $uzsakymas['id'];

        $sql = "SELECT * FROM gaminiai WHERE uzsakymo_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$uzsakymo_id]);
        $rez = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rez ?: null;
    }

    /**
     * Grąžina gaminio duomenis pagal jo ID.
     * Grąžina pagrindinius laukus (be didelių BYTEA PDF duomenų).
     * Jei nerastas arba ID tuščias — grąžina null.
     */
    public function gautiPagalId($id) {
        if (!$id) return null;
        $sql = "SELECT id, uzsakymo_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr, atitikmuo_kodas, mt_paso_failas, mt_dielektriniu_failas, mt_funkciniu_failas FROM gaminiai WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $rez = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rez ?: null;
    }

    /**
     * Sukuria naują gaminį duomenų bazėje.
     * Reikalingas užsakymo ID, gaminio numeris ir gaminio tipo ID.
     * Grąžina true jei pavyko, false jei klaida.
     */
    public function sukurti($uzsakymo_id, $gaminio_numeris, $gaminio_tipas_id) {
        try {
            $sql = "INSERT INTO gaminiai (uzsakymo_id, gaminio_numeris, gaminio_tipas_id)
                    VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$uzsakymo_id, $gaminio_numeris, $gaminio_tipas_id]);
        } catch (PDOException $e) {
            error_log("Klaida kuriant gaminį: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atnaujina gaminio duomenis pagal jo ID.
     * Galima perduoti bet kokį laukų rinkinį kaip masyvą ['laukas' => 'reikšmė'].
     * Jei laukų masyvas tuščias — nieko nedaro ir grąžina false.
     *
     * Pavyzdys: $gaminys->updateGamini(5, ['protokolo_nr' => 'P-2024-001'])
     */
    public function updateGamini($id, $laukeliai = []) {
        if (empty($laukeliai)) return false;

        $dalys = [];
        $reiksmes = [];

        foreach ($laukeliai as $laukas => $reiksme) {
            $dalys[] = "$laukas = ?";
            $reiksmes[] = $reiksme;
        }

        $reiksmes[] = $id;
        $sql = "UPDATE gaminiai SET " . implode(', ', $dalys) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($reiksmes);
    }

    /**
     * Ištrina gaminį iš duomenų bazės pagal jo ID.
     * Dėmesio: kartu ištrinami ir susiję duomenys (komponentai, bandymai ir kt.)
     * nes duomenų bazėje nustatyti CASCADE trynimo ryšiai.
     */
    public function istrintiGamini($id) {
        $sql = "DELETE FROM gaminiai WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }
}
