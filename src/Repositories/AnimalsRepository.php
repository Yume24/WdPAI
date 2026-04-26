<?php

namespace FurEver\Repositories;

use FurEver\Models\Animal;
use PDO;

final class AnimalsRepository extends Repository
{
    private const SELECT_BASE = '
        SELECT a.id, a.name, a.species_id, a.breed, a.gender, a.date_of_birth,
               a.intake_date, a.status, a.description, a.photo_path,
               a.created_by, a.created_at, a.updated_at,
               s.name AS species_name, s.icon AS species_icon,
               COALESCE(pending.cnt, 0) AS pending_requests
          FROM animals a
          JOIN species s ON s.id = a.species_id
          LEFT JOIN (
              SELECT animal_id, COUNT(*) AS cnt
                FROM adoption_requests
               WHERE status = ' . "'pending'" . '
               GROUP BY animal_id
          ) pending ON pending.animal_id = a.id
    ';

    /**
     * @param array{species_id?:int,status?:string,gender?:string,q?:string,limit?:int} $filters
     * @return Animal[]
     */
    public function filter(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['species_id'])) {
            $where[] = 'a.species_id = :species_id';
            $params[':species_id'] = (int) $filters['species_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (!empty($filters['gender'])) {
            $where[] = 'a.gender = :gender';
            $params[':gender'] = (string) $filters['gender'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.name ILIKE :q OR a.breed ILIKE :q OR a.description ILIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql = self::SELECT_BASE;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.created_at DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . (int) $filters['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([Animal::class, 'fromRow'], $stmt->fetchAll());
    }

    /** @return Animal[] */
    public function all(): array
    {
        return $this->filter([]);
    }

    /** @return Animal[] */
    public function featured(int $limit = 6): array
    {
        return $this->filter(['status' => Animal::STATUS_AVAILABLE, 'limit' => $limit]);
    }

    public function findById(int $id): ?Animal
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE a.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Animal::fromRow($row) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO animals (name, species_id, breed, gender, date_of_birth, intake_date,
                                 status, description, photo_path, created_by)
            VALUES (:name, :species_id, :breed, :gender, :dob, :intake, :status, :description, :photo, :created_by)
            RETURNING id
        ');
        $stmt->execute([
            ':name'        => $data['name'],
            ':species_id'  => (int) $data['species_id'],
            ':breed'       => $data['breed'] ?? null,
            ':gender'      => $data['gender'] ?? Animal::GENDER_UNKNOWN,
            ':dob'         => $data['date_of_birth'] ?? null,
            ':intake'      => $data['intake_date'],
            ':status'      => $data['status'] ?? Animal::STATUS_AVAILABLE,
            ':description' => $data['description'] ?? null,
            ':photo'       => $data['photo_path'] ?? null,
            ':created_by'  => $data['created_by'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        $params = [':id' => $id];
        $allowed = ['name','species_id','breed','gender','date_of_birth','intake_date','status','description','photo_path'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (!$sets) {
            return;
        }
        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE animals SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM animals WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function counts(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS cnt FROM animals GROUP BY status');
        $out = ['available'=>0,'pending'=>0,'adopted'=>0,'medical_hold'=>0,'total'=>0];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['status']] = (int) $row['cnt'];
            $out['total'] += (int) $row['cnt'];
        }
        return $out;
    }
}
