<?php
require_once __DIR__ . '/db.php';

function getTeamCalendarEvents(PDO $conn, int $organizationId, ?int $teamId = null, int $limit = 5): array
{
    $sql = 'SELECT id, title, description, event_type, start_datetime, end_datetime, location, team_id
            FROM events
            WHERE organization_id = :organization_id';
    $bindings = [':organization_id' => [$organizationId, PDO::PARAM_INT]];

    if ($teamId !== null) {
        $sql .= ' AND (team_id = :team_id OR team_id IS NULL)';
        $bindings[':team_id'] = [$teamId, PDO::PARAM_INT];
    }

    $sql .= ' ORDER BY start_datetime ASC, id DESC LIMIT ' . max(1, $limit);

    $statement = $conn->prepare($sql);

    foreach ($bindings as $parameter => [$value, $type]) {
        $statement->bindValue($parameter, $value, $type);
    }

    $statement->execute();

    return array_map(static function (array $event): array {
        $startDate = new DateTimeImmutable((string) $event['start_datetime']);
        $endDate = new DateTimeImmutable((string) $event['end_datetime']);

        return [
            'id' => (int) $event['id'],
            'title' => $event['title'],
            'description' => $event['description'],
            'event_type' => $event['event_type'],
            'start_label' => $startDate->format('d M · H:i'),
            'end_label' => $endDate->format('H:i'),
            'location' => $event['location'] ?: 'Sin ubicación',
            'team_id' => $event['team_id'] !== null ? (int) $event['team_id'] : null,
        ];
    }, $statement->fetchAll());
}

function getTeamScrimEvents(PDO $conn, int $organizationId, ?int $teamId = null, int $limit = 5): array
{
    $sql = 'SELECT m.id, m.opponent_name, m.opponent_tag, m.match_date, m.result, m.score_for, m.score_against, gm.name AS game_mode_name
            FROM matches m
            INNER JOIN game_modes gm ON gm.id = m.game_mode_id
            INNER JOIN teams t ON t.id = m.team_id
            WHERE t.organization_id = :organization_id AND m.match_type = "scrim"';
    $bindings = [':organization_id' => [$organizationId, PDO::PARAM_INT]];

    if ($teamId !== null) {
        $sql .= ' AND m.team_id = :team_id';
        $bindings[':team_id'] = [$teamId, PDO::PARAM_INT];
    }

    $sql .= ' ORDER BY m.match_date DESC, m.id DESC LIMIT ' . max(1, $limit);

    $statement = $conn->prepare($sql);

    foreach ($bindings as $parameter => [$value, $type]) {
        $statement->bindValue($parameter, $value, $type);
    }

    $statement->execute();

    return array_map(static function (array $scrim): array {
        $matchDate = new DateTimeImmutable((string) $scrim['match_date']);

        return [
            'id' => (int) $scrim['id'],
            'opponent_name' => $scrim['opponent_name'],
            'opponent_tag' => $scrim['opponent_tag'],
            'match_date_label' => $matchDate->format('d M · H:i'),
            'result' => (string) $scrim['result'],
            'score_label' => ((int) ($scrim['score_for'] ?? 0)) . ' - ' . ((int) ($scrim['score_against'] ?? 0)),
            'game_mode_name' => $scrim['game_mode_name'],
        ];
    }, $statement->fetchAll());
}