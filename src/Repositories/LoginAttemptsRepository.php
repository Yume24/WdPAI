<?php

namespace FurEver\Repositories;

class LoginAttemptsRepository extends Repository
{
    public function record(string $ip, ?string $email, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip_address, email, success) VALUES (:ip, :email, :ok)'
        );
        $stmt->execute([':ip' => $ip, ':email' => $email, ':ok' => $success ? 'true' : 'false']);
    }

    public function recentFailuresByIp(string $ip, int $windowMinutes): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = :ip
                AND success = FALSE
                AND attempted_at > NOW() - (:mins || ' minutes')::interval"
        );
        $stmt->execute([':ip' => $ip, ':mins' => (string) $windowMinutes]);
        return (int) $stmt->fetchColumn();
    }

    public function clearForEmail(string $email): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE email = :email AND success = FALSE');
        $stmt->execute([':email' => $email]);
    }
}
