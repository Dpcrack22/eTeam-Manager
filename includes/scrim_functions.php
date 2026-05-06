<?php
require_once __DIR__ . '/db.php';

function getScrimStatusOptions(): array
{
    return [
        ['key' => 'all', 'label' => 'Todos'],
        ['key' => 'pending', 'label' => 'Pendientes'],
        ['key' => 'win', 'label' => 'Victorias'],
        ['key' => 'loss', 'label' => 'Derrotas'],
        ['key' => 'draw', 'label' => 'Empates'],
    ];
}

function scrimResultBadgeClass(string $result): string
{
    return match ($result) {
        'win' => 'badge-success',
        'loss' => 'badge-error',
        'draw' => 'badge-warning',
        'pending' => 'badge-info',
        default => 'badge-info',
    };
}

function scrimResultLabel(string $result): string
{
    return match ($result) {
        'win' => 'Victoria',
        'loss' => 'Derrota',
        'draw' => 'Empate',
        'pending' => 'Pendiente',
        default => ucfirst($result),
    };
}

function getGameModesForGame(PDO $conn, int $gameId): array
{
    $statement = $conn->prepare(
        'SELECT id, name, is_ranked FROM game_modes WHERE game_id = :game_id ORDER BY is_ranked DESC, name ASC'
    );
    $statement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getGameMapsForGame(PDO $conn, int $gameId): array
{
    $statement = $conn->prepare(
        'SELECT id, name FROM game_maps WHERE game_id = :game_id AND is_active = 1 ORDER BY name ASC'
    );
    $statement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function getTeamScrims(PDO $conn, int $teamId, string $statusFilter = 'all', string $searchQuery = ''): array
{
    $sql = 'SELECT m.id, m.opponent_name, m.opponent_tag, m.match_type, m.game_mode_id, gm.name AS game_mode_name,
                   m.match_date, m.result, m.score_for, m.score_against,
                   COUNT(mm.id) AS maps_count
            FROM matches m
            INNER JOIN game_modes gm ON gm.id = m.game_mode_id
            LEFT JOIN match_maps mm ON mm.match_id = m.id
            WHERE m.team_id = :team_id AND m.match_type = \'scrim\'';

    $bindings = [':team_id' => [$teamId, PDO::PARAM_INT]];

    if ($statusFilter !== 'all') {
        $sql .= ' AND m.result = :result';
        $bindings[':result'] = [$statusFilter, PDO::PARAM_STR];
    }

    $searchQuery = trim($searchQuery);
    if ($searchQuery !== '') {
        $sql .= ' AND (m.opponent_name LIKE :search_query OR COALESCE(m.opponent_tag, \'\') LIKE :search_query OR gm.name LIKE :search_query)';
        $bindings[':search_query'] = ['%' . $searchQuery . '%', PDO::PARAM_STR];
    }

    $sql .= ' GROUP BY m.id, m.opponent_name, m.opponent_tag, m.match_type, m.game_mode_id, gm.name, m.match_date, m.result, m.score_for, m.score_against
              ORDER BY m.match_date DESC, m.id DESC';

    $statement = $conn->prepare($sql);

    foreach ($bindings as $parameter => [$value, $type]) {
        $statement->bindValue($parameter, $value, $type);
    }

    $statement->execute();

    $scrims = $statement->fetchAll();

    return array_map(static function (array $scrim): array {
        $scoreFor = $scrim['score_for'];
        $scoreAgainst = $scrim['score_against'];
        $matchDate = new DateTimeImmutable((string) $scrim['match_date']);

        return [
            'id' => (int) $scrim['id'],
            'opponent_name' => $scrim['opponent_name'],
            'opponent_tag' => $scrim['opponent_tag'],
            'game_mode_name' => $scrim['game_mode_name'],
            'match_date_label' => $matchDate->format('d M · H:i'),
            'result' => (string) $scrim['result'],
            'result_label' => scrimResultLabel((string) $scrim['result']),
            'result_badge' => scrimResultBadgeClass((string) $scrim['result']),
            'score_label' => (($scoreFor !== null ? (int) $scoreFor : 0) . ' - ' . ($scoreAgainst !== null ? (int) $scoreAgainst : 0)),
            'maps_count' => (int) $scrim['maps_count'],
        ];
    }, $scrims);
}

function getScrimById(PDO $conn, int $scrimId, int $teamId): array|false
{
    $statement = $conn->prepare(
        'SELECT m.id, m.team_id, m.opponent_name, m.opponent_tag, m.match_type, m.game_mode_id, gm.name AS game_mode_name,
                m.match_date, m.result, m.score_for, m.score_against, m.created_by, m.created_at
         FROM matches m
         INNER JOIN game_modes gm ON gm.id = m.game_mode_id
            WHERE m.id = :scrim_id AND m.team_id = :team_id AND m.match_type = \'scrim\'
         LIMIT 1'
    );
    $statement->bindValue(':scrim_id', $scrimId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
    $statement->execute();

    $scrim = $statement->fetch();

    if (!$scrim) {
        return false;
    }

    $matchDate = new DateTimeImmutable((string) $scrim['match_date']);

    return [
        'id' => (int) $scrim['id'],
        'team_id' => (int) $scrim['team_id'],
        'opponent_name' => $scrim['opponent_name'],
        'opponent_tag' => $scrim['opponent_tag'],
        'match_type' => $scrim['match_type'],
        'game_mode_id' => (int) $scrim['game_mode_id'],
        'game_mode_name' => $scrim['game_mode_name'],
        'match_date' => $matchDate->format('Y-m-d\TH:i'),
        'match_date_label' => $matchDate->format('d M Y · H:i'),
        'result' => (string) $scrim['result'],
        'result_label' => scrimResultLabel((string) $scrim['result']),
        'result_badge' => scrimResultBadgeClass((string) $scrim['result']),
        'score_for' => $scrim['score_for'] !== null ? (int) $scrim['score_for'] : null,
        'score_against' => $scrim['score_against'] !== null ? (int) $scrim['score_against'] : null,
        'created_by' => (int) $scrim['created_by'],
        'created_at' => $scrim['created_at'],
    ];
}

function getScrimMaps(PDO $conn, int $scrimId): array
{
    $statement = $conn->prepare(
        'SELECT mm.id, mm.map_id, gm.name AS map_name, mm.score_for, mm.score_against, mm.order_index
         FROM match_maps mm
         INNER JOIN game_maps gm ON gm.id = mm.map_id
         WHERE mm.match_id = :match_id
         ORDER BY mm.order_index ASC, mm.id ASC'
    );
    $statement->bindValue(':match_id', $scrimId, PDO::PARAM_INT);
    $statement->execute();

    return array_map(static function (array $map): array {
        return [
            'id' => (int) $map['id'],
            'map_id' => (int) $map['map_id'],
            'map_name' => $map['map_name'],
            'score_for' => $map['score_for'] !== null ? (int) $map['score_for'] : null,
            'score_against' => $map['score_against'] !== null ? (int) $map['score_against'] : null,
            'order_index' => (int) $map['order_index'],
        ];
    }, $statement->fetchAll());
}

function normalizeScrimMapRows(array $mapRows): array
{
    $normalizedRows = [];

    foreach ($mapRows as $mapRow) {
        if (!is_array($mapRow)) {
            continue;
        }

        $mapId = (int) ($mapRow['map_id'] ?? 0);
        if ($mapId <= 0) {
            continue;
        }

        $orderIndex = (int) ($mapRow['order_index'] ?? (count($normalizedRows) + 1));
        $scoreFor = trim((string) ($mapRow['score_for'] ?? ''));
        $scoreAgainst = trim((string) ($mapRow['score_against'] ?? ''));

        $normalizedRows[] = [
            'map_id' => $mapId,
            'order_index' => $orderIndex > 0 ? $orderIndex : count($normalizedRows) + 1,
            'score_for' => $scoreFor === '' ? null : (int) $scoreFor,
            'score_against' => $scoreAgainst === '' ? null : (int) $scoreAgainst,
        ];
    }

    usort($normalizedRows, static function (array $left, array $right): int {
        return $left['order_index'] <=> $right['order_index'];
    });

    return $normalizedRows;
}

function syncScrimMaps(PDO $conn, int $scrimId, array $mapRows): void
{
    $deleteStatement = $conn->prepare('DELETE FROM match_maps WHERE match_id = :match_id');
    $deleteStatement->bindValue(':match_id', $scrimId, PDO::PARAM_INT);
    $deleteStatement->execute();

    $insertStatement = $conn->prepare(
        'INSERT INTO match_maps (match_id, map_id, score_for, score_against, order_index)
         VALUES (:match_id, :map_id, :score_for, :score_against, :order_index)'
    );

    foreach (normalizeScrimMapRows($mapRows) as $mapRow) {
        $insertStatement->bindValue(':match_id', $scrimId, PDO::PARAM_INT);
        $insertStatement->bindValue(':map_id', $mapRow['map_id'], PDO::PARAM_INT);
        if ($mapRow['score_for'] === null) {
            $insertStatement->bindValue(':score_for', null, PDO::PARAM_NULL);
        } else {
            $insertStatement->bindValue(':score_for', $mapRow['score_for'], PDO::PARAM_INT);
        }

        if ($mapRow['score_against'] === null) {
            $insertStatement->bindValue(':score_against', null, PDO::PARAM_NULL);
        } else {
            $insertStatement->bindValue(':score_against', $mapRow['score_against'], PDO::PARAM_INT);
        }

        $insertStatement->bindValue(':order_index', $mapRow['order_index'], PDO::PARAM_INT);
        $insertStatement->execute();
    }
}

function createScrim(PDO $conn, int $teamId, int $gameModeId, string $opponentName, ?string $opponentTag, string $matchDate, string $result, ?int $scoreFor, ?int $scoreAgainst, int $createdBy, array $mapRows = []): int
{
    $conn->beginTransaction();

    try {
        $statement = $conn->prepare(
              'INSERT INTO matches (team_id, opponent_name, opponent_tag, match_type, game_mode_id, match_date, result, score_for, score_against, created_by, created_at)
               VALUES (:team_id, :opponent_name, :opponent_tag, \'scrim\', :game_mode_id, :match_date, :result, :score_for, :score_against, :created_by, NOW())'
        );
        $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $statement->bindValue(':opponent_name', $opponentName, PDO::PARAM_STR);
        $statement->bindValue(':opponent_tag', $opponentTag, $opponentTag === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':game_mode_id', $gameModeId, PDO::PARAM_INT);
        $statement->bindValue(':match_date', $matchDate, PDO::PARAM_STR);
        $statement->bindValue(':result', $result, PDO::PARAM_STR);
        $statement->bindValue(':score_for', $scoreFor, $scoreFor === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':score_against', $scoreAgainst, $scoreAgainst === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $statement->execute();

        $scrimId = (int) $conn->lastInsertId();
        syncScrimMaps($conn, $scrimId, $mapRows);
        $conn->commit();

        return $scrimId;
    } catch (Throwable $throwable) {
        $conn->rollBack();
        throw $throwable;
    }
}

function updateScrim(PDO $conn, int $scrimId, int $teamId, int $gameModeId, string $opponentName, ?string $opponentTag, string $matchDate, string $result, ?int $scoreFor, ?int $scoreAgainst, array $mapRows = []): bool
{
    $conn->beginTransaction();

    try {
        $statement = $conn->prepare(
            'UPDATE matches
             SET opponent_name = :opponent_name,
                 opponent_tag = :opponent_tag,
                 game_mode_id = :game_mode_id,
                 match_date = :match_date,
                 result = :result,
                 score_for = :score_for,
                 score_against = :score_against
               WHERE id = :scrim_id AND team_id = :team_id AND match_type = \'scrim\''
        );
        $statement->bindValue(':opponent_name', $opponentName, PDO::PARAM_STR);
        $statement->bindValue(':opponent_tag', $opponentTag, $opponentTag === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':game_mode_id', $gameModeId, PDO::PARAM_INT);
        $statement->bindValue(':match_date', $matchDate, PDO::PARAM_STR);
        $statement->bindValue(':result', $result, PDO::PARAM_STR);
        $statement->bindValue(':score_for', $scoreFor, $scoreFor === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':score_against', $scoreAgainst, $scoreAgainst === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':scrim_id', $scrimId, PDO::PARAM_INT);
        $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);
        $statement->execute();

        syncScrimMaps($conn, $scrimId, $mapRows);
        $conn->commit();

        return true;
    } catch (Throwable $throwable) {
        $conn->rollBack();
        throw $throwable;
    }
}

function deleteScrim(PDO $conn, int $scrimId, int $teamId): bool
{
    $statement = $conn->prepare('DELETE FROM matches WHERE id = :scrim_id AND team_id = :team_id AND match_type = \'scrim\'');
    $statement->bindValue(':scrim_id', $scrimId, PDO::PARAM_INT);
    $statement->bindValue(':team_id', $teamId, PDO::PARAM_INT);

    return $statement->execute();
}