<?php

namespace FurEver\Models;

final class VolunteerAssignment
{
    public const STATUS_SIGNED_UP = 'signed_up';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        public int $volunteerId,
        public int $shiftId,
        public string $status,
        public string $assignedAt,
        public ?string $volunteerName = null,
        public ?string $volunteerEmail = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            volunteerId:    (int) $row['volunteer_id'],
            shiftId:        (int) $row['shift_id'],
            status:         (string) ($row['status'] ?? self::STATUS_SIGNED_UP),
            assignedAt:     (string) ($row['assigned_at'] ?? ''),
            volunteerName:  $row['volunteer_name'] ?? null,
            volunteerEmail: $row['volunteer_email'] ?? null,
        );
    }
}
