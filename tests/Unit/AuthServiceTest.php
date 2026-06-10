<?php

declare(strict_types=1);

namespace FurEver\Tests\Unit;

use FurEver\Core\Database;
use FurEver\Models\Role;
use FurEver\Models\User;
use FurEver\Repositories\LoginAttemptsRepository;
use FurEver\Repositories\RolesRepository;
use FurEver\Repositories\UserProfilesRepository;
use FurEver\Repositories\UsersRepository;
use FurEver\Services\AuthService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private PDO $pdo;
    private AuthService $auth;

    protected function setUp(): void
    {
        // In-memory SQLite with just enough schema for the auth flow.
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec("
            CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, description TEXT);
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE, email TEXT UNIQUE, password TEXT,
                role_id INTEGER, is_active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE user_profiles (
                user_id INTEGER PRIMARY KEY,
                full_name TEXT, phone TEXT, address TEXT, bio TEXT, avatar_path TEXT
            );
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                email TEXT,
                attempted_at TEXT DEFAULT CURRENT_TIMESTAMP,
                success INTEGER DEFAULT 0
            );
            INSERT INTO roles (name) VALUES ('admin'),('worker'),('volunteer'),('adopter');
        ");
        Database::setInstance($this->pdo);

        // Repository subclasses swap PostgreSQL-specific SQL for SQLite-compatible equivalents.
        $this->auth = new AuthService(
            new class extends UsersRepository {
                public function create(string $username, string $email, string $passwordHash, int $roleId): int
                {
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO users (username, email, password, role_id) VALUES (:u, :e, :p, :r)'
                    );
                    $stmt->execute([':u' => $username, ':e' => $email, ':p' => $passwordHash, ':r' => $roleId]);
                    return (int) $this->pdo->lastInsertId();
                }

                public function findById(int $id): ?User
                {
                    $stmt = $this->pdo->prepare('
                        SELECT u.id, u.username, u.email, u.password, u.role_id, u.is_active, u.created_at,
                               r.name AS role_name,
                               p.full_name, p.phone, p.address, p.bio, p.avatar_path
                          FROM users u
                          JOIN roles r ON r.id = u.role_id
                          LEFT JOIN user_profiles p ON p.user_id = u.id
                         WHERE u.id = :id
                    ');
                    $stmt->execute([':id' => $id]);
                    $row = $stmt->fetch();
                    return $row ? User::fromRow($row) : null;
                }

                public function findByEmail(string $email): ?User
                {
                    $stmt = $this->pdo->prepare('
                        SELECT u.id, u.username, u.email, u.password, u.role_id, u.is_active, u.created_at,
                               r.name AS role_name,
                               p.full_name, p.phone, p.address, p.bio, p.avatar_path
                          FROM users u
                          JOIN roles r ON r.id = u.role_id
                          LEFT JOIN user_profiles p ON p.user_id = u.id
                         WHERE u.email = :email
                    ');
                    $stmt->execute([':email' => $email]);
                    $row = $stmt->fetch();
                    return $row ? User::fromRow($row) : null;
                }

                public function findByUsername(string $username): ?User
                {
                    $stmt = $this->pdo->prepare('
                        SELECT u.id, u.username, u.email, u.password, u.role_id, u.is_active, u.created_at,
                               r.name AS role_name,
                               p.full_name, p.phone, p.address, p.bio, p.avatar_path
                          FROM users u
                          JOIN roles r ON r.id = u.role_id
                          LEFT JOIN user_profiles p ON p.user_id = u.id
                         WHERE u.username = :u
                    ');
                    $stmt->execute([':u' => $username]);
                    $row = $stmt->fetch();
                    return $row ? User::fromRow($row) : null;
                }
            },
            new RolesRepository(),
            new class extends UserProfilesRepository {
                public function upsert(int $userId, ?string $fullName, ?string $phone, ?string $address, ?string $bio, ?string $avatarPath = null): void
                {
                    $stmt = $this->pdo->prepare(
                        'INSERT OR REPLACE INTO user_profiles (user_id, full_name, phone, address, bio, avatar_path)
                         VALUES (:id, :fn, :ph, :ad, :bi, :av)'
                    );
                    $stmt->execute([
                        ':id' => $userId, ':fn' => $fullName, ':ph' => $phone,
                        ':ad' => $address, ':bi' => $bio, ':av' => $avatarPath,
                    ]);
                }
            },
            new class extends LoginAttemptsRepository {
                public function record(string $ip, ?string $email, bool $success): void
                {
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO login_attempts (ip_address, email, success) VALUES (:ip, :email, :ok)'
                    );
                    $stmt->execute([':ip' => $ip, ':email' => $email, ':ok' => $success ? 1 : 0]);
                }

                public function recentFailuresByIp(string $ip, int $windowMinutes): int
                {
                    $stmt = $this->pdo->prepare(
                        "SELECT COUNT(*) FROM login_attempts
                          WHERE ip_address = :ip
                            AND success = 0
                            AND attempted_at > datetime('now', :win)"
                    );
                    $stmt->execute([':ip' => $ip, ':win' => '-' . $windowMinutes . ' minutes']);
                    return (int) $stmt->fetchColumn();
                }

                public function clearForEmail(string $email): void
                {
                    $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE email = :email AND success = 0');
                    $stmt->execute([':email' => $email]);
                }
            },
        );
    }

    public function testRegisterCreatesUserAndProfile(): void
    {
        $user = $this->auth->register('alice@example.com', 'alice', 'password123', 'password123', Role::ADOPTER);
        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame('adopter', $user->roleName);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM user_profiles')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testRegisterRejectsMismatchedPasswords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->auth->register('bob@example.com', 'bob', 'password123', 'wrongmatch1', Role::ADOPTER);
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->auth->register('carol@example.com', 'carol', 'short', 'short', Role::ADOPTER);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->auth->register('dave@example.com', 'dave', 'password123', 'password123', Role::ADOPTER);
        $this->expectException(InvalidArgumentException::class);
        $this->auth->register('dave@example.com', 'dave2', 'password123', 'password123', Role::ADOPTER);
    }

    public function testLoginRoundTrip(): void
    {
        $this->auth->register('eve@example.com', 'eve', 'password123', 'password123', Role::ADOPTER);
        $user = $this->auth->login('eve@example.com', 'password123');
        $this->assertSame('eve@example.com', $user->email);
        $this->assertSame((int) $user->id, $_SESSION['user_id'] ?? null);
        $this->assertSame('adopter', $_SESSION['role'] ?? null);
    }

    public function testLoginRejectsBadPassword(): void
    {
        $this->auth->register('frank@example.com', 'frank', 'password123', 'password123', Role::ADOPTER);
        $this->expectException(InvalidArgumentException::class);
        $this->auth->login('frank@example.com', 'nope');
    }

    protected function tearDown(): void
    {
        Database::reset();
        $_SESSION = [];
    }
}
