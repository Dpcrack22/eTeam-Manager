<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/organization_functions.php';
require_once __DIR__ . '/notification_functions.php';

function ensureTeamInviteTokenStorage(PDO $conn): void
{
    try {
        $statement = $conn->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = "teams" AND column_name = "invite_token"'
        );
        $statement->execute();

        if ((int) ($statement->fetch()['total'] ?? 0) > 0) {
            return;
        }

        $conn->exec(
            'ALTER TABLE teams
             ADD COLUMN invite_token VARCHAR(128) NULL AFTER description,
             ADD COLUMN invite_token_created_at DATETIME NULL AFTER invite_token,
             ADD UNIQUE KEY uq_teams_invite_token (invite_token)'
        );
    } catch (Throwable $exception) {
        // Older databases can still work without invite links.
    }
}

function generateTeamInviteToken(): string
{
    return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}

function ensureTeamInviteToken(PDO $conn, int $teamId): string
{
    ensureTeamInviteTokenStorage($conn);

    $statement = $conn->prepare('SELECT invite_token FROM teams WHERE id = :team_id LIMIT 1');
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch();

    if (!$row) {
        return '';
    }

    if (!empty($row['invite_token'])) {
        return (string) $row['invite_token'];
    }

    $token = generateTeamInviteToken();
    $update = $conn->prepare('UPDATE teams SET invite_token = :invite_token, invite_token_created_at = NOW() WHERE id = :team_id');
    $update->bindValue(':invite_token', $token, PDO::PARAM_STR);
    $update->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $update->execute();

    return $token;
}

function getTeamInviteLink(PDO $conn, int $teamId): string
{
    $token = ensureTeamInviteToken($conn, $teamId);
    if ($token === '') {
        return '';
    }

    $baseUrl = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    return rtrim($baseUrl, '/') . '/invite.php?token=' . urlencode($token);
}

function getTeamByInviteToken(PDO $conn, string $inviteToken): array|false
{
    $statement = $conn->prepare(
        'SELECT t.id, t.name, t.tag, t.description, t.game_id, t.organization_id, t.invite_token, g.name AS game_name
         FROM teams t
         INNER JOIN games g ON g.id = t.game_id
         WHERE t.invite_token = :invite_token AND t.is_active = 1
         LIMIT 1'
    );
    $statement->bindValue(':invite_token', $inviteToken, PDO::PARAM_STR);
    $statement->execute();

    return $statement->fetch() ?: false;
}

ensureTeamInviteTokenStorage($conn);

function getGames(PDO $conn): array
{
    $statement = $conn->query('SELECT id, name, slug FROM games WHERE is_active = 1 ORDER BY name ASC');
    return $statement->fetchAll();
}

function getOrganizationTeams(PDO $conn, int $organizationId): array
{
    $statement = $conn->prepare(
        'SELECT t.id, t.name, t.tag, t.description, t.game_id, t.invite_token, g.name AS game_name,
                COUNT(CASE WHEN tm.is_active = 1 THEN tm.id END) AS members_count
         FROM teams t
         INNER JOIN games g ON g.id = t.game_id
         LEFT JOIN team_members tm ON tm.team_id = t.id
         WHERE t.organization_id = :organization_id AND t.is_active = 1
         GROUP BY t.id, t.name, t.tag, t.description, t.game_id, t.invite_token, g.name
         ORDER BY t.created_at DESC, t.name ASC'
    );
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function getUserOrganizationTeams(PDO $conn, int $organizationId, int $userId): array
{
    $statement = $conn->prepare(
        'SELECT t.id, t.name, t.tag, t.description, t.game_id, t.invite_token, g.name AS game_name,
                COUNT(CASE WHEN tm.is_active = 1 THEN tm.id END) AS members_count
         FROM teams t
         INNER JOIN games g ON g.id = t.game_id
         INNER JOIN team_members my ON my.team_id = t.id AND my.user_id = :user_id AND my.is_active = 1
         LEFT JOIN team_members tm ON tm.team_id = t.id
         WHERE t.organization_id = :organization_id AND t.is_active = 1
         GROUP BY t.id, t.name, t.tag, t.description, t.game_id, t.invite_token, g.name
         ORDER BY my.joined_at DESC, t.created_at DESC, t.name ASC'
    );
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function teamExistsByNameAndGame(PDO $conn, int $organizationId, string $name, int $gameId, ?int $ignoreTeamId = null): bool
{
    $sql = 'SELECT id FROM teams WHERE organization_id = :organization_id AND game_id = :game_id AND name = :name';
    if ($ignoreTeamId !== null) {
        $sql .= ' AND id <> :ignore_team_id';
    }
    $sql .= ' LIMIT 1';

    $statement = $conn->prepare($sql);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    if ($ignoreTeamId !== null) {
        $statement->bindValue(':ignore_team_id', $ignoreTeamId, PDO::PARAM_INT);
    }
    $statement->execute();

    return (bool) $statement->fetch();
}

function getTeamById(PDO $conn, int $teamId, int $organizationId = 0): array|false
{
    $sql = 'SELECT t.id, t.name, t.tag, t.description, t.game_id, t.organization_id, t.invite_token, g.name AS game_name
            FROM teams t
            INNER JOIN games g ON g.id = t.game_id
            WHERE t.id = :team_id';

    if ($organizationId > 0) {
        $sql .= ' AND t.organization_id = :organization_id';
    }

    $sql .= ' LIMIT 1';

    $statement = $conn->prepare($sql);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    if ($organizationId > 0) {
        $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetch() ?: false;
}

function createTeam(PDO $conn, int $organizationId, int $gameId, string $name, ?string $tag = null, ?string $description = null): int
{
    ensureTeamInviteTokenStorage($conn);

    $statement = $conn->prepare(
        'INSERT INTO teams (organization_id, game_id, name, tag, description, invite_token, invite_token_created_at, created_at, is_active)
         VALUES (:organization_id, :game_id, :name, :tag, :description, :invite_token, NOW(), NOW(), 1)'
    );
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    if ($tag === null) {
        $statement->bindValue(':tag', null, PDO::PARAM_NULL);
    } else {
        $statement->bindValue(':tag', $tag, PDO::PARAM_STR);
    }

    if ($description === null) {
        $statement->bindValue(':description', null, PDO::PARAM_NULL);
    } else {
        $statement->bindValue(':description', $description, PDO::PARAM_STR);
    }
    $statement->bindValue(':invite_token', generateTeamInviteToken(), PDO::PARAM_STR);
    $statement->execute();

    $teamId = (int) $conn->lastInsertId();
    ensureTeamInviteToken($conn, $teamId);

    return $teamId;
}

function updateTeam(PDO $conn, int $teamId, int $organizationId, int $gameId, string $name, ?string $tag = null, ?string $description = null): bool
{
    if ($tag !== null) {
        $tag = trim($tag);
        if ($tag === '') {
            $tag = null;
        }
    }

    if ($description !== null) {
        $description = trim($description);
        if ($description === '') {
            $description = null;
        }
    }

    $statement = $conn->prepare(
        'UPDATE teams
         SET game_id = :game_id, name = :name, tag = :tag, description = :description
         WHERE id = :team_id AND organization_id = :organization_id'
    );
    $statement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->bindValue(':tag', $tag, $tag === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);

    $updated = $statement->execute();

    if ($updated) {
        ensureTeamInviteToken($conn, $teamId);
    }

    return $updated;
}

function deleteTeam(PDO $conn, int $teamId, int $organizationId): bool
{
    try {
        $conn->beginTransaction();

        $statement = $conn->prepare(
            'UPDATE teams
             SET is_active = 0
             WHERE id = :team_id AND organization_id = :organization_id AND is_active = 1'
        );
        $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() === 0) {
            $conn->rollBack();
            return false;
        }

        $cancelInvites = $conn->prepare(
            'UPDATE team_invitations
             SET status = "cancelled", responded_at = NOW()
             WHERE team_id = :team_id AND status = "pending"'
        );
        $cancelInvites->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $cancelInvites->execute();

        $deactivateMembers = $conn->prepare('UPDATE team_members SET is_active = 0 WHERE team_id = :team_id');
        $deactivateMembers->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $deactivateMembers->execute();

        $conn->commit();
        return true;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        return false;
    }
}

function getTeamMembers(PDO $conn, int $teamId): array
{
    $statement = $conn->prepare(
        'SELECT tm.id, tm.user_id, tm.role, tm.joined_at, tm.is_active, u.username, u.email, u.avatar_url
         FROM team_members tm
         INNER JOIN users u ON u.id = tm.user_id
         WHERE tm.team_id = :team_id AND tm.is_active = 1
         ORDER BY FIELD(tm.role, "coach", "player", "analyst", "substitute"), u.username ASC'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function addOrUpdateTeamMemberByEmail(PDO $conn, int $teamId, string $email, string $role): array
{
    $userStatement = $conn->prepare('SELECT id, username FROM users WHERE email = :email LIMIT 1');
    $userStatement->bindValue(':email', $email, PDO::PARAM_STR);
    $userStatement->execute();
    $user = $userStatement->fetch();

    if (!$user) {
        return [
            'success' => false,
            'error' => 'No existe ningún usuario con ese email',
        ];
    }

    $memberStatement = $conn->prepare('SELECT id, is_active FROM team_members WHERE team_id = :team_id AND user_id = :user_id LIMIT 1');
    $memberStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $memberStatement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
    $memberStatement->execute();
    $member = $memberStatement->fetch();

    if ($member) {
        $updateStatement = $conn->prepare(
            'UPDATE team_members SET role = :role, is_active = 1 WHERE team_id = :team_id AND user_id = :user_id'
        );
        $updateStatement->bindValue(':role', $role, PDO::PARAM_STR);
        $updateStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $updateStatement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
        $updateStatement->execute();
    } else {
        $insertStatement = $conn->prepare(
            'INSERT INTO team_members (team_id, user_id, role, joined_at, is_active)
             VALUES (:team_id, :user_id, :role, NOW(), 1)'
        );
        $insertStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $insertStatement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
        $insertStatement->bindValue(':role', $role, PDO::PARAM_STR);
        $insertStatement->execute();
    }

    return [
        'success' => true,
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
    ];
}

function updateTeamMemberRole(PDO $conn, int $teamId, int $userId, string $role): bool
{
    $statement = $conn->prepare(
        'UPDATE team_members SET role = :role, is_active = 1 WHERE team_id = :team_id AND user_id = :user_id'
    );
    $statement->bindValue(':role', $role, PDO::PARAM_STR);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

    return $statement->execute();
}

function removeTeamMember(PDO $conn, int $teamId, int $userId): bool
{
    $statement = $conn->prepare(
        'UPDATE team_members SET is_active = 0 WHERE team_id = :team_id AND user_id = :user_id'
    );
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

    $result = $statement->execute();

    // If the affected user is the current session user and the team was active in session,
    // clear the active team context so the UI no longer shows the team.
    if ($result && !empty($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] === $userId) {
        if (!empty($_SESSION['active_team_id']) && (int) $_SESSION['active_team_id'] === $teamId) {
            unset($_SESSION['active_team_id']);
        }
        if (!empty($_SESSION['user']['team_id']) && (int) $_SESSION['user']['team_id'] === $teamId) {
            unset($_SESSION['user']['team_id']);
            unset($_SESSION['user']['team']);
        }
    }

    return $result;
}

function setActiveTeamContext(PDO $conn, int $organizationId, int $teamId): array
{
    $team = getTeamById($conn, $teamId, $organizationId);

    if (!$team) {
        return ['success' => false, 'error' => 'No tienes acceso a ese equipo'];
    }

    // set active team
    $_SESSION['active_team_id'] = (int) $team['id'];
    $_SESSION['user']['team_id'] = (int) $team['id'];
    $_SESSION['user']['team'] = $team['name'];

    // also set active organization context when available so UI shows team info
    if (!empty($team['organization_id'])) {
        $_SESSION['active_organization_id'] = (int) $team['organization_id'];
        $_SESSION['user']['organization_id'] = (int) $team['organization_id'];
        // organization name is not loaded here; app.php will resolve it on next request
    }

    return ['success' => true, 'team' => $team];
}

function getActiveTeamId(PDO $conn, int $organizationId): ?int
{
    $sessionUserId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;

    // Helper to validate a team belongs to the organization and (optionally) that the user is an active member
    $validateTeam = function (int $teamId) use ($conn, $organizationId, $sessionUserId): bool {
        $stmt = $conn->prepare('SELECT id FROM teams WHERE id = :team_id AND organization_id = :organization_id AND is_active = 1 LIMIT 1');
        $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $stmt->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $stmt->execute();
        $team = $stmt->fetch();
        if (!$team) {
            return false;
        }

        if ($sessionUserId !== null) {
            $m = $conn->prepare('SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id AND is_active = 1 LIMIT 1');
            $m->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            $m->bindValue(':user_id', $sessionUserId, PDO::PARAM_INT);
            $m->execute();
            return (bool) $m->fetch();
        }

        return true;
    };

    if (!empty($_SESSION['active_team_id'])) {
        $activeTeamId = (int) $_SESSION['active_team_id'];
        if ($validateTeam($activeTeamId)) {
            return $activeTeamId;
        }
        // invalid session reference -> clear it
        unset($_SESSION['active_team_id']);
    }

    if (!empty($_SESSION['user']['team_id'])) {
        $activeTeamId = (int) $_SESSION['user']['team_id'];
        if ($validateTeam($activeTeamId)) {
            $_SESSION['active_team_id'] = $activeTeamId;
            return $activeTeamId;
        }
        unset($_SESSION['user']['team_id'], $_SESSION['user']['team']);
    }

    // If there's a logged user, find a team in this organization that the user is an active member of
    if ($sessionUserId !== null) {
        $stmt = $conn->prepare(
            'SELECT t.id FROM teams t
             INNER JOIN team_members tm ON tm.team_id = t.id
             WHERE t.organization_id = :organization_id AND t.is_active = 1 AND tm.user_id = :user_id AND tm.is_active = 1
             ORDER BY tm.joined_at DESC, t.id DESC LIMIT 1'
        );
        $stmt->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $sessionUserId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row) {
            $_SESSION['active_team_id'] = (int) $row['id'];
            return (int) $row['id'];
        }

        return null;
    }

    // Fallback for anonymous (no session user): keep previous behaviour
    $statement = $conn->prepare(
        'SELECT id FROM teams WHERE organization_id = :organization_id AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1'
    );
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetch();

    return $row ? (int) $row['id'] : null;
}

function getAllActiveTeams(PDO $conn): array
{
    $statement = $conn->query(
        'SELECT t.id, t.name, t.tag, t.description, t.game_id, g.name AS game_name, t.organization_id, o.name AS organization_name,
                COUNT(CASE WHEN tm.is_active = 1 THEN tm.id END) AS members_count
         FROM teams t
         INNER JOIN games g ON g.id = t.game_id
         LEFT JOIN organizations o ON o.id = t.organization_id
         LEFT JOIN team_members tm ON tm.team_id = t.id
         WHERE t.is_active = 1
         GROUP BY t.id, t.name, t.tag, t.description, t.game_id, g.name, t.organization_id, o.name
         ORDER BY t.created_at DESC, t.name ASC'
    );

    return $statement->fetchAll();
}

function joinTeam(PDO $conn, int $teamId, int $userId, string $role = 'player'): array
{
    $team = getTeamById($conn, $teamId);

    if (!$team) {
        return ['success' => false, 'error' => 'El equipo no existe'];
    }

    $role = strtolower(trim($role));
    $allowedRoles = ['coach', 'player', 'analyst', 'substitute'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'player';
    }

    $organizationId = (int) ($team['organization_id'] ?? 0);

    if ($organizationId > 0) {
        $orgMemberStatement = $conn->prepare(
            'SELECT moderation_status, is_active
             FROM organization_members
             WHERE organization_id = :organization_id AND user_id = :user_id
             LIMIT 1'
        );
        $orgMemberStatement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
        $orgMemberStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $orgMemberStatement->execute();
        $orgMember = $orgMemberStatement->fetch();

        if ($orgMember) {
            if ((string) ($orgMember['moderation_status'] ?? 'active') !== 'active') {
                return ['success' => false, 'error' => 'No tienes acceso a esta organización'];
            }

            if ((int) ($orgMember['is_active'] ?? 0) !== 1) {
                $reactivateOrgMember = $conn->prepare(
                    'UPDATE organization_members
                     SET is_active = 1
                     WHERE organization_id = :organization_id AND user_id = :user_id'
                );
                $reactivateOrgMember->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
                $reactivateOrgMember->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $reactivateOrgMember->execute();
            }
        } else {
            $orgRole = $role;
            if ($orgRole === 'substitute') {
                $orgRole = 'player';
            }

            $insertOrgMember = $conn->prepare(
                'INSERT INTO organization_members (organization_id, user_id, role, moderation_status, joined_at, is_active)
                 VALUES (:organization_id, :user_id, :role, "active", NOW(), 1)'
            );
            $insertOrgMember->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
            $insertOrgMember->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insertOrgMember->bindValue(':role', $orgRole, PDO::PARAM_STR);
            $insertOrgMember->execute();
        }
    }

    $memberStatement = $conn->prepare('SELECT id, is_active FROM team_members WHERE team_id = :team_id AND user_id = :user_id LIMIT 1');
    $memberStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $memberStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $memberStatement->execute();
    $member = $memberStatement->fetch();

    if ($member) {
        $update = $conn->prepare('UPDATE team_members SET is_active = 1, role = :role WHERE id = :id');
        $update->bindValue(':role', $role, PDO::PARAM_STR);
        $update->bindValue(':id', (int) $member['id'], PDO::PARAM_INT);
        $update->execute();
    } else {
        $insert = $conn->prepare('INSERT INTO team_members (team_id, user_id, role, joined_at, is_active) VALUES (:team_id, :user_id, :role, NOW(), 1)');
        $insert->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $insert->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $insert->bindValue(':role', $role, PDO::PARAM_STR);
        $insert->execute();
    }

    $teamOwnerStatement = $conn->prepare('SELECT organization_id, name FROM teams WHERE id = :team_id LIMIT 1');
    $teamOwnerStatement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $teamOwnerStatement->execute();
    $teamContext = $teamOwnerStatement->fetch() ?: null;

    if ($teamContext) {
        $organizationMembers = getOrganizationMembers($conn, (int) $teamContext['organization_id']);

        foreach ($organizationMembers as $organizationMember) {
            if ((int) $organizationMember['user_id'] === $userId) {
                continue;
            }

            if (!in_array((string) $organizationMember['role'], ['owner', 'admin', 'manager'], true)) {
                continue;
            }

            createNotification(
                $conn,
                (int) $organizationMember['user_id'],
                'team_join',
                $teamId,
                'Un usuario se ha unido al equipo ' . $teamContext['name'] . '.'
            );
        }
    }

    createNotification($conn, $userId, 'team_join', $teamId, 'Te has unido al equipo ' . $team['name'] . '.');

    return ['success' => true, 'team' => $team];
}

function unjoinTeam(PDO $conn, int $teamId, int $userId) {
    $team = getTeamById($conn, $teamId);

    if (!$team) {
        return ['success' => false, 'error' => 'El equipo no existe'];
    }

    $update = $conn->prepare("UPDATE team_members SET is_active = 0 WHERE team_id = :team_id AND user_id = :user_id");
    $update->bindValue(":team_id", $teamId, PDO::PARAM_INT);
    $update->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $update->execute();

    if ($update->rowCount() === 0) {
        return ['success' => false, 'error' => 'El usuario no pertenece a este equipo'];
    }

    // If the user who left is the current session user and that team was active, clear session context
    if (!empty($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] === $userId) {
        if (!empty($_SESSION['active_team_id']) && (int) $_SESSION['active_team_id'] === $teamId) {
            unset($_SESSION['active_team_id']);
        }
        if (!empty($_SESSION['user']['team_id']) && (int) $_SESSION['user']['team_id'] === $teamId) {
            unset($_SESSION['user']['team_id']);
            unset($_SESSION['user']['team']);
        }
    }

    createNotification($conn, $userId, 'team_leave', $teamId, 'Has salido del equipo ' . $team['name'] . '.');

    return ["success" => true];

}

function isUserActiveMember(PDO $conn, int $teamId, int $userId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id AND is_active = 1 LIMIT 1');
    $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return (bool) $stmt->fetch();
}