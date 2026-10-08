<?php

// Inloggen, uitloggen en controleren wie er is ingelogd en welke rol hij heeft (TE5).
//
// Hoe werkt een sessie?
// Na het inloggen bewaart PHP het user_id in $_SESSION (op de server).
// De browser krijgt alleen een cookie met een willekeurige sessie-id.
// Bij elk volgend verzoek stuurt de browser die cookie mee, zo weet PHP wie het is.
class Auth
{
    // De ingelogde gebruiker, bewaard zodat we de database maar 1 keer per request hoeven te vragen
    private static ?array $user = null;

    // Start de sessie met veilige instellingen voor de cookie
    public static function startSession(): void
    {
        session_set_cookie_params([
            'httponly' => true,     // JavaScript kan de cookie niet lezen (beschermt tegen XSS)
            'samesite' => 'Strict', // cookie wordt niet meegestuurd vanaf andere websites (beschermt tegen CSRF)
            'secure' => !empty($_SERVER['HTTPS']), // bij HTTPS alleen via HTTPS versturen
        ]);
        session_start();
    }

    // Probeert in te loggen. Geeft de gebruiker terug als e-mail en wachtwoord kloppen, anders null.
    public static function attempt(string $email, string $password): ?array
    {
        // Prepared statement: het ? wordt veilig ingevuld, dus geen SQL-injectie mogelijk
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify vergelijkt het ingevulde wachtwoord met de hash uit de database
        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        // Nieuwe sessie-id na inloggen (tegen session fixation:
        // een aanvaller kan dan geen sessie-id van tevoren klaarzetten)
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];

        return self::publicData($user);
    }

    // Uitloggen: sessie leegmaken en verwijderen
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }

    // De ingelogde gebruiker, of null als niemand is ingelogd
    public static function user(): ?array
    {
        if (self::$user === null && isset($_SESSION['user_id'])) {
            // Uit de database halen, zodat een gewijzigde rol meteen telt
            $stmt = Database::connect()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            // Bestaat de gebruiker niet meer? Dan is niemand ingelogd.
            self::$user = $user ? self::publicData($user) : null;
        }

        return self::$user;
    }

    // Stopt met 401 als niemand is ingelogd. Zet dit bovenaan elk endpoint waarvoor je moet inloggen.
    public static function requireLogin(): array
    {
        $user = self::user();

        if ($user === null) {
            throw new ApiException('Je bent niet ingelogd.', 401);
        }

        return $user;
    }

    // Stopt met 403 als de gebruiker niet een van de rollen heeft.
    // Voorbeeld: Auth::requireRole('employee', 'admin');  = alleen medewerker of beheerder
    // string ...$roles betekent: je mag zoveel rollen meegeven als je wilt
    public static function requireRole(string ...$roles): array
    {
        $user = self::requireLogin();

        if (!in_array($user['role'], $roles, true)) {
            throw new ApiException('Je hebt geen toegang tot deze functie.', 403);
        }

        return $user;
    }

    // Alleen de gegevens die de browser mag zien.
    // Nooit het wachtwoord (de hash) naar de browser sturen.
    private static function publicData(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }
}
