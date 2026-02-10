<?php
class GaminioTipas {
    public static function gautiVisus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT * FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function prideti(PDO $pdo, string $tipas, string $grupe): ?string {
        try {
            $stmt = $pdo->prepare("INSERT INTO gaminio_tipai (gaminio_tipas, grupe) VALUES (?, ?)");
            $stmt->execute([$tipas, $grupe]);
            return null;
        } catch (PDOException $e) {
            return "Klaida pridedant gaminio tipą: " . $e->getMessage();
        }
    }
}
