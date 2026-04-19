<?php

namespace FurEver\Models;

final class Animal
{
    public const STATUS_AVAILABLE     = 'available';
    public const STATUS_PENDING       = 'pending';
    public const STATUS_ADOPTED       = 'adopted';
    public const STATUS_MEDICAL_HOLD  = 'medical_hold';

    public const GENDER_MALE    = 'male';
    public const GENDER_FEMALE  = 'female';
    public const GENDER_UNKNOWN = 'unknown';

    public function __construct(
        public int $id,
        public string $name,
        public int $speciesId,
        public string $speciesName,
        public ?string $speciesIcon,
        public ?string $breed,
        public string $gender,
        public ?string $dateOfBirth,
        public string $intakeDate,
        public string $status,
        public ?string $description,
        public ?string $photoPath,
        public ?int $createdBy,
        public string $createdAt,
        public string $updatedAt,
        public int $pendingRequests = 0,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            name:            (string) $row['name'],
            speciesId:       (int) ($row['species_id'] ?? 0),
            speciesName:     (string) ($row['species_name'] ?? $row['species'] ?? ''),
            speciesIcon:     $row['species_icon'] ?? null,
            breed:           $row['breed'] ?? null,
            gender:          (string) ($row['gender'] ?? self::GENDER_UNKNOWN),
            dateOfBirth:     $row['date_of_birth'] ?? null,
            intakeDate:      (string) ($row['intake_date'] ?? ''),
            status:          (string) ($row['status'] ?? self::STATUS_AVAILABLE),
            description:     $row['description'] ?? null,
            photoPath:       $row['photo_path'] ?? null,
            createdBy:       isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt:       (string) ($row['created_at'] ?? ''),
            updatedAt:       (string) ($row['updated_at'] ?? ''),
            pendingRequests: (int) ($row['pending_requests'] ?? 0),
        );
    }

    public static function statuses(): array
    {
        return [self::STATUS_AVAILABLE, self::STATUS_PENDING, self::STATUS_ADOPTED, self::STATUS_MEDICAL_HOLD];
    }

    public static function genders(): array
    {
        return [self::GENDER_MALE, self::GENDER_FEMALE, self::GENDER_UNKNOWN];
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE    => 'badge-available',
            self::STATUS_PENDING      => 'badge-pending',
            self::STATUS_ADOPTED      => 'badge-adopted',
            self::STATUS_MEDICAL_HOLD => 'badge-rejected',
            default                   => 'badge-pending',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_MEDICAL_HOLD => 'Medical Hold',
            default                   => ucfirst($this->status),
        };
    }
}
