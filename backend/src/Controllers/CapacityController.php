<?php

// API voor capaciteit beheren (FE3). Alleen voor de beheerder.
class CapacityController
{
    // Hoeveel dagen het overzicht laat zien
    private const OVERVIEW_DAYS = 14;

    // GET /api/capacities?shelter_id=1&from=2026-10-08
    // Overzicht van 14 dagen: per datum en diersoort de plekken, bezet en vrij
    public function index(): void
    {
        Auth::requireRole('admin');

        $v = new Validator($_GET);
        $v->required('shelter_id', 'Kies een opvang.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een opvang uit de lijst.');
        $v->required('from', 'Kies een datum.');
        $v->date('from', 'Vul een geldige datum in.');
        $v->validate();

        $shelterId = (int) $v->get('shelter_id');
        $from = $v->get('from');
        $to = date('Y-m-d', strtotime($from . ' +' . (self::OVERVIEW_DAYS - 1) . ' days'));

        $this->findShelter($shelterId);

        // Voor elke diersoort de dagen ophalen, alleen dagen waar capaciteit is ingesteld tonen
        $rows = [];
        foreach (PetController::SPECIES as $species) {
            foreach (CapacityService::perDay($shelterId, $species, $from, $to) as $day) {
                if ($day['has_capacity']) {
                    $rows[] = ['species' => $species] + $day;
                }
            }
        }

        // Sorteren op datum, daarna op soort
        usort($rows, fn ($a, $b) => [$a['date'], $a['species']] <=> [$b['date'], $b['species']]);

        Response::json(['from' => $from, 'to' => $to, 'capacities' => $rows]);
    }

    // PUT /api/capacities – capaciteit instellen voor 1 opvang + datum + diersoort
    // Verwacht JSON: { "shelter_id": 1, "date": "2026-11-01", "species": "hond", "max_slots": 10 }
    // Bestaat de combinatie al, dan wordt het aantal aangepast (geen dubbele regels).
    public function save(): void
    {
        Auth::requireRole('admin');

        $v = new Validator(Request::body());
        $v->required('shelter_id', 'Kies een opvang.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een opvang uit de lijst.');
        $v->required('date', 'Kies een datum.');
        $v->date('date', 'Vul een geldige datum in.');
        $v->required('species', 'Kies een diersoort.');
        $v->in('species', PetController::SPECIES, 'Kies een diersoort uit de lijst.');
        $v->required('max_slots', 'Vul het aantal plekken in.');
        $v->integer('max_slots', 0, 500, 'Het aantal plekken moet een heel getal zijn van 0 t/m 500.');

        $date = $v->get('date');
        if (!$v->hasError('date') && $date < date('Y-m-d')) {
            $v->addError('date', 'De datum mag niet in het verleden liggen.');
        }
        $v->validate();

        $shelterId = (int) $v->get('shelter_id');
        $species = $v->get('species');
        $maxSlots = (int) $v->get('max_slots');
        $shelter = $this->findShelter($shelterId);

        // Niet minder plekken instellen dan er al bezet zijn, anders zit de opvang "overvol"
        $booked = CapacityService::perDay($shelterId, $species, $date, $date)[0]['booked'];
        if ($maxSlots < $booked) {
            throw new ApiException("Er zijn op deze datum al " . $this->slots($booked) . " bezet. Je kunt niet minder plekken instellen.", 409);
        }

        // Bestaat de regel al (unieke sleutel shelter_id + date + species)? Dan bijwerken in plaats van toevoegen.
        $stmt = Database::connect()->prepare(
            'INSERT INTO capacities (shelter_id, date, species, max_slots) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE max_slots = VALUES(max_slots)'
        );
        $stmt->execute([$shelterId, $date, $species, $maxSlots]);

        Response::json(['message' => "Capaciteit opgeslagen: {$shelter['name']}, " . date('d-m-Y', strtotime($date)) . ", $species: " . $this->slots($maxSlots) . '.']);
    }

    // 1 -> "1 plek", 3 -> "3 plekken"
    private function slots(int $count): string
    {
        return $count === 1 ? '1 plek' : "$count plekken";
    }

    // Zoekt een opvang op id. Bestaat hij niet, dan een fout bij het veld.
    private function findShelter(int $id): array
    {
        $stmt = Database::connect()->prepare('SELECT id, name FROM shelters WHERE id = ?');
        $stmt->execute([$id]);
        $shelter = $stmt->fetch();

        if (!$shelter) {
            throw new ApiException('Controleer de ingevulde velden.', 422, ['shelter_id' => 'Kies een opvang uit de lijst.']);
        }

        return $shelter;
    }
}
