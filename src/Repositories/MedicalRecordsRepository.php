<?php

namespace FurEver\Repositories;

use FurEver\Models\MedicalRecord;

final class MedicalRecordsRepository extends Repository
{
    /** @return MedicalRecord[] */
    public function forAnimal(int $animalId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, animal_id, record_date, vet_name, diagnosis, treatment, notes
              FROM medical_records
             WHERE animal_id = :id
             ORDER BY record_date DESC, id DESC
        ');
        $stmt->execute([':id' => $animalId]);
        return array_map([MedicalRecord::class, 'fromRow'], $stmt->fetchAll());
    }

    public function create(int $animalId, string $recordDate, ?string $vetName, ?string $diagnosis, ?string $treatment, ?string $notes): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO medical_records (animal_id, record_date, vet_name, diagnosis, treatment, notes)
            VALUES (:a, :d, :v, :dx, :t, :n)
            RETURNING id
        ');
        $stmt->execute([
            ':a' => $animalId, ':d' => $recordDate, ':v' => $vetName,
            ':dx' => $diagnosis, ':t' => $treatment, ':n' => $notes,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
