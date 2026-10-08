<?php

// Leest de gegevens die React meestuurt (JSON).
// Gebruik in een controller: $data = Request::body();  daarna bv. $data['email']
class Request
{
    public static function body(): array
    {
        // Alleen JSON accepteren. Een andere website kan via een gewoon formulier geen JSON
        // naar onze API sturen, dit helpt dus ook tegen CSRF-aanvallen.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            throw new ApiException('Verzoek moet JSON zijn.', 415);
        }

        // php://input = de ruwe tekst die is meegestuurd.
        // json_decode met true maakt er een array van (in plaats van een object).
        $data = json_decode(file_get_contents('php://input'), true);

        // Kapotte JSON of geen object/lijst? Dan weigeren.
        if (!is_array($data)) {
            throw new ApiException('Ongeldige gegevens verstuurd.', 400);
        }

        return $data;
    }
}
