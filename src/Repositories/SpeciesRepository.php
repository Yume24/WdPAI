<?php

namespace FurEver\Repositories;

use FurEver\Models\Species;

final class SpeciesRepository extends Repository
{
    /** @return Species[] */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, icon FROM species ORDER BY name');
        return array_map([Species::class, 'fromRow'], $stmt->fetchAll());
    }

    public function findById(int $id): ?Species
    {
        $stmt = $this->pdo->prepare('SELECT id, name, icon FROM species WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Species::fromRow($row) : null;
    }
}
