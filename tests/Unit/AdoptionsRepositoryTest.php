<?php

declare(strict_types=1);

namespace FurEver\Tests\Unit;

use FurEver\Core\Database;
use FurEver\Repositories\AdoptionsRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdoptionsRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AdoptionsRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec("
            CREATE TABLE roles    (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE species  (id INTEGER PRIMARY KEY, name TEXT, icon TEXT);
            CREATE TABLE users    (id INTEGER PRIMARY KEY, username TEXT, email TEXT, role_id INTEGER);
            CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, full_name TEXT);
            CREATE TABLE animals  (id INTEGER PRIMARY KEY, name TEXT, species_id INTEGER, photo_path TEXT, status TEXT DEFAULT 'available');
            CREATE TABLE adoption_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                animal_id INTEGER, applicant_id INTEGER,
                status TEXT DEFAULT 'pending',
                message TEXT,
                submitted_at TEXT DEFAULT CURRENT_TIMESTAMP,
                reviewed_by INTEGER, reviewed_at TEXT, decision_notes TEXT,
                UNIQUE (animal_id, applicant_id)
            );
            -- Mimic v_adoption_pipeline as a SQLite view
            CREATE VIEW v_adoption_pipeline AS
            SELECT ar.id, ar.status, ar.message, ar.submitted_at, ar.reviewed_at, ar.reviewed_by, ar.decision_notes,
                   a.id AS animal_id, a.name AS animal_name, sp.name AS species, a.photo_path AS animal_photo,
                   u.id AS applicant_id, u.email AS applicant_email,
                   COALESCE(up.full_name, u.username) AS applicant_name,
                   rev.email AS reviewer_email
              FROM adoption_requests ar
              JOIN animals  a   ON a.id  = ar.animal_id
              JOIN species  sp  ON sp.id = a.species_id
              JOIN users    u   ON u.id  = ar.applicant_id
              LEFT JOIN user_profiles up ON up.user_id = u.id
              LEFT JOIN users rev ON rev.id = ar.reviewed_by;
        ");
        $this->pdo->exec("
            INSERT INTO species  (id, name) VALUES (1, 'Dog');
            INSERT INTO users    (id, username, email, role_id) VALUES (1, 'adam', 'adam@x.test', 1), (2, 'beth', 'beth@x.test', 1);
            INSERT INTO animals  (id, name, species_id) VALUES (1, 'Rex', 1), (2, 'Bella', 1);
        ");

        Database::setInstance($this->pdo);

        // Override Postgres-specific RETURNING with SQLite lastInsertId.
        $this->repo = new class extends AdoptionsRepository {
            public function create(int $animalId, int $applicantId, ?string $message): int
            {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO adoption_requests (animal_id, applicant_id, message) VALUES (:a, :u, :m)'
                );
                $stmt->execute([':a' => $animalId, ':u' => $applicantId, ':m' => $message]);
                return (int) $this->pdo->lastInsertId();
            }
        };
    }

    public function testCreateAndFind(): void
    {
        $id = $this->repo->create(1, 1, 'Loving home');
        $this->assertGreaterThan(0, $id);
        $found = $this->repo->findById($id);
        $this->assertNotNull($found);
        $this->assertSame('Rex', $found->animalName);
        $this->assertSame('adam@x.test', $found->applicantEmail);
        $this->assertSame('pending', $found->status);
    }

    public function testExistsForApplicantPreventsDuplicate(): void
    {
        $this->repo->create(1, 1, null);
        $this->assertTrue($this->repo->existsForApplicant(1, 1));
        $this->assertFalse($this->repo->existsForApplicant(2, 1));
    }

    public function testForApplicantScopesResults(): void
    {
        $this->repo->create(1, 1, null);
        $this->repo->create(2, 1, null);
        $this->repo->create(1, 2, null);
        $this->assertCount(2, $this->repo->forApplicant(1));
        $this->assertCount(1, $this->repo->forApplicant(2));
    }

    public function testCountPending(): void
    {
        $this->repo->create(1, 1, null);
        $this->repo->create(2, 2, null);
        $this->pdo->exec("UPDATE adoption_requests SET status='approved' WHERE id=1");
        $this->assertSame(1, $this->repo->countPending());
    }

    protected function tearDown(): void
    {
        Database::reset();
    }
}
