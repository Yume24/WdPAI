-- =============================================================================
-- FurEver — PostgreSQL schema
--   * 3rd normal form, no redundancy
--   * Demonstrates 1:1 (users↔user_profiles), 1:N (multiple), M:N (users↔volunteer_shifts)
--   * Views, function and trigger required by the assignment.
-- =============================================================================

BEGIN;

DROP TABLE IF EXISTS audit_log               CASCADE;
DROP TABLE IF EXISTS volunteer_assignments   CASCADE;
DROP TABLE IF EXISTS volunteer_shifts        CASCADE;
DROP TABLE IF EXISTS adoption_requests       CASCADE;
DROP TABLE IF EXISTS medical_records         CASCADE;
DROP TABLE IF EXISTS animals                 CASCADE;
DROP TABLE IF EXISTS species                 CASCADE;
DROP TABLE IF EXISTS user_profiles           CASCADE;
DROP TABLE IF EXISTS users                   CASCADE;
DROP TABLE IF EXISTS roles                   CASCADE;

-- ----------------------------------------------------------------------------
-- Lookup: roles
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(32)  UNIQUE NOT NULL,
    description VARCHAR(255)
);

-- ----------------------------------------------------------------------------
-- Users (account/credentials only — extended fields live in user_profiles, 1:1)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id          SERIAL PRIMARY KEY,
    username    VARCHAR(50)  UNIQUE NOT NULL,
    email       VARCHAR(255) UNIQUE NOT NULL,
    password    TEXT NOT NULL,
    role_id     INT NOT NULL REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    is_active   BOOLEAN DEFAULT TRUE NOT NULL,
    created_at  TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX idx_users_role ON users(role_id);

-- ----------------------------------------------------------------------------
-- 1:1 relation — user_profiles
-- ----------------------------------------------------------------------------
CREATE TABLE user_profiles (
    user_id     INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    full_name   VARCHAR(120),
    phone       VARCHAR(40),
    address     TEXT,
    bio         TEXT,
    avatar_path VARCHAR(255)
);

-- ----------------------------------------------------------------------------
-- Lookup: species
-- ----------------------------------------------------------------------------
CREATE TABLE species (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(40) UNIQUE NOT NULL,
    icon VARCHAR(40)
);

-- ----------------------------------------------------------------------------
-- Animals (1:N from species, 1:N from users via created_by)
-- ----------------------------------------------------------------------------
CREATE TABLE animals (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(80) NOT NULL,
    species_id    INT NOT NULL REFERENCES species(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    breed         VARCHAR(80),
    gender        VARCHAR(10) NOT NULL DEFAULT 'unknown'
                  CHECK (gender IN ('male','female','unknown')),
    date_of_birth DATE,
    intake_date   DATE NOT NULL DEFAULT CURRENT_DATE,
    status        VARCHAR(20) NOT NULL DEFAULT 'available'
                  CHECK (status IN ('available','pending','adopted','medical_hold')),
    description   TEXT,
    photo_path    VARCHAR(255),
    created_by    INT REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    created_at    TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at    TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX idx_animals_species ON animals(species_id);
CREATE INDEX idx_animals_status  ON animals(status);

-- ----------------------------------------------------------------------------
-- 1:N — animals → medical_records
-- ----------------------------------------------------------------------------
CREATE TABLE medical_records (
    id          SERIAL PRIMARY KEY,
    animal_id   INT NOT NULL REFERENCES animals(id) ON DELETE CASCADE ON UPDATE CASCADE,
    record_date DATE NOT NULL,
    vet_name    VARCHAR(120),
    diagnosis   VARCHAR(255),
    treatment   TEXT,
    notes       TEXT
);

CREATE INDEX idx_medical_animal ON medical_records(animal_id);

-- ----------------------------------------------------------------------------
-- 1:N — animals/users → adoption_requests
-- ----------------------------------------------------------------------------
CREATE TABLE adoption_requests (
    id              SERIAL PRIMARY KEY,
    animal_id       INT NOT NULL REFERENCES animals(id) ON DELETE CASCADE ON UPDATE CASCADE,
    applicant_id    INT NOT NULL REFERENCES users(id)   ON DELETE CASCADE ON UPDATE CASCADE,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','approved','rejected','withdrawn')),
    message         TEXT,
    submitted_at    TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    reviewed_by     INT REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    reviewed_at     TIMESTAMPTZ,
    decision_notes  TEXT,
    UNIQUE (animal_id, applicant_id)
);

CREATE INDEX idx_adoption_animal    ON adoption_requests(animal_id);
CREATE INDEX idx_adoption_applicant ON adoption_requests(applicant_id);
CREATE INDEX idx_adoption_status    ON adoption_requests(status);

-- ----------------------------------------------------------------------------
-- M:N — users ↔ volunteer_shifts via volunteer_assignments
-- ----------------------------------------------------------------------------
CREATE TABLE volunteer_shifts (
    id               SERIAL PRIMARY KEY,
    shift_date       DATE NOT NULL,
    start_time       TIME NOT NULL,
    end_time         TIME NOT NULL,
    task_description TEXT,
    location         VARCHAR(120),
    capacity         INT NOT NULL DEFAULT 1 CHECK (capacity > 0),
    created_by       INT REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CHECK (end_time > start_time)
);

CREATE INDEX idx_shift_date ON volunteer_shifts(shift_date);

CREATE TABLE volunteer_assignments (
    volunteer_id INT NOT NULL REFERENCES users(id)             ON DELETE CASCADE ON UPDATE CASCADE,
    shift_id     INT NOT NULL REFERENCES volunteer_shifts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    status       VARCHAR(16) NOT NULL DEFAULT 'signed_up'
                 CHECK (status IN ('signed_up','confirmed','completed','cancelled')),
    assigned_at  TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (volunteer_id, shift_id)
);

-- ----------------------------------------------------------------------------
-- Audit trail (populated by trigger below)
-- ----------------------------------------------------------------------------
CREATE TABLE audit_log (
    id          SERIAL PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL,
    entity_id   INT NOT NULL,
    action      VARCHAR(16) NOT NULL,
    old_data    JSONB,
    new_data    JSONB,
    changed_by  INT REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    changed_at  TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX idx_audit_entity ON audit_log(entity_type, entity_id);

-- =============================================================================
-- VIEWS (≥ 2, with multi-table JOINs)
-- =============================================================================

CREATE OR REPLACE VIEW v_animal_directory AS
SELECT a.id,
       a.name,
       s.name             AS species,
       s.icon             AS species_icon,
       a.breed,
       a.gender,
       a.status,
       a.photo_path,
       a.intake_date,
       COUNT(ar.id) FILTER (WHERE ar.status = 'pending') AS pending_requests
  FROM animals a
  JOIN species s ON s.id = a.species_id
  LEFT JOIN adoption_requests ar ON ar.animal_id = a.id
 GROUP BY a.id, s.name, s.icon;

CREATE OR REPLACE VIEW v_adoption_pipeline AS
SELECT ar.id,
       ar.status,
       ar.message,
       ar.submitted_at,
       ar.reviewed_at,
       ar.reviewed_by,
       ar.decision_notes,
       a.id              AS animal_id,
       a.name            AS animal_name,
       sp.name           AS species,
       a.photo_path      AS animal_photo,
       u.id              AS applicant_id,
       u.email           AS applicant_email,
       COALESCE(up.full_name, u.username) AS applicant_name,
       rev.email         AS reviewer_email
  FROM adoption_requests ar
  JOIN animals  a   ON a.id  = ar.animal_id
  JOIN species  sp  ON sp.id = a.species_id
  JOIN users    u   ON u.id  = ar.applicant_id
  LEFT JOIN user_profiles up ON up.user_id = u.id
  LEFT JOIN users rev ON rev.id = ar.reviewed_by;

CREATE OR REPLACE VIEW v_volunteer_schedule AS
SELECT vs.id   AS shift_id,
       vs.shift_date,
       vs.start_time,
       vs.end_time,
       vs.task_description,
       vs.location,
       vs.capacity,
       u.id    AS volunteer_id,
       COALESCE(up.full_name, u.username) AS volunteer_name,
       u.email AS volunteer_email,
       va.status
  FROM volunteer_shifts vs
  LEFT JOIN volunteer_assignments va ON va.shift_id = vs.id
  LEFT JOIN users u                  ON u.id = va.volunteer_id
  LEFT JOIN user_profiles up         ON up.user_id = u.id;

-- =============================================================================
-- FUNCTION (≥ 1) — animal age in months, derived from DOB or fallback to intake_date
-- =============================================================================

CREATE OR REPLACE FUNCTION fn_animal_age_months(p_animal_id INT)
RETURNS INT
LANGUAGE plpgsql
AS $$
DECLARE
    v_dob    DATE;
    v_intake DATE;
    v_ref    DATE;
BEGIN
    SELECT date_of_birth, intake_date INTO v_dob, v_intake
      FROM animals WHERE id = p_animal_id;
    IF NOT FOUND THEN
        RETURN NULL;
    END IF;
    v_ref := COALESCE(v_dob, v_intake);
    RETURN EXTRACT(YEAR  FROM AGE(CURRENT_DATE, v_ref))::INT * 12
         + EXTRACT(MONTH FROM AGE(CURRENT_DATE, v_ref))::INT;
END; $$;

-- =============================================================================
-- TRIGGER (≥ 1) — write every change on animals into the audit log
-- =============================================================================

CREATE OR REPLACE FUNCTION trg_audit_animal_change() RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        INSERT INTO audit_log (entity_type, entity_id, action, old_data, new_data)
        VALUES ('animal', OLD.id, TG_OP, to_jsonb(OLD), NULL);
        RETURN OLD;
    ELSIF TG_OP = 'INSERT' THEN
        INSERT INTO audit_log (entity_type, entity_id, action, old_data, new_data)
        VALUES ('animal', NEW.id, TG_OP, NULL, to_jsonb(NEW));
        RETURN NEW;
    ELSE
        INSERT INTO audit_log (entity_type, entity_id, action, old_data, new_data)
        VALUES ('animal', NEW.id, TG_OP, to_jsonb(OLD), to_jsonb(NEW));
        RETURN NEW;
    END IF;
END; $$;

CREATE TRIGGER tr_animals_audit
AFTER INSERT OR UPDATE OR DELETE ON animals
FOR EACH ROW EXECUTE FUNCTION trg_audit_animal_change();

-- =============================================================================
-- Login attempts — backing store for brute-force rate limiting
-- =============================================================================

CREATE TABLE login_attempts (
    id            SERIAL PRIMARY KEY,
    ip_address    INET        NOT NULL,
    email         VARCHAR(255),
    attempted_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    success       BOOLEAN     NOT NULL DEFAULT FALSE
);
CREATE INDEX idx_login_attempts_ip_time    ON login_attempts(ip_address, attempted_at);
CREATE INDEX idx_login_attempts_email_time ON login_attempts(email, attempted_at);

COMMIT;
