<?php
require_once 'Database.php';

class Gamys1 {
    private $pdo;

    public function __construct($db = null) {
        $this->pdo = $db ?? Database::getConnection();
    }

    // Sukuria naują gaminį
    public function sukurti($uzsakymo_id, $gaminio_numeris, $gaminio_tipas_id) {
        $sql = "INSERT INTO gaminiai (uzsakymo_id, gaminio_numeris, gaminio_tipas_id) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$uzsakymo_id, $gaminio_numeris, $gaminio_tipas_id]);
    }

    // Grąžina užsakymo ID pagal numerį
    public static function gautiUzsakymoId(PDO $pdo, string $numeris): int {
        $stmt = $pdo->prepare("SELECT id FROM uzsakymai WHERE uzsakymo_numeris = ? LIMIT 1");
        $stmt->execute([$numeris]);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    // Grąžina visus gaminio tipus
    public static function gautiGaminioTipus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, gaminio_tipas FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll();
    }

    // Tikrina, ar visi gaminiai turi numerius
    public static function tikrintiNumerius(PDO $pdo, int $uzsakymo_id): bool {
        $stmt = $pdo->prepare("SELECT gaminio_numeris FROM gaminiai WHERE uzsakymo_id = ?");
        $stmt->execute([$uzsakymo_id]);
        $rezultatai = $stmt->fetchAll();

        foreach ($rezultatai as $g) {
            if (empty($g['gaminio_numeris'])) return false;
        }
        return true;
    }

    // Grąžina gaminius pagal užsakymo ID
    public static function gautiPagalUzsakyma(PDO $pdo, int $uzsakymo_id): array {
        $stmt = $pdo->prepare("SELECT g.*, gt.gaminio_tipas FROM gaminiai g LEFT JOIN gaminio_tipai gt ON g.gaminio_tipas_id = gt.id WHERE g.uzsakymo_id = ?");
        $stmt->execute([$uzsakymo_id]);
        return $stmt->fetchAll();
    }

    // ✅ Naujas metodas – Įrašo pilną gaminio pavadinimą į `uzsakymai` lentelę
    public function irasytiPilnaPavadinima($uzsakymo_numeris, $pavadinimas): bool {
        $sql = "UPDATE uzsakymai SET gaminio_pavadinimas = ? WHERE uzsakymo_numeris = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$pavadinimas, $uzsakymo_numeris]);
    }

    // ✅ Naujas metodas – Grąžina esamą pavadinimą
    public function gautiPilnaPavadinima($uzsakymo_numeris): string {
        $sql = "SELECT gaminio_pavadinimas FROM uzsakymai WHERE uzsakymo_numeris = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$uzsakymo_numeris]);
        $row = $stmt->fetch();
        return $row['gaminio_pavadinimas'] ?? '';
    }
}
