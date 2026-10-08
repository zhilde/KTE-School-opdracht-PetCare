<?php

// API voor inloggen, uitloggen en "wie ben ik" (TE5).
// Een controller ontvangt het verzoek, controleert de invoer en stuurt een antwoord terug.
// Het echte inloggen gebeurt in de klasse Auth.
class AuthController
{
    // POST /api/login
    // Verwacht JSON: { "email": "...", "password": "..." }
    public function login(): void
    {
        $data = Request::body();

        // is_string: als iemand geen tekst stuurt (bv. een lijst) zien we het als leeg
        $email = is_string($data['email'] ?? null) ? trim($data['email']) : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        // Verplichte velden controleren. Fouten verzamelen we per veld,
        // zodat React ze onder het juiste invoerveld kan laten zien.
        $errors = [];
        if ($email === '') {
            $errors['email'] = 'E-mailadres is verplicht.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vul een geldig e-mailadres in.';
        }
        if ($password === '') {
            $errors['password'] = 'Wachtwoord is verplicht.';
        }
        if ($errors) {
            // 422 = de ingevulde gegevens kloppen niet
            throw new ApiException('Controleer de ingevulde velden.', 422, $errors);
        }

        $user = Auth::attempt($email, $password);

        // Zelfde melding bij fout e-mail en fout wachtwoord, zo verraden we niet welke accounts bestaan
        if ($user === null) {
            throw new ApiException('E-mail of wachtwoord is onjuist.', 401);
        }

        Response::json(['message' => 'Je bent ingelogd.', 'user' => $user]);
    }

    // POST /api/logout
    public function logout(): void
    {
        Auth::logout();
        Response::json(['message' => 'Je bent uitgelogd.']);
    }

    // GET /api/me – React gebruikt dit om te kijken of iemand nog is ingelogd
    // (bv. na het verversen van de pagina). Niet ingelogd = 401.
    public function me(): void
    {
        Response::json(['user' => Auth::requireLogin()]);
    }
}
