<?php

namespace FurEver\Repositories;

use FurEver\Models\VolunteerAssignment;
use FurEver\Models\VolunteerShift;

final class VolunteerShiftsRepository extends Repository
{
    /** @return VolunteerShift[] */
    public function inRange(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, shift_date, start_time, end_time, task_description, location, capacity, created_by
              FROM volunteer_shifts
             WHERE shift_date BETWEEN :from AND :to
             ORDER BY shift_date, start_time
        ');
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        $shifts = array_map([VolunteerShift::class, 'fromRow'], $stmt->fetchAll());

        if ($shifts) {
            $assignments = $this->loadAssignmentsForShifts(array_map(fn($s) => $s->id, $shifts));
            foreach ($shifts as $shift) {
                $shift->assignments = $assignments[$shift->id] ?? [];
            }
        }
        return $shifts;
    }

    public function findById(int $id): ?VolunteerShift
    {
        $stmt = $this->pdo->prepare('
            SELECT id, shift_date, start_time, end_time, task_description, location, capacity, created_by
              FROM volunteer_shifts WHERE id = :id
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $shift = VolunteerShift::fromRow($row);
        $shift->assignments = $this->loadAssignmentsForShifts([$shift->id])[$shift->id] ?? [];
        return $shift;
    }

    public function create(string $date, string $start, string $end, ?string $task, ?string $location, int $capacity, int $createdBy): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO volunteer_shifts (shift_date, start_time, end_time, task_description, location, capacity, created_by)
            VALUES (:d, :s, :e, :t, :l, :c, :u)
            RETURNING id
        ');
        $stmt->execute([
            ':d' => $date, ':s' => $start, ':e' => $end,
            ':t' => $task, ':l' => $location, ':c' => $capacity, ':u' => $createdBy,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM volunteer_shifts WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function countThisWeek(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM volunteer_shifts
             WHERE shift_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'"
        )->fetchColumn();
    }

    /**
     * @param int[] $shiftIds
     * @return array<int,VolunteerAssignment[]>
     */
    private function loadAssignmentsForShifts(array $shiftIds): array
    {
        if (!$shiftIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($shiftIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT va.volunteer_id, va.shift_id, va.status, va.assigned_at,
                   COALESCE(p.full_name, u.username) AS volunteer_name,
                   u.email AS volunteer_email
              FROM volunteer_assignments va
              JOIN users u ON u.id = va.volunteer_id
              LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE va.shift_id IN ($placeholders)
             ORDER BY va.assigned_at
        ");
        $stmt->execute($shiftIds);
        $byShift = [];
        foreach ($stmt->fetchAll() as $row) {
            $byShift[(int) $row['shift_id']][] = VolunteerAssignment::fromRow($row);
        }
        return $byShift;
    }
}
