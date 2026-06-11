<?php

namespace FurEver\Repositories;

use FurEver\Models\VolunteerAssignment;
use FurEver\Models\VolunteerShift;

final class VolunteerAssignmentsRepository extends Repository
{
    public function signUp(int $volunteerId, int $shiftId): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO volunteer_assignments (volunteer_id, shift_id, status)
            VALUES (:v, :s, :st)
            ON CONFLICT (volunteer_id, shift_id) DO NOTHING
        ');
        $stmt->execute([
            ':v' => $volunteerId,
            ':s' => $shiftId,
            ':st' => VolunteerAssignment::STATUS_SIGNED_UP,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function drop(int $volunteerId, int $shiftId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM volunteer_assignments WHERE volunteer_id = :v AND shift_id = :s'
        );
        $stmt->execute([':v' => $volunteerId, ':s' => $shiftId]);
    }

    /** @return VolunteerShift[] */
    public function shiftsForVolunteer(int $volunteerId, ?string $fromDate = null): array
    {
        $sql = '
            SELECT s.id, s.shift_date, s.start_time, s.end_time, s.task_description, s.location, s.capacity, s.created_by
              FROM volunteer_shifts s
              JOIN volunteer_assignments a ON a.shift_id = s.id
             WHERE a.volunteer_id = :v
        ';
        $params = [':v' => $volunteerId];
        if ($fromDate !== null) {
            $sql .= ' AND s.shift_date >= :from';
            $params[':from'] = $fromDate;
        }
        $sql .= ' ORDER BY s.shift_date, s.start_time';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([VolunteerShift::class, 'fromRow'], $stmt->fetchAll());
    }

    public function countActiveVolunteersThisWeek(): int
    {
        return (int) $this->pdo->query("
            SELECT COUNT(DISTINCT a.volunteer_id)
              FROM volunteer_assignments a
              JOIN volunteer_shifts s ON s.id = a.shift_id
             WHERE s.shift_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
        ")->fetchColumn();
    }
}
