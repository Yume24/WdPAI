# FurEver — Entity-Relationship Diagram

Authoritative source: `docker/db/init/01_schema.sql`. The diagram below mirrors that file. To produce a PNG, paste this Mermaid block into <https://mermaid.live> or render it directly on GitHub.

```mermaid
erDiagram
    roles ||--o{ users : "assigns"
    users ||--|| user_profiles : "1:1"
    species ||--o{ animals : "categorises"
    users ||--o{ animals : "created_by"
    animals ||--o{ medical_records : "history"
    animals ||--o{ adoption_requests : "applied for"
    users ||--o{ adoption_requests : "applicant"
    users ||--o{ adoption_requests : "reviewer"
    users ||--o{ volunteer_shifts : "created_by"
    users ||--o{ volunteer_assignments : "M:N user side"
    volunteer_shifts ||--o{ volunteer_assignments : "M:N shift side"
    users ||--o{ audit_log : "changed_by"

    roles {
        int  id PK
        text name UK
        text description
    }
    users {
        int    id PK
        text   username UK
        text   email UK
        text   password
        int    role_id FK
        bool   is_active
        ts     created_at
    }
    user_profiles {
        int  user_id PK,FK
        text full_name
        text phone
        text address
        text bio
        text avatar_path
    }
    species {
        int  id PK
        text name UK
        text icon
    }
    animals {
        int    id PK
        text   name
        int    species_id FK
        text   breed
        text   gender
        date   date_of_birth
        date   intake_date
        text   status
        text   description
        text   photo_path
        int    created_by FK
        ts     created_at
        ts     updated_at
    }
    medical_records {
        int  id PK
        int  animal_id FK
        date record_date
        text vet_name
        text diagnosis
        text treatment
        text notes
    }
    adoption_requests {
        int    id PK
        int    animal_id FK
        int    applicant_id FK
        text   status
        text   message
        ts     submitted_at
        int    reviewed_by FK
        ts     reviewed_at
        text   decision_notes
    }
    volunteer_shifts {
        int  id PK
        date shift_date
        time start_time
        time end_time
        text task_description
        text location
        int  capacity
        int  created_by FK
    }
    volunteer_assignments {
        int  volunteer_id PK,FK
        int  shift_id PK,FK
        text status
        ts   assigned_at
    }
    audit_log {
        int  id PK
        text entity_type
        int  entity_id
        text action
        json old_data
        json new_data
        int  changed_by FK
        ts   changed_at
    }
```

## Relationship coverage

| Type | Where |
|------|-------|
| 1:1  | `users` ↔ `user_profiles` (PK on user_id is also FK to users) |
| 1:N  | `roles` → `users`, `species` → `animals`, `animals` → `medical_records`, `animals` → `adoption_requests`, `users` (applicant) → `adoption_requests`, `users` → `volunteer_shifts.created_by` |
| M:N  | `users` ↔ `volunteer_shifts` via `volunteer_assignments` (composite PK) |

## Database extras (assignment requirements)

| Required | Implementation | File |
|----------|----------------|------|
| ≥ 2 multi-table views | `v_animal_directory`, `v_adoption_pipeline`, `v_volunteer_schedule` | `docker/db/init/01_schema.sql` |
| ≥ 1 trigger | `tr_animals_audit` (AFTER INSERT/UPDATE/DELETE on animals) writes JSONB diff to `audit_log` | same |
| ≥ 1 function | `fn_animal_age_months(p_animal_id)` | same |
| Transaction at non-default isolation | `AdoptionService::approve()` runs inside `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE` with row-level `SELECT … FOR UPDATE` | `src/Services/AdoptionService.php` |
| FK actions with JOINs | `ON DELETE CASCADE` on profile, medical, adoption, assignment; `RESTRICT` on roles/species; `SET NULL` on optional reviewer/creator references; views demonstrate the JOINs | schema |
| 3NF / no redundancy | All non-key attributes depend only on the PK; lookup data lives in `roles` and `species`; no derived columns stored | schema |
