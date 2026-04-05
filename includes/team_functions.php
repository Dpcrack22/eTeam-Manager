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

function setActiveTeamContext(PDO $conn, int $organizationId, int $teamId): array
{
    $team = getTeamById($conn, $teamId, $organizationId);

    if (!$team) {
        return ['success' => false, 'error' => 'No tienes acceso a ese equipo'];
    }

    $_SESSION['active_team_id'] = (int) $team['id'];
    $_SESSION['user']['team_id'] = (int) $team['id'];
    $_SESSION['user']['team'] = $team['name'];

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