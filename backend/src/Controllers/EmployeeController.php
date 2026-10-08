<?php

// API voor medewerkers koppelen aan opvangen (besluit K, AFW-2). Alleen voor de beheerder.
// Deze koppeling bepaalt later welke aanvragen en gevoelige informatie een medewerker mag zien (FE6, FE7).
class EmployeeController
{
    // GET /api/employees – alle gebruikers met de rol medewerker (voor de keuzelijst)
    public function index(): void
    {
        Auth::requireRole('admin');

        $stmt = Database::connect()->query(
            "SELECT id, name, email FROM users WHERE role = 'employee' ORDER BY name"
        );

        Response::json(['employees' => $stmt->fetchAll()]);
    }

    // GET /api/shelter-employees – alle koppelingen, met naam van medewerker en opvang
    public function links(): void
    {
        Auth::requireRole('admin');

        $stmt = Database::connect()->query(
            'SELECT se.id, u.name AS employee_name, s.name AS shelter_name
             FROM shelter_employees se
             JOIN users u ON u.id = se.user_id
             JOIN shelters s ON s.id = se.shelter_id
             ORDER BY u.name, s.name'
        );

        Response::json(['links' => $stmt->fetchAll()]);
    }

    // POST /api/shelter-employees – medewerker koppelen
    // Verwacht JSON: { "user_id": 2, "shelter_id": 1 }
    public function link(): void
    {
        Auth::requireRole('admin');

        $v = new Validator(Request::body());
        $v->required('user_id', 'Kies een medewerker.');
        $v->integer('user_id', 1, PHP_INT_MAX, 'Kies een medewerker uit de lijst.');
        $v->required('shelter_id', 'Kies een opvang.');
        $v->integer('shelter_id', 1, PHP_INT_MAX, 'Kies een opvang uit de lijst.');
        $v->validate();

        $userId = (int) $v->get('user_id');
        $shelterId = (int) $v->get('shelter_id');
        $pdo = Database::connect();

        // Bestaat de gebruiker en is het echt een medewerker?
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'employee'");
        $stmt->execute([$userId]);
        $employeeName = $stmt->fetchColumn();
        if ($employeeName === false) {
            $v->addError('user_id', 'Kies een medewerker uit de lijst.');
        }

        // Bestaat de opvang?
        $stmt = $pdo->prepare('SELECT name FROM shelters WHERE id = ?');
        $stmt->execute([$shelterId]);
        $shelterName = $stmt->fetchColumn();
        if ($shelterName === false) {
            $v->addError('shelter_id', 'Kies een opvang uit de lijst.');
        }
        $v->validate();

        // Al gekoppeld? Dan niet nog een keer (ook de database blokkeert dit met een unieke sleutel)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM shelter_employees WHERE user_id = ? AND shelter_id = ?');
        $stmt->execute([$userId, $shelterId]);
        if ($stmt->fetchColumn() > 0) {
            throw new ApiException("$employeeName is al gekoppeld aan $shelterName.", 409);
        }

        $stmt = $pdo->prepare('INSERT INTO shelter_employees (user_id, shelter_id) VALUES (?, ?)');
        $stmt->execute([$userId, $shelterId]);

        Response::json(['message' => "$employeeName is gekoppeld aan $shelterName."], 201);
    }

    // DELETE /api/shelter-employees/{id} – koppeling verwijderen
    public function unlink(int $id): void
    {
        Auth::requireRole('admin');
        $pdo = Database::connect();

        // Namen ophalen voor een duidelijke melding, en meteen kijken of de koppeling bestaat
        $stmt = $pdo->prepare(
            'SELECT u.name AS employee_name, s.name AS shelter_name
             FROM shelter_employees se
             JOIN users u ON u.id = se.user_id
             JOIN shelters s ON s.id = se.shelter_id
             WHERE se.id = ?'
        );
        $stmt->execute([$id]);
        $link = $stmt->fetch();

        if (!$link) {
            throw new ApiException('Koppeling niet gevonden.', 404);
        }

        $stmt = $pdo->prepare('DELETE FROM shelter_employees WHERE id = ?');
        $stmt->execute([$id]);

        Response::json(['message' => "{$link['employee_name']} is ontkoppeld van {$link['shelter_name']}."]);
    }
}
