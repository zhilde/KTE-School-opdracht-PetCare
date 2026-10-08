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

    // Veld moet een geldige datum zijn in de vorm jjjj-mm-dd (zo stuurt een <input type="date"> hem)
    public function date(string $field, string $message): void
    {
        $value = $this->get($field);

        if ($value === '') {
            return;
        }

        // ! = tijd op 00:00 zetten. Daarna terug omzetten en vergelijken,
        // zo wordt bv. 2026-02-31 (bestaat niet) ook afgekeurd.
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->addError($field, $message);
        }
    }

    // Veld moet een heel getal zijn tussen $min en $max
    public function integer(string $field, int $min, int $max, string $message): void
    {
        $value = $this->get($field);

        if ($value === '') {
            return;
        }

        $options = ['options' => ['min_range' => $min, 'max_range' => $max]];
        if (filter_var($value, FILTER_VALIDATE_INT, $options) === false) {
            $this->addError($field, $message);
        }
    }

    // Is er al een fout bij dit veld? Handig om extra controles over te slaan.
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    // Stopt met 422 als er fouten zijn gevonden
    public function validate(): void
    {
        if (!empty($this->errors)) {
            throw new ApiException('Controleer de ingevulde velden.', 422, $this->errors);
        }
    }

    // Fout toevoegen. Public, zodat een controller ook eigen controles kan doen
    // (bv. "einddatum moet na startdatum liggen").
    // Per veld bewaren we alleen de eerste fout, dat is duidelijker voor de gebruiker.
    public function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
