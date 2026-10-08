<?php

// Controleert de invoer van de gebruiker (server-side validatie).
// Fouten worden per veld verzameld, zodat React ze onder het juiste veld kan tonen.
//
// Voorbeeld:
//   $v = new Validator(Request::body());
//   $v->required('name', 'Naam is verplicht.');
//   $v->maxLength('name', 100, 'Naam mag maximaal 100 tekens zijn.');
//   $v->validate();              // stopt met 422 als er fouten zijn
//   $name = $v->get('name');     // de opgeschoonde waarde
class Validator
{
    // De gegevens die zijn meegestuurd
    private array $data;

    // Gevonden fouten, bv. ['name' => 'Naam is verplicht.']
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // Geeft de waarde van een veld als tekst, zonder spaties aan het begin/eind.
    // Geen tekst meegestuurd (bv. een lijst)? Dan zien we het als leeg.
    public function get(string $field): string
    {
        $value = $this->data[$field] ?? '';

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) ? trim($value) : '';
    }

    // Veld moet ingevuld zijn
    public function required(string $field, string $message): void
    {
        if ($this->get($field) === '') {
            $this->addError($field, $message);
        }
    }

    // Veld mag niet langer zijn dan $max tekens
    public function maxLength(string $field, int $max, string $message): void
    {
        if (mb_strlen($this->get($field)) > $max) {
            $this->addError($field, $message);
        }
    }

    // Veld moet een van de toegestane waardes zijn, bv. een diersoort uit de lijst
    public function in(string $field, array $allowed, string $message): void
    {
        $value = $this->get($field);

        // Leeg wordt al door required() gemeld
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->addError($field, $message);
        }
    }

    // Stopt met 422 als er fouten zijn gevonden
    public function validate(): void
    {
        if (!empty($this->errors)) {
            throw new ApiException('Controleer de ingevulde velden.', 422, $this->errors);
        }
    }

    // Per veld bewaren we alleen de eerste fout, dat is duidelijker voor de gebruiker
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
