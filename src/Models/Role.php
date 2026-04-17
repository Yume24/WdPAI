<?php

namespace FurEver\Models;

final class Role
{
    public const ADMIN     = 'admin';
    public const WORKER    = 'worker';
    public const VOLUNTEER = 'volunteer';
    public const ADOPTER   = 'adopter';

    public function __construct(
        public int $id,
        public string $name,
        public ?string $description = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            $row['description'] ?? null,
        );
    }
}
