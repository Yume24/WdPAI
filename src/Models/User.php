<?php

namespace FurEver\Models;

final class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public string $password,
        public int $roleId,
        public string $roleName,
        public bool $isActive,
        public ?string $createdAt = null,
        public ?string $fullName = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $bio = null,
        public ?string $avatarPath = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:         (int) $row['id'],
            username:   (string) $row['username'],
            email:      (string) $row['email'],
            password:   (string) ($row['password'] ?? ''),
            roleId:     (int) ($row['role_id'] ?? 0),
            roleName:   (string) ($row['role_name'] ?? $row['role'] ?? ''),
            isActive:   (bool) ($row['is_active'] ?? true),
            createdAt:  $row['created_at'] ?? null,
            fullName:   $row['full_name'] ?? null,
            phone:      $row['phone'] ?? null,
            address:    $row['address'] ?? null,
            bio:        $row['bio'] ?? null,
            avatarPath: $row['avatar_path'] ?? null,
        );
    }

    public function displayName(): string
    {
        return $this->fullName !== null && $this->fullName !== '' ? $this->fullName : $this->username;
    }

    public function initials(): string
    {
        $name = $this->displayName();
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $initials !== '' ? $initials : 'U';
    }
}
