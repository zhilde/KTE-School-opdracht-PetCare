<?php

// API voor verzorgingsinstructies en de wijzigingsregistratie (FE5).
// Per dier is er per type (voeding, medicatie, gedrag) één instructie.
// Elke wijziging wordt apart bewaard in care_changes: wie, oud, nieuw en wanneer.
class CareController
{
    // Toegestane types (zelfde als de ENUM in de database)
    public const TYPES = ['voeding', 'medicatie', 'gedrag'];

    // Gevoelige types (besluit C, FE7). Voeding is niet gevoelig.
    public const SENSITIVE_TYPES = ['medicatie', 'gedrag'];

    // GET /api/pets/{id}/care – instructies + wijzigingsgeschiedenis van een dier
    // Eigenaar, medewerker en beheerder mogen dit bekijken, maar niet iedereen ziet alles (FE7).
    public function show(int $petId): void
    {
        $user = Auth::requireRole('owner', 'employee', 'admin');
        $pet = AccessService::viewablePet($petId, $user);
        $canSeeSensitive = AccessService::canSeeSensitive($pet, $user);
        $pdo = Database::connect();

        // Welke types mag deze gebruiker zien?
        $visibleTypes = $canSeeSensitive ? self::TYPES : array_diff(self::TYPES, self::SENSITIVE_TYPES);

        // Een ? per type, bv. type IN (?, ?, ?)
        $placeholders = implode(', ', array_fill(0, count($visibleTypes), '?'));

        // Gevoelige instructies worden NIET opgehaald als je ze niet mag zien.
        // Ze worden dus ook niet naar de browser gestuurd (niet alleen verborgen in React).
        $stmt = $pdo->prepare(
            "SELECT id, type, text FROM care_instructions
             WHERE pet_id = ? AND type IN ($placeholders) ORDER BY id"
        );
        $stmt->execute(array_merge([$petId], array_values($visibleTypes)));
        $instructions = $stmt->fetchAll();

        // Geschiedenis, nieuwste eerst. Met de naam en rol van wie het heeft gewijzigd.
        $stmt = $pdo->prepare(
            "SELECT cc.id, ci.type, cc.old_text, cc.new_text, cc.changed_at,
                    u.name AS changed_by_name, u.role AS changed_by_role
             FROM care_changes cc
             JOIN care_instructions ci ON ci.id = cc.instruction_id
             JOIN users u ON u.id = cc.changed_by
             WHERE ci.pet_id = ? AND ci.type IN ($placeholders)
             ORDER BY cc.changed_at DESC, cc.id DESC"
        );
        $stmt->execute(array_merge([$petId], array_values($visibleTypes)));
        $changes = $stmt->fetchAll();

        Response::json([
            'pet' => ['id' => $pet['id'], 'name' => $pet['name'], 'species' => $pet['species']],
            'instructions' => $instructions,
            'changes' => $changes,
            // Zodat React een melding kan tonen dat er iets is afgeschermd
            'sensitive_hidden' => !$canSeeSensitive,
            // Alleen de eigenaar mag instructies wijzigen
            'can_edit' => $user['role'] === 'owner',
        ]);
    }

    // PUT /api/pets/{id}/care – instructie opslaan (nieuw of wijzigen)
    // Verwacht JSON: { "type": "medicatie", "text": "1 tablet in de ochtend." }
    public function save(int $petId): void
    {
        $user = Auth::requireRole('owner');
        $pet = AccessService::ownPet($petId, $user);

        $v = new Validator(Request::body());
        $v->required('type', 'Kies een type.');
        $v->in('type', self::TYPES, 'Kies voeding, medicatie of gedrag.');
        $v->required('text', 'Vul de instructie in.');
        $v->maxLength('text', 2000, 'De instructie mag maximaal 2000 tekens zijn.');
        $v->validate();

        $type = $v->get('type');
        $text = $v->get('text');
        $pdo = Database::connect();

        // Bestaat er al een instructie van dit type voor dit dier?
        $stmt = $pdo->prepare('SELECT id, text FROM care_instructions WHERE pet_id = ? AND type = ?');
        $stmt->execute([$petId, $type]);
        $existing = $stmt->fetch();

        // Niks veranderd? Dan ook niks registreren.
        if ($existing && $existing['text'] === $text) {
            Response::json(['message' => 'Er is niets gewijzigd.']);
            return;
        }

        // Instructie en registratie samen opslaan in een transactie:
        // zo kan er nooit een wijziging zijn zonder registratie (of andersom).
        $pdo->beginTransaction();

        try {
            if ($existing) {
                $stmt = $pdo->prepare('UPDATE care_instructions SET text = ? WHERE id = ?');
                $stmt->execute([$text, $existing['id']]);
                $instructionId = (int) $existing['id'];
                $oldText = $existing['text'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO care_instructions (pet_id, type, text) VALUES (?, ?, ?)');
                $stmt->execute([$petId, $type, $text]);
                $instructionId = (int) $pdo->lastInsertId();
                $oldText = null; // NULL = nieuw aangemaakt
            }

            // FE5: wie (changed_by), wat (oud -> nieuw), wanneer (changed_at, automatisch NOW)
            $stmt = $pdo->prepare(
                'INSERT INTO care_changes (instruction_id, changed_by, old_text, new_text) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$instructionId, $user['id'], $oldText, $text]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json(['message' => ucfirst($type) . " van {$pet['name']} is opgeslagen."]);
    }
}
