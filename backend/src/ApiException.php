<?php

// Een fout die we netjes aan de gebruiker mogen laten zien, met een HTTP-statuscode.
// Voorbeeld: throw new ApiException('Geen toegang', 403);
//
// Wordt opgevangen in public/index.php en daar omgezet naar een JSON-foutmelding.
// Veelgebruikte statuscodes:
//   400 = ongeldig verzoek, 401 = niet ingelogd, 403 = geen toegang,
//   404 = niet gevonden, 409 = conflict (bv. geen plek), 422 = validatiefout
class ApiException extends Exception
{
    // De HTTP-statuscode, bv. 403
    private int $status;

    // Foutmeldingen per veld, bv. ['name' => 'Naam is verplicht.']
    // React laat deze meldingen onder het juiste invoerveld zien.
    private array $errors;

    public function __construct(string $message, int $status = 400, array $errors = [])
    {
        // De melding geven we door aan de standaard Exception-klasse van PHP
        parent::__construct($message);
        $this->status = $status;
        $this->errors = $errors;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
