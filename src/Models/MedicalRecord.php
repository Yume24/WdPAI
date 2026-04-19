<?php

namespace FurEver\Models;

final class MedicalRecord
{
    public function __construct(
        public int $id,
        public int $animalId,
        public string $recordDate,
        public ?string $vetName,
        public ?string $diagnosis,
        public ?string $treatment,
        public ?string $notes,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['animal_id'],
            (string) $row['record_date'],
            $row['vet_name'] ?? null,
            $row['diagnosis'] ?? null,
            $row['treatment'] ?? null,
            $row['notes'] ?? null,
        );
    }
}
