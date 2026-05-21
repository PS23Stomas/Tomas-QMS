<?php
/**
 * Duomenų bazės ryšio klasė
 *
 * Tai tarsi "tiltas" tarp programos ir duomenų bazės.
 * Kai programa nori gauti arba įrašyti duomenis, ji naudoja šią klasę
 * kad prisijungtų prie PostgreSQL duomenų bazės.
 *
 * Naudoja Singleton šabloną — tai reiškia, kad visoje programoje
 * egzistuoja TIK VIENAS prisijungimas, o ne naujas kiekvienam puslapiui.
 * Tai greičiau ir taupo resursus.
 */
class Database {
    /** Saugoma prisijungimo kopija — sukuriama tik vieną kartą */
    private static ?PDO $instance = null;

    /**
     * Grąžina prisijungimą prie duomenų bazės.
     *
     * Kaip veikia: jei prisijungimas jau sukurtas — grąžina tą patį.
     * Jei dar ne — perskaito duomenų bazės adresą iš aplinkos kintamojo
     * DATABASE_URL ir sukuria naują prisijungimą.
     *
     * Jei DATABASE_URL nėra nustatytas — programa sustoja su klaida.
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
