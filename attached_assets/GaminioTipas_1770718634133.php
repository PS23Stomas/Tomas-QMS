<?php

class GaminioTipas
{
    // Gauti visus gaminio tipus
    public static function gautiVisus(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT * FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pridėti naują gaminio tipą
    public static function prideti(PDO $pdo, string $tipas, string $grupe): ?string
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO gaminio_tipai (gaminio_tipas, grupe) VALUES (?, ?)");
            $stmt->execute([$tipas, $grupe]);
            return null; // Sėkmė – grąžinama be klaidos
        } catch (PDOException $e) {
            return "Klaida pridedant gaminio tipą: " . $e->getMessage();
        }
    }
}
