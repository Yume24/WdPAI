<?php

namespace FurEver\Repositories;

use FurEver\Models\Role;

final class RolesRepository extends Repository
{
    /** @return Role[] */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, description FROM roles ORDER BY id');
        return array_map([Role::class, 'fromRow'], $stmt->fetchAll());
    }

    public function findByName(string $name): ?Role
    {
        $stmt = $this->pdo->prepare('SELECT id, name, description FROM roles WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ? Role::fromRow($row) : null;
    }

    public function findById(int $id): ?Role
    {
        $stmt = $this->pdo->prepare('SELECT id, name, description FROM roles WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Role::fromRow($row) : null;
    }
}
