-- eTeam Manager
-- Database schema (MySQL 8+ / MariaDB 10.4+)
-- Charset/collation chosen for broad compatibility.

-- Create database
CREATE DATABASE IF NOT EXISTS eteam_manager
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE eteam_manager;

-- CORE SYSTEM

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar_url VARCHAR(2048) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  logo_url VARCHAR(2048) NULL,
  description TEXT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_organizations_slug (slug),
  KEY idx_organizations_owner_id (owner_id),
  CONSTRAINT fk_organizations_owner
    FOREIGN KEY (owner_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','admin','manager','coach','analyst','player','viewer') NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_org_members_org_user (organization_id, user_id),
  KEY idx_org_members_user_id (user_id),
  CONSTRAINT fk_org_members_organization
    FOREIGN KEY (organization_id) REFERENCES organizations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_org_members_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MULTI-GAME SYSTEM

CREATE TABLE IF NOT EXISTS games (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  developer VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_games_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_modes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_ranked TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_game_modes_game_id (game_id),
  CONSTRAINT fk_game_modes_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_maps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_game_maps_game_id (game_id),
  CONSTRAINT fk_game_maps_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_characters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  role VARCHAR(50) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_game_characters_game_id (game_id),
  CONSTRAINT fk_game_characters_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TEAMS

CREATE TABLE IF NOT EXISTS teams (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  tag VARCHAR(16) NULL,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teams_org_name_game (organization_id, name, game_id),
  KEY idx_teams_game_id (game_id),
  CONSTRAINT fk_teams_organization
    FOREIGN KEY (organization_id) REFERENCES organizations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_teams_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('coach','player','analyst','substitute') NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_members_team_user (team_id, user_id),
  KEY idx_team_members_user_id (user_id),
  CONSTRAINT fk_team_members_team
    FOREIGN KEY (team_id) REFERENCES teams (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_team_members_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EXTERNAL GAME ACCOUNTS

CREATE TABLE IF NOT EXISTS user_game_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  external_player_id VARCHAR(128) NOT NULL,
  in_game_name VARCHAR(64) NOT NULL,
  tag_line VARCHAR(16) NULL,
  rank_tier VARCHAR(32) NULL,
  rank_division VARCHAR(32) NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  last_sync_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_game_accounts_user_game (user_id, game_id),
  KEY idx_user_game_accounts_game_id (game_id),
  CONSTRAINT fk_user_game_accounts_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_user_game_accounts_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MATCHES & STATS

CREATE TABLE IF NOT EXISTS matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  opponent_name VARCHAR(120) NOT NULL,
  opponent_tag VARCHAR(16) NULL,
  match_type ENUM('scrim','tournament','league','friendly') NOT NULL,
  game_mode_id BIGINT UNSIGNED NOT NULL,
  match_date DATETIME NOT NULL,
  result ENUM('win','loss','draw','pending') NOT NULL DEFAULT 'pending',
  score_for INT NULL,
  score_against INT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matches_team_id (team_id),
  KEY idx_matches_game_mode_id (game_mode_id),
  KEY idx_matches_created_by (created_by),
  CONSTRAINT fk_matches_team
    FOREIGN KEY (team_id) REFERENCES teams (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_matches_game_mode
    FOREIGN KEY (game_mode_id) REFERENCES game_modes (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_matches_created_by
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_maps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  map_id BIGINT UNSIGNED NOT NULL,
  score_for INT NULL,
  score_against INT NULL,
  order_index INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_match_maps_match_id (match_id),
  KEY idx_match_maps_map_id (map_id),
  CONSTRAINT fk_match_maps_match
    FOREIGN KEY (match_id) REFERENCES matches (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_match_maps_map
    FOREIGN KEY (map_id) REFERENCES game_maps (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_player_stats (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  kills INT NULL,
  deaths INT NULL,
  assists INT NULL,
  score INT NULL,
  custom_stats_json JSON NULL,
  PRIMARY KEY (id),
  KEY idx_match_player_stats_match_id (match_id),
  KEY idx_match_player_stats_user_id (user_id),
  CONSTRAINT fk_match_player_stats_match
    FOREIGN KEY (match_id) REFERENCES matches (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_match_player_stats_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EVENTS & CALENDAR

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  event_type ENUM('scrim','practice','tournament','meeting','review') NOT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  location VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_organization_id (organization_id),
  KEY idx_events_team_id (team_id),
  KEY idx_events_created_by (created_by),
  CONSTRAINT fk_events_organization
    FOREIGN KEY (organization_id) REFERENCES organizations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_events_team
    FOREIGN KEY (team_id) REFERENCES teams (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_events_created_by
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('invited','accepted','declined') NOT NULL,
  PRIMARY KEY (id),
  KEY idx_event_participants_event_id (event_id),
  KEY idx_event_participants_user_id (user_id),
  CONSTRAINT fk_event_participants_event
    FOREIGN KEY (event_id) REFERENCES events (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_event_participants_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TASK MANAGEMENT (KANBAN)

CREATE TABLE IF NOT EXISTS boards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_boards_team_id (team_id),
  CONSTRAINT fk_boards_team
    FOREIGN KEY (team_id) REFERENCES teams (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS board_columns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  order_index INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_board_columns_board_id (board_id),
  CONSTRAINT fk_board_columns_board
    FOREIGN KEY (board_id) REFERENCES boards (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_column_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL,
  assigned_to BIGINT UNSIGNED NULL,
  due_date DATETIME NULL,
  status VARCHAR(32) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_board_column_id (board_column_id),
  KEY idx_tasks_team_id (team_id),
  KEY idx_tasks_assigned_to (assigned_to),
  KEY idx_tasks_created_by (created_by),
  CONSTRAINT fk_tasks_board_column
    FOREIGN KEY (board_column_id) REFERENCES board_columns (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_tasks_team
    FOREIGN KEY (team_id) REFERENCES teams (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_tasks_assigned_to
    FOREIGN KEY (assigned_to) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_tasks_created_by
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_comments_task_id (task_id),
  KEY idx_task_comments_user_id (user_id),
  CONSTRAINT fk_task_comments_task
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_task_comments_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTES & DOCUMENTATION

CREATE TABLE IF NOT EXISTS notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  content TEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notes_team_id (team_id),
  KEY idx_notes_created_by (created_by),
  CONSTRAINT fk_notes_team
    FOREIGN KEY (team_id) REFERENCES teams (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_notes_created_by
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_tag_relations (
  note_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (note_id, tag_id),
  KEY idx_note_tag_relations_tag_id (tag_id),
  CONSTRAINT fk_note_tag_relations_note
    FOREIGN KEY (note_id) REFERENCES notes (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_note_tag_relations_tag
    FOREIGN KEY (tag_id) REFERENCES note_tags (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTIFICATIONS

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  reference_id BIGINT UNSIGNED NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_id (user_id),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
