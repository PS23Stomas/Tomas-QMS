<?php 
require_once 'Database.php';

class Uzsakymas {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function egzistuoja($numeris) {
        $sql = "SELECT COUNT(*) FROM uzsakymai WHERE uzsakymo_numeris = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numeris]);
        return $stmt->fetchColumn() > 0;
    }

    public function sukurti($numeris, $uzsakovas_id, $vartotojas_id) {
        $sql = "INSERT INTO uzsakymai (uzsakymo_numeris, uzsakovas_id, vartotojas_id) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$numeris, $uzsakovas_id, $vartotojas_id]);
    }

    public function sukurtiSuPapildomais($numeris, $uzsakovas_id, $vartotojas_id, $objektas_id, $gaminio_rusis_id, $kiekis) {
        $sql = "INSERT INTO uzsakymai (uzsakymo_numeris, uzsakovas_id, vartotojas_id, objektas_id, gaminiu_rusis_id, kiekis)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numeris, $uzsakovas_id, $vartotojas_id, $objektas_id, $gaminio_rusis_id, $kiekis]);
    }

    public function gautiVisus() {
        $sql = "SELECT * FROM uzsakymai ORDER BY id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gautiPagalNumeri($numeris) {
        $sql = "SELECT * FROM uzsakymai WHERE uzsakymo_numeris = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numeris]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function atnaujintiUzsakova($numeris, $naujas_uzsakovas_id) {
        $sql = "UPDATE uzsakymai SET uzsakovas_id = ? WHERE uzsakymo_numeris = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$naujas_uzsakovas_id, $numeris]);
    }

    public function istrinti($numeris) {
        $sql = "DELETE FROM uzsakymai WHERE uzsakymo_numeris = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$numeris]);
    }

    public function egzistuojaPagalNumeriIrUzsakova($numeris, $uzsakovas_id) {
        $sql = "SELECT COUNT(*) FROM uzsakymai WHERE uzsakymo_numeris = ? AND uzsakovas_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numeris, $uzsakovas_id]);
        return $stmt->fetchColumn() > 0;
    }

    public function pridetiNaujaUzsakova($pavadinimas) {
        $sql = "INSERT INTO uzsakovai (uzsakovas) VALUES (?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pavadinimas]);
        return $this->pdo->lastInsertId();
    }

    public function gautiVisusUzsakovus() {
        $sql = "SELECT id, uzsakovas FROM uzsakovai ORDER BY uzsakovas ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gautiVisusObjektus() {
        $sql = "SELECT id, pavadinimas FROM objektai ORDER BY pavadinimas ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
        public function gautiUzsakovaPagalId($id) {
    $sql = "SELECT uzsakovas FROM uzsakovai WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


    public function gautiVisusGaminioTipus() {
        $sql = "SELECT id, gaminio_tipas FROM gaminio_tipai ORDER BY gaminio_tipas ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pridetiNaujaObjekta($pavadinimas) {
        $sql = "INSERT INTO objektai (pavadinimas) VALUES (?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pavadinimas]);
        return $this->pdo->lastInsertId();
    }

    public static function atnaujintiUzsakovaPagalVarda(PDO $pdo, string $numeris, string $naujas_uzsakovas): bool {
        $stmt = $pdo->prepare("SELECT id FROM uzsakovai WHERE uzsakovas = ? LIMIT 1");
        $stmt->execute([$naujas_uzsakovas]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $uzsakovas_id = (int)$row['id'];
        } else {
            $ins = $pdo->prepare("INSERT INTO uzsakovai (uzsakovas) VALUES (?)");
            $ins->execute([$naujas_uzsakovas]);
            $uzsakovas_id = (int)$pdo->lastInsertId();
        }
        
        $upd = $pdo->prepare("UPDATE uzsakymai SET uzsakovas_id = ? WHERE uzsakymo_numeris = ?");
        return $upd->execute([$uzsakovas_id, $numeris]);
    }
}
