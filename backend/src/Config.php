<?php

// Leest de instellingen uit het .env-bestand.
// In .env staan gegevens die per computer anders zijn (bv. database wachtwoord).
// Daarom staat .env niet in Git, alleen het voorbeeld .env.example.
class Config
{
    // Hier worden alle instellingen bewaard, bv. ['DB_HOST' => '127.0.0.1']
    // static = hoort bij de klasse zelf, dus overal in de code hetzelfde
    private static array $values = [];

    // Leest het .env-bestand regel voor regel in
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException('.env bestand niet gevonden. Kopieer .env.example naar .env');
        }

        // Elke regel is NAAM=waarde. Lege regels en regels met # (commentaar) slaan we over.
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            // Splitsen bij het eerste =, zodat een = in het wachtwoord gewoon kan
            [$key, $value] = explode('=', $line, 2);
            self::$values[trim($key)] = trim($value);
        }
    }

    // Een instelling ophalen. Bestaat hij niet, dan krijg je $default terug.
    // Voorbeeld: Config::get('DB_PORT', '3306')
    public static function get(string $key, string $default = ''): string
    {
        return self::$values[$key] ?? $default;
    }
}
