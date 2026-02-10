<?php
/**
 * Duomenų bazės prisijungimo klasė (Singleton šablonas)
 * Naudoja PDO su PostgreSQL. Analizuoja DATABASE_URL aplinkos kintamąjį.
 */
class Database {
    private static ?PDO $instance = null;

    /**
     * Grąžina PDO prisijungimą prie duomenų bazės.
     * Jei prisijungimas dar nesukurtas, išanalizuoja DATABASE_URL ir sukuria naują PDO instanciją.
     */
    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $database_url = getenv('DATABASE_URL');
        if (!$database_url) {
            die('DATABASE_URL not set');
        }

        $parsed = parse_url($database_url);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? 5432;
        $dbname = ltrim($parsed['path'], '/');
        $user = $parsed['user'];
        $pass = $parsed['pass'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        self::$instance = new PDO($dsn, $user, $pass, $options);
        return self::$instance;
    }
}
