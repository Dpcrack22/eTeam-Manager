-- eTeam Manager (DEV ONLY)
-- Seed data for MySQL/MariaDB focused on Valorant.
-- Idempotent: truncates tables and re-inserts a minimal realistic dataset.

USE eteam_manager;

SET @schema_name = DATABASE();

SET @users_terms_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'users'
    AND column_name = 'terms_accepted_at'
);

SET @sql_users_terms = IF(
  @users_terms_column_exists = 0,
  'ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL DEFAULT NULL AFTER is_active',
  'SELECT 1'
);
PREPARE stmt_users_terms FROM @sql_users_terms;
EXECUTE stmt_users_terms;
DEALLOCATE PREPARE stmt_users_terms;

SET @org_moderation_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'organization_members'
    AND column_name = 'moderation_status'
);

SET @sql_org_moderation = IF(
  @org_moderation_column_exists = 0,
  'ALTER TABLE organization_members
     ADD COLUMN moderation_status ENUM("active","suspended","banned") NOT NULL DEFAULT "active" AFTER role,
     ADD COLUMN moderation_reason TEXT NULL AFTER moderation_status,
     ADD COLUMN moderated_by BIGINT UNSIGNED NULL AFTER moderation_reason,
     ADD COLUMN moderated_at DATETIME NULL AFTER moderated_by,
     ADD COLUMN moderation_until DATETIME NULL AFTER moderated_at',
  'SELECT 1'
);
PREPARE stmt_org_moderation FROM @sql_org_moderation;
EXECUTE stmt_org_moderation;
DEALLOCATE PREPARE stmt_org_moderation;

SET FOREIGN_KEY_CHECKS = 0;

-- Child tables first (safe order)
TRUNCATE TABLE note_tag_relations;
TRUNCATE TABLE note_tags;
TRUNCATE TABLE notes;

TRUNCATE TABLE task_comments;
TRUNCATE TABLE tasks;
TRUNCATE TABLE board_columns;
TRUNCATE TABLE boards;

TRUNCATE TABLE event_participants;
TRUNCATE TABLE events;

TRUNCATE TABLE match_player_stats;
TRUNCATE TABLE match_maps;
TRUNCATE TABLE matches;

TRUNCATE TABLE user_game_accounts;
TRUNCATE TABLE team_members;
TRUNCATE TABLE team_invitations;
TRUNCATE TABLE teams;

TRUNCATE TABLE game_characters;
TRUNCATE TABLE game_maps;
TRUNCATE TABLE game_modes;
TRUNCATE TABLE games;

TRUNCATE TABLE organization_members;
TRUNCATE TABLE organizations;

TRUNCATE TABLE notifications;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1) Juegos (Valorant only)
-- ------------------------------------------------------------

INSERT INTO games (id, name, slug, developer, is_active, created_at)
VALUES (1, 'Valorant', 'valorant', 'Riot Games', 1, NOW());

INSERT INTO game_maps (id, game_id, name, is_active)
VALUES
  (1, 1, 'Bind', 1),
  (2, 1, 'Haven', 1),
  (3, 1, 'Split', 1);

INSERT INTO game_modes (id, game_id, name, is_ranked)
VALUES
  (1, 1, 'Competitivo', 1),
  (2, 1, 'No competitivo', 0);

INSERT INTO game_characters (id, game_id, name, role, is_active)
VALUES
  (1, 1, 'Jett', 'Duelist', 1),
  (2, 1, 'Sova', 'Initiator', 1),
  (3, 1, 'Omen', 'Controller', 1),
  (4, 1, 'Sage', 'Sentinel', 1),
  (5, 1, 'Raze', 'Duelist', 1);

-- ------------------------------------------------------------
-- 2) Usuarios + Organización
-- ------------------------------------------------------------

-- NOTE: all DEV users share the password `password123`.
-- The app validates passwords with SHA-256 in this project.
INSERT INTO users (
  id, username, email, password_hash, avatar_url, is_active, terms_accepted_at, created_at, updated_at, last_login_at
) VALUES
  (1, 'parallax_owner',  'owner@parallax.gg',  'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (2, 'parallax_coach',  'coach@parallax.gg',  'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (3, 'pv_ace',          'ace@parallax.gg',    'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (4, 'pv_lumen',        'lumen@parallax.gg',  'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (5, 'pv_nova',         'nova@parallax.gg',   'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (6, 'pv_kairo',        'kairo@parallax.gg',  'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (7, 'parallax_analyst','analyst@parallax.gg','ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (8, 'parallax_scout',  'scout@parallax.gg',  'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (9, 'parallax_mira',   'mira@parallax.gg',   'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW()),
  (10, 'guest_try',      'guest@parallax.gg',  'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', NULL, 1, NOW(), NOW(), NOW(), NOW());

INSERT INTO organizations (
  id, name, slug, logo_url, description, owner_id, created_at, updated_at
) VALUES (
  1,
  'Parallax Esports',
  'parallax',
  NULL,
  'Organización demo para pruebas internas (DEV).',
  1,
  NOW(),
  NOW()
);

INSERT INTO organization_members (id, organization_id, user_id, role, joined_at, is_active)
VALUES
  (1, 1, 1, 'owner',   NOW(), 1),
  (2, 1, 2, 'coach',   NOW(), 1),
  (3, 1, 3, 'player',  NOW(), 1),
  (4, 1, 4, 'player',  NOW(), 1),
  (5, 1, 5, 'player',  NOW(), 1),
  (6, 1, 6, 'player',  NOW(), 1),
  (7, 1, 7, 'analyst', NOW(), 1),
  (8, 1, 8, 'viewer',  NOW(), 1),
  (9, 1, 9, 'viewer',  NOW(), 1);

-- ------------------------------------------------------------
-- 3) Equipo Valorant + miembros
-- ------------------------------------------------------------

INSERT INTO teams (
  id, organization_id, game_id, name, tag, description, created_at, is_active
) VALUES (
  1,
  1,
  1,
  'Parallax V',
  'PV',
  'Roster principal de Valorant (DEV seed).',
  NOW(),
  1
);

-- Ensure all users are linked to the team
INSERT INTO team_members (id, team_id, user_id, role, joined_at, is_active)
VALUES
  (1, 1, 2, 'coach',      NOW(), 1),
  (2, 1, 3, 'player',     NOW(), 1),
  (3, 1, 4, 'player',     NOW(), 1),
  (4, 1, 5, 'player',     NOW(), 1),
  (5, 1, 6, 'player',     NOW(), 1),
  (6, 1, 7, 'analyst',    NOW(), 1),
  (7, 1, 1, 'substitute', NOW(), 1);

-- External accounts (Valorant)
INSERT INTO user_game_accounts (
  id, user_id, game_id, external_player_id, in_game_name, tag_line,
  rank_tier, rank_division, verified, last_sync_at, created_at
) VALUES
  (1, 1, 1, 'puuid-dev-owner-0001',  'ParallaxOwner', 'PV', 'Diamond', 'II', 1, NOW(), NOW()),
  (2, 2, 1, 'puuid-dev-coach-0002',  'CoachParallax', 'PV', 'Immortal', 'I',  1, NOW(), NOW()),
  (3, 3, 1, 'puuid-dev-ace-0003',    'Ace',          'PV', 'Ascendant','III',1, NOW(), NOW()),
  (4, 4, 1, 'puuid-dev-lumen-0004',  'Lumen',        'PV', 'Ascendant','I',  1, NOW(), NOW()),
  (5, 5, 1, 'puuid-dev-nova-0005',   'Nova',         'PV', 'Diamond',  'III',1, NOW(), NOW()),
  (6, 6, 1, 'puuid-dev-kairo-0006',  'Kairo',        'PV', 'Diamond',  'I',  1, NOW(), NOW()),
  (7, 7, 1, 'puuid-dev-analyst-0007','AnalystP',     'PV', 'Platinum', 'II', 1, NOW(), NOW()),
  (8, 8, 1, 'puuid-dev-scout-0008',  'Scout',        'PV', 'Gold',     'I',  1, NOW(), NOW()),
  (9, 9, 1, 'puuid-dev-mira-0009',   'Mira',         'PV', 'Platinum', 'III',1, NOW(), NOW());

-- ------------------------------------------------------------
-- 4) Invitaciones de prueba
-- ------------------------------------------------------------

INSERT INTO team_invitations (
  id, team_id, organization_id, invited_by, invited_user_id, invited_email, role, status, created_at, responded_at
) VALUES
  (1, 1, 1, 1, 8, 'scout@parallax.gg', 'player',  'pending', NOW(), NULL),
  (2, 1, 1, 2, 9, 'mira@parallax.gg',  'analyst', 'pending', NOW(), NULL);

-- ------------------------------------------------------------
-- 5) Partidos / Scrims
-- ------------------------------------------------------------

INSERT INTO matches (
  id, team_id, opponent_name, opponent_tag, match_type, game_mode_id, match_date,
  result, score_for, score_against, created_by, created_at
) VALUES
  (1, 1, 'Nebula Academy',  'NA', 'scrim', 1, (NOW() - INTERVAL 10 DAY), 'win',  26, 22, 2, NOW()),
  (2, 1, 'Orion Five',      'O5', 'scrim', 1, (NOW() - INTERVAL 6 DAY),  'loss', 18, 26, 2, NOW()),
  (3, 1, 'Vertex Collective','VX','scrim', 2, (NOW() - INTERVAL 2 DAY),  'win',  26, 20, 2, NOW());

-- Maps per match
INSERT INTO match_maps (id, match_id, map_id, score_for, score_against, order_index)
VALUES
  -- Match 1 (Bind + Haven)
  (1, 1, 1, 13, 10, 1),
  (2, 1, 2, 13, 12, 2),
  -- Match 2 (Split + Bind)
  (3, 2, 3,  8, 13, 1),
  (4, 2, 1, 10, 13, 2),
  -- Match 3 (Haven + Split)
  (5, 3, 2, 13,  9, 1),
  (6, 3, 3, 13, 11, 2);

-- Player stats (4 players x 3 matches)
INSERT INTO match_player_stats (id, match_id, user_id, kills, deaths, assists, score, custom_stats_json)
VALUES
  -- Match 1
  (1, 1, 3, 22, 16,  5,  310, NULL),
  (2, 1, 4, 18, 17,  7,  260, NULL),
  (3, 1, 5, 20, 14,  4,  295, NULL),
  (4, 1, 6, 16, 18,  9,  240, NULL),
  -- Match 2
  (5, 2, 3, 15, 20,  4,  210, NULL),
  (6, 2, 4, 17, 19,  6,  235, NULL),
  (7, 2, 5, 14, 21,  3,  205, NULL),
  (8, 2, 6, 12, 22,  8,  190, NULL),
  -- Match 3
  (9,  3, 3, 21, 15,  6,  300, NULL),
  (10, 3, 4, 19, 16,  7,  275, NULL),
  (11, 3, 5, 23, 14,  3,  320, NULL),
  (12, 3, 6, 17, 17, 10,  255, NULL);

-- ------------------------------------------------------------
-- 6) Boards y Tareas (Kanban)
-- ------------------------------------------------------------

INSERT INTO boards (id, team_id, name, created_at)
VALUES (1, 1, 'Kanban — Semana actual', NOW());

INSERT INTO board_columns (id, board_id, name, order_index)
VALUES
  (1, 1, 'Por hacer',    1),
  (2, 1, 'En progreso',  2),
  (3, 1, 'Hecho',        3);

INSERT INTO tasks (
  id, board_column_id, team_id, title, description, priority, assigned_to, due_date, status,
  created_by, created_at, updated_at
) VALUES
  (1, 1, 1, 'Preparar plan de scrim',
      'Definir objetivos del scrim y revisar composición y roles.',
      'high', 2, (NOW() + INTERVAL 2 DAY), 'Por hacer',
      1, NOW(), NOW()),
  (2, 2, 1, 'VOD review: Nebula Academy',
      'Revisar rounds clave y anotar errores recurrentes (mid-round).',
      'medium', 7, (NOW() + INTERVAL 1 DAY), 'En progreso',
      2, NOW(), NOW()),
  (3, 3, 1, 'Actualizar defaults en Bind',
      'Ajustar setup defensivo en Site A y timings de rotación.',
      'low', 4, (NOW() - INTERVAL 1 DAY), 'Hecho',
      2, NOW(), NOW());

-- ------------------------------------------------------------
-- 7) Eventos / Calendario
-- ------------------------------------------------------------

INSERT INTO events (
  id, organization_id, team_id, title, description, event_type,
  start_datetime, end_datetime, location, created_by, created_at
) VALUES
  (1, 1, 1, 'Entrenamiento — Aim + Protocolos',
      'Sesión de práctica centrada en aim routines y comunicación.',
      'practice',
      (NOW() + INTERVAL 1 DAY),
      (NOW() + INTERVAL 1 DAY + INTERVAL 2 HOUR),
      'Discord',
      2,
      NOW()),
  (2, 1, 1, 'Scrim vs Vertex Collective',
      'Bo2 para probar nuevas mid-round calls.',
      'scrim',
      (NOW() + INTERVAL 3 DAY),
      (NOW() + INTERVAL 3 DAY + INTERVAL 2 HOUR),
      'Custom lobby',
      2,
      NOW());

INSERT INTO event_participants (id, event_id, user_id, status)
VALUES
  -- Event 1
  (1, 1, 2, 'accepted'),
  (2, 1, 3, 'accepted'),
  (3, 1, 4, 'accepted'),
  (4, 1, 5, 'accepted'),
  (5, 1, 6, 'accepted'),
  (6, 1, 7, 'accepted'),
  -- Event 2
  (7, 2, 2, 'accepted'),
  (8, 2, 3, 'accepted'),
  (9, 2, 4, 'accepted'),
  (10,2, 5, 'accepted'),
  (11,2, 6, 'accepted'),
  (12,2, 7, 'accepted');

-- ------------------------------------------------------------
-- 8) Notas + tags
-- ------------------------------------------------------------

INSERT INTO notes (
  id, team_id, title, content, created_by, created_at, updated_at
) VALUES
  (1, 1, 'Plantilla de warmup (20 min)',
      '1) 5 min tracking\n2) 5 min flicks\n3) 10 min deathmatch (objetivo: crosshair placement)',
      2, NOW(), NOW()),
  (2, 1, 'Estrategia: Execute A en Bind',
      'Idea base: control de showers + smoke CT + satchel entry. Ajustes según utilidad enemiga.',
      7, NOW(), NOW()),
  (3, 1, 'Review: scrim vs Orion Five',
      'Puntos a mejorar: trade timings, control de mapa en early round y disciplina de utilidad.',
      7, NOW(), NOW());

INSERT INTO note_tags (id, name)
VALUES
  (1, 'estrategia'),
  (2, 'review'),
  (3, 'plantilla');

INSERT INTO note_tag_relations (note_id, tag_id)
VALUES
  (1, 3),
  (2, 1),
  (3, 2);

-- ------------------------------------------------------------
-- 9) Notificaciones (mínimo para demo)
-- ------------------------------------------------------------

INSERT INTO notifications (id, user_id, type, reference_id, message, is_read, created_at)
VALUES
  (1, 3, 'event', 1, 'Nuevo evento: Entrenamiento — Aim + Protocolos', 0, NOW()),
  (2, 2, 'task',  2, 'Tarea en progreso: VOD review: Nebula Academy',  0, NOW()),
  (3, 8, 'team_invite', 1, 'Tienes una invitación para unirte a Parallax V como player.', 0, NOW()),
  (4, 9, 'team_invite', 2, 'Tienes una invitación para unirte a Parallax V como analyst.', 0, NOW());
