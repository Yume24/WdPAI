<?php

namespace FurEver\Repositories;

class UserProfilesRepository extends Repository
{
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
