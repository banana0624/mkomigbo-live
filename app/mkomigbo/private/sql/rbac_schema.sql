-- /private/sql/rbac_schema.sql
-- RBAC schema (application roles + capabilities)
-- Engine: InnoDB, Charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- Staff users (application accounts)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(190) NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at   DATETIME NULL DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_users_email (email),
  KEY idx_staff_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Roles
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  is_system   TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_name (name),
  KEY idx_roles_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Capabilities (permissions)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS capabilities (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_capabilities_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Role -> Capability mapping
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_capabilities (
  role_id        INT UNSIGNED NOT NULL,
  capability_id  INT UNSIGNED NOT NULL,
  granted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, capability_id),
  KEY idx_role_caps_capability (capability_id),
  CONSTRAINT fk_role_caps_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_role_caps_capability
    FOREIGN KEY (capability_id) REFERENCES capabilities(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Staff user -> Role assignment
-- (Matches your authz.php: staff_user_roles sur)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_user_roles (
  staff_user_id  INT UNSIGNED NOT NULL,
  role_id        INT UNSIGNED NOT NULL,
  assigned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (staff_user_id, role_id),
  KEY idx_staff_user_roles_role (role_id),
  CONSTRAINT fk_staff_user_roles_user
    FOREIGN KEY (staff_user_id) REFERENCES staff_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_staff_user_roles_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
