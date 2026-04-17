<?php

namespace FurEver\Models;

final class UserProfile
{
    public function __construct(
        public int $userId,
        public ?string $fullName = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $bio = null,
        public ?string $avatarPath = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            userId:     (int) $row['user_id'],
            fullName:   $row['full_name'] ?? null,
            phone:      $row['phone'] ?? null,
            address:    $row['address'] ?? null,
            bio:        $row['bio'] ?? null,
            avatarPath: $row['avatar_path'] ?? null,
        );
    }
}
