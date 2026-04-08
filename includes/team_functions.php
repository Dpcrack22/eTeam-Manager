<?php
require_once __DIR__ . '/db.php';

function getGames(PDO $conn): array
{
    $statement = $conn->query('SELECT id, name, slug FROM games WHERE is_active = 1 ORDER BY name ASC');
    return $statement->fetchAll();
}

function getOrganizationTeams(PDO $conn, int $organizationId): array
{
    $statement = $conn->prepare(
        'SELECT t.id, t.name, t.tag, t.description, t.game_id, g.name AS game_name,
                COUNT(CASE WHEN tm.is_active = 1 THEN tm.id END) AS members_count
         FROM teams t
         INNER JOIN games g ON g.id = t.game_id
         LEFT JOIN team_members tm ON tm.team_id = t.id
         WHERE t.organization_id = :organization_id AND t.is_active = 1
         GROUP BY t.id, t.name, t.tag, t.description, t.game_id, g.name
         ORDER BY t.created_at DESC, t.name ASC'
    );
    $statement->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
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
    $sql = 'SELECT t.id, t.name, t.tag, t.description, t.game_id, t.organization_id, g.name AS game_name
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
    $statement = $conn->prepare(
        'INSERT INTO teams (organization_id, game_id, name, tag, description, created_at, is_active)
         VALUES (:organization_id, :game_id, :name, :tag, :description, NOW(), 1)'
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
    $statement->execute();

    return (int) $conn->lastInsertId();
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

    return $statement->execute();
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

    return $statement->execute();
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
    if (!empty($_SESSION['active_team_id'])) {
        $activeTeamId = (int) $_SESSION['active_team_id'];
        if (getTeamById($conn, $activeTeamId, $organizationId)) {
            return $activeTeamId;
        }
    }

    if (!empty($_SESSION['user']['team_id'])) {
        $activeTeamId = (int) $_SESSION['user']['team_id'];
        if (getTeamById($conn, $activeTeamId, $organizationId)) {
            $_SESSION['active_team_id'] = $activeTeamId;
            return $activeTeamId;
        }
    }

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

    return ['success' => true, 'team' => $team];
}