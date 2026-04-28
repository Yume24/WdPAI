<?php

namespace FurEver\Repositories;

final class AuditLogRepository extends Repository
{
    public function recent(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, entity_type, entity_id, action, changed_by, changed_at
              FROM audit_log
             ORDER BY changed_at DESC
             LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
