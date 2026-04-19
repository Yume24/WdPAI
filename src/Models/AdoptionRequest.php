<?php

namespace FurEver\Models;

final class AdoptionRequest
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public function __construct(
        public int $id,
        public int $animalId,
        public ?string $animalName,
        public ?string $animalSpecies,
        public int $applicantId,
        public ?string $applicantName,
        public ?string $applicantEmail,
        public string $status,
        public ?string $message,
        public string $submittedAt,
        public ?int $reviewedBy,
        public ?string $reviewerEmail,
        public ?string $reviewedAt,
        public ?string $decisionNotes,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:             (int) $row['id'],
            animalId:       (int) $row['animal_id'],
            animalName:     $row['animal_name'] ?? null,
            animalSpecies:  $row['species'] ?? null,
            applicantId:    (int) $row['applicant_id'],
            applicantName:  $row['applicant_name'] ?? null,
            applicantEmail: $row['applicant_email'] ?? null,
            status:         (string) $row['status'],
            message:        $row['message'] ?? null,
            submittedAt:    (string) $row['submitted_at'],
            reviewedBy:     isset($row['reviewed_by']) ? (int) $row['reviewed_by'] : null,
            reviewerEmail:  $row['reviewer_email'] ?? null,
            reviewedAt:     $row['reviewed_at'] ?? null,
            decisionNotes:  $row['decision_notes'] ?? null,
        );
    }

    public static function statuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED  => 'badge-adopted',
            self::STATUS_REJECTED  => 'badge-rejected',
            self::STATUS_WITHDRAWN => 'badge-rejected',
            default                => 'badge-pending',
        };
    }
}
