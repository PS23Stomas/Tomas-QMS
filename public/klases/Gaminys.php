<?php
/**
 * Gaminio (produkto) valdymo klasė - CRUD operacijos ir tipų valdymas
 */
class Gaminys {
    private $conn;

    public function __construct($db = null) {
        $this->conn = $db ?? Database::getConnection();
    }

    /** Gauna užsakymo ID pagal užsakymo numerį */
    public static function gautiUzsakymoId(PDO $pdo, string $numeris): int {
        $stmt = $pdo->prepare("SELECT id FROM uzsakymai WHERE uzsakymo_numeris = ? LIMIT 1");
        $stmt->execute([$numeris]);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    /** Gauna gaminio tipų sąrašą (id ir pavadinimas), surikiuotą pagal abėcėlę */
    public static function gautiGaminioTipus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, gaminio_tipas FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll();
    }

    /** Gauna visų gaminio tipų pilną informaciją */
    public static function gautiVisusTipus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT * FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Prideda naują gaminio tipą su grupe. Grąžina null sėkmės atveju arba klaidos pranešimą */
    public static function pridetiTipa(PDO $pdo, string $tipas, string $grupe): ?string {
        try {
            $stmt = $pdo->prepare("INSERT INTO gaminio_tipai (gaminio_tipas, grupe) VALUES (?, ?)");
            $stmt->execute([$tipas, $grupe]);
            return null;
        } catch (PDOException $e) {
            return "Klaida pridedant gaminio tipą: " . $e->getMessage();
        }
    }

    /** Tikrina, ar visi užsakymo gaminiai turi priskiritus gaminio numerius */
    public static function tikrintiNumerius(PDO $pdo, int $uzsakymo_id): bool {
        $stmt = $pdo->prepare("SELECT gaminio_numeris FROM gaminiai WHERE uzsakymo_id = ?");
        $stmt->execute([$uzsakymo_id]);
        $gaminiai = $stmt->fetchAll();
        foreach ($gaminiai as $g) {
            if (empty($g['gaminio_numeris'])) return false;
        }
        return true;
    }

    /** Gauna visus gaminius pagal užsakymo ID su gaminio tipo pavadinimu */
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

    /** Įrašo arba atnaujina pilną gaminio pavadinimą pagal užsakymo numerį */
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

    /** Gauna gaminio tipo pavadinimą pagal gaminio ID */
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

    /** Gauna pilną gaminio pavadinimą pagal užsakymo numerį */
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

    /** Gauna paskutinį (naujausią) gaminį pagal užsakymo numerį */
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

    /** Gauna gaminį pagal jo ID */
    public function gautiPagalId($id) {
        if (!$id) return null;
        $sql = "SELECT id, uzsakymo_id, gaminio_numeris, gaminio_tipas_id, protokolo_nr, atitikmuo_kodas, mt_paso_failas, mt_dielektriniu_failas, mt_funkciniu_failas FROM gaminiai WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $rez = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rez ?: null;
    }

    /** Sukuria naują gaminį su užsakymo ID, gaminio numeriu ir tipo ID */
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

    /** Atnaujina gaminio duomenis pagal ID su nurodytais laukeliais */
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

    /** Ištrina gaminį pagal jo ID */
    public function istrintiGamini($id) {
        $sql = "DELETE FROM gaminiai WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }
}
