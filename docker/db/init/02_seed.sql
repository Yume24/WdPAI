-- =============================================================================
-- FurEver — sample data
-- Every seeded user has the password:  password
-- bcrypt hash below was produced with htpasswd -bnBC 10 "" password
-- =============================================================================

BEGIN;

-- Roles -----------------------------------------------------------------------
INSERT INTO roles (name, description) VALUES
  ('admin',     'Shelter administrator with full access'),
  ('worker',    'Shelter staff: manages animals, adoptions, shifts'),
  ('volunteer', 'Volunteer who signs up for shifts'),
  ('adopter',   'Member of the public who can submit adoption requests')
ON CONFLICT (name) DO NOTHING;

-- Users -----------------------------------------------------------------------
INSERT INTO users (username, email, password, role_id) VALUES
  ('admin',     'admin@furever.test',     '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='admin')),
  ('worker',    'worker@furever.test',    '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='worker')),
  ('worker2',   'worker2@furever.test',   '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='worker')),
  ('volunteer', 'volunteer@furever.test', '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='volunteer')),
  ('volunteer2','volunteer2@furever.test','$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='volunteer')),
  ('adopter',   'adopter@furever.test',   '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='adopter')),
  ('adopter2',  'adopter2@furever.test',  '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='adopter')),
  ('adopter3',  'adopter3@furever.test',  '$2y$10$HpMOoXJLuw.0UI3aApnqZe/lSyQqJjwfsN4YbcNK2YjeIAE6i0VEe', (SELECT id FROM roles WHERE name='adopter'))
ON CONFLICT DO NOTHING;

-- User profiles ---------------------------------------------------------------
INSERT INTO user_profiles (user_id, full_name, phone, address, bio) VALUES
  ((SELECT id FROM users WHERE username='admin'),     'Anna Admin',      '+48 600 100 100', 'ul. Schroniska 1, Warszawa', 'Manages FurEver day-to-day.'),
  ((SELECT id FROM users WHERE username='worker'),    'Wojtek Worker',   '+48 600 200 200', 'ul. Łap 7, Warszawa',         'Senior animal carer.'),
  ((SELECT id FROM users WHERE username='worker2'),   'Wiola Worker',    '+48 600 200 201', 'ul. Łap 7, Warszawa',         'Adoption coordinator.'),
  ((SELECT id FROM users WHERE username='volunteer'), 'Vera Volunteer',  '+48 600 300 300', 'al. Niepodległości 22, Wwa',  'Walks dogs every weekend.'),
  ((SELECT id FROM users WHERE username='volunteer2'),'Viktor Volunteer','+48 600 300 301', 'ul. Łazienkowska 14, Wwa',    'Helps with social media.'),
  ((SELECT id FROM users WHERE username='adopter'),   'Adam Adopter',    '+48 600 400 400', 'ul. Mokotowska 5, Warszawa',  'Looking for a senior dog.'),
  ((SELECT id FROM users WHERE username='adopter2'),  'Alice Adopter',   '+48 600 400 401', 'ul. Targowa 3, Warszawa',     'Family of 4, has a garden.'),
  ((SELECT id FROM users WHERE username='adopter3'),  'Antoni Adopter',  '+48 600 400 402', 'ul. Marszałkowska 9, Wwa',    'First-time adopter.')
ON CONFLICT (user_id) DO NOTHING;

-- Species ---------------------------------------------------------------------
INSERT INTO species (name, icon) VALUES
  ('Dog',     'fa-dog'),
  ('Cat',     'fa-cat'),
  ('Rabbit',  'fa-carrot'),
  ('Bird',    'fa-dove'),
  ('Hamster', 'fa-paw'),
  ('Other',   'fa-paw')
ON CONFLICT (name) DO NOTHING;

-- Animals ---------------------------------------------------------------------
INSERT INTO animals (name, species_id, breed, gender, date_of_birth, intake_date, status, description, created_by) VALUES
  ('Luna',   (SELECT id FROM species WHERE name='Cat'),    'Domestic Shorthair', 'female', '2022-03-12', '2026-01-10', 'available', 'Curious and very affectionate. Loves window seats.',         (SELECT id FROM users WHERE username='worker')),
  ('Max',    (SELECT id FROM species WHERE name='Dog'),    'Labrador Mix',       'male',   '2020-08-04', '2025-11-22', 'available', 'Friendly with kids and other dogs. House-trained.',          (SELECT id FROM users WHERE username='worker')),
  ('Buddy',  (SELECT id FROM species WHERE name='Dog'),    'Golden Retriever',   'male',   '2019-05-21', '2025-09-15', 'adopted',   'Gentle giant — went home with the Kowalski family.',         (SELECT id FROM users WHERE username='worker')),
  ('Rocky',  (SELECT id FROM species WHERE name='Dog'),    'Boxer',              'male',   '2021-02-09', '2026-04-30', 'medical_hold', 'Recovering from a leg fracture. Friendly with staff.',  (SELECT id FROM users WHERE username='worker2')),
  ('Whiskers',(SELECT id FROM species WHERE name='Cat'),   'Maine Coon',         'female', '2018-07-15', '2026-02-18', 'available', 'Long, fluffy coat. Prefers a calm home.',                    (SELECT id FROM users WHERE username='worker')),
  ('Snowy',  (SELECT id FROM species WHERE name='Cat'),    'Persian',            'female', '2023-01-20', '2026-03-08', 'pending',   'Quiet, indoor cat. Already has interested applicants.',      (SELECT id FROM users WHERE username='worker')),
  ('Daisy',  (SELECT id FROM species WHERE name='Rabbit'), 'Holland Lop',        'female', '2024-04-17', '2026-04-12', 'available', 'Loves leafy greens and gentle scratches behind the ears.',   (SELECT id FROM users WHERE username='worker2')),
  ('Kiwi',   (SELECT id FROM species WHERE name='Bird'),   'Cockatiel',          'unknown','2022-11-30', '2026-04-25', 'available', 'Whistles tunes — picked up the FurEver theme already.',      (SELECT id FROM users WHERE username='worker')),
  ('Peanut', (SELECT id FROM species WHERE name='Hamster'),'Syrian',             'male',   '2025-06-12', '2026-04-28', 'available', 'Likes running on the wheel and stuffing his cheeks.',         (SELECT id FROM users WHERE username='worker2')),
  ('Bella',  (SELECT id FROM species WHERE name='Dog'),    'Beagle',             'female', '2017-10-02', '2026-02-01', 'available', 'Senior dog with a calm temperament. Looking for a quiet home.',(SELECT id FROM users WHERE username='worker')),
  ('Charlie',(SELECT id FROM species WHERE name='Cat'),    'Tabby',              'male',   '2024-12-05', '2026-04-19', 'pending',   'Playful kitten — full of energy and zoomies.',                (SELECT id FROM users WHERE username='worker')),
  ('Milo',   (SELECT id FROM species WHERE name='Dog'),    'Husky Mix',          'male',   '2022-09-09', '2026-03-21', 'adopted',   'High-energy dog — already adopted to a hiking family.',       (SELECT id FROM users WHERE username='worker2')),
  ('Olive',  (SELECT id FROM species WHERE name='Cat'),    'Russian Blue',       'female', '2023-06-22', '2026-04-14', 'available', 'Soft grey coat, quiet observer.',                              (SELECT id FROM users WHERE username='worker'));

-- Medical records (1:N) -------------------------------------------------------
INSERT INTO medical_records (animal_id, record_date, vet_name, diagnosis, treatment, notes) VALUES
  ((SELECT id FROM animals WHERE name='Luna'),    '2026-02-01', 'Dr. Nowak',  'Routine check-up',          'Vaccinations updated', 'Healthy, 4.2kg.'),
  ((SELECT id FROM animals WHERE name='Max'),     '2025-12-05', 'Dr. Kowal',  'Heartworm prevention',      'Monthly preventative', 'Active and energetic.'),
  ((SELECT id FROM animals WHERE name='Rocky'),   '2026-04-30', 'Dr. Nowak',  'Tibial fracture',           'Cast applied, 6-week rest', 'Re-x-ray in 3 weeks.'),
  ((SELECT id FROM animals WHERE name='Rocky'),   '2026-05-05', 'Dr. Nowak',  'Follow-up post-fracture',   'Pain management',     'Healing well.'),
  ((SELECT id FROM animals WHERE name='Bella'),   '2026-02-10', 'Dr. Kowal',  'Senior wellness exam',      'Joint supplement',    'Mild arthritis observed.'),
  ((SELECT id FROM animals WHERE name='Whiskers'),'2026-02-22', 'Dr. Nowak',  'Dental cleaning',           'Routine cleaning',    'No extractions needed.');

-- Adoption requests (1:N) -----------------------------------------------------
INSERT INTO adoption_requests (animal_id, applicant_id, status, message, submitted_at) VALUES
  ((SELECT id FROM animals WHERE name='Snowy'),    (SELECT id FROM users WHERE username='adopter'),  'pending',  'I have a quiet flat and would love a calm cat.',           NOW() - INTERVAL '2 days'),
  ((SELECT id FROM animals WHERE name='Snowy'),    (SELECT id FROM users WHERE username='adopter2'), 'pending',  'Looking for a Persian — would adore Snowy.',               NOW() - INTERVAL '1 days'),
  ((SELECT id FROM animals WHERE name='Charlie'),  (SELECT id FROM users WHERE username='adopter3'), 'pending',  'My kids have wanted a kitten for years!',                  NOW() - INTERVAL '3 days'),
  ((SELECT id FROM animals WHERE name='Max'),      (SELECT id FROM users WHERE username='adopter'),  'pending',  'I jog every morning and would love a running buddy.',      NOW() - INTERVAL '5 hours'),
  ((SELECT id FROM animals WHERE name='Bella'),    (SELECT id FROM users WHERE username='adopter2'), 'pending',  'Senior-dog focused household, plenty of soft bedding.',    NOW() - INTERVAL '8 hours'),
  ((SELECT id FROM animals WHERE name='Daisy'),    (SELECT id FROM users WHERE username='adopter3'), 'pending',  'We have a rabbit-safe garden enclosure.',                  NOW() - INTERVAL '4 days'),
  ((SELECT id FROM animals WHERE name='Olive'),    (SELECT id FROM users WHERE username='adopter'),  'pending',  'Would love a calm companion cat.',                         NOW() - INTERVAL '20 hours');

INSERT INTO adoption_requests (animal_id, applicant_id, status, message, submitted_at, reviewed_by, reviewed_at, decision_notes) VALUES
  ((SELECT id FROM animals WHERE name='Buddy'), (SELECT id FROM users WHERE username='adopter'),  'approved', 'Family with two children, fenced garden.',          NOW() - INTERVAL '14 days', (SELECT id FROM users WHERE username='worker'),  NOW() - INTERVAL '10 days', 'Excellent home check.'),
  ((SELECT id FROM animals WHERE name='Milo'),  (SELECT id FROM users WHERE username='adopter3'), 'approved', 'Active hikers, large yard, used to huskies.',       NOW() - INTERVAL '20 days', (SELECT id FROM users WHERE username='worker2'), NOW() - INTERVAL '17 days', 'Great fit.'),
  ((SELECT id FROM animals WHERE name='Buddy'), (SELECT id FROM users WHERE username='adopter2'), 'rejected', 'Small studio apartment.',                            NOW() - INTERVAL '14 days', (SELECT id FROM users WHERE username='worker'),  NOW() - INTERVAL '10 days', 'Buddy needs a larger space.');

-- Volunteer shifts (M:N) ------------------------------------------------------
-- Anchor on this Monday so the seed always lands in the current week.
WITH this_monday AS (
    SELECT (CURRENT_DATE - ((EXTRACT(ISODOW FROM CURRENT_DATE)::INT - 1) || ' days')::INTERVAL)::DATE AS d
)
INSERT INTO volunteer_shifts (shift_date, start_time, end_time, task_description, location, capacity, created_by)
SELECT d + offset_days, start_t, end_t, task, loc, cap, (SELECT id FROM users WHERE username='worker')
FROM this_monday, (VALUES
    (0, TIME '08:00', TIME '12:00', 'Morning dog walks',           'Main yard',     3),
    (0, TIME '13:00', TIME '15:00', 'Cat socialization',           'Cat room A',    2),
    (1, TIME '09:00', TIME '11:00', 'Kennel cleaning',             'Kennels',       4),
    (2, TIME '10:00', TIME '13:00', 'Adoption-event helpers',      'Reception',     2),
    (3, TIME '08:00', TIME '12:00', 'Morning dog walks',           'Main yard',     3),
    (4, TIME '14:00', TIME '17:00', 'Photography for new arrivals','Studio',        1),
    (5, TIME '09:00', TIME '12:00', 'Saturday open day',           'Reception',     5),
    (6, TIME '11:00', TIME '14:00', 'Sunday dog walks',            'Main yard',     3)
) AS s(offset_days, start_t, end_t, task, loc, cap);

-- Volunteer assignments -------------------------------------------------------
INSERT INTO volunteer_assignments (volunteer_id, shift_id, status)
SELECT (SELECT id FROM users WHERE username='volunteer'),  vs.id, 'signed_up'
  FROM volunteer_shifts vs
 WHERE vs.task_description IN ('Morning dog walks','Photography for new arrivals','Saturday open day')
 LIMIT 4;

INSERT INTO volunteer_assignments (volunteer_id, shift_id, status)
SELECT (SELECT id FROM users WHERE username='volunteer2'), vs.id, 'confirmed'
  FROM volunteer_shifts vs
 WHERE vs.task_description IN ('Cat socialization','Kennel cleaning','Sunday dog walks')
 LIMIT 4;

COMMIT;
