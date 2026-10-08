<?php

// Rekent uit hoeveel plekken er vrij zijn bij een opvang (FE2, FE3).
// Wordt gebruikt door het capaciteitsoverzicht, het zoeken naar opvang en (taak 8) het boeken.
//
// Regel (besluit D): een plek is bezet door een boeking met status PENDING of APPROVED.
// Een afgewezen boeking (REJECTED) telt niet mee.
class CapacityService
{
    // Geeft voor elke dag van $start t/m $end terug:
    //   date         = de datum
    //   max_slots    = ingestelde plekken (0 als er niks is ingesteld)
    //   booked       = aantal bezette plekken
    //   free         = vrije plekken
    //   has_capacity = of de beheerder voor deze dag capaciteit heeft ingesteld
    public static function perDay(int $shelterId, string $species, string $start, string $end): array
    {
        $pdo = Database::connect();

        // 1. Ingestelde capaciteit per dag ophalen.
        // FETCH_KEY_PAIR geeft een array als ['2026-10-08' => 3, '2026-10-09' => 3]
        $stmt = $pdo->prepare(
            'SELECT date, max_slots FROM capacities
             WHERE shelter_id = ? AND species = ? AND date BETWEEN ? AND ?'
        );
        $stmt->execute([$shelterId, $species, $start, $end]);
        $maxPerDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. Boekingen ophalen die (een deel van) de periode overlappen.
        // De diersoort staat bij het dier, daarom de JOIN met pets.
        $stmt = $pdo->prepare(
            "SELECT b.start_date, b.end_date
             FROM bookings b
             JOIN pets p ON p.id = b.pet_id
             WHERE b.shelter_id = ? AND p.species = ?
               AND b.status IN ('PENDING', 'APPROVED')
               AND b.start_date <= ? AND b.end_date >= ?"
        );
        $stmt->execute([$shelterId, $species, $end, $start]);
        $bookings = $stmt->fetchAll();

        // 3. Elke dag langslopen en tellen hoeveel boekingen er op die dag vallen
        $days = [];
        $current = new DateTime($start);
        $last = new DateTime($end);

        while ($current <= $last) {
            $date = $current->format('Y-m-d');

            $booked = 0;
            foreach ($bookings as $booking) {
                // Einddatum telt mee (t/m), daarom >=
                if ($booking['start_date'] <= $date && $booking['end_date'] >= $date) {
                    $booked++;
                }
            }

            $maxSlots = (int) ($maxPerDay[$date] ?? 0);

            $days[] = [
                'date' => $date,
                'max_slots' => $maxSlots,
                'booked' => $booked,
                'free' => max(0, $maxSlots - $booked),
                'has_capacity' => isset($maxPerDay[$date]),
            ];

            $current->modify('+1 day');
        }

        return $days;
    }
}
