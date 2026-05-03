<?php

namespace FurEver\Services;

use FurEver\Core\Database;
use FurEver\Models\AdoptionRequest;
use FurEver\Models\Animal;
use FurEver\Repositories\AdoptionsRepository;
use FurEver\Repositories\AnimalsRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AdoptionService
{
    public function __construct(
        private AdoptionsRepository $adoptions,
        private AnimalsRepository $animals,
    ) {}

    public static function create(): self
    {
        return new self(new AdoptionsRepository(), new AnimalsRepository());
    }

    public function submit(int $animalId, int $applicantId, ?string $message): int
    {
        $animal = $this->animals->findById($animalId);
        if (!$animal) {
            throw new InvalidArgumentException('Animal not found.');
        }
        if ($animal->status === Animal::STATUS_ADOPTED) {
            throw new InvalidArgumentException('This animal has already been adopted.');
        }
        if ($this->adoptions->existsForApplicant($animalId, $applicantId)) {
            throw new InvalidArgumentException('You have already submitted a request for this animal.');
        }

        return $this->adoptions->create($animalId, $applicantId, $message);
    }

    /**
     * Approve a request inside a SERIALIZABLE transaction:
     * - Locks the animal row.
     * - Verifies it is still available.
     * - Marks this request approved, all other pending ones for the same animal rejected.
     * - Sets the animal to "adopted".
     */
    public function approve(int $requestId, int $reviewerId, ?string $notes = null): void
    {
        $pdo = Database::getInstance();

        $request = $this->adoptions->findById($requestId);
        if (!$request) {
            throw new InvalidArgumentException('Adoption request not found.');
        }
        if ($request->status !== AdoptionRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending requests can be approved.');
        }

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status FROM animals WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $request->animalId]);
            $status = $lock->fetchColumn();
            if ($status === false) {
                throw new RuntimeException('Animal disappeared.');
            }
            if ($status !== Animal::STATUS_AVAILABLE && $status !== Animal::STATUS_PENDING) {
                throw new InvalidArgumentException('Animal is no longer available for adoption.');
            }

            $approve = $pdo->prepare('
                UPDATE adoption_requests
                   SET status = :s, reviewed_by = :r, reviewed_at = NOW(), decision_notes = :n
                 WHERE id = :id AND status = :pending
            ');
            $approve->execute([
                ':s'       => AdoptionRequest::STATUS_APPROVED,
                ':r'       => $reviewerId,
                ':n'       => $notes,
                ':id'      => $requestId,
                ':pending' => AdoptionRequest::STATUS_PENDING,
            ]);
            if ($approve->rowCount() === 0) {
                throw new InvalidArgumentException('Request was modified concurrently.');
            }

            $rejectOthers = $pdo->prepare('
                UPDATE adoption_requests
                   SET status = :s, reviewed_by = :r, reviewed_at = NOW(),
                       decision_notes = :n
                 WHERE animal_id = :a AND status = :pending AND id <> :id
            ');
            $rejectOthers->execute([
                ':s'       => AdoptionRequest::STATUS_REJECTED,
                ':r'       => $reviewerId,
                ':n'       => 'Auto-rejected because another applicant was approved.',
                ':a'       => $request->animalId,
                ':pending' => AdoptionRequest::STATUS_PENDING,
                ':id'      => $requestId,
            ]);

            $markAdopted = $pdo->prepare('UPDATE animals SET status = :s, updated_at = NOW() WHERE id = :id');
            $markAdopted->execute([':s' => Animal::STATUS_ADOPTED, ':id' => $request->animalId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reject(int $requestId, int $reviewerId, ?string $notes = null): void
    {
        $request = $this->adoptions->findById($requestId);
        if (!$request) {
            throw new InvalidArgumentException('Adoption request not found.');
        }
        if ($request->status !== AdoptionRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending requests can be rejected.');
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('
            UPDATE adoption_requests
               SET status = :s, reviewed_by = :r, reviewed_at = NOW(), decision_notes = :n
             WHERE id = :id AND status = :pending
        ');
        $stmt->execute([
            ':s'       => AdoptionRequest::STATUS_REJECTED,
            ':r'       => $reviewerId,
            ':n'       => $notes,
            ':id'      => $requestId,
            ':pending' => AdoptionRequest::STATUS_PENDING,
        ]);
    }
}
