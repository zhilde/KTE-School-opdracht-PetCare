<?php

// Bepaalt welke gegevens een medewerker of beheerder mag zien (besluit B, FE4, FE6, FE7).
// - Beheerder: alle opvangen
// - Medewerker: alleen de opvangen waaraan hij gekoppeld is (tabel shelter_employees)
class AccessService
{
    // Geeft de opvangen (id + naam) terug waar deze gebruiker bij mag
    public static function shelters(array $user): array
    {
        $pdo = Database::connect();

        if ($user['role'] === 'admin') {
            return $pdo->query('SELECT id, name FROM shelters ORDER BY name')->fetchAll();
        }

        $stmt = $pdo->prepare(
            'SELECT s.id, s.name
             FROM shelters s
             JOIN shelter_employees se ON se.shelter_id = s.id
             WHERE se.user_id = ?
             ORDER BY s.name'
        );
        $stmt->execute([$user['id']]);

        return $stmt->fetchAll();
    }

    // Alleen de id's, bv. [1, 3]
    public static function shelterIds(array $user): array
    {
        return array_map('intval', array_column(self::shelters($user), 'id'));
    }

    // Zoekt een dier dat deze gebruiker mag bekijken.
    // - Eigenaar: alleen zijn eigen dieren (anders 404)
    // - Medewerker/beheerder: elk bestaand dier (wat hij ervan ziet, bepaalt canSeeSensitive)
    public static function viewablePet(int $petId, array $user): array
    {
        if ($user['role'] === 'owner') {
            return self::ownPet($petId, $user);
        }

        $stmt = Database::connect()->prepare('SELECT id, name, species, owner_id FROM pets WHERE id = ?');
        $stmt->execute([$petId]);
        $pet = $stmt->fetch();

        if (!$pet) {
            throw new ApiException('Dier niet gevonden.', 404);
        }

        return $pet;
    }

    // Mag deze gebruiker de GEVOELIGE instructies (medicatie, gedrag) van dit dier zien? (FE7)
    // - Eigenaar van het dier: ja
    // - Beheerder: ja
    // - Medewerker: alleen als het dier een lopende aanvraag of goedgekeurde boeking heeft
    //   bij een opvang waaraan hij gekoppeld is ("betrokken medewerker")
    public static function canSeeSensitive(array $pet, array $user): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }

        if ($user['role'] === 'owner') {
            // viewablePet() heeft al gecontroleerd dat het zijn eigen dier is
            return true;
        }

        $stmt = Database::connect()->prepare(
            "SELECT COUNT(*)
             FROM bookings b
             JOIN shelter_employees se ON se.shelter_id = b.shelter_id
             WHERE b.pet_id = ? AND se.user_id = ? AND b.status IN ('PENDING', 'APPROVED')"
        );
        $stmt->execute([$pet['id'], $user['id']]);

        return $stmt->fetchColumn() > 0;
    }

    // Zoekt een dier dat van deze eigenaar is.
    // Niet gevonden OF van iemand anders? Dan 404. Zo verraden we niet dat het dier bestaat.
    public static function ownPet(int $petId, array $user): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, name, species FROM pets WHERE id = ? AND owner_id = ?'
        );
        $stmt->execute([$petId, $user['id']]);
        $pet = $stmt->fetch();

        if (!$pet) {
            throw new ApiException('Dier niet gevonden.', 404);
        }

        return $pet;
    }
}
