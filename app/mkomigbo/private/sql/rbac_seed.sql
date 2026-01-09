-- /private/sql/rbac_seed.sql
-- Seed baseline roles + capabilities + mappings

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- Roles
-- ---------------------------------------------------------
INSERT IGNORE INTO roles (name, description, is_system) VALUES
  ('owner',  'Full control (everything)', 1),
  ('admin',  'Admin operations (manage core content)', 1),
  ('editor', 'Content editing (subjects/pages)', 1),
  ('auditor','Read-only tools/diagnostics access', 1);

-- ---------------------------------------------------------
-- Capabilities
-- ---------------------------------------------------------
INSERT IGNORE INTO capabilities (name, description) VALUES
  ('subject.manage',      'Create/edit/order/publish subjects'),
  ('page.manage',         'Create/edit/publish pages'),
  ('contributor.manage',  'Manage contributor profiles/credits'),
  ('platform.manage',     'Manage platform pages/feature sections'),
  ('tools.view',          'Access diagnostics/security tools'),
  ('rbac.manage',         'Manage roles/capabilities and assignments');

-- ---------------------------------------------------------
-- Role -> Capabilities (mapped by names)
-- ---------------------------------------------------------

-- OWNER gets everything
INSERT IGNORE INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c
WHERE r.name = 'owner';

-- ADMIN gets core management + tools (no RBAC management by default)
INSERT IGNORE INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c
WHERE r.name = 'admin'
  AND c.name IN ('subject.manage','page.manage','contributor.manage','platform.manage','tools.view');

-- EDITOR gets subjects + pages
INSERT IGNORE INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c
WHERE r.name = 'editor'
  AND c.name IN ('subject.manage','page.manage');

-- AUDITOR gets tools only
INSERT IGNORE INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c
WHERE r.name = 'auditor'
  AND c.name IN ('tools.view');

-- ---------------------------------------------------------
-- OPTIONAL: create a first staff user + assign role
-- (Leave commented; you must supply your real email + bcrypt hash)
-- ---------------------------------------------------------
-- INSERT IGNORE INTO staff_users (email, password_hash, display_name, is_active)
-- VALUES ('you@example.com', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH', 'Site Admin', 1);
--
-- INSERT IGNORE INTO staff_user_roles (staff_user_id, role_id)
-- SELECT u.id, r.id
-- FROM staff_users u
-- JOIN roles r
-- WHERE u.email='you@example.com' AND r.name='admin';
