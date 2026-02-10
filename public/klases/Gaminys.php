<?php
class Gaminys {
    public static function gautiUzsakymoId(PDO $pdo, string $numeris): int {
        $stmt = $pdo->prepare("SELECT id FROM uzsakymai WHERE uzsakymo_numeris = ? LIMIT 1");
        $stmt->execute([$numeris]);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    public static function gautiGaminioTipus(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, gaminio_tipas FROM gaminio_tipai ORDER BY gaminio_tipas ASC");
        return $stmt->fetchAll();
    }

    public static function tikrintiNumerius(PDO $pdo, int $uzsakymo_id): bool {
        $stmt = $pdo->prepare("SELECT gaminio_numeris FROM gaminiai WHERE uzsakymo_id = ?");
        $stmt->execute([$uzsakymo_id]);
        $gaminiai = $stmt->fetchAll();
        foreach ($gaminiai as $g) {
            if (empty($g['gaminio_numeris'])) return false;
        }
        return true;
    }

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
}
