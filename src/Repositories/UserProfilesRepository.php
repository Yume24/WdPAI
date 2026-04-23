<?php

namespace FurEver\Repositories;

use FurEver\Models\UserProfile;

class UserProfilesRepository extends Repository
{
    public function findByUserId(int $userId): ?UserProfile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_profiles WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ? UserProfile::fromRow($row) : null;
    }

    public function upsert(
        int $userId,
        ?string $fullName,
        ?string $phone,
        ?string $address,
        ?string $bio,
        ?string $avatarPath = null
    ): void {
        $sql = '
            INSERT INTO user_profiles (user_id, full_name, phone, address, bio, avatar_path)
            VALUES (:id, :fn, :ph, :ad, :bi, :av)
            ON CONFLICT (user_id) DO UPDATE
              SET full_name  = EXCLUDED.full_name,
                  phone      = EXCLUDED.phone,
                  address    = EXCLUDED.address,
                  bio        = EXCLUDED.bio,
                  avatar_path = COALESCE(EXCLUDED.avatar_path, user_profiles.avatar_path)
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $userId,
            ':fn' => $fullName,
            ':ph' => $phone,
            ':ad' => $address,
            ':bi' => $bio,
            ':av' => $avatarPath,
        ]);
    }
}
