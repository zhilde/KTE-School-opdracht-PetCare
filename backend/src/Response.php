<?php

// Stuurt JSON terug naar React (TE4).
// Alle antwoorden van de API gaan via deze klasse, zo zijn ze altijd hetzelfde opgebouwd.
class Response
{
    // Stuurt gegevens als JSON terug.
    // Voorbeeld: Response::json(['message' => 'Opgeslagen.'], 201);
    public static function json(mixed $data, int $status = 200): void
    {
        // Statuscode zetten, bv. 200 = gelukt, 404 = niet gevonden
        http_response_code($status);

        // Vertellen aan de browser dat het antwoord JSON is
        header('Content-Type: application/json; charset=utf-8');

        // Array omzetten naar JSON-tekst. UNESCAPED_UNICODE zodat é en ë leesbaar blijven.
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    // Foutmelding altijd in dezelfde vorm: { "message": "...", "errors": { veld: melding } }
    // "errors" wordt alleen meegestuurd als er fouten per veld zijn.
    public static function error(string $message, int $status, array $errors = []): void
    {
        $body = ['message' => $message];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        self::json($body, $status);
    }
}
