<?php

namespace FurEver\Models;

final class VolunteerShift
{
    public function __construct(
        public int $id,
        public string $shiftDate,
        public string $startTime,
        public string $endTime,
        public ?string $taskDescription,
        public ?string $location,
        public int $capacity,
        public ?int $createdBy = null,
        /** @var VolunteerAssignment[] */
        public array $assignments = [],
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            shiftDate:       (string) $row['shift_date'],
            startTime:       (string) $row['start_time'],
            endTime:         (string) $row['end_time'],
            taskDescription: $row['task_description'] ?? null,
            location:        $row['location'] ?? null,
            capacity:        (int) ($row['capacity'] ?? 1),
            createdBy:       isset($row['created_by']) ? (int) $row['created_by'] : null,
        );
    }

    public function dayOfWeek(): string
    {
        return date('D', strtotime($this->shiftDate));
    }

    public function timeRange(): string
    {
        return substr($this->startTime, 0, 5) . '–' . substr($this->endTime, 0, 5);
    }
}
