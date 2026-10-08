<?php

// API voor opvangaanvragen van de eigenaar (FE2, FE6).
// Een aanvraag wordt alleen opgeslagen als er op ELKE dag van de periode plek is.
class BookingController
{
    // GET /api/bookings/mine – alle aanvragen van de ingelogde eigenaar, met status
    public function mine(): void
    {
        $user = Auth::requireRole('owner');

        $stmt = Database::connect()->prepare(
            'SELECT b.id, b.start_date, b.end_date, b.status,
                    p.name AS pet_name, p.species, s.name AS shelter_name
             FROM bookings b
             JOIN pets p ON p.id = b.pet_id
             JOIN shelters s ON s.id = b.shelter_id
             WHERE p.owner_id = ?
             ORDER BY b.start_date DESC'
        );
        $stmt->execute([$user['id']]);

        Response::json(['bookings' => $stmt->fetchAll()]);
    }

    // POST /api/bookings – nieuwe opvangaanvraag
    // Verwacht JSON: { "pet_id": 1, "shelter_id": 1, "start_date": "2026-11-01", "end_date": "2026-11-03" }
    public function store(): void
    {
        $user = Auth::requireRole('owner');

        // 1. Invoer controleren
        $v = new Validator(Request::body());
        $v->required('pet_id', 'Kies een dier.');
        $v->integer('pet_id', 1, PHP_INT_MAX, 'Kies een dier uit de lijst.');
        $v->required('shelter_id', 'Kies een opvang.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een opvang uit de lijst.');
        $v->required('start_date', 'Kies een startdatum.');
        $v->date('start_date', 'Vul een geldige startdatum in.');
        $v->required('end_date', 'Kies een einddatum.');
        $v->date('end_date', 'Vul een geldige einddatum in.');
        $v->period('start_date', 'end_date', CapacityService::MAX_DAYS);
        $v->validate();

        $petId = (int) $v->get('pet_id');
        $shelterId = (int) $v->get('shelter_id');
        $start = $v->get('start_date');
        $end = $v->get('end_date');
        $pdo = Database::connect();

        // 2. Is het dier van deze eigenaar? (anders 404, zelfde als bij de dierenprofielen)
        $stmt = $pdo->prepare('SELECT id, name, species FROM pets WHERE id = ? AND owner_id = ?');
        $stmt->execute([$petId, $user['id']]);
        $pet = $stmt->fetch();
        if (!$pet) {
            throw new ApiException('Dier niet gevonden.', 404);
        }

        // 3. Bestaat de opvang?
        $stmt = $pdo->prepare('SELECT name FROM shelters WHERE id = ?');
        $stmt->execute([$shelterId]);
        $shelterName = $stmt->fetchColumn();
        if ($shelterName === false) {
            throw new ApiException('Controleer de ingevulde velden.', 422, ['shelter_id' => 'Kies een opvang uit de lijst.']);
        }

        // 4. Controleren en opslaan in een TRANSACTIE.
        // Probleem zonder transactie: twee eigenaren boeken tegelijk de laatste plek,
        // allebei zien ze "1 vrij" en allebei wordt de boeking opgeslagen -> overvol.
        // Oplossing: FOR UPDATE zet een slot (lock) op de capaciteitsregels van deze periode.
        // Een tweede boeking voor dezelfde opvang en diersoort moet dan wachten
        // tot de eerste klaar is, en ziet daarna de juiste (bijgewerkte) bezetting.
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id FROM capacities
                 WHERE shelter_id = ? AND species = ? AND date BETWEEN ? AND ?
                 FOR UPDATE'
            );
            $stmt->execute([$shelterId, $pet['species'], $start, $end]);

            // 4a. Heeft dit dier al een aanvraag die overlapt met deze periode? (ook bij een andere opvang)
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM bookings
                 WHERE pet_id = ? AND status IN ('PENDING', 'APPROVED')
                   AND start_date <= ? AND end_date >= ?"
            );
            $stmt->execute([$petId, $end, $start]);
            if ($stmt->fetchColumn() > 0) {
                throw new ApiException("{$pet['name']} heeft al een aanvraag in (een deel van) deze periode.", 409);
            }

            // 4b. Is er op elke dag nog plek?
            $fullDates = [];
            foreach (CapacityService::perDay($shelterId, $pet['species'], $start, $end) as $day) {
                if ($day['free'] === 0) {
                    $fullDates[] = date('d-m-Y', strtotime($day['date']));
                }
            }
            if (!empty($fullDates)) {
                throw new ApiException("Geen plek beschikbaar bij $shelterName op: " . implode(', ', $fullDates) . '. De aanvraag is niet opgeslagen.', 409);
            }

            // 4c. Alles klopt: aanvraag opslaan met status PENDING
            $stmt = $pdo->prepare(
                "INSERT INTO bookings (pet_id, shelter_id, start_date, end_date, status)
                 VALUES (?, ?, ?, ?, 'PENDING')"
            );
            $stmt->execute([$petId, $shelterId, $start, $end]);
            $bookingId = (int) $pdo->lastInsertId();

            // Pas nu wordt alles echt opgeslagen en het slot vrijgegeven
            $pdo->commit();
        } catch (Throwable $e) {
            // Er ging iets mis: niks opslaan, slot vrijgeven, en de fout doorgeven aan index.php
            $pdo->rollBack();
            throw $e;
        }

        Response::json([
            'message' => "Aanvraag voor {$pet['name']} bij $shelterName is verstuurd. De status is nu: in behandeling.",
            'booking_id' => $bookingId,
        ], 201);
    }
}
