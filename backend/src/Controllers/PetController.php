<?php

// API voor dierenprofielen (FE1).
// Een eigenaar mag alleen zijn EIGEN dieren zien, toevoegen, wijzigen en verwijderen.
class PetController
{
    // Toegestane diersoorten (besluit F). Zelfde lijst als de ENUM in de database.
    public const SPECIES = ['hond', 'kat', 'konijn', 'overig'];

    // GET /api/pets – alle dieren van de ingelogde eigenaar
    public function index(): void
    {
        $user = Auth::requireRole('owner');

        $stmt = Database::connect()->prepare(
            'SELECT id, name, species FROM pets WHERE owner_id = ? ORDER BY name'
        );
        $stmt->execute([$user['id']]);

        Response::json(['pets' => $stmt->fetchAll()]);
    }

    // GET /api/pets/{id} – één dier van de ingelogde eigenaar
    public function show(int $id): void
    {
        $user = Auth::requireRole('owner');
        $pet = $this->findOwnPet($id, $user['id']);

        Response::json(['pet' => $pet]);
    }

    // POST /api/pets – nieuw dier toevoegen
    // Verwacht JSON: { "name": "Rex", "species": "hond" }
    public function store(): void
    {
        $user = Auth::requireRole('owner');
        [$name, $species] = $this->validatePet(Request::body());

        // owner_id komt uit de sessie, NIET uit de verstuurde gegevens.
        // Zo kan niemand een dier aanmaken op naam van iemand anders.
        $pdo = Database::connect();
        $stmt = $pdo->prepare('INSERT INTO pets (owner_id, name, species) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $name, $species]);

        $pet = ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'species' => $species];

        // 201 = er is iets nieuws aangemaakt
        Response::json(['message' => "$name is toegevoegd.", 'pet' => $pet], 201);
    }

    // PUT /api/pets/{id} – dier wijzigen
    public function update(int $id): void
    {
        $user = Auth::requireRole('owner');
        $this->findOwnPet($id, $user['id']); // stopt met 404 als het niet jouw dier is
        [$name, $species] = $this->validatePet(Request::body());

        $stmt = Database::connect()->prepare('UPDATE pets SET name = ?, species = ? WHERE id = ?');
        $stmt->execute([$name, $species, $id]);

        $pet = ['id' => $id, 'name' => $name, 'species' => $species];
        Response::json(['message' => "$name is opgeslagen.", 'pet' => $pet]);
    }

    // DELETE /api/pets/{id} – dier verwijderen
    public function destroy(int $id): void
    {
        $user = Auth::requireRole('owner');
        $pet = $this->findOwnPet($id, $user['id']);
        $pdo = Database::connect();

        // Besluit J: niet verwijderen zolang er een lopende boeking is
        // (aanvraag of goedgekeurd, en nog niet voorbij)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE pet_id = ? AND status IN ('PENDING', 'APPROVED') AND end_date >= CURDATE()"
        );
        $stmt->execute([$id]);

        if ($stmt->fetchColumn() > 0) {
            // 409 = conflict: kan nu niet door de situatie
            throw new ApiException("{$pet['name']} heeft nog een lopende opvangaanvraag en kan niet worden verwijderd.", 409);
        }

        // Instructies en oude boekingen worden automatisch mee verwijderd (ON DELETE CASCADE)
        $stmt = $pdo->prepare('DELETE FROM pets WHERE id = ?');
        $stmt->execute([$id]);

        Response::json(['message' => "{$pet['name']} is verwijderd."]);
    }

    // Zoekt een dier dat van deze eigenaar is.
    // Niet gevonden OF van iemand anders? Dan 404. Zo verraden we niet dat het dier bestaat.
    private function findOwnPet(int $id, int $ownerId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, name, species FROM pets WHERE id = ? AND owner_id = ?'
        );
        $stmt->execute([$id, $ownerId]);
        $pet = $stmt->fetch();

        if (!$pet) {
            throw new ApiException('Dier niet gevonden.', 404);
        }

        return $pet;
    }

    // Controleert naam en soort, en geeft ze terug als ze kloppen.
    // Alleen deze twee velden worden gebruikt, andere velden worden genegeerd (mass assignment-bescherming).
    private function validatePet(array $data): array
    {
        $v = new Validator($data);
        $v->required('name', 'Naam is verplicht.');
        $v->maxLength('name', 100, 'Naam mag maximaal 100 tekens zijn.');
        $v->required('species', 'Kies een diersoort.');
        $v->in('species', self::SPECIES, 'Kies een diersoort uit de lijst.');
        $v->validate();

        return [$v->get('name'), $v->get('species')];
    }
}
