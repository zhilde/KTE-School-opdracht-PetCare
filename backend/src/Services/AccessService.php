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
}
