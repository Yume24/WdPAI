<?php

namespace FurEver\Repositories;

use FurEver\Models\User;
use PDO;

class UsersRepository extends Repository
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private const SELECT_BASE = '
        SELECT u.id, u.username, u.email, u.password, u.role_id, u.is_active, u.created_at,
               r.name AS role_name,
               p.full_name, p.phone, p.address, p.bio, p.avatar_path
          FROM users u
          JOIN roles r ON r.id = u.role_id
          LEFT JOIN user_profiles p ON p.user_id = u.id
    ';

    /** @return User[] */
    public function all(): array
    {
        $stmt = $this->pdo->query(self::SELECT_BASE . ' ORDER BY u.id');
        return array_map([User::class, 'fromRow'], $stmt->fetchAll());
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE u.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE u.email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE u.username = :username');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function create(string $username, string $email, string $passwordHash, int $roleId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password, role_id) VALUES (:u, :e, :p, :r) RETURNING id'
        );
        $stmt->execute([':u' => $username, ':e' => $email, ':p' => $passwordHash, ':r' => $roleId]);
        return (int) $stmt->fetchColumn();
    }

    public function updateRole(int $userId, int $roleId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role_id = :r WHERE id = :id');
        $stmt->execute([':r' => $roleId, ':id' => $userId]);
    }

    public function toggleActive(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = NOT is_active WHERE id = :id RETURNING is_active'
        );
        $stmt->execute([':id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function countByRole(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.name, COUNT(u.id) AS total
               FROM roles r
               LEFT JOIN users u ON u.role_id = r.id
              GROUP BY r.name'
        );
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['name']] = (int) $row['total'];
        }
        return $out;
    }

    public function delete(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }
}
