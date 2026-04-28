<?php

namespace FurEver\Repositories;

use FurEver\Models\AdoptionRequest;

class AdoptionsRepository extends Repository
{
    /** @return AdoptionRequest[] */
    public function all(?string $statusFilter = null): array
    {
        $sql = 'SELECT * FROM v_adoption_pipeline';
        $params = [];
        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            $sql .= ' WHERE status = :status';
            $params[':status'] = $statusFilter;
        }
        $sql .= ' ORDER BY submitted_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([AdoptionRequest::class, 'fromRow'], $stmt->fetchAll());
    }

    /** @return AdoptionRequest[] */
    public function forApplicant(int $applicantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM v_adoption_pipeline WHERE applicant_id = :id ORDER BY submitted_at DESC'
        );
        $stmt->execute([':id' => $applicantId]);
        return array_map([AdoptionRequest::class, 'fromRow'], $stmt->fetchAll());
    }

    public function findById(int $id): ?AdoptionRequest
    {
        $stmt = $this->pdo->prepare('SELECT * FROM v_adoption_pipeline WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? AdoptionRequest::fromRow($row) : null;
    }

    public function create(int $animalId, int $applicantId, ?string $message): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO adoption_requests (animal_id, applicant_id, message)
            VALUES (:a, :u, :m)
            RETURNING id
        ');
        $stmt->execute([':a' => $animalId, ':u' => $applicantId, ':m' => $message]);
        return (int) $stmt->fetchColumn();
    }

    public function existsForApplicant(int $animalId, int $applicantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM adoption_requests WHERE animal_id = :a AND applicant_id = :u LIMIT 1'
        );
        $stmt->execute([':a' => $animalId, ':u' => $applicantId]);
        return (bool) $stmt->fetchColumn();
    }

    public function countPending(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM adoption_requests WHERE status = 'pending'"
        )->fetchColumn();
    }
}
