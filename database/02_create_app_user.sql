-- eTeam Manager
-- Create an application DB user with least-privilege permissions (MySQL/MariaDB).
-- IMPORTANT: Do NOT commit real passwords to git.
-- Replace the placeholder password before running.

-- Settings
SET @DB_NAME = 'eteam_manager';
SET @APP_USER = 'eteam_app';
SET @APP_HOST = 'localhost'; -- change to '%' only if you really need remote connections
SET @APP_PASSWORD = 'pV7!Qm2#Rk9@Ls4^Tx8$Nd3!Wa6Zc1';

-- Create user (dynamic SQL so variables work in identifiers)
SET @sql_create_user = CONCAT(
  "CREATE USER IF NOT EXISTS '", @APP_USER, "'@'", @APP_HOST, "' IDENTIFIED BY '", REPLACE(@APP_PASSWORD, "'", "\\'"), "';"
);
PREPARE stmt FROM @sql_create_user;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Minimal privileges for the web app runtime
SET @sql_grant = CONCAT(
  "GRANT SELECT, INSERT, UPDATE, DELETE ON `", @DB_NAME, "`.* TO '", @APP_USER, "'@'", @APP_HOST, "';"
);
PREPARE stmt FROM @sql_grant;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recommended: force a safe default for connections
-- If you plan to connect remotely, consider REQUIRE SSL and set up TLS.

FLUSH PRIVILEGES;
