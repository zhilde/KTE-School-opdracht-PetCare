<?php

// API voor de dagplanning van medewerker en beheerder (FE4).
// Toont welke dieren op een dag aanwezig zijn (goedgekeurde boekingen),
// te filteren op locatie (opvang) en dienst (= diersoort, besluit E).
class PlanningController
{
    // GET /api/planning?date=2026-10-08&shelter_id=1&species=hond
    // shelter_id en species zijn optioneel (leeg = alle)
    public function index(): void
    {
        $user = Auth::requireRole('employee', 'admin');

        $v = new Validator($_GET);
        $v->required('date', 'Kies een datum.');
        $v->date('date', 'Vul een geldige datum in.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een locatie uit de lijst.');
        $v->in('species', PetController::SPECIES, 'Kies een diersoort uit de lijst.');
        $v->validate();

        $date = $v->get('date');
        $shelterFilter = $v->get('shelter_id');
        $species = $v->get('species');

        // Opvangen waar deze gebruiker bij mag (medewerker: alleen gekoppelde)
        $shelters = AccessService::shelters($user);
        $allowedIds = array_map('intval', array_column($shelters, 'id'));

        // Filter op een opvang waar je niet bij mag? Dan geen toegang.
        if ($shelterFilter !== '' && !in_array((int) $shelterFilter, $allowedIds, true)) {
            throw new ApiException('Je hebt geen toegang tot de planning van deze opvang.', 403);
        }

        // Welke opvangen tonen we? Gekozen opvang, of alle opvangen waar je bij mag
        $shelterIds = $shelterFilter !== '' ? [(int) $shelterFilter] : $allowedIds;

        // Niet gekoppeld aan een opvang = niks te zien
        if (empty($shelterIds)) {
            Response::json(['shelters' => $shelters, 'items' => []]);
            return;
        }

        // Voor elke opvang een ? in de query, bv. IN (?, ?)
        $placeholders = implode(', ', array_fill(0, count($shelterIds), '?'));
        $params = array_merge([$date, $date], $shelterIds);

        $sql = "SELECT b.id AS booking_id, b.start_date, b.end_date,
                       p.id AS pet_id, p.name AS pet_name, p.species,
                       s.id AS shelter_id, s.name AS shelter_name,
                       u.name AS owner_name
                FROM bookings b
                JOIN pets p ON p.id = b.pet_id
                JOIN shelters s ON s.id = b.shelter_id
                JOIN users u ON u.id = p.owner_id
                WHERE b.status = 'APPROVED'
                  AND b.start_date <= ? AND b.end_date >= ?
                  AND b.shelter_id IN ($placeholders)";

        // Filter op dienst (diersoort) alleen als er een is gekozen
        if ($species !== '') {
            $sql .= ' AND p.species = ?';
            $params[] = $species;
        }

        $sql .= ' ORDER BY s.name, p.name';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);

        Response::json(['shelters' => $shelters, 'items' => $stmt->fetchAll()]);
    }
}
