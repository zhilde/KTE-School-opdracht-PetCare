<?php

// API voor opvangaanvragen (FE2, FE6).
// - Eigenaar: aanvraag indienen en eigen aanvragen met status bekijken
// - Medewerker/beheerder: aanvragen van de eigen opvang(en) bekijken, goedkeuren of afwijzen
// Een aanvraag wordt alleen opgeslagen als er op ELKE dag van de periode plek is.
class BookingController
{
    // Mogelijke statussen (zelfde als de ENUM in de database)
    // CANCELLED = geannuleerd door de eigenaar (AFW-6)
    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

    // GET /api/bookings?status=PENDING&shelter_id=1 – aanvragen voor medewerker/beheerder
    // status en shelter_id zijn optioneel (leeg = alle)
    public function index(): void
    {
        $user = Auth::requireRole('employee', 'admin');

        $v = new Validator($_GET);
        $v->in('status', self::STATUSES, 'Kies een status uit de lijst.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een opvang uit de lijst.');
        $v->validate();

        $status = $v->get('status');
        $shelterFilter = $v->get('shelter_id');

        // Alleen aanvragen van opvangen waar deze gebruiker bij mag
        $shelters = AccessService::shelters($user);
        $allowedIds = array_map('intval', array_column($shelters, 'id'));

        if ($shelterFilter !== '' && !in_array((int) $shelterFilter, $allowedIds, true)) {
            throw new ApiException('Je hebt geen toegang tot de aanvragen van deze opvang.', 403);
        }

        $shelterIds = $shelterFilter !== '' ? [(int) $shelterFilter] : $allowedIds;

        if (empty($shelterIds)) {
            Response::json(['shelters' => $shelters, 'bookings' => []]);
            return;
        }

        // Voor elke opvang een ? in de query
        $placeholders = implode(', ', array_fill(0, count($shelterIds), '?'));
        $params = $shelterIds;

        $sql = "SELECT b.id, b.start_date, b.end_date, b.status,
                       p.id AS pet_id, p.name AS pet_name, p.species,
                       s.name AS shelter_name, u.name AS owner_name
                FROM bookings b
                JOIN pets p ON p.id = b.pet_id
                JOIN shelters s ON s.id = b.shelter_id
                JOIN users u ON u.id = p.owner_id
                WHERE b.shelter_id IN ($placeholders)";

        if ($status !== '') {
            $sql .= ' AND b.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY b.start_date, p.name';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);

        Response::json(['shelters' => $shelters, 'bookings' => $stmt->fetchAll()]);
    }

    // PUT /api/bookings/{id}/approve – aanvraag goedkeuren
    public function approve(int $id): void
    {
        $this->review($id, 'APPROVED');
    }

    // PUT /api/bookings/{id}/reject – aanvraag afwijzen (de plek komt weer vrij, besluit D)
    public function reject(int $id): void
    {
        $this->review($id, 'REJECTED');
    }

    // Goedkeuren en afwijzen werken hetzelfde, alleen de nieuwe status verschilt
    private function review(int $id, string $newStatus): void
    {
        $user = Auth::requireRole('employee', 'admin');
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            'SELECT b.id, b.shelter_id, b.status, p.name AS pet_name
             FROM bookings b
             JOIN pets p ON p.id = b.pet_id
             WHERE b.id = ?'
        );
        $stmt->execute([$id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            throw new ApiException('Aanvraag niet gevonden.', 404);
        }

        // Alleen aanvragen van je eigen opvang(en) beoordelen
        if (!in_array((int) $booking['shelter_id'], AccessService::shelterIds($user), true)) {
            throw new ApiException('Je hebt geen toegang tot aanvragen van deze opvang.', 403);
        }

        // "AND status = 'PENDING'" zorgt dat een aanvraag maar 1 keer beoordeeld kan worden,
        // ook als twee medewerkers precies tegelijk op een knop drukken.
        // rowCount() = hoeveel regels er echt zijn aangepast (0 = was al beoordeeld)
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND status = 'PENDING'");
        $stmt->execute([$newStatus, $id]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException('Deze aanvraag is al beoordeeld.', 409);
        }

        $word = $newStatus === 'APPROVED' ? 'goedgekeurd' : 'afgewezen';
        Response::json(['message' => "Aanvraag voor {$booking['pet_name']} is $word."]);
    }
    // GET /api/bookings/mine – alle aanvragen van de ingelogde eigenaar, met status
    public function mine(): void
    {
        $user = Auth::requireRole('owner');

        $stmt = Database::connect()->prepare(
            'SELECT b.id, b.start_date, b.end_date, b.status, b.shelter_id,
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
        $pet = AccessService::ownPet($petId, $user);

        // 3. Bestaat de opvang?
        $shelterName = $this->shelterName($shelterId);

        // 4. Controleren en opslaan in een TRANSACTIE (uitleg bij lockAndCheckCapacity)
        $pdo->beginTransaction();

        try {
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

            // 4b. Slot zetten en controleren of er op elke dag nog plek is
            $this->lockAndCheckCapacity($pdo, $shelterId, $shelterName, $pet['species'], $start, $end);

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

    // PUT /api/bookings/{id}/cancel – eigenaar annuleert zijn aanvraag of boeking.
    // De plek komt weer vrij, want CANCELLED telt niet als bezet.
    public function cancel(int $id): void
    {
        $user = Auth::requireRole('owner');
        $booking = $this->findChangeableBooking($id, $user);

        // "AND status IN (...)": zo kan het maar 1 keer, ook als er tegelijk iets anders gebeurt
        $stmt = Database::connect()->prepare(
            "UPDATE bookings SET status = 'CANCELLED' WHERE id = ? AND status IN ('PENDING', 'APPROVED')"
        );
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException('Deze aanvraag kan niet meer worden geannuleerd.', 409);
        }

        Response::json(['message' => "De aanvraag voor {$booking['pet_name']} is geannuleerd."]);
    }

    // PUT /api/bookings/{id}/shelter – eigenaar verplaatst zijn aanvraag naar een andere opvang.
    // Verwacht JSON: { "shelter_id": 2 }
    // De nieuwe opvang moet de aanvraag opnieuw beoordelen, dus de status wordt weer PENDING.
    public function changeShelter(int $id): void
    {
        $user = Auth::requireRole('owner');
        $booking = $this->findChangeableBooking($id, $user);

        $v = new Validator(Request::body());
        $v->required('shelter_id', 'Kies een opvang.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een opvang uit de lijst.');
        $v->validate();

        $newShelterId = (int) $v->get('shelter_id');
        if ($newShelterId === (int) $booking['shelter_id']) {
            throw new ApiException('Controleer de ingevulde velden.', 422, ['shelter_id' => 'Kies een andere opvang dan de huidige.']);
        }

        $shelterName = $this->shelterName($newShelterId);
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Plek controleren bij de NIEUWE opvang (met slot, net als bij een nieuwe aanvraag)
            $this->lockAndCheckCapacity($pdo, $newShelterId, $shelterName, $booking['species'], $booking['start_date'], $booking['end_date']);

            $stmt = $pdo->prepare(
                "UPDATE bookings SET shelter_id = ?, status = 'PENDING'
                 WHERE id = ? AND status IN ('PENDING', 'APPROVED')"
            );
            $stmt->execute([$newShelterId, $id]);

            if ($stmt->rowCount() === 0) {
                throw new ApiException('Deze aanvraag kan niet meer worden gewijzigd.', 409);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json(['message' => "De aanvraag voor {$booking['pet_name']} is verplaatst naar $shelterName. De status is weer: in behandeling."]);
    }

    // Zoekt een aanvraag van deze eigenaar die nog gewijzigd/geannuleerd mag worden:
    // - van een eigen dier (anders 404)
    // - status PENDING of APPROVED (afgewezen of geannuleerd kan niet meer)
    // - de opvang is nog niet begonnen (startdatum vandaag of later)
    private function findChangeableBooking(int $id, array $user): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT b.id, b.shelter_id, b.start_date, b.end_date, b.status, p.name AS pet_name, p.species
             FROM bookings b
             JOIN pets p ON p.id = b.pet_id
             WHERE b.id = ? AND p.owner_id = ?'
        );
        $stmt->execute([$id, $user['id']]);
        $booking = $stmt->fetch();

        if (!$booking) {
            throw new ApiException('Aanvraag niet gevonden.', 404);
        }
        if (!in_array($booking['status'], ['PENDING', 'APPROVED'], true)) {
            throw new ApiException('Een afgewezen of geannuleerde aanvraag kun je niet meer wijzigen.', 409);
        }
        if ($booking['start_date'] < date('Y-m-d')) {
            throw new ApiException('De opvang is al begonnen. Neem contact op met de opvang.', 409);
        }

        return $booking;
    }

    // Naam van een opvang. Bestaat hij niet, dan een fout bij het veld shelter_id.
    private function shelterName(int $shelterId): string
    {
        $stmt = Database::connect()->prepare('SELECT name FROM shelters WHERE id = ?');
        $stmt->execute([$shelterId]);
        $name = $stmt->fetchColumn();

        if ($name === false) {
            throw new ApiException('Controleer de ingevulde velden.', 422, ['shelter_id' => 'Kies een opvang uit de lijst.']);
        }

        return $name;
    }

    // Zet een slot op de capaciteit en controleert of er op ELKE dag plek is (FE2).
    // Moet binnen een transactie worden aangeroepen.
    //
    // Probleem zonder slot: twee eigenaren boeken tegelijk de laatste plek,
    // allebei zien ze "1 vrij" en allebei wordt de boeking opgeslagen -> overvol.
    // Oplossing: FOR UPDATE zet een slot (lock) op de capaciteitsregels van deze periode.
    // Een tweede boeking voor dezelfde opvang en diersoort moet dan wachten
    // tot de eerste klaar is, en ziet daarna de juiste (bijgewerkte) bezetting.
    private function lockAndCheckCapacity(PDO $pdo, int $shelterId, string $shelterName, string $species, string $start, string $end): void
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM capacities
             WHERE shelter_id = ? AND species = ? AND date BETWEEN ? AND ?
             FOR UPDATE'
        );
        $stmt->execute([$shelterId, $species, $start, $end]);

        $fullDates = [];
        foreach (CapacityService::perDay($shelterId, $species, $start, $end) as $day) {
            if ($day['free'] === 0) {
                $fullDates[] = date('d-m-Y', strtotime($day['date']));
            }
        }

        if (!empty($fullDates)) {
            throw new ApiException("Geen plek beschikbaar bij $shelterName op: " . implode(', ', $fullDates) . '. Er is niets opgeslagen.', 409);
        }
    }
}
