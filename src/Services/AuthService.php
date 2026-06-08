<?php

namespace FurEver\Services;

use FurEver\Core\Database;
use FurEver\Core\Session;
use FurEver\Models\Role;
use FurEver\Models\User;
use FurEver\Repositories\LoginAttemptsRepository;
use FurEver\Repositories\RolesRepository;
use FurEver\Repositories\UserProfilesRepository;
use FurEver\Repositories\UsersRepository;
use InvalidArgumentException;
use RuntimeException;

final class AuthService
{
    private const RATE_LIMIT_MAX     = 5;
    private const RATE_LIMIT_WINDOW  = 10; // minutes

    public function __construct(
        private UsersRepository $users,
        private RolesRepository $roles,
        private UserProfilesRepository $profiles,
        private LoginAttemptsRepository $attempts,
    ) {}

    public static function create(): self
    {
        return new self(
            UsersRepository::getInstance(),
            new RolesRepository(),
            new UserProfilesRepository(),
            new LoginAttemptsRepository()
        );
    }

    public function register(string $email, string $username, string $password, string $confirm, string $defaultRole = Role::ADOPTER): User
    {
        if ($password !== $confirm) {
            throw new InvalidArgumentException('Passwords do not match.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        if ($this->users->findByEmail($email)) {
            throw new InvalidArgumentException('Email already registered.');
        }
        if ($this->users->findByUsername($username)) {
            throw new InvalidArgumentException('Username already taken.');
        }

        $role = $this->roles->findByName($defaultRole);
        if (!$role) {
            throw new RuntimeException("Role '$defaultRole' is missing in database.");
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $userId = $this->users->create($username, $email, $hash, $role->id);
            $this->profiles->upsert($userId, null, null, null, null, null);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $user = $this->users->findById($userId);
        if (!$user) {
            throw new RuntimeException('User created but could not be loaded.');
        }
        return $user;
    }

    public function login(string $email, string $password): User
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->attempts->recentFailuresByIp($ip, self::RATE_LIMIT_WINDOW) >= self::RATE_LIMIT_MAX) {
            throw new InvalidArgumentException(
                'Too many failed attempts. Please wait a few minutes and try again.'
            );
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !$user->isActive || !password_verify($password, $user->password)) {
            $this->attempts->record($ip, $email, false);
            throw new InvalidArgumentException('Invalid credentials.');
        }

        $this->attempts->record($ip, $email, true);
        $this->attempts->clearForEmail($email);

        Session::regenerate();
        Session::set('user_id', $user->id);
        Session::set('role', $user->roleName);
        Session::set('username', $user->username);
        Session::set('email', $user->email);

        return $user;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function currentUser(): ?User
    {
        $id = Session::userId();
        if ($id === null) {
            return null;
        }
        return $this->users->findById($id);
    }

    public function hasRole(string ...$roles): bool
    {
        $current = Session::role();
        return $current !== null && in_array($current, $roles, true);
    }
}
