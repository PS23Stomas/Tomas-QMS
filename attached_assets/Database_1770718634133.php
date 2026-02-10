<?php
class Database {
    public static function getConnection(): PDO {
        $host = getenv('PGHOST');
        $port = getenv('PGPORT');
        $db   = getenv('PGDATABASE');
        $user = getenv('PGUSER');
        $pass = getenv('PGPASSWORD');

        $dsn = "pgsql:host=$host;port=$port;dbname=$db";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        return new PDO($dsn, $user, $pass, $options);
    }
}
