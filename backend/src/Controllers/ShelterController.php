<?php

// API voor opvanglocaties en beschikbaarheid (FE2)
class ShelterController
{
    // GET /api/shelters – lijst met alle opvangen (voor keuzelijsten)
    public function index(): void
    {
        Auth::requireLogin();

        $stmt = Database::connect()->query('SELECT id, name, address FROM shelters ORDER BY name');

        Response::json(['shelters' => $stmt->fetchAll()]);
    }

    // GET /api/availability?species=hond&start=2026-11-01&end=2026-11-03
    // Welke opvangen nemen deze diersoort aan, en is er op ELKE dag plek?
    public function availability(): void
    {
        Auth::requireLogin();

        // Bij een GET-verzoek staan de gegevens in de URL ($_GET)
        $v = new Validator($_GET);
        $v->required('species', 'Kies een diersoort.');
        $v->in('species', PetController::SPECIES, 'Kies een diersoort uit de lijst.');
        $v->required('start', 'Kies een startdatum.');
        $v->date('start', 'Vul een geldige startdatum in.');
        $v->required('end', 'Kies een einddatum.');
        $v->date('end', 'Vul een geldige einddatum in.');
        $v->period('start', 'end', CapacityService::MAX_DAYS);
        $v->validate();

        $species = $v->get('species');
        $start = $v->get('start');
        $end = $v->get('end');

        $result = [];
        $shelters = Database::connect()->query('SELECT id, name, address FROM shelters ORDER BY name')->fetchAll();

        foreach ($shelters as $shelter) {
            $days = CapacityService::perDay($shelter['id'], $species, $start, $end);

            // Neemt deze opvang de diersoort aan? (besluit E: alleen als er capaciteit is ingesteld)
            $acceptsSpecies = false;
            foreach ($days as $day) {
                if ($day['has_capacity']) {
                    $acceptsSpecies = true;
                }
            }
            if (!$acceptsSpecies) {
                continue;
            }

            // Dagen zonder vrije plek = vol
            $fullDates = [];
            foreach ($days as $day) {
                if ($day['free'] === 0) {
                    $fullDates[] = $day['date'];
                }
            }

            $result[] = [
                'id' => $shelter['id'],
                'name' => $shelter['name'],
                'address' => $shelter['address'],
                'available' => empty($fullDates),
                // Het kleinste aantal vrije plekken in de periode
                'free_slots' => min(array_column($days, 'free')),
                'full_dates' => $fullDates,
            ];
        }

        Response::json(['shelters' => $result]);
    }
}
