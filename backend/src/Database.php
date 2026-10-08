<?php

// Maakt de verbinding met de database (MariaDB/MySQL) via PDO.
// Er wordt maar 1 verbinding per request gemaakt, die wordt hergebruikt.
class Database
{
    // De verbinding wordt hier bewaard. null = nog geen verbinding gemaakt.
    private static ?PDO $pdo = null;

    // Geeft de verbinding terug. De eerste keer wordt hij gemaakt, daarna hergebruikt.
    // Gebruik: $stmt = Database::connect()->prepare('SELECT ...');
    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            // DSN = de "adresregel" voor de database: soort, server, poort, database naam en tekenset
            $dsn = 'mysql:host=' . Config::get('DB_HOST') . ';port=' . Config::get('DB_PORT', '3306')
                . ';dbname=' . Config::get('DB_NAME') . ';charset=utf8mb4';

            self::$pdo = self::createPdo($dsn);
        }

        return self::$pdo;
    }

    // Verbinding zonder database, nodig voor setup.php (de database bestaat dan misschien nog niet)
    public static function connectWithoutDatabase(): PDO
    {
        $dsn = 'mysql:host=' . Config::get('DB_HOST') . ';port=' . Config::get('DB_PORT', '3306') . ';charset=utf8mb4';

        return self::createPdo($dsn);
    }

    // Maakt de PDO-verbinding met de juiste instellingen
    private static function createPdo(string $dsn): PDO
    {
        $pdo = new PDO($dsn, Config::get('DB_USER'), Config::get('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // fout = exception, zo missen we geen fouten
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // rijen als array met kolomnamen, bv. $row['name']
            PDO::ATTR_EMULATE_PREPARES => false,               // echte prepared statements (tegen SQL-injectie)
        ]);

        // XAMPP staat niet in strict mode, dan worden foute waardes stil aangepast (bv. -1 wordt 0).
        // Met strict mode geeft de database dan een fout en wordt er niks opgeslagen.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }
}
