<?php

namespace FurEver\Models;

final class Species
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $icon = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            $row['icon'] ?? null,
        );
    }
}
