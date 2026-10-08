<?php
// Zet de database (opnieuw) klaar: tabellen aanmaken en demo-gegevens toevoegen.
// Gebruik: php database/setup.php
// LET OP: alle bestaande gegevens worden verwijderd!

require_once __DIR__ . '/../backend/src/Config.php';
require_once __DIR__ . '/../backend/src/Database.php';

Config::load(__DIR__ . '/../backend/.env');
date_default_timezone_set('Europe/Amsterdam');

$pdo = Database::connectWithoutDatabase();

// 1. Tabellen aanmaken met schema.sql (database naam uit .env)
$schema = file_get_contents(__DIR__ . '/schema.sql');
$schema = str_replace('pet_care_planner', Config::get('DB_NAME'), $schema);
$pdo->exec($schema);
$pdo->exec('USE `' . Config::get('DB_NAME') . '`');

echo "Tabellen aangemaakt.\n";

// Hulpfunctie: voert een INSERT uit en geeft het nieuwe id terug
function insert(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

// Datum vanaf vandaag, zodat de demo altijd actuele gegevens heeft
function dag(int $dagenVanafVandaag): string
{
    return date('Y-m-d', strtotime("$dagenVanafVandaag days"));
}

// 2. Gebruikers (nep-namen, geen echte personen). Wachtwoord voor iedereen: Demo1234!
$hash = password_hash('Demo1234!', PASSWORD_DEFAULT);
$userSql = 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)';

$eigenaar   = insert($pdo, $userSql, ['Simon Singh', 'eigenaar@demo.nl', $hash, 'owner']);
$medewerker = insert($pdo, $userSql, ['Dasalio Kapel', 'medewerker@demo.nl', $hash, 'employee']);
insert($pdo, $userSql, ['Zhiliang Dan', 'beheerder@demo.nl', $hash, 'admin']);

// 3. Opvangen
$shelterSql = 'INSERT INTO shelters (name, address) VALUES (?, ?)';
$noord = insert($pdo, $shelterSql, ['Dierenopvang Noord', 'Hoofdstraat 1, Utrecht']);
$zuid  = insert($pdo, $shelterSql, ['Opvang Zuid', 'Kerkweg 12, Houten']);

// 4. Medewerkers koppelen: Mark bij Noord, Mila bij Zuid
$linkSql = 'INSERT INTO shelter_employees (user_id, shelter_id) VALUES (?, ?)';
insert($pdo, $linkSql, [$medewerker, $noord]);
insert($pdo, $linkSql, [$medewerker2, $zuid]);

// 5. Capaciteit voor de komende 30 dagen. Noord: hond en kat, Zuid: hond en konijn
$capSql = 'INSERT INTO capacities (shelter_id, date, species, max_slots) VALUES (?, ?, ?, ?)';
for ($i = 0; $i < 30; $i++) {
    insert($pdo, $capSql, [$noord, dag($i), 'hond', 3]);
    insert($pdo, $capSql, [$noord, dag($i), 'kat', 2]);
    insert($pdo, $capSql, [$zuid, dag($i), 'hond', 2]);
    insert($pdo, $capSql, [$zuid, dag($i), 'konijn', 2]);
}

// 6. Dieren
$petSql = 'INSERT INTO pets (owner_id, name, species) VALUES (?, ?, ?)';
$rex    = insert($pdo, $petSql, [$eigenaar, 'Rex', 'hond']);
$minoes = insert($pdo, $petSql, [$eigenaar, 'Minoes', 'kat']);
$bello  = insert($pdo, $petSql, [$eigenaar2, 'Bello', 'hond']);

// 7. Verzorgingsinstructies + eerste regel in de wijzigingsregistratie (old_text = NULL = nieuw)
$instructies = [
    [$rex, 'voeding', '2x per dag 200 gram brokken.'],
    [$rex, 'medicatie', '1 tablet in de ochtend.'],
    [$rex, 'gedrag', 'Bang voor andere honden.'],
    [$minoes, 'voeding', '3x per dag natvoer.'],
    [$bello, 'medicatie', 'Oogdruppels voor het slapen.'],
];
foreach ($instructies as [$petId, $type, $text]) {
    $instructionId = insert($pdo, 'INSERT INTO care_instructions (pet_id, type, text) VALUES (?, ?, ?)', [$petId, $type, $text]);
    $ownerId = $petId === $bello ? $eigenaar2 : $eigenaar;
    insert($pdo, 'INSERT INTO care_changes (instruction_id, changed_by, old_text, new_text) VALUES (?, ?, NULL, ?)', [$instructionId, $ownerId, $text]);
}

// 8. Boekingen: een open aanvraag en twee goedgekeurde (zichtbaar in de dagplanning van vandaag)
$bookingSql = 'INSERT INTO bookings (pet_id, shelter_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)';
insert($pdo, $bookingSql, [$rex, $noord, dag(7), dag(9), 'PENDING']);
insert($pdo, $bookingSql, [$minoes, $noord, dag(0), dag(2), 'APPROVED']);
insert($pdo, $bookingSql, [$bello, $zuid, dag(0), dag(3), 'APPROVED']);

echo "Demo-gegevens toegevoegd.\n";
echo "Inloggen kan met bv. eigenaar@demo.nl / Demo1234!\n";
